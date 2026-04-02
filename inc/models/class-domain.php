<?php
/**
 * The Domain model for the Domain Mappings.
 *
 * @package WP_Ultimo
 * @subpackage Models
 * @since 2.0.0
 */

namespace WP_Ultimo\Models;

use stdClass;
use WP_Ultimo\Domain_Mapping\Helper;
use WP_Ultimo\Database\Domains\Domain_Stage;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Domain model class. Implements the Base Model.
 *
 * @since 2.0.0
 */
class Domain extends Base_Model {

	/**
	 * Blog ID of the site associated with this domain.
	 *
	 * @since 2.0.0
	 * @var integer
	 */
	protected $blog_id;

	/**
	 * The domain name mapped.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $domain = '';

	/**
	 * Is this domain active?
	 *
	 * @since 2.0.0
	 * @var boolean
	 */
	protected $active = true;

	/**
	 * Is this a primary_domain? Requests to other mapped domains will resolve to the primary.
	 *
	 * @since 2.0.0
	 * @var boolean
	 */
	protected $primary_domain = false;

	/**
	 * Should this domain be forced to be used only on HTTPS?
	 *
	 * @since 2.0.0
	 * @var boolean
	 */
	protected $secure = false;

	/**
	 * Stages of domain mapping
	 *
	 * - checking-dns
	 * - checking-ssl-cert
	 * - done
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $stage = Domain_Stage::CHECKING_DNS;

	/**
	 * Date when this was created.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $date_created;

	/**
	 * List of stages that should force the domain to an inactive status.
	 *
	 * @since 2.0.0
	 * @var array
	 */
	const INACTIVE_STAGES = [
		Domain_Stage::CHECKING_DNS,
		Domain_Stage::CHECKING_SSL,
		Domain_Stage::FAILED,
		Domain_Stage::SSL_FAILED,
	];

	/**
	 * Query Class to the static query methods.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $query_class = \WP_Ultimo\Database\Domains\Domain_Query::class;

	/**
	 * Cache the path so we don't need to load the site object every time.
	 *
	 * @var string
	 */
	private string $path;

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

		$id = $this->get_id();

		return [
			'blog_id'        => 'required|integer',
			'domain'         => "required|domain|unique:\WP_Ultimo\Models\Domain,domain,{$id}",
			'stage'          => 'required|in:checking-dns,checking-ssl-cert,done-without-ssl,done,failed,ssl-failed|default:checking-dns',
			'active'         => 'default:1',
			'secure'         => 'default:0',
			'primary_domain' => 'default:0',
		];
	}

	/**
	 * Returns the domain address mapped.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_domain() {

		return $this->domain;
	}

	/**
	 * Sets the domain of this model object;
	 *
	 * @since 2.0.0
	 *
	 * @param string $domain Your Domain name. You don't need to put http or https in front of your domain in this field. e.g: example.com.
	 * @return void
	 */
	public function set_domain($domain): void {

		$this->domain = strtolower($domain);
	}

	/**
	 * Gets the URL with schema and all.
	 *
	 * @since 2.0.0
	 *
	 * @param string $path The path to add to the end of the url.
	 */
	public function get_url($path = ''): string {

		$schema = $this->is_secure() ? 'https://' : 'http://';

		return sprintf('%s%s/%s', $schema, $this->get_domain(), $path);
	}

	/**
	 * Get the ID of the corresponding site.
	 *
	 * @access public
	 * @since  2.0
	 * @return int
	 */
	public function get_blog_id() {

		return (int) $this->blog_id;
	}

	/**
	 * Sets the blog_id of this model object;
	 *
	 * @since 2.0.0
	 *
	 * @param int $blog_id The blog ID attached to this domain.
	 * @return void
	 */
	public function set_blog_id($blog_id): void {

		$this->blog_id = $blog_id;
	}

	/**
	 * Get the ID of the corresponding site.
	 *
	 * @since 2.0.0
	 * @return int
	 */
	public function get_site_id() {

		return $this->get_blog_id();
	}

	/**
	 * Get the site object for this particular mapping.
	 *
	 * @since 2.0.0
	 * @return \WP_Site|\WP_Ultimo\Models\Site|false
	 */
	public function get_site() {

		/**
		 * In a domain mapping environment, the user is not yet logged in.
		 * This means that we can't use BerlinDB, unfortunately, as it uses the user caps
		 * to decide which fields to make available.
		 *
		 * To bypass this limitation, we use the default WordPress function on those cases.
		 */
		if ( ! function_exists('current_user_can')) {
			return \WP_Site::get_instance($this->get_blog_id());
		}

		return wu_get_site($this->get_blog_id());
	}

	/**
	 * Gets the path of the mapped site.
	 *
	 * @return string|null
	 */
	public function get_path() {
		if (! isset($this->path)) {
			// don't use $this->get_site() as it causes infinite loop and native is faster anyway.
			$site = \WP_Site::get_instance($this->get_blog_id());
			if ( ! $site) {
				return null;
			}
			$this->path = $site->path;
		}

		return $this->path;
	}

	/**
	 * Check if this particular mapping is active.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function is_active() {

		if ($this->has_inactive_stage()) {
			return false;
		}

		return (bool) $this->active;
	}

	/**
	 * Sets the active state of this model object;
	 *
	 * @since 2.0.0
	 *
	 * @param boolean $active Set this domain as active (true), which means available to be used, or inactive (false).
	 * @return void
	 */
	public function set_active($active): void {

		$this->active = $active;
	}

	/**
	 * Check if this is a primary domain.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function is_primary_domain() {

		return (bool) $this->primary_domain;
	}

	/**
	 * Sets the primary_domain state of this model object;
	 *
	 * @since 2.0.0
	 *
	 * @param boolean $primary_domain Define true to set this as primary domain of a site, meaning it's the main url, or set false.
	 * @return void
	 */
	public function set_primary_domain($primary_domain): void {
		$this->primary_domain = $primary_domain;
	}

	/**
	 * Check if we should use this domain securely (via HTTPS).
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function is_secure() {

		return (bool) $this->secure;
	}

	/**
	 * Sets the secure state of this model object;
	 *
	 * @since 2.0.0
	 *
	 * @param boolean $secure If this domain has some SSL security or not.
	 * @return void
	 */
	public function set_secure($secure): void {

		$this->secure = $secure;
	}

	/**
	 * Get the stage in which this domain is in at the moment.
	 *
	 * This is used to check the stage of the domain lifecycle.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_stage() {

		return $this->stage;
	}

	/**
	 * Sets the stage of this model object;
	 *
	 * @since 2.0.0
	 *
	 * @param string $stage The state of the domain model object. Can be one of this options: checking-dns, checking-ssl-cert, done-without-ssl, done and failed.
	 * @return void
	 */
	public function set_stage($stage): void {

		$this->stage = $stage;
	}

	/**
	 * Check if this domain is on a inactive stage.
	 *
	 * @since 2.0.0
	 */
	public function has_inactive_stage(): bool {

		return in_array($this->get_stage(), self::INACTIVE_STAGES, true);
	}

	/**
	 * Returns the Label for a given stage level.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_stage_label() {

		$type = new Domain_Stage($this->get_stage());

		return $type->get_label();
	}

	/**
	 * Gets the classes for a given stage level.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_stage_class() {

		$type = new Domain_Stage($this->get_stage());

		return $type->get_classes();
	}

	/**
	 * Get date when this was created.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_date_created() {

		return $this->date_created;
	}

	/**
	 * Set date when this was created.
	 *
	 * @since 2.0.0
	 * @param string $date_created Date when the domain was created. If no date is set, the current date and time will be used.
	 * @return void
	 */
	public function set_date_created($date_created): void {

		$this->date_created = $date_created;
	}

	/**
	 * Check if the domain is correctly set-up in terms of DNS resolution.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function has_correct_dns() {

		global $current_site;

		$domain_url = $this->get_domain();

		$domain_manager = \WP_Ultimo\Managers\Domain_Manager::get_instance();

		if ($domain_manager->verify_domain_with_loopback_request($this)) {
			return true;
		}

		$network_ip_address = Helper::get_network_public_ip();

		$results = \WP_Ultimo\Managers\Domain_Manager::dns_get_record($domain_url);

		$domains_and_ips = array_column($results, 'data');

		if (in_array($current_site->domain, $domains_and_ips, true)) {
			return true;
		}

		if (in_array($network_ip_address, $domains_and_ips, true)) {
			return true;
		}

		$result = false;

		/**
		 * Allow plugin developers to add new checks in order to define the results.
		 *
		 * @since 2.0.4
		 * @param bool $result the current result.
		 * @param self $domain The current domain instance.
		 * @param array $domains_and_ips The list of domains and IPs found on the DNS lookup.
		 * @return bool If the DNS is correctly setup or not.
		 */
		$result = apply_filters('wu_domain_has_correct_dns', $result, $this, $domains_and_ips);

		return $result;
	}

	/**
	 * Checks if the current domain has a valid SSL certificate that covers it.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function has_valid_ssl_certificate() {

		return Helper::has_valid_ssl_certificate($this->get_domain());
	}

	/**
	 * Save (create or update) the model on the database.
	 *
	 * Needs to override the parent implementation
	 * to clear the cache.
	 *
	 * @since 2.0.0
	 *
	 * @return bool|\WP_Error
	 */
	public function save() {

		$new_domain = $this->exists();

		$before_changes = clone $this;

		$was_new = ! $this->exists();

		$results = parent::save();

		if (is_wp_error($results) === false) {
			if ($new_domain) {
				if (has_action('mercator.mapping.created')) {
					$deprecated_args = [
						$this,
					];

					/**
					 * Deprecated: Mercator created domain.
					 *
					 * @since 2.0.0
					 * @param self $domain The domain object after saving.
					 * @return void.
					 */
					do_action_deprecated('mercator.mapping.created', $deprecated_args, '2.0.0', 'wu_domain_post_save');
				}
			} elseif (has_action('mercator.mapping.updated')) {
					$deprecated_args = [
						$this,
						$before_changes,
					];

					/**
					 * Deprecated: Mercator updated domain.
					 *
					 * @since 2.0.0
					 * @param self $domain The domain object after saving.
					 * @param self $before_changes The domain object before the changes.
					 * @return void.
					 */
					do_action_deprecated('mercator.mapping.updated', $deprecated_args, '2.0.0', 'wu_domain_post_save');
			}

			/*
			 * Resets cache.
			 *
			 * This will make sure the list of domains gets rebuild
			 * after a change is made.
			 */
			wp_cache_flush();

			/*
			 * If this domain was just set as primary, unset other primaries for this site.
			 */
			if ($this->primary_domain && ($was_new || (isset($this->_original['primary_domain']) && ! $this->_original['primary_domain']))) {
				$old_primary_domains = wu_get_domains(
					[
						'primary_domain' => true,
						'blog_id'        => $this->blog_id,
						'id__not_in'     => [$this->id],
						'fields'         => 'ids',
					]
				);

				do_action('wu_async_remove_old_primary_domains', $old_primary_domains);

				/**
				 * Fires when a domain becomes the primary domain for a site.
				 *
				 * This action is triggered when a domain's primary_domain flag is set to true,
				 * either when creating a new primary domain or when updating an existing domain
				 * to become primary.
				 *
				 * @since 2.0.0
				 *
				 * @param \WP_Ultimo\Models\Domain $domain  The domain that became primary.
				 * @param int                      $blog_id The blog ID of the affected site.
				 * @param bool                     $was_new Whether this is a newly created domain.
				 */
				do_action('wu_domain_became_primary', $this, $this->blog_id, $was_new);
			}
		}

		return $results;
	}

	/**
	 * Delete the model from the database.
	 *
	 * @since 2.0.0
	 * @return \WP_Error|bool
	 */
	public function delete() {

		$results = parent::delete();

		if (is_wp_error($results) === false && has_action('mercator.mapping.deleted')) {
			$deprecated_args = [
				$this,
			];

			/**
			 * Deprecated: Mercator Deleted domain.
			 *
			 * @since 2.0.0
			 * @param self The domain object just deleted.
			 * @return void.
			 */
			do_action_deprecated('mercator.mapping.deleted', $deprecated_args, '2.0.0', 'wu_domain_post_delete');
		}

		/*
		 * Delete log file.
		 */
		wu_log_clear("domain-{$this->get_domain()}");

		wu_log_add("domain-{$this->get_domain()}", __('Domain deleted and logs cleared...', 'ultimate-multisite'));

		return $results;
	}

	/**
	 * Get mapping by site ID
	 *
	 * @since 2.0.0
	 *
	 * @param int|stdClass $site Site ID, or site object from {@see get_blog_details}.
	 * @return Domain|Domain[]|\WP_Error|false Mapping on success, WP_Error if error occurred, or false if no mapping found.
	 */
	public static function get_by_site($site) {

		global $wpdb;

		// Allow passing a site object in
		if (is_object($site) && isset($site->blog_id)) {
			$site = $site->blog_id;
		}

		if ( ! is_numeric($site)) {
			return new \WP_Error('wu_domain_mapping_invalid_id');
		}

		$site = absint($site);

		// Check cache first
		$mappings = wp_cache_get('id:' . $site, 'domain_mapping');

		if ('none' === $mappings) {
			return false;
		}

		if ( ! empty($mappings)) {
			return static::to_instances($mappings);
		}

		// Cache missed, fetch from DB
		// Suppress errors in case the table doesn't exist
		$suppress = $wpdb->suppress_errors();

		$domain_table = "{$wpdb->base_prefix}wu_domain_mappings";

		$mappings = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . $domain_table . ' WHERE blog_id = %d ORDER BY primary_domain DESC, active DESC, secure DESC', $site)); //phpcs:ignore

		$wpdb->suppress_errors($suppress);

		if ( ! $mappings) {
			wp_cache_set('id:' . $site, 'none', 'domain_mapping');

			return false;
		}

		wp_cache_set('id:' . $site, $mappings, 'domain_mapping');

		return static::to_instances($mappings);
	}

	/**
	 * Gets mappings by domain names
	 *
	 * Note: This is used in sunrise, so unfortunately, we can't use the Query model.
	 *
	 * @since 2.0.0
	 *
	 * @param array|string $domains Domain names to search for.
	 * @return self
	 */
	public static function get_by_domain($domains) {

		global $wpdb;

		$domains = (array) $domains;

		// Check cache first
		$not_exists = 0;

		foreach ($domains as $domain) {
			$data = wp_cache_get('domain:' . $domain, 'domain_mappings');

			if ( ! empty($data) && 'notexists' !== $data) {
				return new self($data);
			} elseif ('notexists' === $data) {
				++$not_exists;
			}
		}

		if (count($domains) === $not_exists) {

			// Every domain we checked was found in the cache, but doesn't exist
			// so skip the query
			return null;
		}

		$placeholders = array_fill(0, count($domains), '%s');

		$placeholders_in = implode(',', $placeholders);

		/*
		 * Ensure wu_dmtable is set on $wpdb. In normal (non-sunrise) plugin load
		 * paths Domain_Mapping::startup() may not have run yet, which causes a
		 * PHP notice about an undefined property. Set it here as a fallback so
		 * the query can proceed without warnings.
		 */
		if (empty($wpdb->wu_dmtable)) {
			$wpdb->wu_dmtable = $wpdb->base_prefix . 'wu_domain_mappings';
		}

		// Prepare the query
		$query = "SELECT * FROM {$wpdb->wu_dmtable} WHERE domain IN ($placeholders_in) ORDER BY primary_domain DESC, active DESC, secure DESC LIMIT 1";

		$query = $wpdb->prepare($query, $domains); // phpcs:ignore

		// Suppress errors in case the table doesn't exist
		$suppress = $wpdb->suppress_errors();

		$mapping = $wpdb->get_row($query); // phpcs:ignore

		$wpdb->suppress_errors($suppress);

		if (empty($mapping)) {

			// Cache that it doesn't exist
			foreach ($domains as $domain) {
				wp_cache_set('domain:' . $domain, 'notexists', 'domain_mappings');
			}

			return null;
		}

		wp_cache_set('domain:' . $mapping->domain, $mapping, 'domain_mappings');

		return new self($mapping);
	}
}
