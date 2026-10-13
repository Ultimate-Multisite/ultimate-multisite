<?php
/**
 * Tests for add-on catalog extension points.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Admin_Pages;

use WP_UnitTestCase;

/**
 * Test add-on catalog filters.
 *
 * @group addons-admin-page
 */
class Addons_Admin_Page_Filter_Test extends WP_UnitTestCase {

	/**
	 * Clean up catalog filters and caches.
	 */
	protected function tearDown(): void {
		remove_all_filters('wu_addons_list');
		remove_all_filters('wu_is_debug');
		delete_site_transient('wu-addons-list');

		parent::tearDown();
	}

	/**
	 * Test cached add-ons can be decorated for local availability policies.
	 */
	public function test_filters_transient_catalog_entries(): void {
		$page = new Addons_Admin_Page();
		set_site_transient(
			'wu-addons-list',
			array(
				array(
					'slug'      => 'managed-addon',
					'name'      => 'Managed Addon',
					'installed' => false,
				),
			)
		);
		add_filter('wu_is_debug', '__return_false');
		add_filter(
			'wu_addons_list',
			function ($addons, $current_page) use ($page) {
				$this->assertSame($page, $current_page);
				$addons[0]['installed'] = true;

				return $addons;
			},
			10,
			2
		);

		$method = new \ReflectionMethod($page, 'get_addons_list');
		$method->setAccessible(true);
		$result = $method->invoke($page);

		$this->assertTrue($result[0]['installed']);
	}
}
