<?php
/**
 * Adds the Magic_Link_Url_Element UI to the Admin Panel.
 *
 * @package WP_Ultimo
 * @subpackage UI
 * @since 2.0.0
 */

namespace WP_Ultimo\UI;

use WP_Ultimo\SSO\Magic_Link;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Adds a Magic Link URL Element to pages.
 *
 * This element generates a magic link URL for a specified site,
 * allowing users to create one-click login links to sites with custom domains.
 *
 * @since 2.0.0
 */
class Magic_Link_Url_Element extends Base_Element {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * The id of the element.
	 *
	 * Something simple, without prefixes, like 'checkout', or 'pricing-tables'.
	 *
	 * This is used to construct shortcodes by prefixing the id with 'wu_'
	 * e.g. an id checkout becomes the shortcode 'wu_checkout' and
	 * to generate the Gutenberg block by prefixing it with 'wp-ultimo/'
	 * e.g. checkout would become the block 'wp-ultimo/checkout'.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	public $id = 'magic-link-url';

	/**
	 * Controls if this is a public element to be used in pages/shortcodes by user.
	 *
	 * @since 2.0.24
	 * @var boolean
	 */
	protected $public = true;

	/**
	 * The generated magic link URL.
	 *
	 * @since 2.0.0
	 * @var string|null
	 */
	protected ?string $magic_link_url = null;

	/**
	 * The site for the magic link.
	 *
	 * @since 2.0.0
	 * @var \WP_Ultimo\Models\Site|null
	 */
	protected ?\WP_Ultimo\Models\Site $site = null;

	/**
	 * The icon of the UI element.
	 * e.g. return fa fa-search
	 *
	 * @since 2.0.0
	 * @param string $context One of the values: block, elementor or bb.
	 * @return string
	 */
	public function get_icon($context = 'block'): string {

		if ('elementor' === $context) {
			return 'eicon-link';
		}

		return 'fa fa-link';
	}

	/**
	 * The title of the UI element.
	 *
	 * This is used on the Blocks list of Gutenberg.
	 * You should return a string with the localized title.
	 * e.g. return __('My Element', 'ultimate-multisite').
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_title(): string {

		return __('Magic Link URL', 'ultimate-multisite');
	}

	/**
	 * The description of the UI element.
	 *
	 * This is also used on the Gutenberg block list
	 * to explain what this block is about.
	 * You should return a string with the localized title.
	 * e.g. return __('Adds a checkout form to the page', 'ultimate-multisite').
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_description(): string {

		return __('Generates a magic link URL for quick site access with automatic authentication.', 'ultimate-multisite');
	}

	/**
	 * The list of fields to be added to Gutenberg.
	 *
	 * If you plan to add Gutenberg controls to this block,
	 * you'll need to return an array of fields, following
	 * our fields interface (@see inc/ui/class-field.php).
	 *
	 * You can create new Gutenberg panels by adding fields
	 * with the type 'header'. See the Checkout Elements for reference.
	 *
	 * @see inc/ui/class-checkout-element.php
	 *
	 * Return an empty array if you don't have controls to add.
	 *
	 * @since 2.0.0
	 * @return array<string, array<string, mixed>>
	 */
	public function fields(): array {

		$fields = [];

		$fields['header'] = [
			'title' => __('General', 'ultimate-multisite'),
			'desc'  => __('General', 'ultimate-multisite'),
			'type'  => 'header',
		];

		$fields['site_id'] = [
			'type'        => 'text',
			'title'       => __('Site ID', 'ultimate-multisite'),
			'placeholder' => __('E.g. 2', 'ultimate-multisite'),
			'desc'        => __('The ID of the site to generate the magic link for.', 'ultimate-multisite'),
			'tooltip'     => __('You can find the site ID in the Sites list in the network admin.', 'ultimate-multisite'),
		];

		$fields['display_header'] = [
			'title' => __('Display Options', 'ultimate-multisite'),
			'desc'  => __('Display Options', 'ultimate-multisite'),
			'type'  => 'header',
		];

		$fields['display_as'] = [
			'type'    => 'select',
			'title'   => __('Display As', 'ultimate-multisite'),
			'desc'    => __('Choose how to display the magic link.', 'ultimate-multisite'),
			'options' => [
				'anchor' => __('Clickable Link', 'ultimate-multisite'),
				'button' => __('Button', 'ultimate-multisite'),
				'url'    => __('Plain URL Text', 'ultimate-multisite'),
			],
			'value'   => 'anchor',
		];

		$fields['link_text'] = [
			'type'        => 'text',
			'title'       => __('Link Text', 'ultimate-multisite'),
			'placeholder' => __('E.g. Visit Site', 'ultimate-multisite'),
			'desc'        => __('The text to display for the link or button.', 'ultimate-multisite'),
			'value'       => __('Visit Site', 'ultimate-multisite'),
			'required'    => [
				'display_as' => ['anchor', 'button'],
			],
		];

		$fields['open_in_new_tab'] = [
			'type'     => 'toggle',
			'title'    => __('Open in New Tab?', 'ultimate-multisite'),
			'desc'     => __('Toggle to open the link in a new browser tab.', 'ultimate-multisite'),
			'value'    => 0,
			'required' => [
				'display_as' => ['anchor', 'button'],
			],
		];

		return $fields;
	}

	/**
	 * Registers scripts and styles necessary to render this.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function register_scripts(): void {

		wp_enqueue_style('wu-admin');
	}

	/**
	 * The list of keywords for this element.
	 *
	 * Return an array of strings with keywords describing this
	 * element. Gutenberg uses this to help customers find blocks.
	 *
	 * e.g.:
	 * return array(
	 *  'Ultimate Multisite',
	 *  'Magic Link',
	 *  'URL',
	 * );
	 *
	 * @since 2.0.0
	 * @return array<int, string>
	 */
	public function keywords(): array {

		return [
			'WP Ultimo',
			'Ultimate Multisite',
			'Magic Link',
			'URL',
			'SSO',
			'Authentication',
			'Login',
			'Site Access',
		];
	}

	/**
	 * List of default parameters for the element.
	 *
	 * If you are planning to add controls using the fields,
	 * it might be a good idea to use this method to set defaults
	 * for the parameters you are expecting.
	 *
	 * These defaults will be used inside a 'wp_parse_args' call
	 * before passing the parameters down to the block render
	 * function and the shortcode render function.
	 *
	 * @since 2.0.0
	 * @return array<string, string|int>
	 */
	public function defaults(): array {

		return [
			'site_id'         => '',
			'redirect_to'     => '',
			'display_as'      => 'anchor',
			'link_text'       => __('Visit Site', 'ultimate-multisite'),
			'open_in_new_tab' => 0,
		];
	}

	/**
	 * Runs early on the request lifecycle as soon as we detect the shortcode is present.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function setup(): void {
		// No setup restrictions - we render for both logged-in and anonymous users.
		// Anonymous users get a regular link without the magic token.
	}

	/**
	 * Allows the setup in the context of previews.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function setup_preview(): void {

		$this->site           = wu_mock_site();
		$this->magic_link_url = home_url('?wu_magic_token=preview_token_example');
	}

	/**
	 * Get the site ID from attributes or current context.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $atts Block/shortcode attributes.
	 * @return int|null The site ID or null if not found.
	 */
	protected function get_site_id_from_atts(array $atts): ?int {

		// If site_id is explicitly provided, use it
		if ( ! empty($atts['site_id'])) {
			$site_id = $atts['site_id'];

			if (is_numeric($site_id)) {
				return absint($site_id);
			}
		}

		// Try to get from URL query parameter
		$site_hash = wu_request('site');

		if ($site_hash && is_string($site_hash)) {
			$site = wu_get_site_by_hash($site_hash);

			if ($site) {
				return $site->get_id();
			}
		}

		// Try to get current site from WP_Ultimo context
		$current_site = WP_Ultimo()->currents->get_site();

		if ($current_site) {
			return $current_site->get_id();
		}

		return null;
	}

	/**
	 * Generate the URL for the site.
	 *
	 * For logged-in users with site access, generates a magic link with authentication token.
	 * For anonymous users or users without access, returns a regular site URL.
	 *
	 * @since 2.0.0
	 *
	 * @param int    $site_id     The site ID.
	 * @param string $redirect_to Optional redirect URL.
	 * @return string|null The URL or null on failure.
	 */
	protected function generate_url(int $site_id, string $redirect_to = ''): ?string {

		$site = wu_get_site($site_id);

		if ( ! $site) {
			return null;
		}

		$user_id = get_current_user_id();
		$url     = null;

		// Try to generate magic link for logged-in users with site access
		if ($user_id && is_user_member_of_blog($user_id, $site_id) && wu_get_setting('enable_magic_links', true)) {
			$magic_link = Magic_Link::get_instance();
			$url        = $magic_link->generate_magic_link($user_id, $site_id, $redirect_to);
		}

		// Fall back to regular site URL for anonymous users or if magic link fails
		if ( ! $url) {
			$url = $site->get_active_site_url();

			if ($redirect_to) {
				$url = trailingslashit($url) . ltrim($redirect_to, '/');
			}
		}

		return $url;
	}

	/**
	 * The content to be output on the screen.
	 *
	 * Should return HTML markup to be used to display the block.
	 * This method is shared between the block render method and
	 * the shortcode implementation.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $atts Parameters of the block/shortcode.
	 * @param string|null          $content The content inside the shortcode.
	 * @return void
	 */
	public function output($atts, $content = null) {

		$this->ensure_setup();

		// Get the site ID
		$site_id = $this->get_site_id_from_atts($atts);

		if ( ! $site_id) {
			// No site ID available, show error message in admin context
			if (current_user_can('manage_network')) {
				echo '<p class="wu-text-red-600">' . esc_html__('Magic Link URL: No site ID specified or found.', 'ultimate-multisite') . '</p>';
			}

			return;
		}

		// Get the site
		$site = wu_get_site($site_id);

		if ( ! $site) {
			if (current_user_can('manage_network')) {
				echo '<p class="wu-text-red-600">' . esc_html__('Magic Link URL: Site not found.', 'ultimate-multisite') . '</p>';
			}

			return;
		}

		// Build redirect URL
		$redirect_to = '';

		if ( ! empty($atts['redirect_to']) && is_string($atts['redirect_to'])) {
			$redirect_to = $atts['redirect_to'];

			// If it's a relative path, make it absolute
			if (strpos($redirect_to, 'http') !== 0) {
				$redirect_to = trailingslashit($site->get_active_site_url()) . ltrim($redirect_to, '/');
			}
		}

		// Generate the magic link URL
		$magic_link_url = $this->generate_url($site_id, $redirect_to);

		if ( ! $magic_link_url) {
			return;
		}

		// Prepare template variables
		$atts['magic_link_url'] = $magic_link_url;
		$atts['site']           = $site;

		wu_get_template('dashboard-widgets/magic-link-url', $atts);
	}
}
