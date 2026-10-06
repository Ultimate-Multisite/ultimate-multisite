<?php
/**
 * Add-ons list page.
 *
 * @since 2.0.0
 */

if ( ! defined('ABSPATH') ) {
	exit; // Exit if accessed directly
}

?>

<div id="wp-ultimo-wrap" class="<?php wu_wrap_use_container(); ?> wrap wu-wrap <?php echo esc_attr($classes); ?>">

	<h1 class="wp-heading-inline">

	<?php echo esc_html($page->get_title()); ?> <span v-cloak v-if="count > 0" class="title-count theme-count" v-text="count"></span>

	<?php
	/**
	 * You can filter the get_title_link using wu_page_list_get_title_link, see class-wu-page-list.php
	 *
	 * @since 1.8.2
	 */
	foreach ($page->get_title_links() as $action_link) :
		$action_classes = isset($action_link['classes']) ? $action_link['classes'] : '';

		?>

		<a title="<?php echo esc_attr($action_link['label']); ?>" href="<?php echo esc_url($action_link['url']); ?>" class="page-title-action <?php echo esc_attr($action_classes); ?>">

		<?php if ($action_link['icon']) : ?>

			<span class="dashicons dashicons-<?php echo esc_attr($action_link['icon']); ?> wu-text-sm wu-align-middle wu-h-4 wu-w-4">
			&nbsp;
			</span>

		<?php endif; ?>

		<?php echo esc_html($action_link['label']); ?>

		</a>

	<?php endforeach; ?>

	<?php
	/**
	 * Allow plugin developers to add additional buttons to list pages
	 *
	 * @since 1.8.2
	 * @param WU_Page $page WP Ultimo Page instance
	 */
	do_action('wu_page_addon_after_title', $page);
	?>

	</h1>

	<?php if (wu_request('updated')) : ?>

	<div id="message" class="updated notice wu-admin-notice notice-success is-dismissible below-h2">
		<p><?php esc_html_e('Settings successfully saved.', 'ultimate-multisite'); ?></p>
	</div>

	<?php endif; ?>
	<?php if ( $user ) : ?>
		<div class="notice wu-hidden wu-admin-notice wu-styling hover:wu-styling notice-success">
			<?php // translators: %1$s: the current user display name, %2$s: their password. ?>
			<p class="wu-py-2"><?php echo esc_html(sprintf(__('Connected to UltimateMultisite.com as %1$s (%2$s).', 'ultimate-multisite'), $user['display_name'], $user['user_email'])); ?> <a title="<?php esc_attr_e('Disconnect your site', 'ultimate-multisite'); ?>" href="<?php echo esc_attr($logout_url); ?>"><?php esc_html_e('Disconnect', 'ultimate-multisite'); ?></a></p>
		</div>
	<?php else : ?>
		<div class="notice wu-hidden wu-admin-notice wu-styling hover:wu-styling notice-warning">
			<p class="wu-py-2"><?php esc_html_e('Ultimate Multisite might be at risk because it’s unable to automatically update add-ons. Please complete the connection to get updates and streamlined support.', 'ultimate-multisite'); ?></p>
			<div>
				<ul class="wu-m-0">
					<li class="">
						<a class="button-primary wu-font-bold wu-uppercase" title="<?php esc_attr_e('Connect your site', 'ultimate-multisite'); ?>" href="<?php echo esc_attr($oauth_url); ?>"><?php esc_html_e('Connect your site to UltimateMultisite.com', 'ultimate-multisite'); ?></a>
					</li>
				</ul>
			</div>
		</div>
	<?php endif; ?>

	<hr class="wp-header-end">

	<div class="wu-flex wu-flex-wrap wu-items-center wu-justify-between wu-gap-4 wu-mb-6 wu-p-4 wu-bg-gray-50 wu-border wu-border-gray-200 wu-rounded-lg">
		<div class="wu-flex wu-items-center wu-space-x-4">
			<span class="wu-text-sm wu-text-gray-600">
				<span class="wu-font-semibold" v-text="count" v-cloak>0</span> <?php esc_html_e('add-ons', 'ultimate-multisite'); ?>
			</span>
		</div>
		
		<div class="wu-flex wu-flex-wrap wu-items-center wu-gap-2" id="addons-menu">
			<?php foreach ($sections as $section_name => $section) : ?>
				<a 
					href="<?php echo esc_url($page->get_section_link($section_name)); ?>"
					class="wu-m-0 wu-px-4 wu-py-2 wu-text-sm wu-border wu-transition-colors wu-no-underline"
					:class="category === '<?php echo esc_attr($section_name); ?>' ? 'wu-bg-gray-100 wu-text-gray-900 wu-border-blue-600 wu-border-solid' : 'wu-bg-white wu-text-gray-700 wu-border-gray-300 hover:wu-bg-gray-50'"
					@click.prevent="set_category('<?php echo esc_attr($section_name); ?>')"
					v-show="'<?php echo esc_attr($section_name); ?>' === 'all' || available_categories.some(cat => cat.slug === '<?php echo esc_attr($section_name); ?>')"
				>
					<span class="<?php echo esc_attr($section['icon']); ?> wu-mr-1"></span>
					<?php echo esc_html($section['title']); ?>
				</a>
			<?php endforeach; ?>
		</div>

		<div id="search-addons">
			<input 
				type="search" 
				class="wu-w-64 wu-px-3 wu-py-2 wu-text-sm wu-border wu-border-gray-300 wu-rounded-md focus:wu-outline-none focus:wu-ring-2 focus:wu-ring-blue-500 focus:wu-border-blue-500" 
				placeholder="<?php esc_attr_e('Search add-ons...', 'ultimate-multisite'); ?>" 
				v-model="search" 
			/>
		</div>
	</div>

	<div id="wu-addon">
		
		<div v-if="loading" class="wu-text-center wu-py-12">
			<div class="wu-inline-flex wu-items-center wu-px-4 wu-py-2 wu-text-sm wu-text-blue-600 wu-bg-blue-50 wu-border wu-border-blue-200 wu-rounded-lg">
				<svg class="wu-animate-spin wu-mr-2 wu-h-4 wu-w-4" fill="none" viewBox="0 0 24 24">
					<circle class="wu-opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
					<path class="wu-opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
				</svg>
				<?php esc_html_e('Loading add-ons...', 'ultimate-multisite'); ?>
			</div>
		</div>

		<div class="wu-grid wu-grid-cols-1 md:wu-grid-cols-2 lg:wu-grid-cols-3 wu-gap-6" v-cloak>
			<div 
				v-for="addon in addons_list"
				:key="addon.slug"
				class="wu-bg-white wu-border wu-border-gray-200 wu-rounded-lg wu-shadow-sm wu-overflow-hidden wu-transition-shadow hover:wu-shadow-md"
				:data-slug="addon.slug"
			>
				<div class="wu-relative wu-p-6">
					
					<div v-if="addon.installed" class="wu-absolute wu-top-3 wu-right-3 wu-px-2 wu-py-1 wu-text-xs wu-font-semibold wu-text-white wu-bg-green-600 wu-rounded">
						<?php esc_html_e('Installed', 'ultimate-multisite'); ?>
					</div>
					<div v-else-if="addon.beta" class="wu-absolute wu-top-3 wu-right-3 wu-px-2 wu-py-1 wu-text-xs wu-font-semibold wu-text-white wu-bg-orange-500 wu-rounded">
						<?php esc_html_e('Beta', 'ultimate-multisite'); ?>
					</div>
					<div v-else-if="!addon.is_purchasable" class="wu-absolute wu-top-3 wu-right-3 wu-px-2 wu-py-1 wu-text-xs wu-font-semibold wu-text-white wu-bg-gray-600 wu-rounded">
						<?php esc_html_e('Coming Soon', 'ultimate-multisite'); ?>
					</div>
					<div v-else-if="addon.legacy" class="wu-absolute wu-top-3 wu-right-3 wu-px-2 wu-py-1 wu-text-xs wu-font-semibold wu-text-white wu-bg-purple-600 wu-rounded">
						<?php esc_html_e('Legacy', 'ultimate-multisite'); ?>
					</div>

					<div class="wu-flex">
						<div class="wu-flex wu-items-center wu-justify-center wu-flex-shrink-0">
							<img v-if="addon.extensions['wp-update-server-plugin'].icon"
								:src="addon.extensions['wp-update-server-plugin'].icon"
								:alt="addon.name" 
								class="wu-w-full wu-rounded-lg  wu-bg-gray-100 wu-border wu-border-gray-200 wu-rounded-lg"
								width="70" height="70"
								:class="addon.available ? '' : 'wu-opacity-50'">
							<div v-else class="wu-text-2xl wu-text-gray-400  wu-bg-gray-100 wu-border wu-border-gray-200 wu-rounded-lg">
								<span class="dashicons dashicons-admin-plugins" style="width: 70px; height:70px; line-height: 70px"></span>
							</div>
						</div>

						<div class="wu-ml-6">
							<h3 class="wu-text-lg wu-font-semibold wu-text-gray-900 wu-mb-1">{{ addon.name }}</h3>
							<p class="wu-text-sm wu-text-gray-600 wu-mb-2">
								<?php esc_html_e('By', 'ultimate-multisite'); ?> <span class="wu-font-medium">{{ addon.extensions['wp-update-server-plugin'].author.display_name }}</span>
							</p>
						</div>
					</div>

				</div>
				<div class="wu-px-6">
					<div v-html="addon.short_description"></div>
				</div>
				
				<div class="wu-px-6 wu-py-4 wu-bg-gray-50 wu-border-t wu-border-gray-200">
					
					<div class="wu-flex wu-items-center wu-justify-between wu-text-xs wu-text-gray-600 wu-mb-3">
						<div class="wu-space-y-1">
							<div v-if="addon.last_updated" class="wu-flex wu-items-center wu-space-x-1">
								<span class="wu-font-medium"><?php esc_html_e('Updated:', 'ultimate-multisite'); ?></span>
								<span>{{ addon.last_updated }}</span>
							</div>
							<div v-if="addon.active_installs" class="wu-flex wu-items-center wu-space-x-1">
								<span>{{ addon.active_installs }}+ <?php esc_html_e('installs', 'ultimate-multisite'); ?></span>
							</div>
						</div>

						<div class="wu-text-right wu-space-y-1">
							<div v-if="addon.average_rating > 0" class="wu-flex wu-items-center wu-space-x-1">
								<div class="wu-flex wu-text-yellow-400">
									<span v-for="star in 5" :key="star" 
										:class="star <= Math.round(addon.average_rating) ? 'wu-text-yellow-400' : 'wu-text-gray-300'">
										★
									</span>
								</div>
								<span v-if="addon.review_count" class="wu-text-gray-500">({{ addon.review_count }})</span>
							</div>

						</div>
					</div>
					
					<div class="wu-flex wu-items-center wu-justify-between wu-space-x-3">
						<div>
								<span v-if="addon.prices.price <= 0" class="wu-inline-flex wu-items-center wu-px-2 wu-py-1 wu-text-xs wu-font-medium wu-text-green-800 wu-bg-green-100 wu-rounded">
									<?php esc_html_e('Free', 'ultimate-multisite'); ?>
								</span>
								<span v-else class="wu-inline-flex wu-items-center wu-px-2 wu-py-1 wu-text-xs wu-font-medium wu-text-blue-800 wu-bg-blue-100 wu-rounded">
									<span v-html="addon.price_html"></span>
								</span>
						</div>
						<button 
							v-if="addon.installed"
							type="button" 
							class="wu-px-4 wu-py-2 wu-text-sm wu-font-medium wu-text-gray-500 wu-bg-gray-100 wu-border wu-border-gray-300 wu-rounded-md wu-cursor-not-allowed"
							disabled
						>
							<span class="dashicons-wu-check wu-mr-1"></span>
							<?php esc_html_e('Installed', 'ultimate-multisite'); ?>
						</button>
						<a 
							v-else-if="(addon.is_purchasable && addon.prices.price <= 0) || addon.extensions['wp-update-server-plugin'].download_url"
							:href="'<?php echo esc_attr($more_info_url); ?>'.replace('ADDON_SLUG', addon.slug)"
							class="wubox button-primary wu-inline-flex wu-items-center wu-justify-center wu-px-4 wu-py-2 wu-text-sm wu-font-medium wu-text-white wu-bg-blue-600 wu-border wu-border-blue-600 wu-rounded-md hover:wu-bg-blue-700 wu-transition-colors wu-no-underline"
							:data-title="'Install ' + addon.name"
						>
							<?php esc_html_e('Install Now', 'ultimate-multisite'); ?>
						</a>
						<a 
							v-else-if="addon.is_purchasable && addon.prices.price > 0"
							:href="addon.permalink + '?add-to-cart=' + addon.id"
							class="wu-inline-flex wu-items-center wu-justify-center wu-px-4 wu-py-2 wu-text-sm wu-font-medium wu-bg-gray-300 wu-border wu-border-green-600 wu-rounded-md hover:wu-bg-green-700 wu-transition-colors wu-no-underline"
							target="_blank"
						>
							<?php esc_html_e('Buy Now', 'ultimate-multisite'); ?>
						</a>
						<button 
							v-else
							type="button" 
							class="wu-flex-1 wu-px-4 wu-py-2 wu-text-sm wu-font-medium wu-text-gray-500 wu-bg-gray-100 wu-border wu-border-gray-300 wu-rounded-md wu-cursor-not-allowed"
							disabled
						>
							<?php esc_html_e('Coming Soon', 'ultimate-multisite'); ?>
						</button>
						<a
							:href="'<?php echo esc_attr($more_info_url); ?>'.replace('ADDON_SLUG', addon.slug)"
							class="wubox wu-px-3 wu-py-2 wu-text-sm wu-font-medium wu-text-blue-600 wu-bg-white wu-border wu-border-blue-600 wu-rounded-md hover:wu-bg-blue-50 wu-transition-colors wu-no-underline"
							:aria-label="'<?php esc_attr_e('More information about', 'ultimate-multisite'); ?> ' + addon.name"
							:data-title="addon.name"
						>
							<?php esc_html_e('Details', 'ultimate-multisite'); ?>
						</a>
					</div>

				</div>
			</div>
		</div>

		<div 
			v-cloak
			v-if="!loading && addons_list.length === 0"
			class="wu-text-center wu-py-12"
		>
			<div class="wu-max-w-md wu-mx-auto">
				<div class="wu-text-6xl wu-text-gray-400 wu-mb-4">
					<span class="dashicons dashicons-search"></span>
				</div>
				<h3 class="wu-text-lg wu-font-medium wu-text-gray-900 wu-mb-2"><?php esc_html_e('No add-ons found...', 'ultimate-multisite'); ?></h3>
				<p class="wu-text-sm wu-text-gray-600"><?php esc_html_e('Check the search terms or navigate between categories to see what add-ons we have available.', 'ultimate-multisite'); ?></p>
			</div>
		</div>

	</div>

	<?php
	/**
	 * Allow plugin developers to add scripts to the bottom of the page
	 *
	 * @since 1.8.2
	 * @param WU_Page $page WP Ultimo Page instance
	 */
	do_action('wu_page_addon_footer', $page);
	?>

</div>
