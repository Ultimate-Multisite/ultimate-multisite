<?php
/**
 * Orphaned Tables Manager.
 *
 * @package WP_Ultimo
 * @subpackage Managers
 * @since 2.0.0
 */

namespace WP_Ultimo;

use WP_Ultimo\UI\Form;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Manages orphaned database tables cleanup.
 *
 * @since 2.0.0
 */
class Orphaned_Tables_Manager {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * Sets up the listeners.
	 *
	 * @since 2.0.0
	 */
	public function init(): void {

		global $wp_version;

		// Only run if WordPress version is 6.2 or greater
		if (version_compare($wp_version, '6.2', '<')) {
			return;
		}

		add_action('plugins_loaded', [$this, 'register_forms']);
		add_action('wu_settings_other', [$this, 'register_settings_field']);
	}

	/**
	 * Register ajax forms for orphaned tables management.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function register_forms(): void {

		wu_register_form(
			'orphaned_tables_delete',
			[
				'render'     => [$this, 'render_orphaned_tables_delete_modal'],
				'handler'    => [$this, 'handle_orphaned_tables_delete_modal'],
				'capability' => 'manage_network',
			]
		);
	}

	/**
	 * Registers the cleanup orphaned tables settings field.
	 *
	 * Adds a settings field to the other settings tab that allows administrators
	 * to scan and cleanup database tables from deleted sites.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function register_settings_field(): void {
		wu_register_settings_field(
			'other',
			'cleanup_orphaned_tables',
			[
				'title'             => __('Cleanup Orphaned Database Tables', 'ultimate-multisite'),
				'desc'              => __('Remove database tables from deleted sites that were not properly cleaned up.', 'ultimate-multisite'),
				'type'              => 'link',
				'display_value'     => __('Check for Orphaned Tables', 'ultimate-multisite'),
				'classes'           => 'button button-secondary wu-ml-0 wubox',
				'wrapper_html_attr' => [
					'style' => 'margin-bottom: 20px;',
				],
				'html_attr'         => [
					'href'       => wu_get_form_url('orphaned_tables_delete'),
					'wu-tooltip' => __('Scan and cleanup database tables from deleted sites', 'ultimate-multisite'),
				],
			]
		);
	}

	/**
	 * Renders the orphaned tables deletion confirmation modal.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function render_orphaned_tables_delete_modal(): void {

		$orphaned_tables = $this->find_orphaned_tables();

		$table_count = count($orphaned_tables);
		if (! $table_count) {
			printf(
				'<div class="wu-p-4 wu-bg-red-100 wu-border wu-border-red-400 wu-text-red-700 wu-rounded">
							<h3 class="wu-mt-0 wu-mb-2">%s</h3>
							<p>%s</p>
						</div>',
				esc_html__('Not Found', 'ultimate-multisite'),
				esc_html__('No Orphaned Tables found.', 'ultimate-multisite')
			);
			return;
		}

		$fields = [
			'confirmation' => [
				'type'            => 'note',
				'desc'            => function () use ($orphaned_tables, $table_count) {
					printf(
						'<div class="wu-p-4 wu-bg-red-100 wu-border wu-border-red-400 wu-text-red-700 wu-rounded">
						<h3 class="wu-mt-0 wu-mb-2">%s</h3>
						<p class="wu-mb-2">%s</p>',
						sprintf(
						/* translators: %d: number of orphaned tables */
							esc_html(_n('Confirm Deletion of %d Orphaned Table', 'Confirm Deletion of %d Orphaned Tables', $table_count, 'ultimate-multisite')),
							esc_html($table_count)
						),
						esc_html__('You are about to permanently delete the following database tables:', 'ultimate-multisite'),
					);

					echo '<div class="wu-max-h-32 wu-overflow-y-auto wu-bg-white wu-p-2 wu-border wu-rounded wu-mb-4">';
					foreach ($orphaned_tables as $table) {
						echo '<div class="wu-text-xs wu-font-mono wu-py-1">' . esc_html($table) . '</div>';
					}
					echo '</div>';
					printf(
						'<p class="wu-text-sm wu-mb-4">
							<strong>%s</strong> %s
						</p>',
						esc_html__('Warning:', 'ultimate-multisite'),
						esc_html__('This action cannot be undone. Please ensure you have a database backup before proceeding.', 'ultimate-multisite')
					);
					echo '</div>';
				},
				'wrapper_classes' => 'wu-w-full',
			],
			'submit'       => [
				'type'            => 'submit',
				'title'           => __('Yes, Delete These Tables', 'ultimate-multisite'),
				'value'           => 'delete',
				'classes'         => 'button button-primary',
				'wrapper_classes' => 'wu-items-end',
			],
		];

		$form = new Form(
			'orphaned-tables-delete',
			$fields,
			[
				'views'                 => 'admin-pages/fields',
				'classes'               => 'wu-modal-form wu-widget-list wu-striped wu-m-0',
				'field_wrapper_classes' => 'wu-w-full wu-box-border wu-items-center wu-flex wu-justify-between wu-p-4 wu-m-0 wu-border-t wu-border-l-0 wu-border-r-0 wu-border-b-0 wu-border-gray-300 wu-border-solid',
				'html_attr'             => [
					'data-wu-app' => 'orphaned_tables_delete',
					'data-state'  => wp_json_encode(
						[
							'orphaned_tables' => $orphaned_tables,
							'table_count'     => $table_count,
						]
					),
				],
			]
		);

		$form->render();
	}

	/**
	 * Handles the orphaned tables deletion.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function handle_orphaned_tables_delete_modal(): void {

		if (! current_user_can('manage_network')) {
			wp_die(esc_html__('You do not have the required permissions.', 'ultimate-multisite'));
		}

		$orphaned_tables = $this->find_orphaned_tables();

		$deleted_count = $this->delete_orphaned_tables($orphaned_tables);

		$redirect_to = wu_network_admin_url(
			'wp-ultimo-settings',
			[
				'tab'     => 'other',
				'deleted' => $deleted_count,
				'type'    => 'tables',
			]
		);

		wp_send_json_success(
			[
				'redirect_url' => $redirect_to,
				'message'      => sprintf(
					/* translators: %d: number of deleted tables */
					_n('Successfully deleted %d orphaned table.', 'Successfully deleted %d orphaned tables.', $deleted_count, 'ultimate-multisite'),
					$deleted_count
				),
			]
		);
	}

	/**
	 * Find orphaned database tables.
	 *
	 * @since 2.0.0
	 * @return array List of orphaned table names
	 */
	public function find_orphaned_tables(): array {

		global $wpdb;

		$orphaned_tables   = [];
		$existing_site_ids = $this->get_existing_site_ids();

		if (is_wp_error($existing_site_ids)) {
			return [];
		}

		$site_ids = array_fill_keys($existing_site_ids, true);

		// Get all tables from the database
		$all_tables = $wpdb->get_col('SHOW TABLES'); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		foreach ($all_tables as $table) {
			$site_id = $this->get_table_site_id($table);

			if (0 === $site_id || 1 === $site_id) {
				continue;
			}

			// Check if site ID exists in wp_blogs, across every network.
			if (! isset($site_ids[ $site_id ])) {
				$orphaned_tables[] = $table;
			}
		}

		return $orphaned_tables;
	}

	/**
	 * Get blog IDs that still have a wp_blogs row.
	 *
	 * The cleanup process must not rely on get_sites() here because site queries
	 * can be filtered or scoped by multi-network context. A table is orphaned only
	 * when its blog ID no longer has a row in wp_blogs.
	 *
	 * @since 2.0.0
	 * @return array<int>|\WP_Error
	 */
	private function get_existing_site_ids() {

		global $wpdb;

		$site_ids = $wpdb->get_col("SELECT blog_id FROM {$wpdb->blogs}"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		if (! is_array($site_ids) || '' !== $wpdb->last_error) {
			return new \WP_Error(
				'wu_orphaned_tables_site_lookup_failed',
				__('Unable to read site IDs from wp_blogs.', 'ultimate-multisite')
			);
		}

		return array_map('intval', $site_ids);
	}

	/**
	 * Extract the site ID from a multisite table name.
	 *
	 * @since 2.0.0
	 * @param string $table Table name.
	 * @return int Site ID, or 0 when the table is not a per-site multisite table.
	 */
	private function get_table_site_id(string $table): int {

		global $wpdb;

		$base_prefix = $wpdb->base_prefix ?: $wpdb->prefix;
		$pattern     = '/^' . preg_quote($base_prefix, '/') . '([0-9]+)_(.+)/';

		if (! preg_match($pattern, $table, $matches)) {
			return 0;
		}

		return (int) $matches[1];
	}

	/**
	 * Delete orphaned tables.
	 *
	 * @since 2.0.0
	 * @param array $tables List of table names to delete.
	 * @return int Number of successfully deleted tables
	 */
	public function delete_orphaned_tables(array $tables): int {

		global $wpdb;

		$deleted_count     = 0;
		$existing_site_ids = $this->get_existing_site_ids();

		if (is_wp_error($existing_site_ids)) {
			return 0;
		}

		$site_ids = array_fill_keys($existing_site_ids, true);

		foreach ($tables as $table) {
			// Sanitize table name to prevent SQL injection
			$table = sanitize_key($table);

			$site_id = $this->get_table_site_id($table);

			// Verify the table still matches our pattern and no wp_blogs row exists.
			if (0 === $site_id || 1 === $site_id || isset($site_ids[ $site_id ])) {
				continue;
			}

			// Use DROP TABLE IF EXISTS for safety
			$result = $wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $table)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders.UnsupportedIdentifierPlaceholder

			if (false !== $result) {
				++$deleted_count;
			}
		}

		return $deleted_count;
	}
}
