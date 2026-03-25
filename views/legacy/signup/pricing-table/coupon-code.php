<?php
/**
 * Coupon code view.
 *
 * @since 2.0.0
 */

defined('ABSPATH') || exit;

if (isset($_GET['coupon']) && wu_get_coupon(sanitize_text_field(wp_unslash($_GET['coupon']))) !== false && isset($_GET['step']) && 'plan' === $_GET['step']) : // phpcs:ignore WordPress.Security.NonceVerification
	?>

<div id="coupon-code-app" class="wu-mb-3">
	<div class="wu-p-3 wu-bg-green-50 wu-border wu-border-solid wu-border-green-200 wu-rounded wu-flex wu-items-center wu-justify-between">
		<div class="wu-text-sm">
			<strong><?php esc_html_e('Coupon applied', 'ultimate-multisite'); ?>:</strong>
			<span>{{ coupon && coupon.code ? coupon.code : (coupon && coupon.id ? coupon.id : '') }}</span>
		</div>
		<div>
			<a href="#" class="wu-text-red-600 wu-text-sm wu-no-underline" v-on:click.prevent="remove_coupon">
				<?php esc_html_e('Remove', 'ultimate-multisite'); ?>
			</a>
		</div>
	</div>
</div>

	<?php
endif;
