<?php
/**
 * WebAuthn helper tests.
 *
 * @package WP_Ultimo
 * @subpackage Auth
 */

namespace WP_Ultimo\Auth;

class WebAuthn_Helper_Test extends \WP_UnitTestCase {

	public function test_get_rp_id_strips_port_from_bracketed_ipv6_host() {

		$previous_host          = $_SERVER['HTTP_HOST'] ?? null;
		$_SERVER['HTTP_HOST']   = '[::1]:8080';
		$helper                 = new WebAuthn_Helper();
		$restore_previous_host  = static function () use ($previous_host) {
			if (null === $previous_host) {
				unset($_SERVER['HTTP_HOST']);

				return;
			}

			$_SERVER['HTTP_HOST'] = $previous_host;
		};

		try {
			$this->assertSame('::1', $helper->get_rp_id());
		} finally {
			$restore_previous_host();
		}
	}

	public function test_get_rp_id_strips_port_from_hostname() {

		$previous_host          = $_SERVER['HTTP_HOST'] ?? null;
		$_SERVER['HTTP_HOST']   = 'example.com:8443';
		$helper                 = new WebAuthn_Helper();
		$restore_previous_host  = static function () use ($previous_host) {
			if (null === $previous_host) {
				unset($_SERVER['HTTP_HOST']);

				return;
			}

			$_SERVER['HTTP_HOST'] = $previous_host;
		};

		try {
			$this->assertSame('example.com', $helper->get_rp_id());
		} finally {
			$restore_previous_host();
		}
	}
}
