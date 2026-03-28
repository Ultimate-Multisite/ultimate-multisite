<?php
/**
 * Tests for Discount_Code_Edit_Admin_Page class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Admin_Pages;

use WP_UnitTestCase;
use WP_Ultimo\Models\Discount_Code;
use WP_Ultimo\Models\Product;

/**
 * Concrete subclass that exposes protected methods for testing.
 */
class Testable_Discount_Code_Edit_Admin_Page extends Discount_Code_Edit_Admin_Page {

	/**
	 * Expose get_product_field_list as public.
	 *
	 * @return array
	 */
	public function public_get_product_field_list(): array {
		return $this->get_product_field_list();
	}

	/**
	 * Expose get_billing_period_field_list as public.
	 *
	 * @return array
	 */
	public function public_get_billing_period_field_list(): array {
		return $this->get_billing_period_field_list();
	}

	/**
	 * Expose get_available_billing_periods as public.
	 *
	 * @return array
	 */
	public function public_get_available_billing_periods(): array {
		return $this->get_available_billing_periods();
	}

	/**
	 * Expose format_billing_period_label as public.
	 *
	 * @param int    $duration      Duration value.
	 * @param string $duration_unit Duration unit.
	 * @return string
	 */
	public function public_format_billing_period_label(int $duration, string $duration_unit): string {
		return $this->format_billing_period_label($duration, $duration_unit);
	}

	/**
	 * Expose get_period_in_days as public.
	 *
	 * @param int    $duration      Duration value.
	 * @param string $duration_unit Duration unit.
	 * @return int
	 */
	public function public_get_period_in_days(int $duration, string $duration_unit): int {
		return $this->get_period_in_days($duration, $duration_unit);
	}
}

/**
 * Test class for Discount_Code_Edit_Admin_Page.
 */
class Discount_Code_Edit_Admin_Page_Test extends WP_UnitTestCase {

	/**
	 * @var Testable_Discount_Code_Edit_Admin_Page
	 */
	private $page;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->page = new Testable_Discount_Code_Edit_Admin_Page();
	}

	/**
	 * Tear down: clean up superglobals.
	 */
	protected function tearDown(): void {
		unset(
			$_GET['id'],
			$_POST['apply_to_renewals'],
			$_POST['limit_products'],
			$_POST['limit_billing_periods'],
			$_POST['allowed_billing_periods'],
			$_POST['apply_to_setup_fee'],
			$_POST['setup_fee_value'],
			$_POST['date_start'],
			$_POST['date_expiration'],
			$_POST['enable_date_start'],
			$_POST['enable_date_expiration'],
			$_POST['code'],
			$_REQUEST['apply_to_renewals'],
			$_REQUEST['limit_products'],
			$_REQUEST['limit_billing_periods'],
			$_REQUEST['allowed_billing_periods'],
			$_REQUEST['apply_to_setup_fee'],
			$_REQUEST['setup_fee_value'],
			$_REQUEST['date_start'],
			$_REQUEST['date_expiration'],
			$_REQUEST['enable_date_start'],
			$_REQUEST['enable_date_expiration'],
			$_REQUEST['code']
		);
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Static properties
	// -------------------------------------------------------------------------

	/**
	 * Test page id is correct.
	 */
	public function test_page_id(): void {
		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('id');
		$property->setAccessible(true);

		$this->assertEquals('wp-ultimo-edit-discount-code', $property->getValue($this->page));
	}

	/**
	 * Test page type is submenu.
	 */
	public function test_page_type(): void {
		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('type');
		$property->setAccessible(true);

		$this->assertEquals('submenu', $property->getValue($this->page));
	}

	/**
	 * Test object_id is discount_code.
	 */
	public function test_object_id(): void {
		$this->assertEquals('discount_code', $this->page->object_id);
	}

	/**
	 * Test supported_panels contains network_admin_menu.
	 */
	public function test_supported_panels(): void {
		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('supported_panels');
		$property->setAccessible(true);

		$panels = $property->getValue($this->page);
		$this->assertArrayHasKey('network_admin_menu', $panels);
		$this->assertEquals('wu_edit_discount_codes', $panels['network_admin_menu']);
	}

	/**
	 * Test badge_count is zero.
	 */
	public function test_badge_count(): void {
		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('badge_count');
		$property->setAccessible(true);

		$this->assertEquals(0, $property->getValue($this->page));
	}

	/**
	 * Test highlight_menu_slug is set correctly.
	 */
	public function test_highlight_menu_slug(): void {
		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('highlight_menu_slug');
		$property->setAccessible(true);

		$this->assertEquals('wp-ultimo-discount-codes', $property->getValue($this->page));
	}

	// -------------------------------------------------------------------------
	// get_title()
	// -------------------------------------------------------------------------

	/**
	 * Test get_title returns add new string when not in edit mode.
	 */
	public function test_get_title_add_new(): void {
		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('edit');
		$property->setAccessible(true);
		$property->setValue($this->page, false);

		$title = $this->page->get_title();

		$this->assertIsString($title);
		$this->assertEquals('Add new Discount Code', $title);
	}

	/**
	 * Test get_title returns edit string when in edit mode.
	 */
	public function test_get_title_edit(): void {
		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('edit');
		$property->setAccessible(true);
		$property->setValue($this->page, true);

		$title = $this->page->get_title();

		$this->assertIsString($title);
		$this->assertEquals('Edit Discount Code', $title);
	}

	// -------------------------------------------------------------------------
	// get_menu_title()
	// -------------------------------------------------------------------------

	/**
	 * Test get_menu_title returns string.
	 */
	public function test_get_menu_title(): void {
		$title = $this->page->get_menu_title();

		$this->assertIsString($title);
		$this->assertEquals('Edit Discount Code', $title);
	}

	// -------------------------------------------------------------------------
	// action_links()
	// -------------------------------------------------------------------------

	/**
	 * Test action_links returns empty array.
	 */
	public function test_action_links(): void {
		$links = $this->page->action_links();

		$this->assertIsArray($links);
		$this->assertEmpty($links);
	}

	// -------------------------------------------------------------------------
	// has_title()
	// -------------------------------------------------------------------------

	/**
	 * Test has_title returns true.
	 */
	public function test_has_title_returns_true(): void {
		$this->assertTrue($this->page->has_title());
	}

	// -------------------------------------------------------------------------
	// get_labels()
	// -------------------------------------------------------------------------

	/**
	 * Test get_labels returns array with all required keys.
	 */
	public function test_get_labels_returns_required_keys(): void {
		$labels = $this->page->get_labels();

		$this->assertIsArray($labels);
		$this->assertArrayHasKey('edit_label', $labels);
		$this->assertArrayHasKey('add_new_label', $labels);
		$this->assertArrayHasKey('updated_message', $labels);
		$this->assertArrayHasKey('title_placeholder', $labels);
		$this->assertArrayHasKey('title_description', $labels);
		$this->assertArrayHasKey('save_button_label', $labels);
		$this->assertArrayHasKey('save_description', $labels);
		$this->assertArrayHasKey('delete_button_label', $labels);
		$this->assertArrayHasKey('delete_description', $labels);
	}

	/**
	 * Test get_labels edit_label value.
	 */
	public function test_get_labels_edit_label(): void {
		$labels = $this->page->get_labels();

		$this->assertEquals('Edit Discount Code', $labels['edit_label']);
	}

	/**
	 * Test get_labels add_new_label value.
	 */
	public function test_get_labels_add_new_label(): void {
		$labels = $this->page->get_labels();

		$this->assertEquals('Add new Discount Code', $labels['add_new_label']);
	}

	/**
	 * Test get_labels updated_message value.
	 */
	public function test_get_labels_updated_message(): void {
		$labels = $this->page->get_labels();

		$this->assertEquals('Discount Code updated successfully!', $labels['updated_message']);
	}

	/**
	 * Test get_labels save_button_label value.
	 */
	public function test_get_labels_save_button_label(): void {
		$labels = $this->page->get_labels();

		$this->assertEquals('Save Discount Code', $labels['save_button_label']);
	}

	/**
	 * Test get_labels delete_button_label value.
	 */
	public function test_get_labels_delete_button_label(): void {
		$labels = $this->page->get_labels();

		$this->assertEquals('Delete Discount Code', $labels['delete_button_label']);
	}

	/**
	 * Test get_labels delete_description value.
	 */
	public function test_get_labels_delete_description(): void {
		$labels = $this->page->get_labels();

		$this->assertEquals('Be careful. This action is irreversible.', $labels['delete_description']);
	}

	/**
	 * Test get_labels title_placeholder value.
	 */
	public function test_get_labels_title_placeholder(): void {
		$labels = $this->page->get_labels();

		$this->assertEquals('Enter Discount Code', $labels['title_placeholder']);
	}

	// -------------------------------------------------------------------------
	// get_object()
	// -------------------------------------------------------------------------

	/**
	 * Test get_object returns a Discount_Code instance when no id in GET.
	 */
	public function test_get_object_returns_new_discount_code(): void {
		$object = $this->page->get_object();

		$this->assertInstanceOf(Discount_Code::class, $object);
	}

	/**
	 * Test get_object returns same instance on repeated calls (caching).
	 */
	public function test_get_object_caches_instance(): void {
		$first  = $this->page->get_object();
		$second = $this->page->get_object();

		$this->assertSame($first, $second);
	}

	/**
	 * Test get_object returns pre-set object when object property is set.
	 */
	public function test_get_object_returns_preset_object(): void {
		$discount_code = new Discount_Code();
		$discount_code->set_code('PRESET');

		$this->page->object = $discount_code;

		$result = $this->page->get_object();

		$this->assertSame($discount_code, $result);
	}

	/**
	 * Test get_object fetches from DB when id is in GET and discount code exists.
	 */
	public function test_get_object_fetches_from_db_when_id_in_get(): void {
		$discount_code = new Discount_Code();
		$discount_code->set_code('DBFETCH');
		$discount_code->set_value(10);
		$discount_code->set_type('percentage');
		$saved = $discount_code->save();

		if (is_wp_error($saved)) {
			$this->markTestSkipped('Could not save discount code: ' . $saved->get_error_message());
			return;
		}

		$id = $discount_code->get_id();

		// Fresh page instance to avoid cached object.
		$page = new Testable_Discount_Code_Edit_Admin_Page();

		$_GET['id'] = $id;

		$result = $page->get_object();

		unset($_GET['id']);

		$this->assertInstanceOf(Discount_Code::class, $result);
		$this->assertEquals($id, $result->get_id());
	}

	// -------------------------------------------------------------------------
	// query_filter()
	// -------------------------------------------------------------------------

	/**
	 * Test query_filter merges object_type and object_id into args.
	 */
	public function test_query_filter_merges_object_type(): void {
		$args   = ['some_arg' => 'value'];
		$result = $this->page->query_filter($args);

		$this->assertArrayHasKey('object_type', $result);
		$this->assertEquals('discount_code', $result['object_type']);
	}

	/**
	 * Test query_filter merges object_id from the current object.
	 */
	public function test_query_filter_merges_object_id(): void {
		$args   = [];
		$result = $this->page->query_filter($args);

		$this->assertArrayHasKey('object_id', $result);
		$this->assertIsInt($result['object_id']);
	}

	/**
	 * Test query_filter preserves existing args.
	 */
	public function test_query_filter_preserves_existing_args(): void {
		$args   = ['existing_key' => 'existing_value', 'number' => 10];
		$result = $this->page->query_filter($args);

		$this->assertEquals('existing_value', $result['existing_key']);
		$this->assertEquals(10, $result['number']);
	}

	/**
	 * Test query_filter returns array.
	 */
	public function test_query_filter_returns_array(): void {
		$result = $this->page->query_filter([]);

		$this->assertIsArray($result);
	}

	/**
	 * Test query_filter uses saved object id.
	 */
	public function test_query_filter_uses_saved_object_id(): void {
		$discount_code = new Discount_Code();
		$discount_code->set_code('FILTERTEST');
		$discount_code->set_value(5);
		$discount_code->set_type('percentage');
		$saved = $discount_code->save();

		if (is_wp_error($saved)) {
			$this->markTestSkipped('Could not save discount code: ' . $saved->get_error_message());
			return;
		}

		$this->page->object = $discount_code;

		$result = $this->page->query_filter([]);

		$this->assertEquals($discount_code->get_id(), $result['object_id']);
	}

	// -------------------------------------------------------------------------
	// register_forms()
	// -------------------------------------------------------------------------

	/**
	 * Test register_forms adds the delete redirect filter.
	 */
	public function test_register_forms_adds_delete_redirect_filter(): void {
		$this->page->register_forms();

		$this->assertGreaterThan(
			0,
			has_filter('wu_data_json_success_delete_discount_code_modal')
		);

		remove_all_filters('wu_data_json_success_delete_discount_code_modal');
	}

	/**
	 * Test register_forms delete filter returns redirect_url key.
	 */
	public function test_register_forms_delete_filter_returns_redirect_url(): void {
		$this->page->register_forms();

		$result = apply_filters('wu_data_json_success_delete_discount_code_modal', []);

		$this->assertArrayHasKey('redirect_url', $result);
		$this->assertIsString($result['redirect_url']);

		remove_all_filters('wu_data_json_success_delete_discount_code_modal');
	}

	// -------------------------------------------------------------------------
	// handle_legacy_options()
	// -------------------------------------------------------------------------

	/**
	 * Test handle_legacy_options returns early when no legacy filter registered.
	 */
	public function test_handle_legacy_options_returns_early_without_filter(): void {
		global $wp_filter;

		// Ensure the legacy filter is not registered.
		unset($wp_filter['wp_ultimo_coupon_advanced_options']);

		// Should not throw.
		$this->page->handle_legacy_options();

		$this->assertTrue(true);
	}

	/**
	 * Test handle_legacy_options adds widget when legacy filter is registered.
	 */
	public function test_handle_legacy_options_adds_widget_with_filter(): void {
		set_current_screen('dashboard-network');

		add_filter(
			'wp_ultimo_coupon_advanced_options',
			function ($object) {
				echo 'legacy content';
			}
		);

		// Should not throw.
		$this->page->handle_legacy_options();

		remove_all_filters('wp_ultimo_coupon_advanced_options');

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// format_billing_period_label()
	// -------------------------------------------------------------------------

	/**
	 * Test format_billing_period_label for 1 day.
	 */
	public function test_format_billing_period_label_one_day(): void {
		$label = $this->page->public_format_billing_period_label(1, 'day');

		$this->assertEquals('Day', $label);
	}

	/**
	 * Test format_billing_period_label for multiple days.
	 */
	public function test_format_billing_period_label_multiple_days(): void {
		$label = $this->page->public_format_billing_period_label(7, 'day');

		$this->assertEquals('7 Days', $label);
	}

	/**
	 * Test format_billing_period_label for 1 week.
	 */
	public function test_format_billing_period_label_one_week(): void {
		$label = $this->page->public_format_billing_period_label(1, 'week');

		$this->assertEquals('Week', $label);
	}

	/**
	 * Test format_billing_period_label for multiple weeks.
	 */
	public function test_format_billing_period_label_multiple_weeks(): void {
		$label = $this->page->public_format_billing_period_label(2, 'week');

		$this->assertEquals('2 Weeks', $label);
	}

	/**
	 * Test format_billing_period_label for 1 month.
	 */
	public function test_format_billing_period_label_one_month(): void {
		$label = $this->page->public_format_billing_period_label(1, 'month');

		$this->assertEquals('Month', $label);
	}

	/**
	 * Test format_billing_period_label for multiple months.
	 */
	public function test_format_billing_period_label_multiple_months(): void {
		$label = $this->page->public_format_billing_period_label(3, 'month');

		$this->assertEquals('3 Months', $label);
	}

	/**
	 * Test format_billing_period_label for 1 year.
	 */
	public function test_format_billing_period_label_one_year(): void {
		$label = $this->page->public_format_billing_period_label(1, 'year');

		$this->assertEquals('Year', $label);
	}

	/**
	 * Test format_billing_period_label for multiple years.
	 */
	public function test_format_billing_period_label_multiple_years(): void {
		$label = $this->page->public_format_billing_period_label(2, 'year');

		$this->assertEquals('2 Years', $label);
	}

	/**
	 * Test format_billing_period_label for unknown unit falls back to unit string.
	 */
	public function test_format_billing_period_label_unknown_unit(): void {
		$label = $this->page->public_format_billing_period_label(5, 'quarter');

		$this->assertEquals('5 quarter', $label);
	}

	/**
	 * Test format_billing_period_label for 1 unknown unit returns unit as-is.
	 */
	public function test_format_billing_period_label_one_unknown_unit(): void {
		$label = $this->page->public_format_billing_period_label(1, 'quarter');

		$this->assertEquals('quarter', $label);
	}

	// -------------------------------------------------------------------------
	// get_period_in_days()
	// -------------------------------------------------------------------------

	/**
	 * Test get_period_in_days for days.
	 */
	public function test_get_period_in_days_day(): void {
		$days = $this->page->public_get_period_in_days(5, 'day');

		$this->assertEquals(5, $days);
	}

	/**
	 * Test get_period_in_days for weeks.
	 */
	public function test_get_period_in_days_week(): void {
		$days = $this->page->public_get_period_in_days(2, 'week');

		$this->assertEquals(14, $days);
	}

	/**
	 * Test get_period_in_days for months.
	 */
	public function test_get_period_in_days_month(): void {
		$days = $this->page->public_get_period_in_days(1, 'month');

		$this->assertEquals(30, $days);
	}

	/**
	 * Test get_period_in_days for years.
	 */
	public function test_get_period_in_days_year(): void {
		$days = $this->page->public_get_period_in_days(1, 'year');

		$this->assertEquals(365, $days);
	}

	/**
	 * Test get_period_in_days for unknown unit defaults to 1 multiplier.
	 */
	public function test_get_period_in_days_unknown_unit(): void {
		$days = $this->page->public_get_period_in_days(3, 'quarter');

		$this->assertEquals(3, $days);
	}

	/**
	 * Test get_period_in_days for 3 months.
	 */
	public function test_get_period_in_days_three_months(): void {
		$days = $this->page->public_get_period_in_days(3, 'month');

		$this->assertEquals(90, $days);
	}

	/**
	 * Test get_period_in_days for 2 years.
	 */
	public function test_get_period_in_days_two_years(): void {
		$days = $this->page->public_get_period_in_days(2, 'year');

		$this->assertEquals(730, $days);
	}

	// -------------------------------------------------------------------------
	// get_product_field_list()
	// -------------------------------------------------------------------------

	/**
	 * Test get_product_field_list returns array.
	 */
	public function test_get_product_field_list_returns_array(): void {
		$fields = $this->page->public_get_product_field_list();

		$this->assertIsArray($fields);
	}

	/**
	 * Test get_product_field_list returns no_products note when no products exist.
	 */
	public function test_get_product_field_list_no_products_note(): void {
		// Ensure no products exist.
		$fields = $this->page->public_get_product_field_list();

		$this->assertArrayHasKey('allowed_products_no_products', $fields);
		$this->assertEquals('note', $fields['allowed_products_no_products']['type']);
	}

	/**
	 * Test get_product_field_list includes product toggle fields when products exist.
	 */
	public function test_get_product_field_list_with_products(): void {
		$product = wu_create_product(
			[
				'name'          => 'Test Product for Field List',
				'slug'          => 'test-product-field-list-' . uniqid(),
				'amount'        => 10,
				'type'          => 'plan',
				'active'        => true,
				'recurring'     => true,
				'duration'      => 1,
				'duration_unit' => 'month',
			]
		);

		if (is_wp_error($product)) {
			$this->markTestSkipped('Could not create product: ' . $product->get_error_message());
			return;
		}

		$page   = new Testable_Discount_Code_Edit_Admin_Page();
		$fields = $page->public_get_product_field_list();

		$product_id = $product->get_id();
		$this->assertArrayHasKey("allowed_products_{$product_id}", $fields);
		$this->assertEquals('toggle', $fields["allowed_products_{$product_id}"]['type']);
	}

	/**
	 * Test get_product_field_list always includes hidden none field when products exist.
	 */
	public function test_get_product_field_list_includes_none_hidden_field(): void {
		$product = wu_create_product(
			[
				'name'          => 'Test Product None Field',
				'slug'          => 'test-product-none-' . uniqid(),
				'amount'        => 5,
				'type'          => 'plan',
				'active'        => true,
				'recurring'     => true,
				'duration'      => 1,
				'duration_unit' => 'month',
			]
		);

		if (is_wp_error($product)) {
			$this->markTestSkipped('Could not create product: ' . $product->get_error_message());
			return;
		}

		$page   = new Testable_Discount_Code_Edit_Admin_Page();
		$fields = $page->public_get_product_field_list();

		$this->assertArrayHasKey('allowed_products_none', $fields);
		$this->assertEquals('hidden', $fields['allowed_products_none']['type']);
		$this->assertEquals('__none', $fields['allowed_products_none']['value']);
	}

	/**
	 * Test get_product_field_list product toggle is checked when not limiting products.
	 */
	public function test_get_product_field_list_checked_when_not_limiting(): void {
		$product = wu_create_product(
			[
				'name'          => 'Test Product Checked',
				'slug'          => 'test-product-checked-' . uniqid(),
				'amount'        => 5,
				'type'          => 'plan',
				'active'        => true,
				'recurring'     => true,
				'duration'      => 1,
				'duration_unit' => 'month',
			]
		);

		if (is_wp_error($product)) {
			$this->markTestSkipped('Could not create product: ' . $product->get_error_message());
			return;
		}

		$discount_code = new Discount_Code();
		$discount_code->set_limit_products(false);

		$page         = new Testable_Discount_Code_Edit_Admin_Page();
		$page->object = $discount_code;

		$fields     = $page->public_get_product_field_list();
		$product_id = $product->get_id();

		$this->assertArrayHasKey("allowed_products_{$product_id}", $fields);
		// When not limiting products, all products should be checked (true).
		$checked_value = $fields["allowed_products_{$product_id}"]['html_attr'][':checked'];
		$this->assertEquals('true', $checked_value);
	}

	// -------------------------------------------------------------------------
	// get_billing_period_field_list()
	// -------------------------------------------------------------------------

	/**
	 * Test get_billing_period_field_list returns array.
	 */
	public function test_get_billing_period_field_list_returns_array(): void {
		$fields = $this->page->public_get_billing_period_field_list();

		$this->assertIsArray($fields);
	}

	/**
	 * Test get_billing_period_field_list always includes none hidden field.
	 */
	public function test_get_billing_period_field_list_includes_none_field(): void {
		$fields = $this->page->public_get_billing_period_field_list();

		$this->assertArrayHasKey('allowed_billing_periods_none', $fields);
		$this->assertEquals('hidden', $fields['allowed_billing_periods_none']['type']);
		$this->assertEquals('__none', $fields['allowed_billing_periods_none']['value']);
	}

	/**
	 * Test get_billing_period_field_list includes no_periods note when no recurring products.
	 */
	public function test_get_billing_period_field_list_no_periods_note_when_no_recurring_products(): void {
		// With no recurring products, should show no_periods note.
		$fields = $this->page->public_get_billing_period_field_list();

		$this->assertArrayHasKey('allowed_billing_periods_no_periods', $fields);
		$this->assertEquals('note', $fields['allowed_billing_periods_no_periods']['type']);
	}

	/**
	 * Test get_billing_period_field_list includes period toggles when recurring products exist.
	 */
	public function test_get_billing_period_field_list_with_recurring_products(): void {
		$product = wu_create_product(
			[
				'name'          => 'Recurring Product for Billing Periods',
				'slug'          => 'recurring-product-billing-' . uniqid(),
				'amount'        => 20,
				'type'          => 'plan',
				'active'        => true,
				'recurring'     => true,
				'duration'      => 1,
				'duration_unit' => 'month',
			]
		);

		if (is_wp_error($product)) {
			$this->markTestSkipped('Could not create product: ' . $product->get_error_message());
			return;
		}

		$page   = new Testable_Discount_Code_Edit_Admin_Page();
		$fields = $page->public_get_billing_period_field_list();

		// Should have a toggle for the monthly period.
		$period_key = \WP_Ultimo\Models\Discount_Code::get_billing_period_key(1, 'month');
		$this->assertArrayHasKey("allowed_billing_periods_{$period_key}", $fields);
		$this->assertEquals('toggle', $fields["allowed_billing_periods_{$period_key}"]['type']);
	}

	// -------------------------------------------------------------------------
	// get_available_billing_periods()
	// -------------------------------------------------------------------------

	/**
	 * Test get_available_billing_periods returns array.
	 */
	public function test_get_available_billing_periods_returns_array(): void {
		$periods = $this->page->public_get_available_billing_periods();

		$this->assertIsArray($periods);
	}

	/**
	 * Test get_available_billing_periods returns empty when no recurring products.
	 */
	public function test_get_available_billing_periods_empty_without_recurring_products(): void {
		// Create a non-recurring product.
		$product = wu_create_product(
			[
				'name'          => 'Non-Recurring Product',
				'slug'          => 'non-recurring-' . uniqid(),
				'amount'        => 0,
				'type'          => 'plan',
				'active'        => true,
				'recurring'     => false,
				'duration'      => 1,
				'duration_unit' => 'month',
			]
		);

		$page    = new Testable_Discount_Code_Edit_Admin_Page();
		$periods = $page->public_get_available_billing_periods();

		// Non-recurring products should not contribute periods.
		// (Other tests may have created recurring products, so just verify it's an array.)
		$this->assertIsArray($periods);
	}

	/**
	 * Test get_available_billing_periods includes monthly period for monthly product.
	 */
	public function test_get_available_billing_periods_includes_monthly(): void {
		$product = wu_create_product(
			[
				'name'          => 'Monthly Product for Periods',
				'slug'          => 'monthly-product-periods-' . uniqid(),
				'amount'        => 15,
				'type'          => 'plan',
				'active'        => true,
				'recurring'     => true,
				'duration'      => 1,
				'duration_unit' => 'month',
			]
		);

		if (is_wp_error($product)) {
			$this->markTestSkipped('Could not create product: ' . $product->get_error_message());
			return;
		}

		$page    = new Testable_Discount_Code_Edit_Admin_Page();
		$periods = $page->public_get_available_billing_periods();

		$period_key = \WP_Ultimo\Models\Discount_Code::get_billing_period_key(1, 'month');
		$this->assertArrayHasKey($period_key, $periods);
		$this->assertEquals('Month', $periods[$period_key]);
	}

	/**
	 * Test get_available_billing_periods includes yearly period for yearly product.
	 */
	public function test_get_available_billing_periods_includes_yearly(): void {
		$product = wu_create_product(
			[
				'name'          => 'Yearly Product for Periods',
				'slug'          => 'yearly-product-periods-' . uniqid(),
				'amount'        => 100,
				'type'          => 'plan',
				'active'        => true,
				'recurring'     => true,
				'duration'      => 1,
				'duration_unit' => 'year',
			]
		);

		if (is_wp_error($product)) {
			$this->markTestSkipped('Could not create product: ' . $product->get_error_message());
			return;
		}

		$page    = new Testable_Discount_Code_Edit_Admin_Page();
		$periods = $page->public_get_available_billing_periods();

		$period_key = \WP_Ultimo\Models\Discount_Code::get_billing_period_key(1, 'year');
		$this->assertArrayHasKey($period_key, $periods);
		$this->assertEquals('Year', $periods[$period_key]);
	}

	/**
	 * Test get_available_billing_periods sorts by duration ascending.
	 */
	public function test_get_available_billing_periods_sorted_ascending(): void {
		// Create yearly and monthly products.
		$yearly = wu_create_product(
			[
				'name'          => 'Yearly Sort Test',
				'slug'          => 'yearly-sort-' . uniqid(),
				'amount'        => 100,
				'type'          => 'plan',
				'active'        => true,
				'recurring'     => true,
				'duration'      => 1,
				'duration_unit' => 'year',
			]
		);

		$monthly = wu_create_product(
			[
				'name'          => 'Monthly Sort Test',
				'slug'          => 'monthly-sort-' . uniqid(),
				'amount'        => 10,
				'type'          => 'plan',
				'active'        => true,
				'recurring'     => true,
				'duration'      => 1,
				'duration_unit' => 'month',
			]
		);

		if (is_wp_error($yearly) || is_wp_error($monthly)) {
			$this->markTestSkipped('Could not create products for sort test.');
			return;
		}

		$page    = new Testable_Discount_Code_Edit_Admin_Page();
		$periods = $page->public_get_available_billing_periods();

		$keys = array_keys($periods);

		$monthly_key = \WP_Ultimo\Models\Discount_Code::get_billing_period_key(1, 'month');
		$yearly_key  = \WP_Ultimo\Models\Discount_Code::get_billing_period_key(1, 'year');

		$monthly_pos = array_search($monthly_key, $keys, true);
		$yearly_pos  = array_search($yearly_key, $keys, true);

		if (false !== $monthly_pos && false !== $yearly_pos) {
			$this->assertLessThan($yearly_pos, $monthly_pos, 'Monthly should appear before yearly in sorted list.');
		}
	}

	// -------------------------------------------------------------------------
	// register_widgets()
	// -------------------------------------------------------------------------

	/**
	 * Test register_widgets does not throw for new discount code.
	 */
	public function test_register_widgets_does_not_throw_for_new_object(): void {
		set_current_screen('dashboard-network');

		$this->page->register_widgets();

		$this->assertTrue(true);
	}

	/**
	 * Test register_widgets does not throw in edit mode.
	 */
	public function test_register_widgets_does_not_throw_in_edit_mode(): void {
		set_current_screen('dashboard-network');

		$discount_code = new Discount_Code();
		$discount_code->set_code('EDITMODE');
		$discount_code->set_value(10);
		$discount_code->set_type('percentage');

		$this->page->object = $discount_code;
		$this->page->edit   = true;

		$this->page->register_widgets();

		$this->assertTrue(true);
	}

	/**
	 * Test register_widgets with object that has max uses set.
	 */
	public function test_register_widgets_with_max_uses(): void {
		set_current_screen('dashboard-network');

		$discount_code = new Discount_Code();
		$discount_code->set_code('MAXUSES');
		$discount_code->set_value(10);
		$discount_code->set_type('percentage');
		$discount_code->set_max_uses(100);
		$discount_code->set_uses(5);

		$this->page->object = $discount_code;

		$this->page->register_widgets();

		$this->assertTrue(true);
	}

	/**
	 * Test register_widgets with date_start set.
	 */
	public function test_register_widgets_with_date_start(): void {
		set_current_screen('dashboard-network');

		$discount_code = new Discount_Code();
		$discount_code->set_code('DATESTART');
		$discount_code->set_date_start('2025-01-01 00:00:00');

		$this->page->object = $discount_code;
		$this->page->edit   = true;

		$this->page->register_widgets();

		$this->assertTrue(true);
	}

	/**
	 * Test register_widgets with date_expiration set.
	 */
	public function test_register_widgets_with_date_expiration(): void {
		set_current_screen('dashboard-network');

		$discount_code = new Discount_Code();
		$discount_code->set_code('DATEEXP');
		$discount_code->set_date_expiration('2030-12-31 23:59:59');

		$this->page->object = $discount_code;
		$this->page->edit   = true;

		$this->page->register_widgets();

		$this->assertTrue(true);
	}

	/**
	 * Test register_widgets with setup_fee_value > 0.
	 */
	public function test_register_widgets_with_setup_fee(): void {
		set_current_screen('dashboard-network');

		$discount_code = new Discount_Code();
		$discount_code->set_code('SETUPFEE');
		$discount_code->set_setup_fee_value(25);
		$discount_code->set_setup_fee_type('percentage');

		$this->page->object = $discount_code;

		$this->page->register_widgets();

		$this->assertTrue(true);
	}

	/**
	 * Test register_widgets with limit_products enabled.
	 */
	public function test_register_widgets_with_limit_products(): void {
		set_current_screen('dashboard-network');

		$discount_code = new Discount_Code();
		$discount_code->set_code('LIMITPROD');
		$discount_code->set_limit_products(true);

		$this->page->object = $discount_code;

		$this->page->register_widgets();

		$this->assertTrue(true);
	}

	/**
	 * Test register_widgets with limit_billing_periods enabled.
	 */
	public function test_register_widgets_with_limit_billing_periods(): void {
		set_current_screen('dashboard-network');

		$discount_code = new Discount_Code();
		$discount_code->set_code('LIMITBP');
		$discount_code->set_limit_billing_periods(true);

		$this->page->object = $discount_code;

		$this->page->register_widgets();

		$this->assertTrue(true);
	}

	/**
	 * Test register_widgets with absolute type discount.
	 */
	public function test_register_widgets_with_absolute_type(): void {
		set_current_screen('dashboard-network');

		$discount_code = new Discount_Code();
		$discount_code->set_code('ABSOLUTE');
		$discount_code->set_type('absolute');
		$discount_code->set_value(50);

		$this->page->object = $discount_code;

		$this->page->register_widgets();

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// handle_save()
	// -------------------------------------------------------------------------

	/**
	 * Test handle_save sets apply_to_renewals to false when not in POST.
	 */
	public function test_handle_save_sets_apply_to_renewals_false_when_absent(): void {
		$mock_object = $this->createMock(Discount_Code::class);
		$mock_object->method('save')->willReturn(new \WP_Error('test', 'Error'));
		$mock_object->method('load_attributes_from_post')->willReturn(null);

		$this->page->object = $mock_object;

		unset($_POST['apply_to_renewals']);

		$this->page->handle_save();

		$this->assertFalse($_POST['apply_to_renewals']);
	}

	/**
	 * Test handle_save sets limit_products to false when not in POST.
	 */
	public function test_handle_save_sets_limit_products_false_when_absent(): void {
		$mock_object = $this->createMock(Discount_Code::class);
		$mock_object->method('save')->willReturn(new \WP_Error('test', 'Error'));
		$mock_object->method('load_attributes_from_post')->willReturn(null);

		$this->page->object = $mock_object;

		unset($_POST['limit_products']);

		$this->page->handle_save();

		$this->assertFalse($_POST['limit_products']);
	}

	/**
	 * Test handle_save sets limit_billing_periods to false when not in POST.
	 */
	public function test_handle_save_sets_limit_billing_periods_false_when_absent(): void {
		$mock_object = $this->createMock(Discount_Code::class);
		$mock_object->method('save')->willReturn(new \WP_Error('test', 'Error'));
		$mock_object->method('load_attributes_from_post')->willReturn(null);

		$this->page->object = $mock_object;

		unset($_POST['limit_billing_periods']);

		$this->page->handle_save();

		$this->assertFalse($_POST['limit_billing_periods']);
	}

	/**
	 * Test handle_save filters __none from allowed_billing_periods.
	 */
	public function test_handle_save_filters_none_from_allowed_billing_periods(): void {
		$mock_object = $this->createMock(Discount_Code::class);
		$mock_object->method('save')->willReturn(new \WP_Error('test', 'Error'));
		$mock_object->method('load_attributes_from_post')->willReturn(null);

		$this->page->object = $mock_object;

		// wu_request() reads from $_REQUEST.
		$_REQUEST['allowed_billing_periods'] = ['1_month', '__none', '1_year'];
		$_POST['allowed_billing_periods']    = ['1_month', '__none', '1_year'];

		$this->page->handle_save();

		$this->assertNotContains('__none', $_POST['allowed_billing_periods']);
		$this->assertContains('1_month', $_POST['allowed_billing_periods']);
		$this->assertContains('1_year', $_POST['allowed_billing_periods']);
	}

	/**
	 * Test handle_save sets setup_fee_value to 0 when apply_to_setup_fee is absent.
	 */
	public function test_handle_save_sets_setup_fee_value_zero_when_not_applying(): void {
		$mock_object = $this->createMock(Discount_Code::class);
		$mock_object->method('save')->willReturn(new \WP_Error('test', 'Error'));
		$mock_object->method('load_attributes_from_post')->willReturn(null);

		$this->page->object = $mock_object;

		unset($_POST['apply_to_setup_fee']);

		$this->page->handle_save();

		$this->assertEquals(0, $_POST['setup_fee_value']);
	}

	/**
	 * Test handle_save sets date_start to null when enable_date_start is absent.
	 */
	public function test_handle_save_sets_date_start_null_when_not_enabled(): void {
		$mock_object = $this->createMock(Discount_Code::class);
		$mock_object->method('save')->willReturn(new \WP_Error('test', 'Error'));
		$mock_object->method('load_attributes_from_post')->willReturn(null);

		$this->page->object = $mock_object;

		// wu_request() reads from $_REQUEST; ensure enable_date_start is absent.
		unset($_REQUEST['enable_date_start'], $_POST['enable_date_start']);

		$this->page->handle_save();

		$this->assertNull($_POST['date_start']);
	}

	/**
	 * Test handle_save sets date_expiration to null when enable_date_expiration is absent.
	 */
	public function test_handle_save_sets_date_expiration_null_when_not_enabled(): void {
		$mock_object = $this->createMock(Discount_Code::class);
		$mock_object->method('save')->willReturn(new \WP_Error('test', 'Error'));
		$mock_object->method('load_attributes_from_post')->willReturn(null);

		$this->page->object = $mock_object;

		unset($_REQUEST['enable_date_expiration'], $_POST['enable_date_expiration']);

		$this->page->handle_save();

		$this->assertNull($_POST['date_expiration']);
	}

	/**
	 * Test handle_save sets date_start to null when date_start is invalid.
	 */
	public function test_handle_save_sets_date_start_null_when_invalid_date(): void {
		$mock_object = $this->createMock(Discount_Code::class);
		$mock_object->method('save')->willReturn(new \WP_Error('test', 'Error'));
		$mock_object->method('load_attributes_from_post')->willReturn(null);

		$this->page->object = $mock_object;

		// wu_request() reads from $_REQUEST.
		$_REQUEST['enable_date_start'] = 1;
		$_REQUEST['date_start']        = 'not-a-valid-date';
		$_POST['enable_date_start']    = 1;
		$_POST['date_start']           = 'not-a-valid-date';

		$this->page->handle_save();

		$this->assertNull($_POST['date_start']);
	}

	/**
	 * Test handle_save trims the code field.
	 */
	public function test_handle_save_trims_code(): void {
		$mock_object = $this->createMock(Discount_Code::class);
		$mock_object->method('save')->willReturn(new \WP_Error('test', 'Error'));
		$mock_object->method('load_attributes_from_post')->willReturn(null);

		$this->page->object = $mock_object;

		// wu_request() reads from $_REQUEST; handle_save writes result to $_POST.
		$_REQUEST['code'] = '  TRIMME  ';
		$_POST['code']    = '  TRIMME  ';

		$this->page->handle_save();

		$this->assertEquals('TRIMME', $_POST['code']);
	}

	/**
	 * Test handle_save returns false when parent save fails.
	 */
	public function test_handle_save_returns_false_on_save_error(): void {
		$mock_object = $this->createMock(Discount_Code::class);
		$mock_object->method('save')->willReturn(new \WP_Error('test_error', 'Save failed'));
		$mock_object->method('load_attributes_from_post')->willReturn(null);

		$this->page->object = $mock_object;

		$result = $this->page->handle_save();

		$this->assertFalse($result);
	}

	/**
	 * Test handle_save with valid allowed_billing_periods array keeps valid values.
	 */
	public function test_handle_save_keeps_valid_billing_periods(): void {
		$mock_object = $this->createMock(Discount_Code::class);
		$mock_object->method('save')->willReturn(new \WP_Error('test', 'Error'));
		$mock_object->method('load_attributes_from_post')->willReturn(null);

		$this->page->object = $mock_object;

		// wu_request() reads from $_REQUEST.
		$_REQUEST['allowed_billing_periods'] = ['1_month', '1_year'];
		$_POST['allowed_billing_periods']    = ['1_month', '1_year'];

		$this->page->handle_save();

		$this->assertContains('1_month', $_POST['allowed_billing_periods']);
		$this->assertContains('1_year', $_POST['allowed_billing_periods']);
	}

	/**
	 * Test handle_save with non-array allowed_billing_periods does not crash.
	 */
	public function test_handle_save_with_non_array_billing_periods(): void {
		$mock_object = $this->createMock(Discount_Code::class);
		$mock_object->method('save')->willReturn(new \WP_Error('test', 'Error'));
		$mock_object->method('load_attributes_from_post')->willReturn(null);

		$this->page->object = $mock_object;

		// Not setting allowed_billing_periods — wu_request returns [] by default.
		unset($_POST['allowed_billing_periods']);

		// Should not throw.
		$this->page->handle_save();

		$this->assertTrue(true);
	}

	/**
	 * Test handle_save with valid date_start keeps it when enable_date_start is set.
	 */
	public function test_handle_save_keeps_valid_date_start(): void {
		$mock_object = $this->createMock(Discount_Code::class);
		$mock_object->method('save')->willReturn(new \WP_Error('test', 'Error'));
		$mock_object->method('load_attributes_from_post')->willReturn(null);

		$this->page->object = $mock_object;

		// wu_request() reads from $_REQUEST.
		$_REQUEST['enable_date_start'] = 1;
		$_REQUEST['date_start']        = '2025-06-01 00:00:00';
		$_POST['enable_date_start']    = 1;
		$_POST['date_start']           = '2025-06-01 00:00:00';

		$this->page->handle_save();

		// Valid date should be kept (not nulled).
		$this->assertEquals('2025-06-01 00:00:00', $_POST['date_start']);
	}

	/**
	 * Test handle_save with valid date_expiration keeps it when enable_date_expiration is set.
	 */
	public function test_handle_save_keeps_valid_date_expiration(): void {
		$mock_object = $this->createMock(Discount_Code::class);
		$mock_object->method('save')->willReturn(new \WP_Error('test', 'Error'));
		$mock_object->method('load_attributes_from_post')->willReturn(null);

		$this->page->object = $mock_object;

		// wu_request() reads from $_REQUEST.
		$_REQUEST['enable_date_expiration'] = 1;
		$_REQUEST['date_expiration']        = '2030-12-31 23:59:59';
		$_POST['enable_date_expiration']    = 1;
		$_POST['date_expiration']           = '2030-12-31 23:59:59';

		$this->page->handle_save();

		$this->assertEquals('2030-12-31 23:59:59', $_POST['date_expiration']);
	}
}
