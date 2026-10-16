<?php
/**
 * Tests for legacy pricing table template rendering.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Checkout\Signup_Fields;

use WP_UnitTestCase;
use WP_Ultimo\Checkout\Signup_Fields\Field_Templates\Pricing_Table\Legacy_Pricing_Table_Field_Template;
use WP_Ultimo\Models\Product;

class Legacy_Pricing_Table_Field_Template_Render_Test extends WP_UnitTestCase {

	private $original_scripts;

	protected function setUp(): void {
		parent::setUp();
		$this->original_scripts = wp_scripts();
		$GLOBALS['wp_scripts']  = new \WP_Scripts();
		wp_register_script('wu-checkout', false, [], 'test', true);
		wp_script_add_data('wu-checkout', 'after', []);
	}

	protected function tearDown(): void {
		$GLOBALS['wp_scripts'] = $this->original_scripts;
		parent::tearDown();
	}

	public static function empty_product_contexts(): array {
		return [
			'missing' => [[]],
			'null'    => [['products' => null]],
			'empty'   => [['products' => []]],
		];
	}

	/**
	 * @dataProvider empty_product_contexts
	 */
	public function test_empty_product_context_renders_existing_empty_state(array $attributes): void {
		$html = $this->render($attributes);

		$this->assertStringContainsString('No Products Found.', $html);
		$this->assertStringNotContainsString('add_plan(', $html);
		$this->assertCount(1, wp_scripts()->get_data('wu-checkout', 'after'));
	}

	public static function product_types(): array {
		return [
			'free'           => ['free', false],
			'recurring paid' => ['paid', true],
		];
	}

	/**
	 * @dataProvider product_types
	 */
	public function test_valid_products_preserve_selection_and_recurring_defaults(string $pricing_type, bool $recurring): void {
		$product = wu_create_product(
			[
				'name'          => 'Legacy render plan',
				'slug'          => 'legacy-render-plan-' . wp_rand(),
				'type'          => 'plan',
				'pricing_type'  => $pricing_type,
				'amount'        => $recurring ? 49 : 0,
				'recurring'     => $recurring,
				'duration'      => 1,
				'duration_unit' => 'month',
				'active'        => true,
			]
		);

		$this->assertInstanceOf(Product::class, $product);
		$this->assertSame($recurring, $product->is_recurring());

		$html    = $this->render(['products' => [$product]]);
		$scripts = wp_scripts()->get_data('wu-checkout', 'after');

		$this->assertStringContainsString('Legacy render plan', $html);
		$this->assertStringContainsString('add_plan(' . $product->get_id() . ')', $html);
		$this->assertStringNotContainsString('No Products Found.', $html);
		$this->assertCount($recurring ? 2 : 1, $scripts);

		if ($recurring) {
			$this->assertStringContainsString('49', $html);
			$this->assertStringContainsString('data.duration = 1;', $scripts[1]);
			$this->assertStringContainsString('data.duration_unit = "month";', $scripts[1]);
		} else {
			$this->assertStringContainsString('Free!', $html);
		}
	}

	private function render(array $attributes): string {
		$template     = new Legacy_Pricing_Table_Field_Template();
		$buffer_level = ob_get_level();

		try {
			return $template->render($attributes + ['force_different_durations' => false]);
		} finally {
			while (ob_get_level() > $buffer_level) {
				ob_end_clean();
			}
		}
	}
}
