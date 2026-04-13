<?php
/**
 * Extended tests for site functions.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Functions;

use WP_UnitTestCase;

/**
 * Test class for site functions.
 */
class Site_Functions_Extended_Test extends WP_UnitTestCase {

	/**
	 * Test wu_get_site returns false for nonexistent.
	 */
	public function test_get_site_nonexistent(): void {

		$result = wu_get_site(999999);

		$this->assertFalse($result);
	}

	/**
	 * Test wu_get_site_by_hash returns false for nonexistent.
	 */
	public function test_get_site_by_hash_nonexistent(): void {

		$result = wu_get_site_by_hash('nonexistent_hash');

		$this->assertFalse($result);
	}

	/**
	 * Test wu_get_sites returns array.
	 */
	public function test_get_sites_returns_array(): void {

		$result = wu_get_sites();

		$this->assertIsArray($result);
	}

	/**
	 * Test wu_get_sites with search.
	 */
	public function test_get_sites_with_search(): void {

		$result = wu_get_sites(['search' => 'nonexistent_xyz']);

		$this->assertIsArray($result);
	}

	/**
	 * Test wu_get_sites with count.
	 */
	public function test_get_sites_with_count(): void {

		$result = wu_get_sites(['count' => true]);

		$this->assertIsInt($result);
	}

	/**
	 * Test wu_get_site_templates returns array.
	 */
	public function test_get_site_templates(): void {

		$result = wu_get_site_templates();

		$this->assertIsArray($result);
	}

	/**
	 * Test wu_get_current_site returns a Site model.
	 */
	public function test_get_current_site(): void {

		$result = wu_get_current_site();

		$this->assertInstanceOf(\WP_Ultimo\Models\Site::class, $result);
	}

	/**
	 * Test wu_handle_site_domain parses domain correctly.
	 */
	public function test_handle_site_domain(): void {

		$result = wu_handle_site_domain('example.com');

		$this->assertIsObject($result);
		$this->assertObjectHasProperty('host', $result);
		$this->assertEquals('example.com', $result->host);
	}

	/**
	 * Test wu_handle_site_domain with http prefix.
	 */
	public function test_handle_site_domain_with_http(): void {

		$result = wu_handle_site_domain('https://example.com/path');

		$this->assertIsObject($result);
		$this->assertEquals('example.com', $result->host);
		$this->assertEquals('/path', $result->path);
	}

	/**
	 * Test wu_get_site_domain_and_path returns object.
	 */
	public function test_get_site_domain_and_path(): void {

		$result = wu_get_site_domain_and_path('testsite');

		$this->assertIsObject($result);
		$this->assertObjectHasProperty('domain', $result);
		$this->assertObjectHasProperty('path', $result);
	}

	/**
	 * Test wu_generate_site_url_from_title basic conversion.
	 */
	public function test_generate_site_url_from_title(): void {

		$result = wu_generate_site_url_from_title('My Cool Site');

		$this->assertIsString($result);
		$this->assertEquals('mycoolsite', $result);
	}

	/**
	 * Test wu_generate_site_url_from_title with empty string.
	 */
	public function test_generate_site_url_from_title_empty(): void {

		$result = wu_generate_site_url_from_title('');

		$this->assertEquals('', $result);
	}

	/**
	 * Test wu_generate_site_url_from_title with numeric start.
	 */
	public function test_generate_site_url_from_title_numeric_start(): void {

		$result = wu_generate_site_url_from_title('123test');

		$this->assertStringStartsWith('site', $result);
	}

	/**
	 * Test wu_generate_site_url_from_title with special characters.
	 */
	public function test_generate_site_url_from_title_special_chars(): void {

		$result = wu_generate_site_url_from_title('Hello World!');

		$this->assertEquals('helloworld', $result);
	}

	/**
	 * Test wu_generate_site_title_from_email with valid email.
	 */
	public function test_generate_site_title_from_email(): void {

		$result = wu_generate_site_title_from_email('john.doe@example.com');

		$this->assertIsString($result);
		$this->assertNotEmpty($result);
		$this->assertStringContainsString('John', $result);
	}

	/**
	 * Test wu_generate_site_title_from_email with generic prefix.
	 */
	public function test_generate_site_title_from_email_generic(): void {

		$result = wu_generate_site_title_from_email('info@example.com');

		$this->assertIsString($result);
		$this->assertNotEmpty($result);
		// Should use domain part since 'info' is generic
		$this->assertStringContainsString('Example', $result);
	}

	/**
	 * Test wu_generate_site_title_from_email with invalid email.
	 */
	public function test_generate_site_title_from_email_invalid(): void {

		$result = wu_generate_site_title_from_email('not-an-email');

		$this->assertEquals('', $result);
	}

	/**
	 * Test wu_generate_unique_site_url returns string.
	 */
	public function test_generate_unique_site_url(): void {

		$result = wu_generate_unique_site_url('uniquetestsite' . wp_rand());

		$this->assertIsString($result);
		$this->assertNotEmpty($result);
	}

	/**
	 * Test that the subdomain slug sanitization logic produces valid hostnames.
	 *
	 * This verifies the sanitize_title_with_dashes + wu_clean pipeline used
	 * by wu_create_site when converting a path to a subdomain slug.
	 */
	public function test_subdomain_slug_sanitization_produces_valid_hostname(): void {

		// Simulate the sanitization pipeline from wu_create_site.
		$raw_slug = trim('/My Cool Site!/', '/');
		$slug     = sanitize_title_with_dashes(wu_clean($raw_slug));

		$this->assertNotEmpty($slug, 'A path with valid characters should produce a non-empty slug.');
		$this->assertEquals('my-cool-site', $slug);
		$this->assertMatchesRegularExpression(
			'/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/',
			$slug,
			'Slug should be a valid DNS label (lowercase alphanumeric + hyphens).'
		);
	}

	/**
	 * Test that paths with only invalid characters sanitize to empty.
	 *
	 * This verifies the guard in wu_create_site that returns WP_Error when
	 * the sanitized slug is empty, preventing malformed hostnames.
	 */
	public function test_subdomain_slug_sanitization_empty_for_invalid_path(): void {

		// Paths with only special characters should sanitize to empty.
		$raw_slug = trim('/!!!/', '/');
		$slug     = sanitize_title_with_dashes(wu_clean($raw_slug));

		$this->assertEmpty($slug, 'A path with only special characters should sanitize to empty.');
	}

	/**
	 * Test that unicode paths sanitize to valid slugs.
	 */
	public function test_subdomain_slug_sanitization_handles_unicode(): void {

		$raw_slug = trim('/Café Blog/', '/');
		$slug     = sanitize_title_with_dashes(wu_clean($raw_slug));

		$this->assertNotEmpty($slug, 'A path with unicode chars should produce a non-empty slug.');
		// Should contain only valid hostname characters.
		$this->assertMatchesRegularExpression(
			'/^[a-z0-9%]([a-z0-9%-]*[a-z0-9%])?$/',
			$slug,
			'Slug should only contain valid hostname characters.'
		);
	}
}
