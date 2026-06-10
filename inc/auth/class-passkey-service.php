<?php
/**
 * Passkey service.
 *
 * @package WP_Ultimo
 * @subpackage Auth
 * @since 2.13.2
 */

namespace WP_Ultimo\Auth;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Generates and verifies passkey registration and authentication options.
 *
 * @since 2.13.2
 */
class Passkey_Service {

	/**
	 * WebAuthn helper.
	 *
	 * @since 2.13.2
	 * @var WebAuthn_Helper
	 */
	protected $helper;

	/**
	 * Credential store.
	 *
	 * @since 2.13.2
	 * @var Passkey_Credential_Store
	 */
	protected $credentials;

	/**
	 * Challenge store.
	 *
	 * @since 2.13.2
	 * @var WebAuthn_Challenge_Store
	 */
	protected $challenges;

	/**
	 * Constructs the service.
	 *
	 * @since 2.13.2
	 */
	public function __construct() {

		$this->helper      = new WebAuthn_Helper();
		$this->credentials = new Passkey_Credential_Store();
		$this->challenges  = new WebAuthn_Challenge_Store();
	}

	/**
	 * Returns the credential store.
	 *
	 * @since 2.13.2
	 * @return Passkey_Credential_Store
	 */
	public function credentials() {

		return $this->credentials;
	}

	/**
	 * Generates passkey registration options for a user.
	 *
	 * @since 2.13.2
	 * @param \WP_User $user User object.
	 * @return array
	 */
	public function get_registration_options(\WP_User $user) {

		$rp_id     = $this->helper->get_rp_id();
		$origin    = $this->helper->get_origin();
		$challenge = $this->challenges->create('registration', $user->ID, $rp_id, $origin);
		$existing  = [];

		foreach ($this->credentials->get_user_credentials($user->ID) as $credential) {
			$existing[] = [
				'type' => 'public-key',
				'id'   => $credential->credential_id,
			];
		}

		return [
			'publicKey' => [
				'challenge'              => $challenge,
				'rp'                     => [
					'name' => get_bloginfo('name') ?: 'Ultimate Multisite',
					'id'   => $rp_id,
				],
				'user'                   => [
					'id'          => $this->helper->base64url_encode((string) $user->ID),
					'name'        => $user->user_email ?: $user->user_login,
					'displayName' => $user->display_name ?: $user->user_login,
				],
				'pubKeyCredParams'       => [
					[
						'type' => 'public-key',
						'alg'  => -7,
					],
				],
				'timeout'                => 60000,
				'attestation'            => 'none',
				'excludeCredentials'     => $existing,
				'authenticatorSelection' => [
					'residentKey'      => 'preferred',
					'userVerification' => 'preferred',
				],
			],
		];
	}

	/**
	 * Generates passkey authentication options for a user.
	 *
	 * @since 2.13.2
	 * @param \WP_User $user User object.
	 * @return array|\WP_Error
	 */
	public function get_authentication_options(\WP_User $user) {

		$credentials = $this->credentials->get_user_credentials($user->ID);

		if (empty($credentials)) {
			return new \WP_Error('no_passkeys', __('No passkeys are registered for this account.', 'ultimate-multisite'));
		}

		$rp_id     = $this->helper->get_rp_id();
		$origin    = $this->helper->get_origin();
		$challenge = $this->challenges->create('authentication', $user->ID, $rp_id, $origin);
		$allow     = [];

		foreach ($credentials as $credential) {
			$allow[] = [
				'type' => 'public-key',
				'id'   => $credential->credential_id,
			];
		}

		return [
			'publicKey' => [
				'challenge'        => $challenge,
				'timeout'          => 60000,
				'rpId'             => $rp_id,
				'allowCredentials' => $allow,
				'userVerification' => 'preferred',
			],
		];
	}

	/**
	 * Verifies and stores a registration response.
	 *
	 * @since 2.13.2
	 * @param int   $user_id User ID.
	 * @param array $payload Browser credential payload.
	 * @return true|\WP_Error
	 */
	public function verify_registration($user_id, $payload) {

		$user = get_user_by('id', absint($user_id));

		if ( ! $user) {
			return new \WP_Error('invalid_user', __('Invalid passkey user.', 'ultimate-multisite'));
		}

		$client_data = $this->helper->parse_client_data($payload['response']['clientDataJSON'] ?? '', 'webauthn.create');

		if (is_wp_error($client_data)) {
			return $client_data;
		}

		$challenge = $this->challenges->get_valid($client_data['challenge'], 'registration');

		if ( ! $challenge || (int) $challenge->user_id !== (int) $user->ID) {
			return new \WP_Error('invalid_challenge', __('Invalid or expired passkey challenge.', 'ultimate-multisite'));
		}

		if ( ! $this->helper->is_origin_allowed($client_data['origin'], $challenge->origin, $challenge->rp_id)) {
			return new \WP_Error('invalid_origin', __('Invalid passkey origin.', 'ultimate-multisite'));
		}

		$attestation = $this->helper->parse_attestation_object($payload['response']['attestationObject'] ?? '');

		if (is_wp_error($attestation)) {
			return $attestation;
		}

		if (hash('sha256', $challenge->rp_id, true) !== $attestation['rp_id_hash']) {
			return new \WP_Error('invalid_rp_id', __('Invalid passkey relying party.', 'ultimate-multisite'));
		}

		if ( ! $this->challenges->mark_used((int) $challenge->id)) {
			return new \WP_Error('invalid_challenge', __('Invalid or expired passkey challenge.', 'ultimate-multisite'));
		}

		$transports = $payload['response']['transports'] ?? [];

		$created = $this->credentials->create(
			$user->ID,
			$attestation['credential_id'],
			$attestation['public_key'],
			$attestation['sign_count'],
			$attestation['aaguid'],
			is_array($transports) ? $transports : []
		);

		if ( ! $created) {
			return new \WP_Error('credential_store_failed', __('Could not store the passkey. Please try again.', 'ultimate-multisite'));
		}

		return true;
	}

	/**
	 * Verifies an authentication response.
	 *
	 * @since 2.13.2
	 * @param array $payload Browser credential payload.
	 * @return \WP_User|\WP_Error
	 */
	public function verify_authentication($payload) {

		$credential_id = sanitize_text_field($payload['rawId'] ?? $payload['id'] ?? '');
		$credential    = $this->credentials->find_by_credential_id($credential_id);

		if ( ! $credential) {
			return new \WP_Error('unknown_credential', __('This passkey is not registered.', 'ultimate-multisite'));
		}

		$client_data = $this->helper->parse_client_data($payload['response']['clientDataJSON'] ?? '', 'webauthn.get');

		if (is_wp_error($client_data)) {
			return $client_data;
		}

		$challenge = $this->challenges->get_valid($client_data['challenge'], 'authentication');

		if ( ! $challenge || (int) $challenge->user_id !== (int) $credential->user_id) {
			return new \WP_Error('invalid_challenge', __('Invalid or expired passkey challenge.', 'ultimate-multisite'));
		}

		if ( ! $this->helper->is_origin_allowed($client_data['origin'], $challenge->origin, $challenge->rp_id)) {
			return new \WP_Error('invalid_origin', __('Invalid passkey origin.', 'ultimate-multisite'));
		}

		$authenticator_data = $this->helper->parse_assertion_authenticator_data($payload['response']['authenticatorData'] ?? '');

		if (is_wp_error($authenticator_data)) {
			return $authenticator_data;
		}

		if (hash('sha256', $challenge->rp_id, true) !== $authenticator_data['rp_id_hash']) {
			return new \WP_Error('invalid_rp_id', __('Invalid passkey relying party.', 'ultimate-multisite'));
		}

		$verified = $this->helper->verify_assertion_signature(
			$payload['response']['authenticatorData'] ?? '',
			$client_data['_raw'],
			$payload['response']['signature'] ?? '',
			$credential->public_key
		);

		if ( ! $verified) {
			return new \WP_Error('invalid_signature', __('Passkey verification failed.', 'ultimate-multisite'));
		}

		$old_count = (int) $credential->sign_count;
		$new_count = (int) $authenticator_data['sign_count'];

		if ($old_count > 0 && $new_count > 0 && $new_count <= $old_count) {
			return new \WP_Error('invalid_sign_count', __('Passkey counter verification failed.', 'ultimate-multisite'));
		}

		if ( ! $this->challenges->mark_used((int) $challenge->id)) {
			return new \WP_Error('invalid_challenge', __('Invalid or expired passkey challenge.', 'ultimate-multisite'));
		}

		$user = get_user_by('id', (int) $credential->user_id);

		if ( ! $user) {
			return new \WP_Error('invalid_user', __('Invalid passkey user.', 'ultimate-multisite'));
		}

		$this->credentials->update_usage((int) $credential->id, $new_count);

		return $user;
	}
}
