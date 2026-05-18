/* global wu_block_ui, wu_ajax_error */
(function($) {

	$(document).ready(function() {

		$('#scraper').on('click', function(e) {

			e.preventDefault();

			const block = wu_block_ui('#wp-ultimo-image-widget');

			$('.wu-scraper-note, .wu-scraper-error').hide();

			jQuery.ajax({
				// eslint-disable-next-line no-undef
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'wu_get_screenshot',
					site_id: $('#id').val(),
				},
				error(jqXHR) {

					block.unblock();

					wu_ajax_error(jqXHR);

				},
				success(res) {

					block.unblock();

					if (res.success) {

						$('#wp-ultimo-image-widget img').attr('src', res.data.attachment_url);

						$('#wp-ultimo-image-widget input').val(res.data.attachment_id);

						$('.wu-scraper-note').show();

					} else {

						$('.wu-scraper-error').show();

						$('.wu-scraper-error-message').text(res.data.pop().message);

					} // end if;

				},
			});

		});

	});

}(jQuery));
