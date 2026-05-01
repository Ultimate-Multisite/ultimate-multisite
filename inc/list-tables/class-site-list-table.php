<?php
/**
 * Site List Table class.
 *
 * @package WP_Ultimo
 * @subpackage List_Table
 * @since 2.0.0
 */

namespace WP_Ultimo\List_Tables;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Site List Table class.
 *
 * @since 2.0.0
 */
class Site_List_Table extends Base_List_Table {

	/**
	 * Holds the query class for the object being listed.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $query_class = \WP_Ultimo\Database\Sites\Site_Query::class;

	/**
	 * Initializes the table.
	 *
	 * @since 2.0.0
	 */
	public function __construct() {

		$this->modes = [
			'grid' => __('Grid View', 'ultimate-multisite'),
			'list' => __('List View', 'ultimate-multisite'),
		];

		parent::__construct(
			[
				'singular' => __('Site', 'ultimate-multisite'),  // singular name of the listed records
				'plural'   => __('Sites', 'ultimate-multisite'), // plural name of the listed records
				'ajax'     => true,                     // does this table support ajax?
				'add_new'  => [
					'url'     => wu_get_form_url('add_new_site'),
					'classes' => 'wubox',
				],
			]
		);
	}

	/**
	 * Overrides the parent method to add pending sites.
	 *
	 * In the "All Sites" view (type=all or no type parameter) pending sites are
	 * always prepended to the list.  Pending sites live in membership meta rather
	 * than in the wp_blogs table, so they are fetched separately and merged into
	 * the result set with proper pagination awareness.
	 *
	 * @since 2.0.0
	 *
	 * @param integer $per_page Number of items to display per page.
	 * @param integer $page_number Current page.
	 * @param boolean $count If we should count records or return the actual records.
	 * @return array|int
	 */
	public function get_items($per_page = 5, $page_number = 1, $count = false) {

		$type = wu_request('type');

		if ('pending' === $type) {
			$pending_sites = \WP_Ultimo\Models\Site::get_all_by_type('pending');

			return $count ? count($pending_sites) : $pending_sites;
		}

		/*
		 * For the "All Sites" view (type=all or no type), always fetch pending
		 * sites so they can be prepended to the regular list.
		 */
		$pending_sites = [];

		if ( ! $type || 'all' === $type ) {
			$pending_sites = \WP_Ultimo\Models\Site::get_all_by_type('pending');
		}

		$pending_count = count($pending_sites);

		$query = [
			'number' => $per_page,
			'offset' => ($page_number - 1) * $per_page,
			'count'  => $count,
			'search' => wu_request('s'),
		];

		if ($type && 'all' !== $type) {
			$query['meta_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'type' => [
					'key'   => 'wu_type',
					'value' => $type,
				],
			];
		}

		$query = apply_filters("wu_{$this->id}_get_items", $query, $this);

		if ($count) {
			return (int) wu_get_sites($query) + $pending_count;
		}

		if (0 === $pending_count) {
			return wu_get_sites($query);
		}

		/*
		 * Pending sites always occupy virtual slots 0 … ($pending_count - 1).
		 * Regular sites follow from slot $pending_count onwards.
		 *
		 * For the current page we calculate which portion of that virtual list
		 * falls within the page window [$virtual_start, $virtual_start + $per_page).
		 */
		$virtual_start = ($page_number - 1) * $per_page;

		if ($virtual_start < $pending_count) {
			// This page starts inside the pending block.
			$pending_offset  = $virtual_start;
			$pending_to_show = min($pending_count - $pending_offset, $per_page);
			$page_pending    = array_slice($pending_sites, $pending_offset, $pending_to_show);

			$regular_to_show = $per_page - $pending_to_show;

			if ($regular_to_show <= 0) {
				return $page_pending;
			}

			$query['number'] = $regular_to_show;
			$query['offset'] = 0;

			return array_merge($page_pending, wu_get_sites($query));
		}

		// This page is entirely within the regular sites block; shift the offset
		// back by the number of pending sites that precede all regular sites.
		$query['offset'] = $virtual_start - $pending_count;

		return wu_get_sites($query);
	}

	/**
	 * Render the bulk edit checkbox.
	 *
	 * Pending sites displayed inside the "All Sites" view cannot be bulk-actioned
	 * (the bulk-delete handler expects blog IDs, not membership IDs).  Return an
	 * empty string so no checkbox is rendered for them in that context; the row
	 * actions (Publish / Delete) remain available for individual management.
	 *
	 * @param \WP_Ultimo\Models\Site $item Site object.
	 */
	public function column_cb($item): string {

		if ($item->get_type() === 'pending') {
			/*
			 * In the dedicated "Pending" view, bulk-delete-pending uses
			 * membership IDs as values.  In any other view (e.g. "All Sites")
			 * skip the checkbox to avoid mixing IDs with regular bulk-delete.
			 */
			if ('pending' !== wu_request('type')) {
				return '';
			}

			return sprintf('<input type="checkbox" name="bulk-delete[]" value="%s" />', $item->get_membership_id());
		}

		return sprintf('<input type="checkbox" name="bulk-delete[]" value="%s" />', $item->get_id());
	}

	/**
	 * Displays the content of the name column.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_Ultimo\Models\Site $item Site object.
	 */
	public function column_path($item): string {

		$url_atts = [
			'id'    => $item->get_id(),
			'model' => 'site',
		];

		$title = sprintf('<a href="%s">%s</a>', wu_network_admin_url('wp-ultimo-edit-site', $url_atts), $item->get_title());

		// Concatenate the two blocks
		$title = "<strong>$title</strong>";

		$actions = [
			'edit'      => sprintf('<a href="%s">%s</a>', wu_network_admin_url('wp-ultimo-edit-site', $url_atts), __('Edit', 'ultimate-multisite')),
			'duplicate' => sprintf(
				'<a title="%s" class="wubox" href="%s">%s</a>',
				__('Duplicate Site', 'ultimate-multisite'),
				wu_get_form_url(
					'add_new_site',
					$url_atts
				),
				__('Duplicate', 'ultimate-multisite')
			),
			'delete'    => sprintf(
				'<a title="%s" class="wubox" href="%s">%s</a>',
				__('Delete', 'ultimate-multisite'),
				wu_get_form_url(
					'delete_modal',
					$url_atts
				),
				__('Delete', 'ultimate-multisite')
			),
		];

		if ($item->get_type() === 'pending') {
			$actions = [
				'duplicate' => sprintf(
					'<a title="%s" class="wubox" href="%s">%s</a>',
					__('Publish Site', 'ultimate-multisite'),
					wu_get_form_url(
						'publish_pending_site',
						['membership_id' => $item->get_membership_id()]
					),
					__('Publish', 'ultimate-multisite')
				),
				'delete'    => sprintf(
					'<a title="%s" class="wubox" href="%s">%s</a>',
					__('Delete', 'ultimate-multisite'),
					wu_get_form_url(
						'delete_modal',
						[
							'id'          => $item->get_membership_id(),
							'model'       => 'membership_meta_pending_site',
							'redirect_to' => rawurlencode(
								(string) wu_network_admin_url(
									'wp-ultimo-sites',
									[
										'type' => 'pending',
										'page' => wu_request('page', 1),
									]
								)
							),
						]
					),
					__('Delete', 'ultimate-multisite')
				),
			];
		}

		return $title . sprintf('<span class="wu-block">%s</span>', make_clickable($item->get_active_site_url())) . $this->row_actions($actions);
	}

	/**
	 * Returns the date of the customer registration.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_Ultimo\Models\Site $item Site object.
	 */
	public function column_date_registered($item): string {

		$time = strtotime((string) $item->get_last_login(false));

		return $item->get_date_registered() . sprintf('<br><small>%s</small>', human_time_diff($time));
	}

	/**
	 * Returns the blog_id.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_Ultimo\Models\Site $item Site object.
	 * @return string
	 */
	public function column_blog_id($item) {

		return $item->get_type() === \WP_Ultimo\Database\Sites\Site_Type::PENDING ? '--' : $item->get_blog_id();
	}

	/**
	 * Displays the type of the site.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_Ultimo\Models\Site $item Site object.
	 * @return string
	 */
	public function column_type($item) {

		$label = $item->get_type_label();

		$class = $item->get_type_class();

		return "<span class='wu-bg-gray-200 wu-py-1 wu-px-2 wu-leading-none wu-rounded-sm wu-text-xs wu-font-mono $class'>{$label}</span>";
	}

	/**
	 * Column for the domains associated with this site.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_Ultimo\Models\Site $item Site object.
	 */
	public function column_domains($item): string {

		$domain = wu_get_domains(
			[
				'blog_id' => $item->get_id(),
				'count'   => true,
			]
		);

		$url_atts = [
			'blog_id' => $item->get_id(),
		];

		$actions = [
			'view' => sprintf('<a href="%s">%s</a>', wu_network_admin_url('wp-ultimo-domains', $url_atts), __('View', 'ultimate-multisite')),
		];

		return $domain . $this->row_actions($actions);
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
			'path'              => __('URL', 'ultimate-multisite'),
			'type'              => __('Type', 'ultimate-multisite'),
			'customer'          => __('Customer', 'ultimate-multisite'),
			'membership'        => __('Membership', 'ultimate-multisite'),
			'domains'           => __('Domains', 'ultimate-multisite'),
			'blog_id'           => __('ID', 'ultimate-multisite'),
		];

		return $columns;
	}

	/**
	 * Renders the customer card for grid mode.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_Ultimo\Models\Customer $item The customer being shown.
	 * @return void
	 */
	public function single_row_grid($item): void {

		wu_get_template(
			'base/sites/grid-item',
			[
				'item'       => $item,
				'list_table' => $this,
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
			'filters'      => [
				'vip' => [
					'label'   => __('VIP Status', 'ultimate-multisite'),
					'options' => [
						'0' => __('Regular Sites', 'ultimate-multisite'),
						'1' => __('VIP Sites', 'ultimate-multisite'),
					],
				],
			],
			'date_filters' => [
				'last_login'      => [
					'label'   => __('Last Login', 'ultimate-multisite'),
					'options' => $this->get_default_date_filter_options(),
				],
				'date_registered' => [
					'label'   => __('Site Since', 'ultimate-multisite'),
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
			'all'            => [
				'field' => 'type',
				'url'   => add_query_arg('type', 'all'),
				'label' => __('All Sites', 'ultimate-multisite'),
				'count' => 0,
			],
			'customer_owned' => [
				'field' => 'type',
				'url'   => add_query_arg('type', 'customer_owned'),
				'label' => __('Customer-Owned', 'ultimate-multisite'),
				'count' => 0,
			],
			'site_template'  => [
				'field' => 'type',
				'url'   => add_query_arg('type', 'site_template'),
				'label' => __('Templates', 'ultimate-multisite'),
				'count' => 0,
			],
			'pending'        => [
				'field' => 'type',
				'url'   => add_query_arg('type', 'pending'),
				'label' => __('Pending', 'ultimate-multisite'),
				'count' => 0,
			],
			'demo'           => [
				'field' => 'type',
				'url'   => add_query_arg('type', 'demo'),
				'label' => __('Demo', 'ultimate-multisite'),
				'count' => 0,
			],
		];
	}

	/**
	 * Returns an associative array containing the bulk action
	 *
	 * @return array
	 */
	public function get_bulk_actions() {

		$actions = [
			'screenshot' => __('Take Screenshot', 'ultimate-multisite'),
		];

		$actions[ wu_request('type', 'all') === 'pending' ? 'delete-pending' : 'delete' ] = __('Delete', 'ultimate-multisite');

		/**
		 * Filter the bulk actions available for sites.
		 *
		 * @since 2.4.10
		 *
		 * @param array $actions The bulk actions.
		 * @return array
		 */
		return apply_filters('wu_site_list_get_bulk_actions', $actions);
	}

	/**
	 * Handles the bulk processing.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function process_single_action(): void {

		$action = $this->current_action();

		if ('duplicate' === $action) {
			$site_id = wu_request('id');

			$site = wu_get_site($site_id);

			if ( ! $site) {
				WP_Ultimo()->notices->add(__('Site not found.', 'ultimate-multisite'), 'error', 'network-admin');

				return;
			}

			$new_site = $site->duplicate();

			$new_path = sprintf('%s%s', trim((string) $new_site->get_path(), '/'), 'copy');

			$new_site->set_template_id($new_site->get_blog_id());

			$new_site->set_blog_id(0);

			$new_site->set_path($new_path);

			$new_site->site_date_registered(wu_get_current_time('mysql', true));

			$result = $new_site->save();

			if (is_wp_error($result)) {
				WP_Ultimo()->notices->add($result->get_error_message(), 'error', 'network-admin');

				return;
			}

			$redirect_url = wu_network_admin_url(
				'wp-ultimo-edit-site',
				[
					'id'      => $new_site->get_id(),
					'updated' => 1,
				]
			);

			wp_safe_redirect($redirect_url);

			exit;
		}
	}
}
