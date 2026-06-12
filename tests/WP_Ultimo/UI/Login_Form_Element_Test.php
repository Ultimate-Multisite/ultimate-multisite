<?php
/**
 * Tests for Login_Form_Element class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\UI;

use WP_UnitTestCase;

/**
 * Test class for Login_Form_Element.
 */
class Login_Form_Element_Test extends WP_UnitTestCase {

	/**
	 * Test passwordless fallback URL includes the password escape hatch flag.
	 */
	public function test_passwordless_fallback_url_preserves_redirect_and_escape_hatch(): void {

		$element     = Login_Form_Element::get_instance();
		$reflection  = new \ReflectionClass($element);
		$method      = $reflection->getMethod('get_passwordless_fallback_url');
		$redirect_to = network_home_url('/wp-admin/?from=custom-login');

		$method->setAccessible(true);

		$fallback_url = $method->invoke($element, $redirect_to);
		$query        = [];

		parse_str((string) wp_parse_url($fallback_url, PHP_URL_QUERY), $query);

		$this->assertSame('1', $query['wu_password_fallback']);
		$this->assertSame($redirect_to, $query['redirect_to']);
	}
}
