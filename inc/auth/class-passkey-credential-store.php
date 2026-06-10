<?php
/**
 * Passkey credential storage.
 *
 * @package WP_Ultimo
 * @subpackage Auth
 * @since 2.13.2
 */

namespace WP_Ultimo\Auth;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Stores and retrieves user passkey credentials.
 *
 * @since 2.13.2
 */
class Passkey_Credential_Store {

	/**
	 * Returns the table name.
	 *
	 * @since 2.13.2
	 * @return string
	 */
	public function get_table_name() {

		$table = WP_Ultimo()->tables->passkey_credential_table ?? null;

		if ($table && isset($table->table_name)) {
			return $table->table_name;
		}

		global $wpdb;

		return $wpdb->base_prefix . 'wu_passkey_credentials';
	}

	/**
	 * Returns active credentials for a user.
	 *
	 * @since 2.13.2
	 * @param int $user_id User ID.
	 * @return array
	 */
	public function get_user_credentials($user_id) {

		global $wpdb;

		$table = $this->get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$credentials = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$table} WHERE user_id = %d AND active = 1 ORDER BY date_created ASC",
				absint($user_id)
			)
		);

		return is_array($credentials) ? $credentials : [];
	}

	/**
	 * Checks if a user has at least one passkey.
	 *
	 * @since 2.13.2
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function user_has_credentials($user_id) {

		return ! empty($this->get_user_credentials($user_id));
	}

	/**
	 * Finds a credential by credential ID.
	 *
	 * @since 2.13.2
	 * @param string $credential_id Credential ID.
	 * @return object|null
	 */
	public function find_by_credential_id($credential_id) {

		global $wpdb;

		$table = $this->get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$table} WHERE credential_id = %s AND active = 1 LIMIT 1",
				sanitize_text_field($credential_id)
			)
		);
	}

	/**
	 * Stores a credential.
	 *
	 * @since 2.13.2
	 * @param int    $user_id       User ID.
	 * @param string $credential_id Credential ID.
	 * @param string $public_key    PEM public key.
	 * @param int    $sign_count    Sign counter.
	 * @param string $aaguid        AAGUID.
	 * @param array  $transports    Authenticator transports.
	 * @return bool
	 */
	public function create($user_id, $credential_id, $public_key, $sign_count, $aaguid = '', $transports = []) {

		global $wpdb;

		$now = current_time('mysql', true);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->replace(
			$this->get_table_name(),
			[
				'user_id'        => absint($user_id),
				'credential_id'  => sanitize_text_field($credential_id),
				'public_key'     => $public_key,
				'sign_count'     => absint($sign_count),
				'aaguid'         => sanitize_text_field($aaguid),
				'transports'     => implode(',', array_map('sanitize_key', (array) $transports)),
				'active'         => 1,
				'date_created'   => $now,
				'date_updated'   => $now,
				'date_last_used' => null,
			],
			['%d', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s']
		);

		return false !== $result;
	}

	/**
	 * Updates a credential counter and last-used time.
	 *
	 * @since 2.13.2
	 * @param int $id         Credential row ID.
	 * @param int $sign_count New sign count.
	 * @return bool
	 */
	public function update_usage($id, $sign_count) {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$this->get_table_name(),
			[
				'sign_count'     => absint($sign_count),
				'date_last_used' => current_time('mysql', true),
				'date_updated'   => current_time('mysql', true),
			],
			[
				'id' => absint($id),
			],
			['%d', '%s', '%s'],
			['%d']
		);

		return false !== $result;
	}
}
