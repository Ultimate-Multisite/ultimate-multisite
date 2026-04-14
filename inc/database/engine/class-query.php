<?php
/**
 * Base Custom Database Table Query Class.
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
 */
class Query extends \BerlinDB\Database\Query {

	use Network_Prefix;

	/**
	 * The prefix for the custom table.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $prefix = 'wu';

	/**
	 * If we should use a global cache group.
	 *
	 * @since 2.1.2
	 * @var bool
	 */
	protected $global_cache = false;

	/**
	 * Keep track of the global cache groups we've added.
	 * This is to prevent adding the same group multiple times.
	 *
	 * @since 2.1.2
	 * @var array
	 */
	protected static $added_globals = [];

	/**
	 * Plural version for a group of items.
	 *
	 * @since  2.0.0
	 * @access public
	 * @var string
	 */
	protected $item_name_plural;

	/**
	 * Group to cache queries and queried items in.
	 *
	 * @since  2.0.0
	 * @access public
	 * @var string
	 */
	protected $cache_group = 'sites';

	/**
	 * The class constructor
	 *
	 * @since 2.1.2
	 * @param string|array $query Optional. An array or string of Query parameters.
	 * @return void
	 */
	public function __construct($query = []) {

		$this->update_prefix_with_network_id();

		$cache_group = $this->apply_prefix($this->cache_group, '-');

		if ($this->global_cache && ! in_array($cache_group, self::$added_globals, true)) {
			wp_cache_add_global_groups([$cache_group]);

			self::$added_globals[] = $cache_group;
		}

		parent::__construct($query);
	}

	/**
	 * Get the plural name.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_plural_name() {

		return $this->item_name_plural;
	}

	/**
	 * Get columns from an array of arguments.
	 * Copy of the parent method of public access.
	 *
	 * @param array  $args     Arguments to filter columns by.
	 * @param string $operator Optional. The logical operation to perform.
	 * @param string $field    Optional. A field from the object to place
	 *                         instead of the entire object. Default false.
	 * @return array Array of column.
	 */
	public function get_columns($args = array(), $operator = 'and', $field = false) {
		// Filter columns.
		$filter = wp_filter_object_list($this->columns, $args, $operator, $field);

		// Return column or false.
		return ! empty($filter)
			? array_values($filter)
			: array();
	}

	/**
	 * Atomically increment numeric columns on an existing item.
	 *
	 * Uses SQL `SET column = column + value` to avoid read-modify-write race
	 * conditions when multiple processes update the same row concurrently.
	 *
	 * Non-numeric columns can be set (replaced) at the same time via $sets.
	 *
	 * Unlike update_item(), this method does NOT fire transition hooks, does
	 * NOT process meta, and does NOT diff against the current row — by design,
	 * it never reads the row before writing.
	 *
	 * @since 2.4.9
	 *
	 * @param int   $item_id          The item ID to update.
	 * @param array $increments       Column => numeric value pairs to atomically add.
	 *                                Example: ['cost_value' => 0.005, 'input_tokens' => 150].
	 * @param array $sets             Column => value pairs to set (replace, not increment).
	 *                                Example: ['provider_id' => 'anthropic', 'model_id' => 'claude-sonnet-4'].
	 * @param bool  $invalidate_cache Whether to invalidate the object cache after the update.
	 *                                Set to false for high-frequency increments where eventual
	 *                                consistency is acceptable (e.g. usage counters read by
	 *                                dashboards). The cache will self-heal on TTL expiry.
	 *                                Default true.
	 * @return int|false Number of rows affected, or false on failure.
	 */
	public function increment_item( $item_id = 0, $increments = [], $sets = [], $invalidate_cache = true ) {

		// Bail if nothing to do.
		if ( empty( $increments ) && empty( $sets ) ) {
			return false;
		}

		// Bail if no item ID.
		$item_id = absint( $item_id );
		if ( empty( $item_id ) ) {
			return false;
		}

		$db = $this->get_db();

		// Bail if no database interface.
		if ( empty( $db ) ) {
			return false;
		}

		// Resolve the fully-qualified table name (same as private get_table_name()).
		$table = $db->{$this->table_name};
		if ( empty( $table ) ) {
			return false;
		}

		// Get valid column names for this table.
		$valid_columns = array_flip( $this->get_columns( [], 'and', 'name' ) );

		// Get the primary column name.
		$primary_columns = $this->get_columns( [ 'primary' => true ], 'and', 'name' );
		$primary         = ! empty( $primary_columns ) ? reset( $primary_columns ) : 'id';

		// Build SET clause fragments and prepare values.
		$set_clauses = [];
		$values      = [];

		// Process increments: column = column + %f/%d.
		foreach ( $increments as $column => $amount ) {

			// Skip non-existent or primary columns.
			if ( ! isset( $valid_columns[ $column ] ) || $column === $primary ) {
				continue;
			}

			// Determine placeholder based on type.
			if ( is_float( $amount ) ) {
				$set_clauses[] = "`{$column}` = `{$column}` + %f";
			} else {
				$set_clauses[] = "`{$column}` = `{$column}` + %d";
			}

			$values[] = $amount;
		}

		// Process sets: column = %s/%f/%d.
		foreach ( $sets as $column => $value ) {

			// Skip non-existent or primary columns.
			if ( ! isset( $valid_columns[ $column ] ) || $column === $primary ) {
				continue;
			}

			if ( is_float( $value ) ) {
				$set_clauses[] = "`{$column}` = %f";
			} elseif ( is_int( $value ) ) {
				$set_clauses[] = "`{$column}` = %d";
			} else {
				$set_clauses[] = "`{$column}` = %s";
			}

			$values[] = $value;
		}

		// Bail if no valid clauses.
		if ( empty( $set_clauses ) ) {
			return false;
		}

		// Add the WHERE value.
		$values[] = $item_id;

		// Build and execute the query.
		$sql = sprintf(
			"UPDATE `%s` SET %s WHERE `%s` = %%d",
			$table,
			implode( ', ', $set_clauses ),
			$primary
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic column names validated against schema above.
		$result = $db->query( $db->prepare( $sql, $values ) );

		// Bail on failure.
		if ( ! $this->is_success( $result ) ) {
			return false;
		}

		// Optionally invalidate object cache for this item.
		if ( $invalidate_cache ) {
			wp_cache_delete( $item_id, $this->cache_group );

			// Bump last_changed so list queries re-fetch.
			wp_cache_set( 'last_changed', microtime(), $this->cache_group );
		}

		return $result;
	}
}
