<?php
/**
 * Single sign-on broker.
 *
 * In-tree vendored from jasny/sso. The broker lives on the website visited
 * by the user; it has no credentials of its own and instead talks to the SSO
 * server on behalf of the user to verify credentials and pull user info.
 *
 * @package WP_Ultimo
 * @subpackage SSO\Jasny\Broker
 * @since 2.0.11
 */

declare(strict_types=1);

namespace WP_Ultimo\SSO\Jasny\Broker;

use WP_Ultimo\SSO\Jasny\With;

defined('ABSPATH') || exit;

/**
 * SSO broker.
 *
 * Sits on a member website and exchanges bearer tokens with the SSO server.
 */
class Broker {

	use With;

	/**
	 * URL of SSO server.
	 *
	 * @var string
	 */
	protected $url;

	/**
	 * My identifier, given by SSO provider.
	 *
	 * @var string
	 */
	protected $broker;

	/**
	 * My secret word, given by SSO provider.
	 *
	 * @var string
	 */
	protected $secret;

	/**
	 * @var bool
	 */
	protected $initialized = false;

	/**
	 * Session token of the client.
	 *
	 * @var string|null
	 */
	protected $token;

	/**
	 * Verification code returned by the server.
	 *
	 * @var string|null
	 */
	protected $verificationCode;

	/**
	 * @var \ArrayAccess<string,mixed>
	 */
	protected $state;

	/**
	 * @var Curl
	 */
	protected $curl;

	/**
	 * Constructor.
	 *
	 * @param string $url    URL of the SSO server.
	 * @param string $broker Broker id, assigned by the SSO server.
	 * @param string $secret Shared secret, assigned by the SSO server.
	 */
	public function __construct(string $url, string $broker, string $secret) {
		if ( ! (bool) preg_match('~^https?://~', $url)) {
			throw new \InvalidArgumentException("Invalid SSO server URL '$url'");
		}

		if ((bool) preg_match('/\W/', $broker)) {
			throw new \InvalidArgumentException("Invalid broker id '$broker': must be alphanumeric");
		}

		$this->url    = $url;
		$this->broker = $broker;
		$this->secret = $secret;

		$this->state = new Cookies();
	}

	/**
	 * Get a copy with a different handler for the user state (cookie or session).
	 *
	 * @param \ArrayAccess<string,mixed> $handler State backend.
	 * @return static
	 */
	public function withTokenIn(\ArrayAccess $handler): self {
		return $this->withProperty('state', $handler);
	}

	/**
	 * Set a custom wrapper for cURL.
	 *
	 * @param Curl $curl cURL wrapper.
	 * @return static
	 */
	public function withCurl(Curl $curl): self {
		return $this->withProperty('curl', $curl);
	}

	/**
	 * Get the wrapped cURL helper, lazily creating one if absent.
	 */
	protected function getCurl(): Curl {
		if ( ! isset($this->curl)) {
			$this->curl = new Curl(); // @codeCoverageIgnore
		}

		return $this->curl;
	}

	/**
	 * Get the broker identifier.
	 */
	public function getBrokerId(): string {
		return $this->broker;
	}

	/**
	 * Read token/verification code from the configured state handler.
	 */
	protected function initialize(): void {
		if ($this->initialized) {
			return;
		}

		$this->token            = $this->state[ $this->getCookieName('token') ] ?? null;
		$this->verificationCode = $this->state[ $this->getCookieName('verify') ] ?? null;
		$this->initialized      = true;
	}

	/**
	 * Get the current session token, lazily reading state on first call.
	 */
	protected function getToken(): ?string {
		$this->initialize();

		return $this->token;
	}

	/**
	 * Get the verification code returned by the server.
	 */
	protected function getVerificationCode(): ?string {
		$this->initialize();

		return $this->verificationCode;
	}

	/**
	 * Cookie / state-key name for a given type.
	 *
	 * The broker name is part of the key so multiple brokers can coexist on
	 * the same domain without colliding.
	 *
	 * @param string $type Either `token` or `verify`.
	 */
	protected function getCookieName(string $type): string {
		$brokerName = preg_replace('/[_\W]+/', '_', strtolower($this->broker));

		return "sso_{$type}_{$brokerName}";
	}

	/**
	 * Generate the bearer token used to authenticate against the SSO server.
	 *
	 * @throws NotAttachedException If the broker hasn't yet been attached.
	 */
	public function getBearerToken(): string {
		$token            = $this->getToken();
		$verificationCode = $this->getVerificationCode();

		if (null === $verificationCode) {
			throw new NotAttachedException("The client isn't attached to the SSO server for this broker. "
				. "Make sure that the '" . $this->getCookieName('verify') . "' cookie is set.");
		}

		return "SSO-{$this->broker}-{$token}-" . $this->generateChecksum("bearer:$verificationCode");
	}

	/**
	 * Generate a fresh session token and persist it in state.
	 */
	protected function generateToken(): void {
		$this->token = base_convert(bin2hex(random_bytes(32)), 16, 36);

		$this->state[ $this->getCookieName('token') ] = $this->token;
	}

	/**
	 * Clear the locally-cached session token and verification code.
	 */
	public function clearToken(): void {
		unset($this->state[ $this->getCookieName('token') ]);
		unset($this->state[ $this->getCookieName('verify') ]);

		$this->token            = null;
		$this->verificationCode = null;
	}

	/**
	 * Whether the broker is currently attached (has a verification code).
	 */
	public function isAttached(): bool {
		return null !== $this->getVerificationCode();
	}

	/**
	 * Build the URL the user should be redirected to in order to attach.
	 *
	 * @param array<string,mixed> $params Extra query parameters.
	 */
	public function getAttachUrl(array $params = []): string {
		if (null === $this->getToken()) {
			$this->generateToken();
		}

		$data = [
			'broker'   => $this->broker,
			'token'    => $this->getToken(),
			'checksum' => $this->generateChecksum('attach'),
		];

		return $this->url . '?' . http_build_query($data + $params);
	}

	/**
	 * Verify attaching to the SSO server by accepting the verification code.
	 *
	 * @param string $code Verification code returned by the server.
	 */
	public function verify(string $code): void {
		$this->initialize();

		if ($this->verificationCode === $code) {
			return;
		}

		if (null !== $this->verificationCode) {
			trigger_error('SSO attach already verified', E_USER_WARNING);

			return;
		}

		$this->verificationCode = $code;

		$this->state[ $this->getCookieName('verify') ] = $code;
	}

	/**
	 * Generate a checksum for a broker command.
	 *
	 * @param string $command Command name.
	 */
	protected function generateChecksum(string $command): string {
		return base_convert(hash_hmac('sha256', $command . ':' . $this->token, $this->secret), 16, 36);
	}

	/**
	 * Build the absolute request URL for a given path and query params.
	 *
	 * @param string                     $path   Path relative to the SSO server.
	 * @param array<string,mixed>|string $params Query parameters or pre-encoded string.
	 */
	protected function getRequestUrl(string $path, $params = ''): string {
		$query = is_array($params) ? http_build_query($params) : $params;

		if ('' === $path) {
			throw new \InvalidArgumentException('Request path cannot be empty');
		}

		$base = '/' === $path[0]
			? preg_replace('~^(\w+://[^/]+).*~', '$1', $this->url)
			: preg_replace('~/[^/]*$~', '', $this->url);

		return $base . '/' . ltrim($path, '/') . ('' !== $query ? '?' . $query : '');
	}


	/**
	 * Send an HTTP request to the SSO server.
	 *
	 * @param string                     $method HTTP method (`GET`, `POST`, `DELETE`).
	 * @param string                     $path   Path relative to the SSO server.
	 * @param array<string,mixed>|string $data   Query or post payload.
	 * @return mixed
	 * @throws RequestException On transport or response failure.
	 */
	public function request(string $method, string $path, $data = '') {
		$url     = $this->getRequestUrl($path, 'POST' === $method ? '' : $data);
		$headers = [
			'Accept: application/json',
			'Authorization: Bearer ' . $this->getBearerToken(),
		];

		['httpCode' => $httpCode, 'contentType' => $contentType, 'body' => $body] =
			$this->getCurl()->request($method, $url, $headers, 'POST' === $method ? $data : '');

		return $this->handleResponse($httpCode, $contentType, $body);
	}

	/**
	 * Decode and validate the cURL response.
	 *
	 * @param int         $httpCode HTTP status code.
	 * @param string|null $ctHeader Content-Type header value.
	 * @param string      $body     Raw response body.
	 * @return mixed Decoded JSON payload, or null for 204.
	 * @throws RequestException If the body isn't JSON or the server signalled an error.
	 */
	protected function handleResponse(int $httpCode, $ctHeader, string $body) {
		if (204 === $httpCode) {
			return null;
		}

		[$contentType] = explode(';', $ctHeader, 2);

		if ('application/json' !== $contentType) {
			throw new RequestException(
				"Expected 'application/json' response, got '$contentType'",
				500,
				new RequestException($body, $httpCode)
			);
		}

		try {
			$data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
		} catch (\JsonException $exception) {
			throw new RequestException('Invalid JSON response from server', 500, $exception);
		}

		if ($httpCode >= 400) {
			throw new RequestException($data['error'] ?? $body, $httpCode);
		}

		return $data;
	}
}
