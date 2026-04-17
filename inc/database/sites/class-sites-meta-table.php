<?php
/**
 * Class used for querying products' meta data.
 *
 * @package WP_Ultimo
 * @subpackage Database\Sites
 * @since 2.0.0
 */

namespace WP_Ultimo\Database\Sites;

use WP_Ultimo\Database\Engine\Table;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Setup the "wp_blogmeta" database table.
 *
 * This class wraps the WordPress core wp_blogmeta table in a BerlinDB Table
 * object. Because wp_blogmeta is owned by WordPress core, all destructive
 * operations (install, drop, truncate, uninstall, delete_all) are overridden
 * as no-ops.
 *
 * @since 2.0.0
 */
final class Sites_Meta_Table extends Table {

	/**
	 * Table prefix, including the site prefix.
	 *
	 * Empty because wp_blogmeta uses the base prefix directly, not wu_.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	protected $prefix = '';

	/**
	 * Table name
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $name = 'blogmeta';

	/**
	 * Is this table global?
	 *
	 * @since 2.0.0
	 * @var boolean
	 */
	protected $global = true;

	/**
	 * Table current version
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $version = '2.0.0';

	/**
	 * Setup the database schema
	 *
	 * @access protected
	 * @since  2.0.0
	 * @return void
	 */
	protected function set_schema(): void {

		$this->schema = false;
	}

	/**
	 * Do nothing — wp_blogmeta is a WordPress core table and already exists.
	 *
	 * @since 2.6.3
	 * @return void
	 */
	public function install(): void {}

	/**
	 * Do nothing — wp_blogmeta is a WordPress core table and must never be dropped.
	 *
	 * @since 2.6.3
	 * @return void
	 */
	public function uninstall(): void {}

	/**
	 * Do nothing — wp_blogmeta is a WordPress core table and must never be dropped.
	 *
	 * @since 2.6.3
	 * @return bool Always false.
	 */
	public function drop() {

		return false;
	}

	/**
	 * Do nothing — wp_blogmeta is a WordPress core table and must never be truncated.
	 *
	 * @since 2.6.3
	 * @return bool Always false.
	 */
	public function truncate() {

		return false;
	}

	/**
	 * Do nothing — wp_blogmeta is a WordPress core table and must never be emptied.
	 *
	 * @since 2.6.3
	 * @return bool Always false.
	 */
	public function delete_all() {

		return false;
	}
}
