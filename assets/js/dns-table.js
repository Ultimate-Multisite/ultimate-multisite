/* global ajaxurl, Vue, wu_dns_table_config, wu_ajax_error */
(function($) {

	window.wu_dns_table = new Vue({
		el: '#wu-dns-table',
		data: {
			error: null,
			results: {},
			loading: true,
		},
		updated() {
			this.$nextTick(function() {

				window.wu_initialize_tooltip();

			});
		}
	})

	$(document).ready(function() {

		$.ajax({
			url: ajaxurl,
			data: {
				action: 'wu_get_dns_records',
				domain: wu_dns_table_config.domain,
				_ajax_nonce: wu_dns_table_config.nonce,
			},
			success(data) {

				Vue.set(window.wu_dns_table, 'loading', false);

				if (data.success) {

					Vue.set(window.wu_dns_table, 'results', data.data);

				} else {

					Vue.set(window.wu_dns_table, 'error', data.data);

				}

			},
			error(jqXHR) {

				Vue.set(window.wu_dns_table, 'loading', false);

				wu_ajax_error(jqXHR);

			},
		})

	});
}(jQuery));
