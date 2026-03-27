(() => {
"use strict";

const config = window.wu_payment_poll;

if (!config || !config.should_poll) {
	return;
}

let attempts = 0;
const max_attempts = parseInt(config.max_attempts, 10) || 20;
const poll_interval = parseInt(config.poll_interval, 10) || 3000;

function show_status(message, css_class) {
	const el = document.querySelector(config.status_selector);
	if (!el) {
		return;
	}
	el.textContent = message;
	el.className = "wu-payment-status wu-p-3 wu-rounded wu-mt-3 wu-block wu-text-sm " + (css_class || "");
	el.style.display = "block";
}

async function check_payment_status() {
	attempts++;

	if (attempts > max_attempts) {
		show_status(config.messages.timeout, "wu-bg-yellow-100 wu-text-yellow-800");
		return;
	}

	show_status(config.messages.checking, "wu-bg-gray-100 wu-text-gray-600");

	try {
		const params = new URLSearchParams({
			action: "wu_check_payment_status",
			nonce: config.nonce,
			payment_hash: config.payment_hash,
		});

		const response = await fetch(config.ajax_url, {
			method: "POST",
			headers: { "Content-Type": "application/x-www-form-urlencoded" },
			body: params.toString(),
		});

		const data = await response.json();

		if (data.success && data.data.status === "completed") {
			show_status(config.messages.completed, "wu-bg-green-100 wu-text-green-800");
			if (config.success_redirect) {
				setTimeout(() => { window.location.href = config.success_redirect; }, 1500);
			} else {
				setTimeout(() => { window.location.reload(); }, 1500);
			}
			return;
		}

		// Still pending — continue polling
		show_status(config.messages.pending, "wu-bg-blue-100 wu-text-blue-800");
		setTimeout(check_payment_status, poll_interval);

	} catch (_e) {
		show_status(config.messages.error, "wu-bg-red-100 wu-text-red-800");
		setTimeout(check_payment_status, poll_interval);
	}
}

document.addEventListener("DOMContentLoaded", () => {
	setTimeout(check_payment_status, poll_interval);
});
})();
