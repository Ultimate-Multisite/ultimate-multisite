if (typeof jQuery !== 'undefined') {
	(function($) {
		$(document).ready(function() {
			$('body').on('click', '#wu-toolbox-toggle', function() {
				$(this).parents('#wu-toolbox').toggleClass('wu-toolbox-closed');
			});
		});
	}(jQuery));
}