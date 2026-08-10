<?php
/**
 * Tests for Admin_Bar_Magic_Links class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\SSO;

use WP_UnitTestCase;

/**
 * Test class for Admin_Bar_Magic_Links.
 */
class Admin_Bar_Magic_Links_Test extends WP_UnitTestCase {

	/**
	 * @var Admin_Bar_Magic_Links
	 */
	private $magic_links;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->magic_links = Admin_Bar_Magic_Links::get_instance();
	}

	/**
	 * Test get_instance returns an Admin_Bar_Magic_Links instance.
	 */
	public function test_get_instance_returns_instance(): void {
		$this->assertInstanceOf(Admin_Bar_Magic_Links::class, $this->magic_links);
	}

	/**
	 * Test get_instance returns the same instance (singleton).
	 */
	public function test_get_instance_is_singleton(): void {
		$instance1 = Admin_Bar_Magic_Links::get_instance();
		$instance2 = Admin_Bar_Magic_Links::get_instance();

		$this->assertSame($instance1, $instance2);
	}

	/**
	 * Test modify_my_sites_menu does not throw when user is not logged in.
	 */
	public function test_modify_my_sites_menu_no_user(): void {
		// Ensure no user is logged in.
		wp_set_current_user(0);

		if ( ! class_exists('\WP_Admin_Bar') ) {
			require_once ABSPATH . WPINC . '/class-wp-admin-bar.php';
		}

		$admin_bar = new \WP_Admin_Bar();
		$this->magic_links->modify_my_sites_menu($admin_bar);

		$this->assertTrue(true); // No exception thrown.
	}

	/**
	 * Test dashboard nodes are changed to lazy same-origin action URLs.
	 */
	public function test_modify_my_sites_menu_uses_lazy_action_urls(): void {
		$user_id = self::factory()->user->create();
		$site_id = get_current_blog_id();

		add_user_to_blog($site_id, $user_id, 'administrator');
		wp_set_current_user($user_id);

		if ( ! class_exists('\WP_Admin_Bar') ) {
			require_once ABSPATH . WPINC . '/class-wp-admin-bar.php';
		}

		$admin_bar = new \WP_Admin_Bar();
		$admin_bar->initialize();
		$admin_bar->add_node(
			array(
				'id'   => 'blog-' . $site_id . '-d',
				'href' => 'https://example.test/original-dashboard',
			)
		);
		$admin_bar->add_node(
			array(
				'id'   => 'blog-' . $site_id . '-c',
				'href' => 'https://example.test/original-site',
			)
		);
		$admin_bar->add_node(
			array(
				'id'   => 'blog-' . $site_id . '-d-extra',
				'href' => 'https://example.test/malformed-dashboard',
			)
		);

		$generated_magic_links = 0;
		$magic_link_filter     = static function ($url) use (&$generated_magic_links) {
			++$generated_magic_links;

			return $url;
		};

		add_filter('wu_magic_link_url', $magic_link_filter);
		$this->magic_links->modify_my_sites_menu($admin_bar);
		remove_filter('wu_magic_link_url', $magic_link_filter);

		$dashboard_node = $admin_bar->get_node('blog-' . $site_id . '-d');
		$site_node      = $admin_bar->get_node('blog-' . $site_id . '-c');
		$malformed_node = $admin_bar->get_node('blog-' . $site_id . '-d-extra');
		$action_args    = array();

		wp_parse_str(wp_parse_url($dashboard_node->href, PHP_URL_QUERY), $action_args);

		$this->assertSame(Admin_Bar_Magic_Links::ADMIN_POST_ACTION, $action_args['action']);
		$this->assertSame((string) $site_id, $action_args[ Admin_Bar_Magic_Links::SITE_ID_QUERY_ARG ]);
		$this->assertNotFalse(wp_verify_nonce($action_args['_wpnonce'], Admin_Bar_Magic_Links::ADMIN_POST_ACTION . '_' . $site_id));
		$this->assertSame(0, $generated_magic_links);
		$this->assertSame('https://example.test/original-site', $site_node->href);
		$this->assertSame('https://example.test/malformed-dashboard', $malformed_node->href);
	}

	/**
	 * Test only accessible, existing sites can resolve dashboard URLs.
	 */
	public function test_get_site_dashboard_url_rejects_inaccessible_and_missing_sites(): void {
		$user_id = self::factory()->user->create();
		$site_id = get_current_blog_id();

		add_user_to_blog($site_id, $user_id, 'administrator');
		wp_set_current_user($user_id);

		$this->assertSame(wu_get_admin_url($site_id), $this->magic_links->get_site_dashboard_url($site_id));
		$this->assertFalse($this->magic_links->get_site_dashboard_url(999999));

		$inaccessible_site_id = self::factory()->blog->create();

		$this->assertFalse($this->magic_links->get_site_dashboard_url($inaccessible_site_id));
	}

	/**
	 * Test malformed request values cannot reach nonce or site validation.
	 */
	public function test_request_values_reject_arrays(): void {
		$site_id_method = new \ReflectionMethod($this->magic_links, 'get_requested_site_id');
		$nonce_method   = new \ReflectionMethod($this->magic_links, 'get_requested_nonce');
		$request        = $_REQUEST; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tests malformed request input.

		if (PHP_VERSION_ID < 80100) {
			$site_id_method->setAccessible(true);
			$nonce_method->setAccessible(true);
		}

		$_REQUEST[ Admin_Bar_Magic_Links::SITE_ID_QUERY_ARG ] = array('invalid'); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tests malformed request input.
		$_REQUEST['_wpnonce']                                 = array('invalid'); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tests malformed request input.

		try {
			$this->assertFalse($site_id_method->invoke($this->magic_links));
			$this->assertFalse($nonce_method->invoke($this->magic_links));
		} finally {
			$_REQUEST = $request; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Restores malformed request input fixture.
		}
	}

	/**
	 * Test the handler redirects to the resolved same-domain dashboard URL.
	 */
	public function test_handle_admin_bar_magic_link_redirects_to_resolved_same_domain_url(): void {
		$user_id = self::factory()->user->create();
		$site_id = get_current_blog_id();
		$request = $_REQUEST; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Restores the request fixture.

		add_user_to_blog($site_id, $user_id, 'administrator');
		wp_set_current_user($user_id);

		$_REQUEST = array( // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Sets the handler request fixture.
			Admin_Bar_Magic_Links::SITE_ID_QUERY_ARG => (string) $site_id,
			'_wpnonce'                               => wp_create_nonce(Admin_Bar_Magic_Links::ADMIN_POST_ACTION . '_' . $site_id),
			'redirect_to'                            => 'https://attacker.example.test/',
		);

		$redirect        = array();
		$redirect_filter = static function ($location, $status) use (&$redirect) {
			$redirect = array(
				'location' => $location,
				'status'   => $status,
			);

			throw new \RuntimeException('redirect_intercepted');
		};

		add_filter('wp_redirect', $redirect_filter, 10, 2);

		try {
			$this->magic_links->handle_admin_bar_magic_link();
		} catch (\RuntimeException $e) {
			$this->assertSame('redirect_intercepted', $e->getMessage());
		} finally {
			remove_filter('wp_redirect', $redirect_filter, 10);
			$_REQUEST = $request; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Restores the request fixture.
		}

		$this->assertSame(get_admin_url($site_id), $redirect['location']);
		$this->assertSame(302, $redirect['status']);
	}

	/**
	 * Test the handler permits the resolved mapped-domain magic link only.
	 */
	public function test_handle_admin_bar_magic_link_redirects_to_resolved_mapped_domain_url(): void {
		$user_id = self::factory()->user->create();
		$site_id = self::factory()->blog->create();
		$request = $_REQUEST; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Restores the request fixture.

		add_user_to_blog($site_id, $user_id, 'administrator');
		wp_set_current_user($user_id);

		$mapping = wu_create_domain(
			array(
				'blog_id'        => $site_id,
				'domain'         => 'admin-bar-magic-links.example.test',
				'active'         => true,
				'primary_domain' => true,
				'secure'         => false,
				'stage'          => \WP_Ultimo\Database\Domains\Domain_Stage::DONE,
			)
		);

		$this->assertNotWPError($mapping);

		$_REQUEST = array( // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Sets the handler request fixture.
			Admin_Bar_Magic_Links::SITE_ID_QUERY_ARG => (string) $site_id,
			'_wpnonce'                               => wp_create_nonce(Admin_Bar_Magic_Links::ADMIN_POST_ACTION . '_' . $site_id),
			'redirect_to'                            => 'https://attacker.example.test/',
		);

		$magic_link        = 'https://admin-bar-magic-links.example.test/wp-admin/?wu_magic_token=test-token';
		$redirect          = array();
		$redirect_to       = '';
		$magic_link_filter = static function ($url, $filter_user_id, $filter_site_id, $filter_redirect_to) use (&$redirect_to, $magic_link) {
			$redirect_to = $filter_redirect_to;

			return $magic_link;
		};
		$redirect_filter   = static function ($location, $status) use (&$redirect) {
			$redirect = array(
				'location' => $location,
				'status'   => $status,
			);

			throw new \RuntimeException('redirect_intercepted');
		};

		add_filter('wu_magic_links_enabled', '__return_true');
		add_filter('wu_magic_link_url', $magic_link_filter, 10, 4);
		add_filter('wp_redirect', $redirect_filter, 10, 2);

		try {
			$this->magic_links->handle_admin_bar_magic_link();
		} catch (\RuntimeException $e) {
			$this->assertSame('redirect_intercepted', $e->getMessage());
		} finally {
			remove_filter('wu_magic_links_enabled', '__return_true');
			remove_filter('wu_magic_link_url', $magic_link_filter, 10);
			remove_filter('wp_redirect', $redirect_filter, 10);
			$_REQUEST = $request; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Restores the request fixture.
		}

		$this->assertSame($magic_link, $redirect['location']);
		$this->assertSame(302, $redirect['status']);
		$this->assertSame(get_admin_url($site_id), $redirect_to);
	}

	/**
	 * Test invalid nonces and inaccessible sites are rejected by the handler.
	 */
	public function test_handle_admin_bar_magic_link_rejects_invalid_nonce_and_inaccessible_site(): void {
		$user_id = self::factory()->user->create();
		$site_id = get_current_blog_id();
		$request = $_REQUEST; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Restores the request fixture.

		add_user_to_blog($site_id, $user_id, 'administrator');
		wp_set_current_user($user_id);

		$die_handler = static function () {
			return static function ($message) {
				throw new \WPDieException(esc_html((string) $message));
			};
		};

		add_filter('wp_die_handler', $die_handler, 1);

		try {
			$_REQUEST = array( // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Sets the handler request fixture.
				Admin_Bar_Magic_Links::SITE_ID_QUERY_ARG => (string) $site_id,
				'_wpnonce'                               => 'invalid-nonce',
			);

			try {
				$this->magic_links->handle_admin_bar_magic_link();
			} catch (\WPDieException $e) {
				$this->assertSame('The requested site link is invalid.', $e->getMessage());
			}

			$inaccessible_site_id = self::factory()->blog->create();
			$_REQUEST             = array( // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Sets the handler request fixture.
				Admin_Bar_Magic_Links::SITE_ID_QUERY_ARG => (string) $inaccessible_site_id,
				'_wpnonce'                               => wp_create_nonce(Admin_Bar_Magic_Links::ADMIN_POST_ACTION . '_' . $inaccessible_site_id),
			);

			try {
				$this->magic_links->handle_admin_bar_magic_link();
			} catch (\WPDieException $e) {
				$this->assertSame('You do not have permission to access this site.', $e->getMessage());
			}
		} finally {
			remove_filter('wp_die_handler', $die_handler, 1);
			$_REQUEST = $request; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Restores the request fixture.
		}
	}
}
