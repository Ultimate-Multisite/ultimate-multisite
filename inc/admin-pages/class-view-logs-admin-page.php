<?php
/**
 * Ultimate Multisite System Info Admin Page.
 *
 * @package WP_Ultimo
 * @subpackage Admin_Pages
 * @since 2.0.0
 */

namespace WP_Ultimo\Admin_Pages;

use WP_Ultimo\Logger;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Ultimate Multisite System Info Admin Page.
 */
class View_Logs_Admin_Page extends Edit_Admin_Page {

	/**
	 * Holds the ID for this page, this is also used as the page slug.
	 *
	 * @var string
	 */
	protected $id = 'wp-ultimo-view-logs';

	/**
	 * Is this a top-level menu or a submenu?
	 *
	 * @since 1.8.2
	 * @var string
	 */
	protected $type = 'submenu';

	/**
	 * If this is a submenu, we need a parent menu to attach this to
	 *
	 * @since 1.8.2
	 * @var string
	 */
	protected $parent = 'none';

	/**
	 * Allows us to highlight another menu page, if this page has no parent page at all.
	 *
	 * @since 2.0.0
	 * @var boolean
	 */
	protected $highlight_menu_slug = 'wp-ultimo-events';

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
	 * Allow child classes to add further initializations.
	 *
	 * @since 1.8.2
	 * @return void
	 */
	public function init(): void {

		add_action('wp_ajax_wu_handle_view_logs', [$this, 'ajax_handle_view_logs']);
	}

	/**
	 * Handles the AJAX request for viewing logs.
	 *
	 * @since 2.5.2
	 * @return void
	 */
	public function ajax_handle_view_logs(): void {

		$this->handle_view_logs();
	}

	/**
	 * Registers extra scripts needed for this page.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function register_scripts(): void {

		parent::register_scripts();

		\WP_Ultimo\Scripts::get_instance()->register_script('wu-view-log', wu_get_asset('view-logs.js', 'js'), ['jquery']);

		wp_localize_script(
			'wu-view-log',
			'wu_view_logs',
			[
				'i18n' => [
					'copied' => __('Copied!', 'ultimate-multisite'),
				],
			]
		);

		wp_enqueue_script('wu-view-log');

		wp_enqueue_script('clipboard');
	}

	/**
	 * Returns the title of the page.
	 *
	 * @since 2.0.0
	 * @return string Title of the page.
	 */
	public function get_title() {

		return __('View Log', 'ultimate-multisite');
	}

	/**
	 * Returns the title of menu for this page.
	 *
	 * @since 2.0.0
	 * @return string Menu label of the page.
	 */
	public function get_menu_title() {

		return __('View Log', 'ultimate-multisite');
	}

	/**
	 * Handles the actions for the logs and system info.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	public function handle_view_logs() {

		if ( ! current_user_can('manage_network')) {
			wp_die(esc_html__('You do not have permission to access this resource.', 'ultimate-multisite'), 403);
		}

		$logs_folder = Logger::get_logs_folder();

		$logs_list = list_files(
			$logs_folder,
			2,
			[
				'index.html',
			]
		);

		$logs_list = array_combine(array_values($logs_list), array_map(fn($file) => str_replace($logs_folder, '', (string) $file), $logs_list));

		if (empty($logs_list)) {
			$logs_list[''] = __('No log files found', 'ultimate-multisite');
		}

		$file = wu_request('file');

		$file_name = '';

		$contents = '';

		/*
		 * Security check: confine the requested file to the logs folder.
		 *
		 * realpath() resolves any '..' traversal so a crafted path cannot
		 * escape the logs directory (the previous substring check accepted
		 * any path that merely *contained* the logs folder, e.g.
		 * "<logs>/../../../wp-config.php"). The resolved path must also be a
		 * real file located under the resolved logs folder.
		 */
		if ($file) {
			$real_file   = realpath((string) $file);
			$real_folder = realpath($logs_folder);

			if (false === $real_folder) {
				wp_die(esc_html__('You can only view Ultimate Multisite log files.', 'ultimate-multisite'), 403);
			}

			if (false === $real_file) {
				$real_file = trailingslashit(realpath(dirname((string) $file)) ?: '') . basename((string) $file);
			}

			if ( ! str_starts_with($real_file, trailingslashit($real_folder))) {
				wp_die(esc_html__('You can only view Ultimate Multisite log files.', 'ultimate-multisite'), 403);
			}

			$file = $real_file;
		}

		if ( ! $file && ! empty($logs_list)) {
			$file = ! $file && ! empty($logs_list) ? current(array_keys($logs_list)) : false;
		}

		$file_name = str_replace(Logger::get_logs_folder(), '', (string) $file);

		$default_content = wu_request('return_ascii', 'yes') === 'yes' ? wu_get_template_contents('events/ascii-badge') : __('No log entries found.', 'ultimate-multisite');

		$contents = $file && file_exists($file) ? file_get_contents($file) : $default_content; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads a verified local log file, not a remote URL.

		$response = [
			'file'      => $file,
			'file_name' => $file_name,
			'contents'  => $contents,
			'logs_list' => $logs_list,
		];

		if (wp_doing_ajax()) {
			wp_send_json_success($response);
		} else {
			return $response;
		}
	}

	/**
	 * Allow child classes to register widgets, if they need them.
	 *
	 * @since 1.8.2
	 * @return void
	 */
	public function register_widgets(): void {

		$info = $this->handle_view_logs();

		add_meta_box('wp-ultimo-log-contents', __('Log Contents', 'ultimate-multisite'), [$this, 'output_default_widget_payload'], get_current_screen()->id, 'normal', null, $info);

		$this->add_fields_widget(
			'file-selector',
			[
				'title'  => __('Log Files', 'ultimate-multisite'),
				'fields' => [
					'log_file' => [
						'type'        => 'select',
						'title'       => __('Select Log File', 'ultimate-multisite'),
						'placeholder' => __('Select Log File', 'ultimate-multisite'),
						'value'       => wu_request('file'),
						'tooltip'     => '',
						'options'     => $info['logs_list'],
					],
					'download' => [
						'type'    => 'submit',
						'title'   => __('Download Log', 'ultimate-multisite'),
						'value'   => 'download',
						'classes' => 'button button-primary wu-w-full',
					],
				],
			]
		);

		$this->add_fields_widget(
			'info',
			[
				'title'    => __('Timestamps', 'ultimate-multisite'),
				'position' => 'side',
				'fields'   => [
					'date_modified' => [
						'title'         => __('Last Modified at', 'ultimate-multisite'),
						'type'          => 'text-edit',
						'date'          => true,
						'value'         => date_i18n('Y-m-d H:i:s', filemtime($info['file'])),
						'display_value' => date_i18n('Y-m-d H:i:s', filemtime($info['file'])),
					],
				],
			]
		);
	}

	/**
	 * Outputs the pre block that shows the content.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed $unused Not sure.
	 * @param array $data Arguments passed by add_meta_box.
	 * @return void
	 */
	public function output_default_widget_payload($unused, $data): void {

		wu_get_template(
			'events/widget-payload',
			[
				'title'        => __('Event Payload', 'ultimate-multisite'),
				'loading_text' => __('Loading Payload', 'ultimate-multisite'),
				'payload'      => $data['args']['contents'],
			]
		);
	}

	/**
	 * Returns the labels to be used on the admin page.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_labels() {

		return [
			'edit_label'          => __('View Log', 'ultimate-multisite'),
			'add_new_label'       => __('View Log', 'ultimate-multisite'),
			'title_placeholder'   => __('Enter Customer', 'ultimate-multisite'),
			'title_description'   => __('Viewing file: ', 'ultimate-multisite'),
			'delete_button_label' => __('Delete Log File', 'ultimate-multisite'),
			'delete_description'  => __('Be careful. This action is irreversible.', 'ultimate-multisite'),
		];
	}

	/**
	 * Returns the object being edit at the moment.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_object() {

		return [];
	}

	/**
	 * Register additional hooks to page load such as the action links and the save processing.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function page_loaded(): void {

		/**
		 * Get the action links
		 */
		$this->action_links = $this->action_links();

		/**
		 * Process save, if necessary
		 */
		$this->process_save();
	}

	/**
	 * Should implement the processes necessary to save the changes made to the object.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function handle_save(): void {

		$action = wu_request('submit_button', 'none');

		if ('none' === $action) {
			WP_Ultimo()->notices->add(__('Something wrong happened', 'ultimate-multisite'), 'error', 'network-admin');

			return;
		}

		$file = wu_request('log_file', false);

		if ( ! file_exists($file)) {
			WP_Ultimo()->notices->add(__('File not found', 'ultimate-multisite'), 'error', 'network-admin');

			return;
		}

		if ('download' === $action) {
			$file_name = str_replace(Logger::get_logs_folder(), '', (string) $file);

			header('Content-Type: application/octet-stream');
			header("Content-Disposition: attachment; filename=$file_name");
			header('Pragma: no-cache');

			global $wp_filesystem;
			if ( ! $wp_filesystem ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				WP_Filesystem();
			}

			if ( ! $wp_filesystem->exists($file) ) {
				wp_die(esc_html__('Log file not found.', 'ultimate-multisite'));
			}

			$content = $wp_filesystem->get_contents($file);
			if ( false === $content ) {
				wp_die(esc_html__('Unable to read log file.', 'ultimate-multisite'));
			}

			header('Content-Length: ' . strlen($content));
			echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

			exit;
		} elseif ('delete' === $action) {
			$status = wp_delete_file($file);

			if ( ! $status) {
				WP_Ultimo()->notices->add(__('We were unable to delete file', 'ultimate-multisite'), 'error', 'network-admin');

				return;
			}
		}

		$url = remove_query_arg('log_file');

		wp_safe_redirect(add_query_arg('deleted', 1, $url));

		exit;
	}
}
