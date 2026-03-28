<?php
/**
 * Domain mapping view.
 *
 * @since 2.0.0
 */
defined('ABSPATH') || exit;

?>
<div class="wu-styling <?php echo esc_attr($className); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase ?>">

	<div class="<?php echo esc_attr(wu_env_picker('', 'wu-widget-inset')); ?>">

	<!-- Title Element -->
	<div class="wu-p-4 wu-flex wu-items-center <?php echo esc_attr(wu_env_picker('', 'wu-bg-gray-100')); ?>">

		<?php if ($title) : ?>

		<h3 class="wu-m-0 <?php echo esc_attr(wu_env_picker('', 'wu-widget-title')); ?>">

			<?php echo esc_html($title); ?>

		</h3>

		<?php endif; ?>

		<div class="wu-ml-auto">

		<a title="<?php esc_html_e('Add Domain', 'ultimate-multisite'); ?>" href="<?php echo esc_attr($modal['url']); ?>" class="wu-text-sm wu-no-underline wubox button">

			<?php esc_html_e('Add Domain', 'ultimate-multisite'); ?>

		</a>

		<?php
		/**
		 * Fires after the "Add Domain" button in the domain mapping widget header.
		 *
		 * Allows addons to add additional action buttons (e.g. "Register Domain").
		 *
		 * @since 2.4.0
		 */
		do_action('wu_domain_mapping_header_actions');
		?>

		</div>

	</div>
	<!-- Title Element - End -->

	<div class="wu-border-t wu-border-solid wu-border-0 wu-border-gray-200">

		<table class="wu-m-0 wu-my-2 wu-p-0 wu-w-full">

		<tbody class="wu-align-baseline">

			<?php if ($domains) : ?>

				<?php
				foreach ($domains as $key => $domain) :
					$item = $domain['domain_object'];
					?>

					<tr>

					<td class="wu-px-1">

						<?php

						$label = $item->get_stage_label();

						if ( ! $item->is_active()) {
							$label = sprintf('%s <small>(%s)</small>', $label, __('Inactive', 'ultimate-multisite'));
						}

						$class = $item->get_stage_class();

						$status = "<span class='wu-py-1 wu-px-2 wu-rounded-sm wu-text-xs wu-leading-none wu-font-mono $class'>{$label}</span>";

						$second_row_actions = [];

						// Check if DNS management is available
						$dns_manager    = \WP_Ultimo\Managers\DNS_Record_Manager::get_instance();
						$dns_provider   = $dns_manager->get_dns_provider();
						$can_manage_dns = $dns_manager->customer_can_manage_dns(get_current_user_id(), $item->get_domain());

						if ($dns_provider || wu_get_setting('enable_customer_dns_management', false)) {
							$second_row_actions['manage_dns'] = [
								'wrapper_classes' => 'wubox',
								'icon'            => 'dashicons-wu-globe wu-align-middle wu-mr-1',
								'label'           => '',
								'url'             => wu_get_form_url('user_manage_dns_records', ['domain_id' => $item->get_id()]),
								'value'           => __('DNS Records', 'ultimate-multisite'),
							];
						}

						if ( ! $item->is_primary_domain()) {
							$second_row_actions['make_primary'] = [
								'wrapper_classes' => 'wubox',
								'icon'            => 'dashicons-wu-edit1 wu-align-middle wu-mr-1',
								'label'           => '',
								'url'             => $domain['primary_link'],
								'value'           => __('Make Primary', 'ultimate-multisite'),
							];
						}

						$second_row_actions['remove'] = [
							'wrapper_classes' => 'wu-text-red-500 wubox',
							'icon'            => 'dashicons-wu-trash-2 wu-align-middle wu-mr-1',
							'label'           => '',
							'value'           => __('Delete', 'ultimate-multisite'),
							'url'             => $domain['delete_link'],
						];

						/**
						 * Filters the action links for a domain row in the domain mapping widget.
						 *
						 * Allows addons to add extra actions (e.g. Manage DNS, Renew) for domain rows.
						 *
						 * @since 2.4.0
						 *
						 * @param array  $second_row_actions The action items for the row.
						 * @param object $item               The domain object.
						 */
						$second_row_actions = apply_filters('wu_domain_mapping_row_actions', $second_row_actions, $item);

						$first_row = [
							'primary' => [
								'wrapper_classes' => $item->is_primary_domain() ? 'wu-text-blue-600' : '',
								'icon'            => $item->is_primary_domain() ? 'dashicons-wu-filter_1 wu-align-text-bottom wu-mr-1' : 'dashicons-wu-plus-square wu-align-text-bottom wu-mr-1',
								'label'           => '',
								'value'           => function () use ($item) {
									if ($item->is_primary_domain()) {
										esc_html_e('Primary', 'ultimate-multisite');
										wu_tooltip(__('All other mapped domains will redirect to the primary domain.', 'ultimate-multisite'), 'dashicons-editor-help wu-align-middle wu-ml-1');
									} else {
										esc_html_e('Alias', 'ultimate-multisite');
									}
								},
							],
							'secure'  => [
								'wrapper_classes' => $item->is_secure() ? 'wu-text-green-500' : '',
								'icon'            => $item->is_secure() ? 'dashicons-wu-lock1 wu-align-text-bottom wu-mr-1' : 'dashicons-wu-lock1 wu-align-text-bottom wu-mr-1',
								'label'           => '',
								'value'           => $item->is_secure() ? __('Secure (HTTPS)', 'ultimate-multisite') : __('Not Secure (HTTP)', 'ultimate-multisite'),
							],
						];

						/**
						 * Filters the info columns for a domain row in the domain mapping widget.
						 *
						 * Allows addons to add extra info (e.g. expiry date) for domain rows.
						 *
						 * @since 2.4.0
						 *
						 * @param array  $first_row The info columns for the row.
						 * @param object $item      The domain object.
						 */
						$first_row = apply_filters('wu_domain_mapping_row_info', $first_row, $item);

						wu_responsive_table_row(
							[
								'id'     => false,
								'title'  => strtolower($item->get_domain()),
								'url'    => false,
								'status' => $status,
							],
							$first_row,
							$second_row_actions
						);

						?>

					</td>

					</tr>

				<?php endforeach; ?>

			<?php else : ?>

			<div class="wu-text-center wu-bg-gray-100 wu-rounded wu-uppercase wu-font-semibold wu-text-xs wu-text-gray-700 wu-p-4 wu-m-4 wu-mt-6">
				<span><?php echo esc_html__('No domains added.', 'ultimate-multisite'); ?></span>
			</div>

			<?php endif; ?>

		</tbody>

	</table>

	</div>

	</div>

</div>
