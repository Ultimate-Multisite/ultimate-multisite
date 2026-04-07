<?php
/**
 * The Site model.
 *
 * @package WP_Ultimo
 * @subpackage Models
 * @since 2.0.0
 */

namespace WP_Ultimo\Models;

use Psr\Log\LogLevel;
use WP_Ultimo\Database\Sites\Site_Type;
use WP_Ultimo\Models\Interfaces\Limitable;
use WP_Ultimo\Models\Interfaces\Notable;
use WP_Ultimo\UI\Template_Previewer;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Site model class. Implements the Base Model.
 *
 * @since 2.0.0
 */
class Site extends Base_Model implements Limitable, Notable {

	use Traits\Limitable;
	use \WP_Ultimo\Traits\WP_Ultimo_Site_Deprecated;
	use Traits\Notable;

	/**
	 * Meta key for categories.
	 */
	const META_CATEGORIES = 'wu_categories';

	/**
	 * Meta key for featured image ID.
	 */
	const META_FEATURED_IMAGE_ID = 'wu_featured_image_id';

	/**
	 * Meta key for active status.
	 */
	const META_ACTIVE = 'wu_active';

	/**
	 * Meta key for customer ID.
	 */
	const META_CUSTOMER_ID = 'wu_customer_id';

	/**
	 * Meta key for membership ID.
	 */
	const META_MEMBERSHIP_ID = 'wu_membership_id';

	/**
	 * Meta key for template ID.
	 */
	const META_TEMPLATE_ID = 'wu_template_id';

	/**
	 * Meta key for site type.
	 */
	const META_TYPE = 'wu_type';

	/**
	 * Meta key for transient status.
	 */
	const META_TRANSIENT = 'wu_transient';

	/**
	 * Meta key for demo expiration timestamp.
	 *
	 * @since 2.5.0
	 */
	const META_DEMO_EXPIRES_AT = 'wu_demo_expires_at';

	/**  DEFAULT WP_SITE COLUMNS */

	/**
	 * Title of the site.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $title;

	/**
	 * The site description.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $description;

	/**
	 * Blog ID. Should be accessed via id.
	 *
	 * @since 2.0.0
	 * @var int
	 */
	protected $blog_id;

	/**
	 * Network ID for this site.
	 *
	 * @since 2.0.0
	 * @var int
	 */
	protected $site_id = 1;

	/**
	 * Domain name used by this site.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $domain;

	/**
	 * Path of the site. Used when in sub-directory mode.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $path;

	/**
	 * Alias for WP CLI support.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $site_path;

	/**
	 * Date when the site was registered.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $registered;

	/**
	 * Date of the last update on this site.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $last_updated;

	/**
	 * If the site is being published.
	 *
	 * @since 2.0.11
	 * @var string
	 */
	protected $is_publishing;

	/**
	 * Is this a active site?
	 *
	 * @since 2.0.0
	 * @var bool
	 */
	protected $active;

	/**
	 * Is this a public site?
	 *
	 * @since 2.0.0
	 * @var bool
	 */
	protected $public = true;

	/**
	 * Is this an archived site?
	 *
	 * @since 2.0.0
	 * @var bool
	 */
	protected $archived;

	/**
	 * Is this a site with mature content?
	 *
	 * @since 2.0.0
	 * @var bool
	 */
	protected $mature;

	/**
	 * Is this an spam site?
	 *
	 * @since 2.0.0
	 * @var bool
	 */
	protected $spam;

	/**
	 * Is this site deleted?
	 *
	 * @since 2.0.0
	 * @var bool
	 */
	protected $deleted;

	/**
	 * ID of the language being used on this site.
	 *
	 * @since 2.0.0
	 * @var int
	 */
	protected $lang_id;

	/**
	 * Holds the ID of the customer that owns this site.
	 *
	 * @since 2.0.0
	 * @var int
	 */
	protected $customer_id;

	/**
	 * Holds the ID of the membership associated with this site, if any.
	 *
	 * @since 2.0.0
	 * @var int
	 */
	protected $membership_id;

	/**
	 * Local membership cache.
	 *
	 * @since 2.0.0
	 * @var null|\WP_Ultimo\Models\Membership
	 */
	private $membership;

	/**
	 * The site template id used to create this site.
	 *
	 * @since 2.0.0
	 * @var int
	 */
	protected $template_id;

	/**
	 * Duplication arguments.
	 *
	 * @since 2.0.0
	 * @var array
	 */
	private $duplication_arguments = [];

	/**
	 * The site type of this particular site.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $type;

	/**
	 * ID of the featured image being used on this product.
	 *
	 * @since 2.0.0
	 * @var int
	 */
	protected $featured_image_id;

	/**
	 * Categories
	 *
	 * @since 2.0.0
	 * @var int
	 */
	protected $categories;

	/**
	 * Query Class to the static query methods.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $query_class = \WP_Ultimo\Database\Sites\Site_Query::class;

	/**
	 * Keeps form date from the signup form.
	 *
	 * @since 2.0.0
	 * @var array
	 */
	protected $transient;

	/**
	 * Keeps signup options for the site.
	 *
	 * @since 2.0.0
	 * @var null|array
	 */
	protected $signup_options;

	/**
	 * Keeps signup meta for the site.
	 *
	 * @since 2.0.0
	 * @var null|array
	 */
	protected $signup_meta;

	/**
	 * Set the validation rules for this particular model.
	 *
	 * To see how to setup rules, check the documentation of the
	 * validation library we are using: https://github.com/rakit/validation
	 *
	 * @since 2.0.0
	 * @link https://github.com/rakit/validation
	 * @return array
	 */
	public function validation_rules() {

		$date = wu_get_current_time('mysql', true);

		$site_types = new \WP_Ultimo\Database\Sites\Site_Type();

		$site_types = implode(',', array_values($site_types->get_options()));

		return [
			'categories'        => 'default:',
			'featured_image_id' => 'integer|default:',
			// site_id is the network id; defaults to 1 (the main network) in
			// the model, so it's optional for a regular WordPress subsite.
			'site_id'           => 'integer|default:1',
			'title'             => 'required',
			'name'              => 'required',
			// description is optional metadata. A regular WordPress subsite
			// doesn't have one at the network level — it lives in
			// blogdescription per blog and can be set later.
			'description'       => 'default:',
			'domain'            => 'domain',
			'path'              => 'required|default:',
			'registered'        => "default:{$date}",
			'last_updated'      => 'default:',
			'public'            => 'boolean|default:1',
			'archived'          => 'boolean|default:0',
			'mature'            => 'boolean|default:0',
			'spam'              => 'boolean|default:0',
			'deleted'           => 'boolean|default:0',
			'is_publishing'     => 'boolean|default:0',
			'land_id'           => 'integer|default:',
			// customer_id and membership_id are WaaS-specific. A vanilla
			// WordPress subsite (type=default) does not need a paying
			// customer or a membership. Keep the foreign-key existence
			// check so customer-owned sites still validate when supplied.
			'customer_id'       => 'integer|default:|exists:\WP_Ultimo\Models\Customer,id',
			'membership_id'     => 'integer|default:|exists:\WP_Ultimo\Models\Membership,id',
			'template_id'       => 'integer|default:',
			'type'              => "required|in:{$site_types}",
			'signup_options'    => 'default:',
		];
	}

	/**
	 * Get the visits for this particular sites.
	 *
	 * @since 2.0.0
	 * @return int
	 */
	public function get_visits_count() {

		$visits_manager = new \WP_Ultimo\Objects\Visits($this->get_id());

		return $visits_manager->get_visit_total('first day of this month', 'last day of this month');
	}

	/**
	 * Set the categories for the site.
	 *
	 * @since 2.0.0
	 *
	 * @param array $categories The categories this site belongs to.
	 * @return void
	 */
	public function set_categories($categories): void {

		$this->meta[ self::META_CATEGORIES ] = $categories;

		$this->categories = $categories;
	}

	/**
	 * Get the list of categories.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_categories() {

		if (null === $this->categories) {
			$this->categories = $this->get_meta(self::META_CATEGORIES, []);
		}

		if ( ! is_array($this->categories)) {
			return [];
		}

		return array_filter($this->categories);
	}

	/**
	 * Get featured image ID.
	 *
	 * @since 2.0.0
	 * @return int
	 */
	public function get_featured_image_id() {

		if (null === $this->featured_image_id) {
			return $this->get_meta(self::META_FEATURED_IMAGE_ID);
		}

		return $this->featured_image_id;
	}

	/**
	 * Get featured image url.
	 *
	 * @since 2.0.0
	 * @param string $size The size of the image to retrieve.
	 * @return string
	 */
	public function get_featured_image($size = 'wu-thumb-medium') {

		if ($this->get_type() === 'external') {
			return wu_get_asset('wp-ultimo-screenshot.webp');
		}

		is_multisite() && switch_to_blog(wu_get_main_site_id());

		$size = apply_filters('wu_site_featured_image_size', $size, $this);

		$image_attributes = wp_get_attachment_image_src($this->get_featured_image_id(), $size);

		is_multisite() && restore_current_blog();

		if ($image_attributes) {
			return $image_attributes[0];
		}

		return wu_get_asset('site-placeholder-image.webp', 'img');
	}

	/**
	 * Set featured image ID.
	 *
	 * @since 2.0.0
	 * @param int $image_id The ID of the feature image of the site.
	 * @return void
	 */
	public function set_featured_image_id($image_id): void {

		$this->meta[ self::META_FEATURED_IMAGE_ID ] = $image_id;

		$this->featured_image_id = $image_id;
	}

	/**
	 * Get the preview URL.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_preview_url() {

		return Template_Previewer::get_instance()->get_preview_url($this->get_id());
	}

	/**
	 * Get the preview URL attrs.
	 *
	 * @since 2.0.0
	 */
	public function get_preview_url_attrs(): void {

		if ( ! Template_Previewer::get_instance()->get_setting('enabled', true)) {
			printf('href="%s" target="_blank"', esc_attr($this->get_active_site_url()));
			return;
		}

		printf('onclick="window.open(\'%s\')"', esc_attr(add_query_arg('open', 1, $this->get_preview_url())));
	}

	/**
	 * Get blog ID. Should be accessed via id.
	 *
	 * @since 2.0.0
	 * @return int
	 */
	public function get_id() {

		return $this->get_blog_id();
	}

	/**
	 * Get blog ID. Should be accessed via id..
	 *
	 * @since 2.0.0
	 * @return int
	 */
	public function get_blog_id() {

		return (int) $this->blog_id;
	}

	/**
	 * Set blog ID. Should be accessed via id..
	 *
	 * @since 2.0.0
	 * @param int $blog_id The blog ID. Should be accessed via id.
	 * @return void
	 */
	public function set_blog_id($blog_id): void {

		$this->blog_id = $blog_id;
	}

	/**
	 * Get network ID for this site..
	 *
	 * @since 2.0.0
	 * @return int
	 */
	public function get_site_id() {

		return $this->site_id;
	}

	/**
	 * Set network ID for this site..
	 *
	 * @since 2.0.0
	 * @param int $site_id The network ID for this site.
	 * @return void
	 */
	public function set_site_id($site_id): void {

		$this->site_id = $site_id;
	}

	/**
	 * Get title of the site..
	 *
	 * @since 2.0.0
	 */
	public function get_title(): string {

		return stripslashes($this->title);
	}

	/**
	 * Set title of the site.
	 *
	 * @since 2.0.0
	 *
	 * @param string $title The site title.
	 * @return void
	 */
	public function set_title($title): void {

		$this->title = sanitize_text_field($title);
	}

	/**
	 * Alias to get name.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_name() {

		return $this->get_title();
	}

	/**
	 * Alias to set title.
	 *
	 * @since 2.0.0
	 * @param string $title The site name.
	 * @return void
	 */
	public function set_name($title): void {

		$this->set_title($title);
	}

	/**
	 * Gets the site description.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_description() {

		if ($this->description) {
			return $this->description;
		}

		return get_blog_option($this->get_id(), 'blogdescription');
	}

	/**
	 * Sets the site description.
	 *
	 * @todo This is not yet persistent.
	 *
	 * @since 2.0.0
	 * @param string $description A description for the site, usually a short text.
	 * @return void
	 */
	public function set_description($description): void {

		$this->description = $description;
	}

	/**
	 * Get domain name used by this site..
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_domain() {

		return $this->domain;
	}

	/**
	 * Set domain name used by this site..
	 *
	 * @since 2.0.0
	 * @param string $domain Full hostname for the site, no protocol. On a subdomain multisite install supply the full subdomain you want (e.g. "blog.example.com"). On a subdirectory install leave empty to inherit the network root domain. wu_create_site() will auto-derive this from `path` on subdomain installs when you only supply a slug.
	 * @return void
	 */
	public function set_domain($domain): void {

		$this->domain = $domain;
	}

	/**
	 * Get path of the site. Used when in sub-directory mode..
	 *
	 * @since 2.0.0
	 */
	public function get_path(): string {

		return $this->path;
	}

	/**
	 * Set path of the site. Used when in sub-directory mode..
	 *
	 * @since 2.0.0
	 * @param string $path URL path for the site. On a subdirectory multisite install this is the URL prefix (e.g. "/blog/"). On a subdomain install supply just the slug (e.g. "blog") and wu_create_site() will convert it into the full subdomain "blog.<network-domain>" automatically.
	 * @return void
	 */
	public function set_path($path): void {

		$this->path = $path;
	}

	/**
	 * Get date when the site was registered..
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_registered() {

		return $this->registered;
	}

	/**
	 * Proxy for a common API.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_date_registered() {

		return $this->get_registered();
	}

	/**
	 * Set date when the site was registered..
	 *
	 * @since 2.0.0
	 * @param string $registered Date when the site was registered.
	 * @return void
	 */
	public function set_registered($registered): void {

		$this->registered = $registered;
	}

	/**
	 * Get date of the last update on this site.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_last_updated() {

		return $this->last_updated;
	}

	/**
	 * Proxy to last_updated.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_date_modified() {

		return $this->get_last_updated();
	}

	/**
	 * Set date of the last update on this site..
	 *
	 * @since 2.0.0
	 * @param string $last_updated Date of the last update on this site.
	 * @return void
	 */
	public function set_last_updated($last_updated): void {

		$this->last_updated = $last_updated;
	}

	/**
	 * Get if the site is being published.
	 *
	 * @since 2.0.11
	 * @return int
	 */
	public function is_publishing() {

		return $this->is_publishing;
	}

	/**
	 * Set if the site is being published.
	 *
	 * @since 2.0.11
	 * @param int $publishing Holds the ID of the customer that owns this site.
	 * @return void
	 */
	public function set_publishing($publishing): void {

		$this->is_publishing = $publishing;
	}

	/**
	 * Get holds the ID of the customer that owns this site.
	 *
	 * @since 2.0.0
	 * @return int
	 */
	public function is_active() {

		if (null === $this->active) {
			$this->active = $this->get_meta(self::META_ACTIVE, true);
		}

		return $this->active;
	}

	/**
	 * Set holds the ID of the customer that owns this site..
	 *
	 * @since 2.0.0
	 * @param int $active Holds the ID of the customer that owns this site.
	 * @return void
	 */
	public function set_active($active): void {

		$this->meta[ self::META_ACTIVE ] = $active;

		$this->active = $active;
	}

	/**
	 * Get is this a public site?.
	 *
	 * @since 2.0.0
	 * @return bool
	 */
	public function get_public() {

		return $this->public;
	}

	/**
	 * Set is this a public site?.
	 *
	 * @since 2.0.0
	 * @param bool $is_public Set true if this site is a public one, false if not.
	 * @return void
	 */
	public function set_public($is_public): void {

		$this->public = $is_public;
	}

	/**
	 * Get is this an archived site.
	 *
	 * @since 2.0.0
	 * @return bool
	 */
	public function is_archived() {

		return $this->archived;
	}

	/**
	 * Set is this an archived site?.
	 *
	 * @since 2.0.0
	 * @param bool $archived Is this an archived site.
	 * @return void
	 */
	public function set_archived($archived): void {

		$this->archived = $archived;
	}

	/**
	 * Get is this a site with mature content.
	 *
	 * @since 2.0.0
	 * @return bool
	 */
	public function is_mature() {

		return $this->mature;
	}

	/**
	 * Set is this a site with mature content?.
	 *
	 * @since 2.0.0
	 * @param bool $mature Is this a site with mature content.
	 * @return void
	 */
	public function set_mature($mature): void {

		$this->mature = $mature;
	}

	/**
	 * Get is this an spam site.
	 *
	 * @since 2.0.0
	 * @return bool
	 */
	public function is_spam() {

		return $this->spam;
	}

	/**
	 * Set is this an spam site?.
	 *
	 * @since 2.0.0
	 * @param bool $spam Is this an spam site.
	 * @return void
	 */
	public function set_spam($spam): void {

		$this->spam = $spam;
	}

	/**
	 * Get is this site deleted.
	 *
	 * @since 2.0.0
	 * @return bool
	 */
	public function is_deleted() {

		return $this->deleted;
	}

	/**
	 * Set is this site deleted?.
	 *
	 * @since 2.0.0
	 * @param bool $deleted Is this site deleted.
	 * @return void
	 */
	public function set_deleted($deleted): void {

		$this->deleted = $deleted;
	}

	/**
	 * Get iD of the language being used on this site.
	 *
	 * @since 2.0.0
	 * @return int
	 */
	public function get_lang_id() {

		return $this->lang_id;
	}

	/**
	 * Set iD of the language being used on this site.
	 *
	 * @since 2.0.0
	 * @param int $lang_id The ID of the language being used on this site.
	 * @return void
	 */
	public function set_lang_id($lang_id): void {

		$this->lang_id = $lang_id;
	}

	/**
	 * Get holds the ID of the customer that owns this site..
	 *
	 * @since 2.0.0
	 * @return int
	 */
	public function get_customer_id() {

		if (null === $this->customer_id) {
			$this->customer_id = $this->get_meta(self::META_CUSTOMER_ID);
		}

		return (int) $this->customer_id;
	}

	/**
	 * Set holds the ID of the customer that owns this site..
	 *
	 * @since 2.0.0
	 * @param int $customer_id The ID of the customer that owns this site.
	 * @return void
	 */
	public function set_customer_id($customer_id): void {

		$this->meta[ self::META_CUSTOMER_ID ] = $customer_id;

		$this->customer_id = $customer_id;
	}

	/**
	 * Gets the customer object associated with this membership.
	 *
	 * @since 2.0.0
	 * @return \WP_Ultimo\Models\Customer|false;
	 */
	public function get_customer() {

		return wu_get_customer($this->get_customer_id());
	}

	/**
	 * Checks if a given customer should have access to this site options.
	 *
	 * @since 2.0.0
	 *
	 * @param int $customer_id The customer id to check.
	 * @return boolean
	 */
	public function is_customer_allowed($customer_id = false) {

		if (current_user_can('manage_network')) {
			return true;
		}

		if ( ! $customer_id) {
			$customer = WP_Ultimo()->currents->get_customer();

			$customer_id = $customer ? $customer->get_id() : 0;
		}

		$allowed = absint($customer_id) === absint($this->get_customer_id());

		return apply_filters('wu_site_is_customer_allowed', $allowed, $customer_id, $this);
	}

	/**
	 * Get holds the ID of the membership associated with this site, if any..
	 *
	 * @since 2.0.0
	 * @return int
	 */
	public function get_membership_id() {

		if (null === $this->membership_id) {
			$this->membership_id = $this->get_meta(self::META_MEMBERSHIP_ID);
		}

		return $this->membership_id;
	}

	/**
	 * Set holds the ID of the membership associated with this site, if any..
	 *
	 * @since 2.0.0
	 * @param int $membership_id The ID of the membership associated with this site, if any.
	 * @return void
	 */
	public function set_membership_id($membership_id): void {

		$this->meta[ self::META_MEMBERSHIP_ID ] = $membership_id;

		$this->membership_id = $membership_id;
	}

	/**
	 * Checks if this site has a membership.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function has_membership() {

		return ! empty($this->get_membership());
	}

	/**
	 * Checks if the site has a product.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function has_product() {

		return $this->has_membership() && $this->get_membership()->has_plan();
	}

	/**
	 * Gets the membership object associated with this membership.
	 *
	 * @since 2.0.0
	 * @return \WP_Ultimo\Models\Membership|false;
	 */
	public function get_membership() {

		if (null !== $this->membership) {
			return $this->membership;
		}

		if (function_exists('wu_get_membership')) {
			$this->membership = wu_get_membership($this->get_membership_id());

			return $this->membership;
		}

		global $wpdb;

		$table_name = "{$wpdb->base_prefix}wu_memberships";

		$membership_id = $this->get_membership_id();

		if ( ! $membership_id) {
			return false;
		}

		$query = $wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d LIMIT 1", $membership_id); // phpcs:ignore

		$results = $wpdb->get_row($query); // phpcs:ignore

		if ( ! $results) {
			return false;
		}

		$this->membership = new \WP_Ultimo\Models\Membership($results);

		return $this->membership;
	}

	/**
	 * Returns the plan that created this site.
	 *
	 * @since 2.0.0
	 * @return \WP_Ultimo\Models\Product|false
	 */
	public function get_plan() {

		if ($this->has_membership()) {
			return $this->get_membership()->get_plan();
		}

		return false;
	}

	/**
	 * Get template ID.
	 *
	 * @since 2.0.0
	 * @return int|bool
	 */
	public function get_template_id() {

		if (null === $this->template_id) {
			$this->template_id = $this->get_meta(self::META_TEMPLATE_ID);
		}

		return $this->template_id;
	}

	/**
	 * Set the template ID.
	 *
	 * @since 2.0.0
	 * @param int $template_id The ID of the templated used to create this site.
	 * @return void
	 */
	public function set_template_id($template_id): void {

		$this->meta[ self::META_TEMPLATE_ID ] = absint($template_id);

		$this->template_id = $template_id;
	}

	/**
	 * Gets the site object associated with this membership.
	 *
	 * @since 2.0.0
	 * @return \WP_Ultimo\Models\Site|false;
	 */
	public function get_template() {

		return wu_get_site($this->get_template_id());
	}

	/**
	 * Returns the default duplication arguments.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	protected function get_default_duplication_arguments() {

		return [
			'keep_users' => true,
			'copy_files' => true,
			'public'     => true,
		];
	}

	/**
	 * Convert the Ultimo instance to a WP_Site.
	 *
	 * @since 2.0.11
	 * @return \WP_Site
	 */
	public function to_wp_site() {

		return get_site($this->get_id());
	}

	/**
	 * Get duplication arguments..
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_duplication_arguments() {

		$args = wp_parse_args($this->duplication_arguments, $this->get_default_duplication_arguments());

		return $args;
	}

	/**
	 * Set duplication arguments..
	 *
	 * @since 2.0.0
	 * @param array $duplication_arguments Duplication arguments.
	 * @return void
	 */
	public function set_duplication_arguments($duplication_arguments): void {

		$this->duplication_arguments = $duplication_arguments;
	}

	/**
	 * Get the site type of this particular site..
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_type() {

		if ($this->get_id() && is_main_site($this->get_id())) {
			return 'main';
		}

		if (null === $this->type) {
			$type = $this->get_meta(self::META_TYPE);

			$this->type = $type ?: 'default';
		}

		return $this->type;
	}

	/**
	 * Set the site type of this particular site.
	 *
	 * @since 2.0.0
	 * @param string $type The type of this particular site. Can be default, site_template, customer_owned, pending, external, main or other values added by third-party add-ons.
	 * @options \WP_Ultimo\Database\Sites\Site_Type
	 * @return void
	 */
	public function set_type($type): void {

		$this->meta = (array) $this->meta;

		$this->meta[ self::META_TYPE ] = $type;

		$this->type = $type;
	}

	/**
	 * Get the primary mapped domain for this site.
	 *
	 * @since 2.0.0
	 * @return \WP_Ultimo\Models\Domain|false
	 */
	public function get_primary_mapped_domain() {

		if ( ! function_exists('wu_get_domains')) {
			return false;
		}

		$domains = wu_get_domains(
			[
				'primary_domain' => true,
				'blog_id'        => $this->get_id(),
				'stage__not_in'  => \WP_Ultimo\Models\Domain::INACTIVE_STAGES,
				'number'         => 1,
			]
		);

		return empty($domains) ? false : $domains[0];
	}

	/**
	 * Returns the active site URL, which can be a mapped domain.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_active_site_url() {

		if ( ! $this->get_id()) {
			return $this->get_site_url();
		}

		$domain = $this->get_primary_mapped_domain();

		if ($domain) {
			return $domain->get_url();
		}

		return $this->get_site_url();
	}

	/**
	 * Returns the original URL for the blog.
	 *
	 * This is useful when we need to know the original URL, without
	 * mapping applied.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_site_url() {

		$url = set_url_scheme(esc_url(sprintf($this->get_domain() . '/' . trim($this->get_path(), '/'))));

		return $url;
	}

	/**
	 * Checks if this model was already saved to the database.
	 *
	 * @since 2.0.0
	 * @return bool
	 */
	public function exists() {

		return ! empty($this->blog_id);
	}

	/**
	 * Override te constructor due to this being a native table.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed $object_model Object containing the parameters.
	 */
	public function __construct($object_model = null) {

		parent::__construct($object_model);

		if (is_array($object_model)) {
			$object_model = (object) $object_model;
		}

		$details = get_blog_details($this->get_blog_id());

		if ($details && null === $this->title) {
			$this->set_title($details->blogname);
		}

		/*
		 * Quick fix for WP CLI, since it uses the --path arg to do other things.
		 */
		if ( ! $this->path && is_object($object_model) && isset($object_model->site_path)) {
			$this->path = $object_model->site_path;
		}

		$object_model = (object) $object_model;
	}

	/**
	 * Gets the form data saved at the time of the site creation.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_transient() {

		if (null === $this->transient) {
			$this->transient = $this->get_meta(self::META_TRANSIENT);
		}

		return $this->transient;
	}

	/**
	 * Holds the form data at the time of registration.
	 *
	 * @since 2.0.0
	 * @param array $transient Form data.
	 * @return void
	 */
	public function set_transient($transient): void {

		$this->meta[ self::META_TRANSIENT ] = $transient;

		$this->transient = $transient;
	}

	/**
	 * Get signup options for the site.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_signup_options() {

		return is_array($this->signup_options) ? $this->signup_options : [];
	}

	/**
	 * Set signup options for the site.
	 *
	 * @since 2.0.0
	 * @param array $signup_options Keeps signup options for the site.
	 * @return void
	 */
	public function set_signup_options($signup_options): void {

		$this->signup_options = $signup_options;
	}

	/**
	 * Get signup meta for the site.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_signup_meta() {

		return is_array($this->signup_meta) ? $this->signup_meta : [];
	}

	/**
	 * Set signup meta for the site.
	 *
	 * @since 2.0.0
	 * @param array $signup_meta Keeps signup meta for the site.
	 * @return void
	 */
	public function set_signup_meta($signup_meta): void {

		$this->signup_meta = $signup_meta;
	}

	/**
	 * Returns the Label for a given type.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_type_label() {

		$type = new Site_Type($this->get_type());

		return $type->get_label();
	}

	/**
	 * Gets the classes for a given class.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_type_class() {

		$type = new Site_Type($this->get_type());

		return $type->get_classes();
	}

	/**
	 * Check if this is a demo site.
	 *
	 * @since 2.5.0
	 * @return bool
	 */
	public function is_demo(): bool {

		return $this->get_type() === Site_Type::DEMO;
	}

	/**
	 * Check if this demo site is configured to stay until the customer goes live.
	 *
	 * Returns true when the associated plan product has demo_behavior = 'keep_until_live'.
	 * In this mode, the site has no expiration timer and the frontend is blocked
	 * until the customer explicitly activates the site.
	 *
	 * @since 2.5.0
	 * @return bool
	 */
	public function is_keep_until_live(): bool {

		if ( ! $this->is_demo()) {
			return false;
		}

		$plan = $this->get_plan();

		return $plan && $plan->is_keep_until_live();
	}

	/**
	 * Get the demo expiration date/time.
	 *
	 * @since 2.5.0
	 * @return string|null MySQL datetime string or null if not set.
	 */
	public function get_demo_expires_at(): ?string {

		$expires_at = $this->get_meta(self::META_DEMO_EXPIRES_AT);

		return $expires_at ?: null;
	}

	/**
	 * Set the demo expiration date/time.
	 *
	 * @since 2.5.0
	 *
	 * @param string $expires_at MySQL datetime string for when the demo expires.
	 * @return void
	 */
	public function set_demo_expires_at(string $expires_at): void {

		$this->update_meta(self::META_DEMO_EXPIRES_AT, $expires_at);
	}

	/**
	 * Check if this demo site has expired.
	 *
	 * @since 2.5.0
	 * @return bool True if expired, false if still active or not a demo.
	 */
	public function is_demo_expired(): bool {

		if ( ! $this->is_demo()) {
			return false;
		}

		$expires_at = $this->get_demo_expires_at();

		if (empty($expires_at)) {
			return false;
		}

		return $expires_at <= wu_get_current_time('mysql', true);
	}

	/**
	 * Calculate and set demo expiration based on settings.
	 *
	 * This method calculates the expiration time using the global
	 * demo_duration and demo_duration_unit settings.
	 *
	 * @since 2.5.0
	 *
	 * @param int|null    $duration      Optional custom duration. Defaults to settings value.
	 * @param string|null $duration_unit Optional custom unit (hour, day, week). Defaults to settings value.
	 * @return string The calculated expiration datetime.
	 */
	public function calculate_demo_expiration(?int $duration = null, ?string $duration_unit = null): string {

		$duration      = $duration ?? (int) wu_get_setting('demo_duration', 2);
		$duration_unit = $duration_unit ?? wu_get_setting('demo_duration_unit', 'hour');

		// Convert to seconds.
		$seconds_map = [
			'hour' => HOUR_IN_SECONDS,
			'day'  => DAY_IN_SECONDS,
			'week' => WEEK_IN_SECONDS,
		];

		$multiplier = $seconds_map[ $duration_unit ] ?? HOUR_IN_SECONDS;
		$expires_at = gmdate('Y-m-d H:i:s', time() + ($duration * $multiplier));

		return $expires_at;
	}

	/**
	 * Get time remaining until demo expiration.
	 *
	 * @since 2.5.0
	 * @return int|null Seconds remaining, or null if not a demo or no expiration set.
	 */
	public function get_demo_time_remaining(): ?int {

		if ( ! $this->is_demo()) {
			return null;
		}

		$expires_at = $this->get_demo_expires_at();

		if (empty($expires_at)) {
			return null;
		}

		$remaining = strtotime($expires_at) - time();

		return max(0, $remaining);
	}

	/**
	 * Get human-readable time remaining for demo.
	 *
	 * @since 2.5.0
	 * @return string|null Human-readable time string, or null if not applicable.
	 */
	public function get_demo_time_remaining_human(): ?string {

		$remaining = $this->get_demo_time_remaining();

		if (null === $remaining) {
			return null;
		}

		if ($remaining <= 0) {
			return __('Expired', 'ultimate-multisite');
		}

		return human_time_diff(time(), time() + $remaining);
	}

	/**
	 * Adds magic methods to return options.
	 *
	 * @since 2.0.0
	 *
	 * @param string $name Method name.
	 * @param array  $args List of arguments.
	 * @throws \BadMethodCallException Throws exception when method is not found.
	 * @return mixed
	 */
	public function __call($name, $args) {

		if (str_contains($name, 'get_option_')) {
			$option = str_replace('get_option_', '', $name);

			return get_blog_option($this->get_id(), $option, false);
		}

		throw new \BadMethodCallException(esc_html(self::class . "::$name()"));
	}

	/**
	 * Checks if this is the primary site of the customer.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function is_customer_primary_site() {

		$customer = $this->get_customer();

		if ( ! $customer) {
			return false;
		}

		$user_id = $customer->get_user_id();

		if ( ! $user_id) {
			return false;
		}

		$primary_site_id = get_user_option('primary_blog', $user_id);

		return absint($primary_site_id) === absint($this->get_id());
	}

	/**
	 * Delete the model from the database.
	 *
	 * @since 2.0.0
	 * @return \WP_Error|bool
	 */
	public function delete() {

		if ( ! $this->get_id()) {
			return new \WP_Error("wu_{$this->model}_delete_unsaved_item", __('Item not found.', 'ultimate-multisite'));
		}

		/**
		 * Fires after an object is stored into the database.
		 *
		 * @since 2.0.0
		 *
		 * @param Base_Model $this The object instance.
		 */
		do_action("wu_{$this->model}_pre_delete", $this); // @phpstan-ignore-line

		try {
			$result = (bool) wp_delete_site($this->get_id());
		} catch (\Throwable $e) {
			$result = false;

			wu_log_add('fatal-error', $e->getMessage(), LogLevel::ERROR);
		}

		/**
		 * Fires after an object is stored into the database.
		 *
		 * @since 2.0.0
		 *
		 * @param bool       $result True if the object was successfully deleted.
		 * @param Base_Model $model   The object instance.
		 */
		do_action("wu_{$this->model}_post_delete", $result, $this);

		wp_cache_flush();

		return $result;
	}

	/**
	 * Replaces meta fields with the data collected during signup.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	protected function handles_existing_search_and_replace() {

		$transient = $this->get_transient();

		if ($transient) {
			add_filter(
				'wu_search_and_replace_on_duplication',
				function ($replace_list, $from_site_id, $to_site_id) use ($transient) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter

					foreach ($transient as $transient_key => $transient_value) {
						$key = sprintf('{{%s}}', $transient_key);

						$replace_list[ $key ] = $transient_value;
					}

					return $replace_list;
				},
				9,
				3
			);
		}
	}

	/**
	 * Transform the object into an assoc array.
	 *
	 * Overrides Base_Model::to_array() to ensure lazy-loaded meta properties
	 * are populated before serialization. Without this, properties such as
	 * customer_id, membership_id, type, active, etc. remain null in REST API
	 * responses because get_object_vars() captures the raw (unloaded) values.
	 *
	 * @since 2.0.11
	 * @return array
	 */
	public function to_array() {

		// Only trigger lazy-loading when the model has been persisted (has an ID).
		// For unsaved models (during validate/save), meta is not available and the
		// getters return default values (e.g. false) that can fail validation rules
		// like 'integer', preventing the model from being saved at all.
		if ($this->get_id()) {
			$this->get_customer_id();
			$this->get_membership_id();
			$this->get_template_id();
			$this->get_featured_image_id();
			$this->get_categories();
			$this->get_type();
			$this->is_active();
			// Lazy-load description from blogdescription option so it is not null
			// in REST API responses (get_description() calls get_blog_option()).
			$this->description = $this->get_description();
		}

		$array = parent::to_array();

		// Expose blog_id as id so callers get the correct non-zero value.
		$array['id'] = $this->get_id();

		// Description is lazy-loaded from wp_options (blogdescription), not a column.
		$array['description'] = $this->get_description();

		// Notes are lazy-loaded from metadata via the Notable trait.
		$array['notes'] = $this->get_notes();

		// Limitations are lazy-loaded from metadata via the Limitable trait.
		$array['limitations'] = $this->get_limitations();

		return $array;
	}

	/**
	 * Save (create or update) the model on the database.
	 *
	 * @since 2.0.0
	 *
	 * @return bool|\WP_Error
	 */
	public function save() {
		/*
		 * Prepares the substitutions.
		 */
		$this->handles_existing_search_and_replace();

		/**
		 * In order to be backwards compatible here, we'll have to do some crazy stuff,
		 * like overload the form session with the meta data saved on the pending site.
		 */
		if (has_filter('wu_search_and_replace_on_duplication')) {
			$transient = $this->get_transient();

			$session = wu_get_session('signup');

			$session->set('form', $transient);

			$session->commit();
		}

		$data = get_object_vars($this);

		$original_site = new self($data['_original']);

		$original_customer_id = $original_site->get_customer_id();

		unset($data['_original']);

		$data_unserialized = $data;

		$saved = true;

		$new = ! $this->exists();

		if ($new) {
			$network = get_network();

			$domain = $this->get_domain() ?: $network->domain;

			$network_id = $this->get_site_id() ?: get_current_network_id();

			$user_id = get_current_user_id();

			$customer = wu_get_customer($this->get_customer_id());

			/*
			 * By default, use the current user email address.
			 */
			$email = wp_get_current_user() ? wp_get_current_user()->user_email : get_network_option(null, 'admin_email');

			if ($customer) {
				$user_id = $customer->get_user_id();

				$email = $customer->get_email_address();
			}

			/*
			 * Decide if we need to duplicate this site, or create a new one.
			 */
			if ($this->get_template()) {
				$saved = \WP_Ultimo\Helpers\Site_Duplicator::duplicate_site(
					$this->get_template_id(),
					$this->get_title(),
					[
						'email'   => $email,
						'path'    => $this->get_path(),
						'domain'  => $domain,
						'meta'    => $this->get_signup_options(),
						'user_id' => $user_id ?: 0,
					]
				);

				if (is_wp_error($saved)) {

					// Here we check if the site was created and if so, get the ID to finish the execution to ensure the customer is set.

					$error = $saved;
					$saved = get_blog_id_from_url($domain, $this->get_path());

					if (0 === $saved || wu_get_main_site_id() === $saved) {
						return $error;
					}
				}
			} else {
				$saved = wpmu_create_blog($domain, $this->get_path(), $this->get_title(), $user_id, $this->get_signup_options(), $network_id);

				if ($saved && $this->get_public()) {
					$site_id = $saved;

					wp_update_site(
						$site_id,
						[
							'public' => $this->get_public(),
						]
					);
				}

				/**
				 * Fires after a site is created for the first time.
				 * Does not fire if duplicated from a template.
				 *
				 * @since 2.0.0
				 *
				 * @param array $data The object data that will be stored.
				 * @param Site  $site The object instance.
				 */
				do_action('wu_site_created', $data, $this);
			}

			if ( ! is_wp_error($saved) && wu_get_setting('enable_screenshot_generator', true)) {
				/*
				 * Delay screenshot by 90 seconds so the template is fully
				 * copied before the screenshot is taken. Without this delay,
				 * screenshots are taken immediately and show blank pages.
				 *
				 * @since 2.4.13
				 */
				wu_schedule_single_action(
					time() + 90,
					'wu_async_take_screenshot',
					[
						'site_id' => $saved,
					],
					'site'
				);
			}
		} else {
			$saved = wp_update_site($this->get_id(), $this->to_array());
		}

		if (is_wp_error($saved)) {
			return $saved;
		}

		$this->blog_id = $saved;

		switch_to_blog($this->blog_id);

		foreach ($this->get_signup_options() as $key => $value) {
			update_option($key, $value);
		}

		restore_current_blog();

		foreach ($this->get_signup_meta() as $key => $value) {
			update_site_meta($saved, $key, $value);
		}

		foreach ($this->meta as $key => $value) {
			update_site_meta($saved, $key, $value);
		}

		/**
		 * Handles membership
		 */
		$membership = $this->get_membership();

		if ($membership) {
			$customer_id = $membership->get_customer_id();

			$this->set_customer_id($customer_id);
		}

		/**
		 * Handles customers.
		 */
		$customer = $this->get_customer();

		if ($customer) {
			$role = wu_get_setting('default_role', 'administrator');

			if ($membership && $membership->has_limitations()) {
				$role = $membership->get_limitations()->customer_user_role->get_limit();
			}

			update_site_meta($this->get_id(), 'wu_customer_id', $customer->get_id());

			$user_id = $customer->get_user_id();

			// only add user to blog if they are not already a member, or we are downgrading their role.
			// Without this check the user could lose additional roles added manually or with hooks.
			if ('administrator' !== $role || ! is_user_member_of_blog($user_id, $this->get_id())) {
				add_user_to_blog($this->get_id(), $user_id, $role);
			}
		} elseif ($this->get_type() !== Site_Type::CUSTOMER_OWNED && $original_customer_id) {
			$user_id = wu_get_customer($original_customer_id)->get_user_id();

			remove_user_from_blog($user_id, $this->get_id());
		}

		update_blog_option($this->get_id(), 'blogname', $this->get_name());

		update_blog_option($this->get_id(), 'blogdescription', $this->get_description());

		/**
		 * Fires after an object is stored into the database.
		 *
		 * @since 2.0.0
		 *
		 * @param array      $model The model slug.
		 * @param array      $data The object data that will be stored, serialized.
		 * @param array      $data_unserialized The object data that will be stored.
		 * @param Base_Model $model_object The object instance.
		 * @param bool       $is_new If this object is a new one.
		 */
		do_action('wu_model_post_save', $this->model, $data, $data_unserialized, $this, $new); // @phpstan-ignore-line

		/**
		 * Fires after an object is stored into the database.
		 *
		 * @since 2.0.0
		 *
		 * @param array      $data The object data that will be stored.
		 * @param Base_Model $model_obeject The object instance.
		 * @param bool      $is_new If this object is a new one.
		 */
		do_action("wu_{$this->model}_post_save", $data, $this, $new);

		// Only compute extra hook parameters if the deprecated hook is actually in use.
		if ($new && has_filter('wu_create_site_meta')) {
			$signup_options = $this->get_signup_options();
			/**
			 * Fires immediately after a new site is created.
			 *
			 * @deprecated 2.0.0 Use {@see 'wu_site_post_save'} instead.
			 *
			 * @param array  $meta       Meta data. Used to set initial site options.
			 * @param array  $transient  Form data. Used to set initial site options.
			 */
			$meta = apply_filters_deprecated(
				'wu_create_site_meta',
				[$signup_options, $this->get_transient()],
				'2.0.0',
				'wu_site_post_save'
			);
			if ($signup_options !== $meta) {
				foreach ($meta as $key => $value) {
					if (! isset($signup_options[ $key ]) || $value !== $signup_options[ $key ]) {
						update_blog_option($this->blog_id, $key, $value);
					}
				}
			}
		}

		if (isset($session)) {
			$session->destroy();
		}

		return $saved;
	}

	/**
	 * By default, we just use the to_array method, but you can rewrite this.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function to_search_results() {

		$search_result = $this->to_array();

		$search_result['siteurl'] = $this->get_active_site_url();

		return $search_result;
	}

	/**
	 * Returns a list of sites based on the type.
	 *
	 * Type can be customer_owned or template.
	 *
	 * @since 2.0.0
	 *
	 * @param string $type Type to return. Can be customer_owned or template.
	 * @param array  $query_args Additional query args.
	 * @return Site[]
	 */
	public static function get_all_by_type($type = 'customer_owned', $query_args = []) {

		global $wpdb;

		if ('pending' === $type) {
			$table_name = "{$wpdb->base_prefix}wu_membershipmeta";

			$customer_id = (int) wu_get_isset($query_args, 'customer_id');

			$customer_id_query = '';

			if ($customer_id) {
				$memberships = wu_get_memberships(
					[
						'fields'      => ['id'],
						'customer_id' => $customer_id,
					]
				);

				$memberships_str = '';

				foreach ($memberships as $membership) {
					$memberships_str = ! empty($memberships_str) ? $memberships_str . ', ' . $membership->id : $membership->id;
				}

				$customer_id_query = ! empty($memberships_str) ? "AND wu_membership_id IN ($memberships_str)" : '';
			}

			$sql = "SELECT meta_value FROM {$table_name} WHERE meta_key = 'pending_site' $customer_id_query ORDER BY meta_id DESC"; // phpcs:ignore

			$results = array_column($wpdb->get_results($sql), 'meta_value'); // phpcs:ignore

			$results = array_map(
				function ($item) {

					$pending_site = maybe_unserialize($item);

					$pending_site->set_type('pending');

					return $pending_site;
				},
				$results
			);

			return $results;
		}

		$query = $query_args;

		$query['meta_query'] = [ // phpcs:ignore WordPress
			[
				'key'   => 'wu_type',
				'value' => $type,
			],
		];

		return static::query($query);
	}

	/**
	 * Returns a list of sites of given categories
	 *
	 * @since 2.1.3
	 *
	 * @param array $categories Array of categories names.
	 * @param array $query_args Additional query args.
	 * @return array
	 */
	public static function get_all_by_categories($categories = [], $query_args = []) {

		global $wpdb;

		$query = $query_args;

		$query['meta_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			[
				'key'     => 'wu_categories',
				'value'   => maybe_serialize($categories),
				'compare' => 'LIKE',
			],
		];

		return static::query($query);
	}

	/**
	 * Get the list of all Site Template Categories.
	 *
	 * @since 2.0.0
	 * @param array $sites An array of selected site ids or site objects.
	 * @return array
	 */
	public static function get_all_categories($sites = []) {

		global $wpdb;

		$site_ids = [];

		foreach ($sites as $site) {
			if ($site instanceof Site) {
				$site_ids[] = $site->get_id();
			}
		}

		$cache_key = 'site_categories_' . implode(':', $site_ids);

		$cache = wp_cache_get($cache_key, 'sites');

		if (is_array($cache)) {
			return $cache;
		}

		$final_array = [];

		$query = "SELECT DISTINCT meta_value FROM {$wpdb->base_prefix}blogmeta WHERE meta_key = %s";

		if ( ! empty($site_ids)) {
			$query .= ' AND blog_id IN (' . implode(', ', $site_ids) . ')';
		}

		$results = $wpdb->get_results($wpdb->prepare($query, 'wu_categories')); // phpcs:ignore

		foreach ($results as $category_array_raw) {
			$category_array = maybe_unserialize($category_array_raw->meta_value);
			if ( is_array($category_array) ) {
				foreach ($category_array as $category) {
					if ( ! isset($final_array[ $category ])) {
						$final_array[ $category ] = $category;
					}
				}
			}
		}

		wp_cache_set($cache_key, $final_array, 'sites');

		return $final_array;
	}

	/**
	 * List of limitations that need to be merged.
	 *
	 * Every model that is limitable (imports this trait)
	 * needs to declare explicitly the limitations that need to be
	 * merged. This allows us to chain the merges, and gives us
	 * a final list of limitations at the end of the process.
	 *
	 * In the case of sites, we need to get the membership
	 * limitations.
	 *
	 * @see \WP_Ultimo\Models\Traits\Trait_Limitable
	 * @since 2.0.0
	 * @return array
	 */
	public function limitations_to_merge() {

		$limitations_to_merge = [];

		$membership = $this->get_membership();

		if ($membership) {
			$membership_limitations = $membership->get_limitations();

			$limitations_to_merge[] = $membership_limitations;
		}

		return $limitations_to_merge;
	}
}
