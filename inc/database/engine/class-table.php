<?php
/**
 * Base Custom Database Table Class.
 */

namespace WP_Ultimo\Database\Engine;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * The base class that all other database base classes extend.
 *
 * This class attempts to provide some universal immutability to all other
 * classes that extend it, starting with a magic getter, but likely expanding
 * into a magic call handler and others.
 *
 * @since 1.0.0
 * @property-read string $name
 */
abstract class Table extends \BerlinDB\Database\Table {
	use Network_Prefix;

	/**
	 * Table prefix.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $prefix = 'wu';

	/**
	 * Caches the SHOW TABLES result.
	 *
	 * @since 2.0.0
	 * @var bool
	 */
	private bool $exists;

	/**
	 * Network option holding the installed schema version of each of this
	 * network's tables, keyed by prefixed table name.
	 *
	 * One option per network, so checking every table costs a single
	 * (cached) option read per request.
	 *
	 * @since 2.18.0
	 * @var string
	 */
	const NETWORK_TABLE_VERSIONS_OPTION = 'wu_network_table_versions';

	/**
	 * Overrides the is_upgradeable method.
	 *
	 * We need to do this because we are using the table object
	 * early in the lifecycle, which means that upgrade.php is not
	 * available.
	 *
	 * Every network keeps its own tables (wu_{network_id}_* on secondary
	 * networks), so the main site of any network may install and upgrade them.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function is_upgradeable(): bool {

		return is_main_site();
	}

	/**
	 * Adds a caching layer to the parent exists method.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function exists(): bool {

		if (! isset($this->exists)) {
			$this->exists = parent::exists();
		}

		return $this->exists;
	}

	/**
	 * Install the table, then drop the cached exists() result.
	 *
	 * A table checked before installation would otherwise keep reporting
	 * that it does not exist for the rest of the request.
	 *
	 * @since 2.18.0
	 * @return void
	 */
	public function install() {

		parent::install();

		unset($this->exists);
	}

	/**
	 * Change the prefix if needed.
	 */
	public function __construct() {
		$base_prefix = (string) $this->prefix;

		$this->update_prefix_with_network_id();
		parent::__construct();

		if (true === $this->global) {
			remove_action('switch_blog', [$this, 'switch_blog']);
		}

		$this->register_network_meta_table();

		if ($base_prefix !== $this->prefix) {
			$this->maybe_install_network_table();
		}
	}

	/**
	 * Create or upgrade this secondary network's copy of the table.
	 *
	 * BerlinDB only installs and upgrades on admin_init, and only for the
	 * main site, so a new network's wu_{network_id}_* tables would not exist
	 * when its first (often front-end) request queries them. Run the same
	 * install/upgrade on the first request instead, once per schema version.
	 *
	 * The per-table versions BerlinDB records (on the main network) remain
	 * the source of truth; this network option only avoids re-checking them
	 * on every request. A MySQL named lock keeps concurrent first requests
	 * from creating or altering the same table twice; a request that finds
	 * the lock taken skips setup instead of waiting. A failed setup is retried
	 * at most hourly so a broken table never adds DDL to every request.
	 *
	 * @since 2.18.0
	 * @return void
	 */
	protected function maybe_install_network_table(): void {

		if (wp_installing() || empty($this->prefixed_name) || empty($this->table_name)) {
			return;
		}

		$network_id = get_current_network_id();
		$versions   = get_network_option($network_id, self::NETWORK_TABLE_VERSIONS_OPTION, []);
		$versions   = is_array($versions) ? $versions : [];

		if (isset($versions[ $this->prefixed_name ]) && (string) $this->version === (string) $versions[ $this->prefixed_name ]) {
			return;
		}

		$retry_key = 'wu_table_setup_failed_' . $this->prefixed_name;

		if (get_site_transient($retry_key)) {
			return;
		}

		$db   = $this->get_db();
		$lock = 'wu_tables_' . $network_id;

		// Another request is setting up this network's tables: do not wait for it.
		if ('1' !== (string) $db->get_var($db->prepare('SELECT GET_LOCK(%s, 0)', $lock))) {
			return;
		}

		try {
			// A version can be recorded for a table that no longer exists.
			if ( ! parent::exists()) {
				$this->install();
			} elseif ($this->needs_upgrade()) {
				$this->upgrade();
			}

			if ($this->needs_upgrade() || ! parent::exists()) {
				set_site_transient($retry_key, 1, HOUR_IN_SECONDS);

				return;
			}

			$versions = get_network_option($network_id, self::NETWORK_TABLE_VERSIONS_OPTION, []);
			$versions = is_array($versions) ? $versions : [];

			$versions[ $this->prefixed_name ] = (string) $this->version;

			update_network_option($network_id, self::NETWORK_TABLE_VERSIONS_OPTION, $versions);
		} finally {
			$db->get_var($db->prepare('SELECT RELEASE_LOCK(%s)', $lock));
		}
	}

	/**
	 * Point the canonical meta table property at this network's table.
	 *
	 * On a secondary network the prefix becomes `wu_{network_id}`, so BerlinDB
	 * registers `$wpdb->wu_{network_id}_customermeta`. WordPress's metadata API
	 * resolves meta type `wu_customer` through `$wpdb->wu_customermeta`, which
	 * was then unset: get_meta()/update_meta() on customers, memberships,
	 * payments and other models silently did nothing on that network.
	 *
	 * @since 2.18.0
	 * @return void
	 */
	protected function register_network_meta_table(): void {

		global $wpdb;

		$base = 'wu';

		if ($this->prefix === $base || ! str_starts_with((string) $this->prefix, $base . '_') || ! str_ends_with((string) $this->name, 'meta') || empty($this->table_name)) {
			return;
		}

		$wpdb->{$base . '_' . $this->name} = $this->table_name;
	}
}
