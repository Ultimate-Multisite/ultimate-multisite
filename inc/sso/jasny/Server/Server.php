<?php
/**
 * Single sign-on server.
 *
 * In-tree vendored from jasny/sso 0.4.x, with the PSR-16 cache dependency
 * removed: broker-token -> session-id mappings are persisted directly via
 * WordPress site transients (`wu_sso_*`). The PSR-16 wrapper (formerly
 * `WordPress_Simple_Cache`) caused fatal signature collisions with other
 * plugins that ship psr/simple-cache v3.x; vendoring jasny/sso in-tree
 * lets us avoid that interface entirely.
 *
 * @package WP_Ultimo
 * @subpackage SSO\Jasny\Server
 * @since 2.0.11
 */

declare(strict_types=1);

namespace WP_Ultimo\SSO\Jasny\Server;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use WP_Ultimo\SSO\Jasny\With;

defined('ABSPATH') || exit;

/**
 * SSO server.
 *
 * Responsible for managing user sessions on the SSO host so brokers can
 * exchange bearer tokens for live sessions.
 */
class Server {

	use With;

	/**
	 * Transient key prefix for broker-session mappings.
	 *
	 * Kept aligned with the prefix used by the previous
	 * `WordPress_Simple_Cache('wu_sso_')` wrapper so existing transients
	 * remain addressable across the upgrade.
	 */
	private const CACHE_PREFIX = 'wu_sso_';

	/**
	 * Callback to look up broker info by id.
	 *
	 * @var \Closure
	 */
	protected $getBrokerInfo;

	/**
	 * @var LoggerInterface
	 */
	protected $logger;

	/**
	 * Pluggable session handler.
	 *
	 * @var SessionInterface
	 */
	protected $session;

	/**
	 * Constructor.
	 *
	 * @phpstan-param callable(string):?array{secret:string,domains:string[]} $getBrokerInfo
	 *
	 * @param callable $getBrokerInfo Lookup callback returning broker info by id.
	 */
	public function __construct(callable $getBrokerInfo) {
		$this->getBrokerInfo = \Closure::fromCallable($getBrokerInfo);

		$this->logger  = new NullLogger();
		$this->session = new GlobalSession();
	}

	/**
	 * Get a copy of the service with logging.
	 *
	 * @param LoggerInterface $logger PSR-3 logger instance.
	 * @return static
	 */
	public function withLogger(LoggerInterface $logger): self {
		return $this->withProperty('logger', $logger);
	}

	/**
	 * Get a copy of the service with a custom session service.
	 *
	 * @param SessionInterface $session Session handler.
	 * @return static
	 */
	public function withSession(SessionInterface $session): self {
		return $this->withProperty('session', $session);
	}


	/**
	 * Start the session for broker requests to the SSO server.
	 *
	 * @param ServerRequestInterface|null $request Optional PSR-7 request.
	 * @throws BrokerException If the bearer is invalid or unmapped.
	 * @throws ServerException If a session is already active.
	 */
	public function startBrokerSession(?ServerRequestInterface $request = null): void {
		if ($this->session->isActive()) {
			throw new ServerException('Session is already started', 500);
		}

		$bearer = $this->getBearerToken($request);

		[$brokerId, $token, $checksum] = $this->parseBearer($bearer);

		$sessionId = $this->cacheGet($this->getCacheKey($brokerId, $token));

		if (null === $sessionId) {
			$this->logger->warning(
				"Bearer token isn't attached to a client session",
				['broker' => $brokerId, 'token' => $token]
			);
			throw new BrokerException("Bearer token isn't attached to a client session", 403);
		}

		$code = $this->getVerificationCode($brokerId, $token, $sessionId);
		$this->validateChecksum($checksum, 'bearer', $brokerId, $token, $code);

		$this->session->resume($sessionId);

		$this->logger->debug(
			'Broker request with session',
			['broker' => $brokerId, 'token' => $token, 'session' => $sessionId]
		);
	}

	/**
	 * Get bearer token from Authorization header.
	 *
	 * @param ServerRequestInterface|null $request Optional PSR-7 request.
	 */
	protected function getBearerToken(?ServerRequestInterface $request = null): string {
		$authorization = null === $request
			? ($_SERVER['HTTP_AUTHORIZATION'] ?? '') // @codeCoverageIgnore
			: $request->getHeaderLine('Authorization');

		[$type, $token] = explode(' ', $authorization, 2) + ['', ''];

		if ('Bearer' !== $type) {
			$this->logger->warning("Broker didn't use bearer authentication: "
				. ('' === $authorization ? "No 'Authorization' header" : "$type authorization used"));
			throw new BrokerException("Broker didn't use bearer authentication", 401);
		}

		return $token;
	}

	/**
	 * Get the broker id and token from the bearer token used by the broker.
	 *
	 * @param string $bearer Raw bearer token of the form `SSO-<broker>-<token>-<checksum>`.
	 * @return string[]
	 * @throws BrokerException If the bearer is malformed.
	 */
	protected function parseBearer(string $bearer): array {
		$matches = null;

		if ( ! (bool) preg_match('/^SSO-(\w*+)-(\w*+)-([a-z0-9]*+)$/', $bearer, $matches)) {
			$this->logger->warning('Invalid bearer token', ['bearer' => $bearer]);
			throw new BrokerException('Invalid bearer token', 403);
		}

		return array_slice($matches, 1);
	}

	/**
	 * Generate cache key for linking the broker token to the client session.
	 *
	 * @param string $brokerId Broker id.
	 * @param string $token    Per-attach token.
	 */
	protected function getCacheKey(string $brokerId, string $token): string {
		return "SSO-{$brokerId}-{$token}";
	}

	/**
	 * Read a previously stored broker -> session id mapping.
	 *
	 * Backs the storage with `get_site_transient()` so this works under
	 * a single-site install or any subsite without colliding with regular
	 * post/option caches. Returns `null` (not `false`) on miss to keep the
	 * same null-sentinel semantics the original PSR-16 backed code used.
	 *
	 * @param string $key Logical cache key as built by `getCacheKey()`.
	 * @return string|null Stored session id, or null when absent.
	 */
	protected function cacheGet(string $key): ?string {
		$raw = get_site_transient(self::CACHE_PREFIX . $key);

		// We deliberately stored a wrapped array so an empty string can be
		// distinguished from a miss. Anything else means "not set".
		if ( ! is_array($raw) || ! array_key_exists('v', $raw)) {
			return null;
		}

		return is_string($raw['v']) ? $raw['v'] : (string) $raw['v'];
	}

	/**
	 * Persist a broker -> session id mapping.
	 *
	 * Stored with no expiration (`$expiration = 0`) which mirrors the
	 * behaviour of the previous PSR-16 wrapper when no TTL was supplied.
	 *
	 * @param string $key   Logical cache key as built by `getCacheKey()`.
	 * @param string $value Session id to bind to the token.
	 * @return bool True on success, false on failure.
	 */
	protected function cacheSet(string $key, string $value): bool {
		return (bool) set_site_transient(self::CACHE_PREFIX . $key, ['v' => $value], 0);
	}

	/**
	 * Get the broker secret using the configured callback.
	 *
	 * @param string $brokerId Broker id.
	 */
	protected function getBrokerSecret(string $brokerId): ?string {
		return ($this->getBrokerInfo)($brokerId)['secret'] ?? null;
	}

	/**
	 * Generate the verification code based on the token using the server secret.
	 *
	 * @param string $brokerId  Broker id.
	 * @param string $token     Per-attach token.
	 * @param string $sessionId Server session id.
	 */
	protected function getVerificationCode(string $brokerId, string $token, string $sessionId): string {
		return base_convert(hash('sha256', $brokerId . $token . $sessionId), 16, 36);
	}

	/**
	 * Generate checksum for a broker command.
	 *
	 * @param string $command  Command name (e.g. `attach`, `bearer`).
	 * @param string $brokerId Broker id.
	 * @param string $token    Per-attach token.
	 * @throws BrokerException If the broker is unknown.
	 */
	protected function generateChecksum(string $command, string $brokerId, string $token): string {
		$secret = $this->getBrokerSecret($brokerId);

		if (null === $secret) {
			$this->logger->warning('Unknown broker', ['broker' => $brokerId, 'token' => $token]);
			throw new BrokerException('Broker is unknown or disabled', 403);
		}

		return base_convert(hash_hmac('sha256', $command . ':' . $token, $secret), 16, 36);
	}

	/**
	 * Assert that the checksum matches the expected checksum.
	 *
	 * @param string      $checksum Received checksum.
	 * @param string      $command  Command name.
	 * @param string      $brokerId Broker id.
	 * @param string      $token    Per-attach token.
	 * @param string|null $code     Optional verification code to include.
	 * @throws BrokerException If the checksum does not match.
	 */
	protected function validateChecksum(
		string $checksum,
		string $command,
		string $brokerId,
		string $token,
		?string $code = null
	): void {
		$expected = $this->generateChecksum($command . (null !== $code ? ":$code" : ''), $brokerId, $token);

		if ( ! hash_equals($expected, $checksum)) {
			$this->logger->warning(
				"Invalid $command checksum",
				['expected' => $expected, 'received' => $checksum, 'broker' => $brokerId, 'token' => $token]
					+ (null !== $code ? ['verification_code' => $code] : [])
			);
			throw new BrokerException("Invalid $command checksum", 403);
		}
	}

	/**
	 * Validate that the URL has a domain that is allowed for the broker.
	 *
	 * @param string      $type     Origin / referer / return_url label, used in logs.
	 * @param string      $url      URL to inspect.
	 * @param string      $brokerId Broker id.
	 * @param string|null $token    Optional per-attach token for logging.
	 * @throws BrokerException If the host is not allow-listed for the broker.
	 */
	public function validateDomain(string $type, string $url, string $brokerId, ?string $token = null): void {
		$domains = ($this->getBrokerInfo)($brokerId)['domains'] ?? [];
		$host    = parse_url($url, PHP_URL_HOST);

		if ( ! in_array($host, $domains, true)) {
			$this->logger->warning(
				"Domain of $type is not allowed for broker",
				[$type => $url, 'broker' => $brokerId] + (null !== $token ? ['token' => $token] : [])
			);
			throw new BrokerException("Domain of $type is not allowed", 400);
		}
	}

	/**
	 * Attach a client session to a broker session.
	 *
	 * Returns the verification code for the freshly-attached pair.
	 *
	 * @param ServerRequestInterface|null $request Optional PSR-7 request.
	 * @throws BrokerException If the attach request is invalid.
	 * @throws ServerException If persisting the mapping fails.
	 */
	public function attach(?ServerRequestInterface $request = null): string {
		['broker' => $brokerId, 'token' => $token] = $this->processAttachRequest($request);

		$this->session->start();

		$this->assertNotAttached($brokerId, $token);

		$key    = $this->getCacheKey($brokerId, $token);
		$cached = $this->cacheSet($key, $this->session->getId());

		$info = ['broker' => $brokerId, 'token' => $token, 'session' => $this->session->getId()];

		if ( ! $cached) {
			$this->logger->error('Failed to attach bearer token to session id due to cache issue', $info);
			throw new ServerException('Failed to attach bearer token to session id', 500);
		}

		$this->logger->info('Attached broker token to session', $info);

		return $this->getVerificationCode($brokerId, $token, $this->session->getId());
	}

	/**
	 * Assert that the token isn't already attached to a different session.
	 *
	 * @param string $brokerId Broker id.
	 * @param string $token    Per-attach token.
	 * @throws BrokerException If the token is already attached elsewhere.
	 */
	protected function assertNotAttached(string $brokerId, string $token): void {
		$key      = $this->getCacheKey($brokerId, $token);
		$attached = $this->cacheGet($key);

		if (null !== $attached && $attached !== $this->session->getId()) {
			$this->logger->warning(
				'Token is already attached',
				[
					'broker'      => $brokerId,
					'token'       => $token,
					'attached_to' => $attached,
					'session'     => $this->session->getId(),
				]
			);
			throw new BrokerException('Token is already attached', 400);
		}
	}

	/**
	 * Validate attach request and return broker id and token.
	 *
	 * @param ServerRequestInterface|null $request Optional PSR-7 request.
	 * @return array{broker:string,token:string}
	 * @throws BrokerException If a required parameter is missing or invalid.
	 */
	protected function processAttachRequest(?ServerRequestInterface $request): array {
		$brokerId = $this->getRequiredQueryParam($request, 'broker');
		$token    = $this->getRequiredQueryParam($request, 'token');
		$checksum = $this->getRequiredQueryParam($request, 'checksum');

		$this->validateChecksum($checksum, 'attach', $brokerId, $token);

		$origin = $this->getHeader($request, 'Origin');
		if ('' !== $origin) {
			$this->validateDomain('origin', $origin, $brokerId, $token);
		}

		$referer = $this->getHeader($request, 'Referer');
		if ('' !== $referer) {
			$this->validateDomain('referer', $referer, $brokerId, $token);
		}

		$returnUrl = $this->getQueryParam($request, 'return_url');
		if (null !== $returnUrl) {
			$this->validateDomain('return_url', $returnUrl, $brokerId, $token);
		}

		return ['broker' => $brokerId, 'token' => $token];
	}

	/**
	 * Get query parameter from PSR-7 request or $_GET.
	 *
	 * @param ServerRequestInterface|null $request Optional PSR-7 request.
	 * @param string                      $key     Query parameter name.
	 */
	protected function getQueryParam(?ServerRequestInterface $request, string $key): ?string {
		$params = null === $request
			? $_GET // @codeCoverageIgnore
			: $request->getQueryParams();

		return $params[$key] ?? null;
	}

	/**
	 * Get required query parameter from PSR-7 request or $_GET.
	 *
	 * @param ServerRequestInterface|null $request Optional PSR-7 request.
	 * @param string                      $key     Query parameter name.
	 * @throws BrokerException If the query parameter isn't set.
	 */
	protected function getRequiredQueryParam(?ServerRequestInterface $request, string $key): string {
		$value = $this->getQueryParam($request, $key);

		if (null === $value) {
			throw new BrokerException("Missing '$key' query parameter", 400);
		}

		return $value;
	}

	/**
	 * Get HTTP Header from PSR-7 request or $_SERVER.
	 *
	 * @param ServerRequestInterface|null $request Optional PSR-7 request.
	 * @param string                      $key     Header name.
	 */
	protected function getHeader(?ServerRequestInterface $request, string $key): string {
		return null === $request
			? ($_SERVER['HTTP_' . str_replace('-', '_', strtoupper($key))] ?? '') // @codeCoverageIgnore
			: $request->getHeaderLine($key);
	}
}
