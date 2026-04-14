<?php
/**
 * Multisite Setup Admin Page.
 *
 * Handles the configuration and activation of WordPress Multisite
 * when it's not already enabled.
 *
 * @package WP_Ultimo
 * @subpackage Admin_Pages
 * @since 2.0.0
 */

namespace WP_Ultimo\Admin_Pages;

// Exit if accessed directly
defined('ABSPATH') || exit;

use WP_Ultimo\Installers\Core_Installer;
use WP_Ultimo\Installers\Multisite_Network_Installer;

/**
 * Multisite Setup Admin Page.
 */
class Multisite_Setup_Admin_Page extends Wizard_Admin_Page {

	/**
	 * Holds the ID for this page, this is also used as the page slug.
	 *
	 * @var string
	 */
	protected $id = 'wp-ultimo-multisite-setup';

	/**
	 * Is this a top-level menu or a submenu?
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $type = 'menu';

	/**
	 * This page has no parent, so we need to highlight another sub-menu.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $highlight_menu_slug = false;

	/**
	 * If this number is greater than 0, a badge with the number will be displayed alongside the menu title
	 *
	 * @since 2.0.0
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
		'admin_menu' => 'manage_options',
	];

	/**
	 * Constructor method.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function __construct() {

		$this->type      = 'menu';
		$this->position  = 10_101_010;
		$this->menu_icon = self::MENU_ICON_SVG;

		parent::__construct();

		add_action('admin_enqueue_scripts', [$this, 'register_scripts']);
		/**
		 * Same route as main setup wiz, but we run  first to use different caps
		 */
		add_action('wp_ajax_wu_setup_install', [$this, 'setup_install'], 5);
	}

	/**
	 * Returns the title of the page.
	 *
	 * @since 2.0.0
	 * @return string Title of the page.
	 */
	public function get_title(): string {
		return __('Enable WordPress Multisite', 'multisite-ultimate');
	}

	/**
	 * Returns the title of menu for this page.
	 *
	 * @since 2.0.0
	 * @return string Menu label of the page.
	 */
	public function get_menu_title() {
		return __('Multisite Ultimate', 'multisite-ultimate');
	}

	/**
	 * Returns the logo for the wizard.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_logo() {
		return wu_get_asset('logo.webp', 'img');
	}

	/**
	 * Returns the sections for this Wizard.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_sections() {

		return [
			'welcome'   => [
				'title'      => __('Multisite Required', 'multisite-ultimate'),
				'next_label' => __('Get Started &rarr;', 'multisite-ultimate'),
				'back'       => false,
				'view'       => [$this, 'section_welcome'],
			],
			'configure' => [
				'title'       => __('Network Configuration', 'multisite-ultimate'),
				'description' => __('Configure your network settings. These settings determine how your sites will be structured.', 'multisite-ultimate'),
				'next_label'  => __('Continue &rarr;', 'multisite-ultimate'),
				'handler'     => [$this, 'handle_configure'],
				'fields'      => [$this, 'get_network_configuration_fields'],
				'back'        => true,
			],
			'install'   => [
				'title'        => __('Installing Network', 'multisite-ultimate'),
				'description'  => __('Setting up your WordPress Multisite network...', 'multisite-ultimate'),
				'next_label'   => Core_Installer::get_instance()->all_done() ? __('Begin Ultimate Multisite Setup &rarr;', 'ultimate-multisite') : __('Install', 'ultimate-multisite'),
				'disable_next' => true,
				'back'         => false,
				'fields'       => [
					'terms' => [
						'type' => 'note',
						'desc' => fn() => $this->render_installation_steps(Multisite_Network_Installer::get_instance()->get_steps(), false),
					],
				],
			],
			'complete'  => [
				'title'       => __('Setup Complete', 'multisite-ultimate'),
				'description' => __('WordPress Multisite setup is now complete!', 'multisite-ultimate'),
				'view'        => [$this, 'section_complete'],
				'back'        => false,
				'next'        => false,
			],
		];
	}

	/**
	 * Welcome section view.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function section_welcome(): void {

		wu_get_template('wizards/multisite-setup/welcome');

		$this->render_submit_box();
	}

	/**
	 * Returns the network configuration fields.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_network_configuration_fields() {

		$home_url    = get_option('home');
		$base_domain = wp_parse_url($home_url, PHP_URL_HOST);
		$user        = wp_get_current_user();

		return [
			'network_structure_header' => [
				'type'  => 'header',
				'title' => __('Network Structure', 'multisite-ultimate'),
				'desc'  => __('Choose how you want your network sites to be organized:', 'multisite-ultimate'),
			],
			'subdomain_install'        => [
				'type'    => 'select',
				'title'   => __('Site Structure', 'multisite-ultimate'),
				'desc'    => __('Choose between subdomains or subdirectories for your network sites.', 'multisite-ultimate'),
				'options' => [
					'1' => sprintf(
						/* translators: %s is an example subdomain URL like site1.example.com */
						__('Sub-domains — e.g. %s (Recommended)', 'multisite-ultimate'),
						'site1.' . esc_html($base_domain)
					),
					'0' => sprintf(
						/* translators: %s is an example subdirectory URL like example.com/site1 */
						__('Sub-directories — e.g. %s', 'multisite-ultimate'),
						esc_html($base_domain) . '/site1'
					),
				],
				'default' => '1',
			],
			'subdomain_recommendation' => [
				'type' => 'note',
				'desc' => '<div class="wu-bg-blue-100 wu-border wu-border-blue-500 wu-rounded-lg wu-p-4">
					<div class="wu-flex">
						<div class="wu-flex-shrink-0">
							<span class="dashicons dashicons-info wu-text-blue-500"></span>
						</div>
						<div class="wu-ml-3">
							<h4 class="wu-text-sm wu-font-medium wu-text-blue-700">' . esc_html__('Sub-domains are recommended for most businesses', 'multisite-ultimate') . '</h4>
							<p class="wu-text-sm wu-text-blue-700 wu-mt-1">' . esc_html__('Sub-domains (e.g. site1.yourdomain.com) allow custom domain mapping and look more professional. Sub-directories (e.g. yourdomain.com/site1) are simpler to set up but cannot be changed later without rebuilding your network.', 'multisite-ultimate') . '</p>
						</div>
					</div>
				</div>',
			],
			'network_details_header'   => [
				'type'  => 'header',
				'title' => __('Network Details', 'multisite-ultimate'),
			],
			'sitename'                 => [
				'type'        => 'text',
				'title'       => __('Network Title', 'multisite-ultimate'),
				'desc'        => __('This will be the title of your network.', 'multisite-ultimate'),
				'placeholder' => __('Enter network title', 'multisite-ultimate'),
				'value'       => get_option('blogname') . ' Network',
			],
			'email'                    => [
				'type'        => 'email',
				'title'       => __('Network Admin Email', 'multisite-ultimate'),
				'desc'        => __('This email address will be used for network administration.', 'multisite-ultimate'),
				'placeholder' => __('Enter admin email', 'multisite-ultimate'),
				'value'       => $user->user_email,
			],
			'backup_warning'           => [
				'type' => 'note',
				'desc' => '<div class="wu-bg-yellow-50 wu-border wu-border-yellow-200 wu-rounded-lg wu-p-4">
					<div class="wu-flex">
						<div class="wu-flex-shrink-0">
							<span class="dashicons dashicons-warning wu-text-yellow-500"></span>
						</div>
						<div class="wu-ml-3">
							<h4 class="wu-text-sm wu-font-medium wu-text-yellow-800">' . esc_html__('Before You Continue', 'multisite-ultimate') . '</h4>
							<p class="wu-text-sm wu-text-yellow-700 wu-mt-1">' . esc_html__('Please ensure you have a recent backup of your website files and database. The multisite setup process will modify your wp-config.php file and create new database tables.', 'multisite-ultimate') . '</p>
						</div>
					</div>
				</div>',
			],
		];
	}

	/**
	 * Handles the network configuration form submission.
	 *
	 * Validates inputs, stores the configuration in a transient,
	 * and redirects to the install step.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function handle_configure(): void {

		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('Permission denied.', 'multisite-ultimate'));
		}

		$subdomain_install = wu_request('subdomain_install', '0') === '1';
		$sitename          = sanitize_text_field(wu_request('sitename', ''));
		$email             = sanitize_email(wu_request('email', ''));

		$home_url = get_option('home');
		$base     = wp_parse_url(trailingslashit($home_url), PHP_URL_PATH);
		$domain   = wp_parse_url($home_url, PHP_URL_HOST);
		$port     = wp_parse_url($home_url, PHP_URL_PORT);

		if ($port) {
			$domain .= ':' . $port;
		}

		set_transient(
			Multisite_Network_Installer::CONFIG_TRANSIENT,
			[
				'subdomain_install' => $subdomain_install,
				'sitename'          => $sitename,
				'email'             => $email,
				'domain'            => $domain,
				'base'              => $base,
			],
			HOUR_IN_SECONDS
		);

		wp_safe_redirect($this->get_next_section_link());
		exit;
	}

	/**
	 * Completion section view.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function section_complete(): void {

		$result = wu_request('result', ''); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ('success' === $result || is_multisite()) :
			?>
		<div class="wu-bg-green-100 wu-border wu-border-green-300 wu-rounded-lg wu-p-4 wu-mb-6">
			<div class="wu-flex">
				<div class="wu-flex-shrink-0">
					<span class="dashicons dashicons-yes-alt wu-text-green-500"></span>
				</div>
				<div class="wu-ml-3">
					<h4 class="wu-text-sm wu-font-medium wu-text-green-800">
						<?php esc_html_e('Success!', 'multisite-ultimate'); ?>
					</h4>
					<p class="wu-text-sm wu-text-green-700 wu-mt-1">
						<?php esc_html_e('WordPress Multisite has been successfully enabled. You can now continue with the Multisite Ultimate setup.', 'multisite-ultimate'); ?>
					</p>
				</div>
			</div>
		</div>

		<div class="wu-flex wu-justify-center">
			<a href="<?php echo esc_url(wu_network_admin_url('wp-ultimo-setup')); ?>" class="button button-primary button-large">
				<?php esc_html_e('Continue to Multisite Ultimate Setup', 'multisite-ultimate'); ?>
			</a>
		</div>
			<?php
		else :
			$this->display_manual_instructions();
		endif;
	}

	/**
	 * Display manual configuration instructions.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	protected function display_manual_instructions(): void {

		$home_url          = get_option('home');
		$base_domain       = wp_parse_url($home_url, PHP_URL_HOST);
		$port              = wp_parse_url($home_url, PHP_URL_PORT);
		$subdomain_install = defined('SUBDOMAIN_INSTALL') ? SUBDOMAIN_INSTALL : true; // @phpstan-ignore phpstanWP.wpConstant.fetch

		if ($port) {
			$base_domain .= ':' . $port;
		}

		$wp_config_constants = "define( 'WP_ALLOW_MULTISITE', true );
define( 'MULTISITE', true );
define( 'SUBDOMAIN_INSTALL', " . ($subdomain_install ? 'true' : 'false') . " );
define( 'DOMAIN_CURRENT_SITE', '{$base_domain}' );
define( 'PATH_CURRENT_SITE', '/' );
define( 'SITE_ID_CURRENT_SITE', 1 );
define( 'BLOG_ID_CURRENT_SITE', 1 );";

		if ($subdomain_install) {
			$htaccess_rules = 'RewriteEngine On
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
RewriteBase /
RewriteRule ^index\.php$ - [L]

# add a trailing slash to /wp-admin
RewriteRule ^wp-admin$ wp-admin/ [R=301,L]

RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]
RewriteRule ^(wp-(content|admin|includes).*) $1 [L]
RewriteRule ^(.*\.php)$ $1 [L]
RewriteRule . index.php [L]';
		} else {
			$htaccess_rules = 'RewriteEngine On
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
RewriteBase /
RewriteRule ^index\.php$ - [L]

# add a trailing slash to /wp-admin
RewriteRule ^([_0-9a-zA-Z-]+/)?wp-admin$ $1wp-admin/ [R=301,L]

RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]
RewriteRule ^([_0-9a-zA-Z-]+/)?(wp-(content|admin|includes).*) $2 [L]
RewriteRule ^([_0-9a-zA-Z-]+/)?(.*\.php)$ $2 [L]
RewriteRule . index.php [L]';
		}

		?>
		<div class="wu-mb-6">
			<p class="wu-text-gray-600 wu-mb-4">
				<?php esc_html_e('We were unable to automatically configure your wp-config.php file. Please follow the manual instructions below to complete the multisite setup.', 'multisite-ultimate'); ?>
			</p>
		</div>

		<div class="wu-mb-6">
			<h3 class="wu-text-lg wu-font-semibold wu-text-gray-900 wu-mb-3">
				<?php esc_html_e('Step 1: Add to wp-config.php', 'multisite-ultimate'); ?>
			</h3>
			<p class="wu-text-gray-600 wu-mb-4">
				<?php
				printf(
					/* translators: %1$s is the wp-config.php filename, %2$s is the "Happy publishing" comment marker */
					esc_html__('Add the following lines to your %1$s file, just before the comment %2$s:', 'multisite-ultimate'),
					'<code>wp-config.php</code>',
					'<code>/* That\'s all, stop editing! Happy publishing. */</code>'
				);
				?>
			</p>
		<div class="wu-bg-gray-100 wu-border wu-border-gray-200 wu-rounded-lg wu-p-4 wu-mb-4">
			<pre class="wu-text-sm wu-overflow-x-auto"><code class="wu-p-0"><?php echo esc_html($wp_config_constants); ?></code></pre>
		</div>
		</div>

		<?php if (got_url_rewrite()) : ?>
		<div class="wu-mb-6">
			<h3 class="wu-text-lg wu-font-semibold wu-text-gray-900 wu-mb-3">
				<?php esc_html_e('Step 2: Add to .htaccess', 'multisite-ultimate'); ?>
			</h3>
			<p class="wu-text-gray-600 wu-mb-4">
				<?php esc_html_e('Replace the existing WordPress rules in your .htaccess file with:', 'multisite-ultimate'); ?>
			</p>
		<div class="wu-bg-gray-100 wu-border wu-border-gray-200 wu-rounded-lg wu-p-4 wu-mb-4">
			<pre class="wu-text-sm wu-overflow-x-auto"><code><?php echo esc_html($htaccess_rules); ?></code></pre>
		</div>
		</div>
		<?php endif; ?>

		<div class="wu-bg-blue-100 wu-border wu-border-blue-500 wu-rounded-lg wu-p-4 wu-mb-6">
			<div class="wu-flex">
				<div class="wu-flex-shrink-0">
					<span class="dashicons dashicons-info wu-text-blue-500"></span>
				</div>
				<div class="wu-ml-3">
					<h4 class="wu-text-sm wu-font-medium wu-text-blue-700">
						<?php esc_html_e('Next Steps', 'multisite-ultimate'); ?>
					</h4>
					<p class="wu-text-sm wu-text-blue-700 wu-mt-1">
						<?php esc_html_e('After making these changes, refresh this page. WordPress will detect that multisite is enabled and you can proceed with the Multisite Ultimate setup.', 'multisite-ultimate'); ?>
					</p>
				</div>
			</div>
		</div>

		<div class="wu-flex wu-justify-center">
			<a href="<?php echo esc_url(admin_url('admin.php?page=wp-ultimo-multisite-setup&step=complete')); ?>" class="button button-primary button-large">
				<?php esc_html_e('Refresh and Check Again', 'multisite-ultimate'); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Handles the ajax actions for installers and migrators.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function setup_install(): void {

		if ( ! current_user_can('manage_options') ) {
			wp_send_json_error(new \WP_Error('not-allowed', __('Permission denied.', 'ultimate-multisite')));

			exit;
		}

		$installer                   = wu_request('installer', '');
		$multisite_network_installer = Multisite_Network_Installer::get_instance();
		$steps                       = $multisite_network_installer->get_steps();
		if ( ! isset($steps[ $installer ])) {
			return;
		}

		$status = $multisite_network_installer->handle(true, $installer, $this);

		if (is_wp_error($status)) {
			wp_send_json_error($status);
		}

		wp_send_json_success();
	}

	/**
	 * Register page scripts and styles.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function register_scripts(): void {

		if (get_current_screen()->id !== 'toplevel_page_wp-ultimo-multisite-setup') {
			return;
		}

		wp_enqueue_script('wu-block-ui', wu_get_asset('lib/jquery.blockUI.js', 'js'), ['jquery'], \WP_Ultimo::VERSION, true);

		wp_enqueue_script('wu-setup-wizard-extra', wu_get_asset('setup-wizard-extra.js', 'js'), ['jquery'], wu_get_version(), true);

		wp_register_script('wu-setup-wizard', wu_get_asset('setup-wizard.js', 'js'), ['jquery'], wu_get_version(), true);
	}
}
