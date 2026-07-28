/* global wu_setup, wu_setup_settings, ajaxurl, wu_block_ui_polyfill, _wu_block_ui_polyfill  */
(function($) {

	window._wu_block_ui_polyfill = wu_block_ui_polyfill;

	wu_block_ui_polyfill = function() { };

	$(document).ready(function() {

		// Click button
		// Generates queue
		// Start to process queue items one by one
		// Changes the status
		// Move to the next item
		// When all is done, redirect to the next page via a form submission
		$('#poststuff').on('submit', 'form', function(e) {

			e.preventDefault();

			const $form = $(this);

			const install_id = $form.find('table[data-id]').data('id');

			$form.find('[name=next]').attr('disabled', 'disabled');

			let queue = $form.find('tr[data-content]');

			/*
       * Only keep items selected on the queue.
       */
			queue = queue.filter(function() {

				const checkbox = $(this).find('input[type=checkbox]');

				if (checkbox.length) {

					return checkbox.is(':checked');

				} // end if;

				return true;

			});

			let successes = 0;

			let index = 0;

			process_queue_item(queue.eq(index));

			/**
			 * Process the queue items one by one recursively.
			 *
			 * @param {string} item The item to process.
			 */
			function process_queue_item(item) {

				window.onbeforeunload = function() {

					return '';

				};

				if (item.length === 0) {

					if (queue.length === successes || install_id === 'migration') {

						window.onbeforeunload = null;

						_wu_block_ui_polyfill($('#poststuff .inside'));

						setTimeout(() => {

							$form.get(0).submit();

						}, 100);

					} // end if;

					$form.find('[name=next]').removeAttr('disabled');

					return false;

				} // end if;

				const $item = $(item);

				const content = $item.data('content');

				$item.get(0).scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });

				$item.find('td.status')
					.attr('class', '')
					.addClass('status')
					.find('> span').html(wu_setup[ content ].installing).end()
					.find('.spinner').addClass('is-active').end()
					.find('a.help').slideUp();

				/**
				 * Move to the next selected installer row.
				 */
				function process_next_item() {

					index++;

					process_queue_item(queue.eq(index));

				} // end process_next_item;

				/**
				 * Display an installer error and continue processing the queue.
				 *
				 * @param {string} errorMessage The message to display.
				 */
				function show_error(errorMessage) {

					$item.find('td.status')
						.attr('class', '')
						.addClass('status wu-text-red-400')
						.find('> span').html(errorMessage).end()
						.find('.spinner').removeClass('is-active').end()
						.find('a.help').slideDown();

					process_next_item();

				} // end show_error;

				/**
				 * Mark the current row complete after all of its actions succeed.
				 */
				function mark_complete() {

					$item.find('td.status')
						.attr('class', '')
						.addClass('status wu-text-green-600')
						.find('> span').html(wu_setup[ content ].success).end()
						.find('.spinner').removeClass('is-active');

					$item.removeAttr('data-content');

					successes++;

					process_next_item();

				} // end mark_complete;

				/**
				 * Run one installer action for the current row.
				 *
				 * @param {string}   installer The installer action name.
				 * @param {Function} onSuccess Callback after the action succeeds.
				 */
				function run_installer(installer, onSuccess) {

					$.ajax({
						url: ajaxurl,
						method: 'post',
						data: {
							action: wu_setup_settings.ajax_action || 'wu_setup_install',
							installer,
							'dry-run': wu_setup_settings.dry_run,
							_wpnonce: wu_setup_settings.install_nonce,
						},
						success(data) {

							if (data.success === true) {

								onSuccess();

								return;

							} // end if;

							show_error(data.data[ 0 ].message);

						},
						error(jqXHR) {

							let errorMessage = wu_setup_settings.generic_error_message || 'An error occurred.';

							if (jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data[ 0 ]) {
								errorMessage = jqXHR.responseJSON.data[ 0 ].message || errorMessage;
							}

							show_error(errorMessage);

						},
					});

				} // end run_installer;

				run_installer(content, function() {

					if (wu_setup[ content ].activation) {

						$item.find('td.status > span').html(wu_setup[ content ].activating);

						run_installer(wu_setup[ content ].activation, mark_complete);

						return;

					} // end if;

					mark_complete();

				});

			} // end process_queue_item;

		});

		$('#poststuff [name=next]').removeAttr('disabled');

	});

}(jQuery));

if (typeof wu_initialize_tooltip !== 'function') {

	const wu_initialize_tooltip = function() {

		jQuery('[role="tooltip"]').tipTip({
			attribute: 'aria-label',
		});

	}; // end wu_initialize_tooltip;

	// eslint-disable-next-line no-unused-vars
	const wu_block_ui = function(el) {

		jQuery(el).wu_block({
			message: '<span>Please wait...</span>',
			overlayCSS: {
				backgroundColor: '#FFF',
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

		return jQuery(el);

	};

	(function($) {

		$(document).ready(function() {

			wu_initialize_tooltip();

		});

	}(jQuery));

} // end if;
