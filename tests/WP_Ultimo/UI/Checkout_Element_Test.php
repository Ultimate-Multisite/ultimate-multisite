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
	 * Test block attributes remain available when checkout scripts are registered during rendering.
	 */
	public function test_display_retains_block_slug_for_late_script_registration(): void {
		$slug = 'custom-css-form-' . wp_generate_uuid4();
		$form = wu_create_checkout_form(
			[
				'name'       => 'Custom CSS Form',
				'slug'       => $slug,
				'custom_css' => '.wu-custom-css-test { color: red; }',
			]
		);

		$this->assertNotWPError($form);

		$property                       = new \ReflectionProperty($this->element, 'pre_loaded_attributes');
		$original_pre_loaded_attributes = $property->getValue($this->element);

		if ( ! wp_style_is('wu-checkout', 'registered')) {
			wp_register_style('wu-checkout', false, [], 'test');
		}

		wp_enqueue_style('wu-checkout');

		$skip_output = static function () {
			return true;
		};

		add_filter('wu_checkout_skip_output', $skip_output);

		try {
			$property->setValue($this->element, false);

			$this->element->display(['slug' => $slug]);

			$this->element->register_scripts();

			$styles = wp_styles()->get_data('wu-checkout', 'after');

			$this->assertSame($slug, $this->element->get_pre_loaded_attribute('slug'));
			$this->assertIsArray($styles);
			$this->assertStringContainsString('.wu_checkout_form_' . $slug . ' .wu-custom-css-test', implode("\n", $styles));
		} finally {
			remove_filter('wu_checkout_skip_output', $skip_output);
			$property->setValue($this->element, $original_pre_loaded_attributes);
		}
	}

	/**
	 * Test checkout scripts use the default form when attributes were not pre-loaded.
	 */
	public function test_register_scripts_uses_default_slug_without_pre_loaded_attributes(): void {
		$custom_css = '.wu-default-custom-css-test { color: red; }';
		$form       = wu_get_checkout_form_by_slug('main-form');
		$old_css    = false;

		if (! $form) {
			$form = wu_create_checkout_form(
				[
					'name'       => 'Main Form',
					'slug'       => 'main-form',
					'custom_css' => $custom_css,
				]
			);
		} else {
			$old_css = $form->get_custom_css();
			$form->set_custom_css($custom_css);
			$form->save();
		}

		$this->assertNotWPError($form);

		$property                       = new \ReflectionProperty($this->element, 'pre_loaded_attributes');
		$original_pre_loaded_attributes = $property->getValue($this->element);

		if ( ! wp_style_is('wu-checkout', 'registered')) {
			wp_register_style('wu-checkout', false, [], 'test');
		}

		wp_enqueue_style('wu-checkout');

		try {
			$property->setValue($this->element, false);

			$this->element->register_scripts();

			$styles = wp_styles()->get_data('wu-checkout', 'after');

			$this->assertIsArray($styles);
			$this->assertStringContainsString('.wu_checkout_form_main-form .wu-default-custom-css-test', implode("\n", $styles));
		} finally {
			$property->setValue($this->element, $original_pre_loaded_attributes);

			if (false !== $old_css) {
				$form->set_custom_css($old_css);
				$form->save();
			}
		}
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
