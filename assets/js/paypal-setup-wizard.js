// PayPal setup wizard — test connection step.
//
// Pings the wu_paypal_setup_wizard_test AJAX endpoint and renders the
// status with Vue. Triggered from views/wizards/paypal-setup/test.php.
//
// @since 2.6.0

(function($) {
	$( document ).ready( function () {
		if ( typeof wu_paypal_setup_wizard === 'undefined' ) {
			return;
		}

		const data = wu_paypal_setup_wizard;

		new Vue( {
			el: '#wu-paypal-setup-wizard-test',
			data: {
				loading: true,
				success: false,
				webhookStatus: 'not_attempted',
				errorMessage: data.error_message,
				rawResponse: data.waiting_message,
			},
			mounted () {
				this.runTest();
			},
			methods: {
				runTest () {
					const that = this;
					this.loading = true;
					this.success = false;
					this.rawResponse = data.waiting_message;

					$.ajax( {
						url: data.ajax_url,
						method: 'POST',
						data: {
							action: 'wu_paypal_setup_wizard_test',
							nonce: data.nonce,
							sandbox_mode: data.sandbox_mode,
						},
						success ( response ) {
							that.loading = false;
							that.rawResponse = JSON.stringify( response, null, 2 );

							if ( response.success ) {
								that.success = true;
								that.webhookStatus = response.data.webhook_status || 'not_attempted';
							} else {
								that.success = false;
								that.errorMessage =
									( response.data && response.data.message ) ||
									data.error_message;
							}
						},
						error ( xhr ) {
							that.loading = false;
							that.success = false;
							that.errorMessage = data.error_message;
							that.rawResponse =
								( xhr.responseText && xhr.responseText.slice( 0, 1000 ) ) ||
								data.error_message;
						},
					} );
				},
			},
		} );
	} );
}( jQuery ));
