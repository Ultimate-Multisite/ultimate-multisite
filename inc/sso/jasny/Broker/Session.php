<?php
/**
 * `$_SESSION`-backed state handler for the broker.
 *
 * In-tree vendored from jasny/sso.
 *
 * @package WP_Ultimo
 * @subpackage SSO\Jasny\Broker
 * @since 2.0.11
 *
 * @codeCoverageIgnore
 */

declare(strict_types=1);

namespace WP_Ultimo\SSO\Jasny\Broker;

defined('ABSPATH') || exit;

/**
 * Persist the client token via PHP's global `$_SESSION` superglobal.
 *
 * @implements \ArrayAccess<string,mixed>
 */
class Session implements \ArrayAccess {

	/**
	 * @inheritDoc
	 */
	public function offsetSet($name, $value): void {
		$_SESSION[ $name ] = $value;
	}

	/**
	 * @inheritDoc
	 */
	public function offsetUnset($name): void {
		unset($_SESSION[ $name ]);
	}

	/**
	 * @inheritDoc
	 */
	public function offsetGet(mixed $name): mixed {
		return $_SESSION[ $name ] ?? null;
	}

	/**
	 * @inheritDoc
	 */
	public function offsetExists($name): bool {
		return isset($_SESSION[ $name ]);
	}
}
