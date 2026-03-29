<?php

namespace WP_Ultimo\Integrations\Providers\WPEngine;

use WP_UnitTestCase;

class WPEngine_Domain_Mapping_Test extends WP_UnitTestCase {

	private WPEngine_Domain_Mapping $module;
	private WPEngine_Integration $integration;

	public function setUp(): void {

		parent::setUp();

		$this->integration = $this->getMockBuilder(WPEngine_Integration::class)
			->onlyMethods(['load_dependencies'])
			->getMock();

		$this->module = new WPEngine_Domain_Mapping();
		$this->module->set_integration($this->integration);
	}

	public function test_get_capability_id(): void {

		$this->assertSame('domain-mapping', $this->module->get_capability_id());
	}

	public function test_get_title(): void {

		$this->assertNotEmpty($this->module->get_title());
	}

	public function test_does_not_support_autossl(): void {

		// WP Engine does not declare autossl support.
		$this->assertFalse($this->module->supports('autossl'));
	}

	public function test_get_explainer_lines_structure(): void {

		$lines = $this->module->get_explainer_lines();

		$this->assertArrayHasKey('will', $lines);
		$this->assertNotEmpty($lines['will']);
		$this->assertArrayHasKey('will_not', $lines);
	}

	public function test_register_hooks_adds_actions(): void {

		$this->module->register_hooks();

		$this->assertIsInt(has_action('wu_add_domain', [$this->module, 'on_add_domain']));
		$this->assertIsInt(has_action('wu_remove_domain', [$this->module, 'on_remove_domain']));
		$this->assertIsInt(has_action('wu_add_subdomain', [$this->module, 'on_add_subdomain']));
		$this->assertIsInt(has_action('wu_remove_subdomain', [$this->module, 'on_remove_subdomain']));
	}

	public function test_on_add_domain_loads_dependencies(): void {

		$this->integration->expects($this->once())
			->method('load_dependencies');

		// WPE_API class is not available in test env — on_add_domain returns early.
		$this->module->on_add_domain('example.com', 1);
	}

	public function test_on_remove_domain_loads_dependencies(): void {

		$this->integration->expects($this->once())
			->method('load_dependencies');

		$this->module->on_remove_domain('example.com', 1);
	}

	public function test_on_add_subdomain_delegates_to_on_add_domain(): void {

		// Both should call load_dependencies once each.
		$this->integration->expects($this->once())
			->method('load_dependencies');

		$this->module->on_add_subdomain('sub.example.com', 1);
	}

	public function test_on_remove_subdomain_delegates_to_on_remove_domain(): void {

		$this->integration->expects($this->once())
			->method('load_dependencies');

		$this->module->on_remove_subdomain('sub.example.com', 1);
	}

	public function test_test_connection_delegates_to_integration(): void {

		$this->integration->expects($this->once())
			->method('load_dependencies');

		$result = $this->module->test_connection();

		// WPE_API not available in test env — returns WP_Error.
		$this->assertNotNull($result);
	}
}
