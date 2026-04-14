<?php
/**
 * Ultimate Multisite Dashboard Admin Page.
 *
 * @package WP_Ultimo
 * @subpackage Admin_Pages
 * @since 2.0.0
 */

namespace WP_Ultimo\Admin_Pages;

use WP_Ultimo\Exception\Runtime_Exception;
use WP_Ultimo\Settings;
use WP_Ultimo\UI\Form;
use WP_Ultimo\UI\Field;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Ultimate Multisite Dashboard Admin Page.
 */
class Settings_Admin_Page extends Wizard_Admin_Page {

	/**
	 * Holds the ID for this page, this is also used as the page slug.
	 *
	 * @var string
	 */
	protected $id = 'wp-ultimo-settings';

	/**
	 * Is this a top-level menu or a submenu?
	 *
	 * @since 1.8.2
	 * @var string
	 */
	protected $type = 'submenu';

	/**
	 * Dashicon to be used on the menu item. This is only used on top-level menus
	 *
	 * @since 1.8.2
	 * @var string
	 */
	protected $menu_icon = '';

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
		'network_admin_menu' => 'wu_read_settings',
	];

	/**
	 * Should we hide admin notices on this page?
	 *
	 * @since 2.0.0
	 * @var boolean
	 */
	protected $hide_admin_notices = false;

	/**
	 * Should we force the admin menu into a folded state?
	 *
	 * @since 2.0.0
	 * @var boolean
	 */
	protected $fold_menu = false;

	/**
	 * Holds the section slug for the URLs.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $section_slug = 'tab';

	/**
	 * Defines if the step links on the side are clickable or not.
	 *
	 * @since 2.0.0
	 * @var boolean
	 */
	protected $clickable_navigation = true;

	/**
	 * Allow child classes to register scripts and styles that can be loaded on the output function, for example.
	 *
	 * @since 1.8.2
	 * @return void
	 */
	public function register_scripts(): void {

		wp_enqueue_editor();

		parent::register_scripts();

		/*
		 * Adds Vue.
		 */
		wp_enqueue_script('wu-vue-apps');

		wp_enqueue_script('wu-fields');
		wp_enqueue_script('wu-ajax-button', wu_get_asset('ajax-button.js', 'js'), ['jquery'], wu_get_version(), true);

		wp_enqueue_style('wp-color-picker');
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
	public function register_widgets(): void {

		parent::register_widgets();

		wu_register_settings_side_panel(
			'login-and-registration',
			[
				'title'  => __('Checkout Forms', 'ultimate-multisite'),
				'render' => [$this, 'render_checkout_forms_side_panel'],
			]
		);

		wu_register_settings_side_panel(
			'sites',
			[
				'title'  => __('Template Previewer', 'ultimate-multisite'),
				'render' => [$this, 'render_site_template_side_panel'],
			]
		);

		wu_register_settings_side_panel(
			'sites',
			[
				'title'  => __('Placeholder Editor', 'ultimate-multisite'),
				'render' => [$this, 'render_site_placeholders_side_panel'],
			]
		);

		wu_register_settings_side_panel(
			'payment-gateways',
			[
				'title'  => __('Invoices', 'ultimate-multisite'),
				'render' => [$this, 'render_invoice_side_panel'],
			]
		);

		wu_register_settings_side_panel(
			'emails',
			[
				'title'  => __('System Emails', 'ultimate-multisite'),
				'render' => [$this, 'render_system_emails_side_panel'],
			]
		);

		wu_register_settings_side_panel(
			'emails',
			[
				'title'  => __('Email Template', 'ultimate-multisite'),
				'render' => [$this, 'render_email_template_side_panel'],
			]
		);
	}

	/**
	 * Renders the addons side panel
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function render_checkout_forms_side_panel(): void {
		?>

		<div class="wu-widget-inset">

			<div class="wu-p-4">

				<span class="wu-text-gray-700 wu-font-bold wu-uppercase wu-tracking-wide wu-text-xs">
					<?php esc_html_e('Checkout Forms', 'ultimate-multisite'); ?>
				</span>

				<div class="wu-py-2">
					<img class="wu-w-full" alt="<?php esc_attr_e('Checkout Forms', 'ultimate-multisite'); ?>" src="<?php echo esc_attr(wu_get_asset('sidebar/checkout-forms.webp')); ?>">
				</div>

				<p class="wu-text-gray-600 wu-p-0 wu-m-0">
					<?php esc_html_e('You can create multiple Checkout Forms for different occasions (seasonal campaigns, launches, etc)!', 'ultimate-multisite'); ?>
				</p>

			</div>

			<?php if (current_user_can('wu_edit_checkout_forms')) : ?>

				<div class="wu-p-4 wu-bg-gray-100 wu-border-solid wu-border-0 wu-border-t wu-border-gray-300">
					<a class="button wu-w-full wu-text-center" href="<?php echo esc_attr(wu_network_admin_url('wp-ultimo-checkout-forms')); ?>">
						<?php esc_html_e('Manage Checkout Forms &rarr;', 'ultimate-multisite'); ?>
					</a>
				</div>

			<?php endif; ?>

		</div>

		<?php
	}

	/**
	 * Renders the site template side panel
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function render_site_template_side_panel(): void {

		?>

		<div class="wu-widget-inset">

			<div class="wu-p-4">

				<span class="wu-text-gray-700 wu-font-bold wu-uppercase wu-tracking-wide wu-text-xs">
					<?php esc_html_e('Customize the Template Previewer', 'ultimate-multisite'); ?>
				</span>

				<div class="wu-py-2">
					<img class="wu-w-full" alt="<?php esc_attr_e('Customize the Template Previewer', 'ultimate-multisite'); ?>" src="<?php echo esc_attr(wu_get_asset('sidebar/site-template.webp')); ?>">
				</div>

				<p class="wu-text-gray-600 wu-p-0 wu-m-0">
					<?php esc_html_e('Did you know that you can customize colors, logos, and more options of the Site Template Previewer top-bar?', 'ultimate-multisite'); ?>
				</p>

			</div>

			<?php if (current_user_can('wu_edit_sites')) : ?>

				<div class="wu-p-4 wu-bg-gray-100 wu-border-solid wu-border-0 wu-border-t wu-border-gray-300">
					<a class="button wu-w-full wu-text-center" target="_blank" href="<?php echo esc_attr(wu_network_admin_url('wp-ultimo-customize-template-previewer')); ?>">
						<?php esc_html_e('Go to Customizer &rarr;', 'ultimate-multisite'); ?>
					</a>
				</div>

			<?php endif; ?>

		</div>

		<?php
	}

	/**
	 * Renders the site placeholder side panel
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function render_site_placeholders_side_panel(): void {

		?>

		<div class="wu-widget-inset">

			<div class="wu-p-4">

				<span class="wu-text-gray-700 wu-font-bold wu-uppercase wu-tracking-wide wu-text-xs">
					<?php esc_html_e('Customize the Template Placeholders', 'ultimate-multisite'); ?>
				</span>

				<div class="wu-py-2">
					<img class="wu-w-full" alt="<?php esc_attr_e('Customize the Template Placeholders', 'ultimate-multisite'); ?>" src="<?php echo esc_attr(wu_get_asset('sidebar/template-placeholders.webp')); ?>">
				</div>

				<p class="wu-text-gray-600 wu-p-0 wu-m-0">
					<?php esc_html_e('If you are using placeholder substitutions inside your site templates, use this tool to add, remove, or change the default content of those placeholders.', 'ultimate-multisite'); ?>
				</p>

			</div>

			<?php if (current_user_can('wu_edit_sites')) : ?>

				<div class="wu-p-4 wu-bg-gray-100 wu-border-solid wu-border-0 wu-border-t wu-border-gray-300">
					<a class="button wu-w-full wu-text-center" target="_blank" href="<?php echo esc_attr(wu_network_admin_url('wp-ultimo-template-placeholders')); ?>">
						<?php esc_html_e('Edit Placeholders &rarr;', 'ultimate-multisite'); ?>
					</a>
				</div>

			<?php endif; ?>

		</div>

		<?php
	}

	/**
	 * Renders the invoice side panel
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function render_invoice_side_panel(): void {

		?>

		<div class="wu-widget-inset">

			<div class="wu-p-4">

				<span class="wu-text-gray-700 wu-font-bold wu-uppercase wu-tracking-wide wu-text-xs">
					<?php esc_html_e('Customize the Invoice Template', 'ultimate-multisite'); ?>
				</span>

				<div class="wu-py-2">
					<img class="wu-w-full" alt="<?php esc_attr_e('Customize the Invoice Template', 'ultimate-multisite'); ?>" src="<?php echo esc_attr(wu_get_asset('sidebar/invoice-template.webp')); ?>">
				</div>

				<p class="wu-text-gray-600 wu-p-0 wu-m-0">
					<?php esc_html_e('Did you know that you can customize colors, logos, and more options of the Invoice PDF template?', 'ultimate-multisite'); ?>
				</p>

			</div>

			<?php if (current_user_can('wu_edit_payments')) : ?>

				<div class="wu-p-4 wu-bg-gray-100 wu-border-solid wu-border-0 wu-border-t wu-border-gray-300">
					<a class="button wu-w-full wu-text-center" target="_blank" href="<?php echo esc_attr(wu_network_admin_url('wp-ultimo-customize-invoice-template')); ?>">
						<?php esc_html_e('Go to Customizer &rarr;', 'ultimate-multisite'); ?>
					</a>
				</div>

			<?php endif; ?>

		</div>

		<?php
	}

	/**
	 * Renders system emails side panel.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function render_system_emails_side_panel(): void {

		?>

		<div class="wu-widget-inset">

			<div class="wu-p-4">

				<span class="wu-text-gray-700 wu-font-bold wu-uppercase wu-tracking-wide wu-text-xs">
					<?php esc_html_e('Customize System Emails', 'ultimate-multisite'); ?>
				</span>

				<div class="wu-py-2">
					<img class="wu-w-full" alt="<?php esc_attr_e('Customize System Emails', 'ultimate-multisite'); ?>" src="<?php echo esc_attr(wu_get_asset('sidebar/system-emails.webp')); ?>">
				</div>

				<p class="wu-text-gray-600 wu-p-0 wu-m-0">
					<?php esc_html_e('You can completely customize the contents of the emails sent out by Ultimate Multisite when particular events occur, such as Account Creation, Payment Failures, etc.', 'ultimate-multisite'); ?>
				</p>

			</div>

			<?php if (current_user_can('wu_edit_broadcasts')) : ?>

				<div class="wu-p-4 wu-bg-gray-100 wu-border-solid wu-border-0 wu-border-t wu-border-gray-300">
					<a class="button wu-w-full wu-text-center" target="_blank" href="<?php echo esc_attr(wu_network_admin_url('wp-ultimo-emails')); ?>">
						<?php esc_html_e('Customize System Emails &rarr;', 'ultimate-multisite'); ?>
					</a>
				</div>

			<?php endif; ?>

		</div>

		<?php
	}

	/**
	 * Renders the email template side panel.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function render_email_template_side_panel(): void {

		?>

		<div class="wu-widget-inset">

			<div class="wu-p-4">

				<span class="wu-text-gray-700 wu-font-bold wu-uppercase wu-tracking-wide wu-text-xs">
					<?php esc_html_e('Customize Email Template', 'ultimate-multisite'); ?>
				</span>

				<div class="wu-py-2">
					<img class="wu-w-full" alt="<?php esc_attr_e('Customize Email Template', 'ultimate-multisite'); ?>" src="<?php echo esc_attr(wu_get_asset('sidebar/email-template.webp')); ?>">
				</div>

				<p class="wu-text-gray-600 wu-p-0 wu-m-0">
					<?php esc_html_e('If your network is using the HTML email option, you can customize the look and feel of the email template.', 'ultimate-multisite'); ?>
				</p>

			</div>

			<?php if (current_user_can('wu_edit_broadcasts')) : ?>

				<div class="wu-p-4 wu-bg-gray-100 wu-border-solid wu-border-0 wu-border-t wu-border-gray-300">
					<a class="button wu-w-full wu-text-center" target="_blank" href="<?php echo esc_attr(wu_network_admin_url('wp-ultimo-customize-email-template')); ?>">
						<?php esc_html_e('Customize Email Template &rarr;', 'ultimate-multisite'); ?>
					</a>
				</div>

			<?php endif; ?>

		</div>

		<?php
	}

	// phpcs:enable

	/**
	 * Returns the title of the page.
	 *
	 * @since 2.0.0
	 * @return string Title of the page.
	 */
	public function get_title() {

		return __('Settings', 'ultimate-multisite');
	}

	/**
	 * Returns the title of menu for this page.
	 *
	 * @since 2.0.0
	 * @return string Menu label of the page.
	 */
	public function get_menu_title() {

		return __('Settings', 'ultimate-multisite');
	}

	/**
	 * Every child class should implement the output method to display the contents of the page.
	 *
	 * @since 1.8.2
	 * @return void
	 */
	public function output(): void {
		/*
		 * Enqueue the base Dashboard Scripts
		 */
		wp_enqueue_media();
		wp_enqueue_script('dashboard');
		wp_enqueue_style('wp-color-picker');
		wp_enqueue_script('wp-color-picker');
		wp_enqueue_script('media');
		wp_enqueue_script('wu-vue');
		wp_enqueue_script('wu-selectizer');
		wp_enqueue_script('wu-settings-loader', wu_get_asset('settings-loader.js', 'js'), ['wu-functions'], wu_get_version(), true);

		do_action('wu_render_settings');

		wu_get_template(
			'base/settings',
			[
				'screen'               => get_current_screen(),
				'page'                 => $this,
				'classes'              => '',
				'sections'             => $this->get_sections(),
				'current_section'      => $this->get_current_section(),
				'clickable_navigation' => $this->clickable_navigation,
			]
		);
	}

	/**
	 * Returns the list of settings sections.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_sections() {

		return WP_Ultimo()->settings->get_sections();
	}

	/**
	 * Default handler for step submission. Simply redirects to the next step.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function default_handler(): void {

		if ( ! current_user_can('wu_edit_settings')) {
			wp_die(esc_html__('You do not have the permissions required to change settings.', 'ultimate-multisite'));
		}

		// Get all valid setting keys from sections
		$sections       = WP_Ultimo()->settings->get_sections();
		$allowed_fields = [];
		foreach ($sections as $section) {
			if (isset($section['fields'])) {
				$allowed_fields = array_merge($allowed_fields, $section['fields']);
			}
		}

		// Filter and sanitize $_POST to only include allowed setting fields
		// Nonce processed in the calling method.
		$filtered_data = [];
		foreach ($allowed_fields as $field => $field_data) {
			if (isset($_POST[ $field ])) { // phpcs:ignore WordPress.Security.NonceVerification
				$value = wp_unslash($_POST[ $field ]); // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				if (is_array($value)) {
					$filtered_data[ $field ] = wu_clean($value);
				} elseif ( ! empty($field_data['allow_html'])) {
					$filtered_data[ $field ] = sanitize_post_field('post_content', $value, $this->get_id(), 'db');
				} else {
					$filtered_data[ $field ] = sanitize_text_field($value);
				}
			}
		}

		if ( ! isset($filtered_data['active_gateways']) && 'payment-gateways' === wu_request('tab')) {
			$filtered_data['active_gateways'] = [];
		}

		WP_Ultimo()->settings->save_settings($filtered_data);

		$redirect_url = add_query_arg('updated', 1, wu_get_current_url());

		/**
		 * Filters the redirect URL after settings are saved.
		 *
		 * Allows gateways or other components to redirect elsewhere
		 * after a settings save (e.g. to initiate an OAuth flow).
		 *
		 * @since 2.x.x
		 *
		 * @param string $redirect_url The default redirect URL.
		 * @param array  $filtered_data The saved settings data.
		 * @return string
		 */
		$redirect_url = apply_filters('wu_settings_save_redirect', $redirect_url, $filtered_data);

		wp_safe_redirect($redirect_url);

		exit;
	}

	/**
	 * Default method for views.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function default_view(): void {

		$sections = $this->get_sections();

		$section_slug = $this->get_current_section();

		$section = $this->current_section;

		$fields = array_filter($section['fields'] ?? [], fn($item) => current_user_can($item['capability']));

		uasort($fields, 'wu_sort_by_order');

		/*
		 * Get Field to save
		 */
		$fields['save'] = [
			'type'            => 'submit',
			'title'           => __('Save Settings', 'ultimate-multisite'),
			'classes'         => 'button button-primary button-large wu-ml-auto wu-w-full md:wu-w-auto',
			'wrapper_classes' => 'wu-sticky wu-bottom-0 wu-save-button wu-mr-px wu-w-full md:wu-w-auto',
			'html_attr'       => [
				'v-on:click' => 'send("window", "wu_block_ui", "#wpcontent")',
			],
		];

		if ( ! current_user_can('wu_edit_settings')) {
			$fields['save']['html_attr']['disabled'] = 'disabled';
		}

		$form = new Form(
			$section_slug,
			$fields,
			[
				'views'                 => 'admin-pages/fields',
				'classes'               => 'wu-modal-form wu-widget-list wu-striped wu--mt-5 wu--mx-in wu--mb-in',
				'field_wrapper_classes' => 'wu-w-full wu-box-border wu-items-center wu-flex wu-justify-between wu-p-4 wu-py-5 wu-m-0 wu-border-t wu-border-l-0 wu-border-r-0 wu-border-b-0 wu-border-gray-300 wu-border-solid',
				'html_attr'             => [
					'style'        => '',
					'data-on-load' => 'remove_block_ui',
					'data-wu-app'  => str_replace('-', '_', $section_slug),
					'data-state'   => wp_json_encode(wu_array_map_keys('wu_replace_dashes', Settings::get_instance()->get_all_with_defaults(true))),
				],
			]
		);

		$form->render();

		/**
		 * Fires after a settings section's form is rendered.
		 *
		 * The dynamic portion of the hook name, `$section_slug`, refers to the
		 * settings section slug (e.g. 'multi-currency', 'payment-gateways').
		 *
		 * @since 2.3.0
		 */
		do_action("wu_settings_{$section_slug}_after");
	}

	/**
	 * Overrides parent page_loaded to handle export/import functionality.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function page_loaded() {

		$this->handle_export();
		$this->handle_import_redirect();
		$this->handle_orphaned_delete_redirect();
		$this->handle_integration_setup_redirect();
		$this->register_forms();

		parent::page_loaded();
	}

	/**
	 * Handle settings export request.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	protected function handle_export() {

		if ( ! isset($_GET['wu_export_settings'])) {
			return;
		}
		check_admin_referer('wu_export_settings');

		// Check permissions
		if ( ! current_user_can('wu_edit_settings')) {
			wp_die(esc_html__('You do not have permission to export settings.', 'ultimate-multisite'));
		}

		$result = $this->export_settings();

		$export_data = $result['data'];
		$filename    = $result['filename'];

		nocache_headers();

		header('Content-Disposition: attachment; filename=' . $filename);
		header('Pragma: no-cache');
		header('Expires: 0');
		wp_send_json($export_data, null, JSON_PRETTY_PRINT);

		exit;
	}

	/**
	 * Register import form.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function register_forms() {

		wu_register_form(
			'import_settings',
			[
				'render'     => [$this, 'render_import_settings_modal'],
				'handler'    => [$this, 'handle_import_settings_modal'],
				'capability' => 'wu_edit_settings',
			]
		);
	}

	/**
	 * Render the import settings modal.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function render_import_settings_modal() {

		$fields = [
			'import_file_header' => [
				'type'  => 'header',
				'title' => __('Upload Settings File', 'ultimate-multisite'),
				'desc'  => __('Select a JSON file previously exported from Ultimate Multisite.', 'ultimate-multisite'),
			],
			'import_file'        => [
				'type'    => 'html',
				'content' => '<input type="file" name="import_file" id="import_file" accept=".json" required class="wu-w-full" />',
			],
			'confirm'            => [
				'type'      => 'toggle',
				'title'     => __('I understand this will replace all current settings', 'ultimate-multisite'),
				'desc'      => __('This action cannot be undone. Make sure you have a backup of your current settings.', 'ultimate-multisite'),
				'value'     => false,
				'html_attr' => [
					'v-model' => 'confirm',
				],
			],
			'submit_button'      => [
				'type'            => 'submit',
				'title'           => __('Import Settings', 'ultimate-multisite'),
				'value'           => 'save',
				'classes'         => 'button button-primary wu-w-full',
				'wrapper_classes' => 'wu-items-end',
				'html_attr'       => [
					'v-bind:disabled' => '!confirm',
				],
			],
		];

		$form = new Form(
			'import_settings',
			$fields,
			[
				'views'                 => 'admin-pages/fields',
				'classes'               => 'wu-modal-form wu-widget-list wu-striped wu-m-0',
				'field_wrapper_classes' => 'wu-w-full wu-box-border wu-items-center wu-flex wu-justify-between wu-p-4 wu-m-0 wu-border-t wu-border-l-0 wu-border-r-0 wu-border-b-0 wu-border-gray-300 wu-border-solid',
				'html_attr'             => [
					'data-wu-app' => 'import_settings_modal',
					'data-state'  => wp_json_encode(['confirm' => false]),
					'enctype'     => 'multipart/form-data',
				],
			]
		);

		$form->render();
	}

	/**
	 * Handle import settings form submission.
	 *
	 * @since 2.0.0
	 * @return void
	 * @throws Runtime_Exception When an error is found in the file.
	 */
	public function handle_import_settings_modal() {

		try {
			// Validate file upload
			if ( ! isset($_FILES['import_file']) || empty($_FILES['import_file']['tmp_name'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				throw new Runtime_Exception('no_file');
			}

			// Validate and parse the file
			$file = $_FILES['import_file']; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			// Check for upload errors
			if (UPLOAD_ERR_OK !== $file['error']) {
				throw new Runtime_Exception('upload_error');
			}

			// Check file extension
			$file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

			if ('json' !== $file_ext) {
				throw new Runtime_Exception('invalid_file_type');
			}

			// Read and decode JSON
			$json_content = file_get_contents($file['tmp_name']); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

			if (false === $json_content) {
				throw new Runtime_Exception('read_error');
			}

			$data = json_decode($json_content, true);

			if (null === $data) {
				throw new Runtime_Exception('invalid_json');
			}

			// Validate structure
			if ( ! isset($data['plugin']) || 'ultimate-multisite' !== $data['plugin']) {
				throw new Runtime_Exception('invalid_format');
			}

			if ( ! isset($data['settings']) || ! is_array($data['settings'])) {
				throw new Runtime_Exception('invalid_structure');
			}
		} catch ( Runtime_Exception $e ) {
			wp_send_json_error(new \WP_Error($e->getMessage(), __('Something is wrong with the uploaded file.', 'ultimate-multisite')));
		}

		// Validate imported settings against allowed fields (same as default_handler)
		$sections       = WP_Ultimo()->settings->get_sections();
		$allowed_fields = [];
		foreach ($sections as $section) {
			if (isset($section['fields'])) {
				$allowed_fields = array_merge($allowed_fields, array_keys($section['fields']));
			}
		}

		$filtered_settings = array_intersect_key($data['settings'], array_flip($allowed_fields));

		WP_Ultimo()->settings->save_settings($filtered_settings);

		do_action('wu_settings_imported', $data['settings'], $data);

		do_action('wu_settings_imported', $data['settings'], $data);

		// Success
		wp_send_json_success(
			[
				'redirect_url' => add_query_arg(
					[
						'tab'     => 'import-export',
						'updated' => 1,
					],
					wu_network_admin_url('wp-ultimo-settings')
				),
			]
		);
	}

	/**
	 * Display a success message after import redirect.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	protected function handle_import_redirect() {

		if ( ! isset($_GET['updated']) || 'import-export' !== wu_request('tab')) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		add_action(
			'wu_page_wizard_after_title',
			function () {
				?>
			<div id="message" class="updated notice wu-admin-notice notice-success is-dismissible">
				<p><?php esc_html_e('Settings successfully imported!', 'ultimate-multisite'); ?></p>
			</div>
				<?php
			}
		);
	}

	/**
	 * Display a success or info message after orphaned tables/users deletion redirect.
	 *
	 * Handles the redirect from both the orphaned tables and orphaned users deletion
	 * modals, showing the admin a notice with the number of items deleted.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	protected function handle_orphaned_delete_redirect() {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset($_GET['deleted']) || ! isset($_GET['type']) || 'other' !== wu_request('tab')) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$deleted_count = absint($_GET['deleted']);
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$type = sanitize_key($_GET['type']);

		add_action(
			'wu_page_wizard_after_title',
			function () use ($deleted_count, $type) {

				if ('tables' === $type) {
					if ($deleted_count > 0) {
						$message = sprintf(
							/* translators: %d: number of deleted tables */
							_n(
								'Successfully deleted %d orphaned database table.',
								'Successfully deleted %d orphaned database tables.',
								$deleted_count,
								'ultimate-multisite'
							),
							$deleted_count
						);
						$notice_class = 'notice-success';
					} else {
						$message      = __('No orphaned database tables were found to delete.', 'ultimate-multisite');
						$notice_class = 'notice-info';
					}
				} elseif ('users' === $type) {
					if ($deleted_count > 0) {
						$message = sprintf(
							/* translators: %d: number of deleted users */
							_n(
								'Successfully deleted %d orphaned user account.',
								'Successfully deleted %d orphaned user accounts.',
								$deleted_count,
								'ultimate-multisite'
							),
							$deleted_count
						);
						$notice_class = 'notice-success';
					} else {
						$message      = __('No orphaned user accounts were found to delete.', 'ultimate-multisite');
						$notice_class = 'notice-info';
					}
				} else {
					return;
				}

				printf(
					'<div id="message" class="updated notice wu-admin-notice %s is-dismissible"><p>%s</p></div>',
					esc_attr($notice_class),
					esc_html($message)
				);
			}
		);
	}

	/**
	 * Display a success notice after completing the hosting integration wizard.
	 *
	 * Triggered when redirected back to the integrations settings tab
	 * with ?integration-setup=1 in the URL.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	protected function handle_integration_setup_redirect() {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset($_GET['integration-setup']) || 'integrations' !== wu_request('tab')) {
			return;
		}

		add_action(
			'wu_page_wizard_after_title',
			function () {
				?>
			<div id="message" class="updated notice wu-admin-notice notice-success is-dismissible">
				<p><?php esc_html_e('Integration successfully set up!', 'ultimate-multisite'); ?></p>
			</div>
				<?php
			}
		);
	}

	/**
	 * Export settings to JSON format.
	 *
	 * @since 2.0.0
	 *
	 * @return array Array containing 'success' bool, 'data' string (JSON), and 'filename' string.
	 */
	private function export_settings() {

		$settings = wu_get_all_settings();

		$export_data = [
			'version'    => \WP_Ultimo::VERSION,
			'plugin'     => 'ultimate-multisite',
			'timestamp'  => time(),
			'site_url'   => get_site_url(),
			'wp_version' => get_bloginfo('version'),
			'settings'   => $settings,
		];

		$filename = sprintf(
			'ultimate-multisite-settings-export-%s-%s.json',
			gmdate('Y-m-d'),
			get_current_site()->cookie_domain,
		);

		return [
			'success'  => true,
			'data'     => $export_data,
			'filename' => $filename,
		];
	}
}
