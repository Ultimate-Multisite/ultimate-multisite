/* global ajaxurl, wu_block_ui, ClipboardJS, wu_log_payload, wu_view_logs, Vue, wu_ajax_error */
(function($) {

	$(document).ready(function() {

		wu_log_payload = new Vue({
			el: '#wu_payload',
			data() {

				return {
					payload: '',
					log: $('select[name=log_file]').val(),
					loading: true,
				};

			},
			watch: {
				log() {

					this.get_log_payload();

				},
			},
			methods: {
				get_log_payload() {

					const block = wu_block_ui('#wu_payload_content');

					const app = this;

					app.loading = true;

					$.ajax({
						method: 'post',
						// eslint-disable-next-line no-undef
						url: ajaxurl,
						data: {
							action: 'wu_handle_view_logs',
							file: app.log,
						},
						success(response) {

							app.payload = response.data.contents;

							app.loading = false;

							block.unblock();

							try {

								history.pushState({}, null, '?' + 'page=wp-ultimo-view-logs&log_file=' + app.log);

							} catch (err) {

								// eslint-disable-next-line no-console
								console.warn('Browser does not support pushState.', err);

							} // end try;

						},
						error(jqXHR) {

							app.loading = false;

							block.unblock();

							wu_ajax_error(jqXHR);

						},

					});

				},

			},
			mounted() {

				this.get_log_payload();

			},

		});

		$(document).on('change', 'select[name=log_file]', function() {

			wu_log_payload.log = $('select[name=log_file]').val();

		});

		// eslint-disable-next-line no-unused-vars
		const clipboard = new ClipboardJS('.btn-clipboard');

		clipboard.on('success', function(e) {

			const target = $(e.trigger);

			const default_text = target.text();

			target.attr('disabled', 'disabled').text(wu_view_logs.i18n.copied);

			setTimeout(function() {

				target.text(default_text).removeAttr('disabled');

			}, 3000);

		});

	});

}(jQuery));
