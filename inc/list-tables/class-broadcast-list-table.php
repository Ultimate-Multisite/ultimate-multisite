<?php
/**
 * Broadcast List Table class.
 *
 * @package WP_Ultimo
 * @subpackage List_Table
 * @since 2.0.0
 */

namespace WP_Ultimo\List_Tables;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Broadcast List Table class.
 *
 * @since 2.0.0
 */
class Broadcast_List_Table extends Base_List_Table {

	/**
	 * Holds the query class for the object being listed.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $query_class = \WP_Ultimo\Database\Broadcasts\Broadcast_Query::class;

	/**
	 * Initializes the table.
	 *
	 * @since 2.0.0
	 */
	public function __construct() {

		parent::__construct(
			[
				'singular' => __('Broadcast', 'ultimate-multisite'),  // singular name of the listed records
				'plural'   => __('Broadcasts', 'ultimate-multisite'), // plural name of the listed records
				'ajax'     => true,                          // does this table support ajax?
				'add_new'  => [
					'url'     => wu_get_form_url('add_new_broadcast_message'),
					'classes' => 'wubox',
				],
			]
		);
	}

	/**
	 * Overrides the checkbox column to disable the checkboxes on the email types.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_Ultimo\Models\Broadcast $item The broadcast object.
	 * @return string
	 */
	public function column_cb($item) {

		if ($item->get_type() === 'broadcast_email') {
			return '<input type="checkbox" disabled>';
		}

		return parent::column_cb($item);
	}

	/**
	 * Returns the markup for the type column.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_Ultimo\Models\Broadcast $item The broadcast object.
	 * @return string
	 */
	public function column_type($item) {

		$type = $item->get_type();

		$class = 'wu-bg-gray-200';

		if ('broadcast_email' === $type) {
			$label = __('Email', 'ultimate-multisite');
		}

		if ('broadcast_notice' === $type) {
			$status = $item->get_notice_type();

			$label = __('Notice', 'ultimate-multisite');

			if ('info' === $status) {
				$class = 'wu-bg-blue-200';
			} elseif ('success' === $status) {
				$class = 'wu-bg-green-200';
			} elseif ('warning' === $status) {
				$class = 'wu-bg-orange-200';
			} elseif ('error' === $status) {
				$class = 'wu-bg-red-200';
			}
		}

		return "<span class='wu-py-1 wu-px-2 $class wu-rounded-sm wu-text-gray-700 wu-text-xs wu-font-mono'>{$label}</span>";
	}

	/**
	 * Displays the name of the broadcast.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_Ultimo\Models\Broadcast $item The broadcast object.
	 */
	public function column_the_content($item): string {

		$title = sprintf('<strong class="wu-block wu-text-gray-700">%s</strong>', $item->get_title()); // phpcs:ignore

		$content = wp_trim_words(wp_strip_all_tags($item->get_content()), 7);

		$url_atts = [
			'id'    => $item->get_id(),
			'slug'  => $item->get_slug(),
			'model' => 'broadcast',
		];

		$actions = [
			'edit'   => sprintf('<a href="%s">%s</a>', wu_network_admin_url('wp-ultimo-edit-broadcast', $url_atts), __('Edit', 'ultimate-multisite')),
			'delete' => sprintf('<a title="%s" class="wubox" href="%s">%s</a>', __('Delete', 'ultimate-multisite'), wu_get_form_url('delete_modal', $url_atts), __('Delete', 'ultimate-multisite')),
		];

		return $title . $content . $this->row_actions($actions);
	}

	/**
	 * Displays the target customers of the broadcast.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_Ultimo\Models\Broadcast $item The broadcast object.
	 * @return string
	 */
	public function column_target_customers($item) {

		$targets = wu_get_broadcast_targets($item->get_id(), 'customers');

		$targets = array_filter(array_map('wu_get_customer', $targets));

		$targets_count = count($targets);

		$html = '<div class="wu-p-2 wu-mr-1 wu-flex wu-rounded wu-items-center wu-border wu-border-solid wu-border-gray-300 wu-bg-gray-100 wu-relative wu-overflow-hidden">';

		switch ($targets_count) {
			case 0:
				$not_found = __('No customer found', 'ultimate-multisite');

				return "<div class='wu-p-2 wu-mr-1 wu-flex wu-rounded wu-items-center wu-border wu-border-solid wu-border-gray-300 wu-bg-gray-100 wu-relative wu-overflow-hidden'>
										<span class='dashicons dashicons-wu-block wu-text-gray-600 wu-px-1 wu-pr-3'>&nbsp;</span>
												<div class=''>
														<span class='wu-block wu-py-3 wu-text-gray-600 wu-text-2xs wu-font-bold wu-uppercase'>{$not_found}</span>
												</div>
										</div>";
			case 1:
				$customer = array_pop($targets);

				$url_atts = [
					'id' => $customer->get_id(),
				];

				$customer_link = wu_network_admin_url('wp-ultimo-edit-customer', $url_atts);

				$avatar = get_avatar(
					$customer->get_user_id(),
					32,
					'identicon',
					'',
					[
						'force_display' => true,
						'class'         => 'wu-rounded-full wu-border-solid wu-border-1 wu-border-white hover:wu-border-gray-400',
					]
				);

				$display_name = $customer->get_display_name();

				$id = $customer->get_id();

				$email = $customer->get_email_address();

				$html = "<a href='" . esc_url($customer_link) . "' class='wu-p-2 wu-flex wu-flex-grow wu-bg-gray-100 wu-rounded wu-items-center wu-border wu-border-solid wu-border-gray-300'>
										{$avatar}
										<div class='wu-pl-2'>
												<strong class='wu-block'>" . esc_html($display_name) . " <small class='wu-font-normal'>(#" . esc_html((string) $id) . ")</small></strong>
												<small>" . esc_html($email) . "</small>
										</div>
								</a>";

				return $html;
			default:
				foreach ($targets as $key => $target) {
					$customer = $target;

					$tooltip_name = $customer->get_display_name();

					$email = $customer->get_email_address();

					$avatar = get_avatar(
						$email,
						32,
						'identicon',
						'',
						[
							'class' => 'wu-rounded-full wu-border-solid wu-border-1 wu-border-white hover:wu-border-gray-400',
						]
					);

					$url_atts = [
						'id' => $customer->get_id(),
					];

					$customer_link = wu_network_admin_url('wp-ultimo-edit-customer', $url_atts);

					$html .= "<div class='wu-flex wu--mr-4'><a role='tooltip' aria-label='" . esc_attr($tooltip_name) . "' href='" . esc_url($customer_link) . "'>{$avatar}</a></div>";
				}

				if ($targets_count < 7) {
					$modal_atts = [
						'action'      => 'wu_modal_targets_display',
						'object_id'   => $item->get_id(),
						'width'       => '400',
						'height'      => '360',
						'target_type' => 'customers',
					];

					$html .= sprintf(
						'<div class="wu-inline-block wu-mr-2">
										<a href="%s" title="%s" class="wubox"><span class="wu-ml-6 wu-uppercase wu-text-xs wu-font-bold"> %s %s</span></a>
										</div>',
						wu_get_form_url('view_broadcast_targets', $modal_atts),
						__('Targets', 'ultimate-multisite'),
						$targets_count,
						__('Targets', 'ultimate-multisite')
					);

					$html .= '</div>';

					return $html;
				}

				$modal_atts = [
					'action'      => 'wu_modal_targets_display',
					'object_id'   => $item->get_id(),
					'width'       => '400',
					'height'      => '360',
					'target_type' => 'customers',
				];

				$html .= sprintf(
					'<div class="wu-inline-block wu-ml-4">
								<a href="%s" title="%s" class="wubox"><span class="wu-pl-2 wu-uppercase wu-text-xs wu-font-bold"> %s %s</span></a>
								</div>',
					wu_get_form_url('view_broadcast_targets', $modal_atts),
					__('Targets', 'ultimate-multisite'),
					$targets_count,
					__('Targets', 'ultimate-multisite')
				);

				$html .= '</div>';

				return $html;
		}
	}

	/**
	 * Displays the target products of the broadcast.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_Ultimo\Models\Broadcast $item The broadcast object.
	 * @return string
	 */
	public function column_target_products($item) {

		$targets = wu_get_broadcast_targets($item->get_id(), 'products');

		$html = '';

		$products = array_filter(array_map('wu_get_product', $targets));

		$product_count = count($products);

		switch ($product_count) {
			case 0:
				$not_found = __('No product found', 'ultimate-multisite');

				$html = "<div class='wu-p-2 wu-mr-1 wu-flex wu-rounded wu-items-center wu-border wu-border-solid wu-border-gray-300 wu-bg-gray-100 wu-relative wu-overflow-hidden'>
					<span class='dashicons dashicons-wu-block wu-text-gray-600 wu-px-1 wu-pr-3'>&nbsp;</span>
							<div class=''>
									<span class='wu-block wu-py-3 wu-text-gray-600 wu-text-2xs wu-font-bold wu-uppercase'>{$not_found}</span>
							</div>
					</div>";
				break;
			case 1:
				$product = array_pop($products);

				$image = $product->get_featured_image('thumbnail');

				if ($image) {
					$image = sprintf('<img class="wu-w-7 wu-h-7 wu-bg-gray-200 wu-rounded-full wu-text-gray-600 wu-flex wu-items-center wu-justify-center wu-border-solid wu-border-1 wu-border-white hover:wu-border-gray-400" src="%s">', esc_attr($image));
				} else {
					$image = '<div class="wu-w-7 wu-h-7 wu-bg-gray-200 wu-rounded-full wu-text-gray-600 wu-flex wu-items-center wu-justify-center wu-border-solid wu-border-1 wu-border-white">
					<span class="dashicons-wu-image"></span>
					</div>';
				}

				$name = $product->get_name();

				$id = $product->get_id();

				$plan_customers = wu_get_membership_customers($product->get_id());

				$customer_count = (int) 0;

				if ($plan_customers) {
					$customer_count = count($plan_customers);
				}
				// translators: %s is the number of customers.
				$description = sprintf(__('%s customer(s) targeted.', 'ultimate-multisite'), $customer_count);

				$url_atts = [
					'id' => $product->get_id(),
				];

				$product_link = wu_network_admin_url('wp-ultimo-edit-product', $url_atts);

				$html = "<a href='{$product_link}' class='wu-p-2 wu-flex wu-flex-grow wu-bg-gray-100 wu-rounded wu-items-center wu-border wu-border-solid wu-border-gray-300'>
						{$image}
						<div class='wu-pl-2'>
								<strong class='wu-block'>{$name} <small class='wu-font-normal'>(#{$id})</small></strong>
								<small>{$description}</small>
						</div>
				</a>";
				break;
		}

		if ($html) {
			return $html;
		}

		$html = '<div class="wu-p-2 wu-mr-1 wu-flex wu-rounded wu-items-center wu-border wu-border-solid wu-border-gray-300 wu-bg-gray-100 wu-relative wu-overflow-hidden">';

		foreach ($products as $product) {
			$url_atts = [
				'id' => $product->get_id(),
			];

			$product_link = wu_network_admin_url('wp-ultimo-edit-product', $url_atts);

			$product_name = $product->get_name();

			$image = $product->get_featured_image('thumbnail');

			if ($image) {
				$image = sprintf('<img class="wu-w-7 wu-h-7 wu-bg-gray-200 wu-rounded-full wu-text-gray-600 wu-flex wu-items-center wu-justify-center wu-border-solid wu-border-1 wu-border-white hover:wu-border-gray-400" src="%s">', esc_attr($image));
			} else {
				$image = '<div class="wu-w-7 wu-h-7 wu-bg-gray-200 wu-rounded-full wu-text-gray-600 wu-flex wu-items-center wu-justify-center wu-border-solid wu-border-1 wu-border-white hover:wu-border-gray-400">
				<span class="dashicons-wu-image wu-p-1 wu-rounded-full"></span>
		</div>';
			}

			$html .= "<div class='wu-flex wu--mr-4'><a role='tooltip' aria-label='" . esc_attr($product_name) . "' href='" . esc_url($product_link) . "'>{$image}</a></div>";
		}

		if ($product_count > 1 && $product_count < 5) {
			$modal_atts = [
				'action'      => 'wu_modal_targets_display',
				'object_id'   => $item->get_id(),
				'width'       => '400',
				'height'      => '360',
				'target_type' => 'products',
			];

			$html .= sprintf(
				'<div class="wu-inline-block wu-ml-4">
			<a href="%s" title="%s" class="wubox"><span class="wu-pl-2 wu-uppercase wu-text-xs wu-font-bold"> %s %s</span></a></div>',
				wu_get_form_url('view_broadcast_targets', $modal_atts),
				__('Targets', 'ultimate-multisite'),
				$product_count,
				__('Targets', 'ultimate-multisite')
			);

			$html .= '</div>';

			return $html;
		}

		$modal_atts = [
			'action'      => 'wu_modal_targets_display',
			'object_id'   => $item->get_id(),
			'width'       => '400',
			'height'      => '360',
			'target_type' => 'products',
		];

		$html .= sprintf('<div class="wu-inline-block wu-ml-4"><a href="%s" title="%s" class="wubox"><span class="wu-pl-2 wu-uppercase wu-text-xs wu-font-bold"> %s %s</span></a></div>', wu_get_form_url('view_broadcast_targets', $modal_atts), __('Targets', 'ultimate-multisite'), $product_count, __('Targets', 'ultimate-multisite'));

		$html .= '</div>';

		return $html;
	}

	/**
	 * Returns the list of columns for this particular List Table.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_columns() {

		$columns = [
			'cb'               => '<input type="checkbox" />',
			'type'             => __('Type', 'ultimate-multisite'),
			'the_content'      => __('Content', 'ultimate-multisite'),
			'target_customers' => __('Target Customers', 'ultimate-multisite'),
			'target_products'  => __('Target Products', 'ultimate-multisite'),
			'date_created'     => __('Date', 'ultimate-multisite'),
			'id'               => __('ID', 'ultimate-multisite'),
		];

		return $columns;
	}

	/**
	 * Returns the filters for this page.
	 *
	 * @since 2.0.0
	 */
	public function get_filters(): array {

		return [
			'filters'      => [
				'type'   => [
					'label'   => __('Broadcast Type', 'ultimate-multisite'),
					'options' => [
						'broadcast_notice' => __('Email', 'ultimate-multisite'),
						'broadcast_email'  => __('Notices', 'ultimate-multisite'),
					],
				],
				'status' => [
					'label'   => __('Notice Type', 'ultimate-multisite'),
					'options' => [
						'info'    => __('Info - Blue', 'ultimate-multisite'),
						'success' => __('Success - Green', 'ultimate-multisite'),
						'warning' => __('Warning - Yellow', 'ultimate-multisite'),
						'error'   => __('Error - Red', 'ultimate-multisite'),
					],
				],
			],
			'date_filters' => [
				'date_created' => [
					'label'   => __('Date', 'ultimate-multisite'),
					'options' => $this->get_default_date_filter_options(),
				],
			],
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
			'all'              => [
				'field' => 'status',
				'url'   => add_query_arg('type', 'all'),
				'label' => __('All Broadcasts', 'ultimate-multisite'),
				'count' => 0,
			],
			'broadcast_email'  => [
				'field' => 'type',
				'url'   => add_query_arg('type', 'broadcast_email'),
				'label' => __('Emails', 'ultimate-multisite'),
				'count' => 0,
			],
			'broadcast_notice' => [
				'field' => 'type',
				'url'   => add_query_arg('type', 'broadcast_notice'),
				'label' => __('Notices', 'ultimate-multisite'),
				'count' => 0,
			],
		];
	}
}
