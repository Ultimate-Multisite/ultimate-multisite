<?php
/**
 * The Session Handler.
 *
 * This class is responsible for setting up the
 * session of the SSO user, and resuming it
 * when the user is redirected back to the original
 * broker site.
 *
 * @package WP_Ultimo
 * @subpackage SSO
 * @since 2.0.11
 */

namespace WP_Ultimo\SSO;

use WP_Ultimo\SSO\Jasny\Server\BrokerException;
use WP_Ultimo\SSO\Jasny\Server\SessionInterface;
use WP_Ultimo\SSO\Jasny\Server\ServerException;
use WP_Ultimo\SSO\Exception\SSO_Session_Exception;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * The SSO session handler.
 *
 * @since 2.0.11
 */
class SSO_Session_Handler implements SessionInterface {

	/**
	 * The SSO manager instance.
	 *
	 * @since 2.0.11
	 * @var SSO
	 */
	public $sso_manager;

	/**
	 * Build the handler with the SSO manager.
	 *
	 * @param ?SSO $sso_manager The sso manager.
	 *
	 * @since 2.0.11
	 */
	public function __construct(?SSO $sso_manager = null) {
		$this->sso_manager = $sso_manager;
	}

	/**
	 * Returns the session id.
	 *
	 * @since 2.0.11
	 */
	public function getId(): string {
		return $this->sso_manager->input('broker');
	}

	/**
	 * Start a new session.
	 *
	 * @since 2.0.11
	 *
	 * @throws SSO_Session_Exception If session can't be started.
	 */
	public function start(): void {

		$site_hash = $this->sso_manager->input('broker');

		if ( ! get_current_user_id()) {
			throw new SSO_Session_Exception('User not logged in.', 401);
		}

		$id = $this->sso_manager->decode($site_hash, $this->sso_manager->salt());

		set_site_transient("sso-{$site_hash}-{$id}", get_current_user_id(), 180);
	}

	/**
	 * Resume an existing session.
	 *
	 * @since 2.0.11
	 *
	 * @throws ServerException If session can't be started.
	 * @throws BrokerException If session is expired.
	 *
	 * @param string $id The session id.
	 */
	public function resume(string $id): void {

		$decoded_id = $this->sso_manager->decode($id, $this->sso_manager->salt());

		$user_id = get_site_transient("sso-{$id}-{$decoded_id}");

		if ($user_id) {
			$this->sso_manager->set_target_user_id($user_id);
		}
	}

	/**
	 * Check if a session is active. (status PHP_SESSION_ACTIVE).
	 *
	 * @see session_status()
	 *
	 * @since 2.0.11
	 */
	public function isActive(): bool {
		return false;
	}
}
