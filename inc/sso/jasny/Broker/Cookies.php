<?php
/**
 * Cookie-backed state handler for the broker.
 *
 * In-tree vendored from jasny/sso with the PHP-8 `#[\ReturnTypeWillChange]`
 * attributes folded in (previously delivered via cweagans/composer-patches).
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
 * Persist the client token via `$_COOKIE` / `setcookie()`.
 *
 * @implements \ArrayAccess<string,mixed>
 */
class Cookies implements \ArrayAccess {

	/**
	 * @var int
	 */
	protected $ttl;

	/**
	 * @var string
	 */
	protected $path;

	/**
	 * @var string
	 */
	protected $domain;

	/**
	 * @var bool
	 */
	protected $secure;

	/**
	 * Constructor.
	 *
	 * @param int    $ttl    Cookie TTL in seconds.
	 * @param string $path   Cookie path.
	 * @param string $domain Cookie domain.
	 * @param bool   $secure Whether to require HTTPS.
	 */
	public function __construct(int $ttl = 3600, string $path = '', string $domain = '', bool $secure = false) {
		$this->ttl    = $ttl;
		$this->path   = $path;
		$this->domain = $domain;
		$this->secure = $secure;
	}

	/**
	 * @inheritDoc
	 */
	#[\ReturnTypeWillChange]
	public function offsetSet($name, $value) {
		$success = setcookie($name, $value, time() + $this->ttl, $this->path, $this->domain, $this->secure, true);

		if ( ! $success) {
			throw new \RuntimeException("Failed to set cookie '$name'");
		}

		$_COOKIE[ $name ] = $value;
	}

	/**
	 * @inheritDoc
	 */
	public function offsetUnset($name): void {
		setcookie($name, '', 1, $this->path, $this->domain, $this->secure, true);
		unset($_COOKIE[ $name ]);
	}

	/**
	 * @inheritDoc
	 */
	#[\ReturnTypeWillChange]
	public function offsetGet($name) {
		return $_COOKIE[ $name ] ?? null;
	}

	/**
	 * @inheritDoc
	 */
	#[\ReturnTypeWillChange]
	public function offsetExists($name) {
		return isset($_COOKIE[ $name ]);
	}
}
