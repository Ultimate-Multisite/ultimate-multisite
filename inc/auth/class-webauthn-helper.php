<?php
/**
 * WebAuthn helper methods.
 *
 * @package WP_Ultimo
 * @subpackage Auth
 * @since 2.13.2
 */

namespace WP_Ultimo\Auth;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Small WebAuthn helper focused on passkey login and registration.
 *
 * @since 2.13.2
 */
class WebAuthn_Helper {

	/**
	 * Base64 URL encodes binary data.
	 *
	 * @since 2.13.2
	 * @param string $data Binary data.
	 * @return string
	 */
	public function base64url_encode($data) {

		return rtrim(strtr(base64_encode($data), '+/', '-_'), '='); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Base64 URL decodes a string.
	 *
	 * @since 2.13.2
	 * @param string $data Encoded data.
	 * @return string|false
	 */
	public function base64url_decode($data) {

		$data      = (string) $data;
		$remainder = strlen($data) % 4;

		if ($remainder) {
			$data .= str_repeat('=', 4 - $remainder);
		}

		return base64_decode(strtr($data, '-_', '+/'), true); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
	}

	/**
	 * Generates a WebAuthn challenge.
	 *
	 * @since 2.13.2
	 * @return string
	 */
	public function generate_challenge() {

		return $this->base64url_encode(random_bytes(32));
	}

	/**
	 * Returns the relying party ID for the current request.
	 *
	 * @since 2.13.2
	 * @return string
	 */
	public function get_rp_id() {

		$host = isset($_SERVER['HTTP_HOST']) ? strtolower(sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST']))) : '';

		if (empty($host)) {
			$host = (string) wp_parse_url(home_url(), PHP_URL_HOST);
		}

		$host = (string) preg_replace('/^(\[[0-9a-f:]+\]):\d+$|^([^\[\]]+):\d+$/i', '$1$2', $host);

		return trim($host, '[]');
	}

	/**
	 * Returns the current origin, including a port when present.
	 *
	 * @since 2.13.2
	 * @return string
	 */
	public function get_origin() {

		$host = isset($_SERVER['HTTP_HOST']) ? strtolower(sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST']))) : '';

		if (empty($host)) {
			$host = (string) wp_parse_url(home_url(), PHP_URL_HOST);
		}

		return (is_ssl() ? 'https://' : 'http://') . $host;
	}

	/**
	 * Validates an origin against the challenge origin and RP ID.
	 *
	 * @since 2.13.2
	 * @param string $origin          Client supplied origin.
	 * @param string $expected_origin Origin stored with the challenge.
	 * @param string $rp_id           Expected RP ID.
	 * @return bool
	 */
	public function is_origin_allowed($origin, $expected_origin, $rp_id) {

		$origin          = $this->normalize_origin($origin);
		$expected_origin = $this->normalize_origin($expected_origin);

		if (empty($origin) || empty($expected_origin) || $origin !== $expected_origin) {
			return false;
		}

		$scheme = (string) wp_parse_url($origin, PHP_URL_SCHEME);
		$host   = (string) wp_parse_url($origin, PHP_URL_HOST);

		if ($host !== $rp_id) {
			return false;
		}

		if ('https' === $scheme) {
			return true;
		}

		return $this->is_local_development_host($host);
	}

	/**
	 * Normalizes an origin for comparison.
	 *
	 * @since 2.13.2
	 * @param string $origin Origin URL.
	 * @return string
	 */
	public function normalize_origin($origin) {

		$scheme = (string) wp_parse_url($origin, PHP_URL_SCHEME);
		$host   = (string) wp_parse_url($origin, PHP_URL_HOST);
		$port   = wp_parse_url($origin, PHP_URL_PORT);

		if (empty($scheme) || empty($host)) {
			return '';
		}

		$normalized = strtolower($scheme) . '://' . strtolower($host);

		if ($port) {
			$normalized .= ':' . absint($port);
		}

		return $normalized;
	}

	/**
	 * Parses clientDataJSON.
	 *
	 * @since 2.13.2
	 * @param string $client_data_json Encoded clientDataJSON.
	 * @param string $expected_type    Expected WebAuthn type.
	 * @return array|\WP_Error
	 */
	public function parse_client_data($client_data_json, $expected_type) {

		$decoded = $this->base64url_decode($client_data_json);

		if (false === $decoded) {
			return new \WP_Error('invalid_client_data', __('Invalid WebAuthn client data.', 'ultimate-multisite'));
		}

		$data = json_decode($decoded, true);

		if ( ! is_array($data) || empty($data['type']) || empty($data['challenge']) || empty($data['origin'])) {
			return new \WP_Error('invalid_client_data', __('Invalid WebAuthn client data.', 'ultimate-multisite'));
		}

		if ($expected_type !== $data['type']) {
			return new \WP_Error('invalid_client_type', __('Unexpected WebAuthn response type.', 'ultimate-multisite'));
		}

		$data['_raw'] = $decoded;

		return $data;
	}

	/**
	 * Parses an attestation object and extracts credential data.
	 *
	 * @since 2.13.2
	 * @param string $attestation_object Encoded attestation object.
	 * @return array|\WP_Error
	 */
	public function parse_attestation_object($attestation_object) {

		$decoded = $this->base64url_decode($attestation_object);

		if (false === $decoded) {
			return new \WP_Error('invalid_attestation', __('Invalid passkey registration response.', 'ultimate-multisite'));
		}

		try {
			$offset = 0;
			$object = $this->decode_cbor_item($decoded, $offset);
		} catch (\Throwable $exception) {
			return new \WP_Error('invalid_attestation', __('Invalid passkey registration response.', 'ultimate-multisite'));
		}

		if ( ! is_array($object) || empty($object['authData'])) {
			return new \WP_Error('invalid_attestation', __('Invalid passkey registration response.', 'ultimate-multisite'));
		}

		return $this->parse_authenticator_data($object['authData'], true);
	}

	/**
	 * Parses assertion authenticator data.
	 *
	 * @since 2.13.2
	 * @param string $authenticator_data Encoded authenticator data.
	 * @return array|\WP_Error
	 */
	public function parse_assertion_authenticator_data($authenticator_data) {

		$decoded = $this->base64url_decode($authenticator_data);

		if (false === $decoded) {
			return new \WP_Error('invalid_authenticator_data', __('Invalid passkey authentication response.', 'ultimate-multisite'));
		}

		return $this->parse_authenticator_data($decoded, false);
	}

	/**
	 * Verifies an assertion signature.
	 *
	 * @since 2.13.2
	 * @param string $authenticator_data Encoded authenticator data.
	 * @param string $client_data_raw    Raw clientDataJSON.
	 * @param string $signature          Encoded signature.
	 * @param string $public_key         PEM public key.
	 * @return bool
	 */
	public function verify_assertion_signature($authenticator_data, $client_data_raw, $signature, $public_key) {

		$raw_authenticator_data = $this->base64url_decode($authenticator_data);
		$raw_signature          = $this->base64url_decode($signature);

		if (false === $raw_authenticator_data || false === $raw_signature) {
			return false;
		}

		$signed_data = $raw_authenticator_data . hash('sha256', $client_data_raw, true);
		$verified    = openssl_verify($signed_data, $raw_signature, $public_key, OPENSSL_ALGO_SHA256);

		return 1 === $verified;
	}

	/**
	 * Parses authenticator data.
	 *
	 * @since 2.13.2
	 * @param string $auth_data               Raw authenticator data.
	 * @param bool   $expect_attested_payload True when registration data is expected.
	 * @return array|\WP_Error
	 */
	public function parse_authenticator_data($auth_data, $expect_attested_payload) {

		if (strlen($auth_data) < 37) {
			return new \WP_Error('invalid_authenticator_data', __('Invalid passkey response.', 'ultimate-multisite'));
		}

		$rp_id_hash = substr($auth_data, 0, 32);
		$flags      = ord($auth_data[32]);
		$counter    = unpack('N', substr($auth_data, 33, 4));

		if ( ! ($flags & 0x01)) {
			return new \WP_Error('user_presence_required', __('Passkey verification requires user presence.', 'ultimate-multisite'));
		}

		$result = [
			'rp_id_hash' => $rp_id_hash,
			'flags'      => $flags,
			'sign_count' => absint($counter[1] ?? 0),
		];

		if ( ! $expect_attested_payload) {
			return $result;
		}

		if ( ! ($flags & 0x40)) {
			return new \WP_Error('missing_attested_data', __('Passkey registration did not include credential data.', 'ultimate-multisite'));
		}

		if (strlen($auth_data) < 55) {
			return new \WP_Error('invalid_attested_data', __('Invalid passkey credential data.', 'ultimate-multisite'));
		}

		$offset               = 37;
		$aaguid               = substr($auth_data, $offset, 16);
		$offset              += 16;
		$credential_id_length = unpack('n', substr($auth_data, $offset, 2));
		$credential_id_length = absint($credential_id_length[1] ?? 0);
		$offset              += 2;

		if ($credential_id_length < 16 || strlen($auth_data) < ($offset + $credential_id_length + 1)) {
			return new \WP_Error('invalid_credential_id', __('Invalid passkey credential ID.', 'ultimate-multisite'));
		}

		$credential_id = substr($auth_data, $offset, $credential_id_length);
		$offset       += $credential_id_length;

		try {
			$public_key_cose = $this->decode_cbor_item($auth_data, $offset);
			$public_key_pem  = $this->cose_key_to_pem($public_key_cose);
		} catch (\Throwable $exception) {
			return new \WP_Error('invalid_public_key', __('Unsupported passkey public key.', 'ultimate-multisite'));
		}

		if (is_wp_error($public_key_pem)) {
			return $public_key_pem;
		}

		$result['aaguid']        = bin2hex($aaguid);
		$result['credential_id'] = $this->base64url_encode($credential_id);
		$result['public_key']    = $public_key_pem;

		return $result;
	}

	/**
	 * Checks if a host is acceptable for non-HTTPS local development.
	 *
	 * @since 2.13.2
	 * @param string $host Host name.
	 * @return bool
	 */
	protected function is_local_development_host($host) {

		$host = strtolower($host);

		return in_array($host, ['localhost', '127.0.0.1', '::1'], true) || (bool) preg_match('/\.(local|test|localhost)$/', $host);
	}

	/**
	 * Decodes one CBOR item.
	 *
	 * @since 2.13.2
	 * @param string $data   Binary CBOR data.
	 * @param int    $offset Current offset.
	 * @return mixed
	 * @throws \UnexpectedValueException When CBOR data is invalid or unsupported.
	 */
	private function decode_cbor_item($data, &$offset) {

		if ($offset >= strlen($data)) {
			throw new \UnexpectedValueException('Unexpected end of CBOR data.');
		}

		$initial = ord($data[ $offset++ ]);
		$major   = $initial >> 5;
		$extra   = $initial & 0x1f;
		$length  = $this->decode_cbor_length($data, $offset, $extra);

		switch ($major) {
			case 0:
				return $length;

			case 1:
				return -1 - $length;

			case 2:
				return $this->read_cbor_bytes($data, $offset, $length);

			case 3:
				return $this->read_cbor_bytes($data, $offset, $length);

			case 4:
				$array = [];

				for ($i = 0; $i < $length; $i++) {
					$array[] = $this->decode_cbor_item($data, $offset);
				}

				return $array;

			case 5:
				$map = [];

				for ($i = 0; $i < $length; $i++) {
					$key         = $this->decode_cbor_item($data, $offset);
					$map[ $key ] = $this->decode_cbor_item($data, $offset);
				}

				return $map;

			case 7:
				if (20 === $extra) {
					return false;
				}

				if (21 === $extra) {
					return true;
				}

				if (22 === $extra) {
					return null;
				}
		}

		throw new \UnexpectedValueException('Unsupported CBOR major type.');
	}

	/**
	 * Decodes a CBOR length.
	 *
	 * @since 2.13.2
	 * @param string $data   Binary CBOR data.
	 * @param int    $offset Current offset.
	 * @param int    $extra  Additional info bits.
	 * @return int
	 * @throws \UnexpectedValueException When a CBOR length is invalid or unsupported.
	 */
	private function decode_cbor_length($data, &$offset, $extra) {

		if ($extra < 24) {
			return $extra;
		}

		if (24 === $extra) {
			return ord($this->read_cbor_bytes($data, $offset, 1));
		}

		if (25 === $extra) {
			$value = unpack('n', $this->read_cbor_bytes($data, $offset, 2));

			return absint($value[1] ?? 0);
		}

		if (26 === $extra) {
			$value = unpack('N', $this->read_cbor_bytes($data, $offset, 4));

			return absint($value[1] ?? 0);
		}

		throw new \UnexpectedValueException('Unsupported CBOR length.');
	}

	/**
	 * Reads bytes from CBOR input.
	 *
	 * @since 2.13.2
	 * @param string $data   Binary CBOR data.
	 * @param int    $offset Current offset.
	 * @param int    $length Number of bytes.
	 * @return string
	 * @throws \UnexpectedValueException When the requested length exceeds the input.
	 */
	private function read_cbor_bytes($data, &$offset, $length) {

		if ($length < 0 || strlen($data) < ($offset + $length)) {
			throw new \UnexpectedValueException('CBOR length exceeds data size.');
		}

		$bytes   = substr($data, $offset, $length);
		$offset += $length;

		return $bytes;
	}

	/**
	 * Converts a COSE P-256 ES256 key to PEM.
	 *
	 * @since 2.13.2
	 * @param array $key COSE key map.
	 * @return string|\WP_Error
	 */
	private function cose_key_to_pem($key) {

		if ( ! is_array($key)) {
			return new \WP_Error('invalid_public_key', __('Unsupported passkey public key.', 'ultimate-multisite'));
		}

		$key_type  = $key[1] ?? null;
		$algorithm = $key[3] ?? null;
		$curve     = $key[-1] ?? null;
		$x         = $key[-2] ?? '';
		$y         = $key[-3] ?? '';

		if (2 !== $key_type || -7 !== $algorithm || 1 !== $curve || 32 !== strlen($x) || 32 !== strlen($y)) {
			return new \WP_Error('unsupported_public_key', __('Only ES256 passkeys are supported.', 'ultimate-multisite'));
		}

		$algorithm_identifier = "\x30\x13\x06\x07\x2A\x86\x48\xCE\x3D\x02\x01\x06\x08\x2A\x86\x48\xCE\x3D\x03\x01\x07";
		$point                = "\x04" . $x . $y;
		$bit_string           = "\x03" . $this->der_length(strlen($point) + 1) . "\x00" . $point;
		$der                  = "\x30" . $this->der_length(strlen($algorithm_identifier . $bit_string)) . $algorithm_identifier . $bit_string;

		return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n"; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Encodes a DER length.
	 *
	 * @since 2.13.2
	 * @param int $length Length.
	 * @return string
	 */
	private function der_length($length) {

		if ($length < 128) {
			return chr($length);
		}

		$bytes = '';

		while ($length > 0) {
			$bytes  = chr($length & 0xff) . $bytes;
			$length = $length >> 8;
		}

		return chr(0x80 | strlen($bytes)) . $bytes;
	}
}
