/**
 * Customer-facing provisioning progress bar.
 *
 * Renders the `progress` payload returned by the checkout status endpoints:
 * "Step 3 of 6: Copying your template", an ETA based on this install's own
 * history, a queue/retry note and a failure state. Between polls the bar
 * fills toward the current step's expected end, but never past 95% of it.
 *
 * Usage (core and add-ons):
 *
 *     const bar = window.wu_provisioning_progress.mount(element);
 *     bar.update(response.progress, isPublished);
 *     bar.fail();
 */
(() => {
	"use strict";

	const i18n = Object.assign({
		label: "Setup progress",
		step: "Step %1$s of %2$s: %3$s",
		minutes: "About %s min left",
		seconds: "About %s sec left",
		almost: "Almost done",
		done: "Done",
		queue: "Waiting in queue (%s ahead)",
		retrying: "Retrying: %s",
		slow: "This is taking a little longer than usual.",
		failed: "Setup could not finish. Your order is saved.",
		support: "Contact support",
		support_url: "",
		announce: "Step %1$s of %2$s: %3$s"
	}, window.wu_provisioning_progress_i18n || {});

	const format = (text, ...values) => text.replace(/%(?:(\d+)\$)?s/g, (match, position) => {
		const value = position ? values[ position - 1 ] : values.shift();
		return value === undefined ? "" : String(value);
	});

	const element = (tag, className, style) => {
		const node = document.createElement(tag);
		if (className) {
			node.className = className;
		}
		if (style) {
			Object.assign(node.style, style);
		}
		return node;
	};

	const eta = (seconds) => {
		if (seconds < 10) {
			return i18n.almost;
		}
		if (seconds < 90) {
			return format(i18n.seconds, Math.ceil(seconds / 5) * 5);
		}
		return format(i18n.minutes, Math.max(2, Math.round(seconds / 60)));
	};

	/*
	 * Every update is also announced on the document, so a host that covers the
	 * thank-you page with its own screen can mount a second bar from the same poll:
	 * document.addEventListener("wu:provisioning-progress", (e) => bar.update(e.detail)).
	 */
	let lastBroadcast = "";
	const broadcast = (progress) => {
		const key = JSON.stringify(progress);
		if (key !== lastBroadcast) {
			lastBroadcast = key;
			document.dispatchEvent(new CustomEvent("wu:provisioning-progress", { detail: progress }));
		}
	};

	const mount = (container) => {
		if (! container) {
			return null;
		}
		if (container.wu_provisioning_progress) {
			return container.wu_provisioning_progress;
		}

		const root = element("div", "wu-provisioning-progress");
		// Inline layout: completion screens replaced by hosts may not load UM's utility CSS.
		const header = element("div", "", {
			display: "flex", flexWrap: "wrap", justifyContent: "space-between", alignItems: "baseline",
			columnGap: "1rem", fontSize: "0.875rem", lineHeight: "1.4", marginBottom: "0.375rem"
		});
		const step = element("span", "", { fontWeight: "600" });
		const remaining = element("span", "", { whiteSpace: "nowrap", opacity: "0.75" });
		const track = element("div", "", {
			height: "8px", borderRadius: "9999px", background: "#e5e7eb", overflow: "hidden"
		});
		const fill = element("div", "", {
			height: "100%", width: "0%", borderRadius: "9999px", background: "#2563eb", transition: "width 0.6s linear"
		});
		const note = element("p", "", { fontSize: "0.75rem", margin: "0.375rem 0 0", opacity: "0.75" });
		// Visually hidden without relying on the theme defining .screen-reader-text.
		const live = element("div", "screen-reader-text", {
			position: "absolute", width: "1px", height: "1px", padding: "0", margin: "-1px",
			overflow: "hidden", clip: "rect(0, 0, 0, 0)", whiteSpace: "nowrap", border: "0"
		});
		root.style.position = "relative";

		track.setAttribute("role", "progressbar");
		track.setAttribute("aria-label", i18n.label);
		track.setAttribute("aria-valuemin", "0");
		track.setAttribute("aria-valuemax", "100");
		track.setAttribute("aria-valuenow", "0");
		live.setAttribute("aria-live", "polite");
		live.setAttribute("aria-atomic", "true");
		note.hidden = true;

		header.append(step, remaining);
		track.appendChild(fill);
		root.append(header, track, note, live);
		container.replaceChildren(root);
		container.hidden = false;

		let payload = null,
			received = 0,
			shown = 0,
			announced = "",
			run = "",
			failed = false,
			timer = null;

		const render = () => {
			if (! payload || failed) {
				return;
			}
			const steps = Array.isArray(payload.steps) ? payload.steps : [];
			const index = Math.max(0, Math.min(steps.length - 1, (payload.step_index || 1) - 1));
			const completed = payload.state === "completed";
			const elapsed = (payload.step_elapsed || 0) + (completed ? 0 : (Date.now() - received) / 1000);
			let total = 0,
				done = 0,
				left = 0;

			steps.forEach((item, position) => {
				const expected = Math.max(0, Number(item.expected) || 0);
				total += expected;
				if (position < index) {
					done += expected;
				} else if (position > index) {
					left += expected;
				}
			});

			const current = Math.max(1, Number(steps[ index ] && steps[ index ].expected) || 1);
			let percent = total > 0 ? (done + Math.min(elapsed, current * 0.95)) / total * 100 : 0;
			percent = completed ? 100 : Math.min(99, percent);
			// Never move backwards within a run; a new poll can lower the estimate.
			shown = Math.max(shown, percent);

			fill.style.width = shown.toFixed(1) + "%";
			track.setAttribute("aria-valuenow", String(Math.floor(shown)));

			const label = steps[ index ] ? steps[ index ].label : (payload.step_label || "");
			const text = format(i18n.step, index + 1, steps.length || payload.step_count || 1, label);
			step.textContent = completed ? i18n.done : text;
			track.setAttribute("aria-valuetext", completed ? i18n.done : text);
			remaining.textContent = completed ? "" : eta(Math.max(0, current - elapsed) + left);

			let message = "";
			if (payload.queue_ahead > 0) {
				message = format(i18n.queue, payload.queue_ahead);
			} else if (payload.retrying) {
				message = format(i18n.retrying, label);
			} else if (! completed && elapsed > Math.max(current * 1.5, current + 15)) {
				message = i18n.slow;
			}
			note.textContent = message;
			note.hidden = ! message;

			const announcement = completed ? i18n.done : format(i18n.announce, index + 1, steps.length || 1, label);
			if (announcement !== announced) {
				announced = announcement;
				live.textContent = announcement;
			}
		};

		const instance = {
			/**
			 * @param {Object}  progress  Payload from the status endpoint.
			 * @param {boolean} published Whether the site/network is actually ready.
			 *                            true shows "Done"; false never does (a finished run
			 *                            waits at its last step); omitted trusts the payload.
			 */
			update(progress, published) {
				if (! progress || typeof progress !== "object") {
					return;
				}
				if (published === true && progress.state !== "failed") {
					progress = Object.assign({}, progress, { state: "completed" });
				} else if (published === false && progress.state === "completed") {
					const steps = Array.isArray(progress.steps) ? progress.steps : [];
					const last = steps[ steps.length - 1 ] || {};
					progress = Object.assign({}, progress, {
						state: "running", eta: 0, percent: 99, step: last.key || progress.step,
						step_index: steps.length || progress.step_count || 1, step_elapsed: Number(last.expected) || 0
					});
				}
				broadcast(progress);
				if (progress.run && progress.run !== run) {
					run = progress.run;
					shown = 0;
				}
				failed = false;
				payload = progress;
				received = Date.now();
				fill.style.background = "#2563eb";
				if (progress.state === "failed") {
					instance.fail();
					return;
				}
				render();
				if (! timer && progress.state !== "completed") {
					timer = setInterval(render, 500);
				}
				if (progress.state === "completed") {
					clearInterval(timer);
					timer = null;
				}
			},
			fail() {
				broadcast({ state: "failed" });
				failed = true;
				clearInterval(timer);
				timer = null;
				fill.style.background = "#dc2626";
				step.textContent = i18n.failed;
				remaining.textContent = "";
				note.replaceChildren();
				if (i18n.support_url) {
					const link = element("a");
					link.href = i18n.support_url;
					link.textContent = i18n.support;
					note.appendChild(link);
				}
				note.hidden = ! i18n.support_url;
				live.textContent = i18n.failed;
			},
			destroy() {
				clearInterval(timer);
				timer = null;
				container.replaceChildren();
				container.hidden = true;
				delete container.wu_provisioning_progress;
			}
		};

		container.wu_provisioning_progress = instance;

		return instance;
	};

	window.wu_provisioning_progress = { mount, format, eta };
})();
