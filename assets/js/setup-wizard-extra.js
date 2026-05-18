// eslint-disable-next-line no-unused-vars
// eslint-disable-next-line prefer-const
let wu_block_ui_polyfill = function(el) {

	jQuery(el).wu_block({
		message: '<span style="float: none;" class="spinner is-active wu-float-none"></span>',
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

	const el_instance = jQuery(el);

	el_instance.unblock = jQuery(el).wu_unblock;

	return el_instance;

};

(function($) {

	$(document).ready(function() {

		jQuery('[role="tooltip"]').tipTip({
			attribute: 'aria-label',
		});

		$('#poststuff').on('submit', 'form', function() {

			wu_block_ui_polyfill($(this));

		});

	});

}(jQuery));
