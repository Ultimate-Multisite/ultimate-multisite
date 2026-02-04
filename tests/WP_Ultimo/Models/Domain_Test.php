<?php

namespace WP_Ultimo\Models;

use WP_UnitTestCase;
use WP_Ultimo\Database\Domains\Domain_Stage;

/**
 * Test class for Domain model functionality.
 *
 * Tests SSL certificate validation for custom domains including
 * valid certificates, invalid certificates, and empty domain handling.
 * Also tests getters/setters, business logic, stage methods, and CRUD.
 */
class Domain_Test extends WP_UnitTestCase {

	/**
	 * Test blog ID used across tests.
	 *
	 * @var int
	 */
	protected $blog_id;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {

		parent::setUp();

		$this->blog_id = self::factory()->blog->create();
	}

	/**
	 * Test that has_valid_ssl_certificate returns true for valid SSL certificates.
	 */
	public function test_has_valid_ssl_certificate_with_valid_certificate(): void {
		// Mocking a domain with a valid SSL certificate.
		$domain = new Domain();
		$domain->set_domain('dogs.4thelols.uk');

		// Assert that it returns true for a valid SSL certificate.
		$this->assertTrue($domain->has_valid_ssl_certificate());
	}

	/**
	 * Test that has_valid_ssl_certificate returns false when the SSL certificate is invalid.
	 */
	public function test_has_valid_ssl_certificate_with_invalid_certificate(): void {
		// Mocking a domain with an invalid SSL certificate.
		$domain = new Domain();
		$domain->set_domain('eeeeeeeeeeeeeeeeauauexample.com');

		// Assert that it returns false for an invalid SSL certificate.
		$this->assertFalse($domain->has_valid_ssl_certificate());
	}

	/**
	 * Test that has_valid_ssl_certificate handles empty domain.
	 */
	public function test_has_valid_ssl_certificate_with_empty_domain(): void {
		// Mocking a domain with an empty value.
		$domain = new Domain();
		$domain->set_domain('');

		// Assert that it returns false for an empty domain.
		$this->assertFalse($domain->has_valid_ssl_certificate());
	}

	// ----------------------------------------------------------------
	// Getter / Setter tests
	// ----------------------------------------------------------------

	/**
	 * Test get_domain and set_domain.
	 */
	public function test_get_and_set_domain(): void {

		$domain = new Domain();
		$domain->set_domain('example.com');

		$this->assertEquals('example.com', $domain->get_domain());
	}

	/**
	 * Test set_domain converts value to lowercase.
	 */
	public function test_set_domain_lowercases_value(): void {

		$domain = new Domain();
		$domain->set_domain('MyDomain.COM');

		$this->assertEquals('mydomain.com', $domain->get_domain());
	}

	/**
	 * Test get_blog_id and set_blog_id.
	 */
	public function test_get_and_set_blog_id(): void {

		$domain = new Domain();
		$domain->set_blog_id(42);

		$this->assertSame(42, $domain->get_blog_id());
	}

	/**
	 * Test get_blog_id returns integer.
	 */
	public function test_get_blog_id_returns_int(): void {

		$domain = new Domain();
		$domain->set_blog_id('7');

		$this->assertSame(7, $domain->get_blog_id());
	}

	/**
	 * Test get_site_id is an alias for get_blog_id.
	 */
	public function test_get_site_id_aliases_get_blog_id(): void {

		$domain = new Domain();
		$domain->set_blog_id(55);

		$this->assertSame($domain->get_blog_id(), $domain->get_site_id());
	}

	/**
	 * Test is_active and set_active when true.
	 */
	public function test_set_active_true(): void {

		$domain = new Domain();
		$domain->set_stage(Domain_Stage::DONE);
		$domain->set_active(true);

		$this->assertTrue($domain->is_active());
	}

	/**
	 * Test is_active and set_active when false.
	 */
	public function test_set_active_false(): void {

		$domain = new Domain();
		$domain->set_stage(Domain_Stage::DONE);
		$domain->set_active(false);

		$this->assertFalse($domain->is_active());
	}

	/**
	 * Test is_primary_domain and set_primary_domain true.
	 */
	public function test_set_primary_domain_true(): void {

		$domain = new Domain();
		$domain->set_primary_domain(true);

		$this->assertTrue($domain->is_primary_domain());
	}

	/**
	 * Test is_primary_domain defaults to false.
	 */
	public function test_primary_domain_defaults_to_false(): void {

		$domain = new Domain();

		$this->assertFalse($domain->is_primary_domain());
	}

	/**
	 * Test set_primary_domain false.
	 */
	public function test_set_primary_domain_false(): void {

		$domain = new Domain();
		$domain->set_primary_domain(true);
		$domain->set_primary_domain(false);

		$this->assertFalse($domain->is_primary_domain());
	}

	/**
	 * Test is_secure and set_secure true.
	 */
	public function test_set_secure_true(): void {

		$domain = new Domain();
		$domain->set_secure(true);

		$this->assertTrue($domain->is_secure());
	}

	/**
	 * Test is_secure defaults to false.
	 */
	public function test_secure_defaults_to_false(): void {

		$domain = new Domain();

		$this->assertFalse($domain->is_secure());
	}

	/**
	 * Test set_secure false.
	 */
	public function test_set_secure_false(): void {

		$domain = new Domain();
		$domain->set_secure(true);
		$domain->set_secure(false);

		$this->assertFalse($domain->is_secure());
	}

	/**
	 * Test get_stage and set_stage.
	 */
	public function test_get_and_set_stage(): void {

		$domain = new Domain();
		$domain->set_stage(Domain_Stage::DONE);

		$this->assertEquals(Domain_Stage::DONE, $domain->get_stage());
	}

	/**
	 * Test stage default value is checking-dns.
	 */
	public function test_stage_defaults_to_checking_dns(): void {

		$domain = new Domain();

		$this->assertEquals(Domain_Stage::CHECKING_DNS, $domain->get_stage());
	}

	/**
	 * Test get_date_created and set_date_created.
	 */
	public function test_get_and_set_date_created(): void {

		$domain = new Domain();
		$date   = '2025-06-15 10:30:00';
		$domain->set_date_created($date);

		$this->assertEquals($date, $domain->get_date_created());
	}

	// ----------------------------------------------------------------
	// Business logic tests
	// ----------------------------------------------------------------

	/**
	 * Test is_active returns false when stage is checking-dns (inactive stage).
	 */
	public function test_is_active_false_when_stage_checking_dns(): void {

		$domain = new Domain();
		$domain->set_active(true);
		$domain->set_stage(Domain_Stage::CHECKING_DNS);

		$this->assertFalse($domain->is_active());
	}

	/**
	 * Test is_active returns false when stage is checking-ssl-cert (inactive stage).
	 */
	public function test_is_active_false_when_stage_checking_ssl(): void {

		$domain = new Domain();
		$domain->set_active(true);
		$domain->set_stage(Domain_Stage::CHECKING_SSL);

		$this->assertFalse($domain->is_active());
	}

	/**
	 * Test is_active returns false when stage is failed (inactive stage).
	 */
	public function test_is_active_false_when_stage_failed(): void {

		$domain = new Domain();
		$domain->set_active(true);
		$domain->set_stage(Domain_Stage::FAILED);

		$this->assertFalse($domain->is_active());
	}

	/**
	 * Test is_active returns false when stage is ssl-failed (inactive stage).
	 */
	public function test_is_active_false_when_stage_ssl_failed(): void {

		$domain = new Domain();
		$domain->set_active(true);
		$domain->set_stage(Domain_Stage::SSL_FAILED);

		$this->assertFalse($domain->is_active());
	}

	/**
	 * Test is_active returns true when stage is done and active flag is true.
	 */
	public function test_is_active_true_when_stage_done(): void {

		$domain = new Domain();
		$domain->set_active(true);
		$domain->set_stage(Domain_Stage::DONE);

		$this->assertTrue($domain->is_active());
	}

	/**
	 * Test is_active returns true when stage is done-without-ssl.
	 */
	public function test_is_active_true_when_stage_done_without_ssl(): void {

		$domain = new Domain();
		$domain->set_active(true);
		$domain->set_stage(Domain_Stage::DONE_WITHOUT_SSL);

		$this->assertTrue($domain->is_active());
	}

	/**
	 * Test is_active returns false when stage is done but active flag is false.
	 */
	public function test_is_active_false_when_done_but_flag_off(): void {

		$domain = new Domain();
		$domain->set_active(false);
		$domain->set_stage(Domain_Stage::DONE);

		$this->assertFalse($domain->is_active());
	}

	// ----------------------------------------------------------------
	// has_inactive_stage tests
	// ----------------------------------------------------------------

	/**
	 * Test has_inactive_stage returns true for all inactive stages.
	 */
	public function test_has_inactive_stage_returns_true_for_inactive_stages(): void {

		$inactive_stages = [
			Domain_Stage::CHECKING_DNS,
			Domain_Stage::CHECKING_SSL,
			Domain_Stage::FAILED,
			Domain_Stage::SSL_FAILED,
		];

		foreach ($inactive_stages as $stage) {
			$domain = new Domain();
			$domain->set_stage($stage);

			$this->assertTrue(
				$domain->has_inactive_stage(),
				"Stage '{$stage}' should be classified as inactive."
			);
		}
	}

	/**
	 * Test has_inactive_stage returns false for active stages.
	 */
	public function test_has_inactive_stage_returns_false_for_active_stages(): void {

		$active_stages = [
			Domain_Stage::DONE,
			Domain_Stage::DONE_WITHOUT_SSL,
		];

		foreach ($active_stages as $stage) {
			$domain = new Domain();
			$domain->set_stage($stage);

			$this->assertFalse(
				$domain->has_inactive_stage(),
				"Stage '{$stage}' should not be classified as inactive."
			);
		}
	}

	// ----------------------------------------------------------------
	// get_url tests
	// ----------------------------------------------------------------

	/**
	 * Test get_url returns http scheme when not secure.
	 */
	public function test_get_url_http_when_not_secure(): void {

		$domain = new Domain();
		$domain->set_domain('example.com');
		$domain->set_secure(false);

		$this->assertEquals('http://example.com/', $domain->get_url());
	}

	/**
	 * Test get_url returns https scheme when secure.
	 */
	public function test_get_url_https_when_secure(): void {

		$domain = new Domain();
		$domain->set_domain('example.com');
		$domain->set_secure(true);

		$this->assertEquals('https://example.com/', $domain->get_url());
	}

	/**
	 * Test get_url appends path.
	 */
	public function test_get_url_with_path(): void {

		$domain = new Domain();
		$domain->set_domain('example.com');
		$domain->set_secure(false);

		$this->assertEquals('http://example.com/wp-admin', $domain->get_url('wp-admin'));
	}

	/**
	 * Test get_url with secure and path.
	 */
	public function test_get_url_with_secure_and_path(): void {

		$domain = new Domain();
		$domain->set_domain('secure.example.com');
		$domain->set_secure(true);

		$this->assertEquals('https://secure.example.com/some/path', $domain->get_url('some/path'));
	}

	/**
	 * Test get_url with empty path produces trailing slash.
	 */
	public function test_get_url_empty_path_has_trailing_slash(): void {

		$domain = new Domain();
		$domain->set_domain('example.com');
		$domain->set_secure(false);

		$url = $domain->get_url('');
		$this->assertEquals('http://example.com/', $url);
	}

	// ----------------------------------------------------------------
	// Stage label and class tests
	// ----------------------------------------------------------------

	/**
	 * Test get_stage_label returns non-empty string for all valid stages.
	 */
	public function test_get_stage_label_for_all_stages(): void {

		$all_stages = [
			Domain_Stage::CHECKING_DNS,
			Domain_Stage::CHECKING_SSL,
			Domain_Stage::DONE,
			Domain_Stage::DONE_WITHOUT_SSL,
			Domain_Stage::FAILED,
			Domain_Stage::SSL_FAILED,
		];

		foreach ($all_stages as $stage) {
			$domain = new Domain();
			$domain->set_stage($stage);

			$label = $domain->get_stage_label();
			$this->assertNotEmpty(
				$label,
				"Stage '{$stage}' should have a non-empty label."
			);
			$this->assertIsString($label);
		}
	}

	/**
	 * Test get_stage_label returns expected specific labels.
	 */
	public function test_get_stage_label_specific_values(): void {

		$domain = new Domain();

		$domain->set_stage(Domain_Stage::DONE);
		$this->assertEquals('Ready', $domain->get_stage_label());

		$domain->set_stage(Domain_Stage::FAILED);
		$this->assertEquals('DNS Failed', $domain->get_stage_label());

		$domain->set_stage(Domain_Stage::CHECKING_DNS);
		$this->assertEquals('Checking DNS', $domain->get_stage_label());

		$domain->set_stage(Domain_Stage::CHECKING_SSL);
		$this->assertEquals('Checking SSL', $domain->get_stage_label());

		$domain->set_stage(Domain_Stage::SSL_FAILED);
		$this->assertEquals('SSL Failed', $domain->get_stage_label());

		$domain->set_stage(Domain_Stage::DONE_WITHOUT_SSL);
		$this->assertEquals('Ready (without SSL)', $domain->get_stage_label());
	}

	/**
	 * Test get_stage_class returns non-empty string for all valid stages.
	 */
	public function test_get_stage_class_for_all_stages(): void {

		$all_stages = [
			Domain_Stage::CHECKING_DNS,
			Domain_Stage::CHECKING_SSL,
			Domain_Stage::DONE,
			Domain_Stage::DONE_WITHOUT_SSL,
			Domain_Stage::FAILED,
			Domain_Stage::SSL_FAILED,
		];

		foreach ($all_stages as $stage) {
			$domain = new Domain();
			$domain->set_stage($stage);

			$class = $domain->get_stage_class();
			$this->assertNotEmpty(
				$class,
				"Stage '{$stage}' should have non-empty CSS classes."
			);
			$this->assertIsString($class);
		}
	}

	/**
	 * Test get_stage_class returns expected CSS classes for specific stages.
	 */
	public function test_get_stage_class_specific_values(): void {

		$domain = new Domain();

		$domain->set_stage(Domain_Stage::DONE);
		$this->assertStringContainsString('wu-bg-green', $domain->get_stage_class());

		$domain->set_stage(Domain_Stage::FAILED);
		$this->assertStringContainsString('wu-bg-red', $domain->get_stage_class());

		$domain->set_stage(Domain_Stage::CHECKING_DNS);
		$this->assertStringContainsString('wu-bg-blue', $domain->get_stage_class());
	}

	// ----------------------------------------------------------------
	// Validation rules tests
	// ----------------------------------------------------------------

	/**
	 * Test validation_rules returns expected keys.
	 */
	public function test_validation_rules_has_required_keys(): void {

		$domain = new Domain();
		$rules  = $domain->validation_rules();

		$this->assertArrayHasKey('blog_id', $rules);
		$this->assertArrayHasKey('domain', $rules);
		$this->assertArrayHasKey('stage', $rules);
		$this->assertArrayHasKey('active', $rules);
		$this->assertArrayHasKey('secure', $rules);
		$this->assertArrayHasKey('primary_domain', $rules);
	}

	/**
	 * Test validation_rules blog_id is required integer.
	 */
	public function test_validation_rules_blog_id_required_integer(): void {

		$domain = new Domain();
		$rules  = $domain->validation_rules();

		$this->assertStringContainsString('required', $rules['blog_id']);
		$this->assertStringContainsString('integer', $rules['blog_id']);
	}

	/**
	 * Test validation_rules domain is required and unique.
	 */
	public function test_validation_rules_domain_required_unique(): void {

		$domain = new Domain();
		$rules  = $domain->validation_rules();

		$this->assertStringContainsString('required', $rules['domain']);
		$this->assertStringContainsString('domain', $rules['domain']);
		$this->assertStringContainsString('unique', $rules['domain']);
	}

	/**
	 * Test validation_rules stage has in constraint with all stage values.
	 */
	public function test_validation_rules_stage_in_constraint(): void {

		$domain = new Domain();
		$rules  = $domain->validation_rules();

		$this->assertStringContainsString('required', $rules['stage']);
		$this->assertStringContainsString('in:', $rules['stage']);
		$this->assertStringContainsString('checking-dns', $rules['stage']);
		$this->assertStringContainsString('done', $rules['stage']);
		$this->assertStringContainsString('failed', $rules['stage']);
	}

	// ----------------------------------------------------------------
	// Domain_Stage constant tests
	// ----------------------------------------------------------------

	/**
	 * Test INACTIVE_STAGES constant contains exactly the expected stages.
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
	 * Test INACTIVE_STAGES does not contain done stages.
	 */
	public function test_inactive_stages_excludes_done(): void {

		$this->assertNotContains(Domain_Stage::DONE, Domain::INACTIVE_STAGES);
		$this->assertNotContains(Domain_Stage::DONE_WITHOUT_SSL, Domain::INACTIVE_STAGES);
	}

	// ----------------------------------------------------------------
	// Constructor / object initialization tests
	// ----------------------------------------------------------------

	/**
	 * Test Domain can be constructed with an associative array.
	 */
	public function test_construct_with_data_array(): void {

		$domain = new Domain(
			[
				'blog_id'        => 10,
				'domain'         => 'constructed.com',
				'active'         => true,
				'primary_domain' => true,
				'secure'         => true,
				'stage'          => Domain_Stage::DONE,
				'date_created'   => '2025-01-01 00:00:00',
			]
		);

		$this->assertSame(10, $domain->get_blog_id());
		$this->assertEquals('constructed.com', $domain->get_domain());
		$this->assertTrue($domain->is_active());
		$this->assertTrue($domain->is_primary_domain());
		$this->assertTrue($domain->is_secure());
		$this->assertEquals(Domain_Stage::DONE, $domain->get_stage());
		$this->assertEquals('2025-01-01 00:00:00', $domain->get_date_created());
	}

	/**
	 * Test Domain constructed with stdClass object.
	 */
	public function test_construct_with_stdclass(): void {

		$data                 = new \stdClass();
		$data->blog_id        = 20;
		$data->domain         = 'stdclass.example.com';
		$data->active         = false;
		$data->primary_domain = false;
		$data->secure         = true;
		$data->stage          = Domain_Stage::DONE;

		$domain = new Domain($data);

		$this->assertSame(20, $domain->get_blog_id());
		$this->assertEquals('stdclass.example.com', $domain->get_domain());
		$this->assertFalse($domain->is_active());
		$this->assertFalse($domain->is_primary_domain());
		$this->assertTrue($domain->is_secure());
	}

	// ----------------------------------------------------------------
	// CRUD via wu_create_domain
	// ----------------------------------------------------------------

	/**
	 * Test wu_create_domain creates a domain and persists it.
	 */
	public function test_wu_create_domain_success(): void {

		$domain = wu_create_domain(
			[
				'blog_id'        => $this->blog_id,
				'domain'         => 'created-test.example.com',
				'active'         => true,
				'primary_domain' => false,
				'secure'         => false,
				'stage'          => Domain_Stage::DONE,
			]
		);

		$this->assertNotWPError($domain);
		$this->assertInstanceOf(Domain::class, $domain);
		$this->assertGreaterThan(0, $domain->get_id());
		$this->assertEquals('created-test.example.com', $domain->get_domain());
		$this->assertSame($this->blog_id, $domain->get_blog_id());

		// Verify it can be fetched from the database.
		$fetched = wu_get_domain($domain->get_id());
		$this->assertInstanceOf(Domain::class, $fetched);
		$this->assertEquals($domain->get_id(), $fetched->get_id());

		// Clean up.
		$domain->delete();
	}

	/**
	 * Test wu_create_domain returns WP_Error when blog_id is missing.
	 */
	public function test_wu_create_domain_fails_without_blog_id(): void {

		$result = wu_create_domain(
			[
				'domain' => 'no-blog-id.example.com',
				'stage'  => Domain_Stage::DONE,
			]
		);

		$this->assertWPError($result);
	}

	/**
	 * Test wu_create_domain returns WP_Error when domain is missing.
	 */
	public function test_wu_create_domain_fails_without_domain(): void {

		$result = wu_create_domain(
			[
				'blog_id' => $this->blog_id,
				'stage'   => Domain_Stage::DONE,
			]
		);

		$this->assertWPError($result);
	}

	// ----------------------------------------------------------------
	// wu_get_domain_by_domain
	// ----------------------------------------------------------------

	/**
	 * Test wu_get_domain_by_domain returns the correct domain object.
	 */
	public function test_wu_get_domain_by_domain(): void {

		$domain = wu_create_domain(
			[
				'blog_id' => $this->blog_id,
				'domain'  => 'lookup-test.example.com',
				'stage'   => Domain_Stage::DONE,
			]
		);

		$this->assertNotWPError($domain);

		$fetched = wu_get_domain_by_domain('lookup-test.example.com');
		$this->assertInstanceOf(Domain::class, $fetched);
		$this->assertEquals($domain->get_id(), $fetched->get_id());

		// Clean up.
		$domain->delete();
	}

	/**
	 * Test wu_get_domain_by_domain returns false for non-existent domain.
	 */
	public function test_wu_get_domain_by_domain_returns_false_for_missing(): void {

		$result = wu_get_domain_by_domain('does-not-exist-at-all.example.com');

		$this->assertFalse($result);
	}

	// ----------------------------------------------------------------
	// wu_get_domains query
	// ----------------------------------------------------------------

	/**
	 * Test wu_get_domains returns created domains.
	 */
	public function test_wu_get_domains_returns_results(): void {

		$domain1 = wu_create_domain(
			[
				'blog_id' => $this->blog_id,
				'domain'  => 'query-one.example.com',
				'stage'   => Domain_Stage::DONE,
			]
		);

		$domain2 = wu_create_domain(
			[
				'blog_id' => $this->blog_id,
				'domain'  => 'query-two.example.com',
				'stage'   => Domain_Stage::DONE,
			]
		);

		$this->assertNotWPError($domain1);
		$this->assertNotWPError($domain2);

		$results = wu_get_domains(['blog_id' => $this->blog_id]);

		$this->assertIsArray($results);
		$this->assertGreaterThanOrEqual(2, count($results));

		// Clean up.
		$domain1->delete();
		$domain2->delete();
	}

	// ----------------------------------------------------------------
	// get_site tests
	// ----------------------------------------------------------------

	/**
	 * Test get_site returns a site object for a valid blog_id.
	 */
	public function test_get_site_returns_object_for_valid_blog(): void {

		$domain = new Domain();
		$domain->set_blog_id($this->blog_id);

		$site = $domain->get_site();

		$this->assertNotFalse($site);
	}

	/**
	 * Test get_site returns false for a non-existent blog_id.
	 */
	public function test_get_site_returns_false_for_invalid_blog(): void {

		$domain = new Domain();
		$domain->set_blog_id(999999);

		$site = $domain->get_site();

		$this->assertFalse($site);
	}

	// ----------------------------------------------------------------
	// get_path tests
	// ----------------------------------------------------------------

	/**
	 * Test get_path returns a string for a valid site.
	 */
	public function test_get_path_returns_string_for_valid_blog(): void {

		$domain = new Domain();
		$domain->set_blog_id($this->blog_id);

		$path = $domain->get_path();

		$this->assertIsString($path);
	}

	/**
	 * Test get_path returns null for a non-existent blog_id.
	 */
	public function test_get_path_returns_null_for_invalid_blog(): void {

		$domain = new Domain();
		$domain->set_blog_id(999999);

		$path = $domain->get_path();

		$this->assertNull($path);
	}

	/**
	 * Test get_path caches the result.
	 */
	public function test_get_path_is_cached(): void {

		$domain = new Domain();
		$domain->set_blog_id($this->blog_id);

		$path1 = $domain->get_path();
		$path2 = $domain->get_path();

		$this->assertSame($path1, $path2);
	}

	// ----------------------------------------------------------------
	// get_by_site static method tests
	// ----------------------------------------------------------------

	/**
	 * Test get_by_site returns domains for a site with mappings.
	 */
	public function test_get_by_site_with_existing_mappings(): void {

		$domain = wu_create_domain(
			[
				'blog_id' => $this->blog_id,
				'domain'  => 'by-site-test.example.com',
				'stage'   => Domain_Stage::DONE,
			]
		);

		$this->assertNotWPError($domain);

		// Flush cache so get_by_site hits the database.
		wp_cache_flush();

		$result = Domain::get_by_site($this->blog_id);

		$this->assertNotFalse($result);
		$this->assertNotWPError($result);

		// It should be an array or a single Domain instance.
		if (is_array($result)) {
			$this->assertNotEmpty($result);
			$this->assertInstanceOf(Domain::class, $result[0]);
		} else {
			$this->assertInstanceOf(Domain::class, $result);
		}

		// Clean up.
		$domain->delete();
	}

	/**
	 * Test get_by_site returns WP_Error for non-numeric site.
	 */
	public function test_get_by_site_returns_error_for_non_numeric(): void {

		$result = Domain::get_by_site('not-a-number');

		$this->assertWPError($result);
	}

	/**
	 * Test get_by_site returns false when no mappings exist.
	 */
	public function test_get_by_site_returns_false_for_no_mappings(): void {

		// Use a fresh blog_id that has no domain mappings.
		$empty_id = self::factory()->blog->create();

		wp_cache_flush();

		$result = Domain::get_by_site($empty_id);

		$this->assertFalse($result);
	}

	/**
	 * Test get_by_site accepts a site object with blog_id property.
	 */
	public function test_get_by_site_accepts_site_object(): void {

		$site_obj          = new \stdClass();
		$site_obj->blog_id = $this->blog_id;

		// Should not produce an error; might return false if no mappings.
		$result = Domain::get_by_site($site_obj);

		$this->assertTrue($result === false || is_array($result) || $result instanceof Domain);
	}

	// ----------------------------------------------------------------
	// Save and delete integration tests
	// ----------------------------------------------------------------

	/**
	 * Test saving a domain persists all properties.
	 */
	public function test_save_persists_all_properties(): void {

		$domain = wu_create_domain(
			[
				'blog_id'        => $this->blog_id,
				'domain'         => 'save-test.example.com',
				'active'         => true,
				'primary_domain' => false,
				'secure'         => true,
				'stage'          => Domain_Stage::DONE,
			]
		);

		$this->assertNotWPError($domain);

		$fetched = wu_get_domain($domain->get_id());
		$this->assertInstanceOf(Domain::class, $fetched);

		$this->assertEquals('save-test.example.com', $fetched->get_domain());
		$this->assertSame($this->blog_id, $fetched->get_blog_id());
		$this->assertTrue($fetched->is_secure());
		$this->assertEquals(Domain_Stage::DONE, $fetched->get_stage());

		// Clean up.
		$domain->delete();
	}

	/**
	 * Test deleting a domain removes it from the database.
	 */
	public function test_delete_removes_domain(): void {

		$domain = wu_create_domain(
			[
				'blog_id' => $this->blog_id,
				'domain'  => 'delete-test.example.com',
				'stage'   => Domain_Stage::DONE,
			]
		);

		$this->assertNotWPError($domain);
		$id = $domain->get_id();

		$domain->delete();

		$fetched = wu_get_domain($id);
		$this->assertFalse($fetched);
	}

	/**
	 * Test updating an existing domain.
	 */
	public function test_update_existing_domain(): void {

		$domain = wu_create_domain(
			[
				'blog_id' => $this->blog_id,
				'domain'  => 'update-test.example.com',
				'stage'   => Domain_Stage::CHECKING_DNS,
				'secure'  => false,
			]
		);

		$this->assertNotWPError($domain);

		// Update properties.
		$domain->set_stage(Domain_Stage::DONE);
		$domain->set_secure(true);
		$result = $domain->save();

		$this->assertNotWPError($result);

		// Verify updated values.
		$fetched = wu_get_domain($domain->get_id());
		$this->assertEquals(Domain_Stage::DONE, $fetched->get_stage());
		$this->assertTrue($fetched->is_secure());

		// Clean up.
		$domain->delete();
	}

	// ----------------------------------------------------------------
	// Edge case tests
	// ----------------------------------------------------------------

	/**
	 * Test set_domain with empty string.
	 */
	public function test_set_domain_empty_string(): void {

		$domain = new Domain();
		$domain->set_domain('');

		$this->assertEquals('', $domain->get_domain());
	}

	/**
	 * Test set_stage cycles through all stages.
	 */
	public function test_set_stage_cycles_through_all(): void {

		$domain = new Domain();

		$stages = [
			Domain_Stage::CHECKING_DNS,
			Domain_Stage::CHECKING_SSL,
			Domain_Stage::FAILED,
			Domain_Stage::SSL_FAILED,
			Domain_Stage::DONE_WITHOUT_SSL,
			Domain_Stage::DONE,
		];

		foreach ($stages as $stage) {
			$domain->set_stage($stage);
			$this->assertEquals($stage, $domain->get_stage());
		}
	}

	/**
	 * Test that a new Domain object has expected defaults.
	 */
	public function test_new_domain_defaults(): void {

		$domain = new Domain();

		$this->assertEquals('', $domain->get_domain());
		$this->assertFalse($domain->is_primary_domain());
		$this->assertFalse($domain->is_secure());
		$this->assertEquals(Domain_Stage::CHECKING_DNS, $domain->get_stage());
	}

	/**
	 * Test get_url reflects domain changes.
	 */
	public function test_get_url_reflects_domain_changes(): void {

		$domain = new Domain();
		$domain->set_domain('first.example.com');
		$domain->set_secure(false);

		$this->assertEquals('http://first.example.com/', $domain->get_url());

		$domain->set_domain('second.example.com');
		$domain->set_secure(true);

		$this->assertEquals('https://second.example.com/', $domain->get_url());
	}
}
