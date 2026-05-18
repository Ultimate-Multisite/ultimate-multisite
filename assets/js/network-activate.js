/**
 * Network Activate button handler for the Setup Wizard requirements table.
 *
 * Fires an AJAX request to network-activate Ultimate Multisite, then
 * reloads the page on success or shows an error message with a fallback
 * help link on failure.
 *
 * @package
 * @subpackage Assets
 * @since 2.6.0
 */

/* global jQuery, ajaxurl, wu_network_activate */
jQuery(function($) {

	$(document).on('click', '.wu-network-activate-btn', function() {

		const $btn = $(this);
		const $wrapper = $btn.closest('div');
		const nonce = $btn.data('ajax-nonce');
		const $spinner = $wrapper.find('.wu-network-activate-spinner');
		const $message = $wrapper.find('.wu-network-activate-message');
		const $fallback = $wrapper.find('.wu-network-activate-fallback');

		$btn.prop('disabled', true);
		$spinner.addClass('is-active');
		$message.text('');

		$.post(
			ajaxurl,
			{
				action: 'wu_setup_network_activate',
				_ajax_nonce: nonce,
			},
			function(response) {

				if (response.success) {
					window.location.reload();
					return;
				}

				$spinner.removeClass('is-active');
				$btn.hide();
				$fallback.show();

				let errorMsg = wu_network_activate.error_message;

				if (response.data) {
					if (Array.isArray(response.data) && response.data.length > 0 && response.data[ 0 ] && response.data[ 0 ].message) {
						errorMsg = response.data[ 0 ].message;
					} else if (response.data.message) {
						errorMsg = response.data.message;
					}
				}

				$message.text(errorMsg);
			}
		).fail(function() {

			$spinner.removeClass('is-active');
			$btn.hide();
			$fallback.show();
			$message.text(wu_network_activate.error_message);

		});

	});

});
