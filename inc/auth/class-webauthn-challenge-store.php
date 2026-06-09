<?php
/**
 * WebAuthn challenge storage.
 *
 * @package WP_Ultimo
 * @subpackage Auth
 * @since 2.13.2
 */

namespace WP_Ultimo\Auth;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Stores short-lived, single-use WebAuthn challenges.
 *
 * @since 2.13.2
 */
class WebAuthn_Challenge_Store {

	/**
	 * Challenge TTL in seconds.
	 *
	 * @since 2.13.2
	 */
	const TTL = 300;

	/**
	 * Returns the table name.
	 *
	 * @since 2.13.2
	 * @return string
	 */
	public function get_table_name() {

		$table = WP_Ultimo()->tables->webauthn_challenge_table ?? null;

		if ($table && isset($table->table_name)) {
			return $table->table_name;
		}

		global $wpdb;

		return $wpdb->base_prefix . 'wu_webauthn_challenges';
	}

	/**
	 * Creates a challenge record.
	 *
	 * @since 2.13.2
	 * @param string $type   Challenge type.
	 * @param int    $user_id User ID.
	 * @param string $rp_id  Relying party ID.
	 * @param string $origin Request origin.
	 * @return string Challenge value.
	 */
	public function create($type, $user_id, $rp_id, $origin) {

		global $wpdb;

		$helper    = new WebAuthn_Helper();
		$challenge = $helper->generate_challenge();
		$now       = current_time('mysql', true);
		$expires   = gmdate('Y-m-d H:i:s', time() + self::TTL);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$this->get_table_name(),
			[
				'challenge_hash' => hash('sha256', $challenge),
				'type'           => sanitize_key($type),
				'user_id'        => absint($user_id),
				'rp_id'          => sanitize_text_field($rp_id),
				'origin'         => esc_url_raw($origin),
				'expires_at'     => $expires,
				'used_at'        => null,
				'date_created'   => $now,
			],
			['%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s']
		);

		return $challenge;
	}

	/**
	 * Retrieves a valid challenge by challenge value and type.
	 *
	 * @since 2.13.2
	 * @param string $challenge Challenge value.
	 * @param string $type      Challenge type.
	 * @return object|null
	 */
	public function get_valid($challenge, $type) {

		global $wpdb;

		$table = $this->get_table_name();
		$hash  = hash('sha256', (string) $challenge);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$table} WHERE challenge_hash = %s AND type = %s AND used_at IS NULL AND expires_at >= %s LIMIT 1",
				$hash,
				sanitize_key($type),
				current_time('mysql', true)
			)
		);
	}

	/**
	 * Marks a challenge as used.
	 *
	 * @since 2.13.2
	 * @param int $id Challenge ID.
	 * @return bool
	 */
	public function mark_used($id) {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$this->get_table_name(),
			[
				'used_at' => current_time('mysql', true),
			],
			[
				'id' => absint($id),
			],
			['%s'],
			['%d']
		);

		return false !== $result;
	}
}
