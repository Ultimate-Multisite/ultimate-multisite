<?php
/**
 * Site Manager
 *
 * Handles processes related to sites.
 *
 * @package WP_Ultimo
 * @subpackage Managers/Site_Manager
 * @since 2.0.0
 */

namespace WP_Ultimo\Managers;

use WP_Ultimo\Helpers\Screenshot;
use WP_Ultimo\Database\Sites\Site_Type;
use WP_Ultimo\Database\Memberships\Membership_Status;
use WP_Ultimo\Models\Site;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Handles processes related to sites.
 *
 * @since 2.0.0
 */
class Site_Manager extends Base_Manager {

	use \WP_Ultimo\Apis\WP_CLI;
	use \WP_Ultimo\Apis\MCP_Abilities;
	use \WP_Ultimo\Apis\Command_Palette;
	use \WP_Ultimo\Traits\Singleton;
	use \WP_Ultimo\Apis\Rest_Api {
		\WP_Ultimo\Apis\Rest_Api::get_collection_params as trait_get_collection_params;
	}

	/**
	 * The manager slug.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $slug = 'site';

	/**
	 * The model class associated to this manager.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $model_class = \WP_Ultimo\Models\Site::class;

	/**
	 * Instantiate the necessary hooks.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function init(): void {

		$this->enable_rest_api();

		$this->enable_wp_cli();

		$this->enable_mcp_abilities();

		$this->enable_command_palette();

		add_action('after_setup_theme', [$this, 'additional_thumbnail_sizes']);

		add_action('wp_ajax_wu_get_screenshot', [$this, 'get_site_screenshot']);

		add_action('wu_async_take_screenshot', [$this, 'async_get_site_screenshot']);

		add_action('wp', [$this, 'lock_site']);

		add_action('admin_init', [$this, 'add_no_index_warning']);

		add_action('wp_head', [$this, 'prevent_site_template_indexing'], 0);

		add_action('login_enqueue_scripts', [$this, 'custom_login_logo']);

		add_filter('login_headerurl', [$this, 'login_header_url']);

		add_filter('login_headertext', [$this, 'login_header_text']);

		add_action('wu_pending_site_published', [$this, 'handle_site_published'], 10, 2);

		add_action('load-sites.php', [$this, 'add_notices_to_default_site_page']);

		add_action('load-site-new.php', [$this, 'add_notices_to_default_site_page']);

		add_filter('mucd_string_to_replace', [$this, 'search_and_replace_on_duplication'], 10, 3);

		add_action('wu_site_created', [$this, 'search_and_replace_for_new_site'], 10, 2);

		add_action('wu_handle_bulk_action_form_site_delete-pending', [$this, 'handle_delete_pending_sites'], 100, 3);

		add_filter('users_list_table_query_args', [$this, 'hide_super_admin_from_list'], 10, 1);

		add_action('wu_before_handle_order_submission', [$this, 'maybe_validate_add_new_site'], 15);

		add_action('wu_checkout_before_process_checkout', [$this, 'maybe_add_new_site'], 5);

		add_action('pre_get_blogs_of_user', [$this, 'hide_customer_sites_from_super_admin_list'], 999, 3);

		add_filter('wpmu_validate_blog_signup', [$this, 'allow_hyphens_in_site_name'], 10, 1);

		add_action('wu_daily', [$this, 'delete_pending_sites']);

		// Demo site cleanup - runs hourly to check for expired demo sites.
		add_action('wu_hourly', [$this, 'check_expired_demo_sites']);

		// Demo site expiring notification - runs hourly to send warning emails.
		add_action('wu_hourly', [$this, 'check_expiring_demo_sites']);

		// Async handler for demo site deletion.
		add_action('wu_async_delete_demo_site', [$this, 'async_delete_demo_site'], 10, 1);

		// Admin bar notice for keep-until-live demo sites.
		add_action('admin_bar_menu', [$this, 'add_demo_admin_bar_menu'], 999);

		// Handle "go live" action requests from site admins.
		add_action('wp', [$this, 'handle_go_live_action']);
	}

	/**
	 * Returns the query params for the site collection endpoint.
	 *
	 * Extends the base pagination params with site-specific filters for
	 * meta-stored fields (type, customer_id, membership_id, template_id).
	 * These params are converted to meta_query clauses by Site_Query::query().
	 *
	 * @since 2.5.0
	 * @return array
	 */
	public function get_collection_params() {

		$params = $this->trait_get_collection_params();

		$params['type'] = [
			'description'       => __('Filter sites by type (e.g. customer_owned, site_template, pending, external).', 'ultimate-multisite'),
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => 'rest_validate_request_arg',
		];

		$params['customer_id'] = [
			'description'       => __('Filter sites by the ID of the owning customer.', 'ultimate-multisite'),
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
			'minimum'           => 1,
		];

		$params['membership_id'] = [
			'description'       => __('Filter sites by the ID of the associated membership.', 'ultimate-multisite'),
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
			'minimum'           => 1,
		];

		$params['template_id'] = [
			'description'       => __('Filter sites by the ID of the template used to create them.', 'ultimate-multisite'),
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
			'minimum'           => 1,
		];

		return $params;
	}

	/**
	 * Allows for hyphens to be used, since WordPress supports it.
	 *
	 * @since 2.1.3
	 *
	 * @param array $result The wpmu_validate_blog_signup result.
	 * @return array
	 */
	public function allow_hyphens_in_site_name($result) {

		$errors = $result['errors'];

		$blogname_errors = $errors->get_error_messages('blogname');

		$message_to_ignore = __('Site names can only contain lowercase letters (a-z) and numbers.', 'ultimate-multisite');

		$error_key = array_search($message_to_ignore, $blogname_errors, true);

		/**
		 * Check if we have an error for only letters and numbers
		 * if so, we remove it and re-validate with our custom rule
		 * which is the same, but also allows for hyphens.
		 */
		if ( ! empty($blogname_errors) && false !== $error_key) {
			unset($result['errors']->errors['blogname'][ $error_key ]);

			if (empty($result['errors']->errors['blogname'])) {
				unset($result['errors']->errors['blogname']);
			}

			if (preg_match('/[^a-z0-9-]+/', (string) $result['blogname'])) {
				$result['errors']->add('blogname', __('Site names can only contain lowercase letters (a-z), numbers, and hyphens.', 'ultimate-multisite'));
			}
		}

		return $result;
	}

	/**
	 * Handles the request to add a new site, if that's the case.
	 *
	 * @since 2.0.11
	 *
	 * @param \WP_Ultimo\Checkout\Checkout $checkout The current checkout object.
	 * @return void
	 */
	public function maybe_validate_add_new_site($checkout): void {

		global $wpdb;

		if (wu_request('create-new-site') && wp_verify_nonce(wu_request('create-new-site'), 'create-new-site')) {
			$errors = new \WP_Error();

			$rules = [
				'site_title' => 'min:4',
				'site_url'   => 'required|lowercase|unique_site',
			];

			if ($checkout->is_last_step()) {
				$membership = WP_Ultimo()->currents->get_membership();

				$customer = wu_get_current_customer();

				if ( ! $customer || ! $membership || $customer->get_id() !== $membership->get_customer_id()) {
					$errors->add('not-owner', __('You do not have the necessary permissions to add a site to this membership', 'ultimate-multisite'));
				}

				if ($errors->has_errors() === false) {
					$d = wu_get_site_domain_and_path(wu_request('site_url', ''), $checkout->request_or_session('site_domain'));

					/*
					 * Apply the wu_checkout_template_id filter so that
					 * "Assign Site Template" mode is honoured when adding
					 * a new site to an existing membership.
					 *
					 * @since 2.5.0
					 */
					$template_id = apply_filters(
						'wu_checkout_template_id',
						(int) $checkout->request_or_session('template_id'),
						$membership,
						$checkout
					);

					$pending_site = $membership->create_pending_site(
						[
							'domain'        => $d->domain,
							'path'          => $d->path,
							'template_id'   => $template_id,
							'title'         => $checkout->request_or_session('site_title'),
							'customer_id'   => $customer->get_id(),
							'membership_id' => $membership->get_id(),
						]
					);

					if (is_wp_error($pending_site)) {
						wp_send_json_error($pending_site);

						exit;
					}

					$results = $membership->publish_pending_site();

					if (is_wp_error($results)) {
						wp_send_json_error($errors);
					}
				} else {
					wp_send_json_error($errors);
				}

				wp_send_json_success([]);
			} else {
				$validation = $checkout->validate($rules);

				if (is_wp_error($validation)) {
					wp_send_json_error($validation);
				}

				$wpdb->query('COMMIT'); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

				wp_send_json_success([]);
			}
		}
	}

	/**
	 * Checks if the current request is a add new site request.
	 *
	 * @since 2.0.11
	 * @return void
	 */
	public function maybe_add_new_site(): void {

		if (wu_request('create-new-site') && wp_verify_nonce(wu_request('create-new-site'), 'create-new-site')) {
			$redirect_url = wu_request('redirect_url', admin_url('admin.php?page=sites'));

			$redirect_url = add_query_arg(
				[
					'new_site_created' => true,
				],
				$redirect_url
			);

			wp_safe_redirect($redirect_url);

			exit;
		}
	}

	/**
	 * Triggers the do_event of the site publish successful.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_Ultimo\Models\Site       $site The site.
	 * @param \WP_Ultimo\Models\Membership $membership The payment.
	 * @return void
	 */
	public function handle_site_published($site, $membership): void {
		/*
		 * If this is a demo site, set the expiration time.
		 * Skip if the demo product is configured to keep until the customer goes live.
		 */
		if ($site->is_demo()) {
			if ($site->is_keep_until_live()) {
				wu_log_add(
					'demo-sites',
					sprintf(
						// translators: %d is the site ID.
						__('Demo site #%d created in keep-until-live mode (no expiration set).', 'ultimate-multisite'),
						$site->get_id()
					)
				);
			} else {
				$expires_at = $site->calculate_demo_expiration();
				$site->set_demo_expires_at($expires_at);

				wu_log_add(
					'demo-sites',
					sprintf(
						// translators: %1$d is site ID, %2$s is expiration datetime.
						__('Demo site #%1$d created, expires at %2$s', 'ultimate-multisite'),
						$site->get_id(),
						$expires_at
					)
				);
			}
		}

		$payload = array_merge(
			wu_generate_event_payload('site', $site),
			wu_generate_event_payload('membership', $membership),
			wu_generate_event_payload('customer', $membership->get_customer())
		);

		wu_do_event('site_published', $payload);
	}

	/**
	 * Locks the site front-end if the site is not public.
	 *
	 * @todo Let the admin chose the behavior. Maybe redirect to main site?
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function lock_site(): void {

		if (is_main_site() || is_admin() || wu_is_login_page() || wp_doing_ajax() || wu_request('wu-ajax') || (function_exists('wp_is_rest_endpoint') && wp_is_rest_endpoint())) {
			return;
		}

		$can_access = true;

		$redirect_url = null;

		$site = wu_get_current_site();

		/*
		 * Block frontend for keep-until-live demo sites.
		 *
		 * These sites remain accessible in wp-admin (the admin can still build
		 * their site) but the public-facing frontend is blocked until the customer
		 * explicitly activates the site. Site administrators are allowed through
		 * so they can preview their work.
		 */
		if ($site->is_keep_until_live()) {
			if (current_user_can('manage_options') || is_super_admin()) {
				// Site admins can see the frontend — let them through.
				return;
			}

			wp_die(
				wp_kses_post(
					sprintf(
						// translators: %s: link to the login page
						__('This site is currently in demo mode and is not yet available to the public.<br><small>If you are the site owner, <a href="%s">log in</a> to access your dashboard.</small>', 'ultimate-multisite'),
						esc_url(wp_login_url(get_permalink()))
					)
				),
				esc_html__('Site in Demo Mode', 'ultimate-multisite'),
			);
		}

		if ( ! $site->is_active()) {
			$can_access = false;
		}

		$membership = $site->get_membership();

		$status = $membership ? $membership->get_status() : false;

		$is_cancelled = Membership_Status::CANCELLED === $status;

		$is_inactive = $status && ! $membership->is_active() && Membership_Status::TRIALING !== $status;

		if ($is_cancelled || ($is_inactive && wu_get_setting('block_frontend', false))) {

			// If membership is cancelled we do not add the grace period
			$grace_period = Membership_Status::CANCELLED !== $status ? (int) wu_get_setting('block_frontend_grace_period', 0) : 0;

			$expiration_time = wu_date($membership->get_date_expiration())->getTimestamp() + $grace_period * DAY_IN_SECONDS;

			if ($expiration_time < wu_date()->getTimestamp()) {
				$checkout_pages = \WP_Ultimo\Checkout\Checkout_Pages::get_instance();

				// We only show the url field when block_frontend is true
				$redirect_url = wu_get_setting('block_frontend', false) ? $checkout_pages->get_page_url('block_frontend') : false;

				$can_access = false;
			}
		}

		if (false === $can_access) {

			/*
			 * Allow plugins to provide a reactivation URL for blocked sites.
			 *
			 * This filter fires for ALL inactive membership states (cancelled,
			 * expired, on-hold, suspended) so that a custom reactivation page
			 * can intercept the user before they see the generic "site not
			 * available" message.
			 *
			 * @since 2.5.0
			 *
			 * @param string|null $reactivation_url URL to redirect to, or null to skip.
			 * @param object|null $membership       The membership object, or null if not found.
			 * @param object      $site             The site object.
			 */
			$reactivation_url = apply_filters('wu_blocked_site_reactivation_url', null, $membership, $site);

			if ($reactivation_url) {
				wp_safe_redirect($reactivation_url);

				exit;
			}

			if ($redirect_url) {
				wp_safe_redirect($redirect_url);

				exit;
			}

			wp_die(
				// translators: %s: link to the login page
				sprintf(wp_kses_post(__('This site is not available at the moment.<br><small>If you are the site admin, click <a href="%s">here</a> to login.</small>', 'ultimate-multisite')), esc_attr(wp_login_url())),
				esc_html__('Site not available', 'ultimate-multisite'),
			);
		}
	}

	/**
	 * Takes screenshots asynchronously.
	 *
	 * @since 2.0.0
	 *
	 * @param int $site_id The site ID.
	 * @return void
	 */
	public function async_get_site_screenshot($site_id) {

		$site = wu_get_site($site_id);

		if ( ! $site) {
			return;
		}

		$domain = $site->get_active_site_url();

		$attachment_id = Screenshot::take_screenshot($domain);

		if ( ! $attachment_id) {
			return;
		}

		$site->set_featured_image_id($attachment_id);

		$site->save();
	}

	/**
	 * Listens for the ajax endpoint and generate the screenshot.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function get_site_screenshot(): void {

		$site_id = wu_request('site_id');

		$site = wu_get_site($site_id);

		if ( ! $site) {
			wp_send_json_error(
				new \WP_Error('missing-site', __('Site not found.', 'ultimate-multisite'))
			);
		}

		$domain = $site->get_active_site_url();

		$attachment_id = Screenshot::take_screenshot($domain);

		if ( ! $attachment_id) {
			wp_send_json_error(
				new \WP_Error('error', __('We were not able to fetch the screenshot.', 'ultimate-multisite'))
			);
		}

		$attachment_url = wp_get_attachment_image_src($attachment_id, 'wu-thumb-medium');

		wp_send_json_success(
			[
				'attachment_id'  => $attachment_id,
				'attachment_url' => $attachment_url[0],
			]
		);
	}

	/**
	 * Add the additional sizes required by Ultimate Multisite.
	 *
	 * Add for the main site only.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function additional_thumbnail_sizes(): void {

		if (is_main_site()) {
			add_image_size('wu-thumb-large', 900, 675, ['center', 'top']); // cropped
			add_image_size('wu-thumb-medium', 400, 300, ['center', 'top']); // cropped
		}
	}

	/**
	 * Adds a notification if the no-index setting is active.
	 *
	 * @since 1.9.8
	 * @return void
	 */
	public function add_no_index_warning(): void {

		if (wu_get_setting('stop_template_indexing', false)) {
			add_meta_box('wu-warnings', __('Ultimate Multisite - Search Engines', 'ultimate-multisite'), [$this, 'render_no_index_warning'], 'dashboard-network', 'normal', 'high');
		}
	}

	/**
	 * Renders the no indexing warning.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function render_no_index_warning(): void {
		?>

		<div class="wu-styling">

			<div class="wu-border-l-4 wu-border-yellow-500 wu-border-solid wu-border-0 wu-px-4 wu-py-2 wu--m-3">

				<p><?php echo wp_kses_post(__('Your Ultimate Multisite settings are configured to <strong>prevent search engines such as Google from indexing your template sites</strong>.', 'ultimate-multisite')); ?></p>

				<?php // translators: %s: link to the settings page ?>
				<p><?php echo wp_kses_post(sprintf(__('If you are experiencing negative SEO impacts on other sites in your network, consider disabling this setting <a href="%s">here</a>.', 'ultimate-multisite'), wu_network_admin_url('wp-ultimo-settings', ['tab' => 'sites']))); ?></p>

			</div>

		</div>

		<?php
	}

	/**
	 * Prevents Search Engines from indexing Site Templates.
	 *
	 * @since 1.6.0
	 * @return void
	 */
	public function prevent_site_template_indexing(): void {

		if ( ! wu_get_setting('stop_template_indexing', false)) {
			return;
		}

		$site = wu_get_current_site();

		if ($site && $site->get_type() === Site_Type::SITE_TEMPLATE) {
			if (function_exists('wp_robots_no_robots')) {
				add_filter('wp_robots', 'wp_robots_no_robots'); // WordPress 5.7+

			} else {
				wp_no_robots(); // phpcs:ignore WordPress.WP.DeprecatedFunctions.wp_no_robotsFound
			}
		}
	}

	/**
	 * Check if sub-site has a custom logo and change login logo.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function custom_login_logo(): void {

		if ( ! wu_get_setting('subsite_custom_login_logo', false) || ! has_custom_logo()) {
			$logo = wu_get_network_logo();
		} else {
			$logo = wp_get_attachment_image_src(get_theme_mod('custom_logo'), 'full');

			$logo = wu_get_isset($logo, 0, false);
		}

		if (empty($logo)) {
			return;
		}

		wp_add_inline_style(
			'login',
			sprintf(
				'#login h1 a, .login h1 a {
                    background-image: url(%s);
                    background-position: center center;
                    background-size: contain;
                }',
				esc_url($logo)
			)
		);
	}

	/**
	 * Replaces the WordPress url with the site url.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function login_header_url() {

		return get_site_url();
	}

	/**
	 * Replaces the WordPress text with the site name.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function login_header_text() {

		return get_bloginfo('name');
	}

	/**
	 * Add notices to default site page, recommending the Ultimate Multisite option.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function add_notices_to_default_site_page(): void {

		$notice = __('Hey there! We highly recommend managing your network sites using the Ultimate Multisite &rarr; Sites page. <br>If you want to avoid confusion, you can also hide this page from the admin panel completely on the Ultimate Multisite &rarr; Settings &rarr; Whitelabel options.', 'ultimate-multisite');

		WP_Ultimo()->notices->add(
			$notice,
			'info',
			'network-admin',
			'wu-sites-use-wp-ultimo',
			[
				[
					'title' => __('Go to the Ultimate Multisite Sites page &rarr;', 'ultimate-multisite'),
					'url'   => wu_network_admin_url('wp-ultimo-sites'),
				],
				[
					'title' => __('Go to the Whitelabel Settings &rarr;', 'ultimate-multisite'),
					'url'   => wu_network_admin_url(
						'wp-ultimo-settings',
						[
							'tab' => 'whitelabel',
						]
					),
				],
			]
		);
	}

	/**
	 * Add search and replace filter to be used on site duplication.
	 *
	 * @since 1.6.2
	 * @param array $search_and_replace List to search and replace.
	 * @param int   $from_site_id original site id.
	 * @param int   $to_site_id New site id.
	 * @return array
	 */
	public function search_and_replace_on_duplication($search_and_replace, $from_site_id, $to_site_id) {

		$search_and_replace_settings = $this->get_search_and_replace_settings();

		$additional_duplication = apply_filters('wu_search_and_replace_on_duplication', $search_and_replace_settings, $from_site_id, $to_site_id);

		$final_list = array_merge($search_and_replace, $additional_duplication);

		return $this->filter_illegal_search_keys($final_list);
	}

	/**
	 * Get search and replace settings
	 *
	 * @since 1.7.0
	 * @return array
	 */
	public function get_search_and_replace_settings() {

		$search_and_replace = wu_get_setting('search_and_replace', []);

		$pairs = [];

		foreach ($search_and_replace as $item) {
			if ((isset($item['search']) && ! empty($item['search'])) && isset($item['replace'])) {
				$pairs[ $item['search'] ] = $item['replace'];
			}
		}

		return $pairs;
	}

	/**
	 * Handles search and replace for new blogs from WordPress.
	 *
	 * @since 1.7.0
	 * @param array $data The date being saved.
	 * @param Site  $site The site object.
	 * @return void
	 */
	public static function search_and_replace_for_new_site($data, $site): void {

		$to_site_id = $site->get_id();

		if ( ! $to_site_id) {
			return;
		}

		/**
		 * In order to be backwards compatible here, we'll have to do some crazy stuff,
		 * like overload the form session with the meta data saved on the pending site.
		 */
		$transient = wu_get_site($to_site_id)->get_meta('wu_form_data', []);

		wu_get_session('signup')->set('form', $transient);

		global $wpdb;

		$to_blog_prefix = $wpdb->get_blog_prefix($to_site_id);

		$string_to_replace = apply_filters('mucd_string_to_replace', [], false, $to_site_id); // phpcs:ignore

		$tables = [];

		$to_blog_prefix_like = $wpdb->esc_like($to_blog_prefix);

		$results = \MUCD_Data::do_sql_query('SHOW TABLES LIKE \'' . $to_blog_prefix_like . '%\'', 'col', false);

		foreach ($results as $k => $v) {
			$tables[ str_replace($to_blog_prefix, '', (string) $v) ] = [];
		}

		foreach ( $tables as $table => $col) {
			$results = \MUCD_Data::do_sql_query('SHOW COLUMNS FROM `' . $to_blog_prefix . $table . '`', 'col', false);

			$columns = [];

			foreach ($results as $k => $v) {
				$columns[] = $v;
			}

			$tables[ $table ] = $columns;
		}

		$default_tables = \MUCD_Option::get_fields_to_update();

		foreach ($default_tables as $table => $field) {
			$tables[ $table ] = $field;
		}

		foreach ($tables as $table => $field) {
			foreach ($string_to_replace as $from_string => $to_string) {
				\MUCD_Data::update($to_blog_prefix . $table, $field, $from_string, $to_string);
			}
		}
	}
	/**
	 * Makes sure the search and replace array have no illegal values, such as null, false, etc
	 *
	 * @since 1.7.3
	 * @param array $search_and_replace The search and replace list.
	 */
	public function filter_illegal_search_keys($search_and_replace): array {

		return array_filter($search_and_replace, fn($k) => ! is_null($k) && false !== $k && ! empty($k), ARRAY_FILTER_USE_KEY);
	}

	/**
	 * Handle the deletion of pending sites.
	 *
	 * @since 2.0.0
	 *
	 * @param string $action The action.
	 * @param string $model The model.
	 * @param array  $ids The ids list.
	 * @return void
	 */
	public function handle_delete_pending_sites($action, $model, $ids): void {

		foreach ($ids as $membership_id) {
			$membership = wu_get_membership($membership_id);

			if (empty($membership)) {
				/*
				 * Make sure we are able to delete pending
				 * sites even when memberships no longer exist.
				 */
				delete_metadata('wu_membership', $membership_id, 'pending_site');

				continue;
			}

			$membership->delete_pending_site();
		}

		wp_send_json_success(
			[
				'redirect_url' => add_query_arg('deleted', count($ids), wu_get_current_url()),
			]
		);
	}

	/**
	 * Hide the super admin user from the sub-site table list.
	 *
	 * @since 2.0.0
	 *
	 * @param array $args List table user search arguments.
	 * @return array
	 */
	public function hide_super_admin_from_list($args) {

		if ( ! is_super_admin()) {
			$args['login__not_in'] = get_super_admins();
		}

		return $args;
	}

	/**
	 * Hides customer sites from the super admin user on listing.
	 *
	 * @since 2.0.11
	 *
	 * @param null|object[] $sites   An array of site objects of which the user is a member.
	 * @param int           $user_id User ID.
	 * @param bool          $all     Whether the returned array should contain all sites, including
	 *                               those marked 'deleted', 'archived', or 'spam'. Default false.
	 */
	public function hide_customer_sites_from_super_admin_list($sites, $user_id, $all) {

		global $wpdb;

		if ( ! is_super_admin()) {
			return $sites;
		}

		$keys = get_user_meta($user_id);

		if (empty($keys)) {
			return $sites;
		}

		// List the main site at beginning of array.
		if (isset($keys[ $wpdb->base_prefix . 'capabilities' ]) && defined('MULTISITE')) {
			$site_ids[] = 1;

			unset($keys[ $wpdb->base_prefix . 'capabilities' ]);
		}

		$keys = array_keys($keys);

		foreach ($keys as $key) {
			if (! str_ends_with($key, 'capabilities')) {
				continue;
			}

			if ($wpdb->base_prefix && ! str_starts_with($key, (string) $wpdb->base_prefix)) {
				continue;
			}

			$site_id = str_replace([$wpdb->base_prefix, '_capabilities'], '', $key);

			if ( ! is_numeric($site_id)) {
				continue;
			}

			$site_ids[] = (int) $site_id;
		}

		$sites = [];

		if ( ! empty($site_ids)) {

			/**
			 * Here we change the default WP behavior to filter
			 * sites with wu_type meta value different than
			 * Site_Type::CUSTOMER_OWNED or without this meta
			 */
			$args = [
				'site__in'               => $site_ids,
				'update_site_meta_cache' => false,
				'number'                 => 40,
				'meta_query'             => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'OR',
					[
						'key'     => 'wu_type',
						'compare' => 'NOT EXISTS',
					],
					[
						'key'     => 'wu_type',
						'compare' => 'NOT LIKE',
						'value'   => Site_Type::CUSTOMER_OWNED,
					],
				],
			];

			if ( ! $all) {
				$args['archived'] = 0;
				$args['spam']     = 0;
				$args['deleted']  = 0;
			}

			$_sites = array_merge(
				[
					get_site(wu_get_main_site_id()),
				],
				get_sites($args),
			);

			foreach ($_sites as $site) {
				if ( ! $site) {
					continue;
				}

				$sites[ $site->id ] = (object) [
					'userblog_id' => $site->id,
					'blogname'    => $site->blogname,
					'domain'      => $site->domain,
					'path'        => $site->path,
					'site_id'     => $site->network_id,
					'siteurl'     => $site->siteurl,
					'archived'    => $site->archived,
					'mature'      => $site->mature,
					'spam'        => $site->spam,
					'deleted'     => $site->deleted,
				];
			}
		}

		/**
		 * Replicates the original WP Filter here, for good measure.
		 *
		 * Filters the list of sites a user belongs to.
		 *
		 * @since 2.0.11
		 *
		 * @param object[] $sites   An array of site objects belonging to the user.
		 * @param int      $user_id User ID.
		 * @param bool     $all     Whether the returned sites array should contain all sites, including
		 *                          those marked 'deleted', 'archived', or 'spam'. Default false.
		 */
		return apply_filters('get_blogs_of_user', $sites, $user_id, $all); // phpcs:ignore
	}

	/**
	 * Delete pending sites from non-pending memberships
	 *
	 * Runs daily via `wu_daily`. Cleans up stale `pending_site` records in two
	 * scenarios:
	 *
	 * 1. Active/trialing membership with a pending_site older than 1 day: the
	 *    site was supposed to be published but the async job never ran or was
	 *    lost. Remove the stale record so the membership does not remain stuck.
	 *
	 * 2. Membership still in `pending` state with a pending_site older than
	 *    1 day: the customer never completed payment. `cleanup_expired_drafts`
	 *    (primary fix, GH#982) cancels the membership after 30 days, which
	 *    triggers `handle_pending_site_on_cancellation()` automatically. This
	 *    branch acts as a safety net for records that slip through — for
	 *    example if the cron payment cleanup ran before the hook was in place,
	 *    or if the membership was put into `pending` state via a code path
	 *    that did not call `cancel()`. In this case we directly delete the
	 *    pending_site from membership meta; no site was ever created, so
	 *    there is nothing to reclaim. GH#982.
	 *
	 * @since 2.1.3
	 */
	public function delete_pending_sites(): void {

		$pending_sites = \WP_Ultimo\Models\Site::get_all_by_type('pending');

		$one_day_ago = gmdate('Y-m-d H:i:s', strtotime('-1 days'));

		foreach ($pending_sites as $site) {
			if ($site->is_publishing()) {
				continue;
			}

			$membership = $site->get_membership();

			// Guard against pending_site objects that lack a valid membership_id
			// (e.g. created without one in an unusual code path, or serialised
			// before GH#982 normalised the data).
			if ( ! $membership) {
				continue;
			}

			if ($membership->is_active() || $membership->is_trialing()) {

				// Check if the last modify has more than some time, to avoid the deletion of sites on creation process
				if ($membership->get_date_modified() < $one_day_ago) {
					$membership->delete_pending_site();
				}
			} elseif (Membership_Status::PENDING === $membership->get_status()) {

				/*
				 * Safety net for orphaned pending_sites on `pending` memberships.
				 *
				 * The primary fix (cleanup_expired_drafts) cancels the membership
				 * after 30 days, which triggers handle_pending_site_on_cancellation.
				 * This branch catches records that predate that fix or were created
				 * via a code path that skipped the payment watchdog (e.g. manual
				 * gateway, admin-created memberships without a payment). We wait at
				 * least 1 day before deleting to avoid removing sites that are in
				 * the process of being published. GH#982.
				 */
				if ($membership->get_date_modified() < $one_day_ago) {
					$membership->delete_pending_site();
				}
			}
		}
	}

	/**
	 * Check for expired demo sites and schedule their deletion.
	 *
	 * This method runs hourly via the wu_hourly cron hook.
	 * It finds all demo sites that have passed their expiration time
	 * and schedules async deletion for each one.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function check_expired_demo_sites(): void {

		$demo_sites = Site::get_all_by_type(Site_Type::DEMO);

		if (empty($demo_sites)) {
			return;
		}

		$current_time = wu_get_current_time('mysql', true);

		foreach ($demo_sites as $site) {
			// Skip keep-until-live sites — they never auto-expire.
			if ($site->is_keep_until_live()) {
				continue;
			}

			$expires_at = $site->get_meta('wu_demo_expires_at');

			// Skip sites without expiration set.
			if (empty($expires_at)) {
				continue;
			}

			// Check if the demo has expired.
			if ($expires_at <= $current_time) {
				wu_enqueue_async_action(
					'wu_async_delete_demo_site',
					['site_id' => $site->get_id()],
					'wu_demo_cleanup'
				);
			}
		}
	}

	/**
	 * Check for demo sites that are about to expire and send notification emails.
	 *
	 * This method runs hourly via the wu_hourly cron hook.
	 * It finds all demo sites that will expire within the configured warning window
	 * and fires the demo_site_expiring event for each one.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function check_expiring_demo_sites(): void {

		// Check if expiration notifications are enabled.
		if ( ! wu_get_setting('demo_expiring_notification', false)) {
			return;
		}

		$demo_sites = Site::get_all_by_type(Site_Type::DEMO);

		if (empty($demo_sites)) {
			return;
		}

		// Get the warning time in hours.
		$warning_hours = (int) wu_get_setting('demo_expiring_warning_time', 24);

		// Calculate warning threshold based on current time.
		$current_time    = wu_get_current_time('timestamp', true);
		$warning_seconds = $warning_hours * HOUR_IN_SECONDS;

		foreach ($demo_sites as $site) {
			// Skip keep-until-live sites — they don't have an expiration.
			if ($site->is_keep_until_live()) {
				continue;
			}

			$expires_at = $site->get_meta('wu_demo_expires_at');

			// Skip sites without expiration set.
			if (empty($expires_at)) {
				continue;
			}

			// Skip sites already notified.
			if ($site->get_meta('wu_demo_expiring_notified')) {
				continue;
			}

			// Convert expiration to timestamp for comparison.
			$expires_timestamp = strtotime($expires_at);

			// Skip if already expired (handled by check_expired_demo_sites).
			if ($expires_timestamp <= $current_time) {
				continue;
			}

			// Calculate time remaining until expiration.
			$time_remaining = $expires_timestamp - $current_time;

			// Check if site is within the warning window.
			if ($time_remaining > $warning_seconds) {
				continue;
			}

			// Get associated data for the event payload.
			$membership = $site->get_membership();
			$customer   = $membership ? $membership->get_customer() : null;

			// Skip if no customer to notify.
			if (empty($customer)) {
				continue;
			}

			// Build human-readable time remaining.
			$time_remaining_human = human_time_diff($current_time, $expires_timestamp);

			// Build the event payload.
			$payload = [
				'site'                => $site->to_array(),
				'membership'          => $membership ? $membership->to_array() : [],
				'customer'            => $customer->to_array(),
				'demo_expires_at'     => $expires_at,
				'demo_time_remaining' => $time_remaining_human,
				'site_admin_url'      => get_admin_url($site->get_blog_id()),
				'site_url'            => $site->get_active_site_url(),
			];

			// Fire the demo_site_expiring event.
			wu_do_event('demo_site_expiring', $payload);

			// Mark the site as notified to prevent duplicate emails.
			$site->update_meta('wu_demo_expiring_notified', 1);
		}
	}

	/**
	 * Async handler to delete a demo site.
	 *
	 * This method handles the actual deletion of a demo site,
	 * including the WordPress blog and associated membership/customer
	 * data if configured to do so.
	 *
	 * @since 2.5.0
	 *
	 * @param int $site_id The site ID to delete.
	 * @return void
	 */
	public function async_delete_demo_site($site_id): void {

		$site = wu_get_site($site_id);

		if (empty($site)) {
			return;
		}

		// Verify it's still a demo site (could have been converted).
		if ($site->get_type() !== Site_Type::DEMO) {
			return;
		}

		// Get associated data before deletion.
		$membership = $site->get_membership();
		$customer   = $site->get_customer();
		$blog_id    = $site->get_blog_id();

		// Fire pre-deletion hook for extensibility.
		do_action('wu_before_demo_site_deleted', $site, $membership, $customer);

		// Delete the site record (wp_delete_site handles the underlying blog removal).
		$result = $site->delete();

		if (true !== $result) {
			wu_log_add(
				'demo-cleanup',
				sprintf(
					// translators: %d is the site ID.
					__('Failed to delete demo site #%d; skipping related cleanup.', 'ultimate-multisite'),
					$site_id
				)
			);

			return;
		}

		// Optionally delete the membership if it only has this demo site.
		$delete_membership = apply_filters('wu_demo_site_delete_membership', true, $membership, $site);

		if ($delete_membership && $membership) {
			$membership_sites = $membership->get_sites();

			// Only delete if this was the only site on the membership.
			if (empty($membership_sites) || count($membership_sites) === 0) {
				$membership->delete();
			}
		}

		// Optionally delete the customer if they only had this demo.
		$delete_customer = apply_filters('wu_demo_site_delete_customer', wu_get_setting('demo_delete_customer', false), $customer, $site);

		if ($delete_customer && $customer) {
			$customer_memberships = $customer->get_memberships();

			// Only delete if this was their only membership.
			if (empty($customer_memberships) || count($customer_memberships) === 0) {
				$user_id = $customer->get_user_id();

				$customer->delete();

				// Delete the WordPress user as well.
				if ($user_id && apply_filters('wu_demo_site_delete_user', true, $user_id)) {
					require_once ABSPATH . 'wp-admin/includes/user.php';
					wpmu_delete_user($user_id);
				}
			}
		}

		// Fire post-deletion hook.
		do_action('wu_after_demo_site_deleted', $site_id, $blog_id, $membership, $customer);

		// Log the deletion.
		wu_log_add(
			'demo-cleanup',
			sprintf(
				// translators: %1$d is site ID, %2$d is blog ID.
				__('Deleted expired demo site #%1$d (blog_id: %2$d)', 'ultimate-multisite'),
				$site_id,
				$blog_id ?: 0
			)
		);
	}

	/**
	 * Add an admin bar notice for keep-until-live demo sites.
	 *
	 * When a site administrator views the frontend of a site that is in
	 * "keep until live" demo mode, this adds an admin bar item informing
	 * them that the site is in demo mode and providing a "Go Live" link.
	 *
	 * @since 2.5.0
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar The WP_Admin_Bar instance.
	 * @return void
	 */
	public function add_demo_admin_bar_menu(\WP_Admin_Bar $wp_admin_bar): void {

		if (is_admin()) {
			return;
		}

		$site = wu_get_current_site();

		if ( ! $site || ! $site->is_keep_until_live()) {
			return;
		}

		// Only show to users who have admin access to this site.
		if ( ! current_user_can('manage_options') && ! is_super_admin()) {
			return;
		}

		$go_live_url = wu_get_setting('demo_go_live_url', '');

		if (empty($go_live_url)) {
			// Fall back to the direct go-live action URL.
			$go_live_url = wp_nonce_url(
				add_query_arg(
					[
						'wu_go_live' => $site->get_id(),
					],
					get_home_url()
				),
				'wu_go_live_' . $site->get_id()
			);
		}

		$go_live_url = apply_filters('wu_demo_go_live_url', $go_live_url, $site);

		// Parent node: "Demo Mode" label.
		$wp_admin_bar->add_node(
			[
				'id'    => 'wu-demo-mode',
				'title' => '<span style="color: #f0b849; font-weight: 600;">&#9733; ' . esc_html__('Demo Mode', 'ultimate-multisite') . '</span>',
				'href'  => false,
				'meta'  => [
					'title' => __('This site is in demo mode. The frontend is not visible to visitors.', 'ultimate-multisite'),
				],
			]
		);

		// Child node: "Go Live" link.
		$wp_admin_bar->add_node(
			[
				'parent' => 'wu-demo-mode',
				'id'     => 'wu-demo-go-live',
				'title'  => esc_html__('Go Live &rarr;', 'ultimate-multisite'),
				'href'   => esc_url($go_live_url),
				'meta'   => [
					'title' => __('Activate your site and make it visible to visitors.', 'ultimate-multisite'),
				],
			]
		);

		// Child node: informational note.
		$wp_admin_bar->add_node(
			[
				'parent' => 'wu-demo-mode',
				'id'     => 'wu-demo-mode-info',
				'title'  => esc_html__('Visitors cannot see this site yet.', 'ultimate-multisite'),
				'href'   => false,
			]
		);
	}

	/**
	 * Handle the "go live" direct action when no external URL is configured.
	 *
	 * Listens for `?wu_go_live=SITE_ID` on the frontend. Verifies the nonce,
	 * checks permissions, and converts the demo site to a customer-owned site.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function handle_go_live_action(): void {

		$site_id = wu_request('wu_go_live');

		if ( ! $site_id) {
			return;
		}

		$site_id = absint($site_id);

		if ( ! wp_verify_nonce(wu_request('_wpnonce'), 'wu_go_live_' . $site_id)) {
			wp_die(esc_html__('Security check failed. Please try again.', 'ultimate-multisite'));
		}

		if ( ! current_user_can('manage_options') && ! is_super_admin()) {
			wp_die(esc_html__('You do not have permission to activate this site.', 'ultimate-multisite'));
		}

		$result = $this->convert_demo_to_live($site_id);

		if (is_wp_error($result)) {
			wp_die(esc_html($result->get_error_message()));
		}

		// Redirect back to the home page without the query arg.
		wp_safe_redirect(remove_query_arg(['wu_go_live', '_wpnonce']));

		exit;
	}

	/**
	 * Convert a keep-until-live demo site to a fully live customer-owned site.
	 *
	 * Changes the site type from DEMO to CUSTOMER_OWNED, clears demo meta,
	 * and fires before/after hooks for extensibility.
	 *
	 * @since 2.5.0
	 *
	 * @param int $site_id The WP Ultimo site ID.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public function convert_demo_to_live(int $site_id) {

		$site = wu_get_site($site_id);

		if ( ! $site) {
			return new \WP_Error('site_not_found', __('Demo site not found.', 'ultimate-multisite'));
		}

		if ( ! $site->is_keep_until_live()) {
			return new \WP_Error('not_demo_site', __('This site is not a keep-until-live demo site.', 'ultimate-multisite'));
		}

		/**
		 * Fires before a keep-until-live demo site is converted to live.
		 *
		 * @since 2.5.0
		 *
		 * @param \WP_Ultimo\Models\Site $site The site being converted.
		 */
		do_action('wu_before_demo_site_converted', $site);

		// Convert site type from demo to customer-owned.
		$site->set_type(Site_Type::CUSTOMER_OWNED);

		// Clear demo-specific meta.
		$site->delete_meta(Site::META_DEMO_EXPIRES_AT);
		$site->delete_meta('wu_demo_expiring_notified');

		$saved = $site->save();

		if ( ! $saved) {
			return new \WP_Error('save_failed', __('Failed to activate the demo site. Please try again.', 'ultimate-multisite'));
		}

		wu_log_add(
			'demo-sites',
			sprintf(
				// translators: %d is the site ID.
				__('Demo site #%d converted to live (customer-owned).', 'ultimate-multisite'),
				$site_id
			)
		);

		/**
		 * Fires after a keep-until-live demo site has been successfully converted to live.
		 *
		 * @since 2.5.0
		 *
		 * @param \WP_Ultimo\Models\Site $site The site that was converted.
		 */
		do_action('wu_after_demo_site_converted', $site);

		return true;
	}
}
