<?php
/**
 * Rewrites configured production URLs to an environment URL at runtime.
 *
 * @package WP_Ultimo
 * @subpackage Domain_Mapping
 * @since 2.15.2
 */

namespace WP_Ultimo\Domain_Mapping;

defined('ABSPATH') || exit;

/**
 * Runtime-only URL rewriter proof of concept.
 *
 * This class never changes database values. It maps an incoming environment
 * URL back to the canonical multisite URL during bootstrap, then rewrites
 * generated URLs and rendered content to the environment URL.
 *
 * @since 2.15.2
 */
final class Runtime_URL_Rewriter {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * Normalized source-to-target mappings.
	 *
	 * @since 2.15.2
	 * @var array
	 */
	private $mappings = [];

	/**
	 * Mapping selected by the current request host and path.
	 *
	 * @since 2.15.2
	 * @var array|null
	 */
	private $active_mapping;

	/**
	 * Register early bootstrap hooks when runtime rewriting is configured.
	 *
	 * @since 2.15.2
	 * @return void
	 */
	public function init(): void {

		$this->mappings = $this->get_configured_mappings();

		if (empty($this->mappings)) {
			return;
		}

		$this->active_mapping = $this->find_request_mapping();

		if (empty($this->active_mapping)) {
			return;
		}

		add_filter('pre_get_site_by_path', [$this, 'resolve_site'], 1, 5);
		add_filter('pre_get_network_by_path', [$this, 'resolve_network'], 1, 5);
		add_action('ms_loaded', [$this, 'register_rewrite_filters'], 999);
	}

	/**
	 * Resolve an environment request against the canonical site address.
	 *
	 * @since 2.15.2
	 *
	 * @param null|false|\WP_Site $site     Site already resolved by an earlier filter.
	 * @param string              $domain   Requested domain.
	 * @param string              $path     Requested path.
	 * @param int|null            $segments Suggested path segment count.
	 * @param string[]            $paths    Candidate paths.
	 * @return null|false|\WP_Site
	 */
	public function resolve_site($site, $domain, $path, $segments = null, $paths = []) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter

		if (null !== $site || ! $this->request_matches_active_mapping($domain, $path)) {
			return $site;
		}

		$source_path = $this->translate_request_path($path, $this->active_mapping);

		remove_filter('pre_get_site_by_path', [$this, 'resolve_site'], 1);
		$site = get_site_by_path($this->active_mapping['source']['authority'], $source_path);
		add_filter('pre_get_site_by_path', [$this, 'resolve_site'], 1, 5);

		return $site;
	}

	/**
	 * Resolve an environment request against the canonical network address.
	 *
	 * @since 2.15.2
	 *
	 * @param null|false|\WP_Network $network  Network already resolved by an earlier filter.
	 * @param string                 $domain   Requested domain.
	 * @param string                 $path     Requested path.
	 * @param int|null               $segments Suggested path segment count.
	 * @param string[]               $paths    Candidate paths.
	 * @return null|false|\WP_Network
	 */
	public function resolve_network($network, $domain, $path, $segments = null, $paths = []) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter

		if (null !== $network || ! $this->request_matches_active_mapping($domain, $path)) {
			return $network;
		}

		$source_path = $this->translate_request_path($path, $this->active_mapping);

		remove_filter('pre_get_network_by_path', [$this, 'resolve_network'], 1);
		$network = get_network_by_path($this->active_mapping['source']['authority'], $source_path);
		add_filter('pre_get_network_by_path', [$this, 'resolve_network'], 1, 5);

		return $network;
	}

	/**
	 * Register filters covering core URL generation and rendered content.
	 *
	 * @since 2.15.2
	 * @return void
	 */
	public function register_rewrite_filters(): void {

		$string_and_array_filters = [
			'option_home',
			'option_siteurl',
			'home_url',
			'site_url',
			'network_home_url',
			'network_site_url',
			'content_url',
			'plugins_url',
			'includes_url',
			'theme_file_uri',
			'stylesheet_directory_uri',
			'template_directory_uri',
			'script_loader_src',
			'style_loader_src',
			'wp_get_attachment_url',
			'post_link',
			'page_link',
			'post_type_link',
			'attachment_link',
			'term_link',
			'author_link',
			'day_link',
			'month_link',
			'year_link',
			'feed_link',
			'get_canonical_url',
			'redirect_canonical',
			'wp_redirect',
			'login_url',
			'logout_url',
			'lostpassword_url',
			'register_url',
			'the_content',
			'the_excerpt',
			'the_content_feed',
			'the_excerpt_rss',
			'widget_text',
			'widget_text_content',
			'render_block',
			'oembed_result',
			'embed_oembed_html',
			'wp_audio_shortcode',
			'wp_video_shortcode',
			'retrieve_password_message',
			'wp_prepare_attachment_for_js',
			'wp_resource_hints',
			'wp_mail',
		];

		foreach ($string_and_array_filters as $filter) {
			add_filter($filter, [$this, 'rewrite_value'], PHP_INT_MAX);
		}

		add_filter('upload_dir', [$this, 'rewrite_upload_directory'], PHP_INT_MAX);
		add_filter('wp_calculate_image_srcset', [$this, 'rewrite_value'], PHP_INT_MAX);
		add_filter('rest_post_dispatch', [$this, 'rewrite_rest_response'], PHP_INT_MAX, 3);
		add_filter('allowed_redirect_hosts', [$this, 'allow_target_hosts'], PHP_INT_MAX);

		/**
		 * Fires after the proof-of-concept runtime URL filters are registered.
		 *
		 * @since 2.15.2
		 *
		 * @param callable $callback URL and rendered-value rewrite callback.
		 * @param self     $rewriter Runtime URL rewriter instance.
		 */
		do_action('wu_runtime_url_rewriter_register_filters', [$this, 'rewrite_value'], $this);
	}

	/**
	 * Recursively rewrite strings in a rendered value.
	 *
	 * @since 2.15.2
	 *
	 * @param mixed $value Filtered value.
	 * @return mixed
	 */
	public function rewrite_value($value) {

		if (is_string($value)) {
			return $this->rewrite_string($value);
		}

		if (is_array($value)) {
			foreach ($value as $key => $item) {
				$value[ $key ] = $this->rewrite_value($item);
			}
		}

		return $value;
	}

	/**
	 * Rewrite configured URL forms in a string without touching bare domains.
	 *
	 * Absolute, protocol-relative, JSON-escaped, and URL-encoded forms are
	 * covered. Bare domains are deliberately ignored to avoid changing email
	 * addresses or unrelated text.
	 *
	 * @since 2.15.2
	 *
	 * @param string $value String that may contain canonical URLs.
	 * @return string
	 */
	public function rewrite_string($value) {

		if ('' === $value) {
			return $value;
		}

		foreach ($this->mappings as $mapping) {
			$target          = $mapping['target']['authority'] . $mapping['target']['base_path'];
			$absolute_target = $mapping['target']['scheme'] . '://' . $target;
			$plain_source    = preg_quote($mapping['source']['authority'], '#')
				. '(?-i:' . preg_quote($mapping['source']['base_path'], '#') . ')';
			$plain_boundary  = '(?=$|[/?\#\s"\'<>)\]},;&])';

			$value = $this->replace_pattern(
				$value,
				'#https?://' . $plain_source . $plain_boundary . '#i',
				$absolute_target
			);
			$value = $this->replace_pattern(
				$value,
				'#(?<!:)//' . $plain_source . $plain_boundary . '#i',
				'//' . $target
			);

			$escaped_target   = str_replace('/', '\\/', $absolute_target);
			$escaped_source   = preg_quote($mapping['source']['authority'], '#')
				. '(?-i:' . preg_quote(str_replace('/', '\\/', $mapping['source']['base_path']), '#') . ')';
			$escaped_boundary = '(?=$|\\\\/|[?\#\s"\'<>)\]},;&])';

			$value = $this->replace_pattern(
				$value,
				'#https?:\\\\/\\\\/' . $escaped_source . $escaped_boundary . '#i',
				$escaped_target
			);

			$encoded_source   = preg_quote(rawurlencode($mapping['source']['authority']), '#')
				. $this->get_encoded_path_pattern($mapping['source']['base_path']);
			$encoded_boundary = '(?=$|%2F|%3F|%23|%20|%22|%27|%3C|%3E|%29|%5D|%7D|%2C|%3B|%26|[/?\#\s"\'<>)\]},;&])';

			$value = $this->replace_pattern(
				$value,
				'#https?%3A%2F%2F' . $encoded_source . $encoded_boundary . '#i',
				rawurlencode($absolute_target)
			);
			$value = $this->replace_pattern(
				$value,
				'#%2F%2F' . $encoded_source . $encoded_boundary . '#i',
				rawurlencode('//' . $target)
			);
		}

		return $value;
	}

	/**
	 * Replace a URL pattern while treating the replacement as a literal string.
	 *
	 * @since 2.15.2
	 *
	 * @param string $value       Subject string.
	 * @param string $pattern     Regular expression.
	 * @param string $replacement Literal replacement.
	 * @return string
	 */
	private function replace_pattern($value, $pattern, $replacement) {

		$result = preg_replace_callback(
			$pattern,
			static function () use ($replacement) {

				return $replacement;
			},
			$value
		);

		return is_string($result) ? $result : $value;
	}

	/**
	 * Build a pattern with case-sensitive path text and flexible percent escapes.
	 *
	 * @since 2.15.2
	 *
	 * @param string $path URL path.
	 * @return string
	 */
	private function get_encoded_path_pattern($path) {

		$encoded = rawurlencode($path);
		$parts   = preg_split('/(%[0-9A-F]{2})/', $encoded, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

		if (! is_array($parts)) {
			return '(?-i:' . preg_quote($encoded, '#') . ')';
		}

		$pattern = '';

		foreach ($parts as $part) {
			if (1 === preg_match('/^%[0-9A-F]{2}$/', $part)) {
				$pattern .= '(?i:' . preg_quote($part, '#') . ')';
			} else {
				$pattern .= '(?-i:' . preg_quote($part, '#') . ')';
			}
		}

		return $pattern;
	}

	/**
	 * Rewrite upload URL fields without changing filesystem paths.
	 *
	 * @since 2.15.2
	 *
	 * @param array $uploads Upload directory data.
	 * @return array
	 */
	public function rewrite_upload_directory($uploads) {

		foreach (['url', 'baseurl'] as $key) {
			if (isset($uploads[ $key ])) {
				$uploads[ $key ] = $this->rewrite_value($uploads[ $key ]);
			}
		}

		return $uploads;
	}

	/**
	 * Rewrite URLs contained in a REST response payload.
	 *
	 * @since 2.15.2
	 *
	 * @param mixed            $response REST response.
	 * @param \WP_REST_Server  $server   REST server.
	 * @param \WP_REST_Request $request  REST request.
	 * @return mixed
	 */
	public function rewrite_rest_response($response, $server, $request) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter

		if ($response instanceof \WP_HTTP_Response) {
			$response->set_data($this->rewrite_value($response->get_data()));
		}

		return $response;
	}

	/**
	 * Permit safe redirects to configured environment hosts.
	 *
	 * @since 2.15.2
	 *
	 * @param string[] $hosts Allowed redirect hosts.
	 * @return string[]
	 */
	public function allow_target_hosts($hosts) {

		foreach ($this->mappings as $mapping) {
			$hosts[] = $mapping['target']['host'];
		}

		return array_values(array_unique($hosts));
	}

	/**
	 * Get normalized mappings for diagnostics and extensions.
	 *
	 * @since 2.15.2
	 * @return array
	 */
	public function get_mappings() {

		return $this->mappings;
	}

	/**
	 * Read supported constants and environment variables.
	 *
	 * @since 2.15.2
	 * @return array
	 */
	private function get_configured_mappings() {

		$config = $this->get_config_value('WP_ULTIMO_RUNTIME_URL_MAP');

		if (is_string($config) && '' !== $config) {
			$decoded = json_decode($config, true);
			$config  = is_array($decoded) ? $decoded : [];
		}

		if (! is_array($config)) {
			$config = [];
		}

		if (empty($config)) {
			$target = $this->get_config_value('WP_ULTIMO_RUNTIME_URL_TO');

			if (empty($target)) {
				$target = $this->get_config_value('WP_ULTIMO_RUNTIME_URL');
			}

			$source = $this->get_config_value('WP_ULTIMO_RUNTIME_URL_FROM');

			if (empty($source) && ! empty($target) && defined('DOMAIN_CURRENT_SITE')) {
				$target_parts = $this->normalize_url($target);
				$scheme       = $target_parts ? $target_parts['scheme'] : 'https';
				$network_path = defined('PATH_CURRENT_SITE') ? PATH_CURRENT_SITE : '/';
				$source       = $scheme . '://' . DOMAIN_CURRENT_SITE . $network_path;
			}

			if (! empty($source) && ! empty($target)) {
				$config = [$source => $target];
			}
		}

		/**
		 * Filters runtime URL mappings before they are normalized.
		 *
		 * @since 2.15.2
		 *
		 * @param array $config Source URL to target URL mappings.
		 */
		$config = apply_filters('wu_runtime_url_rewriter_mappings', $config);

		$mappings = [];

		foreach ($config as $source_url => $target_url) {
			$source = $this->normalize_url($source_url);
			$target = $this->normalize_url($target_url);

			if (! $source || ! $target) {
				continue;
			}

			$mappings[] = [
				'source' => $source,
				'target' => $target,
			];
		}

		usort(
			$mappings,
			static function ($left, $right) {

				$left_length  = strlen($left['source']['authority'] . $left['source']['base_path']);
				$right_length = strlen($right['source']['authority'] . $right['source']['base_path']);

				return $right_length <=> $left_length;
			}
		);

		return $mappings;
	}

	/**
	 * Read a constant first, then an environment variable of the same name.
	 *
	 * @since 2.15.2
	 *
	 * @param string $name Configuration name.
	 * @return mixed
	 */
	private function get_config_value($name) {

		if (defined($name)) {
			return constant($name);
		}

		if (function_exists('getenv')) {
			$value = getenv($name);

			if (false !== $value) {
				return $value;
			}
		}

		return null;
	}

	/**
	 * Normalize a configured HTTP URL into routing components.
	 *
	 * @since 2.15.2
	 *
	 * @param mixed $url Configured URL.
	 * @return array|false
	 */
	private function normalize_url($url) {

		if (! is_string($url) || '' === trim($url)) {
			return false;
		}

		$url = trim($url);

		if (! str_contains($url, '://')) {
			$url = 'https://' . ltrim($url, '/');
		}

		// wp_parse_url() may not be available in every sunrise integration.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		$parts = parse_url($url);

		if (
			! is_array($parts)
			|| empty($parts['host'])
			|| empty($parts['scheme'])
			|| isset($parts['user'])
			|| isset($parts['pass'])
			|| isset($parts['query'])
			|| isset($parts['fragment'])
		) {
			return false;
		}

		$scheme = strtolower($parts['scheme']);

		if (! in_array($scheme, ['http', 'https'], true)) {
			return false;
		}

		$host      = strtolower($parts['host']);
		$authority = $host . (isset($parts['port']) ? ':' . (int) $parts['port'] : '');
		$base_path = isset($parts['path']) ? '/' . trim($parts['path'], '/') : '';

		return [
			'scheme'    => $scheme,
			'host'      => $host,
			'authority' => $authority,
			'base_path' => '/' === $base_path ? '' : rtrim($base_path, '/'),
		];
	}

	/**
	 * Find the mapping represented by the current HTTP request.
	 *
	 * @since 2.15.2
	 * @return array|null
	 */
	private function find_request_mapping() {

		$host = isset($_SERVER['HTTP_HOST']) ? strtolower(sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST']))) : '';
		// The request URI is parsed as a path and only compared with configured paths; preserving percent-encoding is required.
		$path = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ('' === $host) {
			return null;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		$parsed_path = parse_url($path, PHP_URL_PATH);
		$path        = is_string($parsed_path) ? $parsed_path : '/';
		$selected    = null;
		$best_length = -1;

		foreach ($this->mappings as $mapping) {
			if (! $this->request_matches_mapping($host, $path, $mapping)) {
				continue;
			}

			$target_length = strlen($mapping['target']['authority'] . $mapping['target']['base_path']);

			if ($target_length > $best_length) {
				$selected    = $mapping;
				$best_length = $target_length;
			}
		}

		return $selected;
	}

	/**
	 * Check a bootstrap request against the active mapping.
	 *
	 * @since 2.15.2
	 *
	 * @param string $domain Requested domain.
	 * @param string $path   Requested path.
	 * @return bool
	 */
	private function request_matches_active_mapping($domain, $path) {

		return ! empty($this->active_mapping)
			&& $this->request_matches_mapping(strtolower($domain), $path, $this->active_mapping);
	}

	/**
	 * Check a host and path against a target mapping.
	 *
	 * @since 2.15.2
	 *
	 * @param string $host    Request host, optionally including a port.
	 * @param string $path    Request path.
	 * @param array  $mapping Normalized mapping.
	 * @return bool
	 */
	private function request_matches_mapping($host, $path, $mapping) {

		if ($host !== $mapping['target']['authority']) {
			return false;
		}

		$target_path = $mapping['target']['base_path'];

		return '' === $target_path
			|| $path === $target_path
			|| str_starts_with($path, $target_path . '/');
	}

	/**
	 * Translate a target request path to its canonical source path.
	 *
	 * @since 2.15.2
	 *
	 * @param string $path    Target request path.
	 * @param array  $mapping Normalized mapping.
	 * @return string
	 */
	private function translate_request_path($path, $mapping) {

		$target_path = $mapping['target']['base_path'];
		$source_path = $mapping['source']['base_path'];
		$relative    = '' === $target_path ? $path : substr($path, strlen($target_path));
		$translated  = '/' . trim($source_path . '/' . ltrim($relative, '/'), '/');

		if ('/' !== $translated && str_ends_with($path, '/')) {
			$translated .= '/';
		}

		return $translated;
	}
}
