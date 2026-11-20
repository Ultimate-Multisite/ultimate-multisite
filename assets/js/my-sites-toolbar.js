(function (window, document) {

	'use strict';

	const config = window.wu_my_sites_toolbar || {};
	const labels = config.labels || {};

	function createMenuItem(id, label, url) {

		const item = document.createElement('li');
		const link = document.createElement('a');

		item.id = 'wp-admin-bar-' + id;
		item.setAttribute('role', 'group');
		link.className = 'ab-item';
		link.href = url;
		link.setAttribute('role', 'menuitem');
		link.textContent = label;
		item.appendChild(link);

		return item;
	}

	function setExpanded(item, expanded) {

		const link = item.querySelector(':scope > .ab-item');

		item.classList.toggle('hover', expanded);

		if (link) {
			link.setAttribute('aria-expanded', expanded ? 'true' : 'false');
		}
	}

	/*
	 * Core admin-bar.js binds submenu behaviour only to items present on
	 * DOMContentLoaded, and with JavaScript enabled core CSS reveals a nested
	 * .ab-sub-wrapper only when its parent has the "hover" class. Lazily added
	 * site entries therefore need their own equivalent handlers.
	 */
	function bindSubmenu(item) {

		const link = item.querySelector(':scope > .ab-item');
		let touched = false;

		item.addEventListener('mouseenter', function () {

			// Touch emulates mouseenter before click; the click handler owns taps.
			if (! touched) {
				setExpanded(item, true);
			}
		});

		item.addEventListener('mouseleave', function () {

			if (! touched) {
				setExpanded(item, false);
			}
		});

		item.addEventListener('focusout', function (event) {

			if (! item.contains(event.relatedTarget)) {
				setExpanded(item, false);
			}
		});

		item.addEventListener('keydown', function (event) {

			if ('Escape' === event.key && item.classList.contains('hover')) {
				event.preventDefault();
				event.stopPropagation();
				setExpanded(item, false);
				link.focus();
			}
		});

		link.addEventListener('keydown', function (event) {

			// Like core: the first Enter opens the flyout, the second follows the link.
			if ('Enter' === event.key && ! item.classList.contains('hover')) {
				event.preventDefault();
				setExpanded(item, true);
			}
		});

		link.addEventListener('pointerdown', function (event) {
			touched = 'touch' === event.pointerType;
		});

		link.addEventListener('click', function (event) {

			// Like core: the first tap opens the flyout, the second follows the link.
			if (touched && ! item.classList.contains('hover')) {
				event.preventDefault();

				item.parentNode.querySelectorAll(':scope > li.hover').forEach(function (sibling) {
					setExpanded(sibling, false);
				});

				setExpanded(item, true);
			}

			touched = false;
		});
	}

	function createSiteItem(site) {

		const item = createMenuItem('blog-' + site.id, site.name, site.url);
		const menu = document.createElement('ul');
		const actions = [
			[ 'dashboard', site.dashboard_url ],
			[ 'new_post', site.new_post_url ],
			[ 'comments', site.comments_url ],
			[ 'visit', site.visit_url ],
		];

		menu.className = 'ab-submenu';
		menu.setAttribute('role', 'menu');

		actions.forEach(function (action) {

			if (action[ 1 ]) {
				menu.appendChild(createMenuItem('blog-' + site.id + '-' + action[ 0 ], labels[ action[ 0 ] ], action[ 1 ]));
			}
		});

		if (menu.children.length) {
			const wrapper = document.createElement('div');
			const link = item.querySelector(':scope > .ab-item');

			item.classList.add('menupop');
			link.setAttribute('aria-haspopup', 'true');
			link.setAttribute('aria-expanded', 'false');
			wrapper.className = 'ab-sub-wrapper';
			wrapper.appendChild(menu);
			item.appendChild(wrapper);
			bindSubmenu(item);
		}

		return item;
	}

	function init() {

		const root = document.getElementById('wp-admin-bar-my-sites');
		const list = document.getElementById('wp-admin-bar-my-sites-list');
		const allSites = document.getElementById('wp-admin-bar-wu-my-sites-toolbar-all');

		if (! root || ! list || ! allSites || ! config.ajax_url || ! config.nonce) {
			return;
		}

		const trigger = root.querySelector(':scope > .ab-item');
		const status = document.createElement('li');
		const state = {
			loading: false,
			attempted: false,
			nextPage: 1,
		};

		// Keyboard activation of a link dispatches a click with detail 0.
		function isKeyboardClick(event) {
			return 0 === event.detail;
		}

		status.id = 'wp-admin-bar-wu-my-sites-toolbar-status';
		status.setAttribute('role', 'status');
		status.setAttribute('aria-live', 'polite');
		list.insertBefore(status, allSites);

		function setStatus(message, retry) {

			status.textContent = '';

			if (! message) {
				return;
			}

			if (! retry) {
				status.textContent = message;
				return;
			}

			const link = document.createElement('a');

			link.className = 'ab-item';
			link.href = '#';
			link.setAttribute('role', 'menuitem');
			link.textContent = message + ' ' + (labels.retry || 'Retry');
			link.addEventListener('click', function (event) {

				event.preventDefault();
				load(state.nextPage, isKeyboardClick(event));
			});
			status.appendChild(link);
		}

		function addLoadMore() {

			const existing = document.getElementById('wp-admin-bar-wu-my-sites-toolbar-more');

			if (existing || ! state.nextPage) {
				return;
			}

			const more = createMenuItem('wu-my-sites-toolbar-more', labels.load_more || 'Load more sites', '#');

			more.querySelector('.ab-item').addEventListener('click', function (event) {

				event.preventDefault();
				more.remove();
				load(state.nextPage, isKeyboardClick(event));
			});
			list.insertBefore(more, allSites);
		}

		/*
		 * Keeps keyboard users inside the menu after the control they activated
		 * is replaced: the first new site, else the "All My Sites" link.
		 */
		function restoreFocus(target) {

			const link = (target || allSites).querySelector('.ab-item');

			if (link) {
				link.focus();
			}
		}

		function load(page, keepFocus) {

			if (state.loading || ! page) {
				return;
			}

			let ownsFocus = Boolean(keepFocus);

			function cancelFocusRestore() {
				ownsFocus = false;
			}

			state.loading = true;
			setStatus(labels.loading || 'Loading sites…');

			if (ownsFocus) {
				document.addEventListener('focusin', cancelFocusRestore, true);
			}

			const request = new window.URLSearchParams({
				action: config.action,
				nonce: config.nonce,
				page,
			});

			window.fetch(config.ajax_url, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
				},
				body: request.toString(),
			}).then(function (response) {

				if (! response.ok) {
					throw new Error('request_failed');
				}

				return response.json();

			}).then(function (response) {

				if (! response || ! response.success || ! response.data) {
					throw new Error('invalid_response');
				}

				const data = response.data;
				let firstNew = null;

				(data.sites || []).forEach(function (site) {

					// The server-rendered fallback entry may already be present.
					if (document.getElementById('wp-admin-bar-blog-' + site.id)) {
						return;
					}

					const item = createSiteItem(site);

					list.insertBefore(item, allSites);
					firstNew = firstNew || item;
				});

				state.nextPage = data.next_page || 0;
				setStatus((data.sites || []).length ? '' : (labels.empty || 'No additional sites are available.'));
				addLoadMore();

				if (ownsFocus) {
					document.removeEventListener('focusin', cancelFocusRestore, true);
					ownsFocus = false;
					restoreFocus(firstNew);
				}

			}).catch(function () {

				setStatus(labels.failed || 'Sites could not be loaded.', true);

				if (ownsFocus) {
					document.removeEventListener('focusin', cancelFocusRestore, true);
					ownsFocus = false;
					restoreFocus(status);
				}

			}).finally(function () {

				document.removeEventListener('focusin', cancelFocusRestore, true);
				state.loading = false;

			});
		}

		/*
		 * Opening the menu requests the first page once. After a failure, only the
		 * explicit Retry action requests it again, so focusing Retry does not
		 * replace the control the user is about to activate.
		 */
		function loadInitialPage() {

			if (! state.attempted) {
				state.attempted = true;
				load(1);
			}
		}

		root.addEventListener('mouseenter', loadInitialPage);
		root.addEventListener('focusin', loadInitialPage);
		root.addEventListener('pointerdown', function (event) {

			if ('touch' === event.pointerType) {
				loadInitialPage();
			}
		});

		if (trigger) {
			trigger.addEventListener('keydown', function (event) {

				if ('Enter' === event.key || ' ' === event.key || 'ArrowDown' === event.key) {
					loadInitialPage();
				}
			});
		}

		root.addEventListener('keydown', function (event) {

			if ('Escape' === event.key && trigger && root.contains(root.ownerDocument.activeElement)) {
				event.preventDefault();
				trigger.focus();
			}
		});
	}

	if ('loading' === document.readyState) {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

}(window, document));
