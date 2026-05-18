/* global ajaxurl, Vue, wu_event_payload_placeholders, _, wu_ajax_error */
(function($) {

	$(document).ready(function() {

		wu_event_payload_placeholders = new Vue({
			el: '#wu_event_payload_placeholders',
			data() {

				return {
					placeholders: [],
					event: $("select[name='event']").val(),
					search: '',
					loading: true,
				};

			},
			computed: {
				filtered_placeholders() {

					const search = this.search.toLowerCase();

					return _.filter(this.placeholders, function(item) {

						return item.name.toLowerCase().indexOf(search) > -1 || item.placeholder.toLowerCase().indexOf(search) > -1;

					});

				},
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
						url: ajaxurl,
						data: {
							action: 'wu_get_event_payload_placeholders',
							email_event: app.event,
						},
						success(response) {

							app.placeholders = response;

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

		$(document).on('change', 'select[name="event"]', function() {

			wu_event_payload_placeholders.event = $("select[name='event']").val();

		});

	});

}(jQuery));
