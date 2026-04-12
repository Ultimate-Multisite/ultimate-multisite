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

	/**
	 * Test try_replace returns original value when unserialize fails.
	 *
	 * If unserialize() fails (e.g. corrupted data), the original value
	 * should be returned instead of serialize(false) which would destroy it.
	 */
	public function test_try_replace_returns_original_on_unserialize_failure() {
		// Construct data that looks serialized (starts with a:) but is actually invalid.
		$corrupted = 'a:2:{s:3:"foo";CORRUPTED_DATA}';
		$row       = ['meta_value' => $corrupted];

		$result = \MUCD_Data::try_replace($row, 'meta_value', 'foo', 'bar');

		// Should return the original corrupted string unchanged — not 'b:0;'.
		$this->assertEquals($corrupted, $result);
	}

	/**
	 * Test try_replace with Elementor Kit-like serialized page settings.
	 *
	 * Simulates the _elementor_page_settings structure for a Kit post.
	 * After URL replacement, all non-URL settings must be preserved
	 * exactly and the result must be valid serialized data.
	 */
	public function test_try_replace_elementor_kit_page_settings() {
		$kit_settings = [
			'system_colors'     => [
				[
					'_id'   => 'primary',
					'color' => '#6EC1E4',
					'title' => 'Primary',
				],
				[
					'_id'   => 'secondary',
					'color' => '#54595F',
					'title' => 'Secondary',
				],
				[
					'_id'   => 'text',
					'color' => '#7A7A7A',
					'title' => 'Text',
				],
				[
					'_id'   => 'accent',
					'color' => '#61CE70',
					'title' => 'Accent',
				],
			],
			'system_typography' => [
				[
					'_id'                       => 'primary',
					'typography_font_family'     => 'Roboto',
					'typography_font_weight'     => '600',
					'typography_typography'      => 'custom',
				],
				[
					'_id'                       => 'secondary',
					'typography_font_family'     => 'Roboto Slab',
					'typography_font_weight'     => '400',
					'typography_typography'      => 'custom',
				],
			],
			'custom_colors'     => [
				[
					'_id'   => 'brand_dark',
					'color' => '#1A1A2E',
					'title' => 'Brand Dark',
				],
			],
			'container_width'   => [
				'size' => 1140,
				'unit' => 'px',
			],
			'space_between_widgets' => [
				'size' => 20,
				'unit' => 'px',
			],
			'page_title_selector' => 'h1.entry-title',
			'active_breakpoints'  => [
				'viewport_mobile',
				'viewport_tablet',
			],
			'custom_css'        => 'body { font-family: "Roboto", sans-serif; } .site-logo img { max-height: 60px; } .hero-section { background-image: url(https://plantilla2.example.com/wp-content/uploads/sites/97/hero-bg.jpg); }',
			'site_logo'         => [
				'url' => 'https://plantilla2.example.com/wp-content/uploads/sites/97/logo.png',
				'id'  => 42,
			],
			'site_favicon'      => [
				'url' => 'https://plantilla2.example.com/wp-content/uploads/sites/97/favicon.png',
				'id'  => 43,
			],
		];

		$serialized     = serialize($kit_settings);
		$original_count = count($kit_settings);
		$row            = ['meta_value' => $serialized];

		// Run the replacement as the duplication code would.
		$result = \MUCD_Data::try_replace(
			$row,
			'meta_value',
			'plantilla2.example.com/wp-content/uploads/sites/97',
			'newsite.example.com/wp-content/uploads/sites/145'
		);

		// Result must be valid serialized data.
		$unserialized = @unserialize($result);
		$this->assertIsArray($unserialized, 'Result must be a valid serialized array');

		// All top-level keys must be preserved.
		$this->assertCount($original_count, $unserialized, 'All top-level keys must survive serialization round-trip');

		// Colors and typography must be completely unchanged (no URL content).
		$this->assertEquals($kit_settings['system_colors'], $unserialized['system_colors']);
		$this->assertEquals($kit_settings['system_typography'], $unserialized['system_typography']);
		$this->assertEquals($kit_settings['custom_colors'], $unserialized['custom_colors']);
		$this->assertEquals($kit_settings['container_width'], $unserialized['container_width']);
		$this->assertEquals($kit_settings['page_title_selector'], $unserialized['page_title_selector']);

		// URLs must be replaced.
		$this->assertStringContainsString('newsite.example.com/wp-content/uploads/sites/145/hero-bg.jpg', $unserialized['custom_css']);
		$this->assertEquals('https://newsite.example.com/wp-content/uploads/sites/145/logo.png', $unserialized['site_logo']['url']);
		$this->assertEquals('https://newsite.example.com/wp-content/uploads/sites/145/favicon.png', $unserialized['site_favicon']['url']);
		$this->assertEquals(42, $unserialized['site_logo']['id']);
	}

	/**
	 * Test try_replace preserves data across multiple replacement passes.
	 *
	 * Simulates the full duplication pipeline: upload URL replacement,
	 * blog URL replacement, prefix replacement, and JSON-escaped versions.
	 * This mirrors the actual db_update_data() flow.
	 */
	public function test_try_replace_multiple_passes_preserve_data() {
		$settings = [
			'color'      => '#FF5733',
			'font'       => 'Open Sans',
			'custom_css' => '.hero { background: url(https://old.example.com/wp-content/uploads/sites/97/bg.jpg); color: #333; }',
			'site_url'   => 'https://old.example.com',
			'nested'     => [
				'image' => 'https://old.example.com/wp-content/uploads/sites/97/photo.png',
				'count' => 5,
				'flag'  => true,
			],
		];

		$serialized = serialize($settings);
		$row        = ['meta_value' => $serialized];

		// Pass 1: Upload URL
		$result = \MUCD_Data::try_replace(
			$row,
			'meta_value',
			'old.example.com/wp-content/uploads/sites/97',
			'new.example.com/wp-content/uploads/sites/200'
		);
		$row = ['meta_value' => $result];

		// Pass 2: Blog URL
		$result = \MUCD_Data::try_replace(
			$row,
			'meta_value',
			'old.example.com',
			'new.example.com'
		);
		$row = ['meta_value' => $result];

		// Pass 3: Prefix
		$result = \MUCD_Data::try_replace(
			$row,
			'meta_value',
			'wp_97_',
			'wp_200_'
		);

		// Final result must be valid.
		$unserialized = @unserialize($result);
		$this->assertIsArray($unserialized, 'Data must survive multiple replacement passes');

		// Non-URL data must be perfectly preserved.
		$this->assertEquals('#FF5733', $unserialized['color']);
		$this->assertEquals('Open Sans', $unserialized['font']);
		$this->assertEquals(5, $unserialized['nested']['count']);
		$this->assertTrue($unserialized['nested']['flag']);

		// URLs must be correctly replaced.
		$this->assertStringContainsString('new.example.com/wp-content/uploads/sites/200/bg.jpg', $unserialized['custom_css']);
		$this->assertEquals('https://new.example.com/wp-content/uploads/sites/200/photo.png', $unserialized['nested']['image']);
		$this->assertEquals('https://new.example.com', $unserialized['site_url']);
	}

	/**
	 * Test get_primary_key returns the correct column for standard WP tables.
	 */
	public function test_get_primary_key_returns_correct_column() {
		global $wpdb;

		$pk = \MUCD_Data::get_primary_key($wpdb->postmeta);
		$this->assertEquals('meta_id', $pk);

		$pk = \MUCD_Data::get_primary_key($wpdb->posts);
		$this->assertEquals('ID', $pk);

		$pk = \MUCD_Data::get_primary_key($wpdb->options);
		$this->assertEquals('option_id', $pk);
	}

	/**
	 * Test get_primary_key caching — same table queried twice returns same result.
	 */
	public function test_get_primary_key_is_cached() {
		global $wpdb;

		$pk1 = \MUCD_Data::get_primary_key($wpdb->postmeta);
		$pk2 = \MUCD_Data::get_primary_key($wpdb->postmeta);

		$this->assertEquals($pk1, $pk2);
		$this->assertEquals('meta_id', $pk1);
	}

	/**
	 * Test that serialized boolean false (b:0;) is handled correctly.
	 *
	 * Ensures the unserialize safety check doesn't treat legitimate
	 * serialized false as an error.
	 */
	public function test_try_replace_serialized_false_not_treated_as_error() {
		$data = serialize(false);  // 'b:0;'
		$row  = ['meta_value' => $data];

		$result = \MUCD_Data::try_replace($row, 'meta_value', 'foo', 'bar');

		// b:0; contains neither 'foo' nor 'bar', so it should come back unchanged.
		$this->assertEquals('b:0;', $result);
	}

	/**
	 * Integration test: MUCD_Data::update() preserves Elementor Kit byte count.
	 *
	 * Reproduces the exact customer-reported scenario:
	 *   - Clone from plantilla2 (blog 97): _elementor_page_settings for post_id=3
	 *     loses ~748 bytes (origin 6387 bytes → clone 5639 bytes).
	 *   - Result: cloned site renders with default Elementor styles instead of
	 *     the template's brand colors/typography.
	 *
	 * The bug: MUCD_Data::update() issued UPDATE ... WHERE meta_value = %s using
	 * the full serialized value. With 7 replacement passes (upload URL, blog URL,
	 * prefix, plus JSON-escaped variants), the second pass would fail to match
	 * the row (already rewritten by pass 1), leaving stale URL data in the clone.
	 * In some environments this also silently truncated large TEXT comparisons.
	 *
	 * The fix: update() now uses the table primary key (meta_id) in the WHERE
	 * clause, guaranteeing reliable identification regardless of value size or
	 * prior-pass rewrites.
	 *
	 * Verification: after all replacement passes, the stored value must
	 * unserialize cleanly and contain all expected colors and typography entries,
	 * with lengths differing from the origin only by the predictable URL delta.
	 */
	public function test_update_preserves_elementor_kit_byte_count() {
		global $wpdb;

		$origin_site_url    = 'plantilla2.example.com';
		$clone_site_url     = 'newsite.example.com';
		$origin_upload_base = $origin_site_url . '/wp-content/uploads/sites/97';
		$clone_upload_base  = $clone_site_url . '/wp-content/uploads/sites/145';

		// Build a realistic Elementor Kit _elementor_page_settings payload.
		// This mirrors the structure found in a typical Elementor-powered template
		// site and triggers the full range of replacement passes.
		$kit_settings = [
			'system_colors'     => [
				['_id' => 'primary',   'title' => 'Primary',   'color' => '#6EC1E4'],
				['_id' => 'secondary', 'title' => 'Secondary', 'color' => '#54595F'],
				['_id' => 'text',      'title' => 'Text',      'color' => '#7A7A7A'],
				['_id' => 'accent',    'title' => 'Accent',    'color' => '#61CE70'],
			],
			'custom_colors'     => [
				['_id' => 'brand_dark',  'title' => 'Brand Dark',  'color' => '#1A1A2E'],
				['_id' => 'brand_light', 'title' => 'Brand Light', 'color' => '#E8E8F0'],
			],
			'system_typography' => [
				[
					'_id'                    => 'primary',
					'title'                  => 'Primary',
					'typography_typography'   => 'custom',
					'typography_font_family'  => 'Roboto',
					'typography_font_weight'  => '600',
					'typography_font_size'    => ['unit' => 'px', 'size' => 16],
					'typography_line_height'  => ['unit' => 'em', 'size' => 1.5],
				],
				[
					'_id'                    => 'secondary',
					'title'                  => 'Secondary',
					'typography_typography'   => 'custom',
					'typography_font_family'  => 'Roboto Slab',
					'typography_font_weight'  => '400',
					'typography_font_size'    => ['unit' => 'px', 'size' => 14],
					'typography_line_height'  => ['unit' => 'em', 'size' => 1.6],
				],
				[
					'_id'                    => 'text',
					'title'                  => 'Text',
					'typography_typography'   => 'custom',
					'typography_font_family'  => 'Open Sans',
					'typography_font_weight'  => '400',
					'typography_font_size'    => ['unit' => 'px', 'size' => 15],
					'typography_line_height'  => ['unit' => 'em', 'size' => 1.7],
				],
				[
					'_id'                    => 'accent',
					'title'                  => 'Accent',
					'typography_typography'   => 'custom',
					'typography_font_family'  => 'Montserrat',
					'typography_font_weight'  => '700',
					'typography_font_size'    => ['unit' => 'px', 'size' => 13],
					'typography_line_height'  => ['unit' => 'em', 'size' => 1.4],
				],
			],
			'custom_typography' => [
				[
					'_id'                    => 'heading_brand',
					'title'                  => 'Heading Brand',
					'typography_typography'   => 'custom',
					'typography_font_family'  => 'Playfair Display',
					'typography_font_weight'  => '700',
				],
			],
			'container_width'      => ['size' => 1140, 'unit' => 'px'],
			'space_between_widgets' => ['size' => 20, 'unit' => 'px'],
			'page_title_selector'  => 'h1.entry-title',
			'active_breakpoints'   => ['viewport_mobile', 'viewport_tablet'],
			'site_logo'   => [
				'id'  => 42,
				'url' => 'https://' . $origin_upload_base . '/2024/01/logo.png',
			],
			'site_favicon' => [
				'id'  => 43,
				'url' => 'https://' . $origin_upload_base . '/2024/01/favicon.png',
			],
			'custom_css'  => implode("\n", [
				'/* Brand styles — ' . $origin_site_url . ' */',
				'body { font-family: "Roboto", sans-serif; color: #7A7A7A; }',
				'h1, h2, h3 { color: #1A1A2E; font-family: "Playfair Display", serif; }',
				'a { color: #6EC1E4; }',
				'a:hover { color: #61CE70; }',
				'.site-logo img { max-height: 60px; }',
				'.hero { background-image: url("https://' . $origin_upload_base . '/hero-bg.jpg"); }',
				'.cta-button { background-color: #61CE70; color: #fff; border-radius: 4px; }',
				'.cta-button:hover { background-color: #54595F; }',
			]),
			'_elementor_page_assets' => [
				'fonts'   => ['Roboto' => 1, 'Roboto Slab' => 1, 'Open Sans' => 1, 'Montserrat' => 1, 'Playfair Display' => 1],
				'icons'   => [],
			],
		];

		// Insert directly as raw PHP-serialized data (as WordPress stores it).
		$raw_value      = serialize($kit_settings);
		$original_bytes = strlen($raw_value);

		$post_id = self::factory()->post->create(['post_title' => 'Elementor Kit', 'post_status' => 'publish']);
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->postmeta,
			[
				'post_id'    => $post_id,
				'meta_key'   => '_elementor_page_settings',
				'meta_value' => $raw_value,
			],
			['%d', '%s', '%s']
		);
		$inserted_meta_id = $wpdb->insert_id;

		$this->assertGreaterThan(0, $inserted_meta_id, 'Postmeta row must be inserted');

		// Simulate all 7 replacement passes that db_update_data() applies.
		// Pass order mirrors the string_to_replace array in db_update_data().
		$passes = [
			$origin_upload_base                         => $clone_upload_base,
			str_replace('/', '\\/', $origin_upload_base) => str_replace('/', '\\/', $clone_upload_base),
			$origin_site_url                            => $clone_site_url,
			str_replace('/', '\\/', $origin_site_url)   => str_replace('/', '\\/', $clone_site_url),
		];

		foreach ($passes as $from => $to) {
			\MUCD_Data::update($wpdb->postmeta, ['meta_value'], $from, $to);
		}

		// Read the final stored value directly from DB (bypasses WP caching).
		$final_value = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				'SELECT meta_value FROM ' . $wpdb->postmeta . ' WHERE meta_id = %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$inserted_meta_id
			)
		);

		$this->assertNotNull($final_value, 'Postmeta row must still exist after replacement passes');
		$this->assertNotEquals('b:0;', $final_value, 'Value must not be reduced to serialized false');

		// Unserialize and verify structural integrity.
		$final_data = @unserialize($final_value);
		$this->assertIsArray($final_data, 'Final value must be a valid serialized array — not corrupt');

		// All top-level keys must survive.
		$this->assertArrayHasKey('system_colors', $final_data, 'system_colors key must be preserved');
		$this->assertArrayHasKey('system_typography', $final_data, 'system_typography key must be preserved');
		$this->assertArrayHasKey('custom_colors', $final_data, 'custom_colors key must be preserved');
		$this->assertArrayHasKey('custom_typography', $final_data, 'custom_typography key must be preserved');
		$this->assertArrayHasKey('container_width', $final_data, 'container_width key must be preserved');
		$this->assertArrayHasKey('custom_css', $final_data, 'custom_css key must be preserved');

		// Colors must be 100% intact (they never contain URLs — loss indicates corruption).
		$this->assertCount(4, $final_data['system_colors'], 'All 4 system colors must survive');
		$this->assertEquals('#6EC1E4', $final_data['system_colors'][0]['color'], 'Primary color #6EC1E4 must be preserved');
		$this->assertEquals('#54595F', $final_data['system_colors'][1]['color'], 'Secondary color #54595F must be preserved');
		$this->assertEquals('#7A7A7A', $final_data['system_colors'][2]['color'], 'Text color #7A7A7A must be preserved');
		$this->assertEquals('#61CE70', $final_data['system_colors'][3]['color'], 'Accent color #61CE70 must be preserved');
		$this->assertEquals('#1A1A2E', $final_data['custom_colors'][0]['color'], 'Custom brand-dark color must be preserved');

		// Typography entries must be fully intact.
		$this->assertCount(4, $final_data['system_typography'], 'All 4 typography entries must survive');
		$this->assertEquals('Roboto',         $final_data['system_typography'][0]['typography_font_family']);
		$this->assertEquals('Roboto Slab',    $final_data['system_typography'][1]['typography_font_family']);
		$this->assertEquals('Open Sans',      $final_data['system_typography'][2]['typography_font_family']);
		$this->assertEquals('Montserrat',     $final_data['system_typography'][3]['typography_font_family']);

		// Logo and favicon URLs must be rewritten to the clone.
		$this->assertStringContainsString($clone_upload_base, $final_data['site_logo']['url'], 'Logo URL must point to clone uploads');
		$this->assertStringContainsString($clone_upload_base, $final_data['site_favicon']['url'], 'Favicon URL must point to clone uploads');
		$this->assertStringNotContainsString($origin_upload_base, $final_data['site_logo']['url'], 'Logo URL must not retain origin path');

		// custom_css hero background must be rewritten; brand colors must be intact.
		$this->assertStringContainsString($clone_upload_base, $final_data['custom_css'], 'custom_css background must reference clone uploads');
		$this->assertStringContainsString('#6EC1E4', $final_data['custom_css'], 'Brand color in custom_css must survive');
		$this->assertStringContainsString('#61CE70', $final_data['custom_css'], 'CTA color in custom_css must survive');
		$this->assertStringNotContainsString($origin_site_url, $final_data['custom_css'], 'Origin URL must be gone from custom_css');

		// Byte count should differ only by the predictable URL-length delta,
		// not by a large unexpected amount (which would indicate data corruption).
		$final_bytes   = strlen($final_value);
		$url_delta_per = strlen($origin_upload_base) - strlen($clone_upload_base); // positive if origin longer
		// Two occurrences (logo, favicon), one in custom_css hero: 3 upload-URL replacements.
		// One comment occurrence of the site URL: 1 site-URL replacement.
		$max_expected_delta = abs($url_delta_per) * 10 + abs(strlen($origin_site_url) - strlen($clone_site_url)) * 10;

		$actual_delta = abs($original_bytes - $final_bytes);
		$this->assertLessThanOrEqual(
			$max_expected_delta,
			$actual_delta,
			sprintf(
				'Byte count changed by %d bytes (original %d → final %d). ' .
				'Only URL-length differences (~%d bytes) should cause size changes. ' .
				'A larger delta indicates data corruption — the 748-byte loss bug may have regressed.',
				$actual_delta,
				$original_bytes,
				$final_bytes,
				$max_expected_delta
			)
		);
	}
}
