<?php
/**
 * Customers' Membership List Table class.
 *
 * @package WP_Ultimo
 * @subpackage List_Table
 * @since 2.0.0
 */

namespace WP_Ultimo\List_Tables;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Membership List Table class.
 *
 * @since 2.0.0
 */
class Customers_Membership_List_Table extends Membership_List_Table {

	/**
	 * Returns the list of columns for this particular List Table.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_columns() {

		$columns = [
			'responsive' => '',
		];

		return $columns;
	}

	/**
	 * Renders the inside column responsive.
	 *
	 * @since 2.0.0
	 *
	 * @param object $item The item being rendered.
	 * @return void
	 */
	public function column_responsive($item): void {

		$p = $item->get_plan();

		$date_expiration = $item->get_date_expiration();

		if (empty($date_expiration) || '0000-00-00 00:00:00' === $date_expiration) {
			// translators: %1$s: lifetime membership label, %2$s: lifetime expiration description.
			$expiration_label = sprintf('%1$s / %2$s', __('Lifetime', 'ultimate-multisite'), __('It never expires', 'ultimate-multisite'));
		} else {
			$expired = time() >= strtotime($date_expiration);
			// translators: %s is a placeholder for the human-readable time difference, e.g., "2 hours ago".
			$expiration_label = sprintf($expired ? __('Expired %s', 'ultimate-multisite') : __('Expiring %s', 'ultimate-multisite'), wu_human_time_diff(strtotime($date_expiration)));
		}

		$product_count = 1 + count($item->get_addon_ids());

		$addon_count = count($item->get_addon_ids());

		if ( ! $p) {
			$products_list = '';
		} elseif (0 === $addon_count) {
			// translators: %s: the product name.
			$products_list = sprintf(__('Contains %s', 'ultimate-multisite'), $p->get_name());
		} else {
			// translators: %1$s: the product name, %2$s: the count of other products.
			$products_list = sprintf(__('Contains %1$s and %2$s other product(s)', 'ultimate-multisite'), $p->get_name(), $addon_count);
		}

		wu_responsive_table_row(
			[
				'id'     => $item->get_id(),
				'title'  => $item->get_hash(),
				'url'    => wu_network_admin_url(
					'wp-ultimo-edit-membership',
					[
						'id' => $item->get_id(),
					]
				),
				'status' => $this->column_status($item),
			],
			[
				'total'    => [
					'icon'  => 'dashicons-wu-shopping-bag1 wu-align-middle wu-mr-1',
					'label' => __('Payment Total', 'ultimate-multisite'),
					'value' => $item->get_price_description(),
				],
				'products' => [
					'icon'  => 'dashicons-wu-package wu-align-middle wu-mr-1',
					'label' => __('Products', 'ultimate-multisite'),
					'value' => $products_list,
				],
				'gateway'  => [
					'icon'  => 'dashicons-wu-credit-card2 wu-align-middle wu-mr-1',
					'label' => __('Gateway', 'ultimate-multisite'),
					'value' => wu_slug_to_name($item->get_gateway()),
				],
			],
			[
				'date_expiration' => [
					'icon'  => 'dashicons-wu-calendar1 wu-align-middle wu-mr-1',
					'label' => __('Expires', 'ultimate-multisite'),
					'value' => $expiration_label,
				],
				'date_created'    => [
					'icon'  => 'dashicons-wu-calendar1 wu-align-middle wu-mr-1',
					'label' => __('Created at', 'ultimate-multisite'),
					// translators: %s is a placeholder for the human-readable time difference, e.g., "2 hours ago"
					'value' => sprintf(__('Created %s', 'ultimate-multisite'), wu_human_time_diff(strtotime((string) $item->get_date_created()))),
				],
			]
		);
	}
}
