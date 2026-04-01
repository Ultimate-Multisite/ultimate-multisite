/**
 * Command Palette Integration for Ultimate Multisite
 *
 * Registers dynamic commands for searching entities using WordPress Command Palette.
 *
 * @package WP_Ultimo
 * @since 2.1.0
 */

(function (wp) {
	'use strict';

	// Check if wp.commands is available
	if (!wp || !wp.commands || !wp.element) {
		console.log('[Ultimate Multisite] Command palette not available - wp.commands or wp.element missing');
		return;
	}

	console.log('[Ultimate Multisite] Command palette API detected, initializing...');

	const { useState, useEffect, useRef } = wp.element;
	const { __ } = wp.i18n;
	const apiFetch = wp.apiFetch;

	// Get configuration from localized script
	const config = window.wuCommandPalette || {};
	const entities = config.entities || {};
	const restUrl = config.restUrl || '';
	const networkAdminUrl = config.networkAdminUrl || '';
	const customLinks = config.customLinks || [];

	console.log('[Ultimate Multisite] Configuration loaded:', {
		entitiesCount: Object.keys(entities).length,
		restUrl: restUrl,
		customLinksCount: customLinks.length,
		entities: entities
	});

	/**
	 * Custom hook for searching entities.
	 * Called by the command palette with search parameter.
	 *
	 * @param {Object} params Parameters object.
	 * @param {string} params.search The search term.
	 * @return {Object} Commands and loading state.
	 */
	function useEntitySearch({ search }) {
		const [commands, setCommands] = useState([]);
		const [isLoading, setIsLoading] = useState(false);
		const searchTimeoutRef = useRef(null);
		const cacheRef = useRef({});

		useEffect(() => {
			// Clear any pending timeouts
			if (searchTimeoutRef.current) {
				clearTimeout(searchTimeoutRef.current);
			}

			// Minimum 2 characters required
			if (!search || search.length < 2) {
				setCommands([]);
				setIsLoading(false);
				return;
			}

			// Check cache
			if (cacheRef.current[search]) {
				setCommands(cacheRef.current[search]);
				setIsLoading(false);
				return;
			}

			// Debounce: wait 300ms before searching
			setIsLoading(true);

			searchTimeoutRef.current = setTimeout(() => {
				const searchUrl = restUrl + '?' + new URLSearchParams({
					query: search,
					limit: 15
				}).toString();

				console.log('[Ultimate Multisite] Searching:', searchUrl);

				apiFetch({
					url: searchUrl
				})
					.then((response) => {
						console.log('[Ultimate Multisite] Search response:', response);
						const results = response.results || [];

						const cmds = results.map((result) => {
							const cmd = {
								name: 'ultimate-multisite/' + result.type + '-' + result.id,
								label: result.title,
								searchLabel: result.title + ' ' + result.subtitle,
								callback: ({ close }) => {
									close();
									window.location.href = result.url;
								}
							};

							// Add icon if available
							const icon = getIcon(result.icon);
							if (icon) {
								cmd.icon = icon;
							}

							return cmd;
						});

						// Cache the results
						cacheRef.current[search] = cmds;

						console.log('[Ultimate Multisite] Commands generated:', cmds.length);
						setCommands(cmds);
						setIsLoading(false);
					})
					.catch((error) => {
						console.error('[Ultimate Multisite] Search error:', error);
						setCommands([]);
						setIsLoading(false);
					});
			}, 300);

			return () => {
				if (searchTimeoutRef.current) {
					clearTimeout(searchTimeoutRef.current);
				}
			};
		}, [search]);

		return {
			commands,
			isLoading
		};
	}

	/**
	 * Get icon component for command palette.
	 *
	 * WordPress command palette expects icon components from @wordpress/icons package.
	 * We'll map dashicon names to WordPress icons when available.
	 *
	 * @param {string} icon Dashicon name.
	 * @return {Object|undefined} WordPress icon component or undefined.
	 */
	function getIcon(icon) {
		// Check if @wordpress/icons is available
		if (!wp.icons) {
			console.log('[Ultimate Multisite] wp.icons not available');
			return undefined;
		}

		// Map dashicon names to @wordpress/icons
		const iconMap = {
			'admin-users': wp.icons.people,
			'admin-multisite': wp.icons.layout,
			'id-alt': wp.icons.card,
			'money-alt': wp.icons.payment,
			'products': wp.icons.box,
			'networking': wp.icons.globe,
			'tickets-alt': wp.icons.tag,
			'rest-api': wp.icons.code,
			'megaphone': wp.icons.megaphone,
			'feedback': wp.icons.commentContent,
			'dashboard': wp.icons.home,
			'admin-settings': wp.icons.settings,
			'info': wp.icons.info,
			'external': wp.icons.external
		};

		const mappedIcon = iconMap[icon];
		if (!mappedIcon) {
			console.log('[Ultimate Multisite] Icon not found:', icon);
		}

		return mappedIcon || undefined;
	}

	/**
	 * Register the entity search command loader.
	 */
	function registerEntitySearchLoader() {
		if (!wp.data || !wp.data.dispatch || !wp.commands || !wp.commands.store) {
			return;
		}

		const { registerCommandLoader } = wp.data.dispatch(wp.commands.store);

		registerCommandLoader({
			name: 'ultimate-multisite/entity-search',
			hook: useEntitySearch
		});

		console.log('[Ultimate Multisite] Command loader registered');
	}

	/**
	 * Register static commands for custom links.
	 */
	function registerCustomLinks() {
		if (!customLinks || customLinks.length === 0) {
			return;
		}

		const { registerCommand } = wp.data.dispatch(wp.commands.store);

		customLinks.forEach((link, index) => {
			const cmd = {
				name: 'ultimate-multisite/custom-link-' + index,
				label: link.title,
				callback: ({ close }) => {
					close();
					window.location.href = link.url;
				}
			};

			// Add external link icon
			const icon = getIcon('external');
			if (icon) {
				cmd.icon = icon;
			}

			registerCommand(cmd);
		});
	}

	/**
	 * Register static commands for Ultimate Multisite pages.
	 */
	function registerStaticCommands() {
		if (!wp.data || !wp.data.dispatch || !wp.commands || !wp.commands.store) {
			return;
		}

		const { registerCommand } = wp.data.dispatch(wp.commands.store);

		// Register common Ultimate Multisite pages
		const staticCommands = [];

		// Dashboard command
		const dashboardCmd = {
			name: 'ultimate-multisite/dashboard',
			label: __('Ultimate Multisite Dashboard', 'ultimate-multisite'),
			callback: ({ close }) => {
				close();
				window.location.href = networkAdminUrl + 'admin.php?page=wp-ultimo';
			}
		};
		const dashboardIcon = getIcon('dashboard');
		if (dashboardIcon) dashboardCmd.icon = dashboardIcon;
		staticCommands.push(dashboardCmd);

		// Settings command
		const settingsCmd = {
			name: 'ultimate-multisite/settings',
			label: __('Ultimate Multisite Settings', 'ultimate-multisite'),
			callback: ({ close }) => {
				close();
				window.location.href = networkAdminUrl + 'admin.php?page=wp-ultimo-settings';
			}
		};
		const settingsIcon = getIcon('admin-settings');
		if (settingsIcon) settingsCmd.icon = settingsIcon;
		staticCommands.push(settingsCmd);

		// System info command
		const systemInfoCmd = {
			name: 'ultimate-multisite/system-info',
			label: __('System Information', 'ultimate-multisite'),
			callback: ({ close }) => {
				close();
				window.location.href = networkAdminUrl + 'admin.php?page=wp-ultimo-system-info';
			}
		};
		const systemInfoIcon = getIcon('info');
		if (systemInfoIcon) systemInfoCmd.icon = systemInfoIcon;
		staticCommands.push(systemInfoCmd);

		// Register list pages for each entity type
		Object.keys(entities).forEach((slug) => {
			const entity = entities[slug];

			const cmd = {
				name: 'ultimate-multisite/list-' + slug,
				label: entity.label_plural || entity.label + 's',
				callback: ({ close }) => {
					close();
					window.location.href = networkAdminUrl + 'admin.php?page=wu-' + slug.replaceAll('_', '-') + 's';
				}
			};

			// Add icon if available
			const icon = getIcon(entity.icon);
			if (icon) {
				cmd.icon = icon;
			}

			staticCommands.push(cmd);
		});

		// Register all static commands
		staticCommands.forEach((command) => {
			try {
				registerCommand(command);
			} catch (error) {
				console.error('Error registering command:', command.name, error);
			}
		});
	}

	/**
	 * Initialize command palette integration.
	 */
	function init() {
		// Wait for DOM to be ready
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', init);
			return;
		}

		// Register entity search loader (dynamic commands)
		registerEntitySearchLoader();

		// Register static commands
		registerStaticCommands();

		// Register custom links
		registerCustomLinks();
	}

	// Initialize
	init();

})(window.wp);
