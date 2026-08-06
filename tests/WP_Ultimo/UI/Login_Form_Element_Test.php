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

	/**
	 * Test passwordless fallback renders the regular login form.
	 */
	public function test_passwordless_fallback_renders_regular_login_form(): void {

		$element         = Login_Form_Element::get_instance();
		$setting         = wu_get_setting('use_passwordless_login', 0);
		$current_user_id = get_current_user_id();

		try {
			wp_set_current_user(0);
			wu_save_setting('use_passwordless_login', 1);
			unset($_GET['wu_password_fallback'], $_REQUEST['wu_password_fallback']);

			$element->setup();

			$this->assertStringContainsString('wu-passwordless-auth', $element->get_content([]));

			$_GET['wu_password_fallback']     = '1';
			$_REQUEST['wu_password_fallback'] = '1';

			$markup = $element->get_content([]);

			$this->assertStringContainsString('name="pwd"', $markup);
			$this->assertStringNotContainsString('wu-passwordless-auth', $markup);
		} finally {
			wu_save_setting('use_passwordless_login', $setting);
			wp_set_current_user($current_user_id);
			unset($_GET['wu_password_fallback'], $_REQUEST['wu_password_fallback']);
		}
	}
}
