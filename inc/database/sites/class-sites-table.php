<?php
/**
 * Class used for querying blogs.
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
 * Setup the "wp_blogs" database table.
 *
 * This class wraps the WordPress core wp_blogs table in a BerlinDB Table
 * object so that our Site model can use BerlinDB's query layer. Because
 * wp_blogs is owned by WordPress core, all destructive operations (drop,
 * truncate, uninstall, delete_all) are overridden as no-ops.
 *
 * @since 2.0.0
 */
final class Sites_Table extends Table {

	/**
	 * Table prefix, including the site prefix.
	 *
	 * Empty because wp_blogs uses the base prefix directly, not wu_.
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
	protected $name = 'blogs';

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
	 * Do nothing — wp_blogs is a WordPress core table and already exists.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function install(): void {}

	/**
	 * Do nothing — wp_blogs is a WordPress core table and must never be dropped.
	 *
	 * BerlinDB's default uninstall() calls drop() then deletes the version
	 * option. We override it here as a failsafe so that no code path — whether
	 * our own wu_drop_tables(), a third-party plugin, or a BerlinDB lifecycle
	 * hook — can accidentally destroy this critical Multisite table.
	 *
	 * @since 2.6.3
	 * @return void
	 */
	public function uninstall(): void {}

	/**
	 * Do nothing — wp_blogs is a WordPress core table and must never be dropped.
	 *
	 * @since 2.6.3
	 * @return bool Always false.
	 */
	public function drop() {

		return false;
	}

	/**
	 * Do nothing — wp_blogs is a WordPress core table and must never be truncated.
	 *
	 * @since 2.6.3
	 * @return bool Always false.
	 */
	public function truncate() {

		return false;
	}

	/**
	 * Do nothing — wp_blogs is a WordPress core table and must never be emptied.
	 *
	 * @since 2.6.3
	 * @return bool Always false.
	 */
	public function delete_all() {

		return false;
	}
}
