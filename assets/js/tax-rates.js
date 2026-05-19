/* eslint-disable no-lonely-if */
/* global _, vuedraggable, Vue, wu_block_ui, ajaxurl, wu_tax_ratesl10n, wu_ajax_error */
(function($) {

	$(document).ready(function() {

		/**
		 * Vue
		 */
		if ($('#wu-tax-rates').length) {

			Vue.component('selectizer', {
				props: [
					'value',
					'id',
					'name',
					'model',
					'country',
					'state',
					'selected',
					'options',
					'placeholder',
				],
				template: '<input v-bind="$props">',
				updated() {

					this.$nextTick(function() {

						const options = jQuery(this.$el).data('__options');

						if (options.data.country !== this.country) {

							options.data.country = this.country;

							jQuery(this.$el).data('__options', options);

							this.$el.selectize.clear();

							this.$el.selectize.clearOptions();

						} // end if;

						if (options.data.state !== this.state) {

							options.data.state = this.state;

							jQuery(this.$el).data('__options', options);

						} // end if;

					});

				},
				mounted() {

					const that = this;

					const item = this.$el;

					window.wu_selector({
						el: item,
						maxItems: 1000,
						options: this.options,
						valueField: 'slug',
						labelField: 'name',
						searchField: [ 'slug', 'name' ],
						create: true,
						templateName: 'checkout_form',
						data: {
							action: 'wu_search',
							model: this.model,
							country: this.country,
							state: this.state,
							number: 10,
						},
					});

					this.$el.selectize.on('change', function(value) {

						that.$emit('input', value);

					});

				},
			});

			window.wu_tax_rates = new Vue({
				el: '#wu-tax-rates',
				components: {
					vuedraggable,
				},
				data() {

					return {
						dragging: false,
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
							default: {
								name: 'Default',
								rates: [],
							},
						},
						delete: [],
						saveMessage: '',
						errorMessage: '',
						rate_type: 'standard_rate',
						editing: 0,
						item: {
							title: wu_tax_ratesl10n.name,
							country: '',
							state: '',
							tax_rate: '',
							type: 'regular',
							compound: false,
						},
					};

				},
				watch: {
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

					const vm = this;

					this.$watch('data', function() {

						if (vm.initialLoading) {

							vm.initialLoading = false;

							return;

						}

						vm.changed = true;

					}, {
						deep: true,
					});

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

						return $(this.data[ this.tax_category ].rates).filter(function(index, item) {

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

						this.data[ this.tax_category ].rates = $.map(this.data[ this.tax_category ].rates, function(item) {

							item.selected = toggle;

							return item;

						});

					},
					pull_data() {

						const that = this;

						jQuery.getJSON(ajaxurl + '?action=wu_get_tax_rates').done(function(response) {

							that.loading = false;

							that.data = response.data;

						})
							.fail(function(jqXHR) {

								that.loading = false;

								that.error = true;

								that.errorMessage = jqXHR.statusText;

								wu_ajax_error(jqXHR);

							});

					},
					add_tax_category() {

						this.data[ this.create_name ] = {
							name: this.create_name,
							rates: [],
						};

						this.creating = false;

						this.tax_category = this.create_name;

					},
					add_row() {

						Vue.set(this.data[ this.tax_category ], 'rates', this.data[ this.tax_category ].rates.concat([
							{
								title: wu_tax_ratesl10n.name,
								country: '',
								state: '',
								tax_rate: '',
								type: 'regular',
								compound: false,
							},
						]));

						this.$forceUpdate();

					},
					delete_tax_category() {

						// eslint-disable-next-line no-alert
						const are_you_sure = confirm(wu_tax_ratesl10n.confirm_delete_tax_category_message);

						const that = this;

						if (are_you_sure) {

							const cleaned_list = _.filter(this.data, function(item, index) {

								return index !== that.tax_category;

							});

							that.data = cleaned_list.length ? cleaned_list : {
								default: {
									name: 'Default',
									rates: [],
								},
							};

							that.tax_category = Object.keys(that.data).shift();

						} // end if

					},
					delete_rows() {

						this.delete = this.delete.concat(this.selected.get());

						// eslint-disable-next-line no-alert
						const are_you_sure = confirm(wu_tax_ratesl10n.confirm_message);

						if (are_you_sure) {

							const cleaned_list = $(this.data[ this.tax_category ].rates).filter(function(index, item) {

								return ! item.selected;

							});

							Vue.set(this.data[ this.tax_category ], 'rates', cleaned_list.get());

							this.$forceUpdate();

						} // end if

					},
					save() {

						const that = this;

						that.saving = true;

						if (typeof that.data[ that.tax_category ].rates !== 'undefined') {

							_.map(that.data[ that.tax_category ].rates, (item, index) => {

								item.priority = index * 10;

								return item;

							});

						}

						$.post({
							url: ajaxurl + '?action=wu_save_tax_rates&' + $('#nonce_form').serialize(),
							data: JSON.stringify({
								tax_rates: that.data,
								tax_category: that.tax_category,
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

								that.tax_category = data.tax_category;

							}

							setTimeout(function() {

								that.saveMessage = '';

							}, 6000);

						}).fail(function(jqXHR) {

							that.saving = false;

							wu_ajax_error(jqXHR);

						});

					},
				},
			});

		} // end if;

	});

}(jQuery));
