<?php
/**
 * Test case for MUCD_Data replacement methods.
 *
 * @package WP_Ultimo
 * @subpackage Tests
 */

namespace WP_Ultimo\Tests\Duplication;

use WP_UnitTestCase;

// Load the MUCD files.
require_once WP_ULTIMO_PLUGIN_DIR . '/inc/duplication/data.php';

/**
 * Test MUCD_Data replacement functionality.
 */
class MUCD_Data_Test extends WP_UnitTestCase {

	/**
	 * Test basic string replacement.
	 */
	public function test_replace_basic() {
		$result = \MUCD_Data::replace(
			'Visit example.com/old-site for details',
			'example.com/old-site',
			'example.com/new-site'
		);

		$this->assertEquals('Visit example.com/new-site for details', $result);
	}

	/**
	 * Test replacement works when to_string already exists in value.
	 *
	 * This was the bug: the old code skipped replacement if $to_string
	 * was found anywhere in $val, which broke subdirectory multisite
	 * where upload URL replacement happened first.
	 */
	public function test_replace_when_to_string_already_present() {
		// Simulate: upload URL was already replaced, now blog URL replacement runs.
		// Value already contains "example.com/new-site" from previous replacement,
		// but still has "example.com/old-site" in button URLs.
		$val = 'https://example.com/new-site/wp-content/uploads/image.jpg and https://example.com/old-site/contact';

		$result = \MUCD_Data::replace(
			$val,
			'example.com/old-site',
			'example.com/new-site'
		);

		$this->assertEquals(
			'https://example.com/new-site/wp-content/uploads/image.jpg and https://example.com/new-site/contact',
			$result
		);
	}

	/**
	 * Test replacement with multiple occurrences of from_string.
	 */
	public function test_replace_multiple_occurrences() {
		$val = 'Link: example.com/old and another: example.com/old/page';

		$result = \MUCD_Data::replace($val, 'example.com/old', 'example.com/new');

		$this->assertEquals('Link: example.com/new and another: example.com/new/page', $result);
	}

	/**
	 * Test replacement with non-string value.
	 */
	public function test_replace_non_string_returns_unchanged() {
		$this->assertEquals(42, \MUCD_Data::replace(42, 'foo', 'bar'));
		$this->assertNull(\MUCD_Data::replace(null, 'foo', 'bar'));
		$this->assertTrue(\MUCD_Data::replace(true, 'foo', 'bar'));
	}

	/**
	 * Test replacement when from_string is not found.
	 */
	public function test_replace_no_match() {
		$val    = 'No matching content here';
		$result = \MUCD_Data::replace($val, 'example.com/old', 'example.com/new');

		$this->assertEquals($val, $result);
	}

	/**
	 * Test try_replace with serialized array data.
	 */
	public function test_try_replace_serialized_array() {
		$data = serialize(['url' => 'https://example.com/old-site/page']);
		$row  = ['meta_value' => $data];

		$result = \MUCD_Data::try_replace($row, 'meta_value', 'example.com/old-site', 'example.com/new-site');

		$unserialized = unserialize($result);
		$this->assertEquals('https://example.com/new-site/page', $unserialized['url']);
	}

	/**
	 * Test try_replace with nested serialized data.
	 */
	public function test_try_replace_nested_serialized_array() {
		$data = serialize([
			'settings' => [
				'link' => 'https://example.com/old-site/about',
				'icon' => 'fa-home',
			],
			'content'  => 'Visit https://example.com/old-site for more',
		]);
		$row  = ['meta_value' => $data];

		$result       = \MUCD_Data::try_replace($row, 'meta_value', 'example.com/old-site', 'example.com/new-site');
		$unserialized = unserialize($result);

		$this->assertEquals('https://example.com/new-site/about', $unserialized['settings']['link']);
		$this->assertEquals('Visit https://example.com/new-site for more', $unserialized['content']);
		$this->assertEquals('fa-home', $unserialized['settings']['icon']);
	}

	/**
	 * Test try_replace with plain (non-serialized) string.
	 */
	public function test_try_replace_plain_string() {
		$row = ['post_content' => '<a href="https://example.com/old-site/page">Link</a>'];

		$result = \MUCD_Data::try_replace($row, 'post_content', 'example.com/old-site', 'example.com/new-site');

		$this->assertEquals('<a href="https://example.com/new-site/page">Link</a>', $result);
	}

	/**
	 * Test try_replace with double-serialized data.
	 */
	public function test_try_replace_double_serialized() {
		$data = serialize(serialize(['url' => 'https://example.com/old-site/page']));
		$row  = ['meta_value' => $data];

		$result = \MUCD_Data::try_replace($row, 'meta_value', 'example.com/old-site', 'example.com/new-site');

		$unserialized = unserialize(unserialize($result));
		$this->assertEquals('https://example.com/new-site/page', $unserialized['url']);
	}

	/**
	 * Test try_replace with serialized data containing the to_string already.
	 *
	 * Ensures the fix works: even when to_string is present, remaining
	 * from_string occurrences should still be replaced.
	 */
	public function test_try_replace_serialized_with_partial_replacement() {
		$data = serialize([
			'upload_url' => 'https://example.com/new-site/wp-content/uploads/img.jpg',
			'button_url' => 'https://example.com/old-site/contact',
			'page_url'   => 'https://example.com/old-site/about',
		]);
		$row  = ['meta_value' => $data];

		$result       = \MUCD_Data::try_replace($row, 'meta_value', 'example.com/old-site', 'example.com/new-site');
		$unserialized = unserialize($result);

		$this->assertEquals('https://example.com/new-site/wp-content/uploads/img.jpg', $unserialized['upload_url']);
		$this->assertEquals('https://example.com/new-site/contact', $unserialized['button_url']);
		$this->assertEquals('https://example.com/new-site/about', $unserialized['page_url']);
	}

	/**
	 * Test try_replace with Elementor-like JSON data stored in postmeta.
	 *
	 * JSON encodes forward slashes as \/, so the plain URL replacement won't
	 * match. The system handles this by running a separate pass with
	 * JSON-escaped search/replace strings at the DB level.
	 */
	public function test_try_replace_elementor_json_data() {
		$elementor_data = json_encode([
			[
				'elType'   => 'section',
				'settings' => [],
				'elements' => [
					[
						'elType'   => 'widget',
						'settings' => [
							'link' => [
								'url' => 'https://example.com/old-site/services',
							],
							'button_link' => [
								'url' => 'https://example.com/old-site/contact-us',
							],
						],
					],
				],
			],
		]);

		// Elementor stores _elementor_data as a plain JSON string (not serialized).
		$row = ['meta_value' => $elementor_data];

		// First pass: plain URL replacement (won't match JSON-escaped slashes).
		$result = \MUCD_Data::try_replace($row, 'meta_value', 'example.com/old-site', 'example.com/new-site');

		// Second pass: JSON-escaped URL replacement (matches \/ in JSON).
		$row    = ['meta_value' => $result];
		$result = \MUCD_Data::try_replace(
			$row,
			'meta_value',
			str_replace('/', '\\/', 'example.com/old-site'),
			str_replace('/', '\\/', 'example.com/new-site')
		);

		$this->assertStringNotContainsString('old-site', $result);
		$this->assertStringContainsString('new-site\\/services', $result);
		$this->assertStringContainsString('new-site\\/contact-us', $result);
	}

	/**
	 * Test replace with JSON-escaped forward slashes.
	 */
	public function test_replace_json_escaped_slashes() {
		// JSON-encoded URL: forward slashes become \/
		$val = 'https:\\/\\/example.com\\/old-site\\/page';

		$result = \MUCD_Data::replace(
			$val,
			str_replace('/', '\\/', 'example.com/old-site'),
			str_replace('/', '\\/', 'example.com/new-site')
		);

		$this->assertEquals('https:\\/\\/example.com\\/new-site\\/page', $result);
	}

	/**
	 * Test replace_recursive with nested array.
	 */
	public function test_replace_recursive_nested_array() {
		$data = [
			'level1' => [
				'level2' => [
					'url' => 'https://example.com/old/deep-page',
				],
			],
			'flat'   => 'https://example.com/old/flat-page',
		];

		$result = \MUCD_Data::replace_recursive($data, 'example.com/old', 'example.com/new');

		$this->assertEquals('https://example.com/new/deep-page', $result['level1']['level2']['url']);
		$this->assertEquals('https://example.com/new/flat-page', $result['flat']);
	}

	/**
	 * Test replace_recursive with mixed types.
	 */
	public function test_replace_recursive_mixed_types() {
		$data = [
			'string' => 'https://example.com/old/page',
			'int'    => 42,
			'bool'   => true,
			'null'   => null,
			'array'  => ['https://example.com/old/nested'],
		];

		$result = \MUCD_Data::replace_recursive($data, 'example.com/old', 'example.com/new');

		$this->assertEquals('https://example.com/new/page', $result['string']);
		$this->assertEquals(42, $result['int']);
		$this->assertTrue($result['bool']);
		$this->assertNull($result['null']);
		$this->assertEquals('https://example.com/new/nested', $result['array'][0]);
	}

	/**
	 * Test serialized data preserves string lengths correctly after replacement.
	 */
	public function test_try_replace_serialized_preserves_length() {
		$data = serialize(['url' => 'https://example.com/short/page']);
		$row  = ['meta_value' => $data];

		$result = \MUCD_Data::try_replace($row, 'meta_value', 'example.com/short', 'example.com/much-longer-path');

		// Should be valid serialized data.
		$unserialized = @unserialize($result);
		$this->assertIsArray($unserialized);
		$this->assertEquals('https://example.com/much-longer-path/page', $unserialized['url']);
	}
}
