/* eslint-disable no-lonely-if */
/* global Vue, wu_block_ui, ajaxurl, wu_placeholdersl10n */
(function($) {

	$(document).ready(function() {

		/**
		 * Vue
		 */
		if ($('#wu-template-placeholders').length) {

			window.wu_placeholders = new Vue({
				el: '#wu-template-placeholders',
				data: {
					tax_category: 'default',
					switching: false,
					creating: false,
					create_name: '',
					toggle: false,
					loading: true,
					saving: false,
					initialLoading: true,
					error: false,
					changed: false,
					data: {
						placeholders: [],
					},
					delete: [],
					saveMessage: '',
					errorMessage: '',
					rate_type: 'standard_rate',
				},
				watch: {
					data: {
						deep: true,
						handler() {

							if (this.initialLoading) {

								this.initialLoading = false;

								return;

							}

							this.changed = true;

						},
					},
					loading(new_value) {

						if (new_value === true) {

							window.wu_blocked_table = wu_block_ui('table.wp-list-table');

						} else {

							if (typeof window.wu_blocked_table !== 'undefined') {

								window.wu_blocked_table.unblock();

							} // end if;

						} // end if;

					},
				},
				mounted() {

					this.loading = true;

					this.pull_data(true);

					$('.wu-tooltip-vue').tipTip();

				},
				created() {

					// Create the event.
					const event = document.createEvent('Event');

					// Define that the event name is 'build'.
					event.initEvent('vue_loaded', true, true);

					event.vue = this;

					// target can be any Element or other EventTarget.
					window.dispatchEvent(event);

				},
				computed: {
					selected() {

						return $(this.data.placeholders).filter(function(index, item) {

							return item.selected;

						});

					},
				},
				methods: {
					refresh(e) {

						e.preventDefault();

						this.loading = true;

						this.pull_data();

					},
					select_all(event) {

						const toggle = $(event.target).is(':checked');

						this.data.placeholders = $.map(this.data.placeholders, function(item) {

							item.selected = toggle;

							return item;

						});

					},
					pull_data() {

						const that = this;

						jQuery.getJSON(ajaxurl + '?action=wu_get_placeholders').done(function(response) {

							that.loading = false;

							that.data = response.data;

						})
							.fail(function(error) {

								that.loading = false;

								that.error = true;

								that.errorMessage = error.statusText;

							});

					},
					add_row() {

						Vue.set(this.data, 'placeholders', this.data.placeholders.concat([
							{
								placeholder: '',
								content: '',
								selected: false,
							},
						]));

						this.$forceUpdate();

					},
					delete_rows() {

						this.delete = this.delete.concat(this.selected.get());

						// eslint-disable-next-line no-alert
						const are_you_sure = confirm(wu_placeholdersl10n.confirm_message);

						if (are_you_sure) {

							const cleaned_list = $(this.data.placeholders).filter(function(index, item) {

								return ! item.selected;

							});

							Vue.set(this.data, 'placeholders', cleaned_list.get());

							this.$forceUpdate();

						} // end if

					},
					save() {

						const that = this;

						that.saving = true;

						$.post({
							url: ajaxurl + '?action=wu_save_placeholders&' + $('#nonce_form').serialize(),
							data: JSON.stringify({
								placeholders: that.data.placeholders,
							}),
							dataType: 'json',
							contentType: 'application/json; charset=utf-8',
						}).success(function(data) {

							that.saving = false;

							that.changed = false;

							that.delete = [];

							that.saveMessage = data.message;

							if (data.code === 'success') {

								that.loading = true;

								that.initialLoading = true;

								that.pull_data();

							}

							setInterval(function() {

								that.saveMessage = '';

							}, 6000);

						});

					},
				},
			});

		} // end if;

	});

}(jQuery));
