<?php
/**
 * Default Ajax hooks.
 *
 * @package WP_Ultimo
 * @subpackage Ajax
 * @since 2.0.0
 */

namespace WP_Ultimo;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Adds a lighter ajax option to Ultimate Multisite.
 *
 * @since 1.9.14
 */
class Ajax implements \WP_Ultimo\Interfaces\Singleton {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * Sets up the listeners.
	 *
	 * @since 2.0.0
	 */
	public function init(): void {
		/*
		 * Load search endpoints.
		 */
		add_action('wp_ajax_wu_list_table_fetch_ajax_results', [$this, 'refresh_list_table']);
	}

	/**
	 * Reverts the name of the table being processed.
	 *
	 * @since 2.0.0
	 *
	 * @param string $table_id The ID of the table in the format "line_item_list_table".
	 */
	private function get_table_class_name($table_id): string {

		return str_replace(' ', '_', (ucwords(str_replace('_', ' ', $table_id))));
	}

	/**
	 * Serves the pagination and search results of a list table ajax query.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function refresh_list_table(): void {

		$table_id = wu_request('table_id');

		$class_name = $this->get_table_class_name($table_id);

		$full_class_name = "\\WP_Ultimo\\List_Tables\\{$class_name}";

		if (class_exists($full_class_name)) {
			$table = new $full_class_name();

			$table->ajax_response();
		}

		do_action('wu_list_table_fetch_ajax_results', $table_id);
	}
}
