/* global wubox */
wp.hooks.addAction('wu_add_checkout_form_field_mounted', 'nextpress/wp-ultimo', function(data) {

	if (data.type === '') {

		wubox.width(600);

	} // end if;

});

wp.hooks.addAction('wu_add_checkout_form_field_changed', 'nextpress/wp-ultimo', function(val, data) {

	if (data.type === '') {

		wubox.width(600);

	} else {

		wubox.width(400);

	}// end if;

});
