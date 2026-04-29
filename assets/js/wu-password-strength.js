/* global jQuery, wp, pwsL10n, wu_password_strength_settings, WU_PasswordStrength */
/**
 * Shared password strength utility for WP Ultimo.
 *
 * This module provides reusable password strength checking functionality
 * that can be used across different forms (checkout, password reset, etc.)
 *
 * Password strength levels:
 * - Medium: zxcvbn score 3
 * - Strong: zxcvbn score 4
 * - Super Strong: zxcvbn score 4 plus additional requirements:
 *   - Minimum length (default 12)
 *   - Uppercase letters
 *   - Lowercase letters
 *   - Numbers
 *   - Special characters
 *
 * @since 2.3.0
 * @param {jQuery} $ jQuery object
 */
(function($) {
	'use strict';

	/**
	 * Get password settings from localized PHP settings.
	 *
	 * @return {Object} Password settings
	 */
	function getSettings() {
		const defaults = {
			min_strength: 4,
			enforce_rules: false,
			min_length: 12,
			require_uppercase: false,
			require_lowercase: false,
			require_number: false,
			require_special: false
		};

		if (typeof wu_password_strength_settings === 'undefined') {
			return defaults;
		}

		return $.extend(defaults, wu_password_strength_settings);
	}

	/**
	 * Get the default minimum password strength from localized settings.
	 *
	 * Can be filtered via the 'wu_minimum_password_strength' PHP filter.
	 *
	 * @return {number} The minimum strength level (default: 4 = Strong)
	 */
	function getDefaultMinStrength() {
		return parseInt(getSettings().min_strength, 10) || 4;
	}

	/**
	 * Password strength checker utility.
	 *
	 * @param {Object}   options                  Configuration options
	 * @param {jQuery}   options.pass1            First password field element
	 * @param {jQuery}   options.pass2            Second password field element (optional)
	 * @param {jQuery}   options.result           Strength result display element
	 * @param {jQuery}   options.submit           Submit button element (optional)
	 * @param {number}   options.minStrength      Minimum required strength level (default from PHP filter, usually 4)
	 * @param {Function} options.onValidityChange Callback when password validity changes
	 */
	window.WU_PasswordStrength = function(options) {
		this.settings = getSettings();

		this.options = $.extend({
			pass1: null,
			pass2: null,
			result: null,
			submit: null,
			minStrength: getDefaultMinStrength(),
			onValidityChange: null
		}, options);

		this.isPasswordValid = false;
		this.failedRules = [];

		this.init();
	};

	WU_PasswordStrength.prototype = {
		/**
		 * Initialize the password strength checker.
		 */
		init() {
			const self = this;

			if (! this.options.pass1 || ! this.options.pass1.length) {
				return;
			}

			// Create or find strength meter element
			if (! this.options.result || ! this.options.result.length) {
				this.options.result = $('#pass-strength-result');

				if (! this.options.result.length) {
					return;
				}
			}

			// Set initial message
			this.options.result.html(this.getStrengthLabel('empty'));

			// Bind events.
			// Include 'change' for Safari/iOS autofill which does NOT fire
			// keyup/input when auto-generating or pasting passwords (WebKit bug).
			this.options.pass1.on('keyup input change', function() {
				self.checkStrength();
			});

			if (this.options.pass2 && this.options.pass2.length) {
				this.options.pass2.on('keyup input change', function() {
					self.checkStrength();
				});
			}

			// Safari autofill detection — poll for value changes that bypass all
			// DOM events (known WebKit bug). Stops after 60 seconds to avoid
			// unnecessary CPU usage once the user has had time to fill the form.
			this._lastPass1Val = '';
			this._autofillPoll = setInterval(function() {
				const currentVal = self.options.pass1.val();
				if (currentVal !== self._lastPass1Val) {
					self._lastPass1Val = currentVal;
					self.checkStrength();
				}
			}, 1000);

			setTimeout(function() {
				if (self._autofillPoll) {
					clearInterval(self._autofillPoll);
					self._autofillPoll = null;
				}
			}, 60000);

			// Disable submit initially if provided
			if (this.options.submit && this.options.submit.length) {
				this.options.submit.prop('disabled', true);
			}

			// Initial check
			this.checkStrength();
		},

		/**
		 * Check password strength and update the UI.
		 */
		checkStrength() {
			const pass1 = this.options.pass1.val();
			const pass2 = this.options.pass2 ? this.options.pass2.val() : '';

			// Reset classes
			this.options.result.attr('class', 'wu-py-2 wu-px-4 wu-block wu-text-sm wu-border-solid wu-border wu-mt-2');

			if (! pass1) {
				this.options.result.addClass('wu-bg-gray-100 wu-border-gray-200').html(this.getStrengthLabel('empty'));
				this.setValid(false);
				return;
			}

			// Get disallowed list from WordPress
			const disallowedList = this.getDisallowedList();

			const strength = wp.passwordStrength.meter(pass1, disallowedList, pass2);

			this.updateUI(strength);
			this.updateValidity(strength);
		},

		/**
		 * Get the disallowed list for password checking.
		 *
		 * @return {Array} The disallowed list
		 */
		getDisallowedList() {
			if (typeof wp === 'undefined' || typeof wp.passwordStrength === 'undefined') {
				return [];
			}

			// Support both old and new WordPress naming
			return typeof wp.passwordStrength.userInputDisallowedList === 'undefined'
				? wp.passwordStrength.userInputBlacklist()
				: wp.passwordStrength.userInputDisallowedList();
		},

		/**
		 * Get the appropriate label for a given strength level.
		 *
		 * @param {string|number} strength The strength level
		 * @return {string} The label text
		 */
		getStrengthLabel(strength) {
			// Use WordPress's built-in localized strings
			if (typeof pwsL10n === 'undefined') {
				// Fallback labels if pwsL10n is not available
				const fallbackLabels = {
					empty: 'Enter a password',
					'-1': 'Unknown',
					0: 'Very weak',
					1: 'Very weak',
					2: 'Weak',
					3: 'Medium',
					4: 'Strong',
					super_strong: 'Super Strong',
					5: 'Mismatch'
				};
				return fallbackLabels[ strength ] || fallbackLabels[ '0' ];
			}

			switch (strength) {
				case 'empty':
					// pwsL10n doesn't have 'empty', use our localized string
					return this.settings.i18n && this.settings.i18n.empty
						? this.settings.i18n.empty
						: 'Enter a password';
				case -1:
					return pwsL10n.unknown || 'Unknown';
				case 0:
				case 1:
					return pwsL10n.short || 'Very weak';
				case 2:
					return pwsL10n.bad || 'Weak';
				case 3:
					return pwsL10n.good || 'Medium';
				case 4:
					return pwsL10n.strong || 'Strong';
				case 'super_strong':
					return this.settings.i18n && this.settings.i18n.super_strong
						? this.settings.i18n.super_strong
						: 'Super Strong';
				case 5:
					return pwsL10n.mismatch || 'Mismatch';
				default:
					return pwsL10n.short || 'Very weak';
			}
		},

		/**
		 * Update the UI based on password strength.
		 *
		 * @param {number} strength The password strength level
		 */
		updateUI(strength) {
			let label = this.getStrengthLabel(strength);
			let colorClass = '';

			switch (strength) {
				case -1:
				case 0:
				case 1:
				case 2:
					colorClass = 'wu-bg-red-200 wu-border-red-300';
					break;
				case 3:
					colorClass = 'wu-bg-yellow-200 wu-border-yellow-300';
					break;
				case 4:
					colorClass = 'wu-bg-green-200 wu-border-green-300';
					break;
				case 5:
					colorClass = 'wu-bg-red-200 wu-border-red-300';
					break;
				default:
					colorClass = 'wu-bg-red-200 wu-border-red-300';
			}

			// Check additional rules and update label if needed
			if (this.settings.enforce_rules && strength >= this.options.minStrength && strength !== 5) {
				const password = this.options.pass1.val();
				const rulesResult = this.checkPasswordRules(password);

				if (! rulesResult.valid) {
					colorClass = 'wu-bg-red-200 wu-border-red-300';
					label = this.getRulesHint(rulesResult.failedRules);
				} else {
					// Password meets all requirements - show Super Strong
					colorClass = 'wu-bg-green-300 wu-border-green-400';
					label = this.getStrengthLabel('super_strong');
				}
			} else if (strength === 4 && strength !== 5) {
				// Even when enforce_rules is off, show Super Strong if the password
				// voluntarily meets all the super-strong criteria.
				const password = this.options.pass1.val();

				if (this.checkSuperStrongRules(password)) {
					colorClass = 'wu-bg-green-300 wu-border-green-400';
					label = this.getStrengthLabel('super_strong');
				}
			}

			this.options.result.addClass(colorClass).html(label);
		},

		/**
		 * Get a hint message for failed password rules.
		 *
		 * Uses localized strings from PHP.
		 *
		 * @param {Array} failedRules Array of failed rule names
		 * @return {string} Hint message
		 */
		getRulesHint(failedRules) {
			const hints = [];
			const i18n = this.settings.i18n;

			if (! i18n) {
				return 'Required: ' + failedRules.join(', ');
			}

			if (failedRules.indexOf('length') !== -1) {
				hints.push(i18n.min_length.replace('%d', this.settings.min_length));
			}
			if (failedRules.indexOf('uppercase') !== -1) {
				hints.push(i18n.uppercase_letter);
			}
			if (failedRules.indexOf('lowercase') !== -1) {
				hints.push(i18n.lowercase_letter);
			}
			if (failedRules.indexOf('number') !== -1) {
				hints.push(i18n.number);
			}
			if (failedRules.indexOf('special') !== -1) {
				hints.push(i18n.special_char);
			}

			if (hints.length === 0) {
				return this.getStrengthLabel('super_strong');
			}

			return i18n.required + ' ' + hints.join(', ');
		},

		/**
		 * Update password validity based on strength and additional rules.
		 *
		 * @param {number} strength The password strength level
		 */
		updateValidity(strength) {
			let isValid = false;
			const password = this.options.pass1.val();

			// Check minimum strength
			if (strength >= this.options.minStrength && strength !== 5) {
				isValid = true;
			}

			// Check additional rules if enforcement is enabled
			if (isValid && this.settings.enforce_rules) {
				const rulesResult = this.checkPasswordRules(password);
				isValid = rulesResult.valid;
				this.failedRules = rulesResult.failedRules;
			} else {
				this.failedRules = [];
			}

			this.setValid(isValid);
		},

		/**
		 * Check password against additional rules (Defender Pro compatible).
		 *
		 * @param {string} password The password to check
		 * @return {Object} Object with valid boolean and failedRules array
		 */
		checkPasswordRules(password) {
			const failedRules = [];
			const settings = this.settings;

			// Check minimum length
			if (settings.min_length && password.length < settings.min_length) {
				failedRules.push('length');
			}

			// Check for uppercase letter
			if (settings.require_uppercase && ! /[A-Z]/.test(password)) {
				failedRules.push('uppercase');
			}

			// Check for lowercase letter
			if (settings.require_lowercase && ! /[a-z]/.test(password)) {
				failedRules.push('lowercase');
			}

			// Check for number
			if (settings.require_number && ! /[0-9]/.test(password)) {
				failedRules.push('number');
			}

			// Check for special character (matches Defender Pro's pattern)
			if (settings.require_special && ! /[!@#$%^&*()_+\-={};:'",.<>?~\[\]\/|`\\]/.test(password)) {
				failedRules.push('special');
			}

			return {
				valid: failedRules.length === 0,
				failedRules
			};
		},

		/**
		 * Check if a password meets all super-strong criteria unconditionally.
		 *
		 * Used to display "Super Strong" even when the site minimum is set below
		 * super_strong — a reward for users who go above and beyond.
		 *
		 * @param {string} password
		 * @return {boolean} True if password meets all super-strong criteria.
		 */
		checkSuperStrongRules(password) {
			return password.length >= 12 &&
				/[A-Z]/.test(password) &&
				/[a-z]/.test(password) &&
				/[0-9]/.test(password) &&
				/[!@#$%^&*()_+\-={};:'",.<>?~\[\]\/|`\\]/.test(password);
		},

		/**
		 * Get failed rules for external access.
		 *
		 * @return {Array} Array of failed rule names
		 */
		getFailedRules() {
			return this.failedRules;
		},

		/**
		 * Set password validity and update submit button.
		 *
		 * @param {boolean} isValid Whether the password is valid
		 */
		setValid(isValid) {
			const wasValid = this.isPasswordValid;
			this.isPasswordValid = isValid;

			if (this.options.submit && this.options.submit.length) {
				this.options.submit.prop('disabled', ! isValid);
			}

			// Trigger callback if validity changed
			if (wasValid !== isValid && typeof this.options.onValidityChange === 'function') {
				this.options.onValidityChange(isValid);
			}
		},

		/**
		 * Get the current password validity.
		 *
		 * @return {boolean} Whether the password is valid
		 */
		isValid() {
			return this.isPasswordValid;
		},

		/**
		 * Destroy the strength checker and remove event listeners.
		 *
		 * Call before re-initializing to prevent duplicate handlers when
		 * the password field DOM element is replaced by a Vue update cycle
		 * (e.g. the inline login prompt being shown/hidden causes Vue 2 to
		 * recycle adjacent sibling DOM nodes, creating a fresh #field-password
		 * element whose jQuery bindings are missing).
		 *
		 * @since 2.5.2
		 */
		destroy() {
			if (this.options.pass1 && this.options.pass1.length) {
				this.options.pass1.off('keyup input change');
			}
			if (this.options.pass2 && this.options.pass2.length) {
				this.options.pass2.off('keyup input change');
			}
			if (this._autofillPoll) {
				clearInterval(this._autofillPoll);
				this._autofillPoll = null;
			}
		}
	};

}(jQuery));
