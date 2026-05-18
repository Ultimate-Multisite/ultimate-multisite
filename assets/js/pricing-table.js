(function($) {
	$(document).ready(function() {
		/**
		 * Select the default pricing option
		 */
		setTimeout(function() {
			$('[data-frequency-selector="' + wu_default_pricing_option + '"]').click();
		}, 100);
	});
}(jQuery));