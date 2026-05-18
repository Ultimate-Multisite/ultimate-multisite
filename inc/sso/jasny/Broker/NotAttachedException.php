<?php
/**
 * Exception raised when a request is made while no SSO session is attached.
 *
 * In-tree vendored from jasny/sso.
 *
 * @package WP_Ultimo
 * @subpackage SSO\Jasny\Broker
 * @since 2.0.11
 */

declare(strict_types=1);

namespace WP_Ultimo\SSO\Jasny\Broker;

defined('ABSPATH') || exit;

/**
 * Thrown when the broker tries to talk to the server without an attached token.
 */
class NotAttachedException extends \RuntimeException {
}
