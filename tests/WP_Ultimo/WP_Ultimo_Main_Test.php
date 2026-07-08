<?php
/**
 * Unit tests for the main WP_Ultimo class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo;

/**
 * Unit tests for the main WP_Ultimo class.
 */
class WP_Ultimo_Main_Test extends \WP_UnitTestCase {

	/**
	 * Get the singleton instance.
	 *
	 * @return \WP_Ultimo
	 */
	protected function get_instance(): \WP_Ultimo {

		return \WP_Ultimo::get_instance();
	}

	/**
	 * Test singleton returns correct instance.
	 */
	public function test_singleton_returns_correct_instance(): void {

		$this->assertInstanceOf(\WP_Ultimo::class, $this->get_instance());
	}

	/**
	 * Test singleton returns same instance.
	 */
	public function test_singleton_returns_same_instance(): void {

		$this->assertSame(
			\WP_Ultimo::get_instance(),
			\WP_Ultimo::get_instance()
		);
	}

	/**
	 * Test VERSION constant is a valid semver string.
	 */
	public function test_version_constant_is_valid(): void {

		$this->assertIsString(\WP_Ultimo::VERSION);
		$this->assertNotEmpty(\WP_Ultimo::VERSION);

		// Should start with a digit
		$this->assertMatchesRegularExpression('/^\d+\.\d+/', \WP_Ultimo::VERSION);
	}

	/**
	 * Test version property matches VERSION constant.
	 */
	public function test_version_property_matches_constant(): void {

		$instance = $this->get_instance();

		$this->assertSame(\WP_Ultimo::VERSION, $instance->version);
	}

	/**
	 * Test LOG_HANDLE constant.
	 */
	public function test_log_handle_constant(): void {

		$this->assertSame('ultimate-multisite-core', \WP_Ultimo::LOG_HANDLE);
	}

	/**
	 * Test NETWORK_OPTION_SETUP_FINISHED constant.
	 */
	public function test_network_option_setup_finished_constant(): void {

		$this->assertSame('wu_setup_finished', \WP_Ultimo::NETWORK_OPTION_SETUP_FINISHED);
	}

	/**
	 * Test is_loaded returns boolean.
	 */
	public function test_is_loaded_returns_boolean(): void {

		$result = $this->get_instance()->is_loaded();

		$this->assertIsBool($result);
	}

	/**
	 * Test tables property is set after init.
	 */
	public function test_tables_property_is_set(): void {

		$instance = $this->get_instance();

		// Tables should be set (either array or Table_Loader instance)
		$this->assertNotNull($instance->tables);
	}

	/**
	 * Test register_addon_headers adds expected headers.
	 */
	public function test_register_addon_headers(): void {

		$instance = $this->get_instance();

		$headers = $instance->register_addon_headers([]);

		$this->assertContains('UM requires at least', $headers);
		$this->assertContains('UM tested up to', $headers);
	}

	/**
	 * Test register_addon_headers preserves existing headers.
	 */
	public function test_register_addon_headers_preserves_existing(): void {

		$instance = $this->get_instance();

		$existing = ['Existing Header'];
		$headers  = $instance->register_addon_headers($existing);

		$this->assertContains('Existing Header', $headers);
		$this->assertContains('UM requires at least', $headers);
		$this->assertCount(3, $headers);
	}

	/**
	 * Test grant_customer_capabilities returns allcaps unchanged for non-wu caps.
	 */
	public function test_grant_customer_capabilities_ignores_non_wu_caps(): void {

		$instance = $this->get_instance();

		$allcaps = ['manage_options' => true];
		$caps    = ['edit_posts'];
		$args    = [];
		$user    = (object) ['ID' => 1];

		$result = $instance->grant_customer_capabilities($allcaps, $caps, $args, $user);

		$this->assertSame($allcaps, $result);
	}

	/**
	 * Test grant_customer_capabilities adds wu_manage_membership for admin customers.
	 */
	public function test_grant_customer_capabilities_for_admin_customer(): void {

		$instance = $this->get_instance();

		// Create a user and customer
		$user_id  = self::factory()->user->create(['role' => 'administrator']);
		$customer = wu_create_customer([
			'user_id'            => $user_id,
			'email_verification' => 'none',
		]);

		if (is_wp_error($customer) || ! $customer) {
			$this->markTestSkipped('Could not create customer');
		}

		$allcaps = ['manage_options' => true];
		$caps    = ['wu_manage_membership'];
		$args    = [];
		$user    = get_userdata($user_id);

		$result = $instance->grant_customer_capabilities($allcaps, $caps, $args, $user);

		$this->assertArrayHasKey('wu_manage_membership', $result);
		$this->assertTrue($result['wu_manage_membership']);
	}

	/**
	 * Test grant_customer_capabilities does not add cap for non-admin.
	 */
	public function test_grant_customer_capabilities_skips_non_admin(): void {

		$instance = $this->get_instance();

		$allcaps = [];
		$caps    = ['wu_manage_membership'];
		$args    = [];
		$user    = (object) ['ID' => 999];

		$result = $instance->grant_customer_capabilities($allcaps, $caps, $args, $user);

		$this->assertArrayNotHasKey('wu_manage_membership', $result);
	}

	/**
	 * Test maybe_add_beta_param_to_update_url returns args unchanged when constant not defined.
	 */
	public function test_maybe_add_beta_param_returns_unchanged_without_constant(): void {

		$instance = $this->get_instance();

		$args = ['timeout' => 10];
		$url  = 'https://example.com/update';

		$result = $instance->maybe_add_beta_param_to_update_url($args, $url);

		$this->assertSame($args, $result);
	}

	/**
	 * Test maybe_inject_beta_update returns transient unchanged when not object.
	 */
	public function test_maybe_inject_beta_update_returns_non_object(): void {

		$instance = $this->get_instance();

		$result = $instance->maybe_inject_beta_update(false);

		$this->assertFalse($result);
	}

	/**
	 * Test maybe_inject_beta_update returns transient unchanged when beta disabled.
	 */
	public function test_maybe_inject_beta_update_returns_unchanged_when_disabled(): void {

		$instance = $this->get_instance();

		$transient = (object) ['response' => []];

		$result = $instance->maybe_inject_beta_update($transient);

		$this->assertSame($transient, $result);
	}

	/**
	 * Test get_addon_repository returns an Addon_Repository instance.
	 */
	public function test_get_addon_repository(): void {

		$instance = $this->get_instance();

		$repo = $instance->get_addon_repository();

		$this->assertInstanceOf(\WP_Ultimo\Addon_Repository::class, $repo);
	}

	/**
	 * Test get_addon_repository returns same instance on repeated calls.
	 */
	public function test_get_addon_repository_returns_same_instance(): void {

		$instance = $this->get_instance();

		$repo1 = $instance->get_addon_repository();
		$repo2 = $instance->get_addon_repository();

		$this->assertSame($repo1, $repo2);
	}
}
