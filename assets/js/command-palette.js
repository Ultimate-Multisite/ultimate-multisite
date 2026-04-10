/**
 * Command Palette Integration for Ultimate Multisite
 *
 * Registers dynamic commands for searching entities using WordPress Command Palette.
 * Works on WP 6.4+ with progressive enhancements for WP 7.0+:
 *
 * - WP 6.4+: Basic command registration and dynamic search (core functionality).
 * - WP 6.9+: Admin-wide command palette (not just Gutenberg editor).
 * - WP 7.0+: Categories, keywords, and category labels in the palette UI.
 *
 * Icons use wp.primitives (SVG/Path) when available — the same approach WordPress
 * core uses in @wordpress/core-commands. There is no global wp.icons package.
 *
 * @package WP_Ultimo
 * @since 2.1.0
 */

(function (wp) {
	'use strict';

	// Bail if the minimum required APIs are not available (WP 6.4+).
	if (!wp || !wp.commands || !wp.element || !wp.data) {
		return;
	}

	var createElement = wp.element.createElement;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var useRef = wp.element.useRef;
	var __ = wp.i18n.__;
	var apiFetch = wp.apiFetch;

	// Progressive enhancement: wp.primitives for SVG icons (WP 6.1+).
	var SVG = wp.primitives ? wp.primitives.SVG : null;
	var Path = wp.primitives ? wp.primitives.Path : null;

	// Progressive enhancement: wp.compose.useDebounce (WP 6.1+).
	var useDebounce = wp.compose && wp.compose.useDebounce ? wp.compose.useDebounce : null;

	// Get configuration from localized script.
	var config = window.wuCommandPalette || {};
	var entities = config.entities || {};
	var restUrl = config.restUrl || '';
	var networkAdminUrl = config.networkAdminUrl || '';
	var customLinks = config.customLinks || [];

	/**
	 * SVG icon definitions using wp.primitives.
	 *
	 * WordPress command palette expects React elements (SVG components) for icons.
	 * These are created using wp.primitives.SVG and wp.primitives.Path, which is
	 * the same pattern WordPress core uses internally in @wordpress/core-commands.
	 *
	 * When wp.primitives is not available (older WP on non-Gutenberg pages),
	 * commands are registered without icons — they still work, just without
	 * the visual indicator.
	 *
	 * @type {Object|null}
	 */
	var icons = (SVG && Path) ? {
		'admin-users': createElement(SVG, {
			xmlns: 'http://www.w3.org/2000/svg',
			viewBox: '0 0 24 24'
		}, createElement(Path, {
			d: 'M15.5 9.5a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm0 1.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Zm-2.25 6v-2a2.75 2.75 0 0 1 2.75-2.75h1a2.75 2.75 0 0 1 2.75 2.75v2h-1.5v-2c0-.69-.56-1.25-1.25-1.25h-1c-.69 0-1.25.56-1.25 1.25v2h-1.5Zm-4.75-6a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm0 1.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5ZM4.25 17v-2a2.75 2.75 0 0 1 2.75-2.75h1A2.75 2.75 0 0 1 10.75 15v2h-1.5v-2c0-.69-.56-1.25-1.25-1.25H7c-.69 0-1.25.56-1.25 1.25v2h-1.5Z'
		})),

		'admin-multisite': createElement(SVG, {
			xmlns: 'http://www.w3.org/2000/svg',
			viewBox: '0 0 24 24'
		}, createElement(Path, {
			d: 'M18 5.5H6a.5.5 0 00-.5.5v3h13V6a.5.5 0 00-.5-.5zm.5 5H10v8h8a.5.5 0 00.5-.5v-7.5zm-10 0h-3V18a.5.5 0 00.5.5h2.5v-8zM6 4h12a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V6a2 2 0 012-2z'
		})),

		'id-alt': createElement(SVG, {
			xmlns: 'http://www.w3.org/2000/svg',
			viewBox: '0 0 24 24'
		}, createElement(Path, {
			fillRule: 'evenodd',
			clipRule: 'evenodd',
			d: 'M5 5.5h14a.5.5 0 0 1 .5.5v12a.5.5 0 0 1-.5.5H5a.5.5 0 0 1-.5-.5V6a.5.5 0 0 1 .5-.5ZM3 6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6Zm4 2v2h2V8H7Zm5 0v2h5V8h-5Zm-5 4v2h2v-2H7Zm5 0v2h5v-2h-5Z'
		})),

		'money-alt': createElement(SVG, {
			xmlns: 'http://www.w3.org/2000/svg',
			viewBox: '0 0 24 24'
		}, createElement(Path, {
			d: 'M3.25 12a8.75 8.75 0 1 1 17.5 0 8.75 8.75 0 0 1-17.5 0ZM12 4.75a7.25 7.25 0 1 0 0 14.5 7.25 7.25 0 0 0 0-14.5Zm.28 3.19c.95.12 1.72.7 1.72 1.74h-1.5c0-.23-.17-.45-.72-.52-.53-.07-1.08.07-1.08.52 0 .29.14.4.98.63 1.07.29 2.32.56 2.32 1.94 0 1.12-.9 1.65-1.72 1.78V15h-1.5v-.97c-.95-.12-1.78-.7-1.78-1.78h1.5c0 .3.23.52.78.59.53.07 1.08-.07 1.08-.52 0-.36-.2-.47-1.04-.7-1.07-.29-2.26-.56-2.26-1.87 0-1.04.77-1.6 1.72-1.73V7h1.5v.94Z'
		})),

		products: createElement(SVG, {
			xmlns: 'http://www.w3.org/2000/svg',
			viewBox: '0 0 24 24'
		}, createElement(Path, {
			d: 'M19 8h-1V6c0-1.1-.9-2-2-2H8c-1.1 0-2 .9-2 2v2H5c-1.1 0-2 .9-2 2v8c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2v-8c0-1.1-.9-2-2-2ZM7.5 6c0-.3.2-.5.5-.5h8c.3 0 .5.2.5.5v2h-9V6Zm12 12c0 .3-.2.5-.5.5H5c-.3 0-.5-.2-.5-.5v-8c0-.3.2-.5.5-.5h14c.3 0 .5.2.5.5v8Z'
		})),

		networking: createElement(SVG, {
			xmlns: 'http://www.w3.org/2000/svg',
			viewBox: '0 0 24 24'
		}, createElement(Path, {
			d: 'M12 3.3c-4.8 0-8.8 3.9-8.8 8.8 0 4.8 3.9 8.8 8.8 8.8 4.8 0 8.8-3.9 8.8-8.8s-4-8.8-8.8-8.8zm6.5 5.5h-2.6c-.3-1.3-.8-2.5-1.4-3.4 1.8.6 3.2 1.8 4 3.4zM12 4.8c.9 1 1.5 2.2 1.9 3.5h-3.8c.4-1.3 1-2.6 1.9-3.5zM5 13.6c-.2-.5-.3-1.1-.3-1.6s.1-1.1.3-1.6h3c-.1.5-.1 1.1-.1 1.6s0 1.1.1 1.6H5zm.5 1.6h2.6c.3 1.3.8 2.5 1.4 3.4-1.8-.6-3.2-1.8-4-3.4zM8.1 8.8H5.5c.8-1.6 2.2-2.8 4-3.4-.6.9-1.1 2.1-1.4 3.4zM12 19.2c-.9-1-1.5-2.2-1.9-3.5h3.8c-.4 1.3-1 2.6-1.9 3.5zm2.3-5.1H9.7c-.1-.5-.2-1.1-.2-1.6s.1-1.1.2-1.6h4.6c.1.5.2 1.1.2 1.6s-.1 1.1-.2 1.6zm.3 5c.6-.9 1.1-2.1 1.4-3.4h2.6c-.8 1.6-2.2 2.8-4 3.4zM16 13.6c.1-.5.1-1.1.1-1.6s0-1.1-.1-1.6h3c.2.5.3 1.1.3 1.6s-.1 1.1-.3 1.6h-3z'
		})),

		'tickets-alt': createElement(SVG, {
			xmlns: 'http://www.w3.org/2000/svg',
			viewBox: '0 0 24 24'
		}, createElement(Path, {
			d: 'M4.75 4a.75.75 0 0 0-.75.75v3.5h1.5V5.5H9V4H4.75ZM20 4.75a.75.75 0 0 0-.75-.75H15v1.5h3.5v2.75H20v-3.5ZM4 15.25v3.5c0 .414.336.75.75.75H9v-1.5H5.5V15.25H4ZM18.5 18H15v1.5h4.25a.75.75 0 0 0 .75-.75v-3.5h-1.5V18ZM7.5 8v8h1.5V8H7.5Zm3 0v8h1V8h-1Zm2 0v8h1.5V8H12.5Zm3 0v8h1V8h-1Z'
		})),

		'rest-api': createElement(SVG, {
			xmlns: 'http://www.w3.org/2000/svg',
			viewBox: '0 0 24 24'
		}, createElement(Path, {
			d: 'M20.8 10.7l-4.3-4.3-1.1 1.1 4.3 4.3c.1.1.1.3 0 .4l-4.3 4.3 1.1 1.1 4.3-4.3c.7-.8.7-1.9 0-2.6zM4.2 11.8l4.3-4.3-1.1-1.1-4.3 4.3c-.7.7-.7 1.8 0 2.5l4.3 4.3 1.1-1.1-4.3-4.2c-.2-.1-.2-.3 0-.4zm4.1 4.7h2.1l3.3-8.9h-2.1l-3.3 8.9z'
		})),

		megaphone: createElement(SVG, {
			xmlns: 'http://www.w3.org/2000/svg',
			viewBox: '0 0 24 24'
		}, createElement(Path, {
			d: 'M19 7.5c0-.3-.2-.5-.5-.5h-1.3l-5.4-2.7c-.3-.1-.6-.1-.8.1-.2.2-.3.4-.3.6v14c0 .2.1.5.3.6.1.1.3.1.4.1.1 0 .3 0 .4-.1l5.4-2.7h1.3c.3 0 .5-.2.5-.5v-9zm-6.8-1.4L16.5 8.5v7l-4.3 2.4V6.1zM5.5 9h1.8v6H5.5c-.3 0-.5-.2-.5-.5v-5c0-.3.2-.5.5-.5z'
		})),

		feedback: createElement(SVG, {
			xmlns: 'http://www.w3.org/2000/svg',
			viewBox: '0 0 24 24'
		}, createElement(Path, {
			fillRule: 'evenodd',
			clipRule: 'evenodd',
			d: 'M6.68 3.05h10.64a2.5 2.5 0 0 1 2.5 2.5v7.42a2.5 2.5 0 0 1-2.5 2.5H12.3l-3.56 3.05v-3.05H6.68a2.5 2.5 0 0 1-2.5-2.5V5.55a2.5 2.5 0 0 1 2.5-2.5ZM5.68 5.55a1 1 0 0 1 1-1h10.64a1 1 0 0 1 1 1v7.42a1 1 0 0 1-1 1h-4.6l-2.04 1.75v-1.75H6.68a1 1 0 0 1-1-1V5.55Z'
		})),

		dashboard: createElement(SVG, {
			xmlns: 'http://www.w3.org/2000/svg',
			viewBox: '0 0 24 24'
		}, createElement(Path, {
			d: 'M12 4L4 7.9V20h16V7.9L12 4zm6.5 14.5H14V13h-4v5.5H5.5V8.8L12 5.7l6.5 3.1v9.7z'
		})),

		'admin-settings': createElement(SVG, {
			xmlns: 'http://www.w3.org/2000/svg',
			viewBox: '0 0 24 24'
		}, createElement(Path, {
			d: 'M12 4c-4.4 0-8 3.6-8 8s3.6 8 8 8 8-3.6 8-8-3.6-8-8-8zm0 14.5c-3.6 0-6.5-2.9-6.5-6.5S8.4 5.5 12 5.5s6.5 2.9 6.5 6.5-2.9 6.5-6.5 6.5zM9 16l4.5-3L15 8.4l-4.5 3L9 16z'
		})),

		info: createElement(SVG, {
			xmlns: 'http://www.w3.org/2000/svg',
			viewBox: '0 0 24 24'
		}, createElement(Path, {
			d: 'M12 3.2c-4.8 0-8.8 3.9-8.8 8.8 0 4.8 3.9 8.8 8.8 8.8 4.8 0 8.8-3.9 8.8-8.8 0-4.8-4-8.8-8.8-8.8zm0 16c-4 0-7.2-3.3-7.2-7.2C4.8 8 8 4.8 12 4.8s7.2 3.3 7.2 7.2c0 4-3.2 7.2-7.2 7.2zM13 11v4h-2v-4h2zm0-3v1.5h-2V8h2z'
		})),

		external: createElement(SVG, {
			xmlns: 'http://www.w3.org/2000/svg',
			viewBox: '0 0 24 24'
		}, createElement(Path, {
			d: 'M19.5 4.5h-7V6h4.44l-5.97 5.97 1.06 1.06L18 7.06v4.44h1.5v-7Zm-13 1a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-3H17v3a.5.5 0 0 1-.5.5h-10a.5.5 0 0 1-.5-.5v-10a.5.5 0 0 1 .5-.5h3V5.5h-3Z'
		}))
	} : null;

	/**
	 * Get an icon element by dashicon name.
	 *
	 * Returns undefined when wp.primitives is not available, which causes
	 * commands to render without icons — a safe degradation on older WP.
	 *
	 * @param {string} name Dashicon name.
	 * @return {Object|undefined} React element or undefined.
	 */
	function getIcon(name) {
		if (!icons) {
			return undefined;
		}

		return icons[name] || undefined;
	}

	/**
	 * Debounce helper for search input.
	 *
	 * Uses wp.compose.useDebounce when available (WP 6.1+), otherwise falls
	 * back to a manual setTimeout-based debounce. The strategy is resolved
	 * once at load time so hooks are always called unconditionally (React
	 * Rules of Hooks).
	 *
	 * @param {string} value The value to debounce.
	 * @return {string} The debounced value.
	 */
	var useDebouncedValue = useDebounce
		? function useDebouncedValueCompose(value) {
			var _state = useState(''),
				debouncedValue = _state[0],
				setDebouncedValue = _state[1];
			var debounced = useDebounce(setDebouncedValue, 250);
			useEffect(function () {
				debounced(value);
				return function () {
					debounced.cancel();
				};
			}, [value, debounced]);
			return debouncedValue;
		}
		: function useDebouncedValueFallback(value) {
			var _state = useState(''),
				debouncedValue = _state[0],
				setDebouncedValue = _state[1];
			var timeoutRef = useRef(null);
			useEffect(function () {
				if (timeoutRef.current) {
					clearTimeout(timeoutRef.current);
				}
				timeoutRef.current = setTimeout(function () {
					setDebouncedValue(value);
				}, 250);
				return function () {
					if (timeoutRef.current) {
						clearTimeout(timeoutRef.current);
					}
				};
			}, [value]);
			return debouncedValue;
		};

	/**
	 * Custom hook for searching entities via REST API.
	 *
	 * Called by the command palette with search parameter. Debounces the
	 * search input, caches results, and returns commands with proper
	 * categories and icons when available.
	 *
	 * @param {Object} params Parameters object.
	 * @param {string} params.search The search term.
	 * @return {Object} Commands and loading state.
	 */
	function useEntitySearch({ search }) {
		var _useState = useState([]),
			commands = _useState[0],
			setCommands = _useState[1];
		var _useLoading = useState(false),
			isLoading = _useLoading[0],
			setIsLoading = _useLoading[1];
		var cacheRef = useRef({});

		var debouncedSearch = useDebouncedValue(search || '');

		// Fetch results when debounced search changes.
		useEffect(function () {
			if (!debouncedSearch || debouncedSearch.length < 2) {
				setCommands([]);
				setIsLoading(false);
				return;
			}

			// Check cache.
			if (cacheRef.current[debouncedSearch]) {
				setCommands(cacheRef.current[debouncedSearch]);
				setIsLoading(false);
				return;
			}

			setIsLoading(true);

			var searchUrl = restUrl + '?' + new URLSearchParams({
				query: debouncedSearch,
				limit: 15
			}).toString();

			apiFetch({ url: searchUrl })
				.then(function (response) {
					var results = response.results || [];

					var cmds = results.map(function (result) {
						return {
							name: 'ultimate-multisite/' + result.type + '-' + result.id,
							label: result.title,
							searchLabel: result.title + ' ' + result.subtitle,
							icon: getIcon(result.icon),
							// WP 7+: category label shown in palette UI.
							// WP 6.x: ignored safely, defaults to 'action' internally.
							category: 'action',
							callback: function ({ close }) {
								close();
								window.location.href = result.url;
							}
						};
					});

					cacheRef.current[debouncedSearch] = cmds;
					setCommands(cmds);
					setIsLoading(false);
				})
				.catch(function () {
					setCommands([]);
					setIsLoading(false);
				});
		}, [debouncedSearch]);

		return {
			commands: commands,
			isLoading: isLoading
		};
	}

	/**
	 * Register the entity search command loader.
	 *
	 * Uses wp.data.dispatch to register a dynamic command loader that
	 * searches entities via the REST API as the user types.
	 */
	function registerEntitySearchLoader() {
		if (!wp.commands.store) {
			return;
		}

		var dispatch = wp.data.dispatch(wp.commands.store);

		dispatch.registerCommandLoader({
			name: 'ultimate-multisite/entity-search',
			hook: useEntitySearch
		});
	}

	/**
	 * Register static commands for custom links from settings.
	 *
	 * These are user-defined links configured in Ultimate Multisite settings.
	 */
	function registerCustomLinks() {
		if (!customLinks || customLinks.length === 0 || !wp.commands.store) {
			return;
		}

		var dispatch = wp.data.dispatch(wp.commands.store);

		customLinks.forEach(function (link, index) {
			dispatch.registerCommand({
				name: 'ultimate-multisite/custom-link-' + index,
				label: link.title,
				icon: getIcon('external'),
				// WP 7+: shows "View" category label. WP 6.x: ignored safely.
				category: 'view',
				callback: function ({ close }) {
					close();
					window.location.href = link.url;
				}
			});
		});
	}

	/**
	 * Register static commands for Ultimate Multisite pages.
	 *
	 * Note: WordPress 7+ automatically registers admin menu items as "Go to: ..."
	 * commands via wp_enqueue_command_palette_assets(). Our commands add value on
	 * top of those with icons, keywords, and proper categorization.
	 *
	 * On WP 6.x, these are the only way to navigate to our pages via the palette.
	 *
	 * Properties that are progressive enhancements (ignored on older WP):
	 * - category: WP 7+ displays category labels (View, Action, etc.)
	 * - keywords: WP 7+ uses these for additional search matching
	 */
	function registerStaticCommands() {
		if (!wp.commands.store) {
			return;
		}

		var dispatch = wp.data.dispatch(wp.commands.store);

		// Dashboard command with keywords for discoverability.
		dispatch.registerCommand({
			name: 'ultimate-multisite/dashboard',
			label: __('Ultimate Multisite Dashboard', 'ultimate-multisite'),
			icon: getIcon('dashboard'),
			category: 'view',
			keywords: ['waas', 'multisite', 'network', 'overview'],
			callback: function ({ close }) {
				close();
				window.location.href = networkAdminUrl + 'admin.php?page=wp-ultimo';
			}
		});

		// Settings command.
		dispatch.registerCommand({
			name: 'ultimate-multisite/settings',
			label: __('Ultimate Multisite Settings', 'ultimate-multisite'),
			icon: getIcon('admin-settings'),
			category: 'view',
			keywords: ['configuration', 'options', 'setup'],
			callback: function ({ close }) {
				close();
				window.location.href = networkAdminUrl + 'admin.php?page=wp-ultimo-settings';
			}
		});

		// System info command.
		dispatch.registerCommand({
			name: 'ultimate-multisite/system-info',
			label: __('System Information', 'ultimate-multisite'),
			icon: getIcon('info'),
			category: 'view',
			keywords: ['debug', 'status', 'health', 'diagnostics'],
			callback: function ({ close }) {
				close();
				window.location.href = networkAdminUrl + 'admin.php?page=wp-ultimo-system-info';
			}
		});

		// Entity-specific keywords for better search matching (WP 7+).
		var entityKeywords = {
			customer: ['users', 'subscribers', 'clients', 'accounts'],
			site: ['subsites', 'blogs', 'websites', 'domains'],
			membership: ['subscriptions', 'plans', 'recurring'],
			payment: ['transactions', 'invoices', 'billing', 'revenue'],
			product: ['plans', 'pricing', 'packages', 'offers'],
			domain: ['domains', 'mapping', 'dns', 'urls'],
			discount_code: ['coupons', 'promotions', 'discounts', 'vouchers'],
			webhook: ['hooks', 'integrations', 'api', 'notifications'],
			broadcast: ['emails', 'messages', 'announcements', 'notifications'],
			checkout_form: ['forms', 'registration', 'signup', 'onboarding']
		};

		// Register list pages for each entity type.
		Object.keys(entities).forEach(function (slug) {
			var entity = entities[slug];

			dispatch.registerCommand({
				name: 'ultimate-multisite/list-' + slug,
				label: entity.label_plural || entity.label + 's',
				icon: getIcon(entity.icon),
				category: 'view',
				keywords: entityKeywords[slug] || [],
				callback: function ({ close }) {
					close();
					window.location.href = networkAdminUrl + 'admin.php?page=wu-' + slug.replaceAll('_', '-') + 's';
				}
			});
		});
	}

	/**
	 * Initialize command palette integration.
	 */
	function init() {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', init);
			return;
		}

		registerEntitySearchLoader();
		registerStaticCommands();
		registerCustomLinks();
	}

	init();

})(window.wp);
