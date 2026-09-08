<?php
/**
 * Add-on details modal.
 *
 * @since 2.0.0
 */

if ( ! defined('ABSPATH') ) {
	exit; // Exit if accessed directly
}

?>

<div id="plugin-information" style="position: static">

	<div id="plugin-information-scrollable">

		<div id="plugin-information-title" class="with-banner" 
		<?php
		if ( isset($addon->images[0]['thumbnail'])) :
			?>
style="background-image:url(<?php echo esc_url($addon->images[0]['thumbnail']); ?>);background-position:center;"<?php endif; ?>>
			<div class="vignette"></div>
			<h2><?php echo esc_html($addon->name); ?></h2>
		</div>

		<div id="plugin-information-tabs" class="with-banner">

			<a name="description" href="#" class="current">

				<?php esc_html_e('Description', 'ultimate-multisite'); ?>

			</a>
			<!-- 

			<a name="faq" href="#">

				<?php esc_html_e('FAQ', 'ultimate-multisite'); ?>

			</a>

			<a name="changelog" href="#">

				<?php esc_html_e('Changelog', 'ultimate-multisite'); ?>

			</a>

			<a name="screenshots" href="#">

				<?php esc_html_e('Screenshots', 'ultimate-multisite'); ?>

			</a>

			<a name="reviews" href="#">

				<?php esc_html_e('Reviews', 'ultimate-multisite'); ?>

			</a>

			-->

		</div>

		<div id="plugin-information-content" class="with-banner">

			<div class="fyi">

				<ul>
					<li>
						<strong><?php esc_html_e('Author:', 'ultimate-multisite'); ?></strong>

							<?php echo esc_html($addon->extensions['wp-update-server-plugin']['author']['display_name']); ?>

					<?php if (isset($addon->requires_version)) : ?>

						<li>
							<strong><?php esc_html_e('Requires Ultimate Multisite Version:', 'ultimate-multisite'); ?></strong>
							<?php // translators: %s minimum required version number. ?>
							<?php printf(esc_html__('%s or higher', 'ultimate-multisite'), esc_html($addon->requires_version)); ?>
						</li>

					<?php endif; ?>

					<li>
						<a class="wu-no-underline" target="_blank" href="<?php echo esc_attr($addon->permalink); ?>">
						<?php esc_html_e('See on the Official Site »', 'ultimate-multisite'); ?>
						</a>
					</li>

				</ul>
			</div>
			<div id="section-holder">

				<!-- Description Section -->
				<div id="section-description" class="section" style="display: block; min-height: 200px;">

					<?php echo wp_kses_post($addon->description); ?>

				</div>

			</div>

		</div>

	</div>

	<div id="plugin-information-footer" style="height: auto !important;">

		<?php if (! $addon->prices['price'] > 0) : ?>

		<span class="wu-text-green-800 wu-inline-block wu-py-1">

			<?php esc_html_e('This is a Premium Add-on.', 'ultimate-multisite'); ?>

		</span>

		<?php endif; ?>

		<?php if ($addon->installed) : ?>

			<button
			disabled="disabled"
			data-slug="<?php echo esc_attr($addon_slug); ?>"
			class="button button-disabled right"
			>
			<?php esc_html_e('Already Installed', 'ultimate-multisite'); ?>
			</button>

		<?php else : ?>

			<?php if ($addon->is_purchasable) : ?>

				<?php if ($addon->extensions['wp-update-server-plugin']['download_url'] || (float) $addon->prices['price'] < 0) : ?>

				<button
				type="submit"
				name="install"
				data-slug="<?php echo esc_attr($addon_slug); ?>"
				class="button button-primary right"
				>
					<?php esc_html_e('Install Now', 'ultimate-multisite'); ?>
				</button>

			<?php else : ?>

				<a
				href="<?php echo esc_attr($addon->permalink . '?add-to-cart=' . $addon->id); ?>"
				class="button button-primary right"
				>
				<?php esc_html_e('Purchase', 'ultimate-multisite'); ?>
				</a>

			<?php endif; ?>

			<?php endif; ?>

			<input type="hidden" name="action" value="wu_form_handler">

			<input type="hidden" name="addon" value="<?php echo esc_attr($addon_slug); ?>">

			<?php wp_nonce_field('wu_form_addon_more_info'); ?>

		<?php endif; ?>

	</div>

</div>
