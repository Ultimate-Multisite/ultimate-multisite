<?php
/**
 * External Cron dashboard view.
 *
 * @since 2.3.0
 * @package WP_Ultimo
 * @subpackage Views
 */

defined('ABSPATH') || exit;

/**
 * Variables available in this template.
 *
 * @var WP_Ultimo\Admin_Pages\External_Cron_Admin_Page $page
 * @var bool   $is_connected
 * @var bool   $is_enabled
 * @var string $site_id
 * @var string $granularity
 * @var int    $last_sync
 * @var int    $schedule_count
 * @var array  $recent_logs
 * @var array  $service_status
 * @var string $subscription_url
 * @var string $nonce
 */
?>

<div id="wp-ultimo-wrap" class="<?php wu_wrap_use_container(); ?> wrap wu-styling">

	<h1 class="wp-heading-inline"><?php esc_html_e('External Cron Service', 'ultimate-multisite'); ?></h1>

	<hr class="wp-header-end">

	<div class="wu-grid wu-grid-cols-1 lg:wu-grid-cols-3 wu-gap-4 wu-mt-4">

		<!-- Status Card -->
		<div class="wu-bg-white wu-rounded wu-shadow wu-p-4">
			<h3 class="wu-text-gray-800 wu-text-lg wu-font-semibold wu-m-0 wu-mb-4">
				<?php esc_html_e('Service Status', 'ultimate-multisite'); ?>
			</h3>

			<div class="wu-flex wu-items-center wu-mb-4">
				<span class="wu-inline-block wu-w-3 wu-h-3 wu-rounded-full wu-mr-2 wu-bg-<?php echo esc_attr($service_status['color']); ?>-500"></span>
				<span class="wu-text-lg wu-font-medium"><?php echo esc_html($service_status['label']); ?></span>
			</div>

			<?php if ($is_connected) : ?>
				<div class="wu-text-sm wu-text-gray-600 wu-space-y-2">
					<p class="wu-m-0">
						<strong><?php esc_html_e('Site ID:', 'ultimate-multisite'); ?></strong>
						<code class="wu-ml-1"><?php echo esc_html($site_id); ?></code>
					</p>
					<p class="wu-m-0">
						<strong><?php esc_html_e('Granularity:', 'ultimate-multisite'); ?></strong>
						<span class="wu-ml-1"><?php echo 'network' === $granularity ? esc_html__('Per Network', 'ultimate-multisite') : esc_html__('Per Site', 'ultimate-multisite'); ?></span>
					</p>
					<p class="wu-m-0">
						<strong><?php esc_html_e('Scheduled Jobs:', 'ultimate-multisite'); ?></strong>
						<span class="wu-ml-1"><?php echo esc_html($schedule_count); ?></span>
					</p>
					<?php if ($last_sync > 0) : ?>
						<p class="wu-m-0">
							<strong><?php esc_html_e('Last Sync:', 'ultimate-multisite'); ?></strong>
							<span class="wu-ml-1"><?php echo esc_html(human_time_diff($last_sync, time()) . ' ' . __('ago', 'ultimate-multisite')); ?></span>
						</p>
					<?php endif; ?>
				</div>
			<?php else : ?>
				<p class="wu-text-gray-600 wu-m-0">
					<?php esc_html_e('Connect your network to start using the External Cron Service for reliable scheduled task execution.', 'ultimate-multisite'); ?>
				</p>
			<?php endif; ?>
		</div>

		<!-- Actions Card -->
		<div class="wu-bg-white wu-rounded wu-shadow wu-p-4">
			<h3 class="wu-text-gray-800 wu-text-lg wu-font-semibold wu-m-0 wu-mb-4">
				<?php esc_html_e('Actions', 'ultimate-multisite'); ?>
			</h3>

			<div class="wu-space-y-3">
				<?php if ( ! $is_connected) : ?>
					<p class="wu-text-gray-600 wu-text-sm wu-m-0 wu-mb-3">
						<?php
						printf(
							wp_kses(
								/* translators: %s: link to purchase page */
								__('To use the External Cron Service, you need an active subscription. <a href="%s" target="_blank" class="wu-no-underline">Purchase a subscription</a> to get started.', 'ultimate-multisite'),
								[
									'a' => [
										'href'   => [],
										'target' => [],
										'class'  => [],
									],
								]
							),
							esc_url($subscription_url)
						);
						?>
					</p>
					<button type="button" class="button button-primary wu-external-cron-connect" data-nonce="<?php echo esc_attr($nonce); ?>">
						<?php esc_html_e('Connect Network', 'ultimate-multisite'); ?>
					</button>
				<?php else : ?>
					<div class="wu-flex wu-items-center wu-mb-3">
						<label class="wu-flex wu-items-center wu-cursor-pointer">
							<input type="checkbox" class="wu-external-cron-toggle wu-mr-2" data-nonce="<?php echo esc_attr($nonce); ?>" <?php checked($is_enabled); ?>>
							<span class="wu-text-sm"><?php esc_html_e('Enable External Cron Service', 'ultimate-multisite'); ?></span>
						</label>
					</div>

					<button type="button" class="button wu-external-cron-sync wu-mr-2" data-nonce="<?php echo esc_attr($nonce); ?>">
						<span class="dashicons dashicons-update wu-align-middle wu-mr-1"></span>
						<?php esc_html_e('Sync Schedules', 'ultimate-multisite'); ?>
					</button>

					<button type="button" class="button wu-external-cron-disconnect" data-nonce="<?php echo esc_attr($nonce); ?>">
						<?php esc_html_e('Disconnect', 'ultimate-multisite'); ?>
					</button>
				<?php endif; ?>
			</div>
		</div>

		<!-- Info Card -->
		<div class="wu-bg-white wu-rounded wu-shadow wu-p-4">
			<h3 class="wu-text-gray-800 wu-text-lg wu-font-semibold wu-m-0 wu-mb-4">
				<?php esc_html_e('About External Cron', 'ultimate-multisite'); ?>
			</h3>

			<div class="wu-text-sm wu-text-gray-600 wu-space-y-2">
				<p class="wu-m-0">
					<?php esc_html_e('The External Cron Service provides reliable, precise execution of WordPress scheduled tasks.', 'ultimate-multisite'); ?>
				</p>
				<ul class="wu-list-disc wu-pl-5 wu-m-0 wu-mt-2">
					<li><?php esc_html_e('Executes jobs at exact scheduled times', 'ultimate-multisite'); ?></li>
					<li><?php esc_html_e('Works independently of site traffic', 'ultimate-multisite'); ?></li>
					<li><?php esc_html_e('Supports both WP Cron and Action Scheduler', 'ultimate-multisite'); ?></li>
					<li><?php esc_html_e('Automatic retry on failures', 'ultimate-multisite'); ?></li>
				</ul>
			</div>
		</div>

	</div>

	<!-- Recent Logs -->
	<?php if ($is_connected && ! empty($recent_logs)) : ?>
		<div class="wu-bg-white wu-rounded wu-shadow wu-p-4 wu-mt-4">
			<h3 class="wu-text-gray-800 wu-text-lg wu-font-semibold wu-m-0 wu-mb-4">
				<?php esc_html_e('Recent Executions', 'ultimate-multisite'); ?>
			</h3>

			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e('Hook', 'ultimate-multisite'); ?></th>
						<th><?php esc_html_e('Status', 'ultimate-multisite'); ?></th>
						<th><?php esc_html_e('Duration', 'ultimate-multisite'); ?></th>
						<th><?php esc_html_e('Time', 'ultimate-multisite'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($recent_logs as $log) : ?>
						<tr>
							<td><code><?php echo esc_html($log['hook_name'] ?? ''); ?></code></td>
							<td>
								<?php
								$status       = $log['status'] ?? 'unknown';
								$status_class = 'wu-text-gray-600';
								if ('success' === $status) {
									$status_class = 'wu-text-green-600';
								} elseif (in_array($status, ['failed', 'timeout'], true)) {
									$status_class = 'wu-text-red-600';
								}
								?>
								<span class="<?php echo esc_attr($status_class); ?>"><?php echo esc_html(ucfirst($status)); ?></span>
							</td>
							<td><?php echo isset($log['duration_ms']) ? esc_html($log['duration_ms'] . 'ms') : '-'; ?></td>
							<td>
							<?php
							$exec_time = isset($log['execution_time']) ? strtotime($log['execution_time']) : false;
							echo $exec_time ? esc_html(human_time_diff($exec_time, time()) . ' ' . __('ago', 'ultimate-multisite')) : '-';
							?>
						</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php elseif ($is_connected) : ?>
		<div class="wu-bg-white wu-rounded wu-shadow wu-p-4 wu-mt-4">
			<h3 class="wu-text-gray-800 wu-text-lg wu-font-semibold wu-m-0 wu-mb-4">
				<?php esc_html_e('Recent Executions', 'ultimate-multisite'); ?>
			</h3>
			<p class="wu-text-gray-600 wu-m-0">
				<?php esc_html_e('No execution logs available yet. Logs will appear here once the service starts executing your scheduled tasks.', 'ultimate-multisite'); ?>
			</p>
		</div>
	<?php endif; ?>

</div>

<script>
jQuery(function($) {
	// Connect
	$('.wu-external-cron-connect').on('click', function() {
		var $btn = $(this);
		$btn.prop('disabled', true).text('<?php echo esc_js(__('Connecting...', 'ultimate-multisite')); ?>');

		$.post(ajaxurl, {
			action: 'wu_external_cron_connect',
			nonce: $btn.data('nonce')
		}, function(response) {
			if (response.success) {
				alert(response.data.message);
				location.reload();
			} else {
				alert(response.data.message || '<?php echo esc_js(__('Connection failed.', 'ultimate-multisite')); ?>');
				$btn.prop('disabled', false).text('<?php echo esc_js(__('Connect Network', 'ultimate-multisite')); ?>');
			}
		}).fail(function() {
			alert('<?php echo esc_js(__('Connection failed.', 'ultimate-multisite')); ?>');
			$btn.prop('disabled', false).text('<?php echo esc_js(__('Connect Network', 'ultimate-multisite')); ?>');
		});
	});

	// Disconnect
	$('.wu-external-cron-disconnect').on('click', function() {
		if (!confirm('<?php echo esc_js(__('Are you sure you want to disconnect from the External Cron Service?', 'ultimate-multisite')); ?>')) {
			return;
		}

		var $btn = $(this);
		$btn.prop('disabled', true).text('<?php echo esc_js(__('Disconnecting...', 'ultimate-multisite')); ?>');

		$.post(ajaxurl, {
			action: 'wu_external_cron_disconnect',
			nonce: $btn.data('nonce')
		}, function(response) {
			if (response.success) {
				alert(response.data.message);
				location.reload();
			} else {
				alert(response.data.message || '<?php echo esc_js(__('Disconnect failed.', 'ultimate-multisite')); ?>');
				$btn.prop('disabled', false).text('<?php echo esc_js(__('Disconnect', 'ultimate-multisite')); ?>');
			}
		}).fail(function() {
			alert('<?php echo esc_js(__('Disconnect failed.', 'ultimate-multisite')); ?>');
			$btn.prop('disabled', false).text('<?php echo esc_js(__('Disconnect', 'ultimate-multisite')); ?>');
		});
	});

	// Sync
	$('.wu-external-cron-sync').on('click', function() {
		var $btn = $(this);
		var originalText = $btn.html();
		$btn.prop('disabled', true).html('<span class="dashicons dashicons-update wu-align-middle wu-mr-1 wu-animate-spin"></span><?php echo esc_js(__('Syncing...', 'ultimate-multisite')); ?>');

		$.post(ajaxurl, {
			action: 'wu_external_cron_sync',
			nonce: $btn.data('nonce')
		}, function(response) {
			if (response.success) {
				alert(response.data.message);
				location.reload();
			} else {
				alert(response.data.message || '<?php echo esc_js(__('Sync failed.', 'ultimate-multisite')); ?>');
			}
			$btn.prop('disabled', false).html(originalText);
		}).fail(function() {
			alert('<?php echo esc_js(__('Sync failed.', 'ultimate-multisite')); ?>');
			$btn.prop('disabled', false).html(originalText);
		});
	});

	// Toggle
	$('.wu-external-cron-toggle').on('change', function() {
		var $toggle = $(this);
		var enabled = $toggle.is(':checked');

		$.post(ajaxurl, {
			action: 'wu_external_cron_toggle',
			nonce: $toggle.data('nonce'),
			enabled: enabled ? 1 : 0
		}, function(response) {
			if (response.success) {
				// Update status display if needed
			} else {
				alert(response.data.message || '<?php echo esc_js(__('Failed to update setting.', 'ultimate-multisite')); ?>');
				$toggle.prop('checked', !enabled);
			}
		}).fail(function() {
			alert('<?php echo esc_js(__('Failed to update setting.', 'ultimate-multisite')); ?>');
			$toggle.prop('checked', !enabled);
		});
	});
});
</script>
