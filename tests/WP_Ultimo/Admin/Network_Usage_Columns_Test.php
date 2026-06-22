<?php
/**
 * Tests for Network_Usage_Columns class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Admin;

use WP_UnitTestCase;

/**
 * Test class for Network_Usage_Columns.
 */
class Network_Usage_Columns_Test extends WP_UnitTestCase {

	/**
	 * @var Network_Usage_Columns
	 */
	private $columns;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->columns = Network_Usage_Columns::get_instance();
	}

	/**
	 * Test get_instance returns a Network_Usage_Columns instance.
	 */
	public function test_get_instance_returns_instance(): void {
		$this->assertInstanceOf(Network_Usage_Columns::class, $this->columns);
	}

	/**
	 * Test get_instance returns the same instance (singleton).
	 */
	public function test_get_instance_is_singleton(): void {
		$instance1 = Network_Usage_Columns::get_instance();
		$instance2 = Network_Usage_Columns::get_instance();

		$this->assertSame($instance1, $instance2);
	}

	/**
	 * Test SITE_TRANSIENT_BLOGS_PLUGINS constant value.
	 */
	public function test_site_transient_constant(): void {
		$this->assertEquals('wu_blogs_data_plugins', Network_Usage_Columns::SITE_TRANSIENT_BLOGS_PLUGINS);
	}

	/**
	 * Test add_plugins_column adds the usage column used by WordPress.
	 */
	public function test_add_plugins_column(): void {
		$columns = $this->columns->add_plugins_column([]);

		$this->assertIsArray($columns);
		$this->assertArrayHasKey('active_blogs', $columns);
		$this->assertSame('Usage', $columns['active_blogs']);
	}

	/**
	 * Test add_themes_column adds the usage column used by WordPress.
	 */
	public function test_add_themes_column(): void {
		$columns = $this->columns->add_themes_column([]);

		$this->assertIsArray($columns);
		$this->assertArrayHasKey('active_blogs', $columns);
		$this->assertStringContainsString('Usage', $columns['active_blogs']);
	}

	/**
	 * Test get_blogs_with_plugin returns an array.
	 */
	public function test_get_blogs_with_plugin_returns_array(): void {
		$result = $this->columns->get_blogs_with_plugin('hello.php');

		$this->assertIsArray($result);
	}

	/**
	 * Test get_blogs_with_theme returns an array.
	 */
	public function test_get_blogs_with_theme_returns_array(): void {
		$result = $this->columns->get_blogs_with_theme('twentytwentyfour');

		$this->assertIsArray($result);
	}

	/**
	 * Test get_blogs_data returns an array.
	 */
	public function test_get_blogs_data_returns_array(): void {
		$result = $this->columns->get_blogs_data();

		$this->assertIsArray($result);
	}

	/**
	 * Test clear_site_transient does not throw.
	 */
	public function test_clear_site_transient_does_not_throw(): void {
		$this->columns->clear_site_transient('hello.php', false);

		$this->assertTrue(true); // No exception thrown.
	}
}
