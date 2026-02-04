<?php
/**
 * Integration class.
 *
 * Represents a single integration provider (e.g. Closte, OpenSRS).
 * Credentials are stored once and shared across all capability modules.
 *
 * @package WP_Ultimo
 * @subpackage Integrations
 * @since 2.5.0
 */

namespace WP_Ultimo\Integrations;

use WP_Ultimo\Helpers\Credential_Store;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Integration provider class.
 *
 * @since 2.5.0
 */
class Integration {

	/**
	 * Unique integration identifier.
	 *
	 * @since 2.5.0
	 * @var string
	 */
	protected string $id;

	/**
	 * Display title.
	 *
	 * @since 2.5.0
	 * @var string
	 */
	protected string $title;

	/**
	 * Description text.
	 *
	 * @since 2.5.0
	 * @var string
	 */
	protected string $description = '';

	/**
	 * URL to the logo image.
	 *
	 * @since 2.5.0
	 * @var string
	 */
	protected string $logo = '';

	/**
	 * URL to the tutorial/documentation.
	 *
	 * @since 2.5.0
	 * @var string
	 */
	protected string $tutorial_link = '';

	/**
	 * Required constants for this integration.
	 *
	 * @since 2.5.0
	 * @var array
	 */
	protected array $constants = [];

	/**
	 * Optional constants for this integration.
	 *
	 * @since 2.5.0
	 * @var array
	 */
	protected array $optional_constants = [];

	/**
	 * Features supported at the integration level.
	 *
	 * @since 2.5.0
	 * @var array
	 */
	protected array $supports = [];

	/**
	 * Constructor.
	 *
	 * @since 2.5.0
	 *
	 * @param string $id    Unique identifier.
	 * @param string $title Display title.
	 */
	public function __construct(string $id, string $title) {

		$this->id    = $id;
		$this->title = $title;
	}

	/**
	 * Returns the integration ID.
	 *
	 * @since 2.5.0
	 * @return string
	 */
	public function get_id(): string {

		return $this->id;
	}

	/**
	 * Returns the display title.
	 *
	 * @since 2.5.0
	 * @return string
	 */
	public function get_title(): string {

		return $this->title;
	}

	/**
	 * Returns the description.
	 *
	 * @since 2.5.0
	 * @return string
	 */
	public function get_description(): string {

		return $this->description;
	}

	/**
	 * Sets the description.
	 *
	 * @since 2.5.0
	 *
	 * @param string $description Description text.
	 * @return static
	 */
	public function set_description(string $description) {

		$this->description = $description;

		return $this;
	}

	/**
	 * Returns the logo URL.
	 *
	 * @since 2.5.0
	 * @return string
	 */
	public function get_logo(): string {

		return $this->logo;
	}

	/**
	 * Sets the logo URL.
	 *
	 * @since 2.5.0
	 *
	 * @param string $logo Logo URL.
	 * @return static
	 */
	public function set_logo(string $logo) {

		$this->logo = $logo;

		return $this;
	}

	/**
	 * Returns the tutorial link.
	 *
	 * @since 2.5.0
	 * @return string
	 */
	public function get_tutorial_link(): string {

		return $this->tutorial_link;
	}

	/**
	 * Sets the tutorial link.
	 *
	 * @since 2.5.0
	 *
	 * @param string $tutorial_link Tutorial URL.
	 * @return static
	 */
	public function set_tutorial_link(string $tutorial_link) {

		$this->tutorial_link = $tutorial_link;

		return $this;
	}

	/**
	 * Returns the required constants.
	 *
	 * @since 2.5.0
	 * @return array
	 */
	public function get_constants(): array {

		return $this->constants;
	}

	/**
	 * Sets the required constants.
	 *
	 * @since 2.5.0
	 *
	 * @param array $constants Required constant names.
	 * @return static
	 */
	public function set_constants(array $constants) {

		$this->constants = $constants;

		return $this;
	}

	/**
	 * Returns the optional constants.
	 *
	 * @since 2.5.0
	 * @return array
	 */
	public function get_optional_constants(): array {

		return $this->optional_constants;
	}

	/**
	 * Sets the optional constants.
	 *
	 * @since 2.5.0
	 *
	 * @param array $optional_constants Optional constant names.
	 * @return static
	 */
	public function set_optional_constants(array $optional_constants) {

		$this->optional_constants = $optional_constants;

		return $this;
	}

	/**
	 * Returns the supports array.
	 *
	 * @since 2.5.0
	 * @return array
	 */
	public function get_supports(): array {

		return $this->supports;
	}

	/**
	 * Sets the supports array.
	 *
	 * @since 2.5.0
	 *
	 * @param array $supports Feature identifiers.
	 * @return static
	 */
	public function set_supports(array $supports) {

		$this->supports = $supports;

		return $this;
	}

	/**
	 * Returns all constants (required + optional).
	 *
	 * @since 2.5.0
	 * @return array
	 */
	public function get_all_constants(): array {

		$constants = [];

		foreach ($this->constants as $constant) {
			$current   = is_array($constant) ? $constant : [$constant];
			$constants = array_merge($constants, $current);
		}

		return array_merge($constants, $this->optional_constants);
	}

	/**
	 * Retrieves a credential value by constant name.
	 *
	 * Constants defined in wp-config.php take priority over stored network options.
	 *
	 * @since 2.5.0
	 *
	 * @param string $constant_name The constant name to look up.
	 * @return string The credential value, or empty string if not found.
	 */
	public function get_credential(string $constant_name): string {

		if (defined($constant_name) && constant($constant_name)) {
			return (string) constant($constant_name);
		}

		$stored = get_network_option(null, 'wu_hosting_credential_' . $constant_name, '');

		if ( ! empty($stored)) {
			return Credential_Store::decrypt($stored);
		}

		return '';
	}

	/**
	 * Saves credential values as encrypted network options.
	 *
	 * @since 2.5.0
	 *
	 * @param array $values Key => Value pairs of credential constants.
	 * @return void
	 */
	public function save_credentials(array $values): void {

		$defaults = array_fill_keys($this->get_all_constants(), '');
		$values   = shortcode_atts($defaults, $values);

		foreach ($values as $constant_name => $value) {
			if ( ! empty($value)) {
				update_network_option(null, 'wu_hosting_credential_' . $constant_name, Credential_Store::encrypt($value));
			} else {
				delete_network_option(null, 'wu_hosting_credential_' . $constant_name);
			}
		}
	}

	/**
	 * Deletes all stored credentials for this integration.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function delete_credentials(): void {

		foreach ($this->get_all_constants() as $constant_name) {
			delete_network_option(null, 'wu_hosting_credential_' . $constant_name);
		}
	}

	/**
	 * Checks if the integration is correctly set up (all required constants have values).
	 *
	 * @since 2.5.0
	 * @return bool
	 */
	public function is_setup(): bool {

		foreach ($this->constants as $constant) {
			$constants = is_array($constant) ? $constant : [$constant];
			$found     = false;

			foreach ($constants as $name) {
				if ($this->get_credential($name)) {
					$found = true;

					break;
				}
			}

			if ( ! $found) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Returns a list of missing required constants.
	 *
	 * @since 2.5.0
	 * @return array
	 */
	public function get_missing_constants(): array {

		$missing = [];

		foreach ($this->constants as $constant) {
			$constants = is_array($constant) ? $constant : [$constant];
			$found     = false;

			foreach ($constants as $name) {
				if ($this->get_credential($name)) {
					$found = true;

					break;
				}
			}

			if ( ! $found) {
				$missing = array_merge($missing, $constants);
			}
		}

		return $missing;
	}

	/**
	 * Option key for storing enabled integrations.
	 *
	 * Uses the same key as legacy host providers for backwards compatibility.
	 *
	 * @since 2.5.0
	 * @var string
	 */
	protected string $enabled_option_key = 'wu_host_integrations_enabled';

	/**
	 * Checks if this integration is enabled.
	 *
	 * @since 2.5.0
	 * @return bool
	 */
	public function is_enabled(): bool {

		$list = get_network_option(null, $this->enabled_option_key, []);

		return ! empty($list[ $this->id ]);
	}

	/**
	 * Enables this integration.
	 *
	 * @since 2.5.0
	 * @return bool
	 */
	public function enable(): bool {

		$list = get_network_option(null, $this->enabled_option_key, []);

		$list[ $this->id ] = true;

		return update_network_option(null, $this->enabled_option_key, $list);
	}

	/**
	 * Disables this integration.
	 *
	 * @since 2.5.0
	 * @return bool
	 */
	public function disable(): bool {

		$list = get_network_option(null, $this->enabled_option_key, []);

		$list[ $this->id ] = false;

		return update_network_option(null, $this->enabled_option_key, $list);
	}

	/**
	 * Returns credential form fields for the wizard.
	 *
	 * @since 2.5.0
	 * @return array
	 */
	public function get_fields(): array {

		return [];
	}

	/**
	 * Auto-detect if this integration's environment is present.
	 *
	 * @since 2.5.0
	 * @return bool
	 */
	public function detect(): bool {

		return false;
	}

	/**
	 * Checks if a feature is supported.
	 *
	 * First checks integration-level supports, then delegates to capability modules.
	 *
	 * @since 2.5.0
	 *
	 * @param string $feature Feature identifier.
	 * @return bool
	 */
	public function supports(string $feature): bool {

		if (in_array($feature, $this->supports, true)) {
			return true;
		}

		$registry = Integration_Registry::get_instance();

		foreach ($registry->get_capabilities($this->id) as $module) {
			if ($module->supports($feature)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Tests the connection with the integration's API.
	 *
	 * @since 2.5.0
	 * @return bool|\WP_Error
	 */
	public function test_connection() {

		return true;
	}

	/**
	 * Returns the explainer lines for the wizard.
	 *
	 * Aggregates lines from all capability modules.
	 *
	 * @since 2.5.0
	 * @return array{will: array, will_not: array}
	 */
	public function get_explainer_lines(): array {

		$lines = [
			'will'     => [],
			'will_not' => [],
		];

		$registry = Integration_Registry::get_instance();

		foreach ($registry->get_capabilities($this->id) as $module) {
			$module_lines      = $module->get_explainer_lines();
			$lines['will']     = array_merge($lines['will'], $module_lines['will'] ?? []);
			$lines['will_not'] = array_merge($lines['will_not'], $module_lines['will_not'] ?? []);
		}

		return $lines;
	}

	/**
	 * Generates a define string for manual insertion into wp-config.php.
	 *
	 * @since 2.5.0
	 *
	 * @param array $constant_values Key => Value of the necessary constants.
	 * @return string
	 */
	public function get_constants_string(array $constant_values): string {

		$content = [
			sprintf('// Ultimate Multisite - Domain Mapping - %s', $this->get_title()),
		];

		$constant_values = shortcode_atts(array_fill_keys($this->get_all_constants(), ''), $constant_values);

		foreach ($constant_values as $constant => $value) {
			$content[] = sprintf('define( %s, %s );', var_export($constant, true), var_export($value, true)); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
		}

		$content[] = sprintf('// Ultimate Multisite - Domain Mapping - %s - End', $this->get_title());

		return implode(PHP_EOL, $content);
	}
}
