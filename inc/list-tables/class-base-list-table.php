<?php
/**
 * Base List Table class. Extends WP_List_Table.
 *
 * @package WP_Ultimo
 * @subpackage List_Table
 * @since 2.0.0
 */

namespace WP_Ultimo\List_Tables;

use Closure;
use WP_Ultimo\Helpers\Hash;

// Exit if accessed directly
defined('ABSPATH') || exit;

if ( ! class_exists('WP_List_Table')) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Base List Table class. Extends WP_List_Table.
 *
 * All of Ultimate Multisite's list tables should extend this class.
 * It provides ajax-filtering and pagination out-of-the-box among other cool features.
 *
 * @since 2.0.0
 */
class Base_List_Table extends \WP_List_Table {

	/**
	 * Holds the id for this list table. Used on filters.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $id;

	/**
	 * Holds the query class for the object being listed.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $query_class;

	/**
	 * Holds the labels, singular and plural, to be used when generating labels.
	 *
	 * @since 2.0.0
	 * @var array
	 */
	protected $labels = [
		'singular' => '',
		'plural'   => '',
	];

	/**
	 * Keeps track of the current view mode for this particular list table.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	public $current_mode = 'list';

	/**
	 * Sets the allowed modes.
	 *
	 * @since 2.0.0
	 * @var array
	 */
	public $modes = ['list' => 'List'];

	/**
	 * The list table context.
	 *
	 * Can be page, if the table is on a list page;
	 * or widget, if the table is inside a widget.
	 *
	 * We use this to determine how to encapsulate the fields for filtering
	 * and to support multiple list tables with ajax pagination per page.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	public $context = 'page';

	/**
	 * Returns the table id.
	 *
	 * @since 2.0.0
	 */
	public function get_table_id(): string {

		return strtolower(substr(strrchr(static::class, '\\'), 1));
	}

	/**
	 * Changes the context of the list table.
	 *
	 * Available contexts are 'page' and 'widget'.
	 *
	 * @since 2.0.0
	 *
	 * @param string $context The new context to set.
	 * @return void
	 */
	public function set_context($context = 'page'): void {

		$this->context = $context;
	}

	/**
	 * Initializes the table.
	 *
	 * @since 2.0.0
	 *
	 * @param array $args Arguments of the list table.
	 */
	public function __construct($args = []) {

		$this->id = $this->get_table_id();

		$args = wp_parse_args(
			$args,
			[
				'screen' => $this->id,
			]
		);

		parent::__construct($args);

		$this->labels = shortcode_atts($this->labels, $args);

		add_action('admin_enqueue_scripts', [$this, 'register_scripts']);

		add_action('in_admin_header', [$this, 'add_default_screen_options']);

		$this->set_list_mode();

		$this->_args['add_new'] = wu_get_isset($args, 'add_new', []);
	}

	/**
	 * Adds the screen option fields.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function add_default_screen_options(): void {

		$args = [
			'default' => 20,
			'label'   => $this->get_per_page_option_label(),
			'option'  => $this->get_per_page_option_name(),
		];

		add_screen_option('per_page', $args);
	}

	/**
	 * Adds the select all button for the Grid Mode.
	 *
	 * @since 2.0.0
	 *
	 * @param string $which Bottom or top navbar.
	 * @return void
	 */
	protected function extra_tablenav($which) {

		if ('grid' === $this->current_mode) {
			printf(
				'<button id="cb-select-all-grid" v-on:click.prevent="select_all" class="button">%s</button>',
				esc_html__('Select All', 'ultimate-multisite')
			);
		}
	}

	/**
	 * Set the list display mode for the list table.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function set_list_mode(): void {

		if ('page' !== $this->context) {
			$this->current_mode = 'list';

			return;
		}

		$list_table_name = $this->id;

		if ( ! empty($_REQUEST['mode']) && in_array($_REQUEST['mode'], array_keys($this->modes), true)) { // phpcs:ignore WordPress.Security.NonceVerification
			$mode = sanitize_text_field(wp_unslash($_REQUEST['mode'])); // phpcs:ignore WordPress.Security.NonceVerification
			set_user_setting("{$list_table_name}_list_mode", $mode);
		} else {
			$mode = get_user_setting("{$list_table_name}_list_mode", current(array_keys($this->modes)));
		}

		$this->current_mode = $mode;
	}

	/**
	 * Returns a label.
	 *
	 * @since 2.0.0
	 *
	 * @param string $label singular or plural.
	 * @return string
	 */
	public function get_label($label = 'singular') {

		return $this->labels[ $label ] ?? 'Object';
	}

	/**
	 * Uses the query class to return the items to be displayed.
	 *
	 * @since 2.0.0
	 *
	 * @param integer $per_page Number of items to display per page.
	 * @param integer $page_number Current page.
	 * @param boolean $count If we should count records or return the actual records.
	 * @return array|int
	 */
	public function get_items($per_page = 5, $page_number = 1, $count = false) {

		$query_args = [
			'number'  => $per_page,
			'offset'  => ($page_number - 1) * $per_page,
			'orderby' => wu_request('orderby', 'id'),
			'order'   => wu_request('order', 'DESC'),
			'search'  => wu_request('s', false),
			'count'   => $count,
		];

		$extra_query_args = [
			'status',
			'type',
		];

		foreach ($extra_query_args as $extra_query_arg) {
			$query = wu_request($extra_query_arg, 'all');

			if ('all' !== $query) {
				$query_args[ $extra_query_arg ] = $query;
			}
		}

		/**
		 * Accounts for hashes
		 */
		if (isset($query_args['search']) && strlen((string) $query_args['search']) === Hash::LENGTH) {
			$item_id = Hash::decode($query_args['search']);

			if ($item_id) {
				unset($query_args['search']);

				$query_args['id'] = $item_id;
			}
		}

		return $this->_get_items($query_args);
	}

	/**
	 * General purpose get_items.
	 *
	 * @since 2.0.0
	 *
	 * @param array $query_args The query args.
	 * @return mixed
	 */
	protected function _get_items($query_args) { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore

		$query_class = new $this->query_class();

		$query_args = array_merge($query_args, array_filter($this->get_extra_query_fields()));

		$query_args = apply_filters("wu_{$this->id}_get_items", $query_args, $this);

		$function_name = 'wu_get_' . $query_class->get_plural_name();

		if (function_exists($function_name)) {
			$query = $function_name($query_args);
		} else {
			$query = $query_class->query($query_args);
		}

		return $query;
	}

	/**
	 * Checks if we have any items at all.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function has_items() {

		if ( ! empty($this->items)) {
			return true;
		}

		$key = $this->get_table_id();

		$results = $this->get_items(1, 1, false);

		return (int) $results > 0;
	}

	/**
	 * Returns the total record count. Used on pagination.
	 *
	 * @since 2.0.0
	 * @return int
	 */
	public function record_count() {

		return $this->get_items(9_999_999, 1, true);
	}
	/**
	 * Returns the slug of the per_page option for this data type.
	 *
	 * @since 2.0.0
	 */
	public function get_per_page_option_name(): string {

		return sprintf('%s_per_page', $this->id);
	}
	/**
	 * Returns the label for the per_page option for this data_type.
	 *
	 * @since 2.0.0
	 */
	public function get_per_page_option_label(): string {

		// translators: %s will be replaced by the data type plural name. e.g. Books.
		return sprintf(__('%s per page', 'ultimate-multisite'), $this->get_label('plural'));
	}

	/**
	 * Uses the query class to determine if there's any searchable fields.
	 * If that's the case, we automatically add the search field.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	protected function has_search() {

		return ! empty($this->get_schema_columns(['searchable' => true]));
	}
	/**
	 * Generates the search field label, based on the table labels.
	 *
	 * @since 2.0.0
	 */
	public function get_search_input_label(): string {

		// translators: %s will be replaced with the data type plural name. e.g. Books.
		return sprintf(__('Search %s', 'ultimate-multisite'), $this->get_label('plural'));
	}

	/**
	 * Prepare the list table before actually displaying records.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function prepare_items(): void {

		$this->_column_headers = $this->get_column_info();

		$per_page = $this->get_items_per_page($this->get_per_page_option_name(), 10);

		$current_page = $this->get_pagenum();

		$total_items = $this->record_count();

		$this->set_pagination_args(
			[
				'total_items' => $total_items, // We have to calculate the total number of items.
				'per_page'    => $per_page,     // We have to determine how many items to show on a page.
			]
		);

		$this->items = $this->get_items($per_page, $current_page);
	}

	/**
	 * Register Scripts that might be needed for ajax pagination and so on.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function register_scripts(): void {

		wp_localize_script(
			'wu-ajax-list-table',
			'wu_list_table',
			[
				'base_url' => wu_get_form_url('bulk_actions'),
				'model'    => strstr($this->get_table_id(), '_', true),
				'i18n'     => [
					'confirm' => __('Confirm Action', 'ultimate-multisite'),
				],
			]
		);

		wp_enqueue_script('wu-ajax-list-table');
	}

	/**
	 * Adds the hidden fields necessary to handle pagination.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function display_ajax_filters(): void {

		/**
		 * Add the nonce field before we generate the results
		 */
		wp_nonce_field(sprintf('ajax-%s-nonce', $this->_get_js_var_name()), sprintf('_ajax_%s_nonce', $this->_get_js_var_name()));

		/**
		 * Page attribute to be used with the push state
		 */
		printf('<input type="hidden" id="page" name="page" value="%s" />', esc_attr(wu_request('page', '')));

		/**
		 * ID attribute to be used with the push state
		 */
		if (wu_request('id')) {
			printf('<input type="hidden" id="id" name="id" value="%s" />', esc_attr(wu_request('id')));
		}

		foreach ($this->get_hidden_fields() as $field_name => $field_value) {

			/**
			 * Add a hidden field to later be sent via the ajax call.
			 */
			printf('<input type="hidden" id="%s" name="%s" value="%s" />', esc_attr($field_name), esc_attr($field_name), esc_attr($field_value));
		}
	}

	/**
	 * Handles the default display for list mode.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function display_view_list(): void {

		printf('<div id="wu-%s" class="wu-list-table wu-mode-list">', esc_attr($this->id));

		$this->display_ajax_filters();

		/**
		 * Call parents implementation.
		 */
		parent::display();

		echo '</div>';
	}

	/**
	 * Handles the default display for grid mode.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function display_view_grid(): void {

		printf('<div id="wu-%s" class="wu-list-table wu-mode-grid">', esc_attr($this->id));

		$this->display_ajax_filters();

		wu_get_template(
			'base/grid',
			[
				'table' => $this,
			]
		);

		echo '</div>';
	}

	/**
	 * Displays the table.
	 *
	 * Adds a Nonce field and calls parent's display method.
	 *
	 * @since 3.1.0
	 * @access public
	 */
	public function display(): void {
		/*
		 * Any items at all?
		 */
		if ( ! $this->has_items() && 'page' === $this->context) {
			wu_render_empty_state(
				[
					// translators: %s name of items in list.
					'message'      => sprintf(__("You don't have any %s yet.", 'ultimate-multisite'), $this->labels['plural']),
					'sub_message'  => $this->_args['add_new'] ? __('How about we create a new one?', 'ultimate-multisite') : __('...but you will see them here once they get created.', 'ultimate-multisite'),
					// translators: %s is the singular value of the model, such as Product, or Payment.
					'link_label'   => sprintf(__('Create a new %s', 'ultimate-multisite'), $this->labels['singular']),
					'link_url'     => wu_get_isset($this->_args['add_new'], 'url', ''),
					'link_classes' => wu_get_isset($this->_args['add_new'], 'classes', ''),
					'link_icon'    => 'dashicons-wu-circle-with-plus',
				]
			);
		} else {
			call_user_func([$this, "display_view_{$this->current_mode}"]);
		}
	}

	/**
	 * Returns the filters for this page.
	 *
	 * @since 2.0.0
	 */
	public function get_filters(): array {

		return [
			'filters'      => [],
			'date_filters' => [],
		];
	}

	/**
	 * Display the filters if they exist.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function filters(): void {

		$filters = $this->get_filters();

		$views = apply_filters("wu_{$this->id}_get_views", $this->get_views());

		$args = array_merge(
			$filters,
			[
				'filters_el_id'   => sprintf('%s-filters', $this->id),
				'has_search'      => $this->has_search(),
				'search_label'    => $this->get_search_input_label(),
				'views'           => $views,
				'has_view_switch' => ! empty($this->modes),
				'table'           => $this,
			]
		);

		wu_get_template('base/filter', $args);
	}

	/**
	 * Overrides the single row method to create different methods depending on the mode.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed $item The line item being displayed.
	 * @return void
	 */
	public function single_row($item): void {

		call_user_func([$this, "single_row_{$this->current_mode}"], $item);
	}

	/**
	 * Handles the item display for list mode.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed $item The line item being displayed.
	 * @return void
	 */
	public function single_row_list($item): void {

		parent::single_row($item);
	}

	/**
	 * Handles the item display for grid mode.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed $item The line item being displayed.
	 * @return void
	 */
	public function single_row_grid($item) {}

	/**
	 * Displays a base div when there is not item.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function no_items(): void {

		printf(
			'<div class="wu-py-6 wu-text-gray-600 wu-text-sm wu-text-center">
			<span class="">%s</span>
		</div>',
			esc_html__('No items found', 'ultimate-multisite')
		);
	}

	/**
	 * Returns an associative array containing the bulk action
	 *
	 * @return array
	 */
	public function get_bulk_actions() {

		$default_bulk_actions = [
			'delete' => __('Delete', 'ultimate-multisite'),
		];

		$has_active = $this->get_schema_columns(
			[
				'name' => 'active',
			]
		);

		if ($has_active) {
			$default_bulk_actions['activate']   = __('Activate', 'ultimate-multisite');
			$default_bulk_actions['deactivate'] = __('Deactivate', 'ultimate-multisite');
		}

		return apply_filters('wu_bulk_actions', $default_bulk_actions, $this->id);
	}

	/**
	 * Process single action.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function process_single_action() {}

	/**
	 * Handles the bulk processing.
	 *
	 * @since 2.0.0
	 * @return bool|\WP_Error
	 */
	public static function process_bulk_action() {

		global $wpdb;

		$bulk_action = wu_request('bulk_action');

		$model = wu_request('model');

		if ('checkout' === $model) {
			$model = 'checkout_form';
		} elseif ('discount' === $model) {
			$model = 'discount_code';
		}

		$item_ids = explode(',', (string) wu_request('ids', ''));

		$prefix = apply_filters('wu_bulk_action_function_prefix', 'wu_get_', $model);

		$func_name = $prefix . $model;

		if ( ! function_exists($func_name)) {
			return new \WP_Error('func-not-exists', __('Something went wrong.', 'ultimate-multisite'));
		}

		switch ($bulk_action) {
			case 'activate':
				foreach ($item_ids as $item_id) {
					$item = $func_name($item_id);

					$item->set_active(true);

					$item->save();
				}

				break;

			case 'deactivate':
				foreach ($item_ids as $item_id) {
					$item = $func_name($item_id);

					$item->set_active(false);

					$item->save();
				}

				break;

			case 'delete':
				foreach ($item_ids as $item_id) {
					$item = $func_name($item_id);
					if ($item) {
						$item->delete();
					}
				}

				break;

			default:
				do_action('wu_process_bulk_action', $bulk_action);
				break;
		}

		return true;
	}

	/**
	 * Handles ajax requests for pagination and filtering.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function ajax_response(): void {

		check_ajax_referer(sprintf('ajax-%s-nonce', $this->_get_js_var_name()), sprintf('_ajax_%s_nonce', $this->_get_js_var_name()));

		$this->set_context('ajax');

		$this->prepare_items();

		extract($this->_args); // phpcs:ignore

		extract($this->_pagination_args, EXTR_SKIP); // phpcs:ignore

		/**
		 * Load the rows
		 */
		ob_start();

		if ( ! empty($_REQUEST['no_placeholder'])) {
			$this->display_rows();
		} else {
			$this->display_rows_or_placeholder();
		}

		$rows = ob_get_clean();

		/**
		 * Get headers into a variable
		 */
		ob_start();

		$this->print_column_headers();

		$headers = ob_get_clean();

		/**
		 * Get the top bar into a variable
		 */
		ob_start();

		$this->pagination('top');

		$pagination_top = ob_get_clean();

		/**
		 * Get the bottom nav into a variable
		 */
		ob_start();

		$this->pagination('bottom');

		$pagination_bottom = ob_get_clean();

		/**
		 * Build the response
		 */
		$response                         = ['rows' => $rows];
		$response['pagination']['top']    = $pagination_top;
		$response['pagination']['bottom'] = $pagination_bottom;
		$response['column_headers']       = $headers;
		$response['count']                = $this->record_count();
		$response['type']                 = wu_request('type', 'all');

		if (isset($total_items)) {
			// translators: %s number of total items.
			$response['total_items_i18n'] = sprintf(_n('%s item', '%s items', $total_items, 'ultimate-multisite'), number_format_i18n($total_items));
		}

		if (isset($total_pages)) {
			$response['total_pages']      = $total_pages;
			$response['total_pages_i18n'] = number_format_i18n($total_pages);
		}

		/**
		 * Send the response
		 */
		wp_send_json($response);

		exit;
	}

	/**
	 * Render a column when no column specific method exist.
	 *
	 * @param array  $item Item object/array.
	 * @param string $column_name Column name being displayed.
	 *
	 * @return string
	 */
	public function column_default($item, $column_name) {

		$value = call_user_func([$item, "get_{$column_name}"]);

		$datetime_columns = array_column(
			$this->get_schema_columns(
				[
					'date_query' => true,
				]
			),
			'name'
		);

		if (in_array($column_name, $datetime_columns, true)) {
			return $this->_column_datetime($value);
		}

		return $value;
	}

	/**
	 * Handles the default displaying of datetime columns.
	 *
	 * @since 2.0.0
	 *
	 * @param string $date Valid date to be used inside a strtotime call.
	 * @return string
	 */
	public function _column_datetime($date) { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore

		if ( ! wu_validate_date($date)) {
			return __('--', 'ultimate-multisite');
		}

		$time = strtotime(get_date_from_gmt((string) $date));

		$formatted_value = date_i18n(get_option('date_format'), $time);

		// translators: %s is a relative past date.
		$placeholder = wu_get_current_time('timestamp') > $time ? esc_html__('%s ago', 'ultimate-multisite') : esc_html__('In %s', 'ultimate-multisite');

		$text = esc_html($formatted_value) . sprintf('<br><small>%s</small>', esc_html(sprintf($placeholder, human_time_diff($time))));

		return sprintf('<span role="tooltip" aria-label="%s">%s</span>', esc_attr(date_i18n('Y-m-d H:i:s', $time)), $text);
	}

	/**
	 * Returns the membership object associated with this object.
	 *
	 * @since 2.0.0
	 *
	 * @param object $item Object.
	 * @return void
	 */
	public function column_membership($item): void {

		$membership = $item->get_membership();

		if ( ! $membership) {
			?>
			<div class='wu-table-card  wu-text-gray-700 wu-py-1 wu-px-2 wu-flex wu-flex-grow wu-block wu-rounded wu-items-center wu-border wu-border-solid wu-border-gray-300 wu-relative wu-overflow-hidden'>
				<span class='dashicons dashicons-wu-block wu-text-gray-600 wu-px-1 wu-pr-3'>&nbsp;</span>
				<div class=''>
					<span class='wu-block wu-py-3 wu-text-gray-600 wu-text-2xs wu-font-bold wu-uppercase'><?php esc_html_e('No membership found', 'ultimate-multisite'); ?></span>
				</div>
			</div>
			<?php
			return;
		}

		$url_atts = [
			'id' => $membership->get_id(),
		];

		$status_classes = $membership->get_status_class();

		$id = $membership->get_id();

		$reference = $membership->get_hash();

		$description = $membership->get_price_description();

		$membership_link = wu_network_admin_url('wp-ultimo-edit-membership', $url_atts);

		printf(
			"<a href='%s' class='wu-no-underline wu-table-card wu-text-gray-700 wu-py-1 wu-px-2 wu-pl-4 wu-flex wu-flex-grow wu-block wu-rounded wu-items-center wu-border wu-border-solid wu-border-gray-300 wu-relative wu-overflow-hidden'>
			<div class='wu-absolute wu-top-0 wu-bottom-0 wu-left-0 wu-w-2 %s'>&nbsp;</div>
			<div class=''>
				<strong class='wu-block'>%s <small class='wu-font-normal'>(#%s)</small></strong>
				<small>%s</small>
			</div>
		</a>",
			esc_attr($membership_link),
			esc_attr($status_classes),
			esc_html($reference),
			esc_html($id),
			esc_html($description)
		);
	}

	/**
	 * Returns the payment object associated with this object.
	 *
	 * @since 2.0.0
	 *
	 * @param object $item Object.
	 * @return void
	 */
	public function column_payment($item): void {

		$payment = $item->get_payment();

		if ( ! $payment) {
			$not_found = __('No payment found', 'ultimate-multisite');

			printf(
				"<div class='wu-table-card  wu-text-gray-700 wu-py-1 wu-px-2 wu-flex wu-flex-grow wu-block wu-rounded wu-items-center wu-border wu-border-solid wu-border-gray-300 wu-relative wu-overflow-hidden'>
				<span class='dashicons dashicons-wu-block wu-text-gray-600 wu-px-1 wu-pr-3'>&nbsp;</span>
				<div class=''>
					<span class='wu-block wu-py-3 wu-text-gray-600 wu-text-2xs wu-font-bold wu-uppercase'>%s</span>
				</div>
			</div>",
				esc_html($not_found)
			);
		}

		$url_atts = [
			'id' => $payment->get_id(),
		];

		$status_classes = $payment->get_status_class();

		$id = $payment->get_id();

		$reference = $payment->get_hash();
		// translators: %s the payment total.
		$description = sprintf(__('Total %s', 'ultimate-multisite'), wu_format_currency($payment->get_total(), $payment->get_currency()));

		$payment_link = wu_network_admin_url('wp-ultimo-edit-payment', $url_atts);

		printf(
			"<a href='%s' class='wu-no-underline wu-table-card wu-text-gray-700 wu-py-1 wu-px-2 wu-pl-4 wu-flex wu-flex-grow wu-block wu-rounded wu-items-center wu-border wu-border-solid wu-border-gray-300 wu-relative wu-overflow-hidden'>
			<div class='wu-absolute wu-top-0 wu-bottom-0 wu-left-0 wu-w-2 %s'>&nbsp;</div>
			<div class=''>
				<strong class='wu-block'>%s <small class='wu-font-normal'>(#%s)</small></strong>
				<small>%s</small>
			</div>
		</a>",
			esc_attr($payment_link),
			esc_attr($status_classes),
			esc_html($reference),
			esc_html($id),
			esc_html($description)
		);
	}

	/**
	 * Returns the customer object associated with this object.
	 *
	 * @since 2.0.0
	 *
	 * @param object $item Object.
	 * @return string
	 */
	public function column_customer($item) {

		$customer = $item->get_customer();

		if ( ! $customer) {
			$not_found = __('No customer found', 'ultimate-multisite');

			return "<div class='wu-table-card  wu-text-gray-700 wu-py-1 wu-px-2 wu-flex wu-flex-grow wu-rounded wu-items-center wu-border wu-border-solid wu-border-gray-300 wu-relative wu-overflow-hidden'>
				<span class='dashicons dashicons-wu-block wu-text-gray-600 wu-px-1 wu-pr-3'>&nbsp;</span>
				<div class=''>
					<span class='wu-block wu-py-3 wu-text-gray-600 wu-text-2xs wu-font-bold wu-uppercase'>{$not_found}</span>
				</div>
			</div>";
		}

		$url_atts = [
			'id' => $customer->get_id(),
		];

		$avatar = get_avatar(
			$customer->get_user_id(),
			32,
			'identicon',
			'',
			[
				'force_display' => true,
				'class'         => 'wu-rounded-full wu-mr-2',
			]
		);

		$display_name = $customer->get_display_name();

		$id = $customer->get_id();

		$email = $customer->get_email_address();

		$customer_link = wu_network_admin_url('wp-ultimo-edit-customer', $url_atts);

		$html = "<a href='{$customer_link}' class='wu-no-underline wu-table-card wu-text-gray-700 wu-p-1 wu-flex wu-flex-grow wu-rounded wu-items-center wu-border wu-border-solid wu-border-gray-300'>
			{$avatar}
			<div class='wu-flex-wrap wu-overflow-hidden'>
				<strong class='wu-block wu-flex-grow wu-truncate'>{$display_name} <small class='wu-font-normal'>(#{$id})</small></strong>
				<small class='wu-truncate wu-block'>{$email}</small>
			</div>
		</a>";

		return $html;
	}

	/**
	 * Returns the product object associated with this object.
	 *
	 * @since 2.0.0
	 *
	 * @param object $item Object.
	 * @return string
	 */
	public function column_product($item) {

		$product = $item->get_plan();

		if ( ! $product) {
			$not_found = __('No product found', 'ultimate-multisite');

			return "<div class='wu-table-card wu-text-gray-700 wu-py-1 wu-px-2 wu-flex wu-flex-grow wu-rounded wu-items-center wu-border wu-border-solid wu-border-gray-300 wu-relative wu-overflow-hidden'>
				<span class='dashicons dashicons-wu-block wu-text-gray-600 wu-px-1 wu-pr-3'>&nbsp;</span>
				<div class=''>
					<span class='wu-block wu-py-3 wu-text-gray-600 wu-text-2xs wu-font-bold wu-uppercase'>{$not_found}</span>
				</div>
			</div>";
		}

		$url_atts = [
			'id' => $product->get_id(),
		];

		$image = $product->get_featured_image('thumbnail');

		$image = $image ? sprintf('<img class="wu-w-7 wu-h-7 wu-rounded wu-mr-3" src="%s">', esc_attr($image)) : '
			<div class="wu-w-7 wu-h-7 wu-bg-gray-200 wu-rounded wu-text-gray-600 wu-flex wu-items-center wu-justify-center wu-mr-2 wu-ml-1">
				<span class="dashicons-wu-image"></span>
			</div>';

		$name = $product->get_name();

		$id = $product->get_id();

		$description = wu_slug_to_name($product->get_type());

		$product_link = wu_network_admin_url('wp-ultimo-edit-product', $url_atts);

		$html = "<a href='{$product_link}' class='wu-table-card wu-no-underline wu-text-gray-700 wu-p-1 wu-flex wu-flex-grow wu-rounded wu-items-center wu-border wu-border-solid wu-border-gray-300'>
			{$image}
			<div class=''>
				<strong class='wu-block'>{$name} <small class='wu-font-normal'>(#{$id})</small></strong>
				<small>{$description}</small>
			</div>
		</a>";

		return $html;
	}

	/**
	 * Returns the site object associated with this object.
	 *
	 * @since 2.0.0
	 *
	 * @param object $item Object.
	 * @return string
	 */
	public function column_blog_id($item) {

		$site = $item->get_site();

		if ( ! $site) {
			$not_found = __('No site found', 'ultimate-multisite');

			return "<div class='wu-table-card  wu-text-gray-700 wu-py-0 wu-px-2 wu-flex wu-flex-grow wu-block wu-rounded wu-items-center wu-border wu-border-solid wu-border-gray-300 wu-relative wu-overflow-hidden'>
				<span class='dashicons dashicons-wu-block wu-text-gray-600 wu-px-1 wu-pr-3'>&nbsp;</span>
				<div class=''>
					<span class='wu-block wu-py-3 wu-text-gray-600 wu-text-2xs wu-font-bold wu-uppercase'>{$not_found}</span>
				</div>
			</div>";
		}

		$url_atts = [
			'id' => $site->get_id(),
		];

		$site_link = wu_network_admin_url('wp-ultimo-edit-site', $url_atts);

		$avatar = $site->get_featured_image();

		$title = $site->get_title();

		$html = "<a href='{$site_link}' class='wu-table-card wu-no-underline wu-text-gray-700 wu-p-1 wu-flex wu-flex-grow wu-block wu-rounded wu-items-center wu-border wu-border-solid wu-border-gray-300'>
			<img class='wu-rounded wu-mr-3' height='40' width='40' src='{$avatar}'>
			<div class='wu-flex wu-flex-wrap wu-overflow-hidden'>
				<strong class='wu-w-full wu-truncate'>{$title}</strong>
				<small class='wu-w-full wu-truncate'>{$site->get_active_site_url()}</small>
			</div>
		</a>";

		return $html;
	}
	/**
	 * Display the column for feature image.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_Ultimo\Models\Product $item Product object.
	 */
	public function column_featured_image_id($item): string {

		$image = $item->get_featured_image('thumbnail');

		$large_image = $item->get_featured_image('large');

		if ( ! $image) {
			return '
			<div class="wu-w-thumb wu-h-thumb wu-bg-gray-200 wu-rounded wu-text-gray-600 wu-flex wu-items-center wu-justify-center">
				<span class="dashicons-wu-image"></span>
			</div>';
		}

		return sprintf(
			'<div class="wu-w-thumb wu-h-thumb wu-bg-gray-200 wu-rounded wu-overflow-hidden wu-text-center">
			<img src="%s" class="wu-object-cover wu-w-thumb wu-h-thumb wu-image-preview" data-image="%s">
		</div>',
			$image,
			$large_image
		);
	}

	/**
	 * Render the bulk edit checkbox.
	 *
	 * @param \WP_Ultimo\Models\Product $item Product object.
	 *
	 * @return string
	 */
	public function column_cb($item) {

		return sprintf('<input type="checkbox" name="bulk-delete[]" value="%s" />', $item->get_id());
	}
	/**
	 * Return the js var name. This will be used on other places.
	 *
	 * @since 2.0.0
	 */
	public function _get_js_var_name(): string { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore

		return str_replace('-', '_', $this->id);
	}

	/**
	 * Overrides the parent method to include the custom ajax functionality for Ultimate Multisite.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function _js_vars(): void { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore

		/**
		 * Call the parent method for backwards compat.
		 */
		parent::_js_vars();

		$table_id = $this->_get_js_var_name();

		$inline_script = sprintf(
			'document.addEventListener("DOMContentLoaded", function() {
				let table_id = %s;
				if (typeof window[table_id] === "undefined") {
					window[table_id + "_config"] = %s;
					window[table_id] = wu_create_list(table_id).init();
				}
			});',
			wp_json_encode($table_id),
			wp_json_encode(
				[
					'filters' => $this->get_filters(),
					'context' => $this->context,
				]
			)
		);

		wp_add_inline_script('wu-ajax-list-table', $inline_script);
	}

	/**
	 * Get the default date filter options.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_default_date_filter_options() {

		return [
			'all'           => [
				'label'  => __('All', 'ultimate-multisite'),
				'after'  => null,
				'before' => null,
			],
			'today'         => [
				'label'  => __('Today', 'ultimate-multisite'),
				'after'  => date_i18n('Y-m-d 00:00:00', strtotime('today')),
				'before' => date_i18n('Y-m-d 23:59:59', strtotime('today')),
			],
			'yesterday'     => [
				'label'  => __('Yesterday', 'ultimate-multisite'),
				'after'  => date_i18n('Y-m-d 00:00:00', strtotime('yesterday')),
				'before' => date_i18n('Y-m-d 23:59:59', strtotime('yesterday')),
			],
			'last_week'     => [
				'label'  => __('Last 7 Days', 'ultimate-multisite'),
				'after'  => date_i18n('Y-m-d 00:00:00', strtotime('last week')),
				'before' => date_i18n('Y-m-d 23:59:59', strtotime('today')),
			],
			'last_month'    => [
				'label'  => __('Last 30 Days', 'ultimate-multisite'),
				'after'  => date_i18n('Y-m-d 00:00:00', strtotime('last month')),
				'before' => date_i18n('Y-m-d 23:59:59', strtotime('today')),
			],
			'current_month' => [
				'label'  => __('Current Month', 'ultimate-multisite'),
				'after'  => date_i18n('Y-m-d 00:00:00', strtotime('first day of this month')),
				'before' => date_i18n('Y-m-d 23:59:59', strtotime('today')),
			],
			'last_year'     => [
				'label'  => __('Last 12 Months', 'ultimate-multisite'),
				'after'  => date_i18n('Y-m-d 00:00:00', strtotime('last year')),
				'before' => date_i18n('Y-m-d 23:59:59', strtotime('today')),
			],
			'year_to_date'  => [
				'label'  => __('Year to Date', 'ultimate-multisite'),
				'after'  => date_i18n('Y-m-d 00:00:00', strtotime('first day of january this year')),
				'before' => date_i18n('Y-m-d 23:59:59', strtotime('today')),
			],
			'custom'        => [
				'label'  => __('Custom', 'ultimate-multisite'),
				'after'  => null,
				'before' => null,
			],
		];
	}

	/**
	 * Returns the columns from the BerlinDB Schema.
	 *
	 * @since 2.0.0
	 *
	 * @param array   $args Key => Value pair to search the return columns. e.g. array('searchable' => true).
	 * @param string  $operator How to use the $args arrays in the search. As logic and's or or's.
	 * @param boolean $field Field to return.
	 * @return array.
	 */
	protected function get_schema_columns($args = [], $operator = 'and', $field = false) {
		return (new $this->query_class())->get_columns($args, $operator, $field);
	}

	/**
	 * Returns sortable columns on the schema.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {

		$sortable_columns_from_schema = $this->get_schema_columns(
			[
				'sortable' => true,
			]
		);

		$sortable_columns = [];

		foreach ($sortable_columns_from_schema as $sortable_column_from_schema) {
			$sortable_columns[ $sortable_column_from_schema->name ] = [$sortable_column_from_schema->name, false];
		}

		return $sortable_columns;
	}

	/**
	 * Get the extra fields based on the request.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_extra_fields() {

		return [];
	}

	/**
	 * Returns the date fields.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_extra_date_fields() {

		$filters = $this->get_filters();

		$_filter_fields = [];

		if (isset($filters['date_filters'])) {
			foreach ($filters['date_filters'] as $field_name => $field) {
				if ( ! isset($_REQUEST[ $field_name ])) { // phpcs:ignore WordPress.Security.NonceVerification
					continue;
				}

				if (isset($_REQUEST[ $field_name ]['before']) && isset($_REQUEST[ $field_name ]['after']) && '' === $_REQUEST[ $field_name ]['before'] && '' === $_REQUEST[ $field_name ]['after']) { // phpcs:ignore WordPress.Security.NonceVerification
					continue;
				}

				$_filter_fields[ $field_name ] = wu_request($field_name, '');
			}
		}

		return $_filter_fields;
	}

	/**
	 * Returns a list of filters on the request to be used on the query.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_extra_query_fields() {

		return [];
	}

	/**
	 * Returns the hidden fields that are embedded into the page.
	 *
	 * These are used to make sure the URL on the browser is always up to date.
	 * This makes sure that when a use refreshes, they don't loose the current filtering state.
	 * This also makes filtered searches shareable via the URL =)
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_hidden_fields() {

		$final_fields = [
			'order'   => $this->_pagination_args['order'] ?? '',
			'orderby' => $this->_pagination_args['orderby'] ?? '',
		];

		return $final_fields;
	}

	/**
	 * Returns the pre-selected filters on the filter bar.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_views() {

		return [
			'all' => [
				'field' => 'type',
				'url'   => '#',
				// translators: %s will be replaced with a plural label
				'label' => sprintf(__('All %s', 'ultimate-multisite'), $this->get_label('plural')),
				'count' => 0,
			],
		];
	}

	/**
	 * Generates the required HTML for a list of row action links.
	 *
	 * @since 2.1
	 *
	 * @param string[] $actions        An array of action links.
	 * @param bool     $always_visible Whether the actions should be always visible.
	 * @return string The HTML for the row actions.
	 */
	protected function row_actions($actions, $always_visible = false) {

		$actions = apply_filters('wu_list_row_actions', $actions, $this->id);

		return parent::row_actions($actions);
	}
}
