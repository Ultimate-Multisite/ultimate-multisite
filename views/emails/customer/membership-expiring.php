<?php
/**
 * Trial or Membership Expiring - Customer Email Template
 *
 * Conditional {{#key}}...{{/key}} blocks are kept only when the payload value is truthy.
 *
 * @since 2.18.0
 */
defined('ABSPATH') || exit;
?>
<?php // translators: %s: Customer Name ?>
<p><?php printf(esc_html__('Hey %s,', 'ultimate-multisite'), '{{customer_name}}'); ?></p>

{{#expiring_is_trial}}<p><?php // translators: %s: the date the trial ends. ?><?php printf(esc_html__('Your free trial ends on %s.', 'ultimate-multisite'), '{{expiring_date}}'); ?></p>{{/expiring_is_trial}}

{{#expiring_is_membership}}<p><?php // translators: %s: the date the membership ends. ?><?php printf(esc_html__('Your membership ends on %s and does not renew automatically.', 'ultimate-multisite'), '{{expiring_date}}'); ?></p>{{/expiring_is_membership}}

{{#expiring_will_renew}}<p><?php esc_html_e('Your membership will continue automatically when the trial ends, using the payment method on file. There is nothing you need to do.', 'ultimate-multisite'); ?></p>{{/expiring_will_renew}}

{{#expiring_needs_action}}<p><?php esc_html_e('To keep your account active, log in and complete your payment before then. If you have any questions, please get in touch.', 'ultimate-multisite'); ?></p>{{/expiring_needs_action}}

<h2><b><?php esc_html_e('Membership', 'ultimate-multisite'); ?></b></h2>

<table cellpadding="0" cellspacing="0" style="width: 100%; border-collapse: collapse;">
	<tbody>
	<tr>
		<td style="text-align: right; width: 160px; padding: 8px; background: #f9f9f9; border: 1px solid #eee;"><b><?php esc_html_e('Amount', 'ultimate-multisite'); ?></b></td>
		<td style="padding: 8px; background: #fff; border: 1px solid #eee;">
		{{membership_description}}
		</td>
	</tr>
	<tr>
		<td style="text-align: right; width: 160px; padding: 8px; background: #f9f9f9; border: 1px solid #eee;"><b><?php esc_html_e('Reference Code', 'ultimate-multisite'); ?></b></td>
		<td style="padding: 8px; background: #fdfdfd; border: 1px solid #eee;">
		{{membership_reference_code}}
		</td>
	</tr>
	<tr>
		<td style="text-align: right; width: 160px; padding: 8px; background: #f9f9f9; border: 1px solid #eee;"><b><?php esc_html_e('Ends', 'ultimate-multisite'); ?></b></td>
		<td style="padding: 8px; background: #fff; border: 1px solid #eee;">{{expiring_date}}</td>
	</tr>
	</tbody>
</table>
