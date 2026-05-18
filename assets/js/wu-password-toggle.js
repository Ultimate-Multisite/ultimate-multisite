/**
 * Password visibility toggle functionality.
 *
 * Adds show/hide functionality to password fields using the WordPress
 * core approach with dashicons.
 *
 * @since 2.4.0
 * @output assets/js/wu-password-toggle.js
 */

(function() {
	'use strict';

	const __ = wp.i18n.__;

	/**
	 * Toggle password visibility.
	 *
	 * @param {Event} event Click event.
	 */
	function togglePassword(event) {
		const toggle = event.target.closest('.wu-pwd-toggle');

		if (! toggle) {
			return;
		}

		event.preventDefault();

		const status = toggle.getAttribute('data-toggle');
		const input = toggle.parentElement.querySelector('input[type="password"], input[type="text"]');
		const icon = toggle.querySelector('.dashicons');

		if (! input || ! icon) {
			return;
		}

		if ('0' === status) {
			// Show password
			toggle.setAttribute('data-toggle', '1');
			toggle.setAttribute('aria-label', __('Hide password', 'ultimate-multisite'));
			input.setAttribute('type', 'text');
			icon.classList.remove('dashicons-visibility');
			icon.classList.add('dashicons-hidden');
		} else {
			// Hide password
			toggle.setAttribute('data-toggle', '0');
			toggle.setAttribute('aria-label', __('Show password', 'ultimate-multisite'));
			input.setAttribute('type', 'password');
			icon.classList.remove('dashicons-hidden');
			icon.classList.add('dashicons-visibility');
		}
	}

	// Use event delegation to handle dynamically added elements (Vue, etc.)
	document.addEventListener('click', togglePassword);
}());
