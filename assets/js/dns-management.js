/* global jQuery, ajaxurl, wu_dns_config */
/**
 * DNS Management Vue.js Component
 *
 * Handles DNS record display and management in the Ultimate Multisite UI.
 *
 * @param {Function} $ jQuery.
 * @since 2.3.0
 */
(function($) {
	'use strict';

	/**
	 * Initialize DNS Management when DOM is ready.
	 */
	$(document).ready(function() {
		initDNSManagement();
	});

	/**
	 * Get the localized DNS management config.
	 *
	 * @return {Object} DNS management config.
	 */
	function getConfig() {
		return window.wu_dns_config || {};
	}

	/**
	 * Get a localized string with a fallback.
	 *
	 * @param {string} key      The config i18n key.
	 * @param {string} fallback The fallback string.
	 * @return {string} Localized string.
	 */
	function getString(key, fallback) {
		const config = getConfig();

		return (config.i18n && config.i18n[ key ]) || fallback;
	}

	/**
	 * Get the AJAX endpoint for front-end and admin contexts.
	 *
	 * @return {string} AJAX endpoint URL.
	 */
	function getAjaxUrl() {
		const config = getConfig();

		if (config.ajaxurl) {
			return config.ajaxurl;
		}

		return typeof ajaxurl !== 'undefined' ? ajaxurl : '';
	}

	/**
	 * Add a query argument to a URL.
	 *
	 * @param {string} url   Base URL.
	 * @param {string} key   Query key.
	 * @param {string} value Query value.
	 * @return {string} URL with the query argument.
	 */
	function addQueryArg(url, key, value) {
		return url + (url.indexOf('?') > -1 ? '&' : '?') + key + '=' + encodeURIComponent(value);
	}

	/**
	 * Show an error message.
	 *
	 * @param {string} message The message to show.
	 */
	function showError(message) {
		if (window.wu_ajax_error) {
			window.wu_ajax_error(message);
			return;
		}

		if (window.console && window.console.error) {
			window.console.error(message);
		}
	}

	/**
	 * Initialize the DNS Management Vue instance.
	 */
	function initDNSManagement() {
		const container = document.getElementById('wu-dns-records-table');

		if (! container || typeof Vue === 'undefined') {
			return;
		}

		// Check if Vue instance already exists
		if (container.__vue__) {
			return;
		}

		window.WU_DNS_Management = new Vue({
			el: '#wu-dns-records-table',
			data: {
				loading: true,
				error: null,
				records: [],
				readonly: false,
				domain: '',
				domainId: '',
				canManage: false,
				provider: '',
				recordTypes: [ 'A', 'AAAA', 'CNAME', 'MX', 'TXT' ],
				selectedRecords: [],
			},

			computed: {
				hasRecords() {
					return this.records && this.records.length > 0;
				},

				sortedRecords() {
					if (! this.records) {
						return [];
					}

					// Sort by type, then by name
					return [ ...this.records ].sort(function(a, b) {
						if (a.type !== b.type) {
							return a.type.localeCompare(b.type);
						}
						return a.name.localeCompare(b.name);
					});
				},
			},

			mounted() {
				const el = this.$el;

				this.domain = el.dataset.domain || '';
				this.domainId = el.dataset.domainId || '';
				this.canManage = el.dataset.canManage === 'true';

				if (this.domain) {
					this.loadRecords();
				}
			},

			methods: {
				/**
				 * Load DNS records from the server.
				 */
				loadRecords() {
					const self = this;
					const ajaxUrl = getAjaxUrl();
					const config = getConfig();

					this.loading = true;
					this.error = null;

					if (! ajaxUrl || ! config.nonce) {
						this.loading = false;
						this.error = getString('missing_config', 'DNS management could not be initialized. Please refresh the page and try again.');
						return;
					}

					$.ajax({
						url: ajaxUrl,
						method: 'POST',
						data: {
							action: 'wu_get_dns_records_for_domain',
							nonce: config.nonce,
							domain: this.domain,
						},
						success(response) {
							self.loading = false;

							if (response.success) {
								self.records = response.data.records || [];
								self.readonly = response.data.readonly || false;
								self.provider = response.data.provider || '';

								if (response.data.record_types) {
									self.recordTypes = response.data.record_types;
								}

								if (response.data.message && self.readonly) {
									self.error = response.data.message;
								}
							} else {
								self.error = (response.data && response.data.message) || getString('failed_load_records', 'Failed to load DNS records.');
							}
						},
						error(xhr, status, errorMsg) {
							self.loading = false;
							self.error = getString('network_error', 'Network error:') + ' ' + errorMsg;
						},
					});
				},

				/**
				 * Refresh the records list.
				 */
				refresh() {
					this.loadRecords();
				},

				/**
				 * Get CSS class for record type badge.
				 *
				 * @param {string} type The record type.
				 * @return {string} CSS classes.
				 */
				getTypeClass(type) {
					const classes = {
						A: 'wu-bg-blue-100 wu-text-blue-800',
						AAAA: 'wu-bg-purple-100 wu-text-purple-800',
						CNAME: 'wu-bg-green-100 wu-text-green-800',
						MX: 'wu-bg-orange-100 wu-text-orange-800',
						TXT: 'wu-bg-gray-100 wu-text-gray-800',
					};

					return classes[ type ] || 'wu-bg-gray-100 wu-text-gray-800';
				},

				/**
				 * Format TTL value for display.
				 *
				 * @param {number} seconds TTL in seconds.
				 * @return {string} Formatted TTL.
				 */
				formatTTL(seconds) {
					if (seconds === 1) {
						return 'Auto';
					}

					if (seconds < 60) {
						return seconds + 's';
					}

					if (seconds < 3600) {
						return Math.floor(seconds / 60) + 'm';
					}

					if (seconds < 86400) {
						return Math.floor(seconds / 3600) + 'h';
					}

					return Math.floor(seconds / 86400) + 'd';
				},

				/**
				 * Truncate content for display.
				 *
				 * @param {string} content   The content to truncate.
				 * @param {number} maxLength Maximum length.
				 * @return {string} Truncated content.
				 */
				truncateContent(content, maxLength) {
					maxLength = maxLength || 40;

					if (! content || content.length <= maxLength) {
						return content;
					}

					return content.substring(0, maxLength) + '...';
				},

				/**
				 * Get the edit URL for a record.
				 *
				 * @param {Object} record The record object.
				 * @return {string} Edit URL.
				 */
				getEditUrl(record) {
					const editUrl = getConfig().edit_url;

					if (! editUrl) {
						return '#';
					}

					return addQueryArg(addQueryArg(editUrl, 'record_id', record.id), 'domain_id', this.domainId);
				},

				/**
				 * Get the delete URL for a record.
				 *
				 * @param {Object} record The record object.
				 * @return {string} Delete URL.
				 */
				getDeleteUrl(record) {
					const deleteUrl = getConfig().delete_url;

					if (! deleteUrl) {
						return '#';
					}

					return addQueryArg(addQueryArg(deleteUrl, 'record_id', record.id), 'domain_id', this.domainId);
				},

				/**
				 * Toggle record selection.
				 *
				 * @param {string} recordId The record ID.
				 */
				toggleSelection(recordId) {
					const index = this.selectedRecords.indexOf(recordId);

					if (index > -1) {
						this.selectedRecords.splice(index, 1);
					} else {
						this.selectedRecords.push(recordId);
					}
				},

				/**
				 * Check if a record is selected.
				 *
				 * @param {string} recordId The record ID.
				 * @return {boolean} True if selected.
				 */
				isSelected(recordId) {
					return this.selectedRecords.indexOf(recordId) > -1;
				},

				/**
				 * Select all records.
				 */
				selectAll() {
					const self = this;

					this.selectedRecords = this.records.map(function(record) {
						return record.id;
					});
				},

				/**
				 * Deselect all records.
				 */
				deselectAll() {
					this.selectedRecords = [];
				},

				/**
				 * Delete selected records (admin bulk operation).
				 */
				deleteSelected() {
					if (! this.selectedRecords.length) {
						return;
					}

					// eslint-disable-next-line no-alert -- Bulk deletion needs a synchronous confirmation before the AJAX request.
					if (! window.confirm(getString('confirm_delete', 'Are you sure you want to delete the selected DNS records?'))) {
						return;
					}

					const self = this;
					const ajaxUrl = getAjaxUrl();
					const config = getConfig();

					if (! ajaxUrl || ! config.nonce) {
						showError(getString('missing_config', 'DNS management could not be initialized. Please refresh the page and try again.'));
						return;
					}

					$.ajax({
						url: ajaxUrl,
						method: 'POST',
						data: {
							action: 'wu_bulk_dns_operations',
							nonce: config.nonce,
							domain: this.domain,
							operation: 'delete',
							records: this.selectedRecords,
						},
						success(response) {
							if (response.success) {
								self.selectedRecords = [];
								self.loadRecords();
							} else {
								showError((response.data && response.data.message) || getString('failed_delete', 'Failed to delete records.'));
							}
						},
						error() {
							showError(getString('network_failed', 'Network error occurred.'));
						},
					});
				},

				/**
				 * Get proxied status display.
				 *
				 * @param {Object} record The record object.
				 * @return {string} Proxied status HTML.
				 */
				getProxiedStatus(record) {
					if (this.provider !== 'cloudflare') {
						return '';
					}

					if (record.proxied) {
						return '<span class="wu-text-orange-500" title="Proxied through Cloudflare">&#9729;</span>';
					}

					return '<span class="wu-text-gray-400" title="DNS only">&#9729;</span>';
				},
			},
		});
	}

	/**
	 * Reinitialize DNS management when modal content is loaded.
	 * This handles wubox modal scenarios.
	 */
	$(document.body).on('wubox:load', function() {
		setTimeout(function() {
			initDNSManagement();
		}, 100);
	});

	$(document).on('wubox-load', function() {
		setTimeout(function() {
			initDNSManagement();
		}, 100);
	});

}(jQuery));
