/**
 * Create a new cookie.
 *
 * @param {string} name  The cookie name.
 * @param {*}      value The cookie value.
 * @param {number} days  The expiration, in days.
 * @return {void}
 */
window.wu_create_cookie = function(name, value, days) {

	let expires;

	if (days) {

		const date = new Date();

		date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));

		expires = '; expires=' + date.toGMTString();

	} else {

		expires = '';

	} // end if;

	document.cookie = name + '=' + value + expires + '; path=/';

};

/**
 * Reads from a cookie.
 *
 * @param {string} name The cookie name
 * @return {*} The cookie value
 */
window.wu_read_cookie = function(name) {

	const nameEQ = name + '=';

	const ca = document.cookie.split(';');

	for (let i = 0; i < ca.length; i++) {

		let c = ca[ i ];

		while (c.charAt(0) === ' ') {

			c = c.substring(1, c.length);

		} // end while;

		if (c.indexOf(nameEQ) === 0) {

			return c.substring(nameEQ.length, c.length);

		} // end if;

	} // end for;

	return null;

};

/**
 * Erase a cookie.
 *
 * @param {string} name The cookie name.
 */
window.wu_erase_cookie = function(name) {

	window.wu_create_cookie(name, '', -1);

};

/**
 * Listens to a particular cookie to catch a change in value.
 *
 * @param {string}   name     The cookie name.
 * @param {Function} callback The callback to call when a change is detected.
 */
window.wu_listen_to_cookie_change = function(name, callback) {

	let cookie_value = window.wu_read_cookie(name);

	setInterval(function() {

		const new_cookie_value = window.wu_read_cookie(name);

		if (new_cookie_value !== cookie_value) {

			// update registry so we dont get triggered again
			cookie_value = new_cookie_value;

			callback(cookie_value);

		} // end if;

	}, 100);

};
