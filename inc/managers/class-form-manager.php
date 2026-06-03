<?php
/**
 * Gateway Manager
 *
 * Manages the registering and activation of gateways.
 *
 * @package WP_Ultimo
 * @subpackage Managers/Gateway
 * @since 2.0.0
 */

namespace WP_Ultimo\Managers;

use WP_Ultimo\Managers\Base_Manager;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Handles the ajax form registering, rendering, and permissions checking.
 *
 * @since 2.0.0
 */
class Form_Manager extends Base_Manager {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * Keeps the registered forms.
	 *
	 * @since 2.0.0
	 * @var array
	 */
	protected $registered_forms = [];

	/**
	 * Instantiate the necessary hooks.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function init(): void {

		add_action('wu_ajax_wu_form_display', [$this, 'display_form']);

		add_action('wu_ajax_wu_form_handler', [$this, 'handle_form']);

		add_action('wu_register_forms', [$this, 'register_action_forms']);

		add_action('wu_page_load', 'add_wubox');

		do_action('wu_register_forms');
	}

	/**
	 * Displays the form unavailable message.
	 *
	 * This is returned when the form doesn't exist, or the
	 * logged user doesn't have the required permissions to see the form.
	 *
	 * @since 2.0.0
	 * @param \WP_Error|false $error Error message, if applicable.
	 * @return void
	 */
	public function display_form_unavailable($error = false): void {

		$message = __('Form not available', 'ultimate-multisite');

		if (is_wp_error($error)) {
			$message = $error->get_error_message();
		}

		printf(
			'
      <div class="wu-modal-form wu-h-full wu-flex wu-items-center wu-justify-center wu-bg-gray-200 wu-m-0 wu-mt-0 wu--mb-3">
        <div>
          <span class="dashicons dashicons-warning wu-h-8 wu-w-8 wu-mx-auto wu-text-center wu-text-4xl wu-block"></span>
          <span class="wu-block wu-text-sm">%s</span>
        </div>
      </div>
    ',
			esc_html($message)
		);

		do_action('wu_form_scripts', false);

		die;
	}

	/**
	 * Renders a registered form, when requested.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function display_form(): void {

		$this->security_checks();

		$form = $this->get_form(wu_request('form'));

		printf(
			"<form class='wu_form wu-styling' id='%s' action='%s' method='post'>",
			esc_attr($form['id']),
			esc_attr(
				$this->get_form_url(
					$form['id'],
					[
						'action' => 'wu_form_handler',
					]
				)
			)
		);

		printf(
			'
		<div v-cloak data-wu-app="%s" data-state="%s">
			<ul class="wu-p-4 wu-bg-red-200 wu-m-0 wu-list-none" v-if="errors.length">
				<li class="wu-m-0 wu-p-0" v-for="error in errors" v-html="error.message"></li>
			</ul>
		</div>',
			esc_attr($form['id'] . '_errors'),
			esc_attr(wp_json_encode(['errors' => []]))
		);

		call_user_func($form['render']);

		printf('<input type="hidden" name="form" value="%s">', esc_attr($form['id']));

		echo '<input type="hidden" name="action" value="wu_form_handler">';

		wp_nonce_field('wu_form_' . $form['id']);

		echo '</form>';

		do_action('wu_form_scripts', $form);

		exit;
	}

	/**
	 * Handles the submission of a registered form.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function handle_form(): void {

		$this->security_checks();

		$form = $this->get_form(wu_request('form'));

		if ( ! wp_verify_nonce(wu_request('_wpnonce'), 'wu_form_' . $form['id'])) {
			wp_send_json_error();
		}

		/**
		 * The handler is supposed to send a wp_json message back.
		 * However, if it returns a WP_Error object, we know
		 * something went wrong and that we should display the error message.
		 */
		$check = call_user_func($form['handler']);

		if (is_wp_error($check)) {
			$this->display_form_unavailable($check);
		}

		exit;
	}

	/**
	 * Checks that the form exists and that the user has permission to see it.
	 *
	 * @since 2.0.0
	 * @return mixed
	 */
	public function security_checks() {
		/*
		 * We only want ajax requests.
		 */
		if ((empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower(sanitize_key(wp_unslash($_SERVER['HTTP_X_REQUESTED_WITH']))) !== 'xmlhttprequest')) {
			wp_die(0);
		}

		$form = $this->get_form(wu_request('form'));

		if ( ! $form) {
			$this->display_form_unavailable();
		}

		if ( ! current_user_can($form['capability'])) {
			$this->display_form_unavailable();
		}
	}

	/**
	 * Returns a list of all the registered gateways.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_registered_forms() {

		return $this->registered_forms;
	}

	/**
	 * Checks if a form is already registered.
	 *
	 * @since 2.0.0
	 *
	 * @param string $id The id of the form.
	 * @return boolean
	 */
	public function is_form_registered($id) {

		return is_array($this->registered_forms) && isset($this->registered_forms[ $id ]);
	}

	/**
	 * Returns a registered form.
	 *
	 * @since 2.0.0
	 *
	 * @param string $id The id of the form to return.
	 * @return array
	 */
	public function get_form($id) {

		return $this->is_form_registered($id) ? $this->registered_forms[ $id ] : false;
	}

	/**
	 * Registers a new Ajax Form.
	 *
	 * Ajax forms are forms that get loaded via an ajax call using thickbox (or rather our fork).
	 * This is useful for displaying inline edit forms that support Vue and our
	 * Form/Fields API.
	 *
	 * @since 2.0.0
	 *
	 * @param string $id Form id.
	 * @param array  $atts Form attributes, check wp_parse_atts call below.
	 * @return void
	 */
	public function register_form($id, $atts = []) {

		$atts = wp_parse_args(
			$atts,
			[
				'id'         => $id,
				'form'       => '',
				'capability' => 'manage_network',
				'handler'    => '__return_false',
				'render'     => '__return_empty_string',
			]
		);

		// Checks if gateway was already added
		if ($this->is_form_registered($id)) {
			return;
		}

		$this->registered_forms[ $id ] = $atts;

		return true;
	}

	/**
	 * Returns the ajax URL for a given form.
	 *
	 * @since 2.0.0
	 *
	 * @param string $form_id The id of the form to return.
	 * @param array  $atts List of parameters, check wp_parse_args below.
	 * @return string
	 */
	public function get_form_url($form_id, $atts = []) {

		$atts = wp_parse_args(
			$atts,
			[
				'form'   => $form_id,
				'action' => 'wu_form_display',
				'width'  => '400',
				'height' => '360',
			]
		);

		return add_query_arg($atts, wu_ajax_url('init'));
	}

	/**
	 * Register the confirmation modal form to delete a customer.
	 *
	 * @since 2.0.0
	 */
	public function register_action_forms(): void {

		$model = wu_request('model');

		wu_register_form(
			'delete_modal',
			[
				'render'     => [$this, 'render_model_delete_form'],
				'handler'    => [$this, 'handle_model_delete_form'],
				'capability' => "wu_delete_{$model}s",
			]
		);

		wu_register_form(
			'bulk_actions',
			[
				'render'  => [$this, 'render_bulk_action_form'],
				'handler' => [$this, 'handle_bulk_action_form'],
			]
		);

		add_action('wu_handle_bulk_action_form', [$this, 'default_bulk_action_handler'], 100, 3);
	}

	/**
	 * Renders the deletion confirmation form.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function render_model_delete_form(): void {

		$model = wu_request('model');

		$id = wu_request('id');

		$meta_key = false;

		if ($model) {
			/*
			 * Handle metadata elements passed as model
			 */
			if (str_contains((string) $model, '_meta_')) {
				$elements = explode('_meta_', (string) $model);

				$model = $elements[0];

				$meta_key = $elements[1];
			}

			try {
				$object = call_user_func("wu_get_{$model}", $id);
			} catch (\Throwable $exception) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
				// No need to do anything, but cool to stop fatal errors.
			}

			$object = apply_filters("wu_delete_form_get_object_{$model}", $object, $id, $model);

			if ( ! $object) {
				$this->display_form_unavailable(new \WP_Error('not-found', __('Object not found.', 'ultimate-multisite')));

				return;
			}

			$fields = apply_filters(
				"wu_form_fields_delete_{$model}_modal",
				[
					'confirm'       => [
						'type'      => 'toggle',
						'title'     => __('Confirm Deletion', 'ultimate-multisite'),
						'desc'      => __('This action can not be undone.', 'ultimate-multisite'),
						'html_attr' => [
							'v-model' => 'confirmed',
						],
					],
					'submit_button' => [
						'type'            => 'submit',
						'title'           => __('Delete', 'ultimate-multisite'),
						'placeholder'     => __('Delete', 'ultimate-multisite'),
						'value'           => 'save',
						'classes'         => 'button button-primary wu-w-full',
						'wrapper_classes' => 'wu-items-end',
						'html_attr'       => [
							'v-bind:disabled' => '!confirmed',
						],
					],
					'id'            => [
						'type'  => 'hidden',
						'value' => $object->get_id(),
					],
					'meta_key'      => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
						'type'  => 'hidden',
						'value' => $meta_key,
					],
					'redirect_to'   => [
						'type'  => 'hidden',
						'value' => wu_request('redirect_to'),
					],
					'model'         => [
						'type'  => 'hidden',
						'value' => $model,
					],
				],
				$object
			);

			$form_attributes = apply_filters(
				"wu_form_attributes_delete_{$model}_modal",
				[
					'title'                 => 'Delete',
					'views'                 => 'admin-pages/fields',
					'classes'               => 'wu-modal-form wu-widget-list wu-striped wu-m-0 wu-mt-0',
					'field_wrapper_classes' => 'wu-w-full wu-box-border wu-items-center wu-flex wu-justify-between wu-p-4 wu-m-0 wu-border-t wu-border-l-0 wu-border-r-0 wu-border-b-0 wu-border-gray-300 wu-border-solid',
					'html_attr'             => [
						'data-wu-app' => 'true',
						'data-state'  => wp_json_encode(
							[
								'confirmed' => false,
							]
						),
					],
				]
			);

			$form = new \WP_Ultimo\UI\Form('total-actions', $fields, $form_attributes);

			do_action("wu_before_render_delete_{$model}_modal", $form);

			$form->render();
		}
	}

	/**
	 * Handles the deletion of customer.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function handle_model_delete_form(): void {

		global $wpdb;

		$model = wu_request('model');

		$id = wu_request('id');

		$meta_key = wu_request('meta_key');

		$redirect_to = wu_request('redirect_to', wp_get_referer());

		$plural_name = str_replace('_', '-', (string) $model) . 's';

		if ($model) {
			/*
			 * Require the confirmation toggle to be flipped before proceeding.
			 * The submit button is disabled client-side via Vue, but we must
			 * enforce this server-side as well to prevent bypasses.
			 */
			if ( ! wu_request('confirm')) {
				wp_send_json_error(new \WP_Error('not-confirmed', __('Please confirm the deletion.', 'ultimate-multisite')));
			}

			/*
			 * Handle meta key deletion
			 */
			if ($meta_key) {
				$status = delete_metadata('wu_membership', wu_request('id'), 'pending_site');

				$data_json_success = [
					'redirect_url' => add_query_arg('deleted', 1, $redirect_to),
				];

				wp_send_json_success($data_json_success);

				exit;
			}

			try {
				$object = call_user_func("wu_get_{$model}", $id);
			} catch (\Throwable $exception) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement

				// No need to do anything, but cool to stop fatal errors.
			}

			$object = apply_filters("wu_delete_form_get_object_{$model}", $object, $id, $model);

			if ( ! $object) {
				wp_send_json_error(new \WP_Error('not-found', __('Object not found.', 'ultimate-multisite')));
			}

			/*
			 * Handle objects (default state)
			 */
			do_action("wu_before_delete_{$model}_modal", $object);

			$saved = $object->delete();

			if (is_wp_error($saved)) {
				wp_send_json_error($saved);
			}

			if ( ! $saved) {
				wp_send_json_error(
					new \WP_Error(
						'delete-failed',
						__('The item could not be deleted.', 'ultimate-multisite')
					)
				);
			}

			do_action("wu_after_delete_{$model}_modal", $object);

			$data_json_success = apply_filters(
				"wu_data_json_success_delete_{$model}_modal",
				[
					'redirect_url' => wu_network_admin_url("wp-ultimo-{$plural_name}", ['deleted' => 1]),
				]
			);

			wp_send_json_success($data_json_success);
		} else {
			wp_send_json_error(new \WP_Error('model-not-found', __('Something went wrong.', 'ultimate-multisite')));
		}
	}

	/**
	 * Renders the deletion confirmation form.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function render_bulk_action_form(): void {

		$action = wu_request('bulk_action');

		$model = wu_request('model');

		$fields = apply_filters(
			"wu_bulk_actions_{$model}_{$action}",
			[
				'confirm'       => [
					'type'      => 'toggle',
					'title'     => __('Confirm Action', 'ultimate-multisite'),
					'desc'      => __('Review this action carefully.', 'ultimate-multisite'),
					'html_attr' => [
						'v-model' => 'confirmed',
					],
				],
				'submit_button' => [
					'type'            => 'submit',
					'title'           => wu_slug_to_name($action),
					'placeholder'     => wu_slug_to_name($action),
					'value'           => 'save',
					'classes'         => 'button button-primary wu-w-full',
					'wrapper_classes' => 'wu-items-end',
					'html_attr'       => [
						'v-bind:disabled' => '!confirmed',
					],
				],
				'model'         => [
					'type'  => 'hidden',
					'value' => $model,
				],
				'bulk_action'   => [
					'type'  => 'hidden',
					'value' => wu_request('bulk_action'),
				],
				'ids'           => [
					'type'  => 'hidden',
					'value' => implode(',', wu_request('bulk-delete', '')),
				],
			]
		);

		$form_attributes = apply_filters(
			"wu_bulk_actions_{$action}_form",
			[
				'views'                 => 'admin-pages/fields',
				'classes'               => 'wu-modal-form wu-widget-list wu-striped wu-m-0 wu-mt-0',
				'field_wrapper_classes' => 'wu-w-full wu-box-border wu-items-center wu-flex wu-justify-between wu-p-4 wu-m-0 wu-border-t wu-border-l-0 wu-border-r-0 wu-border-b-0 wu-border-gray-300 wu-border-solid',
				'html_attr'             => [
					'data-wu-app' => 'true',
					'data-state'  => wp_json_encode(
						[
							'confirmed' => false,
						]
					),
				],
			]
		);

		$form = new \WP_Ultimo\UI\Form('total-actions', $fields, $form_attributes);

		$form->render();
	}

	/**
	 * Handles the deletion of customer.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function handle_bulk_action_form(): void {

		global $wpdb;

		$action = wu_request('bulk_action');

		$model = wu_request('model');

		$ids = explode(',', (string) wu_request('ids', ''));

		do_action("wu_handle_bulk_action_form_{$model}_{$action}", $action, $model, $ids);

		do_action('wu_handle_bulk_action_form', $action, $model, $ids);
	}

	/**
	 * Default handler for bulk actions.
	 *
	 * @since 2.0.0
	 *
	 * @param string $action The action.
	 * @param string $model The model.
	 * @param array  $ids The ids list.
	 * @return void
	 */
	public function default_bulk_action_handler($action, $model, $ids): void {

		$status = \WP_Ultimo\List_Tables\Base_List_Table::process_bulk_action();

		if (is_wp_error($status)) {
			wp_send_json_error($status);
		}

		wp_send_json_success(
			[
				'redirect_url' => add_query_arg($action, count($ids), wu_get_current_url()),
			]
		);
	}
}
