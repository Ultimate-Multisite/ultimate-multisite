<?php
/**
 * Default view.
 *
 * @since 2.0.0
 */
defined('ABSPATH') || exit;

$title        = $title ?? '';
$description  = $description ?? '';
$content      = $content ?? '';
$back_label   = $back_label ?? '';
$skip_label   = $skip_label ?? '';
$next_label   = $next_label ?? '';
$page         = $page ?? null;
$back         = $back ?? false;
$skip         = $skip ?? false;
$next         = $next ?? true;
$disable_next = $disable_next ?? false;

$title_value       = wu_resolve_template_string($title);
$description_value = wu_resolve_template_string($description);
$content_value     = wu_resolve_template_string($content);
$back_label_value  = wu_resolve_template_string($back_label);
$skip_label_value  = wu_resolve_template_string($skip_label);
$next_label_value  = wu_resolve_template_string($next_label);
?>
<h1>
	<?php echo esc_html($title_value); ?>
</h1>

<?php if ($description_value) : ?>
	<p class="wu-text-lg wu-text-gray-600 wu-mt-4 wu-mb-0">
		<?php echo wp_kses($description_value, ['br' => []]); ?>
	</p>
<?php endif; ?>

<div class="wu-bg-white wu-p-4 wu--mx-5">
	<?php echo wp_kses($content_value, wu_kses_allowed_html()); ?>
</div>

<!-- Submit Box -->
<div class="wu-flex wu-justify-between wu-bg-gray-100 wu--m-in wu-mt-4 wu-p-4 wu-overflow-hidden wu-border-t wu-border-solid wu-border-l-0 wu-border-r-0 wu-border-b-0 wu-border-gray-300">
	<?php if ($back) : ?>
		<a href="<?php echo esc_url($page->get_prev_section_link()); ?>" class="wu-self-center button button-large wu-float-left" data-testid="button-primary">
			<?php echo esc_html($back_label_value); ?>
		</a>
	<?php endif; ?>
	<div class="wu-text-right wu-relative wu-w-full">
		<?php if ($skip) : ?>
			<a href="<?php echo esc_url($page->get_next_section_link()); ?>" class="wu-skip-button button button-large" data-testid="button-primary">
				<?php echo esc_html($skip_label_value); ?>
			</a>
		<?php endif; ?>
		<?php if ($next) : ?>
			<button name="next" value="1" class="wu-next-button button button-primary button-large wu-ml-2" data-testid="button-primary" <?php echo $disable_next ? 'disabled="disabled"' : ''; ?>>
				<?php echo esc_html($next_label_value); ?>
			</button>
		<?php endif; ?>
	</div>
</div>
<!-- End Submit Box -->
