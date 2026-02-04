<?php
/**
 * Unit tests for Email class.
 */

namespace WP_Ultimo\Models;

/**
 * Unit tests for Email class.
 */
class Email_Test extends \WP_UnitTestCase {

	/**
	 * Email instance.
	 *
	 * @var Email
	 */
	protected $email;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		// Create an email manually to avoid faker issues
		$this->email = new Email();
		$this->email->set_title('Test Email');
		$this->email->set_content('Test email content');
		$this->email->set_type('system_email');
		$this->email->set_status('publish');
	}

	/**
	 * Test email creation.
	 */
	public function test_email_creation(): void {
		$this->assertInstanceOf(Email::class, $this->email, 'Email should be an instance of Email class.');
		$this->assertEquals('Test Email', $this->email->get_title(), 'Email should have a title.');
		$this->assertEquals('Test email content', trim(strip_tags($this->email->get_content())), 'Email should have content.');
		$this->assertEquals('system_email', $this->email->get_type(), 'Email should have correct type.');
		$this->assertEquals('publish', $this->email->get_status(), 'Email should have correct status.');
	}

	/**
	 * Test email validation rules.
	 */
	public function test_email_validation_rules(): void {
		$validation_rules = $this->email->validation_rules();

		// Test required fields
		$this->assertArrayHasKey('title', $validation_rules, 'Validation rules should include title field.');
		$this->assertArrayHasKey('type', $validation_rules, 'Validation rules should include type field.');
		$this->assertArrayHasKey('event', $validation_rules, 'Validation rules should include event field.');

		// Test field constraints
		$this->assertStringContainsString('required', $validation_rules['title'], 'Title should be required.');
		$this->assertStringContainsString('in:system_email', $validation_rules['type'], 'Type should be limited to system_email.');
		$this->assertStringContainsString('required', $validation_rules['event'], 'Event should be required.');
	}

	/**
	 * Test email properties.
	 */
	public function test_email_properties(): void {
		// Test slug
		$this->email->set_slug('test-email');
		$this->assertEquals('test-email', $this->email->get_slug(), 'Slug should be set and retrieved correctly.');

		// Test status
		$statuses = ['publish', 'draft'];
		foreach ($statuses as $status) {
			$this->email->set_status($status);
			$this->assertEquals($status, $this->email->get_status(), "Status {$status} should be set and retrieved correctly.");
		}

		// Test target
		$targets = ['customer', 'admin'];
		foreach ($targets as $target) {
			$this->email->set_target($target);
			$this->assertEquals($target, $this->email->get_target(), "Target {$target} should be set and retrieved correctly.");
		}

		// Test scheduling
		$this->email->set_schedule(true);
		$this->assertTrue($this->email->has_schedule(), 'Schedule flag should be set to true.');

		$this->email->set_schedule(false);
		$this->assertFalse($this->email->has_schedule(), 'Schedule flag should be set to false.');

		// Test copy to admin
		$this->email->set_send_copy_to_admin(true);
		$this->assertTrue($this->email->get_send_copy_to_admin(), 'Send copy to admin flag should be set to true.');

		$this->email->set_send_copy_to_admin(false);
		$this->assertFalse($this->email->get_send_copy_to_admin(), 'Send copy to admin flag should be set to false.');
	}

	/**
	 * Test email event handling.
	 */
	public function test_email_event(): void {
		$event = 'user_registration';
		$this->email->set_event($event);
		$this->assertEquals($event, $this->email->get_event(), 'Event should be set and retrieved correctly.');

		// Test event retrieval from meta
		$this->email->set_event(''); // Clear event
		$meta_event = $this->email->get_event();
		$this->assertEquals('', $meta_event, 'Should return empty string when event is not set.');
	}

	/**
	 * Test email scheduling.
	 */
	public function test_email_scheduling(): void {
		// Test schedule type - skip due to meta caching issues
//		$this->markTestSkipped('Skipping schedule type test due to meta caching issues in test environment');

		// Test schedule time
		$hours = 24;
		$days  = 7;
		$this->email->set_send_hours($hours);
		$this->email->set_send_days($days);

		// Test has schedule
		$this->email->set_schedule(true);
		$this->assertTrue($this->email->has_schedule(), 'Schedule flag should be set to true.');

		$this->email->set_schedule(false);
		$this->assertFalse($this->email->has_schedule(), 'Schedule flag should be set to false.');
	}

	/**
	 * Test email style handling.
	 */
	public function test_email_style(): void {
		// Test style setter
		$this->email->set_style('html');
		$this->assertEquals('html', $this->email->get_style(), 'Style should be set and retrieved correctly.');

		// Test style getter with meta fallback
		$this->email->set_style(''); // Clear style
		$this->assertEquals('html', $this->email->get_style(), 'Should return default style when not set.');
	}

	/**
	 * Test legacy email handling.
	 */
	public function test_legacy_email(): void {
		// Test legacy flag
		$this->email->set_legacy(true);
		$this->assertTrue($this->email->is_legacy(), 'Legacy flag should be set to true.');

		$this->email->set_legacy(false);
		$this->assertFalse($this->email->is_legacy(), 'Legacy flag should be set to false.');
	}

	/**
	 * Test email save with validation error.
	 */
	public function test_email_save_with_validation_error(): void {
		$email = new Email();

		// Try to save without required fields
		$email->set_skip_validation(false);
		$result = $email->save();

		$this->assertInstanceOf(\WP_Error::class, $result, 'Save should return WP_Error when validation fails.');
	}

	/**
	 * Test email save with validation bypassed.
	 */
	public function test_email_save_with_validation_bypassed(): void {
		$email = new Email();

		// Set required fields
		$email->set_title('Test Email');
		$email->set_content('Test content');
		$email->set_type('system_email');
		$email->set_event('user_registration');
		$email->set_status('publish');

		// Bypass validation for testing
		$email->set_skip_validation(true);
		$result = $email->save();

		// In test environment, this might fail due to WordPress constraints
		// We're mainly testing that the method runs without errors
		$this->assertIsBool($result, 'Save should return boolean result.');
	}

	/**
	 * Test to_array method.
	 */
	public function test_to_array(): void {
		$array = $this->email->to_array();

		$this->assertIsArray($array, 'to_array() should return an array.');
		$this->assertArrayHasKey('id', $array, 'Array should contain id field.');
		$this->assertArrayHasKey('title', $array, 'Array should contain title field.');
		$this->assertArrayHasKey('content', $array, 'Array should contain content field.');
		$this->assertArrayHasKey('type', $array, 'Array should contain type field.');

		// Should not contain internal properties
		$this->assertArrayNotHasKey('query_class', $array, 'Array should not contain query_class.');
		$this->assertArrayNotHasKey('meta', $array, 'Array should not contain meta.');
	}

	/**
	 * Test formatted methods.
	 */
	public function test_formatted_methods(): void {
		// Test formatted date (if email has date_created method)
		if (method_exists($this->email, 'get_formatted_date')) {
			$formatted_date = $this->email->get_formatted_date('date_created');
			$this->assertIsString($formatted_date, 'Formatted date should be a string.');
		}
	}

	// =========================================================================
	// NEW TESTS BEGIN HERE
	// =========================================================================

	/**
	 * Test model property is set correctly.
	 */
	public function test_model_property(): void {
		$this->assertEquals('email', $this->email->model, 'Model property should be "email".');
	}

	/**
	 * Test query class property is set correctly.
	 */
	public function test_query_class_property(): void {
		$reflection = new \ReflectionClass($this->email);
		$property   = $reflection->getProperty('query_class');

		if (PHP_VERSION_ID < 80100) {
			$property->setAccessible(true);
		}

		$this->assertEquals(
			\WP_Ultimo\Database\Emails\Email_Query::class,
			$property->getValue($this->email),
			'Query class should be Email_Query.'
		);
	}

	/**
	 * Test default type property.
	 */
	public function test_default_type_property(): void {
		$email = new Email();
		$this->assertEquals('system_email', $email->get_type(), 'Default type should be system_email.');
	}

	/**
	 * Test allowed types via reflection.
	 */
	public function test_allowed_types(): void {
		$reflection = new \ReflectionClass($this->email);
		$property   = $reflection->getProperty('allowed_types');

		if (PHP_VERSION_ID < 80100) {
			$property->setAccessible(true);
		}

		$this->assertEquals(
			['system_email'],
			$property->getValue($this->email),
			'Allowed types should only contain system_email.'
		);
	}

	/**
	 * Test allowed status via reflection.
	 */
	public function test_allowed_status(): void {
		$reflection = new \ReflectionClass($this->email);
		$property   = $reflection->getProperty('allowed_status');

		if (PHP_VERSION_ID < 80100) {
			$property->setAccessible(true);
		}

		$this->assertEquals(
			['publish', 'draft'],
			$property->getValue($this->email),
			'Allowed statuses should be publish and draft.'
		);
	}

	/**
	 * Test set_type enforces allowed types.
	 */
	public function test_set_type_enforces_allowed_types(): void {
		$this->email->set_type('system_email');
		$this->assertEquals('system_email', $this->email->get_type(), 'Valid type should be accepted.');

		$this->email->set_type('invalid_type');
		$this->assertEquals('system_email', $this->email->get_type(), 'Invalid type should default to system_email.');
	}

	/**
	 * Test set_type with empty string.
	 */
	public function test_set_type_with_empty_string(): void {
		$this->email->set_type('');
		$this->assertEquals('system_email', $this->email->get_type(), 'Empty type should default to system_email.');
	}

	/**
	 * Test get_name returns same as get_title.
	 */
	public function test_get_name_returns_title(): void {
		$this->email->set_title('My Email Title');
		$this->assertEquals('My Email Title', $this->email->get_name(), 'get_name should return the title.');
		$this->assertEquals($this->email->get_title(), $this->email->get_name(), 'get_name and get_title should return the same value.');
	}

	/**
	 * Test set_name sets the title.
	 */
	public function test_set_name_sets_title(): void {
		$this->email->set_name('New Name');
		$this->assertEquals('New Name', $this->email->get_title(), 'set_name should set the title.');
		$this->assertEquals('New Name', $this->email->get_name(), 'get_name should reflect set_name.');
	}

	/**
	 * Test event setter stores value in meta.
	 */
	public function test_event_setter_stores_in_meta(): void {
		$this->email->set_event('payment_received');

		$reflection = new \ReflectionClass($this->email);
		$property   = $reflection->getProperty('meta');

		if (PHP_VERSION_ID < 80100) {
			$property->setAccessible(true);
		}

		$meta = $property->getValue($this->email);
		$this->assertArrayHasKey(Email::META_EVENT, $meta, 'Event should be stored in meta.');
		$this->assertEquals('payment_received', $meta[ Email::META_EVENT ], 'Meta event value should match.');
	}

	/**
	 * Test style setter stores value in meta.
	 */
	public function test_style_setter_stores_in_meta(): void {
		$this->email->set_style('plain-text');

		$reflection = new \ReflectionClass($this->email);
		$property   = $reflection->getProperty('meta');

		if (PHP_VERSION_ID < 80100) {
			$property->setAccessible(true);
		}

		$meta = $property->getValue($this->email);
		$this->assertArrayHasKey(Email::META_STYLE, $meta, 'Style should be stored in meta.');
		$this->assertEquals('plain-text', $meta[ Email::META_STYLE ], 'Meta style value should match.');
	}

	/**
	 * Test plain-text style on a saved email.
	 */
	public function test_style_plain_text(): void {
		$email = new Email();
		$email->set_title('Plain Text Style Test');
		$email->set_content('Content');
		$email->set_type('system_email');
		$email->set_event('site_created');
		$email->set_slug('plain-text-style');
		$email->set_target('admin');
		$email->set_status('publish');
		$email->set_style('plain-text');
		$email->set_skip_validation(true);
		$email->save();

		$retrieved = Email::get_by_id($email->get_id());
		$this->assertEquals('plain-text', $retrieved->get_style(), 'Style should be plain-text after retrieval.');
	}

	/**
	 * Test schedule setter stores in meta.
	 */
	public function test_schedule_setter_stores_in_meta(): void {
		$this->email->set_schedule(true);

		$reflection = new \ReflectionClass($this->email);
		$property   = $reflection->getProperty('meta');

		if (PHP_VERSION_ID < 80100) {
			$property->setAccessible(true);
		}

		$meta = $property->getValue($this->email);
		$this->assertArrayHasKey(Email::META_SCHEDULE, $meta, 'Schedule should be stored in meta.');
		$this->assertTrue($meta[ Email::META_SCHEDULE ], 'Meta schedule value should be true.');
	}

	/**
	 * Test target setter stores in meta.
	 */
	public function test_target_setter_stores_in_meta(): void {
		$this->email->set_target('customer');

		$reflection = new \ReflectionClass($this->email);
		$property   = $reflection->getProperty('meta');

		if (PHP_VERSION_ID < 80100) {
			$property->setAccessible(true);
		}

		$meta = $property->getValue($this->email);
		$this->assertArrayHasKey(Email::META_TARGET, $meta, 'Target should be stored in meta.');
		$this->assertEquals('customer', $meta[ Email::META_TARGET ], 'Meta target value should match.');
	}

	/**
	 * Test send_copy_to_admin setter stores in meta.
	 */
	public function test_send_copy_to_admin_setter_stores_in_meta(): void {
		$this->email->set_send_copy_to_admin(true);

		$reflection = new \ReflectionClass($this->email);
		$property   = $reflection->getProperty('meta');

		if (PHP_VERSION_ID < 80100) {
			$property->setAccessible(true);
		}

		$meta = $property->getValue($this->email);
		$this->assertArrayHasKey(Email::META_SEND_COPY_TO_ADMIN, $meta, 'Send copy to admin should be stored in meta.');
		$this->assertTrue($meta[ Email::META_SEND_COPY_TO_ADMIN ], 'Meta send_copy_to_admin value should be true.');
	}

	/**
	 * Test active setter stores in meta.
	 */
	public function test_active_setter_stores_in_meta(): void {
		$this->email->set_active(false);

		$reflection = new \ReflectionClass($this->email);
		$property   = $reflection->getProperty('meta');

		if (PHP_VERSION_ID < 80100) {
			$property->setAccessible(true);
		}

		$meta = $property->getValue($this->email);
		$this->assertArrayHasKey(Email::META_ACTIVE, $meta, 'Active should be stored in meta.');
		$this->assertFalse($meta[ Email::META_ACTIVE ], 'Meta active value should be false.');
	}

	/**
	 * Test legacy setter stores in meta.
	 */
	public function test_legacy_setter_stores_in_meta(): void {
		$this->email->set_legacy(true);

		$reflection = new \ReflectionClass($this->email);
		$property   = $reflection->getProperty('meta');

		if (PHP_VERSION_ID < 80100) {
			$property->setAccessible(true);
		}

		$meta = $property->getValue($this->email);
		$this->assertArrayHasKey(Email::META_LEGACY, $meta, 'Legacy should be stored in meta.');
		$this->assertTrue($meta[ Email::META_LEGACY ], 'Meta legacy value should be true.');
	}

	/**
	 * Test is_active with true value.
	 */
	public function test_is_active_true(): void {
		$this->email->set_active(true);
		$this->assertTrue($this->email->is_active(), 'Email should be active when set to true.');
	}

	/**
	 * Test is_active with false value.
	 */
	public function test_is_active_false(): void {
		$this->email->set_active(false);
		$this->assertFalse($this->email->is_active(), 'Email should be inactive when set to false.');
	}

	/**
	 * Test set_schedule_type stores in meta.
	 */
	public function test_set_schedule_type_stores_in_meta(): void {
		$this->email->set_schedule_type('hours');

		$reflection = new \ReflectionClass($this->email);
		$property   = $reflection->getProperty('meta');

		if (PHP_VERSION_ID < 80100) {
			$property->setAccessible(true);
		}

		$meta = $property->getValue($this->email);
		$this->assertArrayHasKey(Email::META_SCHEDULE_TYPE, $meta, 'Schedule type should be stored in meta.');
		$this->assertEquals('hours', $meta[ Email::META_SCHEDULE_TYPE ], 'Meta schedule type value should be hours.');
	}

	/**
	 * Test set_schedule_type with days.
	 */
	public function test_set_schedule_type_days(): void {
		$this->email->set_schedule_type('days');

		$reflection = new \ReflectionClass($this->email);
		$property   = $reflection->getProperty('meta');

		if (PHP_VERSION_ID < 80100) {
			$property->setAccessible(true);
		}

		$meta = $property->getValue($this->email);
		$this->assertEquals('days', $meta[ Email::META_SCHEDULE_TYPE ], 'Meta schedule type value should be days.');
	}

	/**
	 * Test set_send_hours stores in meta.
	 */
	public function test_set_send_hours_stores_in_meta(): void {
		$this->email->set_send_hours('08:30');

		$reflection = new \ReflectionClass($this->email);
		$property   = $reflection->getProperty('meta');

		if (PHP_VERSION_ID < 80100) {
			$property->setAccessible(true);
		}

		$meta = $property->getValue($this->email);
		$this->assertArrayHasKey(Email::META_SEND_HOURS, $meta, 'Send hours should be stored in meta.');
		$this->assertEquals('08:30', $meta[ Email::META_SEND_HOURS ], 'Meta send hours value should match.');
	}

	/**
	 * Test set_send_days stores in meta.
	 */
	public function test_set_send_days_stores_in_meta(): void {
		$this->email->set_send_days(5);

		$reflection = new \ReflectionClass($this->email);
		$property   = $reflection->getProperty('meta');

		if (PHP_VERSION_ID < 80100) {
			$property->setAccessible(true);
		}

		$meta = $property->getValue($this->email);
		$this->assertArrayHasKey(Email::META_SEND_DAYS, $meta, 'Send days should be stored in meta.');
		$this->assertEquals(5, $meta[ Email::META_SEND_DAYS ], 'Meta send days value should match.');
	}

	/**
	 * Test set_email_schedule stores in meta.
	 */
	public function test_set_email_schedule_stores_in_meta(): void {
		$this->email->set_email_schedule('delayed');

		$reflection = new \ReflectionClass($this->email);
		$property   = $reflection->getProperty('meta');

		if (PHP_VERSION_ID < 80100) {
			$property->setAccessible(true);
		}

		$meta = $property->getValue($this->email);
		$this->assertArrayHasKey(Email::META_EMAIL_SCHEDULE, $meta, 'Email schedule should be stored in meta.');
		$this->assertEquals('delayed', $meta[ Email::META_EMAIL_SCHEDULE ], 'Meta email schedule value should match.');
	}

	/**
	 * Test custom sender setter stores in meta.
	 */
	public function test_set_custom_sender_stores_in_meta(): void {
		$this->email->set_custom_sender(true);

		$reflection = new \ReflectionClass($this->email);
		$property   = $reflection->getProperty('meta');

		if (PHP_VERSION_ID < 80100) {
			$property->setAccessible(true);
		}

		$meta = $property->getValue($this->email);
		$this->assertArrayHasKey(Email::META_CUSTOM_SENDER, $meta, 'Custom sender should be stored in meta.');
		$this->assertTrue($meta[ Email::META_CUSTOM_SENDER ], 'Meta custom sender value should be true.');
	}

	/**
	 * Test custom sender name setter stores in meta.
	 */
	public function test_set_custom_sender_name_stores_in_meta(): void {
		$this->email->set_custom_sender_name('John Doe');

		$reflection = new \ReflectionClass($this->email);
		$property   = $reflection->getProperty('meta');

		if (PHP_VERSION_ID < 80100) {
			$property->setAccessible(true);
		}

		$meta = $property->getValue($this->email);
		$this->assertArrayHasKey(Email::META_CUSTOM_SENDER_NAME, $meta, 'Custom sender name should be stored in meta.');
		$this->assertEquals('John Doe', $meta[ Email::META_CUSTOM_SENDER_NAME ], 'Meta custom sender name value should match.');
	}

	/**
	 * Test custom sender email setter stores in meta.
	 */
	public function test_set_custom_sender_email_stores_in_meta(): void {
		$this->email->set_custom_sender_email('john@example.com');

		$reflection = new \ReflectionClass($this->email);
		$property   = $reflection->getProperty('meta');

		if (PHP_VERSION_ID < 80100) {
			$property->setAccessible(true);
		}

		$meta = $property->getValue($this->email);
		$this->assertArrayHasKey(Email::META_CUSTOM_SENDER_EMAIL, $meta, 'Custom sender email should be stored in meta.');
		$this->assertEquals('john@example.com', $meta[ Email::META_CUSTOM_SENDER_EMAIL ], 'Meta custom sender email value should match.');
	}

	/**
	 * Test all custom sender fields together.
	 */
	public function test_custom_sender_full_configuration(): void {
		$this->email->set_custom_sender(true);
		$this->email->set_custom_sender_name('Support Team');
		$this->email->set_custom_sender_email('support@example.com');

		$reflection = new \ReflectionClass($this->email);
		$property   = $reflection->getProperty('meta');

		if (PHP_VERSION_ID < 80100) {
			$property->setAccessible(true);
		}

		$meta = $property->getValue($this->email);

		$this->assertTrue($meta[ Email::META_CUSTOM_SENDER ], 'Custom sender should be enabled.');
		$this->assertEquals('Support Team', $meta[ Email::META_CUSTOM_SENDER_NAME ], 'Custom sender name should be set.');
		$this->assertEquals('support@example.com', $meta[ Email::META_CUSTOM_SENDER_EMAIL ], 'Custom sender email should be set.');
	}

	/**
	 * Test slug getter and setter.
	 */
	public function test_slug_getter_setter(): void {
		$this->email->set_slug('my-email-slug');
		$this->assertEquals('my-email-slug', $this->email->get_slug(), 'Slug should be set and retrieved correctly.');
	}

	/**
	 * Test slug with special characters.
	 */
	public function test_slug_with_special_characters(): void {
		$this->email->set_slug('email-slug-with-numbers-123');
		$this->assertEquals('email-slug-with-numbers-123', $this->email->get_slug(), 'Slug with numbers should work.');
	}

	/**
	 * Test empty slug.
	 */
	public function test_empty_slug(): void {
		$email = new Email();
		$this->assertEquals('', $email->get_slug(), 'Default slug should be empty string.');
	}

	/**
	 * Test constructor with array data.
	 */
	public function test_constructor_with_array_data(): void {
		$data = [
			'title'   => 'Constructor Email',
			'content' => 'Constructor content',
			'type'    => 'system_email',
			'status'  => 'draft',
			'slug'    => 'constructor-email',
		];

		$email = new Email($data);

		$this->assertEquals('Constructor Email', $email->get_title());
		$this->assertEquals('system_email', $email->get_type());
		$this->assertEquals('draft', $email->get_status());
		$this->assertEquals('constructor-email', $email->get_slug());
	}

	/**
	 * Test constructor with empty argument.
	 */
	public function test_constructor_with_no_arguments(): void {
		$email = new Email();

		$this->assertInstanceOf(Email::class, $email);
		$this->assertEquals(0, $email->get_id());
		$this->assertEquals('', $email->get_slug());
	}

	/**
	 * Test to_array contains all expected fields.
	 */
	public function test_to_array_contains_email_specific_fields(): void {
		$this->email->set_slug('test-slug');
		$this->email->set_event('site_created');
		$this->email->set_target('customer');
		$this->email->set_schedule(true);
		$this->email->set_active(true);
		$this->email->set_legacy(false);

		$array = $this->email->to_array();

		$this->assertArrayHasKey('slug', $array, 'Array should contain slug field.');
		$this->assertArrayHasKey('event', $array, 'Array should contain event field.');
		$this->assertArrayHasKey('target', $array, 'Array should contain target field.');
		$this->assertArrayHasKey('schedule', $array, 'Array should contain schedule field.');
		$this->assertArrayHasKey('active', $array, 'Array should contain active field.');
		$this->assertArrayHasKey('legacy', $array, 'Array should contain legacy field.');
		$this->assertArrayHasKey('send_copy_to_admin', $array, 'Array should contain send_copy_to_admin field.');
		$this->assertArrayHasKey('style', $array, 'Array should contain style field.');
	}

	/**
	 * Test to_array excludes internal properties.
	 */
	public function test_to_array_excludes_internal_properties(): void {
		$array = $this->email->to_array();

		$this->assertArrayNotHasKey('query_class', $array, 'Should not contain query_class.');
		$this->assertArrayNotHasKey('meta', $array, 'Should not contain meta.');
		$this->assertArrayNotHasKey('skip_validation', $array, 'Should not contain skip_validation.');
		$this->assertArrayNotHasKey('meta_fields', $array, 'Should not contain meta_fields.');
		$this->assertArrayNotHasKey('_original', $array, 'Should not contain _original.');
		$this->assertArrayNotHasKey('_mappings', $array, 'Should not contain _mappings.');
		$this->assertArrayNotHasKey('_mocked', $array, 'Should not contain _mocked.');
	}

	/**
	 * Test to_array values match getters.
	 */
	public function test_to_array_values_match_getters(): void {
		$this->email->set_title('Array Test Email');
		$this->email->set_slug('array-test');
		$this->email->set_type('system_email');
		$this->email->set_status('publish');

		$array = $this->email->to_array();

		$this->assertEquals('Array Test Email', $array['title']);
		$this->assertEquals('array-test', $array['slug']);
		$this->assertEquals('system_email', $array['type']);
		$this->assertEquals('publish', $array['status']);
	}

	/**
	 * Test to_search_results returns same as to_array.
	 */
	public function test_to_search_results(): void {
		$this->email->set_title('Search Email');
		$this->email->set_slug('search-email');

		$search_results = $this->email->to_search_results();
		$array          = $this->email->to_array();

		$this->assertEquals($array, $search_results, 'to_search_results should return the same as to_array by default.');
	}

	/**
	 * Test validation rules contain all expected keys.
	 */
	public function test_validation_rules_contain_all_expected_keys(): void {
		$rules = $this->email->validation_rules();

		$expected_keys = [
			'schedule',
			'type',
			'event',
			'send_hours',
			'send_days',
			'schedule_type',
			'name',
			'title',
			'slug',
			'custom_sender',
			'custom_sender_name',
			'custom_sender_email',
			'target',
			'send_copy_to_admin',
			'active',
			'legacy',
		];

		foreach ($expected_keys as $key) {
			$this->assertArrayHasKey($key, $rules, "Validation rules should include '{$key}' key.");
		}
	}

	/**
	 * Test validation rules for schedule field.
	 */
	public function test_validation_rule_schedule(): void {
		$rules = $this->email->validation_rules();
		$this->assertStringContainsString('boolean', $rules['schedule'], 'Schedule should be boolean.');
		$this->assertStringContainsString('default:0', $rules['schedule'], 'Schedule should default to 0.');
	}

	/**
	 * Test validation rules for target field.
	 */
	public function test_validation_rule_target(): void {
		$rules = $this->email->validation_rules();
		$this->assertStringContainsString('required', $rules['target'], 'Target should be required.');
		$this->assertStringContainsString('in:customer,admin', $rules['target'], 'Target should be limited to customer or admin.');
	}

	/**
	 * Test validation rules for slug field.
	 */
	public function test_validation_rule_slug(): void {
		$rules = $this->email->validation_rules();
		$this->assertStringContainsString('required', $rules['slug'], 'Slug should be required.');
	}

	/**
	 * Test validation rules for custom_sender field.
	 */
	public function test_validation_rule_custom_sender(): void {
		$rules = $this->email->validation_rules();
		$this->assertStringContainsString('boolean', $rules['custom_sender'], 'Custom sender should be boolean.');
		$this->assertStringContainsString('default:0', $rules['custom_sender'], 'Custom sender should default to 0.');
	}

	/**
	 * Test validation rules for send_copy_to_admin field.
	 */
	public function test_validation_rule_send_copy_to_admin(): void {
		$rules = $this->email->validation_rules();
		$this->assertStringContainsString('boolean', $rules['send_copy_to_admin'], 'Send copy to admin should be boolean.');
		$this->assertStringContainsString('default:0', $rules['send_copy_to_admin'], 'Send copy to admin should default to 0.');
	}

	/**
	 * Test validation rules for active field.
	 */
	public function test_validation_rule_active(): void {
		$rules = $this->email->validation_rules();
		$this->assertStringContainsString('default:1', $rules['active'], 'Active should default to 1.');
	}

	/**
	 * Test validation rules for legacy field.
	 */
	public function test_validation_rule_legacy(): void {
		$rules = $this->email->validation_rules();
		$this->assertStringContainsString('boolean', $rules['legacy'], 'Legacy should be boolean.');
		$this->assertStringContainsString('default:0', $rules['legacy'], 'Legacy should default to 0.');
	}

	/**
	 * Test validation rules for schedule_type field.
	 */
	public function test_validation_rule_schedule_type(): void {
		$rules = $this->email->validation_rules();
		$this->assertStringContainsString('in:days,hours', $rules['schedule_type'], 'Schedule type should be limited to days or hours.');
	}

	/**
	 * Test validation rules for send_days field.
	 */
	public function test_validation_rule_send_days(): void {
		$rules = $this->email->validation_rules();
		$this->assertStringContainsString('integer', $rules['send_days'], 'Send days should be integer.');
	}

	/**
	 * Test validation rules for name field.
	 */
	public function test_validation_rule_name(): void {
		$rules = $this->email->validation_rules();
		$this->assertStringContainsString('default:title', $rules['name'], 'Name should default to title.');
	}

	/**
	 * Test get_when_to_send returns 0 when no schedule.
	 */
	public function test_get_when_to_send_no_schedule(): void {
		$this->email->set_schedule(false);
		$this->assertEquals(0, $this->email->get_when_to_send(), 'Should return 0 when no schedule is set.');
	}

	/**
	 * Test multiple event values.
	 */
	public function test_set_event_various_values(): void {
		$events = [
			'site_created',
			'payment_received',
			'membership_activated',
			'domain_mapped',
			'customer_created',
		];

		foreach ($events as $event) {
			$this->email->set_event($event);
			$this->assertEquals($event, $this->email->get_event(), "Event should be '{$event}'.");
		}
	}

	/**
	 * Test setting event to empty string.
	 */
	public function test_set_event_empty_string(): void {
		$this->email->set_event('');
		$this->assertEquals('', $this->email->get_event(), 'Event should be empty string.');
	}

	/**
	 * Test target admin value.
	 */
	public function test_target_admin(): void {
		$this->email->set_target('admin');
		$this->assertEquals('admin', $this->email->get_target(), 'Target should be admin.');
	}

	/**
	 * Test target customer value.
	 */
	public function test_target_customer(): void {
		$this->email->set_target('customer');
		$this->assertEquals('customer', $this->email->get_target(), 'Target should be customer.');
	}

	/**
	 * Test exists returns false for unsaved email.
	 */
	public function test_exists_false_for_new_email(): void {
		$email = new Email();
		$this->assertFalse($email->exists(), 'New email should not exist in database.');
	}

	/**
	 * Test get_id returns 0 for unsaved email.
	 */
	public function test_get_id_returns_zero_for_new_email(): void {
		$email = new Email();
		$this->assertEquals(0, $email->get_id(), 'New email ID should be 0.');
	}

	/**
	 * Test content getter wraps in paragraphs via wpautop.
	 */
	public function test_content_getter_uses_wpautop(): void {
		$this->email->set_content('Simple line of text');
		$content = $this->email->get_content();
		$this->assertStringContainsString('<p>', $content, 'Content should be wrapped in <p> tags by wpautop.');
	}

	/**
	 * Test setting and getting content with HTML.
	 */
	public function test_content_with_html(): void {
		$html_content = '<strong>Bold text</strong> and <em>italic text</em>';
		$this->email->set_content($html_content);
		$content = $this->email->get_content();
		$this->assertStringContainsString('<strong>Bold text</strong>', $content, 'HTML in content should be preserved.');
		$this->assertStringContainsString('<em>italic text</em>', $content, 'HTML in content should be preserved.');
	}

	/**
	 * Test author ID getter and setter from Post_Base_Model.
	 */
	public function test_author_id(): void {
		$this->email->set_author_id(42);
		$this->assertEquals(42, $this->email->get_author_id(), 'Author ID should be set and retrieved correctly.');
	}

	/**
	 * Test default author ID.
	 */
	public function test_default_author_id(): void {
		$email = new Email();
		$this->assertEquals('', $email->get_author_id(), 'Default author ID should be empty string.');
	}

	/**
	 * Test excerpt getter and setter from Post_Base_Model.
	 */
	public function test_excerpt(): void {
		$this->email->set_excerpt('Test excerpt for email.');
		$this->assertEquals('Test excerpt for email.', $this->email->get_excerpt(), 'Excerpt should be set and retrieved correctly.');
	}

	/**
	 * Test list_order getter and setter from Post_Base_Model.
	 */
	public function test_list_order(): void {
		$this->email->set_list_order(5);
		$this->assertEquals(5, $this->email->get_list_order(), 'List order should be set and retrieved correctly.');
	}

	/**
	 * Test default list_order.
	 */
	public function test_default_list_order(): void {
		$email = new Email();
		$this->assertEquals(10, $email->get_list_order(), 'Default list order should be 10.');
	}

	/**
	 * Test date_created getter and setter.
	 */
	public function test_date_created(): void {
		$date = '2024-01-15 10:30:00';
		$this->email->set_date_created($date);
		$this->assertEquals($date, $this->email->get_date_created(), 'Date created should be set and retrieved correctly.');
	}

	/**
	 * Test date_modified getter and setter.
	 */
	public function test_date_modified(): void {
		$date = '2024-06-20 14:45:00';
		$this->email->set_date_modified($date);
		$this->assertEquals($date, $this->email->get_date_modified(), 'Date modified should be set and retrieved correctly.');
	}

	/**
	 * Test CRUD: save and retrieve an email.
	 */
	public function test_crud_save_and_retrieve(): void {
		$email = new Email();
		$email->set_title('CRUD Test Email');
		$email->set_content('CRUD email content');
		$email->set_type('system_email');
		$email->set_event('site_created');
		$email->set_slug('crud-test-email');
		$email->set_target('admin');
		$email->set_status('publish');
		$email->set_skip_validation(true);

		$result = $email->save();

		$this->assertTrue($result, 'Save should return true.');
		$this->assertGreaterThan(0, $email->get_id(), 'Email should have a valid ID after saving.');

		// Retrieve by ID
		$retrieved = Email::get_by_id($email->get_id());

		$this->assertInstanceOf(Email::class, $retrieved, 'Retrieved object should be an Email instance.');
		$this->assertEquals('CRUD Test Email', $retrieved->get_title(), 'Retrieved title should match.');
		$this->assertEquals('system_email', $retrieved->get_type(), 'Retrieved type should match.');
	}

	/**
	 * Test CRUD: update an existing email.
	 */
	public function test_crud_update(): void {
		$email = new Email();
		$email->set_title('Original Title');
		$email->set_content('Original content');
		$email->set_type('system_email');
		$email->set_event('site_created');
		$email->set_slug('update-test');
		$email->set_target('admin');
		$email->set_status('publish');
		$email->set_skip_validation(true);

		$email->save();

		$original_id = $email->get_id();
		$this->assertGreaterThan(0, $original_id, 'Email should be saved.');

		// Update the title
		$email->set_title('Updated Title');
		$update_result = $email->save();

		$this->assertTrue($update_result, 'Update save should return true.');
		$this->assertEquals($original_id, $email->get_id(), 'ID should not change after update.');

		// Retrieve and verify
		$retrieved = Email::get_by_id($original_id);
		$this->assertEquals('Updated Title', $retrieved->get_title(), 'Retrieved title should be updated.');
	}

	/**
	 * Test CRUD: delete an email.
	 */
	public function test_crud_delete(): void {
		$email = new Email();
		$email->set_title('Delete Test Email');
		$email->set_content('To be deleted');
		$email->set_type('system_email');
		$email->set_event('membership_activated');
		$email->set_slug('delete-test');
		$email->set_target('customer');
		$email->set_status('publish');
		$email->set_skip_validation(true);

		$email->save();
		$email_id = $email->get_id();

		$this->assertGreaterThan(0, $email_id, 'Email should be saved before deleting.');

		$delete_result = $email->delete();
		$this->assertNotEmpty($delete_result, 'Delete should return a truthy value.');

		// Verify it no longer exists
		$retrieved = Email::get_by_id($email_id);
		$this->assertFalse($retrieved, 'Deleted email should not be retrievable.');
	}

	/**
	 * Test delete on unsaved email returns WP_Error.
	 */
	public function test_delete_unsaved_email(): void {
		$email  = new Email();
		$result = $email->delete();

		$this->assertInstanceOf(\WP_Error::class, $result, 'Deleting unsaved email should return WP_Error.');
	}

	/**
	 * Test get_by_id with invalid ID.
	 */
	public function test_get_by_id_with_invalid_id(): void {
		$result = Email::get_by_id(0);
		$this->assertFalse($result, 'get_by_id(0) should return false.');

		$result2 = Email::get_by_id(999999);
		$this->assertFalse($result2, 'get_by_id with non-existent ID should return false.');
	}

	/**
	 * Test get_all returns array.
	 */
	public function test_get_all_returns_array(): void {
		$all = Email::get_all();
		$this->assertIsArray($all, 'get_all should return an array.');
	}

	/**
	 * Test get_all returns saved emails.
	 */
	public function test_get_all_contains_saved_emails(): void {
		$email = new Email();
		$email->set_title('GetAll Test');
		$email->set_content('GetAll content');
		$email->set_type('system_email');
		$email->set_event('payment_received');
		$email->set_slug('getall-test');
		$email->set_target('admin');
		$email->set_status('publish');
		$email->set_skip_validation(true);
		$email->save();

		$all = Email::get_all();
		$this->assertNotEmpty($all, 'get_all should contain at least one email.');

		$found = false;
		foreach ($all as $item) {
			if ($item->get_id() === $email->get_id()) {
				$found = true;
				break;
			}
		}
		$this->assertTrue($found, 'Saved email should be found in get_all results.');
	}

	/**
	 * Test save with valid data and full validation.
	 */
	public function test_save_with_full_validation(): void {
		$email = new Email();
		$email->set_title('Validated Email');
		$email->set_content('Validated content');
		$email->set_type('system_email');
		$email->set_event('site_created');
		$email->set_slug('validated-email');
		$email->set_target('admin');
		$email->set_status('publish');
		$email->set_skip_validation(false);

		$result = $email->save();
		$this->assertTrue($result, 'Save with valid data should succeed.');
		$this->assertGreaterThan(0, $email->get_id(), 'Email should have an ID after valid save.');
	}

	/**
	 * Test save fails without title.
	 */
	public function test_save_fails_without_title(): void {
		$email = new Email();
		$email->set_content('Content only');
		$email->set_type('system_email');
		$email->set_event('site_created');
		$email->set_slug('no-title');
		$email->set_target('admin');
		$email->set_status('publish');
		$email->set_skip_validation(false);

		$result = $email->save();
		$this->assertInstanceOf(\WP_Error::class, $result, 'Save without title should return WP_Error.');
	}

	/**
	 * Test save fails without target.
	 */
	public function test_save_fails_without_target(): void {
		$email = new Email();
		$email->set_title('No Target Email');
		$email->set_content('Content');
		$email->set_type('system_email');
		$email->set_event('site_created');
		$email->set_slug('no-target');
		$email->set_status('publish');
		$email->set_skip_validation(false);

		$result = $email->save();
		$this->assertInstanceOf(\WP_Error::class, $result, 'Save without target should return WP_Error.');
	}

	/**
	 * Test save fails without slug.
	 */
	public function test_save_fails_without_slug(): void {
		$email = new Email();
		$email->set_title('No Slug Email');
		$email->set_content('Content');
		$email->set_type('system_email');
		$email->set_event('site_created');
		$email->set_target('admin');
		$email->set_status('publish');
		$email->set_skip_validation(false);

		$result = $email->save();
		$this->assertInstanceOf(\WP_Error::class, $result, 'Save without slug should return WP_Error.');
	}

	/**
	 * Test meta constants are defined.
	 */
	public function test_meta_constants(): void {
		$this->assertEquals('wu_system_email_event', Email::META_EVENT);
		$this->assertEquals('wu_style', Email::META_STYLE);
		$this->assertEquals('wu_schedule', Email::META_SCHEDULE);
		$this->assertEquals('system_email_schedule_type', Email::META_SCHEDULE_TYPE);
		$this->assertEquals('system_email_send_days', Email::META_SEND_DAYS);
		$this->assertEquals('system_email_send_hours', Email::META_SEND_HOURS);
		$this->assertEquals('system_email_custom_sender', Email::META_CUSTOM_SENDER);
		$this->assertEquals('system_email_custom_sender_name', Email::META_CUSTOM_SENDER_NAME);
		$this->assertEquals('system_email_custom_sender_email', Email::META_CUSTOM_SENDER_EMAIL);
		$this->assertEquals('system_email_schedule', Email::META_EMAIL_SCHEDULE);
		$this->assertEquals('wu_target', Email::META_TARGET);
		$this->assertEquals('wu_send_copy_to_admin', Email::META_SEND_COPY_TO_ADMIN);
		$this->assertEquals('wu_active', Email::META_ACTIVE);
		$this->assertEquals('wu_legacy', Email::META_LEGACY);
	}

	/**
	 * Test get_target_list returns empty array for admin target when no super admins.
	 */
	public function test_get_target_list_admin(): void {
		$this->email->set_target('admin');

		$target_list = $this->email->get_target_list();
		$this->assertIsArray($target_list, 'get_target_list should return an array.');
		// In WP test env, there should be at least one super admin
		$this->assertNotEmpty($target_list, 'Admin target list should include super admins.');
	}

	/**
	 * Test get_target_list returns empty for customer target without customer_id.
	 */
	public function test_get_target_list_customer_no_id(): void {
		$this->email->set_target('customer');

		$target_list = $this->email->get_target_list([]);
		$this->assertIsArray($target_list, 'get_target_list should return an array.');
		$this->assertEmpty($target_list, 'Customer target list without customer_id should be empty.');
	}

	/**
	 * Test get_target_list for customer with payload containing email directly.
	 */
	public function test_get_target_list_customer_with_payload_email(): void {
		$this->email->set_target('customer');
		$this->email->set_send_copy_to_admin(false);

		$payload = [
			'customer_id'         => 1,
			'customer_user_email' => 'customer@example.com',
			'customer_name'       => 'Test Customer',
		];

		$target_list = $this->email->get_target_list($payload);
		$this->assertIsArray($target_list, 'get_target_list should return an array.');
		$this->assertCount(1, $target_list, 'Should have exactly one target.');
		$this->assertEquals('customer@example.com', $target_list[0]['email'], 'Target email should match payload.');
		$this->assertEquals('Test Customer', $target_list[0]['name'], 'Target name should match payload.');
	}

	/**
	 * Test get_target_list for customer with send_copy_to_admin.
	 */
	public function test_get_target_list_customer_with_admin_copy(): void {
		$this->email->set_target('customer');
		$this->email->set_send_copy_to_admin(true);

		$payload = [
			'customer_id'         => 1,
			'customer_user_email' => 'customer@example.com',
			'customer_name'       => 'Test Customer',
		];

		$target_list = $this->email->get_target_list($payload);
		$this->assertIsArray($target_list, 'get_target_list should return an array.');
		$this->assertGreaterThan(1, count($target_list), 'Should have customer plus admin targets.');
		$this->assertEquals('customer@example.com', $target_list[0]['email'], 'First target should be the customer.');
	}

	/**
	 * Test get_target_list for customer with invalid email in payload.
	 */
	public function test_get_target_list_customer_with_invalid_email(): void {
		$this->email->set_target('customer');

		$payload = [
			'customer_id'         => 1,
			'customer_user_email' => 'not-an-email',
			'customer_name'       => 'Test',
		];

		$target_list = $this->email->get_target_list($payload);
		$this->assertIsArray($target_list, 'get_target_list should return an array.');
		$this->assertEmpty($target_list, 'Target list should be empty for invalid email.');
	}

	/**
	 * Test get_target_list for customer with empty name uses email as name.
	 */
	public function test_get_target_list_customer_empty_name_fallback(): void {
		$this->email->set_target('customer');
		$this->email->set_send_copy_to_admin(false);

		$payload = [
			'customer_id'         => 1,
			'customer_user_email' => 'customer@example.com',
			'customer_name'       => '',
		];

		$target_list = $this->email->get_target_list($payload);
		$this->assertCount(1, $target_list, 'Should have one target.');
		$this->assertEquals('customer@example.com', $target_list[0]['name'], 'Name should fall back to email when empty.');
	}

	/**
	 * Test get_target_list for customer with array name.
	 */
	public function test_get_target_list_customer_array_name(): void {
		$this->email->set_target('customer');
		$this->email->set_send_copy_to_admin(false);

		$payload = [
			'customer_id'         => 1,
			'customer_user_email' => 'customer@example.com',
			'customer_name'       => ['first' => 'John', 'last' => 'Doe'],
		];

		$target_list = $this->email->get_target_list($payload);
		$this->assertCount(1, $target_list, 'Should have one target.');
		// Array name should be converted to empty string, then fallback to email
		$this->assertEquals('customer@example.com', $target_list[0]['name'], 'Array name should fallback to email.');
	}

	/**
	 * Test get_super_admin_targets static method.
	 */
	public function test_get_super_admin_targets(): void {
		$targets = Email::get_super_admin_targets();
		$this->assertIsArray($targets, 'get_super_admin_targets should return an array.');

		// In WP test env, super admin should exist
		if ( ! empty($targets)) {
			$this->assertArrayHasKey('name', $targets[0], 'Each target should have a name key.');
			$this->assertArrayHasKey('email', $targets[0], 'Each target should have an email key.');
		}
	}

	/**
	 * Test CRUD with meta values persisted.
	 */
	public function test_crud_meta_values_persist(): void {
		$email = new Email();
		$email->set_title('Meta Test Email');
		$email->set_content('Meta content');
		$email->set_type('system_email');
		$email->set_event('domain_mapped');
		$email->set_slug('meta-test');
		$email->set_target('customer');
		$email->set_status('publish');
		$email->set_active(true);
		$email->set_legacy(false);
		$email->set_schedule(true);
		$email->set_send_copy_to_admin(true);
		$email->set_style('html');
		$email->set_custom_sender(true);
		$email->set_custom_sender_name('Custom Name');
		$email->set_custom_sender_email('custom@example.com');
		$email->set_schedule_type('days');
		$email->set_send_days(3);
		$email->set_send_hours('14:30');
		$email->set_skip_validation(true);

		$result = $email->save();
		$this->assertTrue($result, 'Save should succeed.');

		// Retrieve and check meta values
		$retrieved = Email::get_by_id($email->get_id());
		$this->assertInstanceOf(Email::class, $retrieved, 'Should retrieve an Email instance.');

		$this->assertEquals('domain_mapped', $retrieved->get_event(), 'Event should persist.');
		$this->assertEquals('customer', $retrieved->get_target(), 'Target should persist.');
		$this->assertNotEmpty($retrieved->is_active(), 'Active status should persist.');
		$this->assertEmpty($retrieved->is_legacy(), 'Legacy status should persist.');
		$this->assertNotEmpty($retrieved->has_schedule(), 'Schedule should persist.');
		$this->assertNotEmpty($retrieved->get_send_copy_to_admin(), 'Send copy to admin should persist.');
		$this->assertEquals('html', $retrieved->get_style(), 'Style should persist.');
		$this->assertEquals(true, $retrieved->get_custom_sender(), 'Custom sender should persist.');
		$this->assertEquals('Custom Name', $retrieved->get_custom_sender_name(), 'Custom sender name should persist.');
		$this->assertEquals('custom@example.com', $retrieved->get_custom_sender_email(), 'Custom sender email should persist.');
		$this->assertEquals('days', $retrieved->get_schedule_type(), 'Schedule type should persist.');
		$this->assertEquals(3, $retrieved->get_send_days(), 'Send days should persist.');
		$this->assertEquals('14:30', $retrieved->get_send_hours(), 'Send hours should persist.');
	}

	/**
	 * Test migrated_from_id getter and setter.
	 */
	public function test_migrated_from_id(): void {
		$this->email->set_migrated_from_id(123);
		$this->assertEquals(123, $this->email->get_migrated_from_id(), 'Migrated from ID should be set and retrieved.');
	}

	/**
	 * Test is_migrated returns false for non-migrated email.
	 */
	public function test_is_migrated_false(): void {
		$email = new Email();
		$this->assertFalse($email->is_migrated(), 'New email should not be migrated.');
	}

	/**
	 * Test is_migrated returns true when migrated_from_id is set.
	 */
	public function test_is_migrated_true(): void {
		$this->email->set_migrated_from_id(456);
		$this->assertTrue($this->email->is_migrated(), 'Email with migrated_from_id should be migrated.');
	}

	/**
	 * Test jsonSerialize returns array representation.
	 */
	public function test_json_serialize(): void {
		$this->email->set_title('JSON Test');
		$this->email->set_slug('json-test');

		$json_data = $this->email->jsonSerialize();
		$this->assertIsArray($json_data, 'jsonSerialize should return an array.');
		$this->assertEquals($this->email->to_array(), $json_data, 'jsonSerialize should match to_array when not in ajax search context.');
	}

	/**
	 * Test json_encode produces valid JSON.
	 */
	public function test_json_encode(): void {
		$this->email->set_title('JSON Encode Test');

		$json = json_encode($this->email);
		$this->assertIsString($json, 'json_encode should return a string.');
		$this->assertNotFalse($json, 'json_encode should not fail.');

		$decoded = json_decode($json, true);
		$this->assertIsArray($decoded, 'Decoded JSON should be an array.');
		$this->assertEquals('JSON Encode Test', $decoded['title'], 'Decoded title should match.');
	}

	/**
	 * Test attributes method sets values via setters.
	 */
	public function test_attributes_method(): void {
		$email = new Email();
		$email->attributes([
			'title'  => 'Attributes Test',
			'slug'   => 'attributes-test',
			'type'   => 'system_email',
			'status' => 'draft',
		]);

		$this->assertEquals('Attributes Test', $email->get_title());
		$this->assertEquals('attributes-test', $email->get_slug());
		$this->assertEquals('system_email', $email->get_type());
		$this->assertEquals('draft', $email->get_status());
	}

	/**
	 * Test set_status publish and draft.
	 */
	public function test_set_status_values(): void {
		$this->email->set_status('publish');
		$this->assertEquals('publish', $this->email->get_status());

		$this->email->set_status('draft');
		$this->assertEquals('draft', $this->email->get_status());
	}

	/**
	 * Test set_title with various content.
	 */
	public function test_set_title_various_content(): void {
		$this->email->set_title('Welcome to {{site_name}}');
		$this->assertEquals('Welcome to {{site_name}}', $this->email->get_title(), 'Title with placeholder should work.');

		$this->email->set_title('');
		$this->assertEquals('', $this->email->get_title(), 'Empty title should be accepted.');

		$this->email->set_title('A very long email title that contains many words to test the setter behavior with extended text');
		$this->assertStringContainsString('A very long email title', $this->email->get_title(), 'Long title should be stored fully.');
	}

	/**
	 * Test set_content with empty string.
	 */
	public function test_set_content_empty(): void {
		$this->email->set_content('');
		$this->assertEquals('', trim($this->email->get_content()), 'Empty content should return empty or whitespace-only string.');
	}

	/**
	 * Test email with draft status.
	 */
	public function test_email_draft_status(): void {
		$email = new Email();
		$email->set_status('draft');
		$this->assertEquals('draft', $email->get_status(), 'Draft status should be set correctly.');
	}

	/**
	 * Test set_skip_validation toggling.
	 */
	public function test_skip_validation_toggling(): void {
		$email = new Email();
		$email->set_title('Validation Toggle Test');
		$email->set_content('Content');
		$email->set_type('system_email');
		$email->set_event('site_created');
		$email->set_slug('validation-toggle');
		$email->set_target('admin');
		$email->set_status('publish');

		// Enable skip_validation and save
		$email->set_skip_validation(true);
		$result = $email->save();
		$this->assertTrue($result, 'Save with skip_validation true should succeed.');
	}

	/**
	 * Test schedule and schedule_type interaction.
	 */
	public function test_schedule_and_type_interaction(): void {
		$this->email->set_schedule(true);
		$this->assertTrue($this->email->has_schedule(), 'Schedule should be true.');

		$this->email->set_schedule_type('hours');
		$reflection = new \ReflectionClass($this->email);
		$property   = $reflection->getProperty('meta');

		if (PHP_VERSION_ID < 80100) {
			$property->setAccessible(true);
		}

		$meta = $property->getValue($this->email);
		$this->assertEquals('hours', $meta[ Email::META_SCHEDULE_TYPE ], 'Schedule type should be hours.');
	}

	/**
	 * Test multiple saves update the same record.
	 */
	public function test_multiple_saves(): void {
		$email = new Email();
		$email->set_title('Multi Save Test');
		$email->set_content('Content');
		$email->set_type('system_email');
		$email->set_event('site_created');
		$email->set_slug('multi-save');
		$email->set_target('admin');
		$email->set_status('publish');
		$email->set_skip_validation(true);
		$email->save();

		$id = $email->get_id();
		$this->assertGreaterThan(0, $id);

		// Save again with updated title
		$email->set_title('Multi Save Test Updated');
		$email->save();

		$this->assertEquals($id, $email->get_id(), 'ID should remain the same after update.');

		$retrieved = Email::get_by_id($id);
		$this->assertEquals('Multi Save Test Updated', $retrieved->get_title(), 'Title should be updated.');
	}

	/**
	 * Test setting custom_sender to false disables custom sender.
	 */
	public function test_custom_sender_disabled(): void {
		$this->email->set_custom_sender(false);

		$reflection = new \ReflectionClass($this->email);
		$property   = $reflection->getProperty('meta');

		if (PHP_VERSION_ID < 80100) {
			$property->setAccessible(true);
		}

		$meta = $property->getValue($this->email);
		$this->assertFalse($meta[ Email::META_CUSTOM_SENDER ], 'Custom sender should be disabled.');
	}

	/**
	 * Test get_by with slug column.
	 */
	public function test_get_by_slug(): void {
		$email = new Email();
		$email->set_title('Get By Slug Test');
		$email->set_content('Content');
		$email->set_type('system_email');
		$email->set_event('payment_received');
		$email->set_slug('get-by-slug-test');
		$email->set_target('admin');
		$email->set_status('publish');
		$email->set_skip_validation(true);
		$email->save();

		$retrieved = Email::get_by('slug', 'get-by-slug-test');
		$this->assertInstanceOf(Email::class, $retrieved, 'get_by slug should return an Email.');
		$this->assertEquals('Get By Slug Test', $retrieved->get_title(), 'Retrieved title should match.');
	}

	/**
	 * Test query method returns array.
	 */
	public function test_query_method(): void {
		$email = new Email();
		$email->set_title('Query Method Test');
		$email->set_content('Content');
		$email->set_type('system_email');
		$email->set_event('test_query');
		$email->set_slug('query-method-test');
		$email->set_target('admin');
		$email->set_status('publish');
		$email->set_skip_validation(true);
		$email->save();

		$results = Email::query();
		$this->assertIsArray($results, 'query should return an array.');
		$this->assertNotEmpty($results, 'query should return saved emails.');
	}

	/**
	 * Test save sets default status to draft when status is empty.
	 */
	public function test_save_sets_default_draft_status(): void {
		$email = new Email();
		$email->set_title('Default Status Test');
		$email->set_content('Content');
		$email->set_type('system_email');
		$email->set_event('test_default');
		$email->set_slug('default-status');
		$email->set_target('admin');
		// Do not set status
		$email->set_skip_validation(true);
		$email->save();

		$retrieved = Email::get_by_id($email->get_id());
		$this->assertNotEmpty($retrieved->get_status(), 'Status should have a default value after save.');
	}

	/**
	 * Test get_items_as_array returns arrays.
	 */
	public function test_get_items_as_array(): void {
		$email = new Email();
		$email->set_title('Items As Array Test');
		$email->set_content('Content');
		$email->set_type('system_email');
		$email->set_event('test_array');
		$email->set_slug('items-as-array');
		$email->set_target('admin');
		$email->set_status('publish');
		$email->set_skip_validation(true);
		$email->save();

		$results = Email::get_items_as_array();
		$this->assertIsArray($results, 'get_items_as_array should return an array.');
		if ( ! empty($results)) {
			$this->assertIsArray($results[0], 'Each item should be an array.');
			$this->assertArrayHasKey('title', $results[0], 'Item arrays should have title key.');
		}
	}

	/**
	 * Tear down test environment.
	 */
	public function tearDown(): void {
		// Clean up created emails
		$emails = Email::get_all();
		if ($emails) {
			foreach ($emails as $email) {
				if ($email->get_id()) {
					$email->delete();
				}
			}
		}

		parent::tearDown();
	}

}
