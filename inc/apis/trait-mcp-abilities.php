<?php
/**
 * A trait to be included in entities to enable MCP Abilities API integration.
 *
 * @package WP_Ultimo
 * @subpackage Apis
 * @since 2.5.0
 */

namespace WP_Ultimo\Apis;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * MCP Abilities trait.
 *
 * This trait provides methods to register abilities with the WordPress Abilities API
 * for use with the Model Context Protocol (MCP). It follows the same pattern as the
 * Rest_Api trait, allowing managers to expose their entities via MCP.
 *
 * @since 2.5.0
 */
trait MCP_Abilities {

	/**
	 * MCP abilities enabled for this entity.
	 *
	 * @since 2.5.0
	 * @var array
	 */
	protected $enabled_mcp_abilities = [
		'get_item',
		'get_items',
		'create_item',
		'update_item',
		'delete_item',
	];

	/**
	 * Cached MCP schemas keyed by context.
	 *
	 * @since 2.5.0
	 * @var array<string, array>
	 */
	protected array $mcp_schema_cache = [];

	/**
	 * Returns the ability prefix used for this entity.
	 * Uses the slug property of the manager.
	 *
	 * @since 2.5.0
	 * @return string
	 */
	public function get_mcp_ability_prefix(): string {

		return 'multisite-ultimate/' . str_replace('_', '-', $this->slug);
	}

	/**
	 * Enable MCP abilities for this entity.
	 * Should be called by the manager to register abilities.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function enable_mcp_abilities(): void {

		$is_enabled = \WP_Ultimo\MCP_Adapter::get_instance()->is_mcp_enabled();

		if (! $is_enabled) {
			return;
		}

		if (! function_exists('wp_register_ability')) {
			return;
		}

		add_action('wp_abilities_api_categories_init', [$this, 'register_ability_category'], 10, 0);
		add_action('wp_abilities_api_init', [$this, 'register_abilities'], 10, 0);
	}

	/**
	 * Register the ability category for this entity.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function register_ability_category(): void {

		if (! function_exists('wp_register_ability_category')) {
			return;
		}

		if (wp_has_ability_category('ultimate-multisite')) {
			return;
		}
		wp_register_ability_category(
			'ultimate-multisite',
			[
				'label'       => __('Multisite Ultimate', 'ultimate-multisite'),
				'description' => __('CRUD operations for Multisite Ultimate entities including customers, sites, products, memberships, and more.', 'ultimate-multisite'),
			]
		);
	}

	/**
	 * Permission callback for MCP abilities.
	 * Checks if the current user has the required capabilities.
	 *
	 * @since 2.5.0
	 * @param array $input_data The input data passed to the ability.
	 * @return bool|\WP_Error True if user has permission, WP_Error otherwise.
	 */
	public function mcp_permission_callback(array $input_data) {
		unset($input_data);

		$capability = "wu_read_{$this->slug}";

		if (! current_user_can($capability) && ! current_user_can('manage_network')) {
			return new \WP_Error(
				'rest_forbidden',
				__('You do not have permission to access this resource.', 'ultimate-multisite'),
				['status' => 403]
			);
		}

		return true;
	}

	/**
	 * Register abilities with the Abilities API.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function register_abilities(): void {

		$ability_prefix = $this->get_mcp_ability_prefix();
		$model_name     = $this->slug;
		$display_name   = ucwords(str_replace(['_', '-'], ' ', $model_name));

		if (in_array('get_item', $this->enabled_mcp_abilities, true)) {
			$this->register_get_item_ability($ability_prefix, $display_name);
		}

		if (in_array('get_items', $this->enabled_mcp_abilities, true)) {
			$this->register_get_items_ability($ability_prefix, $display_name);
		}

		if (in_array('create_item', $this->enabled_mcp_abilities, true)) {
			$this->register_create_item_ability($ability_prefix, $display_name);
		}

		if (in_array('update_item', $this->enabled_mcp_abilities, true)) {
			$this->register_update_item_ability($ability_prefix, $display_name);
		}

		if (in_array('delete_item', $this->enabled_mcp_abilities, true)) {
			$this->register_delete_item_ability($ability_prefix, $display_name);
		}

		/**
		 * Fires after MCP abilities are registered for an entity.
		 *
		 * @since 2.5.0
		 * @param string $ability_prefix The ability prefix.
		 * @param string $model_name The model name.
		 * @param object $manager The manager instance.
		 */
		do_action('wu_mcp_abilities_registered', $ability_prefix, $model_name, $this);
	}

	/**
	 * Register the get item ability.
	 *
	 * @since 2.5.0
	 * @param string $ability_prefix The ability prefix.
	 * @param string $display_name The display name.
	 * @return void
	 */
	protected function register_get_item_ability(string $ability_prefix, string $display_name): void {

		wp_register_ability(
			"$ability_prefix-get-item",
			[
				// translators: %s: entity name (e.g., Customer, Site, Product)
				'label'               => sprintf(__('Get %s by ID', 'ultimate-multisite'), $display_name),
				// translators: %s: entity name (e.g., customer, site, product)
				'description'         => sprintf(__('Retrieve a single %1$s by its ID. Returns all fields for the %2$s.', 'ultimate-multisite'), strtolower($display_name), strtolower($display_name)),
				'category'            => 'ultimate-multisite',
				'execute_callback'    => [$this, 'mcp_get_item'],
				'permission_callback' => [$this, 'mcp_permission_callback'],
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => [
						'id' => [
							// translators: %s: entity name (e.g., customer, site, product)
							'description' => sprintf(__('The ID of the %s to retrieve.', 'ultimate-multisite'), strtolower($display_name)),
							'type'        => 'integer',
						],
					],
					'required'             => ['id'],
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'item' => [
							// translators: %s: entity name (e.g., customer, site, product)
							'description' => sprintf(__('The %s object', 'ultimate-multisite'), strtolower($display_name)),
							'type'        => 'object',
						],
					],
				],
				'meta'                => [
					'mcp'         => [
						'public' => true,
						'type'   => 'tool',
					],
					'annotations' => [
						'readOnlyHint' => true,
					],
				],
			]
		);
	}

	/**
	 * Register the get items ability.
	 *
	 * @since 2.5.0
	 * @param string $ability_prefix The ability prefix.
	 * @param string $display_name The display name.
	 * @return void
	 */
	protected function register_get_items_ability(string $ability_prefix, string $display_name): void {

		$filter_properties = $this->get_mcp_filter_properties();

		$properties = array_merge(
			[
				'per_page' => [
					'description' => __('Number of items to retrieve per page (max 100).', 'ultimate-multisite'),
					'type'        => 'integer',
					'default'     => 10,
				],
				'page'     => [
					'description' => __('Page number to retrieve.', 'ultimate-multisite'),
					'type'        => 'integer',
					'default'     => 1,
				],
				'search'   => [
					'description' => __('Search term to filter results by searchable fields.', 'ultimate-multisite'),
					'type'        => 'string',
				],
				'orderby'  => [
					'description' => __('Field to order results by.', 'ultimate-multisite'),
					'type'        => 'string',
				],
				'order'    => [
					'description' => __('Sort direction.', 'ultimate-multisite'),
					'type'        => 'string',
					'enum'        => ['ASC', 'DESC'],
					'default'     => 'DESC',
				],
			],
			$filter_properties
		);

		$filter_list = ! empty($filter_properties) ? ' Filterable by: ' . implode(', ', array_keys($filter_properties)) . '.' : '';

		wp_register_ability(
			"$ability_prefix-get-items",
			[
				// translators: %s: entity name (e.g., Customer, Site, Product)
				'label'               => sprintf(__('List %s', 'ultimate-multisite'), $display_name),
				// translators: %1$s: entity name, %2$s: filter list
				'description'         => sprintf(__('Retrieve a paginated list of %1$s with optional filters.%2$s', 'ultimate-multisite'), strtolower($display_name), $filter_list),
				'category'            => 'ultimate-multisite',
				'execute_callback'    => [$this, 'mcp_get_items'],
				'permission_callback' => [$this, 'mcp_permission_callback'],
				'input_schema'        => [
					'type'       => 'object',
					'properties' => $properties,
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'items' => [
							// translators: %s: entity name (e.g., customer, site, product)
							'description' => sprintf(__('Array of %s objects', 'ultimate-multisite'), strtolower($display_name)),
							'type'        => 'array',
							'items'       => [
								'type' => 'object',
							],
						],
						'total' => [
							'description' => __('Total number of items', 'ultimate-multisite'),
							'type'        => 'integer',
						],
					],
				],
				'meta'                => [
					'mcp'         => [
						'public' => true,
						'type'   => 'tool',
					],
					'annotations' => [
						'readOnlyHint' => true,
					],
				],
			]
		);
	}

	/**
	 * Register the create item ability.
	 *
	 * @since 2.5.0
	 * @param string $ability_prefix The ability prefix.
	 * @param string $display_name The display name.
	 * @return void
	 */
	protected function register_create_item_ability(string $ability_prefix, string $display_name): void {

		$input_schema = $this->get_mcp_schema_for_ability('create');

		$required_fields = $input_schema['required'] ?? [];

		$required_hint = ! empty($required_fields) ? ' Required fields: ' . implode(', ', $required_fields) . '.' : '';

		wp_register_ability(
			"$ability_prefix-create-item",
			[
				// translators: %s: entity name (e.g., Customer, Site, Product)
				'label'               => sprintf(__('Create %s', 'ultimate-multisite'), $display_name),
				// translators: %1$s: entity name, %2$s: required fields hint
				'description'         => sprintf(__('Create a new %1$s.%2$s', 'ultimate-multisite'), strtolower($display_name), $required_hint),
				'category'            => 'ultimate-multisite',
				'execute_callback'    => [$this, 'mcp_create_item'],
				'permission_callback' => [$this, 'mcp_permission_callback'],
				'input_schema'        => $input_schema,
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'item' => [
							// translators: %s: entity name (e.g., customer, site, product)
							'description' => sprintf(__('The created %s object', 'ultimate-multisite'), strtolower($display_name)),
							'type'        => 'object',
						],
					],
				],
				'meta'                => [
					'mcp' => [
						'public' => true,
						'type'   => 'tool',
					],
				],
			]
		);
	}

	/**
	 * Register the update item ability.
	 *
	 * @since 2.5.0
	 * @param string $ability_prefix The ability prefix.
	 * @param string $display_name The display name.
	 * @return void
	 */
	protected function register_update_item_ability(string $ability_prefix, string $display_name): void {

		$input_schema = $this->get_mcp_schema_for_ability('update');

		// Add ID to the properties
		$input_schema['properties']['id'] = [
			// translators: %s: entity name (e.g., customer, site, product)
			'description' => sprintf(__('The ID of the %s to update.', 'ultimate-multisite'), strtolower($display_name)),
			'type'        => 'integer',
		];

		// Only ID is required for update; all other fields are optional
		$input_schema['required'] = ['id'];

		wp_register_ability(
			"$ability_prefix-update-item",
			[
				// translators: %s: entity name (e.g., Customer, Site, Product)
				'label'               => sprintf(__('Update %s', 'ultimate-multisite'), $display_name),
				// translators: %s: entity name (e.g., customer, site, product)
				'description'         => sprintf(__('Update an existing %s by ID. Only the fields provided will be changed; omitted fields remain unchanged.', 'ultimate-multisite'), strtolower($display_name)),
				'category'            => 'ultimate-multisite',
				'execute_callback'    => [$this, 'mcp_update_item'],
				'permission_callback' => [$this, 'mcp_permission_callback'],
				'input_schema'        => $input_schema,
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'item' => [
							// translators: %s: entity name (e.g., customer, site, product)
							'description' => sprintf(__('The updated %s object', 'ultimate-multisite'), strtolower($display_name)),
							'type'        => 'object',
						],
					],
				],
				'meta'                => [
					'mcp'         => [
						'public' => true,
						'type'   => 'tool',
					],
					'annotations' => [
						'idempotentHint' => true,
					],
				],
			]
		);
	}

	/**
	 * Register the delete item ability.
	 *
	 * @since 2.5.0
	 * @param string $ability_prefix The ability prefix.
	 * @param string $display_name The display name.
	 * @return void
	 */
	protected function register_delete_item_ability(string $ability_prefix, string $display_name): void {

		wp_register_ability(
			"$ability_prefix-delete-item",
			[
				// translators: %s: entity name (e.g., Customer, Site, Product)
				'label'               => sprintf(__('Delete %s', 'ultimate-multisite'), $display_name),
				// translators: %s: entity name (e.g., customer, site, product)
				'description'         => sprintf(__('Permanently delete a %s by its ID. This action cannot be undone.', 'ultimate-multisite'), strtolower($display_name)),
				'category'            => 'ultimate-multisite',
				'execute_callback'    => [$this, 'mcp_delete_item'],
				'permission_callback' => [$this, 'mcp_permission_callback'],
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => [
						'id' => [
							// translators: %s: entity name (e.g., customer, site, product)
							'description' => sprintf(__('The ID of the %s to delete.', 'ultimate-multisite'), strtolower($display_name)),
							'type'        => 'integer',
						],
					],
					'required'             => ['id'],
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'success' => [
							'description' => __('Whether the deletion was successful', 'ultimate-multisite'),
							'type'        => 'boolean',
						],
					],
				],
				'meta'                => [
					'mcp'         => [
						'public' => true,
						'type'   => 'tool',
					],
					'annotations' => [
						'destructiveHint' => true,
					],
				],
			]
		);
	}

	/**
	 * Get MCP schema for an ability (create or update).
	 * Returns JSON Schema format for input_schema.
	 * Results are cached per context to avoid repeated reflection.
	 *
	 * @since 2.5.0
	 * @param string $context The context (create or update).
	 * @return array
	 */
	protected function get_mcp_schema_for_ability(string $context = 'create'): array {

		if (isset($this->mcp_schema_cache[ $context ])) {
			return $this->mcp_schema_cache[ $context ];
		}

		if (! method_exists($this, 'get_arguments_schema')) {
			return [];
		}

		$rest_schema = $this->get_arguments_schema('update' === $context);

		$properties = [];
		$required   = [];

		foreach ($rest_schema as $key => $args) {
			$type        = self::sanitize_json_schema_type($args['type'] ?? 'string');
			$description = $args['description'] ?? ucfirst(str_replace('_', ' ', $key));

			// Append enum values to description for better LLM context
			if (isset($args['enum']) && is_array($args['enum'])) {
				$enum_values  = array_map('strval', $args['enum']);
				$description .= ' Allowed values: ' . implode(', ', $enum_values) . '.';
			}

			// Note default values in description
			if (isset($args['default']) && '' !== $args['default'] && ! is_array($args['default'])) {
				$default_str  = is_bool($args['default']) ? ($args['default'] ? 'true' : 'false') : (string) $args['default'];
				$description .= ' Defaults to ' . $default_str . '.';
			}

			$properties[ $key ] = [
				'description' => $description,
				'type'        => $type,
			];

			if (isset($args['default'])) {
				$properties[ $key ]['default'] = $args['default'];
			}

			if (isset($args['enum'])) {
				$properties[ $key ]['enum'] = $args['enum'];
			}

			if (isset($args['required']) && $args['required']) {
				$required[] = $key;
			}
		}

		$schema = [
			'type'                 => 'object',
			'properties'           => $properties,
			'additionalProperties' => false,
		];

		if (! empty($required)) {
			$schema['required'] = $required;
		}

		$this->mcp_schema_cache[ $context ] = $schema;

		return $schema;
	}

	/**
	 * Sanitize a PHP type hint into a valid JSON Schema type.
	 *
	 * Handles union types (e.g., 'int|null'), PHP shorthand types (e.g., 'bool', 'int', 'float'),
	 * and fully-qualified class names by mapping them to valid JSON Schema types.
	 *
	 * @since 2.5.0
	 * @param string $type The PHP type hint.
	 * @return string|array A valid JSON Schema type string or array for nullable types.
	 */
	protected static function sanitize_json_schema_type(string $type) {

		$type_map = [
			'bool'   => 'boolean',
			'int'    => 'integer',
			'float'  => 'number',
			'double' => 'number',
			'mixed'  => 'string',
		];

		$valid_types = ['string', 'integer', 'number', 'boolean', 'array', 'object', 'null'];

		// Handle union types like "int|null" or "string|\WP_Ultimo\Models\Discount_Code"
		if (str_contains($type, '|')) {
			$parts      = explode('|', $type);
			$has_null   = false;
			$json_types = [];

			foreach ($parts as $part) {
				$part = trim($part);

				if ('null' === strtolower($part)) {
					$has_null = true;

					continue;
				}

				$mapped = $type_map[ $part ] ?? $part;

				// Class names or unknown types default to string
				if (! in_array($mapped, $valid_types, true)) {
					$mapped = 'string';
				}

				if (! in_array($mapped, $json_types, true)) {
					$json_types[] = $mapped;
				}
			}

			if ($has_null) {
				$json_types[] = 'null';
			}

			return 1 === count($json_types) ? $json_types[0] : $json_types;
		}

		// Simple type
		$mapped = $type_map[ $type ] ?? $type;

		// Class names or unknown types default to string
		if (! in_array($mapped, $valid_types, true)) {
			$mapped = 'string';
		}

		return $mapped;
	}

	/**
	 * Get filter properties for the get-items schema based on the model's database columns.
	 *
	 * Introspects the model's query class to discover filterable columns and exposes
	 * them as optional properties on the get-items input schema.
	 *
	 * @since 2.5.0
	 * @return array<string, array> Properties array for filter fields.
	 */
	protected function get_mcp_filter_properties(): array {

		$filter_map = $this->get_model_filter_columns();

		$properties = [];

		foreach ($filter_map as $column => $type) {
			$properties[ $column ] = [
				'description' => sprintf(
					// translators: %s: column name
					__('Filter by %s.', 'ultimate-multisite'),
					str_replace('_', ' ', $column)
				),
				'type'        => $type,
			];
		}

		return $properties;
	}

	/**
	 * Returns the filterable columns and their types for this model.
	 *
	 * Override this method in specific managers to provide model-specific filters.
	 * The default implementation returns common filter columns based on the model slug.
	 *
	 * @since 2.5.0
	 * @return array<string, string> Column name => JSON Schema type.
	 */
	protected function get_model_filter_columns(): array {

		$common_filters = [
			'customer'      => [
				'user_id' => 'integer',
				'type'    => 'string',
				'vip'     => 'boolean',
			],
			'product'       => [
				'type'         => 'string',
				'active'       => 'boolean',
				'pricing_type' => 'string',
				'recurring'    => 'boolean',
				'currency'     => 'string',
			],
			'membership'    => [
				'customer_id' => 'integer',
				'plan_id'     => 'integer',
				'status'      => 'string',
				'gateway'     => 'string',
				'currency'    => 'string',
				'recurring'   => 'boolean',
			],
			'site'          => [
				'blog_id' => 'integer',
				'domain'  => 'string',
			],
			'payment'       => [
				'status'        => 'string',
				'membership_id' => 'integer',
				'customer_id'   => 'integer',
				'parent_id'     => 'integer',
				'gateway'       => 'string',
			],
			'domain'        => [
				'blog_id'        => 'integer',
				'active'         => 'boolean',
				'primary_domain' => 'boolean',
				'stage'          => 'string',
			],
			'discount_code' => [
				'code'   => 'string',
				'active' => 'boolean',
			],
			'event'         => [
				'object_type' => 'string',
				'object_id'   => 'integer',
				'severity'    => 'integer',
				'author_id'   => 'integer',
			],
			'webhook'       => [
				'event'       => 'string',
				'active'      => 'boolean',
				'integration' => 'string',
			],
			'broadcast'     => [
				'type'   => 'string',
				'status' => 'string',
			],
		];

		return $common_filters[ $this->slug ] ?? [];
	}

	/**
	 * MCP callback to get a single item.
	 *
	 * @since 2.5.0
	 * @param array $args The arguments passed to the ability.
	 * @return array|\WP_Error
	 */
	public function mcp_get_item(array $args) {

		if (! isset($args['id'])) {
			return new \WP_Error('missing_id', __('ID is required', 'ultimate-multisite'));
		}

		$item = $this->model_class::get_by_id($args['id']);

		if (empty($item)) {
			return new \WP_Error(
				"wu_{$this->slug}_not_found",
				sprintf(
					// translators: %1$s: entity name, %2$d: ID
					__('%1$s with ID %2$d not found.', 'ultimate-multisite'),
					ucfirst(str_replace('_', ' ', $this->slug)),
					$args['id']
				)
			);
		}

		return [
			'item' => $item->to_array(),
		];
	}

	/**
	 * MCP callback to get a list of items.
	 *
	 * @since 2.5.0
	 * @param array $args The arguments passed to the ability.
	 * @return array
	 */
	public function mcp_get_items(array $args): array {

		$query_args = array_merge(
			[
				'per_page' => 10,
				'page'     => 1,
			],
			$args
		);

		// Clamp per_page to a reasonable maximum
		$query_args['per_page'] = min((int) $query_args['per_page'], 100);

		$items = $this->model_class::query($query_args);

		$total = $this->model_class::query(array_merge($query_args, ['count' => true]));

		return [
			'items' => array_map(
				function ($item) {
					return $item->to_array();
				},
				$items
			),
			'total' => $total,
		];
	}

	/**
	 * MCP callback to create an item.
	 *
	 * @since 2.5.0
	 * @param array $args The arguments passed to the ability.
	 * @return array|\WP_Error
	 */
	public function mcp_create_item(array $args) {

		$model_name = (new $this->model_class([]))->model;

		$saver_function = "wu_create_{$model_name}";

		if (function_exists($saver_function)) {
			$item = call_user_func($saver_function, $args);

			$saved = is_wp_error($item) ? $item : true;
		} else {
			$item = new $this->model_class($args);

			$saved = $item->save();
		}

		if (is_wp_error($saved)) {
			return $this->format_validation_error($saved);
		}

		if (! $saved) {
			return new \WP_Error(
				"wu_{$this->slug}_save_failed",
				sprintf(
					// translators: %s: entity name
					__('Failed to create %s. The save operation returned false without a specific error.', 'ultimate-multisite'),
					str_replace('_', ' ', $this->slug)
				)
			);
		}

		return [
			'item' => $item->to_array(),
		];
	}

	/**
	 * MCP callback to update an item.
	 *
	 * @since 2.5.0
	 * @param array $args The arguments passed to the ability.
	 * @return array|\WP_Error
	 */
	public function mcp_update_item(array $args) {

		if (! isset($args['id'])) {
			return new \WP_Error('missing_id', __('ID is required', 'ultimate-multisite'));
		}

		$id = $args['id'];
		unset($args['id']);

		$item = $this->model_class::get_by_id($id);

		if (empty($item)) {
			return new \WP_Error(
				"wu_{$this->slug}_not_found",
				sprintf(
					// translators: %1$s: entity name, %2$d: ID
					__('%1$s with ID %2$d not found.', 'ultimate-multisite'),
					ucfirst(str_replace('_', ' ', $this->slug)),
					$id
				)
			);
		}

		$unknown_fields = [];

		foreach ($args as $param => $value) {
			$set_method = "set_{$param}";

			if ('meta' === $param) {
				$item->update_meta_batch($value);
			} elseif (method_exists($item, $set_method)) {
				call_user_func([$item, $set_method], $value);
			} else {
				$unknown_fields[] = $param;
			}
		}

		if (! empty($unknown_fields)) {
			return new \WP_Error(
				"wu_{$this->slug}_unknown_fields",
				sprintf(
					// translators: %1$s: entity name, %2$s: field names
					__('Unknown fields for %1$s: %2$s. These fields do not have setter methods and were not applied.', 'ultimate-multisite'),
					str_replace('_', ' ', $this->slug),
					implode(', ', $unknown_fields)
				)
			);
		}

		$saved = $item->save();

		if (is_wp_error($saved)) {
			return $this->format_validation_error($saved);
		}

		if (! $saved) {
			return new \WP_Error(
				"wu_{$this->slug}_save_failed",
				sprintf(
					// translators: %1$s: entity name, %2$d: ID
					__('Failed to update %1$s with ID %2$d. The save operation returned false without a specific error.', 'ultimate-multisite'),
					str_replace('_', ' ', $this->slug),
					$id
				)
			);
		}

		return [
			'item' => $item->to_array(),
		];
	}

	/**
	 * MCP callback to delete an item.
	 *
	 * @since 2.5.0
	 * @param array $args The arguments passed to the ability.
	 * @return array|\WP_Error
	 */
	public function mcp_delete_item(array $args) {

		if (! isset($args['id'])) {
			return new \WP_Error('missing_id', __('ID is required', 'ultimate-multisite'));
		}

		$item = $this->model_class::get_by_id($args['id']);

		if (empty($item)) {
			return new \WP_Error(
				"wu_{$this->slug}_not_found",
				sprintf(
					// translators: %1$s: entity name, %2$d: ID
					__('%1$s with ID %2$d not found.', 'ultimate-multisite'),
					ucfirst(str_replace('_', ' ', $this->slug)),
					$args['id']
				)
			);
		}

		$result = $item->delete();

		if (is_wp_error($result)) {
			return $this->format_validation_error($result);
		}

		return [
			'success' => (bool) $result,
		];
	}

	/**
	 * Format a WP_Error into a more detailed error response for MCP.
	 *
	 * Collects all error codes and messages from a WP_Error and combines them
	 * into a single, detailed error message for better LLM understanding.
	 *
	 * @since 2.5.0
	 * @param \WP_Error $error The WP_Error to format.
	 * @return \WP_Error A formatted WP_Error with combined messages.
	 */
	protected function format_validation_error(\WP_Error $error): \WP_Error {

		$codes = $error->get_error_codes();

		if (count($codes) <= 1) {
			return $error;
		}

		$messages = [];

		foreach ($codes as $code) {
			$code_messages = $error->get_error_messages($code);

			foreach ($code_messages as $message) {
				$messages[] = "[{$code}] {$message}";
			}
		}

		return new \WP_Error(
			$codes[0],
			sprintf(
				// translators: %1$d: error count, %2$s: combined messages
				__('Validation failed with %1$d error(s): %2$s', 'ultimate-multisite'),
				count($messages),
				implode('; ', $messages)
			),
			$error->get_error_data()
		);
	}
}
