<?php
/**
 * GridPane instructions view.
 *
 * @since 2.0.0
 */
defined('ABSPATH') || exit;
?>
<h1><?php esc_html_e('Instructions', 'ultimate-multisite'); ?></h1>

<p class="wu-text-lg wu-text-gray-600 wu-my-4 wu-mb-6"><?php esc_html_e('Connect Ultimate Multisite to GridPane using a GridPane API bearer token.', 'ultimate-multisite'); ?></p>

<p class="wu-text-sm">
	<?php esc_html_e('In GridPane, open Settings, select GridPane API, and create a GridPane Token. Copy the complete token before leaving that screen.', 'ultimate-multisite'); ?>
</p>

<p class="wu-text-sm">
	<?php esc_html_e('Continue to Configuration and paste the token. Ultimate Multisite stores it encrypted in the network database; no wp-config.php or user-configs.php changes are required.', 'ultimate-multisite'); ?>
</p>

<p class="wu-text-sm">
	<?php esc_html_e('The connection test automatically discovers the GridPane site and server IDs that match this network. You can enter those IDs manually if automatic discovery cannot find a unique match.', 'ultimate-multisite'); ?>
</p>
