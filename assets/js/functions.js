/* eslint-disable no-undef */
/* eslint-disable no-unused-vars */
/* global wu_settings, wu_input_masks, wu_money_input_masks, Cleave, ClipboardJS, wu_fields, tinymce, wu_media_frame, fontIconPicker */
window.wu_initialize_tooltip = function() {

	jQuery('[role="tooltip"]').tipTip({
		attribute: 'aria-label',
	});

}; // end wu_initialize_tooltip;

window.wu_initialize_editors = function() {

	jQuery('textarea[data-editor]').each(function() {

		tinymce.remove('#' + jQuery(this).attr('id'));

		tinymce.init({
			selector: '#' + jQuery(this).attr('id'), // change this value according to your HTML
			menubar: '',
			theme: 'modern',
			...wp.editor.getDefaultSettings().tinymce,
		});

	});

}; // end wu_initialize_editors

window.wu_initialize_imagepicker = function() {

	jQuery('.wu-wrapper-image-field').each(function() {

		const that = jQuery(this);

		that.find('img').css({
			maxWidth: '100%',
		});

		const value = that.find('img').attr('src');

		if (value) {

			that.find('.wu-wrapper-image-field-upload-actions').show();

		} else {

			that.find('.wu-add-image-wrapper').show();

		} // end if;

		that.on('click', 'a.wu-add-image', function() {

			if (typeof wu_media_frame !== 'undefined') {

				wu_media_frame.open();

				return;

			} // end if;

			wu_media_frame = wp.media({
				title: wu_fields.l10n.image_picker_title,
				multiple: false,
				button: {
					text: wu_fields.l10n.image_picker_button_text,
				},
			});

			wu_media_frame.on('select', function() {

				const mediaObject = wu_media_frame.state().get('selection').first().toJSON();

				const img_el = that.find('img');

				that.find('img').removeClass('wu-absolute').attr('src', mediaObject.url);

				that.find('.wubox').attr('href', mediaObject.url);

				that.find('input').val(mediaObject.id);

				that.find('.wu-add-image-wrapper').hide();

				img_el.on('load', function() {

					that.find('.wu-wrapper-image-field-upload-actions').show();

				});

			});

			wu_media_frame.open();

		});

		that.find('.wu-remove-image').on('click', function(e) {

			e.preventDefault();

			that.find('img').removeAttr('src').addClass('wu-absolute');

			that.find('input').val('');

			that.find('.wu-wrapper-image-field-upload-actions').hide();

			that.find('.wu-add-image-wrapper').show();

		});

	});

}; // end wu_initialize_imagepicker

window.wu_initialize_colorpicker = function() {

	jQuery(document).ready(function() {

		jQuery('.wu_color_field').each(function() {

			jQuery(this).wpColorPicker();

		});

	});

}; // end wu_initialize_colorpicker;

window.wu_initialize_iconfontpicker = function() {

	jQuery(document).ready(function() {

		if (jQuery('.wu_select_icon').length) {

			jQuery('.wu_select_icon').fontIconPicker({
				theme: 'wu-theme',
			});

		}

	});

}; // end wu_initialize_iconfontpicker;

window.wu_initialize_clipboardjs = function() {

	// Prevent page jump on copy link click
	jQuery(document).off('click.wu-copy').on('click.wu-copy', 'a.wu-copy[href="#"]', function(e) {
		e.preventDefault();
	});

	// Destroy previous instance to avoid duplicate handlers on repeated calls
	if (window._wu_clipboard_instance) {
		window._wu_clipboard_instance.destroy();
	}

	function showCopyFeedback(trigger) {
		const $trigger = jQuery(trigger);
		const $textNodes = $trigger.contents().filter(function() {
			return this.nodeType === 3 && this.textContent.trim().length > 0;
		});

		if ($textNodes.length) {
			const node = $textNodes[ 0 ];
			const originalText = node.textContent;
			node.textContent = ' Copied!';
			setTimeout(function() {
				node.textContent = originalText;
			}, 2000);
		}
	}

	if (typeof ClipboardJS !== 'undefined') {
		window._wu_clipboard_instance = new ClipboardJS('.wu-copy');

		window._wu_clipboard_instance.on('success', function(e) {
			showCopyFeedback(e.trigger);
			e.clearSelection();
		});

		window._wu_clipboard_instance.on('error', function(e) {
			const text = e.trigger.getAttribute('data-clipboard-text');
			if (text && navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text).then(function() {
					showCopyFeedback(e.trigger);
				});
			}
		});
	} else {
		// Fallback when ClipboardJS is not available
		jQuery(document).off('click.wu-copy-fallback').on('click.wu-copy-fallback', '.wu-copy', function() {
			const text = jQuery(this).attr('data-clipboard-text');
			const trigger = this;
			if (text && navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text).then(function() {
					showCopyFeedback(trigger);
				});
			}
		});
	}

}; // end wu_initialize_clipboardjs;

// DatePicker;
window.wu_initialize_datepickers = function() {

	jQuery('.wu-datepicker, [wu-datepicker]').each(function() {

		const $this = jQuery(this);

		const format = $this.data('format'),
			allow_time = $this.data('allow-time');

		$this.flatpickr({
			animate: false,
			// locale: wpu.datepicker_locale,
			time_24hr: true,
			enableTime: typeof allow_time === 'undefined' ? true : allow_time,
			dateFormat: format,
			allowInput: true,
			defaultDate: $this.val(),
		});

	});

}; // end wu_initialize_datepickers;

window.wu_update_clock = function() {

	// eslint-disable-next-line no-undef
	const yourTimeZoneFrom = wu_ticker.server_clock_offset; // time zone value where you are at

	const d = new Date();
	//get the timezone offset from local time in minutes

	// eslint-disable-next-line no-mixed-operators
	const tzDifference = yourTimeZoneFrom * 60 + d.getTimezoneOffset();

	//convert the offset to milliseconds, add to targetTime, and make a new Date
	const offset = tzDifference * 60 * 1000;

	function callback_update_clock() {

		const tDate = new Date(new Date().getTime() + offset);

		const in_years = tDate.getFullYear();

		let in_months = tDate.getMonth() + 1;

		let in_days = tDate.getDate();

		let in_hours = tDate.getHours();

		let in_minutes = tDate.getMinutes();

		let in_seconds = tDate.getSeconds();

		if (in_months < 10) {

			in_months = '0' + in_months;

		}

		if (in_days < 10) {

			in_days = '0' + in_days;

		}

		if (in_minutes < 10) {

			in_minutes = '0' + in_minutes;

		}

		if (in_seconds < 10) {

			in_seconds = '0' + in_seconds;

		}

		if (in_hours < 10) {

			in_hours = '0' + in_hours;

		}

		jQuery('#wu-ticker').text(in_years + '-' + in_months + '-' + in_days + ' ' + in_hours + ':' + in_minutes + ':' + in_seconds);

	}

	function start_clock() {

		setInterval(callback_update_clock, 500);

	}

	start_clock();

};

// eslint-disable-next-line no-unused-vars
function wu_on_load() {

	wu_initialize_tooltip();

	wu_initialize_datepickers();

	wu_initialize_colorpicker();

	wu_initialize_iconfontpicker();

	wu_initialize_editors();

	wu_update_clock();

	wu_initialize_clipboardjs();

	wu_initialize_imagepicker();

	wu_image_preview();

} // end wu_on_load;

window.wu_on_load = wu_on_load;

// eslint-disable-next-line no-unused-vars
window.wu_block_ui = function(el) {

	jQuery(el).wu_block({
		message: '<div class="spinner is-active wu-float-none" style="float: none !important;"></div>',
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

function wu_format_money(value) {

	value = parseFloat(value.toString().replace(/[^0-9\.]/g, ''));

	const settings = wp.hooks.applyFilters('wu_format_money', {
		currency: {
			symbol: wu_settings.currency_symbol, // default currency symbol is '$'
			format: wu_settings.currency_position, // controls output: %s = symbol, %v = value/number (can be object: see below)
			decimal: wu_settings.decimal_separator, // decimal point separator
			thousand: wu_settings.thousand_separator, // thousands separator
			precision: wu_settings.precision, // decimal places
		},
		number: {
			precision: 0, // default precision on numbers is 0
			thousand: ',',
			decimal: ',',
		},
	});

	accounting.settings = settings;

	return accounting.formatMoney(value);

} // end wu_format_money;

window.wu_image_preview = function() {

	const xOffset = 10;

	const yOffset = 30;

	const preview_el = '#wu-image-preview';

	// eslint-disable-next-line eqeqeq
	const selector = wu_settings.disable_image_zoom == true ? '.wu-image-preview:not(img)' : '.wu-image-preview';

	const el_id = preview_el.replace('#', '');

	if (jQuery(preview_el).length === 0) {

		jQuery('body').append(
			"<div id='" + el_id + "' class='wu-rounded wu-p-1 wp-ui-primary' style='max-width: 600px; display: none; z-index: 9999999;'>" +
        "<img class='wu-rounded wu-block wu-m-0 wu-p-0 wu-bg-gray-100' style='max-width: 100%;' src='' alt=''>" +
      '</div>'
		);

	} // end if;

	/* END CONFIG */
	jQuery(selector).hover(function(e) {

		this.t = this.title;

		this.title = '';

		const img = jQuery(this).data('image');

		jQuery(preview_el)
			.find('img')
			.attr('src', img)
			.attr('alt', this.t)
			.end()
			.css({
				position: 'absolute',
				display: 'none',
			})
			.css('top', (e.pageY - xOffset) + 'px')
			.css('left', (e.pageX + yOffset) + 'px')
			.fadeIn('fast');

	},
	function() {

		this.title = this.t;

		jQuery(preview_el).fadeOut('fast');

	});

	jQuery(selector).mousemove(function(e) {

		jQuery(preview_el)
			.css('top', (e.pageY - xOffset) + 'px')
			.css('left', (e.pageX + yOffset) + 'px');

	});

};

// eslint-disable-next-line no-undef
window.wu_initialize_code_editors = function() {

	if (jQuery('[data-code-editor]').length) {

		if (typeof window.wu_editor_instances === 'undefined') {

			window.wu_editor_instances = {};

		} // end if;

		jQuery('[data-code-editor]').each(function() {

			const code_editor = jQuery(this);

			const editor_id = code_editor.attr('id');

			if (typeof window.wu_editor_instances[ editor_id ] === 'undefined') {

				if (! code_editor.is(':visible')) {

					return;

				} // end if;

				window.wu_editor_instances[ editor_id ] = wp.codeEditor.initialize(editor_id, {
					codemirror: {
						mode: code_editor.data('code-editor'),
						lint: true,
						autoCloseBrackets: true,
						matchBrackets: true,
						indentUnit: 2,
						indentWithTabs: true,
						lineNumbers: true,
						lineWrapping: true,
						styleActiveLine: true,
						continueComments: true,
						inputStyle: 'contenteditable',
						direction: 'ltr', // Code is shown in LTR even in RTL languages.
						gutters: [],
						extraKeys: {
							'Ctrl-Space': 'autocomplete',
							'Ctrl-/': 'toggleComment',
							'Cmd-/': 'toggleComment',
							'Alt-F': 'findPersistent',
						},
					},
				});

			} // end if;

		});

	} // end if;

}; // end wu_initialize_code_editors;

/**
 * Get a timezone-d moment instance.
 *
 * @param {*} a The date.
 * @return {Object} moment instance
 */
window.wu_moment = function(a) {

	return moment.tz(a, 'Etc/UTC');

}; // end wu_moment;
