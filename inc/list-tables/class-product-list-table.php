<?php
/**
 * Product List Table class.
 *
 * @package WP_Ultimo
 * @subpackage List_Table
 * @since 2.0.0
 */

namespace WP_Ultimo\List_Tables;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Product List Table class.
 *
 * @since 2.0.0
 */
class Product_List_Table extends Base_List_Table {

	/**
	 * Holds the query class for the object being listed.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $query_class = \WP_Ultimo\Database\Products\Product_Query::class;

	/**
	 * Initializes the table.
	 *
	 * @since 2.0.0
	 */
	public function __construct() {

		parent::__construct(
			[
				'singular' => __('Product', 'ultimate-multisite'),  // singular name of the listed records
				'plural'   => __('Products', 'ultimate-multisite'), // plural name of the listed records
				'ajax'     => true,                        // does this table support ajax?
				'add_new'  => [
					'url'     => wu_network_admin_url('wp-ultimo-edit-product'),
					'classes' => '',
				],
			]
		);
	}

	/**
	 * Displays the content of the product column.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_Ultimo\Models\Product $item Product object.
	 * @return string
	 */
	public function column_name($item) {

		$url_atts = [
			'id'    => $item->get_id(),
			'model' => 'product',
		];

		$title = sprintf('<a href="%s">%s</a>', wu_network_admin_url('wp-ultimo-edit-product', $url_atts), $item->get_name());

		// Concatenate the two blocks
		$title = "<strong>$title</strong>";

		$actions = [
			'edit'      => sprintf('<a href="%s">%s</a>', wu_network_admin_url('wp-ultimo-edit-product', $url_atts), __('Edit', 'ultimate-multisite')),
			'duplicate' => sprintf(
				'<a href="%s">%s</a>',
				wu_network_admin_url(
					'wp-ultimo-products',
					[
						'action' => 'duplicate',
						'id'     => $item->get_id(),
					]
				),
				__('Duplicate', 'ultimate-multisite')
			),
			'delete'    => sprintf('<a title="%s" class="wubox" href="%s">%s</a>', __('Delete', 'ultimate-multisite'), wu_get_form_url('delete_modal', $url_atts), __('Delete', 'ultimate-multisite')),
		];

		return $title . $this->row_actions($actions);
	}

	/**
	 * Displays the type of the product.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_Ultimo\Models\Product $item Product object.
	 * @return string
	 */
	public function column_type($item) {

		$label = $item->get_type_label();

		$class = $item->get_type_class();

		return "<span class='wu-bg-gray-200 wu-text-gray-700 wu-leading-none wu-py-1 wu-px-2 wu-rounded-sm wu-text-xs wu-font-mono $class'>{$label}</span>";
	}

	/**
	 * Displays the slug of the product.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_Ultimo\Models\Product $item Product object.
	 * @return string
	 */
	public function column_slug($item) {

		$slug = $item->get_slug();

		return "<span class='wu-bg-gray-200 wu-text-gray-700 wu-leading-none wu-py-1 wu-px-2 wu-rounded-sm wu-text-xs wu-font-mono'>{$slug}</span>";
	}

	/**
	 * Displays the price of the product.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_Ultimo\Models\Product $item Product object.
	 * @return string
	 */
	public function column_amount($item) {

		if ($item->get_pricing_type() === 'contact_us') {
			return __('None', 'ultimate-multisite') . sprintf('<br><small>%s</small>', __('Requires contact', 'ultimate-multisite'));
		}

		if (empty($item->get_amount())) {
			return __('Free', 'ultimate-multisite');
		}

		$amount = wu_format_currency($item->get_amount(), $item->get_currency());

		if ($item->is_recurring()) {
			$duration = $item->get_duration();

			if ($duration <= 1) {
				$message = $item->get_duration_unit();
			} else {
				$message = sprintf(
					// translators: %1$s: the duration number, %2$s: the duration unit (days, weeks, months, etc).
					__('every %1$s %2$s', 'ultimate-multisite'),
					$duration,
					$item->get_duration_unit()
				);
			}

			if ( ! $item->is_forever_recurring()) {
				$billing_cycles_message = sprintf(
					// translators: %s is the number of billing cycles.
					_n('for %s cycle', 'for %s cycles', $item->get_billing_cycles(), 'ultimate-multisite'),
					$item->get_billing_cycles()
				);

				$message .= ' ' . $billing_cycles_message;
			}
		} else {
			$message = __('one time payment', 'ultimate-multisite');
		}

		return sprintf('%s<br><small>%s</small>', $amount, $message);
	}

	/**
	 * Displays the setup fee of the product.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_Ultimo\Models\Product $item Product object.
	 * @return string
	 */
	public function column_setup_fee($item) {

		if ($item->get_pricing_type() === 'contact_us') {
			return __('None', 'ultimate-multisite') . sprintf('<br><small>%s</small>', __('Requires contact', 'ultimate-multisite'));
		}

		if ( ! $item->has_setup_fee()) {
			return __('No Setup Fee', 'ultimate-multisite');
		}

		return wu_format_currency($item->get_setup_fee(), $item->get_currency());
	}

	/**
	 * Handles the bulk processing adding duplication.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function process_single_action(): void {

		$bulk_action = $this->current_action();

		if ('duplicate' === $bulk_action) {
			$new_product = $this->duplicate_product(wu_request('id'));

			if (is_wp_error($new_product)) {
				WP_Ultimo()->notices->add($new_product->get_error_message(), 'error', 'network-admin');

				return;
			}

			$redirect_url = wu_network_admin_url(
				'wp-ultimo-edit-product',
				[
					'id'      => $new_product->get_id(),
					'updated' => 1,
				]
			);

			wp_safe_redirect($redirect_url);

			exit;
		}
	}

	/**
	 * Duplicates a product and persists the copy.
	 *
	 * @since 2.5.2
	 *
	 * @param int $product_id Product ID to duplicate.
	 * @return \WP_Ultimo\Models\Product|\WP_Error The duplicated product or error on failure.
	 */
	protected function duplicate_product($product_id) {

		$product = wu_get_product(absint($product_id));

		if ( ! $product) {
			return new \WP_Error('wu_product_not_found', __('Product not found.', 'ultimate-multisite'));
		}

		$new_product = $product->duplicate();

		// translators: the %s is the thing copied.
		$new_name = sprintf(__('Copy of %s', 'ultimate-multisite'), $product->get_name());

		$new_product->set_name($new_name);

		$new_product->set_slug(sanitize_title($new_name . '-' . time()));

		$new_product->set_date_created(wu_get_current_time('mysql', true));

		$result = $new_product->save();

		if (is_wp_error($result)) {
			return $result;
		}

		if ( ! $result || ! $new_product->get_id()) {
			return new \WP_Error('wu_product_duplicate_failed', __('Product could not be duplicated.', 'ultimate-multisite'));
		}

		return $new_product;
	}

	/**
	 * Returns the list of columns for this particular List Table.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_columns() {

		$columns = [
			'cb'                => '<input type="checkbox" />',
			'featured_image_id' => '<span class="dashicons-wu-image"></span>',
			'name'              => __('Name', 'ultimate-multisite'),
			'type'              => __('Type', 'ultimate-multisite'),
			'slug'              => __('Slug', 'ultimate-multisite'),
			'amount'            => __('Price', 'ultimate-multisite'),
			'setup_fee'         => __('Setup Fee', 'ultimate-multisite'),
			'id'                => __('ID', 'ultimate-multisite'),
		];

		return $columns;
	}

	/**
	 * Handles the item display for grid mode.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_Ultimo\Models\Product $item The line item being displayed.
	 * @return void
	 */
	public function single_row_grid($item): void {

		wu_get_template(
			'base/products/grid-item',
			[
				'item'  => $item,
				'table' => $this,
			]
		);
	}

	/**
	 * Returns the filters for this page.
	 *
	 * @since 2.0.0
	 */
	public function get_filters(): array {

		return [
			'filters' => [],
		];
	}

	/**
	 * Returns the pre-selected filters on the filter bar.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_views() {

		return [
			'all'     => [
				'field' => 'type',
				'url'   => add_query_arg('type', 'all'),
				'label' => __('All Products', 'ultimate-multisite'),
				'count' => 0,
			],
			'plan'    => [
				'field' => 'type',
				'url'   => add_query_arg('type', 'plan'),
				'label' => __('Plans', 'ultimate-multisite'),
				'count' => 0,
			],
			'package' => [
				'field' => 'type',
				'url'   => add_query_arg('type', 'package'),
				'label' => __('Packages', 'ultimate-multisite'),
				'count' => 0,
			],
			'service' => [
				'field' => 'type',
				'url'   => add_query_arg('type', 'service'),
				'label' => __('Services', 'ultimate-multisite'),
				'count' => 0,
			],
		];
	}
}
