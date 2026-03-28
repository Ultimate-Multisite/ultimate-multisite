<?php
/**
 * Ultimate Multisite Tax Class.
 *
 * @package WP_Ultimo
 * @subpackage Tax
 * @since 2.0.0
 */

namespace WP_Ultimo\Tax;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Ultimate Multisite Tax Class.
 *
 * @since 2.0.0
 */
class Tax {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * Adds hooks to be added at the original instantiations.
	 *
	 * @since 1.9.0
	 */
	public function init(): void {

		add_action('init', [$this, 'add_settings']);

		add_action('wu_page_wp-ultimo-settings_load', [$this, 'add_sidebar_widget']);

		// Always register the Tax Rates admin page so users can manage rates even when taxes are disabled.
		add_action('wp_ultimo_admin_pages', [$this, 'add_admin_page']);
		add_action('wp_ajax_wu_get_tax_rates', [$this, 'serve_taxes_rates_via_ajax']);
		add_action('wp_ajax_wu_save_tax_rates', [$this, 'save_taxes_rates']);

		if ($this->is_enabled()) {
			add_action(
				'wu_before_search_models',
				function () {

					$model = wu_request('model', 'state');

					$country = wu_request('country', 'not-present');

					if ('not-present' === $country) {
						return;
					}

					if ('state' === $model) {
						$results = wu_get_country_states($country, 'slug', 'name');
					} elseif ('city' === $model) {
						$states = explode(',', (string) wu_request('state', ''));

						$results = wu_get_country_cities($country, $states, 'slug', 'name');
					}

					$query = wu_request(
						'query',
						[
							'search' => 'searching....',
						]
					);

					$s = trim((string) wu_get_isset($query, 'search', 'searching...'), '*');

					$filtered = [];

					if ( ! empty($s)) {
						$filtered = \Arrch\Arrch::find(
							$results,
							[
								'sort_key' => 'name',
								'where'    => [
									[['slug', 'name'], '~', $s],
								],
							]
						);
					}

					wp_send_json(array_values($filtered));

					exit;
				}
			);
		}
	}

	/**
	 * Register tax settings.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function add_settings(): void {

		wu_register_settings_section(
			'taxes',
			[
				'title' => __('Taxes', 'ultimate-multisite'),
				'desc'  => __('Taxes', 'ultimate-multisite'),
				'icon'  => 'dashicons-wu-percent',
				'order' => 55,
			]
		);

		wu_register_settings_field(
			'taxes',
			'enable_taxes',
			[
				'title'   => __('Enable Taxes', 'ultimate-multisite'),
				'desc'    => __('Enable this option to be able to collect sales taxes on your network payments.', 'ultimate-multisite'),
				'type'    => 'toggle',
				'default' => 0,
			]
		);

		wu_register_settings_field(
			'taxes',
			'inclusive_tax',
			[
				'title'   => __('Inclusive Tax', 'ultimate-multisite'),
				'desc'    => __('Enable this option if your prices include taxes. In that case, Ultimate Multisite will calculate the included tax instead of adding taxes to the price.', 'ultimate-multisite'),
				'type'    => 'toggle',
				'default' => 0,
				'require' => [
					'enable_taxes' => 1,
				],
			]
		);
	}

	/**
	 * Adds the sidebar widget.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function add_sidebar_widget(): void {

		wu_register_settings_side_panel(
			'taxes',
			[
				'title'  => __('Tax Rates', 'ultimate-multisite'),
				'render' => [$this, 'render_taxes_side_panel'],
			]
		);
	}

	/**
	 * Checks if this functionality is available and should be loaded.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function is_enabled() {

		$is_enabled = wu_get_setting('enable_taxes', false);

		return apply_filters('wu_enable_taxes', $is_enabled);
	}

	/**
	 * Adds the Tax Rate edit admin screen.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function add_admin_page(): void {

		new \WP_Ultimo\Admin_Pages\Tax_Rates_Admin_Page();
	}

	/**
	 * Returns the Tax Rate Types available in the platform; Filterable
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_tax_rate_types() {

		return apply_filters(
			'wu_get_tax_rate_types',
			[
				'regular' => __('Regular', 'ultimate-multisite'),
			]
		);
	}

	/**
	 * Returns the default elements of a tax rate.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_tax_rate_defaults() {

		$defaults = [
			'id'         => uniqid(),
			'title'      => __('Tax Rate', 'ultimate-multisite'),
			'country'    => '',
			'state'      => '',
			'city'       => '',
			'tax_type'   => 'percentage',
			'tax_amount' => 0,
			'priority'   => 10,
			'compound'   => false,
			'type'       => 'regular',
		];

		return apply_filters('wu_get_tax_rate_defaults', $defaults);
	}

	/**
	 * Returns the registered tax rates.
	 *
	 * @since 2.0.0
	 *
	 * @param bool $fetch_state_options If true, sends the state options along-side the results.
	 * @return array
	 */
	public function get_tax_rates($fetch_state_options = false) {

		$tax_rates_categories = wu_get_option(
			'tax_rates',
			[
				'default' => [
					'name'  => __('Default', 'ultimate-multisite'),
					'rates' => [],
				],
			]
		);

		if ( ! isset($tax_rates_categories['default'])) {
			/**
			 * We need to make sure the default category is always present.
			 */
			$default = array_shift($tax_rates_categories);

			$tax_rates_categories = array_merge(['default' => $default], $tax_rates_categories);
		}

		foreach ($tax_rates_categories as &$tax_rate_category) {
			$tax_rate_category['rates'] = array_map(
				function ($rate) use ($fetch_state_options) {

					if ($fetch_state_options) {
						/*
						 * Rates with country='*' (Apply to all countries) are wildcard
						 * fallbacks and have no meaningful state list — skip the lookup
						 * to avoid passing '*' to wu_get_country_states().
						 */
						$rate['state_options'] = ('*' !== $rate['country'])
							? wu_get_country_states($rate['country'], 'slug', 'name')
							: [];
					}

					$rate['tax_rate'] = is_numeric($rate['tax_rate']) ? $rate['tax_rate'] : 0;

					return wp_parse_args($rate, $this->get_tax_rate_defaults());
				},
				$tax_rate_category['rates']
			);
		}

		return apply_filters('wu_get_tax_rates', $tax_rates_categories, $fetch_state_options);
	}

	/**
	 * Retrieves the tax rates to serve via ajax.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function serve_taxes_rates_via_ajax(): void {

		$tax_rates = [];

		if (current_user_can('read_tax_rates')) {
			$tax_rates = $this->get_tax_rates(true);
		}

		wp_send_json_success((object) $tax_rates);
	}

	/**
	 * Handles the saving of new tax rates.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function save_taxes_rates(): void {

		if ( ! check_ajax_referer('wu_tax_editing')) {
			wp_send_json(
				[
					'code'    => 'not-enough-permissions',
					'message' => __('You don\'t have permission to alter tax rates', 'ultimate-multisite'),
				]
			);
		}

		// Init filesystem if not yet initiated.
		WP_Filesystem();

		// Get POST body HTML data.
		global $wp_filesystem;
		$json_data = $wp_filesystem->get_contents('php://input');
		$data      = wu_clean(json_decode($json_data, true));

		$tax_rates = $data['tax_rates'] ?? false;

		if ( ! $tax_rates) {
			wp_send_json(
				[
					'code'    => 'tax-rates-not-found',
					'message' => __('No tax rates present in the request', 'ultimate-multisite'),
				]
			);
		}

		$treated_tax_rates = [];

		foreach ($tax_rates as $tax_rate_slug => $tax_rate) {
			if ( ! isset($tax_rate['rates'])) {
				continue;
			}

			$tax_rate['rates'] = array_map(
				function ($item) {

					unset($item['selected']);

					unset($item['state_options']);

					return $item;
				},
				$tax_rate['rates']
			);

			$treated_tax_rates[ strtolower(sanitize_title($tax_rate_slug)) ] = $tax_rate;
		}

		wu_save_option('tax_rates', $treated_tax_rates);

		wp_send_json(
			[
				'code'         => 'success',
				'message'      => __('Tax Rates successfully updated!', 'ultimate-multisite'),
				'tax_category' => strtolower(sanitize_title(wu_get_isset($data, 'tax_category', 'default'))),
			]
		);
	}

	/**
	 * Render the tax side panel.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function render_taxes_side_panel(): void {

		wp_enqueue_script('wu-vue');

		$inline_script = sprintf(
			'document.addEventListener("DOMContentLoaded", function() {
				new Vue({
					el: "#wu-taxes-side-panel",
					data: {},
					computed: {
						enabled: function() {
							return %s;
						}
					}
				});
			});',
			wp_json_encode(wu_get_setting('enable_taxes', false))
		);

		wp_add_inline_script('wu-vue', $inline_script);

		?>

		<div id="wu-taxes-side-panel" class="wu-widget-inset">

			<div class="wu-p-4">

				<span class="wu-text-gray-700 wu-font-bold wu-uppercase wu-tracking-wide wu-text-xs">
					<?php esc_html_e('Manage Tax Rates', 'ultimate-multisite'); ?>
				</span>

				<div class="wu-py-2">
					<img class="wu-w-full" alt="<?php esc_attr_e('Manage Tax Rates', 'ultimate-multisite'); ?>" src="<?php echo esc_attr(wu_get_asset('sidebar/invoices.webp')); ?>">
				</div>

				<p class="wu-text-gray-600 wu-p-0 wu-m-0">
					<?php esc_html_e('Add different tax rates depending on the country of your customers.', 'ultimate-multisite'); ?>
				</p>

			</div>

			<div v-cloak v-show="enabled == 0" class="wu-mx-4 wu-p-2 wu-bg-blue-100 wu-text-blue-600 wu-rounded wu-mb-4">
				<?php esc_html_e('You need to activate tax support first.', 'ultimate-multisite'); ?>
			</div>

			<?php if (current_user_can('wu_edit_payments')) : ?>

				<div class="wu-p-4 wu-bg-gray-100 wu-border-solid wu-border-0 wu-border-t wu-border-gray-300">

					<span v-if="false" class="button wu-w-full wu-text-center">
						<?php esc_html_e('Manage Tax Rates &rarr;', 'ultimate-multisite'); ?>
					</span>

					<div v-cloak>

						<a v-if="enabled" class="button wu-w-full wu-text-center" target="_blank" href="<?php echo esc_attr(wu_network_admin_url('wp-ultimo-tax-rates')); ?>">
							<?php esc_html_e('Manage Tax Rates &rarr;', 'ultimate-multisite'); ?>
						</a>

						<button v-else disabled="disabled" class="button wu-w-full wu-text-center">
							<?php esc_html_e('Manage Tax Rates &rarr;', 'ultimate-multisite'); ?>
						</button>

					</div>
				</div>

			<?php endif; ?>

		</div>

		<?php
	}
}
