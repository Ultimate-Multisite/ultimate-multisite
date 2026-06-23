<?php
/**
 * Payment List Table class.
 *
 * @package WP_Ultimo
 * @subpackage List_Table
 * @since 2.0.0
 */

namespace WP_Ultimo\List_Tables;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Payment List Table class.
 *
 * @since 2.0.0
 */
class Membership_Line_Item_List_Table extends Product_List_Table {

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
	 * Overrides the parent get_items to add a total line.
	 *
	 * @since 2.0.0
	 *
	 * @param integer $per_page Items per page. This gets overridden as well.
	 * @param integer $page_number The page number.
	 * @param boolean $count Return as count or not.
	 * @return array|int
	 */
	public function get_items($per_page = 5, $page_number = 1, $count = false) {

		$membership = wu_get_membership(wu_request('id'));

		if ( ! $membership) {
			return $count ? 0 : [];
		}

		$products = $membership->get_all_products();

		if ($count) {
			return count($products);
		}

		return $products;
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

		$quantity = $item['quantity'];

		$membership_id = wu_request('id');

		$item = $item['product'];

		if ( ! $item) {
			wu_responsive_table_row(
				[
					'url'    => false,
					'id'     => 'not-found',
					'title'  => __('Product not found', 'ultimate-multisite'),
					'status' => '',
					'image'  => $this->column_featured_image_id(new \WP_Ultimo\Models\Product()),
				],
				[
					'quantity' => [
						'icon'  => 'dashicons-wu-package wu-align-middle wu-mr-1',
						'label' => __('Quantity', 'ultimate-multisite'),
						// translators: %d is a quantity number
						'value' => sprintf(__('x%d', 'ultimate-multisite'), $quantity),
					],
				]
			);

			return;
		}

		$first_row = [
			'quantity' => [
				'icon'  => 'dashicons-wu-package wu-align-middle wu-mr-1',
				'label' => __('Quantity', 'ultimate-multisite'),
				// translators: %d is a quantity number
				'value' => sprintf(__('x%d', 'ultimate-multisite'), $quantity),
			],
			'total'    => [
				'icon'  => 'dashicons-wu-shopping-bag1 wu-align-middle wu-mr-1',
				'label' => __('Price description', 'ultimate-multisite'),
				'value' => $item->get_price_description(),
			],
		];

		$second_row = [
			'slug' => [
				'icon'  => 'dashicons-wu-bookmark1 wu-align-middle wu-mr-1',
				'label' => __('Product Slug', 'ultimate-multisite'),
				'value' => $item->get_slug(),
			],
		];

		if ($item->get_type() === 'plan') {
			$first_row['change'] = [
				'wrapper_classes' => 'wubox',
				'icon'            => 'dashicons-wu-edit1 wu-align-middle wu-mr-1',
				'label'           => '',
				'value'           => __('Upgrade or Downgrade', 'ultimate-multisite'),
				'url'             => wu_get_form_url(
					'change_membership_plan',
					[
						'id'         => $membership_id,
						'product_id' => $item->get_id(),
					]
				),
			];
		} else {
			$first_row['remove'] = [
				'wrapper_classes' => 'wu-text-red-500 wubox',
				'icon'            => 'dashicons-wu-trash-2 wu-align-middle wu-mr-1',
				'label'           => '',
				'value'           => __('Remove', 'ultimate-multisite'),
				'url'             => wu_get_form_url(
					'remove_membership_product',
					[
						'id'         => $membership_id,
						'product_id' => $item->get_id(),
					]
				),
			];
		}

		wu_responsive_table_row(
			[
				'id'     => $item->get_id(),
				'title'  => $item->get_name(),
				'url'    => wu_network_admin_url(
					'wp-ultimo-edit-product',
					[
						'id' => $item->get_id(),
					]
				),
				'image'  => $this->column_featured_image_id($item),
				'status' => $this->column_type($item),
			],
			$first_row,
			$second_row
		);
	}
}
