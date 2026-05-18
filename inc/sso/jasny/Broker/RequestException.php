<?php
/**
 * Exception raised when an SSO server request fails.
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
 * Generic broker-side request failure.
 */
class RequestException extends \RuntimeException {
}
