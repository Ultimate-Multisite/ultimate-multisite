/* global jQuery, ajaxurl, wu_dns_config */
/**
 * DNS Management Vue.js Component
 *
 * Handles DNS record display and management in the Ultimate Multisite UI.
 *
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
	 * Initialize the DNS Management Vue instance.
	 */
	function initDNSManagement() {
		const container = document.getElementById('wu-dns-records-table');

		if (!container || typeof Vue === 'undefined') {
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
				recordTypes: ['A', 'AAAA', 'CNAME', 'MX', 'TXT'],
				selectedRecords: [],
			},

			computed: {
				hasRecords: function() {
					return this.records && this.records.length > 0;
				},

				sortedRecords: function() {
					if (!this.records) {
						return [];
					}

					// Sort by type, then by name
					return [...this.records].sort(function(a, b) {
						if (a.type !== b.type) {
							return a.type.localeCompare(b.type);
						}
						return a.name.localeCompare(b.name);
					});
				},
			},

			mounted: function() {
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
				loadRecords: function() {
					const self = this;

					this.loading = true;
					this.error = null;

					$.ajax({
						url: ajaxurl,
						method: 'POST',
						data: {
							action: 'wu_get_dns_records_for_domain',
							nonce: wu_dns_config.nonce,
							domain: this.domain,
						},
						success: function(response) {
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
								self.error = (response.data && response.data.message) || 'Failed to load DNS records.';
							}
						},
						error: function(xhr, status, errorMsg) {
							self.loading = false;
							self.error = 'Network error: ' + errorMsg;
						},
					});
				},

				/**
				 * Refresh the records list.
				 */
				refresh: function() {
					this.loadRecords();
				},

				/**
				 * Get CSS class for record type badge.
				 *
				 * @param {string} type The record type.
				 * @return {string} CSS classes.
				 */
				getTypeClass: function(type) {
					const classes = {
						'A': 'wu-bg-blue-100 wu-text-blue-800',
						'AAAA': 'wu-bg-purple-100 wu-text-purple-800',
						'CNAME': 'wu-bg-green-100 wu-text-green-800',
						'MX': 'wu-bg-orange-100 wu-text-orange-800',
						'TXT': 'wu-bg-gray-100 wu-text-gray-800',
					};

					return classes[type] || 'wu-bg-gray-100 wu-text-gray-800';
				},

				/**
				 * Format TTL value for display.
				 *
				 * @param {number} seconds TTL in seconds.
				 * @return {string} Formatted TTL.
				 */
				formatTTL: function(seconds) {
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
				 * @param {string} content The content to truncate.
				 * @param {number} maxLength Maximum length.
				 * @return {string} Truncated content.
				 */
				truncateContent: function(content, maxLength) {
					maxLength = maxLength || 40;

					if (!content || content.length <= maxLength) {
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
				getEditUrl: function(record) {
					if (!wu_dns_config.edit_url) {
						return '#';
					}

					return wu_dns_config.edit_url +
						'&record_id=' + encodeURIComponent(record.id) +
						'&domain_id=' + encodeURIComponent(this.domainId);
				},

				/**
				 * Get the delete URL for a record.
				 *
				 * @param {Object} record The record object.
				 * @return {string} Delete URL.
				 */
				getDeleteUrl: function(record) {
					if (!wu_dns_config.delete_url) {
						return '#';
					}

					return wu_dns_config.delete_url +
						'&record_id=' + encodeURIComponent(record.id) +
						'&domain_id=' + encodeURIComponent(this.domainId);
				},

				/**
				 * Toggle record selection.
				 *
				 * @param {string} recordId The record ID.
				 */
				toggleSelection: function(recordId) {
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
				isSelected: function(recordId) {
					return this.selectedRecords.indexOf(recordId) > -1;
				},

				/**
				 * Select all records.
				 */
				selectAll: function() {
					const self = this;

					this.selectedRecords = this.records.map(function(record) {
						return record.id;
					});
				},

				/**
				 * Deselect all records.
				 */
				deselectAll: function() {
					this.selectedRecords = [];
				},

				/**
				 * Delete selected records (admin bulk operation).
				 */
				deleteSelected: function() {
					if (!this.selectedRecords.length) {
						return;
					}

					if (!confirm('Are you sure you want to delete ' + this.selectedRecords.length + ' selected records?')) {
						return;
					}

					const self = this;

					$.ajax({
						url: ajaxurl,
						method: 'POST',
						data: {
							action: 'wu_bulk_dns_operations',
							nonce: wu_dns_config.nonce,
							domain: this.domain,
							operation: 'delete',
							records: this.selectedRecords,
						},
						success: function(response) {
							if (response.success) {
								self.selectedRecords = [];
								self.loadRecords();
							} else {
								alert('Error: ' + ((response.data && response.data.message) || 'Failed to delete records.'));
							}
						},
						error: function() {
							alert('Network error occurred.');
						},
					});
				},

				/**
				 * Get proxied status display.
				 *
				 * @param {Object} record The record object.
				 * @return {string} Proxied status HTML.
				 */
				getProxiedStatus: function(record) {
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
	$(document).on('wubox-load', function() {
		setTimeout(function() {
			initDNSManagement();
		}, 100);
	});

})(jQuery);
