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
			'https://customer-one.example.org/site/' => 'https://customer-one.staging.example.test/Preview/site/',
			'https://deep.site.example.org/site/'    => 'https://deep.site.staging.example.test/Preview/site/',
			'https:\/\/customer-one.example.org\/asset.jpg' => 'https:\/\/customer-one.staging.example.test\/Preview\/asset.jpg',
			'https%3a%2f%2fcustomer-one.example.org%2fapi%2Fitem' => 'https%3A%2F%2Fcustomer-one.staging.example.test%2FPreview%2fapi%2Fitem',
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
			'https://customer.example.org.evil/site/',
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
	 * Root-domain rules preserve subdomains and allow specific child overrides.
	 */
	public function test_domain_suffix_rules_preserve_subdomains_and_child_precedence() {

		$mapping_filter = static function () {

			return [
				'https://example.com'      => 'https://staging.example.com',
				'https://vip.example.com'  => 'https://preview.example.com',
				'https://example.net/root' => 'https://staging.example.net/long-path',
				'https://vip.example.net'  => 'https://vip.staging.example.net',
			];
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
			$this->assertSame('https://staging.example.com', self::$rewriter->rewrite_string('https://example.com'));
			$this->assertSame(
				'https://customer-one.staging.example.com/page',
				self::$rewriter->rewrite_string('https://customer-one.example.com/page')
			);
			$this->assertSame(
				'https://deep.site.staging.example.com/page',
				self::$rewriter->rewrite_string('https://deep.site.example.com/page')
			);
			$this->assertSame(
				'https://preview.example.com/page',
				self::$rewriter->rewrite_string('https://vip.example.com/page')
			);
			$this->assertSame(
				'https://customer.preview.example.com/page',
				self::$rewriter->rewrite_string('https://customer.vip.example.com/page')
			);
			$this->assertSame(
				'https://preview.example.com/page',
				self::$rewriter->rewrite_string('https://preview.example.com/page')
			);
			$this->assertSame(
				'https://customer-one.staging.example.com/page',
				self::$rewriter->rewrite_string('https://customer-one.staging.example.com/page')
			);

			$_SERVER['HTTP_HOST']   = 'vip.staging.example.net';
			$_SERVER['REQUEST_URI'] = '/long-path';

			try {
				$find_request_mapping = $reflection->getMethod('find_request_mapping');
				$selected             = $find_request_mapping->invoke(self::$rewriter);

				$this->assertSame('vip.example.net', $selected['source']['host']);
				$this->assertSame('', $selected['source']['base_path']);
			} finally {
				$_SERVER['HTTP_HOST']   = 'staging.example.test';
				$_SERVER['REQUEST_URI'] = '/Preview/';
			}
		} finally {
			$property->setValue(self::$rewriter, $original);
		}
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
	 * A target subdomain and port resolve against the canonical site hostname.
	 */
	public function test_resolves_canonical_site_from_target_subdomain() {

		$source_domain = 'runtime-' . wp_rand(100000, 999999) . '.example.org';
		$target_domain = str_replace('.example.org', '.staging.example.test', $source_domain);
		$blog_id       = self::factory()->blog->create(
			[
				'domain' => $source_domain,
				'path'   => '/',
			]
		);

		$this->assertNotWPError($blog_id);

		$mapping_filter = static function () {

			return ['https://example.org:8443' => 'https://staging.example.test:9443/Preview'];
		};

		add_filter('wu_runtime_url_rewriter_mappings', $mapping_filter);

		$reflection = new \ReflectionClass(Runtime_URL_Rewriter::class);
		$method     = $reflection->getMethod('get_configured_mappings');
		$mappings   = $method->invoke(self::$rewriter);

		remove_filter('wu_runtime_url_rewriter_mappings', $mapping_filter);

		$previous_host     = 'staging.example.test';
		$previous_uri      = '/Preview/';
		$mappings_property = $reflection->getProperty('mappings');
		$active_property   = $reflection->getProperty('active_mapping');
		$original_mappings = $mappings_property->getValue(self::$rewriter);
		$original_active   = $active_property->getValue(self::$rewriter);

		$mappings_property->setValue(self::$rewriter, $mappings);
		$_SERVER['HTTP_HOST']   = $target_domain . ':9443';
		$_SERVER['REQUEST_URI'] = '/Preview/';

		try {
			$method  = $reflection->getMethod('find_request_mapping');
			$mapping = $method->invoke(self::$rewriter);

			$this->assertSame($source_domain . ':8443', $mapping['source']['authority']);
			$this->assertSame($target_domain . ':9443', $mapping['target']['authority']);

			$active_property->setValue(self::$rewriter, $mapping);
			$site = self::$rewriter->resolve_site(null, $target_domain, '/Preview/');

			$this->assertInstanceOf(\WP_Site::class, $site);
			$this->assertSame($source_domain, $site->domain);
			$this->assertContains($target_domain, self::$rewriter->allow_target_hosts([]));
		} finally {
			$mappings_property->setValue(self::$rewriter, $original_mappings);
			$active_property->setValue(self::$rewriter, $original_active);
			$_SERVER['HTTP_HOST']   = $previous_host;
			$_SERVER['REQUEST_URI'] = $previous_uri;
		}
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
