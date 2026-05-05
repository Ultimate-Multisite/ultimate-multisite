<?php
/**
 * Ultimate Multisite Switch Template Admin Page.
 *
 * @package WP_Ultimo
 * @subpackage Admin_Pages
 * @since 2.0.0
 */

namespace WP_Ultimo\Admin_Pages\Customer_Panel;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Ultimate Multisite Switch Template Admin Page.
 */
class Template_Switching_Admin_Page extends \WP_Ultimo\Admin_Pages\Base_Customer_Facing_Admin_Page {

	/**
	 * Holds the ID for this page, this is also used as the page slug.
	 *
	 * @var string
	 */
	protected $id = 'wu-template-switching';

	/**
	 * Is this a top-level menu or a submenu?
	 *
	 * @since 1.8.2
	 * @var string
	 */
	protected $type = 'submenu';

	/**
	 * This page has no parent, so we need to highlight another sub-menu.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $highlight_menu_slug = 'account';

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
		'user_admin_menu' => 'wu_manage_membership',
		'admin_menu'      => 'wu_manage_membership',
	];

	/**
	 * Should we hide admin notices on this page?
	 *
	 * @since 2.0.0
	 * @var boolean
	 */
	protected $hide_admin_notices = true;

	/**
	 * Should we force the admin menu into a folded state?
	 *
	 * @since 2.0.0
	 * @var boolean
	 */
	protected $fold_menu = true;

	/**
	 * If this customer facing page has menu settings.
	 *
	 * @since 2.0.9
	 * @var boolean
	 */
	protected $menu_settings = false;

	/**
	 * Returns the title of the page.
	 *
	 * @since 2.0.0
	 * @return string Title of the page.
	 */
	public function get_title() {

		return __('Switch Template', 'ultimate-multisite');
	}

	/**
	 * Returns the title of menu for this page.
	 *
	 * @since 2.0.0
	 * @return string Menu label of the page.
	 */
	public function get_menu_title() {

		return __('Switch Template', 'ultimate-multisite');
	}

	/**
	 * Registers the necessary scripts.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function register_scripts(): void {

		do_action('wu_template_switching_admin_page_scripts', null, null);
	}

	/**
	 * Overrides the page loaded method.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function page_loaded(): void {

		do_action('wu_template_switching_admin_page', null);

		parent::page_loaded();
	}

	/**
	 * Displays the content of the activation section.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function output(): void {
		/*
		 * Pick the success label that matches the action just performed.
		 * The AJAX handler in Template_Switching_Element::switch_template()
		 * sets ?wu_template_action=reset when the customer re-applied
		 * their existing template and ?wu_template_action=switch when
		 * they moved to a different one. We use a namespaced query var
		 * (rather than the generic `?action`) because wp-admin/admin.php
		 * intercepts and rewrites generic `action=` requests as admin-
		 * action dispatches, which would drop our companion `updated=1`
		 * flag from the URL and silently break the notice.
		 *
		 * Falling back to the switch wording keeps the message correct
		 * for legacy callers that may redirect with only ?updated=1.
		 *
		 * phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display flag, no state change.
		 */
		$action  = isset($_GET['wu_template_action']) ? sanitize_key(wp_unslash($_GET['wu_template_action'])) : '';
		$message = 'reset' === $action
			? __('Template reset successfully!', 'ultimate-multisite')
			: __('Template switched successfully!', 'ultimate-multisite');

		/*
		 * Renders the base edit page layout, with the columns and everything else =)
		 */
		wu_get_template(
			'base/dash',
			[
				'screen'            => get_current_screen(),
				'page'              => $this,
				'has_full_position' => false,
				'content'           => '',
				'labels'            => [
					'updated_message' => $message,
				],
			]
		);
	}

	/**
	 * Allow child classes to register widgets, if they need them.
	 *
	 * @since 1.8.2
	 * @return void
	 */
	public function register_widgets(): void {
		add_action(
			'wu_dash_before_metaboxes',
			function () {
				$screen_id = get_current_screen()->id;

				ob_start();

				\WP_Ultimo\UI\Simple_Text_Element::get_instance()->as_inline_content($screen_id, 'wu_template_switching_content');
				\WP_Ultimo\UI\Template_Switching_Element::get_instance()->as_inline_content($screen_id, 'wu_template_switching_content');

				do_action('wu_template_switching_content');

				$content = ob_get_clean();

				if (trim($content)) {
					echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Buffered widget output is escaped by each element.
					return;
				}

				printf(
					'<div class="notice notice-info wu-m-0 wu-p-4"><p>%s</p></div>',
					esc_html__('Template switching is not available right now. Please contact your network administrator.', 'ultimate-multisite')
				);
			}
		);
	}
}
