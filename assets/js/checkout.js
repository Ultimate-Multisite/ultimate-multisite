/* global Vue, moment, _, wu_checkout, wu_checkout_form, wu_create_cookie, wu_listen_to_cookie_change, wu_initialize_tooltip */
(function ($, hooks, _) {

	/*
   * Remove the pre-flight parameter.
   */
	if (window.history.replaceState) {

		window.history.replaceState(null, null, wu_checkout.baseurl);

	} // end if;

	/*
   * Sets default template.
   */
	hooks.addAction('wu_on_create_order', 'nextpress/wp-ultimo', function (checkout, data) {

		if (typeof data.order.extra.template_id !== 'undefined' && data.order.extra.template_id) {

			checkout.template_id = data.order.extra.template_id;

		} // end if;

	});

	/*
   * Handle auto-submittable fields.
   *
   * Some fields are auto-submittable if they are the one relevant
   * field on a checkout step.
   */
	hooks.addAction('wu_checkout_loaded', 'nextpress/wp-ultimo', function (checkout) {

		/*
     * The checkout sets the auto submittable field as a global variable
     */
		if (typeof window.wu_auto_submittable_field !== 'undefined' && window.wu_auto_submittable_field) {

			const options = {
				deep: true,
			};

			checkout.$watch(window.wu_auto_submittable_field, function () {

				jQuery(this.$el).submit();

			}, options);

		} // end if;

	});

	/*
   * Sets up the cookie listener for template selection.
   */
	hooks.addAction('wu_checkout_loaded', 'nextpress/wp-ultimo', function (checkout) {

		/*
     * Resets the template selection cookie.
     */
		wu_create_cookie('wu_template', '');

		/*
     * Resets the selected products cookie.
     */
		wu_create_cookie('wu_selected_products', '');
		/*
     * Listens for changes and set the template if one is detected.
     */
		wu_listen_to_cookie_change('wu_template', function (value) {
			if (value) {
				checkout.template_id = value;
			}
		});

	});

	/**
	 * Allows for cross-sells
	 */
	$(document).on('click', '[href|="#wu-checkout-add"]', function (event) {

		event.preventDefault();

		const el = $(this);

		const product_slug = el.attr('href').split('#').pop().replace('wu-checkout-add-', '');

		if (typeof wu_checkout_form !== 'undefined') {

			if (wu_checkout_form.products.indexOf(product_slug) === -1) {

				wu_checkout_form.add_product(product_slug);

				el.html(wu_checkout.i18n.added_to_order);

			} // end if;

		} // end if;

	});

	/**
	 * Reload page when history back button was pressed
	 */
	window.addEventListener('pageshow', function (event) {

		if (event.persisted && this.window.wu_checkout_form) {

			this.window.wu_checkout_form.unblock();

		} // end if;

	});

	/**
	 * Setup
	 */
	$(document).ready(function () {

		/*
     * Prevent app creation when vue is not available.
     */
		if (typeof window.Vue === 'undefined') {

			return;

		} // end if;

		Object.defineProperty(Vue.prototype, '$moment', { value: moment });

		const maybe_cast_to_int = function (value) {

			return isNaN(value) ? value : parseInt(value, 10);

		};

		const initial_data = {
			plan: maybe_cast_to_int(wu_checkout.plan),
			errors: [],
			order: wu_checkout.order,
			products: _.map(wu_checkout.products, maybe_cast_to_int),
			template_id: wu_checkout.template_id,
			template_category: '',
			gateway: wu_checkout.gateway,
			request_billing_address: wu_checkout.request_billing_address,
			country: wu_checkout.country,
			state: '',
			city: '',
			site_title: wu_checkout.site_title || '',
			site_url: wu_checkout.site_url,
			site_domain: wu_checkout.site_domain,
			is_subdomain: wu_checkout.is_subdomain,
			discount_code: wu_checkout.discount_code || '',
			toggle_discount_code: 0,
			payment_method: '',
			username: '',
			email_address: '',
			payment_id: wu_checkout.payment_id,
			membership_id: wu_checkout.membership_id,
			cart_type: 'new',
			auto_renew: 1,
			duration: wu_checkout.duration,
			duration_unit: wu_checkout.duration_unit,
			prevent_submission: false,
			valid_password: true,
			stored_templates: {},
			state_list: [],
			city_list: [],
			labels: {},
			show_login_prompt: false,
			login_prompt_field: '',
			checking_user_exists: false,
			logging_in: false,
			login_error: '',
			inline_login_password: '',
			custom_amounts: wu_checkout.custom_amounts || {},
			pwyw_recurring: wu_checkout.pwyw_recurring || {},
		};

		hooks.applyFilters('wu_before_form_init', initial_data);

		if (! jQuery('#wu_form').length) {

			return;

		} // end if;

		/**
		 * ColorPicker Component
		 */
		Vue.component('colorPicker', {
			props: [ 'value' ],
			template: '<input type="text">',
			mounted() {

				const vm = this;

				$(this.$el)
					.val(this.value)
				// WordPress color picker
					.wpColorPicker({
						width: 200,
						defaultColor: this.value,
						change(event, ui) {

							// emit change event on color change using mouse
							vm.$emit('input', ui.color.toString());

						},
					});

			},
			watch: {
				value(value) {

					// update value
					$(this.$el).wpColorPicker('color', value);

				},
			},
			destroyed() {

				$(this.$el).off().wpColorPicker('destroy'); // (!) Not tested

			},
		});

		/**
		 * Declare the dynamic content for Vue.
		 */
		const dynamic = {
			functional: true,
			template: '#dynamic',
			props: [ 'template' ],
			render(h, context) {

				const template = context.props.template;

				const component = template ? { template } : '<div>nbsp;</div>';

				return h(component);

			},
		};

		// eslint-disable-next-line no-unused-vars
		window.wu_checkout_form = new Vue({
			el: '#wu_form',
			data: initial_data,
			directives: {
				init: {
					bind(el, binding, vnode) {

						vnode.context[ binding.arg ] = binding.value;

					},
				},
			},
			components: {
				dynamic,
			},
			computed: {
				hooks() {

					return wp.hooks;

				},
				unique_products() {

					return _.uniq(this.products, false, (item) => parseInt(item, 10));

				},
			},
			methods: {
				debounce(fn) {

					return _.debounce(fn, 200, true);

				},
				open_url(url, target = '_blank') {

					window.open(url, target);

				},
				get_template(template, data) {

					if (typeof data.id === 'undefined') {

						data.id = 'default';

					} // end if;

					const template_name = template + '/' + data.id;

					if (typeof this.stored_templates[ template_name ] !== 'undefined') {

						return this.stored_templates[ template_name ];

					} // end if;

					const template_data = this.hooks.applyFilters('wu_before_template_fetch', {
						duration: this.duration,
						duration_unit: this.duration_unit,
						products: this.products,
						...data,
					}, this);

					this.fetch_template(template, template_data);

					return '<div class="wu-p-4 wu-bg-gray-100 wu-text-center wu-my-2 wu-rounded">' + wu_checkout.i18n.loading + '</div>';

				},
				reset_templates(to_clear) {

					if (typeof to_clear === 'undefined') {

						this.stored_templates = {};

						return;

					}

					const new_list = {};

					_.forEach(this.stored_templates, function (item, key) {

						const type = key.toString().substr(0, key.toString().indexOf('/'));

						if (_.contains(to_clear, type) === false) {

							new_list[ key ] = item;

						} // end if;

					});

					this.stored_templates = new_list;

				},
				fetch_template(template, data) {

					const that = this;

					if (typeof data.id === 'undefined') {

						data.id = 'default';

					} // end if;

					this.request('wu_render_field_template', {
						template,
						attributes: data,
					}, function (results) {

						const template_name = template + '/' + data.id;

						if (results.success) {

							Vue.set(that.stored_templates, template_name, results.data.html);

						} else {

							Vue.set(that.stored_templates, template_name, '<div>' + results.data[ 0 ].message + '</div>');

						} // end if;

					});

				},
				go_back() {

					this.block();

					window.history.back();

				},
				set_prevent_submission(value) {

					this.$nextTick(function () {

						this.prevent_submission = value;

					});

				},
				remove_product(product_id, product_slug) {

					this.products = _.filter(this.products, function (item) {

						// eslint-disable-next-line eqeqeq
						return item != product_id && item != product_slug;

					});

				},
				add_plan(product_id) {

					if (this.plan) {

						this.remove_product(this.plan);

					} // end if;

					this.plan = product_id;

					this.add_product(product_id);

				},
				add_product(product_id) {

					this.products.push(product_id);

				},
				has_product(product_id) {

					return this.products.indexOf(product_id) > -1 || this.products.indexOf(parseInt(product_id, 10)) > -1;

				},
				set_custom_amount(product_id, amount) {

					Vue.set(this.custom_amounts, product_id, parseFloat(amount) || 0);

					this.create_order();

				},
				get_custom_amount(product_id) {

					return this.custom_amounts[ product_id ] || null;

				},
				set_pwyw_recurring(product_id, is_recurring) {

					Vue.set(this.pwyw_recurring, product_id, Boolean(is_recurring));

					this.create_order();

				},
				get_pwyw_recurring(product_id) {

					return this.pwyw_recurring[ product_id ] || false;

				},
				wu_format_money(value) {

					return window.wu_format_money(value);

				},
				filter_for_request(data, request_type = '') {

					const filter_list = this.hooks.doAction('wu_filter_for_request', [
						'stored_templates',
					], data, request_type);

					const filtered_list = _.omit(data, filter_list);

					return filtered_list;

				},
				create_order() {

					/*
           * Bail if there is no order summary to update.
           */
					if (! jQuery('#wu-order-summary-content').length) {

						return;

					} // end if;

					this.block();

					this.order = false;

					const that = this;

					const _request = this.debounce(this.request);

					const data = { ...this.$data };

					delete data.stored_templates;
					delete data.state_list;
					delete data.city_list;
					delete data.labels;

					_request('wu_create_order', this.filter_for_request(data, 'wu_create_order'), function (results) {

						that.order = results.data.order;

						that.state_list = results.data.states;

						that.city_list = results.data.cities;

						that.labels = results.data.labels;

						that.cart_type = results.data.order.type;

						that.errors = results.data.order.errors;

						that.hooks.doAction('wu_on_create_order', that, results.data);

						if (results.data.order.url) {

							try {

								// history.pushState({}, null, wu_checkout.baseurl + results.data.order.url);

							} catch (err) {

								// eslint-disable-next-line no-console
								console.warn('Browser does not support pushState.', err);

							} // end try;

						} // ed if;

						that.unblock();

					}, this.handle_errors);

				},
				get_errors() {

					const result = this.errors.map(function (e) {

						return e.message;

					});

					return result.length > 0 ? result : false;

				},
				get_error(field) {

					const result = this.errors.filter(function (e) {

						return e.code === field;

					});

					return result.length > 0 ? result[ 0 ] : false;

				},
				form_success(results) {

					if (! _.isEmpty(results.data)) {

						this.hooks.doAction('wu_on_form_success', this, results.data);

						const fields = results.data.gateway.data;

						fields.payment_id = results.data.payment_id;

						fields.membership_id = results.data.membership_id;

						fields.cart_type = results.data.cart_type;

						// Append the hidden fields
						jQuery.each(Object.assign({}, fields), function (index, value) {

							const hidden = document.createElement('input');

							hidden.type = 'hidden';

							hidden.name = index;

							hidden.value = value;

							jQuery('#wu_form').append(hidden);

						});

					} // end if;

				},
				/**
				 * Runs client-side validation against the rules exposed by PHP.
				 *
				 * Returns an array of error objects { code, message } for any
				 * failing rules. An empty array means the form is valid client-side.
				 * Server-side rules (uniqueness, DB lookups) are still checked via AJAX.
				 *
				 * @param {Object} values Key/value map of current form field values.
				 * @return {Array} Array of { code, message } error objects.
				 */
				validate_client_side(values) {

					const allRules = (typeof wu_checkout !== 'undefined' && wu_checkout.validation_rules) ? wu_checkout.validation_rules : {};
					const i18n = (typeof wu_checkout !== 'undefined' && wu_checkout.i18n) ? wu_checkout.i18n : {};
					const errors = [];

					/*
					 * Restrict validation to the fields on the current step only.
					 *
					 * The PHP side exposes wu_checkout.step_fields as a map of
					 * step_id => [field_ids]. We read the current step from the
					 * hidden checkout_step input and filter the rules accordingly.
					 * This prevents required fields on later steps (e.g. email,
					 * username, password on step 4) from blocking submission of
					 * earlier steps that do not include those fields.
					 *
					 * Falls back to all rules when step_fields is unavailable
					 * (legacy single-step forms).
					 */
					const stepFields = (typeof wu_checkout !== 'undefined' && wu_checkout.step_fields) ? wu_checkout.step_fields : null;
					const currentStep = jQuery('input[name="checkout_step"]').val();
					let rules = allRules;

					if (stepFields && currentStep && stepFields[ currentStep ]) {

						const allowedFields = stepFields[ currentStep ];

						rules = {};

						allowedFields.forEach(function(fieldId) {

							if (allRules[ fieldId ]) {

								rules[ fieldId ] = allRules[ fieldId ];

							}

						});

					}

					/**
					 * Retrieve a display label for a field, falling back to the field ID.
					 *
					 * @param {string} field Field ID.
					 * @return {string} Human-readable label.
					 */
					function label(field) {

						const labels = (typeof wu_checkout !== 'undefined' && wu_checkout.field_labels) ? wu_checkout.field_labels : {};

						return labels[ field ] || field.replace(/_/g, ' ');

					}

					/**
					 * Resolve the current value for a field from the values map.
					 * Falls back to an empty string so rule checks are always safe.
					 *
					 * @param {string} field Field ID.
					 * @return {string} Current field value as a string.
					 */
					function val(field) {

						return (values[ field ] !== undefined && values[ field ] !== null) ? String(values[ field ]) : '';

					}

					/**
					 * Add an error for a field if one does not already exist.
					 *
					 * @param {string} field
					 * @param {string} message
					 */
					function addError(field, message) {

						const alreadyHas = errors.some(function(e) {

							return e.code === field;

						});

						if (! alreadyHas) {

							errors.push({ code: field, message });

						}

					}

					Object.keys(rules).forEach(function(field) {

						const fieldRules = rules[ field ];
						const fieldVal = val(field);

						fieldRules.forEach(function(ruleObj) {

							const rule = ruleObj.rule;
							const param = ruleObj.param;

							switch (rule) {

								case 'required': {

									if (fieldVal.trim() === '') {

										// translators: %s is the field label.
										addError(field, (i18n.field_required || '%s is required.').replace('%s', label(field)));

									}

									break;

								}

								case 'required_without': {

									// Required when the referenced field is absent/empty.
									if (val(param).trim() === '' && fieldVal.trim() === '') {

										addError(field, (i18n.field_required || '%s is required.').replace('%s', label(field)));

									}

									break;

								}

								case 'required_with': {

									// Required when the referenced field is present/non-empty.
									if (val(param).trim() !== '' && fieldVal.trim() === '') {

										addError(field, (i18n.field_required || '%s is required.').replace('%s', label(field)));

									}

									break;

								}

								case 'email': {

									if (fieldVal.trim() !== '' && ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(fieldVal)) {

										addError(field, (i18n.field_invalid_email || '%s must be a valid email address.').replace('%s', label(field)));

									}

									break;

								}

								case 'min': {

									const minLen = parseInt(param, 10);

									if (! isNaN(minLen) && fieldVal.length > 0 && fieldVal.length < minLen) {

										addError(field, (i18n.field_min_length || '%s must be at least %d characters.').replace('%s', label(field)).replace('%d', minLen));

									}

									break;

								}

								case 'max': {

									const maxLen = parseInt(param, 10);

									if (! isNaN(maxLen) && fieldVal.length > maxLen) {

										addError(field, (i18n.field_max_length || '%s must not exceed %d characters.').replace('%s', label(field)).replace('%d', maxLen));

									}

									break;

								}

								case 'alpha_dash': {

									if (fieldVal.trim() !== '' && ! /^[a-zA-Z0-9_-]+$/.test(fieldVal)) {

										addError(field, (i18n.field_alpha_dash || '%s may only contain letters, numbers, dashes, and underscores.').replace('%s', label(field)));

									}

									break;

								}

								case 'lowercase': {

									if (fieldVal.trim() !== '' && fieldVal !== fieldVal.toLowerCase()) {

										addError(field, (i18n.field_lowercase || '%s must be lowercase.').replace('%s', label(field)));

									}

									break;

								}

								case 'same': {

									// Only validate if the field is present in the form values.
									// If the field is absent (e.g. email_address_confirmation when
									// the checkout form does not include a confirmation field), skip
									// the check so the form can still be submitted.
									if ((field in values) && fieldVal !== val(param)) {

										addError(field, (i18n.field_same || '%s must match %s.').replace('%s', label(field)).replace('%s', label(param)));

									}

									break;

								}

								case 'integer': {

									if (fieldVal.trim() !== '' && ! /^\d+$/.test(fieldVal.trim())) {

										addError(field, (i18n.field_integer || '%s must be a whole number.').replace('%s', label(field)));

									}

									break;

								}

								case 'accepted': {

									// "accepted" means the value must be truthy (1, true, "on", "yes").
									const accepted = [ '1', 'true', 'on', 'yes' ];

									if (fieldVal.trim() !== '' && ! accepted.includes(fieldVal.toLowerCase())) {

										addError(field, (i18n.field_accepted || '%s must be accepted.').replace('%s', label(field)));

									}

									break;

								}

								// Rules handled server-side only (unique, products, country, etc.) are skipped.
								default:
									break;

							}

						});

					});

					return errors;

				},
				validate_form() {

					this.errors = [];

					const form_data_obj = jQuery('#wu_form').serializeArray().reduce(function (json, { name, value }) {

						// Get products from this
						if (name !== 'products[]') {

							json[ name ] = value;

						}

						return json;

					}, {});

					/*
				 * Run client-side validation first.
				 *
				 * Build a values map from the serialised form data plus Vue-managed
				 * fields so the validator has the same picture as the server.
				 * This gives instant feedback without a network round-trip.
				 */
					const form_values = Object.assign({}, form_data_obj, {
						products: this.products,
						membership_id: this.membership_id,
						payment_id: this.payment_id,
						user_id: form_data_obj.user_id || '',
					});

					const client_errors = this.validate_client_side(form_values);

					if (client_errors.length) {

						this.errors = client_errors;

						this.unblock();

						return;

					}

					/*
				 * Client-side checks passed — proceed with the AJAX validation
				 * which handles server-only rules (uniqueness, DB lookups, etc.).
				 */
					const form_data = jQuery.param({
						...form_data_obj,
						products: this.products,
						membership_id: this.membership_id,
						payment_id: this.payment_id,
						auto_renew: this.auto_renew,
						cart_type: this.type,
						duration: this.duration,
						duration_unit: this.duration_unit,
					});

					const that = this;

					this.request('wu_validate_form', form_data, function (results) {

						// Safari/iOS autofill does NOT fire keyup/input events, so
						// valid_password may be stale at submit time. Force a
						// synchronous re-check before deciding to show the error.
						if (! that.valid_password && that.password_strength_checker) {
							that.password_strength_checker.checkStrength();
						}

						if (! that.valid_password) {

							that.errors.push({
								code: 'password',
								message: wu_checkout.i18n.weak_password,
							});

						} // end if;

						if (results.success === false) {

							that.errors = [].concat(that.errors, results.data);

							that.unblock();

							return;

						} // end if;

						if (! that.errors.length) {

							that.form_success(results);

							if (that.prevent_submission === false) {

								that.resubmit();

							} // end if;

						} else {

							that.unblock();

						} // end if;

					}, this.handle_errors);

				},
				resubmit() {

					jQuery('#wu_form').get(0).submit();

				},
				handle_errors(errors) {

					this.unblock();

					// eslint-disable-next-line no-console
					console.error(errors);

				},
				on_submit(event) {

					event.preventDefault();

				},
				on_change_product(new_value, old_value) {

					window.wu_create_cookie('wu_selected_products', new_value.join(','), 0.5) // Save it for 12 hours max.

					this.reset_templates([ 'template-selection' ]);

					hooks.doAction('wu_on_change_product', new_value, old_value, this);

					this.create_order();

				},
				on_change_gateway(new_value, old_value) {

					hooks.doAction('wu_on_change_gateway', new_value, old_value, this);

				},
				on_change_country(new_value, old_value) {

					hooks.doAction('wu_on_change_country', new_value, old_value, this);

					this.create_order();

				},
				on_change_state(new_value, old_value) {

					hooks.doAction('wu_on_change_state', new_value, old_value, this);

					this.create_order();

				},
				on_change_city(new_value, old_value) {

					hooks.doAction('wu_on_change_city', new_value, old_value, this);

					this.create_order();

				},
				on_change_duration(new_value, old_value) {

					this.reset_templates();

					hooks.doAction('wu_on_change_duration', new_value, old_value, this);

					this.create_order();

				},
				on_change_duration_unit(new_value, old_value) {

					this.reset_templates();

					hooks.doAction('wu_on_change_duration_unit', new_value, old_value, this);

					this.create_order();

				},
				on_change_discount_code(new_value, old_value) {

					hooks.doAction('wu_on_change_discount_code', new_value, old_value, this);

					this.create_order();

				},
				block() {

					/*
           * Get the first bg color from a parent.
           */
					const bg_color = jQuery(this.$el).parents().filter(function () {

						return $(this).css('backgroundColor') !== 'rgba(0, 0, 0, 0)';

					}).first().css('backgroundColor');

					jQuery(this.$el).wu_block({
						message: '<div class="spinner is-active wu-float-none" style="float: none !important;"></div>',
						overlayCSS: {
							backgroundColor: bg_color ? bg_color : '#ffffff',
							opacity: 0.6,
						},
						css: {
							padding: 0,
							margin: 0,
							width: '50%',
							fontSize: '14px !important',
							top: '40%',
							left: '35%',
							textAlign: 'center',
							color: '#000',
							border: 'none',
							backgroundColor: 'none',
							cursor: 'wait',
						},
					});

				},
				unblock() {

					jQuery(this.$el).wu_unblock();

				},
				request(action, data, success_handler, error_handler) {

					const actual_ajax_url = (action === 'wu_validate_form' || action === 'wu_create_order' || action === 'wu_render_field_template' || action === 'wu_check_user_exists' || action === 'wu_inline_login') ? wu_checkout.late_ajaxurl : wu_checkout.ajaxurl;

					jQuery.ajax({
						method: 'POST',
						url: actual_ajax_url + '&action=' + action,
						data,
						success: success_handler,
						error: error_handler,
					});

				},
				init_password_strength() {

					const that = this;
					const pass1_el = jQuery('#field-password');

					if (! pass1_el.length) {

						return;

					} // end if;

					// If the strength meter element doesn't exist, skip validation
					if (! jQuery('#pass-strength-result').length) {

						return;

					} // end if;

					// Use the shared WU_PasswordStrength utility
					if (typeof window.WU_PasswordStrength !== 'undefined') {

						// Set valid_password to false initially since password field exists and needs validation
						this.valid_password = false;

						this.password_strength_checker = new window.WU_PasswordStrength({
							pass1: pass1_el,
							result: jQuery('#pass-strength-result'),
							onValidityChange(isValid) {

								that.valid_password = isValid;

							}
						});

					} // end if;

				},
				check_user_exists_debounced: _.debounce(function(field_type, value) {

					this.check_user_exists(field_type, value);

				}, 500),
				check_user_exists(field_type, value) {

					// Don't let other field checks interfere with an active email prompt
					if (this.show_login_prompt && this.login_prompt_field === 'email' && field_type !== 'email') {
						return;
					}

					// Don't check if value is too short
					if (! value || value.length < 3) {

						if (this.login_prompt_field === field_type) {
							this.show_login_prompt = false;
							this.remove_field_error(field_type === 'email' ? 'email_address' : 'username');
						}

						return;

					}

					this.checking_user_exists = true;
					this.login_error = '';

					const that = this;

					this.request('wu_check_user_exists', {
						field_type,
						value,
						_wpnonce: jQuery('[name="_wpnonce"]').val()
					}, function(results) {

						that.checking_user_exists = false;

						if (results.success && results.data.exists) {

							that.show_login_prompt = true;
							that.login_prompt_field = field_type;

							that.add_field_error(field_type === 'email' ? 'email_address' : 'username', wu_checkout.i18n.email_exists);

						} else if (that.login_prompt_field === field_type) {

							that.show_login_prompt = false;
							that.remove_field_error(field_type === 'email' ? 'email_address' : 'username');

						}

					}, function(error) {

						that.checking_user_exists = false;

						if (that.login_prompt_field === field_type) {
							that.show_login_prompt = false;
							that.remove_field_error(field_type === 'email' ? 'email_address' : 'username');
						}

					});

				},
				async handle_inline_login(event) {

					// Prevent any default behavior or form submission
					if (event) {

						event.preventDefault();
						event.stopPropagation();
						event.stopImmediatePropagation();

					}

					if (! this.inline_login_password) {

						this.login_error = wu_checkout.i18n.password_required || 'Password is required';

						return false;

					}

					this.logging_in = true;
					this.login_error = '';

					const that = this;
					const field_type = this.login_prompt_field;
					const username_or_email = field_type === 'email'
						? this.email_address || ''
						: this.username || '';

					/**
					 * Filter collecting async pre-submission work for the inline login.
					 *
					 * Addons can push promises into the array; the login request waits
					 * for all of them to resolve before being sent. Useful when an
					 * addon needs to perform asynchronous work (e.g. solving an
					 * invisible captcha) before the payload is built.
					 *
					 * @param {Promise[]} promises   The array of pending promises.
					 * @param {string}    field_type The field type ('email' or 'username').
					 */
					try {

						await Promise.all(hooks.applyFilters('wu_before_inline_login_submitted', [], field_type));

					} catch (err) {

						this.logging_in = false;
						this.login_error = (err && err.message) ? err.message : (wu_checkout.i18n.login_failed || 'Login failed. Please try again.');

						/**
						 * Fires when an inline login attempt fails due to a rejected
						 * wu_before_inline_login_submitted promise.
						 *
						 * Mirrors the failure hook emitted by the AJAX error path so that
						 * addons (e.g. captcha widgets) can reset themselves regardless of
						 * which failure path was taken.
						 *
						 * @param {Object} error      Object containing the error message and originalError.
						 * @param {string} field_type The field type ('email' or 'username').
						 */
						hooks.doAction('wu_inline_login_error', {
							data: {
								message: this.login_error,
							},
							originalError: err,
						}, field_type);

						return false;

					}

					/**
					 * Filter the inline login request data.
					 *
					 * Addons can hook into this filter to append extra fields to the
					 * inline login request (for example, captcha tokens).
					 *
					 * @param {Object} data       The data object to be sent.
					 * @param {string} field_type The field type ('email' or 'username').
					 */
					const login_data = hooks.applyFilters('wu_inline_login_data', {
						username_or_email,
						password: this.inline_login_password,
						_wpnonce: jQuery('[name="_wpnonce"]').val()
					}, field_type);

					this.request('wu_inline_login', login_data, function(results) {

						that.logging_in = false;

						if (results.success) {

							/**
							 * Fires when an inline login attempt succeeds.
							 *
							 * @param {Object} results    The AJAX success response.
							 * @param {string} field_type The field type ('email' or 'username').
							 */
							hooks.doAction('wu_inline_login_success', results, field_type);

							// Login successful - reload page to show logged-in state
							window.location.reload();

						}

					}, function(error) {

						that.logging_in = false;

						if (error.responseJSON && error.responseJSON.data && error.responseJSON.data.message) {

							that.login_error = error.responseJSON.data.message;

						} else {

							that.login_error = wu_checkout.i18n.login_failed || 'Login failed. Please try again.';

						}

						/**
						 * Fires when an inline login attempt fails.
						 *
						 * Addons can hook into this action to react to failed logins
						 * (for example, resetting a captcha widget).
						 *
						 * @param {Object} error      The AJAX error response.
						 * @param {string} field_type The field type ('email' or 'username').
						 */
						hooks.doAction('wu_inline_login_error', error, field_type);

					});

					return false;

				},
				add_field_error(field_code, message) {

					this.remove_field_error(field_code);

					this.errors.push({
						code: field_code,
						message,
					});

				},
				remove_field_error(field_code) {

					this.errors = this.errors.filter(function(e) {

						return e.code !== field_code;

					});

				},
				dismiss_login_prompt() {

					this.show_login_prompt = false;
					this.inline_login_password = '';
					this.login_error = '';

				},
				setup_inline_login_handlers() {

					const that = this;

					// Setup handlers for both email and username field types
					[ 'email', 'username' ].forEach(function(fieldType) {

						const loginPromptContainer = document.getElementById('wu-inline-login-prompt-' + fieldType);

						if (! loginPromptContainer) {
							return;
						}

						// Only attach handlers once per container
						if (loginPromptContainer.dataset.wuHandlersAttached) {
							return;
						}

						loginPromptContainer.dataset.wuHandlersAttached = '1';

						const passwordField = document.getElementById('wu-inline-login-password-' + fieldType);
						const submitButton = document.getElementById('wu-inline-login-submit-' + fieldType);

						if (! passwordField || ! submitButton) {
							return;
						}

						const errorDiv = document.getElementById('wu-login-error-' + fieldType);

						function showError(message) {

							errorDiv.textContent = message;
							errorDiv.classList.remove('wu-hidden');

						}

						function hideError() {

							errorDiv.classList.add('wu-hidden');

						}

						function handleError(error) {

							submitButton.disabled = false;
							submitButton.textContent = wu_checkout.i18n.sign_in || 'Sign in';

							if (error.data && error.data.message) {

								showError(error.data.message);

							} else {

								showError(wu_checkout.i18n.login_failed || 'Login failed. Please try again.');

							}

							/**
							 * Fires when an inline login attempt fails.
							 *
							 * Addons can hook into this action to react to failed logins
							 * (for example, resetting a captcha widget).
							 *
							 * @param {Object} error     The AJAX error response or jqXHR.
							 * @param {string} fieldType The field type ('email' or 'username').
							 */
							hooks.doAction('wu_inline_login_error', error, fieldType);

						}

						async function handleLogin(e) {

							e.preventDefault();
							e.stopPropagation();
							e.stopImmediatePropagation();

							const password = passwordField.value;

							if (! password) {

								showError(wu_checkout.i18n.password_required || 'Password is required');

								return false;

							}

							submitButton.disabled = true;
							submitButton.innerHTML = '<span class="spinner is-active wu-inline-block" style="float: none; width: 16px; height: 16px; margin: 0 4px 0 0;"></span>' + (wu_checkout.i18n.logging_in || 'Logging in...');
							hideError();

							/**
							 * Filter collecting async pre-submission work for the
							 * inline login.
							 *
							 * Addons can push promises into the array; the login
							 * request waits for all of them to resolve before being
							 * sent. Useful when an addon needs to perform async work
							 * (e.g. solving an invisible captcha) before the payload
							 * is built.
							 *
							 * @param {Promise[]} promises  The array of pending promises.
							 * @param {string}    fieldType The field type ('email' or 'username').
							 */
							try {

								await Promise.all(hooks.applyFilters('wu_before_inline_login_submitted', [], fieldType));

							} catch (err) {

								handleError({ data: { message: (err && err.message) ? err.message : (wu_checkout.i18n.login_failed || 'Login failed. Please try again.') } });
								return false;

							}

							const username_or_email = fieldType === 'email' ? that.email_address : that.username;

							/**
							 * Filter the inline login request data.
							 *
							 * Addons can hook into this filter to append extra fields
							 * to the inline login request (for example, captcha tokens).
							 *
							 * @param {Object} data      The data object to be sent.
							 * @param {string} fieldType The field type ('email' or 'username').
							 */
							const inline_login_data = hooks.applyFilters('wu_inline_login_data', {
								username_or_email,
								password,
								_wpnonce: jQuery('[name="_wpnonce"]').val()
							}, fieldType);

							jQuery.ajax({
								method: 'POST',
								url: wu_checkout.late_ajaxurl + '&action=wu_inline_login',
								data: inline_login_data,
								success(results) {

									if (results.success) {

										/**
										 * Fires when an inline login attempt succeeds.
										 *
										 * @param {Object} results   The AJAX success response.
										 * @param {string} fieldType The field type.
										 */
										hooks.doAction('wu_inline_login_success', results, fieldType);

										window.location.reload();

									} else {
										handleError(results);
									}

								},
								error: handleError
							});

							return false;

						}

						// Stop all events from bubbling out of the login prompt
						loginPromptContainer.addEventListener('click', function(e) {

							e.stopPropagation();

						});

						loginPromptContainer.addEventListener('keydown', function(e) {

							e.stopPropagation();

						});

						loginPromptContainer.addEventListener('keyup', function(e) {

							e.stopPropagation();

						});

						submitButton.addEventListener('click', handleLogin);

						passwordField.addEventListener('keydown', function(e) {

							if (e.key === 'Enter') {

								handleLogin(e);

							}

						});

						/**
						 * Fires once the inline login prompt is attached and ready.
						 *
						 * Addons can hook into this action to initialize their own
						 * widgets inside the prompt (for example, rendering a captcha
						 * widget whose markup was injected via the
						 * `wu_inline_login_prompt_before_submit` server-side action).
						 *
						 * @param {string}      fieldType            The field type ('email' or 'username').
						 * @param {HTMLElement} loginPromptContainer The prompt container element.
						 */
						hooks.doAction('wu_inline_login_prompt_ready', fieldType, loginPromptContainer);

					});

				},
			},
			updated() {

				this.$nextTick(function () {

					hooks.doAction('wu_on_form_updated', this);

					wu_initialize_tooltip();

					// Setup inline login handlers if prompt is visible
					this.setup_inline_login_handlers();

					// Re-initialize password strength if field appeared after mount
					if (! this.password_strength_checker && jQuery('#field-password').length) {
						this.init_password_strength();
					}

				});

			},
			mounted() {

				const that = this;

				jQuery(this.$el).on('click', function (e) {

					$(this).data('submited_via', $(e.target));

				});

				jQuery(this.$el).on('submit', async function (e) {

					e.preventDefault();

					/**
					 * Handle button submission.
					 */
					const submit_el = jQuery(this).data('submited_via');

					if (submit_el) {

						const new_input = jQuery('<input>');

						new_input.attr('type', 'hidden');

						new_input.attr('name', submit_el.attr('name'));

						new_input.attr('value', submit_el.val());

						jQuery(this).append(new_input);

					} // end if;

					that.block();

					try {

						const promises = [];

						// Here we use filter to return possible promises to await
						await Promise.all(hooks.applyFilters("wu_before_form_submitted", promises, that, that.gateway));

					} catch (error) {

						that.errors = [];

						that.errors.push({
							code: 'before-submit-error',
							message: error.message,
						});

						that.unblock();

						that.handle_errors(error);

						return;

					} // end try;

					that.validate_form();

					hooks.doAction('wu_on_form_submitted', that, that.gateway);

				});

				this.create_order();

				hooks.doAction('wu_checkout_loaded', this);

				hooks.doAction('wu_on_change_gateway', this.gateway, this.gateway);

				// Initialize password strength checker using the shared utility
				this.init_password_strength();

				wu_initialize_tooltip();

			},
			watch: {
				email_address: _.debounce(function(new_value) {

					this.check_user_exists('email', new_value);

				}, 500),
				products(new_value, old_value) {

					this.on_change_product(new_value, old_value);

				},
				toggle_discount_code(new_value) {

					if (! new_value) {

						this.discount_code = '';

					} // end if;

				},
				discount_code(new_value, old_value) {

					this.on_change_discount_code(new_value, old_value);

				},
				gateway(new_value, old_value) {

					this.on_change_gateway(new_value, old_value);

				},
				country(new_value, old_value) {

					this.state = '';

					this.on_change_country(new_value, old_value);

				},
				state(new_value, old_value) {

					this.city = '';

					this.on_change_state(new_value, old_value);

				},
				city(new_value, old_value) {

					this.on_change_city(new_value, old_value);

				},
				duration(new_value, old_value) {

					this.on_change_duration(new_value, old_value);

				},
				duration_unit(new_value, old_value) {

					this.on_change_duration_unit(new_value, old_value);

				},
			},
		});

	});

}(jQuery, wp.hooks, _));
