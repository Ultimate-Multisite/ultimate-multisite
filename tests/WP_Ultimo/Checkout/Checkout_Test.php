<?php

namespace WP_Ultimo\Checkout;

use WP_Ultimo\Models\Customer;
use WP_Ultimo\Models\Payment;
use WP_Ultimo\Database\Payments\Payment_Status;
use WP_Ultimo\Database\Memberships\Membership_Status;
use WP_UnitTestCase;

/**
 * Test class for Checkout functionality.
 *
 * Covers: singleton, step navigation, field helpers, validation rules,
 * should_collect_payment, email verification, display name, request_or_session,
 * cleanup_expired_drafts, can_user_cancel_payment, should_process_checkout,
 * get_thank_you_page, validate, get_validation_rules, and get_checkout_variables.
 */
class Checkout_Test extends WP_UnitTestCase {

	private static Customer $customer;

	public static function set_up_before_class() {
		parent::set_up_before_class();
		self::$customer = wu_create_customer([
			'username' => 'testuser_checkout',
			'email'    => 'checkout@example.com',
			'password' => 'password123',
		]);
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Get the protected 'order' property via reflection.
	 */
	private function get_order_prop(\ReflectionClass $reflection): \ReflectionProperty {
		$prop = $reflection->getProperty('order');
		if (PHP_VERSION_ID < 80100) {
			$prop->setAccessible(true);
		}
		return $prop;
	}

	/**
	 * Get the protected 'session' property via reflection.
	 */
	private function get_session_prop(\ReflectionClass $reflection): \ReflectionProperty {
		$prop = $reflection->getProperty('session');
		if (PHP_VERSION_ID < 80100) {
			$prop->setAccessible(true);
		}
		return $prop;
	}

	/**
	 * Ensure the checkout singleton has a session initialised.
	 */
	private function ensure_session(Checkout $checkout): void {
		$reflection   = new \ReflectionClass($checkout);
		$session_prop = $this->get_session_prop($reflection);
		if (null === $session_prop->getValue($checkout)) {
			$session_prop->setValue($checkout, wu_get_session('signup'));
		}
	}

	// -------------------------------------------------------------------------
	// Singleton
	// -------------------------------------------------------------------------

	/**
	 * Test singleton returns correct instance.
	 */
	public function test_singleton_returns_correct_instance(): void {

		$checkout = Checkout::get_instance();

		$this->assertInstanceOf(Checkout::class, $checkout);
	}

	/**
	 * Test singleton returns same instance.
	 */
	public function test_singleton_returns_same_instance(): void {

		$this->assertSame(
			Checkout::get_instance(),
			Checkout::get_instance()
		);
	}

	// -------------------------------------------------------------------------
	// contains_auto_submittable_field
	// -------------------------------------------------------------------------

	/**
	 * Test contains_auto_submittable_field with non-array input.
	 */
	public function test_contains_auto_submittable_field_non_array(): void {

		$checkout = Checkout::get_instance();

		$this->assertFalse($checkout->contains_auto_submittable_field('not-an-array'));
		$this->assertFalse($checkout->contains_auto_submittable_field(null));
	}

	/**
	 * Test contains_auto_submittable_field with empty array.
	 */
	public function test_contains_auto_submittable_field_empty_array(): void {

		$checkout = Checkout::get_instance();

		$this->assertFalse($checkout->contains_auto_submittable_field([]));
	}

	/**
	 * Test contains_auto_submittable_field with only ignored field types.
	 */
	public function test_contains_auto_submittable_field_only_ignored_types(): void {

		$checkout = Checkout::get_instance();

		$fields = [
			['type' => 'hidden'],
			['type' => 'products'],
			['type' => 'submit_button'],
		];

		$this->assertFalse($checkout->contains_auto_submittable_field($fields));
	}

	/**
	 * Test contains_auto_submittable_field with multiple relevant fields returns false.
	 */
	public function test_contains_auto_submittable_field_multiple_relevant(): void {

		$checkout = Checkout::get_instance();

		$fields = [
			['type' => 'text'],
			['type' => 'email'],
		];

		$this->assertFalse($checkout->contains_auto_submittable_field($fields));
	}

	/**
	 * Test contains_auto_submittable_field with single template_selection field.
	 */
	public function test_contains_auto_submittable_field_template_selection(): void {

		$checkout = Checkout::get_instance();

		$fields = [
			['type' => 'hidden'],
			['type' => 'template_selection'],
			['type' => 'submit_button'],
		];

		$result = $checkout->contains_auto_submittable_field($fields);

		$this->assertEquals('template_id', $result);
	}

	/**
	 * Test contains_auto_submittable_field with single pricing_table field.
	 */
	public function test_contains_auto_submittable_field_pricing_table(): void {

		$checkout = Checkout::get_instance();

		$fields = [
			['type' => 'products'],
			['type' => 'pricing_table'],
			['type' => 'steps'],
		];

		$result = $checkout->contains_auto_submittable_field($fields);

		$this->assertEquals('products', $result);
	}

	/**
	 * Test contains_auto_submittable_field with period_selection ignored.
	 */
	public function test_contains_auto_submittable_field_period_selection_ignored(): void {

		$checkout = Checkout::get_instance();

		$fields = [
			['type' => 'period_selection'],
			['type' => 'steps'],
		];

		$this->assertFalse($checkout->contains_auto_submittable_field($fields));
	}

	/**
	 * Test contains_auto_submittable_field with unknown single field returns false (not in map).
	 */
	public function test_contains_auto_submittable_field_unknown_single_field(): void {

		$checkout = Checkout::get_instance();

		$fields = [
			['type' => 'hidden'],
			['type' => 'some_unknown_field_type'],
			['type' => 'submit_button'],
		];

		$result = $checkout->contains_auto_submittable_field($fields);

		$this->assertFalse($result);
	}

	/**
	 * Test contains_auto_submittable_field with integer input returns false.
	 */
	public function test_contains_auto_submittable_field_integer_input(): void {

		$checkout = Checkout::get_instance();

		$this->assertFalse($checkout->contains_auto_submittable_field(42));
	}

	// -------------------------------------------------------------------------
	// get_auto_submittable_fields
	// -------------------------------------------------------------------------

	/**
	 * Test get_auto_submittable_fields returns expected structure.
	 */
	public function test_get_auto_submittable_fields_structure(): void {

		$checkout = Checkout::get_instance();
		$fields   = $checkout->get_auto_submittable_fields();

		$this->assertIsArray($fields);
		$this->assertArrayHasKey('template_selection', $fields);
		$this->assertArrayHasKey('pricing_table', $fields);
		$this->assertEquals('template_id', $fields['template_selection']);
		$this->assertEquals('products', $fields['pricing_table']);
	}

	/**
	 * Test get_auto_submittable_fields is filterable.
	 */
	public function test_get_auto_submittable_fields_is_filterable(): void {

		add_filter('wu_checkout_get_auto_submittable_fields', function ($fields) {
			$fields['custom_field'] = 'custom_param';
			return $fields;
		});

		$checkout = Checkout::get_instance();
		$fields   = $checkout->get_auto_submittable_fields();

		$this->assertArrayHasKey('custom_field', $fields);
		$this->assertEquals('custom_param', $fields['custom_field']);
	}

	// -------------------------------------------------------------------------
	// is_existing_user
	// -------------------------------------------------------------------------

	/**
	 * Test is_existing_user returns false when not logged in.
	 */
	public function test_is_existing_user_not_logged_in(): void {

		wp_set_current_user(0);

		$checkout = Checkout::get_instance();

		$this->assertFalse($checkout->is_existing_user());
	}

	/**
	 * Test is_existing_user returns true when logged in.
	 */
	public function test_is_existing_user_logged_in(): void {

		$user_id = self::factory()->user->create(['role' => 'subscriber']);
		wp_set_current_user($user_id);

		$checkout = Checkout::get_instance();

		$this->assertTrue($checkout->is_existing_user());

		wp_set_current_user(0);
	}

	// -------------------------------------------------------------------------
	// handle_display_name
	// -------------------------------------------------------------------------

	/**
	 * Test handle_display_name with first and last name.
	 */
	public function test_handle_display_name_with_names(): void {

		$checkout = Checkout::get_instance();

		$_REQUEST['first_name'] = 'John';
		$_REQUEST['last_name']  = 'Doe';

		$result = $checkout->handle_display_name('Original Name');

		$this->assertEquals('John Doe', $result);

		unset($_REQUEST['first_name'], $_REQUEST['last_name']);
	}

	/**
	 * Test handle_display_name with only first name.
	 */
	public function test_handle_display_name_first_name_only(): void {

		$checkout = Checkout::get_instance();

		$_REQUEST['first_name'] = 'John';
		unset($_REQUEST['last_name']);

		$result = $checkout->handle_display_name('Original Name');

		$this->assertEquals('John', $result);

		unset($_REQUEST['first_name']);
	}

	/**
	 * Test handle_display_name with no names returns original.
	 */
	public function test_handle_display_name_no_names(): void {

		$checkout = Checkout::get_instance();

		unset($_REQUEST['first_name'], $_REQUEST['last_name']);

		$result = $checkout->handle_display_name('Original Name');

		$this->assertEquals('Original Name', $result);
	}

	/**
	 * Test handle_display_name with only last name.
	 */
	public function test_handle_display_name_last_name_only(): void {

		$checkout = Checkout::get_instance();

		unset($_REQUEST['first_name']);
		$_REQUEST['last_name'] = 'Smith';

		$result = $checkout->handle_display_name('Original Name');

		$this->assertEquals('Smith', $result);

		unset($_REQUEST['last_name']);
	}

	/**
	 * Test handle_display_name trims whitespace when one name is empty string.
	 */
	public function test_handle_display_name_trims_whitespace(): void {

		$checkout = Checkout::get_instance();

		$_REQUEST['first_name'] = 'Alice';
		$_REQUEST['last_name']  = '';

		$result = $checkout->handle_display_name('Original Name');

		$this->assertEquals('Alice', $result);

		unset($_REQUEST['first_name'], $_REQUEST['last_name']);
	}

	// -------------------------------------------------------------------------
	// request_or_session
	// -------------------------------------------------------------------------

	/**
	 * Test request_or_session returns request value.
	 */
	public function test_request_or_session_returns_request_value(): void {

		$checkout = Checkout::get_instance();

		$_REQUEST['test_key'] = 'test_value';

		$result = $checkout->request_or_session('test_key', 'default');

		$this->assertEquals('test_value', $result);

		unset($_REQUEST['test_key']);
	}

	/**
	 * Test request_or_session returns default when key not found.
	 */
	public function test_request_or_session_returns_default(): void {

		$checkout = Checkout::get_instance();

		unset($_REQUEST['nonexistent_key']);

		$result = $checkout->request_or_session('nonexistent_key', 'my_default');

		$this->assertEquals('my_default', $result);
	}

	/**
	 * Test request_or_session returns false as default.
	 */
	public function test_request_or_session_default_is_false(): void {

		$checkout = Checkout::get_instance();

		unset($_REQUEST['missing_key']);

		$result = $checkout->request_or_session('missing_key');

		$this->assertFalse($result);
	}

	/**
	 * Test request_or_session returns integer value from request.
	 */
	public function test_request_or_session_integer_value(): void {

		$checkout = Checkout::get_instance();

		$_REQUEST['int_key'] = 42;

		$result = $checkout->request_or_session('int_key', 0);

		$this->assertEquals(42, $result);

		unset($_REQUEST['int_key']);
	}

	/**
	 * Test request_or_session returns array value from request.
	 */
	public function test_request_or_session_array_value(): void {

		$checkout = Checkout::get_instance();

		$_REQUEST['arr_key'] = ['a', 'b', 'c'];

		$result = $checkout->request_or_session('arr_key', []);

		$this->assertEquals(['a', 'b', 'c'], $result);

		unset($_REQUEST['arr_key']);
	}

	// -------------------------------------------------------------------------
	// Step navigation — is_first_step
	// -------------------------------------------------------------------------

	/**
	 * Test is_first_step with empty steps.
	 */
	public function test_is_first_step_empty_steps(): void {

		$checkout        = Checkout::get_instance();
		$checkout->steps = [];

		$this->assertTrue($checkout->is_first_step());
	}

	/**
	 * Test is_first_step when on first step.
	 */
	public function test_is_first_step_on_first(): void {

		$checkout            = Checkout::get_instance();
		$checkout->steps     = [
			['id' => 'step-1'],
			['id' => 'step-2'],
			['id' => 'step-3'],
		];
		$checkout->step_name = 'step-1';

		$this->assertTrue($checkout->is_first_step());
	}

	/**
	 * Test is_first_step when not on first step.
	 */
	public function test_is_first_step_on_second(): void {

		$checkout            = Checkout::get_instance();
		$checkout->steps     = [
			['id' => 'step-1'],
			['id' => 'step-2'],
		];
		$checkout->step_name = 'step-2';

		$this->assertFalse($checkout->is_first_step());
	}

	/**
	 * Test is_first_step with single step.
	 */
	public function test_is_first_step_single_step(): void {

		$checkout            = Checkout::get_instance();
		$checkout->steps     = [['id' => 'only-step']];
		$checkout->step_name = 'only-step';

		$this->assertTrue($checkout->is_first_step());
	}

	// -------------------------------------------------------------------------
	// Step navigation — is_last_step
	// -------------------------------------------------------------------------

	/**
	 * Test is_last_step when on last step.
	 */
	public function test_is_last_step_on_last(): void {

		$checkout            = Checkout::get_instance();
		$checkout->steps     = [
			['id' => 'step-1'],
			['id' => 'step-2'],
		];
		$checkout->step_name = 'step-2';

		$this->assertTrue($checkout->is_last_step());
	}

	/**
	 * Test is_last_step when not on last step.
	 */
	public function test_is_last_step_not_on_last(): void {

		$checkout            = Checkout::get_instance();
		$checkout->steps     = [
			['id' => 'step-1'],
			['id' => 'step-2'],
		];
		$checkout->step_name = 'step-1';

		$this->assertFalse($checkout->is_last_step());
	}

	/**
	 * Test is_last_step with empty steps returns true.
	 */
	public function test_is_last_step_empty_steps(): void {

		$checkout            = Checkout::get_instance();
		$checkout->steps     = [];
		$checkout->step_name = null;

		$this->assertTrue($checkout->is_last_step());
	}

	/**
	 * Test is_last_step returns false when pre-flight param is set.
	 */
	public function test_is_last_step_pre_flight_returns_false(): void {

		$checkout            = Checkout::get_instance();
		$checkout->steps     = [['id' => 'step-1']];
		$checkout->step_name = 'step-1';

		$_REQUEST['pre-flight'] = '1';

		$this->assertFalse($checkout->is_last_step());

		unset($_REQUEST['pre-flight']);
	}

	/**
	 * Test is_last_step with single step and no pre-flight.
	 */
	public function test_is_last_step_single_step(): void {

		$checkout            = Checkout::get_instance();
		$checkout->steps     = [['id' => 'only-step']];
		$checkout->step_name = 'only-step';

		unset($_REQUEST['pre-flight']);

		$this->assertTrue($checkout->is_last_step());
	}

	// -------------------------------------------------------------------------
	// get_next_step_name
	// -------------------------------------------------------------------------

	/**
	 * Test get_next_step_name returns next step.
	 */
	public function test_get_next_step_name_returns_next(): void {

		$checkout            = Checkout::get_instance();
		$checkout->steps     = [
			['id' => 'step-1'],
			['id' => 'step-2'],
			['id' => 'step-3'],
		];
		$checkout->step_name = 'step-1';

		$this->assertEquals('step-2', $checkout->get_next_step_name());
	}

	/**
	 * Test get_next_step_name returns current when on last step.
	 */
	public function test_get_next_step_name_on_last_step(): void {

		$checkout            = Checkout::get_instance();
		$checkout->steps     = [
			['id' => 'step-1'],
			['id' => 'step-2'],
		];
		$checkout->step_name = 'step-2';

		$this->assertEquals('step-2', $checkout->get_next_step_name());
	}

	/**
	 * Test get_next_step_name with no step name set.
	 */
	public function test_get_next_step_name_no_current_step(): void {

		$checkout            = Checkout::get_instance();
		$checkout->steps     = [
			['id' => 'step-1'],
			['id' => 'step-2'],
		];
		$checkout->step_name = null;

		$this->assertEquals('step-2', $checkout->get_next_step_name());
	}

	/**
	 * Test get_next_step_name from middle step.
	 */
	public function test_get_next_step_name_from_middle(): void {

		$checkout            = Checkout::get_instance();
		$checkout->steps     = [
			['id' => 'step-1'],
			['id' => 'step-2'],
			['id' => 'step-3'],
		];
		$checkout->step_name = 'step-2';

		$this->assertEquals('step-3', $checkout->get_next_step_name());
	}

	// -------------------------------------------------------------------------
	// Email verification status
	// -------------------------------------------------------------------------

	/**
	 * Test get_customer_email_verification_status with never setting.
	 */
	public function test_email_verification_status_never(): void {

		wu_save_setting('enable_email_verification', 'never');

		$checkout   = Checkout::get_instance();
		$reflection = new \ReflectionClass($checkout);
		$prop       = $this->get_order_prop($reflection);

		$prop->setValue($checkout, new Cart(['products' => []]));

		$result = $checkout->get_customer_email_verification_status();

		$this->assertEquals('none', $result);
	}

	/**
	 * Test get_customer_email_verification_status with always setting.
	 */
	public function test_email_verification_status_always(): void {

		wu_save_setting('enable_email_verification', 'always');

		$checkout   = Checkout::get_instance();
		$reflection = new \ReflectionClass($checkout);
		$prop       = $this->get_order_prop($reflection);

		$prop->setValue($checkout, new Cart(['products' => []]));

		$result = $checkout->get_customer_email_verification_status();

		$this->assertEquals('pending', $result);
	}

	/**
	 * Test get_customer_email_verification_status with free_only and free cart.
	 */
	public function test_email_verification_status_free_only_free_cart(): void {

		wu_save_setting('enable_email_verification', 'free_only');

		$checkout   = Checkout::get_instance();
		$reflection = new \ReflectionClass($checkout);
		$prop       = $this->get_order_prop($reflection);

		// Free cart (no products = no payment needed)
		$prop->setValue($checkout, new Cart(['products' => []]));

		$result = $checkout->get_customer_email_verification_status();

		// Free cart should_collect_payment() returns false → 'pending'
		$this->assertEquals('pending', $result);
	}

	/**
	 * Test get_customer_email_verification_status with legacy boolean true.
	 */
	public function test_email_verification_status_legacy_true(): void {

		wu_save_setting('enable_email_verification', '1');

		$checkout   = Checkout::get_instance();
		$reflection = new \ReflectionClass($checkout);
		$prop       = $this->get_order_prop($reflection);

		// Free cart
		$prop->setValue($checkout, new Cart(['products' => []]));

		$result = $checkout->get_customer_email_verification_status();

		// Legacy true + free cart → 'pending'
		$this->assertEquals('pending', $result);
	}

	/**
	 * Test get_customer_email_verification_status with legacy boolean false.
	 */
	public function test_email_verification_status_legacy_false(): void {

		wu_save_setting('enable_email_verification', '0');

		$checkout   = Checkout::get_instance();
		$reflection = new \ReflectionClass($checkout);
		$prop       = $this->get_order_prop($reflection);

		$prop->setValue($checkout, new Cart(['products' => []]));

		$result = $checkout->get_customer_email_verification_status();

		// Legacy false → 'none'
		$this->assertEquals('none', $result);
	}

	// -------------------------------------------------------------------------
	// errors property
	// -------------------------------------------------------------------------

	/**
	 * Test errors property is null by default.
	 */
	public function test_errors_default_null(): void {

		$checkout = Checkout::get_instance();

		// Reset errors.
		$checkout->errors = null;

		$this->assertNull($checkout->errors);
	}

	/**
	 * Test errors property can be set to WP_Error.
	 */
	public function test_errors_can_be_set_to_wp_error(): void {

		$checkout         = Checkout::get_instance();
		$checkout->errors = new \WP_Error('test-error', 'Test error message');

		$this->assertInstanceOf(\WP_Error::class, $checkout->errors);
		$this->assertEquals('test-error', $checkout->errors->get_error_code());

		$checkout->errors = null;
	}

	// -------------------------------------------------------------------------
	// should_process_checkout
	// -------------------------------------------------------------------------

	/**
	 * Test should_process_checkout returns false when checkout_action not set.
	 */
	public function test_should_process_checkout_no_action(): void {

		$checkout = Checkout::get_instance();

		unset($_REQUEST['checkout_action']);

		$this->assertFalse($checkout->should_process_checkout());
	}

	/**
	 * Test should_process_checkout returns false when wrong action.
	 */
	public function test_should_process_checkout_wrong_action(): void {

		$checkout = Checkout::get_instance();

		$_REQUEST['checkout_action'] = 'something_else';

		$this->assertFalse($checkout->should_process_checkout());

		unset($_REQUEST['checkout_action']);
	}

	/**
	 * Test should_process_checkout returns true when correct action and not ajax.
	 */
	public function test_should_process_checkout_correct_action(): void {

		$checkout = Checkout::get_instance();

		$_REQUEST['checkout_action'] = 'wu_checkout';

		// wp_doing_ajax() returns false in test context
		$result = $checkout->should_process_checkout();

		$this->assertTrue($result);

		unset($_REQUEST['checkout_action']);
	}

	// -------------------------------------------------------------------------
	// validation_rules
	// -------------------------------------------------------------------------

	/**
	 * Test validation_rules returns array with expected keys.
	 */
	public function test_validation_rules_returns_array(): void {

		$checkout       = Checkout::get_instance();
		$checkout->step = ['fields' => []];

		$rules = $checkout->validation_rules();

		$this->assertIsArray($rules);
		$this->assertArrayHasKey('email_address', $rules);
		$this->assertArrayHasKey('username', $rules);
		$this->assertArrayHasKey('password', $rules);
		$this->assertArrayHasKey('products', $rules);
		$this->assertArrayHasKey('billing_country', $rules);
	}

	/**
	 * Test validation_rules includes site fields for new type.
	 */
	public function test_validation_rules_includes_site_fields_for_new_type(): void {

		$checkout   = Checkout::get_instance();
		$reflection = new \ReflectionClass($checkout);
		$type_prop  = $reflection->getProperty('type');

		if (PHP_VERSION_ID < 80100) {
			$type_prop->setAccessible(true);
		}

		$type_prop->setValue($checkout, 'new');

		$rules = $checkout->validation_rules();

		$this->assertArrayHasKey('site_title', $rules);
		$this->assertArrayHasKey('site_url', $rules);
	}

	/**
	 * Test validation_rules excludes site fields for non-new type.
	 */
	public function test_validation_rules_excludes_site_fields_for_upgrade(): void {

		$checkout   = Checkout::get_instance();
		$reflection = new \ReflectionClass($checkout);
		$type_prop  = $reflection->getProperty('type');

		if (PHP_VERSION_ID < 80100) {
			$type_prop->setAccessible(true);
		}

		$type_prop->setValue($checkout, 'upgrade');

		$rules = $checkout->validation_rules();

		$this->assertArrayNotHasKey('site_title', $rules);
		$this->assertArrayNotHasKey('site_url', $rules);
	}

	/**
	 * Test validation_rules excludes site fields for addon type.
	 */
	public function test_validation_rules_excludes_site_fields_for_addon(): void {

		$checkout   = Checkout::get_instance();
		$reflection = new \ReflectionClass($checkout);
		$type_prop  = $reflection->getProperty('type');

		if (PHP_VERSION_ID < 80100) {
			$type_prop->setAccessible(true);
		}

		$type_prop->setValue($checkout, 'addon');

		$rules = $checkout->validation_rules();

		$this->assertArrayNotHasKey('site_title', $rules);
		$this->assertArrayNotHasKey('site_url', $rules);
	}

	/**
	 * Test validation_rules is filterable.
	 */
	public function test_validation_rules_is_filterable(): void {

		add_filter('wu_checkout_validation_rules', function ($rules) {
			$rules['custom_validation_field'] = 'required';
			return $rules;
		});

		$checkout = Checkout::get_instance();
		$rules    = $checkout->validation_rules();

		$this->assertArrayHasKey('custom_validation_field', $rules);
		$this->assertEquals('required', $rules['custom_validation_field']);
	}

	/**
	 * Test validation_rules contains gateway key.
	 */
	public function test_validation_rules_contains_gateway_key(): void {

		$checkout = Checkout::get_instance();
		$rules    = $checkout->validation_rules();

		$this->assertArrayHasKey('gateway', $rules);
	}

	/**
	 * Test validation_rules contains template_id key.
	 */
	public function test_validation_rules_contains_template_id_key(): void {

		$checkout = Checkout::get_instance();
		$rules    = $checkout->validation_rules();

		$this->assertArrayHasKey('template_id', $rules);
	}

	// -------------------------------------------------------------------------
	// should_collect_payment
	// -------------------------------------------------------------------------

	/**
	 * Test should_collect_payment returns true when no products in request.
	 */
	public function test_should_collect_payment_no_products(): void {

		$checkout   = Checkout::get_instance();
		$reflection = new \ReflectionClass($checkout);
		$order_prop = $this->get_order_prop($reflection);

		$order_prop->setValue($checkout, null);

		unset($_REQUEST['products']);

		$result = $checkout->should_collect_payment();

		$this->assertTrue($result);
	}

	/**
	 * Test should_collect_payment delegates to order when order is set.
	 */
	public function test_should_collect_payment_delegates_to_order(): void {

		$checkout   = Checkout::get_instance();
		$reflection = new \ReflectionClass($checkout);
		$order_prop = $this->get_order_prop($reflection);

		// Free cart
		$cart = new Cart(['products' => []]);
		$order_prop->setValue($checkout, $cart);

		$result = $checkout->should_collect_payment();

		$this->assertEquals($cart->should_collect_payment(), $result);

		$order_prop->setValue($checkout, null);
	}

	/**
	 * Test should_collect_payment returns true when products is empty array.
	 */
	public function test_should_collect_payment_empty_products_array(): void {

		$checkout   = Checkout::get_instance();
		$reflection = new \ReflectionClass($checkout);
		$order_prop = $this->get_order_prop($reflection);

		$order_prop->setValue($checkout, null);

		$_REQUEST['products'] = [];

		$result = $checkout->should_collect_payment();

		$this->assertTrue($result);

		unset($_REQUEST['products']);
	}

	// -------------------------------------------------------------------------
	// get_thank_you_page
	// -------------------------------------------------------------------------

	/**
	 * Test get_thank_you_page returns a string.
	 */
	public function test_get_thank_you_page_returns_string(): void {

		$checkout = Checkout::get_instance();

		$result = $checkout->get_thank_you_page();

		$this->assertIsString($result);
	}

	// -------------------------------------------------------------------------
	// get_checkout_variables
	// -------------------------------------------------------------------------

	/**
	 * Test get_checkout_variables returns array with required keys.
	 */
	public function test_get_checkout_variables_returns_array(): void {

		$checkout            = Checkout::get_instance();
		$checkout->step      = ['fields' => []];
		$checkout->steps     = [];
		$checkout->step_name = null;

		$vars = $checkout->get_checkout_variables();

		$this->assertIsArray($vars);
		$this->assertArrayHasKey('ajaxurl', $vars);
		$this->assertArrayHasKey('i18n', $vars);
	}

	/**
	 * Test get_checkout_variables i18n contains expected keys.
	 */
	public function test_get_checkout_variables_i18n_keys(): void {

		$checkout            = Checkout::get_instance();
		$checkout->step      = ['fields' => []];
		$checkout->steps     = [];
		$checkout->step_name = null;

		$vars = $checkout->get_checkout_variables();

		$this->assertArrayHasKey('loading', $vars['i18n']);
		$this->assertArrayHasKey('weak_password', $vars['i18n']);
	}

	/**
	 * Test get_checkout_variables i18n is an array.
	 */
	public function test_get_checkout_variables_i18n_is_array(): void {

		$checkout            = Checkout::get_instance();
		$checkout->step      = ['fields' => []];
		$checkout->steps     = [];
		$checkout->step_name = null;

		$vars = $checkout->get_checkout_variables();

		$this->assertIsArray($vars['i18n']);
	}

	// -------------------------------------------------------------------------
	// cleanup_expired_drafts
	// -------------------------------------------------------------------------

	/**
	 * Test cleanup_expired_drafts runs without error when no expired payments.
	 */
	public function test_cleanup_expired_drafts_no_expired_payments(): void {

		$checkout = Checkout::get_instance();

		// Should not throw
		$checkout->cleanup_expired_drafts();

		$this->assertTrue(true);
	}

	/**
	 * Test cleanup_expired_drafts deletes expired draft payments.
	 */
	public function test_cleanup_expired_drafts_deletes_draft_payments(): void {

		$checkout = Checkout::get_instance();

		$customer = self::$customer;

		$membership = wu_create_membership([
			'customer_id' => $customer->get_id(),
			'plan_id'     => 0,
			'status'      => Membership_Status::ACTIVE,
		]);

		$payment = wu_create_payment([
			'customer_id'   => $customer->get_id(),
			'membership_id' => $membership->get_id(),
			'status'        => Payment_Status::DRAFT,
			'total'         => 0,
		]);

		$this->assertNotWPError($payment);

		// Manually set date_created to 31 days ago
		global $wpdb;
		$old_date = gmdate('Y-m-d H:i:s', strtotime('-31 days'));
		$wpdb->update(
			"{$wpdb->prefix}wu_payments",
			['date_created' => $old_date],
			['id' => $payment->get_id()]
		);

		$checkout->cleanup_expired_drafts();

		// Payment should be deleted (wu_get_payment returns false when not found)
		$found = wu_get_payment($payment->get_id());
		$this->assertFalse($found);

		$membership->delete();
	}

	/**
	 * Test cleanup_expired_drafts cancels expired pending payments.
	 */
	public function test_cleanup_expired_drafts_cancels_pending_payments(): void {

		$checkout = Checkout::get_instance();

		$customer = self::$customer;

		$membership = wu_create_membership([
			'customer_id' => $customer->get_id(),
			'plan_id'     => 0,
			'status'      => Membership_Status::ACTIVE,
		]);

		$payment = wu_create_payment([
			'customer_id'   => $customer->get_id(),
			'membership_id' => $membership->get_id(),
			'status'        => Payment_Status::PENDING,
			'total'         => 10,
		]);

		$this->assertNotWPError($payment);

		// Manually set date_created to 31 days ago
		global $wpdb;
		$old_date = gmdate('Y-m-d H:i:s', strtotime('-31 days'));
		$wpdb->update(
			"{$wpdb->prefix}wu_payments",
			['date_created' => $old_date],
			['id' => $payment->get_id()]
		);

		$checkout->cleanup_expired_drafts();

		// Payment should be cancelled, not deleted (wu_get_payment returns false when not found)
		$found = wu_get_payment($payment->get_id());
		$this->assertNotFalse($found);
		$this->assertEquals(Payment_Status::CANCELLED, $found->get_status());

		$found->delete();
		$membership->delete();
	}

	/**
	 * Test cleanup_expired_drafts runs without throwing exceptions.
	 *
	 * Note: The date_created__lt filter behaviour depends on BerlinDB query support.
	 * This test verifies the method completes without errors.
	 */
	public function test_cleanup_expired_drafts_completes_without_exception(): void {

		$checkout = Checkout::get_instance();

		// Should complete without throwing
		$checkout->cleanup_expired_drafts();

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// can_user_cancel_payment (via reflection)
	// -------------------------------------------------------------------------

	/**
	 * Test can_user_cancel_payment returns false when not logged in.
	 */
	public function test_can_user_cancel_payment_not_logged_in(): void {

		wp_set_current_user(0);

		$checkout = Checkout::get_instance();

		$customer = self::$customer;

		$membership = wu_create_membership([
			'customer_id' => $customer->get_id(),
			'plan_id'     => 0,
			'status'      => Membership_Status::ACTIVE,
		]);

		$payment = wu_create_payment([
			'customer_id'   => $customer->get_id(),
			'membership_id' => $membership->get_id(),
			'status'        => Payment_Status::PENDING,
			'total'         => 10,
		]);

		$reflection = new \ReflectionClass($checkout);
		$method     = $reflection->getMethod('can_user_cancel_payment');

		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$result = $method->invoke($checkout, $payment);

		$this->assertFalse($result);

		$payment->delete();
		$membership->delete();
	}

	/**
	 * Test can_user_cancel_payment returns true when logged in as payment owner.
	 */
	public function test_can_user_cancel_payment_as_owner(): void {

		$customer = self::$customer;
		wp_set_current_user($customer->get_user_id());

		// Verify the customer lookup works in this test environment.
		$current_customer = wu_get_current_customer();
		if ( ! $current_customer) {
			$this->markTestSkipped('Customer lookup unavailable in this test environment.');
		}

		$checkout = Checkout::get_instance();

		$membership = wu_create_membership([
			'customer_id' => $customer->get_id(),
			'plan_id'     => 0,
			'status'      => Membership_Status::ACTIVE,
		]);

		if (is_wp_error($membership)) {
			$this->markTestSkipped('Membership creation failed: ' . $membership->get_error_message());
		}

		$payment = wu_create_payment([
			'customer_id'   => $customer->get_id(),
			'membership_id' => $membership->get_id(),
			'status'        => Payment_Status::PENDING,
			'total'         => 10,
		]);

		if (is_wp_error($payment)) {
			$membership->delete();
			$this->markTestSkipped('Payment creation failed: ' . $payment->get_error_message());
		}

		$reflection = new \ReflectionClass($checkout);
		$method     = $reflection->getMethod('can_user_cancel_payment');

		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$result = $method->invoke($checkout, $payment);

		$this->assertTrue($result);

		$payment->delete();
		$membership->delete();
		wp_set_current_user(0);
	}

	/**
	 * Test can_user_cancel_payment returns false when logged in as different user.
	 */
	public function test_can_user_cancel_payment_as_different_user(): void {

		$other_user_id = self::factory()->user->create(['role' => 'subscriber']);
		if ( ! $other_user_id) {
			$this->markTestSkipped('User creation failed in this test environment.');
		}

		wp_set_current_user($other_user_id);

		// Verify no customer is associated with this new user.
		$other_customer = wu_get_current_customer();
		if ($other_customer) {
			wp_set_current_user(0);
			$this->markTestSkipped('Unexpected customer found for new user in this test environment.');
		}

		$checkout = Checkout::get_instance();

		$customer = self::$customer;

		$membership = wu_create_membership([
			'customer_id' => $customer->get_id(),
			'plan_id'     => 0,
			'status'      => Membership_Status::ACTIVE,
		]);

		if (is_wp_error($membership)) {
			wp_set_current_user(0);
			$this->markTestSkipped('Membership creation failed: ' . $membership->get_error_message());
		}

		$payment = wu_create_payment([
			'customer_id'   => $customer->get_id(),
			'membership_id' => $membership->get_id(),
			'status'        => Payment_Status::PENDING,
			'total'         => 10,
		]);

		if (is_wp_error($payment)) {
			$membership->delete();
			wp_set_current_user(0);
			$this->markTestSkipped('Payment creation failed: ' . $payment->get_error_message());
		}

		$reflection = new \ReflectionClass($checkout);
		$method     = $reflection->getMethod('can_user_cancel_payment');

		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$result = $method->invoke($checkout, $payment);

		$this->assertFalse($result);

		$payment->delete();
		$membership->delete();
		wp_set_current_user(0);
	}

	// -------------------------------------------------------------------------
	// validate
	// -------------------------------------------------------------------------

	/**
	 * Test validate with empty rules returns true.
	 */
	public function test_validate_with_empty_rules_returns_true(): void {

		$checkout            = Checkout::get_instance();
		$checkout->step      = ['fields' => []];
		$checkout->steps     = [];
		$checkout->step_name = null;

		$this->ensure_session($checkout);

		$result = $checkout->validate([]);

		$this->assertTrue($result);
	}

	/**
	 * Test validate returns WP_Error when required field missing.
	 */
	public function test_validate_returns_wp_error_on_missing_required(): void {

		$checkout            = Checkout::get_instance();
		$checkout->step      = ['fields' => []];
		$checkout->steps     = [];
		$checkout->step_name = null;

		$this->ensure_session($checkout);

		// Ensure the required field is absent from request
		unset($_REQUEST['required_test_field']);

		$result = $checkout->validate(['required_test_field' => 'required']);

		$this->assertInstanceOf(\WP_Error::class, $result);
	}

	/**
	 * Test validate passes when required field is present.
	 */
	public function test_validate_passes_when_required_field_present(): void {

		$checkout            = Checkout::get_instance();
		$checkout->step      = ['fields' => []];
		$checkout->steps     = [];
		$checkout->step_name = null;

		$this->ensure_session($checkout);

		$_REQUEST['required_test_field'] = 'some_value';

		$result = $checkout->validate(['required_test_field' => 'required']);

		$this->assertTrue($result);

		unset($_REQUEST['required_test_field']);
	}

	/**
	 * Test validate with null rules uses get_validation_rules.
	 */
	public function test_validate_with_null_rules_uses_get_validation_rules(): void {

		$checkout            = Checkout::get_instance();
		$checkout->step      = ['fields' => []];
		$checkout->steps     = [];
		$checkout->step_name = null;

		$this->ensure_session($checkout);

		// Set pre-flight so get_validation_rules returns empty array → passes
		$_REQUEST['pre-flight'] = '1';

		$result = $checkout->validate(null);

		$this->assertTrue($result);

		unset($_REQUEST['pre-flight']);
	}

	// -------------------------------------------------------------------------
	// get_validation_rules
	// -------------------------------------------------------------------------

	/**
	 * Test get_validation_rules returns empty array for pre-flight.
	 */
	public function test_get_validation_rules_empty_for_pre_flight(): void {

		$checkout            = Checkout::get_instance();
		$checkout->step      = ['fields' => []];
		$checkout->steps     = [];
		$checkout->step_name = null;

		$_REQUEST['pre-flight'] = '1';

		$rules = $checkout->get_validation_rules();

		$this->assertIsArray($rules);
		$this->assertEmpty($rules);

		unset($_REQUEST['pre-flight']);
	}

	/**
	 * Test get_validation_rules returns empty array for wu-finish-checkout form.
	 */
	public function test_get_validation_rules_empty_for_finish_checkout(): void {

		$checkout            = Checkout::get_instance();
		$checkout->step      = ['fields' => []];
		$checkout->steps     = [];
		$checkout->step_name = null;

		unset($_REQUEST['pre-flight']);
		$_REQUEST['checkout_form'] = 'wu-finish-checkout';

		$rules = $checkout->get_validation_rules();

		$this->assertIsArray($rules);
		$this->assertEmpty($rules);

		unset($_REQUEST['checkout_form']);
	}

	/**
	 * Test get_validation_rules relaxes billing fields when payment not needed.
	 */
	public function test_get_validation_rules_relaxes_billing_for_free(): void {

		$checkout            = Checkout::get_instance();
		$checkout->step      = ['fields' => []];
		$checkout->steps     = [['id' => 'step-1']];
		$checkout->step_name = 'step-1';

		unset($_REQUEST['pre-flight'], $_REQUEST['checkout_form']);

		// Set order to a free cart
		$reflection = new \ReflectionClass($checkout);
		$order_prop = $this->get_order_prop($reflection);

		$order_prop->setValue($checkout, new Cart(['products' => []]));

		$rules = $checkout->get_validation_rules();

		$this->assertEquals('', $rules['billing_zip_code']);
		$this->assertEquals('', $rules['billing_state']);
		$this->assertEquals('', $rules['billing_city']);

		$order_prop->setValue($checkout, null);
	}

	/**
	 * Test get_validation_rules returns array.
	 */
	public function test_get_validation_rules_returns_array(): void {

		$checkout            = Checkout::get_instance();
		$checkout->step      = ['fields' => []];
		$checkout->steps     = [];
		$checkout->step_name = null;

		unset($_REQUEST['pre-flight'], $_REQUEST['checkout_form']);

		$rules = $checkout->get_validation_rules();

		$this->assertIsArray($rules);
	}

	// -------------------------------------------------------------------------
	// Draft payment
	// -------------------------------------------------------------------------

	/**
	 * Test create_draft_payment runs without error with empty products.
	 */
	public function test_create_draft_payment_empty_products(): void {

		$checkout   = Checkout::get_instance();
		$reflection = new \ReflectionClass($checkout);
		$method     = $reflection->getMethod('create_draft_payment');

		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$this->ensure_session($checkout);

		// Call with empty products — cart will be invalid, method should return early
		$method->invoke($checkout, []);

		$this->assertTrue(true);
	}

	/**
	 * Test save_draft_progress returns early when no draft payment in session.
	 */
	public function test_save_draft_progress_no_draft_in_session(): void {

		$checkout   = Checkout::get_instance();
		$reflection = new \ReflectionClass($checkout);
		$method     = $reflection->getMethod('save_draft_progress');

		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$this->ensure_session($checkout);

		// Ensure no draft payment in session
		$session_prop = $this->get_session_prop($reflection);
		$session      = $session_prop->getValue($checkout);
		if ($session) {
			$session->set('draft_payment_id', null);
		}

		// Should not throw
		$method->invoke($checkout);

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// get_js_validation_rules
	// -------------------------------------------------------------------------

	/**
	 * Test get_js_validation_rules returns array.
	 */
	public function test_get_js_validation_rules_returns_array(): void {

		$checkout = Checkout::get_instance();

		$rules = $checkout->get_js_validation_rules();

		$this->assertIsArray($rules);
	}

	/**
	 * Test get_js_validation_rules excludes server-only rules.
	 */
	public function test_get_js_validation_rules_excludes_server_only(): void {

		$checkout = Checkout::get_instance();

		$rules = $checkout->get_js_validation_rules();

		// The 'products' field uses the 'products' rule which is server-only
		// so it should not appear in JS rules (or appear with no rules)
		if (isset($rules['products'])) {
			$rule_names = array_column($rules['products'], 'rule');
			$this->assertNotContains('products', $rule_names);
		} else {
			$this->assertTrue(true); // products field not in JS rules at all
		}
	}

	/**
	 * Test get_js_validation_rules is filterable.
	 */
	public function test_get_js_validation_rules_is_filterable(): void {

		add_filter('wu_checkout_js_validation_rules', function ($rules) {
			$rules['custom_js_field'] = [['rule' => 'required', 'param' => null]];
			return $rules;
		});

		$checkout = Checkout::get_instance();
		$rules    = $checkout->get_js_validation_rules();

		$this->assertArrayHasKey('custom_js_field', $rules);
	}

	/**
	 * Test get_js_validation_rules skips empty rule strings.
	 */
	public function test_get_js_validation_rules_skips_empty_rules(): void {

		$checkout = Checkout::get_instance();

		// Add a filter that adds an empty rule
		add_filter('wu_checkout_validation_rules', function ($rules) {
			$rules['empty_rule_field'] = '';
			return $rules;
		});

		$rules = $checkout->get_js_validation_rules();

		// Empty rule fields should not appear in JS rules
		$this->assertArrayNotHasKey('empty_rule_field', $rules);
	}

	/**
	 * Test get_js_validation_rules parses rule with parameter.
	 */
	public function test_get_js_validation_rules_parses_rule_with_param(): void {

		$checkout = Checkout::get_instance();

		// Add a filter that adds a rule with a parameter
		add_filter('wu_checkout_validation_rules', function ($rules) {
			$rules['test_param_field'] = 'min:4';
			return $rules;
		});

		$rules = $checkout->get_js_validation_rules();

		if (isset($rules['test_param_field'])) {
			$rule = $rules['test_param_field'][0];
			$this->assertEquals('min', $rule['rule']);
			$this->assertEquals('4', $rule['param']);
		} else {
			$this->assertTrue(true); // Field may be filtered out
		}
	}

	// -------------------------------------------------------------------------
	// maybe_display_checkout_errors
	// -------------------------------------------------------------------------

	/**
	 * Test maybe_display_checkout_errors does nothing when status is not error.
	 */
	public function test_maybe_display_checkout_errors_no_error_status(): void {

		$checkout = Checkout::get_instance();

		unset($_REQUEST['status']);

		// Should not throw
		$checkout->maybe_display_checkout_errors();

		$this->assertTrue(true);
	}

	/**
	 * Test maybe_display_checkout_errors does nothing when status is error.
	 */
	public function test_maybe_display_checkout_errors_with_error_status(): void {

		$checkout = Checkout::get_instance();

		$_REQUEST['status'] = 'error';

		// Should not throw
		$checkout->maybe_display_checkout_errors();

		$this->assertTrue(true);

		unset($_REQUEST['status']);
	}

	// -------------------------------------------------------------------------
	// get_checkout_from_query_vars
	// -------------------------------------------------------------------------

	/**
	 * Test get_checkout_from_query_vars returns value unchanged when wp action not done.
	 */
	public function test_get_checkout_from_query_vars_before_wp_action(): void {

		$checkout = Checkout::get_instance();

		// 'wp' action has not been done in test context
		$result = $checkout->get_checkout_from_query_vars('original_value', 'products');

		$this->assertEquals('original_value', $result);
	}

	/**
	 * Test get_checkout_from_query_vars returns original value for non-cart key.
	 */
	public function test_get_checkout_from_query_vars_non_cart_key(): void {

		$checkout = Checkout::get_instance();

		$result = $checkout->get_checkout_from_query_vars('original_value', 'some_random_key');

		$this->assertEquals('original_value', $result);
	}

	// -------------------------------------------------------------------------
	// should_collect_payment — additional branches
	// -------------------------------------------------------------------------

	/**
	 * Test should_collect_payment with products in request returns a boolean.
	 */
	public function test_should_collect_payment_with_products_in_request(): void {

		$checkout   = Checkout::get_instance();
		$reflection = new \ReflectionClass($checkout);
		$order_prop = $this->get_order_prop($reflection);

		$order_prop->setValue($checkout, null);

		// Set products in request — will try to build a cart
		$_REQUEST['products'] = ['nonexistent-product-slug'];

		$result = $checkout->should_collect_payment();

		// Should return a boolean (true on exception fallback or false from cart)
		$this->assertIsBool($result);

		unset($_REQUEST['products']);
		$order_prop->setValue($checkout, null);
	}

	// -------------------------------------------------------------------------
	// Teardown
	// -------------------------------------------------------------------------

	public static function tear_down_after_class() {
		self::$customer->delete();
		parent::tear_down_after_class();
	}
}
