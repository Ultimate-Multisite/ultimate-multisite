<?php
/**
 * Tests for Nav_Menu_Subsite_Links class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\SSO;

use WP_UnitTestCase;

/**
 * Test class for Nav_Menu_Subsite_Links.
 */
class Nav_Menu_Subsite_Links_Test extends WP_UnitTestCase {

	/**
	 * @var Nav_Menu_Subsite_Links
	 */
	private $nav_menu;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->nav_menu = Nav_Menu_Subsite_Links::get_instance();
	}

	/**
	 * Test get_instance returns a Nav_Menu_Subsite_Links instance.
	 */
	public function test_get_instance_returns_instance(): void {
		$this->assertInstanceOf(Nav_Menu_Subsite_Links::class, $this->nav_menu);
	}

	/**
	 * Test get_instance returns the same instance (singleton).
	 */
	public function test_get_instance_is_singleton(): void {
		$instance1 = Nav_Menu_Subsite_Links::get_instance();
		$instance2 = Nav_Menu_Subsite_Links::get_instance();

		$this->assertSame($instance1, $instance2);
	}

	/**
	 * Test META_KEY_SUBSITE_ID constant value.
	 */
	public function test_meta_key_constant(): void {
		$this->assertEquals('_wu_subsite_id', Nav_Menu_Subsite_Links::META_KEY_SUBSITE_ID);
	}

	/**
	 * Test CLASS_PREFIX constant value.
	 */
	public function test_class_prefix_constant(): void {
		$this->assertEquals('wu-subsite-', Nav_Menu_Subsite_Links::CLASS_PREFIX);
	}

	/**
	 * Test filter_menu_item_urls returns array.
	 */
	public function test_filter_menu_item_urls_returns_array(): void {
		$result = $this->nav_menu->filter_menu_item_urls([], new \stdClass());

		$this->assertIsArray($result);
	}

	/**
	 * Test filter_menu_item_urls passes through items unchanged when no subsite items.
	 */
	public function test_filter_menu_item_urls_passthrough(): void {
		$item          = new \stdClass();
		$item->ID      = 1;
		$item->url     = 'https://example.com';
		$item->classes = [];

		$items  = [$item];
		$result = $this->nav_menu->filter_menu_item_urls($items, new \stdClass());

		$this->assertCount(1, $result);
		$this->assertEquals('https://example.com', $result[0]->url);
	}
}
