<?php
/**
 * Displays the Admin Notices on the admin panels (network, sub-sites, and user)
 *
 * @package WP_Ultimo/Views
 * @subpackage Admin_Notices
 * @since 2.0.0
 */
defined('ABSPATH') || exit;

foreach ($notices as $key => $notice) : ?>

<div class="notice wu-hidden wu-admin-notice wu-styling hover:wu-styling notice-<?php echo esc_attr($notice['type']); ?> <?php echo $notice['dismissible_key'] ? esc_attr('is-dismissible') : ''; ?>">

	<?php if (str_contains($notice['message'], '<p>')) : ?>
	
		<?php echo wp_kses_post($notice['message']); ?>
	
	<?php else : ?>

	<p class="wu-py-2"><?php echo wp_kses_post($notice['message']); ?></p>

<?php endif; ?>

	<?php if ( ! empty($notice['actions'])) : ?>

	<div class="wu-border wu-border-solid wu-border-gray-300 wu-border-r-0 wu-border-l-0 wu-border-b-0 wu-bg-gray-100 wu--ml-2 wu--mb-1 wu--mr-2 sm:wu--mr-7.5 sm:wu--ml-3 sm:wu--mb-px">

		<ul class="wu-text-right wu-p-0 wu-m-0 wu-flex wu-justify-end">

		<?php foreach ($notice['actions'] as $action) : ?>

		<li class="wu-inline-block wu-p-0 wu-m-0 wu-flex-shrink">
			<a class="wu-bg-white wu-uppercase wu-no-underline wu-font-bold wu-text-gray-600 hover:wu-text-gray-700 wu-text-xs wu-inline-block wu-px-4 wu-py-2 wu-border wu-border-solid wu-border-gray-300 wu-border-r-0 wu-border-t-0 wu-border-b-0 wu-transition-all wu-mr-px <?php echo esc_attr($action['classes'] ?? ''); ?>" title="<?php echo esc_attr($action['title']); ?>" href="<?php echo esc_attr($action['url']); ?>"><?php echo wp_kses_post($action['title']); ?></a>
		</li>

		<?php endforeach; ?>

		</ul>

	</div>

	<?php endif; ?>

	<?php if (! empty($notice['dismissible_key'])) : ?>

	<input type='hidden' name='notice_id' value='<?php echo esc_attr($notice['dismissible_key']); ?>'>

	<input type='hidden' name='nonce' value='<?php echo esc_attr($nonce); ?>'>

	<?php endif; ?>

</div>

<?php endforeach; ?>
