<?php
/**
 * Requirements table view.
 *
 * @since 2.0.0
 */
defined('ABSPATH') || exit;
?>
<div class="wu-block">

	<div class="wu-block wu-text-gray-700 wu-font-bold wu-uppercase wu-text-xs wu-py-2">
	<?php esc_html_e('Ultimate Multisite Requires:', 'ultimate-multisite'); ?>
	</div>

	<div class="wu-advanced-filters">
	<table class="widefat fixed striped wu-border-b">
		<thead>
		<tr>
			<th><?php esc_html_e('Item', 'ultimate-multisite'); ?></th>
			<th><?php esc_html_e('Minimum Version', 'ultimate-multisite'); ?></th>
			<th><?php esc_html_e('Recommended', 'ultimate-multisite'); ?></th>
			<th><?php esc_html_e('Installed', 'ultimate-multisite'); ?></th>
		</tr>
		</thead>
		<tbody>
		<?php foreach ($requirements as $req) : ?>
		<tr class="">
			<td><?php echo esc_html($req['name']); ?></td>
			<td><?php echo esc_html($req['required_version']); ?></td>
			<?php // translators: %s is the requirement version ?>
			<td><?php printf(esc_html__('%s or later', 'ultimate-multisite'), esc_html($req['recommended_version'])); ?></td>
			<td class="<?php echo $req['pass_requirements'] ? 'wu-text-green-600' : 'wu-text-red-600'; ?>">
				<?php echo esc_html($req['installed_version']); ?>
				<?php echo $req['pass_requirements'] ? '<span class="dashicons-wu-check"></span>' : '<span class="dashicons-wu-cross"></span>'; ?>

				<?php if ( ! $req['pass_requirements']) : ?>

					<a class="wu-no-underline wu-block" href="<?php echo esc_url($req['help']); ?>" title="<?php esc_attr_e('Help', 'ultimate-multisite'); ?>">
						<?php esc_html_e('Read More', 'ultimate-multisite'); ?>
						<span class="dashicons-wu-help-with-circle"></span>
					</a>

				<?php endif; ?>
			</td>
		</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<br>
	</div>

	<div class="wu-block wu-text-gray-700 wu-font-bold wu-uppercase wu-text-xs wu-py-2">
		<?php echo esc_html__('And', 'ultimate-multisite'); ?>
	</div>

	<div class="wu-advanced-filters">
	<table class="widefat fixed striped wu-border-b">
		<thead>
		<tr>
			<th><?php esc_html_e('Item', 'ultimate-multisite'); ?></th>
			<th><?php esc_html_e('Condition', 'ultimate-multisite'); ?></th>
		</tr>
		</thead>
		<tbody>
		<?php foreach ($plugin_requirements as $req) : ?>
		<tr class="">
			<td><?php echo esc_html($req['name']); ?></td>
			<td class="<?php echo $req['pass_requirements'] ? 'wu-text-green-600' : 'wu-text-red-600'; ?>">
				<?php echo esc_html($req['condition']); ?>
			<?php echo $req['pass_requirements'] ? '<span class="dashicons-wu-check"></span>' : '<span class="dashicons-wu-cross wu-align-middle"></span>'; ?>

			<?php if ( ! $req['pass_requirements']) : ?>

				<?php if ( ! empty($req['can_activate'])) : ?>

					<div class="wu-mt-2">
						<button
							type="button"
							class="button button-primary wu-network-activate-btn"
							data-nonce="<?php echo esc_attr($req['network_activate_nonce']); ?>"
						>
							<?php esc_html_e('Network Activate', 'ultimate-multisite'); ?>
						</button>
						<span class="wu-network-activate-spinner spinner" style="float: none; margin-top: 0; vertical-align: middle;"></span>
						<span class="wu-network-activate-message wu-block wu-mt-1 wu-text-xs wu-text-red-500"></span>
						<a
							target="_blank"
							class="wu-no-underline wu-ml-2 wu-network-activate-fallback"
							href="<?php echo esc_url($req['help']); ?>"
							title="<?php esc_attr_e('Help', 'ultimate-multisite'); ?>"
							style="display: none;"
						>
							<span class="dashicons-wu-help-with-circle wu-align-baseline"></span>
							<?php esc_html_e('Read More', 'ultimate-multisite'); ?>
						</a>
					</div>

				<?php else : ?>

					<a target="_blank" class="wu-no-underline wu-ml-2" href="<?php echo esc_url($req['help']); ?>" title="<?php esc_attr_e('Help', 'ultimate-multisite'); ?>">
					<span class="dashicons-wu-help-with-circle wu-align-baseline"></span>
					<?php esc_html_e('Read More', 'ultimate-multisite'); ?>
					</a>

				<?php endif; ?>

			<?php endif; ?>
			</td>
		</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<br>
	</div>

	<?php if (\WP_Ultimo\Requirements::met() === false) : ?>

	<div class="wu-mt-4 wu-p-4 wu-bg-red-100 wu-border wu-border-solid wu-border-red-200 wu-rounded-sm wu-text-red-500">
		<?php if ( ! empty($has_activate_button)) : ?>
			<?php esc_html_e('Ultimate Multisite is not network active. Click the Network Activate button above to activate it automatically. If activation fails, use the Read More link that appears to resolve the issue manually.', 'ultimate-multisite'); ?>
		<?php else : ?>
			<?php esc_html_e('It looks like your hosting environment does not support the current version of Ultimate Multisite. Visit the Read More links on each item to see what steps you need to take to bring your environment up to the Ultimate Multisite current requirements.', 'ultimate-multisite'); ?>
		<?php endif; ?>
	</div>

	<?php endif; ?>

</div>

<script type="text/javascript">
jQuery(function($) {

	$(document).on('click', '.wu-network-activate-btn', function() {

		var $btn     = $(this);
		var $wrapper = $btn.closest('div');
		var nonce    = $btn.data('nonce');
		var $spinner  = $wrapper.find('.wu-network-activate-spinner');
		var $message  = $wrapper.find('.wu-network-activate-message');
		var $fallback = $wrapper.find('.wu-network-activate-fallback');

		$btn.prop('disabled', true);
		$spinner.addClass('is-active');
		$message.text('');

		$.post(
			ajaxurl,
			{
				action: 'wu_setup_network_activate',
				nonce: nonce,
			},
			function(response) {

				if (response.success) {
					window.location.reload();
					return;
				}

				$spinner.removeClass('is-active');
				$btn.hide();
				$fallback.show();

				var errorMsg = <?php echo wp_json_encode(__('Activation failed. Please activate the plugin manually.', 'ultimate-multisite')); ?>;

				if (response.data && response.data.message) {
					errorMsg = response.data.message;
				}

				$message.text(errorMsg);
			}
		).fail(function() {

			$spinner.removeClass('is-active');
			$btn.hide();
			$fallback.show();
			$message.text(<?php echo wp_json_encode(__('Activation failed. Please activate the plugin manually.', 'ultimate-multisite')); ?>);

		});

	});

});
</script>
