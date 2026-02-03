/* global Vue, moment, _, wu_checkout, wu_checkout_form, wu_create_cookie, wu_listen_to_cookie_change */
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
      discount_code: wu_checkout.discount_code,
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

    if (!jQuery('#wu_form').length) {

      return;

    } // end if;

    /**
     * ColorPicker Component
     */
    Vue.component('colorPicker', {
      props: ['value'],
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
      props: ['template'],
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

            vnode.context[binding.arg] = binding.value;

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

          if (typeof this.stored_templates[template_name] !== 'undefined') {

            return this.stored_templates[template_name];

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

              new_list[key] = item;

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

              Vue.set(that.stored_templates, template_name, '<div>' + results.data[0].message + '</div>');

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

          return this.custom_amounts[product_id] || null;

        },
        set_pwyw_recurring(product_id, is_recurring) {

          Vue.set(this.pwyw_recurring, product_id, Boolean(is_recurring));

          this.create_order();

        },
        get_pwyw_recurring(product_id) {

          return this.pwyw_recurring[product_id] || false;

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
          if (!jQuery('#wu-order-summary-content').length) {

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

          return result.length > 0 ? result[0] : false;

        },
        form_success(results) {

          if (!_.isEmpty(results.data)) {

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
        validate_form() {

          this.errors = [];

          const form_data_obj = jQuery('#wu_form').serializeArray().reduce(function (json, { name, value }) {

            // Get products from this
            if (name !== 'products[]') {

              json[name] = value;

            }

            return json;

          }, {});

          const form_data = jQuery.param({
            ...form_data_obj,
            products: this.products,
            membership_id: this.membership_id,
            payment_id: this.payment_id,
            auto_renew: this.auto_renew,
            cart_type: this.type,
            valid_password: this.valid_password,
            duration: this.duration,
            duration_unit: this.duration_unit,
          });

          const that = this;

          this.request('wu_validate_form', form_data, function (results) {

            if (!that.valid_password) {

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

            if (!that.errors.length) {

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

          this.reset_templates(['template-selection']);

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

          if (!pass1_el.length) {

            return;

          } // end if;

          // Use the shared WU_PasswordStrength utility
          if (typeof window.WU_PasswordStrength !== 'undefined') {

            this.password_strength_checker = new window.WU_PasswordStrength({
              pass1: pass1_el,
              result: jQuery('#pass-strength-result'),
              minStrength: 3,
              onValidityChange: function(isValid) {

                that.valid_password = isValid;

              }
            });

          } // end if;

        },
        check_user_exists_debounced: _.debounce(function(field_type, value) {

          this.check_user_exists(field_type, value);

        }, 500),
        check_user_exists(field_type, value) {

          // Don't check if value is too short
          if (!value || value.length < 3) {

            this.show_login_prompt = false;

            return;

          }

          this.checking_user_exists = true;
          this.login_error = '';

          const that = this;

          this.request('wu_check_user_exists', {
            field_type: field_type,
            value: value,
            _wpnonce: jQuery('[name="_wpnonce"]').val()
          }, function(results) {

            that.checking_user_exists = false;

            if (results.success && results.data.exists) {

              that.show_login_prompt = true;
              that.login_prompt_field = field_type;

            } else {

              that.show_login_prompt = false;

            }

          }, function(error) {

            that.checking_user_exists = false;
            that.show_login_prompt = false;

          });

        },
        handle_inline_login(event) {

          console.log('handle_inline_login called', event);

          // Prevent any default behavior or form submission
          if (event) {

            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();

          }

          if (!this.inline_login_password) {

            this.login_error = wu_checkout.i18n.password_required || 'Password is required';

            return false;

          }

          this.logging_in = true;
          this.login_error = '';

          const that = this;
          const username_or_email = this.login_prompt_field === 'email'
            ? this.email_address || ''
            : this.username || '';

          this.request('wu_inline_login', {
            username_or_email: username_or_email,
            password: this.inline_login_password,
            _wpnonce: jQuery('[name="_wpnonce"]').val()
          }, function(results) {

            that.logging_in = false;

            if (results.success) {

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

          });

          return false;

        },
        dismiss_login_prompt() {

          this.show_login_prompt = false;
          this.inline_login_password = '';
          this.login_error = '';

        },
        setup_inline_login_handlers() {

          const that = this;

          // Setup handlers for both email and username field types
          ['email', 'username'].forEach(function(fieldType) {

            const passwordField = document.getElementById('wu-inline-login-password-' + fieldType);
            const submitButton = document.getElementById('wu-inline-login-submit-' + fieldType);
            const dismissButton = document.getElementById('wu-dismiss-login-prompt-' + fieldType);
            const errorDiv = document.getElementById('wu-login-error-' + fieldType);
            const loginPromptContainer = document.getElementById('wu-inline-login-prompt-' + fieldType);

            if (!passwordField || !submitButton) return;

            // Remove any existing listeners to avoid duplicates
            const newSubmitButton = submitButton.cloneNode(true);
            submitButton.parentNode.replaceChild(newSubmitButton, submitButton);

            const newPasswordField = passwordField.cloneNode(true);
            passwordField.parentNode.replaceChild(newPasswordField, passwordField);
			function handleError(error) {

				newSubmitButton.disabled = false;
				newSubmitButton.textContent = wu_checkout.i18n.sign_in || 'Sign in';

				if (error.data && error.data.message) {

					errorDiv.textContent = error.data.message;

				} else {

					errorDiv.textContent = wu_checkout.i18n.login_failed || 'Login failed. Please try again.';

				}

				errorDiv.style.display = 'block';


			}

            function handleLogin(e) {

              e.preventDefault();
              e.stopPropagation();
              e.stopImmediatePropagation();

              const password = newPasswordField.value;

              if (!password) {

                errorDiv.textContent = wu_checkout.i18n.password_required || 'Password is required';
                errorDiv.style.display = 'block';

                return false;

              }

              newSubmitButton.disabled = true;
              newSubmitButton.innerHTML = '<span class="spinner is-active wu-inline-block" style="float: none; width: 16px; height: 16px; margin: 0 4px 0 0;"></span>' + (wu_checkout.i18n.logging_in || 'Logging in...');
              errorDiv.style.display = 'none';

              const username_or_email = fieldType === 'email' ? that.email_address : that.username;

              jQuery.ajax({
                method: 'POST',
                url: wu_checkout.late_ajaxurl + '&action=wu_inline_login',
                data: {
                  username_or_email: username_or_email,
                  password: password,
                  _wpnonce: jQuery('[name="_wpnonce"]').val()
                },
                success: function(results) {

                  if (results.success) {

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
            if (loginPromptContainer) {

              loginPromptContainer.addEventListener('click', function(e) {

                e.stopPropagation();

              });

              loginPromptContainer.addEventListener('keydown', function(e) {

                e.stopPropagation();

              });

              loginPromptContainer.addEventListener('keyup', function(e) {

                e.stopPropagation();

              });

            }

            newSubmitButton.addEventListener('click', handleLogin);

            newPasswordField.addEventListener('keydown', function(e) {

              if (e.key === 'Enter') {

                handleLogin(e);

              }

            });

            if (dismissButton) {

              dismissButton.addEventListener('click', function(e) {

                e.preventDefault();
                e.stopPropagation();
                that.show_login_prompt = false;
                that.inline_login_password = '';
                newPasswordField.value = '';

              });

            }

          });

        },
      },
      updated() {

        this.$nextTick(function () {

          hooks.doAction('wu_on_form_updated', this);

          wu_initialize_tooltip();

          // Setup inline login handlers if prompt is visible
          this.setup_inline_login_handlers();

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
        products(new_value, old_value) {

          this.on_change_product(new_value, old_value);

        },
        toggle_discount_code(new_value) {

          if (!new_value) {

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
