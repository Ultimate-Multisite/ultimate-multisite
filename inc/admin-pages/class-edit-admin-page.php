<?php
/**
 * Base admin page class.
 *
 * Abstract class that makes it easy to create new admin pages.
 *
 * Most of Ultimate Multisite pages are implemented using this class, which means that the filters and hooks
 * listed below can be used to append content to all of our pages at once.
 *
 * @package WP_Ultimo
 * @subpackage Admin_Pages
 * @since 2.0.0
 */

namespace WP_Ultimo\Admin_Pages;

// Exit if accessed directly
use WP_Ultimo\Models\Interfaces\Limitable;

defined('ABSPATH') || exit;

/**
 * Abstract class that makes it easy to create new admin pages.
 */
abstract class Edit_Admin_Page extends Base_Admin_Page {

	/**
	 * Checks if we are adding a new object or if we are editing one
	 *
	 * @since 1.8.2
	 * @var boolean
	 */
	public $edit = false;

	/**
	 * The id/name/slug of the object being edited/created. e.g: plan
	 *
	 * @since 1.8.2
	 * @var string
	 */
	public $object_id;

	/**
	 * The object being edited.
	 *
	 * @since 1.8.2
	 * @var object
	 */
	public $object;

	/**
	 * Holds validations errors on edition.
	 *
	 * @since 2.0.0
	 * @var null|\WP_Error
	 */
	protected $errors;

	/**
	 * Returns the errors, if any.
	 *
	 * @since 2.0.0
	 * @return \WP_Error
	 */
	public function get_errors() {

		if (null === $this->errors) {
			$this->errors = new \WP_Error();
		}

		return $this->errors;
	}

	/**
	 * Register additional hooks to page load such as the action links and the save processing.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function page_loaded() {

		/**
		 * Setups the object
		 */
		$this->object = $this->get_object();

		$this->edit = $this->object->exists();

		/**
		 * Deals with lock statuses.
		 */
		$this->add_lock_notices();

		if (wu_request('submit_button') === 'delete') {
			$this->process_delete();
		} elseif (wu_request('remove-lock')) {
			$this->remove_lock();
		} else {
			/*
			 * Process save, if necessary
			 */
			$this->process_save();
		}
	}

	/**
	 * Add some other necessary hooks.
	 *
	 * @return void
	 */
	public function hooks(): void {

		parent::hooks();

		add_filter('removable_query_args', [$this, 'removable_query_args']);
	}

	/**
	 * Adds the wu-new-model to the list of removable query args of WordPress.
	 *
	 * @since 2.0.0
	 *
	 * @param array $removable_query_args Existing list of removable query args.
	 * @return array
	 */
	public function removable_query_args($removable_query_args) {

		$removable_query_args[] = 'wu-new-model';

		return $removable_query_args;
	}

	/**
	 * Displays lock notices, if necessary.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	protected function add_lock_notices() {

		$locked = $this->get_object()->is_locked();

		if ($locked && $this->edit) {

			// translators: %1$s is the date, using the site format options, %2$s is a <br /> tag.
			$message = sprintf(esc_html__('This item is locked from editions. %2$s This is probably due to a background action being performed (like a transfer between different accounts, for example). You can manually unlock it, but be careful. The lock should be released automatically in %1$s seconds.', 'ultimate-multisite'), wu_get_next_queue_run() + 10, '<br />');

			$actions = [
				'preview' => [
					'title' => esc_html__('Unlock', 'ultimate-multisite'),
					'url'   => add_query_arg(
						[
							'remove-lock'           => 1,
							'unlock_wpultimo_nonce' => wp_create_nonce(sprintf('unlocking_%s', $this->object_id)),
						]
					),
				],
			];

			WP_Ultimo()->notices->add($message, 'warning', 'network-admin', false, $actions);
		}
	}

	/**
	 * Remove the lock from the object.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function remove_lock(): void {

		$unlock_tag = "unlocking_{$this->object_id}";

		if (isset($_REQUEST['remove-lock'])) {
			check_admin_referer($unlock_tag, 'unlock_wpultimo_nonce');

			/**
			 * Allow plugin developers to add actions to the unlocking process.
			 *
			 * @since 1.8.2
			 */
			do_action("wu_unlock_{$this->object_id}");

			/**
			 * Unlocks and redirects.
			 */
			$this->get_object()->unlock();

			wp_safe_redirect(
				remove_query_arg(
					[
						'remove-lock',
						'unlock_wpultimo_nonce',
					]
				)
			);

			exit;
		}
	}

	/**
	 * Handles saves, after verifying nonces and such. Should not be rewritten by child classes.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	final public function process_save(): void {

		$saving_tag = "saving_{$this->object_id}";

		if (isset($_REQUEST[ $saving_tag ])) {
			check_admin_referer($saving_tag, '_wpultimo_nonce');

			/**
			 * Allow plugin developers to add actions to the saving process
			 *
			 * @since 1.8.2
			 */
			do_action("wu_save_{$this->object_id}", $this);

			/**
			 * Calls the saving function
			 */
			$status = $this->handle_save();

			if ($status) {
				exit;
			}
		}
	}

	/**
	 * Handles delete, after verifying nonces and such. Should not be rewritten by child classes.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	final public function process_delete(): void {

		$deleting_tag = "deleting_{$this->object_id}";

		if (isset($_REQUEST[ $deleting_tag ])) {
			check_admin_referer($deleting_tag, 'delete_wpultimo_nonce');

			/**
			 * Allow plugin developers to add actions to the deleting process
			 *
			 * @since 1.8.2
			 */
			do_action("wu_delete_{$this->object_id}");

			/**
			 * Calls the deleting function
			 */
			$this->handle_delete();
		}
	}

	/**
	 * Returns the labels to be used on the admin page.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_labels() {

		$default_labels = [
			'edit_label'          => __('Edit Object', 'ultimate-multisite'),
			'add_new_label'       => __('Add New Object', 'ultimate-multisite'),
			'updated_message'     => __('Object updated with success!', 'ultimate-multisite'),
			'title_placeholder'   => __('Enter Object Name', 'ultimate-multisite'),
			'title_description'   => '',
			'save_button_label'   => __('Save', 'ultimate-multisite'),
			'save_description'    => '',
			'delete_button_label' => __('Delete', 'ultimate-multisite'),
			'delete_description'  => __('Be careful. This action is irreversible.', 'ultimate-multisite'),
		];

		return apply_filters('wu_edit_admin_page_labels', $default_labels);
	}

	/**
	 * Allow child classes to register scripts and styles that can be loaded on the output function, for example.
	 *
	 * @since 1.8.2
	 * @return void
	 */
	public function register_scripts(): void {

		parent::register_scripts();

		/*
		 * Enqueue the base Dashboard Scripts
		 */
		wp_enqueue_script('dashboard');

		/*
		 * Adds Vue.
		 */
		wp_enqueue_script('wu-vue-apps');

		wp_enqueue_script('wu-fields');

		wp_enqueue_style('wp-color-picker');

		wp_enqueue_script('wu-selectizer');
	}

	/**
	 * Registers widgets to the edit page.
	 *
	 * This implementation register the default save widget.
	 * Child classes that wish to inherit that widget while registering other,
	 * can do such by adding a parent::register_widgets() to their own register_widgets() method.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function register_widgets() {

		$this->add_info_widget(
			'info',
			[
				'title'    => __('Timestamps', 'ultimate-multisite'),
				'position' => 'side-bottom',
			]
		);

		if ($this->edit) {
			$this->add_delete_widget('delete', []);
		}
	}

	/**
	 * Adds a basic widget with info (and fields) to be shown.
	 *
	 * @since 2.0.0
	 *
	 * @param string $id Unique ID for the widget, since we can have more than one per page.
	 * @param array  $atts Array containing the attributes to be passed to the widget.
	 * @return void
	 */
	protected function add_info_widget($id, $atts = []) {

		$created_key = 'date_created';

		if (method_exists($this->get_object(), 'get_date_registered')) {
			$created_key = 'date_registered';
		}

		$created_value = call_user_func([$this->get_object(), "get_$created_key"]);

		$atts['fields'][ $created_key ] = [
			'title'         => __('Created at', 'ultimate-multisite'),
			'type'          => 'text-display',
			'date'          => true,
			'display_value' => $this->edit ? $created_value : false,
			'value'         => $created_value,
			'placeholder'   => '2020-04-04 12:00:00',
			'html_attr'     => [
				'wu-datepicker'   => 'true',
				'data-format'     => 'Y-m-d H:i:S',
				'data-allow-time' => 'true',
			],
		];

		$show_modified = wu_get_isset($atts, 'modified', true);

		if ($this->edit && true === $show_modified) {
			$atts['fields']['date_modified'] = [
				'title'         => __('Last Modified at', 'ultimate-multisite'),
				'type'          => 'text-display',
				'date'          => true,
				'display_value' => $this->edit ? $this->get_object()->get_date_modified() : __('No date', 'ultimate-multisite'),
				'value'         => $this->get_object()->get_date_modified(),
				'placeholder'   => '2020-04-04 12:00:00',
				'html_attr'     => [
					'wu-datepicker'   => 'true',
					'data-format'     => 'Y-m-d H:i:S',
					'data-allow-time' => 'true',
				],
			];
		}

		$this->add_fields_widget($id, $atts);
	}

	/**
	 * Adds a basic widget to display list tables.
	 *
	 * @since 2.0.0
	 *
	 * @param string $id Unique ID for the widget, since we can have more than one per page.
	 * @param array  $atts Array containing the attributes to be passed to the widget.
	 * @return void
	 */
	protected function add_list_table_widget($id, $atts = []) {

		$atts = wp_parse_args(
			$atts,
			[
				'widget_id'    => $id,
				'before'       => fn() => null,
				'after'        => fn() => null,
				'title'        => __('List Table', 'ultimate-multisite'),
				'position'     => 'advanced',
				'screen'       => get_current_screen(),
				'page'         => $this,
				'labels'       => $this->get_labels(),
				'object'       => $this->get_object(),
				'edit'         => true,
				'table'        => false,
				'query_filter' => false,
			]
		);

		$atts['table']->set_context('widget');

		$table_name = $atts['table']->get_table_id();

		if (is_callable($atts['query_filter'])) {
			add_filter("wu_{$table_name}_get_items", $atts['query_filter']);
		}

		add_filter(
			'wu_events_list_table_get_columns',
			function ($columns) {

				unset($columns['object_type']);

				unset($columns['code']);

				return $columns;
			}
		);

		add_meta_box(
			"wp-ultimo-list-table-{$id}",
			$atts['title'],
			function () use ($atts) {

				wp_enqueue_script('wu-ajax-list-table');

				wu_get_template('base/edit/widget-list-table', $atts);
			},
			$atts['screen']->id,
			$atts['position'],
			'default'
		);
	}

	/**
	 * Adds field widgets to edit pages with the same Form/Field APIs used elsewhere.
	 *
	 * @see Take a look at /inc/ui/form and inc/ui/field for reference.
	 * @since 2.0.0
	 *
	 * @param string $id ID of the widget.
	 * @param array  $atts Array of attributes to pass to the form.
	 * @return void
	 */
	public function add_fields_widget($id, $atts = []) {

		$atts = wp_parse_args(
			$atts,
			[
				'widget_id'             => $id,
				'before'                => '',
				'after'                 => '',
				'title'                 => __('Fields', 'ultimate-multisite'),
				'position'              => 'side',
				'screen'                => get_current_screen(),
				'fields'                => [],
				'html_attr'             => [],
				'classes'               => '',
				'field_wrapper_classes' => 'wu-w-full wu-box-border wu-items-center wu-flex wu-justify-between wu-p-4 wu-m-0 wu-border-t wu-border-l-0 wu-border-r-0 wu-border-b-0 wu-border-gray-300 wu-border-solid',
			]
		);

		add_meta_box(
			"wp-ultimo-{$id}-widget",
			$atts['title'],
			function () use ($atts) {

				if (wu_get_isset($atts['html_attr'], 'data-wu-app')) {
					$atts['fields']['loading'] = [
						'type'              => 'note',
						'desc'              => sprintf('<div class="wu-block wu-text-center wu-blinking-animation wu-text-gray-600 wu-my-1 wu-text-2xs wu-uppercase wu-font-semibold">%s</div>', __('Loading...', 'ultimate-multisite')),
						'wrapper_html_attr' => [
							'v-if' => 0,
						],
					];
				}

				/**
				 * Instantiate the form for the order details.
				 *
				 * @since 2.0.0
				 */
				$form = new \WP_Ultimo\UI\Form(
					$atts['widget_id'],
					$atts['fields'],
					[
						'views'                 => 'admin-pages/fields',
						'classes'               => 'wu-widget-list wu-striped wu-m-0 wu--mt-2 wu--mb-3 wu--mx-3 ' . $atts['classes'],
						'field_wrapper_classes' => $atts['field_wrapper_classes'],
						'html_attr'             => $atts['html_attr'],
						'before'                => $atts['before'],
						'after'                 => $atts['after'],
					]
				);

				$form->render();
			},
			$atts['screen']->id,
			$atts['position'],
			'default'
		);
	}

	/**
	 * Adds field widgets to edit pages with the same Form/Field APIs used elsewhere.
	 *
	 * @see Take a look at /inc/ui/form and inc/ui/field for reference.
	 * @since 2.0.0
	 *
	 * @param string $id ID of the widget.
	 * @param array  $atts Array of attributes to pass to the form.
	 * @return void
	 */
	protected function add_tabs_widget($id, $atts = []) {

		$atts = wp_parse_args(
			$atts,
			[
				'widget_id' => $id,
				'before'    => '',
				'after'     => '',
				'title'     => __('Tabs', 'ultimate-multisite'),
				'position'  => 'advanced',
				'screen'    => get_current_screen(),
				'sections'  => [],
				'html_attr' => [],
			]
		);

		$current_section = wu_request($id, current(array_keys($atts['sections'])));

		$atts['html_attr']['data-wu-app'] = $id;

		$atts['html_attr']['data-state'] = [
			'section'     => $current_section,
			'display_all' => false,
		];

		add_meta_box(
			"wp-ultimo-{$id}-widget",
			$atts['title'],
			function () use ($atts) {

				foreach ($atts['sections'] as $section_id => &$section) {
					$section = wp_parse_args(
						$section,
						[
							'form'                  => '',
							'before'                => '',
							'after'                 => '',
							'v-show'                => '1',
							'fields'                => [],
							'html_attr'             => [],
							'state'                 => [],
							'field_wrapper_classes' => 'wu-w-full wu-box-border wu-items-center wu-flex wu-justify-between wu-p-4 wu-m-0 wu-border-t wu-border-l-0 wu-border-r-0 wu-border-b-0 wu-border-gray-300 wu-border-solid',
						]
					);

					/**
					 * Move state ont step up
					 */
					$atts['html_attr']['data-state'] = array_merge($atts['html_attr']['data-state'], $section['state']);

					$section['html_attr'] = [
						'v-cloak' => 1,
						'v-show'  => "(section == '{$section_id}' || display_all) && " . $section['v-show'],
					];

					/**
					 * Adds a header field
					 */
					$section['fields'] = array_merge(
						[
							$section_id => [
								'title'             => $section['title'],
								'desc'              => $section['desc'],
								'type'              => 'header',
								'wrapper_html_attr' => [
									'v-show' => 'display_all',
								],
							],
						],
						$section['fields']
					);

					/**
					 * Instantiate the form for the order details.
					 *
					 * @since 2.0.0
					 */
					$section['form'] = new \WP_Ultimo\UI\Form(
						$section_id,
						$section['fields'],
						[
							'views'                 => 'admin-pages/fields',
							'classes'               => 'wu-widget-list wu-striped wu-m-0 wu-border-solid wu-border-gray-300 wu-border-0 wu-border-b',
							'field_wrapper_classes' => $section['field_wrapper_classes'],
							'html_attr'             => $section['html_attr'],
							'before'                => $section['before'],
							'after'                 => $section['after'],
						]
					);
				}

				wu_get_template(
					'base/edit/widget-tabs',
					[
						'sections'  => $atts['sections'],
						'html_attr' => $atts['html_attr'],
						'before'    => $atts['before'],
						'after'     => $atts['after'],
					]
				);
			},
			$atts['screen']->id,
			$atts['position'],
			'default'
		);
	}

	/**
	 * Adds a generic widget to the admin page.
	 *
	 * @since 2.0.0
	 *
	 * @param string $id ID of the widget.
	 * @param array  $atts Widget parameters.
	 * @return void
	 */
	protected function add_widget($id, $atts = []) {

		$atts = wp_parse_args(
			$atts,
			[
				'widget_id' => $id,
				'before'    => '',
				'after'     => '',
				'title'     => __('Fields', 'ultimate-multisite'),
				'screen'    => get_current_screen(),
				'position'  => 'side',
				'display'   => '__return_empty_string',
			]
		);

		add_meta_box("wp-ultimo-{$id}-widget", $atts['title'], $atts['display'], $atts['screen']->id, $atts['position'], 'default');
	}

	/**
	 * Adds a basic save widget.
	 *
	 * @since 2.0.0
	 *
	 * @param string $id Unique ID for the widget, since we can have more than one per page.
	 * @param array  $atts Array containing the attributes to be passed to the widget.
	 * @return void
	 */
	protected function add_save_widget($id, $atts = []) {

		$labels = $this->get_labels();

		$atts['title'] = __('Save', 'ultimate-multisite');

		/**
		 * Adds Submit Button
		 */
		$atts['fields']['submit_save'] = [
			'type'              => 'submit',
			'title'             => $labels['save_button_label'],
			'placeholder'       => $labels['save_button_label'],
			'value'             => 'save',
			'classes'           => 'button button-primary wu-w-full',
			'html_attr'         => [],
			'wrapper_html_attr' => [],
		];

		if (isset($atts['html_attr']['data-wu-app'])) {
			$atts['fields']['submit_save']['wrapper_html_attr']['v-cloak'] = 1;
		}

		if ($this->get_object() && $this->edit && $this->get_object()->is_locked()) {
			$atts['fields']['submit_save']['title']                 = __('Locked', 'ultimate-multisite');
			$atts['fields']['submit_save']['value']                 = 'none';
			$atts['fields']['submit_save']['html_attr']['disabled'] = 'disabled';
		}

		$this->add_fields_widget('save', $atts);
	}

	/**
	 * Adds a basic delete widget.
	 *
	 * @since 2.0.0
	 *
	 * @param string $id Unique ID for the widget, since we can have more than one per page.
	 * @param array  $atts Array containing the attributes to be passed to the widget.
	 * @return void
	 */
	protected function add_delete_widget($id, $atts = []) {

		$labels = $this->get_labels();

		$atts_default = [
			'title'    => __('Delete', 'ultimate-multisite'),
			'position' => 'side-bottom',
		];
		$atts         = array_merge($atts_default, $atts);

		/**
		 * Adds Note
		 */
		$atts['fields']['note'] = [
			'type' => 'note',
			'desc' => $labels['delete_description'],
		];

		/**
		 * Adds Submit Button
		 */
		$default_delete_field_settings = [
			'type'            => 'link',
			'title'           => '',
			'display_value'   => $labels['delete_button_label'] ?? '',
			'placeholder'     => $labels['delete_button_label'] ?? '',
			'value'           => 'delete',
			'classes'         => 'button wubox wu-w-full wu-text-center',
			'wrapper_classes' => 'wu-bg-gray-100',
			'html_attr'       => [
				'title' => $labels['delete_button_label'],
				'href'  => wu_get_form_url(
					'delete_modal',
					[
						'id'    => $this->get_object()->get_id(),
						'model' => $this->get_object()->model,
					]
				),
			],
		];

		$custom_delete_field_settings = wu_get_isset($atts['fields'], 'delete', []);

		$atts['fields']['delete'] = array_merge($default_delete_field_settings, $custom_delete_field_settings);

		$this->add_fields_widget('delete', $atts);
	}

	/**
	 * Displays the contents of the edit page.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function output(): void {
		/*
		 * Renders the base edit page layout, with the columns and everything else =)
		 */
		wu_get_template(
			'base/edit',
			[
				'screen' => get_current_screen(),
				'page'   => $this,
				'labels' => $this->get_labels(),
				'object' => $this->get_object(),
			]
		);
	}

	/**
	 * Wether or not this pages should have a title field.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function has_title() {

		return false;
	}

	/**
	 * Wether or not this pages should have an editor field.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function has_editor() {

		return false;
	}

	/**
	 * Should return the object being edited, or false.
	 *
	 * Child classes need to implement this method, returning an object to be edited,
	 * such as a WP_Ultimo\Model, or false, in case this is a 'Add New' page.
	 *
	 * @since 2.0.0
	 * @return \WP_Ultimo\Models\Base_Model
	 */
	abstract public function get_object();

	/**
	 * Should implement the processes necessary to save the changes made to the object.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function handle_save() {

		$object = $this->get_object();

		/*
		 * Active fix
		 */
		$_POST['active'] = (bool) wu_request('active', false);

		$object->load_attributes_from_post();

		if ($object instanceof Limitable) {
			$object->handle_limitations();
		}

		$save = $object->save();

		if (is_wp_error($save)) {
			$errors = implode('<br>', $save->get_error_messages());

			WP_Ultimo()->notices->add($errors, 'error', 'network-admin');

			return false;
		} else {
			$array_params = [
				'updated' => 1,
			];

			if (false === $this->edit) {
				$array_params['id'] = $object->get_id();

				$array_params['wu-new-model'] = true;
			}

			$url = add_query_arg($array_params);

			wp_safe_redirect($url);

			return true;
		}
	}

	/**
	 * Should implement the processes necessary to delete  the object.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function handle_delete(): void {

		$object = $this->get_object();

		$saved = $object->delete();

		if (is_wp_error($saved)) {
			$errors = implode('<br>', $saved->get_error_messages());

			WP_Ultimo()->notices->add($errors, 'error', 'network-admin');

			return;
		}

		$url = str_replace('_', '-', (string) $object->model);
		$url = wu_network_admin_url("wp-ultimo-{$url}s");

		wp_safe_redirect($url);

		exit;
	}
}
