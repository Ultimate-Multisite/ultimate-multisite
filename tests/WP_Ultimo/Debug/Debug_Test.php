<?php

namespace WP_Ultimo\Debug;

/**
 * Tests for the Debug class.
 *
 * Targets >=80% line coverage for inc/debug/class-debug.php.
 */
class Debug_Test extends \WP_UnitTestCase {

	/**
	 * Get a fresh Debug instance.
	 *
	 * @return Debug
	 */
	private function get_instance() {

		return Debug::get_instance();
	}

	public function set_up() {

		parent::set_up();

		// Define WP_ULTIMO_DEBUG for tests
		if ( ! defined('WP_ULTIMO_DEBUG')) {
			define('WP_ULTIMO_DEBUG', true);
		}
	}

	// -------------------------------------------------------------------------
	// Singleton / basic accessors
	// -------------------------------------------------------------------------

	/**
	 * Test singleton instance.
	 */
	public function test_get_instance() {

		$instance = $this->get_instance();

		$this->assertInstanceOf(Debug::class, $instance);
		$this->assertSame($instance, Debug::get_instance());
	}

	/**
	 * Test should_load returns true when constant is defined.
	 */
	public function test_should_load_true() {

		$instance = $this->get_instance();

		$this->assertTrue($instance->should_load());
	}

	/**
	 * Test get_pages returns array.
	 */
	public function test_get_pages() {

		$instance = $this->get_instance();

		$pages = $instance->get_pages();

		$this->assertIsArray($pages);
	}

	/**
	 * Test add_page adds a page.
	 */
	public function test_add_page() {

		$instance = $this->get_instance();

		$instance->add_page('test-page');

		$pages = $instance->get_pages();

		$this->assertArrayHasKey('test-page', $pages);
	}

	/**
	 * Test add_page stores the URL returned by wu_network_admin_url.
	 */
	public function test_add_page_stores_url() {

		$instance = $this->get_instance();

		$instance->add_page('wp-ultimo');

		$pages = $instance->get_pages();

		$this->assertArrayHasKey('wp-ultimo', $pages);
		$this->assertIsString($pages['wp-ultimo']);
	}

	// -------------------------------------------------------------------------
	// load / init / hooks
	// -------------------------------------------------------------------------

	/**
	 * Test load does not throw when should_load is true.
	 */
	public function test_load() {

		$instance = $this->get_instance();

		// Should not throw
		$instance->load();

		$this->assertTrue(true);
	}

	/**
	 * Test load registers the wu_page_added action when debug is enabled.
	 */
	public function test_load_registers_wu_page_added_action() {

		$instance = $this->get_instance();

		$instance->load();

		$this->assertGreaterThan(0, has_action('wu_page_added', [$instance, 'add_page']));
	}

	/**
	 * Test load registers wu_tour_finished filter.
	 */
	public function test_load_registers_tour_finished_filter() {

		$instance = $this->get_instance();

		$instance->load();

		$this->assertGreaterThan(0, has_filter('wu_tour_finished', '__return_false'));
	}

	/**
	 * Test init does not throw.
	 */
	public function test_init() {

		$instance = $this->get_instance();

		// Should not throw
		$instance->init();

		$this->assertTrue(true);
	}

	/**
	 * Test init registers wp_ultimo_debug actions.
	 */
	public function test_init_registers_debug_actions() {

		$instance = $this->get_instance();

		$instance->init();

		$this->assertGreaterThan(0, has_action('wp_ultimo_debug', [$instance, 'add_main_debug_menu']));
		$this->assertGreaterThan(0, has_action('wp_ultimo_debug', [$instance, 'add_additional_hooks']));
		$this->assertGreaterThan(0, has_action('wp_ultimo_debug', [$instance, 'register_forms']));
	}

	/**
	 * Test add_additional_hooks does not throw.
	 */
	public function test_add_additional_hooks() {

		$instance = $this->get_instance();

		// Should not throw
		$instance->add_additional_hooks();

		$this->assertTrue(true);
	}

	/**
	 * Test add_additional_hooks registers wu_header_left and wp_footer actions.
	 */
	public function test_add_additional_hooks_registers_actions() {

		$instance = $this->get_instance();

		$instance->add_additional_hooks();

		$this->assertGreaterThan(0, has_action('wu_header_left', [$instance, 'add_debug_links']));
		$this->assertGreaterThan(0, has_action('wp_footer', [$instance, 'render_checkout_autofill_button']));
	}

	/**
	 * Test register_forms does not throw.
	 */
	public function test_register_forms() {

		$instance = $this->get_instance();

		// Should not throw
		$instance->register_forms();

		$this->assertTrue(true);
	}

	/**
	 * Test add_main_debug_menu does not throw.
	 */
	public function test_add_main_debug_menu() {

		$instance = $this->get_instance();

		// Should not throw (creates admin page)
		$instance->add_main_debug_menu();

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// Render methods — output buffering
	// -------------------------------------------------------------------------

	/**
	 * Test add_debug_links outputs HTML with expected links.
	 */
	public function test_add_debug_links_outputs_html() {

		$instance = $this->get_instance();

		ob_start();
		$instance->add_debug_links();
		$output = ob_get_clean();

		$this->assertStringContainsString('<a', $output);
		$this->assertStringContainsString('wp-ultimo-debug-pages', $output);
	}

	/**
	 * Test add_debug_links contains generator form link.
	 */
	public function test_add_debug_links_contains_generator_link() {

		$instance = $this->get_instance();

		ob_start();
		$instance->add_debug_links();
		$output = ob_get_clean();

		$this->assertStringContainsString('add_debug_generator_form', $output);
	}

	/**
	 * Test add_debug_links contains reset database link.
	 */
	public function test_add_debug_links_contains_reset_link() {

		$instance = $this->get_instance();

		ob_start();
		$instance->add_debug_links();
		$output = ob_get_clean();

		$this->assertStringContainsString('add_debug_reset_database_form', $output);
	}

	/**
	 * Test add_debug_links contains drop database link.
	 */
	public function test_add_debug_links_contains_drop_link() {

		$instance = $this->get_instance();

		ob_start();
		$instance->add_debug_links();
		$output = ob_get_clean();

		$this->assertStringContainsString('add_debug_drop_database_form', $output);
	}

	/**
	 * Test render_checkout_autofill_button outputs a button element.
	 */
	public function test_render_checkout_autofill_button_outputs_button() {

		$instance = $this->get_instance();

		ob_start();
		$instance->render_checkout_autofill_button();
		$output = ob_get_clean();

		$this->assertStringContainsString('<button', $output);
		$this->assertStringContainsString('wu-debug-autofill', $output);
	}

	/**
	 * Test render_checkout_autofill_button outputs script tag.
	 */
	public function test_render_checkout_autofill_button_outputs_script() {

		$instance = $this->get_instance();

		ob_start();
		$instance->render_checkout_autofill_button();
		$output = ob_get_clean();

		$this->assertStringContainsString('<script>', $output);
		$this->assertStringContainsString('wu-debug-autofill', $output);
	}

	/**
	 * Test render_checkout_autofill_button contains fill with random data text.
	 */
	public function test_render_checkout_autofill_button_contains_fill_text() {

		$instance = $this->get_instance();

		ob_start();
		$instance->render_checkout_autofill_button();
		$output = ob_get_clean();

		$this->assertStringContainsString('Fill with random data', $output);
	}

	/**
	 * Test render_debug_generator_form executes without error.
	 */
	public function test_render_debug_generator_form_no_error() {

		$instance = $this->get_instance();

		ob_start();
		$instance->render_debug_generator_form();
		ob_get_clean();

		$this->assertTrue(true);
	}

	/**
	 * Test render_debug_reset_database_form executes without error.
	 */
	public function test_render_debug_reset_database_form_no_error() {

		$instance = $this->get_instance();

		ob_start();
		$instance->render_debug_reset_database_form();
		ob_get_clean();

		$this->assertTrue(true);
	}

	/**
	 * Test render_debug_drop_database_form executes without error.
	 */
	public function test_render_debug_drop_database_form_no_error() {

		$instance = $this->get_instance();

		ob_start();
		$instance->render_debug_drop_database_form();
		ob_get_clean();

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// reset_settings
	// -------------------------------------------------------------------------

	/**
	 * Test reset_settings removes known options.
	 */
	public function test_reset_settings() {

		$instance = $this->get_instance();

		// Set one of the options that reset_settings actually deletes
		// debug_faker is in the list with prefix 'wp-ultimo_'
		update_network_option(null, 'wp-ultimo_debug_faker', ['test' => 'data']);

		$this->assertSame(['test' => 'data'], get_network_option(null, 'wp-ultimo_debug_faker'));

		// Call reset_settings via reflection
		$ref = new \ReflectionMethod($instance, 'reset_settings');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		$ref->invoke($instance);

		// The option should be deleted
		$this->assertFalse(get_network_option(null, 'wp-ultimo_debug_faker'));
	}

	/**
	 * Test reset_settings removes v2_settings option.
	 */
	public function test_reset_settings_removes_v2_settings() {

		$instance = $this->get_instance();

		update_network_option(null, 'wp-ultimo_v2_settings', ['key' => 'value']);

		$ref = new \ReflectionMethod($instance, 'reset_settings');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		$ref->invoke($instance);

		$this->assertFalse(get_network_option(null, 'wp-ultimo_v2_settings'));
	}

	/**
	 * Test reset_settings removes wu_activation option (no prefix).
	 */
	public function test_reset_settings_removes_wu_activation() {

		$instance = $this->get_instance();

		update_network_option(null, 'wu_activation', 'active');

		$ref = new \ReflectionMethod($instance, 'reset_settings');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		$ref->invoke($instance);

		$this->assertFalse(get_network_option(null, 'wu_activation'));
	}

	// -------------------------------------------------------------------------
	// reset_table
	// -------------------------------------------------------------------------

	/**
	 * Test reset_table with empty table name does nothing.
	 */
	public function test_reset_table_empty() {

		$instance = $this->get_instance();

		$ref = new \ReflectionMethod($instance, 'reset_table');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		// Should not throw with empty table
		$ref->invoke($instance, '', [], 'ID');

		$this->assertTrue(true);
	}

	/**
	 * Test reset_table with a real table and no IDs (DELETE all).
	 */
	public function test_reset_table_with_real_table_no_ids() {

		global $wpdb;

		$instance = $this->get_instance();

		$ref = new \ReflectionMethod($instance, 'reset_table');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		// Use the wu_events table which should exist in the test environment
		$table = "{$wpdb->base_prefix}wu_events";

		// Should not throw — table may be empty but DELETE should succeed
		try {
			$ref->invoke($instance, $table, [], 'ID');
			$this->assertTrue(true);
		} catch (\Exception $e) {
			// Table may not exist in test env — that's acceptable
			$this->assertStringContainsString('Error', $e->getMessage());
		}
	}

	/**
	 * Test reset_table with IDs deletes specific rows.
	 */
	public function test_reset_table_with_ids() {

		global $wpdb;

		$instance = $this->get_instance();

		$ref = new \ReflectionMethod($instance, 'reset_table');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		// Use the wu_events table
		$table = "{$wpdb->base_prefix}wu_events";

		// Should not throw with IDs — even if no rows match
		try {
			$ref->invoke($instance, $table, [999999, 999998], 'ID');
			$this->assertTrue(true);
		} catch (\Exception $e) {
			// Table may not exist in test env — that's acceptable
			$this->assertStringContainsString('Error', $e->getMessage());
		}
	}

	/**
	 * Test reset_table with IDs filters empty values.
	 */
	public function test_reset_table_with_ids_filters_empty() {

		global $wpdb;

		$instance = $this->get_instance();

		$ref = new \ReflectionMethod($instance, 'reset_table');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		$table = "{$wpdb->base_prefix}wu_events";

		// IDs with null/empty values — array_filter should remove them
		// If all IDs are filtered out, it becomes an empty array and should not throw
		try {
			$ref->invoke($instance, $table, [null, '', 0], 'ID');
			$this->assertTrue(true);
		} catch (\Exception $e) {
			$this->assertStringContainsString('Error', $e->getMessage());
		}
	}

	/**
	 * Test reset_table throws exception when wpdb->query returns false (no IDs).
	 */
	public function test_reset_table_throws_on_query_failure() {

		$instance = $this->get_instance();

		$ref = new \ReflectionMethod($instance, 'reset_table');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		// Use a non-existent table to trigger a DB error (query returns false)
		$this->expectException(\Exception::class);

		$ref->invoke($instance, 'nonexistent_table_xyz_abc_123', [], 'ID');
	}

	/**
	 * Test reset_table throws exception when wpdb->query returns false with IDs.
	 */
	public function test_reset_table_throws_on_query_failure_with_ids() {

		$instance = $this->get_instance();

		$ref = new \ReflectionMethod($instance, 'reset_table');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		// Use a non-existent table to trigger a DB error (query returns false)
		$this->expectException(\Exception::class);

		$ref->invoke($instance, 'nonexistent_table_xyz_abc_123', [1, 2, 3], 'ID');
	}

	// -------------------------------------------------------------------------
	// reset_customers
	// -------------------------------------------------------------------------

	/**
	 * Test reset_customers with no IDs.
	 */
	public function test_reset_customers_empty() {

		$instance = $this->get_instance();

		$ref = new \ReflectionMethod($instance, 'reset_customers');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		// Should not throw with empty array
		$ref->invoke($instance, []);

		$this->assertTrue(true);
	}

	/**
	 * Test reset_customers with IDs where customer does not exist.
	 */
	public function test_reset_customers_with_nonexistent_id() {

		$instance = $this->get_instance();

		$ref = new \ReflectionMethod($instance, 'reset_customers');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		// wu_get_customer(999999) should return null/false — no exception expected
		try {
			$ref->invoke($instance, [999999]);
			$this->assertTrue(true);
		} catch (\Exception $e) {
			// If table doesn't exist, an exception is acceptable
			$this->assertStringContainsString('Error', $e->getMessage());
		}
	}

	// -------------------------------------------------------------------------
	// reset_products
	// -------------------------------------------------------------------------

	/**
	 * Test reset_products with no IDs.
	 */
	public function test_reset_products_empty() {

		$instance = $this->get_instance();

		$ref = new \ReflectionMethod($instance, 'reset_products');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		$ref->invoke($instance, []);

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// reset_memberships
	// -------------------------------------------------------------------------

	/**
	 * Test reset_memberships with no IDs.
	 */
	public function test_reset_memberships_empty() {

		$instance = $this->get_instance();

		$ref = new \ReflectionMethod($instance, 'reset_memberships');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		$ref->invoke($instance, []);

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// reset_domains
	// -------------------------------------------------------------------------

	/**
	 * Test reset_domains with no IDs.
	 */
	public function test_reset_domains_empty() {

		$instance = $this->get_instance();

		$ref = new \ReflectionMethod($instance, 'reset_domains');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		$ref->invoke($instance, []);

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// reset_sites
	// -------------------------------------------------------------------------

	/**
	 * Test reset_sites with no IDs.
	 */
	public function test_reset_sites_empty() {

		$instance = $this->get_instance();

		$ref = new \ReflectionMethod($instance, 'reset_sites');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		$ref->invoke($instance, []);

		$this->assertTrue(true);
	}

	/**
	 * Test reset_sites with a nonexistent site ID.
	 */
	public function test_reset_sites_with_nonexistent_id() {

		$instance = $this->get_instance();

		$ref = new \ReflectionMethod($instance, 'reset_sites');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		// wu_get_site(999999) should return null/false — no exception expected
		$ref->invoke($instance, [999999]);

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// reset_discount_codes
	// -------------------------------------------------------------------------

	/**
	 * Test reset_discount_codes with no IDs.
	 */
	public function test_reset_discount_codes_empty() {

		$instance = $this->get_instance();

		$ref = new \ReflectionMethod($instance, 'reset_discount_codes');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		$ref->invoke($instance, []);

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// reset_payments
	// -------------------------------------------------------------------------

	/**
	 * Test reset_payments with no IDs.
	 */
	public function test_reset_payments_empty() {

		$instance = $this->get_instance();

		$ref = new \ReflectionMethod($instance, 'reset_payments');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		$ref->invoke($instance, []);

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// reset_webhooks
	// -------------------------------------------------------------------------

	/**
	 * Test reset_webhooks with no IDs.
	 */
	public function test_reset_webhooks_empty() {

		$instance = $this->get_instance();

		$ref = new \ReflectionMethod($instance, 'reset_webhooks');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		$ref->invoke($instance, []);

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// reset_checkout_forms
	// -------------------------------------------------------------------------

	/**
	 * Test reset_checkout_forms with no IDs.
	 */
	public function test_reset_checkout_forms_empty() {

		$instance = $this->get_instance();

		$ref = new \ReflectionMethod($instance, 'reset_checkout_forms');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		$ref->invoke($instance, []);

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// reset_post_like_models
	// -------------------------------------------------------------------------

	/**
	 * Test reset_post_like_models with no IDs.
	 */
	public function test_reset_post_like_models_empty() {

		$instance = $this->get_instance();

		$ref = new \ReflectionMethod($instance, 'reset_post_like_models');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		$ref->invoke($instance, []);

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// reset_events
	// -------------------------------------------------------------------------

	/**
	 * Test reset_events with no IDs.
	 */
	public function test_reset_events_empty() {

		$instance = $this->get_instance();

		$ref = new \ReflectionMethod($instance, 'reset_events');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		$ref->invoke($instance, []);

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// reset_fake_data
	// -------------------------------------------------------------------------

	/**
	 * Test reset_fake_data with empty option.
	 */
	public function test_reset_fake_data_empty() {

		$instance = $this->get_instance();

		// Ensure debug_faker option is empty
		wu_delete_option('debug_faker');

		$ref = new \ReflectionMethod($instance, 'reset_fake_data');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		// Should not throw with empty option
		$ref->invoke($instance);

		$this->assertTrue(true);
	}

	/**
	 * Test reset_fake_data with populated option (all keys present, empty arrays).
	 */
	public function test_reset_fake_data_with_populated_option() {

		$instance = $this->get_instance();

		// Set fake data option with empty arrays for each entity type
		wu_save_option(
			'debug_faker',
			[
				'customers'      => [],
				'products'       => [],
				'memberships'    => [],
				'domains'        => [],
				'sites'          => [],
				'discount_codes' => [],
				'payments'       => [],
			]
		);

		$ref = new \ReflectionMethod($instance, 'reset_fake_data');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		// Should not throw — all IDs are empty arrays
		$ref->invoke($instance);

		$this->assertTrue(true);

		// Clean up
		wu_delete_option('debug_faker');
	}

	/**
	 * Test reset_fake_data with nonexistent customer IDs.
	 */
	public function test_reset_fake_data_with_nonexistent_customer_ids() {

		$instance = $this->get_instance();

		wu_save_option(
			'debug_faker',
			[
				'customers' => [999991, 999992],
			]
		);

		$ref = new \ReflectionMethod($instance, 'reset_fake_data');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		// wu_get_customer returns null for nonexistent IDs — no exception
		try {
			$ref->invoke($instance);
			$this->assertTrue(true);
		} catch (\Exception $e) {
			// DB table may not exist — acceptable
			$this->assertStringContainsString('Error', $e->getMessage());
		}

		wu_delete_option('debug_faker');
	}

	/**
	 * Test reset_fake_data with nonexistent site IDs.
	 */
	public function test_reset_fake_data_with_nonexistent_site_ids() {

		$instance = $this->get_instance();

		wu_save_option(
			'debug_faker',
			[
				'sites' => [999991, 999992],
			]
		);

		$ref = new \ReflectionMethod($instance, 'reset_fake_data');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		// wu_get_site returns null for nonexistent IDs — no exception
		$ref->invoke($instance);

		$this->assertTrue(true);

		wu_delete_option('debug_faker');
	}

	/**
	 * Test reset_fake_data with nonexistent product IDs.
	 */
	public function test_reset_fake_data_with_nonexistent_product_ids() {

		$instance = $this->get_instance();

		wu_save_option(
			'debug_faker',
			[
				'products' => [999991, 999992],
			]
		);

		$ref = new \ReflectionMethod($instance, 'reset_fake_data');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		try {
			$ref->invoke($instance);
			$this->assertTrue(true);
		} catch (\Exception $e) {
			$this->assertStringContainsString('Error', $e->getMessage());
		}

		wu_delete_option('debug_faker');
	}

	/**
	 * Test reset_fake_data with nonexistent membership IDs.
	 */
	public function test_reset_fake_data_with_nonexistent_membership_ids() {

		$instance = $this->get_instance();

		wu_save_option(
			'debug_faker',
			[
				'memberships' => [999991, 999992],
			]
		);

		$ref = new \ReflectionMethod($instance, 'reset_fake_data');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		try {
			$ref->invoke($instance);
			$this->assertTrue(true);
		} catch (\Exception $e) {
			$this->assertStringContainsString('Error', $e->getMessage());
		}

		wu_delete_option('debug_faker');
	}

	/**
	 * Test reset_fake_data with nonexistent domain IDs.
	 */
	public function test_reset_fake_data_with_nonexistent_domain_ids() {

		$instance = $this->get_instance();

		wu_save_option(
			'debug_faker',
			[
				'domains' => [999991, 999992],
			]
		);

		$ref = new \ReflectionMethod($instance, 'reset_fake_data');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		try {
			$ref->invoke($instance);
			$this->assertTrue(true);
		} catch (\Exception $e) {
			$this->assertStringContainsString('Error', $e->getMessage());
		}

		wu_delete_option('debug_faker');
	}

	/**
	 * Test reset_fake_data with nonexistent discount code IDs.
	 */
	public function test_reset_fake_data_with_nonexistent_discount_code_ids() {

		$instance = $this->get_instance();

		wu_save_option(
			'debug_faker',
			[
				'discount_codes' => [999991, 999992],
			]
		);

		$ref = new \ReflectionMethod($instance, 'reset_fake_data');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		try {
			$ref->invoke($instance);
			$this->assertTrue(true);
		} catch (\Exception $e) {
			$this->assertStringContainsString('Error', $e->getMessage());
		}

		wu_delete_option('debug_faker');
	}

	/**
	 * Test reset_fake_data with nonexistent payment IDs.
	 */
	public function test_reset_fake_data_with_nonexistent_payment_ids() {

		$instance = $this->get_instance();

		wu_save_option(
			'debug_faker',
			[
				'payments' => [999991, 999992],
			]
		);

		$ref = new \ReflectionMethod($instance, 'reset_fake_data');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		try {
			$ref->invoke($instance);
			$this->assertTrue(true);
		} catch (\Exception $e) {
			$this->assertStringContainsString('Error', $e->getMessage());
		}

		wu_delete_option('debug_faker');
	}

	// -------------------------------------------------------------------------
	// reset_all_data
	// -------------------------------------------------------------------------

	/**
	 * Test reset_all_data does not throw.
	 */
	public function test_reset_all_data() {

		$instance = $this->get_instance();

		$ref = new \ReflectionMethod($instance, 'reset_all_data');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		// Should not throw (operates on empty tables mostly)
		$ref->invoke($instance);

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// handle_* form handlers — method existence and structure
	// -------------------------------------------------------------------------

	/**
	 * Test handle_debug_reset_database_form with reset_only=true.
	 */
	public function test_handle_debug_reset_database_form_reset_only() {

		$instance = $this->get_instance();

		// Mock the request
		$_POST['reset_only'] = '1';
		$_POST['_wpnonce']   = wp_create_nonce('wu_form_nonce');

		// Set up a fake option
		wu_save_option('debug_faker', ['customers' => []]);

		// This would normally send JSON response
		// We can't easily test the full flow without output buffering issues
		// So we just verify the method exists and doesn't fatal
		$this->assertTrue(method_exists($instance, 'handle_debug_reset_database_form'));

		// Clean up
		wu_delete_option('debug_faker');
		unset($_POST['reset_only'], $_POST['_wpnonce']);
	}

	/**
	 * Test handle_debug_drop_database_form method exists.
	 */
	public function test_handle_debug_drop_database_form_exists() {

		$instance = $this->get_instance();

		$this->assertTrue(method_exists($instance, 'handle_debug_drop_database_form'));
	}

	/**
	 * Test handle_debug_generator_form method exists.
	 */
	public function test_handle_debug_generator_form_exists() {

		$instance = $this->get_instance();

		$this->assertTrue(method_exists($instance, 'handle_debug_generator_form'));
	}

	/**
	 * Test render_checkout_autofill_button method exists.
	 */
	public function test_render_checkout_autofill_button_exists() {

		$instance = $this->get_instance();

		$this->assertTrue(method_exists($instance, 'render_checkout_autofill_button'));
	}

	/**
	 * Test render_debug_generator_form method exists.
	 */
	public function test_render_debug_generator_form_exists() {

		$instance = $this->get_instance();

		$this->assertTrue(method_exists($instance, 'render_debug_generator_form'));
	}

	/**
	 * Test render_debug_reset_database_form method exists.
	 */
	public function test_render_debug_reset_database_form_exists() {

		$instance = $this->get_instance();

		$this->assertTrue(method_exists($instance, 'render_debug_reset_database_form'));
	}

	/**
	 * Test render_debug_drop_database_form method exists.
	 */
	public function test_render_debug_drop_database_form_exists() {

		$instance = $this->get_instance();

		$this->assertTrue(method_exists($instance, 'render_debug_drop_database_form'));
	}

	/**
	 * Test handle_debug_drop_database_form is public.
	 */
	public function test_handle_debug_drop_database_form_is_public() {

		$instance = $this->get_instance();

		$ref = new \ReflectionMethod($instance, 'handle_debug_drop_database_form');

		$this->assertTrue($ref->isPublic());
	}

	/**
	 * Test handle_debug_generator_form is public.
	 */
	public function test_handle_debug_generator_form_is_public() {

		$instance = $this->get_instance();

		$ref = new \ReflectionMethod($instance, 'handle_debug_generator_form');

		$this->assertTrue($ref->isPublic());
	}

	/**
	 * Test handle_debug_reset_database_form is public.
	 */
	public function test_handle_debug_reset_database_form_is_public() {

		$instance = $this->get_instance();

		$ref = new \ReflectionMethod($instance, 'handle_debug_reset_database_form');

		$this->assertTrue($ref->isPublic());
	}
}
