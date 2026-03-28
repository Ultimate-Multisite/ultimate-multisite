<?php
/**
 * DNS Record Form View
 *
 * Shared form for adding/editing DNS records.
 *
 * @package WP_Ultimo
 * @subpackage Views/Domain
 * @since 2.3.0
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * @var array                   $record           Existing record data (for edit mode)
 * @var int                     $domain_id        Domain ID
 * @var string                  $domain_name      Domain name
 * @var string                  $mode             'add' or 'edit'
 * @var array                   $allowed_types    Allowed record types
 * @var bool                    $show_proxied     Show proxied toggle (Cloudflare)
 */

$mode          = $mode ?? 'add';
$record        = $record ?? [];
$show_proxied  = $show_proxied ?? false;
$allowed_types = $allowed_types ?? ['A', 'AAAA', 'CNAME', 'MX', 'TXT'];
?>

<div class="wu-styling" data-wu-app="dns_record_form" data-state='
<?php
echo wp_json_encode(
	[
		'record_type' => $record['type'] ?? 'A',
		'proxied'     => $record['proxied'] ?? false,
	]
);
?>
'>

	<form class="wu-modal-form wu-widget-list wu-striped wu-m-0"
			method="post"
			id="wu-dns-record-form">

		<?php wp_nonce_field('wu_dns_nonce', 'nonce'); ?>

		<input type="hidden" name="domain_id" value="<?php echo esc_attr($domain_id); ?>">
		<input type="hidden" name="domain" value="<?php echo esc_attr($domain_name); ?>">

		<?php if ('edit' === $mode && ! empty($record['id'])) : ?>
			<input type="hidden" name="record_id" value="<?php echo esc_attr($record['id']); ?>">
		<?php endif; ?>

		<!-- Record Type -->
		<div class="wu-w-full wu-box-border wu-items-center wu-flex wu-justify-between wu-p-4 wu-m-0 wu-border-t wu-border-l-0 wu-border-r-0 wu-border-b-0 wu-border-gray-300 wu-border-solid wu-bg-gray-100">
			<div class="wu-w-1/3">
				<label for="record_type" class="wu-font-medium wu-text-gray-700">
					<?php esc_html_e('Type', 'ultimate-multisite'); ?>
					<span class="wu-text-red-500">*</span>
				</label>
			</div>
			<div class="wu-w-2/3">
				<select name="record[type]"
						id="record_type"
						class="wu-w-full"
						v-model="record_type"
						required
						<?php echo 'edit' === $mode ? 'disabled' : ''; ?>>
					<?php foreach ($allowed_types as $type) : ?>
						<option value="<?php echo esc_attr($type); ?>"
							<?php selected(($record['type'] ?? 'A'), $type); ?>>
							<?php echo esc_html($type); ?>
							<?php
							// Add description
							$descriptions = [
								'A'     => __('IPv4 Address', 'ultimate-multisite'),
								'AAAA'  => __('IPv6 Address', 'ultimate-multisite'),
								'CNAME' => __('Alias/Canonical Name', 'ultimate-multisite'),
								'MX'    => __('Mail Exchange', 'ultimate-multisite'),
								'TXT'   => __('Text Record', 'ultimate-multisite'),
							];
							if (isset($descriptions[ $type ])) {
								echo ' - ' . esc_html($descriptions[ $type ]);
							}
							?>
						</option>
					<?php endforeach; ?>
				</select>
				<?php if ('edit' === $mode) : ?>
					<input type="hidden" name="record[type]" value="<?php echo esc_attr($record['type'] ?? 'A'); ?>">
				<?php endif; ?>
			</div>
		</div>

		<!-- Record Name -->
		<div class="wu-w-full wu-box-border wu-items-center wu-flex wu-justify-between wu-p-4 wu-m-0 wu-border-t wu-border-l-0 wu-border-r-0 wu-border-b-0 wu-border-gray-300 wu-border-solid">
			<div class="wu-w-1/3">
				<label for="record_name" class="wu-font-medium wu-text-gray-700">
					<?php esc_html_e('Name', 'ultimate-multisite'); ?>
					<span class="wu-text-red-500">*</span>
				</label>
				<p class="wu-text-xs wu-text-gray-500 wu-m-0 wu-mt-1">
					<?php esc_html_e('Use @ for root domain', 'ultimate-multisite'); ?>
				</p>
			</div>
			<div class="wu-w-2/3">
				<div class="wu-flex wu-items-center">
					<input type="text"
							name="record[name]"
							id="record_name"
							class="wu-w-full"
							placeholder="<?php esc_attr_e('@ or subdomain', 'ultimate-multisite'); ?>"
							value="<?php echo esc_attr($record['name'] ?? ''); ?>"
							required>
					<span class="wu-ml-2 wu-text-gray-500 wu-text-sm wu-whitespace-nowrap">
						.<?php echo esc_html($domain_name); ?>
					</span>
				</div>
			</div>
		</div>

		<!-- Record Content -->
		<div class="wu-w-full wu-box-border wu-items-center wu-flex wu-justify-between wu-p-4 wu-m-0 wu-border-t wu-border-l-0 wu-border-r-0 wu-border-b-0 wu-border-gray-300 wu-border-solid">
			<div class="wu-w-1/3">
				<label for="record_content" class="wu-font-medium wu-text-gray-700">
					<?php esc_html_e('Content', 'ultimate-multisite'); ?>
					<span class="wu-text-red-500">*</span>
				</label>
				<p class="wu-text-xs wu-text-gray-500 wu-m-0 wu-mt-1">
					<span v-if="record_type === 'A'"><?php esc_html_e('IPv4 address', 'ultimate-multisite'); ?></span>
					<span v-else-if="record_type === 'AAAA'"><?php esc_html_e('IPv6 address', 'ultimate-multisite'); ?></span>
					<span v-else-if="record_type === 'CNAME'"><?php esc_html_e('Target hostname', 'ultimate-multisite'); ?></span>
					<span v-else-if="record_type === 'MX'"><?php esc_html_e('Mail server', 'ultimate-multisite'); ?></span>
					<span v-else-if="record_type === 'TXT'"><?php esc_html_e('Text value', 'ultimate-multisite'); ?></span>
				</p>
			</div>
			<div class="wu-w-2/3">
				<input type="text"
						name="record[content]"
						id="record_content"
						class="wu-w-full"
						placeholder="<?php esc_attr_e('Value', 'ultimate-multisite'); ?>"
						value="<?php echo esc_attr($record['content'] ?? ''); ?>"
						required>
			</div>
		</div>

		<!-- Priority (MX only) -->
		<div class="wu-w-full wu-box-border wu-items-center wu-flex wu-justify-between wu-p-4 wu-m-0 wu-border-t wu-border-l-0 wu-border-r-0 wu-border-b-0 wu-border-gray-300 wu-border-solid"
			v-if="record_type === 'MX'">
			<div class="wu-w-1/3">
				<label for="record_priority" class="wu-font-medium wu-text-gray-700">
					<?php esc_html_e('Priority', 'ultimate-multisite'); ?>
					<span class="wu-text-red-500">*</span>
				</label>
				<p class="wu-text-xs wu-text-gray-500 wu-m-0 wu-mt-1">
					<?php esc_html_e('Lower = higher priority', 'ultimate-multisite'); ?>
				</p>
			</div>
			<div class="wu-w-2/3">
				<input type="number"
						name="record[priority]"
						id="record_priority"
						class="wu-w-full"
						min="0"
						max="65535"
						placeholder="10"
						value="<?php echo esc_attr($record['priority'] ?? 10); ?>">
			</div>
		</div>

		<!-- TTL -->
		<div class="wu-w-full wu-box-border wu-items-center wu-flex wu-justify-between wu-p-4 wu-m-0 wu-border-t wu-border-l-0 wu-border-r-0 wu-border-b-0 wu-border-gray-300 wu-border-solid">
			<div class="wu-w-1/3">
				<label for="record_ttl" class="wu-font-medium wu-text-gray-700">
					<?php esc_html_e('TTL', 'ultimate-multisite'); ?>
				</label>
				<p class="wu-text-xs wu-text-gray-500 wu-m-0 wu-mt-1">
					<?php esc_html_e('Time to live', 'ultimate-multisite'); ?>
				</p>
			</div>
			<div class="wu-w-2/3">
				<select name="record[ttl]" id="record_ttl" class="wu-w-full">
					<option value="1" <?php selected(($record['ttl'] ?? 1), 1); ?>><?php esc_html_e('Auto', 'ultimate-multisite'); ?></option>
					<option value="300" <?php selected(($record['ttl'] ?? 0), 300); ?>><?php esc_html_e('5 minutes', 'ultimate-multisite'); ?></option>
					<option value="3600" <?php selected(($record['ttl'] ?? 0), 3600); ?>><?php esc_html_e('1 hour', 'ultimate-multisite'); ?></option>
					<option value="14400" <?php selected(($record['ttl'] ?? 0), 14400); ?>><?php esc_html_e('4 hours', 'ultimate-multisite'); ?></option>
					<option value="86400" <?php selected(($record['ttl'] ?? 0), 86400); ?>><?php esc_html_e('1 day', 'ultimate-multisite'); ?></option>
				</select>
			</div>
		</div>

		<?php if ($show_proxied) : ?>
		<!-- Proxied (Cloudflare only) -->
		<div class="wu-w-full wu-box-border wu-items-center wu-flex wu-justify-between wu-p-4 wu-m-0 wu-border-t wu-border-l-0 wu-border-r-0 wu-border-b-0 wu-border-gray-300 wu-border-solid"
			v-if="record_type === 'A' || record_type === 'AAAA' || record_type === 'CNAME'">
			<div class="wu-w-1/3">
				<label for="record_proxied" class="wu-font-medium wu-text-gray-700">
					<?php esc_html_e('Proxied', 'ultimate-multisite'); ?>
				</label>
				<p class="wu-text-xs wu-text-gray-500 wu-m-0 wu-mt-1">
					<?php esc_html_e('Route through Cloudflare', 'ultimate-multisite'); ?>
				</p>
			</div>
			<div class="wu-w-2/3">
				<label class="wu-toggle">
					<input type="checkbox"
							name="record[proxied]"
							id="record_proxied"
							value="1"
							v-model="proxied"
							<?php checked(! empty($record['proxied'])); ?>>
					<span class="wu-toggle-slider"></span>
				</label>
			</div>
		</div>
		<?php endif; ?>

		<!-- Submit -->
		<div class="wu-w-full wu-box-border wu-items-center wu-flex wu-justify-end wu-p-4 wu-m-0 wu-border-t wu-border-l-0 wu-border-r-0 wu-border-b-0 wu-border-gray-300 wu-border-solid wu-bg-gray-50">
			<button type="submit" class="button button-primary">
				<?php if ('edit' === $mode) : ?>
					<?php esc_html_e('Update Record', 'ultimate-multisite'); ?>
				<?php else : ?>
					<?php esc_html_e('Add Record', 'ultimate-multisite'); ?>
				<?php endif; ?>
			</button>
		</div>

	</form>
</div>
