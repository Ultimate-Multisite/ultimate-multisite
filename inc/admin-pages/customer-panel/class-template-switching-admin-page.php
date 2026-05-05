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
					'updated_message' => __('Template switched successfully!', 'ultimate-multisite'),
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
		\WP_Ultimo\UI\Simple_Text_Element::get_instance()->as_inline_content(get_current_screen()->id, 'wu_dash_before_metaboxes');
		\WP_Ultimo\UI\Template_Switching_Element::get_instance()->as_inline_content(get_current_screen()->id, 'wu_dash_before_metaboxes');

		/*
		 * Defence-in-depth: if both as_inline_content() calls bailed silently
		 * (e.g. via a third-party `wu_template_switching_should_display`
		 * filter, or because the page was reached on an unsupported screen),
		 * the page would render with three empty meta-box columns and no
		 * explanation. Wrap the action in a buffer so we can detect
		 * "nothing printed" and emit a fallback notice. Priority 5 starts
		 * the buffer; priority 999 closes it and emits either the captured
		 * output or a fallback message.
		 */
		add_action(
			'wu_dash_before_metaboxes',
			static function (): void {
				ob_start();
			},
			5
		);

		add_action(
			'wu_dash_before_metaboxes',
			static function (): void {
				$captured = ob_get_clean();

				if (false === $captured || '' === trim((string) $captured)) {
					printf(
						'<div class="wu-bg-yellow-100 wu-border wu-border-solid wu-border-yellow-300 wu-text-yellow-800 wu-p-4 wu-rounded">' .
							'<p class="wu-m-0 wu-font-semibold">%1$s</p>' .
							'<p class="wu-m-0 wu-mt-2 wu-text-sm">%2$s</p>' .
							'</div>',
						esc_html__('Template switching is not available right now.', 'ultimate-multisite'),
						esc_html__('No template switching widgets are available on this page. If you reached this page expecting to switch your site template, please contact your network administrator.', 'ultimate-multisite')
					);

					return;
				}

				echo $captured; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- previously-buffered widget HTML, already escaped at source.
			},
			999
		);
	}
}
