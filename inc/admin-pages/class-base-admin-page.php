<?php
/**
 * Base admin page class.
 *
 * Abstract class that makes it easy to create new admin pages.
 *
 * Most of Ultimate Multisite pages are implemented using this class, which means that the filters and hooks
 * listed below can be used to append content to all of our pages at once.
 *
 * @package WP_Ultimo
 * @subpackage Admin_Pages
 * @since 2.0.0
 */

namespace WP_Ultimo\Admin_Pages;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Abstract class that makes it easy to create new admin pages.
 */
abstract class Base_Admin_Page {

	/**
	 * SVG data URI for the Ultimate Multisite menu icon.
	 *
	 * Used instead of a custom dashicon class so the icon renders on all admin
	 * pages, not just pages where the full admin.css stylesheet is enqueued.
	 *
	 * @since 2.5.2
	 * @var string
	 */
	const MENU_ICON_SVG = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyMCAyMCI+PHBhdGggZmlsbD0iYmxhY2siIGQ9Ik0xOCAxM2gxYy41NSAwIDEgLjQ1IDEgMS4wMXYyLjk4YzAgLjU2LS40NSAxLjAxLTEgMS4wMWgtNGMtLjU1IDAtMS0uNDUtMS0xLjAxdi0yLjk4YzAtLjU2LjQ1LTEuMDEgMS0xLjAxaDF2LTJoLTV2MmgxYy41NSAwIDEgLjQ1IDEgMS4wMXYyLjk4YzAgLjU2LS40NSAxLjAxLTEgMS4wMUg4Yy0uNTUgMC0xLS40NS0xLTEuMDF2LTIuOThjMC0uNTYuNDUtMS4wMSAxLTEuMDFoMXYtMkg0djJoMWMuNTUgMCAxIC40NSAxIDEuMDF2Mi45OEM2IDE3LjU1IDUuNTUgMTggNSAxOEgxYy0uNTUgMC0xLS40NS0xLTEuMDF2LTIuOThDMCAxMy40NS40NSAxMyAxIDEzaDF2LTJjMC0xLjEuOS0yIDItMmg1VjdIOGMtLjU1IDAtMS0uNDUtMS0xLjAxVjMuMDFDNyAyLjQ1IDcuNDUgMiA4IDJoNGMuNTUgMCAxIC40NSAxIDEuMDF2Mi45OEMxMyA2LjU1IDEyLjU1IDcgMTIgN2gtMXYyaDVjMS4xIDAgMiAuOSAyIDJ2MnoiLz48L3N2Zz4=';

	/**
	 * In Edit mode
	 *
	 * @var bool
	 */
	protected $edit;

	/**
	 * Holds the ID for this page, this is also used as the page slug.
	 *
	 * @var string
	 */
	protected $id;

	/**
	 * Is this a top-level menu or a submenu?
	 *
	 * @since 1.8.2
	 * @var string
	 */
	protected $type = 'menu';

	/**
	 * If this is a submenu, we need a parent menu to attach this to
	 *
	 * @since 1.8.2
	 * @var string
	 */
	protected $parent = 'wp-ultimo';

	/**
	 * Holds the list of action links.
	 * These are the ones displayed next to the title of the page. e.g. Add New.
	 *
	 * @since 1.8.2
	 * @var array
	 */
	public $action_links = [];

	/**
	 * Holds the page title
	 *
	 * @since 1.8.2
	 * @var string
	 */
	protected $title;

	/**
	 * Holds the menu label of the page, this is what we effectively use on the menu item
	 *
	 * @since 1.8.2
	 * @var string
	 */
	protected $menu_title;

	/**
	 * After we create the menu item using WordPress functions, we need to store the generated hook.
	 *
	 * @since 1.8.2
	 * @var string
	 */
	protected $page_hook;

	/**
	 * Menu position. This is only used for top-level menus
	 *
	 * @since 1.8.2
	 * @var integer
	 */
	protected $position;

	/**
	 * Dashicon to be used on the menu item. This is only used on top-level menus
	 *
	 * @since 1.8.2
	 * @var string
	 */
	protected $menu_icon;

	/**
	 * If this number is greater than 0, a badge with the number will be displayed alongside the menu title
	 *
	 * @since 1.8.2
	 * @var integer
	 */
	protected $badge_count = 0;

	/**
	 * If this is a top-level menu, we can need the option to rewrite the sub-menu
	 *
	 * @since 1.8.2
	 * @var boolean|string
	 */
	protected $submenu_title = false;

	/**
	 * Allows us to highlight another menu page, if this page has no parent page at all.
	 *
	 * @since 2.0.0
	 * @var bool|string
	 */
	protected $highlight_menu_slug = false;

	/**
	 * Should we hide admin notices on this page?
	 *
	 * @since 2.0.0
	 * @var boolean
	 */
	protected $hide_admin_notices = false;

	/**
	 * Should we force the admin menu into a folded state?
	 *
	 * @since 2.0.0
	 * @var boolean
	 */
	protected $fold_menu = false;

	/**
	 * Should we remove the default WordPress frame?
	 *
	 * When set to true, this will remove the admin top-bar and the admin menu.
	 *
	 * @since 2.0.0
	 * @var boolean
	 */
	protected $remove_frame = false;

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
	 * Creates the page with the necessary hooks.
	 *
	 * @since 1.8.2
	 */
	public function __construct() {
		if ( ! doing_action('init') && ! did_action('init') ) {
			_doing_it_wrong(
				__FUNCTION__,
				sprintf(
					/* translators: 1: The current class. 2: 'init'. */
					esc_html__('Admin page %1$s loaded too early. Admin page should be loaded at the %2$s action or later.', 'ultimate-multisite'),
					'<code>' . static::class . '</code>',
					'<code>init</code>'
				),
				'2.4.6'
			);
			add_action('init', [$this, 'start_init']);
		} else {
			$this->start_init();
		}
	}

	/**
	 * Where the real init happens.
	 *
	 * @return void
	 */
	public function start_init() {
		/*
		 * Adds the page to all the necessary admin panels.
		 */
		foreach ($this->supported_panels as $panel => $capability) {
			add_action($panel, [$this, 'add_menu_page']);

			add_action($panel, [$this, 'fix_subdomain_name'], 100);
		}

		/*
		 * Delegates further initializations to the child class.
		 */
		$this->init();

		/*
		 * Add forms
		 */
		$this->register_forms();

		/**
		 * Allow plugin developers to run additional things when pages are registered.
		 *
		 * Unlike the wu_page_load, which only runs when a specific page
		 * is being seen, this hook runs at registration for every admin page
		 * being added using Ultimate Multisite code.
		 *
		 * @since 2.0.0
		 * @param string $page_id The ID of this page.
		 * @param string $page_hook The hook name of this page.
		 * @return void
		 */
		do_action('wu_page_added', $this->id, $this->page_hook);
	}
	/**
	 * Returns the ID of the admin page.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_id() {

		return $this->id;
	}

	/**
	 * Returns the appropriate capability for this page, depending on the context.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_capability() {

		if (is_user_admin()) {
			return $this->supported_panels['user_admin_menu'];
		} elseif (is_network_admin()) {
			return $this->supported_panels['network_admin_menu'];
		}

		return $this->supported_panels['admin_menu'];
	}

	/**
	 * Fix the subdomain name if an option (submenu title) is passed.
	 *
	 * @since 1.8.2
	 * @return void
	 */
	public function fix_subdomain_name(): void {

		global $submenu;

		if ($this->get_submenu_title() && 'menu' === $this->type && isset($submenu[ $this->id ]) && $this->get_title() === $submenu[ $this->id ][0][3]) {
			$submenu[ $this->id ][0][0] = $this->get_submenu_title();
		}
	}

	/**
	 * Fix the highlight Menu.
	 *
	 * @since 2.0.0
	 * @param string $file Fix the menu highlight for menus without parent.
	 * @return string
	 */
	public function fix_menu_highlight($file) {

		global $plugin_page;

		if ($this->highlight_menu_slug && isset($_GET['page']) && $this->get_id() === $_GET['page']) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$plugin_page = $this->highlight_menu_slug;

			$file = $this->highlight_menu_slug;
		}

		return $file;
	}

	/**
	 * Install the base hooks for developers
	 *
	 * @since 1.8.2
	 * @return void
	 */
	public function install_hooks(): void {

		/**
		 * Get the action links
		 */
		$this->action_links = $this->action_links();

		/**
		 * Allow plugin developers to add additional hooks to our pages.
		 *
		 * @since 1.8.2
		 * @since 2.0.4 Added third parameter: the page instance.
		 *
		 * @param string $id The ID of this page.
		 * @param string $page_hook The page hook of this page.
		 * @param self   $admin_page TThe page instance.
		 *
		 * @return void
		 */
		do_action('wu_page_load', $this->id, $this->page_hook, $this);

		/**
		 * Allow plugin developers to add additional hooks to our pages.
		 *
		 * @since 1.8.2
		 * @since 2.0.4 Added third parameter: the page instance.
		 *
		 * @param string $id The ID of this page.
		 * @param string $page_hook The page hook of this page.
		 * @param self   $admin_page TThe page instance.
		 *
		 * @return void
		 */
		do_action("wu_page_{$this->id}_load", $this->id, $this->page_hook, $this);

		/**
		 * Fixes menu highlights when necessary.
		 */
		add_filter('parent_file', [$this, 'fix_menu_highlight'], 99);

		add_filter('submenu_file', [$this, 'fix_menu_highlight'], 99);
	}

	/**
	 * Get the badge value, to append to the menu item title.
	 *
	 * @since 1.8.2
	 * @return string
	 */
	public function get_badge() {

		$markup = '&nbsp;<span class="update-plugins count-%s">
      <span class="update-count">%s</span>
    </span>';

		return $this->badge_count >= 1 ? sprintf($markup, $this->badge_count, $this->badge_count) : '';
	}

	/**
	 * Displays the page content.
	 *
	 * @since 1.8.2
	 * @return void
	 */
	final public function display(): void {

		/**
		 * 'Hack-y' solution for the customer facing title problem... but good enough for now.
		 *
		 * @todo review when possible.
		 */
		add_filter(
			'wp_ultimo_render_vars',
			function ($vars) {

				$vars['page_title'] = $this->get_title();

				return $vars;
			}
		);

		/**
		 * Allow plugin developers to add additional content before we print the page.
		 *
		 * @since 1.8.2
		 * @param string $page_id The id of this page.
		 * @param object $page The page object.
		 * @return void
		 */
		do_action('wu_page_before_render', $this->id, $this);

		/**
		 * Allow plugin developers to add additional content before we print the page.
		 *
		 * @since 1.8.2
		 * @param string $page_id The id of this page.
		 * @param object $page The page object.
		 * @return void
		 */
		do_action("wu_page_{$this->id}_before_render", $this->id, $this);

		/*
		 * Calls the output function.
		 */
		$this->output();

		/**
		 * Allow plugin developers to add additional content after we print the page
		 *
		 * @since 1.8.2
		 * @param string $page_id The id of this page
		 * @param object $page The page object.
		 * @return void
		 */
		do_action('wu_page_after_render', $this->id, $this);

		/**
		 * Allow plugin developers to add additional content after we print the page
		 *
		 * @since 1.8.2
		 * @param string $page_id The id of this page
		 * @param object $page The page object.
		 * @return void
		 */
		do_action("wu_page_{$this->id}_after_render", $this->id, $this);
	}

	/**
	 * Get the menu item, with the badge if necessary.
	 *
	 * @since 1.8.2
	 * @return string
	 */
	public function get_menu_label() {

		return $this->get_menu_title() . $this->get_badge();
	}

	/**
	 * Adds the menu items using default WordPress functions and handles the side-effects
	 *
	 * @since 1.8.2
	 * @return void
	 */
	public function add_menu_page(): void {

		/**
		 * Create the admin page or sub-page
		 */
		$this->page_hook = 'menu' === $this->type ? $this->add_toplevel_menu_page() : $this->add_submenu_page();

		/**
		 * Add the default hooks
		 */
		$this->enqueue_default_hooks();
	}

	/**
	 * Adds top-level admin page.
	 *
	 * @since 1.8.2
	 * @return string Page hook generated by WordPress.
	 */
	public function add_toplevel_menu_page() {

		if (wu_request('id')) {
			$this->edit = true;
		}

		return add_menu_page(
			$this->get_title(),
			$this->get_menu_label(),
			$this->get_capability(),
			$this->id,
			[$this, 'display'],
			$this->menu_icon,
			$this->position
		);
	}

	/**
	 * Adds sub-pages.
	 *
	 * @since 1.8.2
	 * @return string Page hook generated by WordPress.
	 */
	public function add_submenu_page() {

		if (wu_request('id')) {
			$this->edit = true;
		}

		return add_submenu_page(
			$this->parent,
			$this->get_title(),
			$this->get_menu_label(),
			$this->get_capability(),
			$this->id,
			[$this, 'display']
		);
	}

	/**
	 * Adds Ultimate Multisite branding to this page, if that's the case.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function add_branding(): void {

		if (apply_filters('wp_ultimo_remove_branding', false) === false) {
			add_action('in_admin_header', [$this, 'brand_header']);

			add_action('wu_header_right', [$this, 'add_container_toggle']);

			add_action('in_admin_footer', [$this, 'brand_footer']);

			add_filter('admin_footer_text', '__return_empty_string', 1000);

			add_filter('update_footer', '__return_empty_string', 1000);
		}
	}

	/**
	 * Adds the Jumper trigger to the admin top pages.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function add_container_toggle(): void {

		wu_get_template(
			'ui/container-toggle',
			[
				'page' => $this,
			]
		);
	}

	/**
	 * Adds the Ultimate Multisite branding header.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function brand_header(): void {

		wu_get_template(
			'ui/branding/header',
			[
				'page' => $this,
			]
		);
	}

	/**
	 * Adds the Ultimate Multisite branding footer.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function brand_footer(): void {

		wu_get_template(
			'ui/branding/footer',
			[
				'page' => $this,
			]
		);
	}

	/**
	 * Injects our admin classes to the admin body classes.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function add_admin_body_classes(): void {

		add_filter(
			'admin_body_class',
			function ($classes) {

				if ($this->hide_admin_notices) {
					$classes .= ' wu-hide-admin-notices';
				}

				if ($this->fold_menu) {
					$classes .= ' folded';
				}

				if ($this->remove_frame) {
					$classes .= ' wu-remove-frame folded';
				}

				if (is_network_admin()) {
					$classes .= ' wu-network-admin';
				}

				return "$classes wu-page-{$this->id} wu-styling hover:wu-styling first:wu-styling odd:wu-styling";
			}
		);
	}

	/**
	 * Register the default hooks.
	 *
	 * @todo: this does not need to run on every page.
	 *
	 * @since 1.8.2
	 * @return void
	 */
	final public function enqueue_default_hooks(): void {

		if ($this->page_hook) {
			add_action("load-$this->page_hook", [$this, 'install_hooks']);

			add_action("load-$this->page_hook", [$this, 'page_loaded']);

			add_action("load-$this->page_hook", [$this, 'hooks']);

			add_action("load-$this->page_hook", [$this, 'register_scripts'], 10);

			add_action("load-$this->page_hook", [$this, 'screen_options'], 10);

			add_action("load-$this->page_hook", [$this, 'register_widgets'], 20);

			add_action("load-$this->page_hook", [$this, 'fire_register_widgets_hook'], 21);

			add_action("load-$this->page_hook", [$this, 'add_admin_body_classes'], 20);

			/*
			 * Add the page to Ultimate Multisite branding (aka top-bar and footer)
			 */
			if (is_network_admin()) {
				add_action("load-$this->page_hook", [$this, 'add_branding']);
			}

			/**
			 * Allow plugin developers to add additional hooks
			 *
			 * @since 1.8.2
			 * @param string $page_hook The page hook.
			 */
			do_action('wu_enqueue_extra_hooks', $this->page_hook);
		}
	}

	/**
	 * Returns an array with the title links.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_title_links() {

		if (wu_get_documentation_url($this->get_id(), false)) {
			$this->action_links[] = [
				'url'   => wu_get_documentation_url($this->get_id()),
				'label' => __('Documentation', 'ultimate-multisite'),
				'icon'  => 'wu-open-book',
			];
		}

		/**
		 * Allow plugin developers, and ourselves, to add action links to our edit pages
		 *
		 * @since 1.8.2
		 * @param array $action_links The action links.
		 * @param Base_Admin_Page $page This instance.
		 * @return array
		 */
		return apply_filters('wu_page_get_title_links', $this->action_links, $this);
	}

	/**
	 * Allows child classes to register their own title links.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function action_links() {

		return [];
	}

	/**
	 * Allow child classes to add further initializations.
	 *
	 * @since 1.8.2
	 * @return void
	 */
	public function init() {}

	/**
	 * Allow child classes to add further initializations, but only after the page is loaded.
	 *
	 * @since 1.8.2
	 * @return void
	 */
	public function page_loaded() {}

	/**
	 * Allow child classes to add hooks to be run once the page is loaded.
	 *
	 * @see https://codex.wordpress.org/Plugin_API/Action_Reference/load-(page)
	 * @since 1.8.2
	 * @return void
	 */
	public function hooks() {}

	/**
	 * Allow child classes to add screen options; Useful for pages that have list tables.
	 *
	 * @since 1.8.2
	 * @return void
	 */
	public function screen_options() {}

	/**
	 * Allow child classes to register scripts and styles that can be loaded on the output function, for example.
	 *
	 * @since 1.8.2
	 * @return void
	 */
	public function register_scripts() {}

	/**
	 * Allow child classes to register widgets, if they need them.
	 *
	 * @since 1.8.2
	 * @return void
	 */
	public function register_widgets() {}

	/**
	 * Fire the register widgets hook for this page.
	 *
	 * This allows addons to add their own widgets to admin pages.
	 *
	 * @since 2.4.10
	 * @return void
	 */
	public function fire_register_widgets_hook(): void {

		/**
		 * Fires after widgets are registered for this page.
		 *
		 * The dynamic portion of the hook name, `$this->id`, refers to the page id.
		 *
		 * @since 2.4.10
		 *
		 * @param string $id        The page id.
		 * @param string $page_hook The page hook.
		 * @param object $page      The page object.
		 */
		do_action("wu_page_{$this->id}_register_widgets", $this->id, $this->page_hook, $this);
	}

	/**
	 * Allow child classes to register forms, if they need them.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function register_forms() {}

	/**
	 * Returns the title of the page. Must be declared on the child classes.
	 *
	 * @since 2.0.0
	 * @return string Title of the page.
	 */
	abstract public function get_title();

	/**
	 * Returns the title of menu for this page. Must be declared on the child classes.
	 *
	 * @since 2.0.0
	 * @return string Menu label of the page.
	 */
	abstract public function get_menu_title();

	/**
	 * Allows admins to rename the sub-menu (first item) for a top-level page.
	 *
	 * @since 2.0.0
	 * @return string False to use the title menu or string with sub-menu title.
	 */
	public function get_submenu_title() {

		return false;
	}

	/**
	 * Every child class should implement the output method to display the contents of the page.
	 *
	 * @since 1.8.2
	 * @return void
	 */
	abstract public function output();
}
