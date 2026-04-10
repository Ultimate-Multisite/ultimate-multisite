<?php
/**
 * Base Capability Module.
 *
 * Abstract base class providing default implementations for capability modules.
 *
 * @package WP_Ultimo
 * @subpackage Integrations
 * @since 2.5.0
 */

namespace WP_Ultimo\Integrations;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Abstract base class for capability modules.
 *
 * @extensible External addons extend this class to add capability modules.
 *             Do NOT add PHP return type declarations to public methods — it will
 *             cause a fatal Compile Error in any addon that overrides the method
 *             without the matching return type. Use @return PHPDoc tags instead.
 *
 * @since 2.5.0
 */
abstract class Base_Capability_Module implements Capability_Module {

	/**
	 * The parent integration instance.
	 *
	 * @since 2.5.0
	 * @var Integration
	 */
	protected Integration $integration;

	/**
	 * Features supported by this module.
	 *
	 * @since 2.5.0
	 * @var array
	 */
	protected array $supported_features = [];

	/**
	 * Check if this module supports a given feature.
	 *
	 * @since 2.5.0
	 *
	 * @param string $feature The feature identifier to check.
	 * @return bool
	 */
	public function supports($feature) {

		return in_array($feature, $this->supported_features, true);
	}

	/**
	 * Set the parent integration instance.
	 *
	 * @since 2.5.0
	 *
	 * @param Integration $integration The parent integration instance.
	 * @return void
	 */
	public function set_integration(Integration $integration) {

		$this->integration = $integration;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_integration() {

		return $this->integration;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_supported_features() {

		return $this->supported_features;
	}

	/**
	 * {@inheritdoc}
	 */
	public function register_hooks() {}

	/**
	 * {@inheritdoc}
	 */
	public function get_fields() {

		return [];
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_explainer_lines() {

		return [
			'will'     => [],
			'will_not' => [],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function test_connection() {

		return true;
	}
}
