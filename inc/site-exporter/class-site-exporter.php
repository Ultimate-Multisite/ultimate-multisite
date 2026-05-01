<?php
/**
 * Site Exporter & Importer main class.
 *
 * @package WP_Ultimo\Site_Exporter
 * @since 2.5.0
 */

namespace WP_Ultimo\Site_Exporter;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Site Exporter & Importer main class
 *
 * This class handles site export and import functionality.
 *
 * @package WP_Ultimo\Site_Exporter
 * @since 2.5.0
 */
final class Site_Exporter {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * Checks if Site Exporter was loaded or not.
	 *
	 * @since 2.5.0
	 * @var bool
	 */
	protected bool $loaded = false;

	/**
	 * Loads the necessary components into the main class
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function init(): void {

		/**
		 * Loads the listeners to export events.
		 */
		$this->setup();

		$this->loaded = true;

		/**
		 * Triggers when all the dependencies were loaded.
		 *
		 * Allows plugin developers to add new functionality. For example, support to new
		 * Hosting providers, etc.
		 *
		 * @since 2.5.0
		 */
		do_action('wu_site_exporter_loaded');

		// Backwards compatibility
		do_action('wp_ultimo_site_exporter_load');
	}

	/**
	 * Adds the necessary hooks to deal with exports and imports.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function setup(): void {

		/*
		 * Register the mu-migration WP-CLI commands early so WP-CLI discovers
		 * them during the command bootstrap phase.
		 *
		 * Each mu-migration class file ends with a bare `WP_CLI::add_command()`
		 * call that executes at file-include time. Those files are only loaded
		 * via `load_dependencies()`, which is normally called lazily from
		 * `handle_site_export()` / `handle_site_import()` — far too late for
		 * WP-CLI's command discovery. Calling `load_dependencies()` here when
		 * WP-CLI is active means the commands are registered before WP-CLI
		 * finishes bootstrapping.
		 *
		 * The `require_once` calls inside `load_dependencies()` are idempotent,
		 * so calling it again later from `handle_site_export()` is harmless.
		 *
		 * This check mirrors the pattern used by the `WP_CLI` trait in
		 * `inc/apis/trait-wp-cli.php` (see `enable_wp_cli()`).
		 */
		if ( defined('WP_CLI') && WP_CLI ) {
			$this->load_dependencies();
		}

		add_action('wu_export_site', [$this, 'handle_site_export'], 10, 3);

		add_action('wu_import_site', [$this, 'handle_site_import']);

		add_filter('wu_site_exporter_files_to_zip', [$this, 'maybe_exclude_wp_ultimo_plugins']);

		add_filter('cron_schedules', [$this, 'maybe_add_schedule']);

		add_action('init', [$this, 'maybe_run_imports']);

		// Register admin forms and hooks
		add_action('wu_register_forms', [$this, 'register_forms']);

		// Add export/import action links to Sites list page
		add_filter('wu_site_list_page_action_links', [$this, 'add_site_list_action_links']);

		// Add export widget to Site edit page
		add_action('wu_edit_site_page_register_widgets', [$this, 'register_site_edit_widgets']);

		// Handle import form submission
		add_action('admin_init', [$this, 'maybe_handle_import']);

		// Add bulk export action
		add_filter('wu_site_bulk_actions', [$this, 'add_bulk_export_action']);
		add_action('wu_handle_bulk_action_form_site_export', [$this, 'handle_bulk_export'], 10, 3);

		// Authenticated file download handler (GH#1010)
		Export_Download_Handler::get_instance()->init();

		// Protect the export folder for sites upgrading from versions that lacked .htaccess protection.
		add_action('admin_init', [$this, 'maybe_protect_export_folder']);

		// WordPress default Sites page integration (works without Ultimate Multisite setup)
		$this->setup_wordpress_sites_integration();
	}

	/**
	 * Ensures the export folder has .htaccess and index.html protection files.
	 *
	 * `wu_maybe_create_folder()` adds these files when creating the folder, but
	 * existing installations may have the folder without them. This method is
	 * idempotent — it only writes the protection files when they are absent.
	 *
	 * @since 2.5.1
	 * @return void
	 */
	public function maybe_protect_export_folder(): void {

		// wu_maybe_create_folder() returns a path with a trailing slash already.
		$folder_path = wu_maybe_create_folder('wu-site-exports');

		$htaccess = $folder_path . '.htaccess';

		if (! file_exists($htaccess)) {
			$fp = @fopen($htaccess, 'w'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.PHP.NoSilencedErrors.Discouraged

			if ($fp) {
				@fwrite($fp, 'deny from all'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.PHP.NoSilencedErrors.Discouraged
				@fclose($fp); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose, WordPress.PHP.NoSilencedErrors.Discouraged
			}
		}

		$index = $folder_path . 'index.html';

		if (! file_exists($index)) {
			$fp = @fopen($index, 'w'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.PHP.NoSilencedErrors.Discouraged

			if ($fp) {
				@fwrite($fp, ''); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.PHP.NoSilencedErrors.Discouraged
				@fclose($fp); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose, WordPress.PHP.NoSilencedErrors.Discouraged
			}
		}
	}

	/**
	 * Set up integration with the default WordPress Sites page.
	 *
	 * This allows exporting sites even before Ultimate Multisite is fully set up,
	 * making migration from other solutions easier. In single-site (non-multisite)
	 * installs, a Tools > Export & Import menu page is registered instead of the
	 * network-admin Sites page integration.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	private function setup_wordpress_sites_integration(): void {

		if ( is_multisite() ) {
			// Add export action link to each site row
			add_filter('manage_sites_action_links', [$this, 'add_wp_sites_row_actions'], 10, 2);

			// Add bulk export action
			add_filter('bulk_actions-sites-network', [$this, 'add_wp_sites_bulk_actions']);

			// Handle bulk export action
			add_filter('handle_network_bulk_actions-sites-network', [$this, 'handle_wp_sites_bulk_action'], 10, 3);

			// Add admin menu page for export/import (network admin)
			add_action('network_admin_menu', [$this, 'add_wp_export_menu_page']);

			// Display admin notices (network admin)
			add_action('network_admin_notices', [$this, 'display_export_notices']);
		} else {
			// Single-site: add Tools > Export & Import menu page
			add_action('admin_menu', [$this, 'add_single_site_export_menu_page']);

			// Display admin notices in regular admin
			add_action('admin_notices', [$this, 'display_export_notices']);
		}

		// Handle direct export requests (works in both multisite and single-site)
		add_action('admin_init', [$this, 'handle_direct_export_request']);

		// Enqueue scripts for WordPress sites page
		add_action('admin_enqueue_scripts', [$this, 'enqueue_wp_sites_scripts']);
	}

	/**
	 * Add export action link to WordPress Sites page rows.
	 *
	 * In multisite, the main site is excluded from the network Sites list because
	 * it is exported through the regular admin Tools > Export & Import page.
	 * In single-site installs this filter is never called (the network Sites page
	 * does not exist), so the guard is multisite-specific.
	 *
	 * @since 2.5.0
	 *
	 * @param array $actions Existing actions.
	 * @param int   $blog_id The blog ID.
	 * @return array
	 */
	public function add_wp_sites_row_actions(array $actions, int $blog_id): array {

		// In multisite, skip the main site — it is exported from the regular admin
		// Tools > Export & Import page to avoid confusion in the network admin.
		if ( is_multisite() && is_main_site($blog_id) ) {
			return $actions;
		}

		$export_url = add_query_arg(
			[
				'page'    => 'wu-site-export',
				'site_id' => $blog_id,
				'action'  => 'export',
			],
			network_admin_url('sites.php')
		);

		$actions['export'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url($export_url),
			__('Export', 'ultimate-multisite')
		);

		return $actions;
	}

	/**
	 * Add bulk export action to WordPress Sites page.
	 *
	 * @since 2.5.0
	 *
	 * @param array $actions Existing bulk actions.
	 * @return array
	 */
	public function add_wp_sites_bulk_actions(array $actions): array {

		$actions['export'] = __('Export', 'ultimate-multisite');

		return $actions;
	}

	/**
	 * Handle bulk export action from WordPress Sites page.
	 *
	 * @since 2.5.0
	 *
	 * @param string $redirect_url The redirect URL.
	 * @param string $action       The action being performed.
	 * @param array  $blog_ids     The selected blog IDs.
	 * @return string
	 */
	public function handle_wp_sites_bulk_action(string $redirect_url, string $action, array $blog_ids): string {

		if ('export' !== $action) {
			return $redirect_url;
		}

		$exported = 0;

		foreach ($blog_ids as $blog_id) {
			// Skip main site
			if (is_main_site($blog_id)) {
				continue;
			}

			wu_exporter_export($blog_id, ['uploads' => true], true);
			++$exported;
		}

		return add_query_arg(
			[
				'page'          => 'wu-site-export',
				'bulk_exported' => $exported,
			],
			network_admin_url('sites.php')
		);
	}

	/**
	 * Get the admin base URL for the export/import page.
	 *
	 * Returns the network admin Sites URL in multisite installations and the
	 * regular admin Tools URL in single-site installations.
	 *
	 * @since 2.5.1
	 * @return string
	 */
	private function get_export_admin_base_url(): string {

		if ( is_multisite() ) {
			return network_admin_url('sites.php');
		}

		return admin_url('tools.php');
	}

	/**
	 * Add export/import menu page under Sites (multisite network admin).
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function add_wp_export_menu_page(): void {

		add_submenu_page(
			'sites.php',
			__('Export & Import Sites', 'ultimate-multisite'),
			__('Export & Import', 'ultimate-multisite'),
			'manage_network',
			'wu-site-export',
			[$this, 'render_wp_export_page']
		);
	}

	/**
	 * Add export/import menu page under Tools for single-site installs.
	 *
	 * Registers a Tools > Export & Import page when the plugin is active on a
	 * plain (non-multisite) WordPress installation so that site owners can still
	 * export their site or import an export package without network admin access.
	 *
	 * @since 2.5.1
	 * @return void
	 */
	public function add_single_site_export_menu_page(): void {

		add_management_page(
			__('Export & Import', 'ultimate-multisite'),
			__('Export & Import', 'ultimate-multisite'),
			'manage_options',
			'wu-site-export',
			[$this, 'render_single_site_export_page']
		);
	}

	/**
	 * Render the export/import page for single-site WordPress installs.
	 *
	 * Shown at Tools > Export & Import when running outside of a multisite
	 * network. Supports exporting the current site and importing a ZIP package
	 * that was produced by a previous export.
	 *
	 * @since 2.5.1
	 * @return void
	 */
	public function render_single_site_export_page(): void {

		$action = wu_request('action', '');

		$exports         = wu_exporter_get_all_exports();
		$pending_exports = function_exists('wu_exporter_get_pending') ? wu_exporter_get_pending() : [];
		$pending_imports = wu_exporter_get_pending_imports();

		?>
		<div class="wrap">
			<h1><?php esc_html_e('Export & Import', 'ultimate-multisite'); ?></h1>

			<?php if ('export' === $action) : ?>
				<?php $this->render_single_site_export_form(); ?>
			<?php else : ?>
				<?php $this->render_single_site_dashboard($exports, $pending_exports, $pending_imports); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the export form for a single-site install.
	 *
	 * Shows site information and export options for the single site, then posts
	 * back to handle_direct_export_request() via admin_init.
	 *
	 * @since 2.5.1
	 * @return void
	 */
	private function render_single_site_export_form(): void {

		$site_id  = get_current_blog_id();
		$site_url = get_bloginfo('url');
		$sitename = get_bloginfo('name');

		$export_url = wp_nonce_url(
			add_query_arg(
				[
					'page'    => 'wu-site-export',
					'action'  => 'do_export',
					'site_id' => $site_id,
				],
				admin_url('tools.php')
			),
			'wu_export_site_' . $site_id
		);

		?>
		<div class="card" style="max-width: 600px;">
			<h2><?php esc_html_e('Export This Site', 'ultimate-multisite'); ?></h2>

			<p>
				<?php
				printf(
					/* translators: 1: site name, 2: site URL */
					esc_html__('You are about to export: %1$s (%2$s)', 'ultimate-multisite'),
					'<strong>' . esc_html($sitename) . '</strong>',
					esc_html($site_url)
				);
				?>
			</p>

			<form method="post" action="<?php echo esc_url($export_url); ?>">
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e('Include Themes', 'ultimate-multisite'); ?></th>
						<td>
							<label>
								<input type="checkbox" name="include_themes" value="1">
								<?php esc_html_e('Include the active theme and parent theme', 'ultimate-multisite'); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('Include Plugins', 'ultimate-multisite'); ?></th>
						<td>
							<label>
								<input type="checkbox" name="include_plugins" value="1">
								<?php esc_html_e('Include active plugins', 'ultimate-multisite'); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('Include Uploads', 'ultimate-multisite'); ?></th>
						<td>
							<label>
								<input type="checkbox" name="include_uploads" value="1" checked>
								<?php esc_html_e('Include media files from uploads folder', 'ultimate-multisite'); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('Background Processing', 'ultimate-multisite'); ?></th>
						<td>
							<label>
								<input type="checkbox" name="background_run" value="1">
								<?php esc_html_e('Run export in background (recommended for large sites)', 'ultimate-multisite'); ?>
							</label>
						</td>
					</tr>
				</table>

				<p class="submit">
					<input type="submit" class="button button-primary" value="<?php esc_attr_e('Export Site', 'ultimate-multisite'); ?>">
					<a href="<?php echo esc_url(admin_url('tools.php?page=wu-site-export')); ?>" class="button"><?php esc_html_e('Cancel', 'ultimate-multisite'); ?></a>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the export/import dashboard for single-site installs.
	 *
	 * Displays completed exports, pending exports, pending imports and an
	 * import form. The import form for single-site installs overwrites the
	 * current site rather than creating a new one, so no "New Site URL"
	 * field is shown.
	 *
	 * @since 2.5.1
	 *
	 * @param array $exports         Completed exports.
	 * @param array $pending_exports Pending exports.
	 * @param array $pending_imports Pending imports.
	 * @return void
	 */
	private function render_single_site_dashboard(array $exports, array $pending_exports, array $pending_imports): void {

		$export_url = add_query_arg(
			[
				'page'   => 'wu-site-export',
				'action' => 'export',
			],
			admin_url('tools.php')
		);

		?>
		<div class="card" style="max-width: 800px; margin-bottom: 20px;">
			<h2><?php esc_html_e('Export This Site', 'ultimate-multisite'); ?></h2>
			<p><?php esc_html_e('Create a ZIP export of this site\'s database and media uploads. The export can be imported into any Ultimate Multisite network or used as a standalone backup.', 'ultimate-multisite'); ?></p>
			<p>
				<a href="<?php echo esc_url($export_url); ?>" class="button button-primary">
					<?php esc_html_e('Export This Site', 'ultimate-multisite'); ?>
				</a>
			</p>
		</div>

		<?php if ( ! empty($pending_exports) ) : ?>
		<div class="card" style="max-width: 800px; margin-bottom: 20px;">
			<h2><?php esc_html_e('Pending Exports', 'ultimate-multisite'); ?></h2>
			<p><?php esc_html_e('These exports are currently being processed in the background.', 'ultimate-multisite'); ?></p>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e('Site', 'ultimate-multisite'); ?></th>
						<th><?php esc_html_e('Status', 'ultimate-multisite'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($pending_exports as $pending) : ?>
					<tr>
						<td><?php echo esc_html($pending->options[0] ?? __('Unknown', 'ultimate-multisite')); ?></td>
						<td><span class="dashicons dashicons-update spin"></span> <?php esc_html_e('Processing...', 'ultimate-multisite'); ?></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php endif; ?>

		<?php if ( ! empty($pending_imports) ) : ?>
		<div class="card" style="max-width: 800px; margin-bottom: 20px;">
			<h2><?php esc_html_e('Pending Imports', 'ultimate-multisite'); ?></h2>
			<p><?php esc_html_e('These imports are queued and will be processed shortly.', 'ultimate-multisite'); ?></p>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e('File', 'ultimate-multisite'); ?></th>
						<th><?php esc_html_e('Actions', 'ultimate-multisite'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($pending_imports as $hash => $pending) : ?>
					<tr>
						<td><?php echo esc_html(basename($pending->options[0] ?? '')); ?></td>
						<td>
							<a href="<?php echo esc_url(wp_nonce_url(add_query_arg('wu-cancel-import', $hash), 'wu-cancel-import')); ?>" class="button button-small">
								<?php esc_html_e('Cancel', 'ultimate-multisite'); ?>
							</a>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php endif; ?>

		<div class="card" style="max-width: 800px; margin-bottom: 20px;">
			<h2><?php esc_html_e('Completed Exports', 'ultimate-multisite'); ?></h2>

			<?php if ( empty($exports) ) : ?>
				<p><?php esc_html_e('No exports available yet. Export this site to see it here.', 'ultimate-multisite'); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e('File', 'ultimate-multisite'); ?></th>
							<th><?php esc_html_e('Date', 'ultimate-multisite'); ?></th>
							<th><?php esc_html_e('Size', 'ultimate-multisite'); ?></th>
							<th><?php esc_html_e('Actions', 'ultimate-multisite'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($exports as $export) : ?>
						<tr>
							<td><?php echo esc_html($export['file']); ?></td>
							<td><?php echo esc_html($export['date']); ?></td>
							<td><?php echo esc_html($export['size'] ?? '-'); ?></td>
							<td>
								<a href="<?php echo esc_url($export['url']); ?>" class="button button-small" target="_blank">
									<?php esc_html_e('Download', 'ultimate-multisite'); ?>
								</a>
								<a href="
								<?php
								echo esc_url(
									wp_nonce_url(
										add_query_arg(
											[
												'page'   => 'wu-site-export',
												'action' => 'delete',
												'file'   => $export['file'],
											],
											admin_url('tools.php')
										),
										'wu_delete_export'
									)
								);
								?>
											" class="button button-small" onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this export?', 'ultimate-multisite'); ?>');">
									<?php esc_html_e('Delete', 'ultimate-multisite'); ?>
								</a>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

		<div class="card" style="max-width: 800px;">
			<h2><?php esc_html_e('Import a Site', 'ultimate-multisite'); ?></h2>
			<p><?php esc_html_e('Upload a site export ZIP file to import it into this site. This will overwrite the current site\'s database and uploads.', 'ultimate-multisite'); ?></p>

			<div class="notice notice-warning inline" style="margin: 0 0 15px 0;">
				<p><strong><?php esc_html_e('Warning:', 'ultimate-multisite'); ?></strong> <?php esc_html_e('Importing will replace the current site\'s data. Make sure you have a backup before proceeding.', 'ultimate-multisite'); ?></p>
			</div>

			<form method="post" action="
			<?php
			echo esc_url(
				wp_nonce_url(
					add_query_arg(
						[
							'page'   => 'wu-site-export',
							'action' => 'import',
						],
						admin_url('tools.php')
					),
					'wu_import_site'
				)
			);
			?>
									">
				<table class="form-table">
					<tr>
						<th scope="row"><label for="zip_url"><?php esc_html_e('ZIP File URL', 'ultimate-multisite'); ?></label></th>
						<td>
							<input type="text" name="zip_url" id="zip_url" class="regular-text" placeholder="<?php esc_attr_e('https://example.com/export.zip', 'ultimate-multisite'); ?>">
							<button type="button" class="button" id="wu-wp-upload-zip"><?php esc_html_e('Upload', 'ultimate-multisite'); ?></button>
							<p class="description"><?php esc_html_e('Enter the URL to a site export ZIP file, or upload one.', 'ultimate-multisite'); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('Options', 'ultimate-multisite'); ?></th>
						<td>
							<label>
								<input type="checkbox" name="delete_zip" value="1" checked>
								<?php esc_html_e('Delete ZIP file after import', 'ultimate-multisite'); ?>
							</label>
						</td>
					</tr>
				</table>

				<p class="submit">
					<input type="submit" class="button button-primary" value="<?php esc_attr_e('Import Site', 'ultimate-multisite'); ?>">
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the export/import page.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function render_wp_export_page(): void {

		$site_id = absint(wu_request('site_id', 0));
		$action  = wu_request('action', '');

		// Get exports and pending items
		$exports         = wu_exporter_get_all_exports();
		$pending_exports = function_exists('wu_exporter_get_pending') ? wu_exporter_get_pending() : [];
		$pending_imports = wu_exporter_get_pending_imports();

		?>
		<div class="wrap">
			<h1><?php esc_html_e('Export & Import Sites', 'ultimate-multisite'); ?></h1>

			<?php if ('export' === $action && $site_id) : ?>
				<?php $this->render_export_form($site_id); ?>
			<?php else : ?>
				<?php $this->render_export_import_dashboard($exports, $pending_exports, $pending_imports); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the export form for a specific site.
	 *
	 * @since 2.5.0
	 *
	 * @param int $site_id The site ID to export.
	 * @return void
	 */
	private function render_export_form(int $site_id): void {

		$blog_details = get_blog_details($site_id);

		if (! $blog_details) {
			wp_die(esc_html__('Site not found.', 'ultimate-multisite'));
		}

		$export_url = wp_nonce_url(
			add_query_arg(
				[
					'page'    => 'wu-site-export',
					'action'  => 'do_export',
					'site_id' => $site_id,
				],
				network_admin_url('sites.php')
			),
			'wu_export_site_' . $site_id
		);

		?>
		<div class="card" style="max-width: 600px;">
			<h2><?php esc_html_e('Export Site', 'ultimate-multisite'); ?></h2>

			<p>
				<?php
				printf(
					/* translators: %s site name */
					esc_html__('You are about to export: %s', 'ultimate-multisite'),
					'<strong>' . esc_html($blog_details->blogname) . '</strong> (' . esc_html($blog_details->siteurl) . ')'
				);
				?>
			</p>

			<form method="post" action="<?php echo esc_url($export_url); ?>">
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e('Include Themes', 'ultimate-multisite'); ?></th>
						<td>
							<label>
								<input type="checkbox" name="include_themes" value="1">
								<?php esc_html_e('Include the active theme and parent theme', 'ultimate-multisite'); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('Include Plugins', 'ultimate-multisite'); ?></th>
						<td>
							<label>
								<input type="checkbox" name="include_plugins" value="1">
								<?php esc_html_e('Include active plugins', 'ultimate-multisite'); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('Include Uploads', 'ultimate-multisite'); ?></th>
						<td>
							<label>
								<input type="checkbox" name="include_uploads" value="1" checked>
								<?php esc_html_e('Include media files from uploads folder', 'ultimate-multisite'); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('Background Processing', 'ultimate-multisite'); ?></th>
						<td>
							<label>
								<input type="checkbox" name="background_run" value="1">
								<?php esc_html_e('Run export in background (recommended for large sites)', 'ultimate-multisite'); ?>
							</label>
						</td>
					</tr>
				</table>

				<p class="submit">
					<input type="submit" class="button button-primary" value="<?php esc_attr_e('Export Site', 'ultimate-multisite'); ?>">
					<a href="<?php echo esc_url(network_admin_url('sites.php?page=wu-site-export')); ?>" class="button"><?php esc_html_e('Cancel', 'ultimate-multisite'); ?></a>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the export/import dashboard.
	 *
	 * @since 2.5.0
	 *
	 * @param array $exports         Completed exports.
	 * @param array $pending_exports Pending exports.
	 * @param array $pending_imports Pending imports.
	 * @return void
	 */
	private function render_export_import_dashboard(array $exports, array $pending_exports, array $pending_imports): void {

		?>
		<div class="card" style="max-width: 800px; margin-bottom: 20px;">
			<h2><?php esc_html_e('Export a Site', 'ultimate-multisite'); ?></h2>
			<p><?php esc_html_e('Select a site from the Sites list and click "Export" in the row actions, or use the bulk action to export multiple sites at once.', 'ultimate-multisite'); ?></p>
			<p>
				<a href="<?php echo esc_url(network_admin_url('sites.php')); ?>" class="button button-primary">
					<?php esc_html_e('Go to Sites', 'ultimate-multisite'); ?>
				</a>
			</p>
		</div>

		<?php if (! empty($pending_exports)) : ?>
		<div class="card" style="max-width: 800px; margin-bottom: 20px;">
			<h2><?php esc_html_e('Pending Exports', 'ultimate-multisite'); ?></h2>
			<p><?php esc_html_e('These exports are currently being processed in the background.', 'ultimate-multisite'); ?></p>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e('Site', 'ultimate-multisite'); ?></th>
						<th><?php esc_html_e('Status', 'ultimate-multisite'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($pending_exports as $pending) : ?>
					<tr>
						<td><?php echo esc_html($pending->options[0] ?? __('Unknown', 'ultimate-multisite')); ?></td>
						<td><span class="dashicons dashicons-update spin"></span> <?php esc_html_e('Processing...', 'ultimate-multisite'); ?></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php endif; ?>

		<?php if (! empty($pending_imports)) : ?>
		<div class="card" style="max-width: 800px; margin-bottom: 20px;">
			<h2><?php esc_html_e('Pending Imports', 'ultimate-multisite'); ?></h2>
			<p><?php esc_html_e('These imports are queued and will be processed shortly.', 'ultimate-multisite'); ?></p>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e('File', 'ultimate-multisite'); ?></th>
						<th><?php esc_html_e('Target URL', 'ultimate-multisite'); ?></th>
						<th><?php esc_html_e('Actions', 'ultimate-multisite'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($pending_imports as $hash => $pending) : ?>
					<tr>
						<td><?php echo esc_html(basename($pending->options[0] ?? '')); ?></td>
						<td><?php echo esc_html($pending->options[1]['new_url'] ?? ''); ?></td>
						<td>
							<a href="<?php echo esc_url(wp_nonce_url(add_query_arg('wu-cancel-import', $hash), 'wu-cancel-import')); ?>" class="button button-small">
								<?php esc_html_e('Cancel', 'ultimate-multisite'); ?>
							</a>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php endif; ?>

		<div class="card" style="max-width: 800px; margin-bottom: 20px;">
			<h2><?php esc_html_e('Completed Exports', 'ultimate-multisite'); ?></h2>

			<?php if (empty($exports)) : ?>
				<p><?php esc_html_e('No exports available yet. Export a site to see it here.', 'ultimate-multisite'); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e('File', 'ultimate-multisite'); ?></th>
							<th><?php esc_html_e('Date', 'ultimate-multisite'); ?></th>
							<th><?php esc_html_e('Size', 'ultimate-multisite'); ?></th>
							<th><?php esc_html_e('Actions', 'ultimate-multisite'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($exports as $export) : ?>
						<tr>
							<td><?php echo esc_html($export['file']); ?></td>
							<td><?php echo esc_html($export['date']); ?></td>
							<td><?php echo esc_html($export['size'] ?? '-'); ?></td>
							<td>
								<a href="<?php echo esc_url($export['url']); ?>" class="button button-small" target="_blank">
									<?php esc_html_e('Download', 'ultimate-multisite'); ?>
								</a>
								<a href="
								<?php
								echo esc_url(
									wp_nonce_url(
										add_query_arg(
											[
												'page'   => 'wu-site-export',
												'action' => 'delete',
												'file'   => $export['file'],
											],
											network_admin_url('sites.php')
										),
										'wu_delete_export'
									)
								);
								?>
											" class="button button-small" onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this export?', 'ultimate-multisite'); ?>');">
									<?php esc_html_e('Delete', 'ultimate-multisite'); ?>
								</a>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

		<div class="card" style="max-width: 800px;">
			<h2><?php esc_html_e('Import a Site', 'ultimate-multisite'); ?></h2>
			<p><?php esc_html_e('Upload a site export ZIP file to import it into this network.', 'ultimate-multisite'); ?></p>

			<form method="post" action="
			<?php
			echo esc_url(
				wp_nonce_url(
					add_query_arg(
						[
							'page'   => 'wu-site-export',
							'action' => 'import',
						],
						network_admin_url('sites.php')
					),
					'wu_import_site'
				)
			);
			?>
										">
				<table class="form-table">
					<tr>
						<th scope="row"><label for="zip_url"><?php esc_html_e('ZIP File URL', 'ultimate-multisite'); ?></label></th>
						<td>
							<input type="text" name="zip_url" id="zip_url" class="regular-text" placeholder="<?php esc_attr_e('https://example.com/export.zip', 'ultimate-multisite'); ?>">
							<button type="button" class="button" id="wu-wp-upload-zip"><?php esc_html_e('Upload', 'ultimate-multisite'); ?></button>
							<p class="description"><?php esc_html_e('Enter the URL to a site export ZIP file, or upload one.', 'ultimate-multisite'); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="new_url"><?php esc_html_e('New Site URL', 'ultimate-multisite'); ?></label></th>
						<td>
							<input type="text" name="new_url" id="new_url" class="regular-text" placeholder="<?php echo esc_attr(is_subdomain_install() ? 'newsite.example.com' : 'example.com/newsite'); ?>">
							<p class="description"><?php esc_html_e('The URL for the imported site.', 'ultimate-multisite'); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('Options', 'ultimate-multisite'); ?></th>
						<td>
							<label>
								<input type="checkbox" name="delete_zip" value="1" checked>
								<?php esc_html_e('Delete ZIP file after import', 'ultimate-multisite'); ?>
							</label>
						</td>
					</tr>
				</table>

				<p class="submit">
					<input type="submit" class="button button-primary" value="<?php esc_attr_e('Import Site', 'ultimate-multisite'); ?>">
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle direct export requests from the WordPress Sites page or Tools page.
	 *
	 * Works in both multisite (network admin Sites page) and single-site installs
	 * (regular admin Tools > Export & Import page). The required capability and
	 * redirect destination are determined by `get_export_admin_base_url()` and
	 * `current_user_can_export()`.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function handle_direct_export_request(): void {

		$page   = wu_request('page', '');
		$action = wu_request('action', '');

		if ('wu-site-export' !== $page) {
			return;
		}

		// Required capability differs between multisite and single-site.
		$required_cap = is_multisite() ? 'manage_network' : 'manage_options';
		$base_url     = $this->get_export_admin_base_url();

		// Handle export
		if ('do_export' === $action) {
			$site_id = absint(wu_request('site_id', 0));

			if (! $site_id || ! wp_verify_nonce(wu_request('_wpnonce'), 'wu_export_site_' . $site_id)) {
				wp_die(esc_html__('Security check failed.', 'ultimate-multisite'));
			}

			if (! current_user_can($required_cap)) {
				wp_die(esc_html__('You do not have permission to export sites.', 'ultimate-multisite'));
			}

			$options = [
				'themes'  => ! empty($_POST['include_themes']),
				'plugins' => ! empty($_POST['include_plugins']),
				'uploads' => ! empty($_POST['include_uploads']),
			];

			$background = ! empty($_POST['background_run']);

			$export_result = wu_exporter_export($site_id, $options, $background);

			if (is_wp_error($export_result)) {
				wp_safe_redirect(
					add_query_arg(
						[
							'page'    => 'wu-site-export',
							'message' => 'export_error',
						],
						$base_url
					)
				);
				exit;
			}

			$message = $background ? 'export_started' : 'export_complete';

			wp_safe_redirect(
				add_query_arg(
					[
						'page'    => 'wu-site-export',
						'message' => $message,
					],
					$base_url
				)
			);
			exit;
		}

		// Handle delete
		if ('delete' === $action) {
			$file = wu_request('file', '');

			if (! wp_verify_nonce(wu_request('_wpnonce'), 'wu_delete_export')) {
				wp_die(esc_html__('Security check failed.', 'ultimate-multisite'));
			}

			if (! current_user_can($required_cap)) {
				wp_die(esc_html__('You do not have permission to delete exports.', 'ultimate-multisite'));
			}

			// Validate file name format for security
			if (preg_match('/^wu-site-export-[0-9]+-/', $file)) {
				$path = wu_maybe_create_folder('wu-site-exports') . $file;

				if (file_exists($path)) {
					wp_delete_file($path);
				}
			}

			wp_safe_redirect(
				add_query_arg(
					[
						'page'    => 'wu-site-export',
						'message' => 'deleted',
					],
					$base_url
				)
			);
			exit;
		}

		// Handle import
		if ('import' === $action && isset($_POST['zip_url'])) {
			if (! wp_verify_nonce(wu_request('_wpnonce'), 'wu_import_site')) {
				wp_die(esc_html__('Security check failed.', 'ultimate-multisite'));
			}

			if (! current_user_can($required_cap)) {
				wp_die(esc_html__('You do not have permission to import sites.', 'ultimate-multisite'));
			}

			$zip_url    = sanitize_text_field(wp_unslash($_POST['zip_url']));
			$delete_zip = ! empty($_POST['delete_zip']);

			// In single-site installs the import overwrites the current site, so
			// new_url is not required — fall back to the current site URL.
			if ( is_multisite() ) {
				$new_url = sanitize_text_field(wp_unslash($_POST['new_url'] ?? ''));
			} else {
				$new_url = sanitize_text_field(wp_unslash($_POST['new_url'] ?? get_site_url()));
			}

			if ( empty($zip_url) || ( is_multisite() && empty($new_url) ) ) {
				wp_safe_redirect(
					add_query_arg(
						[
							'page'    => 'wu-site-export',
							'message' => 'import_error',
							'error'   => 'missing_fields',
						],
						$base_url
					)
				);
				exit;
			}

			$file_path = $this->url_to_path($zip_url);

			if (! $file_path || ! file_exists($file_path)) {
				wp_safe_redirect(
					add_query_arg(
						[
							'page'    => 'wu-site-export',
							'message' => 'import_error',
							'error'   => 'file_not_found',
						],
						$base_url
					)
				);
				exit;
			}

			wu_exporter_import(
				$file_path,
				[
					'delete_file' => $delete_zip,
					'zip_url'     => $zip_url,
					'url'         => $new_url,
					'new_url'     => $new_url,
				]
			);

			wp_safe_redirect(
				add_query_arg(
					[
						'page'    => 'wu-site-export',
						'message' => 'import_started',
					],
					$base_url
				)
			);
			exit;
		}
	}

	/**
	 * Display admin notices for export/import actions.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function display_export_notices(): void {

		$message       = wu_request('message', '');
		$bulk_exported = absint(wu_request('bulk_exported', 0));

		if ($bulk_exported > 0) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				/* translators: %d number of sites */
				esc_html(sprintf(_n('%d site export started in background.', '%d site exports started in background.', $bulk_exported, 'ultimate-multisite'), $bulk_exported))
			);
		}

		switch ($message) {
			case 'export_error':
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('The site export failed. Please check server logs for details or try again.', 'ultimate-multisite') . '</p></div>';
				break;
			case 'export_complete':
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Site exported successfully!', 'ultimate-multisite') . '</p></div>';
				break;
			case 'export_started':
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Site export started in background. Check back shortly.', 'ultimate-multisite') . '</p></div>';
				break;
			case 'deleted':
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Export deleted successfully.', 'ultimate-multisite') . '</p></div>';
				break;
			case 'import_started':
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Site import started. The site will be available shortly.', 'ultimate-multisite') . '</p></div>';
				break;
			case 'import_error':
				$error          = wu_request('error', '');
				$error_messages = [
					'missing_fields' => __('Please provide both a ZIP file URL and target URL.', 'ultimate-multisite'),
					'file_not_found' => __('The ZIP file could not be found. Make sure it is uploaded to this site.', 'ultimate-multisite'),
				];
				$error_text     = $error_messages[ $error ] ?? __('An error occurred during import.', 'ultimate-multisite');
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($error_text) . '</p></div>';
				break;
		}
	}

	/**
	 * Enqueue scripts for WordPress Sites page.
	 *
	 * @since 2.5.0
	 *
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function enqueue_wp_sites_scripts(string $hook): void {

		/*
		 * The Ultimate Multisite Sites list page (wp-ultimo-sites) hosts the
		 * "Import Site" wubox modal which contains an "Upload ZIP File" button
		 * that opens the WordPress media library (wp.media).
		 *
		 * wubox injects modal HTML via innerHTML, so inline <script> tags inside
		 * the modal response are never executed. The click handler must be
		 * registered on the parent page using event delegation so it is present
		 * before the modal opens.
		 *
		 * Hook: "wp-ultimo_page_wp-ultimo-sites" (network admin submenu under
		 * parent slug "wp-ultimo", menu slug "wp-ultimo-sites").
		 */
		if ('wp-ultimo_page_wp-ultimo-sites' === $hook) {
			wp_enqueue_media();

			wp_add_inline_script(
				'media-editor',
				"jQuery(document).on('click', '#wu-upload-zip-btn', function(e) {
					e.preventDefault();
					var frame = wp.media({
						title: '" . esc_js(__('Select or Upload ZIP File', 'ultimate-multisite')) . "',
						button: { text: '" . esc_js(__('Use this file', 'ultimate-multisite')) . "' },
						multiple: false
					});
					frame.on('select', function() {
						var attachment = frame.state().get('selection').first().toJSON();
						jQuery('#wu-import-zip-url').val(attachment.url);
					});
					frame.open();
				});"
			);

			return;
		}

		// Multisite: hook is "sites_page_wu-site-export".
		// Single-site (Tools menu): hook is "tools_page_wu-site-export".
		$allowed_hooks = ['sites_page_wu-site-export', 'tools_page_wu-site-export'];

		if (! in_array($hook, $allowed_hooks, true)) {
			return;
		}

		wp_enqueue_media();

		wp_add_inline_script(
			'media-editor',
			"
			jQuery(document).ready(function($) {
				$('#wu-wp-upload-zip').on('click', function(e) {
					e.preventDefault();
					var frame = wp.media({
						title: '" . esc_js(__('Select or Upload ZIP File', 'ultimate-multisite')) . "',
						button: { text: '" . esc_js(__('Use this file', 'ultimate-multisite')) . "' },
						multiple: false
					});
					frame.on('select', function() {
						var attachment = frame.state().get('selection').first().toJSON();
						$('#zip_url').val(attachment.url);
					});
					frame.open();
				});
			});
		"
		);

		// Add spinning animation for pending exports
		wp_add_inline_style(
			'common',
			'
			.dashicons.spin {
				animation: wu-spin 1s linear infinite;
			}
			@keyframes wu-spin {
				from { transform: rotate(0deg); }
				to { transform: rotate(360deg); }
			}
		'
		);
	}

	/**
	 * Register export/import forms.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function register_forms(): void {

		wu_register_form(
			'export_site',
			[
				'render'     => [$this, 'render_export_site_modal'],
				'handler'    => [$this, 'handle_export_site_modal'],
				'capability' => 'manage_network',
			]
		);

		wu_register_form(
			'import_site',
			[
				'render'     => [$this, 'render_import_site_modal'],
				'handler'    => [$this, 'handle_import_site_modal'],
				'capability' => 'manage_network',
			]
		);

		wu_register_form(
			'delete_export',
			[
				'render'     => [$this, 'render_delete_export_modal'],
				'handler'    => [$this, 'handle_delete_export_modal'],
				'capability' => 'manage_network',
			]
		);
	}

	/**
	 * Renders the export site modal.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function render_export_site_modal(): void {

		$site_id = wu_request('id');
		$site    = wu_get_site($site_id);

		$fields = [
			'exporting_site'  => [
				'type'        => 'model',
				'title'       => __('Site to Export', 'ultimate-multisite'),
				'placeholder' => __('Search Sites...', 'ultimate-multisite'),
				'desc'        => __('The site will be exported to a .zip file that can be imported into any Ultimate Multisite network.', 'ultimate-multisite'),
				'value'       => '',
				'html_attr'   => [
					'data-model'        => 'site',
					'data-selected'     => $site ? wp_json_encode($site->to_search_results()) : '',
					'data-value-field'  => 'blog_id',
					'data-label-field'  => 'title',
					'data-search-field' => 'title',
					'data-max-items'    => 1,
				],
			],
			'include_themes'  => [
				'type'  => 'toggle',
				'title' => __('Include Themes', 'ultimate-multisite'),
				'desc'  => __('Include the active theme and parent theme if applicable.', 'ultimate-multisite'),
				'value' => false,
			],
			'include_plugins' => [
				'type'  => 'toggle',
				'title' => __('Include Plugins', 'ultimate-multisite'),
				'desc'  => __('Include active plugins in the export.', 'ultimate-multisite'),
				'value' => false,
			],
			'include_uploads' => [
				'type'  => 'toggle',
				'title' => __('Include Uploads', 'ultimate-multisite'),
				'desc'  => __('Include media files from the uploads folder.', 'ultimate-multisite'),
				'value' => true,
			],
			'background_run'  => [
				'type'  => 'toggle',
				'title' => __('Run in Background', 'ultimate-multisite'),
				'desc'  => __('For large sites, run the export as a background process.', 'ultimate-multisite'),
				'value' => false,
			],
			'submit_button'   => [
				'type'            => 'submit',
				'title'           => __('Export Site', 'ultimate-multisite'),
				'value'           => 'save',
				'classes'         => 'button button-primary wu-w-full',
				'wrapper_classes' => 'wu-items-end wu-text-right',
			],
		];

		$form = new \WP_Ultimo\UI\Form(
			'export_site',
			$fields,
			[
				'views'                 => 'admin-pages/fields',
				'classes'               => 'wu-modal-form wu-widget-list wu-striped wu-m-0 wu-mt-0',
				'field_wrapper_classes' => 'wu-w-full wu-box-border wu-items-center wu-flex wu-justify-between wu-p-4 wu-m-0 wu-border-t wu-border-l-0 wu-border-r-0 wu-border-b-0 wu-border-gray-300 wu-border-solid',
			]
		);

		$form->render();
	}

	/**
	 * Handles the export site modal submission.
	 *
	 * On synchronous (non-background) exports, the response includes a
	 * `download_url` key so the JS can immediately trigger a ZIP download
	 * and close the modal. On background exports, a `redirect_url` is
	 * returned to navigate back to the sites list with a status message.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function handle_export_site_modal(): void {

		$site_id    = wu_request('exporting_site', '');
		$site       = wu_get_site($site_id);
		$background = (bool) wu_request('background_run');

		if (! $site) {
			wp_send_json_error(new \WP_Error('invalid-site', __('Invalid site selected.', 'ultimate-multisite')));
		}

		$export_result = wu_exporter_export(
			$site_id,
			[
				'plugins' => wu_request('include_plugins'),
				'themes'  => wu_request('include_themes'),
				'uploads' => wu_request('include_uploads'),
			],
			$background
		);

		if (is_wp_error($export_result)) {
			wp_send_json_error($export_result);
		}

		if (! $background && is_string($export_result)) {
			/*
			 * Synchronous export succeeded — the filename was returned.
			 * Send a download_url so wubox.js can close the modal and
			 * immediately trigger the ZIP download without a page redirect.
			 */
			wp_send_json_success(
				[
					'download_url' => wu_exporter_get_raw_download_url($export_result),
				]
			);
		}

		/*
		 * Background export queued. Redirect to the sites list and pass
		 * message=export_started so display_export_notices() (hooked to
		 * network_admin_notices) displays the correct success banner.
		 * Using 'updated' here would be invisible — the list view only
		 * handles 'deleted', and the export notice handler reads 'message'.
		 */
		wp_send_json_success(
			[
				'redirect_url' => wu_network_admin_url('wp-ultimo-sites', ['message' => 'export_started']),
			]
		);
	}

	/**
	 * Renders the import site modal.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function render_import_site_modal(): void {

		$this->reset_upload_limits();

		$fields = [
			'zip_file'      => [
				'type'        => 'text',
				'title'       => __('ZIP File URL', 'ultimate-multisite'),
				'placeholder' => __('https://example.com/export.zip', 'ultimate-multisite'),
				'desc'        => __('Enter the URL to the export ZIP file, or use the media uploader.', 'ultimate-multisite'),
				'html_attr'   => [
					'id' => 'wu-import-zip-url',
				],
			],
			'upload_btn'    => [
				'type'            => 'html',
				'content'         => sprintf(
					'<button type="button" class="button wu-w-full" id="wu-upload-zip-btn">%s</button>',
					__('Upload ZIP File', 'ultimate-multisite')
				),
				'wrapper_classes' => 'wu-mb-4',
			],
			'new_url'       => [
				'type'        => 'text',
				'title'       => __('New Site URL', 'ultimate-multisite'),
				'placeholder' => is_subdomain_install() ? 'newsite.example.com' : 'example.com/newsite',
				'desc'        => __('The URL for the new imported site.', 'ultimate-multisite'),
			],
			'remove_zip'    => [
				'type'  => 'toggle',
				'title' => __('Delete ZIP After Import', 'ultimate-multisite'),
				'desc'  => __('Remove the ZIP file after successful import.', 'ultimate-multisite'),
				'value' => true,
			],
			'submit_button' => [
				'type'            => 'submit',
				'title'           => __('Import Site', 'ultimate-multisite'),
				'value'           => 'save',
				'classes'         => 'button button-primary wu-w-full',
				'wrapper_classes' => 'wu-items-end wu-text-right',
			],
		];

		$form = new \WP_Ultimo\UI\Form(
			'import_site',
			$fields,
			[
				'views'                 => 'admin-pages/fields',
				'classes'               => 'wu-modal-form wu-widget-list wu-striped wu-m-0 wu-mt-0',
				'field_wrapper_classes' => 'wu-w-full wu-box-border wu-items-center wu-flex wu-justify-between wu-p-4 wu-m-0 wu-border-t wu-border-l-0 wu-border-r-0 wu-border-b-0 wu-border-gray-300 wu-border-solid',
			]
		);

		$form->render();

		/*
		 * NOTE: do NOT add a <script> tag here.
		 *
		 * wubox loads modal content via fetch() and injects it with innerHTML,
		 * which does not execute inline <script> tags. The click handler for
		 * #wu-upload-zip-btn is registered via event delegation in
		 * enqueue_wp_sites_scripts() on the parent page instead.
		 */
	}

	/**
	 * Handles the import site modal submission.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function handle_import_site_modal(): void {

		$zip_url = wu_request('zip_file', '');
		$new_url = wu_request('new_url', '');

		if (empty($zip_url)) {
			wp_send_json_error(new \WP_Error('no-file', __('Please provide a ZIP file URL.', 'ultimate-multisite')));
		}

		if (empty($new_url)) {
			wp_send_json_error(new \WP_Error('no-url', __('Please provide a URL for the new site.', 'ultimate-multisite')));
		}

		$file_path = $this->url_to_path($zip_url);

		if (! $file_path || ! file_exists($file_path)) {
			wp_send_json_error(new \WP_Error('file-not-found', __('ZIP file not found.', 'ultimate-multisite')));
		}

		$result = wu_exporter_import(
			$file_path,
			[
				'delete_file' => wu_request('remove_zip'),
				'zip_url'     => $zip_url,
				'url'         => $new_url,
				'new_url'     => $new_url,
			]
		);

		if (is_wp_error($result)) {
			wp_send_json_error($result);
		}

		wp_send_json_success(
			[
				'redirect_url' => wu_network_admin_url('wp-ultimo-sites', ['message' => 'import_started']),
			]
		);
	}

	/**
	 * Renders the delete export modal.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function render_delete_export_modal(): void {

		$export_name = wu_request('file_name');

		$fields = [
			'confirm'       => [
				'type'      => 'toggle',
				'title'     => __('Confirm Deletion', 'ultimate-multisite'),
				'desc'      => __('This action cannot be undone.', 'ultimate-multisite'),
				'value'     => false,
				'html_attr' => [
					'v-model' => 'confirm',
				],
			],
			'file_name'     => [
				'type'  => 'hidden',
				'value' => $export_name,
			],
			'submit_button' => [
				'type'            => 'submit',
				'title'           => __('Delete Export', 'ultimate-multisite'),
				'value'           => 'save',
				'classes'         => 'button button-primary wu-w-full',
				'wrapper_classes' => 'wu-items-end wu-text-right',
				'html_attr'       => [
					'v-bind:disabled' => '!confirm',
				],
			],
		];

		$form = new \WP_Ultimo\UI\Form(
			'delete_export',
			$fields,
			[
				'views'                 => 'admin-pages/fields',
				'classes'               => 'wu-modal-form wu-widget-list wu-striped wu-m-0 wu-mt-0',
				'field_wrapper_classes' => 'wu-w-full wu-box-border wu-items-center wu-flex wu-justify-between wu-p-4 wu-m-0 wu-border-t wu-border-l-0 wu-border-r-0 wu-border-b-0 wu-border-gray-300 wu-border-solid',
				'html_attr'             => [
					'data-wu-app' => 'delete_export',
					'data-state'  => wu_convert_to_state(['confirm' => false]),
				],
			]
		);

		$form->render();
	}

	/**
	 * Handles the delete export modal submission.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function handle_delete_export_modal(): void {

		$export_name = wu_request('file_name');

		if (empty($export_name)) {
			wp_send_json_error(new \WP_Error('invalid-export', __('Invalid export file.', 'ultimate-multisite')));
		}

		// Validate file name format for security
		if (! preg_match('/^wu-site-export-[0-9]+-/', $export_name)) {
			wp_send_json_error(new \WP_Error('invalid-export', __('Invalid export file name.', 'ultimate-multisite')));
		}

		$path = wu_maybe_create_folder('wu-site-exports') . $export_name;

		if (! file_exists($path)) {
			wp_send_json_error(new \WP_Error('not-found', __('Export file not found.', 'ultimate-multisite')));
		}

		$success = wp_delete_file($path);

		wp_send_json_success(
			[
				'redirect_url' => wu_network_admin_url('wp-ultimo-sites', ['deleted' => 1]),
			]
		);
	}

	/**
	 * Add action links to Sites list page.
	 *
	 * @since 2.5.0
	 *
	 * @param array $links Existing action links.
	 * @return array
	 */
	public function add_site_list_action_links(array $links): array {

		$links[] = [
			'label'   => __('Export Site', 'ultimate-multisite'),
			'icon'    => 'wu-export',
			'classes' => 'wubox',
			'url'     => wu_get_form_url('export_site'),
		];

		$links[] = [
			'label'   => __('Import Site', 'ultimate-multisite'),
			'icon'    => 'wu-import',
			'classes' => 'wubox',
			'url'     => wu_get_form_url('import_site'),
		];

		return $links;
	}

	/**
	 * Register export widget on Site edit page.
	 *
	 * @since 2.5.0
	 *
	 * @param \WP_Ultimo\Admin_Pages\Site_Edit_Admin_Page $page The edit page instance.
	 * @return void
	 */
	public function register_site_edit_widgets($page): void {

		$site = $page->get_object();

		if (! $site) {
			return;
		}

		$exports      = wu_exporter_get_all_exports();
		$site_exports = array_filter(
			$exports,
			function ($export) use ($site) {
				return strpos($export['file'], 'wu-site-export-' . $site->get_id() . '-') !== false;
			}
		);

		$export_url = wu_get_form_url('export_site', ['id' => $site->get_id()]);

		$page->add_fields_widget(
			'site_export',
			[
				'title'    => __('Site Export', 'ultimate-multisite'),
				'position' => 'side',
				'fields'   => [
					'export_button' => [
						'type'            => 'html',
						'wrapper_classes' => 'wu-bg-gray-100',
						'content'         => sprintf(
							'<a href="%s" class="wubox button button-primary wu-w-full wu-text-center">%s</a>',
							esc_url($export_url),
							__('Export This Site', 'ultimate-multisite')
						),
					],
					'export_list'   => [
						'type'    => 'html',
						'content' => $this->render_site_exports_list($site_exports, $site),
					],
				],
			]
		);
	}

	/**
	 * Render the list of exports for a site.
	 *
	 * @since 2.5.0
	 *
	 * @param array                  $exports The exports list.
	 * @param \WP_Ultimo\Models\Site $site    The site object (reserved for future use).
	 * @return string
	 */
	private function render_site_exports_list(array $exports, $site): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed

		if (empty($exports)) {
			return sprintf(
				'<p class="wu-text-gray-600 wu-text-sm wu-m-0 wu-mt-4">%s</p>',
				__('No exports available for this site.', 'ultimate-multisite')
			);
		}

		$html = '<div class="wu-mt-4"><strong class="wu-text-sm">' . __('Previous Exports:', 'ultimate-multisite') . '</strong><ul class="wu-m-0 wu-mt-2">';

		foreach (array_slice($exports, 0, 5) as $export) {
			$delete_url = wu_get_form_url('delete_export', ['file_name' => $export['file']]);

			$html .= sprintf(
				'<li class="wu-flex wu-justify-between wu-items-center wu-py-1 wu-text-sm">
					<a href="%s" target="_blank" class="wu-no-underline">%s</a>
					<a href="%s" class="wubox wu-text-red-600 wu-no-underline wu-text-xs">%s</a>
				</li>',
				esc_url($export['url']),
				esc_html($export['date']),
				esc_url($delete_url),
				__('Delete', 'ultimate-multisite')
			);
		}

		$html .= '</ul></div>';

		return $html;
	}

	/**
	 * Add bulk export action.
	 *
	 * @since 2.5.0
	 *
	 * @param array $actions Existing bulk actions.
	 * @return array
	 */
	public function add_bulk_export_action(array $actions): array {

		$actions['export'] = __('Export Sites', 'ultimate-multisite');

		return $actions;
	}

	/**
	 * Handle bulk export action.
	 *
	 * @since 2.5.0
	 *
	 * @param string $action The action name.
	 * @param string $model  The model name.
	 * @param array  $ids    The selected IDs.
	 * @return void
	 */
	public function handle_bulk_export($action, $model, $ids): void {

		$item_ids = array_filter($ids);

		foreach ($item_ids as $item_id) {
			wu_exporter_export($item_id, ['uploads' => true], true);
		}
	}

	/**
	 * Reset upload limits for importing.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function reset_upload_limits(): void {

		@ini_set('upload_max_size', '2048M'); // phpcs:ignore
		@ini_set('post_max_size', '2064M');   // phpcs:ignore
		@ini_set('max_execution_time', '0');  // phpcs:ignore

		if (is_main_site()) {
			add_filter(
				'upload_mimes',
				function ($mimes) {
					$mimes['zip'] = 'application/zip';
					$mimes['gz']  = 'application/x-gzip';
					return $mimes;
				},
				999
			);

			if (! defined('ALLOW_UNFILTERED_UPLOADS')) {
				define('ALLOW_UNFILTERED_UPLOADS', true);
			}

			add_filter('get_space_allowed', fn() => 999999);
		}
	}

	/**
	 * Handle import form submission (non-AJAX).
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function maybe_handle_import(): void {

		if (! wu_request('wu-cancel-import')) {
			return;
		}

		check_admin_referer('wu-cancel-import');

		$hash = wu_request('wu-cancel-import');

		wu_exporter_delete_transient("wu_pending_site_import_{$hash}");

		wp_safe_redirect(
			add_query_arg(
				'error',
				__('Import cancelled.', 'ultimate-multisite'),
				remove_query_arg(['updated', 'wu-cancel-import', '_wpnonce'])
			)
		);

		exit;
	}

	/**
	 * Convert URL to local file path.
	 *
	 * Normalises URL schemes before comparison so that HTTP/HTTPS mismatches
	 * (common when the media library returns HTTP while the site runs HTTPS, or
	 * vice-versa) do not prevent the conversion from succeeding.
	 *
	 * Falls back to wu_exporter_url_to_path() which uses site_url() for a
	 * broader scheme-normalised replacement and, as a last resort, queries the
	 * WP media library via attachment_url_to_postid().
	 *
	 * @since 2.5.0
	 *
	 * @param string $url The URL to convert.
	 * @return string|false The local filesystem path, or false on failure.
	 */
	private function url_to_path(string $url) {

		$upload_dir = wp_upload_dir();

		// Normalise both sides to HTTPS to survive HTTP/HTTPS mismatches.
		$base_url   = set_url_scheme($upload_dir['baseurl'], 'https');
		$base_dir   = $upload_dir['basedir'];
		$normalized = set_url_scheme($url, 'https');

		if (strpos($normalized, $base_url) === 0) {
			return str_replace($base_url, $base_dir, $normalized);
		}

		/*
		 * Fall back to the site-wide helper which normalises via site_url() and
		 * queries the media library as a last resort.
		 */
		$path = wu_exporter_url_to_path($url);

		return ! empty($path) ? $path : false;
	}

	/**
	 * Maybe exclude WP Ultimo and other plugins from the generated zip.
	 *
	 * @since 2.5.0
	 *
	 * @param array $files_to_zip The files to be zipped.
	 * @return array
	 */
	public function maybe_exclude_wp_ultimo_plugins(array $files_to_zip): array {

		if (isset($files_to_zip['wp-content/plugins'])) {
			$plugins_folder = $files_to_zip['wp-content/plugins'];

			/**
			 * Allows developers to manage a plugin list that maybe exclude from the generated zip.
			 *
			 * @since 2.5.0
			 *
			 * @param array $plugin_list The plugins that will be excluded.
			 * @return array             The plugin list.
			 */
			$not_name = apply_filters(
				'wu_site_exporter_plugin_exclusion_list',
				[
					'wp-ultimo*',
					'ultimate-multisite*',
				]
			);

			// Find all plugin directories/files at depth 0, excluding WP Ultimo plugins
			$all_entries = scandir($plugins_folder);

			foreach ($all_entries as $entry) {
				if ('.' === $entry || '..' === $entry) {
					continue;
				}

				// Check if this entry matches any exclusion pattern
				$excluded = false;

				foreach ($not_name as $pattern) {
					// Convert glob pattern to regex
					$regex = '/^' . str_replace(
						['*', '?'],
						['.*', '.'],
						preg_quote($pattern, '/')
					) . '$/i';

					if (preg_match($regex, $entry)) {
						$excluded = true;
						break;
					}
				}

				if (! $excluded) {
					$full_path                                        = trailingslashit($plugins_folder) . $entry;
					$files_to_zip[ 'wp-content/plugins/' . $entry ] = $full_path;
				}
			}

			unset($files_to_zip['wp-content/plugins']);
		}

		return $files_to_zip;
	}

	/**
	 * Maybe adds the the hook to the cron.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function maybe_run_imports(): void {

		if (! wp_next_scheduled('wu_import_site')) {
			wp_schedule_event(time() + 10, 'wu_site_every_minute', 'wu_import_site');
		}
	}

	/**
	 * Adds the custom cron schedule interval.
	 *
	 * Always registers the wu_site_every_minute interval so that
	 * wp_schedule_event() can succeed regardless of whether there are
	 * pending imports at the time the cron_schedules filter runs.
	 * Previously this method returned early when no imports were pending,
	 * creating a circular dependency: the schedule was only registered when
	 * imports existed, but the event could not be scheduled until the
	 * interval was registered — meaning the very first import might never
	 * be processed.
	 *
	 * @since 2.5.0
	 *
	 * @param array $schedules The list of available schedules.
	 * @return array
	 */
	public function maybe_add_schedule(array $schedules): array {

		$schedules['wu_site_every_minute'] = [
			'interval' => 60,
			'display'  => esc_html__('Every 60 Seconds', 'ultimate-multisite'),
		];

		return $schedules;
	}

	/**
	 * Handles a site export generation.
	 *
	 * @since 2.5.0
	 *
	 * @param int    $site_id The ID of the site being exported.
	 * @param array  $options Export generation options.
	 * @param string $hash The hash generated.
	 * @return string|\WP_Error The export filename on success, WP_Error on failure.
	 */
	public function handle_site_export(int $site_id, array $options = [], string $hash = '') {

		$this->load_dependencies();

		$export_name = sprintf('wu-site-export-%s-%s-%s.zip', $site_id, gmdate('Y-m-d'), time());

		$command = new \TenUp\MU_Migration\Commands\ExportCommand();

		$base_path = wu_maybe_create_folder('wu-site-exports');

		$args = [
			'blog_id' => $site_id,
		];

		if (wu_get_isset($options, 'plugins')) {
			$args['plugins'] = 1;
		}

		if (wu_get_isset($options, 'themes')) {
			$args['themes'] = 1;
		}

		if (wu_get_isset($options, 'uploads')) {
			$args['uploads'] = 1;
		}

		$start = microtime(true);

		try {
			$command->all([$base_path . $export_name], $args);
		} catch (\Exception $e) {
			// Log the exception for server admins and return a user-friendly error.
			error_log('WP Ultimo site export error: ' . $e->getMessage());

			return new \WP_Error(
				'export-failed',
				__('The site export failed due to a server error. Please check server logs for details.', 'ultimate-multisite')
			);
		}

		if (! file_exists($base_path . $export_name)) {
			return new \WP_Error(
				'export-failed',
				__('The export file could not be created. Please check server permissions and available disk space, then try again.', 'ultimate-multisite')
			);
		}

		$time = microtime(true) - $start;

		wu_exporter_save_generation_time($export_name, $time);

		if ( ! empty($hash)) {
			wu_exporter_delete_transient("wu_pending_site_export_{$hash}");
		}

		return $export_name;
	}

	/**
	 * Handles the site import.
	 *
	 * @since 2.5.0
	 * @return bool
	 */
	public function handle_site_import(): bool {

		$pending_imports = wu_exporter_get_pending_imports();

		if (empty($pending_imports)) {
			return false;
		}

		$file_name = '';
		$options   = [];
		$hash      = '';

		foreach ($pending_imports as $pi) {
			if (! isset($pi->options[1]['running'])) {
				$file_name = $pi->options[0];
				$options   = $pi->options[1];
				$hash      = $pi->options[2];

				break;
			}
		}

		if (empty($file_name)) {
			return false;
		}

		$options['running'] = false;

		$base = [
			$file_name,
			$options,
			$hash,
		];

		wu_exporter_set_transient("wu_pending_site_import_{$hash}", $base, 2 * HOUR_IN_SECONDS);

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/theme.php';

		$this->load_dependencies();

		$command = new \TenUp\MU_Migration\Commands\ImportCommand();

		$defaults = [
			'url'                      => '',
			'new_url'                  => '',
			'zip_url'                  => '',
			'delete_zip'               => true,
			'mysql-single-transaction' => true,
		];

		$args = wp_parse_args($options, $defaults);

		$start = microtime(true);

		$command->all([$file_name], $args);

		$time = microtime(true) - $start;

		wu_exporter_save_import_time($file_name, $time);

		wu_exporter_delete_transient("wu_pending_site_import_{$hash}");

		$delete_file = !empty($options['delete_file']);

		if ($delete_file) {
			$attachment_id = attachment_url_to_postid($options['zip_url']);

			wp_delete_attachment($attachment_id, true);
		}

		return true;
	}

	/**
	 * Load the commands from mu-migration.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function load_dependencies(): void {

		$base_path = wu_path('inc/site-exporter/mu-migration');

		require_once $base_path . '/includes/helpers.php';
		require_once $base_path . '/includes/commands/class-mu-migration.php';
		require_once $base_path . '/includes/commands/class-mu-migration-base.php';
		require_once $base_path . '/includes/commands/class-mu-migration-export.php';
		require_once $base_path . '/includes/commands/class-mu-migration-import.php';
		require_once $base_path . '/includes/commands/class-mu-migration-posts.php';
		require_once $base_path . '/includes/commands/class-mu-migration-users.php';

		if (file_exists($base_path . '/vendor/autoload.php')) {
			require_once $base_path . '/vendor/autoload.php';
		}
	}

	/**
	 * Returns true if all the requirements are met.
	 *
	 * @since 2.5.0
	 * @return bool
	 */
	public function is_loaded(): bool {

		return $this->loaded;
	}
}
