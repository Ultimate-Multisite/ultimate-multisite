<?php
/**
 * BerlinDB Dynamic Hooks Reference
 *
 * AUTO-GENERATED — do not edit manually.
 * Regenerate with: php inc/documentation/generate-berlindb-hooks.php
 *
 * This file exists solely so that @10up/wp-hooks-documentor can discover
 * the dynamic hooks fired by BerlinDB's Query class for every registered
 * Ultimate Multisite model. It is never loaded at runtime.
 *
 * @package WP_Ultimo
 * @subpackage Documentation
 * @since 2.3.0
 * @generated
 */

defined('ABSPATH') || exit;

// phpcs:disable -- This file is never executed; it only carries docblocks.

// Variable declarations to keep static analysers and editors happy.
$old_value      = null;
$new_value      = null;
$item_id        = 0;
$query          = null;
$clauses        = [];
$search_columns = [];
$search         = '';
$items          = [];
$item           = [];
$sql            = '';

// ─── Blog ────────────────────────────────────────────────────────────────────

/**
 * Fires before blogs are fetched from the database.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 */
do_action_ref_array('wu_pre_get_blogs', [&$query]);

/**
 * Fires after blogs query vars have been parsed.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 */
do_action_ref_array('wu_parse_blogs_query', [&$query]);

/**
 * Filters the SQL clauses for a blogs query.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param array                    $clauses {
 *     Associative array of SQL clause strings.
 *
 *     @type string $fields  The SELECT fields.
 *     @type string $join    The JOIN clause.
 *     @type string $where   The WHERE clause.
 *     @type string $orderby The ORDER BY clause.
 *     @type string $limits  The LIMIT clause.
 *     @type string $groupby The GROUP BY clause.
 * }
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 * @return array
 */
$clauses = apply_filters_ref_array('wu_blogs_query_clauses', [$clauses, &$query]);

/**
 * Filters the columns to search when performing a blogs search.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param string[]                 $search_columns Array of column names to search.
 * @param string                   $search         The search term.
 * @param \BerlinDB\Database\Query $query          The query instance.
 * @return string[]
 */
$search_columns = apply_filters('wu_blogs_search_columns', $search_columns, $search, $query);

/**
 * Filters the found blogs after a query.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param object[]                 $items The array of found blog objects.
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 * @return object[]
 */
$items = apply_filters_ref_array('wu_the_blogs', [$items, &$query]);

/**
 * Filters a single blog item before it is inserted or updated in the database.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param array                    $item  The item data as an associative array.
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 * @return array
 */
$item = apply_filters_ref_array('wu_filter_blog_item', [$item, &$query]);

/**
 * Filters the FOUND_ROWS() query for blogs.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param string                   $sql   The SQL query to count found rows.
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 * @return string
 */
$sql = apply_filters_ref_array('wu_found_blogs_query', [$sql, &$query]);

// ─── Customer ────────────────────────────────────────────────────────────────

/**
 * Fires when the email verification of a customer transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `email_verification`
 * column for a customer row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param string $old_value The previous email verification value.
 * @param string $new_value The new email verification value.
 * @param int    $item_id   The customer ID.
 */
do_action('wu_transition_customer_email_verification', $old_value, $new_value, $item_id);

/**
 * Fires when the has trialed of a customer transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `has_trialed`
 * column for a customer row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param int $old_value The previous has trialed value.
 * @param int $new_value The new has trialed value.
 * @param int    $item_id   The customer ID.
 */
do_action('wu_transition_customer_has_trialed', $old_value, $new_value, $item_id);

/**
 * Fires when the vip of a customer transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `vip`
 * column for a customer row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param int $old_value The previous vip value.
 * @param int $new_value The new vip value.
 * @param int    $item_id   The customer ID.
 */
do_action('wu_transition_customer_vip', $old_value, $new_value, $item_id);

/**
 * Fires before customers are fetched from the database.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 */
do_action_ref_array('wu_pre_get_customers', [&$query]);

/**
 * Fires after customers query vars have been parsed.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 */
do_action_ref_array('wu_parse_customers_query', [&$query]);

/**
 * Filters the SQL clauses for a customers query.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param array                    $clauses {
 *     Associative array of SQL clause strings.
 *
 *     @type string $fields  The SELECT fields.
 *     @type string $join    The JOIN clause.
 *     @type string $where   The WHERE clause.
 *     @type string $orderby The ORDER BY clause.
 *     @type string $limits  The LIMIT clause.
 *     @type string $groupby The GROUP BY clause.
 * }
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 * @return array
 */
$clauses = apply_filters_ref_array('wu_customers_query_clauses', [$clauses, &$query]);

/**
 * Filters the columns to search when performing a customers search.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param string[]                 $search_columns Array of column names to search.
 * @param string                   $search         The search term.
 * @param \BerlinDB\Database\Query $query          The query instance.
 * @return string[]
 */
$search_columns = apply_filters('wu_customers_search_columns', $search_columns, $search, $query);

/**
 * Filters the found customers after a query.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param object[]                 $items The array of found customer objects.
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 * @return object[]
 */
$items = apply_filters_ref_array('wu_the_customers', [$items, &$query]);

/**
 * Filters a single customer item before it is inserted or updated in the database.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param array                    $item  The item data as an associative array.
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 * @return array
 */
$item = apply_filters_ref_array('wu_filter_customer_item', [$item, &$query]);

/**
 * Filters the FOUND_ROWS() query for customers.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param string                   $sql   The SQL query to count found rows.
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 * @return string
 */
$sql = apply_filters_ref_array('wu_found_customers_query', [$sql, &$query]);

// ─── Discount Code ───────────────────────────────────────────────────────────

/**
 * Fires when the code of a discount code transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `code`
 * column for a discount code row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param string $old_value The previous code value.
 * @param string $new_value The new code value.
 * @param int    $item_id   The discount code ID.
 */
do_action('wu_transition_discount_code_code', $old_value, $new_value, $item_id);

/**
 * Fires when the uses of a discount code transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `uses`
 * column for a discount code row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param int $old_value The previous uses value.
 * @param int $new_value The new uses value.
 * @param int    $item_id   The discount code ID.
 */
do_action('wu_transition_discount_code_uses', $old_value, $new_value, $item_id);

/**
 * Fires when the max uses of a discount code transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `max_uses`
 * column for a discount code row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param int $old_value The previous max uses value.
 * @param int $new_value The new max uses value.
 * @param int    $item_id   The discount code ID.
 */
do_action('wu_transition_discount_code_max_uses', $old_value, $new_value, $item_id);

/**
 * Fires when the apply to renewals of a discount code transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `apply_to_renewals`
 * column for a discount code row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param int $old_value The previous apply to renewals value.
 * @param int $new_value The new apply to renewals value.
 * @param int    $item_id   The discount code ID.
 */
do_action('wu_transition_discount_code_apply_to_renewals', $old_value, $new_value, $item_id);

/**
 * Fires when the type of a discount code transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `type`
 * column for a discount code row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param string $old_value The previous type value.
 * @param string $new_value The new type value.
 * @param int    $item_id   The discount code ID.
 */
do_action('wu_transition_discount_code_type', $old_value, $new_value, $item_id);

/**
 * Fires when the value of a discount code transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `value`
 * column for a discount code row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param string $old_value The previous value value.
 * @param string $new_value The new value value.
 * @param int    $item_id   The discount code ID.
 */
do_action('wu_transition_discount_code_value', $old_value, $new_value, $item_id);

/**
 * Fires when the setup fee type of a discount code transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `setup_fee_type`
 * column for a discount code row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param string $old_value The previous setup fee type value.
 * @param string $new_value The new setup fee type value.
 * @param int    $item_id   The discount code ID.
 */
do_action('wu_transition_discount_code_setup_fee_type', $old_value, $new_value, $item_id);

/**
 * Fires when the setup fee value of a discount code transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `setup_fee_value`
 * column for a discount code row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param string $old_value The previous setup fee value value.
 * @param string $new_value The new setup fee value value.
 * @param int    $item_id   The discount code ID.
 */
do_action('wu_transition_discount_code_setup_fee_value', $old_value, $new_value, $item_id);

/**
 * Fires when the active of a discount code transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `active`
 * column for a discount code row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param int $old_value The previous active value.
 * @param int $new_value The new active value.
 * @param int    $item_id   The discount code ID.
 */
do_action('wu_transition_discount_code_active', $old_value, $new_value, $item_id);

/**
 * Fires when the date start of a discount code transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `date_start`
 * column for a discount code row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param string $old_value The previous date start value.
 * @param string $new_value The new date start value.
 * @param int    $item_id   The discount code ID.
 */
do_action('wu_transition_discount_code_date_start', $old_value, $new_value, $item_id);

/**
 * Fires when the date expiration of a discount code transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `date_expiration`
 * column for a discount code row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param string $old_value The previous date expiration value.
 * @param string $new_value The new date expiration value.
 * @param int    $item_id   The discount code ID.
 */
do_action('wu_transition_discount_code_date_expiration', $old_value, $new_value, $item_id);

/**
 * Fires before discount codes are fetched from the database.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 */
do_action_ref_array('wu_pre_get_discount_codes', [&$query]);

/**
 * Fires after discount codes query vars have been parsed.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 */
do_action_ref_array('wu_parse_discount_codes_query', [&$query]);

/**
 * Filters the SQL clauses for a discount codes query.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param array                    $clauses {
 *     Associative array of SQL clause strings.
 *
 *     @type string $fields  The SELECT fields.
 *     @type string $join    The JOIN clause.
 *     @type string $where   The WHERE clause.
 *     @type string $orderby The ORDER BY clause.
 *     @type string $limits  The LIMIT clause.
 *     @type string $groupby The GROUP BY clause.
 * }
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 * @return array
 */
$clauses = apply_filters_ref_array('wu_discount_codes_query_clauses', [$clauses, &$query]);

/**
 * Filters the columns to search when performing a discount codes search.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param string[]                 $search_columns Array of column names to search.
 * @param string                   $search         The search term.
 * @param \BerlinDB\Database\Query $query          The query instance.
 * @return string[]
 */
$search_columns = apply_filters('wu_discount_codes_search_columns', $search_columns, $search, $query);

/**
 * Filters the found discount codes after a query.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param object[]                 $items The array of found discount code objects.
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 * @return object[]
 */
$items = apply_filters_ref_array('wu_the_discount_codes', [$items, &$query]);

/**
 * Filters a single discount code item before it is inserted or updated in the database.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param array                    $item  The item data as an associative array.
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 * @return array
 */
$item = apply_filters_ref_array('wu_filter_discount_code_item', [$item, &$query]);

/**
 * Filters the FOUND_ROWS() query for discount codes.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param string                   $sql   The SQL query to count found rows.
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 * @return string
 */
$sql = apply_filters_ref_array('wu_found_discount_codes_query', [$sql, &$query]);

// ─── Domain ──────────────────────────────────────────────────────────────────

/**
 * Fires when the domain of a domain transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `domain`
 * column for a domain row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param string $old_value The previous domain value.
 * @param string $new_value The new domain value.
 * @param int    $item_id   The domain ID.
 */
do_action('wu_transition_domain_domain', $old_value, $new_value, $item_id);

/**
 * Fires when the active of a domain transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `active`
 * column for a domain row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param int $old_value The previous active value.
 * @param int $new_value The new active value.
 * @param int    $item_id   The domain ID.
 */
do_action('wu_transition_domain_active', $old_value, $new_value, $item_id);

/**
 * Fires when the primary domain of a domain transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `primary_domain`
 * column for a domain row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param int $old_value The previous primary domain value.
 * @param int $new_value The new primary domain value.
 * @param int    $item_id   The domain ID.
 */
do_action('wu_transition_domain_primary_domain', $old_value, $new_value, $item_id);

/**
 * Fires when the secure of a domain transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `secure`
 * column for a domain row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param int $old_value The previous secure value.
 * @param int $new_value The new secure value.
 * @param int    $item_id   The domain ID.
 */
do_action('wu_transition_domain_secure', $old_value, $new_value, $item_id);

/**
 * Fires when the stage of a domain transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `stage`
 * column for a domain row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param string $old_value The previous stage value.
 * @param string $new_value The new stage value.
 * @param int    $item_id   The domain ID.
 */
do_action('wu_transition_domain_stage', $old_value, $new_value, $item_id);

/**
 * Fires before domains are fetched from the database.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 */
do_action_ref_array('wu_pre_get_domains', [&$query]);

/**
 * Fires after domains query vars have been parsed.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 */
do_action_ref_array('wu_parse_domains_query', [&$query]);

/**
 * Filters the SQL clauses for a domains query.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param array                    $clauses {
 *     Associative array of SQL clause strings.
 *
 *     @type string $fields  The SELECT fields.
 *     @type string $join    The JOIN clause.
 *     @type string $where   The WHERE clause.
 *     @type string $orderby The ORDER BY clause.
 *     @type string $limits  The LIMIT clause.
 *     @type string $groupby The GROUP BY clause.
 * }
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 * @return array
 */
$clauses = apply_filters_ref_array('wu_domains_query_clauses', [$clauses, &$query]);

/**
 * Filters the columns to search when performing a domains search.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param string[]                 $search_columns Array of column names to search.
 * @param string                   $search         The search term.
 * @param \BerlinDB\Database\Query $query          The query instance.
 * @return string[]
 */
$search_columns = apply_filters('wu_domains_search_columns', $search_columns, $search, $query);

/**
 * Filters the found domains after a query.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param object[]                 $items The array of found domain objects.
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 * @return object[]
 */
$items = apply_filters_ref_array('wu_the_domains', [$items, &$query]);

/**
 * Filters a single domain item before it is inserted or updated in the database.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param array                    $item  The item data as an associative array.
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 * @return array
 */
$item = apply_filters_ref_array('wu_filter_domain_item', [$item, &$query]);

/**
 * Filters the FOUND_ROWS() query for domains.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param string                   $sql   The SQL query to count found rows.
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 * @return string
 */
$sql = apply_filters_ref_array('wu_found_domains_query', [$sql, &$query]);

// ─── Event ───────────────────────────────────────────────────────────────────

/**
 * Fires when the author id of a event transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `author_id`
 * column for a event row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param int $old_value The previous author id value.
 * @param int $new_value The new author id value.
 * @param int    $item_id   The event ID.
 */
do_action('wu_transition_event_author_id', $old_value, $new_value, $item_id);

/**
 * Fires when the object id of a event transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `object_id`
 * column for a event row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param int $old_value The previous object id value.
 * @param int $new_value The new object id value.
 * @param int    $item_id   The event ID.
 */
do_action('wu_transition_event_object_id', $old_value, $new_value, $item_id);

/**
 * Fires before events are fetched from the database.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 */
do_action_ref_array('wu_pre_get_events', [&$query]);

/**
 * Fires after events query vars have been parsed.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 */
do_action_ref_array('wu_parse_events_query', [&$query]);

/**
 * Filters the SQL clauses for a events query.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param array                    $clauses {
 *     Associative array of SQL clause strings.
 *
 *     @type string $fields  The SELECT fields.
 *     @type string $join    The JOIN clause.
 *     @type string $where   The WHERE clause.
 *     @type string $orderby The ORDER BY clause.
 *     @type string $limits  The LIMIT clause.
 *     @type string $groupby The GROUP BY clause.
 * }
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 * @return array
 */
$clauses = apply_filters_ref_array('wu_events_query_clauses', [$clauses, &$query]);

/**
 * Filters the columns to search when performing a events search.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param string[]                 $search_columns Array of column names to search.
 * @param string                   $search         The search term.
 * @param \BerlinDB\Database\Query $query          The query instance.
 * @return string[]
 */
$search_columns = apply_filters('wu_events_search_columns', $search_columns, $search, $query);

/**
 * Filters the found events after a query.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param object[]                 $items The array of found event objects.
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 * @return object[]
 */
$items = apply_filters_ref_array('wu_the_events', [$items, &$query]);

/**
 * Filters a single event item before it is inserted or updated in the database.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param array                    $item  The item data as an associative array.
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 * @return array
 */
$item = apply_filters_ref_array('wu_filter_event_item', [$item, &$query]);

/**
 * Filters the FOUND_ROWS() query for events.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param string                   $sql   The SQL query to count found rows.
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 * @return string
 */
$sql = apply_filters_ref_array('wu_found_events_query', [$sql, &$query]);

// ─── Form ────────────────────────────────────────────────────────────────────

/**
 * Fires when the name of a form transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `name`
 * column for a form row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param string $old_value The previous name value.
 * @param string $new_value The new name value.
 * @param int    $item_id   The form ID.
 */
do_action('wu_transition_form_name', $old_value, $new_value, $item_id);

/**
 * Fires when the slug of a form transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `slug`
 * column for a form row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param string $old_value The previous slug value.
 * @param string $new_value The new slug value.
 * @param int    $item_id   The form ID.
 */
do_action('wu_transition_form_slug', $old_value, $new_value, $item_id);

/**
 * Fires when the active of a form transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `active`
 * column for a form row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param int $old_value The previous active value.
 * @param int $new_value The new active value.
 * @param int    $item_id   The form ID.
 */
do_action('wu_transition_form_active', $old_value, $new_value, $item_id);

/**
 * Fires when the settings of a form transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `settings`
 * column for a form row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param string $old_value The previous settings value.
 * @param string $new_value The new settings value.
 * @param int    $item_id   The form ID.
 */
do_action('wu_transition_form_settings', $old_value, $new_value, $item_id);

/**
 * Fires before forms are fetched from the database.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 */
do_action_ref_array('wu_pre_get_forms', [&$query]);

/**
 * Fires after forms query vars have been parsed.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 */
do_action_ref_array('wu_parse_forms_query', [&$query]);

/**
 * Filters the SQL clauses for a forms query.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param array                    $clauses {
 *     Associative array of SQL clause strings.
 *
 *     @type string $fields  The SELECT fields.
 *     @type string $join    The JOIN clause.
 *     @type string $where   The WHERE clause.
 *     @type string $orderby The ORDER BY clause.
 *     @type string $limits  The LIMIT clause.
 *     @type string $groupby The GROUP BY clause.
 * }
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 * @return array
 */
$clauses = apply_filters_ref_array('wu_forms_query_clauses', [$clauses, &$query]);

/**
 * Filters the columns to search when performing a forms search.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param string[]                 $search_columns Array of column names to search.
 * @param string                   $search         The search term.
 * @param \BerlinDB\Database\Query $query          The query instance.
 * @return string[]
 */
$search_columns = apply_filters('wu_forms_search_columns', $search_columns, $search, $query);

/**
 * Filters the found forms after a query.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param object[]                 $items The array of found form objects.
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 * @return object[]
 */
$items = apply_filters_ref_array('wu_the_forms', [$items, &$query]);

/**
 * Filters a single form item before it is inserted or updated in the database.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param array                    $item  The item data as an associative array.
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 * @return array
 */
$item = apply_filters_ref_array('wu_filter_form_item', [$item, &$query]);

/**
 * Filters the FOUND_ROWS() query for forms.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param string                   $sql   The SQL query to count found rows.
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 * @return string
 */
$sql = apply_filters_ref_array('wu_found_forms_query', [$sql, &$query]);

// ─── Membership ──────────────────────────────────────────────────────────────

/**
 * Fires when the plan id of a membership transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `plan_id`
 * column for a membership row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param int $old_value The previous plan id value.
 * @param int $new_value The new plan id value.
 * @param int    $item_id   The membership ID.
 */
do_action('wu_transition_membership_plan_id', $old_value, $new_value, $item_id);

/**
 * Fires when the initial amount of a membership transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `initial_amount`
 * column for a membership row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param string $old_value The previous initial amount value.
 * @param string $new_value The new initial amount value.
 * @param int    $item_id   The membership ID.
 */
do_action('wu_transition_membership_initial_amount', $old_value, $new_value, $item_id);

/**
 * Fires when the recurring of a membership transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `recurring`
 * column for a membership row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param int $old_value The previous recurring value.
 * @param int $new_value The new recurring value.
 * @param int    $item_id   The membership ID.
 */
do_action('wu_transition_membership_recurring', $old_value, $new_value, $item_id);

/**
 * Fires when the auto renew of a membership transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `auto_renew`
 * column for a membership row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param int $old_value The previous auto renew value.
 * @param int $new_value The new auto renew value.
 * @param int    $item_id   The membership ID.
 */
do_action('wu_transition_membership_auto_renew', $old_value, $new_value, $item_id);

/**
 * Fires when the duration of a membership transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `duration`
 * column for a membership row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param int $old_value The previous duration value.
 * @param int $new_value The new duration value.
 * @param int    $item_id   The membership ID.
 */
do_action('wu_transition_membership_duration', $old_value, $new_value, $item_id);

/**
 * Fires when the amount of a membership transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `amount`
 * column for a membership row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param string $old_value The previous amount value.
 * @param string $new_value The new amount value.
 * @param int    $item_id   The membership ID.
 */
do_action('wu_transition_membership_amount', $old_value, $new_value, $item_id);

/**
 * Fires when the date expiration of a membership transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `date_expiration`
 * column for a membership row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param string $old_value The previous date expiration value.
 * @param string $new_value The new date expiration value.
 * @param int    $item_id   The membership ID.
 */
do_action('wu_transition_membership_date_expiration', $old_value, $new_value, $item_id);

/**
 * Fires when the date payment plan completed of a membership transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `date_payment_plan_completed`
 * column for a membership row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param string $old_value The previous date payment plan completed value.
 * @param string $new_value The new date payment plan completed value.
 * @param int    $item_id   The membership ID.
 */
do_action('wu_transition_membership_date_payment_plan_completed', $old_value, $new_value, $item_id);

/**
 * Fires when the times billed of a membership transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `times_billed`
 * column for a membership row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param int $old_value The previous times billed value.
 * @param int $new_value The new times billed value.
 * @param int    $item_id   The membership ID.
 */
do_action('wu_transition_membership_times_billed', $old_value, $new_value, $item_id);

/**
 * Fires when the status of a membership transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `status`
 * column for a membership row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param string $old_value The previous status value.
 * @param string $new_value The new status value.
 * @param int    $item_id   The membership ID.
 */
do_action('wu_transition_membership_status', $old_value, $new_value, $item_id);

/**
 * Fires when the gateway customer id of a membership transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `gateway_customer_id`
 * column for a membership row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param string $old_value The previous gateway customer id value.
 * @param string $new_value The new gateway customer id value.
 * @param int    $item_id   The membership ID.
 */
do_action('wu_transition_membership_gateway_customer_id', $old_value, $new_value, $item_id);

/**
 * Fires when the gateway subscription id of a membership transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `gateway_subscription_id`
 * column for a membership row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param string $old_value The previous gateway subscription id value.
 * @param string $new_value The new gateway subscription id value.
 * @param int    $item_id   The membership ID.
 */
do_action('wu_transition_membership_gateway_subscription_id', $old_value, $new_value, $item_id);

/**
 * Fires before memberships are fetched from the database.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 */
do_action_ref_array('wu_pre_get_memberships', [&$query]);

/**
 * Fires after memberships query vars have been parsed.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 */
do_action_ref_array('wu_parse_memberships_query', [&$query]);

/**
 * Filters the SQL clauses for a memberships query.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param array                    $clauses {
 *     Associative array of SQL clause strings.
 *
 *     @type string $fields  The SELECT fields.
 *     @type string $join    The JOIN clause.
 *     @type string $where   The WHERE clause.
 *     @type string $orderby The ORDER BY clause.
 *     @type string $limits  The LIMIT clause.
 *     @type string $groupby The GROUP BY clause.
 * }
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 * @return array
 */
$clauses = apply_filters_ref_array('wu_memberships_query_clauses', [$clauses, &$query]);

/**
 * Filters the columns to search when performing a memberships search.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param string[]                 $search_columns Array of column names to search.
 * @param string                   $search         The search term.
 * @param \BerlinDB\Database\Query $query          The query instance.
 * @return string[]
 */
$search_columns = apply_filters('wu_memberships_search_columns', $search_columns, $search, $query);

/**
 * Filters the found memberships after a query.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param object[]                 $items The array of found membership objects.
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 * @return object[]
 */
$items = apply_filters_ref_array('wu_the_memberships', [$items, &$query]);

/**
 * Filters a single membership item before it is inserted or updated in the database.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param array                    $item  The item data as an associative array.
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 * @return array
 */
$item = apply_filters_ref_array('wu_filter_membership_item', [$item, &$query]);

/**
 * Filters the FOUND_ROWS() query for memberships.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param string                   $sql   The SQL query to count found rows.
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 * @return string
 */
$sql = apply_filters_ref_array('wu_found_memberships_query', [$sql, &$query]);

// ─── Payment ─────────────────────────────────────────────────────────────────

/**
 * Fires when the status of a payment transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `status`
 * column for a payment row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param string $old_value The previous status value.
 * @param string $new_value The new status value.
 * @param int    $item_id   The payment ID.
 */
do_action('wu_transition_payment_status', $old_value, $new_value, $item_id);

/**
 * Fires when the customer id of a payment transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `customer_id`
 * column for a payment row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param int $old_value The previous customer id value.
 * @param int $new_value The new customer id value.
 * @param int    $item_id   The payment ID.
 */
do_action('wu_transition_payment_customer_id', $old_value, $new_value, $item_id);

/**
 * Fires when the membership id of a payment transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `membership_id`
 * column for a payment row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param int $old_value The previous membership id value.
 * @param int $new_value The new membership id value.
 * @param int    $item_id   The payment ID.
 */
do_action('wu_transition_payment_membership_id', $old_value, $new_value, $item_id);

/**
 * Fires when the parent id of a payment transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `parent_id`
 * column for a payment row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param int $old_value The previous parent id value.
 * @param int $new_value The new parent id value.
 * @param int    $item_id   The payment ID.
 */
do_action('wu_transition_payment_parent_id', $old_value, $new_value, $item_id);

/**
 * Fires when the product id of a payment transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `product_id`
 * column for a payment row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param int $old_value The previous product id value.
 * @param int $new_value The new product id value.
 * @param int    $item_id   The payment ID.
 */
do_action('wu_transition_payment_product_id', $old_value, $new_value, $item_id);

/**
 * Fires when the subtotal of a payment transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `subtotal`
 * column for a payment row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param string $old_value The previous subtotal value.
 * @param string $new_value The new subtotal value.
 * @param int    $item_id   The payment ID.
 */
do_action('wu_transition_payment_subtotal', $old_value, $new_value, $item_id);

/**
 * Fires when the refund total of a payment transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `refund_total`
 * column for a payment row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param string $old_value The previous refund total value.
 * @param string $new_value The new refund total value.
 * @param int    $item_id   The payment ID.
 */
do_action('wu_transition_payment_refund_total', $old_value, $new_value, $item_id);

/**
 * Fires when the tax total of a payment transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `tax_total`
 * column for a payment row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param string $old_value The previous tax total value.
 * @param string $new_value The new tax total value.
 * @param int    $item_id   The payment ID.
 */
do_action('wu_transition_payment_tax_total', $old_value, $new_value, $item_id);

/**
 * Fires when the total of a payment transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `total`
 * column for a payment row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param string $old_value The previous total value.
 * @param string $new_value The new total value.
 * @param int    $item_id   The payment ID.
 */
do_action('wu_transition_payment_total', $old_value, $new_value, $item_id);

/**
 * Fires before payments are fetched from the database.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 */
do_action_ref_array('wu_pre_get_payments', [&$query]);

/**
 * Fires after payments query vars have been parsed.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 */
do_action_ref_array('wu_parse_payments_query', [&$query]);

/**
 * Filters the SQL clauses for a payments query.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param array                    $clauses {
 *     Associative array of SQL clause strings.
 *
 *     @type string $fields  The SELECT fields.
 *     @type string $join    The JOIN clause.
 *     @type string $where   The WHERE clause.
 *     @type string $orderby The ORDER BY clause.
 *     @type string $limits  The LIMIT clause.
 *     @type string $groupby The GROUP BY clause.
 * }
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 * @return array
 */
$clauses = apply_filters_ref_array('wu_payments_query_clauses', [$clauses, &$query]);

/**
 * Filters the columns to search when performing a payments search.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param string[]                 $search_columns Array of column names to search.
 * @param string                   $search         The search term.
 * @param \BerlinDB\Database\Query $query          The query instance.
 * @return string[]
 */
$search_columns = apply_filters('wu_payments_search_columns', $search_columns, $search, $query);

/**
 * Filters the found payments after a query.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param object[]                 $items The array of found payment objects.
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 * @return object[]
 */
$items = apply_filters_ref_array('wu_the_payments', [$items, &$query]);

/**
 * Filters a single payment item before it is inserted or updated in the database.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param array                    $item  The item data as an associative array.
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 * @return array
 */
$item = apply_filters_ref_array('wu_filter_payment_item', [$item, &$query]);

/**
 * Filters the FOUND_ROWS() query for payments.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param string                   $sql   The SQL query to count found rows.
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 * @return string
 */
$sql = apply_filters_ref_array('wu_found_payments_query', [$sql, &$query]);

// ─── Post ────────────────────────────────────────────────────────────────────

/**
 * Fires before posts are fetched from the database.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 */
do_action_ref_array('wu_pre_get_posts', [&$query]);

/**
 * Fires after posts query vars have been parsed.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 */
do_action_ref_array('wu_parse_posts_query', [&$query]);

/**
 * Filters the SQL clauses for a posts query.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param array                    $clauses {
 *     Associative array of SQL clause strings.
 *
 *     @type string $fields  The SELECT fields.
 *     @type string $join    The JOIN clause.
 *     @type string $where   The WHERE clause.
 *     @type string $orderby The ORDER BY clause.
 *     @type string $limits  The LIMIT clause.
 *     @type string $groupby The GROUP BY clause.
 * }
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 * @return array
 */
$clauses = apply_filters_ref_array('wu_posts_query_clauses', [$clauses, &$query]);

/**
 * Filters the columns to search when performing a posts search.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param string[]                 $search_columns Array of column names to search.
 * @param string                   $search         The search term.
 * @param \BerlinDB\Database\Query $query          The query instance.
 * @return string[]
 */
$search_columns = apply_filters('wu_posts_search_columns', $search_columns, $search, $query);

/**
 * Filters the found posts after a query.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param object[]                 $items The array of found post objects.
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 * @return object[]
 */
$items = apply_filters_ref_array('wu_the_posts', [$items, &$query]);

/**
 * Filters a single post item before it is inserted or updated in the database.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param array                    $item  The item data as an associative array.
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 * @return array
 */
$item = apply_filters_ref_array('wu_filter_post_item', [$item, &$query]);

/**
 * Filters the FOUND_ROWS() query for posts.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param string                   $sql   The SQL query to count found rows.
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 * @return string
 */
$sql = apply_filters_ref_array('wu_found_posts_query', [$sql, &$query]);

// ─── Product ─────────────────────────────────────────────────────────────────

/**
 * Fires when the parent id of a product transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `parent_id`
 * column for a product row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param int $old_value The previous parent id value.
 * @param int $new_value The new parent id value.
 * @param int    $item_id   The product ID.
 */
do_action('wu_transition_product_parent_id', $old_value, $new_value, $item_id);

/**
 * Fires when the amount of a product transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `amount`
 * column for a product row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param string $old_value The previous amount value.
 * @param string $new_value The new amount value.
 * @param int    $item_id   The product ID.
 */
do_action('wu_transition_product_amount', $old_value, $new_value, $item_id);

/**
 * Fires when the setup fee of a product transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `setup_fee`
 * column for a product row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param string $old_value The previous setup fee value.
 * @param string $new_value The new setup fee value.
 * @param int    $item_id   The product ID.
 */
do_action('wu_transition_product_setup_fee', $old_value, $new_value, $item_id);

/**
 * Fires when the recurring of a product transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `recurring`
 * column for a product row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param int $old_value The previous recurring value.
 * @param int $new_value The new recurring value.
 * @param int    $item_id   The product ID.
 */
do_action('wu_transition_product_recurring', $old_value, $new_value, $item_id);

/**
 * Fires when the trial duration of a product transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `trial_duration`
 * column for a product row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param int $old_value The previous trial duration value.
 * @param int $new_value The new trial duration value.
 * @param int    $item_id   The product ID.
 */
do_action('wu_transition_product_trial_duration', $old_value, $new_value, $item_id);

/**
 * Fires when the duration of a product transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `duration`
 * column for a product row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param int $old_value The previous duration value.
 * @param int $new_value The new duration value.
 * @param int    $item_id   The product ID.
 */
do_action('wu_transition_product_duration', $old_value, $new_value, $item_id);

/**
 * Fires when the billing cycles of a product transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `billing_cycles`
 * column for a product row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param int $old_value The previous billing cycles value.
 * @param int $new_value The new billing cycles value.
 * @param int    $item_id   The product ID.
 */
do_action('wu_transition_product_billing_cycles', $old_value, $new_value, $item_id);

/**
 * Fires when the list order of a product transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `list_order`
 * column for a product row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param int $old_value The previous list order value.
 * @param int $new_value The new list order value.
 * @param int    $item_id   The product ID.
 */
do_action('wu_transition_product_list_order', $old_value, $new_value, $item_id);

/**
 * Fires when the active of a product transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `active`
 * column for a product row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param int $old_value The previous active value.
 * @param int $new_value The new active value.
 * @param int    $item_id   The product ID.
 */
do_action('wu_transition_product_active', $old_value, $new_value, $item_id);

/**
 * Fires before products are fetched from the database.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 */
do_action_ref_array('wu_pre_get_products', [&$query]);

/**
 * Fires after products query vars have been parsed.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 */
do_action_ref_array('wu_parse_products_query', [&$query]);

/**
 * Filters the SQL clauses for a products query.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param array                    $clauses {
 *     Associative array of SQL clause strings.
 *
 *     @type string $fields  The SELECT fields.
 *     @type string $join    The JOIN clause.
 *     @type string $where   The WHERE clause.
 *     @type string $orderby The ORDER BY clause.
 *     @type string $limits  The LIMIT clause.
 *     @type string $groupby The GROUP BY clause.
 * }
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 * @return array
 */
$clauses = apply_filters_ref_array('wu_products_query_clauses', [$clauses, &$query]);

/**
 * Filters the columns to search when performing a products search.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param string[]                 $search_columns Array of column names to search.
 * @param string                   $search         The search term.
 * @param \BerlinDB\Database\Query $query          The query instance.
 * @return string[]
 */
$search_columns = apply_filters('wu_products_search_columns', $search_columns, $search, $query);

/**
 * Filters the found products after a query.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param object[]                 $items The array of found product objects.
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 * @return object[]
 */
$items = apply_filters_ref_array('wu_the_products', [$items, &$query]);

/**
 * Filters a single product item before it is inserted or updated in the database.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param array                    $item  The item data as an associative array.
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 * @return array
 */
$item = apply_filters_ref_array('wu_filter_product_item', [$item, &$query]);

/**
 * Filters the FOUND_ROWS() query for products.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param string                   $sql   The SQL query to count found rows.
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 * @return string
 */
$sql = apply_filters_ref_array('wu_found_products_query', [$sql, &$query]);

// ─── Webhook ─────────────────────────────────────────────────────────────────

/**
 * Fires when the active of a webhook transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `active`
 * column for a webhook row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param int $old_value The previous active value.
 * @param int $new_value The new active value.
 * @param int    $item_id   The webhook ID.
 */
do_action('wu_transition_webhook_active', $old_value, $new_value, $item_id);

/**
 * Fires when the hidden of a webhook transitions from one value to another.
 *
 * This hook is fired by BerlinDB when a database UPDATE changes the `hidden`
 * column for a webhook row.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param int $old_value The previous hidden value.
 * @param int $new_value The new hidden value.
 * @param int    $item_id   The webhook ID.
 */
do_action('wu_transition_webhook_hidden', $old_value, $new_value, $item_id);

/**
 * Fires before webhooks are fetched from the database.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 */
do_action_ref_array('wu_pre_get_webhooks', [&$query]);

/**
 * Fires after webhooks query vars have been parsed.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 */
do_action_ref_array('wu_parse_webhooks_query', [&$query]);

/**
 * Filters the SQL clauses for a webhooks query.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param array                    $clauses {
 *     Associative array of SQL clause strings.
 *
 *     @type string $fields  The SELECT fields.
 *     @type string $join    The JOIN clause.
 *     @type string $where   The WHERE clause.
 *     @type string $orderby The ORDER BY clause.
 *     @type string $limits  The LIMIT clause.
 *     @type string $groupby The GROUP BY clause.
 * }
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 * @return array
 */
$clauses = apply_filters_ref_array('wu_webhooks_query_clauses', [$clauses, &$query]);

/**
 * Filters the columns to search when performing a webhooks search.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param string[]                 $search_columns Array of column names to search.
 * @param string                   $search         The search term.
 * @param \BerlinDB\Database\Query $query          The query instance.
 * @return string[]
 */
$search_columns = apply_filters('wu_webhooks_search_columns', $search_columns, $search, $query);

/**
 * Filters the found webhooks after a query.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param object[]                 $items The array of found webhook objects.
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 * @return object[]
 */
$items = apply_filters_ref_array('wu_the_webhooks', [$items, &$query]);

/**
 * Filters a single webhook item before it is inserted or updated in the database.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param array                    $item  The item data as an associative array.
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 * @return array
 */
$item = apply_filters_ref_array('wu_filter_webhook_item', [$item, &$query]);

/**
 * Filters the FOUND_ROWS() query for webhooks.
 *
 * @since 2.0.0
 * @see vendor/berlindb/core/src/Database/Query.php
 *
 * @param string                   $sql   The SQL query to count found rows.
 * @param \BerlinDB\Database\Query $query The query instance (passed by reference).
 * @return string
 */
$sql = apply_filters_ref_array('wu_found_webhooks_query', [$sql, &$query]);

// phpcs:enable
