<?php
/**
 * Admin DNS Management View
 *
 * DNS management widget for the domain edit admin page.
 * Shows provider-managed DNS records with full CRUD capabilities.
 *
 * @package WP_Ultimo
 * @subpackage Views/Domain
 * @since 2.3.0
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * @var WP_Ultimo\Models\Domain $domain
 * @var int                     $domain_id
 * @var bool                    $can_manage
 * @var bool                    $has_provider
 * @var string                  $provider_name
 * @var string                  $add_url
 */
?>
<div id="wu-admin-dns-management" class="wu-widget-list-table wu-advanced-filters wu--m-3 wu-mt-2 wu--mb-3 wu-border-0 wu-border-t wu-border-solid wu-border-gray-400">

	<?php if ($has_provider) : ?>
		<!-- Provider-managed DNS Records -->
		<div class="wu-p-4 wu-bg-gray-50 wu-border-b wu-border-solid wu-border-0 wu-border-gray-300">
			<div class="wu-flex wu-items-center wu-justify-between">
				<div>
					<span class="wu-text-sm wu-text-gray-600">
						<?php
						printf(
							/* translators: %s: Provider name */
							esc_html__('DNS managed via %s', 'ultimate-multisite'),
							'<strong>' . esc_html($provider_name) . '</strong>'
						);
						?>
					</span>
				</div>
				<div class="wu-flex wu-gap-2">
					<a href="<?php echo esc_url($add_url); ?>"
						class="button button-primary wubox"
						title="<?php esc_attr_e('Add DNS Record', 'ultimate-multisite'); ?>">
						<span class="dashicons dashicons-plus-alt2 wu-mr-1" style="margin-top: 3px;"></span>
						<?php esc_html_e('Add Record', 'ultimate-multisite'); ?>
					</a>
					<button type="button"
							class="button"
							onclick="if(window.WU_DNS_Management) window.WU_DNS_Management.refresh();">
						<span class="dashicons dashicons-update" style="margin-top: 3px;"></span>
					</button>
				</div>
			</div>
		</div>

		<!-- DNS Records Table (Provider) -->
		<div id="wu-dns-records-table"
			data-domain="<?php echo esc_attr($domain->get_domain()); ?>"
			data-domain-id="<?php echo esc_attr($domain_id); ?>"
			data-can-manage="<?php echo $can_manage ? 'true' : 'false'; ?>">

			<!-- Loading State -->
			<div v-if="loading" class="wu-text-center wu-py-8">
				<span class="spinner is-active" style="float: none;"></span>
				<p class="wu-m-0 wu-mt-2 wu-text-gray-600">
					<?php esc_html_e('Loading DNS records from provider...', 'ultimate-multisite'); ?>
				</p>
			</div>

			<!-- Error State -->
			<div v-if="!loading && error && !readonly" class="wu-bg-red-50 wu-border wu-border-red-200 wu-rounded wu-p-3 wu-m-4">
				<span class="dashicons dashicons-warning wu-text-red-600 wu-mr-2"></span>
				{{ error }}
			</div>

			<!-- Records Table -->
			<table v-if="!loading && hasRecords" class="wp-list-table widefat fixed striped wu-mt-0 wu-border-t-0">
				<thead>
					<tr>
						<th class="wu-w-16"><?php esc_html_e('Type', 'ultimate-multisite'); ?></th>
						<th><?php esc_html_e('Name', 'ultimate-multisite'); ?></th>
						<th><?php esc_html_e('Content', 'ultimate-multisite'); ?></th>
						<th class="wu-w-16"><?php esc_html_e('TTL', 'ultimate-multisite'); ?></th>
						<th v-if="canManage && !readonly" class="wu-w-24"><?php esc_html_e('Actions', 'ultimate-multisite'); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="record in sortedRecords" :key="record.id">
						<td>
							<span class="wu-inline-block wu-px-2 wu-py-1 wu-rounded wu-text-xs wu-font-medium" :class="getTypeClass(record.type)">
								{{ record.type }}
							</span>
							<span v-if="provider === 'cloudflare'" v-html="getProxiedStatus(record)"></span>
						</td>
						<td class="wu-font-mono wu-text-sm">
							{{ record.name }}
						</td>
						<td class="wu-font-mono wu-text-sm wu-max-w-xs wu-truncate" :title="record.content">
							{{ truncateContent(record.content, 40) }}
							<span v-if="record.priority !== null && record.priority !== undefined" class="wu-text-gray-500 wu-text-xs">
								(Priority: {{ record.priority }})
							</span>
						</td>
						<td class="wu-text-gray-600">
							{{ formatTTL(record.ttl) }}
						</td>
						<td v-if="canManage && !readonly">
							<div class="wu-flex wu-gap-2">
								<a :href="getEditUrl(record)"
									class="wubox wu-text-blue-600 hover:wu-text-blue-800"
									title="<?php esc_attr_e('Edit', 'ultimate-multisite'); ?>">
									<span class="dashicons dashicons-edit"></span>
								</a>
								<a :href="getDeleteUrl(record)"
									class="wubox wu-text-red-600 hover:wu-text-red-800"
									title="<?php esc_attr_e('Delete', 'ultimate-multisite'); ?>">
									<span class="dashicons dashicons-trash"></span>
								</a>
							</div>
						</td>
					</tr>
				</tbody>
			</table>

			<!-- Empty State -->
			<div v-if="!loading && !hasRecords && !error" class="wu-text-center wu-py-8 wu-text-gray-500">
				<span class="dashicons dashicons-admin-site-alt3 wu-text-4xl wu-text-gray-300"></span>
				<p class="wu-m-0 wu-mt-2"><?php esc_html_e('No DNS records found.', 'ultimate-multisite'); ?></p>
				<p class="wu-m-0 wu-mt-2">
					<a href="<?php echo esc_url($add_url); ?>" class="wubox button button-primary">
						<?php esc_html_e('Add Your First Record', 'ultimate-multisite'); ?>
					</a>
				</p>
			</div>

			<!-- Read-only Notice -->
			<div v-if="readonly && !loading" class="wu-bg-gray-50 wu-border wu-border-gray-200 wu-rounded wu-p-3 wu-m-4">
				<span class="dashicons dashicons-lock wu-text-gray-500 wu-mr-2"></span>
				<?php esc_html_e('DNS records are read-only. The provider does not support DNS management through this interface.', 'ultimate-multisite'); ?>
			</div>
		</div>

	<?php else : ?>
		<!-- No Provider Configured - Show Read-Only PHP DNS Lookup -->
		<div class="wu-p-4 wu-bg-yellow-50 wu-border-b wu-border-solid wu-border-0 wu-border-yellow-200">
			<span class="dashicons dashicons-warning wu-text-yellow-600 wu-mr-2"></span>
			<span class="wu-text-sm wu-text-gray-700">
				<?php esc_html_e('No DNS provider configured. Showing read-only DNS lookup results.', 'ultimate-multisite'); ?>
			</span>
			<a href="<?php echo esc_url(wu_network_admin_url('wp-ultimo-settings', ['tab' => 'integrations'])); ?>" class="wu-ml-2 wu-text-blue-600">
				<?php esc_html_e('Configure a provider', 'ultimate-multisite'); ?>
			</a>
		</div>

		<!-- Read-only DNS Table (PHP DNS Lookup) -->
		<div id="wu-dns-table">
			<table class="wp-list-table widefat fixed striped wu-border-t-0" v-cloak>
				<thead>
					<tr>
						<th class="wu-w-4/12"><?php esc_html_e('Host', 'ultimate-multisite'); ?></th>
						<th class="wu-w-2/12"><?php esc_html_e('Type', 'ultimate-multisite'); ?></th>
						<th class="wu-w-4/12"><?php esc_html_e('IP / Target', 'ultimate-multisite'); ?></th>
						<th class="wu-w-2/12"><?php esc_html_e('TTL', 'ultimate-multisite'); ?></th>
					</tr>
				</thead>

				<tbody v-if="loading">
					<tr>
						<td colspan="4">
							<?php esc_html_e('Loading DNS entries...', 'ultimate-multisite'); ?>
						</td>
					</tr>
				</tbody>

			<tbody v-if="!loading && error">
				<tr>
					<td colspan="4">
						<div class="wu-mt-0 wu-p-4 wu-bg-red-100 wu-border wu-border-solid wu-border-red-200 wu-rounded-sm wu-text-red-500" v-if="error && error[0]">{{ error[0].message }}</div>
					</td>
				</tr>
			</tbody>

				<tbody v-if="!loading && !error">
					<tr v-for="dns in results.entries">
						<td>{{ dns.host }}<span v-html="dns.tag" v-if="dns.tag"></span></td>
						<td>{{ dns.type }}</td>
						<td>{{ dns.data }}</td>
						<td>{{ dns.ttl }}</td>
					</tr>

					<tr v-for="dns in results.auth">
						<td>{{ dns.host }}<span v-html="dns.tag" v-if="dns.tag"></span></td>
						<td>{{ dns.type }}</td>
						<td>{{ dns.data }}</td>
						<td>{{ dns.ttl }}</td>
					</tr>

					<tr v-for="dns in results.additional">
						<td>{{ dns.host }}<span v-html="dns.tag" v-if="dns.tag"></span></td>
						<td>{{ dns.type }}</td>
						<td>{{ dns.data }}</td>
						<td>{{ dns.ttl }}</td>
					</tr>

					<tr>
						<td colspan="2"><?php esc_html_e('Your Network IP', 'ultimate-multisite'); ?></td>
						<td colspan="2" class="wu-text-left">{{ results.network_ip }}</td>
					</tr>
				</tbody>
			</table>
		</div>
	<?php endif; ?>

</div>
