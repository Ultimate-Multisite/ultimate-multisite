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
	 * The prefix before the network ID is appended on secondary networks.
	 *
	 * Meta tables share one schema on every network: their object column is
	 * always `wu_{item}_id`. The meta type must therefore use this prefix,
	 * not the network-suffixed one.
	 *
	 * @since 2.18.0
	 * @var string
	 */
	protected $shared_prefix = '';

	/**
	 * The class constructor
	 *
	 * @since 2.1.2
	 * @param string|array $query Optional. An array or string of Query parameters.
	 * @return void
	 */
	public function __construct($query = []) {

		$base_prefix         = $this->prefix;
		$this->shared_prefix = (string) $base_prefix;

		$this->update_prefix_with_network_id();

		$cache_group = $this->apply_prefix($this->cache_group, '-');

		if ($this->global_cache && ! in_array($cache_group, self::$added_globals, true)) {
			wp_cache_add_global_groups([$cache_group]);

			self::$added_globals[] = $cache_group;
		}

		parent::__construct($query);

		if ($base_prefix !== $this->prefix) {
			$this->bridge_network_transition_hooks($base_prefix);
		}
	}

	/**
	 * Keep track of the transition hooks we've bridged.
	 *
	 * @since 2.17.3
	 * @var array<string, bool>
	 */
	protected static $bridged_transition_hooks = [];

	/**
	 * Re-fires network-prefixed transition hooks under their canonical names.
	 *
	 * On non-main networks the table prefix becomes e.g. `wu_2`, so BerlinDB
	 * fires `wu_2_transition_customer_email_verification` instead of
	 * `wu_transition_customer_email_verification`. All listeners use the
	 * canonical name, so without this bridge every transition side effect
	 * (email verification, membership/payment status changes, domain changes)
	 * is silently skipped on secondary networks.
	 *
	 * @since 2.17.3
	 *
	 * @param string $base_prefix The prefix before the network ID was appended.
	 * @return void
	 */
	protected function bridge_network_transition_hooks(string $base_prefix): void {

		$columns = $this->get_columns(['transition' => true], 'and', 'name');

		foreach ($columns as $column) {
			$suffix         = "transition_{$this->item_name}_{$column}";
			$network_hook   = $this->apply_prefix($suffix);
			$canonical_hook = $base_prefix ? "{$base_prefix}_{$suffix}" : $suffix;

			if (isset(self::$bridged_transition_hooks[ $network_hook ])) {
				continue;
			}

			self::$bridged_transition_hooks[ $network_hook ] = true;

			add_action(
				$network_hook,
				function ($old_value, $new_value, $item_id) use ($canonical_hook) {
					do_action($canonical_hook, $old_value, $new_value, $item_id); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
				},
				10,
				3
			);
		}
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
	 * Get the WordPress meta type for this query's items.
	 *
	 * Uses the shared prefix on every network (e.g. `wu_customer`, never
	 * `wu_99_customer`), because WordPress derives the meta object column
	 * as `{$meta_type}_id` and the meta schema always uses `wu_{item}_id`.
	 * Table::register_network_meta_table() points `$wpdb->wu_{item}meta` at
	 * the current network's meta table.
	 *
	 * @since 2.18.0
	 * @return string
	 */
	public function get_shared_meta_type() {

		return '' !== $this->shared_prefix
			? "{$this->shared_prefix}_{$this->item_name}"
			: $this->item_name;
	}

	/**
	 * Get the meta type BerlinDB uses for meta queries and meta writes.
	 *
	 * Overrides the (patched) BerlinDB method, which applies the
	 * network-suffixed prefix.
	 *
	 * @since 2.18.0
	 * @return string
	 */
	protected function get_meta_type() {

		return $this->get_shared_meta_type();
	}

	/**
	 * Get the meta type BerlinDB uses to prime the meta cache.
	 *
	 * BerlinDB derives it from the table name (`wu_99_customers` →
	 * `wu_99_customer`); strip the network suffix so priming queries the
	 * shared `wu_{item}_id` column on secondary networks.
	 *
	 * @since 2.18.0
	 * @return string
	 */
	protected function get_meta_cache_type() {

		$type = parent::get_meta_cache_type();

		if ($this->shared_prefix === $this->prefix || ! str_starts_with($type, $this->prefix . '_')) {
			return $type;
		}

		return $this->shared_prefix . substr($type, strlen($this->prefix));
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
	public function increment_item($item_id = 0, $increments = [], $sets = [], $invalidate_cache = true) {

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
		$primary_columns = $this->get_columns( ['primary' => true], 'and', 'name' );
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
			'UPDATE `%s` SET %s WHERE `%s` = %%d',
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
