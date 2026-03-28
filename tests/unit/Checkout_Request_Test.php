<?php
use PHPUnit\Framework\TestCase;

final class Checkout_Request_Test extends TestCase {

    public function test_request_or_session_accepts_empty_string(): void {
        // Ensure empty string in request overrides any default/session value
        $_REQUEST['discount_code'] = '';

        $checkout = new \WP_Ultimo\Checkout\Checkout();

        $value = $checkout->request_or_session('discount_code', 'DEFAULT');

        $this->assertSame('', $value, 'Expected empty discount_code to be honored (clear code).');

        unset($_REQUEST['discount_code']);
    }

    /**
     * Verify that get_checkout_variables() always includes discount_code as a
     * string so wu_checkout.discount_code is never undefined in JS.
     *
     * An undefined value causes the Vue watcher to fire a spurious create_order()
     * call on page load when v-init sets the field to an empty string.
     */
    public function test_checkout_variables_always_has_discount_code_string(): void {

        $checkout = new \WP_Ultimo\Checkout\Checkout();

        $vars = $checkout->get_checkout_variables();

        $this->assertArrayHasKey('discount_code', $vars, 'discount_code key must always be present in checkout variables.');
        $this->assertIsString($vars['discount_code'], 'discount_code must always be a string (never undefined/null).');
    }
}
