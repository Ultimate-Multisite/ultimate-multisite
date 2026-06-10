<?php
/**
 * Tests for Checkout_Element cache safety.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\UI;

use WP_UnitTestCase;

/**
 * Test class for checkout element cache controls.
 */
class Checkout_Element_Test extends WP_UnitTestCase {

	/**
	 * @var Checkout_Element
	 */
	private $element;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->element = Checkout_Element::get_instance();
	}

	/**
	 * Test defaults include disabled deferred checkout mode.
	 */
	public function test_defaults_include_deferred_checkout_options(): void {
		$defaults = $this->element->defaults();

		$this->assertArrayHasKey('defer', $defaults);
		$this->assertArrayHasKey('defer_trigger', $defaults);
		$this->assertFalse($defaults['defer']);
		$this->assertSame('viewport', $defaults['defer_trigger']);
	}

	/**
	 * Test fields expose deferred checkout controls.
	 */
	public function test_fields_include_deferred_checkout_controls(): void {
		$fields = $this->element->fields();

		$this->assertArrayHasKey('defer', $fields);
		$this->assertArrayHasKey('defer_trigger', $fields);
		$this->assertSame('toggle', $fields['defer']['type']);
		$this->assertSame('select', $fields['defer_trigger']['type']);
		$this->assertArrayHasKey('viewport', $fields['defer_trigger']['options']);
		$this->assertArrayHasKey('click', $fields['defer_trigger']['options']);
	}

	/**
	 * Test deferred output renders a cache-safe placeholder instead of live checkout markup.
	 */
	public function test_deferred_output_renders_placeholder_without_nocache_action(): void {
		$before = did_action('wu_checkout_nocache_required');

		ob_start();

		$this->element->output(
			[
				'slug'                   => 'main-form',
				'step'                   => false,
				'display_title'          => false,
				'membership_limitations' => [],
				'defer'                  => true,
				'defer_trigger'          => 'click',
			]
		);

		$html = ob_get_clean();

		$this->assertSame($before, did_action('wu_checkout_nocache_required'));
		$this->assertStringContainsString('data-wu-checkout-deferred="1"', $html);
		$this->assertStringContainsString('wu_render_checkout', $html);
		$this->assertStringContainsString('Start checkout', $html);
	}

	/**
	 * Test live checkout output path contains explicit no-cache safeguards.
	 */
	public function test_source_contains_live_checkout_cache_safeguards(): void {
		// Source-token assertions intentionally guard cache-safety hooks/headers that
		// are otherwise hard to observe reliably in PHPUnit.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$source = file_get_contents(dirname(__DIR__, 3) . '/inc/ui/class-checkout-element.php');

		$this->assertStringContainsString('DONOTCACHEPAGE', $source);
		$this->assertStringContainsString('nocache_headers()', $source);
		$this->assertStringContainsString('wu_checkout_nocache_required', $source);
		$this->assertStringContainsString('litespeed_control_set_nocache', $source);
		$this->assertStringContainsString('X-Accel-Expires', $source);
		$this->assertStringContainsString('CDN-Cache-Control', $source);
		$this->assertStringContainsString('Surrogate-Control', $source);
		$this->assertStringContainsString('X-LiteSpeed-Cache-Control', $source);
	}
}
