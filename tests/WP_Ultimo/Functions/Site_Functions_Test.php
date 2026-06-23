<?php
/**
 * Tests for site functions.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Functions;

use WP_UnitTestCase;

/**
 * Test class for site functions.
 */
class Site_Functions_Test extends WP_UnitTestCase {

	/**
	 * Test wu_generate_site_url_from_title with normal title.
	 */
	public function test_generate_site_url_from_title_normal(): void {
		$url = wu_generate_site_url_from_title('My Cool Site');

		$this->assertEquals('mycoolsite', $url);
	}

	/**
	 * Test wu_generate_site_url_from_title with special characters.
	 */
	public function test_generate_site_url_from_title_special_chars(): void {
		$url = wu_generate_site_url_from_title("John's Blog!");

		$this->assertEquals('johnsblog', $url);
	}

	/**
	 * Test wu_generate_site_url_from_title with unicode.
	 */
	public function test_generate_site_url_from_title_unicode(): void {
		$url = wu_generate_site_url_from_title('Café Blog');

		// Non-ascii chars should be removed
		$this->assertEquals('cafblog', $url);
	}

	/**
	 * Test wu_generate_site_url_from_title with empty string.
	 */
	public function test_generate_site_url_from_title_empty(): void {
		$url = wu_generate_site_url_from_title('');

		$this->assertEquals('', $url);
	}

	/**
	 * Test wu_generate_site_url_from_title with numbers at start.
	 */
	public function test_generate_site_url_from_title_starts_with_number(): void {
		$url = wu_generate_site_url_from_title('123 Test Site');

		// Should prepend 'site' if starts with number
		$this->assertStringStartsWith('site', $url);
	}

	/**
	 * Test wu_generate_site_url_from_title with dash at start.
	 */
	public function test_generate_site_url_from_title_starts_with_dash(): void {
		$url = wu_generate_site_url_from_title('-test-site');

		// Should prepend 'site' if starts with dash
		$this->assertStringStartsWith('site', $url);
	}

	/**
	 * Test wu_generate_site_title_from_email with normal email.
	 */
	public function test_generate_site_title_from_email_normal(): void {
		$title = wu_generate_site_title_from_email('john.doe@example.com');

		$this->assertEquals('John Doe', $title);
	}

	/**
	 * Test wu_generate_site_title_from_email with underscore separator.
	 */
	public function test_generate_site_title_from_email_underscore(): void {
		$title = wu_generate_site_title_from_email('jane_smith@example.com');

		$this->assertEquals('Jane Smith', $title);
	}

	/**
	 * Test wu_generate_site_title_from_email with generic prefix.
	 */
	public function test_generate_site_title_from_email_generic(): void {
		$title = wu_generate_site_title_from_email('admin@company.com');

		// Should fall back to domain
		$this->assertStringContainsString('Company', $title);
	}

	/**
	 * Test wu_generate_site_title_from_email with invalid email.
	 */
	public function test_generate_site_title_from_email_invalid(): void {
		$title = wu_generate_site_title_from_email('not-an-email');

		$this->assertEquals('', $title);
	}

	/**
	 * Test wu_generate_site_title_from_email with empty string.
	 */
	public function test_generate_site_title_from_email_empty(): void {
		$title = wu_generate_site_title_from_email('');

		$this->assertEquals('', $title);
	}

	/**
	 * Test wu_generate_site_title_from_email adds Site suffix for short names.
	 */
	public function test_generate_site_title_from_email_short_name(): void {
		$title = wu_generate_site_title_from_email('jo@example.com');

		// Short names should have " Site" appended
		$this->assertStringContainsString('Site', $title);
	}

	/**
	 * Test wu_get_current_site returns Site object.
	 */
	public function test_get_current_site_returns_site(): void {
		$site = wu_get_current_site();

		$this->assertInstanceOf(\WP_Ultimo\Models\Site::class, $site);
	}

	/**
	 * Test wu_get_sites returns array.
	 */
	public function test_get_sites_returns_array(): void {
		$sites = wu_get_sites();

		$this->assertIsArray($sites);
	}

	/**
	 * Test wu_get_sites with count returns int.
	 */
	public function test_get_sites_count(): void {
		$count = wu_get_sites(['count' => true]);

		$this->assertIsInt($count);
	}

	/**
	 * Test wu_get_site_templates returns array.
	 */
	public function test_get_site_templates_returns_array(): void {
		$templates = wu_get_site_templates();

		$this->assertIsArray($templates);
	}

	/**
	 * Test wu_get_site with invalid id returns false.
	 */
	public function test_get_site_invalid_id(): void {
		$site = wu_get_site(999999);

		$this->assertFalse($site);
	}

	/**
	 * Test wu_normalize_sites_list filters missing sites.
	 */
	public function test_normalize_sites_list_filters_missing_sites(): void {
		$sites = wu_normalize_sites_list([get_current_blog_id(), 999999, false]);

		$this->assertCount(1, $sites);
		$this->assertInstanceOf(\WP_Ultimo\Models\Site::class, $sites[0]);
		$this->assertSame(get_current_blog_id(), $sites[0]->get_id());
	}

	/**
	 * Test wu_normalize_sites_list rejects non-integer scalar site identifiers.
	 */
	public function test_normalize_sites_list_rejects_non_integer_scalar_site_ids(): void {
		$sites = wu_normalize_sites_list([true, 1.5, '1.5', 'site-1', '0', 0, -1]);

		$this->assertSame([], $sites);
	}

	/**
	 * Test wu_get_site_by_hash with invalid hash returns false.
	 */
	public function test_get_site_by_hash_invalid(): void {
		$site = wu_get_site_by_hash('invalid-hash');

		$this->assertFalse($site);
	}
}
