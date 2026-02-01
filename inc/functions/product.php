<?php
/**
 * Product Functions
 *
 * @package WP_Ultimo\Functions
 * @since   2.0.0
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

use WP_Ultimo\Models\Product;

/**
 * Returns a product.
 *
 * @since 2.0.0
 *
 * @param null|int|string $product_id_or_slug The ID or slug of the product.
 * @return Product|false
 */
function wu_get_product($product_id_or_slug) {

	if (is_numeric($product_id_or_slug) === false) {
		return wu_get_product_by_slug($product_id_or_slug);
	}

	return Product::get_by_id($product_id_or_slug);
}

/**
 * Queries products.
 *
 * @since 2.0.0
 *
 * @param array $query Query arguments.
 * @return Product[]
 */
function wu_get_products($query = []) {

	return Product::query($query);
}

/**
 * Queries plans.
 *
 * @since 2.0.0
 *
 * @param array $query Query arguments.
 * @return Product[]
 */
function wu_get_plans($query = []) {

	$query['type'] = 'plan';

	/*
	 * Fixes the order.
	 */
	$query['order']   = 'ASC';
	$query['orderby'] = 'list_order';

	return Product::query($query);
}

/**
 * Returns the list of plans as ID -> Name.
 *
 * @since 2.0.0
 * @return array
 */
function wu_get_plans_as_options() {

	$options = [];

	foreach (wu_get_plans() as $plan) {
		$options[ $plan->get_id() ] = $plan->get_name();
	}

	return $options;
}

/**
 * Returns a product based on slug.
 *
 * @since 2.0.0
 *
 * @param string $product_slug The slug of the product.
 * @return Product|false
 */
function wu_get_product_by_slug($product_slug) {

	return Product::get_by('slug', $product_slug);
}

/**
 * Returns a single product defined by a particular column and value.
 *
 * @since 2.0.0
 *
 * @param string $column The column name.
 * @param mixed  $value The column value.
 * @return \WP_Ultimo\Models\Product|false
 */
function wu_get_product_by($column, $value) {

	return Product::get_by($column, $value);
}

/**
 * Creates a new product.
 *
 * @since 2.0.0
 *
 * @param array $product_data Product data.
 * @return Product|\WP_Error
 */
function wu_create_product($product_data) {

	$product_data = wp_parse_args(
		$product_data,
		[
			'name'                => false,
			'description'         => false,
			'currency'            => false,
			'pricing_type'        => false,
			'setup_fee'           => false,
			'parent_id'           => 0,
			'slug'                => false,
			'recurring'           => false,
			'trial_duration'      => 0,
			'trial_duration_unit' => 'day',
			'duration'            => 1,
			'duration_unit'       => 'day',
			'amount'              => false,
			'billing_cycles'      => false,
			'active'              => false,
			'type'                => false,
			'featured_image_id'   => 0,
			'list_order'          => 0,
			'date_created'        => wu_get_current_time('mysql', true),
			'date_modified'       => wu_get_current_time('mysql', true),
			'migrated_from_id'    => 0,
			'meta'                => [],
			'available_addons'    => [],
			'group'               => '',
		]
	);

	$product = new Product($product_data);

	$saved = $product->save();

	return is_wp_error($saved) ? $saved : $product;
}

/**
 * Returns a list of available product groups.
 *
 * @since 2.0.0
 */
function wu_get_product_groups(): array {

	global $wpdb;

	$query = "SELECT DISTINCT `product_group` FROM {$wpdb->base_prefix}wu_products WHERE `product_group` <> ''";

	$results = array_column($wpdb->get_results($query, ARRAY_A), 'product_group'); // phpcs:ignore

	return array_combine($results, $results);
}

/**
 * Checks if a product type should be treated as a plan.
 *
 * By default, only 'plan' type products are considered plans.
 * Addons can extend this via the 'wu_plan_product_types' filter
 * to add additional types (e.g., 'network' for multinetwork addon).
 *
 * @since 2.3.0
 *
 * @param string $type The product type to check.
 * @return bool Whether the type should be treated as a plan.
 */
function wu_is_plan_type(string $type): bool {

	/**
	 * Filter the product types that should be treated as plans.
	 *
	 * This filter allows addons to register additional product types
	 * that should be recognized as plans in validation and segregation.
	 *
	 * @since 2.3.0
	 * @param array $plan_types Array of product types to treat as plans.
	 * @return array
	 */
	$plan_types = apply_filters('wu_plan_product_types', ['plan']);

	return in_array($type, $plan_types, true);
}

/**
 * Checks if a product type has an independent billing cycle.
 *
 * Products with independent billing cycles keep their own duration
 * and duration unit regardless of the plan's billing period.
 * They are not subject to price variation lookups in the cart.
 *
 * @since 2.5.0
 *
 * @param string $type The product type to check.
 * @return bool
 */
function wu_has_independent_billing_cycle(string $type): bool {

	/**
	 * Filter the product types that have independent billing cycles.
	 *
	 * Products with independent billing cycles are not forced to match the
	 * plan's billing period in the cart. This is useful for products like
	 * domain registrations that always bill yearly regardless of whether
	 * the plan is monthly or annual.
	 *
	 * @since 2.5.0
	 * @param array $types Array of product types with independent billing cycles.
	 * @return array
	 */
	$types = apply_filters('wu_independent_billing_cycle_product_types', []);

	return in_array($type, $types, true);
}

/**
 * Takes a list of product objects and separates them into plan and addons.
 *
 * @since 2.0.0
 *
 * @param Product[] $products List of products.
 * @return array first element is the first plan found, the second is an array with all the other products.
 */
function wu_segregate_products($products) {

	$results = [false, []];

	foreach ($products as $product) {
		if (is_a($product, Product::class) === false) {
			$product = wu_get_product($product);

			if ( ! $product) {
				continue;
			}
		}

		if (wu_is_plan_type($product->get_type()) && false === $results[0]) {
			$results[0] = $product;
		} else {
			$results[1][] = $product;
		}
	}

	return $results;
}
