<?php
/**
 * Displays the navigation part on the bottom of the page
 *
 * This template can be overridden by copying it to yourtheme/wp-ultimo/signup/signup-nav-links.php.
 *
 * HOWEVER, on occasion Ultimate Multisite will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @author      NextPress
 * @package     WP_Ultimo/Views
 * @version     1.4.0
 */

if ( ! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

?>

<?php

/**
 * Get Navigational Links
 *
 * @var array
 */
$nav_links = apply_filters(
	'wu_signup_form_nav_links',
	[
		home_url()     => __('Return to Home', 'ultimate-multisite'),
		wp_login_url() => sprintf('<strong>%s</strong>', esc_html__('Log In', 'ultimate-multisite')),
	]
);

if ( ! isset($signup->step)) {
	return;
}

?>

<?php if ('plan' !== $signup->step && 'template' !== $signup->step) : ?>

	<p id="nav">

	<?php $i = 1; foreach ($nav_links as $link => $label) : ?>

		<a href="<?php echo esc_attr($link); ?>">

		<?php echo wp_kses_post($label); ?>

		</a>

		<?php
		if ($i < count($nav_links)) {

			/**
			 * We need this in order to maintain backwards compatibility with WordPress login page
		 *
			 * @since 1.9.2
			 */
			echo ' | ';
			++$i;
		}
		?>

	<?php endforeach; ?>

	</p>

<?php endif; ?>
