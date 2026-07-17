<?php
/**
 * Tests for Magic_Link_Url_Element class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\UI;

use WP_UnitTestCase;

/**
 * Test class for Magic_Link_Url_Element.
 */
class Magic_Link_Url_Element_Test extends WP_UnitTestCase {

	/**
	 * Test customer model.
	 *
	 * @var \WP_Ultimo\Models\Customer
	 */
	private $customer;

	/**
	 * Test site model.
	 *
	 * @var \WP_Ultimo\Models\Site
	 */
	private $site;

	/**
	 * Original remote address value.
	 *
	 * @var string|null
	 */
	private $remote_addr;

	/**
	 * Whether REMOTE_ADDR existed before the test.
	 *
	 * @var bool
	 */
	private $remote_addr_was_set = false;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {

		parent::setUp();

		$unique = wp_rand(1000, 9999);

		$this->remote_addr_was_set = isset($_SERVER['REMOTE_ADDR']);
		$this->remote_addr         = $this->remote_addr_was_set ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : null;

		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

		$this->customer = wu_create_customer(
			[
				'username' => 'magiclinkcustomer' . $unique,
				'email'    => 'magiclinkcustomer' . $unique . '@example.com',
				'password' => 'password123',
			]
		);

		if (is_wp_error($this->customer)) {
			$this->fail('Could not create test customer');
		}

		$site_id = self::factory()->blog->create(
			[
				'user_id' => $this->customer->get_user_id(),
			]
		);

		add_user_to_blog($site_id, $this->customer->get_user_id(), 'administrator');

		$this->site = wu_create_site(
			[
				'blog_id'     => $site_id,
				'customer_id' => $this->customer->get_id(),
				'type'        => 'customer_owned',
			]
		);

		if (is_wp_error($this->site)) {
			$this->fail('Could not create test site');
		}

		wp_set_current_user($this->customer->get_user_id());

		WP_Ultimo()->currents->set_customer($this->customer);
		WP_Ultimo()->currents->set_site(false);
		WP_Ultimo()->currents->set_membership(false);
	}

	/**
	 * Tear down test fixtures.
	 */
	protected function tearDown(): void {

		unset($_GET['site'], $_POST['site'], $_REQUEST['site']);

		wp_set_current_user(0);

		if ($this->remote_addr_was_set) {
			$_SERVER['REMOTE_ADDR'] = $this->remote_addr;
		} else {
			unset($_SERVER['REMOTE_ADDR']);
		}

		WP_Ultimo()->currents->set_customer(false);
		WP_Ultimo()->currents->set_site(false);
		WP_Ultimo()->currents->set_membership(false);

		parent::tearDown();
	}

	/**
	 * Test site resolution from explicit shortcode attributes.
	 */
	public function test_get_site_id_from_atts_prefers_explicit_site_id(): void {

		$this->assertSame($this->site->get_id(), $this->invoke_site_id_from_atts(['site_id' => $this->site->get_id()]));
	}

	/**
	 * Test site resolution from URL context.
	 */
	public function test_get_site_id_from_atts_uses_site_hash_request(): void {

		$_GET['site']     = $this->site->get_hash();
		$_REQUEST['site'] = $this->site->get_hash();

		$this->assertSame($this->site->get_id(), $this->invoke_site_id_from_atts([]));
	}

	/**
	 * Test fallback to the current customer's primary site on the main site.
	 */
	public function test_get_site_id_from_atts_falls_back_to_customer_primary_site(): void {

		update_user_option($this->customer->get_user_id(), 'primary_blog', $this->site->get_id(), true);

		$this->assertSame($this->site->get_id(), $this->invoke_site_id_from_atts([]));
	}

	/**
	 * Test fallback skips a primary blog that is not a customer-owned site.
	 */
	public function test_get_site_id_from_atts_skips_non_customer_primary_blog(): void {

		update_user_option($this->customer->get_user_id(), 'primary_blog', get_main_site_id(), true);

		$this->assertSame($this->site->get_id(), $this->invoke_site_id_from_atts([]));
	}

	/**
	 * Test the shortcode renders a customer-site link without an explicit site context.
	 */
	public function test_shortcode_renders_customer_site_link_without_site_context(): void {

		update_user_option($this->customer->get_user_id(), 'primary_blog', $this->site->get_id(), true);

		$html = do_shortcode('[wu_magic_link_url redirect_to="/wp-admin/admin.php?page=account" display_as="button" link_text="Account"]');

		$this->assertStringContainsString('Account', $html);
		$this->assertStringContainsString('href=', $html);
		$this->assertStringContainsString($this->site->get_active_site_url(), $html);
	}

	/**
	 * Test array-based field requirements do not trigger PHP warnings.
	 */
	public function test_generator_modal_supports_array_field_requirements(): void {

		$warnings = [];

		set_error_handler( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
			static function ($error_number, $error_message) use (&$warnings) {
				if (E_WARNING === $error_number) {
					$warnings[] = $error_message;

					return true;
				}

				return false;
			}
		);

		ob_start();

		try {
			Magic_Link_Url_Element::get_instance()->render_generator_modal();
			$html = ob_get_clean();
		} finally {
			restore_error_handler();
		}

		$this->assertSame([], $warnings);
		$this->assertStringContainsString('includes', $html);
	}

	/**
	 * Invoke the protected site resolution method.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return int|null
	 */
	private function invoke_site_id_from_atts(array $atts): ?int {

		$element = Magic_Link_Url_Element::get_instance();
		$method  = new \ReflectionMethod($element, 'get_site_id_from_atts');

		$method->setAccessible(true);

		return $method->invoke($element, $atts);
	}
}
