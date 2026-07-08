<?php
/**
 * Tests for the Newsletter class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo;

use WP_UnitTestCase;

/**
 * Test class for Newsletter.
 */
class Newsletter_Test extends WP_UnitTestCase {

	/**
	 * Test get_instance returns Newsletter.
	 */
	public function test_get_instance(): void {

		$newsletter = \WP_Ultimo\Newsletter::get_instance();

		$this->assertInstanceOf(\WP_Ultimo\Newsletter::class, $newsletter);
	}

	/**
	 * Test SETTING_FIELD_SLUG constant.
	 */
	public function test_setting_field_slug(): void {

		$this->assertSame('newsletter_optin', \WP_Ultimo\Newsletter::SETTING_FIELD_SLUG);
	}

	/**
	 * Test maybe_update_newsletter_subscription returns settings unchanged when no optin change.
	 */
	public function test_maybe_update_newsletter_subscription_no_change(): void {

		$newsletter = \WP_Ultimo\Newsletter::get_instance();

		$settings         = [
			'company_email'   => 'test@example.com',
			'company_name'    => 'Test',
			'company_country' => 'US',
		];
		$settings_to_save = [];
		$saved_settings   = [];

		$result = $newsletter->maybe_update_newsletter_subscription($settings, $settings_to_save, $saved_settings);

		$this->assertSame($settings, $result);
	}

	/**
	 * Test maybe_update_newsletter_subscription returns settings when optin same as saved.
	 */
	public function test_maybe_update_newsletter_subscription_same_value(): void {

		$newsletter = \WP_Ultimo\Newsletter::get_instance();

		$settings         = [
			'company_email'   => 'test@example.com',
			'company_name'    => 'Test',
			'company_country' => 'US',
		];
		$settings_to_save = ['newsletter_optin' => '1'];
		$saved_settings   = ['newsletter_optin' => '1'];

		$result = $newsletter->maybe_update_newsletter_subscription($settings, $settings_to_save, $saved_settings);

		$this->assertSame($settings, $result);
	}
}
