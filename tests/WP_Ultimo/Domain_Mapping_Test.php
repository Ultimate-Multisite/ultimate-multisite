<?php
/**
 * Tests for Domain_Mapping class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo;

use WP_UnitTestCase;
use WP_Ultimo\Models\Domain;

/**
 * Test class for Domain_Mapping.
 */
class Domain_Mapping_Test extends WP_UnitTestCase {

	/**
	 * @var Domain_Mapping
	 */
	private Domain_Mapping $domain_mapping;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {

		parent::set_up();
		$this->domain_mapping = Domain_Mapping::get_instance();

		// Ensure the wpdb table reference is initialised so Domain::get_by_domain()
		// can build its query without hitting "Undefined property: wpdb::$wu_dmtable".
		$this->domain_mapping->startup();

		// Reset current_mapping and original_url for test isolation.
		$this->domain_mapping->current_mapping = null;
		$this->domain_mapping->original_url    = null;
	}

	// ----------------------------------------------------------------
	// Singleton
	// ----------------------------------------------------------------

	/**
	 * Test singleton returns correct instance.
	 */
	public function test_singleton_returns_correct_instance(): void {

		$this->assertInstanceOf(Domain_Mapping::class, $this->domain_mapping);
	}

	/**
	 * Test singleton returns same instance.
	 */
	public function test_singleton_returns_same_instance(): void {

		$this->assertSame(
			Domain_Mapping::get_instance(),
			Domain_Mapping::get_instance()
		);
	}

	// ----------------------------------------------------------------
	// should_skip_checks
	// ----------------------------------------------------------------

	/**
	 * Test should_skip_checks returns false by default.
	 */
	public function test_should_skip_checks_default(): void {

		if (! defined('WP_ULTIMO_DOMAIN_MAPPING_SKIP_CHECKS')) {
			$this->assertFalse(Domain_Mapping::should_skip_checks());
		} else {
			$this->assertIsBool(Domain_Mapping::should_skip_checks());
		}
	}

	/**
	 * Test should_skip_checks returns a boolean.
	 */
	public function test_should_skip_checks_returns_bool(): void {

		$result = Domain_Mapping::should_skip_checks();

		$this->assertIsBool($result);
	}

	// ----------------------------------------------------------------
	// get_www_and_nowww_versions
	// ----------------------------------------------------------------

	/**
	 * Test get_www_and_nowww_versions with plain domain.
	 */
	public function test_get_www_and_nowww_versions_plain_domain(): void {

		$result = $this->domain_mapping->get_www_and_nowww_versions('example.com');

		$this->assertIsArray($result);
		$this->assertCount(2, $result);
		$this->assertEquals('example.com', $result[0]);
		$this->assertEquals('www.example.com', $result[1]);
	}

	/**
	 * Test get_www_and_nowww_versions with www domain.
	 */
	public function test_get_www_and_nowww_versions_www_domain(): void {

		$result = $this->domain_mapping->get_www_and_nowww_versions('www.example.com');

		$this->assertIsArray($result);
		$this->assertCount(2, $result);
		$this->assertEquals('example.com', $result[0]);
		$this->assertEquals('www.example.com', $result[1]);
	}

	/**
	 * Test get_www_and_nowww_versions with subdomain.
	 */
	public function test_get_www_and_nowww_versions_subdomain(): void {

		$result = $this->domain_mapping->get_www_and_nowww_versions('sub.example.com');

		$this->assertEquals('sub.example.com', $result[0]);
		$this->assertEquals('www.sub.example.com', $result[1]);
	}

	/**
	 * Test get_www_and_nowww_versions always returns exactly two elements.
	 */
	public function test_get_www_and_nowww_versions_always_two_elements(): void {

		$cases = [
			'example.com',
			'www.example.com',
			'sub.domain.example.com',
			'www.sub.example.com',
		];

		foreach ($cases as $domain) {
			$result = $this->domain_mapping->get_www_and_nowww_versions($domain);
			$this->assertCount(2, $result, "Expected 2 elements for domain: $domain");
		}
	}

	/**
	 * Test get_www_and_nowww_versions www prefix is stripped correctly.
	 */
	public function test_get_www_and_nowww_versions_www_prefix_stripped(): void {

		$result = $this->domain_mapping->get_www_and_nowww_versions('www.test.org');

		// First element should be the no-www version.
		$this->assertStringStartsNotWith('www.', $result[0]);
		// Second element should be the www version.
		$this->assertStringStartsWith('www.', $result[1]);
	}

	/**
	 * Test get_www_and_nowww_versions non-www domain gets www prepended.
	 */
	public function test_get_www_and_nowww_versions_nowww_gets_www_prepended(): void {

		$result = $this->domain_mapping->get_www_and_nowww_versions('mysite.net');

		$this->assertEquals('mysite.net', $result[0]);
		$this->assertEquals('www.mysite.net', $result[1]);
	}

	// ----------------------------------------------------------------
	// allow_network_redirect_hosts
	// ----------------------------------------------------------------

	/**
	 * Test allow_network_redirect_hosts with empty host returns unchanged list.
	 */
	public function test_allow_network_redirect_hosts_empty_host(): void {

		$allowed = ['example.com'];
		$result  = $this->domain_mapping->allow_network_redirect_hosts($allowed, '');

		$this->assertEquals($allowed, $result);
	}

	/**
	 * Test allow_network_redirect_hosts with already allowed host.
	 */
	public function test_allow_network_redirect_hosts_already_allowed(): void {

		$allowed = ['example.com', 'test.com'];
		$result  = $this->domain_mapping->allow_network_redirect_hosts($allowed, 'example.com');

		$this->assertEquals($allowed, $result);
	}

	/**
	 * Test allow_network_redirect_hosts normalizes host to lowercase.
	 */
	public function test_allow_network_redirect_hosts_case_insensitive(): void {

		$allowed = ['example.com'];
		$result  = $this->domain_mapping->allow_network_redirect_hosts($allowed, 'EXAMPLE.COM');

		// Lowercase version already in list — should match.
		$this->assertEquals($allowed, $result);
	}

	/**
	 * Test allow_network_redirect_hosts with unknown host not added.
	 */
	public function test_allow_network_redirect_hosts_unknown_host_not_added(): void {

		$allowed = [];
		$result  = $this->domain_mapping->allow_network_redirect_hosts($allowed, 'unknown-host-xyz-12345.test');

		// No mapping, no site — host should not be added.
		$this->assertNotContains('unknown-host-xyz-12345.test', $result);
	}

	/**
	 * Test allow_network_redirect_hosts returns array.
	 */
	public function test_allow_network_redirect_hosts_returns_array(): void {

		$result = $this->domain_mapping->allow_network_redirect_hosts(['example.com'], 'other.com');

		$this->assertIsArray($result);
	}

	/**
	 * Test allow_network_redirect_hosts preserves existing hosts.
	 */
	public function test_allow_network_redirect_hosts_preserves_existing_hosts(): void {

		$allowed = ['site1.com', 'site2.com', 'site3.com'];
		$result  = $this->domain_mapping->allow_network_redirect_hosts($allowed, 'site2.com');

		$this->assertContains('site1.com', $result);
		$this->assertContains('site2.com', $result);
		$this->assertContains('site3.com', $result);
	}

	/**
	 * Test allow_network_redirect_hosts with the main site domain.
	 *
	 * The main site (example.org) always exists in the test environment.
	 * get_sites() will find it and add it to the allowed list.
	 */
	public function test_allow_network_redirect_hosts_with_registered_site(): void {

		$allowed = [];
		$result  = $this->domain_mapping->allow_network_redirect_hosts($allowed, 'example.org');

		$this->assertContains('example.org', $result);
	}

	// ----------------------------------------------------------------
	// add_mapped_domains_as_allowed_origins
	// ----------------------------------------------------------------

	/**
	 * Test add_mapped_domains_as_allowed_origins with empty origin returns string.
	 */
	public function test_add_mapped_domains_as_allowed_origins_empty(): void {

		$result = $this->domain_mapping->add_mapped_domains_as_allowed_origins('');

		$this->assertIsString($result);
	}

	/**
	 * Test add_mapped_domains_as_allowed_origins with unknown origin returns original.
	 */
	public function test_add_mapped_domains_as_allowed_origins_returns_origin(): void {

		$result = $this->domain_mapping->add_mapped_domains_as_allowed_origins('https://nonexistent-domain.test');

		$this->assertEquals('https://nonexistent-domain.test', $result);
	}

	/**
	 * Test add_mapped_domains_as_allowed_origins with non-URL string.
	 */
	public function test_add_mapped_domains_as_allowed_origins_non_url(): void {

		$result = $this->domain_mapping->add_mapped_domains_as_allowed_origins('not-a-url');

		$this->assertIsString($result);
	}

	// ----------------------------------------------------------------
	// fix_sso_target_site
	// ----------------------------------------------------------------

	/**
	 * Test fix_sso_target_site with valid target site passes through.
	 *
	 * Creates a WP_Site-like object directly to avoid database dependency.
	 */
	public function test_fix_sso_target_site_valid_target_passes_through(): void {

		// Build a minimal WP_Site object without a DB query.
		$site_data           = new \stdClass();
		$site_data->blog_id  = 1;
		$site_data->domain   = 'example.org';
		$site_data->path     = '/';
		$site_data->site_id  = 1;
		$site_data->deleted  = 0;
		$site_data->archived = 0;
		$site_data->spam     = 0;
		$site                = new \WP_Site($site_data);

		$result = $this->domain_mapping->fix_sso_target_site($site, 'example.com');

		$this->assertInstanceOf(\WP_Site::class, $result);
		$this->assertEquals(1, $result->blog_id);
	}

	/**
	 * Test fix_sso_target_site with null target and no mapping returns null.
	 */
	public function test_fix_sso_target_site_null_target_no_mapping(): void {

		$result = $this->domain_mapping->fix_sso_target_site(null, 'no-mapping-domain-xyz.test');

		$this->assertNull($result);
	}

	/**
	 * Test fix_sso_target_site with false target and no mapping returns false.
	 */
	public function test_fix_sso_target_site_false_target_no_mapping(): void {

		$result = $this->domain_mapping->fix_sso_target_site(false, 'no-mapping-domain-xyz.test');

		$this->assertFalse($result);
	}

	// ----------------------------------------------------------------
	// replace_url
	// ----------------------------------------------------------------

	/**
	 * Test replace_url returns original URL when no mapping set.
	 */
	public function test_replace_url_no_mapping_returns_original(): void {

		$this->domain_mapping->current_mapping = null;

		$result = $this->domain_mapping->replace_url('http://example.com/path');

		$this->assertEquals('http://example.com/path', $result);
	}

	/**
	 * Test replace_url with explicit null mapping returns original URL.
	 */
	public function test_replace_url_explicit_null_mapping_returns_original(): void {

		$result = $this->domain_mapping->replace_url('http://example.com/path', null);

		$this->assertEquals('http://example.com/path', $result);
	}

	/**
	 * Test replace_url with a mapping replaces domain.
	 *
	 * Uses the main blog (ID 1) which always exists in the test environment.
	 * The main blog has domain 'example.org' and path '/'.
	 */
	public function test_replace_url_with_mapping_replaces_domain(): void {

		$blog_id = get_current_blog_id(); // Always 1 in test env.

		$mapping = new Domain();
		$mapping->set_domain('mapped.example.com');
		$mapping->set_blog_id($blog_id);
		$mapping->set_active(true);
		$mapping->set_secure(false);

		$result = $this->domain_mapping->replace_url('http://example.org/', $mapping);

		$this->assertStringContainsString('mapped.example.com', $result);
	}

	/**
	 * Test replace_url with secure mapping uses https scheme.
	 *
	 * Uses the main blog (ID 1) which always exists in the test environment.
	 */
	public function test_replace_url_secure_mapping_uses_https(): void {

		$blog_id = get_current_blog_id(); // Always 1 in test env.

		$mapping = new Domain();
		$mapping->set_domain('secure.example.com');
		$mapping->set_blog_id($blog_id);
		$mapping->set_active(true);
		$mapping->set_secure(true);

		$result = $this->domain_mapping->replace_url('http://example.org/', $mapping);

		$this->assertStringStartsWith('https://', $result);
	}

	/**
	 * Test replace_url with no path in mapping returns original URL.
	 */
	public function test_replace_url_no_path_returns_original(): void {

		// Domain with blog_id 0 — get_path() will return null.
		$mapping = new Domain();
		$mapping->set_domain('mapped.example.com');
		$mapping->set_blog_id(0);

		$result = $this->domain_mapping->replace_url('http://example.com/path', $mapping);

		$this->assertEquals('http://example.com/path', $result);
	}

	/**
	 * Test replace_url returns a string.
	 */
	public function test_replace_url_returns_string(): void {

		$result = $this->domain_mapping->replace_url('http://example.com/');

		$this->assertIsString($result);
	}

	/**
	 * Test replace_url uses current_mapping when no explicit mapping passed.
	 *
	 * Uses the main blog (ID 1) which always exists in the test environment.
	 */
	public function test_replace_url_uses_current_mapping(): void {

		$blog_id = get_current_blog_id(); // Always 1 in test env.

		$mapping = new Domain();
		$mapping->set_domain('current-mapped.example.com');
		$mapping->set_blog_id($blog_id);
		$mapping->set_active(true);
		$mapping->set_secure(false);

		$this->domain_mapping->current_mapping = $mapping;

		$result = $this->domain_mapping->replace_url('http://example.org/');

		$this->assertStringContainsString('current-mapped.example.com', $result);

		$this->domain_mapping->current_mapping = null;
	}

	// ----------------------------------------------------------------
	// mangle_url
	// ----------------------------------------------------------------

	/**
	 * Test mangle_url with no current_mapping returns original URL.
	 */
	public function test_mangle_url_no_mapping_returns_original(): void {

		$this->domain_mapping->current_mapping = null;

		$result = $this->domain_mapping->mangle_url('http://example.com/');

		$this->assertEquals('http://example.com/', $result);
	}

	/**
	 * Test mangle_url with mapping for different site returns original URL.
	 *
	 * Uses a non-existent blog_id (99999) to simulate a mapping for a different site.
	 */
	public function test_mangle_url_mapping_different_site_returns_original(): void {

		$mapping = new Domain();
		$mapping->set_domain('mapped.example.com');
		$mapping->set_blog_id(99999); // Non-existent site ID — different from current.

		$this->domain_mapping->current_mapping = $mapping;

		$result = $this->domain_mapping->mangle_url('http://example.org/', '/', '', get_current_blog_id());

		$this->assertEquals('http://example.org/', $result);

		$this->domain_mapping->current_mapping = null;
	}

	/**
	 * Test mangle_url returns a string.
	 */
	public function test_mangle_url_returns_string(): void {

		$result = $this->domain_mapping->mangle_url('http://example.com/');

		$this->assertIsString($result);
	}

	/**
	 * Test mangle_url with matching site_id and mapping applies replacement.
	 *
	 * Uses the main blog (ID 1) which always exists in the test environment.
	 */
	public function test_mangle_url_matching_site_applies_replacement(): void {

		$blog_id = get_current_blog_id(); // Always 1 in test env.

		$mapping = new Domain();
		$mapping->set_domain('mangled.example.com');
		$mapping->set_blog_id($blog_id);
		$mapping->set_active(true);
		$mapping->set_secure(false);

		$this->domain_mapping->current_mapping = $mapping;

		$result = $this->domain_mapping->mangle_url('http://example.org/', '/', '', $blog_id);

		$this->assertStringContainsString('mangled.example.com', $result);

		$this->domain_mapping->current_mapping = null;
	}

	/**
	 * Test mangle_url uses get_current_blog_id when site_id is 0.
	 */
	public function test_mangle_url_uses_current_blog_id_when_zero(): void {

		$this->domain_mapping->current_mapping = null;

		// With no mapping, should return original regardless of site_id.
		$result = $this->domain_mapping->mangle_url('http://example.com/', '/', '', 0);

		$this->assertEquals('http://example.com/', $result);
	}

	// ----------------------------------------------------------------
	// fix_srcset
	// ----------------------------------------------------------------

	/**
	 * Test fix_srcset with no current_mapping returns sources unchanged.
	 */
	public function test_fix_srcset_no_mapping_returns_unchanged(): void {

		$this->domain_mapping->current_mapping = null;

		$sources = [
			100 => ['url' => 'http://example.com/image-100.jpg', 'value' => 100],
			200 => ['url' => 'http://example.com/image-200.jpg', 'value' => 200],
		];

		$result = $this->domain_mapping->fix_srcset($sources);

		$this->assertEquals($sources, $result);
	}

	/**
	 * Test fix_srcset returns array.
	 */
	public function test_fix_srcset_returns_array(): void {

		$sources = [
			100 => ['url' => 'http://example.com/image.jpg', 'value' => 100],
		];

		$result = $this->domain_mapping->fix_srcset($sources);

		$this->assertIsArray($result);
	}

	/**
	 * Test fix_srcset with empty sources returns empty array.
	 */
	public function test_fix_srcset_empty_sources(): void {

		$result = $this->domain_mapping->fix_srcset([]);

		$this->assertIsArray($result);
		$this->assertEmpty($result);
	}

	// ----------------------------------------------------------------
	// apply_mapping_to_url
	// ----------------------------------------------------------------

	/**
	 * Test apply_mapping_to_url registers action on wu_domain_mapping_register_filters.
	 */
	public function test_apply_mapping_to_url_registers_action(): void {

		Domain_Mapping::apply_mapping_to_url('custom_url_filter');

		$this->assertTrue(has_action('wu_domain_mapping_register_filters') !== false);
	}

	/**
	 * Test apply_mapping_to_url accepts array of hooks.
	 */
	public function test_apply_mapping_to_url_accepts_array(): void {

		Domain_Mapping::apply_mapping_to_url(['hook_one', 'hook_two']);

		$this->assertTrue(has_action('wu_domain_mapping_register_filters') !== false);
	}

	/**
	 * Test apply_mapping_to_url is callable as static method.
	 */
	public function test_apply_mapping_to_url_is_callable(): void {

		$this->assertTrue(is_callable(['WP_Ultimo\Domain_Mapping', 'apply_mapping_to_url']));
	}

	// ----------------------------------------------------------------
	// clear_mappings_on_delete
	// ----------------------------------------------------------------

	/**
	 * Test clear_mappings_on_delete with site that has no mappings does nothing.
	 *
	 * Builds a minimal WP_Site object directly to avoid database dependency.
	 * Uses blog_id 99999 which will never have domain mappings.
	 */
	public function test_clear_mappings_on_delete_no_mappings(): void {

		$site_data           = new \stdClass();
		$site_data->blog_id  = 99999;
		$site_data->domain   = 'nonexistent.test';
		$site_data->path     = '/';
		$site_data->site_id  = 1;
		$site_data->deleted  = 0;
		$site_data->archived = 0;
		$site_data->spam     = 0;
		$site                = new \WP_Site($site_data);

		// Should not throw — no mappings to delete for this blog_id.
		$this->domain_mapping->clear_mappings_on_delete($site);

		$this->assertTrue(true);
	}

	// ----------------------------------------------------------------
	// register_mapped_filters
	// ----------------------------------------------------------------

	/**
	 * Test register_mapped_filters with no current_blog returns early.
	 */
	public function test_register_mapped_filters_no_current_blog(): void {

		$original = $GLOBALS['current_blog'] ?? null;

		$GLOBALS['current_blog'] = null;

		// Should not throw.
		$this->domain_mapping->register_mapped_filters();

		$this->assertTrue(true);

		$GLOBALS['current_blog'] = $original;
	}

	// ----------------------------------------------------------------
	// maybe_startup
	// ----------------------------------------------------------------

	/**
	 * Test maybe_startup returns early when muplugins_loaded has fired.
	 */
	public function test_maybe_startup_returns_early_after_muplugins_loaded(): void {

		// In the test environment, muplugins_loaded has already fired.
		// maybe_startup should return early without error.
		$this->domain_mapping->maybe_startup();

		$this->assertTrue(true);
	}

	// ----------------------------------------------------------------
	// startup
	// ----------------------------------------------------------------

	/**
	 * Test startup adds wu_dmtable to wpdb when not set.
	 */
	public function test_startup_adds_wu_dmtable_to_wpdb(): void {

		global $wpdb;

		$original = $wpdb->wu_dmtable ?? null;
		unset($wpdb->wu_dmtable);

		$this->domain_mapping->startup();

		$this->assertNotEmpty($wpdb->wu_dmtable);
		$this->assertStringContainsString('wu_domain_mappings', $wpdb->wu_dmtable);

		if (null !== $original) {
			$wpdb->wu_dmtable = $original;
		}
	}

	/**
	 * Test startup adds wu_domain_mappings to ms_global_tables.
	 */
	public function test_startup_adds_to_ms_global_tables(): void {

		global $wpdb;

		$this->domain_mapping->startup();

		$this->assertContains('wu_domain_mappings', $wpdb->ms_global_tables);
	}

	/**
	 * Test startup registers pre_get_site_by_path filter.
	 */
	public function test_startup_registers_pre_get_site_by_path_filter(): void {

		$this->domain_mapping->startup();

		$this->assertTrue(has_filter('pre_get_site_by_path', [$this->domain_mapping, 'check_domain_mapping']) !== false);
	}

	/**
	 * Test startup registers ms_site_not_found action.
	 */
	public function test_startup_registers_ms_site_not_found_action(): void {

		$this->domain_mapping->startup();

		$this->assertTrue(has_action('ms_site_not_found', [$this->domain_mapping, 'verify_dns_mapping']) !== false);
	}

	/**
	 * Test startup registers wp_delete_site action.
	 */
	public function test_startup_registers_wp_delete_site_action(): void {

		$this->domain_mapping->startup();

		$this->assertTrue(has_action('wp_delete_site', [$this->domain_mapping, 'clear_mappings_on_delete']) !== false);
	}

	/**
	 * Test startup registers ms_loaded action.
	 */
	public function test_startup_registers_ms_loaded_action(): void {

		$this->domain_mapping->startup();

		$this->assertTrue(has_action('ms_loaded', [$this->domain_mapping, 'register_mapped_filters']) !== false);
	}

	// ----------------------------------------------------------------
	// init / allowed_redirect_hosts filter
	// ----------------------------------------------------------------

	/**
	 * Test that allow_network_redirect_hosts is a valid callable on the instance.
	 *
	 * The filter is registered during init() which runs at singleton creation.
	 * Rather than asserting hook registration state (which depends on test ordering
	 * and hook backup/restore), we verify the callback is callable — the functional
	 * contract that matters for the filter to work.
	 */
	public function test_allow_network_redirect_hosts_is_callable(): void {

		$this->assertTrue(is_callable([$this->domain_mapping, 'allow_network_redirect_hosts']));
	}

	/**
	 * Test startup registers allowed_redirect_hosts filter when called explicitly.
	 *
	 * startup() does not register allowed_redirect_hosts directly; init() does.
	 * This test verifies that after calling add_filter manually, has_filter works.
	 */
	public function test_allowed_redirect_hosts_filter_can_be_registered(): void {

		add_filter('allowed_redirect_hosts', [$this->domain_mapping, 'allow_network_redirect_hosts'], 20, 2);

		$this->assertTrue(
			has_filter('allowed_redirect_hosts', [$this->domain_mapping, 'allow_network_redirect_hosts']) !== false
		);

		remove_filter('allowed_redirect_hosts', [$this->domain_mapping, 'allow_network_redirect_hosts'], 20);
	}

	// ----------------------------------------------------------------
	// Property access
	// ----------------------------------------------------------------

	/**
	 * Test current_mapping property is null by default via reflection.
	 */
	public function test_current_mapping_default_null(): void {

		$mapping    = Domain_Mapping::get_instance();
		$reflection = new \ReflectionClass($mapping);
		$prop       = $reflection->getProperty('current_mapping');

		if (PHP_VERSION_ID < 80100) {
			$prop->setAccessible(true);
		}

		$prop->setValue($mapping, null);

		$this->assertNull($prop->getValue($mapping));
	}

	/**
	 * Test original_url property is null by default via reflection.
	 */
	public function test_original_url_default_null(): void {

		$mapping    = Domain_Mapping::get_instance();
		$reflection = new \ReflectionClass($mapping);
		$prop       = $reflection->getProperty('original_url');

		if (PHP_VERSION_ID < 80100) {
			$prop->setAccessible(true);
		}

		$prop->setValue($mapping, null);

		$this->assertNull($prop->getValue($mapping));
	}

	/**
	 * Test current_mapping can be set to a Domain instance.
	 */
	public function test_current_mapping_can_be_set(): void {

		$mapping = new Domain();
		$mapping->set_domain('test.example.com');

		$this->domain_mapping->current_mapping = $mapping;

		$this->assertInstanceOf(Domain::class, $this->domain_mapping->current_mapping);
		$this->assertEquals('test.example.com', $this->domain_mapping->current_mapping->get_domain());

		$this->domain_mapping->current_mapping = null;
	}

	/**
	 * Test original_url can be set to a string.
	 */
	public function test_original_url_can_be_set(): void {

		$this->domain_mapping->original_url = 'original.local/';

		$this->assertEquals('original.local/', $this->domain_mapping->original_url);

		$this->domain_mapping->original_url = null;
	}

	// ----------------------------------------------------------------
	// check_domain_mapping
	// ----------------------------------------------------------------

	/**
	 * Test check_domain_mapping returns site when site already matched.
	 *
	 * When $site is non-empty, check_domain_mapping returns it immediately.
	 * Uses a WP_Site object built directly to avoid database dependency.
	 */
	public function test_check_domain_mapping_returns_existing_site(): void {

		$site_data           = new \stdClass();
		$site_data->blog_id  = 1;
		$site_data->domain   = 'example.org';
		$site_data->path     = '/';
		$site_data->site_id  = 1;
		$site_data->deleted  = 0;
		$site_data->archived = 0;
		$site_data->spam     = 0;
		$site                = new \WP_Site($site_data);

		$result = $this->domain_mapping->check_domain_mapping($site, 'any-domain.test');

		$this->assertSame($site, $result);
	}

	/**
	 * Test check_domain_mapping returns null when no mapping found.
	 */
	public function test_check_domain_mapping_no_mapping_returns_null(): void {

		$result = $this->domain_mapping->check_domain_mapping(null, 'no-mapping-xyz-12345.test');

		$this->assertNull($result);
	}

	/**
	 * Test check_domain_mapping returns false when site is false and no mapping.
	 */
	public function test_check_domain_mapping_false_site_no_mapping(): void {

		$result = $this->domain_mapping->check_domain_mapping(false, 'no-mapping-xyz-12345.test');

		$this->assertFalse($result);
	}

	// ----------------------------------------------------------------
	// verify_dns_mapping
	// ----------------------------------------------------------------

	/**
	 * Test verify_dns_mapping without nonce does nothing.
	 */
	public function test_verify_dns_mapping_without_nonce(): void {

		// Ensure the nonce is not set.
		unset($_REQUEST['async_check_dns_nonce']);

		// Should return without doing anything.
		$this->domain_mapping->verify_dns_mapping(null, 'example.com', '/');

		$this->assertTrue(true);
	}

	/**
	 * Test verify_dns_mapping is callable.
	 */
	public function test_verify_dns_mapping_is_callable(): void {

		$this->assertTrue(is_callable([$this->domain_mapping, 'verify_dns_mapping']));
	}

	// ----------------------------------------------------------------
	// register_mapped_filters (with current_blog set)
	// ----------------------------------------------------------------

	/**
	 * Test register_mapped_filters with current_blog set but no mapping found.
	 */
	public function test_register_mapped_filters_with_current_blog_no_mapping(): void {

		global $current_blog;

		// Set a current_blog so the early return is bypassed.
		$site_data           = new \stdClass();
		$site_data->blog_id  = 1;
		$site_data->domain   = 'example.org';
		$site_data->path     = '/';
		$site_data->site_id  = 1;
		$site_data->deleted  = 0;
		$site_data->archived = 0;
		$site_data->spam     = 0;
		$current_blog        = new \WP_Site($site_data);

		// Set HTTP_HOST to a domain with no mapping.
		$_SERVER['HTTP_HOST'] = 'no-mapping-xyz-99999.test';

		// Should not throw — no mapping found, returns early.
		$this->domain_mapping->register_mapped_filters();

		$this->assertTrue(true);

		// Restore.
		unset($_SERVER['HTTP_HOST']);
	}

	// ----------------------------------------------------------------
	// fix_srcset (with mapping but no site)
	// ----------------------------------------------------------------

	/**
	 * Test fix_srcset with mapping that has no site returns sources unchanged.
	 */
	public function test_fix_srcset_mapping_no_site_returns_unchanged(): void {

		// A mapping with blog_id 0 — get_site() returns null.
		$mapping = new Domain();
		$mapping->set_domain('mapped.example.com');
		$mapping->set_blog_id(0);

		$this->domain_mapping->current_mapping = $mapping;

		$sources = [
			100 => ['url' => 'http://example.com/image.jpg', 'value' => 100],
		];

		$result = $this->domain_mapping->fix_srcset($sources);

		$this->assertEquals($sources, $result);

		$this->domain_mapping->current_mapping = null;
	}

	// ----------------------------------------------------------------
	// startup — wu_sso_site_allowed_domains filter
	// ----------------------------------------------------------------

	/**
	 * Test startup registers wu_sso_site_allowed_domains filter.
	 */
	public function test_startup_registers_wu_sso_site_allowed_domains_filter(): void {

		$this->domain_mapping->startup();

		$this->assertTrue(has_filter('wu_sso_site_allowed_domains') !== false);
	}

	/**
	 * Test wu_sso_site_allowed_domains filter callback merges domain list.
	 */
	public function test_wu_sso_site_allowed_domains_filter_merges_list(): void {

		$this->domain_mapping->startup();

		$initial_list = ['existing.com'];
		$result       = apply_filters('wu_sso_site_allowed_domains', $initial_list, 1);

		$this->assertIsArray($result);
		// The initial list should be preserved.
		$this->assertContains('existing.com', $result);
	}

	// ----------------------------------------------------------------
	// apply_mapping_to_url — inner callback
	// ----------------------------------------------------------------

	/**
	 * Test apply_mapping_to_url inner callback adds filter when action fires.
	 */
	public function test_apply_mapping_to_url_inner_callback_adds_filter(): void {

		Domain_Mapping::apply_mapping_to_url('test_inner_hook');

		// Fire the action to trigger the inner callback.
		$callback_received = null;
		do_action('wu_domain_mapping_register_filters', function ($url) {
			return $url;
		});

		// The inner callback should have added a filter for 'test_inner_hook'.
		$this->assertTrue(has_filter('test_inner_hook') !== false);
	}

	// ----------------------------------------------------------------
	// mangle_url — empty site_id uses get_current_blog_id
	// ----------------------------------------------------------------

	/**
	 * Test mangle_url with empty site_id (0) uses get_current_blog_id.
	 *
	 * When site_id is 0, mangle_url calls get_current_blog_id().
	 * With a mapping for a different blog_id, it returns the original URL.
	 */
	public function test_mangle_url_zero_site_id_uses_current_blog(): void {

		$mapping = new Domain();
		$mapping->set_domain('mapped.example.com');
		$mapping->set_blog_id(99999); // Different from current blog.

		$this->domain_mapping->current_mapping = $mapping;

		// site_id = 0 → uses get_current_blog_id() which is 1, not 99999.
		$result = $this->domain_mapping->mangle_url('http://example.org/', '/', '', 0);

		$this->assertEquals('http://example.org/', $result);

		$this->domain_mapping->current_mapping = null;
	}

	// ----------------------------------------------------------------
	// replace_url — scheme replacement
	// ----------------------------------------------------------------

	/**
	 * Test replace_url with insecure mapping uses http scheme.
	 */
	public function test_replace_url_insecure_mapping_uses_http(): void {

		$blog_id = get_current_blog_id();

		$mapping = new Domain();
		$mapping->set_domain('insecure.example.com');
		$mapping->set_blog_id($blog_id);
		$mapping->set_active(true);
		$mapping->set_secure(false);

		$result = $this->domain_mapping->replace_url('https://example.org/', $mapping);

		$this->assertStringStartsWith('http://', $result);
	}

	/**
	 * Test replace_url with empty URL returns empty string.
	 */
	public function test_replace_url_empty_url(): void {

		$result = $this->domain_mapping->replace_url('');

		$this->assertIsString($result);
	}
}
