<?php
/**
 * Checkout_Form List Table class.
 *
 * @package WP_Ultimo
 * @subpackage List_Table
 * @since 2.0.0
 */

namespace WP_Ultimo\List_Tables;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Checkout_Form List Table class.
 *
 * @since 2.0.0
 */
class Checkout_Form_List_Table extends Base_List_Table {

	/**
	 * Holds the query class for the object being listed.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $query_class = \WP_Ultimo\Database\Checkout_Forms\Checkout_Form_Query::class;

	/**
	 * Initializes the table.
	 *
	 * @since 2.0.0
	 */
	public function __construct() {

		parent::__construct(
			[
				'singular' => __('Checkout Form', 'ultimate-multisite'),  // singular name of the listed records
				'plural'   => __('Checkout Forms', 'ultimate-multisite'), // plural name of the listed records
				'ajax'     => true,                              // does this table support ajax?
				'add_new'  => [
					'url'     => wu_get_form_url('add_new_checkout_form'),
					'classes' => 'wubox',
				],
			]
		);
	}

	/**
	 * Displays the content of the product column.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_Ultimo\Models\Checkout_Form $item Checkout Form object.
	 */
	public function column_name($item): string {

		$url_atts = [
			'id'    => $item->get_id(),
			'slug'  => $item->get_slug(),
			'model' => 'checkout_form',
		];

		$title = sprintf('<strong><a href="%s">%s</a></strong>', wu_network_admin_url('wp-ultimo-edit-checkout-form', $url_atts), $item->get_name());

		$actions = [
			'edit'          => sprintf('<a href="%s">%s</a>', wu_network_admin_url('wp-ultimo-edit-checkout-form', $url_atts), __('Edit', 'ultimate-multisite')),
			'duplicate'     => sprintf(
				'<a href="%s">%s</a>',
				wu_network_admin_url(
					'wp-ultimo-checkout-forms',
					[
						'action' => 'duplicate',
						'id'     => $item->get_id(),
					]
				),
				__('Duplicate', 'ultimate-multisite')
			),
			'get_shortcode' => sprintf('<a title="%s" class="wubox" href="%s">%s</a>', __('Shortcode', 'ultimate-multisite'), wu_get_form_url('shortcode_checkout', $url_atts), __('Shortcode', 'ultimate-multisite')),
			'delete'        => sprintf('<a title="%s" class="wubox" href="%s">%s</a>', __('Delete', 'ultimate-multisite'), wu_get_form_url('delete_modal', $url_atts), __('Delete', 'ultimate-multisite')),
		];

		return $title . $this->row_actions($actions);
	}

	/**
	 * Displays the slug of the form.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_Ultimo\Models\Checkout_Form $item Checkout Form object.
	 * @return string
	 */
	public function column_slug($item) {

		$slug = $item->get_slug();

		return "<span class='wu-bg-gray-200 wu-text-gray-700 wu-py-1 wu-px-2 wu-rounded-sm wu-text-xs wu-font-mono'>{$slug}</span>";
	}

	/**
	 * Displays the active status of the form.
	 *
	 * @since 2.0.21
	 *
	 * @param \WP_Ultimo\Models\Checkout_Form $item Checkout Form object.
	 * @return string
	 */
	public function column_active($item) {

		if ($item->is_active()) {
			$label = __('Active', 'ultimate-multisite');
			$class = 'wu-bg-green-200 wu-text-green-700';
		} else {
			$label = __('Inactive', 'ultimate-multisite');
			$class = 'wu-bg-red-200 wu-text-red-700';
		}

		return "<span class='wu-py-1 wu-px-2 wu-rounded-sm wu-text-xs wu-font-mono {$class}'>{$label}</span>";
	}

	/**
	 * Displays the number pof steps and fields.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_Ultimo\Models\Checkout_Form $item Checkout Form object.
	 */
	public function column_steps($item): string {
		// translators: %1$d: number of steps, %2$d: number of fields
		return sprintf(__('%1$d Step(s) and %2$d Field(s)', 'ultimate-multisite'), $item->get_step_count(), $item->get_field_count());
	}

	/**
	 * Displays the form shortcode.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_Ultimo\Models\Checkout_Form $item Checkout Form object.
	 */
	public function column_shortcode($item): string {

		$button = sprintf(
			'
		<button type="button" data-clipboard-action="copy" data-clipboard-target="#hidden_textarea" class="btn-clipboard" title="%s">
      <span class="dashicons-wu-copy"></span>
    </button>',
			__('Copy to the Clipboard', 'ultimate-multisite')
		);

		return sprintf('<input class="wu-bg-gray-200 wu-border-none wu-text-gray-700 wu-py-1 wu-px-2 wu-rounded-sm wu-text-xs wu-font-mono" value="%s">', esc_attr($item->get_shortcode()));
	}

	/**
	 * Handles the bulk processing adding duplication
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function process_single_action(): void {

		$bulk_action = $this->current_action();

		if ('duplicate' === $bulk_action) {
			$checkout_form_id = wu_request('id');

			$checkout_form = wu_get_checkout_form($checkout_form_id);

			if ( ! $checkout_form) {
				WP_Ultimo()->notices->add(__('Checkout form not found.', 'ultimate-multisite'), 'error', 'network-admin');

				return;
			}

			$new_checkout_form = $checkout_form->duplicate();

			// translators: the %s is the thing copied.
			$new_name = sprintf(__('Copy of %s', 'ultimate-multisite'), $checkout_form->get_name());

			$new_checkout_form->set_name($new_name);

			$new_checkout_form->set_slug(sanitize_title($new_name));

			$new_checkout_form->set_date_created(wu_get_current_time('mysql', true));

			$result = $new_checkout_form->save();

			if (is_wp_error($result)) {
				WP_Ultimo()->notices->add($result->get_error_message(), 'error', 'network-admin');

				return;
			}

			$redirect_url = wu_network_admin_url(
				'wp-ultimo-edit-checkout-form',
				[
					'id'      => $new_checkout_form->get_id(),
					'updated' => 1,
				]
			);

			wp_safe_redirect($redirect_url);

			exit;
		}
	}

	/**
	 * Returns the list of columns for this particular List Table.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_columns() {

		$columns = [
			'cb'     => '<input type="checkbox" />',
			'name'   => __('Form Name', 'ultimate-multisite'),
			'active' => __('Status', 'ultimate-multisite'),
			'slug'   => __('Form Slug', 'ultimate-multisite'),
			'steps'  => __('Steps', 'ultimate-multisite'),
			'id'     => __('ID', 'ultimate-multisite'),
		];

		return $columns;
	}
}
