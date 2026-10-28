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
	 * Network option holding a secondary network's setup state: the installed
	 * schema version of each table (keyed by prefixed table name) plus other
	 * one-time setup markers (e.g. Email_Manager::SYSTEM_EMAILS_SETUP_KEY).
	 *
	 * Requirements::run_setup() loads it in the same query as
	 * wu_setup_finished, so once every table is current, checking them adds
	 * no query to the request.
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
	 * Secondary networks' tables are installed and upgraded by
	 * maybe_install_network_table() instead, so BerlinDB's admin_init check
	 * (one version read per table) never runs on their admin requests.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function is_upgradeable(): bool {

		if ( ! is_main_network()) {
			return false;
		}

		return is_main_site();
	}

	/**
	 * Name of the MySQL named lock that serializes a secondary network's setup.
	 *
	 * Named locks are server-wide, so the name is scoped to this installation
	 * (database name and table prefix), like Membership's publish lock.
	 *
	 * @since 2.18.0
	 * @param int $network_id Network ID.
	 * @return string
	 */
	final public static function get_network_setup_lock(int $network_id): string {

		global $wpdb;

		return sprintf('wu_setup_%s_%d', substr(md5(DB_NAME . '|' . $wpdb->base_prefix), 0, 12), $network_id);
	}

	/**
	 * Returns a network's setup state (see NETWORK_TABLE_VERSIONS_OPTION).
	 *
	 * @since 2.18.0
	 * @param int $network_id Network ID.
	 * @return array<string, string>
	 */
	final public static function get_network_setup_state(int $network_id): array {

		$state = get_network_option($network_id, self::NETWORK_TABLE_VERSIONS_OPTION, []);

		return is_array($state) ? $state : [];
	}

	/**
	 * Reads a network's setup state from the database, bypassing the object cache.
	 *
	 * Also removes duplicate rows left by concurrent first requests, keeping
	 * the lowest meta_id. Only used while holding get_network_setup_lock().
	 *
	 * @since 2.18.0
	 * @param int $network_id Network ID.
	 * @return array<string, string>
	 */
	final public static function refresh_network_setup_state(int $network_id): array {

		global $wpdb;

		wp_cache_delete("{$network_id}:" . self::NETWORK_TABLE_VERSIONS_OPTION, 'site-options');
		wp_cache_delete($network_id . ':notoptions', 'site-options');

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT meta_id, meta_value FROM {$wpdb->sitemeta} WHERE site_id = %d AND meta_key = %s ORDER BY meta_id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$network_id,
				self::NETWORK_TABLE_VERSIONS_OPTION
			)
		);

		if (empty($rows)) {
			return [];
		}

		if (count($rows) > 1) {
			foreach (array_slice($rows, 1) as $duplicate) {
				$wpdb->delete($wpdb->sitemeta, ['meta_id' => (int) $duplicate->meta_id]); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			}
		}

		$state = maybe_unserialize($rows[0]->meta_value);

		return is_array($state) ? $state : [];
	}

	/**
	 * Records one completed setup step in a network's setup state.
	 *
	 * Call while holding get_network_setup_lock(), so concurrent steps do not
	 * overwrite each other's entries.
	 *
	 * @since 2.18.0
	 * @param int    $network_id Network ID.
	 * @param string $key        Prefixed table name or setup marker.
	 * @param string $value      Installed version or marker value.
	 * @return void
	 */
	final public static function set_network_setup_state(int $network_id, string $key, string $value): void {

		// Read fresh: the request-start cache can predate other requests' entries.
		$state         = self::refresh_network_setup_state($network_id);
		$state[ $key ] = $value;

		update_network_option($network_id, self::NETWORK_TABLE_VERSIONS_OPTION, $state);
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
		$state      = self::get_network_setup_state($network_id);

		// Fast path on every request once the table is current: no query beyond the primed option.
		if (isset($state[ $this->prefixed_name ]) && (string) $this->version === (string) $state[ $this->prefixed_name ]) {
			return;
		}

		$retry_key = 'wu_table_setup_failed_' . $this->prefixed_name;

		if (get_site_transient($retry_key)) {
			return;
		}

		$db   = $this->get_db();
		$lock = self::get_network_setup_lock($network_id);

		// Another request is setting up this network's tables: do not wait for it.
		if ('1' !== (string) $db->get_var($db->prepare('SELECT GET_LOCK(%s, 0)', $lock))) {
			return;
		}

		try {
			// Another request may have finished this table while we waited on the cached state.
			$fresh = self::refresh_network_setup_state($network_id);

			if (isset($fresh[ $this->prefixed_name ]) && (string) $this->version === (string) $fresh[ $this->prefixed_name ] && parent::exists()) {
				return;
			}

			// A version can be recorded for a table that no longer exists.
			if ( ! parent::exists()) {
				$this->install();
			} else {
				$this->adopt_network_local_version($network_id);

				if ($this->needs_upgrade()) {
					$this->upgrade();
				}
			}

			if ($this->needs_upgrade() || ! parent::exists()) {
				set_site_transient($retry_key, 1, HOUR_IN_SECONDS);

				return;
			}

			self::set_network_setup_state($network_id, $this->prefixed_name, (string) $this->version);
		} finally {
			$db->get_var($db->prepare('SELECT RELEASE_LOCK(%s)', $lock));
		}
	}

	/**
	 * Use a schema version recorded under this network when the main network has none.
	 *
	 * BerlinDB reads every table's version from the main network. Some installs
	 * recorded secondary-network versions under the secondary network itself.
	 * Without a version, BerlinDB's upgrade() stamps an existing table as current
	 * without running its pending upgrades, so copy the network-local version
	 * to where BerlinDB reads it first. An existing table with no version
	 * anywhere is still stamped current, but the stamp is logged.
	 *
	 * Runs only during setup (under the setup lock), never on the fast path.
	 *
	 * @since 2.18.0
	 * @param int $network_id Network ID.
	 * @return void
	 */
	private function adopt_network_local_version(int $network_id): void {

		$main_network_id = get_main_network_id();

		if ($main_network_id === $network_id || false !== get_network_option($main_network_id, $this->db_version_key, false)) {
			return;
		}

		$local_version = get_network_option($network_id, $this->db_version_key, false);

		if (false === $local_version || '' === (string) $local_version) {
			wu_log_add(
				'database',
				sprintf(
					'Table %1$s exists without a recorded schema version; marking it as version %2$s without running upgrades.',
					$this->table_name,
					$this->version
				),
				\Psr\Log\LogLevel::WARNING
			);

			return;
		}

		update_network_option($main_network_id, $this->db_version_key, (string) $local_version);
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
