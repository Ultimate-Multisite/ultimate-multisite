<?php
/**
 * Site Functions
 *
 * @package WP_Ultimo\Functions
 * @since   2.0.0
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Returns the current site.
 *
 * @since 2.0.0
 * @return \WP_Ultimo\Models\Site|null
 */
function wu_get_current_site() {

	if ( ! is_multisite()) {
		return null;
	}

	static $sites = array();
	$blog_id      = get_current_blog_id();

	if ( ! isset($sites[ $blog_id ]) ) {
		$sites[ $blog_id ] = new \WP_Ultimo\Models\Site(get_blog_details($blog_id));
	}
	return $sites[ $blog_id ];
}

/**
 * Returns the site object
 *
 * @since 2.0.0
 *
 * @param int $id The id of the site.
 * @return \WP_Ultimo\Models\Site|false
 */
function wu_get_site($id) {

	return \WP_Ultimo\Models\Site::get_by_id($id);
}

/**
 * Gets a site based on the hash.
 *
 * @since 2.0.0
 *
 * @param string $hash The hash for the payment.
 * @return \WP_Ultimo\Models\Site|false
 */
function wu_get_site_by_hash($hash) {

	return \WP_Ultimo\Models\Site::get_by_hash($hash);
}

/**
 * Returns blog IDs that match a search term via mapped domain or site title.
 *
 * BerlinDB's schema-based search only covers the columns defined in
 * Sites_Schema (blog_id, domain, path). Mapped domains and site titles
 * (blogname) live outside that schema, so we collect their IDs here and
 * merge them into the main query in wu_get_sites().
 *
 * @since 2.5.0
 *
 * @param string       $search      The search term.
 * @param array|false  $blog_id__in Optional array of blog IDs to restrict the search to.
 * @param int          $limit       Maximum number of IDs to return.
 * @return int[]
 */
function wu_get_sites_extra_ids_for_search($search, $blog_id__in = false, $limit = 100) {

	// Find sites with a matching mapped domain.
	$domain_ids = wu_get_domains(
		[
			'number'      => $limit,
			'search'      => '*' . $search . '*',
			'fields'      => ['blog_id'],
			'blog_id__in' => $blog_id__in,
		]
	);

	$domain_ids = array_column($domain_ids, 'blog_id');

	/*
	 * Find sites whose title (blogname) matches the search term.
	 * The title is stored in wp_blogmeta and is not part of the BerlinDB
	 * sites schema, so we query it directly.
	 */
	global $wpdb;

	$title_search_like = '%' . $wpdb->esc_like($search) . '%';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$title_ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT blog_id FROM {$wpdb->blogmeta} WHERE meta_key = 'blogname' AND meta_value LIKE %s",
			$title_search_like
		)
	);

	$title_ids = array_map('intval', (array) $title_ids);

	// Merge and deduplicate.
	return array_values(array_unique(array_merge($domain_ids, $title_ids)));
}

/**
 * Queries sites.
 *
 * @since 2.0.0
 *
 * @param array $query Query arguments.
 * @return \WP_Ultimo\Models\Site[]
 */
function wu_get_sites($query = []) {
	if (empty($query['number'])) {
		$query['number'] = 100;
	}

	// If we're just counting, do a simple count query.
	if ( ! empty($query['count'])) {
		if ( ! empty($query['search'])) {
			/*
			 * When counting with a search term we need to account for sites
			 * matched by mapped domain or by title (blogname), neither of which
			 * is in the BerlinDB schema.  Collect the extra IDs, exclude them
			 * from the schema count, and add them back in.
			 */
			$extra_ids = wu_get_sites_extra_ids_for_search($query['search'], $query['blog_id__in'] ?? false, $query['number']);

			if ( ! empty($extra_ids)) {
				$count_query                    = $query;
				$existing_not_in                = isset($count_query['blog_id__not_in']) ? (array) $count_query['blog_id__not_in'] : [];
				$count_query['blog_id__not_in'] = array_unique(array_merge($existing_not_in, $extra_ids));
				unset($count_query['search']);

				$schema_count = (int) \WP_Ultimo\Models\Site::query($count_query);

				return $schema_count + count($extra_ids);
			}
		}

		return \WP_Ultimo\Models\Site::query($query);
	}

	if ( ! empty($query['search'])) {
		// We also want to find sites with a matching mapped domain or title.
		$extra_ids = wu_get_sites_extra_ids_for_search($query['search'], $query['blog_id__in'] ?? false, $query['number']);

		if ( ! empty($extra_ids)) {
			$sites_with_extra_query                = $query;
			$sites_with_extra_query['blog_id__in'] = $extra_ids;

			unset($sites_with_extra_query['search']);
			$sites_by_extra = \WP_Ultimo\Models\Site::query($sites_with_extra_query);

			$query['number']         -= count($sites_by_extra);
			$existing_not_in          = isset($query['blog_id__not_in']) ? (array) $query['blog_id__not_in'] : [];
			$query['blog_id__not_in'] = array_unique(array_merge($existing_not_in, $extra_ids));

			if ($query['number'] <= 0) {
				// We reached the limit already.
				return $sites_by_extra;
			}
			$sites = \WP_Ultimo\Models\Site::query($query);
			// Return matches by domain/title first.
			return array_merge($sites_by_extra, $sites);
		}
	}

	return \WP_Ultimo\Models\Site::query($query);
}

/**
 * Returns the list of Site Templates.
 *
 * @since 2.0.0
 *
 * @param array $query Query arguments.
 * @return array
 */
function wu_get_site_templates($query = []) {

	$query = wp_parse_args(
		$query,
		[
			'number' => 9999, // By default, we try to get ALL available templates.
		]
	);

	return \WP_Ultimo\Models\Site::get_all_by_type('site_template', $query);
}

/**
 * Parses a URL and breaks it into different parts
 *
 * @since 2.0.0
 *
 * @param string $domain The domain to break up.
 * @return object
 */
function wu_handle_site_domain($domain) {

	global $current_site;

	if (! str_contains($domain, 'http')) {
		$domain = "https://{$domain}";
	}

	$parsed = wp_parse_url($domain);

	return (object) $parsed;
}

/**
 * Creates a new site.
 *
 * @since 2.0.0
 *
 * @param array $site_data Site data.
 * @return \WP_Error|\WP_Ultimo\Models\Site
 */
function wu_create_site($site_data) {

	$current_site = get_current_site();

	$network_domain = $current_site->domain;

	// Mode-aware domain/path normalisation. When the caller passes a path
	// but no explicit domain (or only the network root domain) on a
	// subdomain multisite install, the path is meaningless — WordPress
	// won't route to it. Convert the supplied path into a subdomain
	// prefix instead so the site is reachable. Callers that pass an
	// explicit non-root domain are respected as-is so subdomain/subdir
	// mixing still works.
	$path_supplied   = isset($site_data['path']) && '' !== $site_data['path'] && '/' !== $site_data['path'];
	$domain_supplied = isset($site_data['domain']) && '' !== $site_data['domain'] && $site_data['domain'] !== $network_domain;

	if (is_multisite() && is_subdomain_install() && $path_supplied && ! $domain_supplied) {
		$slug                 = trim((string) $site_data['path'], '/');
		$bare_network_domain  = preg_replace('/^www\./i', '', (string) $network_domain);
		$site_data['domain']  = "{$slug}.{$bare_network_domain}";
		$site_data['path']    = '/';
	}

	$site_data = wp_parse_args(
		$site_data,
		[
			'domain'                => $network_domain,
			'path'                  => '/',
			'title'                 => false,
			'type'                  => false,
			'template_id'           => false,
			'featured_image_id'     => 0,
			'duplication_arguments' => false,
			'public'                => true,
		]
	);

	$site = new \WP_Ultimo\Models\Site($site_data);

	$site->set_public($site_data['public']);

	$saved = $site->save();

	return is_wp_error($saved) ? $saved : $site;
}

/**
 * Returns the correct domain/path combination when creating a new site.
 *
 * @since 2.0.0
 *
 * @param string      $path_or_subdomain The site path.
 * @param string|bool $base_domain The domain selected.
 * @return object Object with a domain and path properties.
 */
function wu_get_site_domain_and_path($path_or_subdomain = '/', $base_domain = false) {

	global $current_site;

	$path_or_subdomain = trim($path_or_subdomain, '/');

	$domain = $base_domain ?: $current_site->domain;

	$d = new \stdClass();

	if (is_multisite() && is_subdomain_install()) {
		/*
		 * Treat for the www. case.
		 */
		$domain = str_replace('www.', '', (string) $domain);

		$d->domain = "{$path_or_subdomain}.{$domain}";

		$d->path = '/';

		return $d;
	}

	$d->domain = $domain;

	$d->path = "/{$path_or_subdomain}";

	/**
	 * Allow developers to manipulate the domain/path pairs.
	 *
	 * This can be useful for a number of things, such as implementing some
	 * sort of staging solution, different servers, etc.
	 *
	 * @since 2.0.0
	 * @param object $d The current object containing a domain and path keys.
	 * @param string $path_or_subdomain The original path/subdomain passed to the function.
	 * @return object An object containing a domain and path keys.
	 */
	return apply_filters('wu_get_site_domain_and_path', $d, $path_or_subdomain);
}

/**
 * Generates a URL-safe slug from a site title.
 *
 * Takes a site title like "Your Cool Site" and converts it to "yourcoolsite"
 * for use as a subdomain or path.
 *
 * @since 2.0.0
 *
 * @param string $site_title The site title to convert.
 * @return string URL-safe slug.
 */
function wu_generate_site_url_from_title($site_title) {

	if (empty($site_title)) {
		return '';
	}

	// Convert to lowercase and remove HTML entities
	$slug = strtolower(html_entity_decode(trim((string) $site_title), ENT_QUOTES, 'UTF-8'));

	// Remove any remaining non-alphanumeric characters
	$slug = preg_replace('/[^a-z0-9-]/', '', $slug);

	// Fallback if empty after cleaning
	if (empty($slug)) {
		$slug = 'site' . wp_rand(1000, 9999);
	} elseif (is_numeric($slug[0]) || '-' === $slug[0]) {
		// Ensure it starts with a letter (WordPress requirement)
		$slug = 'site' . $slug;
	}
	return $slug;
}

/**
 * Generates a site title from an email address.
 *
 * Takes an email like "john.doe@example.com" and converts it to "John Doe Site"
 * or falls back to using the domain part if the username is generic.
 *
 * @since 2.0.0
 *
 * @param string $email The email address to use for generation.
 * @return string Generated site title.
 */
function wu_generate_site_title_from_email($email) {

	if (empty($email) || ! is_email($email)) {
		return '';
	}

	$email_parts = explode('@', $email);
	$username    = $email_parts[0];
	$domain      = $email_parts[1];

	// Common generic email prefixes to avoid
	$generic_prefixes = [
		'admin',
		'administrator',
		'info',
		'contact',
		'support',
		'help',
		'sales',
		'marketing',
		'hello',
		'hi',
		'mail',
		'email',
		'test',
		'demo',
		'sample',
		'example',
		'noreply',
		'no-reply',
	];

	$title_parts = [];

	// Check if username is not generic
	if (! in_array(strtolower($username), $generic_prefixes, true)) {
		// Split on common separators
		$name_parts = preg_split('/[._\-+]/', $username);

		foreach ($name_parts as $part) {
			$part = trim($part);
			if (! empty($part) && ! is_numeric($part)) {
				// Capitalize first letter of each part
				$title_parts[] = ucfirst(strtolower($part));
			}
		}
	}

	// If we don't have good name parts, use domain
	if (empty($title_parts)) {
		$domain_part   = strtok($domain, '.');
		$title_parts[] = ucfirst($domain_part);
	}

	// Create title
	$title = implode(' ', $title_parts);

	// Add "Site" suffix if title is short
	if (strlen($title) < 8) {
		$title .= ' Site';
	}

	return $title;
}

/**
 * Generates a unique site URL with collision detection.
 *
 * Takes a base URL slug and ensures it's unique by checking against existing sites.
 * Appends numbers if needed to avoid collisions.
 *
 * @since 2.0.0
 *
 * @param string $base_url The base URL slug to use.
 * @param string $domain The domain to check against (optional).
 * @return string Unique site URL.
 */
function wu_generate_unique_site_url($base_url, $domain = null) {

	if (empty($base_url)) {
		$base_url = 'site' . wp_rand(1000, 9999);
	}

	// Clean the base URL
	$base_url = wu_generate_site_url_from_title($base_url);

	$site_url = $base_url;
	$counter  = 0;

	// Keep checking until we find a unique URL
	while (true) {
		$d = wu_get_site_domain_and_path($site_url, $domain);

		if (! domain_exists($d->domain, $d->path)) {
			break;
		}

		++$counter;
		$site_url = $base_url . $counter;

		// Safety net to prevent infinite loops
		if ($counter > 9999) {
			$site_url = $base_url . wp_rand(10000, 99999);
			break;
		}
	}

	return $site_url;
}
