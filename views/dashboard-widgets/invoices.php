<?php
/**
 * Invoices
 *
 * @since 2.0.0
 */
defined('ABSPATH') || exit;

?>
<div class="wu-styling <?php echo esc_attr($className); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase ?>">

	<div class="<?php echo esc_attr(wu_env_picker('', 'wu-widget-inset')); ?>">

	<?php if ($title) : ?>

		<!-- Title Element -->

		<div class="wu-p-4 wu-flex wu-items-center <?php echo esc_attr(wu_env_picker('', 'wu-bg-gray-100 wu-border-solid wu-border-0 wu-border-b wu-border-gray-400')); ?>">

			<h3 class="wu-m-0 <?php echo esc_attr(wu_env_picker('', 'wu-widget-title')); ?>">

			<?php echo esc_html($title); ?>

			</h3>

		</div>  

		<!-- Title Element - End -->

	<?php endif; ?>

	<table class="striped <?php echo esc_attr(wu_env_picker('', 'wp-list-table widefat wu-border-none')); ?>">

		<tbody class="wu-align-baseline">

		<?php foreach ($membership->get_payments(['number' => ! empty($limit) ? $limit : null]) as $payment) : ?>

			<!-- Invoice Item -->
			<tr>

			<td class="wu-align-middle wu-py-4 wu-px-2">

				<?php

				$download_link = sprintf(
					'<a target="_blank" class="wu-no-underline wu-ml-2" href="%s" title="%s">

                  <span class="dashicons-wu-download"></span>

                </a>',
					$payment->get_invoice_url(),
					esc_html__('Download Invoice', 'ultimate-multisite')
				);

				$payment_url = $payment->get_payment_url();

				$payment_column = $payment_url ? [
					'pay_now' => [
						'url'   => $payment_url,
						'icon'  => 'dashicons-wu-credit-card wu-align-middle wu-mr-1',
						'label' => esc_html__('Go to payment', 'ultimate-multisite'),
						'value' => esc_html__('Pay Now', 'ultimate-multisite'),
					],
				] : [];

				wu_responsive_table_row(
					[
						'url'    => false,
						'title'  => $payment->get_invoice_number() . $download_link,
						'status' => wu_format_currency($payment->get_total(), $payment->get_currency()),
					],
					array_merge(
						[
							'status' => [
								'url'   => false,
								'icon'  => wu_get_payment_icon_classes($payment->get_status()) . ' wu-mr-1',
								'value' => $payment->get_status_label(),
							],
						],
						$payment_column
					),
					[
						'date_created' => [
							'url'   => false,
							'icon'  => 'dashicons-wu-calendar1 wu-align-middle wu-mr-1',
							'label' => '',
							'value' => $payment->get_formatted_date('date_created'),
						],
					]
				);

				?>

			</td>

			</tr>
			<!-- Invoice Item - End -->

		<?php endforeach; ?>    

		</tbody>

	</table>

	</div>

</div>
