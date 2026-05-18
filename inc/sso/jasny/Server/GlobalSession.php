<?php
/**
 * Default SessionInterface implementation backed by PHP's session_* functions.
 *
 * In-tree vendored from jasny/sso. WP-Ultimo replaces this with
 * {@see \WP_Ultimo\SSO\SSO_Session_Handler} via Server::withSession() at runtime,
 * but the class stays here so the bare Server() construction still works.
 *
 * @package WP_Ultimo
 * @subpackage SSO\Jasny\Server
 * @since 2.0.11
 *
 * @codeCoverageIgnore
 */

declare(strict_types=1);

namespace WP_Ultimo\SSO\Jasny\Server;

defined('ABSPATH') || exit;

/**
 * PHP-session backed implementation of SessionInterface.
 */
class GlobalSession implements SessionInterface {

	/**
	 * Options passed to session_start().
	 *
	 * @var array<string,mixed>
	 */
	protected $options;

	/**
	 * Constructor.
	 *
	 * @param array<string,mixed> $options Options forwarded to session_start().
	 */
	public function __construct(array $options = []) {
		$this->options = $options + [
			'cookie_samesite' => 'None',
			'cookie_secure'   => true,
		];
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return session_id();
	}

	/**
	 * @inheritDoc
	 */
	public function start(): void {
		$started = session_status() !== PHP_SESSION_ACTIVE
			? session_start($this->options)
			: true;

		if ( ! $started) {
			$err = error_get_last() ?? ['message' => 'Failed to start session'];
			throw new ServerException($err['message'], 500);
		}

		// Session shouldn't be empty when resumed.
		$_SESSION['_sso_init'] = 1;
	}

	/**
	 * @inheritDoc
	 */
	public function resume(string $id): void {
		session_id($id);
		$started = session_start($this->options);

		if ( ! $started) {
			$err = error_get_last() ?? ['message' => 'Failed to start session'];
			throw new ServerException($err['message'], 500);
		}

		if ([] === $_SESSION) {
			session_abort();
			throw new BrokerException('Session has expired. Client must attach with new token.', 401);
		}
	}

	/**
	 * @inheritDoc
	 */
	public function isActive(): bool {
		return session_status() === PHP_SESSION_ACTIVE;
	}
}
