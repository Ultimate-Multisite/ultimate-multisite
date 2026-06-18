/* global ajaxurl, Vue, wu_integration_test_data */
(function($) {
	$(document).ready(function() {
		new Vue({
			el: "#integration-test",
			data: {
				success: false,
				loading: false,
				results: wu_integration_test_data.waiting_message,
			},
			mounted() {
				const that = this;
				this.loading = true;

				setTimeout(() => {
					$.ajax({
						url: ajaxurl,
						method: 'POST',
						data: {
							action: 'wu_test_hosting_integration',
							integration: wu_integration_test_data.integration_id,
							_ajax_nonce: wu_integration_test_data.nonce,
						},
						success(response) {
							that.loading = false;
							that.success = response.success;
							that.results = response.data;
						},
						error() {
							that.loading = false;
							that.success = false;
							that.results = wu_integration_test_data.error_message || 'Connection test failed. Please try again.';
						}
					});
				}, 1000);
			},
		});
	});
}(jQuery));
