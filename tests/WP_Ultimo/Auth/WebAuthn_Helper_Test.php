<?php
/**
 * WebAuthn helper tests.
 *
 * @package WP_Ultimo
 * @subpackage Auth
 */

namespace WP_Ultimo\Auth;

class WebAuthn_Helper_Test extends \WP_UnitTestCase {

	protected $previous_host;

	public function setUp(): void {

		parent::setUp();

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$this->previous_host = $_SERVER['HTTP_HOST'] ?? null;
	}

	public function tearDown(): void {

		if (null === $this->previous_host) {
			unset($_SERVER['HTTP_HOST']);
		} else {
			$_SERVER['HTTP_HOST'] = $this->previous_host;
		}

		parent::tearDown();
	}

	public function test_get_rp_id_strips_port_from_bracketed_ipv6_host() {

		$_SERVER['HTTP_HOST'] = '[::1]:8080';
		$helper               = new WebAuthn_Helper();

		$this->assertSame('::1', $helper->get_rp_id());
	}

	public function test_get_rp_id_strips_port_from_hostname() {

		$_SERVER['HTTP_HOST'] = 'example.com:8443';
		$helper               = new WebAuthn_Helper();

		$this->assertSame('example.com', $helper->get_rp_id());
	}
}
