<?php

namespace WP_Ultimo\Objects;

use WP_UnitTestCase;

class Visits_Test extends WP_UnitTestCase {

	/**
	 * @var int
	 */
	protected $site_id;

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {

		parent::setUp();

		$this->site_id = self::factory()->blog->create();
	}

	/**
	 * Test constructor sets site_id.
	 */
	public function test_constructor_sets_site_id(): void {

		$visits = new Visits($this->site_id);

		$reflection = new \ReflectionClass($visits);
		$property   = $reflection->getProperty('site_id');

		$this->assertEquals($this->site_id, $property->getValue($visits));
	}

	/**
	 * Test get_meta_key returns correct format.
	 */
	public function test_get_meta_key_format(): void {

		$visits = new Visits($this->site_id);

		$reflection = new \ReflectionClass($visits);
		$method     = $reflection->getMethod('get_meta_key');

		$result = $method->invoke($visits, '20210211');

		$this->assertEquals('wu_visits_20210211', $result);
	}

	/**
	 * Test add_visit stores a visit count.
	 */
	public function test_add_visit(): void {

		$visits = new Visits($this->site_id);

		$result = $visits->add_visit(1, '20250101');

		$this->assertNotFalse($result);

		// Verify the meta was stored
		$stored = get_site_meta($this->site_id, 'wu_visits_20250101', true);
		$this->assertEquals(1, (int) $stored);
	}

	/**
	 * Test add_visit increments existing count.
	 */
	public function test_add_visit_increments(): void {

		$visits = new Visits($this->site_id);

		$visits->add_visit(3, '20250101');
		$visits->add_visit(5, '20250101');

		$stored = get_site_meta($this->site_id, 'wu_visits_20250101', true);
		$this->assertEquals(8, (int) $stored);
	}

	/**
	 * Test add_visit uses today's date when no day is provided.
	 */
	public function test_add_visit_uses_today_as_default(): void {

		$visits = new Visits($this->site_id);

		$result = $visits->add_visit(1);

		$this->assertNotFalse($result);

		$today  = gmdate('Ymd');
		$stored = get_site_meta($this->site_id, 'wu_visits_' . $today, true);
		$this->assertEquals(1, (int) $stored);
	}

	/**
	 * Test get_visit_total returns integer.
	 */
	public function test_get_visit_total_returns_integer(): void {

		$visits = new Visits($this->site_id);

		$total = $visits->get_visit_total('2025-01-01', '2025-12-31');

		$this->assertIsInt($total);
	}

	/**
	 * Test get_visits returns array.
	 */
	public function test_get_visits_returns_array(): void {

		$visits = new Visits($this->site_id);

		$result = $visits->get_visits('2025-01-01', '2025-12-31');

		$this->assertIsArray($result);
	}

	/**
	 * Test get_sites_by_visit_count returns array.
	 */
	public function test_get_sites_by_visit_count_returns_array(): void {

		$result = Visits::get_sites_by_visit_count('2025-01-01', '2025-12-31', 5);

		$this->assertIsArray($result);
	}

	/**
	 * Test KEY constant value.
	 */
	public function test_key_constant(): void {

		$this->assertEquals('wu_visits', Visits::KEY);
	}
}
