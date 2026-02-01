<?php
/**
 * Capability Module Interface.
 *
 * Defines the contract for capability modules that attach behavior to integrations.
 *
 * @package WP_Ultimo
 * @subpackage Integrations
 * @since 2.5.0
 */

namespace WP_Ultimo\Integrations;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Interface for capability modules.
 *
 * A capability module represents a specific behavior (e.g. domain-mapping, domain-selling,
 * multi-tenancy) that can be attached to an Integration provider.
 *
 * @since 2.5.0
 */
interface Capability_Module {

	/**
	 * Returns the unique capability identifier.
	 *
	 * @since 2.5.0
	 * @return string E.g. 'domain-mapping', 'domain-selling', 'multi-tenancy'.
	 */
	public function get_capability_id(): string;

	/**
	 * Returns the display title for this capability.
	 *
	 * @since 2.5.0
	 * @return string
	 */
	public function get_title(): string;

	/**
	 * Returns the list of supported features.
	 *
	 * @since 2.5.0
	 * @return array E.g. ['autossl'], ['remote_sites'].
	 */
	public function get_supported_features(): array;

	/**
	 * Checks if a specific feature is supported.
	 *
	 * @since 2.5.0
	 *
	 * @param string $feature Feature identifier to check.
	 * @return bool
	 */
	public function supports(string $feature): bool;

	/**
	 * Registers WordPress hooks for this capability.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function register_hooks(): void;

	/**
	 * Returns additional wizard fields beyond shared integration credentials.
	 *
	 * @since 2.5.0
	 * @return array
	 */
	public function get_fields(): array;

	/**
	 * Returns explainer lines for the wizard activation screen.
	 *
	 * @since 2.5.0
	 * @return array{will: array, will_not: array}
	 */
	public function get_explainer_lines(): array;

	/**
	 * Sets the parent integration reference.
	 *
	 * @since 2.5.0
	 *
	 * @param Integration $integration The parent integration.
	 * @return void
	 */
	public function set_integration(Integration $integration): void;

	/**
	 * Gets the parent integration reference.
	 *
	 * @since 2.5.0
	 * @return Integration
	 */
	public function get_integration(): Integration;

	/**
	 * Tests the connection for this capability.
	 *
	 * @since 2.5.0
	 * @return bool|\WP_Error
	 */
	public function test_connection();
}
