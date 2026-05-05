<?php
/**
 * Current-template summary card for the customer-panel template-switching page.
 *
 * Renders the customer's currently active template with a "Reset" button to
 * its right, kept visually distinct from the grid of available templates
 * below it. The Vue model in assets/js/template-switching.js drives state.
 *
 * Expected variables:
 *
 * @var \WP_Ultimo\Models\Site $current_template Current template site (or false).
 * @var int                    $original_template_id Current template ID (0 if none).
 *
 * @package WP_Ultimo
 * @subpackage UI/Views
 * @since 2.9.4
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

$has_current_template = $current_template instanceof \WP_Ultimo\Models\Site && $original_template_id > 0;
?>

<div
	class="wu-template-switching-current wu-mb-6"
	v-cloak
>

	<?php if ($has_current_template) : ?>

		<div class="wu-bg-white wu-border-solid wu-border wu-border-gray-300 wu-shadow-sm wu-rounded wu-p-4 wu-flex wu-items-center wu-gap-4">

			<div class="wu-flex-shrink-0">
				<img
					class="wu-rounded wu-border-solid wu-border wu-border-gray-300 wu-bg-white"
					style="width: 120px; height: 80px; object-fit: cover;"
					src="<?php echo esc_attr($current_template->get_featured_image('wu-thumb-medium')); ?>"
					alt="<?php echo esc_attr($current_template->get_title()); ?>"
				/>
			</div>

			<div class="wu-flex-grow wu-min-w-0">
				<div class="wu-text-2xs wu-uppercase wu-font-semibold wu-text-gray-500 wu-mb-1">
					<?php esc_html_e('Current Template', 'ultimate-multisite'); ?>
				</div>
				<h3 class="wu-text-lg wu-font-semibold wu-m-0 wu-mb-1 wu-truncate">
					<?php echo esc_html($current_template->get_title()); ?>
				</h3>
				<p class="wu-text-sm wu-text-gray-600 wu-m-0 wu-truncate">
					<?php echo esc_html($current_template->get_description()); ?>
				</p>
			</div>

			<div class="wu-flex-shrink-0">
				<a
					href="#"
					class="button wu-no-underline"
					v-on:click.prevent="reset_template()"
					title="<?php esc_attr_e('Re-apply this template, overwriting the site with a fresh copy.', 'ultimate-multisite'); ?>"
				>
					<?php esc_html_e('Reset Current Template', 'ultimate-multisite'); ?>
				</a>
			</div>

		</div>

	<?php else : ?>

		<div class="wu-bg-white wu-border-solid wu-border wu-border-gray-300 wu-shadow-sm wu-rounded wu-p-4">
			<div class="wu-text-2xs wu-uppercase wu-font-semibold wu-text-gray-500 wu-mb-1">
				<?php esc_html_e('Current Template', 'ultimate-multisite'); ?>
			</div>
			<p class="wu-text-sm wu-text-gray-600 wu-m-0">
				<?php esc_html_e('This site is not currently based on a template. Choose one below to apply it.', 'ultimate-multisite'); ?>
			</p>
		</div>

	<?php endif; ?>

</div>

<div class="wu-template-switching-divider wu-mb-4">
	<h3 class="wu-text-base wu-font-semibold wu-text-gray-700 wu-m-0 wu-mb-2 wu-pb-2 wu-border-0 wu-border-b wu-border-solid wu-border-gray-200">
		<?php esc_html_e('Available Templates', 'ultimate-multisite'); ?>
	</h3>
</div>
