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
	 * Overrides the is_upgradeable method.
	 *
	 * We need to do this because we are using the table object
	 * early in the lifecycle, which means that upgrade.php is not
	 * available.
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
	 * Change the prefix if needed.
	 */
	public function __construct() {
		$this->update_prefix_with_network_id();
		parent::__construct();

		if (true === $this->global) {
			remove_action('switch_blog', [$this, 'switch_blog']);
		}

		$this->register_network_meta_table();
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
