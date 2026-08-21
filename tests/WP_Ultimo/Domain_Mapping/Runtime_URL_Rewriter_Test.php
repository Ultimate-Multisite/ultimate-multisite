<?php

namespace WP_Ultimo\Domain_Mapping;

/**
 * Tests for the runtime-only environment URL rewriter.
 */
class Runtime_URL_Rewriter_Test extends \WP_UnitTestCase {

	/**
	 * Rewriter shared by the test methods.
	 *
	 * @var Runtime_URL_Rewriter
	 */
	private static $rewriter;

	/**
	 * Previous request host.
	 *
	 * @var string|null
	 */
	private static $previous_http_host;

	/**
	 * Previous request URI.
	 *
	 * @var string|null
	 */
	private static $previous_request_uri;

	/**
	 * Load one deterministic mapping before the singleton is initialized.
	 */
	public static function set_up_before_class() {

		parent::set_up_before_class();

		self::$previous_http_host   = isset($_SERVER['HTTP_HOST'])
			? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST']))
			: null;
		self::$previous_request_uri = isset($_SERVER['REQUEST_URI'])
			? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI']))
			: null;

		$_SERVER['HTTP_HOST']   = 'staging.example.test';
		$_SERVER['REQUEST_URI'] = '/Preview/';

		$mapping_filter = static function () {

			return [
				'https://example.org'                  => 'https://staging.example.test/Preview',
				'https://user:pass@invalid.example'    => 'https://ignored.example.test',
				'https://invalid-query.example/?key=1' => 'https://ignored-query.example.test',
				'https://invalid-target.example'       => 'https://ignored-target.example.test/#fragment',
			];
		};

		add_filter('wu_runtime_url_rewriter_mappings', $mapping_filter);

		require_once dirname(__DIR__, 3) . '/inc/domain-mapping/class-runtime-url-rewriter.php';

		self::$rewriter = Runtime_URL_Rewriter::get_instance();

		remove_filter('wu_runtime_url_rewriter_mappings', $mapping_filter);
	}

	/**
	 * Restore request state and early hooks.
	 */
	public static function tear_down_after_class() {

		remove_filter('pre_get_site_by_path', [self::$rewriter, 'resolve_site'], 1);
		remove_filter('pre_get_network_by_path', [self::$rewriter, 'resolve_network'], 1);
		remove_action('ms_loaded', [self::$rewriter, 'register_rewrite_filters'], 999);

		if (null === self::$previous_http_host) {
			unset($_SERVER['HTTP_HOST']);
		} else {
			$_SERVER['HTTP_HOST'] = self::$previous_http_host;
		}

		if (null === self::$previous_request_uri) {
			unset($_SERVER['REQUEST_URI']);
		} else {
			$_SERVER['REQUEST_URI'] = self::$previous_request_uri;
		}

		parent::tear_down_after_class();
	}

	/**
	 * Invalid URL components are rejected during normalization.
	 */
	public function test_only_valid_mapping_is_loaded() {

		$mappings = self::$rewriter->get_mappings();

		$this->assertCount(1, $mappings);
		$this->assertSame('example.org', $mappings[0]['source']['authority']);
		$this->assertSame('staging.example.test', $mappings[0]['target']['host']);
		$this->assertSame('/Preview', $mappings[0]['target']['base_path']);
	}

	/**
	 * The active request registers both early multisite routing filters.
	 */
	public function test_early_routing_filters_are_registered() {

		$mapping_filter = static function () {

			return ['https://example.org' => 'https://staging.example.test/Preview'];
		};

		add_filter('wu_runtime_url_rewriter_mappings', $mapping_filter);

		self::$rewriter->init();

		remove_filter('wu_runtime_url_rewriter_mappings', $mapping_filter);

		$this->assertSame(1, has_filter('pre_get_site_by_path', [self::$rewriter, 'resolve_site']));
		$this->assertSame(1, has_filter('pre_get_network_by_path', [self::$rewriter, 'resolve_network']));
	}

	/**
	 * Rewrite supported absolute, relative, escaped, and encoded URL forms.
	 */
	public function test_rewrites_supported_url_forms() {

		$cases = [
			'https://EXAMPLE.ORG/site/'              => 'https://staging.example.test/Preview/site/',
			'http://example.org/site/'               => 'https://staging.example.test/Preview/site/',
			'//example.org/site/'                    => '//staging.example.test/Preview/site/',
			'https:\/\/example.org\/asset.jpg'       => 'https:\/\/staging.example.test\/Preview\/asset.jpg',
			'https%3a%2f%2fEXAMPLE.ORG%2fapi%2Fitem' => 'https%3A%2F%2Fstaging.example.test%2FPreview%2fapi%2Fitem',
		];

		foreach ($cases as $input => $expected) {
			$this->assertSame($expected, self::$rewriter->rewrite_string($input), $input);
		}
	}

	/**
	 * Avoid partial authorities, ports, case-mismatched paths, and bare domains.
	 */
	public function test_does_not_rewrite_unsafe_partial_matches() {

		$unchanged = [
			'https://example.org:8443/site/',
			'https://example.org.evil/site/',
			'https://example.orgish/site/',
			'admin@example.org',
		];

		foreach ($unchanged as $value) {
			$this->assertSame($value, self::$rewriter->rewrite_string($value), $value);
		}
	}

	/**
	 * Paths remain case-sensitive while authorities remain case-insensitive.
	 */
	public function test_source_path_matching_is_case_sensitive() {

		$mapping_filter = static function () {

			return ['https://example.org/Store' => 'https://staging.example.test/Shop'];
		};

		add_filter('wu_runtime_url_rewriter_mappings', $mapping_filter);

		$reflection = new \ReflectionClass(Runtime_URL_Rewriter::class);
		$method     = $reflection->getMethod('get_configured_mappings');
		$mappings   = $method->invoke(self::$rewriter);

		remove_filter('wu_runtime_url_rewriter_mappings', $mapping_filter);

		$property = $reflection->getProperty('mappings');
		$original = $property->getValue(self::$rewriter);

		$property->setValue(self::$rewriter, $mappings);

		try {
			$this->assertSame(
				'https://staging.example.test/Shop/item',
				self::$rewriter->rewrite_string('https://EXAMPLE.ORG/Store/item')
			);
			$this->assertSame(
				'https://example.org/store/item',
				self::$rewriter->rewrite_string('https://example.org/store/item')
			);
		} finally {
			$property->setValue(self::$rewriter, $original);
		}
	}

	/**
	 * Recursive and upload-directory rewriting leaves filesystem paths alone.
	 */
	public function test_rewrites_nested_values_and_upload_urls() {

		$value = [
			'url'    => 'https://example.org/file.jpg',
			'nested' => ['https://example.org/page/'],
		];

		$this->assertSame(
			[
				'url'    => 'https://staging.example.test/Preview/file.jpg',
				'nested' => ['https://staging.example.test/Preview/page/'],
			],
			self::$rewriter->rewrite_value($value)
		);

		$uploads = self::$rewriter->rewrite_upload_directory(
			[
				'path'    => '/var/www/uploads',
				'basedir' => '/var/www/uploads',
				'url'     => 'https://example.org/uploads/file.jpg',
				'baseurl' => 'https://example.org/uploads',
			]
		);

		$this->assertSame('/var/www/uploads', $uploads['path']);
		$this->assertSame('/var/www/uploads', $uploads['basedir']);
		$this->assertSame('https://staging.example.test/Preview/uploads/file.jpg', $uploads['url']);
		$this->assertSame('https://staging.example.test/Preview/uploads', $uploads['baseurl']);
	}

	/**
	 * Large mapping sets can be loaded from JSON with optional inline overrides.
	 */
	public function test_loads_large_mapping_set_from_json_file() {

		$file = wp_tempnam('runtime-url-map.json');
		$this->assertIsString($file);

		$file_mappings = [];

		for ($index = 1; $index <= 250; $index++) {
			$file_mappings["https://customer-{$index}.example"] = "https://customer-{$index}.staging.example.test";
		}

		// Direct file and environment operations intentionally exercise pre-WordPress configuration.
		// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv, WordPress.WP.AlternativeFunctions.unlink_unlink
		$this->assertNotFalse(file_put_contents($file, wp_json_encode($file_mappings)));

		$previous_file   = getenv('WP_ULTIMO_RUNTIME_URL_MAP_FILE');
		$previous_inline = getenv('WP_ULTIMO_RUNTIME_URL_MAP');

		putenv('WP_ULTIMO_RUNTIME_URL_MAP_FILE=' . $file);
		putenv('WP_ULTIMO_RUNTIME_URL_MAP={"https://customer-42.example":"https://override.staging.example.test"}');

		try {
			$reflection = new \ReflectionClass(Runtime_URL_Rewriter::class);
			$method     = $reflection->getMethod('get_configured_mappings');
			$mappings   = $method->invoke(self::$rewriter);
		} finally {
			false === $previous_file
				? putenv('WP_ULTIMO_RUNTIME_URL_MAP_FILE')
				: putenv('WP_ULTIMO_RUNTIME_URL_MAP_FILE=' . $previous_file);
			false === $previous_inline
				? putenv('WP_ULTIMO_RUNTIME_URL_MAP')
				: putenv('WP_ULTIMO_RUNTIME_URL_MAP=' . $previous_inline);
			unlink($file);
		}
		// phpcs:enable

		$targets_by_source = [];

		foreach ($mappings as $mapping) {
			$targets_by_source[ $mapping['source']['authority'] ] = $mapping['target']['authority'];
		}

		$this->assertCount(250, $mappings);
		$this->assertSame(
			'override.staging.example.test',
			$targets_by_source['customer-42.example']
		);
		$this->assertSame(
			'customer-250.staging.example.test',
			$targets_by_source['customer-250.example']
		);
	}

	/**
	 * Resolve the environment root against the canonical multisite records.
	 */
	public function test_resolves_canonical_site_and_network() {

		$site    = self::$rewriter->resolve_site(null, 'staging.example.test', '/Preview/');
		$network = self::$rewriter->resolve_network(null, 'staging.example.test', '/Preview/');

		$this->assertInstanceOf(\WP_Site::class, $site);
		$this->assertSame('example.org', $site->domain);
		$this->assertSame('/', $site->path);
		$this->assertInstanceOf(\WP_Network::class, $network);
		$this->assertSame('example.org', $network->domain);
		$this->assertSame('/', $network->path);
	}

	/**
	 * REST payloads and redirect hosts are rewritten consistently.
	 */
	public function test_rewrites_rest_response_and_allows_target_host() {

		$response = new \WP_REST_Response(['url' => 'https://example.org/api/item']);
		$result   = self::$rewriter->rewrite_rest_response($response, null, null);

		$this->assertSame(
			['url' => 'https://staging.example.test/Preview/api/item'],
			$result->get_data()
		);
		$this->assertSame(
			['existing.example', 'staging.example.test'],
			self::$rewriter->allow_target_hosts(['existing.example'])
		);
	}
}
