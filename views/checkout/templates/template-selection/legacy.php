<?php
/**
 * Template File: Basic Pricing Table.
 *
 * To see what methods are available on the product variable, @param array $products List of product objects.
 *
 * @param string $name ID of the field.
 * @param string $label The field label.
 *
 * @see inc/models/class-products.php.
 *
 * This template can also be overridden using template overrides.
 * See more here: https://ultimatemultisite.com/docs/user-guide/miscellaneous/frequently-asked-questions
 *
 * @since 2.0.0
 * @package WP_Ultimo/Views
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

if ( ! $should_display ) {
	echo '<div></div>';

	return;
}
/** @var \WP_Ultimo\Models\Site[] $sites */
$sites = wu_normalize_sites_list($sites ?? []);

$categories ??= [];

$customer_sites_category = __('Your Sites', 'ultimate-multisite');

$customer_sites = isset($customer_sites) ? array_map('intval', $customer_sites) : [];

?>

<?php if ( empty($sites) ) : ?>

	<div class="wu-text-center wu-bg-gray-100 wu-rounded wu-uppercase wu-font-semibold wu-text-xs wu-text-gray-700 wu-p-4">
		<?php esc_html_e('No Site Templates Found.', 'ultimate-multisite'); ?>
	</div>

<?php else : ?>

	<div class="themes-php wu-styling">

		<div class="wrap wu-template-selection">

			<?php

			/**
			 * Allow developers to hide the title.
			 */
			if ( apply_filters('wu_step_template_display_header', true) ) :

				?>

				<h2>

					<?php esc_html_e('Pick your Template', 'ultimate-multisite'); ?>

					<span class="title-count theme-count">
						<?php echo count($sites); ?>
					</span>

				</h2>

			<?php endif; ?>

			<div class="wp-filter">

				<div class="wp-filter-responsive">

					<h4><?php esc_html_e('Template Categories', 'ultimate-multisite'); ?></h4>

					<select class="">

						<option value="">

							<?php esc_html_e('All Templates', 'ultimate-multisite'); ?>

						</option>

						<?php if ( ! empty($customer_sites) ) : ?>

							<option value="<?php echo esc_attr($customer_sites_category); ?>">


								<?php echo esc_html($customer_sites_category); ?>

							</option>

						<?php endif; ?>

						<?php foreach ( $categories as $category ) : ?>

							<option value="<?php echo esc_attr($category); ?>">


								<?php echo esc_html($category); ?>

							</option>

						<?php endforeach; ?>

					</select>

				</div>

				<ul class="filter-links wp-filter-template">

					<li class="selector-inactive">

						<a
								href="#"
								data-category=""
								:class="$parent.template_category === '' ? 'current' : ''"
								v-on:click.prevent="$parent.template_category = ''"
						>

							<?php esc_html_e('All Templates', 'ultimate-multisite'); ?>

						</a>

					</li>

					<?php if ( ! empty($customer_sites) ) : ?>

						<li class="selector-inactive">

							<a
									href="#"
									data-category="<?php echo esc_attr($customer_sites_category); ?>"
									:class="$parent.template_category === '<?php echo esc_attr($customer_sites_category); ?>' ? 'current' : ''"
									v-on:click.prevent="$parent.template_category = '<?php echo esc_attr($customer_sites_category); ?>'"
							>


								<?php echo esc_html($customer_sites_category); ?>

							</a>

						</li>

					<?php endif; ?>

					<?php foreach ( $categories as $category ) : ?>

						<li class="selector-inactive">

							<a
									href="#"
									data-category="<?php echo esc_attr($category); ?>"
									:class="$parent.template_category === '<?php echo esc_attr($category); ?>' ? 'current' : ''"
									v-on:click.prevent="$parent.template_category = '<?php echo esc_attr($category); ?>'"
							>


								<?php echo esc_html($category); ?>

							</a>

						</li>

					<?php endforeach; ?>

				</ul>

			</div>

			<div class="theme-browser rendered">


				<div class="wu-grid wu-grid-cols-1 sm:wu-grid-cols-2 md:wu-grid-cols-<?php echo esc_attr($cols); ?> wu-gap-4 wp-clearfix">

					<?php
					$i = 0;
					foreach ( $sites as $site ) :
						?>

						<?php
						if ( $site->get_type() !== 'site_template' && ! in_array($site->get_id(), $customer_sites, true) ) {
							continue;
						}
						?>

						<?php $is_template = $site->get_type() === 'site_template'; ?>

						<?php $categories = array_merge($site->get_categories(), ! $is_template ? [$customer_sites_category] : []); ?>

						<div
								class="theme"
								:class="$parent.template_id == <?php echo esc_attr($site->get_id()); ?> ? 'wu-selected-template' : ''"
								tabindex="<?php echo esc_attr($i); ?>"
								aria-describedby="<?php echo esc_attr($site->get_id()); ?>-action <?php echo esc_attr($site->get_id()); ?>-name"
								data-slug="<?php echo esc_attr($site->get_id()); ?>"
								v-show="!$parent.template_category || <?php echo esc_attr(wp_json_encode($categories)); ?>.join(',').indexOf($parent.template_category) > -1"
								v-cloak
						>

							<div class="theme-screenshot">

								<img
										src="<?php echo esc_url($site->get_featured_image('wu-thumb-large')); ?>"
										alt="<?php echo esc_attr($site->get_title()); ?>"
								>
							</div>

							<a
								<?php $is_template ? $site->get_preview_url_attrs() : printf('href="%s" target="_blank"', esc_attr($site->get_active_site_url())); ?>
								class="more-details"
								id="<?php echo esc_attr($site->get_id()); ?>-action"
							>
								<?php $is_template ? esc_html_e('View Template', 'ultimate-multisite') : esc_html_e('View Site', 'ultimate-multisite'); ?>
							</a>

							<div class="wu-flex theme-name-header wu-items-center wu-relative">


								<h2 class="theme-name wu-flex-grow wu-h-full"
									id="<?php echo esc_attr($site->get_id()); ?>-name">
									<?php echo esc_html($site->get_title()); ?>
								</h2>

								<div class="theme-actions wu-flex">

									<button
											class="button button-primary"
											:class="$parent.template_id == <?php echo esc_attr($site->get_id()); ?> ? 'wu-selected-template-button' : ''"
											type="button"
											v-on:click.prevent="$parent.template_id = <?php echo esc_attr($site->get_id()); ?>"
									>

										<span v-if="$parent.template_id == <?php echo esc_attr($site->get_id()); ?>"><?php esc_html_e('Selected', 'ultimate-multisite'); ?></span>

										<span v-else><?php esc_html_e('Select', 'ultimate-multisite'); ?></span>

									</button>

								</div>

							</div>

						</div>

						<?php

						++$i;
					endforeach;

					?>

				</div>

			</div>

			<div class="theme-overlay"></div>

			<p class="no-themes">
				<?php esc_html_e('No Templates Found', 'ultimate-multisite'); ?>
			</p>

		</div>

	</div>

<?php endif; ?>
