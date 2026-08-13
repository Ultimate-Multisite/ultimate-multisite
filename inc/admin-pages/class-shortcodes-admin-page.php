<?php
/**
 * Ultimate Multisite Shortcodes Admin Page.
 *
 * @package WP_Ultimo
 * @subpackage Admin_Pages
 * @since 2.0.24
 */

namespace WP_Ultimo\Admin_Pages;

use WP_Ultimo\UI\Base_Element;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Ultimate Multisite Shortcodes Admin Page.
 */
class Shortcodes_Admin_Page extends Base_Admin_Page {

	/**
	 * Holds the ID for this page, this is also used as the page slug.
	 *
	 * @var string
	 */
	protected $id = 'wp-ultimo-shortcodes';

	/**
	 * Is this a top-level menu or a submenu?
	 *
	 * @since 1.8.2
	 * @var string
	 */
	protected $type = 'submenu';

	/**
	 * If this is a submenu, we need a parent menu to attach this to
	 *
	 * @since 1.8.2
	 * @var string
	 */
	protected $parent = 'none';

	/**
	 * Allows us to highlight another menu page, if this page has no parent page at all.
	 *
	 * @since 2.0.0
	 * @var boolean
	 */
	protected $highlight_menu_slug = 'wp-ultimo-settings';

	/**
	 * If this number is greater than 0, a badge with the number will be displayed alongside the menu title
	 *
	 * @since 1.8.2
	 * @var integer
	 */
	protected $badge_count = 0;

	/**
	 * Holds the admin panels where this page should be displayed, as well as which capability to require.
	 *
	 * To add a page to the regular admin (wp-admin/), use: 'admin_menu' => 'capability_here'
	 * To add a page to the network admin (wp-admin/network), use: 'network_admin_menu' => 'capability_here'
	 * To add a page to the user (wp-admin/user) admin, use: 'user_admin_menu' => 'capability_here'
	 *
	 * @since 2.0.0
	 * @var array
	 */
	protected $supported_panels = [
		'network_admin_menu' => 'manage_network',
	];

	/**
	 * Returns the title of the page.
	 *
	 * @since 2.0.0
	 * @return string Title of the page.
	 */
	public function get_title() {

		return __('Available Shortcodes', 'ultimate-multisite');
	}

	/**
	 * Returns the title of menu for this page.
	 *
	 * @since 2.0.0
	 * @return string Menu label of the page.
	 */
	public function get_menu_title() {

		return __('Available Shortcodes', 'ultimate-multisite');
	}

	/**
	 * Allows admins to rename the sub-menu (first item) for a top-level page.
	 *
	 * @since 2.0.0
	 * @return string False to use the title menu or string with sub-menu title.
	 */
	public function get_submenu_title() {

		return __('Dashboard', 'ultimate-multisite');
	}

	/**
	 * Every child class should implement the output method to display the contents of the page.
	 *
	 * @since 1.8.2
	 * @return void
	 */
	public function output(): void {

		$screen = get_current_screen();

		wu_get_template(
			'shortcodes/shortcodes',
			[
				'screen' => $screen,
				'data'   => $this->get_data(),
			]
		);
	}

	/**
	 * Get data for shortcodes
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_data() {

		$elements = Base_Element::get_public_elements();

		$data = [];

		foreach ($elements as $element) {
			$defaults = $element->defaults();

			$params = array_filter($element->fields(), fn($el) => 'note' !== $el['type'] && 'header' !== $el['type']);

			foreach ($params as $key => $value) {
				$params[ $key ]['default'] = wu_get_isset($defaults, $key, '');

				$params[ $key ]['desc'] = ! isset($value['desc']) ? '' : $params[ $key ]['desc'];

				switch ($value['type']) {
					case 'toggle':
						$params[ $key ]['options'] = '0 | 1';
						break;
					case 'select':
						$options = wu_get_isset($value, 'options', []);

						if (is_callable($options)) {
							$options = call_user_func($options);
						}

						$params[ $key ]['options'] = implode(' | ', array_keys($options));
						break;
					case 'int':
						$params[ $key ]['options'] = __('integer', 'ultimate-multisite');
						break;
					case 'number':
						$params[ $key ]['options'] = __('number', 'ultimate-multisite');
						break;
					case 'text':
						$params[ $key ]['options'] = __('text', 'ultimate-multisite');
						break;
					case 'textarea':
						$params[ $key ]['options'] = __('text', 'ultimate-multisite');
						break;
					default:
						$params[ $key ]['options'] = $value['type'];
						break;
				}
			}

			$id = $element->get_id();

			if (str_starts_with((string) $id, 'wp-ultimo/')) {
				$id = substr((string) $element->get_id(), strlen('wp-ultimo/'));
			}

			$data[] = [
				'generator_form_url' => wu_get_form_url("shortcode_{$id}"),
				'title'              => $element->get_title(),
				'shortcode'          => $element->get_shortcode_id(),
				'description'        => $element->get_description(),
				'params'             => $params,
			];
		}

		return $data;
	}
}
