<?php
/**
 * Tests for Membership_Edit_Admin_Page class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Admin_Pages;

use WP_UnitTestCase;
use WP_Ultimo\Models\Membership;
use WP_Ultimo\Models\Customer;
use WP_Ultimo\Models\Product;
use WP_Ultimo\Database\Memberships\Membership_Status;
use WP_Ultimo\Checkout\Cart;

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound -- Test file includes a small testable subclass for protected method access.

/**
 * Testable subclass that exposes protected methods for testing.
 */
class Testable_Membership_Edit_Admin_Page extends Membership_Edit_Admin_Page {

	/**
	 * Expose add_swap_notices as public.
	 *
	 * @return void
	 */
	public function public_add_swap_notices(): void {
		$this->add_swap_notices();
	}

	/**
	 * Expose handle_convert_to_lifetime as public.
	 *
	 * @return bool
	 */
	public function public_handle_convert_to_lifetime(): bool {
		return $this->handle_convert_to_lifetime();
	}
}

/**
 * Test class for Membership_Edit_Admin_Page.
 */
class Membership_Edit_Admin_Page_Test extends WP_UnitTestCase {

	/**
	 * @var Testable_Membership_Edit_Admin_Page
	 */
	private $page;

	/**
	 * @var Membership
	 */
	private $membership;

	/**
	 * @var Customer
	 */
	private $customer;

	/**
	 * @var Product
	 */
	private $product;

	/**
	 * Redirect captured by the test redirect interceptor.
	 *
	 * @var string|null
	 */
	private $redirect_url;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Create a WordPress user for the customer.
		$user_id = self::factory()->user->create(
			[
				'user_login' => 'memtest_' . wp_generate_password(8, false),
				'user_email' => 'memtest_' . wp_generate_password(8, false) . '@example.com',
			]
		);

		$this->assertIsInt($user_id, 'The customer WordPress user fixture must be created.');

		// Create customer directly.
		$this->customer = new Customer(
			[
				'user_id'            => $user_id,
				'email_verification' => 'none',
				'type'               => 'customer',
			]
		);
		$this->customer->set_skip_validation(true);
		$customer_saved = $this->customer->save();

		$this->assertNotWPError($customer_saved, 'The customer fixture must be saved.');
		$this->assertGreaterThan(0, $this->customer->get_id(), 'The customer fixture must have a persisted ID.');

		// Create product directly.
		$this->product = new Product(
			[
				'name'          => 'Test Plan',
				'slug'          => 'test-plan-' . wp_generate_password(8, false),
				'description'   => 'A test plan',
				'pricing_type'  => 'paid',
				'amount'        => 29.99,
				'currency'      => 'USD',
				'duration'      => 1,
				'duration_unit' => 'month',
				'type'          => 'plan',
				'recurring'     => true,
				'active'        => true,
			]
		);
		$this->product->set_skip_validation(true);
		$product_saved = $this->product->save();

		$this->assertNotWPError($product_saved, 'The product fixture must be saved.');
		$this->assertGreaterThan(0, $this->product->get_id(), 'The product fixture must have a persisted ID.');

		// Create a membership tied to the customer and product.
		$this->membership = wu_create_membership(
			[
				'customer_id'     => $this->customer->get_id(),
				'user_id'         => $user_id,
				'plan_id'         => $this->product->get_id(),
				'status'          => Membership_Status::ACTIVE,
				'amount'          => 29.99,
				'initial_amount'  => 29.99,
				'duration'        => 1,
				'duration_unit'   => 'month',
				'recurring'       => true,
				'auto_renew'      => true,
				'currency'        => 'USD',
				'gateway'         => '',
				'date_created'    => gmdate('Y-m-d H:i:s'),
				'date_modified'   => gmdate('Y-m-d H:i:s'),
				'date_expiration' => gmdate('Y-m-d H:i:s', strtotime('+30 days')),
				'skip_validation' => true,
			]
		);

		$this->assertNotWPError($this->membership, 'The membership fixture must be saved.');
		$this->assertInstanceOf(Membership::class, $this->membership, 'The membership fixture must be a model instance.');
		$this->assertGreaterThan(0, $this->membership->get_id(), 'The membership fixture must have a persisted ID.');

		$this->page = new Testable_Membership_Edit_Admin_Page();

		$this->clear_notices();
	}

	/**
	 * Tear down: clean up superglobals and notices.
	 */
	protected function tearDown(): void {
		unset(
			$_GET['id'],
			$_POST['id'],
			$_REQUEST['id'],
			$_REQUEST['preview-swap'],
			$_REQUEST['cancel_gateway'],
			$_REQUEST['status'],
			$_REQUEST['gateway'],
			$_REQUEST['submit_button'],
			$_REQUEST['auto_renew'],
			$_REQUEST['confirm'],
			$_REQUEST['target_customer_id'],
			$_REQUEST['product_id'],
			$_REQUEST['quantity'],
			$_REQUEST['update_price'],
			$_REQUEST['plan_id'],
			$_POST['auto_renew'],
			$_POST['cancel_gateway'],
			$_POST['status'],
			$_POST['gateway'],
			$_POST['submit_button']
		);

		$this->clear_notices();
		remove_all_filters('wp_redirect');

		parent::tearDown();
	}

	/**
	 * Install a redirect interceptor that prevents headers from being sent.
	 *
	 * @return void
	 */
	private function install_redirect_interceptor(): void {
		$this->redirect_url = null;

		add_filter(
			'wp_redirect',
			function ($location) {
				$this->redirect_url = $location;

				throw new \RuntimeException('redirect_intercepted');
			}
		);
	}

	/**
	 * Assert that the callback attempted a WordPress redirect.
	 *
	 * @param callable $callback Callback expected to redirect.
	 * @return void
	 */
	private function assert_redirected(callable $callback): void {
		$buffer_level = ob_get_level();

		$this->install_redirect_interceptor();

		try {
			$callback();
		} catch (\RuntimeException $e) {
			$this->assertSame('redirect_intercepted', $e->getMessage());
		} finally {
			while (ob_get_level() > $buffer_level) {
				ob_end_clean();
			}

			remove_all_filters('wp_redirect');
		}

		$this->assertNotNull($this->redirect_url, 'wp_redirect should have been called.');
	}

	/**
	 * Add a scheduled swap fixture and assert it is readable before notice tests.
	 *
	 * @param int $swap_time Unix timestamp for the scheduled swap date.
	 * @return void
	 */
	private function schedule_swap_fixture(int $swap_time): void {
		$cart   = new Cart([]);
		$result = $this->membership->schedule_swap($cart, gmdate('Y-m-d H:i:s', $swap_time));

		$this->assertNotWPError($result, 'The scheduled swap fixture must be created.');
		$this->assertNotFalse($this->membership->get_scheduled_swap(), 'The scheduled swap fixture must be readable.');
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

	/**
	 * Install an AJAX die handler so wp_send_json_* does not stop PHPUnit.
	 *
	 * @return callable
	 */
	private function install_ajax_die_handler(): callable {
		add_filter('wp_doing_ajax', '__return_true');

		$handler = function () {
			return function ($message) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Test die handler rethrows the raw wp_die message.
				throw new \WPAjaxDieContinueException((string) $message);
			};
		};

		add_filter('wp_die_ajax_handler', $handler, 1);

		return $handler;
	}

	/**
	 * Remove an AJAX die handler installed by install_ajax_die_handler().
	 *
	 * @param callable $handler The handler returned by install_ajax_die_handler().
	 * @return void
	 */
	private function remove_ajax_die_handler(callable $handler): void {
		remove_filter('wp_doing_ajax', '__return_true');
		remove_filter('wp_die_ajax_handler', $handler, 1);
	}

	/**
	 * Capture and decode a wp_send_json_* response from an AJAX handler.
	 *
	 * @param callable $callback AJAX handler callback.
	 * @return array
	 */
	private function capture_json_response(callable $callback): array {
		$handler          = $this->install_ajax_die_handler();
		$exception_caught = false;
		$output           = '';

		ob_start();

		try {
			$callback();
		} catch (\WPAjaxDieContinueException $e) {
			$exception_caught = true;
		} finally {
			$output = ob_get_clean();
			$this->remove_ajax_die_handler($handler);
		}

		$this->assertTrue($exception_caught, 'wp_send_json_* must terminate through wp_die in AJAX context.');

		$decoded = json_decode($output, true);

		$this->assertIsArray($decoded, 'Response must be valid JSON: ' . $output);

		return $decoded;
	}

	/**
	 * Assert a wp_send_json_error payload error code.
	 *
	 * @param array  $response JSON response payload.
	 * @param string $code     Expected WP_Error code.
	 * @return void
	 */
	private function assert_json_error_code(array $response, string $code): void {
		$this->assertFalse($response['success']);
		$this->assertSame($code, $response['data'][0]['code']);
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

		$this->assertEquals('wp-ultimo-edit-membership', $property->getValue($this->page));
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
	 * Test object_id is membership.
	 */
	public function test_object_id(): void {
		$this->assertEquals('membership', $this->page->object_id);
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
		$this->assertEquals('wu_edit_memberships', $panels['network_admin_menu']);
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

		$this->assertEquals('wp-ultimo-memberships', $property->getValue($this->page));
	}

	/**
	 * Test is_swap_preview defaults to false.
	 */
	public function test_is_swap_preview_defaults_false(): void {
		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('is_swap_preview');
		$property->setAccessible(true);

		$this->assertFalse($property->getValue($this->page));
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
		$this->assertEquals('Add new Membership', $title);
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
		$this->assertEquals('Edit Membership', $title);
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
		$this->assertEquals('Edit Membership', $title);
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
	 * Test has_title returns false.
	 */
	public function test_has_title_returns_false(): void {
		$this->assertFalse($this->page->has_title());
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

		$this->assertEquals('Edit Membership', $labels['edit_label']);
	}

	/**
	 * Test get_labels add_new_label value.
	 */
	public function test_get_labels_add_new_label(): void {
		$labels = $this->page->get_labels();

		$this->assertEquals('Add new Membership', $labels['add_new_label']);
	}

	/**
	 * Test get_labels updated_message value.
	 */
	public function test_get_labels_updated_message(): void {
		$labels = $this->page->get_labels();

		$this->assertEquals('Membership updated with success!', $labels['updated_message']);
	}

	/**
	 * Test get_labels save_button_label value.
	 */
	public function test_get_labels_save_button_label(): void {
		$labels = $this->page->get_labels();

		$this->assertEquals('Save Membership', $labels['save_button_label']);
	}

	/**
	 * Test get_labels delete_button_label value.
	 */
	public function test_get_labels_delete_button_label(): void {
		$labels = $this->page->get_labels();

		$this->assertEquals('Delete Membership', $labels['delete_button_label']);
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

		$this->assertEquals('Enter Membership Name', $labels['title_placeholder']);
	}

	// -------------------------------------------------------------------------
	// get_object()
	// -------------------------------------------------------------------------

	/**
	 * Test get_object returns cached object on repeated calls.
	 */
	public function test_get_object_caches_instance(): void {
		$_REQUEST['id'] = $this->membership->get_id();

		$first  = $this->page->get_object();
		$second = $this->page->get_object();

		$this->assertSame($first, $second);
	}

	/**
	 * Test get_object returns pre-set object when object property is set.
	 */
	public function test_get_object_returns_preset_object(): void {
		$this->page->object = $this->membership;

		$result = $this->page->get_object();

		$this->assertSame($this->membership, $result);
	}

	/**
	 * Test get_object fetches from DB when id is in REQUEST and membership exists.
	 */
	public function test_get_object_fetches_from_db_when_id_in_request(): void {
		$page = new Testable_Membership_Edit_Admin_Page();

		$_REQUEST['id'] = $this->membership->get_id();

		$result = $page->get_object();

		$this->assertInstanceOf(Membership::class, $result);
		$this->assertEquals($this->membership->get_id(), $result->get_id());
	}

	/**
	 * Test get_object with preview-swap sets is_swap_preview and adds info notice.
	 */
	public function test_get_object_with_preview_swap_sets_flag_and_notice(): void {
		$swap_time = strtotime('+100 days');
		$this->schedule_swap_fixture($swap_time);

		$page = new Testable_Membership_Edit_Admin_Page();

		$_REQUEST['id']           = $this->membership->get_id();
		$_REQUEST['preview-swap'] = 1;

		$result = $page->get_object();

		$this->assertInstanceOf(Membership::class, $result);

		$reflection = new \ReflectionClass($page);
		$property   = $reflection->getProperty('is_swap_preview');
		$property->setAccessible(true);
		$this->assertTrue($property->getValue($page));

		$notices = \WP_Ultimo()->notices->get_notices('network-admin');
		$this->assertNotEmpty($notices);
		$notice = array_shift($notices);
		$this->assertEquals('info', $notice['type']);
	}

	/**
	 * Test get_object with preview-swap but no scheduled swap returns object unchanged.
	 */
	public function test_get_object_with_preview_swap_no_scheduled_swap(): void {
		$page = new Testable_Membership_Edit_Admin_Page();

		$_REQUEST['id']           = $this->membership->get_id();
		$_REQUEST['preview-swap'] = 1;

		$result = $page->get_object();

		$this->assertInstanceOf(Membership::class, $result);

		$reflection = new \ReflectionClass($page);
		$property   = $reflection->getProperty('is_swap_preview');
		$property->setAccessible(true);
		$this->assertFalse($property->getValue($page));
	}

	// -------------------------------------------------------------------------
	// add_swap_notices()
	// -------------------------------------------------------------------------

	/**
	 * Test add_swap_notices does nothing when no scheduled swap.
	 */
	public function test_add_swap_notices_does_nothing_without_swap(): void {
		$this->page->object = $this->membership;

		$this->page->public_add_swap_notices();

		$notices = \WP_Ultimo()->notices->get_notices('network-admin');
		$this->assertEmpty($notices);
	}

	/**
	 * Test add_swap_notices adds warning notice when swap is scheduled.
	 */
	public function test_add_swap_notices_adds_warning_when_swap_scheduled(): void {
		$swap_time = strtotime('+100 days');
		$this->schedule_swap_fixture($swap_time);

		$this->page->object = $this->membership;

		$this->page->public_add_swap_notices();

		$notices = \WP_Ultimo()->notices->get_notices('network-admin');
		$this->assertNotEmpty($notices);

		$notice = array_shift($notices);
		$this->assertEquals('warning', $notice['type']);
		$this->assertFalse($notice['dismissible_key']);
		$this->assertNotEmpty($notice['actions']);
	}

	/**
	 * Test add_swap_notices notice contains the scheduled date.
	 */
	public function test_add_swap_notices_message_contains_date(): void {
		$swap_time = strtotime('+100 days');
		$this->schedule_swap_fixture($swap_time);

		$this->page->object = $this->membership;

		$this->page->public_add_swap_notices();

		$notices = \WP_Ultimo()->notices->get_notices('network-admin');
		$this->assertNotEmpty($notices, 'A scheduled swap warning notice must be registered before reading its message.');
		$notice = array_shift($notices);

		$this->assertStringContainsString(gmdate(get_option('date_format'), $swap_time), $notice['message']);
	}

	/**
	 * Test add_swap_notices does nothing when preview-swap param is set.
	 */
	public function test_add_swap_notices_skips_when_preview_swap_param(): void {
		$swap_time = strtotime('+100 days');
		$this->schedule_swap_fixture($swap_time);

		$this->page->object = $this->membership;

		$_REQUEST['preview-swap'] = 1;

		$this->page->public_add_swap_notices();

		$notices = \WP_Ultimo()->notices->get_notices('network-admin');
		$this->assertEmpty($notices);
	}

	/**
	 * Test add_swap_notices notice has preview action link.
	 */
	public function test_add_swap_notices_has_preview_action(): void {
		$swap_time = strtotime('+100 days');
		$this->schedule_swap_fixture($swap_time);

		$this->page->object = $this->membership;

		$this->page->public_add_swap_notices();

		$notices = \WP_Ultimo()->notices->get_notices('network-admin');
		$this->assertNotEmpty($notices, 'A scheduled swap warning notice must be registered before reading its actions.');
		$notice = array_shift($notices);

		$this->assertArrayHasKey('preview', $notice['actions']);
	}

	// -------------------------------------------------------------------------
	// page_loaded()
	// -------------------------------------------------------------------------

	/**
	 * Test page_loaded sets edit to true for existing membership.
	 */
	public function test_page_loaded_sets_edit_true_for_existing_membership(): void {
		$_REQUEST['id'] = $this->membership->get_id();

		$this->page->page_loaded();

		$this->assertTrue($this->page->edit);
	}

	/**
	 * Test page_loaded sets the object property.
	 */
	public function test_page_loaded_sets_object(): void {
		$_REQUEST['id'] = $this->membership->get_id();

		$this->page->page_loaded();

		$this->assertNotNull($this->page->object);
		$this->assertInstanceOf(Membership::class, $this->page->object);
	}

	/**
	 * Test page_loaded calls add_swap_notices (adds notice when swap scheduled).
	 */
	public function test_page_loaded_calls_add_swap_notices(): void {
		$swap_time = strtotime('+100 days');
		$this->schedule_swap_fixture($swap_time);

		$_REQUEST['id'] = $this->membership->get_id();

		$this->page->page_loaded();

		$notices = \WP_Ultimo()->notices->get_notices('network-admin');
		$this->assertNotEmpty($notices);
		$notice = array_shift($notices);
		$this->assertEquals('warning', $notice['type']);
	}

	// -------------------------------------------------------------------------
	// register_forms()
	// -------------------------------------------------------------------------

	/**
	 * Test register_forms adds the delete redirect filter.
	 */
	public function test_register_forms_adds_delete_redirect_filter(): void {
		global $wp_filter;

		// Snapshot the hook state before register_forms() so we only remove what we added.
		$hook_key        = 'wu_data_json_success_delete_membership_modal';
		$original_filter = $wp_filter[ $hook_key ] ?? null;

		$this->page->register_forms();

		$this->assertGreaterThan(
			0,
			has_filter($hook_key)
		);

		// Restore the original hook state.
		if (null === $original_filter) {
			unset($wp_filter[ $hook_key ]);
		} else {
			$wp_filter[ $hook_key ] = $original_filter;
		}
	}

	/**
	 * Test register_forms delete filter returns redirect_url key.
	 */
	public function test_register_forms_delete_filter_returns_redirect_url(): void {
		global $wp_filter;

		$hook_key        = 'wu_data_json_success_delete_membership_modal';
		$original_filter = $wp_filter[ $hook_key ] ?? null;

		$this->page->register_forms();

		$result = apply_filters($hook_key, []);

		$this->assertArrayHasKey('redirect_url', $result);
		$this->assertIsString($result['redirect_url']);

		if (null === $original_filter) {
			unset($wp_filter[ $hook_key ]);
		} else {
			$wp_filter[ $hook_key ] = $original_filter;
		}
	}

	/**
	 * Test register_forms does not throw.
	 */
	public function test_register_forms_does_not_throw(): void {
		global $wp_filter;

		$hook_key        = 'wu_data_json_success_delete_membership_modal';
		$original_filter = $wp_filter[ $hook_key ] ?? null;

		$this->page->register_forms();

		$this->assertTrue(true);

		if (null === $original_filter) {
			unset($wp_filter[ $hook_key ]);
		} else {
			$wp_filter[ $hook_key ] = $original_filter;
		}
	}

	// -------------------------------------------------------------------------
	// register_widgets()
	// -------------------------------------------------------------------------

	/**
	 * Test register_widgets does not throw for a basic membership.
	 */
	public function test_register_widgets_does_not_throw(): void {
		set_current_screen('dashboard-network');

		$this->page->object = $this->membership;
		$this->page->edit   = true;

		$this->page->register_widgets();

		$this->assertTrue(true);
	}

	/**
	 * Test register_widgets with locked membership.
	 */
	public function test_register_widgets_with_locked_membership(): void {
		set_current_screen('dashboard-network');

		$this->membership->lock();

		$this->page->object = $this->membership;
		$this->page->edit   = true;

		$this->page->register_widgets();

		$this->assertTrue(true);
	}

	/**
	 * Test register_widgets with swap preview mode hides events widget.
	 */
	public function test_register_widgets_in_swap_preview_mode(): void {
		global $wp_meta_boxes;

		set_current_screen('dashboard-network');

		$screen_id = get_current_screen()->id;
		unset($wp_meta_boxes[ $screen_id ]);

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('is_swap_preview');
		$property->setAccessible(true);
		$property->setValue($this->page, true);

		$this->page->object = $this->membership;
		$this->page->edit   = true;

		$this->page->register_widgets();

		// When is_swap_preview is true, the events widget must NOT be registered.
		$events_registered = false;
		if ( ! empty($wp_meta_boxes[ $screen_id ])) {
			foreach ($wp_meta_boxes[ $screen_id ] as $context => $priorities) {
				foreach ($priorities as $priority => $boxes) {
					if (isset($boxes['wp-ultimo-list-table-events'])) {
						$events_registered = true;
						break 2;
					}
				}
			}
		}

		$this->assertFalse($events_registered, 'The events widget must not be registered when is_swap_preview is true.');
	}

	/**
	 * Test register_widgets with lifetime membership (no convert button).
	 */
	public function test_register_widgets_with_lifetime_membership(): void {
		set_current_screen('dashboard-network');

		$this->membership->set_date_expiration(null);

		$this->page->object = $this->membership;
		$this->page->edit   = true;

		$this->page->register_widgets();

		$this->assertTrue(true);
	}

	/**
	 * Test register_widgets with gateway set.
	 */
	public function test_register_widgets_with_gateway(): void {
		set_current_screen('dashboard-network');

		$this->membership->set_gateway('manual');
		$this->membership->set_gateway_customer_id('cus_123');
		$this->membership->set_gateway_subscription_id('sub_123');

		$this->page->object = $this->membership;
		$this->page->edit   = true;

		$this->page->register_widgets();

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// payments_query_filter()
	// -------------------------------------------------------------------------

	/**
	 * Test payments_query_filter adds membership_id to args.
	 */
	public function test_payments_query_filter_adds_membership_id(): void {
		$this->page->object = $this->membership;

		$args   = ['some_arg' => 'value'];
		$result = $this->page->payments_query_filter($args);

		$this->assertArrayHasKey('membership_id', $result);
		$this->assertEquals($this->membership->get_id(), $result['membership_id']);
	}

	/**
	 * Test payments_query_filter preserves existing args.
	 */
	public function test_payments_query_filter_preserves_existing_args(): void {
		$this->page->object = $this->membership;

		$args   = [
			'existing_key' => 'existing_value',
			'number'       => 10,
		];
		$result = $this->page->payments_query_filter($args);

		$this->assertEquals('existing_value', $result['existing_key']);
		$this->assertEquals(10, $result['number']);
	}

	/**
	 * Test payments_query_filter returns array.
	 */
	public function test_payments_query_filter_returns_array(): void {
		$this->page->object = $this->membership;

		$result = $this->page->payments_query_filter([]);

		$this->assertIsArray($result);
	}

	// -------------------------------------------------------------------------
	// sites_query_filter()
	// -------------------------------------------------------------------------

	/**
	 * Test sites_query_filter adds meta_query with membership_id.
	 */
	public function test_sites_query_filter_adds_meta_query(): void {
		$this->page->object = $this->membership;

		$args   = [];
		$result = $this->page->sites_query_filter($args);

		$this->assertArrayHasKey('meta_query', $result);
		$this->assertArrayHasKey('membership_id', $result['meta_query']);
		$this->assertEquals('wu_membership_id', $result['meta_query']['membership_id']['key']);
		$this->assertEquals($this->membership->get_id(), $result['meta_query']['membership_id']['value']);
	}

	/**
	 * Test sites_query_filter preserves existing args.
	 */
	public function test_sites_query_filter_preserves_existing_args(): void {
		$this->page->object = $this->membership;

		$args   = ['existing_key' => 'existing_value'];
		$result = $this->page->sites_query_filter($args);

		$this->assertEquals('existing_value', $result['existing_key']);
	}

	/**
	 * Test sites_query_filter returns array.
	 */
	public function test_sites_query_filter_returns_array(): void {
		$this->page->object = $this->membership;

		$result = $this->page->sites_query_filter([]);

		$this->assertIsArray($result);
	}

	// -------------------------------------------------------------------------
	// customer_query_filter()
	// -------------------------------------------------------------------------

	/**
	 * Test customer_query_filter adds id from membership customer_id.
	 */
	public function test_customer_query_filter_adds_customer_id(): void {
		$this->page->object = $this->membership;

		$args   = [];
		$result = $this->page->customer_query_filter($args);

		$this->assertArrayHasKey('id', $result);
		$this->assertEquals($this->membership->get_customer_id(), $result['id']);
	}

	/**
	 * Test customer_query_filter preserves existing args.
	 */
	public function test_customer_query_filter_preserves_existing_args(): void {
		$this->page->object = $this->membership;

		$args   = ['existing_key' => 'existing_value'];
		$result = $this->page->customer_query_filter($args);

		$this->assertEquals('existing_value', $result['existing_key']);
	}

	/**
	 * Test customer_query_filter returns array.
	 */
	public function test_customer_query_filter_returns_array(): void {
		$this->page->object = $this->membership;

		$result = $this->page->customer_query_filter([]);

		$this->assertIsArray($result);
	}

	// -------------------------------------------------------------------------
	// events_query_filter()
	// -------------------------------------------------------------------------

	/**
	 * Test events_query_filter adds object_type and object_id.
	 */
	public function test_events_query_filter_adds_object_type_and_id(): void {
		$this->page->object = $this->membership;

		$args   = [];
		$result = $this->page->events_query_filter($args);

		$this->assertArrayHasKey('object_type', $result);
		$this->assertEquals('membership', $result['object_type']);
		$this->assertArrayHasKey('object_id', $result);
		$this->assertEquals(absint($this->membership->get_id()), $result['object_id']);
	}

	/**
	 * Test events_query_filter merges with existing args.
	 */
	public function test_events_query_filter_merges_with_existing_args(): void {
		$this->page->object = $this->membership;

		$args   = [
			'existing_key' => 'existing_value',
			'number'       => 5,
		];
		$result = $this->page->events_query_filter($args);

		$this->assertEquals('existing_value', $result['existing_key']);
		$this->assertEquals(5, $result['number']);
	}

	/**
	 * Test events_query_filter returns array.
	 */
	public function test_events_query_filter_returns_array(): void {
		$this->page->object = $this->membership;

		$result = $this->page->events_query_filter([]);

		$this->assertIsArray($result);
	}

	// -------------------------------------------------------------------------
	// handle_save()
	// -------------------------------------------------------------------------

	/**
	 * Test handle_save sets auto_renew to false when not in POST.
	 */
	public function test_handle_save_sets_auto_renew_false_when_absent(): void {
		$mock_membership = $this->createMock(Membership::class);
		$mock_membership->method('save')->willReturn(new \WP_Error('test', 'Error'));
		$mock_membership->method('get_billing_address')->willReturn(new \WP_Ultimo\Objects\Billing_Address());

		$this->page->object = $mock_membership;

		unset($_POST['auto_renew'], $_REQUEST['auto_renew']);

		$buffer_level = ob_get_level();
		$this->page->handle_save();

		while (ob_get_level() > $buffer_level) {
			ob_end_clean();
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Test asserts handle_save() normalizes the raw request value.
		$this->assertFalse($_POST['auto_renew']);
	}

	/**
	 * Test handle_save returns false when billing address validation fails.
	 */
	public function test_handle_save_returns_false_on_billing_address_validation_error(): void {
		$mock_billing_address = $this->createMock(\WP_Ultimo\Objects\Billing_Address::class);
		$mock_billing_address->method('load_attributes_from_post')->willReturn(null);
		$mock_billing_address->method('validate')->willReturn(new \WP_Error('invalid', 'Invalid billing address'));

		$mock_membership = $this->createMock(Membership::class);
		$mock_membership->method('get_billing_address')->willReturn($mock_billing_address);

		$this->page->object = $mock_membership;

		$result = $this->page->handle_save();

		$this->assertFalse($result);
	}

	/**
	 * Test handle_save adds error notice when billing address validation fails.
	 */
	public function test_handle_save_adds_error_notice_on_billing_validation_failure(): void {
		$mock_billing_address = $this->createMock(\WP_Ultimo\Objects\Billing_Address::class);
		$mock_billing_address->method('load_attributes_from_post')->willReturn(null);
		$mock_billing_address->method('validate')->willReturn(new \WP_Error('invalid', 'Invalid billing address'));

		$mock_membership = $this->createMock(Membership::class);
		$mock_membership->method('get_billing_address')->willReturn($mock_billing_address);

		$this->page->object = $mock_membership;

		$this->page->handle_save();

		$notices = \WP_Ultimo()->notices->get_notices('network-admin');
		$this->assertNotEmpty($notices);
	}

	/**
	 * Test handle_save calls handle_convert_to_lifetime when submit_button is convert_to_lifetime.
	 */
	public function test_handle_save_routes_to_convert_to_lifetime(): void {
		$mock_billing_address = $this->createMock(\WP_Ultimo\Objects\Billing_Address::class);
		$mock_billing_address->method('load_attributes_from_post')->willReturn(null);
		$mock_billing_address->method('validate')->willReturn(true);

		$mock_membership = $this->createMock(Membership::class);
		$mock_membership->method('get_billing_address')->willReturn($mock_billing_address);
		// Expect set_date_expiration(null) to be called exactly once — proves routing to handle_convert_to_lifetime().
		$mock_membership->expects($this->once())
			->method('set_date_expiration')
			->with(null);
		$mock_membership->method('save')->willReturn(new \WP_Error('test', 'Error'));

		$this->page->object = $mock_membership;

		$_REQUEST['submit_button'] = 'convert_to_lifetime';
		$_POST['submit_button']    = 'convert_to_lifetime';

		$result = $this->page->handle_save();

		// handle_convert_to_lifetime returns false on save error.
		$this->assertFalse($result);
	}

	/**
	 * Test handle_save with swap preview path deletes scheduled swap.
	 */
	public function test_handle_save_in_swap_preview_deletes_scheduled_swap(): void {
		$mock_billing_address = $this->createMock(\WP_Ultimo\Objects\Billing_Address::class);
		$mock_billing_address->method('load_attributes_from_post')->willReturn(null);
		$mock_billing_address->method('validate')->willReturn(true);

		$mock_membership = $this->createMock(Membership::class);
		$mock_membership->method('get_billing_address')->willReturn($mock_billing_address);
		$mock_membership->method('save')->willReturn(new \WP_Error('test', 'Error'));
		$mock_membership->expects($this->once())->method('delete_scheduled_swap');

		$this->page->object = $mock_membership;

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('is_swap_preview');
		$property->setAccessible(true);
		$property->setValue($this->page, true);

		$this->assert_redirected(fn() => $this->page->handle_save());
	}

	// -------------------------------------------------------------------------
	// handle_convert_to_lifetime()
	// -------------------------------------------------------------------------

	/**
	 * Test handle_convert_to_lifetime returns false on save error.
	 */
	public function test_handle_convert_to_lifetime_returns_false_on_save_error(): void {
		$mock_membership = $this->createMock(Membership::class);
		$mock_membership->method('save')->willReturn(new \WP_Error('test_error', 'Save failed'));
		$mock_membership->method('get_id')->willReturn(1);

		$this->page->object = $mock_membership;

		$result = $this->page->public_handle_convert_to_lifetime();

		$this->assertFalse($result);
	}

	/**
	 * Test handle_convert_to_lifetime adds error notice on save failure.
	 */
	public function test_handle_convert_to_lifetime_adds_error_notice_on_failure(): void {
		$mock_membership = $this->createMock(Membership::class);
		$mock_membership->method('save')->willReturn(new \WP_Error('test_error', 'Save failed'));
		$mock_membership->method('get_id')->willReturn(1);

		$this->page->object = $mock_membership;

		$this->page->public_handle_convert_to_lifetime();

		$notices = \WP_Ultimo()->notices->get_notices('network-admin');
		$this->assertNotEmpty($notices);
	}

	/**
	 * Test handle_convert_to_lifetime with real membership sets expiration to null.
	 */
	public function test_handle_convert_to_lifetime_sets_expiration_null(): void {
		$this->membership->set_date_expiration('2030-01-01 00:00:00');
		$this->membership->save();

		$this->page->object = $this->membership;
		$this->page->edit   = true;

		$this->assert_redirected(fn() => $this->page->public_handle_convert_to_lifetime());

		// Reload from DB to verify.
		$reloaded = wu_get_membership($this->membership->get_id());
		$this->assertNull($reloaded->get_date_expiration());
	}

	// -------------------------------------------------------------------------
	// handle_transfer_membership_modal()
	// -------------------------------------------------------------------------

	/**
	 * Test handle_transfer_membership_modal sends error when confirm is absent.
	 */
	public function test_handle_transfer_membership_modal_error_when_not_confirmed(): void {
		unset($_REQUEST['confirm']);

		$response = $this->capture_json_response(fn() => $this->page->handle_transfer_membership_modal());

		$this->assert_json_error_code($response, 'not-confirmed');
	}

	/**
	 * Test handle_transfer_membership_modal sends error when membership not found.
	 */
	public function test_handle_transfer_membership_modal_error_when_membership_not_found(): void {
		$_REQUEST['confirm'] = 1;
		$_REQUEST['id']      = 999999;

		$response = $this->capture_json_response(fn() => $this->page->handle_transfer_membership_modal());

		$this->assert_json_error_code($response, 'not-found');
	}

	/**
	 * Test handle_transfer_membership_modal sends error when target customer not found.
	 */
	public function test_handle_transfer_membership_modal_error_when_target_customer_not_found(): void {
		$_REQUEST['confirm']            = 1;
		$_REQUEST['id']                 = $this->membership->get_id();
		$_REQUEST['target_customer_id'] = 999999;

		$response = $this->capture_json_response(fn() => $this->page->handle_transfer_membership_modal());

		$this->assert_json_error_code($response, 'not-found');
	}

	// -------------------------------------------------------------------------
	// render_transfer_membership_modal()
	// -------------------------------------------------------------------------

	/**
	 * Test render_transfer_membership_modal returns early when membership not found.
	 */
	public function test_render_transfer_membership_modal_returns_early_when_not_found(): void {
		$_REQUEST['id'] = 999999;

		ob_start();
		$this->page->render_transfer_membership_modal();
		$output = ob_get_clean();

		// Should produce no output since membership not found.
		$this->assertEmpty($output);
	}

	/**
	 * Test render_transfer_membership_modal renders form when membership found.
	 */
	public function test_render_transfer_membership_modal_renders_when_found(): void {
		$_REQUEST['id'] = $this->membership->get_id();

		ob_start();
		$this->page->render_transfer_membership_modal();
		$output = ob_get_clean();

		// Should produce some output.
		$this->assertNotEmpty($output);
	}

	// -------------------------------------------------------------------------
	// render_edit_membership_product_modal()
	// -------------------------------------------------------------------------

	/**
	 * Test render_edit_membership_product_modal returns early when membership not found.
	 */
	public function test_render_edit_membership_product_modal_returns_early_when_not_found(): void {
		$_REQUEST['id'] = 999999;

		ob_start();
		$this->page->render_edit_membership_product_modal();
		$output = ob_get_clean();

		$this->assertEmpty($output);
	}

	/**
	 * Test render_edit_membership_product_modal renders when membership found.
	 */
	public function test_render_edit_membership_product_modal_renders_when_found(): void {
		$_REQUEST['id'] = $this->membership->get_id();

		ob_start();
		$this->page->render_edit_membership_product_modal();
		$output = ob_get_clean();

		$this->assertNotEmpty($output);
	}

	// -------------------------------------------------------------------------
	// handle_edit_membership_product_modal()
	// -------------------------------------------------------------------------

	/**
	 * Test handle_edit_membership_product_modal sends error when membership not found.
	 */
	public function test_handle_edit_membership_product_modal_error_when_membership_not_found(): void {
		$_REQUEST['id'] = 999999;

		$response = $this->capture_json_response(fn() => $this->page->handle_edit_membership_product_modal());

		$this->assert_json_error_code($response, 'membership-not-found');
	}

	/**
	 * Test handle_edit_membership_product_modal sends error when product not found.
	 */
	public function test_handle_edit_membership_product_modal_error_when_product_not_found(): void {
		$_REQUEST['id']         = $this->membership->get_id();
		$_REQUEST['product_id'] = 999999;

		$response = $this->capture_json_response(fn() => $this->page->handle_edit_membership_product_modal());

		$this->assert_json_error_code($response, 'product-not-found');
	}

	/**
	 * Test handle_edit_membership_product_modal adds product to membership.
	 */
	public function test_handle_edit_membership_product_modal_adds_product(): void {
		$product = wu_create_product(
			[
				'name'          => 'Test Addon Product',
				'slug'          => 'test-addon-product-' . uniqid(),
				'amount'        => 10,
				'type'          => 'addon',
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

		$_REQUEST['id']         = $this->membership->get_id();
		$_REQUEST['product_id'] = $product->get_id();
		$_REQUEST['quantity']   = 1;

		$payload = $this->capture_json_response(fn() => $this->page->handle_edit_membership_product_modal());

		// Assert the response indicates success (not an error).
		$this->assertTrue($payload['success'], 'handle_edit_membership_product_modal must call wp_send_json_success, not wp_send_json_error.');

		// Also verify the product was persisted on the membership.
		$reloaded = wu_get_membership($this->membership->get_id());
		$this->assertNotFalse($reloaded, 'Membership must still exist after product add.');

		$all_products = $reloaded->get_all_products();
		$product_ids  = array_map(fn($entry) => $entry['product']->get_id(), $all_products);
		$this->assertContains($product->get_id(), $product_ids, 'The added product must be present on the reloaded membership.');
	}

	// -------------------------------------------------------------------------
	// render_remove_membership_product()
	// -------------------------------------------------------------------------

	/**
	 * Test render_remove_membership_product returns early when membership not found.
	 */
	public function test_render_remove_membership_product_returns_early_when_not_found(): void {
		$_REQUEST['id'] = 999999;

		ob_start();
		$this->page->render_remove_membership_product();
		$output = ob_get_clean();

		$this->assertEmpty($output);
	}

	/**
	 * Test render_remove_membership_product renders when membership found.
	 */
	public function test_render_remove_membership_product_renders_when_found(): void {
		$_REQUEST['id'] = $this->membership->get_id();

		ob_start();
		$this->page->render_remove_membership_product();
		$output = ob_get_clean();

		$this->assertNotEmpty($output);
	}

	// -------------------------------------------------------------------------
	// handle_remove_membership_product()
	// -------------------------------------------------------------------------

	/**
	 * Test handle_remove_membership_product sends error when membership not found.
	 */
	public function test_handle_remove_membership_product_error_when_membership_not_found(): void {
		$_REQUEST['id'] = 999999;

		$response = $this->capture_json_response(fn() => $this->page->handle_remove_membership_product());

		$this->assert_json_error_code($response, 'membership-not-found');
	}

	/**
	 * Test handle_remove_membership_product sends error when product not found.
	 */
	public function test_handle_remove_membership_product_error_when_product_not_found(): void {
		$_REQUEST['id']         = $this->membership->get_id();
		$_REQUEST['product_id'] = 999999;

		$response = $this->capture_json_response(fn() => $this->page->handle_remove_membership_product());

		$this->assert_json_error_code($response, 'product-not-found');
	}

	// -------------------------------------------------------------------------
	// render_change_membership_plan_modal()
	// -------------------------------------------------------------------------

	/**
	 * Test render_change_membership_plan_modal returns early when membership not found.
	 */
	public function test_render_change_membership_plan_modal_returns_early_when_membership_not_found(): void {
		$_REQUEST['id'] = 999999;

		ob_start();
		$this->page->render_change_membership_plan_modal();
		$output = ob_get_clean();

		$this->assertEmpty($output);
	}

	/**
	 * Test render_change_membership_plan_modal returns early when product not found.
	 */
	public function test_render_change_membership_plan_modal_returns_early_when_product_not_found(): void {
		$_REQUEST['id']         = $this->membership->get_id();
		$_REQUEST['product_id'] = 999999;

		ob_start();
		$this->page->render_change_membership_plan_modal();
		$output = ob_get_clean();

		$this->assertEmpty($output);
	}

	/**
	 * Test render_change_membership_plan_modal renders when both membership and product found.
	 */
	public function test_render_change_membership_plan_modal_renders_when_found(): void {
		$product = wu_create_product(
			[
				'name'          => 'Test Plan for Change',
				'slug'          => 'test-plan-change-' . uniqid(),
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

		$_REQUEST['id']         = $this->membership->get_id();
		$_REQUEST['product_id'] = $product->get_id();

		ob_start();
		$this->page->render_change_membership_plan_modal();
		$output = ob_get_clean();

		$this->assertNotEmpty($output);
	}

	// -------------------------------------------------------------------------
	// handle_change_membership_plan_modal()
	// -------------------------------------------------------------------------

	/**
	 * Test handle_change_membership_plan_modal sends error when membership not found.
	 */
	public function test_handle_change_membership_plan_modal_error_when_membership_not_found(): void {
		$_REQUEST['id'] = 999999;

		$response = $this->capture_json_response(fn() => $this->page->handle_change_membership_plan_modal());

		$this->assert_json_error_code($response, 'membership-not-found');
	}

	/**
	 * Test handle_change_membership_plan_modal sends error when plan not found.
	 */
	public function test_handle_change_membership_plan_modal_error_when_plan_not_found(): void {
		$_REQUEST['id']      = $this->membership->get_id();
		$_REQUEST['plan_id'] = 999999;

		$response = $this->capture_json_response(fn() => $this->page->handle_change_membership_plan_modal());

		$this->assert_json_error_code($response, 'plan-not-found');
	}

	/**
	 * Test handle_change_membership_plan_modal sends error when same plan selected.
	 */
	public function test_handle_change_membership_plan_modal_error_when_same_plan(): void {
		$plan_id = $this->membership->get_plan_id();

		if ( ! $plan_id) {
			$this->markTestSkipped('Membership has no plan_id set.');
			return;
		}

		$_REQUEST['id']      = $this->membership->get_id();
		$_REQUEST['plan_id'] = $plan_id;

		$response = $this->capture_json_response(fn() => $this->page->handle_change_membership_plan_modal());

		$this->assert_json_error_code($response, 'same-plan');
	}

	// -------------------------------------------------------------------------
	// output_widget_products()
	// -------------------------------------------------------------------------

	/**
	 * Test output_widget_products does not throw.
	 */
	public function test_output_widget_products_does_not_throw(): void {
		$this->page->object = $this->membership;

		ob_start();
		$this->page->output_widget_products();
		ob_end_clean();

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// register_scripts()
	// -------------------------------------------------------------------------

	/**
	 * Test register_scripts does not throw.
	 */
	public function test_register_scripts_does_not_throw(): void {
		$this->page->register_scripts();

		$this->assertTrue(true);
	}
}
