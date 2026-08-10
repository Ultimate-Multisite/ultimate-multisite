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

		if ($this->global) {
			remove_action('switch_blog', [$this, 'switch_blog']);
		}
	}
}
