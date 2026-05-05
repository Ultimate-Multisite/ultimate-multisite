<?php
/**
 * Confirmation panel for the customer-panel template-switching page.
 *
 * Mirrors the layout of views/ui/template-switching-current.php so the
 * "What's about to happen" panel feels visually paired with the
 * "What's currently active" card at the top of the page. Renders one card
 * per candidate template (the current template, used when the customer
 * clicks "Reset", plus every available template they could switch to);
 * Vue's v-if shows only the card whose template_id matches the customer's
 * current selection, so the DOM cost is bounded by the number of templates
 * the customer can choose between (typically <20).
 *
 * Expected variables:
 *
 * @var \WP_Ultimo\Models\Site[] $confirm_sites Sites that may appear in the panel (current + grid).
 * @var int                      $original_template_id Customer's active template ID.
 *
 * @package WP_Ultimo
 * @subpackage UI/Views
 * @since 2.9.4
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

if (empty($confirm_sites)) {
	return;
}

/*
 * Disclaimer text. Two variants — one for "switch to a different template"
 * (the customer is replacing the basis of their site) and one for "reset
 * the current template" (the customer is re-applying the same template,
 * which still overwrites their changes). Keeping them as separate strings
 * lets translators tune the wording for each case rather than overloading
 * a single ambiguous warning.
 */
$switch_disclaimer = __('Switching your template completely overwrites the content of your site with the contents of the newly chosen template. All customizations will be lost. This action cannot be undone.', 'ultimate-multisite');
$reset_disclaimer  = __('Resetting re-applies your current template, overwriting your site with a fresh copy. All customizations will be lost. This action cannot be undone.', 'ultimate-multisite');
?>

<?php
/*
 * The whole panel is gated on `confirm_active`. Each candidate card is
 * additionally gated on `template_id == X`, so on initial page load —
 * when `template_id == original_template_id` and confirm_active is
 * still false — neither layer matches and nothing renders. The panel
 * appears only when:
 *   - the customer picks a non-current template in the grid (Vue
 *     watcher in template-switching.js sets confirm_active = true), or
 *   - the customer clicks "Reset Current Template" (reset_template()
 *     in template-switching.js sets template_id =
 *     original_template_id and confirm_active = true).
 *
 * Without the outer gate, the inner v-if would still match the current
 * template card on first load (because template_id starts equal to
 * original_template_id once v-init fires), causing the "Reset to <name>?"
 * panel to flash visible before the customer has done anything.
 */
?>
<div class="wu-template-switching-confirm wu-mt-6" v-show="confirm_active" v-cloak>

	<?php foreach ($confirm_sites as $confirm_site) : ?>
		<?php /** @var \WP_Ultimo\Models\Site $confirm_site */ ?>
		<?php
		$confirm_id    = (int) $confirm_site->get_id();
		$confirm_title = $confirm_site->get_title();
		$confirm_image = $confirm_site->get_featured_image('wu-thumb-medium');
		$is_current    = $confirm_id === (int) $original_template_id;
		?>

		<div
			class="wu-bg-white wu-border-solid wu-border wu-border-gray-300 wu-shadow-sm wu-rounded wu-overflow-hidden"
			v-if="confirm_active && template_id == <?php echo esc_attr((string) $confirm_id); ?>"
		>

			<div class="wu-p-4 wu-flex wu-items-center wu-gap-4">

				<div class="wu-flex-shrink-0">
					<img
						class="wu-rounded wu-border-solid wu-border wu-border-gray-300 wu-bg-white"
						style="width: 120px; height: 80px; object-fit: cover;"
						src="<?php echo esc_attr($confirm_image); ?>"
						alt="<?php echo esc_attr($confirm_title); ?>"
					/>
				</div>

				<div class="wu-flex-grow wu-min-w-0">
					<div class="wu-text-2xs wu-uppercase wu-font-semibold wu-text-gray-500 wu-mb-1">
						<?php
						/*
						 * Eyebrow text — the kind of action the customer is
						 * about to take. Static strings (one per card) so
						 * translators get full sentences to localise rather
						 * than concatenated fragments.
						 */
						if ($is_current) {
							esc_html_e('Reset Template', 'ultimate-multisite');
						} else {
							esc_html_e('Switch Template', 'ultimate-multisite');
						}
						?>
					</div>
					<h3 class="wu-text-lg wu-font-semibold wu-m-0 wu-mb-1 wu-truncate">
						<?php
						if ($is_current) {
							/* translators: %s: template name */
							printf(esc_html__('Reset to %s?', 'ultimate-multisite'), esc_html($confirm_title));
						} else {
							/* translators: %s: template name */
							printf(esc_html__('Switch to %s?', 'ultimate-multisite'), esc_html($confirm_title));
						}
						?>
					</h3>
					<p class="wu-text-sm wu-text-gray-600 wu-m-0">
						<?php echo esc_html($is_current ? $reset_disclaimer : $switch_disclaimer); ?>
					</p>
				</div>

			</div>

			<?php
			/*
			 * Inline error surface. Shown when the AJAX call to
			 * wu_switch_template fails — the JS show_error() helper
			 * sets `error_message` to the server-supplied reason
			 * (e.g. "You are not allowed to switch templates for this
			 * site.") and keeps `confirm_active` true so this block
			 * stays visible. We render it here, between the disclaimer
			 * and the action buttons, because the customer's eye is
			 * already on the panel and they are about to look at the
			 * Cancel/Confirm row — the error sits directly above where
			 * they were about to click again.
			 *
			 * Without this, the global wu_ajax_error() notice prepended
			 * to the top of #wpbody-content was the only signal, and on
			 * the customer panel that notice is below the fold from
			 * where the customer just clicked, so the failure looked
			 * like a silent no-op.
			 *
			 * `role="alert"` and `aria-live="assertive"` ensure assistive
			 * tech announces the error when Vue reveals the block.
			 */
			?>
			<div
				v-if="error_message"
				class="wu-px-4 wu-pt-4 wu-pb-0"
				role="alert"
				aria-live="assertive"
			>
				<div
					class="wu-rounded wu-border-solid wu-border wu-border-red-300 wu-bg-red-50 wu-text-red-700 wu-p-3 wu-flex wu-items-start wu-gap-2"
					style="border-color: #fc8181; background-color: #fff5f5; color: #c53030;"
				>
					<span class="dashicons dashicons-warning wu-flex-shrink-0" aria-hidden="true" style="margin-top: 2px;"></span>
					<div class="wu-flex-grow wu-min-w-0">
						<strong class="wu-block wu-mb-1">
							<?php esc_html_e('Could not switch template', 'ultimate-multisite'); ?>
						</strong>
						<span class="wu-text-sm">{{ error_message }}</span>
					</div>
				</div>
			</div>

			<?php
			/*
			 * `wu-gap-3` is not present in the compiled framework.css
			 * (only gap-2/4/5/6 are emitted), so relying on it leaves
			 * the Cancel link flush against the destructive button.
			 * Use `wu-gap-4` (compiled — 16px) and back it up with
			 * inline `margin-right` on the Cancel link so the spacing
			 * survives even if a parent admin stylesheet wins the
			 * cascade against the framework's `.wu-styling :is()`
			 * scope.
			 */
			?>
			<div class="wu-bg-gray-100 wu-px-4 wu-py-3 wu-flex wu-items-center wu-justify-end wu-gap-4 wu-border-0 wu-border-t wu-border-solid wu-border-gray-200">

				<a
					href="#"
					class="wu-no-underline wu-uppercase wu-text-2xs wu-font-semibold wu-text-gray-600"
					style="margin-right: 0.5rem;"
					v-on:click.prevent="cancel_switch()"
				>
					<?php esc_html_e('Cancel', 'ultimate-multisite'); ?>
				</a>

				<?php
				/*
				 * Confirm button uses outlined-red styling consistent with
				 * the Reset button in the current-template card — both are
				 * destructive actions, and styling them the same way
				 * teaches the customer "red border = this overwrites my
				 * site" across the page. Inline style mirrors the pattern
				 * in template-switching-current.php; same rationale applies
				 * (compiled framework.css lacks `hover:wu-bg-red-*` and
				 * `wu-border-red-*` variants).
				 */
				?>
				<a
					href="#"
					class="button wu-no-underline"
					style="background-color: transparent; border: 1px solid #c53030; color: #c53030; transition: background-color 0.15s, color 0.15s;"
					onmouseover="this.style.backgroundColor='#c53030';this.style.color='#ffffff';"
					onmouseout="this.style.backgroundColor='transparent';this.style.color='#c53030';"
					v-on:click.prevent="ready = true"
				>
					<?php
					if ($is_current) {
						/* translators: %s: template name */
						printf(esc_html__('Reset to %s', 'ultimate-multisite'), esc_html($confirm_title));
					} else {
						/* translators: %s: template name */
						printf(esc_html__('Switch to %s', 'ultimate-multisite'), esc_html($confirm_title));
					}
					?>
				</a>

			</div>

		</div>

	<?php endforeach; ?>

</div>
