/* global Vue */

if (typeof window.Vue !== 'undefined') {

	/**
	 * Registers the ColorPicker component.
	 *
	 * @since 2.0.0
	 */
	Vue.component('colorPicker', {
		props: [ 'value' ],
		template: '<input type="text">',
		mounted() {

			const vm = this;

			$(this.$el)
				.val(this.value)
				.wpColorPicker({
					width: 200,
					defaultColor: this.value,
					change(event, ui) {

						// emit change event on color change using mouse
						vm.$emit('input', ui.color.toString());

					},
				});

		},
		watch: {
			value(value) {

				// update value
				$(this.$el).wpColorPicker('color', value);

			},
		},
		destroyed() {

			$(this.$el).off().wpColorPicker('destroy'); // (!) Not tested

		},
	});

} // end if;
