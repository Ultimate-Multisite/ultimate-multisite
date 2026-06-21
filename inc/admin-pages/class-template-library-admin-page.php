<?php
/**
 * Template Library Admin Page.
 *
 * @package WP_Ultimo
 * @subpackage Admin_Pages
 * @since 2.5.0
 */

namespace WP_Ultimo\Admin_Pages;

use WP_Ultimo\Template_Library\Template_Library;
use WP_Ultimo\Template_Library\Template_Repository;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Template Library Admin Page.
 *
 * @since 2.5.0
 */
class Template_Library_Admin_Page extends Wizard_Admin_Page {

	/**
	 * Holds the ID for this page, this is also used as the page slug.
	 *
	 * @var string
	 */
	protected $id = 'wp-ultimo-template-library';

	/**
	 * Is this a top-level menu or a submenu?
	 *
	 * @since 2.5.0
	 * @var string
	 */
	protected $type = 'submenu';

	/**
	 * Menu position. This is only used for top-level menus.
	 *
	 * @since 2.5.0
	 * @var int
	 */
	protected $position = 998;

	/**
	 * Is this a top-level menu or a submenu?
	 *
	 * @since 2.5.0
	 * @var string
	 */
	protected $parent = 'wp-ultimo';

	/**
	 * Badge count.
	 *
	 * @since 2.5.0
	 * @var int
	 */
	protected $badge_count = 0;

	/**
	 * Holds the admin panels where this page should be displayed.
	 *
	 * @since 2.5.0
	 * @var array
	 */
	protected $supported_panels = [
		'network_admin_menu' => 'wu_read_settings',
	];

	/**
	 * Should we hide admin notices on this page?
	 *
	 * @since 2.5.0
	 * @var bool
	 */
	protected $hide_admin_notices = false;

	/**
	 * Should we force the admin menu into a folded state?
	 *
	 * @since 2.5.0
	 * @var bool
	 */
	protected $fold_menu = false;

	/**
	 * Holds the section slug for the URLs.
	 *
	 * @since 2.5.0
	 * @var string
	 */
	protected $section_slug = 'tab';

	/**
	 * Defines if the step links on the side are clickable or not.
	 *
	 * @since 2.5.0
	 * @var bool
	 */
	protected $clickable_navigation = true;

	/**
	 * Template Repository instance.
	 *
	 * @since 2.5.0
	 * @var Template_Repository|null
	 */
	protected ?Template_Repository $repository = null;

	/**
	 * Allow child classes to add hooks to be run once the page is loaded.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function init(): void {

		parent::init();

		add_action('wp_ajax_serve_templates_list', [$this, 'serve_templates_list']);
	}

	/**
	 * Register forms.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function register_forms(): void {

		wu_register_form(
			'template_more_info',
			[
				'render'  => [$this, 'display_more_info'],
				'handler' => [$this, 'install_template'],
			]
		);

		wu_register_form(
			'upload_template',
			[
				'render'     => [$this, 'render_upload_template_modal'],
				'handler'    => [$this, 'handle_upload_template_modal'],
				'capability' => 'manage_network',
			]
		);
	}

	/**
	 * Renders the upload template modal.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function render_upload_template_modal(): void {

		// Reset upload limits for large ZIP files
		\WP_Ultimo\Site_Exporter\Site_Exporter::get_instance()->reset_upload_limits();

		$fields = [
			'template_name' => [
				'type'        => 'text',
				'title'       => __('Template Name', 'ultimate-multisite'),
				'placeholder' => __('My Awesome Template', 'ultimate-multisite'),
				'desc'        => __('A descriptive name for this template.', 'ultimate-multisite'),
			],
			'zip_file'      => [
				'type'        => 'text',
				'title'       => __('ZIP File URL', 'ultimate-multisite'),
				'placeholder' => __('https://example.com/export.zip', 'ultimate-multisite'),
				'desc'        => __('Upload or enter URL to a site export ZIP file.', 'ultimate-multisite'),
				'html_attr'   => [
					'id' => 'wu-template-zip-url',
				],
			],
			'upload_btn'    => [
				'type'            => 'html',
				'content'         => sprintf(
					'<button type="button" class="button wu-w-full" id="wu-upload-template-btn">%s</button>',
					__('Upload ZIP File', 'ultimate-multisite')
				),
				'wrapper_classes' => 'wu-mb-4',
			],
			'template_url'  => [
				'type'        => 'text',
				'title'       => __('Template Site URL', 'ultimate-multisite'),
				'placeholder' => is_subdomain_install() ? 'template-name.example.com' : 'example.com/template-name',
				'desc'        => __('The URL for the new template site.', 'ultimate-multisite'),
			],
			'categories'    => [
				'type'        => 'text',
				'title'       => __('Categories', 'ultimate-multisite'),
				'placeholder' => __('business, portfolio', 'ultimate-multisite'),
				'desc'        => __('Comma-separated list of categories.', 'ultimate-multisite'),
			],
			'submit_button' => [
				'type'            => 'submit',
				'title'           => __('Create Template', 'ultimate-multisite'),
				'value'           => 'save',
				'classes'         => 'button button-primary wu-w-full',
				'wrapper_classes' => 'wu-items-end wu-text-right',
			],
		];

		$form = new \WP_Ultimo\UI\Form(
			'upload_template',
			$fields,
			[
				'views'                 => 'admin-pages/fields',
				'classes'               => 'wu-modal-form wu-widget-list wu-striped wu-m-0 wu-mt-0',
				'field_wrapper_classes' => 'wu-w-full wu-box-border wu-items-center wu-flex wu-justify-between wu-p-4 wu-m-0 wu-border-t wu-border-l-0 wu-border-r-0 wu-border-b-0 wu-border-gray-300 wu-border-solid',
			]
		);

		$form->render();

		// Add media uploader script
		?>
		<script>
		jQuery(document).ready(function($) {
			$('#wu-upload-template-btn').on('click', function(e) {
				e.preventDefault();
				var frame = wp.media({
					title: '<?php echo esc_js(__('Select or Upload Template ZIP', 'ultimate-multisite')); ?>',
					button: { text: '<?php echo esc_js(__('Use this file', 'ultimate-multisite')); ?>' },
					multiple: false
				});
				frame.on('select', function() {
					var attachment = frame.state().get('selection').first().toJSON();
					$('#wu-template-zip-url').val(attachment.url);
				});
				frame.open();
			});
		});
		</script>
		<?php
	}

	/**
	 * Handles the upload template form submission.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function handle_upload_template_modal(): void {

		$template_name = wu_request('template_name', '');
		$zip_url       = wu_request('zip_file', '');
		$template_url  = wu_request('template_url', '');
		$categories    = wu_request('categories', '');

		if (empty($template_name)) {
			wp_send_json_error(new \WP_Error('no-name', __('Please provide a template name.', 'ultimate-multisite')));
		}

		if (empty($zip_url)) {
			wp_send_json_error(new \WP_Error('no-file', __('Please provide a ZIP file.', 'ultimate-multisite')));
		}

		if (empty($template_url)) {
			wp_send_json_error(new \WP_Error('no-url', __('Please provide a URL for the template site.', 'ultimate-multisite')));
		}

		// Convert URL to file path
		$upload_dir = wp_upload_dir();
		$file_path  = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $zip_url);

		if (! file_exists($file_path)) {
			wp_send_json_error(new \WP_Error('file-not-found', __('ZIP file not found.', 'ultimate-multisite')));
		}

		// Import the site as a template
		$result = wu_exporter_import(
			$file_path,
			[
				'url'         => $template_url,
				'new_url'     => $template_url,
				'delete_file' => false,
				'zip_url'     => $zip_url,
			]
		);

		if (is_wp_error($result)) {
			wp_send_json_error($result);
		}

		// Parse categories
		$category_list = array_map('trim', explode(',', $categories));
		$category_list = array_filter($category_list);

		// Note: The site will be created by the import process.
		// We set a transient to update it to site_template type after import completes.
		set_site_transient(
			'wu_pending_template_setup_' . md5($template_url),
			[
				'name'       => $template_name,
				'url'        => $template_url,
				'categories' => $category_list,
			],
			HOUR_IN_SECONDS
		);

		wp_send_json_success(
			[
				'redirect_url' => wu_network_admin_url(
					'wp-ultimo-sites',
					[
						'type'    => 'site_template',
						'updated' => __('Template import started. The site will be available shortly.', 'ultimate-multisite'),
					]
				),
			]
		);
	}

	/**
	 * Gets the Template Repository.
	 *
	 * @since 2.5.0
	 * @return Template_Repository
	 */
	protected function get_repository(): Template_Repository {

		if (null === $this->repository) {
			$this->repository = new Template_Repository();
		}

		return $this->repository;
	}

	/**
	 * Get a template given a slug.
	 *
	 * @since 2.5.0
	 * @param string $template_slug The template slug.
	 * @return array|null
	 */
	private function get_template(string $template_slug): ?array {

		$templates = $this->get_templates_list();

		foreach ($templates as $template) {
			if ($template['slug'] === $template_slug) {
				return $template;
			}
		}

		return null;
	}

	/**
	 * Displays the more info modal.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function display_more_info(): void {

		$template_slug = wu_request('template');

		$template = $this->get_template($template_slug);

		if ( ! $template) {
			return;
		}

		wu_get_template(
			'template-library/details',
			[
				'template'      => (object) $template,
				'template_slug' => $template_slug,
			]
		);

		do_action('wu_form_scripts', false);
	}

	/**
	 * Installs a given template.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function install_template(): void {

		if (! current_user_can('manage_network_plugins')) {
			$error = new \WP_Error('error', __('You do not have enough permissions to perform this task.', 'ultimate-multisite'));

			wp_send_json_error($error);
		}

		$template_slug = wu_request('template');

		$template = $this->get_template($template_slug);

		if (! $template) {
			wp_send_json_error(new \WP_Error('not_found', __('Template not found.', 'ultimate-multisite')));
		}

		$download_url = $template['download_url'] ?? '';

		if (! $download_url) {
			wp_send_json_error(
				new \WP_Error(
					'no_download',
					sprintf(
						/* translators: %s slug of the template. */
						__('Unable to download template. User does not have permission to install %s', 'ultimate-multisite'),
						$template_slug
					)
				)
			);
		}

		// Security check: Ensure URL is from our domain
		$allowed = strncmp($download_url, MULTISITE_ULTIMATE_UPDATE_URL, strlen(MULTISITE_ULTIMATE_UPDATE_URL)) === 0;

		if (! $allowed) {
			$error = new \WP_Error('insecure-url', __('You are trying to download a template from an insecure URL', 'ultimate-multisite'));
			wp_send_json_error($error);
		}

		// Install the template
		$installer = $this->get_repository()->get_installer();

		$result = $installer->install(
			$download_url,
			[
				'slug'    => $template['slug'],
				'name'    => $template['name'],
				'version' => $template['template_version'],
			]
		);

		if (is_wp_error($result)) {
			wp_send_json_error($result);
		}

		// Clear template cache to reflect installation
		$this->get_repository()->clear_cache();

		wp_send_json_success(
			[
				'redirect_url' => wu_network_admin_url(
					'wp-ultimo-sites',
					[
						'type' => 'site_template',
					]
				),
				'message'      => __('Template installed successfully!', 'ultimate-multisite'),
			]
		);
	}

	/**
	 * Enqueue the necessary scripts.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function register_scripts(): void {

		wp_enqueue_style('theme');

		// Enqueue media uploader for template uploads
		wp_enqueue_media();

		wp_register_script('wu-template-library', wu_get_asset('template-library.js', 'js'), ['jquery', 'wu-vue', 'underscore'], wu_get_version(), true);

		wp_localize_script(
			'wu-template-library',
			'wu_template_library',
			[
				'search'   => wu_request('s', ''),
				'category' => wu_request('tab', 'all'),
				'i18n'     => [
					'all'       => __('All Templates', 'ultimate-multisite'),
					'loading'   => __('Loading templates...', 'ultimate-multisite'),
					'no_result' => __('No templates found.', 'ultimate-multisite'),
				],
			]
		);

		wp_enqueue_script('wu-template-library');
	}

	/**
	 * Fetches the list of templates available.
	 *
	 * @since 2.5.0
	 * @return array
	 */
	protected function get_templates_list(): array {

		$templates = $this->get_repository()->get_templates();

		if (is_wp_error($templates)) {
			wu_log_add(
				'api-calls',
				sprintf(
				/* translators: %s error message. */
					__('Failed to fetch templates from API: %s', 'ultimate-multisite'),
					$templates->get_error_message()
				)
			);
			return [];
		}

		return $templates;
	}

	/**
	 * Gets the list of templates from the remote server.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function serve_templates_list(): void {

		$templates_list = $this->get_templates_list();

		wp_send_json_success($templates_list);
	}

	/**
	 * Returns the title of the page.
	 *
	 * @since 2.5.0
	 * @return string Title of the page.
	 */
	public function get_title(): string {

		return __('Template Library', 'ultimate-multisite');
	}

	/**
	 * Returns the title of menu for this page.
	 *
	 * @since 2.5.0
	 * @return string Menu label of the page.
	 */
	public function get_menu_title(): string {

		return __('Template Library', 'ultimate-multisite');
	}

	/**
	 * Returns the title links for this page.
	 *
	 * @since 2.5.0
	 * @return array
	 */
	public function get_title_links(): array {

		return [
			[
				'label'   => __('Upload Template', 'ultimate-multisite'),
				'icon'    => 'upload',
				'classes' => 'wubox',
				'url'     => wu_get_form_url('upload_template'),
			],
		];
	}

	/**
	 * Every child class should implement the output method to display the contents of the page.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function output(): void {

		$addon_repo = \WP_Ultimo::get_instance()->get_addon_repository();

		$redirect_url = wu_network_admin_url('wp-ultimo-template-library');
		$code         = wu_request('code');

		if (wu_request('logout') && wp_verify_nonce(wu_request('_wpnonce'), 'logout')) {
			$addon_repo->delete_tokens();
		}

		$more_info_url = wu_get_form_url(
			'template_more_info',
			[
				'width'    => 768,
				'template' => 'TEMPLATE_SLUG',
			]
		);

		$user = $addon_repo->get_user_data();

		if (! $user && $code) {
			$addon_repo->save_access_token($code, $redirect_url);
			$user = $addon_repo->get_user_data();
		}

		wu_get_template(
			'template-library/template-library',
			[
				'screen'               => get_current_screen(),
				'page'                 => $this,
				'classes'              => '',
				'sections'             => $this->get_sections(),
				'current_section'      => $this->get_current_section(),
				'clickable_navigation' => $this->clickable_navigation,
				'more_info_url'        => $more_info_url,
				'oauth_url'            => $addon_repo->get_oauth_url(),
				'logout_url'           => wu_network_admin_url(
					'wp-ultimo-template-library',
					[
						'logout'   => 'logout',
						'_wpnonce' => wp_create_nonce('logout'),
					]
				),
				'user'                 => $user ?? false,
			]
		);
	}

	/**
	 * Returns the list of settings sections.
	 *
	 * @since 2.5.0
	 * @return array
	 */
	public function get_sections(): array {

		return [
			'all'       => [
				'title' => __('All Templates', 'ultimate-multisite'),
				'icon'  => 'dashicons-wu-grid',
			],
			'business'  => [
				'title' => __('Business', 'ultimate-multisite'),
				'icon'  => 'dashicons-wu-briefcase',
			],
			'portfolio' => [
				'title' => __('Portfolio', 'ultimate-multisite'),
				'icon'  => 'dashicons-wu-image',
			],
			'blog'      => [
				'title' => __('Blog', 'ultimate-multisite'),
				'icon'  => 'dashicons-wu-text-page',
			],
			'ecommerce' => [
				'title' => __('E-commerce', 'ultimate-multisite'),
				'icon'  => 'dashicons-wu-shop',
			],
			'agency'    => [
				'title' => __('Agency', 'ultimate-multisite'),
				'icon'  => 'dashicons-wu-rocket',
			],
			'saas'      => [
				'title' => __('SaaS', 'ultimate-multisite'),
				'icon'  => 'dashicons-wu-cloud',
			],
			'community' => [
				'title' => __('Community', 'ultimate-multisite'),
				'icon'  => 'dashicons-wu-users',
			],
		];
	}

	/**
	 * Default handler for step submission. Simply redirects to the next step.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function default_handler(): void {
		// Not used for this page.
	}
}
