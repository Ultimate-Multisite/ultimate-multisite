<?php
/**
 * Tests for checkout form functions.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Functions;

use WP_UnitTestCase;

/**
 * Test class for checkout form functions.
 */
class Checkout_Form_Functions_Test extends WP_UnitTestCase {

	/**
	 * Test wu_get_checkout_form returns false for invalid ID.
	 */
	public function test_wu_get_checkout_form_returns_false_for_invalid_id(): void {

		$result = wu_get_checkout_form(999999);

		$this->assertFalse($result);
	}

	/**
	 * Test wu_get_checkout_forms returns array.
	 */
	public function test_wu_get_checkout_forms_returns_array(): void {

		$result = wu_get_checkout_forms();

		$this->assertIsArray($result);
	}

	/**
	 * Test wu_get_checkout_form_by_slug returns false for empty slug.
	 */
	public function test_wu_get_checkout_form_by_slug_empty(): void {

		$result = wu_get_checkout_form_by_slug('');

		$this->assertFalse($result);
	}

	/**
	 * Test wu_get_checkout_form_by_slug with wu-checkout slug.
	 */
	public function test_wu_get_checkout_form_by_slug_wu_checkout(): void {

		$result = wu_get_checkout_form_by_slug('wu-checkout');

		$this->assertInstanceOf(\WP_Ultimo\Models\Checkout_Form::class, $result);
	}

	/**
	 * Test wu_get_checkout_form_by_slug with wu-add-new-site slug.
	 */
	public function test_wu_get_checkout_form_by_slug_add_new_site(): void {

		$result = wu_get_checkout_form_by_slug('wu-add-new-site');

		$this->assertInstanceOf(\WP_Ultimo\Models\Checkout_Form::class, $result);
	}

	/**
	 * Test wu_get_checkout_form_by_slug with wu-finish-checkout slug.
	 */
	public function test_wu_get_checkout_form_by_slug_finish_checkout(): void {

		$result = wu_get_checkout_form_by_slug('wu-finish-checkout');

		$this->assertInstanceOf(\WP_Ultimo\Models\Checkout_Form::class, $result);
	}

	/**
	 * Test wu_get_checkout_form_by_slug with wu-pay-invoice slug.
	 */
	public function test_wu_get_checkout_form_by_slug_pay_invoice(): void {

		$result = wu_get_checkout_form_by_slug('wu-pay-invoice');

		$this->assertInstanceOf(\WP_Ultimo\Models\Checkout_Form::class, $result);
	}

	/**
	 * Test wu_get_checkout_form_by_slug with nonexistent slug.
	 */
	public function test_wu_get_checkout_form_by_slug_nonexistent(): void {

		$result = wu_get_checkout_form_by_slug('nonexistent-form-slug');

		$this->assertFalse($result);
	}

	/**
	 * Test wu_get_checkout_form_by_slug filter for addon forms.
	 */
	public function test_wu_get_checkout_form_by_slug_addon_filter(): void {

		$mock_form = new \WP_Ultimo\Models\Checkout_Form();

		add_filter(
			'wu_get_checkout_form_by_slug',
			function ($form, $slug) use ($mock_form) {
				if ('addon-form' === $slug) {
					return $mock_form;
				}
				return $form;
			},
			10,
			2
		);

		$result = wu_get_checkout_form_by_slug('addon-form');

		$this->assertInstanceOf(\WP_Ultimo\Models\Checkout_Form::class, $result);

		remove_all_filters('wu_get_checkout_form_by_slug');
	}

	/**
	 * Test wu_create_checkout_form creates a form.
	 */
	public function test_wu_create_checkout_form(): void {

		$form = wu_create_checkout_form([
			'name' => 'Test Form',
			'slug' => 'test-form-' . wp_rand(),
		]);

		$this->assertNotWPError($form);
		$this->assertInstanceOf(\WP_Ultimo\Models\Checkout_Form::class, $form);
	}

	/**
	 * Test wu_form_field_request_arg for template_selection type.
	 */
	public function test_wu_form_field_request_arg_template_selection(): void {

		$field = [
			'type' => 'template_selection',
			'id'   => 'my_field',
		];

		$result = wu_form_field_request_arg($field);

		$this->assertSame('template_id', $result);
	}

	/**
	 * Test wu_form_field_request_arg for pricing_table type.
	 */
	public function test_wu_form_field_request_arg_pricing_table(): void {

		$field = [
			'type' => 'pricing_table',
			'id'   => 'my_field',
		];

		$result = wu_form_field_request_arg($field);

		$this->assertSame('products', $result);
	}

	/**
	 * Test wu_form_field_request_arg for other types.
	 */
	public function test_wu_form_field_request_arg_other_type(): void {

		$field = [
			'type' => 'text',
			'id'   => 'my_custom_field',
		];

		$result = wu_form_field_request_arg($field);

		$this->assertSame('my_custom_field', $result);
	}

	/**
	 * Test wu_should_hide_form_field returns false when not preselected.
	 */
	public function test_wu_should_hide_form_field_not_preselected(): void {

		$field = [
			'type'                        => 'text',
			'id'                          => 'some_field',
			'hide_text_when_pre_selected' => '0',
		];

		$result = wu_should_hide_form_field($field);

		$this->assertFalse($result);
	}
}
