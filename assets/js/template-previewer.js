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
	const ListenToCookieChange = (name, callback) => {
		let cookie_value = ReadCookie(name);
		setInterval(function() {
			const new_cookie_value = ReadCookie(name);
			if (new_cookie_value !== cookie_value) {
				cookie_value = new_cookie_value;
				callback(cookie_value);
			}
		}, 100);
	};
	window.addEventListener("beforeunload", () => {
		let _a;
		return (_a = window.top) == null ? void 0 : _a.postMessage("wu_preview_changed", "*");
	});
	CreateCookie("wu_template", "");
	const isIOS = () => {
		let _a;
		window.addEventListener("touchstart", () => {
		});
		const iDevices = [
			"iPad Simulator",
			"iPhone Simulator",
			"iPod Simulator",
			"iPad",
			"iPhone",
			"iPod"
		];
		const platform = ((_a = navigator == null ? void 0 : navigator.userAgentData) == null ? void 0 : _a.platform) || (navigator == null ? void 0 : navigator.platform) || "";
		return iDevices.includes(platform);
	};
	document.addEventListener("DOMContentLoaded", () => {
		let _a;
		ListenToCookieChange("wu_selected_products", () => document.location.reload());
		const iframe = document.getElementById("iframe");
		const wn = iframe == null ? void 0 : iframe.contentWindow;
		wn == null ? void 0 : wn.postMessage("Hello to iframe from parent!", "https://" + location.hostname);
		const elements = document.querySelectorAll("#action-select, #action-select2");
		elements.forEach((element) => element.addEventListener("click", (event) => {
			event.preventDefault();
			const value = document.getElementById("template-selector").value;
			CreateCookie("wu_template", value);
			window.close();
		}));
		const loadingIndicator = document.getElementById("wu-loading-indicator");
		iframe == null ? void 0 : iframe.addEventListener("load", () => {
			if (loadingIndicator) {
				loadingIndicator.style.display = "none";
			}
			if (isIOS()) {
				const iframe_doc = (iframe == null ? void 0 : iframe.contentDocument) || (iframe == null ? void 0 : iframe.contentWindow == null ? void 0 : iframe.contentWindow.document);
				const body = iframe_doc ? iframe_doc.body : null;
				body == null ? void 0 : body.classList.add("wu-fix-safari-preview");
				(body == null ? void 0 : body.style) && Object.assign(body.style, {
					position: "fixed",
					top: 0,
					right: 0,
					bottom: 0,
					left: 0,
					"overflow-y": "scroll",
					"-webkit-overflow-scrolling": "touch"
				});
			}
		});
		const adjustIframeHeight = () => {
			let _a2;
			const ee = ((_a2 = document.getElementById("switcher")) == null ? void 0 : _a2.offsetHeight) || 0;
			iframe.style.height = document.body.offsetHeight - ee + "px";
		};
		window.addEventListener("resize", adjustIframeHeight);
		adjustIframeHeight();
		const toggleList = () => {
			const list = document.querySelectorAll("#theme_list ul");
			list.forEach((element) => element.style.display = element.style.display === "none" ? "block" : "none");
		};
		toggleList();
		(_a = document.getElementById("template_selector")) == null ? void 0 : _a.addEventListener("click", (event) => {
			event.preventDefault();
			toggleList();
		});
		document.querySelectorAll("#theme_list ul li a").forEach((element) => element.addEventListener("click", (event) => {
			event.preventDefault();
			toggleList();
			const target = event.currentTarget;
			const href = target.getAttribute("href") || "";
			if (loadingIndicator) {
				loadingIndicator.style.display = "flex";
			}
			iframe.src = target.getAttribute("data-frame") || "";
			const selector = document.getElementById("template_selector");
			const selectorText = selector.firstChild;
			selectorText.nodeValue = target.getAttribute("data-title") || "";
			window.history.pushState({}, "", href);
		}));
		const headerBar = document.getElementById("header-bar");
		if (headerBar) {
			headerBar.style.display = "none";
		}
		const screenSizes = {
			desktop: "100%",
			tabletlandscape: "1040px",
			tabletportrait: "788px",
			mobilelandscape: "815px",
			mobileportrait: "375px",
			placebo: "0px"
		};
		document.querySelectorAll(".responsive a").forEach((element) => element.addEventListener("click", (event) => {
			const target = event.currentTarget;
			const width = Array.from(target.classList).reduce((acc, cur) => {
				if (screenSizes[ cur ]) {
					acc = screenSizes[ cur ];
				}
				return acc;
			}, "");
			iframe.style.width = width;
			iframe.style.transition = "200ms";
			document.querySelectorAll(".responsive a").forEach((element2) => element2.classList.remove("active"));
			target.classList.add("active");
		}));
		if (navigator.userAgent.match(/iPad/i) !== null) {
			iframe.style.height = "100%";
		}
	});
})()