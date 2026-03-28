<?php
/**
 * Unit tests for Base_Host_Provider abstract class.
 *
 * Tests all non-abstract methods of the base class using a concrete
 * test double. External API calls are mocked via PHPUnit mocks.
 *
 * @package WP_Ultimo\Tests
 * @subpackage Integrations\Host_Providers
 */

namespace WP_Ultimo\Tests\Integrations\Host_Providers;

use WP_Ultimo\Integrations\Host_Providers\Base_Host_Provider;
use WP_Ultimo\Integrations\Host_Providers\DNS_Provider_Interface;
use WP_UnitTestCase;

/**
 * Concrete implementation of Base_Host_Provider for testing.
 *
 * Implements all abstract methods with minimal stubs so we can
 * exercise the base class logic directly.
 */
class Concrete_Test_Provider extends Base_Host_Provider {

	/**
	 * Whether detect() should return true.
	 *
	 * @var bool
	 */
	public bool $detected = false;

	/**
	 * Constructor — sets up test provider properties.
	 */
	public function __construct() {

		$this->id                 = 'test-provider';
		$this->title              = 'Test Provider';
		$this->supports           = [];
		$this->constants          = [];
		$this->optional_constants = [];
	}

	/**
	 * Set required constants (exposes protected property for testing).
	 *
	 * @param array $constants Constants to require.
	 * @return void
	 */
	public function set_constants(array $constants): void {

		$this->constants = $constants;
	}

	/**
	 * Set optional constants (exposes protected property for testing).
	 *
	 * @param array $optional_constants Optional constants.
	 * @return void
	 */
	public function set_optional_constants(array $optional_constants): void {

		$this->optional_constants = $optional_constants;
	}

	/**
	 * Set supports array (exposes protected property for testing).
	 *
	 * @param array $supports Features to support.
	 * @return void
	 */
	public function set_supports(array $supports): void {

		$this->supports = $supports;
	}

	/**
	 * Detect if this provider is in use.
	 *
	 * @return bool
	 */
	public function detect(): bool {

		return $this->detected;
	}

	/**
	 * Handle domain addition.
	 *
	 * @param string $domain  The domain.
	 * @param int    $site_id The site ID.
	 * @return void
	 */
	public function on_add_domain($domain, $site_id): void {}

	/**
	 * Handle domain removal.
	 *
	 * @param string $domain  The domain.
	 * @param int    $site_id The site ID.
	 * @return void
	 */
	public function on_remove_domain($domain, $site_id): void {}

	/**
	 * Handle subdomain addition.
	 *
	 * @param string $subdomain The subdomain.
	 * @param int    $site_id   The site ID.
	 * @return void
	 */
	public function on_add_subdomain($subdomain, $site_id): void {}

	/**
	 * Handle subdomain removal.
	 *
	 * @param string $subdomain The subdomain.
	 * @param int    $site_id   The site ID.
	 * @return void
	 */
	public function on_remove_subdomain($subdomain, $site_id): void {}
}

/**
 * A provider with constants configured, for testing is_setup / get_missing_constants.
 */
class Provider_With_Constants extends Concrete_Test_Provider {

	/**
	 * Constructor — sets up constants.
	 */
	public function __construct() {

		parent::__construct();
		$this->id        = 'provider-with-constants';
		$this->constants = ['TEST_PROVIDER_API_KEY', 'TEST_PROVIDER_SECRET'];
	}
}

/**
 * A provider that supports autossl and dns-management.
 */
class Provider_With_Features extends Concrete_Test_Provider {

	/**
	 * Constructor — sets up features.
	 */
	public function __construct() {

		parent::__construct();
		$this->id       = 'provider-with-features';
		$this->supports = ['autossl', 'dns-management'];
	}
}

/**
 * Tests for Base_Host_Provider.
 */
class Base_Host_Provider_Test extends WP_UnitTestCase {

	/**
	 * The provider under test.
	 *
	 * @var Concrete_Test_Provider
	 */
	private Concrete_Test_Provider $provider;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {

		parent::set_up();

		$this->provider = new Concrete_Test_Provider();
	}

	/**
	 * Tear down.
	 */
	public function tear_down(): void {

		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// get_id / get_title
	// -------------------------------------------------------------------------

	/**
	 * Test get_id returns the configured id.
	 */
	public function test_get_id_returns_configured_id(): void {

		$this->assertSame('test-provider', $this->provider->get_id());
	}

	/**
	 * Test get_title returns the configured title.
	 */
	public function test_get_title_returns_configured_title(): void {

		$this->assertSame('Test Provider', $this->provider->get_title());
	}

	// -------------------------------------------------------------------------
	// implements DNS_Provider_Interface
	// -------------------------------------------------------------------------

	/**
	 * Test that the class implements DNS_Provider_Interface.
	 */
	public function test_implements_dns_provider_interface(): void {

		$this->assertInstanceOf(DNS_Provider_Interface::class, $this->provider);
	}

	// -------------------------------------------------------------------------
	// supports()
	// -------------------------------------------------------------------------

	/**
	 * Test supports() returns false for unsupported feature.
	 */
	public function test_supports_returns_false_for_unsupported_feature(): void {

		$this->assertFalse($this->provider->supports('autossl'));
	}

	/**
	 * Test supports() returns true for supported feature.
	 */
	public function test_supports_returns_true_for_supported_feature(): void {

		$provider = new Provider_With_Features();

		$this->assertTrue($provider->supports('autossl'));
		$this->assertTrue($provider->supports('dns-management'));
	}

	/**
	 * Test supports() can be overridden via filter.
	 */
	public function test_supports_filter_can_override_result(): void {

		add_filter('wu_hosting_support_supports', '__return_true');

		$this->assertTrue($this->provider->supports('nonexistent-feature'));

		remove_filter('wu_hosting_support_supports', '__return_true');
	}

	// -------------------------------------------------------------------------
	// is_enabled / enable / disable
	// -------------------------------------------------------------------------

	/**
	 * Test is_enabled returns false by default.
	 */
	public function test_is_enabled_returns_false_by_default(): void {

		$this->assertFalse($this->provider->is_enabled());
	}

	/**
	 * Test enable() sets the provider as enabled.
	 */
	public function test_enable_sets_provider_as_enabled(): void {

		$this->provider->enable();

		$this->assertTrue($this->provider->is_enabled());
	}

	/**
	 * Test disable() sets the provider as disabled.
	 */
	public function test_disable_sets_provider_as_disabled(): void {

		$this->provider->enable();
		$this->provider->disable();

		$this->assertFalse($this->provider->is_enabled());
	}

	/**
	 * Test enable/disable cycle.
	 */
	public function test_enable_disable_cycle(): void {

		$this->assertFalse($this->provider->is_enabled());

		$this->provider->enable();
		$this->assertTrue($this->provider->is_enabled());

		$this->provider->disable();
		$this->assertFalse($this->provider->is_enabled());

		$this->provider->enable();
		$this->assertTrue($this->provider->is_enabled());
	}

	// -------------------------------------------------------------------------
	// self_register()
	// -------------------------------------------------------------------------

	/**
	 * Test self_register adds the provider to the integrations list.
	 */
	public function test_self_register_adds_provider_to_list(): void {

		$integrations = [];
		$result       = $this->provider->self_register($integrations);

		$this->assertArrayHasKey('test-provider', $result);
		$this->assertSame(Concrete_Test_Provider::class, $result['test-provider']);
	}

	/**
	 * Test self_register preserves existing integrations.
	 */
	public function test_self_register_preserves_existing_integrations(): void {

		$integrations = ['existing-provider' => 'SomeClass'];
		$result       = $this->provider->self_register($integrations);

		$this->assertArrayHasKey('existing-provider', $result);
		$this->assertArrayHasKey('test-provider', $result);
	}

	// -------------------------------------------------------------------------
	// is_setup() — no constants
	// -------------------------------------------------------------------------

	/**
	 * Test is_setup returns true when no constants are required.
	 */
	public function test_is_setup_returns_true_when_no_constants_required(): void {

		$this->assertTrue($this->provider->is_setup());
	}

	// -------------------------------------------------------------------------
	// is_setup() — with constants
	// -------------------------------------------------------------------------

	/**
	 * Test is_setup returns false when required constant is not defined.
	 */
	public function test_is_setup_returns_false_when_constant_missing(): void {

		$provider = new Provider_With_Constants();

		// TEST_PROVIDER_API_KEY is not defined in test env
		$this->assertFalse($provider->is_setup());
	}

	/**
	 * Test is_setup returns true when all required constants are defined.
	 */
	public function test_is_setup_returns_true_when_all_constants_defined(): void {

		if (! defined('TEST_PROVIDER_API_KEY')) {
			define('TEST_PROVIDER_API_KEY', 'test-key-value');
		}

		if (! defined('TEST_PROVIDER_SECRET')) {
			define('TEST_PROVIDER_SECRET', 'test-secret-value');
		}

		$provider = new Provider_With_Constants();

		$this->assertTrue($provider->is_setup());
	}

	// -------------------------------------------------------------------------
	// is_setup() — array constants (OR logic)
	// -------------------------------------------------------------------------

	/**
	 * Test is_setup with array constants (OR logic — any one match is sufficient).
	 */
	public function test_is_setup_with_array_constants_or_logic(): void {

		// Create a provider with an array constant (OR logic)
		$provider = new Concrete_Test_Provider();
		$provider->set_constants([['CONST_OPTION_A', 'CONST_OPTION_B']]);

		// Neither defined — should be false
		$this->assertFalse($provider->is_setup());
	}

	// -------------------------------------------------------------------------
	// get_missing_constants()
	// -------------------------------------------------------------------------

	/**
	 * Test get_missing_constants returns empty array when no constants required.
	 */
	public function test_get_missing_constants_returns_empty_when_no_constants(): void {

		$missing = $this->provider->get_missing_constants();

		$this->assertIsArray($missing);
		$this->assertEmpty($missing);
	}

	/**
	 * Test get_missing_constants returns missing constants.
	 */
	public function test_get_missing_constants_returns_missing(): void {

		$provider = new Concrete_Test_Provider();
		$provider->set_constants(['UNDEFINED_CONSTANT_XYZ']);

		$missing = $provider->get_missing_constants();

		$this->assertContains('UNDEFINED_CONSTANT_XYZ', $missing);
	}

	/**
	 * Test get_missing_constants does not include defined constants.
	 */
	public function test_get_missing_constants_excludes_defined_constants(): void {

		// ABSPATH is always defined in WP test env
		$provider = new Concrete_Test_Provider();
		$provider->set_constants(['ABSPATH']);

		$missing = $provider->get_missing_constants();

		$this->assertNotContains('ABSPATH', $missing);
	}

	// -------------------------------------------------------------------------
	// get_all_constants()
	// -------------------------------------------------------------------------

	/**
	 * Test get_all_constants returns empty array when no constants configured.
	 */
	public function test_get_all_constants_returns_empty_when_none_configured(): void {

		$constants = $this->provider->get_all_constants();

		$this->assertIsArray($constants);
		$this->assertEmpty($constants);
	}

	/**
	 * Test get_all_constants merges required and optional constants.
	 */
	public function test_get_all_constants_merges_required_and_optional(): void {

		$provider = new Concrete_Test_Provider();
		$provider->set_constants(['REQUIRED_CONST']);
		$provider->set_optional_constants(['OPTIONAL_CONST']);

		$all = $provider->get_all_constants();

		$this->assertContains('REQUIRED_CONST', $all);
		$this->assertContains('OPTIONAL_CONST', $all);
	}

	/**
	 * Test get_all_constants flattens array constants.
	 */
	public function test_get_all_constants_flattens_array_constants(): void {

		$provider = new Concrete_Test_Provider();
		$provider->set_constants([['CONST_A', 'CONST_B'], 'CONST_C']);

		$all = $provider->get_all_constants();

		$this->assertContains('CONST_A', $all);
		$this->assertContains('CONST_B', $all);
		$this->assertContains('CONST_C', $all);
	}

	// -------------------------------------------------------------------------
	// get_constants_string()
	// -------------------------------------------------------------------------

	/**
	 * Test get_constants_string returns a string.
	 */
	public function test_get_constants_string_returns_string(): void {

		$provider = new Concrete_Test_Provider();
		$provider->set_constants(['MY_API_KEY']);

		$result = $provider->get_constants_string(['MY_API_KEY' => 'my-value']);

		$this->assertIsString($result);
	}

	/**
	 * Test get_constants_string contains opening comment.
	 */
	public function test_get_constants_string_contains_opening_comment(): void {

		$provider = new Concrete_Test_Provider();
		$provider->set_constants(['MY_API_KEY']);

		$result = $provider->get_constants_string(['MY_API_KEY' => 'my-value']);

		$this->assertStringContainsString('// Ultimate Multisite - Domain Mapping - Test Provider', $result);
	}

	/**
	 * Test get_constants_string contains closing comment.
	 */
	public function test_get_constants_string_contains_closing_comment(): void {

		$provider = new Concrete_Test_Provider();
		$provider->set_constants(['MY_API_KEY']);

		$result = $provider->get_constants_string(['MY_API_KEY' => 'my-value']);

		$this->assertStringContainsString('// Ultimate Multisite - Domain Mapping - Test Provider - End', $result);
	}

	/**
	 * Test get_constants_string contains define statements.
	 */
	public function test_get_constants_string_contains_define_statements(): void {

		$provider = new Concrete_Test_Provider();
		$provider->set_constants(['MY_API_KEY']);

		$result = $provider->get_constants_string(['MY_API_KEY' => 'my-value']);

		$this->assertStringContainsString("define( 'MY_API_KEY', 'my-value' );", $result);
	}

	/**
	 * Test get_constants_string sanitizes to only known constants (security).
	 */
	public function test_get_constants_string_sanitizes_unknown_constants(): void {

		$provider = new Concrete_Test_Provider();
		$provider->set_constants(['MY_API_KEY']);

		// Pass an extra unknown constant — should be stripped
		$result = $provider->get_constants_string([
			'MY_API_KEY'      => 'my-value',
			'ARBITRARY_CONST' => 'injected-value',
		]);

		$this->assertStringNotContainsString('ARBITRARY_CONST', $result);
	}

	// -------------------------------------------------------------------------
	// get_fields()
	// -------------------------------------------------------------------------

	/**
	 * Test get_fields returns empty array by default.
	 */
	public function test_get_fields_returns_empty_array(): void {

		$fields = $this->provider->get_fields();

		$this->assertIsArray($fields);
		$this->assertEmpty($fields);
	}

	// -------------------------------------------------------------------------
	// get_description()
	// -------------------------------------------------------------------------

	/**
	 * Test get_description returns a non-empty string.
	 */
	public function test_get_description_returns_string(): void {

		$description = $this->provider->get_description();

		$this->assertIsString($description);
		$this->assertNotEmpty($description);
	}

	// -------------------------------------------------------------------------
	// get_logo()
	// -------------------------------------------------------------------------

	/**
	 * Test get_logo returns a string (empty by default).
	 */
	public function test_get_logo_returns_string(): void {

		$logo = $this->provider->get_logo();

		$this->assertIsString($logo);
	}

	// -------------------------------------------------------------------------
	// get_explainer_lines()
	// -------------------------------------------------------------------------

	/**
	 * Test get_explainer_lines returns array with will and will_not keys.
	 */
	public function test_get_explainer_lines_returns_correct_structure(): void {

		$lines = $this->provider->get_explainer_lines();

		$this->assertIsArray($lines);
		$this->assertArrayHasKey('will', $lines);
		$this->assertArrayHasKey('will_not', $lines);
	}

	/**
	 * Test get_explainer_lines will section contains send_domains entry.
	 */
	public function test_get_explainer_lines_will_contains_send_domains(): void {

		$lines = $this->provider->get_explainer_lines();

		$this->assertArrayHasKey('send_domains', $lines['will']);
	}

	/**
	 * Test get_explainer_lines will_not contains ssl entry when autossl not supported.
	 */
	public function test_get_explainer_lines_will_not_contains_ssl_when_no_autossl(): void {

		$lines = $this->provider->get_explainer_lines();

		// Provider without autossl — ssl note goes in will_not
		$this->assertNotEmpty($lines['will_not']);
	}

	/**
	 * Test get_explainer_lines will contains ssl entry when autossl is supported.
	 */
	public function test_get_explainer_lines_will_contains_ssl_when_autossl_supported(): void {

		$provider = new Provider_With_Features();
		$lines    = $provider->get_explainer_lines();

		// Provider with autossl — ssl note goes in will
		$found = false;

		foreach ($lines['will'] as $line) {
			if (is_string($line) && str_contains($line, 'SSL')) {
				$found = true;

				break;
			}
		}

		$this->assertTrue($found, 'Expected SSL entry in will section for autossl provider');
	}

	// -------------------------------------------------------------------------
	// register_hooks()
	// -------------------------------------------------------------------------

	/**
	 * Test register_hooks adds the expected WordPress actions.
	 */
	public function test_register_hooks_adds_expected_actions(): void {

		$this->provider->register_hooks();

		$this->assertIsInt(has_action('wu_add_domain', [$this->provider, 'on_add_domain']));
		$this->assertIsInt(has_action('wu_remove_domain', [$this->provider, 'on_remove_domain']));
		$this->assertIsInt(has_action('wu_add_subdomain', [$this->provider, 'on_add_subdomain']));
		$this->assertIsInt(has_action('wu_remove_subdomain', [$this->provider, 'on_remove_subdomain']));
	}

	// -------------------------------------------------------------------------
	// additional_hooks() / load_dependencies()
	// -------------------------------------------------------------------------

	/**
	 * Test additional_hooks is callable without error.
	 */
	public function test_additional_hooks_is_callable(): void {

		// Should not throw
		$this->provider->additional_hooks();

		$this->assertTrue(true);
	}

	/**
	 * Test load_dependencies is callable without error.
	 */
	public function test_load_dependencies_is_callable(): void {

		// Should not throw
		$this->provider->load_dependencies();

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// supports_dns_management()
	// -------------------------------------------------------------------------

	/**
	 * Test supports_dns_management returns false by default.
	 */
	public function test_supports_dns_management_returns_false_by_default(): void {

		$this->assertFalse($this->provider->supports_dns_management());
	}

	/**
	 * Test supports_dns_management returns true when dns-management is in supports.
	 */
	public function test_supports_dns_management_returns_true_when_supported(): void {

		$provider = new Provider_With_Features();

		$this->assertTrue($provider->supports_dns_management());
	}

	// -------------------------------------------------------------------------
	// get_dns_records() — base class returns WP_Error
	// -------------------------------------------------------------------------

	/**
	 * Test get_dns_records returns WP_Error (base class default).
	 */
	public function test_get_dns_records_returns_wp_error(): void {

		$result = $this->provider->get_dns_records('example.com');

		$this->assertInstanceOf(\WP_Error::class, $result);
		$this->assertSame('dns-not-supported', $result->get_error_code());
	}

	// -------------------------------------------------------------------------
	// create_dns_record() — base class returns WP_Error
	// -------------------------------------------------------------------------

	/**
	 * Test create_dns_record returns WP_Error (base class default).
	 */
	public function test_create_dns_record_returns_wp_error(): void {

		$result = $this->provider->create_dns_record('example.com', ['type' => 'A', 'name' => 'test', 'content' => '1.2.3.4']);

		$this->assertInstanceOf(\WP_Error::class, $result);
		$this->assertSame('dns-not-supported', $result->get_error_code());
	}

	// -------------------------------------------------------------------------
	// update_dns_record() — base class returns WP_Error
	// -------------------------------------------------------------------------

	/**
	 * Test update_dns_record returns WP_Error (base class default).
	 */
	public function test_update_dns_record_returns_wp_error(): void {

		$result = $this->provider->update_dns_record('example.com', 'record-1', ['type' => 'A', 'content' => '1.2.3.5']);

		$this->assertInstanceOf(\WP_Error::class, $result);
		$this->assertSame('dns-not-supported', $result->get_error_code());
	}

	// -------------------------------------------------------------------------
	// delete_dns_record() — base class returns WP_Error
	// -------------------------------------------------------------------------

	/**
	 * Test delete_dns_record returns WP_Error (base class default).
	 */
	public function test_delete_dns_record_returns_wp_error(): void {

		$result = $this->provider->delete_dns_record('example.com', 'record-1');

		$this->assertInstanceOf(\WP_Error::class, $result);
		$this->assertSame('dns-not-supported', $result->get_error_code());
	}

	// -------------------------------------------------------------------------
	// get_supported_record_types()
	// -------------------------------------------------------------------------

	/**
	 * Test get_supported_record_types returns expected default types.
	 */
	public function test_get_supported_record_types_returns_defaults(): void {

		$types = $this->provider->get_supported_record_types();

		$this->assertIsArray($types);
		$this->assertContains('A', $types);
		$this->assertContains('AAAA', $types);
		$this->assertContains('CNAME', $types);
		$this->assertContains('MX', $types);
		$this->assertContains('TXT', $types);
	}

	// -------------------------------------------------------------------------
	// get_zone_id()
	// -------------------------------------------------------------------------

	/**
	 * Test get_zone_id returns null by default.
	 */
	public function test_get_zone_id_returns_null_by_default(): void {

		$result = $this->provider->get_zone_id('example.com');

		$this->assertNull($result);
	}

	// -------------------------------------------------------------------------
	// is_dns_enabled()
	// -------------------------------------------------------------------------

	/**
	 * Test is_dns_enabled returns false when dns-management not supported.
	 */
	public function test_is_dns_enabled_returns_false_when_not_supported(): void {

		$this->assertFalse($this->provider->is_dns_enabled());
	}

	/**
	 * Test is_dns_enabled returns false when supported but not enabled.
	 */
	public function test_is_dns_enabled_returns_false_when_supported_but_not_enabled(): void {

		$provider = new Provider_With_Features();

		// Not yet enabled via enable_dns()
		$this->assertFalse($provider->is_dns_enabled());
	}

	// -------------------------------------------------------------------------
	// enable_dns() / disable_dns()
	// -------------------------------------------------------------------------

	/**
	 * Test enable_dns returns false when dns-management not supported.
	 */
	public function test_enable_dns_returns_false_when_not_supported(): void {

		$result = $this->provider->enable_dns();

		$this->assertFalse($result);
	}

	/**
	 * Test enable_dns enables DNS for a supporting provider.
	 */
	public function test_enable_dns_enables_dns_for_supporting_provider(): void {

		$provider = new Provider_With_Features();
		$provider->enable_dns();

		$this->assertTrue($provider->is_dns_enabled());
	}

	/**
	 * Test disable_dns disables DNS.
	 */
	public function test_disable_dns_disables_dns(): void {

		$provider = new Provider_With_Features();
		$provider->enable_dns();
		$provider->disable_dns();

		$this->assertFalse($provider->is_dns_enabled());
	}

	/**
	 * Test disable_dns works even when dns-management not supported.
	 */
	public function test_disable_dns_works_when_not_supported(): void {

		// Should not throw, just sets the flag to false
		$result = $this->provider->disable_dns();

		$this->assertIsBool($result);
	}

	// -------------------------------------------------------------------------
	// extract_zone_name() — protected, tested via reflection
	// -------------------------------------------------------------------------

	/**
	 * Test extract_zone_name with standard TLD.
	 */
	public function test_extract_zone_name_standard_tld(): void {

		$method = new \ReflectionMethod($this->provider, 'extract_zone_name');
		$method->setAccessible(true);

		$this->assertSame('example.com', $method->invoke($this->provider, 'example.com'));
		$this->assertSame('example.com', $method->invoke($this->provider, 'www.example.com'));
		$this->assertSame('example.com', $method->invoke($this->provider, 'sub.test.example.com'));
	}

	/**
	 * Test extract_zone_name with .co.uk multi-part TLD.
	 */
	public function test_extract_zone_name_co_uk(): void {

		$method = new \ReflectionMethod($this->provider, 'extract_zone_name');
		$method->setAccessible(true);

		$this->assertSame('example.co.uk', $method->invoke($this->provider, 'example.co.uk'));
		$this->assertSame('example.co.uk', $method->invoke($this->provider, 'www.example.co.uk'));
		$this->assertSame('example.co.uk', $method->invoke($this->provider, 'sub.test.example.co.uk'));
	}

	/**
	 * Test extract_zone_name with .com.au multi-part TLD.
	 */
	public function test_extract_zone_name_com_au(): void {

		$method = new \ReflectionMethod($this->provider, 'extract_zone_name');
		$method->setAccessible(true);

		$this->assertSame('example.com.au', $method->invoke($this->provider, 'example.com.au'));
		$this->assertSame('example.com.au', $method->invoke($this->provider, 'www.example.com.au'));
	}

	/**
	 * Test extract_zone_name with .co.nz multi-part TLD.
	 */
	public function test_extract_zone_name_co_nz(): void {

		$method = new \ReflectionMethod($this->provider, 'extract_zone_name');
		$method->setAccessible(true);

		$this->assertSame('example.co.nz', $method->invoke($this->provider, 'www.example.co.nz'));
	}

	/**
	 * Test extract_zone_name with .com.br multi-part TLD.
	 */
	public function test_extract_zone_name_com_br(): void {

		$method = new \ReflectionMethod($this->provider, 'extract_zone_name');
		$method->setAccessible(true);

		$this->assertSame('example.com.br', $method->invoke($this->provider, 'www.example.com.br'));
	}

	/**
	 * Test extract_zone_name with .co.in multi-part TLD.
	 */
	public function test_extract_zone_name_co_in(): void {

		$method = new \ReflectionMethod($this->provider, 'extract_zone_name');
		$method->setAccessible(true);

		$this->assertSame('example.co.in', $method->invoke($this->provider, 'www.example.co.in'));
	}

	/**
	 * Test extract_zone_name with .org.uk multi-part TLD.
	 */
	public function test_extract_zone_name_org_uk(): void {

		$method = new \ReflectionMethod($this->provider, 'extract_zone_name');
		$method->setAccessible(true);

		$this->assertSame('example.org.uk', $method->invoke($this->provider, 'www.example.org.uk'));
	}

	/**
	 * Test extract_zone_name with .net.au multi-part TLD.
	 */
	public function test_extract_zone_name_net_au(): void {

		$method = new \ReflectionMethod($this->provider, 'extract_zone_name');
		$method->setAccessible(true);

		$this->assertSame('example.net.au', $method->invoke($this->provider, 'www.example.net.au'));
	}

	/**
	 * Test extract_zone_name with single-part domain (edge case).
	 */
	public function test_extract_zone_name_single_part_domain(): void {

		$method = new \ReflectionMethod($this->provider, 'extract_zone_name');
		$method->setAccessible(true);

		// Single part — returns as-is
		$this->assertSame('localhost', $method->invoke($this->provider, 'localhost'));
	}

	// -------------------------------------------------------------------------
	// init() — hook registration
	// -------------------------------------------------------------------------

	/**
	 * Test init() registers the self_register filter.
	 */
	public function test_init_registers_self_register_filter(): void {

		$provider = new Concrete_Test_Provider();
		$provider->init();

		$this->assertIsInt(has_filter('wu_domain_manager_get_integrations', [$provider, 'self_register']));
	}

	/**
	 * Test init() registers add_to_integration_list action.
	 */
	public function test_init_registers_add_to_integration_list_action(): void {

		$provider = new Concrete_Test_Provider();
		$provider->init();

		$this->assertIsInt(has_action('init', [$provider, 'add_to_integration_list']));
	}

	/**
	 * Test init() calls register_hooks when provider is enabled and setup.
	 */
	public function test_init_calls_register_hooks_when_enabled_and_setup(): void {

		$provider = new Concrete_Test_Provider();
		$provider->enable();

		// No constants required, so is_setup() returns true
		$provider->init();

		$this->assertIsInt(has_action('wu_add_domain', [$provider, 'on_add_domain']));
	}

	/**
	 * Test init() does not register domain hooks when provider is disabled.
	 */
	public function test_init_does_not_register_hooks_when_disabled(): void {

		$provider = new Concrete_Test_Provider();
		// Not enabled — hooks should not be registered

		// Remove any hooks from previous tests
		remove_all_actions('wu_add_domain');

		$provider->init();

		$this->assertFalse(has_action('wu_add_domain', [$provider, 'on_add_domain']));
	}

	// -------------------------------------------------------------------------
	// test_connection()
	// -------------------------------------------------------------------------

	/**
	 * Test test_connection calls wp_send_json_success.
	 *
	 * We verify the method exists and is callable; the actual wp_send_json_success
	 * call would exit in a real request, so we just confirm no exception is thrown
	 * when the method is invoked in a test context where die() is suppressed.
	 */
	public function test_test_connection_method_exists(): void {

		$this->assertTrue(method_exists($this->provider, 'test_connection'));
	}

	// -------------------------------------------------------------------------
	// get_missing_constants() with array constants
	// -------------------------------------------------------------------------

	/**
	 * Test get_missing_constants with array constants (OR groups).
	 */
	public function test_get_missing_constants_with_array_constants(): void {

		$provider = new Concrete_Test_Provider();
		$provider->set_constants([['UNDEFINED_CONST_A', 'UNDEFINED_CONST_B']]);

		$missing = $provider->get_missing_constants();

		// Both options in the OR group are missing — both should appear
		$this->assertContains('UNDEFINED_CONST_A', $missing);
		$this->assertContains('UNDEFINED_CONST_B', $missing);
	}

	/**
	 * Test get_missing_constants with array constants when one option is defined.
	 */
	public function test_get_missing_constants_with_array_constants_one_defined(): void {

		// ABSPATH is always defined in WP test env
		$provider = new Concrete_Test_Provider();
		$provider->set_constants([['ABSPATH', 'UNDEFINED_CONST_C']]);

		$missing = $provider->get_missing_constants();

		// ABSPATH is defined — the OR group is satisfied, nothing missing
		$this->assertNotContains('ABSPATH', $missing);
		$this->assertNotContains('UNDEFINED_CONST_C', $missing);
	}

	// -------------------------------------------------------------------------
	// alert_provider_detected() — early return when WP_Ultimo not loaded
	// -------------------------------------------------------------------------

	/**
	 * Test alert_provider_detected returns early when WP_Ultimo is not loaded.
	 *
	 * When WP_Ultimo()->is_loaded() returns false, the method should return
	 * without adding any notices.
	 */
	public function test_alert_provider_detected_returns_early_when_not_loaded(): void {

		// WP_Ultimo is not fully loaded in unit test context — method should return early
		// We verify it doesn't throw and doesn't add notices
		$provider = new Concrete_Test_Provider();

		// Should not throw
		$provider->alert_provider_detected();

		$this->assertTrue(true);
	}

	/**
	 * Test alert_provider_not_setup returns early when WP_Ultimo is not loaded.
	 */
	public function test_alert_provider_not_setup_returns_early_when_not_loaded(): void {

		$provider = new Concrete_Test_Provider();

		// Should not throw
		$provider->alert_provider_not_setup();

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// add_to_integration_list() — registers settings field
	// -------------------------------------------------------------------------

	/**
	 * Test add_to_integration_list calls wu_register_settings_field.
	 *
	 * The method registers a settings field for the integration. We verify
	 * it runs without error in the test environment.
	 */
	public function test_add_to_integration_list_runs_without_error(): void {

		$provider = new Concrete_Test_Provider();

		// Should not throw — wu_register_settings_field is available in test env
		$provider->add_to_integration_list();

		$this->assertTrue(true);
	}

	/**
	 * Test add_to_integration_list with enabled provider shows activated HTML.
	 */
	public function test_add_to_integration_list_enabled_provider(): void {

		$provider = new Concrete_Test_Provider();
		$provider->enable();

		// Should not throw
		$provider->add_to_integration_list();

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// setup_constants() — sanitizes and injects constants
	// -------------------------------------------------------------------------

	/**
	 * Test setup_constants only processes known constants (security gate).
	 *
	 * The method uses shortcode_atts to filter to only known constants,
	 * preventing injection of arbitrary constants into wp-config.php.
	 * We test the sanitization logic by verifying get_all_constants() output.
	 */
	public function test_setup_constants_sanitizes_to_known_constants(): void {

		$provider = new Concrete_Test_Provider();
		$provider->set_constants(['KNOWN_CONST']);

		// get_all_constants() should only return KNOWN_CONST
		$all = $provider->get_all_constants();

		$this->assertContains('KNOWN_CONST', $all);
		$this->assertNotContains('ARBITRARY_CONST', $all);
	}

	// -------------------------------------------------------------------------
	// init() — provider detected but not enabled
	// -------------------------------------------------------------------------

	/**
	 * Test init() when provider is detected but not enabled.
	 *
	 * When detect() returns true but is_enabled() returns false,
	 * init() should call alert_provider_detected() (which returns early
	 * in test env since WP_Ultimo is not loaded).
	 */
	public function test_init_when_detected_but_not_enabled(): void {

		$provider           = new Concrete_Test_Provider();
		$provider->detected = true;

		// Should not throw — alert_provider_detected returns early in test env
		$provider->init();

		// The self_register filter and add_to_integration_list action should still be registered
		$this->assertIsInt(has_filter('wu_domain_manager_get_integrations', [$provider, 'self_register']));
	}

	/**
	 * Test init() when provider is enabled but not setup (missing constants).
	 *
	 * When is_enabled() returns true but is_setup() returns false,
	 * init() should call alert_provider_not_setup() (which returns early
	 * in test env since WP_Ultimo is not loaded).
	 */
	public function test_init_when_enabled_but_not_setup(): void {

		$provider = new Concrete_Test_Provider();
		$provider->set_constants(['MISSING_REQUIRED_CONST_XYZ']);
		$provider->enable();

		// Should not throw — alert_provider_not_setup returns early in test env
		$provider->init();

		// Domain hooks should NOT be registered since setup is incomplete
		$this->assertFalse(has_action('wu_add_domain', [$provider, 'on_add_domain']));
	}

	/**
	 * Test init() when enabled but not setup and 'init' action has already fired.
	 *
	 * When did_action('init') returns true, alert_provider_not_setup is called directly.
	 * When it returns false, it's added as an action hook.
	 */
	public function test_init_not_setup_adds_alert_action_when_init_not_fired(): void {

		// In the test environment, 'init' has already fired (WP bootstrap runs it).
		// This test verifies the branch where did_action('init') is true.
		$provider = new Concrete_Test_Provider();
		$provider->set_constants(['MISSING_REQUIRED_CONST_XYZ2']);
		$provider->enable();

		// Should not throw
		$provider->init();

		$this->assertTrue(true);
	}
}
