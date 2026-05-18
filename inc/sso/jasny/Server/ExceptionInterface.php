<?php
/**
 * Marker interface for SSO server exceptions.
 *
 * In-tree vendored from jasny/sso. Do not edit hand-side; if upstream changes
 * are needed, update this file directly and note the divergence.
 *
 * @package WP_Ultimo
 * @subpackage SSO\Jasny\Server
 * @since 2.0.11
 */

declare(strict_types=1);

namespace WP_Ultimo\SSO\Jasny\Server;

defined('ABSPATH') || exit;

/**
 * Shared base for SSO server exception classes.
 */
interface ExceptionInterface {

	/**
	 * Get the exception message.
	 *
	 * @return string
	 */
	public function getMessage();

	/**
	 * Get the exception code.
	 *
	 * @return int
	 */
	public function getCode();
}
