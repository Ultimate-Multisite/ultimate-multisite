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

	function createSiteItem(site) {

		const item = createMenuItem('blog-' + site.id, site.name, site.url);
		const menu = document.createElement('ul');
		const actions = [
			[ 'dashboard', site.dashboard_url ],
			[ 'new_post', site.new_post_url ],
			[ 'comments', site.comments_url ],
			[ 'visit', site.visit_url ],
		];

		item.classList.add('menupop');
		item.querySelector('.ab-item').setAttribute('aria-expanded', 'false');
		menu.className = 'ab-submenu';
		menu.setAttribute('role', 'menu');

		actions.forEach(function (action) {

			if (action[ 1 ]) {
				menu.appendChild(createMenuItem('blog-' + site.id + '-' + action[ 0 ], labels[ action[ 0 ] ], action[ 1 ]));
			}
		});

		if (menu.children.length) {
			const wrapper = document.createElement('div');

			wrapper.className = 'ab-sub-wrapper';
			wrapper.appendChild(menu);
			item.appendChild(wrapper);
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
			loaded: false,
			nextPage: 1,
		};

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
				load(state.nextPage);
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
				load(state.nextPage);
			});
			list.insertBefore(more, allSites);
		}

		function load(page) {

			if (state.loading || ! page) {
				return;
			}

			state.loading = true;
			setStatus(labels.loading || 'Loading sites…');

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

				(data.sites || []).forEach(function (site) {
					list.insertBefore(createSiteItem(site), allSites);
				});

				state.loaded = true;
				state.nextPage = data.next_page || 0;
				setStatus((data.sites || []).length ? '' : (labels.empty || 'No additional sites are available.'));
				addLoadMore();

			}).catch(function () {

				setStatus(labels.failed || 'Sites could not be loaded.', true);

			}).finally(function () {

				state.loading = false;

			});
		}

		function loadInitialPage() {

			if (! state.loaded) {
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
