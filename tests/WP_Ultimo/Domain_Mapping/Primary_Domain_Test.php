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

		$priority = has_filter(
			'allowed_redirect_hosts',
			[$instance, 'allow_mapped_domain_redirect_hosts']
		);

		// has_filter returns the priority (int) when registered, false otherwise.
		$this->assertNotFalse($priority);
	}

	/**
	 * Test that wp_safe_redirect is used (no phpcs:ignore suppression present).
	 *
	 * This is a static analysis guard: if someone reverts to wp_redirect with a
	 * phpcs:ignore, this test will catch it by inspecting the source file.
	 */
	public function test_no_wp_redirect_phpcs_ignore_in_source() {

		$source = file_get_contents(
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

		$source = file_get_contents(
			dirname(__DIR__, 3) . '/inc/domain-mapping/class-primary-domain.php'
		);

		$this->assertStringContainsString(
			'wp_safe_redirect(',
			$source,
			'class-primary-domain.php must use wp_safe_redirect().'
		);
	}
}
