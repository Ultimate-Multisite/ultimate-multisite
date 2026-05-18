/* eslint-disable no-undef */
(function($, hooks) {

	/**
	 * Fixes the value of the action for deleting pending sites.
	 */
	hooks.addAction('wu_list_table_update', 'nextpress/wp-ultimo', function(results, data, $parent) {

		if (results.type === 'pending' && data.table_id === 'site_list_table') {

			$parent.find('select[name^=action] > option[value=delete]').attr('value', 'delete-pending');

		} else {

			$parent.find('select[name^=action] > option[value=delete-pending]').attr('value', 'delete');

		} // end if;

	});

	/**
	 * Select All
	 */
	$(document).on('click', '#cb-select-all-grid', function(e) {

		e.preventDefault();

		const checkboxes = jQuery(this)
			.parents('form')
			.find('#the-list')
			.find('input[type=checkbox]');

		const checked = checkboxes.prop('checked');

		checkboxes.parents('.wu-grid-item')
			.toggleClass('wu-grid-item-selected', ! checked);

		checkboxes.prop('checked', ! checked);

	});

	$(document).on('change', '.wu-grid-item input[type=checkbox]', function() {

		const checked = $(this).prop('checked');

		$(this).parents('.wu-grid-item')
			.toggleClass('wu-grid-item-selected', checked);

	});

	/**
	 * Function that creates the code to handle the AJAX functionality of the WU List Tables.
	 *
	 * @param id
	 * @param count
	 * @param filters
	 * @param date_filters
	 */
	// eslint-disable-next-line no-undef
	wu_create_list = function(id) {

		return {

			/**
			 * Table element ID
			 */
			el: '#' + id,

			/**
			 * Filters ID
			 */
			filters_el: '#' + id + '-filters',

			/**
			 * Wether or not the table was initialized.
			 */
			initialized: false,

			/**
			 * Register our triggers
			 *
			 * We want to capture clicks on specific links, but also value change in
			 * the pagination input field. The links contain all the information we
			 * need concerning the wanted page number or ordering, so we'll just
			 * parse the URL to extract these variables.
			 *
			 * The page number input is trickier: it has no URL so we have to find a
			 * way around. We'll use the hidden inputs added in TT_Example_List_Table::display()
			 * to recover the ordering variables, and the default paged input added
			 * automatically by WordPress.
			 */
			init() {

				const table = this;

				const $table_parent = $('#wu-' + id);

				// This will have its utility when dealing with the page number input
				let timer;

				const delay = 500;

				/*
         * Handles bulk-delete.
         */
				jQuery('body').on('click', '#doaction, #doaction2', function(event) {

					const form = jQuery(event.target).parents('form');

					const form_data = form.serialize();

					const params = new URL('https://example.com?' + form_data);

					const action = params.searchParams.get('action') || params.searchParams.get('action2');

					const ids = params.searchParams.getAll('bulk-delete[]');

					if (action !== '-1' && ids.length) {

						event.preventDefault();

					} else {

						return;

					}// end if;

					params.searchParams.set('bulk_action', action);

					params.searchParams.forEach((value, key) => {

						if (key !== 'bulk_action' && key !== 'bulk-delete[]') {

							params.searchParams.delete(key);

						} // end if;

					});

					params.searchParams.set('model', wu_list_table.model);

					wubox.show(wu_list_table.i18n.confirm, wu_list_table.base_url + '&' + params.searchParams.toString());

				});

				// Pagination links, sortable link
				$table_parent.on('click', '.tablenav-pages a, .manage-column.sortable a, .manage-column.sorted a', function(e) {

					// We don't want to actually follow these links
					e.preventDefault();

					// Simple way: use the URL to extract our needed variables
					const query = this.search.substring(1);

					const data = $.extend({}, table.__get_query(query), {
						order: table.__query(query, 'order') || 'DESC',
						paged: table.__query(query, 'paged') || '1',
						s: table.__query(query, 's') || '',
					});

					table.update(data);

				});

				// Page number input
				$table_parent.on('keyup', 'input[name=paged]', function(e) {

					// If user hit enter, we don't want to submit the form
					// We don't preventDefault() for all keys because it would
					// also prevent to get the page number!
					if (13 === e.which) {

						e.preventDefault();

					} // end if;

					// This time we fetch the variables in inputs
					const data = {
						paged: parseInt($('input[name=paged]').val()) || '1',
						s: $('input[name=s]').val() || '',
					};

					// Now the timer comes to use: we wait half a second after
					// the user stopped typing to actually send the call. If
					// we don't, the keyup event will trigger instantly and
					// thus may cause duplicate calls before sending the intended
					// value
					window.clearTimeout(timer);

					timer = window.setTimeout(function() {

						table.update(data);

					}, delay);

				});

				/**
				 * Initializes filters
				 */
				if (table.initialized === false && $(table.filters_el).get(0)) {

					table.filters = table.init_filters();

				} // end if;

				table.initialized = true;

				return table;

			},

			/**
			 * Copy a object
			 *
			 * @param {Object} obj
			 */
			copy: function copy(obj) {

				return JSON.parse(JSON.stringify(obj));

			},

			/**
			 * Setup up the filters
			 */
			init_filters() {

				if (typeof window.Vue === 'undefined') {

					return;

				} // end if;

				const table = this;

				const available_filters = table.copy(window[ id + '_config' ].filters);

				// eslint-disable-next-line no-undef
				return new Vue({
					el: table.filters_el,
					data() {

						return {
							open: true,
							view: false,
							// relation: 'and',
							available_filters: [], // table.copy(available_filters),
							filters: [], //[_.first(table.copy(available_filters))],
						};

					},
					computed: {
					},
					mounted() {

						let timer;

						const delay = 500;

						wu_on_load();

						$(table.filters_el + ' form.search-form').on('submit', function(e) {

							e.preventDefault();

						});

						// Page number input
						$(table.filters_el + ' input[name=s]').on('input keyup', function(e) {

							// If user hit enter, we don't want to submit the form
							// We don't preventDefault() for all keys because it would
							// also prevent to get the page number!
							if (13 === e.which) {

								e.preventDefault();

							} // end if;

							// This time we fetch the variables in inputs
							const data = {
								paged: parseInt($('input[name=paged]').val()) || '1',
								s: $('input[name=s]').val() || '',
							};

							// Fix paged
							if ($('input[name=s]').val() !== '') {

								data.paged = '1';

							} // end if;

							// Now the timer comes to use: we wait half a second after
							// the user stopped typing to actually send the call. If
							// we don't, the keyup event will trigger instantly and
							// thus may cause duplicate calls before sending the intended
							// value
							window.clearTimeout(timer);

							timer = window.setTimeout(function() {

								table.update(data);

							}, delay);

						});

					},
					methods: {
						set_view(type, value) {

							const query = window.location.href.split('?')[ 1 ];

							const data = $.extend({}, table.__get_query(query), {
								paged: table.__query(query, 'paged') || '1',
								s: table.__query(query, 's') || '',
							});

							this.view = value;

							data[ type ] = value;

							jQuery('.wu-filter .current').removeClass('current');

							table.update(data);

						},
						get_filter_type(field) {

							const available_filter = _.findWhere(available_filters, {
								field,
							});

							return available_filter.type;

						},
						get_filter_rule(field) {

							const available_filter = _.findWhere(available_filters, {
								field,
							});

							return available_filter.rule;

						},
						remove_filter(index) {

							this.filters.splice(index, 1);

						},
						add_new_filter() {

							this.filters.push(_.first(table.copy(this.available_filters)));

						},
						open_filters() {

							this.open = true;

						},
						close_filters() {

							this.open = false;

						},
					},
				});

			},

			set_history(data) {

				if (window[ id + '_config' ].context !== 'page') {

					return;

				}

				/** Update History */
				try {

					const history_vars = _.omit(data, function(value, key) {

						return key === 'action' ||
              key === 'table_id' ||
              ! value ||
              key.indexOf('_') === 0;

					});

					history.pushState({}, null, '?' + $.param(history_vars));

				} catch (err) {

					// eslint-disable-next-line no-console
					console.warn('Browser does not support pushState.', err);

				} // end try;

			},

			/**
			 * AJAX call
			 *
			 * Send the call and replace table parts with updated version!
			 *
			 * @param {Object} data The data to pass through AJAX
			 */
			update(data) {

				const table = this;

				const $table_parent = $('#wu-' + id);

				const default_data = {
					action: 'wu_list_table_fetch_ajax_results',
					table_id: id,
					id: $('input#id').val(),
				};

				default_data[ '_ajax_' + id + '_nonce' ] = $('#_ajax_' + id + '_nonce').val();

				const form_data = $.extend(
					{},
					default_data,
					// $($table_parent).find('input').serializeObject(),
					// $(table.filters_el).find('input').serializeObject(),
					data,
				);

				const $content = $table_parent.find('tbody, .wu-grid-content');

				$content.animate({ opacity: 0.4 }, 300);

				$.ajax({
					// eslint-disable-next-line no-undef
					url: ajaxurl,
					// Add action and nonce to our collected data
					data: form_data,
					// Handle the successful result
					statusCode: {
						403() {

							$content.animate({ opacity: 1 }, 300);

						},
					},
					success(response) {

						table.set_history(form_data, default_data);

						$content.animate({ opacity: 1 }, 300);

						// Add the requested rows
						if (typeof response.rows !== 'undefined') {

							$content.html(response.rows);

						} // end if;

						// Add the requested rows
						if (typeof response.count !== 'undefined') {

							// table.filters.count = response.count;

						} // end if;

						// Update column headers for sorting
						if (response.column_headers.length) {

							$table_parent.find('thead tr, tfoot tr').html(response.column_headers);

						} // end if;

						// Update pagination for navigation
						if (response.pagination.top.length) {

							$table_parent.find('.tablenav.top .tablenav-pages').html($(response.pagination.top).html());

						} // end if;

						if (response.pagination.bottom.length) {

							$table_parent.find('.tablenav.bottom .tablenav-pages').html($(response.pagination.bottom).html());

						} // end if;

						hooks.doAction('wu_list_table_update', response, form_data, $table_parent);

						// Init back our event handlers
						// table.init();

					},
					error(jqXHR) {

						$content.animate({ opacity: 1 }, 300);

						// eslint-disable-next-line no-undef
						wu_ajax_error(jqXHR);

					},
				});

			},

			/**
			 * Filter the URL Query to extract variables
			 *
			 * @see http://css-tricks.com/snippets/javascript/get-url-variables/
			 * @param {string} query    The URL query part containing the variables
			 * @param {string} variable The URL query part containing the variables
			 * @return {string|boolean} The variable value if available, false else.
			 */
			__query(query, variable) {

				const vars = query.split('&');

				for (let i = 0; i < vars.length; i++) {

					const pair = vars[ i ].split('=');

					if (pair[ 0 ] === variable) {

						return pair[ 1 ];

					} // end if;

				} // end for;

				return false;

			},

			__get_query(query) {

				const vars = query.split('&');

				const _query = {};

				for (let i = 0; i < vars.length; i++) {

					const pair = vars[ i ].split('=');

					_query[ pair[ 0 ] ] = pair[ 1 ];

				} // end for;

				return _query;

			},

		};

	}; // end wu_create_list;

}(jQuery, wp.hooks));
