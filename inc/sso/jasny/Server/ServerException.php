<?php
/**
 * Exception thrown when the SSO server itself fails unexpectedly.
 *
 * In-tree vendored from jasny/sso. Should map to a 5xx HTTP response.
 *
 * @package WP_Ultimo
 * @subpackage SSO\Jasny\Server
 * @since 2.0.11
 */

declare(strict_types=1);

namespace WP_Ultimo\SSO\Jasny\Server;

defined('ABSPATH') || exit;

/**
 * Server-side internal error raised by the SSO server.
 */
class ServerException extends \RuntimeException implements ExceptionInterface {
}
