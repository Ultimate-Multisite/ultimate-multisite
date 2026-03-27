<?php
/**
 * Ultimate Multisite helper class to handle global registering of scripts and styles.
 *
 * @package WP_Ultimo
 * @subpackage Scripts
 * @since 2.0.0
 */

namespace WP_Ultimo;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Ultimate Multisite helper class to handle global registering of scripts and styles.
 *
 * @since 2.0.0
 */
class Scripts {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * Runs when the instantiation first occurs.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function init(): void {

		add_action('init', [$this, 'register_default_scripts']);

		add_action('init', [$this, 'register_default_styles']);

		add_action('admin_enqueue_scripts', [$this, 'enqueue_default_admin_styles']);

		add_action('admin_enqueue_scripts', [$this, 'enqueue_default_admin_scripts']);

		add_action('wp_ajax_wu_toggle_container', [$this, 'update_use_container']);

		add_filter('admin_body_class', [$this, 'add_body_class_container_boxed']);
	}

	/**
	 * Wrapper for the register scripts function.
	 *
	 * @since 2.0.0
	 *
	 * @param string     $handle The script handle. Used to enqueue the script.
	 * @param string     $src URL to the file.
	 * @param array      $deps List of dependency scripts.
	 * @param array|bool $args     {
	 *     Optional. An array of additional script loading strategies. Default empty array.
	 *     Otherwise, it may be a boolean in which case it determines whether the script is printed in the footer. Default false.
	 *
	 *     @type string    $strategy     Optional. If provided, may be either 'defer' or 'async'.
	 *     @type bool      $in_footer    Optional. Whether to print the script in the footer. Default 'false'.
	 * }
	 * @return void
	 */
	public function register_script($handle, $src, $deps = [], $args = [
		'in_footer' => true,
	]): void {

		wp_register_script($handle, $src, $deps, \WP_Ultimo::VERSION, $args);
	}

	/**
	 * Wrapper for the register scripts module function.
	 *
	 * @since 2.4.1
	 *
	 * @param string $id The script handle. Used to enqueue the script.
	 * @param string $src URL to the file.
	 * @param array  $deps List of dependency scripts.
	 * @return void
	 */
	public function register_script_module($id, $src, $deps = []): void {
		// This method was added in WP 6.5. We're only using modules as a progressive enhancement so we don't need to add a workaround.
		if (function_exists('wp_register_script_module')) {
			wp_register_script_module($id, $src, $deps, \WP_Ultimo::VERSION);
		}
	}

	/**
	 * Wrapper for the register styles function.
	 *
	 * @since 2.0.0
	 *
	 * @param string $handle The script handle. Used to enqueue the script.
	 * @param string $src URL to the file.
	 * @param array  $deps List of dependency scripts.
	 * @return void
	 */
	public function register_style($handle, $src, $deps = []): void {

		wp_register_style($handle, $src, $deps, \WP_Ultimo::VERSION);
	}

	/**
	 * Registers the default Ultimate Multisite scripts.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function register_default_scripts(): void {
		/*
		 * Adds Vue JS
		 */
		$this->register_script('wu-vue', wu_get_asset('lib/vue.js', 'js'));

		/*
		 * Adds Sweet Alert
		 */
		$this->register_script('wu-sweet-alert', wu_get_asset('lib/sweetalert2.all.js', 'js'));

		/*
		 * Adds Flat Picker
		 */
		$this->register_script('wu-flatpicker', wu_get_asset('lib/flatpicker.js', 'js'));

		/*
		 * Adds tipTip
		 */
		$this->register_script('wu-tiptip', wu_get_asset('lib/tiptip.js', 'js'), ['jquery-core']);

		/*
		 * Ajax list Table pagination
		 */
		$this->register_script('wu-ajax-list-table', wu_get_asset('list-tables.js', 'js'), ['jquery', 'wu-vue', 'underscore', 'wu-flatpicker']);

		/*
		 * Adds jQueryBlockUI
		 */
		$this->register_script('wu-block-ui', wu_get_asset('lib/jquery.blockUI.js', 'js'), ['jquery-core']);

		/*
		 * Adds FontIconPicker
		 */
		$this->register_script('wu-fonticonpicker', wu_get_asset('lib/jquery.fonticonpicker.js', 'js'), ['jquery']);

		/*
		 * Adds Accounting.js
		 */
		$this->register_script('wu-accounting', wu_get_asset('lib/accounting.js', 'js'), ['jquery-core']);

		/*
		 * Adds Cookie Helpers
		 */
		$this->register_script('wu-cookie-helpers', wu_get_asset('cookie-helpers.js', 'js'), ['jquery-core']);

		/*
		 * Adds Password Toggle
		 */
		$this->register_script('wu-password-toggle', wu_get_asset('wu-password-toggle.js', 'js'), ['wp-i18n']);

		/*
		 * Adds Password Strength Checker
		 */
		$this->register_script('wu-password-strength', wu_get_asset('wu-password-strength.js', 'js'), ['jquery', 'password-strength-meter']);

		wp_localize_script(
			'wu-password-strength',
			'wu_password_strength_settings',
			array_merge(
				$this->get_password_requirements(),
				[
					'i18n' => [
						'empty'            => __('Strength indicator', 'ultimate-multisite'),
						'super_strong'     => __('Super Strong', 'ultimate-multisite'),
						'required'         => __('Required:', 'ultimate-multisite'),
						/* translators: %d is the minimum number of characters required */
						'min_length'       => __('at least %d characters', 'ultimate-multisite'),
						'uppercase_letter' => __('uppercase letter', 'ultimate-multisite'),
						'lowercase_letter' => __('lowercase letter', 'ultimate-multisite'),
						'number'           => __('number', 'ultimate-multisite'),
						'special_char'     => __('special character', 'ultimate-multisite'),
					],
				]
			)
		);

		/*
		 * Adds Input Masking
		 */
		$this->register_script('wu-money-mask', wu_get_asset('lib/v-money.js', 'js'), ['wu-vue']);
		$this->register_script('wu-input-mask', wu_get_asset('lib/vue-the-mask.js', 'js'), ['wu-vue']);

		/*
		 * Adds General Functions
		 */
		$this->register_script('wu-functions', wu_get_asset('functions.js', 'js'), ['jquery-core', 'wu-tiptip', 'wu-flatpicker', 'wu-block-ui', 'wu-accounting', 'clipboard', 'wp-hooks']);

		wp_localize_script(
			'wu-functions',
			'wu_settings',
			[
				'currency'           => wu_get_setting('currency_symbol', 'USD'),
				'currency_symbol'    => wu_get_currency_symbol(),
				'currency_position'  => wu_get_setting('currency_position', '%s %v'),
				'decimal_separator'  => wu_get_setting('decimal_separator', '.'),
				'thousand_separator' => wu_get_setting('thousand_separator', ','),
				'precision'          => wu_currency_decimal_filter(wu_get_setting('precision', 2)),
				'use_container'      => get_user_setting('wu_use_container', false),
				'disable_image_zoom' => wu_get_setting('disable_image_zoom', false),
			]
		);

		/*
		 * Localize AJAX error strings used by window.wu_ajax_error().
		 */
		wp_localize_script(
			'wu-functions',
			'wu_ajax_errors',
			[
				'error_title'   => __('Request Failed', 'ultimate-multisite'),
				'error_message' => __('An unexpected error occurred. Please try again or contact support if the problem persists.', 'ultimate-multisite'),
				'error_403'     => __('You do not have permission to perform this action.', 'ultimate-multisite'),
				'error_404'     => __('The requested resource was not found.', 'ultimate-multisite'),
				'error_network' => __('A network error occurred. Please check your connection and try again.', 'ultimate-multisite'),
				'while_prefix'  => __('while', 'ultimate-multisite'),
			]
		);

		/*
		 * Adds Fields & Components
		 */
		$this->register_script(
			'wu-fields',
			wu_get_asset('fields.js', 'js'),
			['jquery', 'wu-vue', 'wu-selectizer', 'wp-color-picker']
		);

		/*
		 * Localize components
		 */
		wp_localize_script(
			'wu-fields',
			'wu_fields',
			[
				'l10n' => [
					'image_picker_title'       => __('Select an Image.', 'ultimate-multisite'),
					'image_picker_button_text' => __('Use this image', 'ultimate-multisite'),
				],
			]
		);

		/*
		 * Adds Admin Script
		 */
		$this->register_script('wu-admin', wu_get_asset('admin.js', 'js'), ['jquery', 'wu-functions']);

		/*
		 * Adds Vue Apps
		 */
		$this->register_script('wu-vue-apps', wu_get_asset('vue-apps.js', 'js'), ['wu-functions', 'wu-vue', 'wu-money-mask', 'wu-input-mask', 'wp-hooks']);
		$this->register_script('wu-vue-sortable', wu_get_asset('lib/sortablejs.js', 'js'), []);
		$this->register_script('wu-vue-draggable', wu_get_asset('lib/vue-draggable.js', 'js'), ['wu-vue-sortable']);

		/*
		 * Adds Selectizer
		 */
		$this->register_script('wu-selectize', wu_get_asset('lib/selectize.js', 'js'), ['jquery']);
		$this->register_script('wu-selectizer', wu_get_asset('selectizer.js', 'js'), ['wu-selectize', 'underscore', 'wu-vue-apps']);

		/*
		 * Localize selectizer
		 */
		wp_localize_script(
			'wu-functions',
			'wu_selectizer',
			[
				'ajaxurl' => wu_ajax_url('init'),
			]
		);

		/*
		 * Load variables to localized it
		 */
		wp_localize_script(
			'wu-functions',
			'wu_ticker',
			[
			'server_clock_offset'          => (wu_get_current_time('timestamp') - time()) / 60 / 60, // phpcs:ignore
			'moment_clock_timezone_name'   => wp_date('e'),
			'moment_clock_timezone_offset' => wp_date('Z'),
			]
		);

		/*
		 * Adds our thickbox fork.
		 */
		$this->register_script('wubox', wu_get_asset('wubox.js', 'js'), ['wu-vue-apps']);

		/*
		 * Add inline script to handle early clicks on wubox elements
		 * before the main wubox.js is fully loaded.
		 */
		wp_add_inline_script(
			'wubox',
			"(function(){
				window.__wuboxEarlyClicks=[];
				window.__wuboxEarlyClickHandler=function(e){
					if(window.__wuboxReady)return;
					var t=e.target.closest('.wubox');
					if(!t)return;
					e.preventDefault();
					e.stopPropagation();
					t.style.cursor='wait';
					window.__wuboxEarlyClicks.push(t);
				};
				document.addEventListener('click',window.__wuboxEarlyClickHandler,true);
			})();",
			'before'
		);

		wp_localize_script(
			'wubox',
			'wuboxL10n',
			[
				'next'             => __('Next &gt;'), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
				'prev'             => __('&lt; Prev'), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
				'image'            => __('Image'), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
				'of'               => __('of'), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
				'close'            => __('Close'), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
				'noiframes'        => __('This feature requires inline frames. You have iframes disabled or your browser does not support them.'), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
				'loadingAnimation' => includes_url('js/thickbox/loadingAnimation.gif'),
				'server_error'     => __('An unexpected error occurred. Please try again or contact support if the problem persists.', 'ultimate-multisite'),
			]
		);

		wp_register_script_module(
			'wu-flags-polyfill',
			wu_get_asset('flags.js', 'js'),
			array(),
			\WP_Ultimo::VERSION
		);

		/*
		 * WordPress localizes month names and all, but
		 * does not localize anything else. We need relative
		 * times to be translated, so we need to do it ourselves.
		 */
		$this->localize_moment();
	}

	/**
	 * Localize moment.js relative times.
	 *
	 * @since 2.0.8
	 * @return bool
	 */
	public function localize_moment() {

		$time_format = get_option('time_format', __('g:i a')); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
		$date_format = get_option('date_format', __('F j, Y')); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain

		$long_date_formats = array_map(
			'wu_convert_php_date_format_to_moment_js_format',
			[
				'LT'   => $time_format,
				'LTS'  => str_replace(':i', ':i:s', (string) $time_format),
				/* translators: the day/month/year date format used by Ultimate Multisite. You can changed it to localize this date format to your language. the default value is d/m/Y, which is the format 31/12/2021. */
				'L'    => __('d/m/Y', 'ultimate-multisite'),
				'LL'   => $date_format,
				'LLL'  => sprintf('%s %s', $date_format, $time_format),
				'LLLL' => sprintf('%s %s', $date_format, $time_format),
			]
		);

		$strings = [
			'relativeTime'   => [
				// translators: %s is a relative future date.
				'future' => __('in %s', 'ultimate-multisite'),
				// translators: %s is a relative past date.
				'past'   => __('%s ago', 'ultimate-multisite'),
				's'      => __('a few seconds', 'ultimate-multisite'),
				// translators: %s is the number of seconds.
				'ss'     => __('%d seconds', 'ultimate-multisite'),
				'm'      => __('a minute', 'ultimate-multisite'),
				// translators: %s is the number of minutes.
				'mm'     => __('%d minutes', 'ultimate-multisite'),
				'h'      => __('an hour', 'ultimate-multisite'),
				// translators: %s is the number of hours.
				'hh'     => __('%d hours', 'ultimate-multisite'),
				'd'      => __('a day', 'ultimate-multisite'),
				// translators: %s is the number of days.
				'dd'     => __('%d days', 'ultimate-multisite'),
				'w'      => __('a week', 'ultimate-multisite'),
				// translators: %s is the number of weeks.
				'ww'     => __('%d weeks', 'ultimate-multisite'),
				'M'      => __('a month', 'ultimate-multisite'),
				// translators: %s is the number of months.
				'MM'     => __('%d months', 'ultimate-multisite'),
				'y'      => __('a year', 'ultimate-multisite'),
				// translators: %s is the number of years.
				'yy'     => __('%d years', 'ultimate-multisite'),
			],
			'longDateFormat' => $long_date_formats,
		];

		$inline_script = sprintf("moment.updateLocale( '%s', %s );", get_user_locale(), wp_json_encode($strings));

		return did_action('init') && wp_add_inline_script('moment', $inline_script, 'after');
	}

	/**
	 * Registers the default Ultimate Multisite styles.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function register_default_styles(): void {

		$this->register_style('wu-styling', wu_get_asset('framework.css', 'css'), []);

		$this->register_style('wu-admin', wu_get_asset('admin.css', 'css'), ['wu-styling']);

		$this->register_style('wu-checkout', wu_get_asset('checkout.css', 'css'), ['wu-styling']);

		$this->register_style('wu-flags', wu_get_asset('flags.css', 'css'), []);

		$this->register_style('wu-password', wu_get_asset('password.css', 'css'), ['dashicons']);
	}

	/**
	 * Loads the default admin styles.
	 *
	 * Only enqueued on WP Ultimo admin pages to avoid loading 150KB+ of CSS
	 * on unrelated admin pages (e.g., Posts, Plugins, Settings).
	 *
	 * @since 2.0.0
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_default_admin_styles(string $hook_suffix): void {

		if ( ! wu_is_wu_page($hook_suffix)) {
			return;
		}

		wp_enqueue_style('wu-admin');

		// Password field styles for AJAX-loaded modals (e.g., Add Customer).
		wp_enqueue_style('wu-password');
	}

	/**
	 * Loads the default admin scripts.
	 *
	 * Only enqueued on WP Ultimo admin pages to avoid loading scripts
	 * on unrelated admin pages (e.g., Posts, Plugins, Settings).
	 *
	 * @since 2.0.0
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_default_admin_scripts(string $hook_suffix): void {

		if ( ! wu_is_wu_page($hook_suffix)) {
			return;
		}

		wp_enqueue_script('wu-admin');

		// Password toggle for AJAX-loaded modals (e.g., Add Customer).
		wp_enqueue_script('wu-password-toggle');
	}

	/**
	 * Update the use container setting.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function update_use_container(): void {

		check_ajax_referer('wu_toggle_container', 'nonce');

		$new_value = (bool) ! (get_user_setting('wu_use_container', false));

		set_user_setting('wu_use_container', $new_value);

		wp_die();
	}

	/**
	 * Add body classes of container boxed if user has setting.
	 *
	 * @since 2.0.0
	 *
	 * @param string $classes Body classes.
	 * @return string
	 */
	public function add_body_class_container_boxed($classes) {

		if (get_user_setting('wu_use_container', false)) {
			$classes .= ' has-wu-container ';
		}

		return $classes;
	}

	/**
	 * Get password requirements for client-side validation.
	 *
	 * Reads the admin setting for minimum password strength:
	 * - medium: Requires strength level 3
	 * - strong: Requires strength level 4
	 * - super_strong: Requires strength level 4 plus additional rules
	 *   (12+ chars, uppercase, lowercase, numbers, special characters)
	 *
	 * Also integrates with WPMU DEV Defender Pro when active.
	 *
	 * All settings are filterable for customization.
	 *
	 * @since 2.4.0
	 * @return array Password requirements settings.
	 */
	protected function get_password_requirements(): array {

		$defender_active = $this->is_defender_strong_password_active();

		// Get admin setting for minimum password strength.
		$strength_setting = wu_get_setting('minimum_password_strength', 'medium');

		// Map setting to zxcvbn score.
		$strength_map = [
			'weak'         => 2,
			'medium'       => 3,
			'strong'       => 4,
			'super_strong' => 4,
		];

		$default_strength = $strength_map[ $strength_setting ] ?? 4;

		// Enable rules enforcement for super_strong or when Defender is active.
		$is_super_strong = 'super_strong' === $strength_setting;
		$default_enforce = $is_super_strong || $defender_active;

		/**
		 * Filter the minimum password strength required (zxcvbn score).
		 *
		 * Strength levels:
		 * - 0, 1: Very weak
		 * - 2: Weak
		 * - 3: Medium
		 * - 4: Strong (default)
		 *
		 * @since 2.4.0
		 *
		 * @param int    $min_strength     The minimum strength level required.
		 * @param string $strength_setting The admin setting value (medium, strong, super_strong).
		 */
		$min_strength = apply_filters('wu_minimum_password_strength', $default_strength, $strength_setting);

		/**
		 * Filter whether to enforce additional password rules.
		 *
		 * When true, enforces minimum length and character requirements.
		 * Automatically enabled for "Super Strong" setting or when
		 * Defender Pro's Strong Password feature is active.
		 *
		 * @since 2.4.0
		 *
		 * @param bool   $enforce_rules    Whether to enforce additional rules.
		 * @param string $strength_setting The admin setting value.
		 * @param bool   $defender_active  Whether Defender Pro Strong Password is active.
		 */
		$enforce_rules = apply_filters('wu_enforce_password_rules', $default_enforce, $strength_setting, $defender_active);

		/**
		 * Filter the minimum password length.
		 *
		 * Only enforced when wu_enforce_password_rules is true.
		 *
		 * @since 2.4.0
		 *
		 * @param int  $min_length    Minimum password length. Default 12 (matches Defender Pro).
		 * @param bool $defender_active Whether Defender Pro Strong Password is active.
		 */
		$min_length = apply_filters('wu_minimum_password_length', 12, $defender_active);

		/**
		 * Filter whether to require uppercase letters in passwords.
		 *
		 * @since 2.4.0
		 *
		 * @param bool $require       Whether to require uppercase. Default true when rules enforced.
		 * @param bool $defender_active Whether Defender Pro Strong Password is active.
		 */
		$require_uppercase = apply_filters('wu_password_require_uppercase', $enforce_rules, $defender_active);

		/**
		 * Filter whether to require lowercase letters in passwords.
		 *
		 * @since 2.4.0
		 *
		 * @param bool $require       Whether to require lowercase. Default true when rules enforced.
		 * @param bool $defender_active Whether Defender Pro Strong Password is active.
		 */
		$require_lowercase = apply_filters('wu_password_require_lowercase', $enforce_rules, $defender_active);

		/**
		 * Filter whether to require numbers in passwords.
		 *
		 * @since 2.4.0
		 *
		 * @param bool $require       Whether to require numbers. Default true when rules enforced.
		 * @param bool $defender_active Whether Defender Pro Strong Password is active.
		 */
		$require_number = apply_filters('wu_password_require_number', $enforce_rules, $defender_active);

		/**
		 * Filter whether to require special characters in passwords.
		 *
		 * @since 2.4.0
		 *
		 * @param bool $require       Whether to require special chars. Default true when rules enforced.
		 * @param bool $defender_active Whether Defender Pro Strong Password is active.
		 */
		$require_special = apply_filters('wu_password_require_special', $enforce_rules, $defender_active);

		return [
			'strength_setting'  => $strength_setting,
			'min_strength'      => absint($min_strength),
			'enforce_rules'     => (bool) $enforce_rules,
			'min_length'        => absint($min_length),
			'require_uppercase' => (bool) $require_uppercase,
			'require_lowercase' => (bool) $require_lowercase,
			'require_number'    => (bool) $require_number,
			'require_special'   => (bool) $require_special,
		];
	}

	/**
	 * Check if WPMU DEV Defender Pro's Strong Password feature is active.
	 *
	 * @since 2.4.0
	 * @return bool True if Defender Strong Password is enabled.
	 */
	protected function is_defender_strong_password_active(): bool {

		// Check if Defender is active.
		if ( ! defined('DEFENDER_VERSION')) {
			return false;
		}

		// Try to get Defender's Strong Password settings.
		if ( ! function_exists('wd_di')) {
			return false;
		}

		try {
			$settings = wd_di()->get('WP_Defender\Model\Setting\Strong_Password');

			if ($settings && method_exists($settings, 'is_active')) {
				return $settings->is_active();
			}
		} catch (\Exception $e) {
			// Defender class not available or error occurred.
			return false;
		}

		return false;
	}
}
