<?php
/**
 * Admin Bar Magic Links
 *
 * Modifies WordPress core My Sites admin bar menu to use magic links
 * for sites with custom domains.
 *
 * @package WP_Ultimo
 * @since 2.0.0
 */

namespace WP_Ultimo\SSO;

defined('ABSPATH') || exit;

/**
 * Adds magic link support to the core WordPress My Sites admin bar menu.
 *
 * @since 2.0.0
 */
class Admin_Bar_Magic_Links {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * Admin-post action used to lazily resolve a dashboard link.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	const ADMIN_POST_ACTION = 'wu_admin_bar_magic_link';

	/**
	 * Query argument containing the requested site ID.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	const SITE_ID_QUERY_ARG = 'wu_site_id';

	/**
	 * Initialize hooks.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function init(): void {

		// Hook late to modify the URLs after WordPress core adds them.
		add_action('admin_bar_menu', array($this, 'modify_my_sites_menu'), 999);

		// Resolve dashboard URLs only after the user selects a site.
		add_action('admin_post_' . self::ADMIN_POST_ACTION, array($this, 'handle_admin_bar_magic_link'));

		// Hook early into admin_page_access_denied to show magic links.
		add_action('admin_page_access_denied', array($this, 'show_access_denied_with_magic_links'), 5);
	}

	/**
	 * Modify the My Sites admin bar menu to use magic links.
	 *
	 * This function hooks into the admin bar after WordPress core has
	 * added all the My Sites menu items, and replaces dashboard URLs
	 * with same-origin lazy redirect URLs. Magic links are generated only
	 * after the user selects a dashboard link.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar The admin bar instance.
	 * @return void
	 */
	public function modify_my_sites_menu($wp_admin_bar): void {

		// Only process if user is logged in.
		if ( ! is_user_logged_in() ) {
			return;
		}

		// Process each dashboard node without resolving its destination.
		foreach ($wp_admin_bar->get_nodes() as $node) {
			if ( ! preg_match('/^blog-(\d+)-d$/', $node->id, $matches)) {
				continue;
			}

			$site_id = (int) $matches[1];

			// Keep the click on this authenticated origin until it is validated.
			$node->href = $this->get_admin_bar_action_url($site_id);

			$wp_admin_bar->add_node($node);
		}
	}

	/**
	 * Build the same-origin action URL for a site's dashboard link.
	 *
	 * @since 2.0.0
	 *
	 * @param int $site_id Site ID.
	 * @return string
	 */
	public function get_admin_bar_action_url($site_id) {

		$site_id = absint($site_id);

		return add_query_arg(
			array(
				'action'                => self::ADMIN_POST_ACTION,
				self::SITE_ID_QUERY_ARG => $site_id,
				'_wpnonce'              => wp_create_nonce($this->get_admin_bar_nonce_action($site_id)),
			),
			admin_url('admin-post.php')
		);
	}

	/**
	 * Resolve a selected dashboard link and redirect to its validated destination.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function handle_admin_bar_magic_link(): void {

		if ( ! is_user_logged_in() ) {
			wp_die(esc_html__('You do not have permission to access this site.', 'ultimate-multisite'), 403);
		}

		$site_id = $this->get_requested_site_id();
		$nonce   = $this->get_requested_nonce();

		if ( ! $site_id || ! $nonce || ! wp_verify_nonce($nonce, $this->get_admin_bar_nonce_action($site_id)) ) {
			wp_die(esc_html__('The requested site link is invalid.', 'ultimate-multisite'), 403);
		}

		$destination = $this->get_site_dashboard_url($site_id);

		if ( ! $destination ) {
			wp_die(esc_html__('You do not have permission to access this site.', 'ultimate-multisite'), 403);
		}

		$destination_host = wp_parse_url($destination, PHP_URL_HOST);

		if ( ! is_string($destination_host) || '' === $destination_host ) {
			wp_die(esc_html__('The requested site link is invalid.', 'ultimate-multisite'), 403);
		}

		$allow_destination_host = static function ($allowed_hosts, $host) use ($destination_host) {
			if (strtolower($destination_host) === strtolower($host)) {
				$allowed_hosts[] = $destination_host;
			}

			return $allowed_hosts;
		};

		add_filter('allowed_redirect_hosts', $allow_destination_host, 100, 2);
		$redirected = wp_safe_redirect($destination, 302, 'Ultimate-Multisite');
		remove_filter('allowed_redirect_hosts', $allow_destination_host, 100);

		if ( ! $redirected ) {
			wp_die(esc_html__('The requested site link is invalid.', 'ultimate-multisite'), 403);
		}

		exit;
	}

	/**
	 * Return the verified dashboard destination for a site.
	 *
	 * @since 2.0.0
	 *
	 * @param int $site_id Site ID.
	 * @return false|string
	 */
	public function get_site_dashboard_url($site_id) {

		$site_id = absint($site_id);
		$site    = get_site($site_id);

		if (
			! $site instanceof \WP_Site
			|| $site->deleted
			|| $site->spam
			|| $site->archived
			|| (! is_super_admin() && ! is_user_member_of_blog(get_current_user_id(), $site_id))
		) {
			return false;
		}

		return wu_get_admin_url($site_id);
	}

	/**
	 * Get the nonce action for a site's dashboard link.
	 *
	 * @since 2.0.0
	 *
	 * @param int $site_id Site ID.
	 * @return string
	 */
	protected function get_admin_bar_nonce_action($site_id) {

		return self::ADMIN_POST_ACTION . '_' . absint($site_id);
	}

	/**
	 * Get a validated site ID from the request.
	 *
	 * @since 2.0.0
	 * @return false|int
	 */
	protected function get_requested_site_id() {

		$site_id = wu_request(self::SITE_ID_QUERY_ARG);

		if ( ! is_string($site_id) || ! ctype_digit($site_id) || ! absint($site_id) ) {
			return false;
		}

		return absint($site_id);
	}

	/**
	 * Get a nonce string from the request.
	 *
	 * @since 2.0.0
	 * @return false|string
	 */
	protected function get_requested_nonce() {

		$nonce = wu_request('_wpnonce');

		return is_string($nonce) ? $nonce : false;
	}

	/**
	 * Show access denied splash screen with magic links.
	 *
	 * This replaces the WordPress core access denied splash screen
	 * with our own version that uses magic links for sites with custom domains.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function show_access_denied_with_magic_links(): void {

		// Only run in multisite and if user is logged in.
		if ( ! is_multisite() || ! is_user_logged_in() || is_network_admin() ) {
			return;
		}

		$blogs = get_blogs_of_user(get_current_user_id());

		// If user has blogs and current blog is not in their list, show our custom message.
		if ( wp_list_filter($blogs, array('userblog_id' => get_current_blog_id())) ) {
			return;
		}

		$blog_name = get_bloginfo('name');

		if ( empty($blogs) ) {
			wp_die(
				sprintf(
					/* translators: 1: Site title. */
					__('You attempted to access the "%1$s" dashboard, but you do not currently have privileges on this site. If you believe you should be able to access the "%1$s" dashboard, please contact your network administrator.', 'ultimate-multisite'), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					$blog_name // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				),
				403
			);
		}

		if ( 1 === count($blogs) ) {
			wp_safe_redirect(get_admin_url(current($blogs)->userblog_id));
			exit;
		}

		$output = '<p>' . sprintf(
			/* translators: 1: Site title. */
			__('You attempted to access the "%1$s" dashboard, but you do not currently have privileges on this site. If you believe you should be able to access the "%1$s" dashboard, please contact your network administrator.', 'ultimate-multisite'),
			$blog_name
		) . '</p>';
		$output .= '<p>' . __('If you reached this screen by accident and meant to visit one of your own sites, here are some shortcuts to help you find your way.', 'ultimate-multisite') . '</p>';

		$output .= '<h3>' . __('Your Sites', 'ultimate-multisite') . '</h3>';
		$output .= '<table>';

		foreach ( $blogs as $blog ) {
			$site_id = (int) $blog->userblog_id;

			// Get dashboard URL (with magic link if needed).
			$dashboard_url = wu_get_admin_url($site_id);

			// Get home URL (with magic link if needed).
			$home_url = wu_get_home_url($site_id);

			$output .= '<tr>';
			$output .= '<td>' . esc_html($blog->blogname) . '</td>';
			$output .= '<td><a href="' . esc_url($dashboard_url) . '">' . __('Visit Dashboard', 'ultimate-multisite') . '</a> | ' .
				'<a href="' . esc_url($home_url) . '">' . __('View Site', 'ultimate-multisite') . '</a></td>';
			$output .= '</tr>';
		}

		$output .= '</table>';

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is escaped above with esc_url() and esc_html().
		wp_die($output, 403);
	}
}
