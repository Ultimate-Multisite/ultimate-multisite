/* global Vue, ajaxurl, Swal, wu_event_payload_preview, ClipboardJS, wu_webhook_page, wu_ajax_error */
(function ($) {

	jQuery(document).ready(function () {

		jQuery(document).on('click', '#action_button', function (event) {

			event.preventDefault();

			const page = $(this).data('page');

			let webhook_url = '';

			let webhook_event = '';

			if (page === 'list') {

				webhook_url = $(this).data('url');

				webhook_event = $(this).data('event');

				const id = $(this).data('object');

				jQuery('[data-loading="wu_action_button_loading_' + id + '"]').removeClass('hidden');

			} // end if;

			if (page === 'edit') {

				webhook_url = $('input[name=webhook_url]').val();

				webhook_event = $('select[name=event]').val();

				const id = $(this).data('object');

				jQuery('[data-loading="wu_action_button_loading_' + id + '"]').removeClass('hidden');

			}

			$.ajax({
				method: 'post',
				url: ajaxurl,
				data: {
					nonce: wu_webhook_page.nonce,
					action: $(this).data('action'),
					webhook_id: $(this).data('object'),
					webhook_url,
					webhook_event,
				},
				success(data) {

					if (data.response) {

						$('[data-loading="wu_action_button_loading_' + data.id + '"]').addClass('hidden');

						Swal.fire({
							title: 'Test Response',
							icon: 'success',
							// eslint-disable-next-line max-len
							html: '<pre id="content" class="wu-overflow-auto wu-p-4 wu-m-0 wu-mt-2 wu-rounded wu-text-left wu-bg-gray-800 wu-text-white wu-font-mono wu-border wu-border-solid wu-border-gray-300 wu-max-h-screen wu-overflow-y-auto">' + JSON.stringify(data.response, null, 2) + '</pre>',
							showCloseButton: true,
							showCancelButton: false,
						});

					} else {

						$('[data-loading="wu_action_button_loading_' + data.id + '"]').addClass('hidden');

						Swal.fire({
							title: wu_webhook_page.i18n.error_title,
							icon: 'error',
							html: wu_webhook_page.i18n.error_message,
							showCloseButton: true,
							showCancelButton: false,
						});

					} // end if;

				},
				error(jqXHR) {

					wu_ajax_error(jqXHR);

				},
			});

		});

		let wu_event_payload_preview;

		if (($('#wu_payload').length)) {

			wu_event_payload_preview = new Vue({
				el: '#wu_payload',
				data() {

					return {
						payload: '',
						event: $('select[name="event"]').val(),
						loading: true,
					};

				},
				watch: {
					event() {

						this.get_event_payload();

					},
				},
				methods: {
					get_event_payload() {

						const app = this;

						app.loading = true;

						$.ajax({
							method: 'post',
							// eslint-disable-next-line no-undef
							url: ajaxurl,
							data: {
								action: 'wu_get_event_payload_preview',
								event: app.event,
							},
							success(response) {

								app.payload = response.data ?? {};

								app.loading = false;

							},
							error(jqXHR) {

								app.loading = false;

								wu_ajax_error(jqXHR);

							},

						});

					},

				},
				mounted() {

					this.get_event_payload();

				},

			});

		}

		$(document).on('change', 'select[name="event"]', function () {

			wu_event_payload_preview.event = $('select[name="event"]').val();

		});

		// eslint-disable-next-line no-unused-vars
		const clipboard = new ClipboardJS('.btn-clipboard');

		clipboard.on('success', function (e) {

			const target = $(e.trigger);

			const default_text = target.text();

			target.attr('disabled', 'disabled').text(wu_webhook_page.i18n.copied);

			setTimeout(function () {

				target.text(default_text).removeAttr('disabled');

			}, 3000);

		});

	});

}(jQuery));
