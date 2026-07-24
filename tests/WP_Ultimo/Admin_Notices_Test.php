<?php
/**
 * Test case for Admin_Notices.
 *
 * @package WP_Ultimo
 * @subpackage Tests
 */

namespace WP_Ultimo\Tests;

use WP_Ultimo\Admin_Notices;

/**
 * Test Admin_Notices functionality.
 */
class Admin_Notices_Test extends \WP_UnitTestCase {

	/**
	 * The Admin_Notices instance.
	 *
	 * @var Admin_Notices
	 */
	private $notices;

	/**
	 * Set up test.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->notices = Admin_Notices::get_instance();

		// Reset notices via reflection
		$reflection = new \ReflectionClass($this->notices);
		$prop       = $reflection->getProperty('notices');

		if (PHP_VERSION_ID < 80100) {
			$prop->setAccessible(true);
		}

		$prop->setValue($this->notices, [
			'admin'         => [],
			'network-admin' => [],
			'user'          => [],
		]);
	}

	/**
	 * Clean up the shared recent-error notice state.
	 */
	public function tear_down(): void {

		unset($_REQUEST['page']);
		delete_site_option('wu_recent_error_log_entry');

		parent::tear_down();
	}

	/**
	 * Test singleton instance.
	 */
	public function test_singleton_instance(): void {

		$this->assertInstanceOf(Admin_Notices::class, $this->notices);
		$this->assertSame($this->notices, Admin_Notices::get_instance());
	}

	/**
	 * Test add notice to admin panel.
	 */
	public function test_add_notice_admin(): void {

		$this->notices->add('Test notice', 'success', 'admin');

		$notices = $this->notices->get_notices('admin', false);

		$this->assertNotEmpty($notices);
		$this->assertCount(1, $notices);

		$notice = reset($notices);
		$this->assertEquals('success', $notice['type']);
		$this->assertEquals('Test notice', $notice['message']);
	}

	/**
	 * Test add notice to network-admin panel.
	 */
	public function test_add_notice_network_admin(): void {

		$this->notices->add('Network notice', 'warning', 'network-admin');

		$notices = $this->notices->get_notices('network-admin', false);

		$this->assertNotEmpty($notices);
		$this->assertCount(1, $notices);

		$notice = reset($notices);
		$this->assertEquals('warning', $notice['type']);
		$this->assertEquals('Network notice', $notice['message']);
	}

	/**
	 * Test add notice with different types.
	 */
	public function test_add_notice_types(): void {

		$this->notices->add('Info notice', 'info', 'admin');
		$this->notices->add('Error notice', 'error', 'admin');
		$this->notices->add('Success notice', 'success', 'admin');
		$this->notices->add('Warning notice', 'warning', 'admin');

		$notices = $this->notices->get_notices('admin', false);

		$this->assertCount(4, $notices);
	}

	/**
	 * Test add notice with dismissible key.
	 */
	public function test_add_notice_dismissible(): void {

		$this->notices->add('Dismissible notice', 'info', 'admin', 'test-dismiss-key');

		$notices = $this->notices->get_notices('admin', false);

		$notice = reset($notices);
		$this->assertEquals('test-dismiss-key', $notice['dismissible_key']);
	}

	/**
	 * Test add notice without dismissible key.
	 */
	public function test_add_notice_not_dismissible(): void {

		$this->notices->add('Non-dismissible notice', 'info', 'admin');

		$notices = $this->notices->get_notices('admin', false);

		$notice = reset($notices);
		$this->assertFalse($notice['dismissible_key']);
	}

	/**
	 * Test add notice with actions.
	 */
	public function test_add_notice_with_actions(): void {

		$actions = [
			'activate' => [
				'title' => 'Activate',
				'url'   => 'https://example.com/activate',
			],
		];

		$this->notices->add('Notice with actions', 'info', 'admin', false, $actions);

		$notices = $this->notices->get_notices('admin', false);

		$notice = reset($notices);
		$this->assertNotEmpty($notice['actions']);
		$this->assertArrayHasKey('activate', $notice['actions']);
	}

	/**
	 * Test get_notices returns empty for panel with no notices.
	 */
	public function test_get_notices_empty_panel(): void {

		$notices = $this->notices->get_notices('user', false);

		$this->assertIsArray($notices);
		$this->assertEmpty($notices);
	}

	/**
	 * Test get_notices filters dismissed notices.
	 */
	public function test_get_notices_filters_dismissed(): void {

		// Add a dismissible notice
		$this->notices->add('Should be filtered', 'info', 'admin', 'already-dismissed');

		// Mark it as dismissed
		$user_id = self::factory()->user->create();
		wp_set_current_user($user_id);
		update_user_meta($user_id, 'wu_dismissed_admin_notices', ['already-dismissed']);

		$notices = $this->notices->get_notices('admin', true);

		$this->assertEmpty($notices);
	}

	/**
	 * Test get_notices does not filter non-dismissed notices.
	 */
	public function test_get_notices_keeps_non_dismissed(): void {

		$this->notices->add('Should remain', 'info', 'admin', 'not-dismissed');

		$user_id = self::factory()->user->create();
		wp_set_current_user($user_id);
		update_user_meta($user_id, 'wu_dismissed_admin_notices', ['other-key']);

		$notices = $this->notices->get_notices('admin', true);

		$this->assertNotEmpty($notices);
	}

	/**
	 * Test get_dismissed_notices returns empty for new user.
	 */
	public function test_get_dismissed_notices_empty(): void {

		$user_id = self::factory()->user->create();
		wp_set_current_user($user_id);

		$dismissed = $this->notices->get_dismissed_notices();

		$this->assertIsArray($dismissed);
		$this->assertEmpty($dismissed);
	}

	/**
	 * Test get_current_panel returns string.
	 */
	public function test_get_current_panel(): void {

		$panel = $this->notices->get_current_panel();

		$this->assertIsString($panel);
		$this->assertContains($panel, ['admin', 'network-admin', 'user']);
	}

	/**
	 * Test multiple notices on same panel.
	 */
	public function test_multiple_notices_same_panel(): void {

		$this->notices->add('Notice 1', 'info', 'admin');
		$this->notices->add('Notice 2', 'warning', 'admin');
		$this->notices->add('Notice 3', 'error', 'admin');

		$notices = $this->notices->get_notices('admin', false);

		$this->assertCount(3, $notices);
	}

	/**
	 * Test notices on different panels are isolated.
	 */
	public function test_notices_panel_isolation(): void {

		$this->notices->add('Admin notice', 'info', 'admin');
		$this->notices->add('Network notice', 'info', 'network-admin');

		$admin_notices   = $this->notices->get_notices('admin', false);
		$network_notices = $this->notices->get_notices('network-admin', false);

		$this->assertCount(1, $admin_notices);
		$this->assertCount(1, $network_notices);
	}

	/**
	 * Test wu_admin_notices filter.
	 */
	public function test_admin_notices_filter(): void {

		$this->notices->add('Original notice', 'info', 'admin');

		add_filter('wu_admin_notices', function ($notices) {
			$notices['custom'] = [
				'type'            => 'info',
				'message'         => 'Filtered notice',
				'dismissible_key' => false,
				'actions'         => [],
			];
			return $notices;
		});

		$notices = $this->notices->get_notices('admin', false);

		$this->assertArrayHasKey('custom', $notices);

		remove_all_filters('wu_admin_notices');
	}

	/**
	 * Test recent error log notice is added on Ultimate Multisite network pages.
	 */
	public function test_recent_error_notice_added_on_ultimate_multisite_network_page(): void {

		$user_id = self::factory()->user->create(['role' => 'administrator']);
		grant_super_admin($user_id);
		wp_set_current_user($user_id);

		$_REQUEST['page'] = 'wp-ultimo-events';

		update_site_option(
			'wu_recent_error_log_entry',
			[
				'handle'    => 'integration-hostinger',
				'message'   => 'Hostinger API failed',
				'level'     => \Psr\Log\LogLevel::ERROR,
				'timestamp' => time(),
			]
		);

		$reflection = new \ReflectionClass($this->notices);
		$method     = $reflection->getMethod('maybe_add_recent_error_notice');

		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$method->invoke($this->notices, 'network-admin');

		$notices = $this->notices->get_notices('network-admin', false);
		$notice  = reset($notices);

		$this->assertCount(1, $notices);
		$this->assertSame('error', $notice['type']);
		$this->assertStringContainsString('integration-hostinger.log', $notice['message']);
		$this->assertArrayHasKey('view-logs', $notice['actions']);
	}

	/**
	 * Test recent error notices logged within the same second are independently dismissible.
	 */
	public function test_recent_error_notices_with_same_timestamp_have_unique_dismissible_keys(): void {

		$user_id = self::factory()->user->create(['role' => 'administrator']);
		grant_super_admin($user_id);
		wp_set_current_user($user_id);

		$_REQUEST['page'] = 'wp-ultimo-events';

		$reflection = new \ReflectionClass($this->notices);
		$method     = $reflection->getMethod('maybe_add_recent_error_notice');

		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$timestamp = time();

		update_site_option(
			'wu_recent_error_log_entry',
			[
				'handle'    => 'integration-hostinger',
				'message'   => 'First API failure',
				'level'     => \Psr\Log\LogLevel::ERROR,
				'timestamp' => $timestamp,
			]
		);

		$method->invoke($this->notices, 'network-admin');

		update_site_option(
			'wu_recent_error_log_entry',
			[
				'handle'    => 'integration-hostinger',
				'message'   => 'Second API failure',
				'level'     => \Psr\Log\LogLevel::ERROR,
				'timestamp' => $timestamp,
			]
		);

		$method->invoke($this->notices, 'network-admin');

		$notices = $this->notices->get_notices('network-admin', false);

		$this->assertCount(2, $notices);
		$this->assertCount(2, array_unique(array_column($notices, 'dismissible_key')));
	}
}
