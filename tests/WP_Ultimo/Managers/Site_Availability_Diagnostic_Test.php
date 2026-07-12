<?php
/**
 * Unit tests for tenant availability diagnostics.
 *
 * @package WP_Ultimo\Tests\Managers
 */

namespace WP_Ultimo\Tests\Managers;

use WP_Ultimo\Managers\Site_Manager;

class Site_Availability_Diagnostic_Test extends \WP_UnitTestCase {

	/**
	 * Test the availability diagnostic is registered as a read-only ability.
	 */
	public function test_registers_site_availability_diagnostic_ability(): void {
		if ( ! function_exists('wp_get_ability')) {
			$this->markTestSkipped('Abilities API is not available.');
		}

		$manager = Site_Manager::get_instance();

		$this->setExpectedIncorrectUsage('wp_register_ability');
		$manager->register_ability_category();
		$manager->register_abilities();

		$ability = wp_get_ability('multisite-ultimate/site-availability-diagnose');

		$this->assertNotNull($ability);
		$this->assertTrue($ability->get_meta_item('annotations')['readonly']);
	}

	/**
	 * Test mapped-domain lookup resolves the owning site without reporting a
	 * visit lock when visit limiting is disabled.
	 */
	public function test_availability_diagnostic_resolves_mapped_domain(): void {
		wu_save_setting('enable_visits_limiting', false);

		$blog_id = self::factory()->blog->create();
		$domain  = wu_create_domain(
			[
				'blog_id'        => $blog_id,
				'domain'         => 'availability-' . wp_rand() . '.example.com',
				'active'         => true,
				'primary_domain' => true,
				'secure'         => true,
				'stage'          => 'done',
			]
		);

		$this->assertNotWPError($domain);

		$result = Site_Manager::get_instance()->mcp_diagnose_site_availability(['domain' => $domain->get_domain()]);

		$this->assertNotWPError($result);
		$this->assertSame($blog_id, $result['site']['blog_id']);
		$this->assertSame($domain->get_domain(), $result['domain_mapping']['domain']);
		$this->assertTrue($result['domain_mapping']['active']);
		$this->assertTrue($result['domain_mapping']['primary_domain']);
		$this->assertTrue($result['frontend_available']);
		$this->assertSame('none', $result['lock_reason']);

		$domain->delete();
		wp_delete_site($blog_id);
	}
}
