<?php
/**
 * Template invoice view.
 *
 * @since 2.0.0
 */
defined('ABSPATH') || exit;

$has_tax_included = false;

$wp_styles = wp_styles();

$wp_styles->do_item('wu-invoice');
?>

<div class="invoice-box">
	<table cellpadding="0" cellspacing="0">
		<tr class="top">
			<td colspan="5">
				<table>
					<tr>
						<td class="title">
							<?php if ($use_custom_logo && $custom_logo) : ?>
								<?php
								echo (wp_get_attachment_image(
									$custom_logo,
									\WP_Ultimo\Invoices\Invoice::LOGO_IMAGE_SIZE,
									false,
									array(
										'loading'  => false,
										'decoding' => false,
										'style'    => 'width: 100px; height: auto;',
									)
								));
								?>
							<?php else : ?>
								<img width="100" src="<?php echo esc_attr($logo_url); ?>" alt="<?php echo esc_attr(get_network_option(null, 'site_name')); ?>">
							<?php endif; ?>
						</td>

						<td>
							<strong><?php esc_html_e('Invoice #', 'ultimate-multisite'); ?></strong><br>
							<?php echo esc_html($payment->get_invoice_number()); ?>
							<br>
							<?php // translators: %s is the payment creation date ?>
							<?php echo esc_html(sprintf(esc_html__('Created: %s', 'ultimate-multisite'), date_i18n(get_option('date_format'), strtotime($payment->get_date_created())))); ?><br>

							<?php esc_html_e('Due on Receipt', 'ultimate-multisite'); ?><br>
						</td>
					</tr>
				</table>
			</td>
		</tr>

		<tr class="information">
			<td colspan="5">
				<table>
					<tr>
						<td>
							<strong>
								<?php

								/**
								 * Displays company name.
								 */
								echo esc_html($company_name);

								?>
							</strong>

							<br>

							<?php

							/**
							 * Displays the company address.
							 */
							echo nl2br(esc_html($company_address));

							?>
						</td>

						<td>
							<strong><?php esc_html_e('Bill to', 'ultimate-multisite'); ?></strong>
							<br>
							<?php

							/**
							 * Displays the clients address.
							 */
							echo nl2br(esc_html(implode(PHP_EOL, (array) $billing_address)));

							?>
						</td>
					</tr>
				</table>
			</td>
		</tr>

		<tr class="heading">

			<th style="text-align: left;">
				<?php esc_html_e('Item', 'ultimate-multisite'); ?>
			</th>

			<th style="width: 17%;">
				<?php esc_html_e('Price', 'ultimate-multisite'); ?>
			</th>

			<th style="width: 17%;">
				<?php esc_html_e('Discount', 'ultimate-multisite'); ?>
			</th>

			<th style="width: 17%;">
				<?php esc_html_e('Tax', 'ultimate-multisite'); ?>
			</th>

			<th style="width: 17%;">
				<?php esc_html_e('Total', 'ultimate-multisite'); ?>
			</th>

		</tr>

		<?php foreach ($line_items as $line_item) : ?>

			<tr class="item">

				<td>
					<span class="font-weight: medium;"><?php echo esc_html($line_item->get_title()); ?></span>
					<br>
					<small><?php echo wp_kses($line_item->get_description(), wu_kses_allowed_html()); ?></small>
				</td>

				<td style="text-align: right;">
					<?php echo esc_html(wu_format_currency($line_item->get_subtotal(), $payment->get_currency())); ?>
				</td>

				<td style="text-align: right;">
					<?php echo esc_html(wu_format_currency($line_item->get_discount_total(), $payment->get_currency())); ?>
				</td>

				<td style="text-align: right;">
					<?php echo esc_html(wu_format_currency($line_item->get_tax_total(), $payment->get_currency())); ?>
					<br>
					<small><?php echo esc_html($line_item->get_tax_label()); ?> (<?php echo esc_html($line_item->get_tax_rate()); ?>%)</small>
					<?php if ($line_item->get_tax_inclusive()) : ?>
						<?php $has_tax_included = true; ?>
						<small>*</small>
					<?php endif; ?>
				</td>

				<td style="text-align: right;">
					<?php echo esc_html(wu_format_currency($line_item->get_total(), $payment->get_currency())); ?>
				</td>

			</tr>

		<?php endforeach; ?>

		<tr class="total">
			<?php if ($has_tax_included) : ?>
				<td style="text-align: left; font-weight: normal;">
					<small>* <?php esc_html_e('Tax included in price.', 'ultimate-multisite'); ?></small>
				</td>
			<?php endif; ?>
			<td colspan='5'>

				<?php // translators: %s is the total amount in currency format. ?>
				<?php printf(esc_html__('Total: %s', 'ultimate-multisite'), esc_html(wu_format_currency($payment->get_total(), $payment->get_currency()))); ?>
			</td>
		</tr>

		<?php if ( ! $payment->is_payable()) : ?>

			<tr class="heading">
				<th colspan="5" style="text-align: left;">
					<?php esc_html_e('Payment Method', 'ultimate-multisite'); ?>
				</th>
			</tr>

			<tr class="details">
				<td colspan="5">
					<?php echo esc_html($payment->get_payment_method()); ?>
				</td>
			</tr>

		<?php endif; ?>
	</table>
</div>
