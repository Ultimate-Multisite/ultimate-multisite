<?php
/**
 * Tests for domain functions.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Functions;

use WP_UnitTestCase;

/**
 * Test class for domain functions.
 */
class Domain_Functions_Test extends WP_UnitTestCase {

	/**
	 * Test wu_get_domain returns false for non-existent ID.
	 */
	public function test_wu_get_domain_returns_false_for_invalid_id(): void {

		$result = wu_get_domain(999999);

		$this->assertFalse($result);
	}

	/**
	 * Test wu_get_domains returns array.
	 */
	public function test_wu_get_domains_returns_array(): void {

		$result = wu_get_domains();

		$this->assertIsArray($result);
	}

	/**
	 * Test wu_get_domains with query args.
	 */
	public function test_wu_get_domains_with_query(): void {

		$result = wu_get_domains(['number' => 5]);

		$this->assertIsArray($result);
	}

	/**
	 * Test wu_create_domain creates a domain.
	 */
	public function test_wu_create_domain(): void {

		$domain = wu_create_domain([
			'blog_id'        => 1,
			'domain'         => 'test-domain-' . wp_rand() . '.example.com',
			'active'         => true,
			'primary_domain' => false,
			'secure'         => true,
			'stage'          => 'checking-dns',
		]);

		$this->assertNotWPError($domain);
		$this->assertInstanceOf(\WP_Ultimo\Models\Domain::class, $domain);
	}

	/**
	 * Test wu_create_domain and retrieve by ID.
	 */
	public function test_wu_create_and_get_domain(): void {

		$domain_name = 'retrieve-test-' . wp_rand() . '.example.com';

		$domain = wu_create_domain([
			'blog_id' => 1,
			'domain'  => $domain_name,
		]);

		$this->assertNotWPError($domain);

		$domain_id = $domain->get_id();

		if ($domain_id) {
			$retrieved = wu_get_domain($domain_id);
			$this->assertNotFalse($retrieved);
			$this->assertSame($domain_name, $retrieved->get_domain());
		} else {
			$this->markTestSkipped('Domain save did not return an ID.');
		}
	}

	/**
	 * Test wu_is_same_domain returns bool.
	 */
	public function test_wu_is_same_domain_returns_bool(): void {

		$result = wu_is_same_domain();

		$this->assertIsBool($result);
	}

	/**
	 * Test wu_restore_original_url returns string.
	 */
	public function test_wu_restore_original_url_returns_string(): void {

		$url = 'https://example.com/test-page';

		$result = wu_restore_original_url($url, 1);

		$this->assertIsString($result);
	}

	/**
	 * Test wu_with_sso returns the default SSO URL when no filter is attached.
	 */
	public function test_wu_with_sso_returns_default_sso_url(): void {

		$url = home_url('/test-page');

		$this->assertSame(\WP_Ultimo\SSO\SSO::with_sso($url), wu_with_sso($url));
	}

	/**
	 * Test wu_with_sso exposes the SSO URL filter with context.
	 */
	public function test_wu_with_sso_filters_url_with_context(): void {

		$user_id = self::factory()->user->create();
		$url     = home_url('/test-page');
		$host    = wp_parse_url($url, PHP_URL_HOST);

		wp_set_current_user($user_id);

		$filter = function ($sso_url, $user, $site_id, $redirect_to) use ($host) {

			$this->assertInstanceOf(\WP_User::class, $user);
			$this->assertSame(get_current_user_id(), $user->ID);
			$this->assertSame((int) get_blog_id_from_url($host), $site_id);
			$this->assertSame('', $redirect_to);

			return $sso_url . '#test-filter-fired';
		};

		add_filter(
			'wu_sso_url',
			$filter,
			10,
			4
		);

		$this->assertSame(\WP_Ultimo\SSO\SSO::with_sso($url) . '#test-filter-fired', wu_with_sso($url));

		remove_filter('wu_sso_url', $filter, 10);
	}
}
