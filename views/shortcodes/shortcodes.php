<?php
/**
 * Shortcodes view.
 *
 * @since 2.0.24
 */
defined('ABSPATH') || exit;
?>

<div id="wp-ultimo-wrap" class="<?php wu_wrap_use_container(); ?> wrap">

	<h1 class="wp-heading-inline"><?php esc_html_e('Available Shortcodes', 'ultimate-multisite'); ?></h1>

	<div id="poststuff">
	<div id="post-body" class="">
		<div id="post-body-content">

		<?php foreach ($data as $shortcode) { ?>

			<div class="metabox-holder">
			<div class="postbox">
				<div class="wu-w-full wu-box-border wu-m-0 wu-border-t wu-border-l-0 wu-border-r-0 wu-border-b-0 wu-border-gray-300 wu-border-solid">

				<div class="wu-bg-gray-100 wu-py-4 wu-w-full wu-box-border wu-p-4 wu-py-5 wu-m-0 wu-border-b wu-border-l-0 wu-border-r-0 wu-border-t-0 wu-border-gray-300 wu-border-solid">
					<a  
					href="<?php echo esc_url($shortcode['generator_form_url']); ?>" 
					class="wu-float-right wubox wu-no-underline wu-text-gray-600"
					title="<?php esc_html_e('Generator', 'ultimate-multisite'); ?>"
					>
					<span class="dashicons-wu-rocket"></span>
					<?php esc_html_e('Generator', 'ultimate-multisite'); ?>
					</a>  
					<div class="wu-block">
					<h3 class="wu-my-1 wu-text-base wu-text-gray-800">
						<?php echo esc_html($shortcode['title']); ?> <code>[<?php echo esc_html($shortcode['shortcode']); ?>]</code>
					</h3>
					<p class="wu-mt-1 wu-mb-0 wu-text-gray-700">
						<?php echo esc_html($shortcode['description']); ?>
					</p>
					</div>
				</div>

				<div class="wu-w-full wu-shortcode-attributes" role="region" tabindex="0" aria-label="<?php esc_attr_e('Shortcode parameters', 'ultimate-multisite'); ?>">
					<table class="wu-table-auto striped wu-w-full">
					<tr>
						<th class="wu-px-4 wu-py-2 wu-w-3/12 wu-text-left">
						<?php esc_html_e('Parameter', 'ultimate-multisite'); ?>
						</th>
						<th class="wu-px-4 wu-py-2 wu-w-4/12 wu-text-left">
						<?php esc_html_e('Description', 'ultimate-multisite'); ?>
						</th>
						<th class="wu-px-4 wu-py-2 wu-w-3/12 wu-text-left">
						<?php esc_html_e('Accepted Values', 'ultimate-multisite'); ?>
						</th>
						<th class="wu-px-4 wu-py-2 wu-w-2/12 wu-text-left">
						<?php esc_html_e('Default Value', 'ultimate-multisite'); ?>
						</th>
					</tr>
					<?php foreach ($shortcode['params'] as $param => $value) { ?>
						<tr>
						<td class="wu-px-4 wu-py-2 wu-text-left">
							<?php echo esc_html($param); ?>
						</td>
						<td class="wu-px-4 wu-py-2 wu-text-left">
							<?php echo esc_html($value['desc']); ?>
						</td>
						<td class="wu-px-4 wu-py-2 wu-text-left">
							<?php echo esc_html($value['options']); ?>
						</td>
						<td class="wu-px-4 wu-py-2 wu-text-left">
							<?php echo esc_html($value['default']); ?>
						</td>
						</tr>
					<?php } ?>
					</table>
				</div>

				</div>
			</div>
			</div>

		<?php } ?>

		</div>
	</div>
	</div>
</div>
