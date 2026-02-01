<?php
/**
 * WP Engine Integration.
 *
 * Shared WP Engine integration providing API access for domain mapping.
 *
 * @package WP_Ultimo
 * @subpackage Integrations/Providers
 * @since 2.5.0
 */

namespace WP_Ultimo\Integrations\Providers\WPEngine;

use WP_Ultimo\Integrations\Integration;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * WP Engine integration provider.
 *
 * @since 2.5.0
 */
class WPEngine_Integration extends Integration {

	/**
	 * Constructor.
	 *
	 * @since 2.5.0
	 */
	public function __construct() {

		parent::__construct('wpengine', 'WP Engine');

		$description = __('WP Engine drives your business forward faster with the first and only WordPress Digital Experience Platform. We offer the best WordPress hosting and developer experience on a proven, reliable architecture that delivers unparalleled speed, scalability, and security for your sites.', 'ultimate-multisite');

		$description .= '<br><br><b>' . __('We recommend to enter in contact with WP Engine support to ask for a Wildcard domain if you are using a subdomain install.', 'ultimate-multisite') . '</b>';

		$this->set_description($description);
		$this->set_logo(function_exists('wu_get_asset') ? wu_get_asset('wpengine.svg', 'img/hosts') : '');
		$this->set_tutorial_link('https://ultimatemultisite.com/docs/user-guide/host-integrations/wp-engine');
		$this->set_constants([['WPE_API', 'WPE_APIKEY']]);
		$this->set_supports(['no-instructions', 'no-config']);
	}

	/**
	 * {@inheritdoc}
	 */
	public function detect(): bool {

		return (defined('WPE_APIKEY') && WPE_APIKEY) || (defined('WPE_API') && WPE_API);
	}

	/**
	 * {@inheritdoc}
	 */
	public function test_connection() {

		$this->load_dependencies();

		if (! class_exists('WPE_API')) {
			return new \WP_Error('wpe-missing', __('Class WPE_API is not installed.', 'ultimate-multisite'));
		}

		$api = new \WPE_API();

		$api->set_arg('method', 'site');

		$results = $api->get();

		if (is_wp_error($results)) {
			return $results;
		}

		return true;
	}

	/**
	 * Loads WP Engine dependencies.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function load_dependencies(): void {

		if (! defined('WPE_PLUGIN_DIR') || ! is_readable(WPE_PLUGIN_DIR . '/class-wpeapi.php')) {
			return;
		}

		include_once WPE_PLUGIN_DIR . '/class-wpeapi.php';
	}
}
