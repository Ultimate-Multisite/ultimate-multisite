<?php
/**
 * MCP Adapter initialization and management.
 *
 * @package WP_Ultimo
 * @subpackage MCP
 * @since 2.5.0
 */

namespace WP_Ultimo;

use Psr\Log\LogLevel;
use WP\MCP\Core\McpAdapter as McpAdapterCore;
use WP\MCP\Transport\HttpTransport;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * MCP Adapter integration for Ultimate Multisite.
 *
 * Initializes the WordPress MCP adapter and manages the integration
 * with the Abilities API to expose Ultimate Multisite functionality
 * via the Model Context Protocol.
 *
 * @since 2.5.0
 */
class MCP_Adapter implements \WP_Ultimo\Interfaces\Singleton {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * Minimum supported canonical MCP Adapter plugin version.
	 *
	 * @since 2.15.2
	 * @var string
	 */
	private const MINIMUM_MCP_ADAPTER_VERSION = '0.6.1';

	/**
	 * Canonical MCP Adapter plugin basename.
	 *
	 * @since 2.15.2
	 * @var string
	 */
	private const MCP_ADAPTER_PLUGIN_BASENAME = 'mcp-adapter/mcp-adapter.php';

	/**
	 * Minimum WordPress version with the Abilities API in core.
	 *
	 * @since 2.15.2
	 * @var string
	 */
	private const MINIMUM_WORDPRESS_VERSION = '6.9';

	/**
	 * The MCP adapter instance.
	 *
	 * @since 2.5.0
	 * @var McpAdapterCore|null
	 */
	private $adapter = null;

	private ?\WP_REST_Request $current_request = null;

	/**
	 * Initiates the MCP adapter hooks.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function init(): void {
		add_action('mcp_adapter_init', [$this, 'initialize_mcp_server']);

		/**
		 * Initialize the MCP adapter.
		 *
		 * @since 2.5.0
		 */
		add_action('init', [$this, 'initialize_adapter'], 10);

		/**
		 * Add the admin settings for MCP.
		 *
		 * @since 2.5.0
		 */
		add_action('init', [$this, 'add_settings'], 20);
	}

	/**
	 * Initialize the MCP adapter instance.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function initialize_adapter(): void {

		if (! $this->is_mcp_enabled()) {
			return;
		}

		try {
			$this->adapter = McpAdapterCore::instance();

			/**
			 * Fires after the MCP adapter is initialized.
			 *
			 * Allows other plugins and themes to register their own abilities.
			 *
			 * @since 2.5.0
			 * @param MCP_Adapter $mcp_adapter The MCP adapter instance.
			 */
			do_action('wu_mcp_adapter_initialized', $this);
		} catch (\Throwable $e) {
			wu_log_add(
				'mcp-adapter',
				sprintf(
					// translators: %s: error message from the exception
					__('Failed to initialize MCP adapter: %s', 'ultimate-multisite'),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Init MCP.
	 *
	 * @return void
	 */
	public function initialize_mcp_server(): void {
		if (! $this->is_mcp_enabled()) {
			return;
		}

		$abilities_ids = $this->get_mcp_abilities();

		// Bail if no abilities are available.
		if ( empty($abilities_ids) ) {
			return;
		}

		add_filter('rest_pre_dispatch', [$this, 'rest_pre_dispatch_save_request'], 10, 3);

		/*
		 * Temporarily disable MCP validation during server creation.
		 * Workaround for validator bug with union types (e.g., ["integer", "null"]).
		 * This will be removed once the mcp-adapter validator bug is fixed.
		 *
		 * @see https://github.com/WordPress/mcp-adapter/issues/47
		 */
		add_filter('mcp_adapter_validation_enabled', '__return_false', 999);

		try {
			// Create MCP server.
			$this->adapter->create_server(
				'ultimate-multisite-server',
				'ultimate-multisite',
				'mcp-adapter',
				__('Ultimate Multisite MCP Server', 'ultimate-multisite'),
				__('AI-accessible Ultimate Multisite operations via MCP', 'ultimate-multisite'),
				'1.0.0',
				[HttpTransport::class],
				\WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler::class,
				\WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler::class,
				$abilities_ids,
				[],
				[],
				[$this, 'permission_callback']
			);
		} catch ( \Throwable $e ) {
			wu_log_add(
				'mcp',
				'MCP server initialization failed: ' . $e->getMessage(),
				LogLevel::ERROR
			);
		} finally {
			// Re-enable MCP validation immediately after server creation.
			remove_filter('mcp_adapter_validation_enabled', '__return_false', 999);
		}
	}

	/**
	 * Add the admin interface to configure MCP adapter.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function add_settings(): void {
		$mcp_available = $this->is_mcp_available();

		wu_register_settings_field(
			'api',
			'mcp_header',
			[
				'title' => __('MCP Adapter Settings', 'ultimate-multisite'),
				'desc'  => __('Options related to the Model Context Protocol (MCP) adapter. MCP allows AI assistants to interact with Ultimate Multisite programmatically.', 'ultimate-multisite'),
				'type'  => 'header',
			]
		);

		wu_register_settings_field(
			'api',
			'enable_mcp',
			$mcp_available
				? [
					'title'   => __('Enable MCP Adapter', 'ultimate-multisite'),
					'desc'    => __('Tick this box to enable the Model Context Protocol (MCP) adapter. This allows AI assistants to interact with Ultimate Multisite through the Abilities API.', 'ultimate-multisite'),
					'type'    => 'toggle',
					'default' => 0,
				]
				: [
					'title' => __('MCP Adapter Unavailable', 'ultimate-multisite'),
					'desc'  => $this->get_dependency_message(),
					'type'  => 'note',
				]
		);

		if (! $mcp_available) {
			return;
		}
		wu_register_settings_field(
			'api',
			'mcp_serveer_urel',
			[
				'title'         => __('MCP Server URL', 'ultimate-multisite'),
				'desc'          => '',
				'tooltip'       => __('This is the URL where the MCP server is accessible via HTTP.', 'ultimate-multisite'),
				'copy'          => true,
				'type'          => 'text-display',
				'display_value' => rest_url('mcp/mcp-adapter-default-server'),
				'require'       => [
					'enable_mcp' => 1,
				],
			]
		);
		wu_register_settings_field(
			'api',
			'mcp_stdio_commande',
			[
				'title'         => __('STDIO Command', 'ultimate-multisite'),
				'desc'          => '',
				'tooltip'       => __('This is the WP-CLI command to run the MCP server via STDIO transport.', 'ultimate-multisite'),
				'copy'          => true,
				'type'          => 'text-display',
				'display_value' => '<code>wp mcp-adapter serve --server=mcp-adapter-default-server --user=admin</code>',
				'require'       => [
					'enable_mcp' => 1,
				],
			]
		);
	}

	/**
	 * Get the MCP adapter instance.
	 *
	 * @since 2.5.0
	 * @return McpAdapterCore|null
	 */
	public function get_adapter(): ?McpAdapterCore {

		return $this->adapter;
	}

	/**
	 * Checks if the MCP adapter is enabled.
	 *
	 * @since 2.5.0
	 * @return bool
	 */
	public function is_mcp_enabled(): bool {
		/**
		 * Filter whether the available MCP Adapter integration is enabled.
		 *
		 * @since 2.5.0
		 * @param bool $enabled Whether the MCP adapter is enabled.
		 * @return bool
		 */
		return $this->is_mcp_available() && apply_filters('wu_is_mcp_enabled', wu_get_setting('enable_mcp', false));
	}

	/**
	 * Check whether the canonical MCP Adapter plugin is available.
	 *
	 * @since 2.15.2
	 * @return bool
	 */
	public function is_mcp_available(): bool {
		global $wp_version;

		if (! function_exists('is_plugin_active_for_network')) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$available = is_plugin_active_for_network(self::MCP_ADAPTER_PLUGIN_BASENAME)
			&& version_compare((string) $wp_version, self::MINIMUM_WORDPRESS_VERSION, '>=')
			&& defined('WP_MCP_VERSION')
			&& version_compare((string) WP_MCP_VERSION, self::MINIMUM_MCP_ADAPTER_VERSION, '>=')
			&& class_exists(McpAdapterCore::class)
			&& class_exists(HttpTransport::class)
			&& function_exists('wp_get_abilities');

		/**
		 * Filter whether the canonical MCP Adapter plugin is available.
		 *
		 * @since 2.15.2
		 * @param bool $available Whether a supported canonical plugin is available.
		 * @return bool
		 */
		return (bool) apply_filters('wu_mcp_adapter_available', $available);
	}

	/**
	 * Get the MCP dependency requirement message.
	 *
	 * @since 2.15.2
	 * @return string
	 */
	private function get_dependency_message(): string {
		global $wp_version;

		if (version_compare((string) $wp_version, self::MINIMUM_WORDPRESS_VERSION, '<')) {
			return sprintf(
				/* translators: %s: minimum required WordPress version. */
				__('MCP requires WordPress %s or newer and the canonical MCP Adapter plugin.', 'ultimate-multisite'),
				self::MINIMUM_WORDPRESS_VERSION
			);
		}

		if (! defined('WP_MCP_VERSION')) {
			return sprintf(
				/* translators: %s: minimum required MCP Adapter version. */
				__('Install and network activate the canonical MCP Adapter plugin version %s or newer.', 'ultimate-multisite'),
				self::MINIMUM_MCP_ADAPTER_VERSION
			);
		}

		return sprintf(
			/* translators: %s: minimum required MCP Adapter version. */
			__('Update the canonical MCP Adapter plugin to version %s or newer.', 'ultimate-multisite'),
			self::MINIMUM_MCP_ADAPTER_VERSION
		);
	}


	/**
	 * Get abilities for MCP server.
	 *
	 * Filters abilities to include only those with 'wu_' namespace by default,
	 * with a filter to allow inclusion of abilities from other namespaces.
	 *
	 * @return array Array of ability IDs for MCP server.
	 */
	private function get_mcp_abilities(): array {

		// Check if the abilities API is available.
		if ( ! function_exists('wp_get_abilities') ) {
			return array();
		}

		$all_abilities_ids = array_keys(wp_get_abilities());

		// Filter abilities based on namespace and custom filter.
		$mcp_abilities = array_filter(
			$all_abilities_ids,
			function ($ability_id) {
				// Include Ultimate Multisite abilities by default.
				$include = str_starts_with($ability_id, 'multisite-ultimate/');

				// Allow filter to override inclusion decision.
				/**
				 * Filter to override MCP ability inclusion decision.
				 *
				 * @since 2.4.8
				 * @param bool   $include    Whether to include the ability.
				 * @param string $ability_id The ability ID.
				 */
				return apply_filters('wu_mcp_include_ability', $include, $ability_id);
			}
		);

		// Re-index array.
		return array_values($mcp_abilities);
	}

	/**
	 * Use API Key.
	 *
	 * @param \WP_REST_Request|null $request The Request.
	 *
	 * @return bool|\WP_Error
	 */
	public function permission_callback(?\WP_REST_Request $request = null) {
		if ($request) {
			$this->current_request = $request;
		}
		if (! $this->current_request) {
			return new \WP_Error('no_request_object', 'Request Object lost', ['status' => 500]);
		}

		$result = API::get_instance()->check_authorization($this->current_request);

		if ( ! $result) {
			return new \WP_Error('invalid_api_key', 'Invalid API key', ['status' => 403]);
		}

		return $result;
	}

	/**
	 * For backwards compatibility we use pre dispatch to save the request object.
	 *
	 * @param \WP_REST_Response $result The result.
	 * @param \WP_REST_Server   $server The server.
	 * @param \WP_REST_Request  $request The request.
	 *
	 * @return mixed
	 */
	public function rest_pre_dispatch_save_request($result, $server, $request) {
		$this->current_request = $request;
		return $result;
	}
}
