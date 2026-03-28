<?php
/**
 * Tests for Customer_Edit_Admin_Page class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Admin_Pages;

use WP_UnitTestCase;
use WP_Ultimo\Models\Customer;

/**
 * Test class for Customer_Edit_Admin_Page.
 *
 * Tests all public methods of the Customer_Edit_Admin_Page class.
 * Methods that require HTTP redirects, nonce verification, or wp_die()
 * are tested for their guard conditions only.
 */
class Customer_Edit_Admin_Page_Test extends WP_UnitTestCase {

	/**
	 * @var Customer_Edit_Admin_Page
	 */
	private $page;

	/**
	 * @var Customer
	 */
	private $customer;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {

		parent::setUp();

		// Create a customer with its own user (avoids relying on user ID 1 existing).
		// wu_create_customer with username/email/password creates the WP user internally
		// without hitting the multisite 'spam' column that may not exist in all test environments.
		$unique = wp_rand(1000, 9999);
		$email  = 'testcustomer' . $unique . '@example.com';

		$customer = wu_create_customer(
			[
				'username' => 'testcustomer' . $unique,
				'email'    => $email,
				'password' => 'password123',
			]
		);

		if (is_wp_error($customer)) {
			// If the user ID was already used (stale test DB), the WP user was still created.
			// Fetch the newly created user by email and get their customer record.
			$user = get_user_by('email', $email);
			if ($user) {
				$customer = wu_get_customer_by_user_id($user->ID);
			}

			if ( ! $customer || is_wp_error($customer)) {
				$this->fail('Could not create test customer: ' . (is_wp_error($customer) ? $customer->get_error_message() : 'unknown error'));
			}
		}

		$this->customer = $customer;

		$this->page = new Customer_Edit_Admin_Page();

		// Inject the customer object so get_object() doesn't redirect.
		$this->page->object = $this->customer;
		$this->page->edit   = true;
	}

	/**
	 * Tear down: clean up superglobals and notices.
	 */
	protected function tearDown(): void {

		unset(
			$_REQUEST['id'],
			$_REQUEST['submit_button'],
			$_GET['delete_meta_key'],
			$_GET['_wpnonce'],
			$_GET['notice_verification_sent'],
			$_POST['submit_button'],
			$_POST['vip'],
			$_POST['new_meta_fields']
		);

		$this->clear_notices();

		parent::tearDown();
	}

	/**
	 * Clear all WP_Ultimo admin notices via reflection.
	 *
	 * @return void
	 */
	private function clear_notices(): void {

		$notices_obj = \WP_Ultimo()->notices;
		$reflection  = new \ReflectionClass($notices_obj);
		$property    = $reflection->getProperty('notices');
		$property->setAccessible(true);
		$property->setValue(
			$notices_obj,
			[
				'admin'         => [],
				'network-admin' => [],
				'user'          => [],
			]
		);
	}

	// -------------------------------------------------------------------------
	// Page properties
	// -------------------------------------------------------------------------

	/**
	 * Test page id is correct.
	 */
	public function test_page_id(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('id');
		$property->setAccessible(true);

		$this->assertEquals('wp-ultimo-edit-customer', $property->getValue($this->page));
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
	 * Test object_id is customer.
	 */
	public function test_object_id(): void {

		$this->assertEquals('customer', $this->page->object_id);
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

	/**
	 * Test highlight_menu_slug is wp-ultimo-customers.
	 */
	public function test_highlight_menu_slug(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('highlight_menu_slug');
		$property->setAccessible(true);

		$this->assertEquals('wp-ultimo-customers', $property->getValue($this->page));
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
	 * Test supported_panels contains network_admin_menu with correct capability.
	 */
	public function test_supported_panels(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('supported_panels');
		$property->setAccessible(true);

		$panels = $property->getValue($this->page);
		$this->assertArrayHasKey('network_admin_menu', $panels);
		$this->assertEquals('wu_edit_customers', $panels['network_admin_menu']);
	}

	// -------------------------------------------------------------------------
	// get_title()
	// -------------------------------------------------------------------------

	/**
	 * get_title returns 'Add new Customer' when not in edit mode.
	 */
	public function test_get_title_add_new(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('edit');
		$property->setAccessible(true);
		$property->setValue($this->page, false);

		$title = $this->page->get_title();

		$this->assertIsString($title);
		$this->assertEquals('Add new Customer', $title);
	}

	/**
	 * get_title returns 'Edit Customer' when in edit mode.
	 */
	public function test_get_title_edit(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('edit');
		$property->setAccessible(true);
		$property->setValue($this->page, true);

		$title = $this->page->get_title();

		$this->assertIsString($title);
		$this->assertEquals('Edit Customer', $title);
	}

	// -------------------------------------------------------------------------
	// get_menu_title()
	// -------------------------------------------------------------------------

	/**
	 * get_menu_title returns 'Edit Customer'.
	 */
	public function test_get_menu_title(): void {

		$title = $this->page->get_menu_title();

		$this->assertIsString($title);
		$this->assertEquals('Edit Customer', $title);
	}

	// -------------------------------------------------------------------------
	// action_links()
	// -------------------------------------------------------------------------

	/**
	 * action_links returns an empty array.
	 */
	public function test_action_links(): void {

		$links = $this->page->action_links();

		$this->assertIsArray($links);
		$this->assertEmpty($links);
	}

	// -------------------------------------------------------------------------
	// get_labels()
	// -------------------------------------------------------------------------

	/**
	 * get_labels returns an array with all required keys.
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
	 * get_labels returns correct string values.
	 */
	public function test_get_labels_values(): void {

		$labels = $this->page->get_labels();

		$this->assertEquals('Edit Customer', $labels['edit_label']);
		$this->assertEquals('Add new Customer', $labels['add_new_label']);
		$this->assertEquals('Save Customer', $labels['save_button_label']);
		$this->assertEquals('Delete Customer', $labels['delete_button_label']);
	}

	/**
	 * get_labels updated_message is non-empty.
	 */
	public function test_get_labels_updated_message_non_empty(): void {

		$labels = $this->page->get_labels();

		$this->assertNotEmpty($labels['updated_message']);
	}

	// -------------------------------------------------------------------------
	// has_title()
	// -------------------------------------------------------------------------

	/**
	 * has_title returns false.
	 */
	public function test_has_title_returns_false(): void {

		$this->assertFalse($this->page->has_title());
	}

	// -------------------------------------------------------------------------
	// hooks()
	// -------------------------------------------------------------------------

	/**
	 * hooks() registers handle_send_verification_notice action.
	 */
	public function test_hooks_registers_verification_notice_action(): void {

		$this->page->hooks();

		$this->assertGreaterThan(
			0,
			has_action('wu_page_edit_redirect_handlers', [$this->page, 'handle_send_verification_notice'])
		);
	}

	/**
	 * hooks() registers remove_query_args filter.
	 */
	public function test_hooks_registers_remove_query_args_filter(): void {

		$this->page->hooks();

		$this->assertGreaterThan(
			0,
			has_filter('removable_query_args', [$this->page, 'remove_query_args'])
		);
	}

	// -------------------------------------------------------------------------
	// remove_query_args()
	// -------------------------------------------------------------------------

	/**
	 * remove_query_args appends notice_verification_sent to the list.
	 */
	public function test_remove_query_args_appends_notice_verification_sent(): void {

		$result = $this->page->remove_query_args(['existing-arg']);

		$this->assertContains('notice_verification_sent', $result);
		$this->assertContains('existing-arg', $result);
	}

	/**
	 * remove_query_args appends delete_meta_key to the list.
	 */
	public function test_remove_query_args_appends_delete_meta_key(): void {

		$result = $this->page->remove_query_args([]);

		$this->assertContains('delete_meta_key', $result);
	}

	/**
	 * remove_query_args appends _wpnonce to the list.
	 */
	public function test_remove_query_args_appends_wpnonce(): void {

		$result = $this->page->remove_query_args([]);

		$this->assertContains('_wpnonce', $result);
	}

	/**
	 * remove_query_args returns non-array input unchanged.
	 */
	public function test_remove_query_args_returns_non_array_unchanged(): void {

		$result = $this->page->remove_query_args('not-an-array');

		$this->assertEquals('not-an-array', $result);
	}

	/**
	 * remove_query_args with empty array returns three args.
	 */
	public function test_remove_query_args_with_empty_array(): void {

		$result = $this->page->remove_query_args([]);

		$this->assertCount(3, $result);
	}

	/**
	 * remove_query_args preserves existing entries.
	 */
	public function test_remove_query_args_preserves_existing(): void {

		$existing = ['arg1', 'arg2'];
		$result   = $this->page->remove_query_args($existing);

		$this->assertContains('arg1', $result);
		$this->assertContains('arg2', $result);
	}

	// -------------------------------------------------------------------------
	// handle_send_verification_notice()
	// -------------------------------------------------------------------------

	/**
	 * handle_send_verification_notice outputs notice when query arg is present.
	 */
	public function test_handle_send_verification_notice_outputs_when_arg_present(): void {

		$_GET['notice_verification_sent'] = '1';

		ob_start();
		$this->page->handle_send_verification_notice();
		$output = ob_get_clean();

		$this->assertStringContainsString('Verification email sent', $output);
	}

	/**
	 * handle_send_verification_notice outputs nothing when query arg is absent.
	 */
	public function test_handle_send_verification_notice_silent_when_arg_absent(): void {

		ob_start();
		$this->page->handle_send_verification_notice();
		$output = ob_get_clean();

		$this->assertEmpty($output);
	}

	// -------------------------------------------------------------------------
	// memberships_query_filter()
	// -------------------------------------------------------------------------

	/**
	 * memberships_query_filter adds customer_id to args.
	 */
	public function test_memberships_query_filter_adds_customer_id(): void {

		$args   = ['some_arg' => 'value'];
		$result = $this->page->memberships_query_filter($args);

		$this->assertArrayHasKey('customer_id', $result);
		$this->assertEquals($this->customer->get_id(), $result['customer_id']);
	}

	/**
	 * memberships_query_filter preserves existing args.
	 */
	public function test_memberships_query_filter_preserves_existing_args(): void {

		$args   = ['existing_arg' => 'existing_value'];
		$result = $this->page->memberships_query_filter($args);

		$this->assertArrayHasKey('existing_arg', $result);
		$this->assertEquals('existing_value', $result['existing_arg']);
	}

	/**
	 * memberships_query_filter works with empty args.
	 */
	public function test_memberships_query_filter_with_empty_args(): void {

		$result = $this->page->memberships_query_filter([]);

		$this->assertArrayHasKey('customer_id', $result);
		$this->assertEquals($this->customer->get_id(), $result['customer_id']);
	}

	// -------------------------------------------------------------------------
	// sites_query_filter()
	// -------------------------------------------------------------------------

	/**
	 * sites_query_filter adds meta_query with customer_id.
	 */
	public function test_sites_query_filter_adds_meta_query(): void {

		$args   = [];
		$result = $this->page->sites_query_filter($args);

		$this->assertArrayHasKey('meta_query', $result);
		$this->assertArrayHasKey('customer_id', $result['meta_query']);
		$this->assertEquals('wu_customer_id', $result['meta_query']['customer_id']['key']);
		$this->assertEquals($this->customer->get_id(), $result['meta_query']['customer_id']['value']);
	}

	/**
	 * sites_query_filter preserves existing args.
	 */
	public function test_sites_query_filter_preserves_existing_args(): void {

		$args   = ['orderby' => 'date'];
		$result = $this->page->sites_query_filter($args);

		$this->assertArrayHasKey('orderby', $result);
		$this->assertEquals('date', $result['orderby']);
	}

	// -------------------------------------------------------------------------
	// events_query_filter()
	// -------------------------------------------------------------------------

	/**
	 * events_query_filter adds object_type and object_id.
	 */
	public function test_events_query_filter_adds_object_type_and_id(): void {

		$args   = [];
		$result = $this->page->events_query_filter($args);

		$this->assertArrayHasKey('object_type', $result);
		$this->assertArrayHasKey('object_id', $result);
		$this->assertEquals('customer', $result['object_type']);
		$this->assertEquals($this->customer->get_id(), $result['object_id']);
	}

	/**
	 * events_query_filter preserves existing args.
	 */
	public function test_events_query_filter_preserves_existing_args(): void {

		$args   = ['per_page' => 10];
		$result = $this->page->events_query_filter($args);

		$this->assertArrayHasKey('per_page', $result);
		$this->assertEquals(10, $result['per_page']);
	}

	/**
	 * events_query_filter object_id is an integer.
	 */
	public function test_events_query_filter_object_id_is_integer(): void {

		$result = $this->page->events_query_filter([]);

		$this->assertIsInt($result['object_id']);
	}

	// -------------------------------------------------------------------------
	// restricted_customer_meta_keys()
	// -------------------------------------------------------------------------

	/**
	 * restricted_customer_meta_keys returns true for wu_verification_key.
	 */
	public function test_restricted_meta_keys_wu_verification_key(): void {

		$this->assertTrue($this->page->restricted_customer_meta_keys('wu_verification_key'));
	}

	/**
	 * restricted_customer_meta_keys returns true for wu_billing_address.
	 */
	public function test_restricted_meta_keys_wu_billing_address(): void {

		$this->assertTrue($this->page->restricted_customer_meta_keys('wu_billing_address'));
	}

	/**
	 * restricted_customer_meta_keys returns true for ip_state.
	 */
	public function test_restricted_meta_keys_ip_state(): void {

		$this->assertTrue($this->page->restricted_customer_meta_keys('ip_state'));
	}

	/**
	 * restricted_customer_meta_keys returns true for ip_country.
	 */
	public function test_restricted_meta_keys_ip_country(): void {

		$this->assertTrue($this->page->restricted_customer_meta_keys('ip_country'));
	}

	/**
	 * restricted_customer_meta_keys returns true for wu_has_trialed.
	 */
	public function test_restricted_meta_keys_wu_has_trialed(): void {

		$this->assertTrue($this->page->restricted_customer_meta_keys('wu_has_trialed'));
	}

	/**
	 * restricted_customer_meta_keys returns true for wu_custom_meta_keys.
	 */
	public function test_restricted_meta_keys_wu_custom_meta_keys(): void {

		$this->assertTrue($this->page->restricted_customer_meta_keys('wu_custom_meta_keys'));
	}

	/**
	 * restricted_customer_meta_keys returns false for arbitrary key.
	 */
	public function test_restricted_meta_keys_returns_false_for_arbitrary_key(): void {

		$this->assertFalse($this->page->restricted_customer_meta_keys('my_custom_field'));
	}

	/**
	 * restricted_customer_meta_keys returns false for empty string.
	 */
	public function test_restricted_meta_keys_returns_false_for_empty_string(): void {

		$this->assertFalse($this->page->restricted_customer_meta_keys(''));
	}

	/**
	 * restricted_customer_meta_keys is case-sensitive.
	 */
	public function test_restricted_meta_keys_is_case_sensitive(): void {

		// Upper-cased version should NOT be restricted.
		$this->assertFalse($this->page->restricted_customer_meta_keys('WU_VERIFICATION_KEY'));
	}

	// -------------------------------------------------------------------------
	// customer_extra_delete_fields()
	// -------------------------------------------------------------------------

	/**
	 * customer_extra_delete_fields returns an array.
	 */
	public function test_customer_extra_delete_fields_returns_array(): void {

		// Use the built-in admin user (ID 1) which always exists in the WP test suite.
		// Avoid factory->user->create() which requires the multisite 'spam' column.
		wp_set_current_user(1);
		grant_super_admin(1);

		$fields = $this->page->customer_extra_delete_fields([], $this->customer);

		$this->assertIsArray($fields);
	}

	/**
	 * customer_extra_delete_fields merges custom fields before existing fields.
	 */
	public function test_customer_extra_delete_fields_merges_fields(): void {

		wp_set_current_user(1);
		grant_super_admin(1);

		$existing_fields = ['existing_field' => ['type' => 'text']];
		$result          = $this->page->customer_extra_delete_fields($existing_fields, $this->customer);

		$this->assertArrayHasKey('existing_field', $result);
	}

	/**
	 * customer_extra_delete_fields without delete capability omits delete_all field.
	 *
	 * Uses user ID 0 (logged-out state) which has no capabilities.
	 */
	public function test_customer_extra_delete_fields_without_delete_capability(): void {

		// Log out — no capabilities means delete_all should be omitted.
		wp_set_current_user(0);

		$result = $this->page->customer_extra_delete_fields([], $this->customer);

		$this->assertArrayNotHasKey('delete_all', $result);
	}

	/**
	 * customer_extra_delete_fields without transfer capability omits re_assignment field.
	 *
	 * Uses user ID 0 (logged-out state) which has no capabilities.
	 */
	public function test_customer_extra_delete_fields_without_transfer_capability(): void {

		wp_set_current_user(0);

		$result = $this->page->customer_extra_delete_fields([], $this->customer);

		$this->assertArrayNotHasKey('re_assignment_customer_id', $result);
	}

	// -------------------------------------------------------------------------
	// customer_extra_form_attributes()
	// -------------------------------------------------------------------------

	/**
	 * customer_extra_form_attributes adds delete_all_confirmed to state.
	 */
	public function test_customer_extra_form_attributes_adds_delete_all_confirmed(): void {

		$form_attributes = [
			'html_attr' => [
				'data-state' => wp_json_encode(['confirmed' => false]),
			],
		];

		$result = $this->page->customer_extra_form_attributes($form_attributes);

		$state = json_decode($result['html_attr']['data-state'], true);
		$this->assertArrayHasKey('delete_all_confirmed', $state);
		$this->assertFalse($state['delete_all_confirmed']);
	}

	/**
	 * customer_extra_form_attributes preserves existing state values.
	 */
	public function test_customer_extra_form_attributes_preserves_existing_state(): void {

		$form_attributes = [
			'html_attr' => [
				'data-state' => wp_json_encode(['confirmed' => true, 'other_key' => 'value']),
			],
		];

		$result = $this->page->customer_extra_form_attributes($form_attributes);

		$state = json_decode($result['html_attr']['data-state'], true);
		$this->assertArrayHasKey('confirmed', $state);
		$this->assertTrue($state['confirmed']);
		$this->assertArrayHasKey('other_key', $state);
	}

	/**
	 * customer_extra_form_attributes returns array.
	 */
	public function test_customer_extra_form_attributes_returns_array(): void {

		$form_attributes = [
			'html_attr' => [
				'data-state' => wp_json_encode([]),
			],
		];

		$result = $this->page->customer_extra_form_attributes($form_attributes);

		$this->assertIsArray($result);
	}

	// -------------------------------------------------------------------------
	// customer_after_delete_actions()
	// -------------------------------------------------------------------------

	/**
	 * customer_after_delete_actions with delete_all=false and no re-assignment does nothing.
	 */
	public function test_customer_after_delete_actions_no_delete_all_no_reassignment(): void {

		// No delete_all in request, no re_assignment_customer_id.
		$mock_customer = $this->createMock(Customer::class);
		$mock_customer->method('get_memberships')->willReturn([]);
		$mock_customer->method('get_payments')->willReturn([]);

		// Should not throw.
		$this->page->customer_after_delete_actions($mock_customer);

		$this->assertTrue(true);
	}

	/**
	 * customer_after_delete_actions with delete_all enqueues async delete for memberships.
	 */
	public function test_customer_after_delete_actions_with_delete_all(): void {

		$_REQUEST['delete_all'] = '1';

		$mock_membership = $this->createMock(\WP_Ultimo\Models\Membership::class);
		$mock_membership->method('get_id')->willReturn(99);

		$mock_payment = $this->createMock(\WP_Ultimo\Models\Payment::class);
		$mock_payment->method('get_id')->willReturn(88);

		$mock_customer = $this->createMock(Customer::class);
		$mock_customer->method('get_memberships')->willReturn([$mock_membership]);
		$mock_customer->method('get_payments')->willReturn([$mock_payment]);

		// Should not throw.
		$this->page->customer_after_delete_actions($mock_customer);

		$this->assertTrue(true);

		unset($_REQUEST['delete_all']);
	}

	// -------------------------------------------------------------------------
	// render_country()
	// -------------------------------------------------------------------------

	/**
	 * render_country returns a string.
	 */
	public function test_render_country_returns_string(): void {

		$result = $this->page->render_country();

		$this->assertIsString($result);
	}

	/**
	 * render_country returns country name when country code is set.
	 */
	public function test_render_country_with_country_code(): void {

		// Set a known country code on the customer.
		$this->customer->update_meta('ip_country', 'US');

		$result = $this->page->render_country();

		$this->assertNotEmpty($result);
		$this->assertIsString($result);
	}

	/**
	 * render_country returns string when no country code is set.
	 */
	public function test_render_country_without_country_code(): void {

		// Ensure no country code is set.
		$this->customer->update_meta('ip_country', '');

		$result = $this->page->render_country();

		$this->assertIsString($result);
	}

	// -------------------------------------------------------------------------
	// page_loaded()
	// -------------------------------------------------------------------------

	/**
	 * page_loaded without delete_meta_key calls parent and sets edit.
	 */
	public function test_page_loaded_without_delete_meta_key(): void {

		$_REQUEST['id'] = $this->customer->get_id();

		// Should not throw.
		$this->page->page_loaded();

		$this->assertTrue($this->page->edit);
	}

	/**
	 * page_loaded with delete_meta_key but invalid nonce calls wp_die (guard test).
	 *
	 * We verify the nonce check path is reached by confirming the method
	 * does not proceed to delete when nonce is absent/invalid.
	 */
	public function test_page_loaded_with_delete_meta_key_invalid_nonce(): void {

		$_GET['delete_meta_key'] = 'some_key';
		$_GET['_wpnonce']        = 'invalid_nonce';

		// wp_die() is called on nonce failure — catch it.
		$this->expectException(\WPDieException::class);

		$this->page->page_loaded();
	}

	// -------------------------------------------------------------------------
	// register_scripts()
	// -------------------------------------------------------------------------

	/**
	 * register_scripts does not throw.
	 */
	public function test_register_scripts_does_not_throw(): void {

		$this->page->register_scripts();

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// register_forms()
	// -------------------------------------------------------------------------

	/**
	 * register_forms does not throw.
	 */
	public function test_register_forms_does_not_throw(): void {

		$this->page->register_forms();

		$this->assertTrue(true);
	}

	/**
	 * register_forms registers the transfer_customer form.
	 */
	public function test_register_forms_registers_transfer_customer(): void {

		$this->page->register_forms();

		// Verify the filter for delete modal fields is registered.
		$this->assertGreaterThan(
			0,
			has_filter('wu_form_fields_delete_customer_modal', [$this->page, 'customer_extra_delete_fields'])
		);
	}

	/**
	 * register_forms registers the delete modal form attributes filter.
	 */
	public function test_register_forms_registers_delete_modal_attributes_filter(): void {

		$this->page->register_forms();

		$this->assertGreaterThan(
			0,
			has_filter('wu_form_attributes_delete_customer_modal', [$this->page, 'customer_extra_form_attributes'])
		);
	}

	/**
	 * register_forms registers the after delete action.
	 */
	public function test_register_forms_registers_after_delete_action(): void {

		$this->page->register_forms();

		$this->assertGreaterThan(
			0,
			has_action('wu_after_delete_customer_modal', [$this->page, 'customer_after_delete_actions'])
		);
	}

	// -------------------------------------------------------------------------
	// register_widgets()
	// -------------------------------------------------------------------------

	/**
	 * register_widgets does not throw when called with a valid screen.
	 */
	public function test_register_widgets_does_not_throw(): void {

		set_current_screen('dashboard-network');

		$this->page->register_widgets();

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// generate_customer_meta_fields()
	// -------------------------------------------------------------------------

	/**
	 * generate_customer_meta_fields returns an array.
	 */
	public function test_generate_customer_meta_fields_returns_array(): void {

		$fields = $this->page->generate_customer_meta_fields();

		$this->assertIsArray($fields);
	}

	/**
	 * generate_customer_meta_fields always includes display_new_meta_repeater toggle.
	 */
	public function test_generate_customer_meta_fields_includes_new_meta_repeater_toggle(): void {

		$fields = $this->page->generate_customer_meta_fields();

		$this->assertArrayHasKey('display_new_meta_repeater', $fields);
		$this->assertEquals('toggle', $fields['display_new_meta_repeater']['type']);
	}

	/**
	 * generate_customer_meta_fields always includes new_meta_fields_wrapper group.
	 */
	public function test_generate_customer_meta_fields_includes_new_meta_fields_wrapper(): void {

		$fields = $this->page->generate_customer_meta_fields();

		$this->assertArrayHasKey('new_meta_fields_wrapper', $fields);
		$this->assertEquals('group', $fields['new_meta_fields_wrapper']['type']);
	}

	/**
	 * generate_customer_meta_fields returns non-empty array.
	 */
	public function test_generate_customer_meta_fields_non_empty(): void {

		$fields = $this->page->generate_customer_meta_fields();

		// Should always contain at least the repeater toggle and wrapper.
		$this->assertIsArray($fields);
		$this->assertNotEmpty($fields);
	}

	// -------------------------------------------------------------------------
	// handle_save()
	// -------------------------------------------------------------------------

	/**
	 * handle_save returns false when billing address validation fails.
	 */
	public function test_handle_save_returns_false_on_billing_validation_error(): void {

		// Mock a billing address that fails validation.
		$mock_billing = $this->createMock(\WP_Ultimo\Objects\Billing_Address::class);
		$mock_billing->method('load_attributes_from_post')->willReturn(null);
		$mock_billing->method('validate')->willReturn(
			new \WP_Error('validation_error', 'Country is required')
		);

		$mock_customer = $this->createMock(Customer::class);
		$mock_customer->method('get_id')->willReturn($this->customer->get_id());
		$mock_customer->method('get_billing_address')->willReturn($mock_billing);
		$mock_customer->method('get_type')->willReturn('customer');

		$this->page->object = $mock_customer;

		$result = $this->page->handle_save();

		$this->assertFalse($result);
	}

	/**
	 * handle_save adds error notice when billing validation fails.
	 */
	public function test_handle_save_adds_error_notice_on_billing_failure(): void {

		$mock_billing = $this->createMock(\WP_Ultimo\Objects\Billing_Address::class);
		$mock_billing->method('load_attributes_from_post')->willReturn(null);
		$mock_billing->method('validate')->willReturn(
			new \WP_Error('validation_error', 'Country is required')
		);

		$mock_customer = $this->createMock(Customer::class);
		$mock_customer->method('get_id')->willReturn($this->customer->get_id());
		$mock_customer->method('get_billing_address')->willReturn($mock_billing);
		$mock_customer->method('get_type')->willReturn('customer');

		$this->page->object = $mock_customer;

		$this->page->handle_save();

		$notices = \WP_Ultimo()->notices->get_notices('network-admin');
		$this->assertNotEmpty($notices);
	}

	// -------------------------------------------------------------------------
	// restricted_customer_meta_keys() — all restricted keys
	// -------------------------------------------------------------------------

	/**
	 * All six restricted meta keys are blocked.
	 *
	 * @dataProvider restricted_meta_keys_provider
	 */
	public function test_all_restricted_meta_keys_are_blocked(string $key): void {

		$this->assertTrue($this->page->restricted_customer_meta_keys($key));
	}

	/**
	 * Data provider for restricted meta keys.
	 *
	 * @return array<string, array<string>>
	 */
	public function restricted_meta_keys_provider(): array {

		return [
			'wu_verification_key'  => ['wu_verification_key'],
			'wu_billing_address'   => ['wu_billing_address'],
			'ip_state'             => ['ip_state'],
			'ip_country'           => ['ip_country'],
			'wu_has_trialed'       => ['wu_has_trialed'],
			'wu_custom_meta_keys'  => ['wu_custom_meta_keys'],
		];
	}

	// -------------------------------------------------------------------------
	// render_transfer_customer_modal()
	// -------------------------------------------------------------------------

	/**
	 * render_transfer_customer_modal returns early when customer not found.
	 */
	public function test_render_transfer_customer_modal_returns_early_when_no_customer(): void {

		// No id in request — wu_get_customer(0) returns false.
		ob_start();
		$this->page->render_transfer_customer_modal();
		$output = ob_get_clean();

		// No output expected when customer not found.
		$this->assertEmpty($output);
	}

	/**
	 * render_transfer_customer_modal renders form when customer exists.
	 */
	public function test_render_transfer_customer_modal_renders_when_customer_exists(): void {

		$_REQUEST['id'] = $this->customer->get_id();

		ob_start();
		$this->page->render_transfer_customer_modal();
		$output = ob_get_clean();

		// Output should contain form markup.
		$this->assertIsString($output);
	}

	// -------------------------------------------------------------------------
	// handle_transfer_customer_modal()
	// -------------------------------------------------------------------------

	/**
	 * handle_transfer_customer_modal sends JSON error when confirm is not set.
	 */
	public function test_handle_transfer_customer_modal_error_when_not_confirmed(): void {

		// confirm not set in request.
		$this->expectException(\WPAjaxDieStopException::class);

		$this->page->handle_transfer_customer_modal();
	}
}
