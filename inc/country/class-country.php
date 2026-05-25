<?php
/**
 * Base Country class.
 *
 * @see https://github.com/harpreetkhalsagtbit/country-state-city
 *
 * @package WP_Ultimo
 * @subpackage Country
 * @since 2.0.11
 */

namespace WP_Ultimo\Country;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Base Country class.
 *
 * @since 2.0.0
 */
abstract class Country {

	/**
	 * General country attributes.
	 *
	 * This might be useful, might be not.
	 * In case of doubt, keep it.
	 *
	 * @since 2.0.11
	 * @var array
	 */
	protected $attributes = [];

	/**
	 * The type of nomenclature used to refer to the country sub-divisions.
	 *
	 * @since 2.0.11
	 * @var string
	 */
	protected $state_type = 'unknown';

	/**
	 * Whether this country uses postal/ZIP codes as part of its addressing system.
	 *
	 * Most countries do, but a notable list (Universal Postal Union "no postcode
	 * in use" countries) do not. When this is false, the checkout form hides
	 * the ZIP / Postal Code field and skips its validation.
	 *
	 * Subclasses for countries without postal codes (or the Country_Default
	 * fallback list) should override this to false.
	 *
	 * @since 2.5.3
	 * @var bool
	 */
	protected $uses_postal_code = true;

	/**
	 * Return the country name.
	 *
	 * @since 2.0.11
	 * @return string
	 */
	abstract public function get_name();

	/**
	 * Magic getter to allow us to access attributes as properties.
	 *
	 * @since 2.0.11
	 *
	 * @param string $attribute The attribute to fetch.
	 * @return mixed|null
	 */
	public function __get($attribute) {

		return wu_get_isset($this->attributes, $attribute, null);
	}

	/**
	 * Whether this country uses postal/ZIP codes as part of its addressing system.
	 *
	 * Filterable via `wu_country_uses_postal_code` so site owners and add-ons can
	 * tailor the list (for example to re-enable ZIP for territories that have
	 * since adopted postal codes).
	 *
	 * @since 2.5.3
	 * @return bool
	 */
	public function get_uses_postal_code() {

		/**
		 * Filters whether a country uses postal/ZIP codes.
		 *
		 * @since 2.5.3
		 *
		 * @param bool                       $uses_postal_code Whether the country uses postal codes.
		 * @param string                     $country_code     Two-letter ISO code for the country.
		 * @param \WP_Ultimo\Country\Country $current_country  Instance of the current class.
		 * @return bool
		 */
		return (bool) apply_filters(
			'wu_country_uses_postal_code',
			(bool) $this->uses_postal_code,
			$this->country_code ?? '',
			$this
		);
	}

	/**
	 * Returns the list of states/provinces of this country.
	 *
	 * The list returned is in the XX => Name format.
	 *
	 * @since 2.0.11
	 * @return array
	 */
	public function get_states() {

		$states = $this->states();

		/**
		 * Returns the list of states for this country.
		 *
		 * @since 2.0.11
		 *
		 * @param array $states List of states in a XX => Name format.
		 * @param string $country_code Two-letter ISO code for the country.
		 * @param \WP_Ultimo\Country\Country $current_country Instance of the current class.
		 * @return array The filtered list of states.
		 */
		return apply_filters('wu_country_get_states', $states, $this->country_code, $this);
	}

	/**
	 * Returns states as options.
	 *
	 * @since 2.0.12
	 *
	 * @param string $placeholder The placeholder for the empty option.
	 * @return array
	 */
	public function get_states_as_options($placeholder = '') {

		$options = $this->get_states();

		$placeholder_option = [];

		if (false !== $placeholder && $options) {
			$division_name = $this->get_administrative_division_name();

			// translators: %s is the name of the administrative division (state, province, etc).
			$placeholder_option[''] = '' !== $placeholder ? $placeholder : sprintf(__('Select your %s', 'ultimate-multisite'), $division_name);
		}

		return array_merge($placeholder_option, $options);
	}

	/**
	 * Returns the list of cities for a country and state.
	 *
	 * @since 2.0.11
	 *
	 * @param string $state_code Two-letter ISO code for the state.
	 * @return array
	 */
	public function get_cities($state_code = '') {

		if (empty($state_code)) {
			return [];
		}

		$repository_file = wu_path("inc/country/{$this->country_code}/{$state_code}.php");

		if (file_exists($repository_file) === false) {
			return [];
		}

		$cities = include $repository_file;

		/**
		 * Returns the list of cities for a state in a country.
		 *
		 * @since 2.0.11
		 *
		 * @param array $cities List of state city names. No keys are present.
		 * @param string $country_code Two-letter ISO code for the country.
		 * @param string $state_code Two-letter ISO code for the state.
		 * @param \WP_Ultimo\Country\Country $current_country Instance of the current class.
		 * @return array The filtered list of states.
		 */
		return apply_filters('wu_country_get_cities', $cities, $this->country_code, $state_code, $this);
	}

	/**
	 * Get state cities as options.
	 *
	 * @since 2.0.12
	 *
	 * @param string $state_code The state code.
	 * @param string $placeholder The placeholder for the empty option.
	 * @return array
	 */
	public function get_cities_as_options($state_code = '', $placeholder = '') {

		$options = $this->get_cities($state_code);

		$placeholder_option = [];

		if (false !== $placeholder && $options) {
			$placeholder_option[''] = '' !== $placeholder ? $placeholder : __('Select your city', 'ultimate-multisite');
		}

		$options = array_combine($options, $options);

		return array_merge($placeholder_option, $options);
	}

	/**
	 * Returns the list of states for a country.
	 *
	 * @since 2.0.11
	 * @return array The list of state/provinces for the country.
	 */
	protected function states() {

		return [];
	}

	/**
	 * Get the name of municipalities for a country/state.
	 *
	 * Some countries call cities, cities, other, town,
	 * others municipalities, etc.
	 *
	 * @since 2.0.12
	 *
	 * @param string  $state_code The municipality name.
	 * @param boolean $ucwords If we need to return the results with ucwords applied.
	 * @return string
	 */
	public function get_municipality_name($state_code = null, $ucwords = false) {

		$name = __('city', 'ultimate-multisite');

		$name = $ucwords ? ucwords($name) : $name;

		return apply_filters('wu_country_get_municipality_name', $name, $this->country_code, $state_code, $ucwords, $this);
	}

	/**
	 * Get the name given to states for a country.
	 *
	 * Some countries call states states, others provinces,
	 * others regions, etc.
	 *
	 * @since 2.0.12
	 *
	 * @param string  $state_code The state code.
	 * @param boolean $ucwords If we need to return the results with ucwords applied.
	 * @return string
	 */
	public function get_administrative_division_name($state_code = null, $ucwords = false) {

		$denominations = [
			'province'             => __('province', 'ultimate-multisite'),
			'state'                => __('state', 'ultimate-multisite'),
			'territory'            => __('territory', 'ultimate-multisite'),
			'region'               => __('region', 'ultimate-multisite'),
			'department'           => __('department', 'ultimate-multisite'),
			'district'             => __('district', 'ultimate-multisite'),
			'prefecture'           => __('prefecture', 'ultimate-multisite'),
			'autonomous_community' => __('autonomous community', 'ultimate-multisite'),
			'parish'               => __('parish', 'ultimate-multisite'),
			'county'               => __('county', 'ultimate-multisite'),
			'division'             => __('division', 'ultimate-multisite'),
			'unknown'              => __('state / province', 'ultimate-multisite'),
		];

		$name = wu_get_isset($denominations, $this->state_type, $denominations['unknown']);

		$name = $ucwords ? ucwords((string) $name) : $name;

		/**
		 * Returns nice name of the country administrative sub-divisions.
		 *
		 * @param string $name The division name. Usually something like state, province, region, etc.
		 * @param string $country_code Two-letter ISO code for the country.
		 * @param string $state_code Two-letter ISO code for the state.
		 * @param bool $ucwords if we uppercase the words.
		 * @param \WP_Ultimo\Country\Country $current_country Instance of the current class.
		 *
		 * @return string The modified division name.
		 *@since 2.0.11
		 */
		return apply_filters('wu_country_get_administrative_division_name', $name, $this->country_code, $state_code, $ucwords, $this);
	}
}
