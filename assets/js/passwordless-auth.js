(function (window, document, $) {

	'use strict';

	const config = window.wu_passwordless_auth || {};
	const i18n = config.i18n || {};

	function base64UrlToBuffer(input) {

		input = String(input || '').replace(/-/g, '+').replace(/_/g, '/');

		while (input.length % 4) {
			input += '=';
		}

		const binary = window.atob(input);
		const bytes = new Uint8Array(binary.length);

		for (let i = 0; i < binary.length; i++) {
			bytes[ i ] = binary.charCodeAt(i);
		}

		return bytes.buffer;
	}

	function bufferToBase64Url(buffer) {

		if (! buffer) {
			return '';
		}

		const bytes = new Uint8Array(buffer);
		let binary = '';

		for (let i = 0; i < bytes.length; i++) {
			binary += String.fromCharCode(bytes[ i ]);
		}

		return window.btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
	}

	function supportsWebAuthn() {

		return !! (window.PublicKeyCredential && window.navigator.credentials && window.navigator.credentials.get && window.navigator.credentials.create);
	}

	function prepareCreationOptions(options) {

		const publicKey = Object.assign({}, options.publicKey || {});

		publicKey.challenge = base64UrlToBuffer(publicKey.challenge);

		if (publicKey.user && publicKey.user.id) {
			publicKey.user = Object.assign({}, publicKey.user, {
				id: base64UrlToBuffer(publicKey.user.id),
			});
		}

		publicKey.excludeCredentials = (publicKey.excludeCredentials || []).map(function (credential) {
			return Object.assign({}, credential, {
				id: base64UrlToBuffer(credential.id),
			});
		});

		return { publicKey };
	}

	function prepareRequestOptions(options) {

		const publicKey = Object.assign({}, options.publicKey || {});

		publicKey.challenge = base64UrlToBuffer(publicKey.challenge);

		publicKey.allowCredentials = (publicKey.allowCredentials || []).map(function (credential) {
			return Object.assign({}, credential, {
				id: base64UrlToBuffer(credential.id),
			});
		});

		return { publicKey };
	}

	function serializeCredential(credential) {

		const response = {
			clientDataJSON: bufferToBase64Url(credential.response.clientDataJSON),
		};

		if (credential.response.attestationObject) {
			response.attestationObject = bufferToBase64Url(credential.response.attestationObject);
		}

		if (credential.response.authenticatorData) {
			response.authenticatorData = bufferToBase64Url(credential.response.authenticatorData);
		}

		if (credential.response.signature) {
			response.signature = bufferToBase64Url(credential.response.signature);
		}

		if (credential.response.userHandle) {
			response.userHandle = bufferToBase64Url(credential.response.userHandle);
		}

		if (credential.response.getTransports) {
			response.transports = credential.response.getTransports();
		}

		return {
			id: credential.id,
			rawId: bufferToBase64Url(credential.rawId),
			type: credential.type,
			response,
			clientExtensionResults: credential.getClientExtensionResults ? credential.getClientExtensionResults() : {},
		};
	}

	function request(action, data) {

		return new Promise(function (resolve, reject) {

			$.ajax({
				url: config.ajax_url,
				method: 'POST',
				data: Object.assign({}, data || {}, {
					action,
					nonce: config.nonce,
				}),
				success(response) {

					if (response && response.success) {
						resolve(response.data || {});
						return;
					}

					reject(response || {});

				},
				error(error) {
					reject(error);
				},
			});

		});
	}

	function getErrorMessage(error) {

		if (error && error.responseJSON && error.responseJSON.data && error.responseJSON.data.message) {
			return error.responseJSON.data.message;
		}

		if (error && error.data && error.data.message) {
			return error.data.message;
		}

		if (error && error.message) {
			return error.message;
		}

		return i18n.login_failed || 'Login failed. Please try again.';
	}

	function bindOnce(element, eventName, key, handler) {

		if (! element) {
			return;
		}

		element.wuPasswordlessBindings = element.wuPasswordlessBindings || {};

		if (element.wuPasswordlessBindings[ key ]) {
			return;
		}

		element.wuPasswordlessBindings[ key ] = true;
		element.addEventListener(eventName, handler);
	}

	function initContainer(container) {

		if (! container) {
			return;
		}

		if (! container.querySelector('.wu-passwordless-start') && ! container.querySelector('.wu-passwordless-passkey') && ! container.querySelector('.wu-passwordless-verify-code') && ! container.querySelector('.wu-passwordless-create-passkey')) {
			return;
		}

		const state = container.wuPasswordlessState || {
			identifier: '',
			token: '',
			authOptions: null,
			registrationOptions: null,
			redirectUrl: container.dataset.redirectTo || '',
		};

		container.wuPasswordlessState = state;
		container.dataset.wuPasswordlessReady = '1';

		const status = container.querySelector('.wu-passwordless-status');
		const emailInput = container.querySelector('.wu-passwordless-email');
		const codeInput = container.querySelector('.wu-passwordless-code');
		const startButton = container.querySelector('.wu-passwordless-start');
		const passkeyButton = container.querySelector('.wu-passwordless-passkey');
		const emailCodeButton = container.querySelector('.wu-passwordless-email-code');
		const verifyCodeButton = container.querySelector('.wu-passwordless-verify-code');
		const resendButton = container.querySelector('.wu-passwordless-resend');
		const createPasskeyButton = container.querySelector('.wu-passwordless-create-passkey');
		const skipPasskeyButton = container.querySelector('.wu-passwordless-skip-passkey');

		function getIdentifier() {

			if (emailInput) {
				return emailInput.value.trim();
			}

			const fieldId = container.dataset.identifierField;

			if (! fieldId) {
				return '';
			}

			const field = document.getElementById(fieldId) || document.querySelector('[name="' + fieldId + '"]');

			return field ? field.value.trim() : '';
		}

		function showStatus(message, type) {

			if (! status) {
				return;
			}

			status.textContent = message || '';
			status.classList.remove('wu-hidden', 'wu-bg-red-100', 'wu-text-red-800', 'wu-bg-blue-50', 'wu-text-blue-900', 'wu-bg-green-100', 'wu-text-green-800');

			if (! message) {
				status.classList.add('wu-hidden');
				return;
			}

			if (type === 'error') {
				status.classList.add('wu-bg-red-100', 'wu-text-red-800');
			} else if (type === 'success') {
				status.classList.add('wu-bg-green-100', 'wu-text-green-800');
			} else {
				status.classList.add('wu-bg-blue-50', 'wu-text-blue-900');
			}
		}

		function showStep(step) {

			container.querySelectorAll('[data-wu-step]').forEach(function (element) {
				const active = element.dataset.wuStep === step;
				element.hidden = ! active;
				element.classList.toggle('wu-hidden', ! active);
			});

			if (step === 'otp' && codeInput) {
				codeInput.focus();
			}
		}

		function setBusy(button, busy, label) {

			if (! button) {
				return;
			}

			if (busy) {
				button.dataset.originalText = button.textContent;
				button.disabled = true;
				button.textContent = label || button.textContent;
				return;
			}

			button.disabled = false;
			button.textContent = button.dataset.originalText || button.textContent;
		}

		function finish(data) {

			if (data.nonce) {
				config.nonce = data.nonce;
			}

			state.redirectUrl = data.redirect_url || state.redirectUrl;

			if (data.registration_options && supportsWebAuthn()) {
				state.registrationOptions = data.registration_options;
				showStatus(data.message || '', 'success');
				showStep('enroll');
				return;
			}

			showStatus(i18n.redirecting || 'Login successful. Redirecting...', 'success');

			if (container.dataset.success === 'reload') {
				window.location.reload();
				return;
			}

			window.location.assign(data.redirect_url || state.redirectUrl || window.location.href);
		}

		function start(forceOtp) {

			state.identifier = getIdentifier();

			if (! state.identifier) {
				showStatus(i18n.email_required || 'Enter your email address to continue.', 'error');
				return;
			}

			setBusy(startButton, true, i18n.sending_code || 'Sending login code...');
			showStatus(forceOtp ? (i18n.sending_code || 'Sending login code...') : '', 'info');

			request('wu_passwordless_start', {
				identifier: state.identifier,
				redirect_to: state.redirectUrl,
				redirect_type: container.dataset.redirectType || 'default',
				supports_webauthn: supportsWebAuthn() ? 1 : 0,
				force_otp: forceOtp ? 1 : 0,
			}).then(function (data) {

				setBusy(startButton, false);

				if (data.mode === 'passkey') {
					state.authOptions = data.options;
					showStep('passkey');
					showStatus(data.message || i18n.passkey_starting || '', 'info');
					usePasskey();
					return;
				}

				state.token = data.token || '';
				showStep('otp');
				showStatus(data.message || '', 'info');

			}).catch(function (error) {

				setBusy(startButton, false);
				showStatus(getErrorMessage(error), 'error');

			});

		}

		function usePasskey() {

			if (! supportsWebAuthn()) {
				showStatus(i18n.passkey_unavailable || 'Passkeys are not available in this browser.', 'error');
				start(true);
				return;
			}

			if (! state.authOptions) {
				return;
			}

			setBusy(passkeyButton, true, i18n.passkey_starting || 'Follow your browser prompt...');

			window.navigator.credentials.get(prepareRequestOptions(state.authOptions)).then(function (credential) {

				return request('wu_passwordless_verify_passkey', {
					credential: JSON.stringify(serializeCredential(credential)),
					redirect_to: state.redirectUrl,
					redirect_type: container.dataset.redirectType || 'default',
				});

			}).then(function (data) {

				setBusy(passkeyButton, false);
				finish(data);

			}).catch(function (error) {

				setBusy(passkeyButton, false);
				showStatus(getErrorMessage(error), 'error');
				showStep('passkey');

			});

		}

		function verifyCode() {

			const code = codeInput ? codeInput.value.trim() : '';

			if (! code) {
				showStatus(i18n.code_required || 'Enter the code from your email.', 'error');
				return;
			}

			setBusy(verifyCodeButton, true, i18n.verifying_code || 'Verifying code...');

			request('wu_passwordless_verify_otp', {
				token: state.token,
				code,
				redirect_to: state.redirectUrl,
				redirect_type: container.dataset.redirectType || 'default',
			}).then(function (data) {

				setBusy(verifyCodeButton, false);
				finish(data);

			}).catch(function (error) {

				setBusy(verifyCodeButton, false);
				showStatus(getErrorMessage(error), 'error');

			});

		}

		function createPasskey() {

			if (! supportsWebAuthn()) {
				finish({ redirect_url: state.redirectUrl });
				return;
			}

			setBusy(createPasskeyButton, true, i18n.creating_passkey || 'Creating passkey...');

			const optionsPromise = state.registrationOptions ? Promise.resolve({ options: state.registrationOptions }) : request('wu_passwordless_register_options', {});

			optionsPromise.then(function (data) {

				return window.navigator.credentials.create(prepareCreationOptions(data.options || data));

			}).then(function (credential) {

				return request('wu_passwordless_register_verify', {
					credential: JSON.stringify(serializeCredential(credential)),
				});

			}).then(function () {

				setBusy(createPasskeyButton, false);
				showStatus(i18n.passkey_created || 'Passkey created. Redirecting...', 'success');
				finish({ redirect_url: state.redirectUrl });

			}).catch(function (error) {

				setBusy(createPasskeyButton, false);
				showStatus(getErrorMessage(error), 'error');

			});

		}

		bindOnce(startButton, 'click', 'wuPasswordlessClick', function () {
			start(false);
		});

		bindOnce(emailInput, 'keydown', 'wuPasswordlessKeydown', function (event) {
			if (event.key === 'Enter') {
				event.preventDefault();
				start(false);
			}
		});

		bindOnce(passkeyButton, 'click', 'wuPasswordlessClick', usePasskey);

		bindOnce(emailCodeButton, 'click', 'wuPasswordlessClick', function () {
			start(true);
		});

		bindOnce(verifyCodeButton, 'click', 'wuPasswordlessClick', verifyCode);

		bindOnce(codeInput, 'keydown', 'wuPasswordlessKeydown', function (event) {
			if (event.key === 'Enter') {
				event.preventDefault();
				verifyCode();
			}
		});

		bindOnce(resendButton, 'click', 'wuPasswordlessClick', function () {
			start(true);
		});

		bindOnce(createPasskeyButton, 'click', 'wuPasswordlessClick', createPasskey);

		bindOnce(skipPasskeyButton, 'click', 'wuPasswordlessClick', function () {
			finish({ redirect_url: state.redirectUrl });
		});

	}

	function initAll(root) {

		if (root && root.nodeType === 1 && root.matches('.wu-passwordless-auth')) {
			initContainer(root);
		}

		(root || document).querySelectorAll('.wu-passwordless-auth').forEach(initContainer);
	}

	window.wuPasswordlessAuth = {
		init: initAll,
		request,
		supportsWebAuthn,
	};

	$(function () {

		initAll(document);

		if (window.MutationObserver) {
			const observer = new window.MutationObserver(function (mutations) {
				mutations.forEach(function (mutation) {
					mutation.addedNodes.forEach(function (node) {
						if (node.nodeType === 1) {
							initAll(node);

							if (node.closest) {
								initContainer(node.closest('.wu-passwordless-auth'));
							}
						}
					});
				});
			});

			observer.observe(document.body, {
				childList: true,
				subtree: true,
			});
		}

	});

}(window, document, jQuery));
