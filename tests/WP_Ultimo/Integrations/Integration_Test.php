<?php

namespace WP_Ultimo\Integrations;

use WP_UnitTestCase;

class Integration_Test extends WP_UnitTestCase {

	private Integration $integration;

	public function setUp(): void {

		parent::setUp();

		$this->integration = new Integration('test-provider', 'Test Provider');
		$this->integration->set_constants(['CONST_A', 'CONST_B']);
		$this->integration->set_optional_constants(['CONST_C']);
		$this->integration->set_supports(['autossl', 'no-instructions']);
	}

	public function tearDown(): void {

		$this->integration->delete_credentials();

		parent::tearDown();
	}

	public function test_get_id(): void {

		$this->assertSame('test-provider', $this->integration->get_id());
	}

	public function test_get_title(): void {

		$this->assertSame('Test Provider', $this->integration->get_title());
	}

	public function test_get_set_description(): void {

		$this->assertSame('', $this->integration->get_description());

		$this->integration->set_description('A test provider');

		$this->assertSame('A test provider', $this->integration->get_description());
	}

	public function test_get_set_logo(): void {

		$this->integration->set_logo('https://example.com/logo.png');

		$this->assertSame('https://example.com/logo.png', $this->integration->get_logo());
	}

	public function test_get_set_tutorial_link(): void {

		$this->integration->set_tutorial_link('https://example.com/docs');

		$this->assertSame('https://example.com/docs', $this->integration->get_tutorial_link());
	}

	public function test_get_constants(): void {

		$this->assertSame(['CONST_A', 'CONST_B'], $this->integration->get_constants());
	}

	public function test_get_optional_constants(): void {

		$this->assertSame(['CONST_C'], $this->integration->get_optional_constants());
	}

	public function test_get_all_constants(): void {

		$all = $this->integration->get_all_constants();

		$this->assertContains('CONST_A', $all);
		$this->assertContains('CONST_B', $all);
		$this->assertContains('CONST_C', $all);
	}

	public function test_get_all_constants_with_grouped_constants(): void {

		$integration = new Integration('grouped', 'Grouped');
		$integration->set_constants([['ALT_A', 'ALT_B'], 'CONST_X']);

		$all = $integration->get_all_constants();

		$this->assertContains('ALT_A', $all);
		$this->assertContains('ALT_B', $all);
		$this->assertContains('CONST_X', $all);
	}

	public function test_supports(): void {

		$this->assertTrue($this->integration->supports('autossl'));
		$this->assertTrue($this->integration->supports('no-instructions'));
		$this->assertFalse($this->integration->supports('nonexistent'));
	}

	public function test_get_fields_returns_empty_by_default(): void {

		$this->assertSame([], $this->integration->get_fields());
	}

	public function test_detect_returns_false_by_default(): void {

		$this->assertFalse($this->integration->detect());
	}

	public function test_test_connection_returns_true_by_default(): void {

		$this->assertTrue($this->integration->test_connection());
	}

	public function test_enable_and_disable(): void {

		$this->assertFalse($this->integration->is_enabled());

		$this->integration->enable();
		$this->assertTrue($this->integration->is_enabled());

		$this->integration->disable();
		$this->assertFalse($this->integration->is_enabled());
	}

	public function test_save_and_get_credentials(): void {

		$this->integration->save_credentials([
			'CONST_A' => 'value_a',
			'CONST_B' => 'value_b',
		]);

		$this->assertSame('value_a', $this->integration->get_credential('CONST_A'));
		$this->assertSame('value_b', $this->integration->get_credential('CONST_B'));
	}

	public function test_save_credentials_ignores_unknown_keys(): void {

		$this->integration->save_credentials([
			'CONST_A'  => 'value_a',
			'UNKNOWN'  => 'should_be_ignored',
		]);

		$this->assertSame('value_a', $this->integration->get_credential('CONST_A'));
		$this->assertSame('', $this->integration->get_credential('UNKNOWN'));
	}

	public function test_delete_credentials(): void {

		$this->integration->save_credentials([
			'CONST_A' => 'value_a',
			'CONST_B' => 'value_b',
		]);

		$this->integration->delete_credentials();

		$this->assertSame('', $this->integration->get_credential('CONST_A'));
		$this->assertSame('', $this->integration->get_credential('CONST_B'));
	}

	public function test_is_setup_returns_false_when_missing_constants(): void {

		$this->assertFalse($this->integration->is_setup());
	}

	public function test_is_setup_returns_true_when_all_set(): void {

		$this->integration->save_credentials([
			'CONST_A' => 'a',
			'CONST_B' => 'b',
		]);

		$this->assertTrue($this->integration->is_setup());
	}

	public function test_is_setup_with_grouped_constants_accepts_either(): void {

		$integration = new Integration('grouped', 'Grouped');
		$integration->set_constants([['ALT_A', 'ALT_B']]);

		$this->assertFalse($integration->is_setup());

		$integration->save_credentials(['ALT_B' => 'val']);

		$this->assertTrue($integration->is_setup());
	}

	public function test_get_missing_constants(): void {

		// Verify a fresh integration reports all constants as missing
		$integration = new Integration('missing-test', 'Missing Test');
		$integration->set_constants(['MISSING_X', 'MISSING_Y']);

		$missing = $integration->get_missing_constants();

		$this->assertContains('MISSING_X', $missing);
		$this->assertContains('MISSING_Y', $missing);
	}

	public function test_get_constants_string(): void {

		$output = $this->integration->get_constants_string([
			'CONST_A' => 'my_key',
			'CONST_B' => 'my_secret',
		]);

		$this->assertStringContainsString("define( 'CONST_A', 'my_key' );", $output);
		$this->assertStringContainsString("define( 'CONST_B', 'my_secret' );", $output);
		$this->assertStringContainsString('Test Provider', $output);
	}

	public function test_get_explainer_lines_returns_empty_arrays(): void {

		$lines = $this->integration->get_explainer_lines();

		$this->assertArrayHasKey('will', $lines);
		$this->assertArrayHasKey('will_not', $lines);
		$this->assertIsArray($lines['will']);
		$this->assertIsArray($lines['will_not']);
	}

	public function test_fluent_setters_return_self(): void {

		$result = $this->integration
			->set_description('desc')
			->set_logo('logo')
			->set_tutorial_link('link')
			->set_constants([])
			->set_optional_constants([])
			->set_supports([]);

		$this->assertSame($this->integration, $result);
	}
}
