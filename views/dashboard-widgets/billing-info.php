<?php
/**
 * Billing Info
 *
 * @since 2.0.0
 */
defined('ABSPATH') || exit;

$billing_address_fields = $billing_address->to_array(true);

?>
<div class="wu-styling <?php echo esc_attr($className); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase ?>">

	<div class="<?php echo esc_attr(wu_env_picker('', 'wu-widget-inset')); ?>">

	<!-- Billing Address -->

	<div id="wu-billing-address">

		<!-- Title Element -->
		<div class="wu-p-4 wu-flex wu-items-center <?php echo esc_attr(wu_env_picker('', 'wu-bg-gray-100')); ?>">

		<?php if ($title) : ?>

			<h3 class="wu-m-0 <?php echo esc_attr(wu_env_picker('', 'wu-widget-title')); ?>">

			<?php echo esc_html($title); ?>

			</h3>

		<?php endif; ?>

		<div class="wu-ml-auto">

			<a 
			title="<?php esc_attr_e('Update Billing Address', 'ultimate-multisite'); ?>"
			class="wu-text-sm wu-no-underline wubox button" 
			href="<?php echo esc_attr($update_billing_address_link); ?>"
			>

			<?php esc_html_e('Update', 'ultimate-multisite'); ?>

			</a>

		</div>

		</div>
		<!-- Title Element - End -->

		<?php if (empty($billing_address_fields)) : ?>

		<div class="wu-p-4 wu-border-t wu-border-solid wu-border-0 wu-border-gray-200">

			<div class="wu-p-4 wu-bg-gray-100 wu-rounded">

			<p class="wu-m-0 wu-mb-3">
				<?php esc_html_e('No billing address found. Add one now so your invoices and payment details stay up to date.', 'ultimate-multisite'); ?>
			</p>

			<a
				title="<?php esc_attr_e('Update Billing Address', 'ultimate-multisite'); ?>"
				class="wubox button wu-no-underline"
				href="<?php echo esc_attr($update_billing_address_link); ?>"
			>
				<?php esc_html_e('Add Billing Address', 'ultimate-multisite'); ?>
			</a>

			</div>

		</div>

		<?php else : ?>

		<div class="wu-overflow-hidden">

			<?php foreach ($billing_address_fields as $label => $value) : ?>

			<div class="wu-border-t wu-border-solid wu-border-0 wu-border-gray-200 wu-px-4 wu-py-2 sm:wu-p-0">

			<div class="sm:wu-divide-y sm:wu-divide-gray-200">
				<div class="wu-py-4 sm:wu-grid sm:wu-grid-cols-3 sm:wu-gap-4 sm:wu-px-4">
				<div class="wu-text-sm wu-font-medium wu-text-gray-600">
				<?php echo esc_html($label); ?>
				</div>
				<div class="wu-mt-1 wu-text-sm wu-text-gray-900 sm:wu-mt-0 sm:wu-col-span-2">
				<?php echo esc_html($value); ?>
				</div>
				</div>
			</div>

			</div>

			<?php endforeach; ?>

		</div>

		<?php endif; ?>

	</div>

	<!-- Billing Address - End -->

	<?php if ($membership->is_recurring() && false) : // phpcs:ignore Generic.CodeAnalysis.UnconditionalIfStatement.Found ?>

		<!-- Payment Method -->

		<div id="wu-payment-method">

		<!-- Title Element -->
		<div class="wu-p-4 wu-flex wu-items-center <?php echo esc_attr(wu_env_picker('', 'wu-bg-gray-100 wu-border-solid wu-border-0 wu-border-b wu-border-t wu-border-gray-200')); ?>">

			<?php if (true) : // phpcs:ignore Generic.CodeAnalysis.UnconditionalIfStatement.Found ?>

			<h3 class="wu-m-0 <?php echo esc_attr(wu_env_picker('', 'wu-widget-title')); ?>">

				<?php esc_html_e('Payment Method', 'ultimate-multisite'); ?>

			</h3>

			<?php endif; ?>

			<div class="wu-ml-auto">

			<a 
				title="<?php esc_attr_e('Update Billing Address', 'ultimate-multisite'); ?>"
				class="wu-text-sm wu-no-underline wubox button" 
				href="<?php echo esc_attr($update_billing_address_link); ?>"
			>

				<?php esc_html_e('Update', 'ultimate-multisite'); ?>

			</a>

			</div>

		</div>
		<!-- Title Element - End -->

		<div class="">

			<div class="wu-p-4">

			<div class="sm:wu-flex sm:wu-items-center sm:wu-justify-between">

				<h4 class="screen-reader-text">Visa</h4>

				<div class="sm:wu-flex sm:wu-items-center">

				<svg class="wu-h-8 wu-w-auto sm:wu-flex-shrink-0 sm:wu-h-6" viewBox="0 0 36 24" aria-hidden="true">
					<rect width="36" height="24" fill="#224DBA" rx="4" />
					<path fill="#fff"
					d="M10.925 15.673H8.874l-1.538-6c-.073-.276-.228-.52-.456-.635A6.575 6.575 0 005 8.403v-.231h3.304c.456 0 .798.347.855.75l.798 4.328 2.05-5.078h1.994l-3.076 7.5zm4.216 0h-1.937L14.8 8.172h1.937l-1.595 7.5zm4.101-5.422c.057-.404.399-.635.798-.635a3.54 3.54 0 011.88.346l.342-1.615A4.808 4.808 0 0020.496 8c-1.88 0-3.248 1.039-3.248 2.481 0 1.097.969 1.673 1.653 2.02.74.346 1.025.577.968.923 0 .519-.57.75-1.139.75a4.795 4.795 0 01-1.994-.462l-.342 1.616a5.48 5.48 0 002.108.404c2.108.057 3.418-.981 3.418-2.539 0-1.962-2.678-2.077-2.678-2.942zm9.457 5.422L27.16 8.172h-1.652a.858.858 0 00-.798.577l-2.848 6.924h1.994l.398-1.096h2.45l.228 1.096h1.766zm-2.905-5.482l.57 2.827h-1.596l1.026-2.827z" />
				</svg>

				<div class="wu-mt-3 sm:wu-mt-0 sm:wu-ml-4">

					<div class="wu-text-sm wu-font-medium wu-text-gray-900">
					Ending with 4242
					</div>

					<div class="wu-mt-1 wu-text-sm wu-text-gray-600 sm:wu-flex sm:wu-items-center">

					<div>
						Expires 12/20
					</div>

					<span class="wu-hidden sm:wu-mx-2 sm:wu-inline md:wu-hidden lg:wu-inline" aria-hidden="true">
						&middot;
					</span>

					<div class="wu-mt-1 sm:wu-mt-0 sm:wu-inline md:wu-hidden lg:wu-inline">
						Last updated on 22 Aug 2017
					</div>

					</div>

				</div>

				</div>

			</div>

			</div>

		</div>

		</div>

		<!-- Payment Method - End -->

	<?php endif; ?>

	</div>

</div>
