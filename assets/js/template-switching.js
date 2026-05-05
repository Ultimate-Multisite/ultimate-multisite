/* global wu_template_switching_params, Vue, wu_create_cookie, wu_listen_to_cookie_change, wu_ajax_error */
(function($, hooks) {

	/*
 * Sets up the cookie listener for template selection.
 */
	hooks.addAction('wu_checkout_loaded', 'nextpress/wp-ultimo', function() {

		/*
     * Resets the template selection cookie.
     */
		wu_create_cookie('wu_template', false);

		/*
     * Listens for changes and set the template if one is detected.
     */
		wu_listen_to_cookie_change('wu_template', function(value) {

			window.wu_template_switching.template_id = value;

		});

	});

	$(document).ready(function() {

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

		hooks.doAction('wu_checkout_loaded');

		// eslint-disable-next-line no-unused-vars
		window.wu_template_switching = new Vue({
			el: '#wp-ultimo-form-wu-template-switching-form',
			data() {

				return {
					template_id: 0,
					original_template_id: -1,
					template_category: '',
					stored_templates: {},
					confirm_switch: 0,

					/*
					 * Drives visibility of the confirm panel. Set true when
					 * the customer picks a grid template (template_id watcher
					 * below) or clicks Reset (reset_template). Set false on
					 * Cancel and after a successful switch. We track this
					 * explicitly rather than deriving it from
					 * (template_id !== original_template_id) because the
					 * Reset flow needs the panel to appear *while*
					 * template_id equals original_template_id, which a
					 * derived check could not express.
					 */
					confirm_active: false,
					ready: false,

					/*
					 * Inline error surface for the confirm panel.
					 *
					 * The legacy error path called wu_ajax_error() which
					 * either shows a SweetAlert modal (when Swal is loaded
					 * — it isn't on customer-panel pages) or prepends a
					 * dismissible notice to the very top of #wpbody-content.
					 * In the customer panel the confirm panel sits well
					 * below the fold on the typical viewport, so a notice
					 * placed at the top of the page is invisible to a
					 * customer who has just clicked "Switch to X". They
					 * see the spinner clear, the panel close, and nothing
					 * else — looking like the action silently failed.
					 *
					 * Setting this string surfaces the error inline inside
					 * the confirm panel itself (see views/ui/template-
					 * switching-confirm.php), right where the customer's
					 * eye is. We keep the wu_ajax_error() call too for
					 * users who scroll up, plus belt-and-braces for the
					 * (rare) case where the panel itself fails to render.
					 */
					error_message: '',
				};

			},
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
			watch: {
				ready() {

					const that = this;

					if (that.ready !== false) {

						that.switch_template();

					} // end if;

				},

				/*
				 * Surface the confirm panel when the customer clicks a Select
				 * button in the grid. Grid buttons live inside the shared
				 * checkout/template-selection clean.php view (which we don't
				 * edit because signup uses it too); they only set
				 * template_id. Watching that change lets us inject the
				 * confirm_active = true behaviour without touching the
				 * shared view.
				 *
				 * Guard: don't flip confirm_active on the initial v-init
				 * assignment (which sets template_id = original_template_id
				 * before the page is interactive). Picking the customer's
				 * existing template from the grid is also a no-op — there's
				 * nothing to confirm.
				 */
				template_id(new_value, old_value) {

					if (old_value === undefined) {

						return;

					} // end if;

					if (new_value === 0 || new_value === '0') {

						this.confirm_active = false;
						return;

					} // end if;

					/*
					 * Defence in depth against directive-ordering races on
					 * mount. The element output co-locates
					 * v-init:original_template_id and v-init:template_id on
					 * the same hidden field, but if anything ever causes the
					 * template_id directive to bind first (Vue plugin order,
					 * future markup change, third-party Vue mixin, etc.) we
					 * would otherwise reach the `confirm_active = true`
					 * branch below with original_template_id still at its
					 * data() sentinel of -1. -1 is a value that no real
					 * template can have, so we treat it as "not yet
					 * initialised" and bail out — the watcher will fire
					 * again on the next legitimate template change.
					 */
					// eslint-disable-next-line eqeqeq
					if (this.original_template_id == -1 || this.original_template_id <= 0) {

						return;

					} // end if;

					// eslint-disable-next-line eqeqeq
					if (new_value == this.original_template_id) {

						// Picking the current template from the grid is a
						// no-op; reset_template() handles the deliberate
						// "Reset" path and will set confirm_active = true
						// itself.
						return;

					} // end if;

					/*
					 * Clear any error message left over from a previous
					 * failed switch attempt. If the customer picks a
					 * different template after seeing an error, they are
					 * starting a new attempt and the stale message would
					 * be misleading.
					 */
					if (this.error_message) {

						this.error_message = '';

					} // end if;

					this.confirm_active = true;

				},
			},
			methods: {
				get_template(template, data) {

					if (typeof data.id === 'undefined') {

						data.id = 'default';

					} // end if;

					const template_name = template + '/' + data.id;

					if (typeof this.stored_templates[ template_name ] !== 'undefined') {

						return this.stored_templates[ template_name ];

					} // end if;

					const template_data = {
						duration: this.duration,
						duration_unit: this.duration_unit,
						products: this.products,
						...data,
					};

					this.fetch_template(template, template_data);

					return '<div class="wu-p-4 wu-bg-gray-100 wu-text-center wu-rounded">Loading</div>';

				},
				fetch_template(template, data) {

					const that = this;

					if (typeof data.id === 'undefined') {

						data.id = 'default';

					} // end if;

					this.request('wu_render_field_template', {
						template,
						attributes: data,
					}, function(results) {

						const template_name = template + '/' + data.id;

						if (results.success) {

							Vue.set(that.stored_templates, template_name, results.data.html);

						} else {

							Vue.set(that.stored_templates, template_name, '<div>' + results.data[ 0 ].message + '</div>');

						} // end if;

					});

				},
				/**
				 * Show the confirm panel for re-applying the customer's
				 * current template. Triggered by the "Reset Current
				 * Template" button in the current-template card.
				 *
				 * Previously this used a native confirm() popup and fired
				 * the switch immediately. The new flow surfaces the same
				 * confirmation card used for switching to a different
				 * template — the customer sees the target thumbnail, name,
				 * and disclaimer before clicking Confirm. Pairing reset
				 * and switch under the same confirmation UI makes the
				 * "you are about to overwrite your site" warning feel
				 * consistent regardless of which path got the customer
				 * here.
				 *
				 * The actual switch is then triggered from the Confirm
				 * button inside the panel (which sets ready = true,
				 * matching the grid switch flow).
				 */
				reset_template() {

					if ( ! this.original_template_id || this.original_template_id <= 0) {

						return;

					} // end if;

					this.template_id     = this.original_template_id;
					this.confirm_active  = true;

					/*
					 * Scroll the confirm panel into view. The current-
					 * template card and the panel can both fit on screen
					 * together for most viewports, but on shorter windows
					 * the panel renders below the fold; without this
					 * scroll, customers reported clicking Reset and seeing
					 * "nothing happen" until they scrolled.
					 */
					this.$nextTick(() => {

						const panel = document.querySelector('.wu-template-switching-confirm');

						if (panel && typeof panel.scrollIntoView === 'function') {

							panel.scrollIntoView({ behavior: 'smooth', block: 'center' });

						} // end if;

					});

				},

				/**
				 * Dismiss the confirm panel without performing a switch.
				 * Resets the queued template selection back to the
				 * customer's active template so the grid no longer shows
				 * a "Selected" highlight on a template they decided not
				 * to switch to.
				 */
				cancel_switch() {

					this.confirm_active = false;
					this.template_id    = this.original_template_id;
					this.error_message  = '';

				},

				/**
				 * Surface an AJAX failure inline in the confirm panel and
				 * also via the global wu_ajax_error() toast/notice.
				 *
				 * Keeping the confirm panel open (confirm_active stays
				 * true) is deliberate: the previous behaviour closed the
				 * panel on failure and let wu_ajax_error() prepend a
				 * notice to the top of #wpbody-content. On the customer
				 * panel that notice is far above the fold from where the
				 * customer just clicked Confirm, so they saw nothing —
				 * the spinner cleared, the panel disappeared, and the
				 * action looked like a silent no-op. Now the error renders
				 * directly inside the panel where the click happened.
				 *
				 * @param {string|null} message Human-readable failure
				 *   reason. Pass null to delegate copy to wu_ajax_error
				 *   (used for network errors where we do not have a
				 *   server-supplied string).
				 */
				show_error(message) {

					this.unblock();
					this.confirm_switch = false;
					this.ready          = false;

					/*
					 * Keep confirm_active true so the panel — which now
					 * carries the inline error block — stays visible.
					 */
					this.confirm_active = true;

					this.error_message  = message || 'An error occurred while switching templates.';

					/*
					 * Belt-and-braces: also show the global notice in case
					 * the panel itself fails to render (e.g. third-party
					 * JS error breaks the Vue update).
					 */
					wu_ajax_error(message);

					/*
					 * Bring the panel into view if the customer scrolled
					 * away or it was rendered below the fold. Without
					 * this, an inline error placed below the visible
					 * region would still go unnoticed.
					 */
					this.$nextTick(() => {

						const panel = document.querySelector('.wu-template-switching-confirm');

						if (panel && typeof panel.scrollIntoView === 'function') {

							panel.scrollIntoView({ behavior: 'smooth', block: 'center' });

						} // end if;

					});

				},
				switch_template() {

					const that = this;

					/*
					 * Clear any error from a previous attempt so the
					 * panel does not show a stale message while the new
					 * request is in flight.
					 */
					that.error_message = '';

					that.block();

					this.request('wu_switch_template', {
						template_id: that.template_id,
					}, function(results) {

						/*
             * Defensive guard — if the server responds with an empty,
             * non-JSON, or otherwise malformed body (which previously
             * happened on the override_site() failure path), treat it
             * as a generic failure so the customer is not left staring
             * at the loading spinner forever.
             */
						if ( ! results || typeof results !== 'object') {

							that.show_error('An error occurred while switching templates.');

							return;

						}

						/*
             * Handle error responses.
             */
						if (results.success === false) {

							let errorMessage = 'An error occurred while switching templates.';

							if (results.data && results.data.message) {

								errorMessage = results.data.message;

							} else if (results.data && Array.isArray(results.data) && results.data[ 0 ] && results.data[ 0 ].message) {

								errorMessage = results.data[ 0 ].message;

							}

							that.show_error(errorMessage);

							return;

						}

						/*
             * Redirect if we get a redirect URL back. Guard against
             * a missing or malformed data payload so a server that
             * answers success without a redirect URL does not throw
             * inside this callback (which would silently leave the
             * page-blocking spinner active).
             */
						if (results.data && typeof results.data.redirect_url === 'string') {

							window.location.href = results.data.redirect_url;

							return;

						} // end if;

						/*
             * Success without a redirect URL — unblock and reset
             * state so the customer can try again instead of being
             * stuck on a loading overlay.
             */
						that.unblock();

						that.confirm_switch = false;
						that.confirm_active = false;

						that.ready = false;

					}, function() {

						/*
             * Handle network errors. wu_ajax_error(null) lets that
             * helper fill in its own "network error" copy; mirror it
             * inline in the panel so the customer sees the same
             * information without scrolling.
             */
						that.show_error('A network error occurred. Please check your connection and try again.');

					});

				},
				block() {

					/*
           * Get the first bg color from a parent.
           */
					const bg_color = jQuery(this.$el).parents().filter(function() {

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

					jQuery.ajax({
						method: 'POST',
						url: wu_template_switching_params.ajaxurl + '&action=' + action,
						data,
						success: success_handler,
						error: error_handler,
					});

				},
			},
		});

	});

}(jQuery, wp.hooks));
