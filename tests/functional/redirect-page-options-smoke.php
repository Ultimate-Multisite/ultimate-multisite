<?php
/**
 * Dev-only redirect picker regression check: wp eval-file <this file>.
 *
 * @package WP_Ultimo
 * @subpackage Tests
 */

if (! defined('WP_CLI') || ! WP_CLI || ! in_array(wp_get_environment_type(), ['local', 'development'], true)) {
	throw new RuntimeException('Run only through WP-CLI in an isolated development environment.');
}

global $wpdb, $post;

$check = static function ($condition, $message) {
	if (! $condition) {
		throw new RuntimeException($message);
	}
};

$elements = [
	[\WP_Ultimo\UI\My_Sites_Element::get_instance(), 'custom_manage_page', __('Current Page', 'ultimate-multisite')],
	[\WP_Ultimo\UI\Current_Site_Element::get_instance(), 'breadcrumbs_my_sites_page', __('Current Page', 'ultimate-multisite')],
	[\WP_Ultimo\UI\Site_Actions_Element::get_instance(), 'redirect_after_delete', __('Default', 'ultimate-multisite')],
];
$manager  = \WP_Ultimo\Builders\Block_Editor\Block_Editor_Widget_Manager::get_instance();
$queries  = $wpdb->num_queries;

foreach ($elements as [$element, $field]) {
	$check(is_callable($element->fields()[ $field ]['options']), 'Options must be lazy.');
	$check(isset($manager->get_attributes_from_fields($element)[ $field ]), 'Attribute schema changed.');
}
$check($wpdb->num_queries === $queries, 'Building fields/attributes must not query pages.');

$ids        = [];
$saved_post = $post;
$restrict   = null;
$transform  = null;

try {
	foreach (['Zulu parent', 'Alpha child', '', 'Draft page'] as $index => $title) {
		$id = wp_insert_post(
			[
				'post_type'    => 'page',
				'post_title'   => $title,
				'post_content' => 'Content must remain available through the normal post cache.',
				'post_status'  => 3 === $index ? 'draft' : 'publish',
				'post_parent'  => 1 === $index ? $ids[0] : 0,
			],
			true
		);
		$check(! is_wp_error($id), 'Could not create isolated fixture.');
		$ids[] = $id;
	}

	// Compare legacy behavior on just the fixtures, not every large content page.
	$restrict = static function ($args, $page_args) use ($ids, $check) {
		$check(isset($page_args['sort_column'], $page_args['hierarchical'], $page_args['exclude']), 'Missing legacy page arguments.');
		$args['post__in'] = $ids;
		return $args;
	};
	$transform = static function ($pages) {
		return array_map(
			static function ($page) {
				$copy             = clone $page;
				$copy->post_title = 'Filtered: ' . $page->post_title;
				return $copy;
			},
			$pages
		);
	};
	add_filter('get_pages_query_args', $restrict, 10, 2);
	add_filter('get_pages', $transform);

	foreach ([null, get_post($ids[1]), get_post($ids[0])] as $current_post) {
		$post   = $current_post;
		$legacy = get_pages(['exclude' => [get_the_ID()]]); // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
		foreach ($elements as [$element, $field, $label]) {
			$expected = [0 => $label];
			foreach ($legacy as $page) {
				$expected[ $page->ID ] = $page->post_title;
			}
			$options = call_user_func($element->fields()[ $field ]['options']);
			$check($expected === $options, 'Titles, ordering, hierarchy, status or exclusion changed.');
		}
	}
	remove_filter('get_pages_query_args', $restrict, 10);
	remove_filter('get_pages', $transform);
	$restrict = null;
	$transform = null;
	$post     = null;

	// Exercise real editor consolidation against the complete large dev dataset.
	wp_cache_delete($ids[0], 'posts');
	$before   = memory_get_usage(true);
	$requests = [];
	$capture  = static function ($sql, $query) use (&$requests) {
		if ('page' === $query->get('post_type')) {
			$requests[] = $sql;
		}
		return $sql;
	};
	add_filter('posts_request', $capture, 10, 2);
	try {
		foreach ($elements as [$element, $field, $label]) {
			$block   = $manager->load_block_settings([], $element)[0];
			$options = $block['fields'][ $field ]['options'];
			$check(is_array($options) && $label === $options[0], 'Editor did not resolve options.');
			$check(isset($options[ $ids[0] ], $options[ $ids[1] ]), 'Editor lost published choices.');
			$check(! isset($options[ $ids[3] ]), 'Editor exposed a draft choice.');
		}
	} finally {
		remove_filter('posts_request', $capture, 10);
	}
	$check(3 === count($requests), 'Expected one lightweight query per editor picker.');
	foreach ($requests as $sql) {
		$select = explode('FROM', $sql, 2)[0];
		$check(! str_contains($select, '*') && ! str_contains($select, 'post_content'), 'Picker selected full posts.');
	}
	$check(false === wp_cache_get($ids[0], 'posts'), 'Picker must not cache a partial post.');
	$check('Content must remain available through the normal post cache.' === get_post($ids[0])->post_content, 'Partial query poisoned the post cache.');

	echo wp_json_encode(
		[
			'fields_and_attributes_queries' => 0,
			'legacy_options_match'          => true,
			'editor_choices'                => count($options),
			'editor_queries'                => count($requests),
			'editor_memory_delta_bytes'     => memory_get_usage(true) - $before,
			'post_cache_intact'             => true,
		]
	) . PHP_EOL;
} finally {
	if ($restrict) {
		remove_filter('get_pages_query_args', $restrict, 10);
	}
	if ($transform) {
		remove_filter('get_pages', $transform);
	}
	$post = $saved_post;
	foreach ($ids as $id) {
		wp_delete_post($id, true);
	}
}
