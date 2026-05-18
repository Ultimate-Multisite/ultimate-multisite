<?php
/**
 * Exception thrown when a broker request to the SSO server is invalid.
 *
 * In-tree vendored from jasny/sso. Should map to a 4xx HTTP response.
 *
 * @package WP_Ultimo
 * @subpackage SSO\Jasny\Server
 * @since 2.0.11
 */

declare(strict_types=1);

namespace WP_Ultimo\SSO\Jasny\Server;

defined('ABSPATH') || exit;

/**
 * Broker-side error raised by the SSO server.
 */
class BrokerException extends \RuntimeException implements ExceptionInterface {
}
