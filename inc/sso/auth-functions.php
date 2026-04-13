<?php
/**
 * SSO-compatible auth functions.
 *
 * For SSO to work completely, we need to make two changes to
 * WordPress's native auth functions.
 *
 * In auth_redirect, we disable the actual redirect while
 * we try to perform a sso redirect.
 *
 * In the case of wp_set_auth_cookie, we need to add
 * support to same-site None on cookies.
 *
 * @since 2.0.11
 * @package WP_Ultimo
 * @subpackage SSO
 */

use Delight\Cookie\Cookie;

defined('ABSPATH') || exit;


if ( ! function_exists('wp_set_auth_cookie') ) :
	/**
	 * Sets the authentication cookies based on user ID.
	 *
	 * The $remember parameter increases the time that the cookie will be kept. The
	 * default the cookie is kept without remembering is two days. When $remember is
	 * set, the cookies will be kept for 14 days or two weeks.
	 *
	 * @since 2.5.0
	 * @since 4.3.0 Added the `$token` parameter.
	 *
	 * @param int         $user_id  User ID.
	 * @param bool        $remember Whether to remember the user.
	 * @param bool|string $secure   Whether the auth cookie should only be sent over HTTPS. Default is an empty
	 *                              string which means the value of `is_ssl()` will be used.
	 * @param string      $token    Optional. User's session token to use for this cookie.
	 */
	function wp_set_auth_cookie($user_id, $remember = false, $secure = '', $token = '') {
		if ( $remember ) {
			/**
			 * Filters the duration of the authentication cookie expiration period.
			 *
			 * @since 2.8.0
			 *
			 * @param int  $length   Duration of the expiration period in seconds.
			 * @param int  $user_id  User ID.
			 * @param bool $remember Whether to remember the user login. Default false.
			 */
			$expiration = time() + apply_filters('auth_cookie_expiration', 14 * DAY_IN_SECONDS, $user_id, $remember); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

			/*
				* Ensure the browser will continue to send the cookie after the expiration time is reached.
				* Needed for the login grace period in wp_validate_auth_cookie().
				*/
			$expire = $expiration + (12 * HOUR_IN_SECONDS);
		} else {
			/** This filter is documented in wp-includes/pluggable.php */
			$expiration = time() + apply_filters('auth_cookie_expiration', 2 * DAY_IN_SECONDS, $user_id, $remember); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			$expire     = 0;
		}

		if ( '' === $secure ) {
			$secure = is_ssl();
		}

		// Front-end cookie is secure when the auth cookie is secure and the site's home URL uses HTTPS.
		$secure_logged_in_cookie = $secure && 'https' === wp_parse_url((string) get_option('home'), PHP_URL_SCHEME);

		/**
		 * Filters whether the auth cookie should only be sent over HTTPS.
		 *
		 * @since 3.1.0
		 *
		 * @param bool $secure  Whether the cookie should only be sent over HTTPS.
		 * @param int  $user_id User ID.
		 */
		$secure = apply_filters('secure_auth_cookie', $secure, $user_id); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		/**
		 * Filters whether the logged in cookie should only be sent over HTTPS.
		 *
		 * @since 3.1.0
		 *
		 * @param bool $secure_logged_in_cookie Whether the logged in cookie should only be sent over HTTPS.
		 * @param int  $user_id                 User ID.
		 * @param bool $secure                  Whether the auth cookie should only be sent over HTTPS.
		 */
		$secure_logged_in_cookie = apply_filters('secure_logged_in_cookie', $secure_logged_in_cookie, $user_id, $secure); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		if ( $secure ) {
			$auth_cookie_name = SECURE_AUTH_COOKIE;
			$scheme           = 'secure_auth';
		} else {
			$auth_cookie_name = AUTH_COOKIE;
			$scheme           = 'auth';
		}

		if ( '' === $token ) {
			$manager = WP_Session_Tokens::get_instance($user_id);
			$token   = $manager->create($expiration);
		}

		$auth_cookie      = wp_generate_auth_cookie($user_id, $expiration, $scheme, $token);
		$logged_in_cookie = wp_generate_auth_cookie($user_id, $expiration, 'logged_in', $token);

		/**
		 * Fires immediately before the authentication cookie is set.
		 *
		 * @since 2.5.0
		 * @since 4.9.0 The `$token` parameter was added.
		 *
		 * @param string $auth_cookie Authentication cookie value.
		 * @param int    $expire      The time the login grace period expires as a UNIX timestamp.
		 *                            Default is 12 hours past the cookie's expiration time.
		 * @param int    $expiration  The time when the authentication cookie expires as a UNIX timestamp.
		 *                            Default is 14 days from now.
		 * @param int    $user_id     User ID.
		 * @param string $scheme      Authentication scheme. Values include 'auth' or 'secure_auth'.
		 * @param string $token       User's session token to use for this cookie.
		 */
		do_action('set_auth_cookie', $auth_cookie, $expire, $expiration, $user_id, $scheme, $token); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		/**
		 * Fires immediately before the logged-in authentication cookie is set.
		 *
		 * @since 2.6.0
		 * @since 4.9.0 The `$token` parameter was added.
		 *
		 * @param string $logged_in_cookie The logged-in cookie value.
		 * @param int    $expire           The time the login grace period expires as a UNIX timestamp.
		 *                                 Default is 12 hours past the cookie's expiration time.
		 * @param int    $expiration       The time when the logged-in authentication cookie expires as a UNIX timestamp.
		 *                                 Default is 14 days from now.
		 * @param int    $user_id          User ID.
		 * @param string $scheme           Authentication scheme. Default 'logged_in'.
		 * @param string $token            User's session token to use for this cookie.
		 */
		do_action('set_logged_in_cookie', $logged_in_cookie, $expire, $expiration, $user_id, 'logged_in', $token); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		/**
		 * Allows preventing auth cookies from actually being sent to the client.
		 *
		 * @since 4.7.4
		 *
		 * @param bool $send Whether to send auth cookies to the client.
		 */
		if ( ! apply_filters('send_auth_cookies', true) ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			return;
		}

		Cookie::setcookie($auth_cookie_name, $auth_cookie, $expire, PLUGINS_COOKIE_PATH, COOKIE_DOMAIN, $secure, true, $secure ? 'None' : 'Lax');
		Cookie::setcookie($auth_cookie_name, $auth_cookie, $expire, ADMIN_COOKIE_PATH, COOKIE_DOMAIN, $secure, true, $secure ? 'None' : 'Lax');
		Cookie::setcookie(LOGGED_IN_COOKIE, $logged_in_cookie, $expire, COOKIEPATH, COOKIE_DOMAIN, $secure_logged_in_cookie, true, $secure_logged_in_cookie ? 'None' : 'Lax');
		if ( COOKIEPATH !== SITECOOKIEPATH ) {
			Cookie::setcookie(LOGGED_IN_COOKIE, $logged_in_cookie, $expire, SITECOOKIEPATH, COOKIE_DOMAIN, $secure_logged_in_cookie, true, $secure_logged_in_cookie ? 'None' : 'Lax');
		}
	}

endif;

/*
 * On subdirectory multisite the auth/secure_auth cookies are path-scoped to the
 * main site's /wp-admin, so they aren't sent when accessing /subsite/wp-admin/.
 * WordPress core's wp_validate_logged_in_cookie() intentionally skips the
 * logged_in cookie fallback when is_blog_admin() is true, leaving the current
 * user as 0 and triggering a 403.
 *
 * This late-priority filter falls back to the logged_in cookie (path /) so that
 * the user is correctly identified on any subsite's wp-admin.
 */
add_filter('determine_current_user', function ($user_id) {

	if ($user_id || ! is_multisite()) {
		return $user_id;
	}

	if ( ! defined('LOGGED_IN_COOKIE') || empty($_COOKIE[LOGGED_IN_COOKIE])) {
		return $user_id;
	}

	return wp_validate_auth_cookie($_COOKIE[LOGGED_IN_COOKIE], 'logged_in');
}, 30);

if ( ! function_exists('auth_redirect') ) :
	/**
	 * Checks if a user is logged in, if not it redirects them to the login page.
	 *
	 * When this code is called from a page, it checks to see if the user viewing the page is logged in.
	 * If the user is not logged in, they are redirected to the login page. The user is redirected
	 * in such a way that, upon logging in, they will be sent directly to the page they were originally
	 * trying to access.
	 *
	 * @since 1.5.0
	 */
	function auth_redirect() {

		if (apply_filters('wu_auth_redirect', null)) {
			return;
		}

		$secure = (is_ssl() || force_ssl_admin());

		/**
		 * Filters whether to use a secure authentication redirect.
		 *
		 * @since 3.1.0
		 *
		 * @param bool $secure Whether to use a secure authentication redirect. Default false.
		 */
		$secure = apply_filters('secure_auth_redirect', $secure); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		$request_uri = wp_unslash($_SERVER['REQUEST_URI'] ?? ''); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$host        = sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'] ?? ''));
		// If https is required and request is http, redirect.
		if ( $secure && ! is_ssl() && str_contains($request_uri, 'wp-admin') ) {
			if ( str_starts_with($request_uri, 'http') ) {
				wp_redirect(set_url_scheme($request_uri, 'https')); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
				exit;
			} else {
				wp_redirect('https://' . $host . $request_uri); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
				exit;
			}
		}

		/**
		 * Filters the authentication redirect scheme.
		 *
		 * @since 2.9.0
		 *
		 * @param string $scheme Authentication redirect scheme. Default empty.
		 */
		$scheme = apply_filters('auth_redirect_scheme', ''); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		$user_id = wp_validate_auth_cookie('', $scheme);

		/*
		 * Fallback: on subdirectory multisite the auth/secure_auth cookies are
		 * scoped to the main site's /wp-admin path and won't be sent when
		 * accessing a subsite's /subsite/wp-admin/.  The logged_in cookie
		 * (path /) IS present, so try that before forcing a login redirect.
		 */
		if ( ! $user_id && is_multisite()) {
			$user_id = wp_validate_auth_cookie('', 'logged_in');
		}

		if ( $user_id ) {
			/**
			 * Fires before the authentication redirect.
			 *
			 * @since 2.8.0
			 *
			 * @param int $user_id User ID.
			 */
			do_action('auth_redirect', $user_id); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

			// If the user wants ssl but the session is not ssl, redirect.
			if ( ! $secure && get_user_option('use_ssl', $user_id) && str_contains($request_uri, 'wp-admin') ) {
				if ( str_starts_with($request_uri, 'http') ) {
					wp_redirect(set_url_scheme($request_uri, 'https')); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
					exit;
				} else {
					wp_redirect('https://' . $host . $request_uri); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
					exit;
				}
			}

			return; // The cookie is good, so we're done.
		}

		// The cookie is no good, so force login.
		nocache_headers();

		$redirect = (strpos($request_uri, '/options.php') && wp_get_referer()) ? wp_get_referer() : set_url_scheme('http://' . $host . $request_uri);

		$login_url = wp_login_url($redirect, true);

		wp_redirect($login_url); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

endif;
