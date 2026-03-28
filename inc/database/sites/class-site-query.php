<?php
/**
 * Class used for querying products.
 *
 * @package WP_Ultimo
 * @subpackage Database\Sites
 * @since 2.0.0
 */

namespace WP_Ultimo\Database\Sites;

use WP_Ultimo\Database\Engine\Query;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Class used for querying sites.
 *
 * @since 2.0.0
 */
class Site_Query extends Query {

	/**
	 * Table prefix, including the site prefix.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	protected $prefix = '';

	/** Table Properties ******************************************************/

	/**
	 * Name of the database table to query.
	 *
	 * @since  2.0.0
	 * @access public
	 * @var string
	 */
	protected $table_name = 'blogs';

	/**
	 * String used to alias the database table in MySQL statement.
	 *
	 * @since  2.0.0
	 * @access public
	 * @var string
	 */
	protected $table_alias = 's';

	/**
	 * Name of class used to setup the database schema
	 *
	 * @since  2.0.0
	 * @access public
	 * @var string
	 */
	protected $table_schema = \WP_Ultimo\Database\Sites\Sites_Schema::class;

	/** Item ******************************************************************/

	/**
	 * Name for a single item
	 *
	 * @since  2.0.0
	 * @access public
	 * @var string
	 */
	protected $item_name = 'blog';

	/**
	 * Plural version for a group of items.
	 *
	 * @since  2.0.0
	 * @access public
	 * @var string
	 */
	protected $item_name_plural = 'blogs';

	/**
	 * Callback function for turning IDs into objects
	 *
	 * @since  2.0.0
	 * @access public
	 * @var mixed
	 */
	protected $item_shape = \WP_Ultimo\Models\Site::class;

	/**
	 * Group to cache queries and queried items in.
	 *
	 * @since  2.0.0
	 * @access public
	 * @var string
	 */
	protected $cache_group = 'sites';

	/**
	 * If we should use a global cache group.
	 *
	 * @since 2.1.2
	 * @var bool
	 */
	protected $global_cache = true;

	/**
	 * Maps convenience query params to their wp_blogmeta keys.
	 *
	 * Fields like `type`, `customer_id`, `membership_id`, and `template_id`
	 * are stored in wp_blogmeta, not as columns in wp_blogs. BerlinDB cannot
	 * filter by them directly, so we convert them to meta_query clauses here.
	 *
	 * @since 2.5.0
	 * @var array<string, string>
	 */
	protected $meta_filter_map = [
		'type'          => \WP_Ultimo\Models\Site::META_TYPE,
		'customer_id'   => \WP_Ultimo\Models\Site::META_CUSTOMER_ID,
		'membership_id' => \WP_Ultimo\Models\Site::META_MEMBERSHIP_ID,
		'template_id'   => \WP_Ultimo\Models\Site::META_TEMPLATE_ID,
	];

	/**
	 * Converts meta-stored field params into meta_query clauses, then queries.
	 *
	 * BerlinDB can only filter by actual wp_blogs columns. Fields stored in
	 * wp_blogmeta (type, customer_id, membership_id, template_id) must be
	 * translated to meta_query clauses before the query is executed.
	 *
	 * Also ensures the current network's site_id is set when not provided.
	 *
	 * @since 2.0.0
	 * @since 2.5.0 Added meta_query conversion for meta-stored fields.
	 *
	 * @param array $query Query parameters.
	 * @return array|int
	 */
	public function query($query = array()) {

		if (empty($query['site_id']) && empty($query['site_id__in'])) {
			$query['site_id'] = get_current_network_id();
		}

		$extra_meta_clauses = [];

		foreach ($this->meta_filter_map as $param => $meta_key) {
			if ( ! isset($query[ $param ])) {
				continue;
			}

			$value = $query[ $param ];

			unset($query[ $param ]);

			$extra_meta_clauses[] = [
				'key'     => $meta_key,
				'value'   => $value,
				'compare' => '=',
			];
		}

		if ( ! empty($extra_meta_clauses)) {
			$existing = isset($query['meta_query']) && is_array($query['meta_query'])
				? $query['meta_query']
				: [];

			/*
			 * Preserve any existing relation key so callers can still pass
			 * their own meta_query with a custom relation. The new clauses
			 * are always ANDed together; if the caller already set a relation
			 * we wrap both sets under a top-level AND.
			 */
			if ( ! empty($existing)) {
				$query['meta_query'] = array_merge(
					['relation' => 'AND'],
					$existing,
					$extra_meta_clauses
				);
			} else {
				$query['meta_query'] = array_merge( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					['relation' => 'AND'],
					$extra_meta_clauses
				);
			}
		}

		return parent::query($query);
	}
}
