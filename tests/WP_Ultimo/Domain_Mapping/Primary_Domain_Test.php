<?php

namespace WP_Ultimo\Domain_Mapping;

/**
 * Tests for the Primary_Domain class.
 */
class Primary_Domain_Test extends \WP_UnitTestCase {

	/**
	 * Test class exists.
	 */
	public function test_class_exists() {

		$this->assertTrue(class_exists(Primary_Domain::class));
	}

	/**
	 * Test singleton returns same instance.
	 */
	public function test_singleton() {

		$instance1 = Primary_Domain::get_instance();
		$instance2 = Primary_Domain::get_instance();

		$this->assertSame($instance1, $instance2);
	}

	/**
	 * Test allow_mapped_domain_redirect_hosts returns hosts array unchanged
	 * when wu_get_domains is not available.
	 */
	public function test_allow_mapped_domain_redirect_hosts_no_function() {

		$instance = Primary_Domain::get_instance();

		// When wu_get_domains does not exist the method should return the
		// original hosts array unmodified.
		if (function_exists('wu_get_domains')) {
			$this->markTestSkipped('wu_get_domains exists; skipping no-function path.');
		}

		$hosts  = ['example.com'];
		$result = $instance->allow_mapped_domain_redirect_hosts($hosts);

		$this->assertSame($hosts, $result);
	}

	/**
	 * Test allow_mapped_domain_redirect_hosts adds mapped domains to hosts.
	 */
	public function test_allow_mapped_domain_redirect_hosts_adds_domains() {

		if ( ! function_exists('wu_get_domains')) {
			$this->markTestSkipped('wu_get_domains not available.');
		}

		$instance = Primary_Domain::get_instance();

		// Capture the hosts list produced by the filter.
		$result = $instance->allow_mapped_domain_redirect_hosts([]);

		// Result must be an array (may be empty if no domains are mapped in
		// the test environment, but must not be false/null).
		$this->assertIsArray($result);
	}

	/**
	 * Test that the allowed_redirect_hosts filter is registered.
	 */
	public function test_allowed_redirect_hosts_filter_registered() {

		$instance = Primary_Domain::get_instance();
		$instance->init();

		$priority = has_filter(
			'allowed_redirect_hosts',
			[$instance, 'allow_mapped_domain_redirect_hosts']
		);

		// has_filter returns the priority (int) when registered, false otherwise.
		$this->assertNotFalse($priority);
	}

	/**
	 * Test force_network does not redirect when the primary domain is the site's
	 * own network subdomain.
	 */
	public function test_force_network_skips_redirect_when_primary_domain_is_network_subdomain() {

		$site_domain = 'network-loop-' . uniqid() . '.example.org';
		$blog_id     = self::factory()->blog->create(
			[
				'domain' => $site_domain,
				'path'   => '/',
			]
		);

		$this->assertNotWPError($blog_id);

		$this->create_primary_domain_mapping($blog_id, $site_domain);

		$previous_setting = wu_get_setting('force_admin_redirect', 'both');
		$previous_server  = $_SERVER;
		$previous_get     = $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Test restores superglobal state.
		$previous_request = $_REQUEST; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Test restores superglobal state.

		$redirect_filter = static function ($location) {
			throw new \RuntimeException(esc_url_raw((string) $location));
		};

		switch_to_blog($blog_id);
		add_filter('wp_redirect', $redirect_filter);

		try {
			wu_save_setting('force_admin_redirect', 'force_network');

			$_GET     = [];
			$_REQUEST = [];

			$_SERVER['REQUEST_METHOD'] = 'GET';
			$_SERVER['HTTP_HOST']      = $site_domain;
			$_SERVER['REQUEST_URI']    = '/wp-admin/';

			try {
				Primary_Domain::get_instance()->maybe_redirect_to_mapped_or_network_domain();
			} catch (\RuntimeException $exception) {
				$this->fail('Unexpected redirect to ' . $exception->getMessage());
			}
		} finally {
			remove_filter('wp_redirect', $redirect_filter);
			restore_current_blog();

			wu_save_setting('force_admin_redirect', $previous_setting);

			$_SERVER  = $previous_server;
			$_GET     = $previous_get;
			$_REQUEST = $previous_request;
		}
	}

	/**
	 * Test force_network still redirects custom domains back to the network URL.
	 */
	public function test_force_network_redirects_custom_domain_to_network_domain() {

		$site_domain   = 'network-target-' . uniqid() . '.example.org';
		$custom_domain = 'custom-target-' . uniqid() . '.example.net';
		$blog_id       = self::factory()->blog->create(
			[
				'domain' => $site_domain,
				'path'   => '/',
			]
		);

		$this->assertNotWPError($blog_id);

		$mapping = $this->create_primary_domain_mapping($blog_id, $custom_domain);

		$previous_setting = wu_get_setting('force_admin_redirect', 'both');
		$previous_server  = $_SERVER;
		$previous_get     = $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Test restores superglobal state.
		$previous_request = $_REQUEST; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Test restores superglobal state.

		$domain_mapping           = \WP_Ultimo\Domain_Mapping::get_instance();
		$previous_current_mapping = $domain_mapping->current_mapping;

		$redirect_filter = static function ($location) {
			throw new \RuntimeException(esc_url_raw((string) $location));
		};

		switch_to_blog($blog_id);

		$domain_mapping->current_mapping = $mapping;

		add_filter('home_url', [$domain_mapping, 'mangle_url'], -10, 4);
		add_filter('site_url', [$domain_mapping, 'mangle_url'], -10, 4);
		add_filter('wp_redirect', $redirect_filter);

		try {
			wu_save_setting('force_admin_redirect', 'force_network');

			$_GET     = [];
			$_REQUEST = [];

			$_SERVER['REQUEST_METHOD'] = 'GET';
			$_SERVER['HTTP_HOST']      = $custom_domain;
			$_SERVER['REQUEST_URI']    = '/wp-admin/edit.php?post_type=page';

			try {
				Primary_Domain::get_instance()->maybe_redirect_to_mapped_or_network_domain();

				$this->fail('Expected a redirect back to the network domain.');
			} catch (\RuntimeException $exception) {
				$redirect_url = $exception->getMessage();

				$this->assertSame($site_domain, wp_parse_url($redirect_url, PHP_URL_HOST));
				$this->assertSame('/wp-admin/edit.php', wp_parse_url($redirect_url, PHP_URL_PATH));
				$this->assertSame('post_type=page', wp_parse_url($redirect_url, PHP_URL_QUERY));
			}
		} finally {
			remove_filter('wp_redirect', $redirect_filter);
			remove_filter('site_url', [$domain_mapping, 'mangle_url'], -10);
			remove_filter('home_url', [$domain_mapping, 'mangle_url'], -10);

			$domain_mapping->current_mapping = $previous_current_mapping;

			restore_current_blog();

			wu_save_setting('force_admin_redirect', $previous_setting);

			$_SERVER  = $previous_server;
			$_GET     = $previous_get;
			$_REQUEST = $previous_request;
		}
	}

	/**
	 * Test that wp_safe_redirect is used (no phpcs:ignore suppression present).
	 *
	 * This is a static analysis guard: if someone reverts to wp_redirect with a
	 * phpcs:ignore, this test will catch it by inspecting the source file.
	 */
	public function test_no_wp_redirect_phpcs_ignore_in_source() {

		$source = file_get_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local source file in a test.
			dirname(__DIR__, 3) . '/inc/domain-mapping/class-primary-domain.php'
		);

		$this->assertStringNotContainsString(
			'wp_redirect(',
			$source,
			'class-primary-domain.php must not use wp_redirect(); use wp_safe_redirect() instead.'
		);

		$this->assertStringNotContainsString(
			'phpcs:ignore WordPress.Security.SafeRedirect',
			$source,
			'class-primary-domain.php must not suppress SafeRedirect PHPCS sniff.'
		);
	}

	/**
	 * Test that wp_safe_redirect is present in the source.
	 */
	public function test_wp_safe_redirect_used_in_source() {

		$source = file_get_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local source file in a test.
			dirname(__DIR__, 3) . '/inc/domain-mapping/class-primary-domain.php'
		);

		$this->assertStringContainsString(
			'wp_safe_redirect(',
			$source,
			'class-primary-domain.php must use wp_safe_redirect().'
		);
	}

	/**
	 * Creates or updates a primary domain mapping for a test site.
	 *
	 * @param int    $blog_id Blog ID.
	 * @param string $domain Domain name.
	 * @return \WP_Ultimo\Models\Domain
	 */
	private function create_primary_domain_mapping($blog_id, $domain) {

		$mapping = wu_get_domain_by_domain($domain);

		if ( ! $mapping) {
			$mapping = wu_create_domain(
				[
					'blog_id'        => $blog_id,
					'domain'         => $domain,
					'active'         => true,
					'primary_domain' => true,
					'secure'         => false,
					'stage'          => \WP_Ultimo\Database\Domains\Domain_Stage::DONE,
				]
			);
		} else {
			$mapping->set_blog_id($blog_id);
			$mapping->set_active(true);
			$mapping->set_primary_domain(true);
			$mapping->set_secure(false);
			$mapping->set_stage(\WP_Ultimo\Database\Domains\Domain_Stage::DONE);

			$mapping->save();
		}

		$this->assertNotWPError($mapping);
		$this->assertInstanceOf(\WP_Ultimo\Models\Domain::class, $mapping);

		return $mapping;
	}
}
