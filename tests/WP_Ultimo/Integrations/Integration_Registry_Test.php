<?php

namespace WP_Ultimo\Integrations;

use WP_UnitTestCase;

class Integration_Registry_Test extends WP_UnitTestCase {

	private Integration_Registry $registry;

	public function setUp(): void {

		parent::setUp();

		// Get fresh instance for each test
		$this->registry = Integration_Registry::get_instance();
	}

	public function test_get_instance_returns_singleton(): void {

		$a = Integration_Registry::get_instance();
		$b = Integration_Registry::get_instance();

		$this->assertSame($a, $b);
	}

	public function test_core_integrations_are_registered(): void {

		$this->assertNotNull($this->registry->get('closte'));
		$this->assertNotNull($this->registry->get('cloudways'));
		$this->assertNotNull($this->registry->get('runcloud'));
		$this->assertNotNull($this->registry->get('cpanel'));
		$this->assertNotNull($this->registry->get('serverpilot'));
		$this->assertNotNull($this->registry->get('gridpane'));
		$this->assertNotNull($this->registry->get('cloudflare'));
		$this->assertNotNull($this->registry->get('hestia'));
		$this->assertNotNull($this->registry->get('enhance'));
		$this->assertNotNull($this->registry->get('rocket'));
		$this->assertNotNull($this->registry->get('wpengine'));
		$this->assertNotNull($this->registry->get('wpmudev'));
		$this->assertNotNull($this->registry->get('hostinger'));
	}

	public function test_get_returns_null_for_unknown(): void {

		$this->assertNull($this->registry->get('nonexistent'));
	}

	public function test_get_all_returns_array(): void {

		$all = $this->registry->get_all();

		$this->assertIsArray($all);
		$this->assertNotEmpty($all);
	}

	public function test_each_integration_has_domain_mapping_capability(): void {

		$domain_mapping_integrations = [
			'closte',
			'cloudways',
			'runcloud',
			'cpanel',
			'serverpilot',
			'gridpane',
			'cloudflare',
			'hestia',
			'enhance',
			'plesk',
			'rocket',
			'wpengine',
			'wpmudev',
			'bunnynet',
			'laravel-forge',
			'cyberpanel',
			'hostinger',
		];

		foreach ($this->registry->get_all() as $integration) {
			if ( ! in_array($integration->get_id(), $domain_mapping_integrations, true)) {
				continue;
			}

			$capabilities = $this->registry->get_capabilities($integration->get_id());

			$found = false;

			foreach ($capabilities as $module) {
				if ($module->get_capability_id() === 'domain-mapping') {
					$found = true;

					break;
				}
			}

			$this->assertTrue($found, sprintf('Integration %s missing domain-mapping capability', $integration->get_id()));
		}
	}

	public function test_email_integrations_have_transactional_email_capability(): void {

		$email_integrations = ['amazon-ses', 'oracle-oci'];

		foreach ($email_integrations as $integration_id) {
			$capabilities = $this->registry->get_capabilities($integration_id);
			$found        = false;

			foreach ($capabilities as $module) {
				if ($module->get_capability_id() === 'transactional-email') {
					$found = true;

					break;
				}
			}

			$this->assertTrue($found, sprintf('Integration %s missing transactional-email capability', $integration_id));
		}
	}

	public function test_capability_modules_have_integration_set(): void {

		foreach ($this->registry->get_all() as $integration) {
			foreach ($this->registry->get_capabilities($integration->get_id()) as $module) {
				$this->assertSame(
					$integration,
					$module->get_integration(),
					sprintf('Module %s for %s has wrong integration reference', $module->get_capability_id(), $integration->get_id())
				);
			}
		}
	}

	public function test_get_integrations_with_capability(): void {

		$results = $this->registry->get_integrations_with_capability('domain-mapping');

		$this->assertNotEmpty($results);

		foreach ($results as $integration) {
			$this->assertInstanceOf(Integration::class, $integration);
		}
	}
}
