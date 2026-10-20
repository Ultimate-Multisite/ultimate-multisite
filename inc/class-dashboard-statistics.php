<?php
/**
 * Ultimate Multisite Dashboard Statistics.
 *
 * Log string messages to a file with a timestamp. Useful for debugging.
 *
 * @package WP_Ultimo
 * @subpackage Logger
 * @since 2.0.0
 */

namespace WP_Ultimo;

use WP_Ultimo\Models\Membership;
use WP_Ultimo\Models\Payment;
use WP_Ultimo\Database\Payments\Payment_Status;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Ultimate Multisite Dashboard Statistics
 *
 * @since 2.0.0
 */
class Dashboard_Statistics {

	/**
	 * The initial date of the statistics.
	 *
	 * @var string
	 */
	protected $start_date;

	/**
	 * The final date of the statistics.
	 *
	 * @var string
	 */
	protected $end_date;

	/**
	 * What kind of information you need.
	 *
	 * @var array
	 */
	protected $types = [];

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param array $args With the start_date, end_date and the data type functions.
	 */
	public function __construct($args = []) {

		if ($args) {
			$this->start_date = $args['start_date'];

			$this->end_date = $args['end_date'];

			$this->types = $args['types'];
		}
	}

	/**
	 * Main function to call the get data functions based on the array of types.
	 *
	 * @since 2.0.0
	 * @return array With all the data requested.
	 */
	public function statistics_data() {

		$data = [];

		foreach ($this->types as $key => $type) {
			$data_function = 'get_data_' . $type;

			$data[ $key ] = $this->$data_function();
		}

		return $data;
	}

	/**
	 * Get data of all completed and refunded payments to show in the main graph.
	 *
	 * @since 2.0.0
	 * @return array With total gross data.
	 */
	public function get_data_mrr_growth() {

		$payments_per_month = [
			'january'   => [
				'total'     => 0,
				'cancelled' => 0,
			],
			'february'  => [
				'total'     => 0,
				'cancelled' => 0,
			],
			'march'     => [
				'total'     => 0,
				'cancelled' => 0,
			],
			'april'     => [
				'total'     => 0,
				'cancelled' => 0,
			],
			'may'       => [
				'total'     => 0,
				'cancelled' => 0,
			],
			'june'      => [
				'total'     => 0,
				'cancelled' => 0,
			],
			'july'      => [
				'total'     => 0,
				'cancelled' => 0,
			],
			'august'    => [
				'total'     => 0,
				'cancelled' => 0,
			],
			'september' => [
				'total'     => 0,
				'cancelled' => 0,
			],
			'october'   => [
				'total'     => 0,
				'cancelled' => 0,
			],
			'november'  => [
				'total'     => 0,
				'cancelled' => 0,
			],
			'december'  => [
				'total'     => 0,
				'cancelled' => 0,
			],
		];

		$memberships = wu_get_memberships(
			[
				'date_query' => [
					'column'   => 'date_created',
					'compare'  => 'BETWEEN',
					'relation' => '',
					[
						'year' => current_time('Y', true),
					],
				],
			]
		);

		$mrr_status = [
			'active',
			'cancelled',
			'expired',
		];

		$churn_status = [
			'cancelled',
			'expired',
		];

		foreach ($memberships as $membership) {
			if ( ! $membership->is_recurring()) {
				continue;
			}

			$status = $membership->get_status();

			if (in_array($status, $mrr_status, true)) {
				$data = getdate(strtotime($membership->get_date_created()));

				$month = strtolower($data['month']);

				$payments_per_month[ $month ]['total'] += floatval($membership->get_normalized_amount());
			}

			if (in_array($status, $churn_status, true)) {
				$data = getdate(strtotime((string) $membership->get_date_cancellation()));

				$month = strtolower($data['month']);

				$payments_per_month[ $month ]['cancelled'] += floatval($membership->get_normalized_amount());
			}
		}

		return $payments_per_month;
	}

	/**
	 * Get signup funnel conversion counts for the current date range.
	 *
	 * Returns an associative array with counts for each funnel stage:
	 * - checkout_started
	 * - checkout_step_completed
	 * - checkout_completed
	 * - checkout_failed
	 *
	 * @since 2.5.0
	 * @return array
	 */
	public function get_data_signup_funnel(): array {

		global $wpdb;

		$table = $wpdb->base_prefix . 'wu_events';

		$slugs = [
			'checkout_started',
			'checkout_step_completed',
			'checkout_completed',
			'checkout_failed',
		];

		$counts = array_fill_keys($slugs, 0);

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// Note: table name comes from $wpdb->base_prefix which is safe.
		foreach ($slugs as $slug) {
			$counts[ $slug ] = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE slug = %s AND date_created BETWEEN %s AND %s",
					$slug,
					$this->start_date,
					$this->end_date
				)
			);
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Compute conversion rate: completed / started (avoid division by zero).
		$started   = $counts['checkout_started'];
		$completed = $counts['checkout_completed'];

		$counts['conversion_rate'] = $started > 0
			? round(($completed / $started) * 100, 1)
			: 0.0;

		return $counts;
	}

	/**
	 * Get post-signup activity counts for the current date range.
	 *
	 * Queries the event slugs produced by Post_Signup_Activity_Manager:
	 * - subsite_post_created
	 * - subsite_cpt_created
	 * - subsite_user_registered
	 * - subsite_woocommerce_order
	 *
	 * @since 2.5.0
	 * @return array Associative array keyed by activity slug with integer counts.
	 */
	public function get_data_site_activity(): array {

		global $wpdb;

		$table = $wpdb->base_prefix . 'wu_events';

		$slugs = [
			'subsite_post_created',
			'subsite_cpt_created',
			'subsite_user_registered',
			'subsite_woocommerce_order',
		];

		$counts = array_fill_keys($slugs, 0);

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// Note: table name comes from $wpdb->base_prefix which is safe.
		foreach ($slugs as $slug) {
			$counts[ $slug ] = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE slug = %s AND date_created BETWEEN %s AND %s",
					$slug,
					$this->start_date,
					$this->end_date
				)
			);
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $counts;
	}
}
