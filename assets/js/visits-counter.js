(() => {
	"use strict";
	const CreateCookie = (name, value, days) => {
		let expires;
		if (days) {
			const date = /* @__PURE__ */ new Date();
			date.setTime(date.getTime() + days * 24 * 60 * 60 * 1e3);
			expires = "; expires=" + date.toUTCString();
		} else {
			expires = "";
		}
		document.cookie = name + "=" + value + expires + "; path=/";
	};
	const ReadCookie = (name) => {
		const nameEQ = name + "=";
		const ca = document.cookie.split(";");
		for (let i = 0; i < ca.length; i++) {
			let c = ca[ i ];
			while (c.charAt(0) === " ") {
				c = c.substring(1, c.length);
			}
			if (c.indexOf(nameEQ) === 0) {
				return c.substring(nameEQ.length, c.length);
			}
		}
		return null;
	};
	const countVisit = () => {
		const counted = ReadCookie("WUVISIT");
		if (counted === "1") {
			return;
		}
		const countVisit2 = async () => {
			const url = new URL(wu_visits_counter.ajaxurl);
			url.searchParams.set("action", "wu_count_visits");
			url.searchParams.set("code", wu_visits_counter.code);
			await fetch(url);
			CreateCookie("WUVISIT", "1", 1);
		};
		document.addEventListener("DOMContentLoaded", () => {
			setTimeout(function() {
				countVisit2();
			}, 1e4);
		});
	};
	countVisit();
})()