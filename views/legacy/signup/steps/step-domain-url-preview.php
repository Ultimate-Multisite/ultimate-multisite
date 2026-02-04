<?php
/**
 * This is the template used to display the URL preview field on the domain step
 *
 * This template can be overridden by copying it to yourtheme/wp-ultimo/signup/steps/step-domain-url-preview.php.
 *
 * HOWEVER, on occasion Ultimate Multisite will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @author      NextPress
 * @package     WP_Ultimo/Views
 * @version     1.0.0
 */

if ( ! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

?>

<div id="wu-your-site-block">

	<small><?php esc_html_e('Your URL will be', 'ultimate-multisite'); ?></small><br>

	<?php
	/**
	 * Change the base, if sub-domain or subdirectory
	 */
	// This is used on the yoursite.network.com during sign-up
	$dynamic_part = $signup->results['blogname'] ?? __('yoursite', 'ultimate-multisite');

	$site_url = preg_replace('#^https?://#', '', WU_Signup()->get_site_url_for_previewer());
	$site_url = str_replace('www.', '', $site_url);

	echo is_subdomain_install() ?
		sprintf('<strong id="wu-your-site" v-html="site_url ? site_url : \'%1$s\'">%1$s</strong>.<span id="wu-site-domain" v-html="site_domain">%2$s</span>', esc_html($dynamic_part), esc_html($site_url)) :
		sprintf('<span id="wu-site-domain" v-html="site_domain">%1$s</span>/<strong id="wu-your-site" v-html="site_url ? site_url : \'%2$s\'">%2$s</strong>', esc_html($site_url), esc_html($dynamic_part));
	?>

</div>
