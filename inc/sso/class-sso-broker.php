<?php
/**
 * The broker class.
 *
 * The broker implementation has a bug on the
 * method used to generate the attach url.
 *
 * @package WP_Ultimo
 * @subpackage SSO
 * @since 2.0.11
 */

namespace WP_Ultimo\SSO;

use WP_Ultimo\SSO\Jasny\Broker\Broker;

defined('ABSPATH') || exit;

/**
 * The SSO Broker implementation.
 *
 * @since 2.0.11
 */
class SSO_Broker extends Broker {

	/**
	 * Checks if the current SSO call is a must-redirect call.
	 *
	 * @since 2.0.11
	 * @return boolean
	 */
	public function is_must_redirect_call(): bool {
		return $this->getVerificationCode() === 'must-redirect';
	}

	/**
	 * Get URL to attach session at SSO server.
	 *
	 * @param array<string,mixed> $params The params to be passed.
	 */
	public function getAttachUrl(array $params = []): string {

		if ($this->getToken() === null) {
			$this->generateToken();
		}

		$data = [
			'broker'   => $this->broker,
			'token'    => $this->getToken(),
			'checksum' => $this->generateChecksum('attach'),
		];

		return add_query_arg($data + $params, $this->url);
	}
}
