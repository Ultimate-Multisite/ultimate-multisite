<?php
/**
 * Handles limitations to site template selection.
 *
 * @package WP_Ultimo
 * @subpackage Limits
 * @since 2.0.0
 */

namespace WP_Ultimo\Limits;

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

		add_action( 'plugins_loaded', array( $this, 'setup' ) );
	}

	/**
	 * Sets up the hooks and checks.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function setup(): void {

		add_filter( 'wu_template_selection_render_attributes', array( $this, 'maybe_filter_template_selection_options' ) );

		add_filter( 'wu_checkout_template_id', array( $this, 'maybe_force_template_selection' ), 10, 2 );

		add_filter( 'wu_cart_get_extra_params', array( $this, 'maybe_force_template_selection_on_cart' ), 10, 2 );

		add_action( 'wu_async_after_membership_update_products', array( $this, 'handle_downgrade' ) );
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
	public function handle_downgrade( $membership_id ): void {

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
	public function maybe_filter_template_selection_options( $attributes ) {

		$attributes['should_display'] = true;

		$products = array_map( 'wu_get_product', wu_get_isset( $attributes, 'products', array() ) );

		$products = array_filter( $products );

		if ( ! empty( $products ) ) {
			$limits = new \WP_Ultimo\Objects\Limitations();

			[$plan, $additional_products] = wu_segregate_products( $products );

			$products = array_filter( array_merge( array( $plan ), $additional_products ) );

			foreach ( $products as $product ) {
				$limits = $limits->merge( $product->get_limitations() );
			}

			if ( $limits->site_templates->get_mode() === Limit_Site_Templates::MODE_DEFAULT ) {
				$attributes['sites'] = wu_get_isset( $attributes, 'sites', explode( ',', ( $attributes['template_selection_sites'] ?? '' ) ) );

				return $attributes;
			} elseif ( $limits->site_templates->get_mode() === Limit_Site_Templates::MODE_ASSIGN_TEMPLATE ) {
				$attributes['should_display'] = false;
			} else {
				$site_list = wu_get_isset( $attributes, 'sites', explode( ',', ( $attributes['template_selection_sites'] ?? '' ) ) );

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
	 * @since 2.0.0
	 *
	 * @param int                          $template_id The current template id.
	 * @param \WP_Ultimo\Models\Membership $membership The membership object.
	 * @return int
	 */
	public function maybe_force_template_selection( $template_id, $membership ) {

		if ( $membership && Limit_Site_Templates::MODE_ASSIGN_TEMPLATE === $membership->get_limitations()->site_templates->get_mode() ) {
			$template_id = $membership->get_limitations()->site_templates->get_pre_selected_site_template();
		}

		return $template_id;
	}

	/**
	 * Pre-selects a given template on the checkout screen depending on permissions.
	 *
	 * @since 2.0.0
	 *
	 * @param array                    $extra List if extra elements.
	 * @param \WP_Ultimo\Checkout\Cart $cart The cart object.
	 * @return array
	 */
	public function maybe_force_template_selection_on_cart( $extra, $cart ) {

		$limits = new \WP_Ultimo\Objects\Limitations();

		$products = $cart->get_all_products();

		[$plan, $additional_products] = wu_segregate_products( $products );

		$products = array_merge( array( $plan ), $additional_products );

		$products = array_filter( $products );

		foreach ( $products as $product ) {
			$limits = $limits->merge( $product->get_limitations() );
		}

		if ( $limits->site_templates->get_mode() === Limit_Site_Templates::MODE_ASSIGN_TEMPLATE ) {
			$extra['template_id'] = $limits->site_templates->get_pre_selected_site_template();
		} elseif ( $limits->site_templates->get_mode() === Limit_Site_Templates::MODE_CHOOSE_AVAILABLE_TEMPLATES ) {
			$template_id = Checkout::get_instance()->request_or_session( 'template_id' );

			$extra['template_id'] = $this->is_template_available( $products, $template_id ) ? $template_id : false;
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
	protected function is_template_available( $products, $template_id ) {

		$template_id = (int) $template_id;

		if ( ! empty( $products ) ) {
			$limits = new \WP_Ultimo\Objects\Limitations();

			[$plan, $additional_products] = wu_segregate_products( $products );

			$products = array_filter( array_merge( array( $plan ), $additional_products ) );

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
}
