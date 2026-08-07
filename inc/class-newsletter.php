<?php
/**
 * Newsletter Subscription Management Class
 */

namespace WP_Ultimo;

defined('ABSPATH') || exit;

/**
 * Newsletter subscription management class.
 *
 * Handles newsletter opt-in settings and subscription management
 * for the WP Ultimo plugin.
 */
class Newsletter {
	use \WP_Ultimo\Traits\Singleton;

	const SETTING_FIELD_SLUG = 'newsletter_optin';

	/**
	 * Initializes the newsletter functionality.
	 *
	 * Sets up hooks for adding settings and handling newsletter subscription updates.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function init(): void {
		add_action('wu_settings_login', [$this, 'add_settings'], 20);
		add_filter('wu_pre_save_settings', [$this, 'maybe_update_newsletter_subscription'], 10, 3);
	}

	/**
	 * Adds the newsletter subscription setting field.
	 *
	 * Registers a toggle setting that allows users to opt-in to the
	 * Multisite Ultimate newsletter for updates and information.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function add_settings(): void {
		wu_register_settings_field(
			'general',
			self::SETTING_FIELD_SLUG,
			[
				'title'   => __('Signup for Ultimate Multisite Newsletter', 'ultimate-multisite'),
				'desc'    => __('Be informed of new releases and all things related to running a WaaS Network.', 'ultimate-multisite'),
				'type'    => 'toggle',
				'default' => '1',
			],
			45
		);
	}


	/**
	 * Fix stripe settings
	 *
	 * @since 2.0.18
	 *
	 * @param array $settings The final settings array being saved, containing ALL options.
	 * @param array $settings_to_save Array containing just the options being updated.
	 * @param array $saved_settings Array containing the original settings.
	 * @return array
	 */
	public function maybe_update_newsletter_subscription($settings, $settings_to_save, $saved_settings) {

		$saved_optin = $saved_settings[ self::SETTING_FIELD_SLUG ] ?? '';

		if ( isset($settings_to_save[ self::SETTING_FIELD_SLUG ]) && $settings_to_save[ self::SETTING_FIELD_SLUG ] && $settings_to_save[ self::SETTING_FIELD_SLUG ] !== $saved_optin ) {
			$response = wp_remote_post(
				'https://ultimatemultisite.com/wp-json/newsletter/v2/subscribers',
				[
					'method'  => 'PUT',
					'body'    => wp_json_encode(
						[
							'email'      => $settings['company_email'],
							'status'     => 'confirmed',
							'first_name' => $settings['company_name'],
							'country'    => $settings['company_country'],
							'language'   => determine_locale(),
						]
					),
					'headers' => [
						'Accept'        => 'application/json',
						'Content-Type'  => 'application/json',
						'Authorization' => 'Basic ' . base64_encode('30220d7fb4ec49a7410b3a309b9346c18410bd56:0407cd731d6f074cd0b96f2643b7619e89af1ed2'), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
					],
				]
			);
		} elseif ( empty($settings_to_save[ self::SETTING_FIELD_SLUG ]) && ! empty($saved_optin) ) {
			$response = wp_remote_post(
				'https://ultimatemultisite.com/wp-json/newsletter/v2/subscribers',
				[
					'method'  => 'PUT',
					'body'    => wp_json_encode(
						[
							'email'  => $settings['company_email'],
							'status' => 'unsubscribed',
						]
					),
					'headers' => [
						'Accept'        => 'application/json',
						'Content-Type'  => 'application/json',
						'Authorization' => 'Basic ' . base64_encode('30220d7fb4ec49a7410b3a309b9346c18410bd56:0407cd731d6f074cd0b96f2643b7619e89af1ed2'), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
					],
				]
			);
		}

		return $settings;
	}
}
