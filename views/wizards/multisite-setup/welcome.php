<?php
/**
 * Multisite setup wizard - Welcome section.
 *
 * @since 2.0.0
 */
defined('ABSPATH') || exit;
?>
<div class="wu-mb-6">
	<h3 class="wu-text-lg wu-font-semibold wu-text-gray-900 wu-mb-3">
		<?php esc_html_e('What is WordPress Multisite?', 'ultimate-multisite'); ?>
	</h3>
	<ul class="wu-list-disc wu-list-inside wu-text-gray-600 wu-space-y-2">
		<li><?php esc_html_e('Create multiple websites from a single WordPress installation', 'ultimate-multisite'); ?></li>
		<li><?php esc_html_e('Share themes, plugins, and users across all sites in the network', 'ultimate-multisite'); ?></li>
		<li><?php esc_html_e('Manage all sites from a central network administration panel', 'ultimate-multisite'); ?></li>
		<li><?php esc_html_e('Perfect foundation for Website-as-a-Service platforms', 'ultimate-multisite'); ?></li>
	</ul>
</div>

<div class="wu-mb-6">
	<h3 class="wu-text-lg wu-font-semibold wu-text-gray-900 wu-mb-3">
		<?php esc_html_e('What happens next?', 'ultimate-multisite'); ?>
	</h3>
	<p class="wu-text-gray-600 wu-mb-4">
		<?php esc_html_e('This wizard will guide you through the process of enabling WordPress Multisite. We will:', 'ultimate-multisite'); ?>
	</p>
	<ol class="wu-list-decimal wu-list-inside wu-text-gray-600 wu-space-y-2">
		<li><?php esc_html_e('Configure your network settings (subdomain or subdirectory structure)', 'ultimate-multisite'); ?></li>
		<li><?php esc_html_e('Automatically modify your wp-config.php file (if we have write access)', 'ultimate-multisite'); ?></li>
		<li><?php esc_html_e('Create the necessary database tables', 'ultimate-multisite'); ?></li>
		<li><?php esc_html_e('Complete the multisite setup process', 'ultimate-multisite'); ?></li>
	</ol>
</div>

<div class="wu-bg-blue-50 wu-border wu-border-blue-200 wu-rounded-lg wu-p-4">
	<div class="wu-flex">
		<div class="wu-flex-shrink-0">
			<span class="dashicons dashicons-info wu-text-blue-500"></span>
		</div>
		<div class="wu-ml-3">
			<h4 class="wu-text-sm wu-font-medium wu-text-blue-800">
				<?php esc_html_e('Important Notice', 'ultimate-multisite'); ?>
			</h4>
			<p class="wu-text-sm wu-text-blue-700 wu-mt-1">
				<?php esc_html_e('This process will make changes to your WordPress installation. We recommend creating a backup of your files and database before proceeding.', 'ultimate-multisite'); ?>
			</p>
		</div>
	</div>
</div>
