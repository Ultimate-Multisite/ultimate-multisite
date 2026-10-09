/* global wu_thank_you */
(() => {
	"use strict";
	document.addEventListener("DOMContentLoaded", () => {
		document.querySelectorAll(".wu-resend-verification-email").forEach((element) => {
			element.addEventListener("click", async (event) => {
				event.preventDefault();
				if (element.getAttribute("aria-busy") === "true") {
					return;
				}
				const original = element.textContent;
				element.setAttribute("aria-busy", "true");
				element.textContent = wu_thank_you.i18n.resending_verification_email;
				try {
					const request = await fetch(wu_thank_you.ajaxurl, {
						method: "POST",
						credentials: "same-origin",
						headers: { "Content-Type": "application/x-www-form-urlencoded" },
						body: new URLSearchParams({ action: "wu_resend_verification_email", _ajax_nonce: wu_thank_you.resend_verification_email_nonce })
					});
					const response = await request.json();
					element.textContent = response.success ? wu_thank_you.i18n.email_sent : wu_thank_you.i18n.request_failed;
				} catch {
					element.textContent = wu_thank_you.i18n.request_failed;
				} finally {
					element.removeAttribute("aria-busy");
					setTimeout(() => {
						element.textContent = original;
					}, 5000);
				}
			});
		});
		const root = document.getElementById("wu-sites");
		if (! root || ! wu_thank_you.membership_hash || ! wu_thank_you.status_nonce) {
			return;
		}
		const status = root.querySelector("[data-wu-provisioning-status]");
		const retry = root.querySelector("[data-wu-provisioning-retry]");
		const login = root.querySelector("[data-wu-provisioning-login]");
		const cards = root.querySelector("[data-wu-site-cards]");
		if (! status || ! retry || ! login || ! cards) {
			return;
		}
		// Keep the existing integration API, but do not mount Vue over server cards.
		const { Vue, defineComponent } = window.wu_vue;
		window.wu_sites = new Vue(defineComponent({
			data() {
				return { creating: true, site_ready: false, clone_failed: false };
			}
		}));
		let controller, timer, started,
			attempts = 0,
			failures = 0,
			paused = false,
			navigating = false;
		const show = (state, stopped = false) => {
			const message = wu_thank_you.i18n[ state ] || wu_thank_you.i18n.pending;
			if (status.textContent !== message) {
				status.textContent = message;
			}
			window.wu_sites.creating = ! stopped && (state === "pending" || state === "cloning");
			cards.setAttribute("aria-busy", window.wu_sites.creating ? "true" : "false");
			root.querySelectorAll("[data-wu-provisioning-pending]").forEach((element) => {
				element.hidden = ! window.wu_sites.creating;
			});
			retry.hidden = ! stopped || state === "forbidden" || state === "ready";
			login.hidden = state !== "forbidden";
		};
		const renderSites = (sites) => {
			const fragment = document.createDocumentFragment();
			for (const site of sites) {
				const card = document.createElement("div");
				card.className = "wu-bg-gray-100 wu-p-4 wu-rounded wu-mb-2 wu-break-all";
				const heading = document.createElement("h5");
				heading.textContent = site.title;
				card.appendChild(heading);
				for (const [ key, label ] of [ [ "admin_url", "admin_panel" ], [ "visit_url", "visit" ] ]) {
					const url = new URL(site[ key ], location.href);
					if (! [ "http:", "https:" ].includes(url.protocol)) {
						throw new Error("Invalid site link");
					}
					const link = document.createElement("a");
					link.href = url.href;
					link.textContent = wu_thank_you.i18n[ label ];
					link.className = "wu-inline-block wu-p-3 wu-mr-4";
					card.appendChild(link);
				}
				fragment.appendChild(card);
			}
			cards.replaceChildren(fragment);
		};
		const poll = async () => {
			if (controller || paused || navigating) {
				return;
			}
			if (Date.now() - started >= 180000 || failures >= 5) {
				show("waiting", true);
				return;
			}
			controller = new AbortController();
			const active = controller;
			let timeout;
			try {
				timeout = setTimeout(() => active.abort(), 10000);
				const response = await fetch(wu_thank_you.ajaxurl, {
					method: "POST", credentials: "same-origin", cache: "no-store", signal: active.signal,
					headers: { "Content-Type": "application/x-www-form-urlencoded" },
					body: new URLSearchParams({
						action: "wu_checkout_provisioning_status", membership_hash: wu_thank_you.membership_hash,
						payment_hash: wu_thank_you.payment_hash || "", _ajax_nonce: wu_thank_you.status_nonce
					})
				});
				if ([ 400, 401, 403 ].includes(response.status)) {
					show("forbidden", true);
					return;
				}
				if (! response.ok) {
					throw new Error("Status unavailable");
				}
				const data = await response.json();
				const state = data.state || ({ completed: "ready", running: "cloning", stopped: "pending", failed: "failed" })[ data.publish_status ];
				if (! [ "pending", "cloning", "ready", "failed", "payment_pending", "verification_pending" ].includes(state)) {
					throw new Error("Invalid status");
				}
				if (state === "ready" && Array.isArray(data.sites)) {
					renderSites(data.sites);
				}
				failures = 0;
				window.wu_sites.creating = state === "pending" || state === "cloning";
				window.wu_sites.clone_failed = state === "failed";
				window.wu_sites.site_ready = state === "ready";
				show(state, state === "ready" || state === "failed");
				root.dispatchEvent(new CustomEvent("wu:checkout-provisioning-status", { bubbles: true, detail: data }));
				if (state === "failed") {
					return;
				}
				if (state === "ready") {
					if (data.redirect_url) {
						const url = new URL(data.redirect_url);
						if (! [ "http:", "https:" ].includes(url.protocol)) {
							throw new Error("Invalid handoff");
						}
						navigating = true;
						window.location.replace(url.href);
					}
					return;
				}
			} catch {
				failures++;
			} finally {
				clearTimeout(timeout);
				controller = null;
			}
			if (paused) {
				return;
			}
			attempts++;
			timer = setTimeout(poll, Math.min(5000, 1500 + (attempts * 250) + (failures * 500)));
		};
		const start = () => {
			if (controller || navigating) {
				return;
			}
			clearTimeout(timer);
			started = Date.now(); attempts = 0; failures = 0;
			show("pending");
			poll();
		};
		retry.addEventListener("click", start);
		window.addEventListener("pagehide", () => {
			paused = true;
			clearTimeout(timer);
			if (controller) {
				controller.abort();
			}
		});
		window.addEventListener("pageshow", (event) => {
			if (event.persisted) {
				paused = false;
				navigating = false;
				start();
			}
		});
		start();
	});
})();
