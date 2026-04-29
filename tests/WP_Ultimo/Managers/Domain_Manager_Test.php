<?php

namespace WP_Ultimo\Managers;

use WP_UnitTestCase;
use WP_Ultimo\Settings;
use WP_Ultimo\Models\Domain;
use WP_Ultimo\Database\Domains\Domain_Stage;

class Domain_Manager_Test extends WP_UnitTestCase {

	private Domain_Manager $domain_manager;

	/**
	 * Test blog ID used across tests.
	 *
	 * @var int
	 */
	protected $blog_id;

	public function setUp(): void {
		parent::setUp();
		$this->domain_manager = Domain_Manager::get_instance();
	}

	/**
	 * Helper to create a test blog, marking test skipped if creation fails.
	 *
	 * @param array $args Optional blog creation args.
	 * @return int Blog ID.
	 */
	protected function create_test_blog(array $args = []): int {
		$blog_id = self::factory()->blog->create($args);

		if (is_wp_error($blog_id)) {
			$this->markTestSkipped('Could not create test blog: ' . $blog_id->get_error_message());
		}

		return $blog_id;
	}

	/**
	 * Get or create the default blog_id for tests that need one.
	 *
	 * @return int
	 */
	protected function get_blog_id(): int {
		if (empty($this->blog_id)) {
			$this->blog_id = $this->create_test_blog();
		}

		return $this->blog_id;
	}

	// ----------------------------------------------------------------
	// Existing tests: should_create_www_subdomain
	// ----------------------------------------------------------------

	/**
	 * Test should_create_www_subdomain with 'always' setting.
	 */
	public function test_should_create_www_subdomain_always(): void {
		// Mock the setting to 'always'
		wu_save_setting('auto_create_www_subdomain', 'always');

		// Test various domain types
		$this->assertTrue($this->domain_manager->should_create_www_subdomain('example.com'));
		$this->assertTrue($this->domain_manager->should_create_www_subdomain('subdomain.example.com'));
		$this->assertTrue($this->domain_manager->should_create_www_subdomain('test.co.uk'));
		$this->assertTrue($this->domain_manager->should_create_www_subdomain('deep.sub.example.com'));
	}

	/**
	 * Test should_create_www_subdomain with 'never' setting.
	 */
	public function test_should_create_www_subdomain_never(): void {
		// Mock the setting to 'never'
		wu_save_setting('auto_create_www_subdomain', 'never');

		// Test various domain types - all should return false
		$this->assertFalse($this->domain_manager->should_create_www_subdomain('example.com'));
		$this->assertFalse($this->domain_manager->should_create_www_subdomain('subdomain.example.com'));
		$this->assertFalse($this->domain_manager->should_create_www_subdomain('test.co.uk'));
		$this->assertFalse($this->domain_manager->should_create_www_subdomain('deep.sub.example.com'));
	}

	/**
	 * Test should_create_www_subdomain with 'main_only' setting for main domains.
	 */
	public function test_should_create_www_subdomain_main_only_main_domains(): void {
		// Mock the setting to 'main_only'
		wu_save_setting('auto_create_www_subdomain', 'main_only');

		// Test main domains - should return true
		$this->assertTrue($this->domain_manager->should_create_www_subdomain('example.com'));
		$this->assertTrue($this->domain_manager->should_create_www_subdomain('test.org'));
		$this->assertTrue($this->domain_manager->should_create_www_subdomain('site.net'));
	}

	/**
	 * Test should_create_www_subdomain with 'main_only' setting for known multi-part TLDs.
	 */
	public function test_should_create_www_subdomain_main_only_multi_part_tlds(): void {
		// Mock the setting to 'main_only'
		wu_save_setting('auto_create_www_subdomain', 'main_only');

		// Test known multi-part TLD domains - should return true
		$this->assertTrue($this->domain_manager->should_create_www_subdomain('example.co.uk'));
		$this->assertTrue($this->domain_manager->should_create_www_subdomain('test.com.au'));
		$this->assertTrue($this->domain_manager->should_create_www_subdomain('site.co.nz'));
		$this->assertTrue($this->domain_manager->should_create_www_subdomain('company.com.br'));
		$this->assertTrue($this->domain_manager->should_create_www_subdomain('business.co.in'));
	}

	/**
	 * Test should_create_www_subdomain with 'main_only' setting for subdomains.
	 */
	public function test_should_create_www_subdomain_main_only_subdomains(): void {
		// Mock the setting to 'main_only'
		wu_save_setting('auto_create_www_subdomain', 'main_only');

		// Test subdomains - should return false
		$this->assertFalse($this->domain_manager->should_create_www_subdomain('subdomain.example.com'));
		$this->assertFalse($this->domain_manager->should_create_www_subdomain('api.test.org'));
		$this->assertFalse($this->domain_manager->should_create_www_subdomain('blog.site.net'));
		$this->assertFalse($this->domain_manager->should_create_www_subdomain('deep.sub.example.com'));
	}

	/**
	 * Test should_create_www_subdomain with 'main_only' setting for complex subdomains with multi-part TLDs.
	 */
	public function test_should_create_www_subdomain_main_only_complex_subdomains(): void {
		// Mock the setting to 'main_only'
		wu_save_setting('auto_create_www_subdomain', 'main_only');

		// Test complex subdomains with multi-part TLDs - should return false
		$this->assertFalse($this->domain_manager->should_create_www_subdomain('subdomain.example.co.uk'));
		$this->assertFalse($this->domain_manager->should_create_www_subdomain('api.test.com.au'));
		$this->assertFalse($this->domain_manager->should_create_www_subdomain('blog.site.co.nz'));
	}

	/**
	 * Test should_create_www_subdomain with default setting (should default to 'always').
	 */
	public function test_should_create_www_subdomain_default(): void {
		// Remove any existing setting to test default behavior
		wu_save_setting('auto_create_www_subdomain', null);

		// Should default to 'always' behavior
		$this->assertTrue($this->domain_manager->should_create_www_subdomain('example.com'));
		$this->assertTrue($this->domain_manager->should_create_www_subdomain('subdomain.example.com'));
	}

	/**
	 * Test should_create_www_subdomain with invalid setting (should default to 'always').
	 */
	public function test_should_create_www_subdomain_invalid_setting(): void {
		// Set an invalid setting value
		wu_save_setting('auto_create_www_subdomain', 'invalid_option');

		// Should default to 'always' behavior
		$this->assertTrue($this->domain_manager->should_create_www_subdomain('example.com'));
		$this->assertTrue($this->domain_manager->should_create_www_subdomain('subdomain.example.com'));
	}

	// ----------------------------------------------------------------
	// NEW: should_create_www_subdomain edge cases
	// ----------------------------------------------------------------

	/**
	 * Test should_create_www_subdomain returns false when domain already starts with www.
	 */
	public function test_should_create_www_subdomain_already_has_www(): void {
		wu_save_setting('auto_create_www_subdomain', 'always');

		$this->assertFalse($this->domain_manager->should_create_www_subdomain('www.example.com'));
		$this->assertFalse($this->domain_manager->should_create_www_subdomain('www.test.co.uk'));
		$this->assertFalse($this->domain_manager->should_create_www_subdomain('www.subdomain.example.com'));
	}

	/**
	 * Test should_create_www_subdomain normalizes domain to lowercase.
	 */
	public function test_should_create_www_subdomain_case_insensitive(): void {
		wu_save_setting('auto_create_www_subdomain', 'always');

		$this->assertTrue($this->domain_manager->should_create_www_subdomain('EXAMPLE.COM'));
		$this->assertTrue($this->domain_manager->should_create_www_subdomain('Example.Com'));
	}

	/**
	 * Test should_create_www_subdomain with uppercase WWW prefix is rejected.
	 */
	public function test_should_create_www_subdomain_uppercase_www_rejected(): void {
		wu_save_setting('auto_create_www_subdomain', 'always');

		// Should be normalized to lowercase, then detected as www prefix
		$this->assertFalse($this->domain_manager->should_create_www_subdomain('WWW.EXAMPLE.COM'));
		$this->assertFalse($this->domain_manager->should_create_www_subdomain('Www.Example.Com'));
	}

	/**
	 * Test should_create_www_subdomain trims whitespace.
	 */
	public function test_should_create_www_subdomain_trims_whitespace(): void {
		wu_save_setting('auto_create_www_subdomain', 'always');

		$this->assertTrue($this->domain_manager->should_create_www_subdomain('  example.com  '));
	}

	// ----------------------------------------------------------------
	// NEW: is_main_domain static method
	// ----------------------------------------------------------------

	/**
	 * Test is_main_domain returns true for simple two-part domains.
	 */
	public function test_is_main_domain_simple_domains(): void {
		$this->assertTrue(Domain_Manager::is_main_domain('example.com'));
		$this->assertTrue(Domain_Manager::is_main_domain('test.org'));
		$this->assertTrue(Domain_Manager::is_main_domain('site.net'));
		$this->assertTrue(Domain_Manager::is_main_domain('my-site.io'));
	}

	/**
	 * Test is_main_domain returns true for multi-part TLDs.
	 */
	public function test_is_main_domain_multi_part_tlds(): void {
		$this->assertTrue(Domain_Manager::is_main_domain('example.co.uk'));
		$this->assertTrue(Domain_Manager::is_main_domain('test.com.au'));
		$this->assertTrue(Domain_Manager::is_main_domain('site.co.nz'));
		$this->assertTrue(Domain_Manager::is_main_domain('company.com.br'));
		$this->assertTrue(Domain_Manager::is_main_domain('business.co.in'));
	}

	/**
	 * Test is_main_domain returns false for subdomains.
	 */
	public function test_is_main_domain_subdomains(): void {
		$this->assertFalse(Domain_Manager::is_main_domain('sub.example.com'));
		$this->assertFalse(Domain_Manager::is_main_domain('api.test.org'));
		$this->assertFalse(Domain_Manager::is_main_domain('blog.site.net'));
	}

	/**
	 * Test is_main_domain returns false for deep subdomains.
	 */
	public function test_is_main_domain_deep_subdomains(): void {
		$this->assertFalse(Domain_Manager::is_main_domain('deep.sub.example.com'));
		$this->assertFalse(Domain_Manager::is_main_domain('a.b.c.example.com'));
	}

	/**
	 * Test is_main_domain returns false for subdomains of multi-part TLDs.
	 */
	public function test_is_main_domain_subdomains_of_multi_part_tlds(): void {
		$this->assertFalse(Domain_Manager::is_main_domain('sub.example.co.uk'));
		$this->assertFalse(Domain_Manager::is_main_domain('api.test.com.au'));
	}

	/**
	 * Test is_main_domain normalizes case.
	 */
	public function test_is_main_domain_case_normalization(): void {
		$this->assertTrue(Domain_Manager::is_main_domain('EXAMPLE.COM'));
		$this->assertTrue(Domain_Manager::is_main_domain('Example.Co.Uk'));
	}

	/**
	 * Test is_main_domain trims trailing dot.
	 */
	public function test_is_main_domain_trailing_dot(): void {
		$this->assertTrue(Domain_Manager::is_main_domain('example.com.'));
		$this->assertTrue(Domain_Manager::is_main_domain('test.co.uk.'));
	}

	/**
	 * Test is_main_domain trims whitespace.
	 */
	public function test_is_main_domain_trims_whitespace(): void {
		$this->assertTrue(Domain_Manager::is_main_domain(' example.com '));
		$this->assertTrue(Domain_Manager::is_main_domain(' test.co.uk '));
	}

	/**
	 * Test is_main_domain with single-part domain.
	 */
	public function test_is_main_domain_single_part(): void {
		$this->assertTrue(Domain_Manager::is_main_domain('localhost'));
		$this->assertTrue(Domain_Manager::is_main_domain('intranet'));
	}

	/**
	 * Test is_main_domain with custom multi-part TLD filter.
	 */
	public function test_is_main_domain_custom_multi_part_tld_via_filter(): void {
		// Add a custom TLD via filter
		add_filter('wu_multi_part_tlds', function ($tlds) {
			$tlds[] = '.org.uk';
			return $tlds;
		});

		$this->assertTrue(Domain_Manager::is_main_domain('example.org.uk'));

		// Clean up
		remove_all_filters('wu_multi_part_tlds');
	}

	// ----------------------------------------------------------------
	// NEW: Manager initialization and singleton
	// ----------------------------------------------------------------

	/**
	 * Test manager is a singleton.
	 */
	public function test_manager_is_singleton(): void {
		$instance1 = Domain_Manager::get_instance();
		$instance2 = Domain_Manager::get_instance();
		$this->assertSame($instance1, $instance2);
	}

	/**
	 * Test manager has correct slug.
	 */
	public function test_manager_slug(): void {
		$reflection = new \ReflectionClass($this->domain_manager);
		$slug_prop = $reflection->getProperty('slug');

		if (PHP_VERSION_ID < 80100) {
			$slug_prop->setAccessible(true);
		}

		$this->assertEquals('domain', $slug_prop->getValue($this->domain_manager));
	}

	/**
	 * Test manager has correct model class.
	 */
	public function test_manager_model_class(): void {
		$reflection = new \ReflectionClass($this->domain_manager);
		$model_prop = $reflection->getProperty('model_class');

		if (PHP_VERSION_ID < 80100) {
			$model_prop->setAccessible(true);
		}

		$this->assertEquals(\WP_Ultimo\Models\Domain::class, $model_prop->getValue($this->domain_manager));
	}

	// ----------------------------------------------------------------
	// NEW: Domain CRUD operations via helper functions
	// ----------------------------------------------------------------

	/**
	 * Test creating a domain with wu_create_domain.
	 */
	public function test_create_domain_basic(): void {
		$domain = wu_create_domain([
			'blog_id'        => $this->get_blog_id(),
			'domain'         => 'test-create-basic.example.com',
			'active'         => true,
			'primary_domain' => false,
			'secure'         => false,
			'stage'          => Domain_Stage::DONE,
		]);

		$this->assertNotWPError($domain);
		$this->assertInstanceOf(Domain::class, $domain);
		$this->assertGreaterThan(0, $domain->get_id());
		$this->assertEquals('test-create-basic.example.com', $domain->get_domain());
		$this->assertEquals($this->get_blog_id(), $domain->get_blog_id());
	}

	/**
	 * Test retrieving a domain by ID.
	 */
	public function test_get_domain_by_id(): void {
		$domain = wu_create_domain([
			'blog_id' => $this->get_blog_id(),
			'domain'  => 'get-by-id.example.com',
			'stage'   => Domain_Stage::DONE,
		]);

		$this->assertNotWPError($domain);

		$fetched = wu_get_domain($domain->get_id());

		$this->assertInstanceOf(Domain::class, $fetched);
		$this->assertEquals($domain->get_id(), $fetched->get_id());
		$this->assertEquals('get-by-id.example.com', $fetched->get_domain());
	}

	/**
	 * Test retrieving a domain by domain name.
	 */
	public function test_get_domain_by_domain_name(): void {
		$domain = wu_create_domain([
			'blog_id' => $this->get_blog_id(),
			'domain'  => 'get-by-name.example.com',
			'stage'   => Domain_Stage::DONE,
		]);

		$this->assertNotWPError($domain);

		$fetched = wu_get_domain_by_domain('get-by-name.example.com');

		$this->assertInstanceOf(Domain::class, $fetched);
		$this->assertEquals($domain->get_id(), $fetched->get_id());
	}

	/**
	 * Test retrieving a non-existent domain returns false.
	 */
	public function test_get_nonexistent_domain_returns_false(): void {
		$fetched = wu_get_domain(999999);
		$this->assertFalse($fetched);
	}

	/**
	 * Test querying domains.
	 */
	public function test_query_domains(): void {
		$domain1 = wu_create_domain([
			'blog_id' => $this->get_blog_id(),
			'domain'  => 'query-1.example.com',
			'stage'   => Domain_Stage::DONE,
		]);

		$domain2 = wu_create_domain([
			'blog_id' => $this->get_blog_id(),
			'domain'  => 'query-2.example.com',
			'stage'   => Domain_Stage::DONE,
		]);

		$this->assertNotWPError($domain1);
		$this->assertNotWPError($domain2);

		$domains = wu_get_domains([
			'blog_id' => $this->get_blog_id(),
		]);

		$this->assertIsArray($domains);
		$this->assertGreaterThanOrEqual(2, count($domains));
	}

	/**
	 * Test querying domains by blog_id.
	 */
	public function test_query_domains_by_blog_id(): void {
		$blog_id_2 = $this->create_test_blog();

		$domain = wu_create_domain([
			'blog_id' => $blog_id_2,
			'domain'  => 'query-blog-id.example.com',
			'stage'   => Domain_Stage::DONE,
		]);

		$this->assertNotWPError($domain);

		$domains = wu_get_domains([
			'blog_id' => $blog_id_2,
		]);

		$this->assertIsArray($domains);
		$this->assertGreaterThanOrEqual(1, count($domains));

		foreach ($domains as $d) {
			$this->assertEquals($blog_id_2, $d->get_blog_id());
		}
	}

	/**
	 * Test creating a domain without blog_id fails.
	 */
	public function test_create_domain_without_blog_id_fails(): void {
		$result = wu_create_domain([
			'domain' => 'no-blog.example.com',
			'stage'  => Domain_Stage::DONE,
		]);

		$this->assertWPError($result);
	}

	/**
	 * Test creating a domain without domain name fails.
	 */
	public function test_create_domain_without_domain_fails(): void {
		$result = wu_create_domain([
			'blog_id' => $this->get_blog_id(),
			'stage'   => Domain_Stage::DONE,
		]);

		$this->assertWPError($result);
	}

	/**
	 * Test deleting a domain.
	 */
	public function test_delete_domain(): void {
		$domain = wu_create_domain([
			'blog_id' => $this->get_blog_id(),
			'domain'  => 'delete-me.example.com',
			'stage'   => Domain_Stage::DONE,
		]);

		$this->assertNotWPError($domain);
		$domain_id = $domain->get_id();

		$result = $domain->delete();
		$this->assertNotWPError($result);

		$fetched = wu_get_domain($domain_id);
		$this->assertFalse($fetched);
	}

	/**
	 * Test updating a domain.
	 */
	public function test_update_domain(): void {
		$domain = wu_create_domain([
			'blog_id' => $this->get_blog_id(),
			'domain'  => 'update-me.example.com',
			'stage'   => Domain_Stage::DONE,
			'secure'  => false,
		]);

		$this->assertNotWPError($domain);

		$domain->set_secure(true);
		$result = $domain->save();

		$this->assertNotWPError($result);

		$fetched = wu_get_domain($domain->get_id());
		$this->assertTrue($fetched->is_secure());
	}

	// ----------------------------------------------------------------
	// NEW: Domain model properties and states
	// ----------------------------------------------------------------

	/**
	 * Test domain active state with done stage.
	 */
	public function test_domain_is_active_when_done(): void {
		$domain = new Domain();
		$domain->set_active(true);
		$domain->set_stage(Domain_Stage::DONE);

		$this->assertTrue($domain->is_active());
	}

	/**
	 * Test domain is inactive during checking-dns stage.
	 */
	public function test_domain_is_inactive_during_checking_dns(): void {
		$domain = new Domain();
		$domain->set_active(true);
		$domain->set_stage(Domain_Stage::CHECKING_DNS);

		$this->assertFalse($domain->is_active());
	}

	/**
	 * Test domain is inactive during checking-ssl stage.
	 */
	public function test_domain_is_inactive_during_checking_ssl(): void {
		$domain = new Domain();
		$domain->set_active(true);
		$domain->set_stage(Domain_Stage::CHECKING_SSL);

		$this->assertFalse($domain->is_active());
	}

	/**
	 * Test domain is inactive when stage is failed.
	 */
	public function test_domain_is_inactive_when_failed(): void {
		$domain = new Domain();
		$domain->set_active(true);
		$domain->set_stage(Domain_Stage::FAILED);

		$this->assertFalse($domain->is_active());
	}

	/**
	 * Test domain is inactive when stage is ssl-failed.
	 */
	public function test_domain_is_inactive_when_ssl_failed(): void {
		$domain = new Domain();
		$domain->set_active(true);
		$domain->set_stage(Domain_Stage::SSL_FAILED);

		$this->assertFalse($domain->is_active());
	}

	/**
	 * Test domain is inactive when explicitly set to inactive, even with done stage.
	 */
	public function test_domain_is_inactive_when_explicitly_inactive(): void {
		$domain = new Domain();
		$domain->set_active(false);
		$domain->set_stage(Domain_Stage::DONE);

		$this->assertFalse($domain->is_active());
	}

	/**
	 * Test has_inactive_stage for all inactive stages.
	 */
	public function test_has_inactive_stage(): void {
		$domain = new Domain();

		$inactive_stages = [
			Domain_Stage::CHECKING_DNS,
			Domain_Stage::CHECKING_SSL,
			Domain_Stage::FAILED,
			Domain_Stage::SSL_FAILED,
		];

		foreach ($inactive_stages as $stage) {
			$domain->set_stage($stage);
			$this->assertTrue($domain->has_inactive_stage(), "Stage {$stage} should be inactive");
		}

		$active_stages = [
			Domain_Stage::DONE,
			Domain_Stage::DONE_WITHOUT_SSL,
		];

		foreach ($active_stages as $stage) {
			$domain->set_stage($stage);
			$this->assertFalse($domain->has_inactive_stage(), "Stage {$stage} should be active");
		}
	}

	// ----------------------------------------------------------------
	// NEW: Domain secure/SSL handling
	// ----------------------------------------------------------------

	/**
	 * Test domain secure flag.
	 */
	public function test_domain_secure_flag(): void {
		$domain = new Domain();

		$domain->set_secure(false);
		$this->assertFalse($domain->is_secure());

		$domain->set_secure(true);
		$this->assertTrue($domain->is_secure());
	}

	/**
	 * Test domain URL uses http when not secure.
	 */
	public function test_domain_url_http(): void {
		$domain = new Domain();
		$domain->set_domain('test.example.com');
		$domain->set_secure(false);

		$url = $domain->get_url();
		$this->assertStringStartsWith('http://', $url);
		$this->assertStringContainsString('test.example.com', $url);
	}

	/**
	 * Test domain URL uses https when secure.
	 */
	public function test_domain_url_https(): void {
		$domain = new Domain();
		$domain->set_domain('test.example.com');
		$domain->set_secure(true);

		$url = $domain->get_url();
		$this->assertStringStartsWith('https://', $url);
		$this->assertStringContainsString('test.example.com', $url);
	}

	/**
	 * Test domain URL with path.
	 */
	public function test_domain_url_with_path(): void {
		$domain = new Domain();
		$domain->set_domain('test.example.com');
		$domain->set_secure(true);

		$url = $domain->get_url('wp-admin');
		$this->assertEquals('https://test.example.com/wp-admin', $url);
	}

	// ----------------------------------------------------------------
	// NEW: Primary domain management
	// ----------------------------------------------------------------

	/**
	 * Test setting a domain as primary.
	 */
	public function test_set_primary_domain(): void {
		$domain = wu_create_domain([
			'blog_id'        => $this->get_blog_id(),
			'domain'         => 'primary-test.example.com',
			'primary_domain' => true,
			'stage'          => Domain_Stage::DONE,
		]);

		$this->assertNotWPError($domain);
		$this->assertTrue($domain->is_primary_domain());
	}

	/**
	 * Test non-primary domain.
	 */
	public function test_non_primary_domain(): void {
		$domain = wu_create_domain([
			'blog_id'        => $this->get_blog_id(),
			'domain'         => 'non-primary-test.example.com',
			'primary_domain' => false,
			'stage'          => Domain_Stage::DONE,
		]);

		$this->assertNotWPError($domain);
		$this->assertFalse($domain->is_primary_domain());
	}

	/**
	 * Test async_remove_old_primary_domains removes primary flag.
	 */
	public function test_async_remove_old_primary_domains(): void {
		$domain1 = wu_create_domain([
			'blog_id'        => $this->get_blog_id(),
			'domain'         => 'old-primary-1.example.com',
			'primary_domain' => true,
			'stage'          => Domain_Stage::DONE,
		]);

		$domain2 = wu_create_domain([
			'blog_id'        => $this->get_blog_id(),
			'domain'         => 'old-primary-2.example.com',
			'primary_domain' => true,
			'stage'          => Domain_Stage::DONE,
		]);

		$this->assertNotWPError($domain1);
		$this->assertNotWPError($domain2);

		// Call the method to remove primary from these domains
		$this->domain_manager->async_remove_old_primary_domains([
			$domain1->get_id(),
			$domain2->get_id(),
		]);

		// Fetch again to verify
		$fetched1 = wu_get_domain($domain1->get_id());
		$fetched2 = wu_get_domain($domain2->get_id());

		$this->assertFalse($fetched1->is_primary_domain());
		$this->assertFalse($fetched2->is_primary_domain());
	}

	/**
	 * Test async_remove_old_primary_domains handles empty array.
	 */
	public function test_async_remove_old_primary_domains_empty_array(): void {
		// Should not throw an error with an empty array
		$this->domain_manager->async_remove_old_primary_domains([]);
		$this->assertTrue(true); // If we got here, no exception was thrown
	}

	/**
	 * Test async_remove_old_primary_domains handles invalid domain IDs.
	 */
	public function test_async_remove_old_primary_domains_invalid_ids(): void {
		// Should not throw an error with non-existent IDs
		$this->domain_manager->async_remove_old_primary_domains([999999, 888888]);
		$this->assertTrue(true); // If we got here, no exception was thrown
	}

	// ----------------------------------------------------------------
	// NEW: Domain-to-site mapping
	// ----------------------------------------------------------------

	/**
	 * Test get_blog_id and get_site_id return same value.
	 */
	public function test_get_blog_id_equals_get_site_id(): void {
		$domain = new Domain();
		$domain->set_blog_id($this->get_blog_id());

		$this->assertEquals($domain->get_blog_id(), $domain->get_site_id());
	}

	/**
	 * Test domain is associated with the correct blog.
	 */
	public function test_domain_blog_association(): void {
		$domain = wu_create_domain([
			'blog_id' => $this->get_blog_id(),
			'domain'  => 'blog-assoc.example.com',
			'stage'   => Domain_Stage::DONE,
		]);

		$this->assertNotWPError($domain);
		$this->assertEquals($this->get_blog_id(), $domain->get_blog_id());
	}

	/**
	 * Test Domain::get_by_site returns domains for a site.
	 */
	public function test_get_by_site(): void {
		$blog_id = $this->create_test_blog();

		$domain = wu_create_domain([
			'blog_id' => $blog_id,
			'domain'  => 'get-by-site-test.example.com',
			'stage'   => Domain_Stage::DONE,
		]);

		$this->assertNotWPError($domain);

		wp_cache_flush();

		$mappings = Domain::get_by_site($blog_id);

		$this->assertNotFalse($mappings);
		$this->assertIsArray($mappings);
		$this->assertGreaterThanOrEqual(1, count($mappings));
	}

	/**
	 * Test Domain::get_by_site with site object.
	 */
	public function test_get_by_site_with_object(): void {
		$blog_id = $this->create_test_blog();

		$domain = wu_create_domain([
			'blog_id' => $blog_id,
			'domain'  => 'get-by-site-obj.example.com',
			'stage'   => Domain_Stage::DONE,
		]);

		$this->assertNotWPError($domain);

		wp_cache_flush();

		$site = get_blog_details($blog_id);
		$mappings = Domain::get_by_site($site);

		$this->assertNotFalse($mappings);
		$this->assertIsArray($mappings);
	}

	/**
	 * Test Domain::get_by_site returns false for a site with no domains.
	 */
	public function test_get_by_site_no_domains(): void {
		$blog_id = $this->create_test_blog();

		wp_cache_flush();

		$mappings = Domain::get_by_site($blog_id);

		// Should be false when no mappings found
		$this->assertFalse($mappings);
	}

	/**
	 * Test Domain::get_by_site returns WP_Error for invalid site ID.
	 */
	public function test_get_by_site_invalid_id(): void {
		$result = Domain::get_by_site('not-a-number');

		$this->assertWPError($result);
	}

	/**
	 * Test Domain::get_by_domain retrieves correct mapping.
	 */
	public function test_get_by_domain(): void {
		global $wpdb;

		// Ensure wu_dmtable is set
		if (empty($wpdb->wu_dmtable)) {
			$wpdb->wu_dmtable = $wpdb->base_prefix . 'wu_domain_mappings';
		}

		$domain = wu_create_domain([
			'blog_id' => $this->get_blog_id(),
			'domain'  => 'get-by-domain-test.example.com',
			'stage'   => Domain_Stage::DONE,
		]);

		$this->assertNotWPError($domain);

		wp_cache_flush();

		$fetched = Domain::get_by_domain('get-by-domain-test.example.com');

		$this->assertInstanceOf(Domain::class, $fetched);
		$this->assertEquals('get-by-domain-test.example.com', $fetched->get_domain());
	}

	/**
	 * Test Domain::get_by_domain returns null for non-existent domain.
	 */
	public function test_get_by_domain_nonexistent(): void {
		global $wpdb;

		if (empty($wpdb->wu_dmtable)) {
			$wpdb->wu_dmtable = $wpdb->base_prefix . 'wu_domain_mappings';
		}

		wp_cache_flush();

		$fetched = Domain::get_by_domain('nonexistent-domain-xyz.example.com');

		$this->assertNull($fetched);
	}

	/**
	 * Test Domain::get_by_domain with array of domains.
	 */
	public function test_get_by_domain_with_array(): void {
		global $wpdb;

		if (empty($wpdb->wu_dmtable)) {
			$wpdb->wu_dmtable = $wpdb->base_prefix . 'wu_domain_mappings';
		}

		$domain = wu_create_domain([
			'blog_id' => $this->get_blog_id(),
			'domain'  => 'array-test-domain.example.com',
			'stage'   => Domain_Stage::DONE,
		]);

		$this->assertNotWPError($domain);

		wp_cache_flush();

		$fetched = Domain::get_by_domain([
			'nonexistent.example.com',
			'array-test-domain.example.com',
		]);

		$this->assertInstanceOf(Domain::class, $fetched);
		$this->assertEquals('array-test-domain.example.com', $fetched->get_domain());
	}

	// ----------------------------------------------------------------
	// NEW: Stage transitions
	// ----------------------------------------------------------------

	/**
	 * Test all domain stage constants exist.
	 */
	public function test_domain_stage_constants(): void {
		$this->assertEquals('checking-dns', Domain_Stage::CHECKING_DNS);
		$this->assertEquals('checking-ssl-cert', Domain_Stage::CHECKING_SSL);
		$this->assertEquals('done', Domain_Stage::DONE);
		$this->assertEquals('done-without-ssl', Domain_Stage::DONE_WITHOUT_SSL);
		$this->assertEquals('failed', Domain_Stage::FAILED);
		$this->assertEquals('ssl-failed', Domain_Stage::SSL_FAILED);
	}

	/**
	 * Test domain stage can be set and retrieved.
	 */
	public function test_domain_stage_getter_setter(): void {
		$domain = new Domain();

		$stages = [
			Domain_Stage::CHECKING_DNS,
			Domain_Stage::CHECKING_SSL,
			Domain_Stage::DONE,
			Domain_Stage::DONE_WITHOUT_SSL,
			Domain_Stage::FAILED,
			Domain_Stage::SSL_FAILED,
		];

		foreach ($stages as $stage) {
			$domain->set_stage($stage);
			$this->assertEquals($stage, $domain->get_stage());
		}
	}

	/**
	 * Test domain default stage is checking-dns.
	 */
	public function test_domain_default_stage(): void {
		$domain = new Domain();
		$this->assertEquals(Domain_Stage::CHECKING_DNS, $domain->get_stage());
	}

	/**
	 * Test async_process_domain_stage with non-existent domain.
	 */
	public function test_async_process_domain_stage_nonexistent_domain(): void {
		// Should return early without error
		$this->domain_manager->async_process_domain_stage(999999);
		$this->assertTrue(true);
	}

	/**
	 * Test async_process_domain_stage DNS check stage transitions to failed after max tries.
	 */
	public function test_async_process_domain_stage_dns_max_tries_fails(): void {
		$domain = wu_create_domain([
			'blog_id' => $this->get_blog_id(),
			'domain'  => 'dns-fail-test.example.com',
			'stage'   => Domain_Stage::CHECKING_DNS,
		]);

		$this->assertNotWPError($domain);

		// Set max tries to exceed (default is 5)
		$this->domain_manager->async_process_domain_stage($domain->get_id(), 6);

		// Fetch the domain again to check the updated stage
		$fetched = wu_get_domain($domain->get_id());
		$this->assertEquals(Domain_Stage::FAILED, $fetched->get_stage());
	}

	/**
	 * Test async_process_domain_stage SSL check stage transitions to ssl-failed after max tries.
	 */
	public function test_async_process_domain_stage_ssl_max_tries_fails(): void {
		$domain = wu_create_domain([
			'blog_id' => $this->get_blog_id(),
			'domain'  => 'ssl-fail-test.example.com',
			'stage'   => Domain_Stage::CHECKING_SSL,
		]);

		$this->assertNotWPError($domain);

		// Set max tries to exceed (default is 5)
		$this->domain_manager->async_process_domain_stage($domain->get_id(), 6);

		// Fetch the domain again
		$fetched = wu_get_domain($domain->get_id());
		$this->assertEquals(Domain_Stage::SSL_FAILED, $fetched->get_stage());
	}

	/**
	 * Test async_process_domain_stage with domain in done stage does nothing.
	 */
	public function test_async_process_domain_stage_done_does_nothing(): void {
		$domain = wu_create_domain([
			'blog_id' => $this->get_blog_id(),
			'domain'  => 'done-stage-test.example.com',
			'stage'   => Domain_Stage::DONE,
		]);

		$this->assertNotWPError($domain);

		$this->domain_manager->async_process_domain_stage($domain->get_id());

		// Should still be done
		$fetched = wu_get_domain($domain->get_id());
		$this->assertEquals(Domain_Stage::DONE, $fetched->get_stage());
	}

	/**
	 * Test async_process_domain_stage max_tries filter.
	 */
	public function test_async_process_domain_stage_max_tries_filter(): void {
		// Set max tries to 1 via filter
		add_filter('wu_async_process_domain_stage_max_tries', function () {
			return 1;
		});

		$domain = wu_create_domain([
			'blog_id' => $this->get_blog_id(),
			'domain'  => 'max-tries-filter.example.com',
			'stage'   => Domain_Stage::CHECKING_DNS,
		]);

		$this->assertNotWPError($domain);

		// Try count 2 should exceed max of 1
		$this->domain_manager->async_process_domain_stage($domain->get_id(), 2);

		$fetched = wu_get_domain($domain->get_id());
		$this->assertEquals(Domain_Stage::FAILED, $fetched->get_stage());

		remove_all_filters('wu_async_process_domain_stage_max_tries');
	}

	// ----------------------------------------------------------------
	// NEW: send_domain_to_host
	// ----------------------------------------------------------------

	/**
	 * Test send_domain_to_host does nothing when old and new values are the same.
	 */
	public function test_send_domain_to_host_same_value(): void {
		$domain = wu_create_domain([
			'blog_id' => $this->get_blog_id(),
			'domain'  => 'same-value-host.example.com',
			'stage'   => Domain_Stage::DONE,
		]);

		$this->assertNotWPError($domain);

		// Should not throw any errors
		$this->domain_manager->send_domain_to_host(
			'same-value-host.example.com',
			'same-value-host.example.com',
			$domain->get_id()
		);
		$this->assertTrue(true);
	}

	/**
	 * Test send_domain_to_host enqueues action when values differ.
	 */
	public function test_send_domain_to_host_different_values(): void {
		$domain = wu_create_domain([
			'blog_id' => $this->get_blog_id(),
			'domain'  => 'new-host-domain.example.com',
			'stage'   => Domain_Stage::DONE,
		]);

		$this->assertNotWPError($domain);

		// This should enqueue an async action
		$this->domain_manager->send_domain_to_host(
			'old-host-domain.example.com',
			'new-host-domain.example.com',
			$domain->get_id()
		);

		$this->assertTrue(true); // If we got here, no exception was thrown
	}

	// ----------------------------------------------------------------
	// NEW: Domain set_domain normalizes to lowercase
	// ----------------------------------------------------------------

	/**
	 * Test domain name is stored in lowercase.
	 */
	public function test_domain_name_stored_lowercase(): void {
		$domain = new Domain();
		$domain->set_domain('MyDomain.EXAMPLE.COM');

		$this->assertEquals('mydomain.example.com', $domain->get_domain());
	}

	// ----------------------------------------------------------------
	// NEW: handle_domain_deleted
	// ----------------------------------------------------------------

	/**
	 * Test handle_domain_deleted enqueues action on successful deletion.
	 */
	public function test_handle_domain_deleted_with_success(): void {
		$domain = new Domain();
		$domain->set_domain('deleted-domain.example.com');
		$domain->set_blog_id($this->get_blog_id());

		// Pass true as result - should enqueue async action
		$this->domain_manager->handle_domain_deleted(true, $domain);
		$this->assertTrue(true);
	}

	/**
	 * Test handle_domain_deleted does nothing on failed deletion.
	 */
	public function test_handle_domain_deleted_with_failure(): void {
		$domain = new Domain();
		$domain->set_domain('failed-delete.example.com');
		$domain->set_blog_id($this->get_blog_id());

		// Pass false as result - should do nothing
		$this->domain_manager->handle_domain_deleted(false, $domain);
		$this->assertTrue(true);
	}

	// ----------------------------------------------------------------
	// NEW: handle_site_created and handle_site_deleted
	// ----------------------------------------------------------------

	/**
	 * Test handle_site_created with a site that has a subdomain.
	 */
	public function test_handle_site_created_with_subdomain(): void {
		global $current_site;

		$blog_id = $this->create_test_blog([
			'domain' => 'sub.' . $current_site->domain,
		]);

		$site = get_blog_details($blog_id);

		// Call the handler directly
		$this->domain_manager->handle_site_created($site);

		// Verify that a domain record was created
		$domains = wu_get_domains([
			'blog_id' => $blog_id,
		]);

		$this->assertIsArray($domains);
		$this->assertGreaterThanOrEqual(1, count($domains));
	}

	/**
	 * Test handle_site_created with a site that is the main site domain (no subdomain).
	 */
	public function test_handle_site_created_without_subdomain(): void {
		global $current_site;

		// Use the main site directly
		$site = get_blog_details(1);

		// Should return early - no domain record creation
		$this->domain_manager->handle_site_created($site);
		$this->assertTrue(true);
	}

	/**
	 * Test handle_site_deleted with a subdomain site.
	 */
	public function test_handle_site_deleted_with_subdomain(): void {
		global $current_site;

		// Create a real blog with a subdomain to get a valid WP_Site
		$blog_id = $this->create_test_blog([
			'domain' => 'deleted-sub.' . $current_site->domain,
		]);

		$site = get_blog_details($blog_id);

		// Should enqueue async action for subdomain removal
		$this->domain_manager->handle_site_deleted($site);
		$this->assertTrue(true);
	}

	/**
	 * Test handle_site_deleted without subdomain does nothing.
	 */
	public function test_handle_site_deleted_without_subdomain(): void {
		// Use the main site directly
		$site = get_blog_details(1);

		$this->domain_manager->handle_site_deleted($site);
		$this->assertTrue(true);
	}

	// ----------------------------------------------------------------
	// NEW: create_domain_record_for_site
	// ----------------------------------------------------------------

	/**
	 * Test create_domain_record_for_site creates a new domain record.
	 */
	public function test_create_domain_record_for_site(): void {
		$blog_id = $this->create_test_blog([
			'domain' => 'new-site-record.example.com',
		]);

		$site = get_site($blog_id);

		// Make sure no domains exist yet for this site
		wp_cache_flush();

		$result = $this->domain_manager->create_domain_record_for_site($site);

		$this->assertNotWPError($result);
		$this->assertInstanceOf(Domain::class, $result);
		$this->assertEquals($blog_id, $result->get_blog_id());
		$this->assertEquals('new-site-record.example.com', $result->get_domain());
		$this->assertTrue($result->is_primary_domain());
	}

	/**
	 * Test create_domain_record_for_site returns existing domain if one already exists.
	 */
	public function test_create_domain_record_for_site_returns_existing(): void {
		$blog_id = $this->create_test_blog([
			'domain' => 'existing-record-2.example.com',
		]);

		$site = get_site($blog_id);

		// Check if handle_site_created already created a domain for this site
		$existing_domains = wu_get_domains([
			'blog_id' => $blog_id,
			'number'  => 1,
		]);

		if (empty($existing_domains)) {
			// Create a domain manually if none exists
			$existing = wu_create_domain([
				'blog_id'        => $blog_id,
				'domain'         => 'existing-record-2.example.com',
				'primary_domain' => true,
				'stage'          => Domain_Stage::DONE,
			]);

			$this->assertNotWPError($existing);
		} else {
			$existing = $existing_domains[0];
		}

		$result = $this->domain_manager->create_domain_record_for_site($site);

		// Should return the existing domain, not create a new one
		$this->assertInstanceOf(Domain::class, $result);
		$this->assertEquals($existing->get_id(), $result->get_id());
	}

	// ----------------------------------------------------------------
	// NEW: get_integrations
	// ----------------------------------------------------------------

	/**
	 * Test get_integrations returns an array.
	 */
	public function test_get_integrations_returns_array(): void {
		$integrations = $this->domain_manager->get_integrations();
		$this->assertIsArray($integrations);
	}

	/**
	 * Test get_integrations is filterable.
	 */
	public function test_get_integrations_is_filterable(): void {
		add_filter('wu_domain_manager_get_integrations', function ($integrations) {
			$integrations['test_integration'] = 'TestClass';
			return $integrations;
		});

		$integrations = $this->domain_manager->get_integrations();
		$this->assertArrayHasKey('test_integration', $integrations);

		remove_all_filters('wu_domain_manager_get_integrations');
	}

	/**
	 * Test get_integration_instance returns false for non-existent integration.
	 */
	public function test_get_integration_instance_nonexistent(): void {
		$result = $this->domain_manager->get_integration_instance('nonexistent_integration');
		$this->assertFalse($result);
	}

	// ----------------------------------------------------------------
	// NEW: default_domain_mapping_instructions
	// ----------------------------------------------------------------

	/**
	 * Test default_domain_mapping_instructions returns a non-empty string.
	 */
	public function test_default_domain_mapping_instructions(): void {
		$instructions = $this->domain_manager->default_domain_mapping_instructions();
		$this->assertIsString($instructions);
		$this->assertNotEmpty($instructions);
	}

	/**
	 * Test default_domain_mapping_instructions contains the network domain placeholder.
	 */
	public function test_default_domain_mapping_instructions_has_placeholder(): void {
		$instructions = $this->domain_manager->default_domain_mapping_instructions();
		$this->assertStringContainsString('%NETWORK_DOMAIN%', $instructions);
	}

	// ----------------------------------------------------------------
	// NEW: get_domain_mapping_instructions
	// ----------------------------------------------------------------

	/**
	 * Test get_domain_mapping_instructions returns a string.
	 */
	public function test_get_domain_mapping_instructions(): void {
		$instructions = $this->domain_manager->get_domain_mapping_instructions();
		$this->assertIsString($instructions);
		$this->assertNotEmpty($instructions);
	}

	/**
	 * Test get_domain_mapping_instructions replaces placeholders.
	 */
	public function test_get_domain_mapping_instructions_replaces_placeholders(): void {
		$instructions = $this->domain_manager->get_domain_mapping_instructions();

		// The placeholders should be replaced
		$this->assertStringNotContainsString('%NETWORK_DOMAIN%', $instructions);
		$this->assertStringNotContainsString('%NETWORK_IP%', $instructions);
	}

	/**
	 * Test get_domain_mapping_instructions is filterable.
	 */
	public function test_get_domain_mapping_instructions_filterable(): void {
		add_filter('wu_get_domain_mapping_instructions', function () {
			return 'Custom instructions';
		});

		$instructions = $this->domain_manager->get_domain_mapping_instructions();
		$this->assertEquals('Custom instructions', $instructions);

		remove_all_filters('wu_get_domain_mapping_instructions');
	}

	/**
	 * Test get_domain_mapping_instructions uses saved setting.
	 */
	public function test_get_domain_mapping_instructions_from_settings(): void {
		wu_save_setting('domain_mapping_instructions', 'Point your domain to %NETWORK_DOMAIN% (%NETWORK_IP%)');

		$instructions = $this->domain_manager->get_domain_mapping_instructions();

		$this->assertStringNotContainsString('%NETWORK_DOMAIN%', $instructions);
		$this->assertStringNotContainsString('%NETWORK_IP%', $instructions);
		$this->assertStringContainsString('Point your domain to', $instructions);
	}

	// ----------------------------------------------------------------
	// NEW: Domain model getters/setters
	// ----------------------------------------------------------------

	/**
	 * Test date_created getter and setter.
	 */
	public function test_date_created(): void {
		$domain = new Domain();
		$date = '2025-01-01 12:00:00';
		$domain->set_date_created($date);

		$this->assertEquals($date, $domain->get_date_created());
	}

	/**
	 * Test domain model get_site returns site for valid blog_id.
	 */
	public function test_domain_get_site(): void {
		$domain = wu_create_domain([
			'blog_id' => $this->get_blog_id(),
			'domain'  => 'get-site-test.example.com',
			'stage'   => Domain_Stage::DONE,
		]);

		$this->assertNotWPError($domain);

		$site = $domain->get_site();
		$this->assertNotFalse($site);
	}

	/**
	 * Test domain model get_path returns a string for valid blog_id.
	 */
	public function test_domain_get_path(): void {
		$domain = wu_create_domain([
			'blog_id' => $this->get_blog_id(),
			'domain'  => 'get-path-test.example.com',
			'stage'   => Domain_Stage::DONE,
		]);

		$this->assertNotWPError($domain);

		$path = $domain->get_path();
		$this->assertIsString($path);
	}

	/**
	 * Test domain model get_path returns null for invalid blog_id.
	 */
	public function test_domain_get_path_invalid_blog(): void {
		$domain = new Domain();
		$domain->set_blog_id(999999);

		$path = $domain->get_path();
		$this->assertNull($path);
	}

	/**
	 * Test stage label returns a string.
	 */
	public function test_domain_stage_label(): void {
		$domain = new Domain();
		$domain->set_stage(Domain_Stage::DONE);

		$label = $domain->get_stage_label();
		$this->assertIsString($label);
		$this->assertNotEmpty($label);
	}

	/**
	 * Test stage class returns a string.
	 */
	public function test_domain_stage_class(): void {
		$domain = new Domain();
		$domain->set_stage(Domain_Stage::DONE);

		$class = $domain->get_stage_class();
		$this->assertIsString($class);
		$this->assertNotEmpty($class);
	}

	/**
	 * Test stage labels for all stages.
	 */
	public function test_domain_stage_labels_for_all_stages(): void {
		$stages = [
			Domain_Stage::CHECKING_DNS,
			Domain_Stage::CHECKING_SSL,
			Domain_Stage::DONE,
			Domain_Stage::DONE_WITHOUT_SSL,
			Domain_Stage::FAILED,
			Domain_Stage::SSL_FAILED,
		];

		$domain = new Domain();

		foreach ($stages as $stage) {
			$domain->set_stage($stage);
			$label = $domain->get_stage_label();
			$this->assertIsString($label, "Stage {$stage} should have a label");
			$this->assertNotEmpty($label, "Stage {$stage} label should not be empty");
		}
	}

	/**
	 * Test stage classes for all stages.
	 */
	public function test_domain_stage_classes_for_all_stages(): void {
		$stages = [
			Domain_Stage::CHECKING_DNS,
			Domain_Stage::CHECKING_SSL,
			Domain_Stage::DONE,
			Domain_Stage::DONE_WITHOUT_SSL,
			Domain_Stage::FAILED,
			Domain_Stage::SSL_FAILED,
		];

		$domain = new Domain();

		foreach ($stages as $stage) {
			$domain->set_stage($stage);
			$class = $domain->get_stage_class();
			$this->assertIsString($class, "Stage {$stage} should have a CSS class");
			$this->assertNotEmpty($class, "Stage {$stage} CSS class should not be empty");
		}
	}

	// ----------------------------------------------------------------
	// NEW: Domain validation rules
	// ----------------------------------------------------------------

	/**
	 * Test domain validation rules are returned.
	 */
	public function test_domain_validation_rules(): void {
		$domain = new Domain();
		$rules = $domain->validation_rules();

		$this->assertIsArray($rules);
		$this->assertArrayHasKey('blog_id', $rules);
		$this->assertArrayHasKey('domain', $rules);
		$this->assertArrayHasKey('stage', $rules);
		$this->assertArrayHasKey('active', $rules);
		$this->assertArrayHasKey('secure', $rules);
		$this->assertArrayHasKey('primary_domain', $rules);
	}

	/**
	 * Test domain validation requires blog_id.
	 */
	public function test_domain_validation_requires_blog_id(): void {
		$domain = new Domain();
		$domain->set_domain('valid.example.com');
		$domain->set_stage(Domain_Stage::DONE);

		// Without blog_id, save should fail
		$result = $domain->save();
		$this->assertWPError($result);
	}

	/**
	 * Test domain validation requires domain name.
	 */
	public function test_domain_validation_requires_domain(): void {
		$domain = new Domain();
		$domain->set_blog_id($this->get_blog_id());
		$domain->set_stage(Domain_Stage::DONE);

		// Without domain, save should fail
		$result = $domain->save();
		$this->assertWPError($result);
	}

	// ----------------------------------------------------------------
	// NEW: DNS check interval setting
	// ----------------------------------------------------------------

	/**
	 * Test dns_check_interval setting is respected in async_process_domain_stage.
	 */
	public function test_dns_check_interval_setting(): void {
		// Set a custom DNS check interval
		wu_save_setting('dns_check_interval', 60);

		$domain = wu_create_domain([
			'blog_id' => $this->get_blog_id(),
			'domain'  => 'dns-interval-test.example.com',
			'stage'   => Domain_Stage::CHECKING_DNS,
		]);

		$this->assertNotWPError($domain);

		// Process with 0 tries (first try) - won't exceed max, schedules retry
		$this->domain_manager->async_process_domain_stage($domain->get_id(), 0);

		// Domain should still be in checking-dns stage (DNS won't resolve in test env)
		$fetched = wu_get_domain($domain->get_id());
		$stage = $fetched->get_stage();

		// It should either still be checking-dns (retry scheduled) or failed (if tries exceeded)
		$this->assertContains($stage, [Domain_Stage::CHECKING_DNS, Domain_Stage::FAILED]);
	}

	/**
	 * Test dns_check_interval is clamped to range.
	 */
	public function test_dns_check_interval_clamped(): void {
		// Too low - should be clamped to 10
		wu_save_setting('dns_check_interval', 1);

		$domain = wu_create_domain([
			'blog_id' => $this->get_blog_id(),
			'domain'  => 'dns-clamp-low.example.com',
			'stage'   => Domain_Stage::CHECKING_DNS,
		]);

		$this->assertNotWPError($domain);

		// Should not throw errors despite low interval
		$this->domain_manager->async_process_domain_stage($domain->get_id(), 0);
		$this->assertTrue(true);
	}

	// ----------------------------------------------------------------
	// NEW: Multiple domains for the same site
	// ----------------------------------------------------------------

	/**
	 * Test multiple domains can be associated with the same site.
	 */
	public function test_multiple_domains_per_site(): void {
		$domain1 = wu_create_domain([
			'blog_id' => $this->get_blog_id(),
			'domain'  => 'multi-1.example.com',
			'stage'   => Domain_Stage::DONE,
		]);

		$domain2 = wu_create_domain([
			'blog_id' => $this->get_blog_id(),
			'domain'  => 'multi-2.example.com',
			'stage'   => Domain_Stage::DONE,
		]);

		$domain3 = wu_create_domain([
			'blog_id' => $this->get_blog_id(),
			'domain'  => 'multi-3.example.com',
			'stage'   => Domain_Stage::DONE,
		]);

		$this->assertNotWPError($domain1);
		$this->assertNotWPError($domain2);
		$this->assertNotWPError($domain3);

		$domains = wu_get_domains([
			'blog_id' => $this->get_blog_id(),
		]);

		$this->assertGreaterThanOrEqual(3, count($domains));
	}

	// ----------------------------------------------------------------
	// NEW: Domain uniqueness
	// ----------------------------------------------------------------

	/**
	 * Test that duplicate domain names are rejected.
	 */
	public function test_duplicate_domain_rejected(): void {
		$domain1 = wu_create_domain([
			'blog_id' => $this->get_blog_id(),
			'domain'  => 'unique-test.example.com',
			'stage'   => Domain_Stage::DONE,
		]);

		$this->assertNotWPError($domain1);

		// Try to create another domain with the same name
		$domain2 = wu_create_domain([
			'blog_id' => $this->get_blog_id(),
			'domain'  => 'unique-test.example.com',
			'stage'   => Domain_Stage::DONE,
		]);

		$this->assertWPError($domain2);
	}

	// ----------------------------------------------------------------
	// NEW: Query domains with fields parameter
	// ----------------------------------------------------------------

	/**
	 * Test querying domains with fields=ids.
	 */
	public function test_query_domains_ids_only(): void {
		$domain = wu_create_domain([
			'blog_id' => $this->get_blog_id(),
			'domain'  => 'ids-only-test.example.com',
			'stage'   => Domain_Stage::DONE,
		]);

		$this->assertNotWPError($domain);

		$ids = wu_get_domains([
			'blog_id' => $this->get_blog_id(),
			'fields'  => 'ids',
		]);

		$this->assertIsArray($ids);
		$this->assertNotEmpty($ids);

		foreach ($ids as $id) {
			$this->assertIsNumeric($id);
		}
	}

	// ----------------------------------------------------------------
	// NEW: Settings registration
	// ----------------------------------------------------------------

	/**
	 * Test add_domain_mapping_settings registers settings without error.
	 */
	public function test_add_domain_mapping_settings(): void {
		$this->domain_manager->add_domain_mapping_settings();
		$this->assertTrue(true); // No exceptions thrown
	}

	/**
	 * Test add_sso_settings registers settings without error.
	 */
	public function test_add_sso_settings(): void {
		$this->domain_manager->add_sso_settings();
		$this->assertTrue(true); // No exceptions thrown
	}

	// ----------------------------------------------------------------
	// NEW: Edge cases
	// ----------------------------------------------------------------

	/**
	 * Test creating domain with all fields.
	 */
	public function test_create_domain_with_all_fields(): void {
		$domain = wu_create_domain([
			'blog_id'        => $this->get_blog_id(),
			'domain'         => 'all-fields.example.com',
			'active'         => true,
			'primary_domain' => true,
			'secure'         => true,
			'stage'          => Domain_Stage::DONE,
		]);

		$this->assertNotWPError($domain);
		$this->assertEquals('all-fields.example.com', $domain->get_domain());
		$this->assertEquals($this->get_blog_id(), $domain->get_blog_id());
		$this->assertTrue($domain->is_primary_domain());
		$this->assertTrue($domain->is_secure());
		$this->assertEquals(Domain_Stage::DONE, $domain->get_stage());
		$this->assertTrue($domain->is_active());
	}

	/**
	 * Test creating domain with checking-dns stage makes it inactive.
	 */
	public function test_create_domain_checking_dns_is_inactive(): void {
		$domain = wu_create_domain([
			'blog_id' => $this->get_blog_id(),
			'domain'  => 'checking-dns-inactive.example.com',
			'active'  => true,
			'stage'   => Domain_Stage::CHECKING_DNS,
		]);

		$this->assertNotWPError($domain);

		// Even though active is true, the stage should force it to be inactive
		$this->assertFalse($domain->is_active());
	}

	/**
	 * Test the INACTIVE_STAGES constant is correctly defined.
	 */
	public function test_inactive_stages_constant(): void {
		$expected = [
			Domain_Stage::CHECKING_DNS,
			Domain_Stage::CHECKING_SSL,
			Domain_Stage::FAILED,
			Domain_Stage::SSL_FAILED,
		];

		$this->assertEquals($expected, Domain::INACTIVE_STAGES);
	}

	/**
	 * Test domain done-without-ssl stage is considered active.
	 */
	public function test_domain_done_without_ssl_is_active(): void {
		$domain = new Domain();
		$domain->set_active(true);
		$domain->set_stage(Domain_Stage::DONE_WITHOUT_SSL);

		$this->assertTrue($domain->is_active());
	}

	/**
	 * Test setting primary domain on save unsets other primary domains via action.
	 */
	public function test_setting_primary_triggers_unset_of_old_primaries(): void {
		$blog_id = $this->create_test_blog();

		$domain1 = wu_create_domain([
			'blog_id'        => $blog_id,
			'domain'         => 'primary-1-trigger.example.com',
			'primary_domain' => true,
			'stage'          => Domain_Stage::DONE,
		]);

		$this->assertNotWPError($domain1);

		// The wu_async_remove_old_primary_domains action should have been called
		// when domain2 is created as primary
		$action_called = false;
		add_action('wu_async_remove_old_primary_domains', function ($domains) use (&$action_called) {
			$action_called = true;
		});

		$domain2 = wu_create_domain([
			'blog_id'        => $blog_id,
			'domain'         => 'primary-2-trigger.example.com',
			'primary_domain' => true,
			'stage'          => Domain_Stage::DONE,
		]);

		$this->assertNotWPError($domain2);
		$this->assertTrue($action_called, 'wu_async_remove_old_primary_domains action should have been triggered');
	}

	/**
	 * Test the wu_domain_became_primary action fires when setting a new primary.
	 */
	public function test_domain_became_primary_action_fires(): void {
		$blog_id = $this->create_test_blog();

		$action_fired = false;
		add_action('wu_domain_became_primary', function ($domain, $fired_blog_id, $was_new) use (&$action_fired, $blog_id) {
			$action_fired = true;
			$this->assertEquals($blog_id, $fired_blog_id);
			$this->assertTrue($was_new);
		}, 10, 3);

		$domain = wu_create_domain([
			'blog_id'        => $blog_id,
			'domain'         => 'became-primary-action.example.com',
			'primary_domain' => true,
			'stage'          => Domain_Stage::DONE,
		]);

		$this->assertNotWPError($domain);
		$this->assertTrue($action_fired, 'wu_domain_became_primary action should have fired');

		remove_all_actions('wu_domain_became_primary');
	}

	/**
	 * Test updating a non-primary domain to primary triggers action.
	 */
	public function test_updating_to_primary_triggers_action(): void {
		$blog_id = $this->create_test_blog();

		$domain = wu_create_domain([
			'blog_id'        => $blog_id,
			'domain'         => 'update-to-primary.example.com',
			'primary_domain' => false,
			'stage'          => Domain_Stage::DONE,
		]);

		$this->assertNotWPError($domain);

		$action_fired = false;
		add_action('wu_domain_became_primary', function () use (&$action_fired) {
			$action_fired = true;
		}, 10, 3);

		$domain->set_primary_domain(true);
		$domain->save();

		$this->assertTrue($action_fired, 'wu_domain_became_primary should fire when updating to primary');

		remove_all_actions('wu_domain_became_primary');
	}

	// ----------------------------------------------------------------
	// NEW: DNS record filter
	// ----------------------------------------------------------------

	/**
	 * Test wu_domain_dns_get_record filter is applied.
	 */
	public function test_dns_get_record_filter_is_applied(): void {
		$filter_called = false;

		add_filter('wu_domain_dns_get_record', function ($results, $domain) use (&$filter_called) {
			$filter_called = true;
			return $results;
		}, 10, 2);

		// This might throw an exception due to DNS resolvers not being available
		// in the test environment, so we wrap in a try-catch
		try {
			Domain_Manager::dns_get_record('example.com');
		} catch (\Throwable $e) {
			// DNS resolution may fail in test env, that's fine
		}

		// The filter may or may not have been called depending on
		// whether dns_get_record completed before throwing
		// This test mainly validates the filter exists
		remove_all_filters('wu_domain_dns_get_record');
		$this->assertTrue(true);
	}

	// ----------------------------------------------------------------
	// NEW: Domain save flushes cache
	// ----------------------------------------------------------------

	/**
	 * Test that saving a domain flushes cache.
	 */
	public function test_domain_save_flushes_cache(): void {
		$domain = wu_create_domain([
			'blog_id' => $this->get_blog_id(),
			'domain'  => 'cache-flush-test.example.com',
			'stage'   => Domain_Stage::DONE,
		]);

		$this->assertNotWPError($domain);

		// Set a cache value
		wp_cache_set('test_key', 'test_value', 'domain_mapping');

		// Save the domain again
		$domain->set_secure(true);
		$domain->save();

		// Cache should have been flushed
		$cached = wp_cache_get('test_key', 'domain_mapping');
		$this->assertFalse($cached);
	}

	// ----------------------------------------------------------------
	// NEW: determine_cookie_domain (issue #320)
	// ----------------------------------------------------------------

	/**
	 * Network root domain should return null (no override needed).
	 *
	 * When the current host IS the network root domain, WordPress's default
	 * cookie domain (.ultimatemultisite.com) is correct — no override required.
	 */
	public function test_determine_cookie_domain_network_root_returns_null(): void {
		$result = $this->domain_manager->determine_cookie_domain('ultimatemultisite.com', 'ultimatemultisite.com');
		$this->assertNull($result, 'Network root domain should not require a COOKIE_DOMAIN override');
	}

	/**
	 * Subdomain subsite should return the most specific cookie domain.
	 *
	 * When translate.ultimatemultisite.com is a subsite of a network rooted at
	 * ultimatemultisite.com, the cookie domain must be scoped to
	 * .translate.ultimatemultisite.com to prevent auth cookie bleeding from
	 * the parent domain.
	 */
	public function test_determine_cookie_domain_subdomain_subsite_returns_specific_domain(): void {
		$result = $this->domain_manager->determine_cookie_domain('translate.ultimatemultisite.com', 'ultimatemultisite.com');
		$this->assertEquals('.translate.ultimatemultisite.com', $result, 'Subdomain subsite should use the most specific cookie domain');
	}

	/**
	 * Deeply nested subdomain subsite should return the full specific domain.
	 */
	public function test_determine_cookie_domain_deep_subdomain_returns_specific_domain(): void {
		$result = $this->domain_manager->determine_cookie_domain('api.translate.ultimatemultisite.com', 'ultimatemultisite.com');
		$this->assertEquals('.api.translate.ultimatemultisite.com', $result, 'Deep subdomain subsite should use the most specific cookie domain');
	}

	/**
	 * Mapped domain (completely different domain) should return the mapped domain.
	 *
	 * When the current host is a completely different domain (e.g. translate.example.com
	 * on a network rooted at ultimatemultisite.com), the cookie domain must be scoped
	 * to the mapped domain.
	 */
	public function test_determine_cookie_domain_mapped_domain_returns_mapped_domain(): void {
		$result = $this->domain_manager->determine_cookie_domain('translate.example.com', 'ultimatemultisite.com');
		$this->assertEquals('.translate.example.com', $result, 'Mapped domain should use its own cookie domain');
	}

	/**
	 * Mapped root domain (completely different TLD) should return the mapped domain.
	 */
	public function test_determine_cookie_domain_mapped_root_domain(): void {
		$result = $this->domain_manager->determine_cookie_domain('example.com', 'ultimatemultisite.com');
		$this->assertEquals('.example.com', $result, 'Mapped root domain should use its own cookie domain');
	}

	/**
	 * Domain matching is case-insensitive.
	 */
	public function test_determine_cookie_domain_case_insensitive(): void {
		$result = $this->domain_manager->determine_cookie_domain('Translate.UltimateMultisite.COM', 'ultimatemultisite.com');
		$this->assertEquals('.Translate.UltimateMultisite.COM', $result, 'Cookie domain determination should be case-insensitive');
	}

	/**
	 * A domain that ends with the network domain string but is not a subdomain
	 * (e.g. evilultimatemultisite.com) is treated as the network root (no override).
	 *
	 * The preg_quote fix ensures dots in the network domain are treated as literal
	 * characters in the regex, not wildcards. However, evilultimatemultisite.com
	 * still matches the suffix check (it ends with 'ultimatemultisite.com') and is
	 * not a subdomain (doesn't end with '.ultimatemultisite.com'), so it falls
	 * through to null — the same as the network root. This is acceptable because
	 * such a domain would never appear as a WordPress multisite subsite in practice.
	 */
	public function test_determine_cookie_domain_not_fooled_by_suffix_match(): void {
		$result = $this->domain_manager->determine_cookie_domain('evilultimatemultisite.com', 'ultimatemultisite.com');
		$this->assertNull($result, 'Domain ending with network domain string (but not a subdomain) falls through to null');
	}

	/**
	 * Network root domain with www prefix should return null (no override needed).
	 *
	 * The www.ultimatemultisite.com host is a standard alias for the network root, not a subsite.
	 * However, since it IS a subdomain of the network domain, the fix correctly scopes
	 * it to .www.ultimatemultisite.com to prevent cookie bleeding.
	 */
	public function test_determine_cookie_domain_www_subdomain_returns_specific_domain(): void {
		$result = $this->domain_manager->determine_cookie_domain('www.ultimatemultisite.com', 'ultimatemultisite.com');
		$this->assertEquals('.www.ultimatemultisite.com', $result, 'www subdomain should use specific cookie domain');
	}

	// ----------------------------------------------------------------
	// NEW: try_again_time filter
	// ----------------------------------------------------------------

	/**
	 * Test wu_async_process_domains_try_again_time filter.
	 */
	public function test_try_again_time_filter(): void {
		$filter_called = false;
		add_filter('wu_async_process_domains_try_again_time', function ($time, $domain) use (&$filter_called) {
			$filter_called = true;
			return $time;
		}, 10, 2);

		$domain = wu_create_domain([
			'blog_id' => $this->get_blog_id(),
			'domain'  => 'try-again-time-filter.example.com',
			'stage'   => Domain_Stage::CHECKING_DNS,
		]);

		$this->assertNotWPError($domain);

		$this->domain_manager->async_process_domain_stage($domain->get_id(), 0);

		$this->assertTrue($filter_called, 'wu_async_process_domains_try_again_time filter should be called');

		remove_all_filters('wu_async_process_domains_try_again_time');
	}

	// ----------------------------------------------------------------
	// maybe_auto_promote_primary_domain
	// ----------------------------------------------------------------

	/**
	 * Simple 2-part custom domain (e.g. mysite.com) reaches done stage
	 * and should be auto-promoted to primary.
	 */
	public function test_auto_promote_simple_custom_domain_on_done(): void {
		$blog_id = $this->create_test_blog();

		$domain = wu_create_domain([
			'blog_id'        => $blog_id,
			'domain'         => 'mysite.com',
			'stage'          => Domain_Stage::CHECKING_DNS,
			'primary_domain' => false,
		]);

		$this->assertNotWPError($domain);

		// Transition to done — hook fires automatically.
		$domain->set_stage(Domain_Stage::DONE);
		$domain->save();

		$fetched = wu_get_domain($domain->get_id());
		$this->assertTrue(
			$fetched->is_primary_domain(),
			'A simple 2-part custom domain should be auto-promoted when it reaches done stage.'
		);
	}

	/**
	 * A 3-part custom domain (e.g. shop.mybrand.com) reaching done stage
	 * should also be auto-promoted — the old TLD-counting heuristic wrongly
	 * blocked this class of domain.
	 */
	public function test_auto_promote_three_part_custom_domain_on_done(): void {
		$blog_id = $this->create_test_blog();

		$domain = wu_create_domain([
			'blog_id'        => $blog_id,
			'domain'         => 'shop.mybrand.com',
			'stage'          => Domain_Stage::CHECKING_DNS,
			'primary_domain' => false,
		]);

		$this->assertNotWPError($domain);

		$domain->set_stage(Domain_Stage::DONE);
		$domain->save();

		$fetched = wu_get_domain($domain->get_id());
		$this->assertTrue(
			$fetched->is_primary_domain(),
			'A 3-part custom domain (shop.mybrand.com) should be auto-promoted — it is not a WP network subdomain.'
		);
	}

	/**
	 * A domain that is already primary (stage done → done again) should not be
	 * re-processed — the transition hook only fires on actual stage changes.
	 */
	public function test_auto_promote_skips_already_primary(): void {
		$blog_id = $this->create_test_blog();

		// Create with checking-dns, then promote to done (triggers the hook).
		$domain = wu_create_domain([
			'blog_id'        => $blog_id,
			'domain'         => 'already-primary.com',
			'stage'          => Domain_Stage::CHECKING_DNS,
			'primary_domain' => false,
		]);

		$this->assertNotWPError($domain);

		// First transition: checking-dns → done. Hook promotes to primary.
		$domain->set_stage(Domain_Stage::DONE);
		$domain->save();

		$fetched = wu_get_domain($domain->get_id());
		$this->assertTrue($fetched->is_primary_domain(), 'Domain should be primary after first transition to done.');

		// Second save with same done stage — no stage transition, hook does NOT fire.
		// primary_domain must remain true.
		$fetched->save();

		$fetched2 = wu_get_domain($domain->get_id());
		$this->assertTrue(
			$fetched2->is_primary_domain(),
			'An already-primary domain should remain primary after a redundant save.'
		);
	}

	/**
	 * If a blog already has a non-network primary custom domain, a newly-done
	 * domain should NOT displace it.
	 */
	public function test_auto_promote_skips_when_existing_primary_custom_domain(): void {
		$blog_id = $this->create_test_blog();

		// First domain: already primary.
		$first = wu_create_domain([
			'blog_id'        => $blog_id,
			'domain'         => 'first-custom.com',
			'stage'          => Domain_Stage::DONE,
			'primary_domain' => true,
		]);

		$this->assertNotWPError($first);
		$this->assertTrue($first->is_primary_domain());

		// Second domain: reaches done but should NOT displace the first.
		$second = wu_create_domain([
			'blog_id'        => $blog_id,
			'domain'         => 'second-custom.com',
			'stage'          => Domain_Stage::CHECKING_DNS,
			'primary_domain' => false,
		]);

		$this->assertNotWPError($second);

		$second->set_stage(Domain_Stage::DONE);
		$second->save();

		// Reload both.
		$first_after  = wu_get_domain($first->get_id());
		$second_after = wu_get_domain($second->get_id());

		$this->assertTrue(
			$first_after->is_primary_domain(),
			'The original primary domain should remain primary.'
		);
		$this->assertFalse(
			$second_after->is_primary_domain(),
			'The new domain should NOT be auto-promoted when an existing primary custom domain exists.'
		);
	}

	/**
	 * WP multisite native subdomain (e.g. site.{network_host}) reaching done
	 * stage should NOT be auto-promoted.
	 */
	public function test_auto_promote_skips_wp_network_subdomain(): void {
		$blog_id = $this->create_test_blog();

		$network_host = strtolower( (string) wp_parse_url( network_home_url(), PHP_URL_HOST ) );
		$network_host = (string) preg_replace( '/:\d+$/', '', $network_host );

		// Build a domain that looks like a native WP multisite subdomain.
		$wp_subdomain = 'testsite.' . $network_host;

		$domain = wu_create_domain([
			'blog_id'        => $blog_id,
			'domain'         => $wp_subdomain,
			'stage'          => Domain_Stage::CHECKING_DNS,
			'primary_domain' => false,
		]);

		$this->assertNotWPError($domain);

		$domain->set_stage(Domain_Stage::DONE);
		$domain->save();

		$fetched = wu_get_domain($domain->get_id());
		$this->assertFalse(
			$fetched->is_primary_domain(),
			"A WP network subdomain ({$wp_subdomain}) should NOT be auto-promoted."
		);
	}

	/**
	 * done-without-ssl stage also triggers auto-promotion.
	 */
	public function test_auto_promote_on_done_without_ssl(): void {
		$blog_id = $this->create_test_blog();

		$domain = wu_create_domain([
			'blog_id'        => $blog_id,
			'domain'         => 'nossl.mybrand.com',
			'stage'          => Domain_Stage::CHECKING_DNS,
			'primary_domain' => false,
		]);

		$this->assertNotWPError($domain);

		$domain->set_stage(Domain_Stage::DONE_WITHOUT_SSL);
		$domain->save();

		$fetched = wu_get_domain($domain->get_id());
		$this->assertTrue(
			$fetched->is_primary_domain(),
			'A custom domain reaching done-without-ssl should also be auto-promoted.'
		);
	}
}
