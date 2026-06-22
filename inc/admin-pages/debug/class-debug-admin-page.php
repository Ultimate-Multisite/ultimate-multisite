<?php
/**
 * Ultimate Multisite Debug Admin Page.
 *
 * @package WP_Ultimo
 * @subpackage Admin_Pages\Debug
 * @since 2.0.0
 */

namespace WP_Ultimo\Admin_Pages\Debug;

// Exit if accessed directly
defined('ABSPATH') || exit;

use WP_Ultimo\Admin_Pages\Base_Admin_Page;
use WP_Ultimo\Debug\Debug;

/**
 * Ultimate Multisite Debug Admin Page.
 */
class Debug_Admin_Page extends Base_Admin_Page {

	/**
	 * Holds the ID for this page, this is also used as the page slug.
	 *
	 * @var string
	 */
	protected $id = 'wp-ultimo-debug-pages';

	/**
	 * Is this a top-level menu or a submenu?
	 *
	 * @since 1.8.2
	 * @var string
	 */
	protected $type = 'submenu';

	/**
	 * Is this a top-level menu or a submenu?
	 *
	 * @since 1.8.2
	 * @var string
	 */
	protected $parent = 'none';

	/**
	 * This page has no parent, so we need to highlight another sub-menu.
	 *
	 * @since 2.0.0
	 * @var string
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
		'network_admin_menu' => 'capability_here',
	];

	/**
	 * Allow child classes to register widgets, if they need them.
	 *
	 * @since 1.8.2
	 * @return void
	 */
	public function register_widgets(): void {

		add_meta_box(
			'wp-ultimo-debug-pages',
			__('All Registered Pages', 'ultimate-multisite'),
			[$this, 'render_debug_pages'],
			get_current_screen()->id,
			'normal',
			'default'
		);
	}

	/**
	 * Renders the list of Ultimate Multisite registered pages.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function render_debug_pages(): void {

		$pages = Debug::get_instance()->get_pages();

		echo '<ul class="wu-flex wu-flex-wrap wu--mx-1">';

		foreach ($pages as $page_id => $url) {
			printf(
				'
				<li class="wu-w-1/2 wu-box-border">
					<a class="wu-mx-1 wu-block wu-p-2 wu-box-border wu-border wu-border-gray-400 wu-border-solid wu-rounded" href="%s">%s</a>
				</li>
			',
				esc_attr($url),
				esc_html($page_id)
			);
		}

		echo '</ul>';
	}

	/**
	 * Returns the title of the page.
	 *
	 * @since 2.0.0
	 * @return string Title of the page.
	 */
	public function get_title() {

		return __('Registered Pages', 'ultimate-multisite');
	}

	/**
	 * Returns the title of menu for this page.
	 *
	 * @since 2.0.0
	 * @return string Menu label of the page.
	 */
	public function get_menu_title() {

		return __('Registered Pages', 'ultimate-multisite');
	}

	/**
	 * Allows admins to rename the sub-menu (first item) for a top-level page.
	 *
	 * @since 2.0.0
	 * @return string False to use the title menu or string with sub-menu title.
	 */
	public function get_submenu_title() {

		return __('Registered Pages', 'ultimate-multisite');
	}

	/**
	 * Every child class should implement the output method to display the contents of the page.
	 *
	 * @since 1.8.2
	 * @return void
	 */
	public function output(): void {

		wu_get_template(
			'base/dash',
			[
				'page'              => $this,
				'page_title'        => $this->get_title(),
				'screen'            => get_current_screen(),
				'has_full_position' => false,
			]
		);
	}
}
