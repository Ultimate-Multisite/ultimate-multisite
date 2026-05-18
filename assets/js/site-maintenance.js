/* global wu_site_maintenance, wu_block_ui, wu_ajax_error */
(function($) {

	$(document).ready(function() {

		$('#wu-tg-maintenance_mode').change(function() {

			const blocked = wu_block_ui('#wp-ultimo-site-maintenance-element .inside');

			jQuery.ajax({
				url: wu_site_maintenance.ajaxurl,
				type: 'post',
				data: {
					action: 'toggle_maintenance_mode',
					maintenance_status: $('#wu-tg-maintenance_mode').is(':checked'),
					site_hash: $('[name=site_hash]').val(),
					_wpnonce: wu_site_maintenance.nonce,
				},
				success(response) {

					blocked.unblock();

					if (response.success) {

						if (response.data.value) {

							$('#wp-admin-bar-wu-maintenance-mode').show();

						} else {

							$('#wp-admin-bar-wu-maintenance-mode').hide();

						} // end if;

					}

				}, // end success;

				error(jqXHR) {

					blocked.unblock();

					wu_ajax_error(jqXHR);

				}, // end error;

			}); // end ajax;

		}); //end checked

	});

}(jQuery));
