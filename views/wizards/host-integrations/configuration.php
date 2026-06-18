<?php
/**
 * Host integrations configuration view.
 *
 * @since 2.0.0
 */
defined('ABSPATH') || exit;

/** @var \WP_Ultimo\Admin_Pages\Wizard_Admin_Page $page  */
$back_url = $page->get_prev_section_link();
/** @var \WP_Ultimo\UI\Form $form */
/** @var array $configuration_instructions */
$configuration_instructions = $configuration_instructions ?? [];
?>
<h1>
	<?php esc_html_e('We are almost there!', 'ultimate-multisite'); ?>
</h1>

<p class="wu-text-lg wu-text-gray-600 wu-my-4">
	<?php esc_html_e('You should have all the information we need in hand right now. The next step is to configure it.', 'ultimate-multisite'); ?>
</p>

<?php if ( ! empty($configuration_instructions)) : ?>
	<div class="wu-bg-blue-50 wu-border wu-border-solid wu-border-blue-200 wu-rounded wu-p-4 wu-my-4 wu-text-left">
		<?php if ( ! empty($configuration_instructions['title'])) : ?>
			<h3 class="wu-mt-0 wu-mb-2 wu-text-blue-900">
				<?php echo esc_html($configuration_instructions['title']); ?>
			</h3>
		<?php endif; ?>

		<?php if ( ! empty($configuration_instructions['description'])) : ?>
			<p class="wu-mt-0 wu-text-blue-900">
				<?php echo wp_kses_post($configuration_instructions['description']); ?>
			</p>
		<?php endif; ?>

		<?php if ( ! empty($configuration_instructions['steps'])) : ?>
			<ol class="wu-mb-0 wu-pl-6 wu-text-blue-900">
				<?php foreach ($configuration_instructions['steps'] as $step) : ?>
					<li class="wu-mb-2"><?php echo wp_kses_post($step); ?></li>
				<?php endforeach; ?>
			</ol>
		<?php endif; ?>
	</div>
<?php endif; ?>

<div class="wu-mt-6 wu--mx-4">

	<?php

	/**
	 * Renders the form.
	 */
	$form->render();

	?>

</div>

<!-- Submit Box -->
<div class="wu-flex wu-justify-between wu-bg-gray-100 wu--m-in wu-mt-4 wu-p-4 wu-overflow-hidden wu-border-t wu-border-solid wu-border-l-0 wu-border-r-0 wu-border-b-0 wu-border-gray-300">
	<?php if ($back_url) : ?>
		<a
		href="<?php echo esc_url($back_url); ?>"
		class="wu-self-center button button-large wu-float-left"
		>
			<?php esc_html_e('← Go Back', 'ultimate-multisite'); ?>
		</a>
	<?php endif; ?>

	<span class="wu-self-center wu-content-center">

	<button name="submit" value="1" class="wu-ml-2 button button-primary button-large" data-testid="button-primary">
		<?php esc_html_e('Test Configuration &rarr;', 'ultimate-multisite'); ?>
	</button>

	</span>

</div>
<!-- End Submit Box -->
