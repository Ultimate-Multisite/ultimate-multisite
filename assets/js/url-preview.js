(function($) {

	$(document).ready(function() {

		/**
		 * Signup change value
		 */
		$('.login').on('keyup', '#field-site_url', function(event) {

			event.preventDefault();

			const $selector = $(this);

			const $target = $('#wu-your-site');

			$target.text($selector.val());

		}); // end on.keyUp;

		$('.login').on('keyup', '#field-site_url', function(event) {

			event.preventDefault();

			const $selector = $(this);

			const $target = $('#wu-your-site');

			$target.text($selector.val());

		}); // end on.keyUp;

		$('.login').on('change', '#domain_option', function(event) {

			event.preventDefault();

			const $selector = $(this);

			const $target = $('#wu-site-domain');

			$target.text($selector.val());

		}); // end on.keyUp;

	});

}(jQuery));
