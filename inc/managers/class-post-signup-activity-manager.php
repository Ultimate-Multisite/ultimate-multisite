<?php
/**
 * Post-Signup Activity Manager
 *
 * Tracks actions taken by subsite users after signup:
 * post creation, custom post type creation, user registration,
 * and WooCommerce orders. Events are linked to the originating
 * membership/subsite and viewable by the network admin on the
 * site edit page's Events widget.
 *
 * @package WP_Ultimo
 * @subpackage Managers
 * @since 2.5.0
 */

namespace WP_Ultimo\Managers;

use WP_Ultimo\Models\Event;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Tracks post-signup activity on customer subsites.
 *
 * @since 2.5.0
 */
class Post_Signup_Activity_Manager {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * Post types that are excluded from tracking (internal WP types).
	 *
	 * @since 2.5.0
	 * @var array
	 */
	const EXCLUDED_POST_TYPES = [
		'revision',
		'auto-draft',
		'nav_menu_item',
		'customize_changeset',
		'oembed_cache',
		'user_request',
		'wp_block',
		'wp_template',
		'wp_template_part',
		'wp_global_styles',
		'wp_navigation',
		'wp_font_family',
		'wp_font_face',
	];

	/**
	 * Registers hooks.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function init(): void {

		// Register event types after the event manager has set up.
		add_action('wu_register_all_events', [$this, 'register_event_types']);

		// Only hook subsite activity on non-main sites.
		add_action('plugins_loaded', [$this, 'register_subsite_hooks'], 20);
	}

	/**
	 * Registers the four post-signup event types.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function register_event_types(): void {

		wu_register_event_type(
			'subsite_post_created',
			[
				'name'            => __('Subsite Post Created', 'ultimate-multisite'),
				'desc'            => __('Fired when a customer publishes a new post on their subsite.', 'ultimate-multisite'),
				'payload'         => fn() => array_merge(
					[
						'post_id'        => 1,
						'post_title'     => 'Example Post',
						'post_type'      => 'post',
						'post_status'    => 'publish',
						'post_author_id' => 1,
					],
					wu_generate_event_payload('site'),
					wu_generate_event_payload('membership')
				),
				'deprecated_args' => [],
			]
		);

		wu_register_event_type(
			'subsite_cpt_created',
			[
				'name'            => __('Subsite Custom Post Type Entry Created', 'ultimate-multisite'),
				'desc'            => __('Fired when a customer creates a new entry of a custom post type on their subsite.', 'ultimate-multisite'),
				'payload'         => fn() => array_merge(
					[
						'post_id'        => 1,
						'post_title'     => 'Example CPT Entry',
						'post_type'      => 'product',
						'post_status'    => 'publish',
						'post_author_id' => 1,
					],
					wu_generate_event_payload('site'),
					wu_generate_event_payload('membership')
				),
				'deprecated_args' => [],
			]
		);

		wu_register_event_type(
			'subsite_user_registered',
			[
				'name'            => __('Subsite User Registered', 'ultimate-multisite'),
				'desc'            => __('Fired when a new user is added to a customer subsite.', 'ultimate-multisite'),
				'payload'         => fn() => array_merge(
					[
						'new_user_id'    => 1,
						'new_user_login' => 'example_user',
						'new_user_email' => 'user@example.com',
						'new_user_role'  => 'subscriber',
					],
					wu_generate_event_payload('site'),
					wu_generate_event_payload('membership')
				),
				'deprecated_args' => [],
			]
		);

		wu_register_event_type(
			'subsite_woocommerce_order',
			[
				'name'            => __('Subsite WooCommerce Order Placed', 'ultimate-multisite'),
				'desc'            => __('Fired when a WooCommerce order is created on a customer subsite.', 'ultimate-multisite'),
				'payload'         => fn() => array_merge(
					[
						'order_id'     => 1,
						'order_total'  => '99.00',
						'order_status' => 'pending',
						'order_items'  => 1,
					],
					wu_generate_event_payload('site'),
					wu_generate_event_payload('membership')
				),
				'deprecated_args' => [],
			]
		);
	}

	/**
	 * Registers hooks that run on customer subsites.
	 *
	 * Only registers on non-main sites that are WU-managed.
	 *
	 * @since 2.5.0
	 * @return void
	 */
	public function register_subsite_hooks(): void {

		// Skip the main site — we only track customer subsites.
		if (is_main_site()) {
			return;
		}

		// Track post/CPT creation (fires on publish transition).
		add_action('transition_post_status', [$this, 'track_post_published'], 10, 3);

		// Track new user added to this subsite.
		add_action('add_user_to_blog', [$this, 'track_user_added_to_blog'], 10, 3);

		// Track WooCommerce orders (fires when a new order is created).
		add_action('woocommerce_new_order', [$this, 'track_woocommerce_order'], 10, 2);
	}

	/**
	 * Tracks when a post transitions to "publish" status for the first time.
	 *
	 * @since 2.5.0
	 *
	 * @param string        $new_status The new post status.
	 * @param string        $old_status The old post status.
	 * @param \WP_Post|null $post       The post object, or null when WordPress failed to create it.
	 * @return void
	 */
	public function track_post_published(string $new_status, string $old_status, ?\WP_Post $post): void {

		// Only fire on the first publish transition.
		if ('publish' !== $new_status || 'publish' === $old_status) {
			return;
		}

		if ( ! $post) {
			return;
		}

		// Skip excluded/internal post types.
		if (in_array($post->post_type, self::EXCLUDED_POST_TYPES, true)) {
			return;
		}

		$blog_id = get_current_blog_id();
		$wu_site = $this->get_wu_site($blog_id);

		if ( ! $wu_site) {
			return;
		}

		$is_cpt = 'post' !== $post->post_type && 'page' !== $post->post_type;
		$slug   = $is_cpt ? 'subsite_cpt_created' : 'subsite_post_created';

		$payload = $this->build_site_membership_payload($wu_site);
		$payload = array_merge(
			$payload,
			[
				'post_id'        => $post->ID,
				'post_title'     => $post->post_title,
				'post_type'      => $post->post_type,
				'post_status'    => $post->post_status,
				'post_author_id' => (int) $post->post_author,
				'object_type'    => 'site',
				'object_id'      => $wu_site->get_id(),
			]
		);

		wu_create_event(
			[
				'severity'    => Event::SEVERITY_INFO,
				'slug'        => $slug,
				'initiator'   => 'system',
				'object_type' => 'site',
				'object_id'   => $wu_site->get_id(),
				'payload'     => $payload,
			]
		);
	}

	/**
	 * Tracks when a user is added to a subsite.
	 *
	 * @since 2.5.0
	 *
	 * @param int    $user_id The user ID being added.
	 * @param string $role    The role assigned.
	 * @param int    $blog_id The blog ID the user is being added to.
	 * @return void
	 */
	public function track_user_added_to_blog(int $user_id, string $role, int $blog_id): void {

		$wu_site = $this->get_wu_site($blog_id);

		if ( ! $wu_site) {
			return;
		}

		$user = get_userdata($user_id);

		$payload = $this->build_site_membership_payload($wu_site);
		$payload = array_merge(
			$payload,
			[
				'new_user_id'    => $user_id,
				'new_user_login' => $user ? $user->user_login : '',
				'new_user_email' => $user ? $user->user_email : '',
				'new_user_role'  => $role,
				'object_type'    => 'site',
				'object_id'      => $wu_site->get_id(),
			]
		);

		wu_create_event(
			[
				'severity'    => Event::SEVERITY_INFO,
				'slug'        => 'subsite_user_registered',
				'initiator'   => 'system',
				'object_type' => 'site',
				'object_id'   => $wu_site->get_id(),
				'payload'     => $payload,
			]
		);
	}

	/**
	 * Tracks when a WooCommerce order is created on a subsite.
	 *
	 * @since 2.5.0
	 *
	 * @param int       $order_id The WooCommerce order ID.
	 * @param \WC_Order $order    The WooCommerce order object.
	 * @return void
	 */
	public function track_woocommerce_order(int $order_id, $order): void {

		$blog_id = get_current_blog_id();
		$wu_site = $this->get_wu_site($blog_id);

		if ( ! $wu_site) {
			return;
		}

		$order_total  = $order ? $order->get_total() : 0;
		$order_status = $order ? $order->get_status() : '';
		$order_items  = $order ? count($order->get_items()) : 0;

		$payload = $this->build_site_membership_payload($wu_site);
		$payload = array_merge(
			$payload,
			[
				'order_id'     => $order_id,
				'order_total'  => (string) $order_total,
				'order_status' => $order_status,
				'order_items'  => $order_items,
				'object_type'  => 'site',
				'object_id'    => $wu_site->get_id(),
			]
		);

		wu_create_event(
			[
				'severity'    => Event::SEVERITY_INFO,
				'slug'        => 'subsite_woocommerce_order',
				'initiator'   => 'system',
				'object_type' => 'site',
				'object_id'   => $wu_site->get_id(),
				'payload'     => $payload,
			]
		);
	}

	/**
	 * Looks up the WU site object for a given WordPress blog ID.
	 *
	 * Returns false if the blog is not a WU-managed customer site.
	 *
	 * @since 2.5.0
	 *
	 * @param int $blog_id The WordPress blog ID.
	 * @return \WP_Ultimo\Models\Site|false
	 */
	protected function get_wu_site(int $blog_id) {

		if ( ! function_exists('wu_get_site')) {
			return false;
		}

		$wu_site = wu_get_site($blog_id);

		if ( ! $wu_site || ! $wu_site->get_membership_id()) {
			return false;
		}

		return $wu_site;
	}

	/**
	 * Builds the site and membership payload arrays for an event.
	 *
	 * @since 2.5.0
	 *
	 * @param \WP_Ultimo\Models\Site $wu_site The WU site object.
	 * @return array
	 */
	protected function build_site_membership_payload($wu_site): array {

		$payload = wu_generate_event_payload('site', $wu_site);

		$membership = $wu_site->get_membership();

		if ($membership) {
			$payload = array_merge($payload, wu_generate_event_payload('membership', $membership));
		}

		return $payload;
	}
}
