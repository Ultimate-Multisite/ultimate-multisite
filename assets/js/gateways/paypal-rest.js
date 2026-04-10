/**
 * PayPal REST Gateway - Checkout Branding.
 *
 * Styles the checkout submit button with official PayPal branding
 * when PayPal is the active payment method.
 *
 * Per PayPal integration guidelines, PayPal buttons must be presented
 * with equal prominence and official logos from approved sources.
 */
/* global wp */
( function () {

	'use strict';

	const GATEWAY_ID = 'paypal-rest';
	const STYLE_ID = 'wu-paypal-btn-style';
	const ICON_CLASS = 'wu-paypal-btn-icon';

	// Small PayPal "P" favicon from PayPal's CDN — square, won't overlap text.
	const ICON_URL = 'https://www.paypalobjects.com/webstatic/icon/pp258.png';

	/**
	 * Inject CSS for PayPal button colours.
	 * Using a <style> block so colours survive Vue re-renders (spinner swaps).
	 *
	 * @param {boolean} active
	 */
	function setButtonColors( active ) {
		const existing = document.getElementById( STYLE_ID );

		if ( active && ! existing ) {
			const style = document.createElement( 'style' );
			style.id = STYLE_ID;
			style.textContent = [
				'#wu_form [type="submit"].button {',
				'  background-color: #FFC439 !important;',
				'  color: #003087 !important;',
				'  border-color: #FFC439 !important;',
				'  font-weight: 700 !important;',
				'}',
				'#wu_form [type="submit"].button:hover,',
				'#wu_form [type="submit"].button:focus {',
				'  background-color: #f0b429 !important;',
				'  border-color: #f0b429 !important;',
				'}',
				'#wu_form [type="submit"].button .' + ICON_CLASS + ' {',
				'  display: inline-block;',
				'  width: 20px;',
				'  height: 20px;',
				'  vertical-align: middle;',
				'  margin-right: 8px;',
				'  margin-bottom: 2px;',
				'  object-fit: contain;',
				'}',
			].join( '\n' );
			document.head.appendChild( style );
		} else if ( ! active && existing ) {
			existing.remove();
		}
	}

	/**
	 * Add or remove the PayPal icon inside each submit button.
	 *
	 * @param {boolean} active
	 */
	function setButtonIcons( active ) {
		const form = document.getElementById( 'wu_form' );
		if ( ! form ) {
			return;
		}

		const buttons = form.querySelectorAll( '[type="submit"].button' );

		buttons.forEach( function ( btn ) {
			const existing = btn.querySelector( '.' + ICON_CLASS );

			if ( active && ! existing ) {
				const img = document.createElement( 'img' );
				img.src = ICON_URL;
				img.alt = '';
				img.className = ICON_CLASS;
				img.setAttribute( 'aria-hidden', 'true' );
				img.setAttribute( 'loading', 'lazy' );
				btn.prepend( img );
			} else if ( ! active && existing ) {
				existing.remove();
			}
		} );
	}

	/**
	 * Apply or remove all PayPal button branding.
	 *
	 * @param {boolean} active
	 */
	function applyBranding( active ) {
		setButtonColors( active );
		setButtonIcons( active );
	}

	/**
	 * Check whether the current order actually requires payment.
	 *
	 * When the cart total is zero (free plan, 100 % discount, etc.)
	 * the checkout hides the payment-method selector and the server
	 * will route the signup through the free gateway — so the submit
	 * button should NOT carry PayPal branding.
	 *
	 * @return {boolean} True when the order needs payment collection.
	 */
	function orderRequiresPayment() {
		const checkout = window.wu_checkout_form;

		if ( ! checkout || ! checkout.order ) {
			// Order not loaded yet — assume payment is needed so branding
			// can be applied once the order confirms it.
			return true;
		}

		return !! checkout.order.should_collect_payment;
	}

	/**
	 * Re-apply icon after Vue re-renders the button (e.g. spinner swap).
	 * The CSS survives re-renders; only the icon DOM node needs re-injection.
	 */
	( function watchForRerender() {
		let isPayPalSelected = false;

		const observer = new MutationObserver( function () {
			if ( isPayPalSelected && orderRequiresPayment() ) {
				setButtonIcons( true );
			}
		} );

		/**
		 * Recalculate whether branding should be shown.
		 *
		 * Branding is active only when PayPal is the selected gateway
		 * AND the order actually requires payment collection.
		 */
		function refreshBranding() {
			const shouldBrand = isPayPalSelected && orderRequiresPayment();
			applyBranding( shouldBrand );

			const form = document.getElementById( 'wu_form' );
			if ( form ) {
				if ( shouldBrand ) {
					observer.observe( form, { childList: true, subtree: true } );
				} else {
					observer.disconnect();
				}
			}
		}

		wp.hooks.addAction(
			'wu_on_change_gateway',
			'wp-ultimo/paypal-rest',
			function ( newGateway ) {
				isPayPalSelected = ( newGateway === GATEWAY_ID );
				refreshBranding();
			}
		);

		/*
		 * When the order updates (product change, coupon applied, etc.)
		 * the should_collect_payment flag may flip — re-evaluate branding.
		 */
		wp.hooks.addAction(
			'wu_on_form_updated',
			'wp-ultimo/paypal-rest',
			function () {
				refreshBranding();
			}
		);
	}() );

}() );
