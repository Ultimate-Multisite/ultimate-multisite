<?php
/**
 * WPMU DEV Integration.
 *
 * Shared WPMU DEV integration providing API access for domain mapping.
 *
 * @package WP_Ultimo
 * @subpackage Integrations/Providers
 * @since 2.5.0
 */

namespace WP_Ultimo\Integrations\Providers\WPMUDEV;

use WP_Ultimo\Integrations\Integration;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * WPMU DEV integration provider.
 *
 * @since 2.5.0
 */
class WPMUDEV_Integration extends Integration {

	/**
	 * Constructor.
	 *
	 * @since 2.5.0
	 */
	public function __construct() {

		parent::__construct('wpmudev', 'WPMU DEV Hosting');

		$this->set_description(__('WPMU DEV is one of the largest companies in the WordPress space. Founded in 2004, it was one of the first companies to scale the Website as a Service model with products such as Edublogs and CampusPress.', 'ultimate-multisite'));
		$this->set_logo(function_exists('wu_get_asset') ? wu_get_asset('wpmudev.webp', 'img/hosts') : '');
		$this->set_tutorial_link('https://ultimatemultisite.com/docs/user-guide/host-integrations/wpmu-dev');
		$this->set_constants(['WPMUDEV_HOSTING_SITE_ID']);
		$this->set_supports(['autossl', 'no-instructions', 'no-config']);
	}

	/**
	 * {@inheritdoc}
	 */
	public function detect(): bool {

		return defined('WPMUDEV_HOSTING_SITE_ID') && WPMUDEV_HOSTING_SITE_ID;
	}

	/**
	 * {@inheritdoc}
	 */
	public function test_connection() {

		$site_id = $this->get_credential('WPMUDEV_HOSTING_SITE_ID');

		$api_key = get_site_option('wpmudev_apikey');

		$response = wp_remote_get(
			"https://premium.wpmudev.org/api/hosting/v1/{$site_id}/domains",
			[
				'timeout' => 50,
				'headers' => [
					'Authorization' => $api_key,
				],
			]
		);

		if (is_wp_error($response)) {
			return $response;
		}

		if (wp_remote_retrieve_response_code($response) === 200) {
			return true;
		}

		return new \WP_Error(
			'wpmudev-connection-failed',
			sprintf(
				/* translators: %1$d: HTTP response code, %2$s: response body. */
				__('Connection failed with HTTP code %1$d: %2$s', 'ultimate-multisite'),
				wp_remote_retrieve_response_code($response),
				wp_remote_retrieve_body($response)
			)
		);
	}
}
