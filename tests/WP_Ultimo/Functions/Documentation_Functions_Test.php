<?php
/**
 * Tests for documentation functions.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Functions;

use WP_UnitTestCase;

/**
 * Test class for documentation functions.
 */
class Documentation_Functions_Test extends WP_UnitTestCase {

	private function create_documentation_instance(): \WP_Ultimo\Documentation {

		$reflection = new \ReflectionClass(\WP_Ultimo\Documentation::class);

		return $reflection->newInstanceWithoutConstructor();
	}

	/**
	 * Test wu_get_documentation_url returns string.
	 */
	public function test_wu_get_documentation_url_returns_string(): void {

		$result = wu_get_documentation_url('some-slug');

		$this->assertIsString($result);
	}

	/**
	 * Test wu_get_documentation_url with return_default false.
	 */
	public function test_wu_get_documentation_url_no_default(): void {

		$result = wu_get_documentation_url('nonexistent-slug', false);

		// Returns false when slug not found and return_default is false.
		$this->assertFalse($result);
	}

	public function test_documentation_setup_is_deferred_to_init(): void {

		$documentation = $this->create_documentation_instance();

		$documentation->init();

		$this->assertSame(0, has_action('init', [$documentation, 'setup_links']));

		remove_action('init', [$documentation, 'setup_links'], 0);
	}

	public function test_get_link_lazily_initializes_locale_aware_links(): void {

		$documentation = $this->create_documentation_instance();
		$locale_filter = static fn() => 'fr_FR';

		add_filter('pre_determine_locale', $locale_filter);

		try {
			$this->assertSame(
				'https://ultimatemultisite.com/docs/fr/',
				$documentation->get_link('wp-ultimo-settings')
			);
		} finally {
			remove_filter('pre_determine_locale', $locale_filter);
		}
	}
}
