<?php
/**
 * Email one-time password service.
 *
 * @package WP_Ultimo
 * @subpackage Auth
 * @since 2.13.2
 */

namespace WP_Ultimo\Auth;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Generates, stores, sends, and verifies email OTP login codes.
 *
 * @since 2.13.2
 */
class Email_OTP_Service {

	/**
	 * OTP TTL in seconds.
	 *
	 * @since 2.13.2
	 */
	const TTL = 600;

	/**
	 * Maximum verification attempts for one code.
	 *
	 * @since 2.13.2
	 */
	const MAX_ATTEMPTS = 5;

	/**
	 * Returns the table name.
	 *
	 * @since 2.13.2
	 * @return string
	 */
	public function get_table_name() {

		$table = WP_Ultimo()->tables->email_otp_attempt_table ?? null;

		if ($table && isset($table->table_name)) {
			return $table->table_name;
		}

		global $wpdb;

		return $wpdb->base_prefix . 'wu_email_otp_attempts';
	}

	/**
	 * Creates and sends a new OTP for a user.
	 *
	 * @since 2.13.2
	 * @param \WP_User|false $user  User object.
	 * @param string         $email Email address or identifier.
	 * @return array|\WP_Error
	 */
	public function create_and_send($user, $email) {

		$email = sanitize_email($email);

		if ( ! $user instanceof \WP_User || ! is_email($email)) {
			return [
				'token'   => wp_generate_password(32, false, false),
				'sent'    => false,
				'generic' => true,
			];
		}

		$rate_limit = $this->check_send_rate_limit($email);

		if (is_wp_error($rate_limit)) {
			return $rate_limit;
		}

		$code = (string) random_int(100000, 999999);

		/**
		 * Filters the generated OTP code.
		 *
		 * Intended for automated tests only.
		 *
		 * @since 2.13.2
		 * @param string   $code Generated code.
		 * @param \WP_User $user User object.
		 */
		$code = (string) apply_filters('wu_passwordless_otp_code', $code, $user);

		$token = wp_generate_password(32, false, false);

		global $wpdb;

		$now     = current_time('mysql', true);
		$expires = gmdate('Y-m-d H:i:s', time() + self::TTL);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert(
			$this->get_table_name(),
			[
				'email'        => $email,
				'user_id'      => $user->ID,
				'token_hash'   => hash('sha256', $token),
				'code_hash'    => wp_hash_password($code),
				'ip_hash'      => $this->get_ip_hash(),
				'attempts'     => 0,
				'expires_at'   => $expires,
				'consumed_at'  => null,
				'date_created' => $now,
			],
			['%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s']
		);

		if (false === $inserted) {
			return new \WP_Error('otp_store_failed', __('Could not create a login code. Please try again.', 'ultimate-multisite'));
		}

		/**
		 * Filters if the OTP email should be sent.
		 *
		 * @since 2.13.2
		 * @param bool     $send Whether to send the OTP.
		 * @param \WP_User $user User object.
		 * @param string   $code Plain OTP code.
		 */
		$should_send = (bool) apply_filters('wu_passwordless_should_send_otp', true, $user, $code);

		if ($should_send && ! $this->send_email($user, $code)) {
			$this->delete_attempt_by_token($token);

			return new \WP_Error('otp_email_failed', __('Could not send the login code email. Please try again.', 'ultimate-multisite'));
		}

		return [
			'token'   => $token,
			'sent'    => $should_send,
			'generic' => false,
		];
	}

	/**
	 * Verifies an OTP code and consumes it on success.
	 *
	 * @since 2.13.2
	 * @param string $token OTP token.
	 * @param string $code  OTP code.
	 * @return \WP_User|\WP_Error
	 */
	public function verify($token, $code) {

		global $wpdb;

		$token = sanitize_text_field($token);
		$code  = preg_replace('/\D+/', '', (string) $code);
		$table = $this->get_table_name();

		if (empty($token) || empty($code)) {
			return new \WP_Error('invalid_otp', __('Invalid login code.', 'ultimate-multisite'));
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$attempt = $wpdb->get_row(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$table} WHERE token_hash = %s AND consumed_at IS NULL AND expires_at >= %s LIMIT 1",
				hash('sha256', $token),
				current_time('mysql', true)
			)
		);

		if ( ! $attempt) {
			return new \WP_Error('invalid_otp', __('Invalid or expired login code.', 'ultimate-multisite'));
		}

		if ((int) $attempt->attempts >= self::MAX_ATTEMPTS) {
			return new \WP_Error('otp_attempts_exceeded', __('Too many code attempts. Request a new code and try again.', 'ultimate-multisite'));
		}

		if ( ! wp_check_password($code, $attempt->code_hash)) {
			$this->increment_attempts((int) $attempt->id, (int) $attempt->attempts + 1);

			return new \WP_Error('invalid_otp', __('Invalid login code.', 'ultimate-multisite'));
		}

		$user = get_user_by('id', (int) $attempt->user_id);

		if ( ! $user) {
			return new \WP_Error('invalid_otp_user', __('Invalid login code.', 'ultimate-multisite'));
		}

		if ( ! $this->consume((int) $attempt->id)) {
			return new \WP_Error('invalid_otp', __('Invalid or expired login code.', 'ultimate-multisite'));
		}

		return $user;
	}

	/**
	 * Deletes a stored OTP attempt by token.
	 *
	 * @since 2.13.2
	 * @param string $token OTP token.
	 * @return bool
	 */
	protected function delete_attempt_by_token($token) {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->delete(
			$this->get_table_name(),
			[
				'token_hash' => hash('sha256', sanitize_text_field($token)),
			],
			['%s']
		);

		return false !== $result;
	}

	/**
	 * Returns a stored OTP attempt row by token.
	 *
	 * @since 2.13.2
	 * @param string $token OTP token.
	 * @return object|null
	 */
	public function get_attempt_by_token($token) {

		global $wpdb;

		$table = $this->get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$table} WHERE token_hash = %s LIMIT 1",
				hash('sha256', sanitize_text_field($token))
			)
		);
	}

	/**
	 * Sends the login code email.
	 *
	 * @since 2.13.2
	 * @param \WP_User $user User object.
	 * @param string   $code OTP code.
	 * @return bool
	 */
	protected function send_email(\WP_User $user, $code) {

		$subject = __('Your Ultimate Multisite login code', 'ultimate-multisite');

		$message = sprintf(
			/* translators: 1: login code, 2: expiration minutes. */
			__('Your Ultimate Multisite login code is %1$s. It expires in %2$d minutes.', 'ultimate-multisite'),
			$code,
			(int) (self::TTL / MINUTE_IN_SECONDS)
		);

		return wp_mail($user->user_email, $subject, $message);
	}

	/**
	 * Checks OTP send rate limits.
	 *
	 * @since 2.13.2
	 * @param string $email Email address.
	 * @return true|\WP_Error
	 */
	protected function check_send_rate_limit($email) {

		$ip_key    = 'wu_passwordless_otp_ip_' . md5($this->get_ip_hash());
		$email_key = 'wu_passwordless_otp_email_' . md5(strtolower($email));
		$ip_count  = (int) get_transient($ip_key);
		$em_count  = (int) get_transient($email_key);

		if ($ip_count >= 10 || $em_count >= 3) {
			return new \WP_Error('otp_rate_limited', __('Too many login code requests. Please try again later.', 'ultimate-multisite'));
		}

		set_transient($ip_key, $ip_count + 1, 15 * MINUTE_IN_SECONDS);
		set_transient($email_key, $em_count + 1, 15 * MINUTE_IN_SECONDS);

		return true;
	}

	/**
	 * Increments OTP attempts.
	 *
	 * @since 2.13.2
	 * @param int $id       Attempt ID.
	 * @param int $attempts Attempt count.
	 * @return void
	 */
	protected function increment_attempts($id, $attempts) {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$this->get_table_name(),
			[
				'attempts' => absint($attempts),
			],
			[
				'id' => absint($id),
			],
			['%d'],
			['%d']
		);
	}

	/**
	 * Consumes an OTP attempt.
	 *
	 * @since 2.13.2
	 * @param int $id Attempt ID.
	 * @return bool
	 */
	protected function consume($id) {

		global $wpdb;

		$table = $this->get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->query(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"UPDATE {$table} SET consumed_at = %s WHERE id = %d AND consumed_at IS NULL",
				current_time('mysql', true),
				absint($id)
			)
		);

		return 1 === $result;
	}

	/**
	 * Returns a salted hash for the current IP.
	 *
	 * @since 2.13.2
	 * @return string
	 */
	protected function get_ip_hash() {

		return hash_hmac('sha256', wu_get_ip(), wp_salt('auth'));
	}
}
