/**
 * Stripe Gateway - Payment Element Only.
 *
 * Uses Stripe Payment Element with deferred intent mode for immediate rendering
 * without requiring a client_secret upfront.
 */
/* eslint-disable */
/* global wu_stripe, Stripe */
let _stripe;
let paymentElement;
let elements;
let currentElementsMode = null;
let currentElementsAmount = null;

/**
 * Check if the current checkout should collect Stripe details.
 *
 * @param {Object} checkout Checkout form instance.
 * @return {boolean} True when Stripe should collect a new payment method.
 */
const shouldCollectStripePayment = function (checkout) {

	return checkout &&
		checkout.order &&
		checkout.order.should_collect_payment &&
		checkout.payment_method === 'add-new';
};

/**
 * Check if Stripe should confirm a previously saved payment method.
 *
 * @param {Object} checkout Checkout form instance.
 * @return {boolean} True when using a saved Stripe payment method.
 */
const shouldConfirmSavedStripePayment = function (checkout) {

	return checkout &&
		checkout.order &&
		checkout.order.should_collect_payment &&
		checkout.payment_method &&
		checkout.payment_method !== 'add-new' &&
		(
			checkout.order.totals.total > 0 ||
			checkout.order.totals.recurring.total > 0
		);
};

/**
 * Unmount the current Payment Element instance.
 */
const resetStripeElements = function () {

	if (paymentElement) {
		try {
			paymentElement.unmount();
		} catch (error) {
			// Silence
		}
	}

	paymentElement = null;
	elements = null;
	currentElementsMode = null;
	currentElementsAmount = null;
};

/**
 * Confirm a Stripe intent returned by the checkout endpoint.
 *
 * @param {Object}  checkout Checkout form instance.
 * @param {Object}  results Checkout response payload.
 * @param {boolean} useElements Whether to confirm through the Payment Element.
 */
const confirmStripeIntent = function (checkout, results, useElements) {

	// Check if we received a client_secret from the server
	if (!results.gateway.data.stripe_client_secret) {
		checkout.set_prevent_submission(false);
		return;
	}

	const clientSecret = results.gateway.data.stripe_client_secret;
	const intentType = results.gateway.data.stripe_intent_type;

	checkout.set_prevent_submission(false);

	// Determine the confirmation method based on intent type
	const confirmMethod = intentType === 'payment_intent'
		? 'confirmPayment'
		: 'confirmSetup';

	const confirmParams = {
		confirmParams: {
			return_url: window.location.href,
		},
		redirect: 'if_required',
	};

	if (useElements) {
		confirmParams.elements = elements;

		// Only pass name and email — Stripe's Payment Element
		// already collects country and postal code natively.
		confirmParams.confirmParams.payment_method_data = {
			billing_details: {
				name: results.customer.display_name,
				email: results.customer.user_email,
			},
		};
	}

	// Add clientSecret for confirmation
	confirmParams.clientSecret = clientSecret;

	_stripe[confirmMethod](confirmParams).then(function (result) {

		if (result.error) {
			wu_checkout_form.unblock();
			wu_checkout_form.errors.push(result.error);
		} else {
			// Payment succeeded - resubmit form to complete checkout
			wu_checkout_form.resubmit();
		}

	});
};

/**
 * Initialize Stripe and set up Payment Element.
 *
 * @param {string} publicKey Stripe publishable key.
 */
const stripeElements = function (publicKey) {

	_stripe = Stripe(publicKey);

	/**
	 * Filter to validate payment before form submission.
	 */
	wp.hooks.addFilter(
		'wu_before_form_submitted',
		'nextpress/wp-ultimo',
		function (promises, checkout, gateway) {

			if (gateway === 'stripe' && shouldCollectStripePayment(checkout)) {

				const paymentEl = document.getElementById('payment-element');

				if (paymentEl && elements) {
					promises.push(
						new Promise(async (resolve, reject) => {
							try {
								// Validate the Payment Element before submission
								const { error } = await elements.submit();

								if (error) {
									reject(error);
								} else {
									resolve();
								}
							} catch (err) {
								reject(err);
							}
						})
					);
				}
			}

			return promises;
		}
	);

	/**
	 * Handle successful form submission - confirm payment with client_secret.
	 */
	wp.hooks.addAction(
		'wu_on_form_success',
		'nextpress/wp-ultimo',
		function (checkout, results) {

			if (checkout.gateway !== 'stripe') {
				return;
			}

			if (!shouldCollectStripePayment(checkout)) {
				checkout.set_prevent_submission(false);
				resetStripeElements();

				if (shouldConfirmSavedStripePayment(checkout)) {
					confirmStripeIntent(checkout, results, false);
				}

				return;
			}

			if (checkout.order.totals.total <= 0 && checkout.order.totals.recurring.total <= 0) {
				return;
			}

			confirmStripeIntent(checkout, results, true);
		}
	);

	/**
	 * Initialize Payment Element on form update.
	 */
	wp.hooks.addAction('wu_on_form_updated', 'nextpress/wp-ultimo', function (form) {

		if (form.gateway !== 'stripe') {
			form.set_prevent_submission(false);

			// Destroy elements if switching away from Stripe
			resetStripeElements();

			return;
		}

		if (!shouldCollectStripePayment(form)) {
			form.set_prevent_submission(false);
			resetStripeElements();

			return;
		}

		const paymentEl = document.getElementById('payment-element');

		if (!paymentEl) {
			form.set_prevent_submission(false);
			return;
		}

		// Determine the correct mode based on order total
		// Use 'payment' mode when there's an immediate charge, 'setup' for trials/$0
		const orderTotal = form.order ? form.order.totals.total : 0;
		const hasImmediateCharge = orderTotal > 0;
		const requiredMode = hasImmediateCharge ? 'payment' : 'setup';

		// Convert amount to cents for Stripe (integer)
		const amountInCents = hasImmediateCharge ? Math.round(orderTotal * 100) : null;

		// Check if we need to reinitialize (mode or amount changed)
		const needsReinit = !elements ||
			!paymentElement ||
			currentElementsMode !== requiredMode ||
			(hasImmediateCharge && currentElementsAmount !== amountInCents);

		if (!needsReinit) {
			// Already initialized with correct mode, just update prevent submission state
			form.set_prevent_submission(shouldCollectStripePayment(form));
			return;
		}

		// Cleanup existing elements if reinitializing
		if (paymentElement) {
			try {
				paymentElement.unmount();
			} catch (error) {
				// Silence
			}
			paymentElement = null;
			elements = null;
		}

		try {
			// Build elements options based on mode
			const elementsOptions = {
				currency: wu_stripe.currency || 'usd',
				appearance: {
					theme: 'stripe',
				},
			};

			if (hasImmediateCharge) {
				// Payment mode - for immediate charges
				elementsOptions.mode = 'payment';
				elementsOptions.amount = amountInCents;
				// Match server-side PaymentIntent setup_future_usage for saving cards
				elementsOptions.setupFutureUsage = 'off_session';
			} else {
				// Setup mode - for trials or $0 orders
				elementsOptions.mode = 'setup';
			}

			elements = _stripe.elements(elementsOptions);

			// Store current mode and amount for comparison
			currentElementsMode = requiredMode;
			currentElementsAmount = amountInCents;

			// Create and mount Payment Element
			paymentElement = elements.create('payment', {
				layout: 'tabs',
			});

			paymentElement.mount('#payment-element');

			// Apply custom styles to match the checkout form
			wu_stripe_update_payment_element_styles('#field-payment_template');

			// Handle Payment Element errors
			paymentElement.on('change', function (event) {
				const errorEl = document.getElementById('payment-errors');

				if (errorEl) {
					if (event.error) {
						errorEl.textContent = event.error.message;
						errorEl.classList.add('wu-text-red-600', 'wu-text-sm', 'wu-mt-2');
					} else {
						errorEl.textContent = '';
					}
				}
			});

			// Set prevent submission until payment element is ready
			form.set_prevent_submission(shouldCollectStripePayment(form));

		} catch (error) {
			// Log error but don't break the form
			console.error('Stripe Payment Element initialization error:', error);
			form.set_prevent_submission(false);
		}
	});
};

/**
 * Initialize form data before checkout loads.
 */
wp.hooks.addFilter('wu_before_form_init', 'nextpress/wp-ultimo', function (data) {

	data.add_new_card = wu_stripe.add_new_card;
	data.payment_method = wu_stripe.payment_method;

	return data;
});

/**
 * Initialize Stripe when checkout loads.
 */
wp.hooks.addAction('wu_checkout_loaded', 'nextpress/wp-ultimo', function () {

	stripeElements(wu_stripe.pk_key);

});

/**
 * Update styles for Payment Element to match the checkout form.
 *
 * @param {string} selector Selector to copy styles from.
 */
function wu_stripe_update_payment_element_styles(selector) {

	if ('undefined' === typeof selector) {
		selector = '#field-payment_template';
	}

	const inputField = document.querySelector(selector);

	if (null === inputField) {
		return;
	}

	const inputStyles = window.getComputedStyle(inputField);

	// Add custom CSS for Payment Element container
	if (!document.getElementById('wu-stripe-payment-element-styles')) {
		const styleTag = document.createElement('style');
		styleTag.id = 'wu-stripe-payment-element-styles';
		styleTag.innerHTML = `
			#payment-element {
				background-color: ${inputStyles.getPropertyValue('background-color')};
				border-radius: ${inputStyles.getPropertyValue('border-radius')};
				padding: ${inputStyles.getPropertyValue('padding')};
			}
		`;
		document.body.appendChild(styleTag);
	}

	// Update elements appearance if possible
	if (elements) {
		try {
			elements.update({
				appearance: {
					theme: 'stripe',
					variables: {
						colorPrimary: inputStyles.getPropertyValue('border-color') || '#0570de',
						colorBackground: inputStyles.getPropertyValue('background-color') || '#ffffff',
						colorText: inputStyles.getPropertyValue('color') || '#30313d',
						fontFamily: inputStyles.getPropertyValue('font-family') || 'system-ui, sans-serif',
						borderRadius: inputStyles.getPropertyValue('border-radius') || '4px',
					},
				},
			});
		} catch (error) {
			// Appearance update not supported, that's fine
		}
	}
}
