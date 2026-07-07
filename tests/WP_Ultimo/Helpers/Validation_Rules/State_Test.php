<?php
/**
 * Test case for State validation rule.
 *
 * @package WP_Ultimo
 * @subpackage Tests
 */

namespace WP_Ultimo\Tests\Helpers\Validation_Rules;

use WP_Ultimo\Helpers\Validation_Rules\State;
use WP_UnitTestCase;

/**
 * Test State validation rule.
 */
class State_Test extends WP_UnitTestCase {

	/**
	 * @var State
	 */
	private State $rule;

	/**
	 * Set up test.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->rule = new State();
	}

	/**
	 * Test state code is accepted for Germany.
	 */
	public function test_german_state_code_passes() {
		$_REQUEST['billing_country'] = 'DE';

		$this->assertTrue($this->rule->check('BW'));
	}

	/**
	 * Test lowercase state code is accepted for Germany.
	 */
	public function test_german_state_code_lowercase_passes() {
		$_REQUEST['billing_country'] = 'DE';

		$this->assertTrue($this->rule->check('bw'));
	}

	/**
	 * Test full state name is accepted for Germany.
	 */
	public function test_german_state_name_passes() {
		$_REQUEST['billing_country'] = 'DE';

		$this->assertTrue($this->rule->check('Baden-Württemberg'));
	}

	/**
	 * Test all German state codes are valid.
	 *
	 * @dataProvider german_state_codes_provider
	 *
	 * @param string $code The state code to test.
	 */
	public function test_all_german_state_codes_pass(string $code) {
		$_REQUEST['billing_country'] = 'DE';

		$this->assertTrue($this->rule->check($code));
	}

	/**
	 * Data provider for German state codes.
	 *
	 * @return array
	 */
	public function german_state_codes_provider(): array {
		return [
			'Baden-Württemberg'      => ['BW'],
			'Bavaria'                => ['BY'],
			'Berlin'                 => ['BE'],
			'Brandenburg'            => ['BB'],
			'Bremen'                 => ['HB'],
			'Hamburg'                => ['HH'],
			'Hesse'                  => ['HE'],
			'Lower Saxony'           => ['NI'],
			'Mecklenburg-Vorpommern' => ['MV'],
			'North Rhine-Westphalia' => ['NW'],
			'Rhineland-Palatinate'   => ['RP'],
			'Saarland'               => ['SL'],
			'Saxony'                 => ['SN'],
			'Saxony-Anhalt'          => ['ST'],
			'Schleswig-Holstein'     => ['SH'],
			'Thuringia'              => ['TH'],
		];
	}

	/**
	 * Test all German state names are valid.
	 *
	 * @dataProvider german_state_names_provider
	 *
	 * @param string $name The state name to test.
	 */
	public function test_all_german_state_names_pass(string $name) {
		$_REQUEST['billing_country'] = 'DE';

		$this->assertTrue($this->rule->check($name));
	}

	/**
	 * Data provider for German state names.
	 *
	 * @return array
	 */
	public function german_state_names_provider(): array {
		return [
			'Baden-Württemberg'      => ['Baden-Württemberg'],
			'Bavaria'                => ['Bavaria'],
			'Berlin'                 => ['Berlin'],
			'Brandenburg'            => ['Brandenburg'],
			'Bremen'                 => ['Bremen'],
			'Hamburg'                => ['Hamburg'],
			'Hesse'                  => ['Hesse'],
			'Lower Saxony'           => ['Lower Saxony'],
			'Mecklenburg-Vorpommern' => ['Mecklenburg-Vorpommern'],
			'North Rhine-Westphalia' => ['North Rhine-Westphalia'],
			'Rhineland-Palatinate'   => ['Rhineland-Palatinate'],
			'Saarland'               => ['Saarland'],
			'Saxony'                 => ['Saxony'],
			'Saxony-Anhalt'          => ['Saxony-Anhalt'],
			'Schleswig-Holstein'     => ['Schleswig-Holstein'],
			'Thuringia'              => ['Thuringia'],
		];
	}

	/**
	 * Test invalid state code is rejected.
	 */
	public function test_invalid_state_code_rejected() {
		$_REQUEST['billing_country'] = 'DE';

		$this->assertFalse($this->rule->check('XX'));
	}

	/**
	 * Test invalid state name is rejected.
	 */
	public function test_invalid_state_name_rejected() {
		$_REQUEST['billing_country'] = 'DE';

		$this->assertFalse($this->rule->check('Atlantis'));
	}

	/**
	 * Test US state code passes.
	 */
	public function test_us_state_code_passes() {
		$_REQUEST['billing_country'] = 'US';

		$this->assertTrue($this->rule->check('CA'));
	}

	/**
	 * Test US state name passes.
	 */
	public function test_us_state_name_passes() {
		$_REQUEST['billing_country'] = 'US';

		$this->assertTrue($this->rule->check('California'));
	}

	/**
	 * Test empty state value passes (state not required by default).
	 */
	public function test_empty_state_passes() {
		$_REQUEST['billing_country'] = 'DE';

		$this->assertTrue($this->rule->check(''));
	}

	/**
	 * Test null state value passes.
	 */
	public function test_null_state_passes() {
		$_REQUEST['billing_country'] = 'DE';

		$this->assertTrue($this->rule->check(null));
	}

	/**
	 * Test validation passes when country has no states defined.
	 */
	public function test_country_without_states_passes_any_value() {
		$_REQUEST['billing_country'] = 'XX';

		$this->assertTrue($this->rule->check('anything'));
	}

	/**
	 * Test validation passes when no country is set.
	 */
	public function test_no_country_passes() {
		unset($_REQUEST['billing_country']);

		$this->assertTrue($this->rule->check('BW'));
	}

	/**
	 * Test lowercase country code works.
	 */
	public function test_lowercase_country_code_works() {
		$_REQUEST['billing_country'] = 'de';

		$this->assertTrue($this->rule->check('BW'));
	}

	/**
	 * Test German state from wrong country fails.
	 */
	public function test_german_state_code_fails_for_us() {
		$_REQUEST['billing_country'] = 'US';

		$this->assertFalse($this->rule->check('BW'));
	}

	/**
	 * Test US state fails for Germany.
	 */
	public function test_us_state_code_fails_for_germany() {
		$_REQUEST['billing_country'] = 'DE';

		$this->assertFalse($this->rule->check('CA'));
	}

	/**
	 * Test unicode state name with special characters.
	 */
	public function test_unicode_state_name_passes() {
		$_REQUEST['billing_country'] = 'DE';

		// Baden-Württemberg has ü character
		$this->assertTrue($this->rule->check('Baden-Württemberg'));
	}

	/**
	 * Test state validation via the Validator class integration.
	 */
	public function test_state_validation_via_validator() {
		$_REQUEST['billing_country'] = 'DE';

		$validator = new \WP_Ultimo\Helpers\Validator();

		$data = [
			'billing_country' => 'DE',
			'billing_state'   => 'BW',
		];

		$rules = [
			'billing_state' => 'state',
		];

		$result = $validator->validate($data, $rules);

		$this->assertFalse($result->fails());
	}

	/**
	 * Test state name validation via the Validator class integration.
	 */
	public function test_state_name_validation_via_validator() {
		$_REQUEST['billing_country'] = 'DE';

		$validator = new \WP_Ultimo\Helpers\Validator();

		$data = [
			'billing_country' => 'DE',
			'billing_state'   => 'Baden-Württemberg',
		];

		$rules = [
			'billing_state' => 'state',
		];

		$result = $validator->validate($data, $rules);

		$this->assertFalse($result->fails());
	}

	/**
	 * Test invalid state fails validation via the Validator class.
	 */
	public function test_invalid_state_fails_via_validator() {
		$_REQUEST['billing_country'] = 'DE';

		$validator = new \WP_Ultimo\Helpers\Validator();

		$data = [
			'billing_country' => 'DE',
			'billing_state'   => 'InvalidState',
		];

		$rules = [
			'billing_state' => 'state',
		];

		$result = $validator->validate($data, $rules);

		$this->assertTrue($result->fails());
	}

	/**
	 * Clean up.
	 */
	public function tearDown(): void {
		unset($_REQUEST['billing_country']);

		parent::tearDown();
	}
}
