<?php
/**
 * Tests for Credits class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo;

use WP_UnitTestCase;

/**
 * Test class for Credits.
 */
class Credits_Test extends WP_UnitTestCase {

	/**
	 * @var Credits
	 */
	private Credits $credits;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {

		parent::set_up();
		$this->credits = Credits::get_instance();
	}

	/**
	 * Test singleton returns correct instance.
	 */
	public function test_singleton_returns_correct_instance(): void {

		$this->assertInstanceOf(Credits::class, $this->credits);
	}

	/**
	 * Test singleton returns same instance.
	 */
	public function test_singleton_returns_same_instance(): void {

		$this->assertSame(
			Credits::get_instance(),
			Credits::get_instance()
		);
	}

	/**
	 * Test filter_admin_footer_text returns original text on network admin.
	 */
	public function test_filter_admin_footer_text_network_admin(): void {

		// Simulate network admin context.
		$this->go_to(network_admin_url());

		$original = 'Thank you for creating with WordPress.';
		$result   = $this->credits->filter_admin_footer_text($original);

		$this->assertEquals($original, $result);
	}

	/**
	 * Test filter_admin_footer_text handles non-string input.
	 */
	public function test_filter_admin_footer_text_non_string(): void {

		// Simulate network admin so it returns early.
		$this->go_to(network_admin_url());

		$result = $this->credits->filter_admin_footer_text(null);

		$this->assertIsString($result);
	}

	/**
	 * Test filter_update_footer_text returns original text on network admin.
	 */
	public function test_filter_update_footer_text_network_admin(): void {

		$this->go_to(network_admin_url());

		$original = 'Version 6.0';
		$result   = $this->credits->filter_update_footer_text($original);

		$this->assertEquals($original, $result);
	}

	/**
	 * Test filter_update_footer_text handles non-string input.
	 */
	public function test_filter_update_footer_text_non_string(): void {

		$this->go_to(network_admin_url());

		$result = $this->credits->filter_update_footer_text(null);

		$this->assertIsString($result);
	}

	/**
	 * Test render_frontend_footer does nothing in admin context.
	 */
	public function test_render_frontend_footer_does_nothing_in_admin(): void {

		set_current_screen('dashboard');

		ob_start();
		$this->credits->render_frontend_footer();
		$output = ob_get_clean();

		$this->assertEmpty($output);
	}

	/**
	 * Test build_credit_html returns empty when disabled.
	 */
	public function test_build_credit_html_returns_empty_when_disabled(): void {

		// Ensure credits are disabled.
		wu_save_setting('credits_enable', 0);

		$reflection = new \ReflectionClass($this->credits);
		$method     = $reflection->getMethod('build_credit_html');

		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$result = $method->invoke($this->credits);

		$this->assertEquals('', $result);
	}

	/**
	 * Test build_credit_html returns content when enabled with default type.
	 */
	public function test_build_credit_html_returns_content_when_enabled(): void {

		wu_save_setting('credits_enable', 1);
		wu_save_setting('credits_type', 'default');

		$reflection = new \ReflectionClass($this->credits);
		$method     = $reflection->getMethod('build_credit_html');

		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$result = $method->invoke($this->credits);

		$this->assertStringContainsString('Powered by', $result);
		$this->assertStringContainsString('ultimatemultisite.com', $result);

		// Clean up.
		wu_save_setting('credits_enable', 0);
	}

	/**
	 * Test build_credit_html with custom HTML type.
	 */
	public function test_build_credit_html_custom_html_type(): void {

		wu_save_setting('credits_enable', 1);
		wu_save_setting('credits_type', 'html');
		wu_save_setting('credits_custom_html', '<p>Custom Footer</p>');

		$reflection = new \ReflectionClass($this->credits);
		$method     = $reflection->getMethod('build_credit_html');

		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$result = $method->invoke($this->credits);

		$this->assertStringContainsString('Custom Footer', $result);

		// Clean up.
		wu_save_setting('credits_enable', 0);
	}

	/**
	 * Test build_credit_html normalizes corrupted custom HTML settings.
	 */
	public function test_build_credit_html_normalizes_corrupted_custom_html(): void {

		wu_save_setting('credits_enable', 1);
		wu_save_setting('credits_type', 'html');
		wu_save_setting('credits_custom_html', '[object Object]');

		$reflection = new \ReflectionClass($this->credits);
		$method     = $reflection->getMethod('build_credit_html');

		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$result = $method->invoke($this->credits);

		$this->assertStringNotContainsString('[object Object]', $result);
		$this->assertStringContainsString('Powered by', $result);

		// Clean up.
		wu_save_setting('credits_enable', 0);
	}

	/**
	 * Test build_credit_html with custom network type.
	 */
	public function test_build_credit_html_custom_network_type(): void {

		wu_save_setting('credits_enable', 1);
		wu_save_setting('credits_type', 'custom');

		$reflection = new \ReflectionClass($this->credits);
		$method     = $reflection->getMethod('build_credit_html');

		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$result = $method->invoke($this->credits);

		$this->assertStringContainsString('Powered by', $result);

		// Clean up.
		wu_save_setting('credits_enable', 0);
	}

	/**
	 * Test build_default_credit contains expected elements.
	 */
	public function test_build_default_credit_structure(): void {

		$reflection = new \ReflectionClass($this->credits);
		$method     = $reflection->getMethod('build_default_credit');

		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$result = $method->invoke($this->credits);

		$this->assertStringContainsString('Powered by', $result);
		$this->assertStringContainsString('Ultimate Multisite', $result);
		$this->assertStringContainsString('https://ultimatemultisite.com', $result);
	}

	/**
	 * Test build_custom_credit contains network name.
	 */
	public function test_build_custom_credit_contains_network_info(): void {

		$reflection = new \ReflectionClass($this->credits);
		$method     = $reflection->getMethod('build_custom_credit');

		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$result = $method->invoke($this->credits);

		$this->assertStringContainsString('Powered by', $result);
		$this->assertStringContainsString('<a href=', $result);
	}

	/**
	 * Test register_settings hooks into init.
	 */
	public function test_init_registers_hooks(): void {

		$this->assertNotFalse(has_filter('admin_footer_text', [$this->credits, 'filter_admin_footer_text']));
		$this->assertNotFalse(has_filter('update_footer', [$this->credits, 'filter_update_footer_text']));
		$this->assertNotFalse(has_action('wp_footer', [$this->credits, 'render_frontend_footer']));
		$this->assertNotFalse(has_action('login_footer', [$this->credits, 'render_frontend_footer']));
	}
}
