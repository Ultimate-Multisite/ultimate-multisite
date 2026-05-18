<?php
/**
 * Session contract used by the in-tree SSO server.
 *
 * In-tree vendored from jasny/sso.
 *
 * @package WP_Ultimo
 * @subpackage SSO\Jasny\Server
 * @since 2.0.11
 */

declare(strict_types=1);

namespace WP_Ultimo\SSO\Jasny\Server;

defined('ABSPATH') || exit;

/**
 * Pluggable session handler contract.
 */
interface SessionInterface {

	/**
	 * Get the current session id.
	 *
	 * @see session_id()
	 */
	public function getId(): string;

	/**
	 * Start a new session.
	 *
	 * @see session_start()
	 * @throws ServerException If the session can't be started.
	 */
	public function start(): void;

	/**
	 * Resume an existing session by id.
	 *
	 * @param string $id Session id to resume.
	 * @throws ServerException If the session can't be started.
	 * @throws BrokerException If the session has expired.
	 */
	public function resume(string $id): void;

	/**
	 * Check whether a session is active (PHP_SESSION_ACTIVE).
	 *
	 * @see session_status()
	 */
	public function isActive(): bool;
}
