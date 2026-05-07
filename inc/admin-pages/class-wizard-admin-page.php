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
defined('ABSPATH') || exit;

/**
 * Abstract class that makes it easy to create new admin pages.
 */
abstract class Wizard_Admin_Page extends Base_Admin_Page {

	/**
	 * Should we hide admin notices on this page?
	 *
	 * @since 2.0.0
	 * @var boolean
	 */
	protected $hide_admin_notices = true;

	/**
	 * Should we force the admin menu into a folded state?
	 *
	 * @since 2.0.0
	 * @var boolean
	 */
	protected $fold_menu = true;

	/**
	 * Holds the section slug for the URLs.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $section_slug = 'step';

	/**
	 * Defines if the step links on the side are clickable or not.
	 *
	 * @since 2.0.0
	 * @var boolean
	 */
	protected $clickable_navigation = false;

	/**
	 * Defined the id to be used on the main form element.
	 *
	 * @since 2.0.0
	 * @var boolean
	 */
	protected $form_id = '';

	/**
	 * Holds the active section for the wizard.
	 *
	 * @since 2.0.0
	 * @var array
	 */
	public $current_section;

	/**
	 * Register additional hooks to page load such as the action links and the save processing.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function page_loaded() {
		/*
		 * Load sections to memory.
		 */
		$sections = $this->get_sections();

		/*
		 * Sets current section for future reference.
		 */
		$this->current_section = $this->prepare_section_for_display($sections[ $this->get_current_section() ]);

		/*
		 * Process save, if necessary
		 */
		$this->process_save();
	}

	/**
	 * Handles saves, after verifying nonces and such. Should not be rewritten by child classes.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	final public function process_save() {

		$saving_tag = sprintf('saving_%s', $this->get_current_section());

		if (isset($_REQUEST[ $saving_tag ])) {
			check_admin_referer($saving_tag, '_wpultimo_nonce');

			$handler = $this->current_section['handler'] ?? [$this, 'default_handler'];

			/*
			 * Calls the saving function
			 */
			call_user_func($handler);
		}
	}

	/**
	 * Returns the labels to be used on the admin page.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_labels() {

		return [
			'edit_label'        => __('Edit Object', 'ultimate-multisite'),
			'add_new_label'     => __('Add New Object', 'ultimate-multisite'),
			'updated_message'   => __('Object updated with success!', 'ultimate-multisite'),
			'title_placeholder' => __('Enter Object Name', 'ultimate-multisite'),
			'title_description' => '',
			'save_button_label' => __('Save', 'ultimate-multisite'),
			'save_description'  => '',
		];
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

		$screen = get_current_screen();

		if (wu_get_isset($this->current_section, 'separator')) {
			return;
		}

		add_meta_box('wp-ultimo-wizard-body', wu_get_isset($this->current_section, 'title', __('Section', 'ultimate-multisite')), [$this, 'output_default_widget_body'], $screen->id, 'normal', null);
	}

	/**
	 * Outputs the markup for the default Save widget.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function output_default_widget_body() {

		echo '<div class="wu-p-4" data-testid="wizard-content-body">';

		$view = $this->current_section['view'] ?? [$this, 'default_view'];

			/*
			 * Calls the view function.
			 */
		call_user_func($view);

		echo '</div>';
	}

	/**
	 * Returns the logo to be used on the wizard.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_logo() {

		return '';
	}

	/**
	 * Displays the contents of the edit page.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function output() {
		/*
		 * Renders the base edit page layout, with the columns and everything else =)
		 */
		wu_get_template(
			'base/wizard',
			[
				'screen'               => get_current_screen(),
				'page'                 => $this,
				'logo'                 => $this->get_logo(),
				'labels'               => $this->get_labels(),
				'sections'             => $this->prepare_sections_for_display($this->get_sections()),
				'current_section'      => $this->get_current_section(),
				'classes'              => $this->get_classes(),
				'clickable_navigation' => $this->clickable_navigation,
				'form_id'              => $this->form_id,
			]
		);
	}

	/**
	 * Resolves a single display value for wizard sections.
	 *
	 * @param mixed $value The raw value.
	 * @param bool  $cast_to_bool Whether the resolved value should be cast to boolean.
	 * @return mixed
	 */
	protected function resolve_section_display_value($value, bool $cast_to_bool = false) {

		if (is_callable($value)) {
			$value = call_user_func($value);
		}

		if ($cast_to_bool) {
			return (bool) $value;
		}

		if (null === $value) {
			return '';
		}

		if (is_scalar($value)) {
			return (string) $value;
		}

		if (is_object($value) && method_exists($value, '__toString')) {
			return (string) $value;
		}

		return '';
	}

	/**
	 * Resolves dynamic section values used for display while preserving callbacks
	 * responsible for handling the view, save routine, and field generation.
	 *
	 * @param array $section The raw section definition.
	 * @return array
	 */
	protected function prepare_section_for_display(array $section): array {

		$display_keys = [
			'title',
			'description',
			'content',
			'next_label',
			'back_label',
			'skip_label',
		];

		foreach ($display_keys as $display_key) {
			if (array_key_exists($display_key, $section)) {
				$section[ $display_key ] = $this->resolve_section_display_value($section[ $display_key ]);
			}
		}

		$boolean_keys = [
			'disable_next',
			'back',
			'skip',
			'next',
		];

		foreach ($boolean_keys as $boolean_key) {
			if (array_key_exists($boolean_key, $section)) {
				$section[ $boolean_key ] = $this->resolve_section_display_value($section[ $boolean_key ], true);
			}
		}

		if ( ! empty($section['sub-sections']) && is_array($section['sub-sections'])) {
			foreach ($section['sub-sections'] as $sub_section_key => $sub_section) {
				if (is_array($sub_section)) {
					$section['sub-sections'][ $sub_section_key ] = $this->prepare_section_for_display($sub_section);
				}
			}
		}

		return $section;
	}

	/**
	 * Resolves the display values of all wizard sections.
	 *
	 * @param array $sections Raw wizard sections.
	 * @return array
	 */
	protected function prepare_sections_for_display(array $sections): array {

		foreach ($sections as $section_key => $section) {
			if (is_array($section)) {
				$sections[ $section_key ] = $this->prepare_section_for_display($section);
			}
		}

		return $sections;
	}

	/**
	 * Return the classes used in the main wrapper.
	 *
	 * @return string
	 */
	protected function get_classes() {
		return 'wu-w-full wu-mx-auto sm:wu-w-11/12 xl:wu-w-8/12 wu-mt-8 sm:wu-max-w-screen-lg';
	}

	/**
	 * Returns the first section of the signup process
	 *
	 * @return string
	 */
	public function get_first_section() {

		$keys = array_keys($this->get_sections());

		if (isset($keys[1])) {
			return $keys[1];
		} else {
			return false;
		}
	}

	/**
	 * Get the current section
	 *
	 * @return string
	 */
	public function get_current_section() {

		$sections = $this->get_sections();

		$sections = array_filter($sections, fn($item) => wu_get_isset($item, 'addon') === false);

		$current_section = isset($_GET[ $this->section_slug ]) ? sanitize_key($_GET[ $this->section_slug ]) : current(array_keys($sections)); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return $current_section;
	}

	/**
	 * Returns the page link for the current section.
	 *
	 * @since 2.0.0
	 *
	 * @param string $section Slug of the section. e.g. general.
	 * @return string
	 */
	public function get_section_link($section) {

		return add_query_arg($this->section_slug, $section);
	}

	/**
	 * Returns the link to the next section on the wizard.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_next_section_link() {

		$sections = $this->get_sections();

		$current_section = $this->get_current_section();

		$keys = array_keys($sections);

		return add_query_arg($this->section_slug, $keys[ array_search($current_section, array_keys($sections), true) + 1 ]);
	}

	/**
	 * Returns the link to the previous section on the wizard.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_prev_section_link() {

		$sections = $this->get_sections();

		$current_section = $this->get_current_section();

		$keys = array_keys($sections);

		$current_section_idx = array_search($current_section, array_keys($sections), true);

		if (false === $current_section_idx) {
			return '';
		}
		if (empty($keys[ $current_section_idx - 1 ])) {
			return '';
		}
		return add_query_arg($this->section_slug, $keys[ $current_section_idx - 1 ]);
	}

	/**
	 * Default handler for step submission. Simply redirects to the next step.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function default_handler() {

		wp_safe_redirect($this->get_next_section_link());

		exit;
	}

	/**
	 * Default method for views.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function default_view() {

		$section = wp_parse_args(
			$this->current_section,
			[
				'title'        => '',
				'description'  => '',
				'content'      => '',
				'fields'       => [],
				'next_label'   => __('Continue &rarr;', 'ultimate-multisite'),
				'back_label'   => __('&larr; Go Back', 'ultimate-multisite'),
				'skip_label'   => __('Skip this Step', 'ultimate-multisite'),
				'disable_next' => false,
				'back'         => false,
				'skip'         => false,
				'next'         => true,
			]
		);

		/*
		 * Check if the section has fields
		 */
		if ( ! empty($section['fields'])) {
			if (is_callable($section['fields'])) {
				$section['fields'] = call_user_func($section['fields']);
			}

			$form = new \WP_Ultimo\UI\Form(
				$this->get_current_section(),
				$section['fields'],
				[
					'views'                 => 'admin-pages/fields',
					'classes'               => 'wu-widget-list wu-striped wu-m-0 wu-mt-2 wu--mb-6 wu--mx-6',
					'field_wrapper_classes' => 'wu-w-full wu-box-border wu-items-center wu-flex wu-justify-between wu-px-6 wu-py-4 wu-m-0 wu-border-t wu-border-l-0 wu-border-r-0 wu-border-b-0 wu-border-gray-300 wu-border-solid',
					'html_attr'             => $section['html_attr'] ?? [],
				]
			);

			ob_start();

			$form->render();

			$section['content'] = ob_get_clean();
		}

		wu_get_template(
			'wizards/setup/default',
			array_merge(
				$section,
				[
					'page' => $this,
				]
			)
		);
	}

	/**
	 * Renders the default submit box with action buttons at the bottom of the wizard.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function render_submit_box(): void {

		wu_get_template(
			'base/wizard/submit-box',
			[
				'screen' => get_current_screen(),
				'page'   => $this,
				'labels' => $this->get_labels(),
			]
		);
	}

	/**
	 * Render the installation steps table.
	 *
	 * @since 2.0.0
	 *
	 * @param array   $steps The list of steps.
	 * @param boolean $checks If we should add the checkbox for selection or not.
	 * @return string
	 */
	public function render_installation_steps($steps, $checks = true) {

		wp_localize_script('wu-setup-wizard', 'wu_setup', $steps);

		wp_localize_script(
			'wu-setup-wizard',
			'wu_setup_settings',
			[
				'dry_run'               => wu_request('dry-run', true),
				'generic_error_message' => __('A server error happened while processing this item.', 'ultimate-multisite'),
			]
		);

		wp_enqueue_script('wu-setup-wizard');

		return wu_get_template_contents(
			'wizards/setup/installation_steps',
			[
				'page'   => $this,
				'steps'  => $steps,
				'checks' => $checks,
			]
		);
	}

	/**
	 * Wizard classes should implement a method that returns an array of sections and subsections.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	abstract public function get_sections();
}
