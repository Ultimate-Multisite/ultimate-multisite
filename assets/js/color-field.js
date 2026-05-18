(function($) {
	$(function() {
		// Add Color Picker to all inputs that have color field class
		if (typeof wu_color_field_ids !== 'undefined') {
			wu_color_field_ids.forEach(function(fieldId) {
				$('.field-' + fieldId).wpColorPicker();
			});
		}
	});
}(jQuery));