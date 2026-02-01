<?php
/**
 * CPanel Integration.
 *
 * Shared cPanel integration providing API access for domain mapping
 * and other capabilities.
 *
 * @package WP_Ultimo
 * @subpackage Integrations/Providers
 * @since 2.5.0
 */

namespace WP_Ultimo\Integrations\Providers\CPanel;

use WP_Ultimo\Integrations\Integration;
use WP_Ultimo\Integrations\Host_Providers\CPanel_API\CPanel_API;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * CPanel integration provider.
 *
 * @since 2.5.0
 */
class CPanel_Integration extends Integration {

	/**
	 * Holds the API object.
	 *
	 * @since 2.5.0
	 * @var CPanel_API|null
	 */
	protected ?CPanel_API $api = null;

	/**
	 * Constructor.
	 *
	 * @since 2.5.0
	 */
	public function __construct() {

		parent::__construct('cpanel', 'cPanel');

		$this->set_description(__('cPanel is the management panel being used on a large number of shared and dedicated hosts across the globe.', 'ultimate-multisite'));
		$this->set_logo(function_exists('wu_get_asset') ? wu_get_asset('cpanel.svg', 'img/hosts') : '');
		$this->set_tutorial_link('https://ultimatemultisite.com/docs/user-guide/host-integrations/cpanel');
		$this->set_constants(['WU_CPANEL_USERNAME', 'WU_CPANEL_PASSWORD', 'WU_CPANEL_HOST']);
		$this->set_optional_constants(['WU_CPANEL_PORT', 'WU_CPANEL_ROOT_DIR']);
		$this->set_supports(['autossl', 'no-instructions']);
	}

	/**
	 * {@inheritdoc}
	 */
	public function detect(): bool {

		return false;
	}

	/**
	 * Tests the connection with the cPanel API.
	 *
	 * @since 2.5.0
	 * @return true|\WP_Error
	 */
	public function test_connection() {

		$results = $this->load_api()->api2('Cron', 'fetchcron', []);

		if (isset($results->cpanelresult->data) && ! isset($results->cpanelresult->error)) {
			return true;
		}

		return new \WP_Error('cpanel-error', __('Could not connect to cPanel.', 'ultimate-multisite'));
	}

	/**
	 * Returns the list of installation fields.
	 *
	 * @since 2.5.0
	 * @return array
	 */
	public function get_fields(): array {

		return [
			'WU_CPANEL_USERNAME' => [
				'title'       => __('cPanel Username', 'ultimate-multisite'),
				'placeholder' => __('e.g. username', 'ultimate-multisite'),
			],
			'WU_CPANEL_PASSWORD' => [
				'type'        => 'password',
				'title'       => __('cPanel Password', 'ultimate-multisite'),
				'placeholder' => __('password', 'ultimate-multisite'),
			],
			'WU_CPANEL_HOST'     => [
				'title'       => __('cPanel Host', 'ultimate-multisite'),
				'placeholder' => __('e.g. yourdomain.com', 'ultimate-multisite'),
			],
			'WU_CPANEL_PORT'     => [
				'title'       => __('cPanel Port', 'ultimate-multisite'),
				'placeholder' => __('Defaults to 2083', 'ultimate-multisite'),
				'value'       => 2083,
			],
			'WU_CPANEL_ROOT_DIR' => [
				'title'       => __('Root Directory', 'ultimate-multisite'),
				'placeholder' => __('Defaults to /public_html', 'ultimate-multisite'),
				'value'       => '/public_html',
			],
		];
	}

	/**
	 * Load the cPanel API.
	 *
	 * @since 2.5.0
	 * @return CPanel_API
	 */
	public function load_api(): CPanel_API {

		if (null === $this->api) {
			$username = $this->get_credential('WU_CPANEL_USERNAME');
			$password = $this->get_credential('WU_CPANEL_PASSWORD');
			$host     = $this->get_credential('WU_CPANEL_HOST');
			$port     = $this->get_credential('WU_CPANEL_PORT') ?: 2083;

			$this->api = new CPanel_API($username, $password, preg_replace('#^https?://#', '', (string) $host), $port);
		}

		return $this->api;
	}
}
