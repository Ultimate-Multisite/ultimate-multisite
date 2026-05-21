/* global vuedraggable, Vue, wu_checkout_forms_editor_app, wu_checkout_form, _, ajaxurl, wu_initialize_tooltip, wu_ajax_error */
(function($) {

	$(document).ready(function() {

		const draggable_table = {
			components: {
				vuedraggable,
			},
			template: '#wu-table',
			props: [ 'list', 'headers', 'step_name' ],
			name: 'wu-draggable-table',
			data() {

				return {
					delete_field_id: '',
				};

			},
			methods: {
				remove_field(field) {

					wu_checkout_forms_editor_app.remove_field(this.step_name, field);

					this.delete_field_id = '';

				},
			},
		};

		wu_checkout_forms_editor_app = new Vue({
			el: '#wu-checkout-editor-app',
			name: 'CheckoutEditor',
			data() {

				return Object.assign({}, {
					dragging: false,
					search: '',
					delete_step_id: '',
					preview_error: false,
					preview: false,
					loading_preview: false,
					preview_content: '',
					iframe_preview_url: '',
				}, wu_checkout_form);

			},
			components: {
				vuedraggable,
				'wu-draggable-table': draggable_table,
			},
			computed: {
				field_count() {

					return _.reduce(this.steps, function(memo, step) {

						return memo + step.fields.length;

					}, 0);

				},
			},
			watch: {
				steps: {

					handler() {

						this.update_session();

					},

					deep: true,

				},
			},
			mounted() {

				this.update_session();

			},
			methods: {
				get_preview(type = null) {

					if (type === null) {

						this.preview = ! this.preview;

					} else {

						this.preview = true;

					} // end if;

					if (this.preview) {

						this.loading_preview = true;

						const that = this;

						const preview_type = type !== null ? type : 'user';

						// eslint-disable-next-line max-len
						that.iframe_preview_url = that.register_page + '?action=wu_generate_checkout_form_preview' + '&form_id=' + that.form_id + '&type=' + preview_type + '&uniq=' + (Math.random() * 1000);

						$('#wp-ultimo-checkout-preview').off('load').one('load', function() {

							that.loading_preview = false;

							setTimeout(() => {

								const height = document.getElementById('wp-ultimo-checkout-preview').contentWindow.document.body.scrollHeight;

								$('#wp-ultimo-checkout-preview').animate({
									height,
								});

							}, 1000);

						});

					} // end if;

				},
				add_step(data, cb = null) {

					const existing_step = data.original_id ? this.find_step(data.original_id) : this.find_step(data.id);

					delete data.original_id;

					if (typeof existing_step !== 'undefined') {

						const index = _.indexOf(this.steps, existing_step);

						data = Object.assign({}, existing_step, data);

						data.fields = existing_step.fields;

						Vue.set(this.steps, index, data);

					} else {

						this.steps.push(data);

					} // end if;

					this.$nextTick(function() {

						if (typeof cb === 'function') {

							cb();

							this.scroll_to(`wp-ultimo-list-table-${ data.id }`);

						} // end if;

					});

				},
				add_field(data, cb = null) {

					const step = _.findWhere(this.steps, {
						id: data.step,
					});

					let existing_field = this.find_field(data.step, data.id);

					if (typeof existing_field === 'undefined') {

						existing_field = this.find_field(data.step, data.original_id);

						delete data.original_id;

					} // end if;

					if (typeof existing_field !== 'undefined') {

						const index = _.indexOf(step.fields, existing_field);

						Vue.set(step.fields, index, data);

					} else {

						step.fields.push(data);

					} // end if;

					this.$nextTick(function() {

						if (typeof cb === 'function') {

							cb();

							this.scroll_to(`wp-ultimo-field-${ data.id }`);

						} // end if;

					});

			},
			scroll_to(element_id) {

				this.$nextTick(function() {

					setTimeout(() => {

						const element = document.getElementById(element_id);

						if (element) {
							element.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
						}

					}, 500);

				});

			},
			find_step(step_name) {

					return _.findWhere(this.steps, {
						id: step_name,
					});

				},
				find_field(step_name, field_name) {

					const step = _.findWhere(this.steps, {
						id: step_name,
					});

					const field = _.findWhere(step.fields, {
						id: field_name,
					});

					return field;

				},
				remove_step(step_name) {

					this.steps = _.reject(this.steps, function(item) {

						return item.id === step_name;

					});

					this.delete_step_id = '';

				},
				remove_field(step_name, field_name) {

					const step = _.findWhere(this.steps, {
						id: step_name,
					});

					step.fields = _.reject(step.fields, function(item) {

						return item.id === field_name;

					});

				},
				update_session() {

					wu_initialize_tooltip();

					const that = this;

					$.ajax({
						method: 'post',
						url: ajaxurl,
						data: {
							action: 'wu_save_editor_session',
							settings: that.steps,
							form_id: that.form_id,
						},
						success() { },
						error(jqXHR) {

							wu_ajax_error(jqXHR);

						},
					});

				},
			},
		});

	});

}(jQuery));
