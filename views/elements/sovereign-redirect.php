<?php
/**
 * Sovereign Mode Redirect Element
 *
 * Displays a redirect link to the main site for sovereign-mode tenants.
 *
 * @since 2.2.0
 */

defined('ABSPATH') || exit;

?>
<div class="wu-sovereign-redirect">
	<a class="button button-primary" href="<?php echo esc_url($main_site_account_url); ?>">
		<?php echo esc_html($element_label); ?> → <?php esc_html_e('manage on main site', 'ultimate-multisite'); ?>
	</a>
</div>
