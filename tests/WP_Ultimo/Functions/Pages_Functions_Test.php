<?php
/**
 * Tests for pages functions.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Functions;

use WP_UnitTestCase;

/**
 * Test class for pages functions.
 */
class Pages_Functions_Test extends WP_UnitTestCase {

	/**
	 * Test wu_is_registration_page returns bool.
	 */
	public function test_is_registration_page_returns_bool(): void {

		$result = wu_is_registration_page();

		$this->assertIsBool($result);
	}

	/**
	 * Test wu_is_registration_page returns false on subsite.
	 */
	public function test_is_registration_page_false_on_subsite(): void {

		// On main site in test env, but no post context.
		$result = wu_is_registration_page();

		$this->assertFalse($result);
	}

	/**
	 * Test wu_is_update_page returns bool.
	 */
	public function test_is_update_page_returns_bool(): void {

		$result = wu_is_update_page();

		$this->assertIsBool($result);
	}

	/**
	 * Test wu_is_update_page returns false without post.
	 */
	public function test_is_update_page_false_without_post(): void {

		$result = wu_is_update_page();

		$this->assertFalse($result);
	}

	/**
	 * Test wu_is_new_site_page returns bool.
	 */
	public function test_is_new_site_page_returns_bool(): void {

		$result = wu_is_new_site_page();

		$this->assertIsBool($result);
	}

	/**
	 * Test wu_is_new_site_page returns false without post.
	 */
	public function test_is_new_site_page_false_without_post(): void {

		$result = wu_is_new_site_page();

		$this->assertFalse($result);
	}

	/**
	 * Test page options are memoized per request with context-specific defaults.
	 */
	public function test_get_pages_as_options_memoizes_pages(): void {

		$page_id = self::factory()->post->create(
			[
				'post_type'  => 'page',
				'post_title' => 'Example page',
			]
		);

		$first  = wu_get_pages_as_options('Current Page');
		$second = wu_get_pages_as_options('Default');

		$this->assertSame('Current Page', $first[0]);
		$this->assertSame('Default', $second[0]);
		$this->assertSame('Example page', $first[ $page_id ]);
		$this->assertSame($first[ $page_id ], $second[ $page_id ]);
	}

	/**
	 * Test wu_is_login_page returns bool.
	 */
	public function test_is_login_page_returns_bool(): void {

		$result = wu_is_login_page();

		$this->assertIsBool($result);
	}
}
