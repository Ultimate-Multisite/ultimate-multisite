<?php
/**
 * Tests for Product_Edit_Admin_Page class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Admin_Pages;

use WP_UnitTestCase;
use WP_Ultimo\Models\Product;
use WP_Ultimo\Database\Products\Product_Type;
use WP_Ultimo\Limitations\Limit_Site_Templates;

/**
 * Concrete subclass that exposes protected methods for testing.
 */
class Testable_Product_Edit_Admin_Page extends Product_Edit_Admin_Page {

	/**
	 * Expose get_product_option_sections as public.
	 *
	 * @return array
	 */
	public function public_get_product_option_sections(): array {
		return $this->get_product_option_sections();
	}
}

/**
 * Test class for Product_Edit_Admin_Page.
 */
class Product_Edit_Admin_Page_Test extends WP_UnitTestCase {

	/**
	 * @var Testable_Product_Edit_Admin_Page
	 */
	private $page;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->page = new Testable_Product_Edit_Admin_Page();
	}

	/**
	 * Tear down: clean up superglobals.
	 */
	protected function tearDown(): void {
		unset(
			$_GET['id'],
			$_POST['recurring'],
			$_REQUEST['recurring'],
			$_POST['legacy_options'],
			$_REQUEST['legacy_options'],
			$_POST['featured_plan'],
			$_REQUEST['featured_plan'],
			$_POST['has_setup_fee'],
			$_REQUEST['has_setup_fee'],
			$_POST['setup_fee'],
			$_REQUEST['setup_fee'],
			$_POST['has_trial'],
			$_REQUEST['has_trial'],
			$_POST['trial_duration'],
			$_REQUEST['trial_duration'],
			$_POST['price_variations'],
			$_REQUEST['price_variations'],
			$_POST['available_addons'],
			$_REQUEST['available_addons'],
			$_POST['taxable'],
			$_REQUEST['taxable'],
			$_POST['wu-new-model'],
			$_REQUEST['wu-new-model'],
			$_POST['re_assignment_product_id'],
			$_REQUEST['re_assignment_product_id']
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

		$this->assertEquals('wp-ultimo-edit-product', $property->getValue($this->page));
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
	 * Test object_id is product.
	 */
	public function test_object_id(): void {
		$this->assertEquals('product', $this->page->object_id);
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
		$this->assertEquals('wu_edit_products', $panels['network_admin_menu']);
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

		$this->assertEquals('wp-ultimo-products', $property->getValue($this->page));
	}

	/**
	 * Test parent is none.
	 */
	public function test_parent_is_none(): void {
		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('parent');
		$property->setAccessible(true);

		$this->assertEquals('none', $property->getValue($this->page));
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
		$this->assertEquals('Add new Product', $title);
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
		$this->assertEquals('Edit Product', $title);
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
		$this->assertEquals('Edit Product', $title);
	}

	// -------------------------------------------------------------------------
	// has_title()
	// -------------------------------------------------------------------------

	/**
	 * Test has_title returns true (products have a title field).
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

		$this->assertEquals('Edit Product', $labels['edit_label']);
	}

	/**
	 * Test get_labels add_new_label value.
	 */
	public function test_get_labels_add_new_label(): void {
		$labels = $this->page->get_labels();

		$this->assertEquals('Add new Product', $labels['add_new_label']);
	}

	/**
	 * Test get_labels updated_message value.
	 */
	public function test_get_labels_updated_message(): void {
		$labels = $this->page->get_labels();

		$this->assertEquals('Product updated with success!', $labels['updated_message']);
	}

	/**
	 * Test get_labels save_button_label value.
	 */
	public function test_get_labels_save_button_label(): void {
		$labels = $this->page->get_labels();

		$this->assertEquals('Save Product', $labels['save_button_label']);
	}

	/**
	 * Test get_labels delete_button_label value.
	 */
	public function test_get_labels_delete_button_label(): void {
		$labels = $this->page->get_labels();

		$this->assertEquals('Delete Product', $labels['delete_button_label']);
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

		$this->assertEquals('Enter Product Name', $labels['title_placeholder']);
	}

	/**
	 * Test get_labels save_description is empty string.
	 */
	public function test_get_labels_save_description_empty(): void {
		$labels = $this->page->get_labels();

		$this->assertEquals('', $labels['save_description']);
	}

	// -------------------------------------------------------------------------
	// get_object()
	// -------------------------------------------------------------------------

	/**
	 * Test get_object returns a Product instance when no id in GET.
	 */
	public function test_get_object_returns_new_product(): void {
		$object = $this->page->get_object();

		$this->assertInstanceOf(Product::class, $object);
	}

	/**
	 * Test get_object returns same instance on repeated calls (caching via $this->object).
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
		$product = new Product();
		$product->set_name('Preset Product');

		$this->page->object = $product;

		$result = $this->page->get_object();

		$this->assertSame($product, $result);
	}

	/**
	 * Test get_object fetches from DB when id is in GET and product exists.
	 */
	public function test_get_object_fetches_from_db_when_id_in_get(): void {
		$product = wu_create_product(
			[
				'name'          => 'DB Fetch Product',
				'slug'          => 'db-fetch-product-' . uniqid(),
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

		$id = $product->get_id();

		// Fresh page instance to avoid cached object.
		$page = new Testable_Product_Edit_Admin_Page();

		$_GET['id'] = $id;

		$result = $page->get_object();

		unset($_GET['id']);

		$this->assertInstanceOf(Product::class, $result);
		$this->assertEquals($id, $result->get_id());
	}

	// -------------------------------------------------------------------------
	// query_filter()
	// -------------------------------------------------------------------------

	/**
	 * Test query_filter merges object_type product into args.
	 */
	public function test_query_filter_merges_object_type(): void {
		$args   = ['some_arg' => 'value'];
		$result = $this->page->query_filter($args);

		$this->assertArrayHasKey('object_type', $result);
		$this->assertEquals('product', $result['object_type']);
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
		$product = wu_create_product(
			[
				'name'          => 'Filter Test Product',
				'slug'          => 'filter-test-product-' . uniqid(),
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

		$this->page->object = $product;

		$result = $this->page->query_filter([]);

		$this->assertEquals($product->get_id(), $result['object_id']);
	}

	// -------------------------------------------------------------------------
	// action_links()
	// -------------------------------------------------------------------------

	/**
	 * Test action_links returns empty array for non-plan product.
	 */
	public function test_action_links_empty_for_non_plan(): void {
		$product = new Product();
		$product->set_type('service');

		$this->page->object = $product;
		$this->page->edit   = true;

		$links = $this->page->action_links();

		$this->assertIsArray($links);
		$this->assertEmpty($links);
	}

	/**
	 * Test action_links returns empty array when not in edit mode.
	 */
	public function test_action_links_empty_when_not_edit_mode(): void {
		$product = new Product();
		$product->set_type('plan');

		$this->page->object = $product;
		$this->page->edit   = false;

		$links = $this->page->action_links();

		$this->assertIsArray($links);
		$this->assertEmpty($links);
	}

	/**
	 * Test action_links includes shareable link for plan in edit mode.
	 */
	public function test_action_links_includes_shareable_link_for_plan_in_edit_mode(): void {
		$product = wu_create_product(
			[
				'name'          => 'Plan With Shareable Link',
				'slug'          => 'plan-shareable-' . uniqid(),
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

		$this->page->object = $product;
		$this->page->edit   = true;

		$links = $this->page->action_links();

		$this->assertIsArray($links);
		$this->assertNotEmpty($links);
		$this->assertEquals('Click to copy Shareable Link', $links[0]['label']);
	}

	// -------------------------------------------------------------------------
	// register_forms()
	// -------------------------------------------------------------------------

	/**
	 * Test register_forms adds the delete product modal filter.
	 */
	public function test_register_forms_adds_delete_product_filter(): void {
		$this->page->register_forms();

		$this->assertGreaterThan(
			0,
			has_filter('wu_form_fields_delete_product_modal')
		);

		remove_all_filters('wu_form_fields_delete_product_modal');
		remove_all_actions('wu_after_delete_product_modal');
		remove_all_actions("wu_page_wp-ultimo-edit-product_load");
	}

	/**
	 * Test register_forms adds the after delete product action.
	 */
	public function test_register_forms_adds_after_delete_action(): void {
		$this->page->register_forms();

		$this->assertGreaterThan(
			0,
			has_action('wu_after_delete_product_modal')
		);

		remove_all_filters('wu_form_fields_delete_product_modal');
		remove_all_actions('wu_after_delete_product_modal');
		remove_all_actions("wu_page_wp-ultimo-edit-product_load");
	}

	/**
	 * Test register_forms adds the page load action.
	 */
	public function test_register_forms_adds_page_load_action(): void {
		$this->page->register_forms();

		$this->assertGreaterThan(
			0,
			has_action('wu_page_wp-ultimo-edit-product_load')
		);

		remove_all_filters('wu_form_fields_delete_product_modal');
		remove_all_actions('wu_after_delete_product_modal');
		remove_all_actions("wu_page_wp-ultimo-edit-product_load");
	}

	// -------------------------------------------------------------------------
	// product_extra_delete_fields()
	// -------------------------------------------------------------------------

	/**
	 * Test product_extra_delete_fields returns array with re_assignment_product_id.
	 */
	public function test_product_extra_delete_fields_returns_re_assignment_field(): void {
		$product = new Product();
		$product->set_name('Product to Delete');

		$fields = $this->page->product_extra_delete_fields([], $product);

		$this->assertIsArray($fields);
		$this->assertArrayHasKey('re_assignment_product_id', $fields);
		$this->assertEquals('model', $fields['re_assignment_product_id']['type']);
	}

	/**
	 * Test product_extra_delete_fields merges with existing fields.
	 */
	public function test_product_extra_delete_fields_merges_with_existing(): void {
		$product = new Product();
		$product->set_name('Product to Delete');

		$existing = [
			'confirm' => [
				'type'  => 'toggle',
				'title' => 'Confirm',
			],
		];

		$fields = $this->page->product_extra_delete_fields($existing, $product);

		$this->assertArrayHasKey('re_assignment_product_id', $fields);
		$this->assertArrayHasKey('confirm', $fields);
	}

	/**
	 * Test product_extra_delete_fields excludes the product being deleted.
	 */
	public function test_product_extra_delete_fields_excludes_current_product(): void {
		$product = new Product();
		$product->set_name('Product to Delete');
		$saved = $product->save();

		if (is_wp_error($saved)) {
			$this->markTestSkipped('Could not save product: ' . $saved->get_error_message());
			return;
		}

		$fields = $this->page->product_extra_delete_fields([], $product);

		$exclude_json = $fields['re_assignment_product_id']['html_attr']['data-exclude'];
		$exclude      = json_decode($exclude_json, true);

		$this->assertContains($product->get_id(), $exclude);
	}

	// -------------------------------------------------------------------------
	// product_after_delete_actions()
	// -------------------------------------------------------------------------

	/**
	 * Test product_after_delete_actions does nothing when no re_assignment_product_id.
	 */
	public function test_product_after_delete_actions_no_reassignment(): void {
		unset($_REQUEST['re_assignment_product_id'], $_POST['re_assignment_product_id']);

		$product = new Product();
		$product->set_name('Deleted Product');

		// Should not throw.
		$this->page->product_after_delete_actions($product);

		$this->assertTrue(true);
	}

	/**
	 * Test product_after_delete_actions reassigns memberships when product found.
	 */
	public function test_product_after_delete_actions_reassigns_when_product_found(): void {
		$new_product = wu_create_product(
			[
				'name'          => 'Replacement Product',
				'slug'          => 'replacement-product-' . uniqid(),
				'amount'        => 10,
				'type'          => 'plan',
				'active'        => true,
				'recurring'     => true,
				'duration'      => 1,
				'duration_unit' => 'month',
			]
		);

		if (is_wp_error($new_product)) {
			$this->markTestSkipped('Could not create replacement product: ' . $new_product->get_error_message());
			return;
		}

		$old_product = wu_create_product(
			[
				'name'          => 'Old Product',
				'slug'          => 'old-product-' . uniqid(),
				'amount'        => 5,
				'type'          => 'plan',
				'active'        => true,
				'recurring'     => true,
				'duration'      => 1,
				'duration_unit' => 'month',
			]
		);

		if (is_wp_error($old_product)) {
			$this->markTestSkipped('Could not create old product: ' . $old_product->get_error_message());
			return;
		}

		$_REQUEST['re_assignment_product_id'] = $new_product->get_id();
		$_POST['re_assignment_product_id']    = $new_product->get_id();

		// Should not throw.
		$this->page->product_after_delete_actions($old_product);

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// add_new_product_warning_message()
	// -------------------------------------------------------------------------

	/**
	 * Test add_new_product_warning_message returns early when wu-new-model not set.
	 */
	public function test_add_new_product_warning_message_returns_early_without_new_model(): void {
		unset($_REQUEST['wu-new-model'], $_GET['wu-new-model']);

		// Should not throw.
		$this->page->add_new_product_warning_message();

		$this->assertTrue(true);
	}

	/**
	 * Test add_new_product_warning_message returns early for non-plan product.
	 */
	public function test_add_new_product_warning_message_returns_early_for_non_plan(): void {
		$_REQUEST['wu-new-model'] = 1;
		$_GET['wu-new-model']     = 1;

		$product = new Product();
		$product->set_type('service');

		$this->page->object = $product;

		// Should not throw.
		$this->page->add_new_product_warning_message();

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// handle_legacy_options()
	// -------------------------------------------------------------------------

	/**
	 * Test handle_legacy_options returns early when no legacy filter registered.
	 */
	public function test_handle_legacy_options_returns_early_without_filter(): void {
		global $wp_filter;

		unset($wp_filter['wu_plans_advanced_options_after_panels']);

		// Should not throw.
		$this->page->handle_legacy_options();

		$this->assertTrue(true);
	}

	/**
	 * Test handle_legacy_options adds widget when legacy filter is registered.
	 */
	public function test_handle_legacy_options_adds_widget_with_filter(): void {
		set_current_screen('dashboard-network');

		add_action(
			'wu_plans_advanced_options_after_panels',
			function ($object) {
				echo 'legacy content';
			}
		);

		// Should not throw.
		$this->page->handle_legacy_options();

		remove_all_actions('wu_plans_advanced_options_after_panels');

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// get_product_option_sections()
	// -------------------------------------------------------------------------

	/**
	 * Test get_product_option_sections returns array.
	 */
	public function test_get_product_option_sections_returns_array(): void {
		$sections = $this->page->public_get_product_option_sections();

		$this->assertIsArray($sections);
	}

	/**
	 * Test get_product_option_sections contains general section.
	 */
	public function test_get_product_option_sections_contains_general(): void {
		$sections = $this->page->public_get_product_option_sections();

		$this->assertArrayHasKey('general', $sections);
	}

	/**
	 * Test get_product_option_sections contains ups-and-downs section.
	 */
	public function test_get_product_option_sections_contains_ups_and_downs(): void {
		$sections = $this->page->public_get_product_option_sections();

		$this->assertArrayHasKey('ups-and-downs', $sections);
	}

	/**
	 * Test get_product_option_sections contains price-variations section.
	 */
	public function test_get_product_option_sections_contains_price_variations(): void {
		$sections = $this->page->public_get_product_option_sections();

		$this->assertArrayHasKey('price-variations', $sections);
	}

	/**
	 * Test get_product_option_sections contains taxes section.
	 */
	public function test_get_product_option_sections_contains_taxes(): void {
		$sections = $this->page->public_get_product_option_sections();

		$this->assertArrayHasKey('taxes', $sections);
	}

	/**
	 * Test get_product_option_sections contains allowed_templates section.
	 */
	public function test_get_product_option_sections_contains_allowed_templates(): void {
		$sections = $this->page->public_get_product_option_sections();

		$this->assertArrayHasKey('allowed_templates', $sections);
	}

	/**
	 * Test get_product_option_sections contains demo-settings section.
	 */
	public function test_get_product_option_sections_contains_demo_settings(): void {
		$sections = $this->page->public_get_product_option_sections();

		$this->assertArrayHasKey('demo-settings', $sections);
	}

	/**
	 * Test general section has required fields.
	 */
	public function test_general_section_has_required_fields(): void {
		$sections = $this->page->public_get_product_option_sections();

		$this->assertArrayHasKey('fields', $sections['general']);
		$this->assertArrayHasKey('slug', $sections['general']['fields']);
		$this->assertArrayHasKey('type', $sections['general']['fields']);
	}

	/**
	 * Test taxes section has taxable and tax_category fields.
	 */
	public function test_taxes_section_has_taxable_field(): void {
		$sections = $this->page->public_get_product_option_sections();

		$this->assertArrayHasKey('fields', $sections['taxes']);
		$this->assertArrayHasKey('taxable', $sections['taxes']['fields']);
		$this->assertArrayHasKey('tax_category', $sections['taxes']['fields']);
	}

	/**
	 * Test price-variations section has enable_price_variations field.
	 */
	public function test_price_variations_section_has_enable_field(): void {
		$sections = $this->page->public_get_product_option_sections();

		$this->assertArrayHasKey('fields', $sections['price-variations']);
		$this->assertArrayHasKey('enable_price_variations', $sections['price-variations']['fields']);
	}

	/**
	 * Test get_product_option_sections is filterable via wu_product_options_sections.
	 */
	public function test_get_product_option_sections_is_filterable(): void {
		add_filter(
			'wu_product_options_sections',
			function ($sections) {
				$sections['custom_test_section'] = [
					'title'  => 'Custom',
					'fields' => [],
				];
				return $sections;
			}
		);

		$sections = $this->page->public_get_product_option_sections();

		remove_all_filters('wu_product_options_sections');

		$this->assertArrayHasKey('custom_test_section', $sections);
	}

	// -------------------------------------------------------------------------
	// register_widgets()
	// -------------------------------------------------------------------------

	/**
	 * Test register_widgets does not throw for new product.
	 */
	public function test_register_widgets_does_not_throw_for_new_object(): void {
		set_current_screen('dashboard-network');

		$this->page->register_widgets();

		$this->assertTrue(true);
	}

	/**
	 * Test register_widgets does not throw in edit mode with a plan.
	 */
	public function test_register_widgets_does_not_throw_in_edit_mode_plan(): void {
		set_current_screen('dashboard-network');

		$product = new Product();
		$product->set_type('plan');
		$product->set_amount(10);
		$product->set_duration(1);
		$product->set_duration_unit('month');

		$this->page->object = $product;
		$this->page->edit   = true;

		$this->page->register_widgets();

		$this->assertTrue(true);
	}

	/**
	 * Test register_widgets does not throw for service product.
	 */
	public function test_register_widgets_does_not_throw_for_service(): void {
		set_current_screen('dashboard-network');

		$product = new Product();
		$product->set_type('service');
		$product->set_amount(0);

		$this->page->object = $product;

		$this->page->register_widgets();

		$this->assertTrue(true);
	}

	/**
	 * Test register_widgets does not throw for recurring product.
	 */
	public function test_register_widgets_does_not_throw_for_recurring_product(): void {
		set_current_screen('dashboard-network');

		$product = new Product();
		$product->set_type('plan');
		$product->set_amount(29);
		$product->set_recurring(true);
		$product->set_duration(1);
		$product->set_duration_unit('month');

		$this->page->object = $product;

		$this->page->register_widgets();

		$this->assertTrue(true);
	}

	/**
	 * Test register_widgets does not throw for product with trial.
	 */
	public function test_register_widgets_does_not_throw_with_trial(): void {
		set_current_screen('dashboard-network');

		$product = new Product();
		$product->set_type('plan');
		$product->set_amount(29);
		$product->set_recurring(true);
		$product->set_duration(1);
		$product->set_duration_unit('month');
		$product->set_trial_duration(7);
		$product->set_trial_duration_unit('day');

		$this->page->object = $product;

		$this->page->register_widgets();

		$this->assertTrue(true);
	}

	/**
	 * Test register_widgets does not throw for product with setup fee.
	 */
	public function test_register_widgets_does_not_throw_with_setup_fee(): void {
		set_current_screen('dashboard-network');

		$product = new Product();
		$product->set_type('plan');
		$product->set_amount(29);
		$product->set_setup_fee(50);

		$this->page->object = $product;

		$this->page->register_widgets();

		$this->assertTrue(true);
	}

	/**
	 * Test register_widgets does not throw for taxable product.
	 */
	public function test_register_widgets_does_not_throw_for_taxable_product(): void {
		set_current_screen('dashboard-network');

		$product = new Product();
		$product->set_type('plan');
		$product->set_amount(29);
		$product->set_taxable(true);

		$this->page->object = $product;

		$this->page->register_widgets();

		$this->assertTrue(true);
	}

	/**
	 * Test register_widgets does not throw for demo product.
	 */
	public function test_register_widgets_does_not_throw_for_demo_product(): void {
		set_current_screen('dashboard-network');

		$product = new Product();
		$product->set_type('demo');
		$product->set_amount(0);

		$this->page->object = $product;

		$this->page->register_widgets();

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// handle_save()
	// -------------------------------------------------------------------------

	/**
	 * Test handle_save sets recurring to false when not in POST.
	 */
	public function test_handle_save_sets_recurring_false_when_absent(): void {
		$mock_product = $this->createMock(Product::class);
		$mock_product->method('save')->willReturn(new \WP_Error('test', 'Error'));
		$mock_product->method('load_attributes_from_post')->willReturn(null);

		$this->page->object = $mock_product;

		unset($_REQUEST['recurring'], $_POST['recurring']);

		$this->page->handle_save();

		$this->assertFalse($_POST['recurring']);
	}

	/**
	 * Test handle_save sets legacy_options to false when not in POST.
	 */
	public function test_handle_save_sets_legacy_options_false_when_absent(): void {
		$mock_product = $this->createMock(Product::class);
		$mock_product->method('save')->willReturn(new \WP_Error('test', 'Error'));
		$mock_product->method('load_attributes_from_post')->willReturn(null);

		$this->page->object = $mock_product;

		unset($_REQUEST['legacy_options'], $_POST['legacy_options']);

		$this->page->handle_save();

		$this->assertFalse($_POST['legacy_options']);
	}

	/**
	 * Test handle_save sets featured_plan to false when not in POST.
	 */
	public function test_handle_save_sets_featured_plan_false_when_absent(): void {
		$mock_product = $this->createMock(Product::class);
		$mock_product->method('save')->willReturn(new \WP_Error('test', 'Error'));
		$mock_product->method('load_attributes_from_post')->willReturn(null);

		$this->page->object = $mock_product;

		unset($_REQUEST['featured_plan'], $_POST['featured_plan']);

		$this->page->handle_save();

		$this->assertFalse($_POST['featured_plan']);
	}

	/**
	 * Test handle_save sets setup_fee to 0 when has_setup_fee is absent.
	 */
	public function test_handle_save_sets_setup_fee_zero_when_no_setup_fee(): void {
		$mock_product = $this->createMock(Product::class);
		$mock_product->method('save')->willReturn(new \WP_Error('test', 'Error'));
		$mock_product->method('load_attributes_from_post')->willReturn(null);

		$this->page->object = $mock_product;

		unset($_REQUEST['has_setup_fee'], $_POST['has_setup_fee']);

		$this->page->handle_save();

		$this->assertEquals(0, $_POST['setup_fee']);
	}

	/**
	 * Test handle_save sets trial_duration to 0 when has_trial is absent.
	 */
	public function test_handle_save_sets_trial_duration_zero_when_no_trial(): void {
		$mock_product = $this->createMock(Product::class);
		$mock_product->method('save')->willReturn(new \WP_Error('test', 'Error'));
		$mock_product->method('load_attributes_from_post')->willReturn(null);

		$this->page->object = $mock_product;

		unset($_REQUEST['has_trial'], $_POST['has_trial']);

		$this->page->handle_save();

		$this->assertEquals(0, $_POST['trial_duration']);
	}

	/**
	 * Test handle_save sets price_variations to empty array when not in POST.
	 */
	public function test_handle_save_sets_price_variations_empty_when_absent(): void {
		$mock_product = $this->createMock(Product::class);
		$mock_product->method('save')->willReturn(new \WP_Error('test', 'Error'));
		$mock_product->method('load_attributes_from_post')->willReturn(null);

		$this->page->object = $mock_product;

		unset($_REQUEST['price_variations'], $_POST['price_variations']);

		$this->page->handle_save();

		$this->assertEquals([], $_POST['price_variations']);
	}

	/**
	 * Test handle_save sets available_addons to empty array when not in POST.
	 */
	public function test_handle_save_sets_available_addons_empty_when_absent(): void {
		$mock_product = $this->createMock(Product::class);
		$mock_product->method('save')->willReturn(new \WP_Error('test', 'Error'));
		$mock_product->method('load_attributes_from_post')->willReturn(null);

		$this->page->object = $mock_product;

		unset($_REQUEST['available_addons'], $_POST['available_addons']);

		$this->page->handle_save();

		$this->assertEquals([], $_POST['available_addons']);
	}

	/**
	 * Test handle_save sets taxable to 0 when not in POST.
	 */
	public function test_handle_save_sets_taxable_zero_when_absent(): void {
		$mock_product = $this->createMock(Product::class);
		$mock_product->method('save')->willReturn(new \WP_Error('test', 'Error'));
		$mock_product->method('load_attributes_from_post')->willReturn(null);

		$this->page->object = $mock_product;

		unset($_REQUEST['taxable'], $_POST['taxable']);

		$this->page->handle_save();

		$this->assertEquals(0, $_POST['taxable']);
	}

	/**
	 * Test handle_save returns false when parent save fails.
	 */
	public function test_handle_save_returns_false_on_save_error(): void {
		$mock_product = $this->createMock(Product::class);
		$mock_product->method('save')->willReturn(new \WP_Error('test_error', 'Save failed'));
		$mock_product->method('load_attributes_from_post')->willReturn(null);

		$this->page->object = $mock_product;

		unset(
			$_REQUEST['recurring'],
			$_REQUEST['legacy_options'],
			$_REQUEST['featured_plan'],
			$_REQUEST['has_setup_fee'],
			$_REQUEST['has_trial'],
			$_REQUEST['price_variations'],
			$_REQUEST['available_addons'],
			$_REQUEST['taxable']
		);

		$result = $this->page->handle_save();

		$this->assertFalse($result);
	}

	/**
	 * Test handle_save preserves recurring when set in POST.
	 */
	public function test_handle_save_preserves_recurring_when_set(): void {
		$mock_product = $this->createMock(Product::class);
		$mock_product->method('save')->willReturn(new \WP_Error('test', 'Error'));
		$mock_product->method('load_attributes_from_post')->willReturn(null);

		$this->page->object = $mock_product;

		$_REQUEST['recurring'] = 1;
		$_POST['recurring']    = 1;

		$this->page->handle_save();

		// When recurring is set, it should NOT be overwritten to false.
		$this->assertNotFalse($_POST['recurring']);
	}
}
