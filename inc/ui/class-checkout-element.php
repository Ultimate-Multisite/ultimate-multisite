<?php
/**
 * Adds the Checkout_Element UI to the Admin Panel.
 *
 * @package WP_Ultimo
 * @subpackage UI
 * @since 2.0.0
 */

namespace WP_Ultimo\UI;

use Psr\Log\LogLevel;
use ScssPhp\ScssPhp\Compiler;
use ScssPhp\ScssPhp\Exception\SassException;
use WP_Ultimo\Database\Memberships\Membership_Status;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Adds the Checkout Element UI to the Admin Panel.
 *
 * @since 2.0.0
 */
class Checkout_Element extends Base_Element {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * The current signup.
	 *
	 * @since 2.2.0
	 * @var Mocked_Signup
	 */
	protected $signup;

	/**
	 * The current checkout form steps.
	 *
	 * @since 2.2.0
	 * @var array|false
	 */
	public $steps;

	/**
	 * The current checkout form step.
	 *
	 * @since 2.2.0
	 * @var array
	 */
	public $step;

	/**
	 * The current checkout form step name.
	 *
	 * @since 2.2.0
	 * @var string
	 */
	public $step_name;

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
	public $id = 'checkout';

	/**
	 * Controls if this is a public element to be used in pages/shortcodes by user.
	 *
	 * @since 2.0.24
	 * @var boolean
	 */
	protected $public = true;

	/**
	 * Tracks if no-cache checkout signals were already sent for this request.
	 *
	 * @since 2.4.14
	 * @var bool
	 */
	protected $checkout_nocache_sent = false;

	/**
	 * Initializes hooks specific to the checkout element.
	 *
	 * @since 2.4.14
	 * @return void
	 */
	public function init() {

		parent::init();

		add_action('wu_ajax_wu_render_checkout', [$this, 'render_deferred_checkout']);

		add_action('wu_ajax_nopriv_wu_render_checkout', [$this, 'render_deferred_checkout']);

		add_filter('wu_light_ajax_should_skip_referer_check', [$this, 'allow_deferred_checkout_ajax_without_referer']);
	}

	/**
	 * The icon of the UI element.
	 * e.g. return fa fa-search
	 *
	 * @since 2.0.0
	 * @param string $context One of the values: block, elementor or bb.
	 */
	public function get_icon($context = 'block'): string {

		if ('elementor' === $context) {
			return 'eicon-cart-medium';
		}

		return 'fa fa-search';
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
	public function get_title() {

		return __('Checkout', 'ultimate-multisite');
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
	public function get_description() {

		return __('Adds a checkout form block to the page.', 'ultimate-multisite');
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
	 * @return array
	 */
	public function fields() {

		$fields = [];

		$fields['header'] = [
			'title' => __('General', 'ultimate-multisite'),
			'desc'  => __('General', 'ultimate-multisite'),
			'type'  => 'header',
		];

		$fields['slug'] = [
			'title' => __('Slug', 'ultimate-multisite'),
			'desc'  => __('The checkout form slug.', 'ultimate-multisite'),
			'type'  => 'text',
		];

		$fields['defer'] = [
			'title' => __('Defer Checkout Loading', 'ultimate-multisite'),
			'desc'  => __('Outputs a cache-safe placeholder first, then loads fresh checkout markup after visitor intent.', 'ultimate-multisite'),
			'type'  => 'toggle',
		];

		$fields['defer_trigger'] = [
			'title'   => __('Deferred Loading Trigger', 'ultimate-multisite'),
			'desc'    => __('Choose when the deferred checkout placeholder should request the live checkout form.', 'ultimate-multisite'),
			'type'    => 'select',
			'options' => [
				'viewport' => __('When the placeholder enters the viewport', 'ultimate-multisite'),
				'click'    => __('Only after a click or focus', 'ultimate-multisite'),
				'load'     => __('As soon as the page loads', 'ultimate-multisite'),
			],
		];

		return $fields;
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
	 *  'Checkout',
	 *  'Form',
	 *  'Cart',
	 * );
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function keywords() {

		return [
			'WP Ultimo',
			'Ultimate Multisite',
			'Checkout',
			'Form',
			'Cart',
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
	 * @return array
	 */
	public function defaults() {

		return [
			'slug'                   => 'main-form',
			'step'                   => false,
			'display_title'          => false,
			'membership_limitations' => [],
			'defer'                  => false,
			'defer_trigger'          => 'viewport',
		];
	}

	/**
	 * Retains the current block or shortcode attributes for script registration.
	 *
	 * Block templates can render without a post whose content contains the block,
	 * so the early element scan cannot always preload the configured slug.
	 *
	 * @since 2.4.15
	 *
	 * @param array $atts Element attributes.
	 * @return void
	 */
	public function display($atts) {

		$this->pre_loaded_attributes = wp_parse_args($atts, $this->defaults());

		parent::display($atts);
	}

	/**
	 * Checks if we are on a thank you page.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function is_thank_you_page() {

		return is_user_logged_in() && wu_request('payment') && wu_request('status') === 'done';
	}

	/**
	 * Triggers the setup event to allow the checkout class to hook in.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function setup() {

		if ($this->is_thank_you_page()) {
			\WP_Ultimo\UI\Thank_You_Element::get_instance()->setup();

			return;
		}

		$pre_loaded_attributes = is_array($this->pre_loaded_attributes) ? $this->pre_loaded_attributes : [];

		$atts = wp_parse_args($pre_loaded_attributes, $this->defaults());

		if ($this->should_defer_checkout($atts)) {
			return;
		}

		$this->send_checkout_nocache_headers($atts);

		do_action('wu_setup_checkout', $this);
	}

	/**
	 * Skips the checkout script enqueue pass for cache-safe deferred placeholders.
	 *
	 * @since 2.4.14
	 * @return void
	 */
	public function enqueue_element_scripts() {

		global $post;

		if ( ! is_a($post, '\WP_Post')) {
			return;
		}

		if ($this->contains_current_element($post->post_content, $post)) {
			$pre_loaded_attributes = is_array($this->pre_loaded_attributes) ? $this->pre_loaded_attributes : [];

			$atts = wp_parse_args($pre_loaded_attributes, $this->defaults());

			if ($this->should_defer_checkout($atts)) {
				return;
			}
		}

		parent::enqueue_element_scripts();
	}

	/**
	 * Allows the deferred checkout light-AJAX request to work from cached pages.
	 *
	 * Cached placeholders can outlive nonce windows, so the endpoint intentionally
	 * avoids relying on the cacheable `r` nonce. The endpoint only renders the same
	 * public checkout form that the shortcode/block would render directly.
	 *
	 * @since 2.4.14
	 *
	 * @param array $allowed_actions Light-AJAX actions allowed without referer checks.
	 * @return array
	 */
	public function allow_deferred_checkout_ajax_without_referer($allowed_actions) {

		$allowed_actions[] = 'wu_render_checkout';

		return array_unique($allowed_actions);
	}

	/**
	 * @return void
	 */
	public function register_scripts() {
		$slug = $this->get_pre_loaded_attribute('slug');

		if (! $slug) {
			$slug = $this->defaults()['slug'];
		}

		$checkout_form = wu_get_checkout_form_by_slug($slug);

		if (! $checkout_form) {
			return;
		}
		$custom_css = $checkout_form->get_custom_css();

		if (! $custom_css) {
			return;
		}

		try {
			$scss       = new Compiler();
			$custom_css = $scss->compileString(
				".wu_checkout_form_{$slug} {
					{$custom_css}
				}"
			)->getCss();

			wp_add_inline_style('wu-checkout', $custom_css);
		} catch (SassException $e) {
			// translators: %s the error message.
			wu_log_add('checkout', sprintf(__('An error occurred while compiling scss: %s', 'ultimate-multisite'), $e->getMessage()), LogLevel::ERROR);
		}
	}

	/**
	 * Renders the live checkout markup for deferred placeholders.
	 *
	 * @since 2.4.14
	 * @return void
	 */
	public function render_deferred_checkout() {

		$atts = $this->get_deferred_checkout_request_atts();

		$_REQUEST['checkout_form'] = $atts['slug']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$this->pre_loaded_attributes = $atts;

		$this->set_checkout_intent_cookie();

		$this->send_checkout_nocache_headers($atts);

		$this->setup();

		do_action('wu_checkout_scripts', null, $this);

		ob_start();

		$this->output($atts);

		$html = ob_get_clean();

		ob_start();

		wp_print_styles(['wu-checkout', 'wu-admin', 'wu-password']);

		wp_print_scripts(['wu-checkout']);

		$assets = ob_get_clean();

		wp_send_json_success(
			[
				'html' => $assets . $html,
			]
		);
	}

	/**
	 * Builds safe checkout attributes from the deferred checkout request.
	 *
	 * @since 2.4.14
	 * @return array
	 */
	protected function get_deferred_checkout_request_atts() {

		$membership_limitations = wu_request('membership_limitations', []);

		if (is_string($membership_limitations)) {
			$membership_limitations = explode(',', $membership_limitations);
		}

		if ( ! is_array($membership_limitations)) {
			$membership_limitations = [];
		}

		$membership_limitations = array_values(array_filter(array_map('sanitize_key', $membership_limitations)));

		return wp_parse_args(
			[
				'slug'                   => sanitize_text_field((string) wu_request('slug', 'main-form')),
				'step'                   => sanitize_key((string) wu_request('step', '')) ?: false,
				'display_title'          => $this->is_truthy_attribute(wu_request('display_title', false)),
				'membership_limitations' => $membership_limitations,
				'defer'                  => false,
			],
			$this->defaults()
		);
	}

	/**
	 * Sets a lightweight intent cookie that cache layers can use as a bypass signal.
	 *
	 * @since 2.4.14
	 * @return void
	 */
	protected function set_checkout_intent_cookie() {

		$cookie = new \Delight\Cookie\Cookie('wu_checkout_intent');
		$cookie->setValue('1');
		$cookie->setMaxAge(HOUR_IN_SECONDS);
		$cookie->setPath('/');
		$cookie->setDomain(COOKIE_DOMAIN);
		$cookie->setHttpOnly(true);
		$cookie->setSecureOnly(is_ssl());
		$cookie->setSameSiteRestriction('Lax');
		$cookie->save();

		$_COOKIE['wu_checkout_intent'] = '1';
	}

	/**
	 * Sends no-cache signals for live checkout markup.
	 *
	 * @since 2.4.14
	 *
	 * @param array $atts Checkout block/shortcode attributes.
	 * @return void
	 */
	protected function send_checkout_nocache_headers($atts) {

		if ($this->checkout_nocache_sent) {
			return;
		}

		$this->checkout_nocache_sent = true;

		if ( ! defined('DONOTCACHEPAGE')) {
			define('DONOTCACHEPAGE', true); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
		}

		/**
		 * Fires when live checkout output requires a fresh, non-cacheable response.
		 *
		 * Cache adapters can use this action to integrate with host-specific bypass
		 * APIs that do not rely on standard WordPress constants or HTTP headers.
		 *
		 * @since 2.4.14
		 *
		 * @param array            $atts Checkout block/shortcode attributes.
		 * @param Checkout_Element $element Checkout element instance.
		 */
		do_action('wu_checkout_nocache_required', $atts, $this);

		do_action('litespeed_control_set_nocache', 'Ultimate Multisite checkout contains per-request state.'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		if (headers_sent()) {
			return;
		}

		nocache_headers();

		$headers = [
			'Cache-Control'             => 'no-store, no-cache, must-revalidate, max-age=0, private',
			'Pragma'                    => 'no-cache',
			'Expires'                   => 'Wed, 11 Jan 1984 05:00:00 GMT',
			'Surrogate-Control'         => 'no-store',
			'CDN-Cache-Control'         => 'no-store',
			'X-Accel-Expires'           => '0',
			'X-LiteSpeed-Cache-Control' => 'no-cache',
		];

		/**
		 * Filters additional no-cache headers sent with live checkout markup.
		 *
		 * @since 2.4.14
		 *
		 * @param array            $headers Header name/value pairs.
		 * @param array            $atts Checkout block/shortcode attributes.
		 * @param Checkout_Element $element Checkout element instance.
		 */
		$headers = apply_filters('wu_checkout_nocache_headers', $headers, $atts, $this);

		foreach ($headers as $name => $value) {
			$name  = trim(str_replace(["\r", "\n"], '', (string) $name));
			$value = str_replace(["\r", "\n"], '', (string) $value);

			if ('' === $name || ! preg_match('/^[A-Za-z0-9-]+$/', $name)) {
				continue;
			}

			header(sprintf('%s: %s', $name, $value), true);
		}
	}

	/**
	 * Checks if a checkout attribute value should be treated as true.
	 *
	 * @since 2.4.14
	 *
	 * @param mixed $value Attribute value.
	 * @return bool
	 */
	protected function is_truthy_attribute($value) {

		if (is_bool($value)) {
			return $value;
		}

		if (is_string($value)) {
			return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
		}

		return (bool) $value;
	}

	/**
	 * Checks if the current request already expresses live checkout intent.
	 *
	 * @since 2.4.14
	 * @return bool
	 */
	protected function has_checkout_intent() {

		if (isset($_COOKIE['wu_checkout_intent']) || isset($_COOKIE['wu_session_signup'])) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			return true;
		}

		$live_request_keys = apply_filters(
			'wu_checkout_live_request_keys',
			[
				'checkout_action',
				'checkout_form',
				'payment',
				'payment_id',
				'pre-flight',
				'resume_checkout',
				'step',
				'wu_form',
			],
			$this
		);

		foreach ($live_request_keys as $request_key) {
			if (wu_request($request_key)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks if checkout output should be deferred for cache safety.
	 *
	 * @since 2.4.14
	 *
	 * @param array $atts Checkout block/shortcode attributes.
	 * @return bool
	 */
	protected function should_defer_checkout($atts) {

		if (wu_is_update_page() || wu_is_new_site_page() || $this->is_thank_you_page()) {
			return false;
		}

		$should_defer = $this->is_truthy_attribute($atts['defer'] ?? false) && ! $this->has_checkout_intent();

		return apply_filters('wu_checkout_should_defer_output', $should_defer, $atts, $this);
	}

	/**
	 * Returns a supported deferred checkout trigger.
	 *
	 * @since 2.4.14
	 *
	 * @param array $atts Checkout block/shortcode attributes.
	 * @return string
	 */
	protected function get_deferred_checkout_trigger($atts) {

		$trigger = sanitize_key((string) wu_get_isset($atts, 'defer_trigger', 'viewport'));

		return in_array($trigger, ['click', 'load', 'viewport'], true) ? $trigger : 'viewport';
	}

	/**
	 * Builds the payload used by the deferred checkout request.
	 *
	 * @since 2.4.14
	 *
	 * @param array $atts Checkout block/shortcode attributes.
	 * @return array
	 */
	protected function get_deferred_checkout_payload($atts) {

		$membership_limitations = wu_get_isset($atts, 'membership_limitations', []);

		if ( ! is_array($membership_limitations)) {
			$membership_limitations = [];
		}

		return [
			'action'                 => 'wu_render_checkout',
			'slug'                   => sanitize_text_field((string) wu_get_isset($atts, 'slug', 'main-form')),
			'step'                   => sanitize_key((string) wu_get_isset($atts, 'step', '')),
			'display_title'          => $this->is_truthy_attribute(wu_get_isset($atts, 'display_title', false)) ? 1 : 0,
			'membership_limitations' => array_values(array_filter(array_map('sanitize_key', $membership_limitations))),
		];
	}

	/**
	 * Outputs a cache-safe placeholder that can request live checkout markup later.
	 *
	 * @since 2.4.14
	 *
	 * @param array $atts Checkout block/shortcode attributes.
	 * @return void
	 */
	protected function output_deferred_placeholder($atts) {

		$placeholder_id = wp_unique_id('wu-checkout-deferred-');
		$endpoint       = wu_ajax_url('init', ['action' => 'wu_render_checkout']);
		$payload        = $this->get_deferred_checkout_payload($atts);
		$trigger        = $this->get_deferred_checkout_trigger($atts);

		?>
		<div
			id="<?php echo esc_attr($placeholder_id); ?>"
			class="wu-checkout-deferred wu-styling"
			data-wu-checkout-deferred="1"
			data-endpoint="<?php echo esc_url($endpoint); ?>"
			data-trigger="<?php echo esc_attr($trigger); ?>"
			data-payload="<?php echo esc_attr(wp_json_encode($payload)); ?>"
			data-error-message="<?php echo esc_attr__('We could not load the checkout form. Please refresh the page and try again.', 'ultimate-multisite'); ?>"
		>
			<div class="wu-checkout-deferred__placeholder wu-p-4 wu-border wu-border-solid wu-border-gray-300 wu-rounded wu-text-center">
				<p class="wu-m-0 wu-mb-3"><?php esc_html_e('Checkout is ready when you are.', 'ultimate-multisite'); ?></p>
				<button type="button" class="button button-primary" data-wu-checkout-deferred-button>
					<?php esc_html_e('Start checkout', 'ultimate-multisite'); ?>
				</button>
				<span class="wu-checkout-deferred__loading wu-ml-2" hidden data-wu-checkout-deferred-loading>
					<?php esc_html_e('Loading checkout...', 'ultimate-multisite'); ?>
				</span>
			</div>
			<div class="wu-checkout-deferred__target" hidden data-wu-checkout-deferred-target></div>
		</div>
		<script>
		(function() {
			var root = document.getElementById('<?php echo esc_js($placeholder_id); ?>');

			if (! root) {
				return;
			}

			var button = root.querySelector('[data-wu-checkout-deferred-button]');
			var loading = root.querySelector('[data-wu-checkout-deferred-loading]');
			var target = root.querySelector('[data-wu-checkout-deferred-target]');
			var endpoint = root.getAttribute('data-endpoint');
			var trigger = root.getAttribute('data-trigger') || 'viewport';
			var loaded = false;
			var payload = {};

			try {
				payload = JSON.parse(root.getAttribute('data-payload') || '{}');
			} catch (error) {
				payload = {};
			}

			function appendParam(parts, key, value) {
				if (Array.isArray(value)) {
					value.forEach(function(item) {
						appendParam(parts, key + '[]', item);
					});

					return;
				}

				parts.push(encodeURIComponent(key) + '=' + encodeURIComponent(value));
			}

			function executeScripts(container) {
				var scripts = container.querySelectorAll('script');

				scripts.forEach(function(script) {
					var replacement = document.createElement('script');

					Array.prototype.forEach.call(script.attributes, function(attribute) {
						replacement.setAttribute(attribute.name, attribute.value);
					});

					replacement.async = false;

					if (script.src) {
						replacement.src = script.src;
					} else {
						replacement.text = script.text || script.textContent || script.innerHTML || '';
					}

					script.parentNode.replaceChild(replacement, script);
				});
			}

			function setLoading(isLoading) {
				if (button) {
					button.disabled = isLoading;
				}

				if (loading) {
					loading.hidden = ! isLoading;
				}
			}

			function showError() {
				var message = root.getAttribute('data-error-message');
				var paragraph;

				setLoading(false);

				if (target) {
					target.hidden = false;
					target.innerHTML = '';
					paragraph = document.createElement('p');
					paragraph.className = 'wu-text-red-600';
					paragraph.textContent = message;
					target.appendChild(paragraph);
				}
			}

			function announceLoaded() {
				var event;

				if ('function' === typeof window.Event) {
					event = new Event('wu_checkout_deferred_loaded', {bubbles: true});
				} else {
					event = document.createEvent('Event');
					event.initEvent('wu_checkout_deferred_loaded', true, true);
				}

				root.dispatchEvent(event);
			}

			function loadCheckout() {
				var parts = [];
				var request;

				if (loaded || ! endpoint || ! target) {
					return;
				}

				loaded = true;
				setLoading(true);

				Object.keys(payload).forEach(function(key) {
					appendParam(parts, key, payload[key]);
				});

				request = new XMLHttpRequest();
				request.open('POST', endpoint, true);
				request.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');

				request.onreadystatechange = function() {
					var response;
					var html = '';

					if (4 !== request.readyState) {
						return;
					}

					if (request.status < 200 || request.status >= 300) {
						showError();

						return;
					}

					try {
						response = JSON.parse(request.responseText);
						html = response && response.data ? response.data.html : '';
					} catch (error) {
						html = request.responseText;
					}

					if (! html) {
						showError();

						return;
					}

					setLoading(false);
					target.hidden = false;
					target.innerHTML = html;
					executeScripts(target);

					if (button) {
						button.hidden = true;
					}

					announceLoaded();
				};

				request.send(parts.join('&'));
			}

			function cleanupFallbackLoad() {
				document.removeEventListener('DOMContentLoaded', fallbackLoad);
				window.removeEventListener('scroll', fallbackLoad);
				window.removeEventListener('resize', fallbackLoad);
			}

			function fallbackLoad() {
				cleanupFallbackLoad();
				loadCheckout();
			}

			function scheduleFallbackLoad() {
				if ('loading' === document.readyState) {
					document.addEventListener('DOMContentLoaded', fallbackLoad);
				} else {
					fallbackLoad();
				}
			}

			if (button) {
				button.addEventListener('click', function(event) {
					event.preventDefault();
					loadCheckout();
				});
			}

			root.addEventListener('focusin', loadCheckout);

			if ('load' === trigger) {
				if ('loading' === document.readyState) {
					document.addEventListener('DOMContentLoaded', loadCheckout);
				} else {
					loadCheckout();
				}
			} else if ('viewport' === trigger && 'IntersectionObserver' in window) {
				new IntersectionObserver(function(entries, observer) {
					if (entries.some(function(entry) { return entry.isIntersecting; })) {
						observer.disconnect();
						loadCheckout();
					}
				}).observe(root);
			} else if ('viewport' === trigger) {
				scheduleFallbackLoad();
			}
		})();
		</script>
		<?php
	}

	/**
	 * Outputs thank you page.
	 *
	 * @since 2.0.0
	 *
	 * @param array       $atts Parameters of the block/shortcode.
	 * @param string|null $content The content inside the shortcode.
	 * @return void
	 */
	public function output_thank_you($atts, $content = null): void {

		$slug = $atts['slug'];

		$checkout_form = wu_get_checkout_form_by_slug($slug);

		$atts = $checkout_form->get_meta('wu_thank_you_settings', []);

		$atts['checkout_form'] = $checkout_form;

		\WP_Ultimo\UI\Thank_You_Element::get_instance()->register_scripts();

		\WP_Ultimo\UI\Thank_You_Element::get_instance()->output($atts, $content);
	}

	/**
	 * Outputs the registration form.
	 *
	 * @since 2.0.0
	 *
	 * @param array       $atts Parameters of the block/shortcode.
	 * @param string|null $content The content inside the shortcode.
	 * @return void
	 */
	public function output_form($atts, $content = null) {

		$atts['step'] = wu_request('step', $atts['step']);

		$slug = $atts['slug'];

		$customer = wu_get_current_customer();

		$membership = WP_Ultimo()->currents->get_membership();

		/**
		 * Allow developers bypass the output and set a new one
		 *
		 * @param string|bool $bypass If we should bypass the checkout form or a string to return instead of the form.
		 * @param array       $atts   Parameters of the checkout block/shortcode.
		 */
		$bypass = apply_filters('wu_bypass_checkout_form', false, $atts);

		if ($bypass) {
			if (is_string($bypass)) {
				echo wp_kses_post($bypass);
			}
			return;
		}

		if ($customer && $membership && 'wu-finish-checkout' !== $slug) {
			$published_sites = $membership->get_published_sites();

			$pending_payment = $membership->get_last_pending_payment();

			/*
			 * Pending payment notice logic:
			 * - active/trialing with pending payment -> show notice AND block form
			 * - pending (abandoned checkout) with pending payment -> show notice
			 *   but do NOT block the form so user can pick a different plan.
			 *
			 * @since 2.4.13
			 */
			$membership_is_active_or_trialing = $membership->is_active()
				|| $membership->get_status() === Membership_Status::TRIALING;

			$membership_is_pending = $membership->get_status() === Membership_Status::PENDING;

			if ($pending_payment && ($membership_is_active_or_trialing || $membership_is_pending)) {
				/**
				 *  We are talking about membership with a pending payment
				 */
				echo '<p>';
				// Translators: Placeholder receives the customer display name
				printf(esc_html__('Hi %s. You have a pending payment for your membership!', 'ultimate-multisite'), esc_html($customer->get_display_name()));

				$payment_url = add_query_arg(
					[
						'payment' => $pending_payment->get_hash(),
					],
					wu_get_registration_url()
				);

				// Translators: The link to registration url with payment hash
				echo '<br>' . wp_kses_post(sprintf(__('Click <a href="%s">here</a> to pay.', 'ultimate-multisite'), esc_attr($payment_url)));

				echo '</p>';

				// Only block the form for active/trialing memberships.
				// For pending (abandoned) memberships, show the notice but let them continue.
				if ($membership_is_active_or_trialing) {
					return;
				}
			}

			$membership_blocked_forms = [
				'wu-add-new-site',
			];

			if ( ! $membership->is_active() && $membership->get_status() !== Membership_Status::TRIALING && in_array($atts['slug'], $membership_blocked_forms, true)) {

				// Translators: Placeholder receives the customer display name
				printf(esc_html__('Hi %s. You cannot take action on your membership while it is not active!', 'ultimate-multisite'), esc_html($customer->get_display_name()));

				if ($membership->get_status() === Membership_Status::PENDING && $customer->get_email_verification() === 'pending') {
					/**
					 * Enqueue thank you page scripts to handle resend email verification link
					 */
					wp_register_script('wu-thank-you', wu_get_asset('thank-you.js', 'js'), [], wu_get_version(), true);

					wp_localize_script(
						'wu-thank-you',
						'wu_thank_you',
						[
							'ajaxurl'         => admin_url('admin-ajax.php'),
							'resend_verification_email_nonce' => wp_create_nonce('wu_resend_verification_email_nonce'),
							'membership_hash' => $membership->get_hash(),
							'i18n'            => [
								'resending_verification_email' => __('Resending verification email...', 'ultimate-multisite'),
								'email_sent' => __('Verification email sent!', 'ultimate-multisite'),
							],
						]
					);

					wp_enqueue_script('wu-thank-you');

					echo '<p>' . esc_html__('Check your inbox and verify your email address.', 'ultimate-multisite') . '</p>';
					echo '<span class="wu-styling">';
					printf('<a href="#" class="wu-mr-2 wu-resend-verification-email wu-no-underline button button-primary">%s</a>', esc_html__('Resend verification email', 'ultimate-multisite'));
					echo '</span>';
				}

				return;
			}

			if ( ! wu_multiple_memberships_enabled() && $membership->is_active()) {
				/**
				 * Allow developers to add new form slugs to bypass this behaviour.
				 *
				 * @param array $slugs a list of form slugs to bypass.
				 */
				$allowed_forms = apply_filters(
					'wu_get_membership_allowed_forms',
					[
						'wu-checkout',
						'wu-add-new-site',
						'wu-register-domain',
					]
				);

				if ( ! in_array($slug, $allowed_forms, true) && ! wu_request('payment')) {
					printf('<p>%s</p>', esc_html__('You already have a membership!', 'ultimate-multisite'));

					if (isset($published_sites[0])) {
						printf(
							'<p><a class="wu-no-underline button button-primary" href="%s">%s</a><p>',
							esc_attr(get_admin_url($published_sites[0]->get_id(), 'admin.php?page=account')),
							esc_html__('Go to my account', 'ultimate-multisite')
						);
					}

					return;
				}
			}

			if ($membership->get_customer_id() !== $customer->get_id()) {
				printf('<p>%s</p>', esc_html__('You are not allowed to change this membership!', 'ultimate-multisite'));

				return;
			}

			/**
			 *  Now we filter the current membership for each membership_limitations
			 *  field in element atts to check if we can show the form, if not we show
			 *  a error message informing the user about and with buttons to allow
			 *  account upgrade and/or to buy a new membership.
			 */
			if (! empty($atts['membership_limitations'])) {
				$limits = $membership->get_limitations();

				foreach ($atts['membership_limitations'] as $limitation) {
					if ( ! method_exists($membership, "get_$limitation")) {
						continue;
					}

					$current_limit = $limits->{$limitation};

					$limit_max = $current_limit->is_enabled() ? $current_limit->get_limit() : PHP_INT_MAX;

					$limit_max = ! empty($limit_max) ? (int) $limit_max : 1;

					$used_limit = $membership->{"get_$limitation"}();

					$used_limit = is_array($used_limit) ? count($used_limit) : (int) $used_limit;

					if ($used_limit >= $limit_max) {

						// Translators: Placeholder receives the limit name
						echo '<p>' . sprintf(esc_html__('You reached your membership %s limit!', 'ultimate-multisite'), esc_html($limitation)) . '</p>';

						echo '<span class="wu-styling">';

						if (wu_multiple_memberships_enabled()) {
							printf(
								'<a class="wu-no-underline button button-primary wu-mr-2" href="%s">%s</a>',
								esc_url(wu_get_registration_url()),
								esc_html(__('Buy a new membership', 'ultimate-multisite'))
							);
						}

						if ('sites' !== $limitation || wu_get_setting('enable_multiple_sites')) {
							$update_link = '';

							$checkout_pages = \WP_Ultimo\Checkout\Checkout_Pages::get_instance();

							$update_url = $checkout_pages->get_page_url('update');

							if ($update_url) {
								$update_link = add_query_arg(
									[
										'membership' => $membership->get_hash(),
									],
									$update_url
								);
							} elseif (is_admin()) {
								$update_link = admin_url('admin.php?page=wu-checkout&membership=' . $membership->get_hash());
							} elseif (isset($published_sites[0])) {
								$update_link = get_admin_url($published_sites[0]->get_id(), 'admin.php?page=wu-checkout&membership=' . $membership->get_hash());
							}

							if ( ! empty($update_link)) {
								$button_text = __('Upgrade your account', 'ultimate-multisite');

								printf('<a class="wu-no-underline button button-primary wu-mr-2" href="%s">%s</a>', esc_attr($update_link), esc_html($button_text));
							}
						}

						echo '</span>';

						return;
					}
				}
			}
		} elseif ( ! $customer && 'wu-finish-checkout' === $slug) {
			echo '<p>';
			if (is_user_logged_in()) {
				esc_html_e('You need to be the account owner to complete this payment.', 'ultimate-multisite');
			} else {
				esc_html_e('You need to be logged in to complete a payment', 'ultimate-multisite');

				echo '<br>' . sprintf(
					// Translators: %s is replaced with <a href="{login_url}">here</a>
					esc_html__('Click %s to sign in.', 'ultimate-multisite'),
					'<a href="' . esc_attr(wp_login_url(wu_get_current_url())) . '">' .
					esc_html__('here', 'ultimate-multisite') .
					'</a>'
				);
			}

			echo '</p>';

			return;
		}

		$checkout_form = wu_get_checkout_form_by_slug($slug);

		if ( ! $checkout_form) {

			// translators: %s is the id of the form. e.g. main-form
			printf(esc_html__('Checkout form %s not found.', 'ultimate-multisite'), esc_html($slug));
			return;
		}

		if ($checkout_form->get_field_count() === 0) {

			// translators: %s is the id of the form. e.g. main-form
			printf(esc_html__('Checkout form %s contains no fields.', 'ultimate-multisite'), esc_html($slug));
			return;
		}

		if ( ! $checkout_form->is_active() || ! wu_get_setting('enable_registration', true)) {
			printf('<p>%s</p>', esc_html__('Registration is not available at this time.', 'ultimate-multisite'));
			return;
		}

		if ($checkout_form->has_country_lock()) {
			$geolocation = \WP_Ultimo\Geolocation::geolocate_ip('', true);

			if ( ! in_array($geolocation['country'], $checkout_form->get_allowed_countries(), true)) {
				printf('<p>%s</p>', esc_html__('Registration is closed for your location.', 'ultimate-multisite'));
				return;
			}
		}

		$checkout = \WP_Ultimo\Checkout\Checkout::get_instance();

		$checkout_form->get_steps_to_show();

		$this->steps = $checkout_form->get_steps_to_show();

		$step = $checkout_form->get_step($atts['step'], true);

		$this->step = $step ?: current($this->steps);

		$this->step = wp_parse_args(
			$this->step,
			[
				'classes' => '',
				'fields'  => [],
			]
		);

		$this->step_name = $this->step['id'] ?? '';

		/*
		 * Hack-y way to make signup available on the template.
		 */
		global $signup;

		$signup = new Mocked_Signup($this->step_name, $this->steps); // phpcs:ignore

		$this->signup = $signup;

		/*
		 * Load the checkout class with the parameters
		 * so we can access them inside the layouts.
		 */
		$checkout->checkout_form = $checkout_form;
		$checkout->steps         = $this->steps;
		$checkout->step          = $this->step;
		$checkout->step_name     = $this->step_name;
		$auto_submittable_field  = $checkout->contains_auto_submittable_field($this->step['fields']);

		$final_fields = wu_create_checkout_fields($this->step['fields']);

		/*
		 * Adds the product fields to keep them.
		 */
		$final_fields['products[]'] = [
			'type'      => 'hidden',
			'html_attr' => [
				'v-for'     => '(product, index) in unique_products',
				'v-model'   => 'products[index]',
				'v-bind:id' => '"products-" + index',
			],
		];

		$this->inject_inline_auto_submittable_field($auto_submittable_field);

		$final_fields = apply_filters('wu_checkout_form_final_fields', $final_fields, $this);

		wu_get_template(
			'checkout/form',
			[
				'step'               => $this->step,
				'step_name'          => $this->step_name,
				'checkout_form_name' => $atts['slug'],
				'errors'             => $checkout->errors,
				'display_title'      => $atts['display_title'],
				'final_fields'       => $final_fields,
			]
		);
	}

	/**
	 * Injects the auto-submittable field inline snippet.
	 *
	 * @since 2.0.11
	 *
	 * @param string $auto_submittable_field The auto-submittable field.
	 * @return void
	 */
	public function inject_inline_auto_submittable_field($auto_submittable_field): void {

		$callback = function () use ($auto_submittable_field) {

			wp_add_inline_script(
				'wu-checkout',
				sprintf(
					'

				/**
				 * Set the auto-submittable field, if one exists.
				 */
				window.wu_auto_submittable_field = %s;

			',
					wp_json_encode($auto_submittable_field)
				),
				'after'
			);
		};

		if (wu_is_block_theme() && ! is_admin()) {
			add_action('wu_checkout_scripts', $callback, 100);
		} else {
			call_user_func($callback);
		}
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
	 * @param array       $atts Parameters of the block/shortcode.
	 * @param string|null $content The content inside the shortcode.
	 * @return void
	 */
	public function output($atts, $content = null) {

		if (apply_filters('wu_checkout_skip_output', false, $atts, $content, $this)) {
			return;
		}

		if ($this->should_defer_checkout($atts)) {
			$this->output_deferred_placeholder($atts);

			return;
		}

		$this->send_checkout_nocache_headers($atts);

		if (wu_is_update_page()) {
			$atts = [
				'slug'          => apply_filters('wu_membership_update_form', 'wu-checkout'),
				'step'          => false,
				'display_title' => false,
			];
		}

		if (wu_is_new_site_page()) {
			$atts = [
				'slug'                   => apply_filters('wu_membership_new_site_form', 'wu-add-new-site'),
				'step'                   => false,
				'display_title'          => false,
				'membership_limitations' => ['sites'],
			];
		}

		if ($this->is_thank_you_page()) {
			$this->output_thank_you($atts, $content);
			return;
		}

		/**
		 * Allow developers to add new update form slugs.
		 *
		 * @param array $slugs a list of form slugs to bypass.
		 */
		$update_forms = apply_filters(
			'wu_membership_update_forms',
			[
				'wu-checkout',
			]
		);

		if ( ! in_array($atts['slug'], $update_forms, true) && (wu_request('payment') || wu_request('payment_id'))) {
			$atts = [
				'slug'          => 'wu-finish-checkout',
				'step'          => false,
				'display_title' => false,
			];
		}

		if (wu_request('wu_form') && in_array(wu_request('wu_form'), $update_forms, true)) {
			$atts = [
				'slug'          => wu_request('wu_form'),
				'step'          => false,
				'display_title' => false,
			];
		}

		$this->output_form($atts, $content);
	}
}
