<?php
/**
 * Ultimate Multisite Dashboard Admin Page.
 *
 * @package WP_Ultimo
 * @subpackage Admin_Pages
 * @since 2.0.0
 */

namespace WP_Ultimo\Admin_Pages;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Ultimate Multisite Dashboard Admin Page.
 */
class Hosting_Integration_Wizard_Admin_Page extends Wizard_Admin_Page {

	/**
	 * Holds the ID for this page, this is also used as the page slug.
	 *
	 * @var string
	 */
	protected $id = 'wp-ultimo-hosting-integration-wizard';

	/**
	 * Is this a top-level menu or a submenu?
	 *
	 * @since 1.8.2
	 * @var string
	 */
	protected $type = 'submenu';

	/**
	 * Is this a top-level menu or a submenu?
	 *
	 * @since 1.8.2
	 * @var string
	 */
	protected $parent = 'none';

	/**
	 * This page has no parent, so we need to highlight another sub-menu.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $highlight_menu_slug = 'wp-ultimo-settings';

	/**
	 * If this number is greater than 0, a badge with the number will be displayed alongside the menu title
	 *
	 * @since 1.8.2
	 * @var integer
	 */
	protected $badge_count = 0;

	/**
	 * Holds the admin panels where this page should be displayed, as well as which capability to require.
	 *
	 * To add a page to the regular admin (wp-admin/), use: 'admin_menu' => 'capability_here'
	 * To add a page to the network admin (wp-admin/network), use: 'network_admin_menu' => 'capability_here'
	 * To add a page to the user (wp-admin/user) admin, use: 'user_admin_menu' => 'capability_here'
	 *
	 * @since 2.0.0
	 * @var array
	 */
	protected $supported_panels = [
		'network_admin_menu' => 'manage_network',
	];

	/**
	 * Current integration being setup.
	 *
	 * @since 2.0.0
	 * @var \WP_Ultimo\Integrations\Integration|null
	 */
	protected $integration;

	/**
	 * Allow child classes to add further initializations.
	 *
	 * @since 1.8.2
	 * @return void
	 */
	public function page_loaded(): void {

		if (isset($_GET['integration'])) { // phpcs:ignore WordPress.Security.NonceVerification
			$id = sanitize_text_field(wp_unslash($_GET['integration'])); // phpcs:ignore WordPress.Security.NonceVerification

			// Try the new Integration Registry first
			$registry    = \WP_Ultimo\Integrations\Integration_Registry::get_instance();
			$integration = $registry->get($id);

			if ($integration) {
				$this->integration = $integration;
			} else {
				// Fall back to legacy Domain_Manager
				$domain_manager    = \WP_Ultimo\Managers\Domain_Manager::get_instance();
				$this->integration = $domain_manager->get_integration_instance($id);
			}
		}

		if ( ! $this->integration) {
			wp_safe_redirect(network_admin_url('admin.php?page=wp-ultimo-settings'));

			exit;
		}

		parent::page_loaded();
	}

	/**
	 * Returns the title of the page.
	 *
	 * @since 2.0.0
	 * @return string Title of the page.
	 */
	public function get_title(): string {

		return sprintf(__('Integration Setup', 'ultimate-multisite'));
	}

	/**
	 * Returns the title of menu for this page.
	 *
	 * @since 2.0.0
	 * @return string Menu label of the page.
	 */
	public function get_menu_title() {

		return __('Host Provider Integration', 'ultimate-multisite');
	}

	/**
	 * Returns the sections for this Wizard.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_sections() {

		$sections = [
			'activation'   => [
				'title'   => __('Activation', 'ultimate-multisite'),
				'view'    => [$this, 'section_activation'],
				'handler' => [$this, 'handle_activation'],
			],
			'instructions' => [
				'title' => __('Instructions', 'ultimate-multisite'),
				'view'  => [$this, 'section_instructions'],
			],
			'config'       => [
				'title'   => __('Configuration', 'ultimate-multisite'),
				'view'    => [$this, 'section_configuration'],
				'handler' => [$this, 'handle_configuration'],
			],
			'testing'      => [
				'title' => __('Testing Integration', 'ultimate-multisite'),
				'view'  => [$this, 'section_test'],
			],
			'done'         => [
				'title' => __('Ready!', 'ultimate-multisite'),
				'view'  => [$this, 'section_ready'],
			],
		];

		/*
		 * Some host providers require no instructions.
		 */
		if ($this->integration->supports('no-instructions') || ! method_exists($this->integration, 'get_instructions')) {
			unset($sections['instructions']);
		}

		/*
		 * Some host providers require no additional setup.
		 */
		if ($this->integration->supports('no-config')) {
			unset($sections['config']);
		}

		/**
		 * Filters the wizard sections for hosting integration setup.
		 *
		 * Allows addons to add, remove, or modify wizard sections.
		 *
		 * @since 2.5.0
		 *
		 * @param array                                                                           $sections    Wizard sections.
		 * @param \WP_Ultimo\Integrations\Integration $integration The integration being configured.
		 * @param Hosting_Integration_Wizard_Admin_Page                                           $page        The wizard page instance.
		 */
		$sections = apply_filters('wu_hosting_integration_wizard_sections', $sections, $this->integration, $this);

		return $sections;
	}

	/**
	 * Displays the content of the activation section.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function section_activation(): void {

		$explainer_lines = $this->integration->get_explainer_lines();

		wu_get_template(
			'wizards/host-integrations/activation',
			[
				'screen'      => get_current_screen(),
				'page'        => $this,
				'integration' => $this->integration,
				'will'        => $explainer_lines['will'],
				'will_not'    => $explainer_lines['will_not'],
			]
		);
	}

	/**
	 * Displays the contents of the instructions section.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function section_instructions(): void {

		if (method_exists($this->integration, 'get_instructions')) {
			call_user_func([$this->integration, 'get_instructions']);
		}

		$this->render_submit_box();
	}

	/**
	 * Displays the content of the configuration section.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function section_configuration(): void {

		$fields = $this->integration->get_fields();

		// Aggregate fields from capability modules if this is a new Integration
		if ($this->integration instanceof \WP_Ultimo\Integrations\Integration) {
			$registry = \WP_Ultimo\Integrations\Integration_Registry::get_instance();

			foreach ($registry->get_capabilities($this->integration->get_id()) as $module) {
				$module_fields = $module->get_fields();

				if ( ! empty($module_fields)) {
					$fields = array_merge($fields, $module_fields);
				}
			}
		}

		foreach ($fields as $field_constant => &$field) {
			$field['value'] = $this->integration->get_credential($field_constant);

			// Mark fields that are defined as PHP constants in wp-config.php
			if (defined($field_constant) && constant($field_constant)) {
				$field['html_attr'] = array_merge(
					$field['html_attr'] ?? [],
					[
						'disabled' => 'disabled',
					]
				);

				$field['desc'] = sprintf(
					'<span class="wu-text-yellow-700">%s</span>',
					sprintf(
						/* translators: %s is the constant name, e.g. WU_CLOUDWAYS_API_KEY */
						__('Defined as <code>%s</code> in wp-config.php. Edit your wp-config.php to change this value.', 'ultimate-multisite'),
						esc_html($field_constant)
					)
				);
			}
		}

		$form = new \WP_Ultimo\UI\Form(
			$this->get_current_section(),
			$fields,
			[
				'views'                 => 'admin-pages/fields',
				'classes'               => 'wu-widget-list wu-striped wu-m-0 wu--mt-2 wu--mb-3 wu--mx-3',
				'field_wrapper_classes' => 'wu-w-full wu-box-border wu-items-center wu-flex wu-justify-between wu-px-6 wu-py-4 wu-m-0 wu-border-t wu-border-l-0 wu-border-r-0 wu-border-b-0 wu-border-gray-300 wu-border-solid',
			]
		);

		wu_get_template(
			'wizards/host-integrations/configuration',
			[
				'screen'      => get_current_screen(),
				'page'        => $this,
				'integration' => $this->integration,
				'form'        => $form,
			]
		);
	}

	/**
	 * Displays the content of the final section.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function section_ready(): void {

		wu_get_template(
			'wizards/host-integrations/ready',
			[
				'screen'      => get_current_screen(),
				'page'        => $this,
				'integration' => $this->integration,
			]
		);
	}

	/**
	 * Handles the activation of a given integration.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function handle_activation(): void {

		$is_enabled = $this->integration->is_enabled();

		if ($is_enabled) {
			$this->integration->disable();

			return;
		}

		$this->integration->enable();

		wp_safe_redirect($this->get_next_section_link());

		exit;
	}

	/**
	 * Handles the configuration of a given integration.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function handle_configuration(): void {

		check_admin_referer('saving_config', 'saving_config');

		$allowed_fields = array_keys($this->integration->get_fields());

		// Also allow fields from capability modules
		if ($this->integration instanceof \WP_Ultimo\Integrations\Integration) {
			$registry = \WP_Ultimo\Integrations\Integration_Registry::get_instance();

			foreach ($registry->get_capabilities($this->integration->get_id()) as $module) {
				$allowed_fields = array_merge($allowed_fields, array_keys($module->get_fields()));
			}
		}

		// Filter and sanitize $_POST to only include allowed integration fields
		$filtered_data = [];
		foreach ($allowed_fields as $field) {
			if (isset($_POST[ $field ])) {
				$filtered_data[ $field ] = sanitize_text_field(wp_unslash($_POST[ $field ]));
			}
		}

		$this->integration->save_credentials($filtered_data);

		wp_safe_redirect($this->get_next_section_link());

		exit;
	}

	/**
	 * Handles the testing of a given configuration.
	 *
	 * @todo Move Vue to a scripts management class.
	 * @since 2.0.0
	 * @return void
	 */
	public function section_test(): void {

		wp_enqueue_script('wu-vue');

		wu_get_template(
			'wizards/host-integrations/test',
			[
				'screen'      => get_current_screen(),
				'page'        => $this,
				'integration' => $this->integration,
			]
		);
	}

	/**
	 * Register the script for the test page.
	 *
	 * @return void
	 */
	public function register_scripts() {
		parent::register_scripts();

		wp_enqueue_script(
			'wu-integration-test',
			wu_get_asset('integration-test.js', 'js'),
			[
				'jquery',
				'wu-vue',
			],
			wu_get_version(),
			true
		);
		wp_add_inline_script(
			'wu-integration-test',
			'var wu_integration_test_data = {
				integration_id: "' . esc_js($this->integration->get_id()) . '",
				waiting_message: "' . esc_js(__('Waiting for results...', 'ultimate-multisite')) . '"
			};',
			'before'
		);
	}
}
