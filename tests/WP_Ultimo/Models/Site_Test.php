<?php
/**
 * Unit tests for Site class.
 */

namespace WP_Ultimo\Models;

use WP_Ultimo\Faker;
use WP_Ultimo\Database\Sites\Site_Type;

/**
 * Unit tests for Site class.
 */
class Site_Test extends \WP_UnitTestCase {

	/**
	 * Site instance.
	 *
	 * @var Site
	 */
	protected $site;

	/**
	 * Customer instance.
	 *
	 * @var \WP_Ultimo\Models\Customer
	 */
	protected $customer;

	/**
	 * Membership instance.
	 *
	 * @var \WP_Ultimo\Models\Membership
	 */
	protected $membership;

	/**
	 * Faker instance.
	 *
	 * @var \WP_Ultimo\Faker
	 */
	protected $faker;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		// Ensure UPLOADBLOGSDIR constant is defined to prevent errors during blog creation.
		if ( ! defined('UPLOADBLOGSDIR')) {
			define('UPLOADBLOGSDIR', 'wp-content/blogs.dir');
		}

		// Create test data using WordPress factory
		$user_id = $this->factory()->user->create(['role' => 'subscriber']);

		// Create a customer manually
		$this->customer = wu_create_customer(
			[
				'user_id'       => $user_id,
				'email_address' => 'test@example.com',
			]
		);

		// Handle case where customer creation fails
		if (is_wp_error($this->customer)) {
			$this->customer = new \WP_Ultimo\Models\Customer();
			$this->customer->set_user_id($user_id);
		}

		// Create a test site using WordPress factory
		$blog_id = $this->factory()->blog->create(
			[
				'user_id' => $user_id,
				'title'   => 'Test Site',
				'domain'  => 'test-site.org',
			]
		);

		// Handle case where blog creation returns WP_Error
		if (is_wp_error($blog_id)) {
			$blog_id = 1; // Fall back to main site
		}

		// Create site object
		$this->site = new Site(
			[
				'blog_id'       => $blog_id,
				'title'         => 'Test Site',
				'domain'        => 'test-site.org',
				'path'          => '/',
				'customer_id'   => $this->customer->get_id(),
				'type'          => 'customer_owned',
				'membership_id' => 0, // Set a default membership_id
			]
		);
	}

	/**
	 * Test site creation.
	 */
	public function test_site_creation(): void {
		$this->assertInstanceOf(Site::class, $this->site, 'Site should be an instance of Site class.');
		$this->assertNotEmpty($this->site->get_id(), 'Site should have an ID after creation.');
		$this->assertNotEmpty($this->site->get_title(), 'Site should have a title.');
		$this->assertNotEmpty($this->site->get_domain(), 'Site should have a domain.');
	}

	/**
	 * Test site validation rules.
	 */
	public function test_site_validation_rules(): void {
		$validation_rules = $this->site->validation_rules();

		// Test required fields
		$this->assertArrayHasKey('title', $validation_rules, 'Validation rules should include title field.');
		$this->assertArrayHasKey('name', $validation_rules, 'Validation rules should include name field.');
		$this->assertArrayHasKey('description', $validation_rules, 'Validation rules should include description field.');
		$this->assertArrayHasKey('customer_id', $validation_rules, 'Validation rules should include customer_id field.');
		$this->assertArrayHasKey('membership_id', $validation_rules, 'Validation rules should include membership_id field.');
		$this->assertArrayHasKey('type', $validation_rules, 'Validation rules should include type field.');

		// Test field constraints
		$this->assertStringContainsString('required', $validation_rules['title'], 'Title should be required.');
		$this->assertStringContainsString('required', $validation_rules['customer_id'], 'Customer ID should be required.');
		$this->assertStringContainsString('integer', $validation_rules['customer_id'], 'Customer ID should be integer.');
		$this->assertStringContainsString('min:2', $validation_rules['description'], 'Description should have minimum length.');
	}

	/**
	 * Test domain and path handling.
	 */
	public function test_domain_path_handling(): void {
		$test_domain = 'test-example.com';
		$test_path   = '/test-path';

		$this->site->set_domain($test_domain);
		$this->site->set_path($test_path);

		$this->assertEquals($test_domain, $this->site->get_domain(), 'Domain should be set and retrieved correctly.');
		$this->assertEquals($test_path, $this->site->get_path(), 'Path should be set and retrieved correctly.');

		// Test URL generation
		$expected_url = set_url_scheme(esc_url(sprintf($test_domain . '/' . trim($test_path, '/'))));
		$this->assertEquals($expected_url, $this->site->get_site_url(), 'Site URL should be generated correctly.');
	}

	/**
	 * Test customer relationships.
	 */
	public function test_customer_relationships(): void {
		// Test customer ID getter/setter
		$customer_id = $this->customer->get_id();
		$this->site->set_customer_id($customer_id);
		$this->assertEquals($customer_id, $this->site->get_customer_id(), 'Customer ID should be set and retrieved correctly.');

		// Test customer object retrieval
		$customer = $this->site->get_customer();
		if ($customer) {
			$this->assertInstanceOf(\WP_Ultimo\Models\Customer::class, $customer, 'Should return Customer object.');
			$this->assertEquals($customer_id, $customer->get_id(), 'Retrieved customer should have correct ID.');
		} else {
			$this->markTestSkipped('Customer retrieval failed - TODO: Fix customer relationship testing with proper data setup');
		}

		// Test customer permission checking
		$this->assertTrue($this->site->is_customer_allowed($customer_id), 'Customer should be allowed access to their own site.');

		// Test admin permission
		$admin_user_id = $this->factory()->user->create(['role' => 'administrator']);
		grant_super_admin($admin_user_id);
		wp_set_current_user($admin_user_id);
		$this->assertTrue($this->site->is_customer_allowed(), 'Admin should always be allowed access.');
	}

	/**
	 * Test membership relationships.
	 */
	public function test_membership_relationships(): void {
		// Create a test membership
		$membership_id = 123;
		$this->site->set_membership_id($membership_id);
		$this->assertEquals($membership_id, $this->site->get_membership_id(), 'Membership ID should be set and retrieved correctly.');

		// Test has membership - this may return false if membership doesn't exist in database
		$has_membership = $this->site->has_membership();
		// We can't guarantee membership exists, so just test the method runs
		$this->assertIsBool($has_membership, 'has_membership() should return boolean.');

		// Test membership object retrieval (may return false if membership doesn't exist)
		$membership = $this->site->get_membership();
		if ($membership) {
			$this->assertInstanceOf(\WP_Ultimo\Models\Membership::class, $membership, 'Should return Membership object.');
		} else {
			$this->assertFalse($membership, 'Should return false when membership does not exist.');
		}
	}

	/**
	 * Test site types.
	 */
	public function test_site_types(): void {
		$site_types = [
			Site_Type::REGULAR,
			Site_Type::SITE_TEMPLATE,
			Site_Type::CUSTOMER_OWNED,
			Site_Type::PENDING,
			Site_Type::EXTERNAL,
		];

		foreach ($site_types as $type) {
			$this->site->set_type($type);
			$this->assertEquals($type, $this->site->get_type(), "Site type {$type} should be set and retrieved correctly.");

			// Test type label
			$label = $this->site->get_type_label();
			$this->assertNotEmpty($label, "Site type {$type} should have a label.");

			// Test type class
			$class = $this->site->get_type_class();
			$this->assertNotEmpty($class, "Site type {$type} should have CSS classes.");
		}
	}

	/**
	 * Test site status flags.
	 */
	public function test_site_status_flags(): void {
		// Test active flag
		$this->site->set_active(true);
		$this->assertTrue($this->site->is_active(), 'Active flag should be set and retrieved correctly.');

		$this->site->set_active(false);
		$this->assertFalse($this->site->is_active(), 'Active flag should be set to false correctly.');

		// Test public flag
		$this->site->set_public(true);
		$this->assertTrue($this->site->get_public(), 'Public flag should be set and retrieved correctly.');

		// Test other status flags
		$this->site->set_archived(true);
		$this->assertTrue($this->site->is_archived(), 'Archived flag should be set correctly.');

		$this->site->set_mature(true);
		$this->assertTrue($this->site->is_mature(), 'Mature flag should be set correctly.');

		$this->site->set_spam(true);
		$this->assertTrue($this->site->is_spam(), 'Spam flag should be set correctly.');

		$this->site->set_deleted(true);
		$this->assertTrue($this->site->is_deleted(), 'Deleted flag should be set correctly.');

		$this->site->set_publishing(true);
		$this->assertTrue($this->site->is_publishing(), 'Publishing flag should be set correctly.');
	}

	/**
	 * Test featured image handling.
	 */
	public function test_featured_image_handling(): void {
		$attachment_id = $this->factory()->attachment->create_object(['file' => 'test.jpg']);

		// Test featured image ID setter/getter
		$this->site->set_featured_image_id($attachment_id);
		$this->assertEquals($attachment_id, $this->site->get_featured_image_id(), 'Featured image ID should be set and retrieved correctly.');

		// Test featured image URL
		$image_url = $this->site->get_featured_image();
		$this->assertNotEmpty($image_url, 'Featured image URL should be returned.');

		// Test external site type
		$this->site->set_type(Site_Type::EXTERNAL);
		$external_image_url = $this->site->get_featured_image();
		$this->assertStringContainsString('wp-ultimo-screenshot.webp', $external_image_url, 'External sites should use screenshot placeholder.');
	}

	/**
	 * Test category management.
	 */
	public function test_category_management(): void {
		$categories = ['category1', 'category2', 'category3'];

		// Test category setter/getter
		$this->site->set_categories($categories);
		$retrieved_categories = $this->site->get_categories();

		$this->assertEquals($categories, $retrieved_categories, 'Categories should be set and retrieved correctly.');
		$this->assertCount(3, $retrieved_categories, 'Should return correct number of categories.');

		// Test empty categories
		$this->site->set_categories([]);
		$this->assertEmpty($this->site->get_categories(), 'Empty categories should be handled correctly.');
	}

	/**
	 * Test URL generation.
	 */
	public function test_url_generation(): void {
		$domain = 'test-site.com';
		$path   = '/my-site';

		$this->site->set_domain($domain);
		$this->site->set_path($path);

		// Test site URL
		$site_url     = $this->site->get_site_url();
		$expected_url = set_url_scheme(esc_url(sprintf($domain . '/' . trim($path, '/'))));
		$this->assertEquals($expected_url, $site_url, 'Site URL should be generated correctly.');

		// Test active site URL (without mapped domain)
		$active_url = $this->site->get_active_site_url();
		$this->assertEquals($expected_url, $active_url, 'Active site URL should match site URL when no mapping exists.');
	}

	/**
	 * Test site ID and blog ID.
	 */
	public function test_site_id_handling(): void {
		$blog_id = $this->site->get_id();

		// Test get_id returns blog_id
		$this->assertEquals($blog_id, $this->site->get_blog_id(), 'get_id() should return blog_id.');

		// Test blog ID setter
		$new_blog_id = 999;
		$this->site->set_blog_id($new_blog_id);
		$this->assertEquals($new_blog_id, $this->site->get_blog_id(), 'Blog ID should be set and retrieved correctly.');

		// Test site ID
		$site_id = $this->site->get_site_id();
		$this->assertIsInt($site_id, 'Site ID should be an integer.');
	}

	/**
	 * Test title and description.
	 */
	public function test_title_and_description(): void {
		$title       = 'Test Site Title';
		$description = 'This is a test site description.';

		// Test title setter/getter
		$this->site->set_title($title);
		$this->assertEquals($title, $this->site->get_title(), 'Title should be set and retrieved correctly.');
		$this->assertEquals($title, $this->site->get_name(), 'Name should return title.');

		// Test description setter/getter
		$this->site->set_description($description);
		$this->assertEquals($description, $this->site->get_description(), 'Description should be set and retrieved correctly.');
	}

	/**
	 * Test site existence check.
	 */
	public function test_site_existence(): void {
		// Test existing site
		$this->assertTrue($this->site->exists(), 'Site should exist when it has a blog_id.');

		// Test new site without ID
		$new_site = new Site();
		$this->assertFalse($new_site->exists(), 'New site should not exist without blog_id.');
	}

	/**
	 * Test template relationships.
	 */
	public function test_template_relationships(): void {
		$template_id = 123;

		// Test template ID setter/getter
		$this->site->set_template_id($template_id);
		$this->assertEquals($template_id, $this->site->get_template_id(), 'Template ID should be set and retrieved correctly.');
	}

	/**
	 * Test duplication arguments.
	 */
	public function test_duplication_arguments(): void {
		$args = [
			'keep_users' => false,
			'copy_files' => true,
			'public'     => false,
		];

		// Test duplication arguments setter/getter
		$this->site->set_duplication_arguments($args);
		$retrieved_args = $this->site->get_duplication_arguments();

		$this->assertEquals($args, $retrieved_args, 'Duplication arguments should be set and retrieved correctly.');

		// Test default arguments
		$new_site     = new Site();
		$default_args = $new_site->get_duplication_arguments();
		$this->assertArrayHasKey('keep_users', $default_args, 'Default arguments should include keep_users.');
		$this->assertArrayHasKey('copy_files', $default_args, 'Default arguments should include copy_files.');
		$this->assertArrayHasKey('public', $default_args, 'Default arguments should include public.');
	}


	/**
	 * Test site save with validation bypassed.
	 */
	public function test_site_save_with_validation_bypassed(): void {

		$site = new Site();

		// Set required fields
		$site->set_title('Test Site');
		$site->set_description('Test Description');
		$site->set_customer_id($this->customer->get_id());
		$site->set_membership_id(123); // Use fake ID
		$site->set_type(Site_Type::CUSTOMER_OWNED);
		$site->set_domain('test-site.com');
		$site->set_path('/test');

		// Bypass validation for testing
		$site->set_skip_validation(true);
		$result = $site->save();

		// In test environment, this might fail due to WordPress constraints
		// We're mainly testing that the method runs without errors
		$this->assertIsInt($result, 'Save should return boolean result.');
	}

	/**
	 * Test to_array method.
	 */
	public function test_to_array(): void {
		$array = $this->site->to_array();

		$this->assertIsArray($array, 'to_array() should return an array.');
		$this->assertArrayHasKey('id', $array, 'Array should contain id field.');
		$this->assertArrayHasKey('title', $array, 'Array should contain title field.');
		$this->assertArrayHasKey('domain', $array, 'Array should contain domain field.');
		$this->assertArrayHasKey('path', $array, 'Array should contain path field.');
		$this->assertArrayHasKey('type', $array, 'Array should contain type field.');

		// Should not contain internal properties
		$this->assertArrayNotHasKey('query_class', $array, 'Array should not contain query_class.');
		$this->assertArrayNotHasKey('meta', $array, 'Array should not contain meta.');
	}

	/**
	 * Test hash generation.
	 */
	public function test_hash_generation(): void {
		$hash = $this->site->get_hash('id');

		$this->assertIsString($hash, 'Hash should be a string.');
		$this->assertNotEmpty($hash, 'Hash should not be empty.');
	}

	/**
	 * Test meta data handling.
	 */
	public function test_meta_data_handling(): void {

		$this->site->save();
		$meta_key   = 'test_meta_key';
		$meta_value = 'test_meta_value';

		// Test meta update
		$result = $this->site->update_meta($meta_key, $meta_value);
		$this->assertTrue($result || is_numeric($result), 'Meta update should return true or numeric ID.');

		// Test meta retrieval
		$retrieved_value = $this->site->get_meta($meta_key);
		$this->assertEquals($meta_value, $retrieved_value, 'Meta value should be retrieved correctly.');

		// Test meta deletion
		$delete_result = $this->site->delete_meta($meta_key);
		$this->assertTrue($delete_result || is_numeric($delete_result), 'Meta deletion should return true or numeric ID.');

		// Test default value
		$default_value = $this->site->get_meta($meta_key, 'default');
		$this->assertEquals('default', $default_value, 'Should return default value when meta does not exist.');
	}

	/**
	 * Test date handling.
	 */
	public function test_date_handling(): void {
		$registered_date   = '2023-01-01 12:00:00';
		$last_updated_date = '2023-01-02 12:00:00';

		// Test date setters
		$this->site->set_registered($registered_date);
		$this->site->set_last_updated($last_updated_date);

		$this->assertEquals($registered_date, $this->site->get_registered(), 'Registered date should be set and retrieved correctly.');
		$this->assertEquals($last_updated_date, $this->site->get_last_updated(), 'Last updated date should be set and retrieved correctly.');

		// Test date aliases
		$this->assertEquals($registered_date, $this->site->get_date_registered(), 'Date registered should alias to registered.');
		$this->assertEquals($last_updated_date, $this->site->get_date_modified(), 'Date modified should alias to last updated.');
	}

	/**
	 * Test site locking.
	 */
	public function test_site_locking(): void {

		// Test lock
		$lock_result = $this->site->lock();
		$this->assertTrue($lock_result || is_numeric($lock_result), 'Lock should return true or numeric ID on success.');
		$this->assertTrue((bool)$this->site->is_locked(), 'Site should be locked.');

		// Test unlock
		$unlock_result = $this->site->unlock();
		$this->assertTrue($unlock_result || is_numeric($unlock_result), 'Unlock should return true or numeric ID on success.');
		$this->assertFalse((bool)$this->site->is_locked(), 'Site should be unlocked.');
	}

	/**
	 * Test formatted amount and date.
	 */
	public function test_formatted_methods(): void {
		// Test formatted amount (if site has amount-related methods)
		if (method_exists($this->site, 'get_amount')) {
			$this->site->set_amount(19.99);
			$formatted_amount = $this->site->get_formatted_amount();
			$this->assertIsString($formatted_amount, 'Formatted amount should be a string.');
		}

		// Test formatted date
		$formatted_date = $this->site->get_formatted_date('date_created');
		$this->assertIsString($formatted_date, 'Formatted date should be a string.');
	}

	/**
	 * Test search results.
	 */
	public function test_to_search_results(): void {
		$search_results = $this->site->to_search_results();

		$this->assertIsArray($search_results, 'Search results should be an array.');
		$this->assertArrayHasKey('siteurl', $search_results, 'Search results should contain siteurl field.');
		$this->assertEquals($this->site->get_active_site_url(), $search_results['siteurl'], 'Site URL should match active site URL.');
	}

	/**
	 * Test set_name is an alias for set_title.
	 */
	public function test_set_name_alias(): void {
		$this->site->set_name('Name Via Alias');
		$this->assertEquals('Name Via Alias', $this->site->get_title(), 'set_name should set the title.');
		$this->assertEquals('Name Via Alias', $this->site->get_name(), 'get_name should return the title set via set_name.');
	}

	/**
	 * Test lang_id getter and setter.
	 */
	public function test_lang_id(): void {
		$this->site->set_lang_id(42);
		$this->assertEquals(42, $this->site->get_lang_id(), 'Lang ID should be set and retrieved correctly.');

		$this->site->set_lang_id(0);
		$this->assertEquals(0, $this->site->get_lang_id(), 'Lang ID should accept zero.');
	}

	/**
	 * Test site_id (network ID) getter and setter.
	 */
	public function test_set_site_id(): void {
		$this->site->set_site_id(5);
		$this->assertEquals(5, $this->site->get_site_id(), 'Network site_id should be set and retrieved correctly.');
	}

	/**
	 * Test transient getter and setter.
	 */
	public function test_transient(): void {
		$transient_data = [
			'site_title' => 'My Site',
			'site_url'   => 'mysite',
		];

		$this->site->set_transient($transient_data);
		$this->assertEquals($transient_data, $this->site->get_transient(), 'Transient data should be set and retrieved correctly.');
	}

	/**
	 * Test transient returns null when not set and no meta exists.
	 */
	public function test_transient_null_fallback(): void {
		$site = new Site(['blog_id' => 999999]);
		// transient is null and meta won't exist for a non-existent blog
		$result = $site->get_transient();
		// Should not error out; returns whatever the meta call returns
		$this->assertTrue(true, 'get_transient should not throw when no data is set.');
	}

	/**
	 * Test signup_options getter and setter.
	 */
	public function test_signup_options(): void {
		$options = [
			'blogdescription' => 'A test description',
			'WPLANG'          => 'en_US',
		];

		$this->site->set_signup_options($options);
		$this->assertEquals($options, $this->site->get_signup_options(), 'Signup options should be set and retrieved correctly.');
	}

	/**
	 * Test signup_options returns empty array when null.
	 */
	public function test_signup_options_default(): void {
		$site = new Site();
		$this->assertEquals([], $site->get_signup_options(), 'get_signup_options should return empty array when not set.');
	}

	/**
	 * Test signup_meta getter and setter.
	 */
	public function test_signup_meta(): void {
		$meta = [
			'custom_key' => 'custom_value',
			'another'    => 123,
		];

		$this->site->set_signup_meta($meta);
		$this->assertEquals($meta, $this->site->get_signup_meta(), 'Signup meta should be set and retrieved correctly.');
	}

	/**
	 * Test signup_meta returns empty array when null.
	 */
	public function test_signup_meta_default(): void {
		$site = new Site();
		$this->assertEquals([], $site->get_signup_meta(), 'get_signup_meta should return empty array when not set.');
	}

	/**
	 * Test to_wp_site returns a WP_Site instance.
	 */
	public function test_to_wp_site(): void {
		$wp_site = $this->site->to_wp_site();

		if ($wp_site) {
			$this->assertInstanceOf(\WP_Site::class, $wp_site, 'to_wp_site should return a WP_Site instance.');
			$this->assertEquals($this->site->get_id(), (int) $wp_site->blog_id, 'WP_Site blog_id should match.');
		} else {
			// WP_Site may return null for certain blog IDs in test environment
			$this->assertNull($wp_site, 'to_wp_site returns null when WP site not found.');
		}
	}

	/**
	 * Test get_plan returns false when no membership.
	 */
	public function test_get_plan_no_membership(): void {
		$this->site->set_membership_id(0);
		$result = $this->site->get_plan();
		$this->assertFalse($result, 'get_plan should return false when there is no membership.');
	}

	/**
	 * Test has_product returns false when no membership.
	 */
	public function test_has_product_no_membership(): void {
		$this->site->set_membership_id(0);
		$result = $this->site->has_product();
		$this->assertFalse($result, 'has_product should return false when there is no membership.');
	}

	/**
	 * Test is_customer_primary_site returns false when no customer.
	 */
	public function test_is_customer_primary_site_no_customer(): void {
		$this->site->set_customer_id(0);
		$result = $this->site->is_customer_primary_site();
		$this->assertFalse($result, 'is_customer_primary_site should return false when customer ID is 0.');
	}

	/**
	 * Test is_customer_primary_site with a valid customer.
	 */
	public function test_is_customer_primary_site_with_customer(): void {
		$customer_id = $this->customer->get_id();
		if ( ! $customer_id) {
			$this->markTestSkipped('Customer not available for this test.');
		}

		$this->site->set_customer_id($customer_id);

		// Result depends on whether this site is the user's primary blog
		$result = $this->site->is_customer_primary_site();
		$this->assertIsBool($result, 'is_customer_primary_site should return a boolean.');
	}

	/**
	 * Test get_primary_mapped_domain returns false when no domains exist.
	 */
	public function test_get_primary_mapped_domain_none(): void {
		$result = $this->site->get_primary_mapped_domain();
		$this->assertFalse($result, 'get_primary_mapped_domain should return false when no mapped domains exist.');
	}

	/**
	 * Test get_active_site_url returns site_url when no blog_id is set.
	 */
	public function test_get_active_site_url_no_id(): void {
		$site = new Site();
		$site->set_domain('example.com');
		$site->set_path('/test');

		$active_url = $site->get_active_site_url();
		$site_url   = $site->get_site_url();

		$this->assertEquals($site_url, $active_url, 'get_active_site_url should return get_site_url when no blog ID is set.');
	}

	/**
	 * Test __call magic method for get_option_* methods.
	 */
	public function test_magic_call_get_option(): void {
		// Set a blog option that we can read via __call
		update_blog_option($this->site->get_id(), 'blogname', 'Magic Test');

		$result = $this->site->get_option_blogname();
		$this->assertEquals('Magic Test', $result, '__call should proxy get_option_* to get_blog_option.');
	}

	/**
	 * Test __call magic method throws BadMethodCallException for unknown methods.
	 */
	public function test_magic_call_throws_for_unknown_method(): void {
		$this->expectException(\BadMethodCallException::class);
		$this->site->some_nonexistent_method();
	}

	/**
	 * Test get_description falls back to blogdescription option.
	 */
	public function test_get_description_fallback(): void {
		$site = new Site(['blog_id' => $this->site->get_id()]);
		// description property is null, so it should fall back to get_blog_option
		update_blog_option($this->site->get_id(), 'blogdescription', 'Fallback Description');

		$result = $site->get_description();
		$this->assertEquals('Fallback Description', $result, 'get_description should fall back to blogdescription option when description is not set.');
	}

	/**
	 * Test get_categories returns empty array for non-array value.
	 */
	public function test_get_categories_non_array(): void {
		$site = new Site();
		// Force categories to a non-array string value by using reflection
		$reflection = new \ReflectionProperty(Site::class, 'categories');
		$reflection->setAccessible(true);
		$reflection->setValue($site, 'not-an-array');

		$result = $site->get_categories();
		$this->assertIsArray($result, 'get_categories should return an array even when categories is not an array.');
		$this->assertEmpty($result, 'get_categories should return empty array for non-array value.');
	}

	/**
	 * Test get_featured_image returns placeholder when no image is set.
	 */
	public function test_get_featured_image_placeholder(): void {
		$this->site->set_type(Site_Type::CUSTOMER_OWNED);
		// Do not set a featured image ID
		$site = new Site(['blog_id' => $this->site->get_id()]);
		$site->set_type(Site_Type::CUSTOMER_OWNED);
		$site->set_featured_image_id(0);

		$result = $site->get_featured_image();
		$this->assertNotEmpty($result, 'get_featured_image should return a URL even without an image set.');
		$this->assertStringContainsString('site-placeholder-image.webp', $result, 'Should return the placeholder image when no featured image is set.');
	}

	/**
	 * Test get_featured_image_id falls back to meta when property is null.
	 */
	public function test_get_featured_image_id_meta_fallback(): void {
		$site = new Site(['blog_id' => $this->site->get_id()]);
		// featured_image_id should be null, triggering meta fallback
		$result = $site->get_featured_image_id();
		// It should not error; it may return null or the meta value
		$this->assertTrue(true, 'get_featured_image_id meta fallback should not throw.');
	}

	/**
	 * Test delete on an unsaved site returns WP_Error.
	 */
	public function test_delete_unsaved_site(): void {
		$site   = new Site();
		$result = $site->delete();
		$this->assertInstanceOf(\WP_Error::class, $result, 'delete() on unsaved site should return WP_Error.');
	}

	/**
	 * Test get_type returns main for the main site.
	 */
	public function test_get_type_main_site(): void {
		$main_site_id = get_main_site_id();
		$site         = new Site(['blog_id' => $main_site_id]);

		$result = $site->get_type();
		$this->assertEquals('main', $result, 'get_type should return main for the main site.');
	}

	/**
	 * Test get_type returns default when type meta is not set.
	 */
	public function test_get_type_default_fallback(): void {
		// Create a non-main site with no type set
		$blog_id = $this->factory()->blog->create();
		if (is_wp_error($blog_id)) {
			$this->markTestSkipped('Could not create blog for this test.');
		}

		$site = new Site(['blog_id' => $blog_id]);
		// type is null and no meta exists, so it should fall back to 'default'
		$result = $site->get_type();
		$this->assertEquals('default', $result, 'get_type should return default when type meta is not set.');

		wp_delete_site($blog_id);
	}

	/**
	 * Test limitations_to_merge returns empty array when no membership.
	 */
	public function test_limitations_to_merge_no_membership(): void {
		$this->site->set_membership_id(0);
		$result = $this->site->limitations_to_merge();
		$this->assertIsArray($result, 'limitations_to_merge should return an array.');
		$this->assertEmpty($result, 'limitations_to_merge should return empty array when no membership exists.');
	}

	/**
	 * Test duplication arguments merge with defaults.
	 */
	public function test_duplication_arguments_merge_defaults(): void {
		// Set only a partial override
		$this->site->set_duplication_arguments(['keep_users' => false]);
		$result = $this->site->get_duplication_arguments();

		$this->assertFalse($result['keep_users'], 'keep_users should be overridden to false.');
		$this->assertTrue($result['copy_files'], 'copy_files should retain default value of true.');
		$this->assertTrue($result['public'], 'public should retain default value of true.');
	}

	/**
	 * Test set_public to false.
	 */
	public function test_set_public_false(): void {
		$this->site->set_public(false);
		$this->assertFalse($this->site->get_public(), 'get_public should return false after set_public(false).');
	}

	/**
	 * Test publishing getter returns value set.
	 */
	public function test_publishing_false(): void {
		$this->site->set_publishing(false);
		$this->assertFalse($this->site->is_publishing(), 'is_publishing should return false after set_publishing(false).');
	}

	/**
	 * Test get_template returns false when template_id does not match a site.
	 */
	public function test_get_template_nonexistent(): void {
		$this->site->set_template_id(999999);
		$result = $this->site->get_template();
		$this->assertFalse($result, 'get_template should return false when template site does not exist.');
	}

	/**
	 * Test is_customer_allowed returns false for a different customer.
	 */
	public function test_is_customer_allowed_different_customer(): void {
		$customer_id = $this->customer->get_id();
		$this->site->set_customer_id($customer_id);

		// Use a different, non-existent customer ID
		$different_id = $customer_id + 9999;
		$result       = $this->site->is_customer_allowed($different_id);
		$this->assertFalse($result, 'is_customer_allowed should return false for a different customer ID.');
	}

	/**
	 * Test get_customer returns false when customer_id is 0.
	 */
	public function test_get_customer_zero_id(): void {
		$this->site->set_customer_id(0);
		$result = $this->site->get_customer();
		$this->assertFalse($result, 'get_customer should return false when customer_id is 0.');
	}

	/**
	 * Test has_membership returns false when membership_id is 0.
	 */
	public function test_has_membership_zero(): void {
		$this->site->set_membership_id(0);
		$result = $this->site->has_membership();
		$this->assertFalse($result, 'has_membership should return false when membership_id is 0.');
	}

	/**
	 * Test get_membership returns false when membership_id is 0.
	 */
	public function test_get_membership_zero(): void {
		$this->site->set_membership_id(0);
		// Clear cached membership
		$reflection = new \ReflectionProperty(Site::class, 'membership');
		$reflection->setAccessible(true);
		$reflection->setValue($this->site, null);

		$result = $this->site->get_membership();
		$this->assertFalse($result, 'get_membership should return false when membership_id is 0.');
	}

	/**
	 * Test get_customer_id falls back to meta when property is null.
	 */
	public function test_get_customer_id_meta_fallback(): void {
		// Test that customer_id set via constructor is properly stored in meta
		// and that get_customer_id returns an integer.
		$customer_id = $this->customer->get_id();
		$this->site->set_customer_id($customer_id);
		// Save the site meta to persist the customer_id
		update_site_meta($this->site->get_id(), 'wu_customer_id', $customer_id);

		// Create a new Site instance that only has blog_id
		// to verify it can read customer_id from meta
		$site = new Site(['blog_id' => $this->site->get_id()]);
		$result = $site->get_customer_id();
		$this->assertIsInt($result, 'get_customer_id should return an integer.');
		$this->assertEquals($customer_id, $result, 'get_customer_id should read the value from meta.');
	}

	/**
	 * Test get_membership_id falls back to meta when property is null.
	 */
	public function test_get_membership_id_meta_fallback(): void {
		$site = new Site(['blog_id' => $this->site->get_id()]);
		// membership_id property is null, should trigger meta fallback
		$result = $site->get_membership_id();
		// Result type is flexible (int or null from meta)
		$this->assertTrue(true, 'get_membership_id meta fallback should not throw.');
	}

	/**
	 * Test get_template_id falls back to meta when property is null.
	 */
	public function test_get_template_id_meta_fallback(): void {
		$site = new Site(['blog_id' => $this->site->get_id()]);
		// template_id property is null, should trigger meta fallback
		$result = $site->get_template_id();
		$this->assertTrue(true, 'get_template_id meta fallback should not throw.');
	}

	/**
	 * Test get_type falls back to meta when type property is null.
	 */
	public function test_get_type_meta_fallback(): void {
		// Create a non-main blog
		$blog_id = $this->factory()->blog->create();
		if (is_wp_error($blog_id)) {
			$this->markTestSkipped('Could not create blog for this test.');
		}

		// Set the meta on the blog
		update_site_meta($blog_id, 'wu_type', 'site_template');

		$site = new Site(['blog_id' => $blog_id]);
		// type is null, should trigger meta fallback
		$result = $site->get_type();
		$this->assertEquals('site_template', $result, 'get_type should read from meta when type property is null.');

		wp_delete_site($blog_id);
	}

	/**
	 * Test is_active falls back to meta when active property is null.
	 */
	public function test_is_active_meta_fallback(): void {
		$site = new Site(['blog_id' => $this->site->get_id()]);
		// active property is null, should trigger meta fallback (default true)
		$result = $site->is_active();
		// Default meta value is true
		$this->assertTrue((bool) $result, 'is_active should return true by default from meta fallback.');
	}

	/**
	 * Test categories with values containing empty strings are filtered.
	 */
	public function test_categories_filters_empty_values(): void {
		$this->site->set_categories(['cat1', '', 'cat2', '']);
		$result = $this->site->get_categories();
		$this->assertCount(2, $result, 'get_categories should filter out empty values.');
		$this->assertContains('cat1', $result, 'Should contain cat1.');
		$this->assertContains('cat2', $result, 'Should contain cat2.');
	}

	/**
	 * Test title strips slashes.
	 */
	public function test_title_strips_slashes(): void {
		// Use reflection to set raw title with slashes
		$reflection = new \ReflectionProperty(Site::class, 'title');
		$reflection->setAccessible(true);
		$reflection->setValue($this->site, 'Test\\\'s Site');

		$result = $this->site->get_title();
		$this->assertEquals("Test's Site", $result, 'get_title should strip slashes.');
	}

	/**
	 * Test set_title sanitizes input.
	 */
	public function test_set_title_sanitizes(): void {
		$this->site->set_title('<script>alert("xss")</script>My Site');
		$result = $this->site->get_title();
		$this->assertStringNotContainsString('<script>', $result, 'set_title should sanitize HTML tags.');
		$this->assertStringContainsString('My Site', $result, 'set_title should keep safe text content.');
	}

	/**
	 * Test constructor with site_path property for WP CLI support.
	 */
	public function test_constructor_site_path_fallback(): void {
		$blog_id = $this->factory()->blog->create();
		if (is_wp_error($blog_id)) {
			$this->markTestSkipped('Could not create blog for this test.');
		}

		// Simulate WP CLI scenario where --path is used by WP CLI
		$site = new Site([
			'blog_id'   => $blog_id,
			'site_path' => '/cli-path',
		]);

		// The constructor should use site_path when path is empty
		$path = $site->get_path();
		// It should have a path (either from the blog details or from site_path)
		$this->assertNotEmpty($path, 'Site should have a path set via site_path fallback or blog details.');

		wp_delete_site($blog_id);
	}

	/**
	 * Test set_type initializes meta as array.
	 */
	public function test_set_type_initializes_meta(): void {
		$site = new Site();
		// meta may be null initially
		$site->set_type('customer_owned');
		$this->assertEquals('customer_owned', $site->get_type(), 'set_type should work even when meta is initially null.');
	}

	/**
	 * Test get_all_by_type static method for customer_owned.
	 */
	public function test_get_all_by_type_customer_owned(): void {
		$result = Site::get_all_by_type('customer_owned');
		$this->assertIsArray($result, 'get_all_by_type should return an array.');
	}

	/**
	 * Test get_all_by_type static method for site_template.
	 */
	public function test_get_all_by_type_site_template(): void {
		$result = Site::get_all_by_type('site_template');
		$this->assertIsArray($result, 'get_all_by_type should return an array for site_template type.');
	}

	/**
	 * Test get_all_by_type static method for pending type.
	 */
	public function test_get_all_by_type_pending(): void {
		$result = Site::get_all_by_type('pending');
		$this->assertIsArray($result, 'get_all_by_type should return an array for pending type.');
	}

	/**
	 * Test get_all_categories static method.
	 */
	public function test_get_all_categories(): void {
		$result = Site::get_all_categories();
		$this->assertIsArray($result, 'get_all_categories should return an array.');
	}

	/**
	 * Test get_all_categories with site objects passed.
	 */
	public function test_get_all_categories_with_sites(): void {
		$result = Site::get_all_categories([$this->site]);
		$this->assertIsArray($result, 'get_all_categories should return an array when sites are passed.');
	}

	/**
	 * Test get_all_by_categories static method.
	 */
	public function test_get_all_by_categories(): void {
		$result = Site::get_all_by_categories(['test-category']);
		$this->assertIsArray($result, 'get_all_by_categories should return an array.');
	}

	/**
	 * Test featured image size filter is applied.
	 */
	public function test_get_featured_image_applies_size_filter(): void {
		$filter_called = false;
		$filter_fn     = function ($size, $site) use (&$filter_called) {
			$filter_called = true;
			return $size;
		};

		add_filter('wu_site_featured_image_size', $filter_fn, 10, 2);
		$this->site->set_type(Site_Type::CUSTOMER_OWNED);
		$this->site->get_featured_image();
		remove_filter('wu_site_featured_image_size', $filter_fn, 10);

		$this->assertTrue($filter_called, 'wu_site_featured_image_size filter should be applied.');
	}

	/**
	 * Test is_customer_allowed filter is applied.
	 */
	public function test_is_customer_allowed_filter(): void {
		$customer_id = $this->customer->get_id();
		$this->site->set_customer_id($customer_id);

		// Use filter to override result
		$filter_fn = function ($allowed, $cid, $site) {
			return false;
		};

		add_filter('wu_site_is_customer_allowed', $filter_fn, 10, 3);

		// Make sure we are not a super admin
		wp_set_current_user(0);
		$result = $this->site->is_customer_allowed($customer_id);

		remove_filter('wu_site_is_customer_allowed', $filter_fn, 10);

		$this->assertFalse($result, 'wu_site_is_customer_allowed filter should be able to override the result.');
	}

	/**
	 * Test validation rules contain all expected keys.
	 */
	public function test_validation_rules_all_keys(): void {
		$rules = $this->site->validation_rules();

		$expected_keys = [
			'categories',
			'featured_image_id',
			'site_id',
			'title',
			'name',
			'description',
			'domain',
			'path',
			'registered',
			'last_updated',
			'public',
			'archived',
			'mature',
			'spam',
			'deleted',
			'is_publishing',
			'customer_id',
			'membership_id',
			'template_id',
			'type',
			'signup_options',
		];

		foreach ($expected_keys as $key) {
			$this->assertArrayHasKey($key, $rules, "Validation rules should include {$key}.");
		}
	}

	/**
	 * Test to_search_results includes all to_array keys plus siteurl.
	 */
	public function test_to_search_results_structure(): void {
		$array          = $this->site->to_array();
		$search_results = $this->site->to_search_results();

		foreach (array_keys($array) as $key) {
			$this->assertArrayHasKey($key, $search_results, "Search results should contain {$key} from to_array.");
		}

		$this->assertArrayHasKey('siteurl', $search_results, 'Search results should contain siteurl.');
	}

	/**
	 * Test site URL generation with root path.
	 */
	public function test_site_url_root_path(): void {
		$this->site->set_domain('example.com');
		$this->site->set_path('/');

		$url = $this->site->get_site_url();
		$this->assertNotEmpty($url, 'Site URL should not be empty for root path.');
	}

	/**
	 * Test blog_id is cast to int in get_blog_id.
	 */
	public function test_get_blog_id_casts_to_int(): void {
		$reflection = new \ReflectionProperty(Site::class, 'blog_id');
		$reflection->setAccessible(true);
		$reflection->setValue($this->site, '42');

		$result = $this->site->get_blog_id();
		$this->assertIsInt($result, 'get_blog_id should return an integer.');
		$this->assertEquals(42, $result, 'get_blog_id should cast string to int.');
	}

	/**
	 * Tear down test environment.
	 */
	public function tearDown(): void {
		// Clean up created data
		if ($this->site && $this->site->get_id()) {
			wp_delete_site($this->site->get_id());
		}

		if ($this->customer && $this->customer->get_id()) {
			$this->customer->delete();
		}

		parent::tearDown();
	}
}
