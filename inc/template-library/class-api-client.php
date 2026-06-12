<?php
/**
 * Template Library API Client.
 *
 * Fetches template products from the WooCommerce Store API.
 *
 * @package WP_Ultimo\Template_Library
 * @since 2.5.0
 */

namespace WP_Ultimo\Template_Library;

use WP_Error;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Template Library API Client for fetching template products.
 *
 * @since 2.5.0
 */
class API_Client {

	/**
	 * Base URL for the WooCommerce API.
	 *
	 * @since 2.5.0
	 * @var string
	 */
	private string $base_url;

	/**
	 * Constructor.
	 *
	 * @since 2.5.0
	 * @param string $base_url The base URL for the WooCommerce API.
	 */
	public function __construct(string $base_url) {

		$this->base_url = trailingslashit($base_url);
	}

	/**
	 * Executes the HTTP request.
	 *
	 * @since 2.5.0
	 * @param string $endpoint The request URL.
	 * @param array  $params   Query parameters.
	 * @param string $method   HTTP method.
	 * @return array|WP_Error API response or WP_Error on failure.
	 */
	private function execute_request(string $endpoint, array $params = [], string $method = 'GET') {

		$url = $this->base_url . 'wp-json/wc/store/v1/' . ltrim($endpoint, '/');

		if ('GET' === $method) {
			$url  = add_query_arg($params, $url);
			$args = [
				'method'  => 'GET',
				'timeout' => 30,
			];
		} else {
			$args = [
				'method'  => $method,
				'body'    => wp_json_encode($params),
				'headers' => [
					'Content-Type' => 'application/json',
				],
				'timeout' => 30,
			];
		}

		// Add authorization if available
		$addon_repo = \WP_Ultimo::get_instance()->get_addon_repository();

		$access_token = $addon_repo->get_access_token();
		if ($access_token) {
			$args['headers']['Authorization'] = 'Bearer ' . $access_token;
		}

		$response = wp_remote_request($url, $args);

		if (is_wp_error($response)) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code($response);
		$response_body = wp_remote_retrieve_body($response);

		if ($response_code < 200 || $response_code >= 300) {
			return new WP_Error(
				'template_api_error',
				sprintf(
					/* translators: %1$s: HTTP response code, %2$s: response body */
					__('Template API request failed with status %1$s: %2$s', 'ultimate-multisite'),
					$response_code,
					$response_body
				)
			);
		}

		$data = json_decode($response_body, true);

		if (null === $data) {
			return new WP_Error(
				'json_decode_error',
				__('Failed to decode API response JSON', 'ultimate-multisite')
			);
		}

		return $data;
	}

	/**
	 * Gets template products with specific metadata.
	 *
	 * @since 2.5.0
	 * @return array|WP_Error Array of template products or WP_Error on failure.
	 */
	public function get_templates() {

		$params = [
			'per_page'     => 100,
			'status'       => 'publish',
			'downloadable' => true,
			'tag'          => 'template',
		];

		$result = $this->execute_request('products', $params);

		if (is_wp_error($result)) {
			return $result;
		}

		// Parse and enhance template data
		return array_map([$this, 'parse_template_data'], $result);
	}

	/**
	 * Parses and enhances template data from the API response.
	 *
	 * @since 2.5.0
	 * @param array $template Raw template data from API.
	 * @return array Enhanced template data.
	 */
	private function parse_template_data(array $template): array {

		$extensions = $template['extensions']['wp-update-server-plugin'] ?? [];

		return [
			'id'                => $template['id'] ?? 0,
			'slug'              => $template['slug'] ?? '',
			'name'              => $template['name'] ?? '',
			'description'       => $template['description'] ?? '',
			// short_description and price_html are rendered with Vue v-html in the
			// Template Library grid; the data is fetched over HTTP from the remote
			// marketplace, so sanitize the HTML to allowed post markup to neutralize
			// any injected script while preserving legitimate formatting.
			'short_description' => wp_kses_post($template['short_description'] ?? ''),
			'price_html'        => wp_kses_post($template['price_html'] ?? ''),
			'permalink'         => $template['permalink'] ?? '',
			'is_free'           => empty($template['prices']['price'] ?? 0),
			'prices'            => $template['prices'] ?? [],
			'images'            => $template['images'] ?? [],
			'categories'        => $template['categories'] ?? [],

			// Template-specific metadata from extensions
			'icon'              => $extensions['icon'] ?? '',
			'download_url'      => $extensions['download_url'] ?? '',
			'author'            => $extensions['author']['display_name'] ?? 'Ultimate Multisite Team',
			'demo_url'          => $extensions['demo_url'] ?? '',
			'industry_type'     => $extensions['industry_type'] ?? '',
			'page_count'        => (int) ($extensions['page_count'] ?? 0),
			'included_plugins'  => $extensions['included_plugins'] ?? [],
			'included_themes'   => $extensions['included_themes'] ?? [],
			'template_version'  => $extensions['template_version'] ?? '1.0.0',
			'compatibility'     => [
				'wp_version' => $extensions['compatibility']['wp_version'] ?? '',
				'wu_version' => $extensions['compatibility']['wu_version'] ?? '',
			],

			// Internal tracking
			'installed'         => false,
		];
	}

	/**
	 * Gets a single template by slug.
	 *
	 * @since 2.5.0
	 * @param string $slug The template slug.
	 * @return array|WP_Error Template data or WP_Error on failure.
	 */
	public function get_template(string $slug) {

		$templates = $this->get_templates();

		if (is_wp_error($templates)) {
			return $templates;
		}

		foreach ($templates as $template) {
			if ($template['slug'] === $slug) {
				return $template;
			}
		}

		return new WP_Error(
			'template_not_found',
			sprintf(
				/* translators: %s: template slug */
				__('Template "%s" not found.', 'ultimate-multisite'),
				$slug
			)
		);
	}
}
