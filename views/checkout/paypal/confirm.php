<?php
/**
 * Confirm paypal view.
 *
 * @since 2.0.0
 */
defined('ABSPATH') || exit;


$is_trial_setup = $membership->is_trialing() && empty($payment->get_total());

$notes = [];

if ($is_trial_setup) {
	$desc = $membership->get_recurring_description();

	$date = wp_date(get_option('date_format'), strtotime($membership->get_date_trial_end(), wu_get_current_time('timestamp', true)));
	// translators: %1$s is the date it will end
	$notes[] = sprintf(__('Your trial period will end on %1$s.', 'ultimate-multisite'), $date);
}

$original_cart = $payment->get_meta('wu_original_cart');

$should_auto_renew = ! empty($original_cart) ? $original_cart->should_auto_renew() : false;

$recurring_total = $membership->get_amount();

if ($membership->is_recurring() && $should_auto_renew) {
	$payment_total = $payment->get_total() ?: $membership->get_initial_amount();

	$desc = $membership->get_recurring_description();

	if ($recurring_total !== $payment_total) {
		$recurring_total_format = wu_format_currency($recurring_total, $payment->get_currency());

		if ($original_cart->get_cart_type() === 'downgrade') {
			$subtotal = wu_format_currency($payment->get_subtotal(), $payment->get_currency());
			if ($is_trial_setup) {
				// translators: %1$s is the start date, %2$s is the subtotal amount, and %3$s is the description of how often.
				$notes[] = sprintf(__('Your updated membership will start on %1$s, from that date you will be billed %2$s %3$s.', 'ultimate-multisite'), $date, $subtotal, $desc);
			} else {
				$date_renew = wp_date(get_option('date_format'), strtotime($membership->get_date_expiration(), wu_get_current_time('timestamp', true)));
				// translators: %1$s the date membership will start, %2$s amount to be billed, %3$s the description of how often.
				$notes[] = sprintf(__('Your updated membership will start on %1$s, from that date you will be billed %2$s %3$s.', 'ultimate-multisite'), $date_renew, $subtotal, $desc);
			}
		} elseif ($is_trial_setup) {
			$initial_amount_format = wu_format_currency($membership->get_initial_amount(), $payment->get_currency());
			// translators: %1$s is the membership level, %2$s is the initial amount, and %3$s is the currency description
			$notes[] = sprintf(__('After the first payment of %1$s you will be billed %2$s %3$s.', 'ultimate-multisite'), $initial_amount_format, $recurring_total_format, $desc);
		} else {
			// translators: %1$s amount to be billed, %2$s how often
			$notes[] = sprintf(__('After this payment you will be billed %1$s %2$s.', 'ultimate-multisite'), $recurring_total_format, $desc);
		}
	} else {
		$recurring_total_format = wu_format_currency($recurring_total, $payment->get_currency());

		if ($is_trial_setup) {
			// translators: %1$s amount to be billed, %2$s how often
			$notes[] = sprintf(__('From that date, you will be billed %1$s %2$s.', 'ultimate-multisite'), $recurring_total_format, $desc);
		} else {
			// translators: %1$s how often
			$notes[] = sprintf(__('After this payment you will be billed %1$s.', 'ultimate-multisite'), $desc);
		}
	}
}

$note = implode(' ', $notes);

$subtotal = 0;

?>

<form id="wu-paypal-express-confirm-form" class="wu-styling" action="<?php echo esc_url(add_query_arg('wu-confirm', 'paypal')); ?>" method="post">
	<div class="wu-confirm-details" id="billing_info">
		<h2><?php esc_html_e('Please confirm your payment', 'ultimate-multisite'); ?></h2>

		<div class="wu-text-sm wu-mb-4 wu-rounded-lg wu-border wu-border-gray-300 wu-bg-white wu-border-solid wu-shadow-sm wu-px-6 wu-py-4">
			<span class="wu-font-semibold wu-block wu-text-gray-900">
				<?php printf('%s %s', esc_html(wu_get_isset($checkout_details, 'FIRSTNAME', '')), esc_html(wu_get_isset($checkout_details, 'LASTNAME', ''))); ?>
			</span>

			<div class="wu-text-gray-600">
				<p>
					<?php esc_html_e('PayPal Status:', 'ultimate-multisite'); ?> <?php echo esc_html(ucfirst(wu_get_isset($checkout_details, 'PAYERSTATUS', 'none'))); ?>
					<br><?php esc_html_e('Email:', 'ultimate-multisite'); ?> <?php echo esc_html(wu_get_isset($checkout_details, 'EMAIL', '--')); ?>
				</p>
			</div>
		</div>
	</div>

	<table class="wu-w-full wu-mb-4">
		<thead class="wu-bg-gray-100">
			<tr>
				<th class="wu-text-left wu-py-2 wu-px-4"><?php esc_html_e('Product', 'ultimate-multisite'); ?></th>
				<th class="wu-text-left wu-py-2 wu-px-4"><?php esc_html_e('Total', 'ultimate-multisite'); ?></th>
			</tr>
		</thead>

		<tbody>
			<?php
			foreach ($payment->get_line_items() as $line_item) :
				$subtotal += $line_item->get_subtotal();
				?>

				<tr>
					<td class="wu-py-2 wu-px-4">
				<?php echo esc_html($line_item->get_title()); ?>
						<code class="wu-ml-1">x<?php echo esc_html($line_item->get_quantity()); ?></code>
					</td>
					<td class="wu-py-2 wu-px-4">
				<?php echo esc_html(wu_format_currency($line_item->get_subtotal(), $payment->get_currency())); ?>
					</td>
				</tr>

			<?php endforeach; ?>

		</tbody>

		<tfoot class="wu-bg-gray-100">
			<tr>
				<th class="wu-text-left wu-py-2 wu-px-4"><?php esc_html_e('Subtotal', 'ultimate-multisite'); ?></th>
				<th class="wu-text-left wu-py-2 wu-px-4"><?php echo esc_html(wu_format_currency($subtotal, $payment->get_currency())); ?></th>
			</tr>

			<?php foreach ($payment->get_tax_breakthrough() as $rate => $total) : ?>

				<tr>
					<?php // translators: %s: Tax rate. ?>
					<th class="wu-text-left wu-py-2 wu-px-4"><?php printf(esc_html__('Tax (%s%%)', 'ultimate-multisite'), esc_html($rate)); ?></th>
					<th class="wu-text-left wu-py-2 wu-px-4"><?php echo esc_html(wu_format_currency($total, $payment->get_currency())); ?></th>
				</tr>

			<?php endforeach; ?>

			<tr>
				<th class="wu-text-left wu-py-2 wu-px-4"><?php esc_html_e("Today's Grand Total", 'ultimate-multisite'); ?></th>
				<th class="wu-text-left wu-py-2 wu-px-4"><?php echo esc_html(wu_format_currency($payment->get_total(), $payment->get_currency())); ?></th>
			</tr>
		</tfoot>
	</table>

	<?php if ( ! empty($note)) : ?>

		<div class="wu-col-span-2 wu-mb-4">
			<div class="wu-p-4 wu-bg-yellow-200">
		<?php
		echo esc_html($note);
		?>
			</div>
		</div>

	<?php endif; ?>

	<input type="hidden" name="confirmation" value="yes" />
	<input type="hidden" name="token" value="<?php echo esc_attr(sanitize_text_field(wp_unslash($_GET['token'] ?? ''))); // phpcs:ignore WordPress.Security.NonceVerification ?>" />

	<?php if (isset($_GET['PayerID'])) : // phpcs:ignore WordPress.Security.NonceVerification ?>

		<input type="hidden" name="payer_id" value="<?php echo esc_attr(sanitize_text_field(wp_unslash($_GET['PayerID']))); // phpcs:ignore WordPress.Security.NonceVerification ?>" />

	<?php endif; ?>

	<input type="hidden" name="wu_ppe_confirm_nonce" value="<?php echo esc_attr(wp_create_nonce('wu-ppe-confirm-nonce')); ?>"/>

	<div class="wu_submit_button">
		<button type="submit" class="button button button-primary btn-primary">
			<?php esc_attr_e('Confirm', 'ultimate-multisite'); ?>
		</button>
	</div>

</form>
