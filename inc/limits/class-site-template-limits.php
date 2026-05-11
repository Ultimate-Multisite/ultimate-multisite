<?php
/**
 * Handles limitations to site template selection.
 *
 * @package WP_Ultimo
 * @subpackage Limits
 * @since 2.0.0
 */

namespace WP_Ultimo\Limits;

use Psr\Log\LogLevel;
use WP_Ultimo\Checkout\Checkout;
use WP_Ultimo\Limitations\Limit_Site_Templates;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Handles limitations to site template selection.
 *
 * @since 2.0.0
 */
class Site_Template_Limits {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * Runs on the first and only instantiation.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function init(): void {

		add_action( 'plugins_loaded', array($this, 'setup') );
	}

	/**
	 * Sets up the hooks and checks.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function setup(): void {

		add_filter( 'wu_template_selection_render_attributes', array($this, 'maybe_filter_template_selection_options') );

		add_filter( 'wu_checkout_template_id', array($this, 'maybe_force_template_selection'), 10, 2 );

		add_filter( 'wu_cart_get_extra_params', array($this, 'maybe_force_template_selection_on_cart'), 10, 2 );

		add_action( 'wu_async_after_membership_update_products', array($this, 'handle_downgrade') );
	}

	/**
	 * Handles site template enforcement after a membership product change (upgrade/downgrade).
	 *
	 * Site template restrictions apply only at site-creation time: the template is a
	 * one-time choice made during checkout and cannot be meaningfully "reverted" after
	 * the site already exists. Therefore this handler is intentionally a no-op for
	 * existing sites.
	 *
	 * The hook is registered so that:
	 * 1. Developers can hook into `wu_site_template_downgrade` to add custom behaviour.
	 * 2. Future versions can implement additional enforcement (e.g. blocking new site
	 *    creation with a disallowed template) without changing the hook registration.
	 *
	 * @since 2.2.0
	 *
	 * @param int $membership_id The membership that was updated.
	 * @return void
	 */
	public function handle_downgrade($membership_id): void {

		$membership = wu_get_membership( $membership_id );

		if ( ! $membership ) {
			return;
		}

		/**
		 * Fires after a membership product change when site template limits may have changed.
		 *
		 * Template restrictions only apply at site-creation time, so no existing sites are
		 * modified. Use this action to add custom enforcement if needed.
		 *
		 * @since 2.2.0
		 *
		 * @param int                          $membership_id The membership ID.
		 * @param \WP_Ultimo\Models\Membership $membership    The membership object.
		 */
		do_action( 'wu_site_template_downgrade', $membership_id, $membership );
	}

	/**
	 * Maybe filter the template selection options on the template selection field.
	 *
	 * @since 2.0.0
	 *
	 * @param array $attributes The template rendering attributes.
	 * @return array
	 */
	public function maybe_filter_template_selection_options($attributes) {

		$attributes['should_display'] = true;

		$products = array_map( 'wu_get_product', wu_get_isset( $attributes, 'products', array() ) );

		$products = array_filter( $products );

		if ( ! empty( $products ) ) {
			$limits = new \WP_Ultimo\Objects\Limitations();

			[$plan, $additional_products] = wu_segregate_products( $products );

			$products = array_filter( array_merge( array($plan), $additional_products ) );

			foreach ( $products as $product ) {
				$limits = $limits->merge( $product->get_limitations() );
			}

			if ( $limits->site_templates->get_mode() === Limit_Site_Templates::MODE_DEFAULT ) {
				$attributes['sites'] = wu_get_isset( $attributes, 'sites', explode( ',', ($attributes['template_selection_sites'] ?? '') ) );

				return $attributes;
			} elseif ( $limits->site_templates->get_mode() === Limit_Site_Templates::MODE_ASSIGN_TEMPLATE ) {
				$attributes['should_display'] = false;
			} else {
				$site_list = wu_get_isset( $attributes, 'sites', explode( ',', ($attributes['template_selection_sites'] ?? '') ) );

				// Ensure consistent type comparison by casting to integers.
				$site_list           = array_map( 'intval', $site_list );
				$available_templates = array_map( 'intval', $limits->site_templates->get_available_site_templates() );

				$attributes['sites'] = array_values( array_intersect( $site_list, $available_templates ) );
			}
		}

		return $attributes;
	}

	/**
	 * Decides if we need to force the selection of a given template during the site creation.
	 *
	 * Implements the full fallback chain so that a product with Allow Site Templates
	 * enabled always results in a cloned site, never default WordPress content:
	 *
	 * 1. Customer-supplied template_id (kept as-is when > 0).
	 * 2. MODE_ASSIGN_TEMPLATE: assigned template always wins, customer pick is ignored.
	 * 3. MODE_CHOOSE_AVAILABLE_TEMPLATES + BEHAVIOR_PRE_SELECTED: use pre-selected.
	 * 4. Oldest active site template the product allows (global fallback).
	 *
	 * When Allow Site Templates is off, the caller's value is returned unchanged.
	 *
	 * @since 2.0.0
	 * @since 2.2.1 Added fallback chain for steps 3 and 4.
	 *
	 * @param int                          $template_id The current template id.
	 * @param \WP_Ultimo\Models\Membership $membership The membership object.
	 * @return int
	 */
	public function maybe_force_template_selection($template_id, $membership) {

		$template_id = (int) $template_id;

		if ( ! $membership instanceof \WP_Ultimo\Models\Membership ) {
			return $template_id;
		}

		$limit = $membership->get_limitations()->site_templates;

		// Allow Site Templates toggle off — leave whatever the caller had.
		if ( ! $limit->is_enabled() ) {
			return $template_id;
		}

		// Existing behaviour: assign-template mode always wins, customer pick is ignored.
		if ( Limit_Site_Templates::MODE_ASSIGN_TEMPLATE === $limit->get_mode() ) {
			return (int) $limit->get_pre_selected_site_template();
		}

		// Customer already picked a valid template — keep it.
		if ( $template_id > 0 ) {
			return $template_id;
		}

		// No customer pick — try pre-selected (only meaningful when mode is choose-available).
		if ( Limit_Site_Templates::MODE_CHOOSE_AVAILABLE_TEMPLATES === $limit->get_mode() ) {
			$pre_selected = (int) $limit->get_pre_selected_site_template();

			if ( $pre_selected > 0 ) {
				return $pre_selected;
			}
		}

		// Final fallback: oldest active template the product allows.
		return $this->resolve_default_template_id( $limit );
	}

	/**
	 * Pre-selects a given template on the checkout screen depending on permissions.
	 *
	 * Mirrors the fallback chain of maybe_force_template_selection() so that the
	 * cart preview always reflects the template that will actually be used when
	 * the site is created.
	 *
	 * @since 2.0.0
	 * @since 2.2.1 Added pre-selected and oldest-active-template fallbacks.
	 *
	 * @param array                    $extra List of extra elements.
	 * @param \WP_Ultimo\Checkout\Cart $cart The cart object.
	 * @return array
	 */
	public function maybe_force_template_selection_on_cart($extra, $cart) {

		$limits = new \WP_Ultimo\Objects\Limitations();

		$products = $cart->get_all_products();

		[$plan, $additional_products] = wu_segregate_products( $products );

		$products = array_merge( array($plan), $additional_products );

		$products = array_filter( $products );

		foreach ( $products as $product ) {
			$limits = $limits->merge( $product->get_limitations() );
		}

		$site_templates_limit = $limits->site_templates;

		// Allow Site Templates toggle off — do not inject a template_id.
		if ( ! $site_templates_limit->is_enabled() ) {
			return $extra;
		}

		// Assign-template mode: the assigned template always wins.
		if ( Limit_Site_Templates::MODE_ASSIGN_TEMPLATE === $site_templates_limit->get_mode() ) {
			$extra['template_id'] = $site_templates_limit->get_pre_selected_site_template();

			return $extra;
		}

		$template_id = (int) Checkout::get_instance()->request_or_session( 'template_id' );

		if ( Limit_Site_Templates::MODE_CHOOSE_AVAILABLE_TEMPLATES === $site_templates_limit->get_mode() ) {
			if ( $template_id > 0 && $this->is_template_available( $products, $template_id ) ) {
				$extra['template_id'] = $template_id;

				return $extra;
			}

			// No valid customer pick — try pre-selected.
			$pre_selected = (int) $site_templates_limit->get_pre_selected_site_template();

			if ( $pre_selected > 0 ) {
				$extra['template_id'] = $pre_selected;

				return $extra;
			}
		} elseif ( $template_id > 0 ) {
			// MODE_DEFAULT: customer picked something — use it (all templates are allowed).
			$extra['template_id'] = $template_id;

			return $extra;
		}

		// Final fallback: oldest active template (mirrors maybe_force_template_selection behaviour).
		$fallback = $this->resolve_default_template_id( $site_templates_limit );

		if ( $fallback > 0 ) {
			$extra['template_id'] = $fallback;
		}

		return $extra;
	}

	/**
	 * Check if site template is available in current limits
	 *
	 * @param array $products    the list of products to check for limit.
	 * @param int   $template_id the site template id.
	 * @return boolean
	 */
	protected function is_template_available($products, $template_id) {

		$template_id = (int) $template_id;

		if ( ! empty( $products ) ) {
			$limits = new \WP_Ultimo\Objects\Limitations();

			[$plan, $additional_products] = wu_segregate_products( $products );

			$products = array_filter( array_merge( array($plan), $additional_products ) );

			foreach ( $products as $product ) {
				$limits = $limits->merge( $product->get_limitations() );
			}

			if ( $limits->site_templates->get_mode() === Limit_Site_Templates::MODE_ASSIGN_TEMPLATE ) {
				return $limits->site_templates->get_pre_selected_site_template() === $template_id;
			} else {
				$available_templates = $limits->site_templates->get_available_site_templates();

				// false means no restriction (MODE_DEFAULT) — all templates are available.
				if ( false === $available_templates ) {
					return true;
				}

				return in_array( $template_id, $available_templates, true );
			}
		}

		return true;
	}

	/**
	 * Resolves the oldest active site template for the given limitations.
	 *
	 * Used as the final fallback when the customer did not pick a template and no
	 * pre-selected template is configured. Queries all site templates ordered by
	 * blog_id ASC and returns the first one that is active, not archived, not
	 * deleted, and not spam.
	 *
	 * When the limit mode is MODE_CHOOSE_AVAILABLE_TEMPLATES the pool is first
	 * restricted to the templates explicitly allowed by the product. Only if that
	 * restricted subset yields no usable candidate does the method fall through to
	 * the unrestricted global pool.
	 *
	 * @since 2.2.1
	 *
	 * @param Limit_Site_Templates $limit The site-templates limitation object.
	 * @return int Template blog_id, or 0 when no usable template exists.
	 */
	protected function resolve_default_template_id(Limit_Site_Templates $limit) {

		// Fetch all site templates ordered oldest-first.
		$all_templates = wu_get_site_templates(
			array(
				'orderby' => 'blog_id',
				'order'   => 'ASC',
				'number'  => 9999,
			)
		);

		/**
		 * Build the candidate pool.
		 *
		 * For MODE_CHOOSE_AVAILABLE_TEMPLATES, attempt to restrict the pool to the
		 * templates explicitly listed on the product first. Fall through to the
		 * unrestricted global pool only when that restricted subset is empty.
		 */
		$candidates = $all_templates;

		if ( Limit_Site_Templates::MODE_CHOOSE_AVAILABLE_TEMPLATES === $limit->get_mode() ) {
			$available_ids = $limit->get_available_site_templates();

			if ( ! empty( $available_ids ) ) {
				$available_ids = array_map( 'intval', (array) $available_ids );

				$restricted = array_filter(
					$all_templates,
					function ($template) use ($available_ids) {
						return in_array( (int) $template->get_id(), $available_ids, true );
					}
				);

				if ( ! empty( $restricted ) ) {
					$candidates = $restricted;
				}
			}
			// If $restricted is empty or $available_ids is empty, fall through to $candidates = $all_templates.
		}

		// Return the first candidate that passes all usability checks.
		foreach ( $candidates as $template ) {
			if ( $template->is_active() && ! $template->is_archived() && ! $template->is_deleted() && ! $template->is_spam() ) {
				return (int) $template->get_id();
			}
		}

		// No usable template found — log for diagnostics and return 0 (default WP site).
		wu_log_add(
			'site-duplication',
			__( 'No usable site template found for fallback resolution. Checkout will proceed with default WordPress content.', 'ultimate-multisite' ),
			LogLevel::WARNING
		);

		return 0;
	}
}
