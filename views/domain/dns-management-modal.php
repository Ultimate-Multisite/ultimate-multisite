<?php
/**
 * DNS Management Modal View
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
 * @var int                     $site_id
 * @var bool                    $can_manage
 * @var bool                    $has_provider
 * @var string                  $provider_name
 */
?>
<div id="wu-dns-management" class="wu-styling">
	<div class="wu-p-4">
		<h3 class="wu-m-0 wu-mb-4">
			<?php
			printf(
				/* translators: %s: domain name */
				esc_html__('DNS Records for %s', 'ultimate-multisite'),
				'<strong>' . esc_html($domain->get_domain()) . '</strong>'
			);
			?>
		</h3>

		<?php if ($can_manage && $has_provider) : ?>
			<div class="wu-mb-4 wu-flex wu-gap-2">
				<a href="<?php echo esc_url(wu_get_form_url('user_add_dns_record', ['domain_id' => $domain_id])); ?>"
					class="button button-primary wubox"
					title="<?php esc_attr_e('Add DNS Record', 'ultimate-multisite'); ?>">
					<span class="dashicons dashicons-plus-alt2 wu-mr-1" style="margin-top: 3px;"></span>
					<?php esc_html_e('Add Record', 'ultimate-multisite'); ?>
				</a>
				<button type="button"
						class="button"
						onclick="if(window.WU_DNS_Management) window.WU_DNS_Management.refresh();">
					<span class="dashicons dashicons-update wu-mr-1" style="margin-top: 3px;"></span>
					<?php esc_html_e('Refresh', 'ultimate-multisite'); ?>
				</button>
			</div>
		<?php endif; ?>

		<?php
		// Get DNS management instructions
		$instructions = wu_get_setting('dns_management_instructions', '');
		if ($instructions && $can_manage) :
			?>
			<div class="wu-bg-blue-50 wu-border wu-border-blue-200 wu-rounded wu-p-3 wu-mb-4">
				<span class="dashicons dashicons-info wu-text-blue-600 wu-mr-2"></span>
				<?php echo wp_kses_post($instructions); ?>
			</div>
		<?php endif; ?>

		<div id="wu-dns-records-table"
			data-domain="<?php echo esc_attr($domain->get_domain()); ?>"
			data-domain-id="<?php echo esc_attr($domain_id); ?>"
			data-can-manage="<?php echo $can_manage && $has_provider ? 'true' : 'false'; ?>">

			<!-- Loading State -->
			<div v-if="loading" class="wu-text-center wu-py-8">
				<span class="spinner is-active" style="float: none;"></span>
				<p class="wu-m-0 wu-mt-2 wu-text-gray-600">
					<?php esc_html_e('Loading DNS records...', 'ultimate-multisite'); ?>
				</p>
			</div>

			<!-- Error State -->
			<div v-if="!loading && error && readonly" class="wu-bg-yellow-50 wu-border wu-border-yellow-200 wu-rounded wu-p-3 wu-mb-4">
				<span class="dashicons dashicons-warning wu-text-yellow-600 wu-mr-2"></span>
				{{ error }}
			</div>

			<!-- Records Table -->
			<table v-if="!loading && hasRecords" class="wp-list-table widefat fixed striped wu-mt-0">
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
			</div>

			<!-- Read-only Notice -->
			<div v-if="readonly && !loading && hasRecords" class="wu-bg-gray-50 wu-border wu-border-gray-200 wu-rounded wu-p-3 wu-mt-4">
				<span class="dashicons dashicons-lock wu-text-gray-500 wu-mr-2"></span>
				<?php esc_html_e('DNS records are read-only. Your hosting provider does not support DNS management through this interface.', 'ultimate-multisite'); ?>
			</div>
		</div>
	</div>
</div>

<script>
	// Initialize config for this modal
	window.wu_dns_config = window.wu_dns_config || {
		nonce: '<?php echo esc_js(wp_create_nonce('wu_dns_nonce')); ?>',
		add_url: '<?php echo esc_js(wu_get_form_url('user_add_dns_record', ['domain_id' => $domain_id])); ?>',
		edit_url: '<?php echo esc_js(wu_get_form_url('user_edit_dns_record', ['domain_id' => $domain_id])); ?>',
		delete_url: '<?php echo esc_js(wu_get_form_url('user_delete_dns_record', ['domain_id' => $domain_id])); ?>',
	};
</script>
