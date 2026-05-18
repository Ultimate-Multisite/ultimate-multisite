(() => {
	"use strict";
	const { addFilter } = window.wp.hooks;
	document.addEventListener("DOMContentLoaded", () => {
		addFilter("wu_before_form_init", "nextpress/wp-ultimo", (data) => {
			if (typeof data !== "undefined") {
				data.billing_option = 1;
				data.default_billing_option = 12;
			}
			return data;
		});
	});
})()