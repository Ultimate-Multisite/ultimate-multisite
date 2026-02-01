<?php
/**
 * Tests that all provider Integration subclasses are properly configured.
 */

namespace WP_Ultimo\Integrations;

use WP_UnitTestCase;

class Provider_Integration_Test extends WP_UnitTestCase {

	/**
	 * @dataProvider provider_list
	 */
	public function test_integration_has_correct_id(string $id): void {

		$registry    = Integration_Registry::get_instance();
		$integration = $registry->get($id);

		$this->assertNotNull($integration);
		$this->assertSame($id, $integration->get_id());
	}

	/**
	 * @dataProvider provider_list
	 */
	public function test_integration_has_title(string $id): void {

		$integration = Integration_Registry::get_instance()->get($id);

		$this->assertNotEmpty($integration->get_title());
	}

	/**
	 * @dataProvider provider_list
	 */
	public function test_integration_has_tutorial_link(string $id): void {

		$integration = Integration_Registry::get_instance()->get($id);

		$this->assertNotEmpty($integration->get_tutorial_link());
		$this->assertStringStartsWith('https://', $integration->get_tutorial_link());
	}

	/**
	 * @dataProvider provider_list
	 */
	public function test_integration_has_logo(string $id): void {

		$integration = Integration_Registry::get_instance()->get($id);

		$this->assertNotEmpty($integration->get_logo());
	}

	/**
	 * @dataProvider provider_list
	 */
	public function test_domain_mapping_module_has_correct_capability_id(string $id): void {

		$registry     = Integration_Registry::get_instance();
		$capabilities = $registry->get_capabilities($id);

		$found = false;

		foreach ($capabilities as $module) {
			if ($module->get_capability_id() === 'domain-mapping') {
				$found = true;

				break;
			}
		}

		$this->assertTrue($found, "No domain-mapping module for $id");
	}

	/**
	 * @dataProvider provider_list
	 */
	public function test_domain_mapping_module_has_explainer_lines(string $id): void {

		$registry     = Integration_Registry::get_instance();
		$capabilities = $registry->get_capabilities($id);

		foreach ($capabilities as $module) {
			if ($module->get_capability_id() === 'domain-mapping') {
				$lines = $module->get_explainer_lines();

				$this->assertArrayHasKey('will', $lines);
				$this->assertArrayHasKey('will_not', $lines);

				return;
			}
		}
	}

	/**
	 * @dataProvider provider_list
	 */
	public function test_domain_mapping_module_registers_hooks(string $id): void {

		$registry     = Integration_Registry::get_instance();
		$capabilities = $registry->get_capabilities($id);

		foreach ($capabilities as $module) {
			if ($module->get_capability_id() === 'domain-mapping') {
				$module->register_hooks();

				$this->assertIsInt(has_action('wu_add_domain', [$module, 'on_add_domain']));
				$this->assertIsInt(has_action('wu_remove_domain', [$module, 'on_remove_domain']));
				$this->assertIsInt(has_action('wu_add_subdomain', [$module, 'on_add_subdomain']));
				$this->assertIsInt(has_action('wu_remove_subdomain', [$module, 'on_remove_subdomain']));

				return;
			}
		}
	}

	public static function provider_list(): array {

		return [
			'closte'      => ['closte'],
			'cloudways'   => ['cloudways'],
			'runcloud'    => ['runcloud'],
			'cpanel'      => ['cpanel'],
			'serverpilot' => ['serverpilot'],
			'gridpane'    => ['gridpane'],
			'cloudflare'  => ['cloudflare'],
			'hestia'      => ['hestia'],
			'enhance'     => ['enhance'],
			'rocket'      => ['rocket'],
			'wpengine'    => ['wpengine'],
			'wpmudev'     => ['wpmudev'],
		];
	}
}
