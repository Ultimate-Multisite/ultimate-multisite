<?php
/**
 * A trait to handle limitable models.
 *
 * @package WP_Ultimo
 * @subpackage Models\Traits
 * @since 2.0.0
 */

namespace WP_Ultimo\Models\Traits;

use WP_Ultimo\Database\Sites\Site_Type;
use WP_Ultimo\Objects\Limitations;

defined('ABSPATH') || exit;

/**
 * Singleton trait.
 */
trait Limitable {

	/**
	 * Internal limitations cache.
	 *
	 * @since 2.0.0
	 * @var array
	 */
	protected array $limitations = [];

	/**
	 * @inheritDoc
	 */
	abstract public function limitations_to_merge();

	/**
	 * Gets the limitations for this model.
	 *
	 * Returns a Limitations object containing the limitations for this model.
	 * Can optionally merge limitations from parent models in a waterfall manner,
	 * and can exclude this model's own limitations for comparison purposes.
	 *
	 * @since 2.0.0
	 * @param bool $waterfall Whether to merge limitations from parent models. Default true.
	 * @param bool $skip_self Whether to skip this model's own limitations. Default false.
	 * @return \WP_Ultimo\Objects\Limitations The limitations object.
	 */
	public function get_limitations($waterfall = true, $skip_self = false) {

		/**
		 * If this is a site, and it's not a customer owned site, we don't have limitations.
		 * This is because we don't want to limit sites other than the customer owned ones.
		 */
		if ('site' === $this->model && $this->get_type() !== Site_Type::CUSTOMER_OWNED) {
			return new Limitations([]);
		}

		$cache_key = $waterfall ? '_composite_limitations_' : '_limitations_';

		$cache_key = $skip_self ? $cache_key . '_no_self_' : $cache_key;

		$cache_key = $this->get_id() . $cache_key . $this->model;

		$cached_version = wu_get_isset($this->limitations, $cache_key);

		if ( ! empty($cached_version)) {
			return $cached_version;
		}

		if ( ! is_array($this->meta)) {
			$this->meta = [];
		}

		if (did_action('muplugins_loaded') === false) {
			$modules_data = $this->get_meta('wu_limitations', []);
		} else {
			$modules_data = Limitations::early_get_limitations($this->model, $this->get_id());
		}

		$limitations = new Limitations([]);

		if ($waterfall) {
			$limitations = $limitations->merge(...$this->limitations_to_merge());

			/**
			 * If we don't want to take into consideration our own permissions
			 * we set this flag to true.
			 *
			 * This will return only the parents permissions and is super useful for
			 * comparisons.
			 */
			if ( ! $skip_self) {
				$limitations = $limitations->merge(true, $modules_data);
			}
		} else {
			$limitations = $limitations->merge($modules_data);
		}

		$this->limitations[ $cache_key ] = $limitations;

		return $limitations;
	}

	/**
	 * @inheritdoc
	 */
	public function has_limitations() {

		return $this->get_limitations()->has_limitations();
	}

	/**
	 * Checks if a particular module is being limited.
	 *
	 * @since 2.0.0
	 *
	 * @param string $module Module to check.
	 * @return boolean
	 */
	public function has_module_limitation($module) {

		return $this->get_limitations()->is_module_enabled($module);
	}

	/**
	 * Returns all user role quotas.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_user_role_quotas() {

		return $this->get_limitations()->get_user_role_quotas();
	}

	/**
	 * Proxy method to retrieve the allowed user roles.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_allowed_user_roles() {

		return $this->get_limitations()->get_allowed_user_roles();
	}

	/**
	 * Schedules plugins to be activated or deactivated based on the current limitations;
	 *
	 * @since 2.0.5
	 * @return void
	 */
	public function sync_plugins(): void {

		$sites = [];

		if ('site' === $this->model) {
			$sites[] = $this;
		} elseif ('membership' === $this->model) {
			$sites = $this->get_sites();
		}

		foreach ($sites as $site_object) {
			if ( ! $site_object->get_id() || $site_object->get_type() !== Site_Type::CUSTOMER_OWNED) {
				continue;
			}

			$site_id     = $site_object->get_id();
			$limitations = $site_object->get_limitations();

			if ( ! $limitations->plugins->is_enabled()) {
				continue;
			}

			$plugins_to_deactivate = $limitations->plugins->get_by_type('force_inactive');
			$plugins_to_activate   = $limitations->plugins->get_by_type('force_active');

			if ($plugins_to_deactivate) {
				wu_async_deactivate_plugins($site_id, array_keys($plugins_to_deactivate));
			}

			if ($plugins_to_activate) {
				wu_async_activate_plugins($site_id, array_keys($plugins_to_activate));
			}
		}
	}

	/**
	 * @inheritdoc
	 */
	public function handle_limitations(): void {
		/*
		 * Only handle limitations if there are to handle in the first place.
		 */
		if ( ! wu_request('modules')) {
			return;
		}

		$object_limitations = $this->get_limitations(false);

		$saved_limitations = $object_limitations->to_array();

		$modules_to_save = [];

		$limitations = Limitations::repository();

		$current_limitations = $this->get_limitations(true, true);

		foreach ($limitations as $limitation_id => $class_name) {
			$module = wu_get_isset($saved_limitations, $limitation_id, []);

			try {
				if (is_string($module)) {
					$module = json_decode($module, true);
				}
			} catch (\Throwable $exception) {

				// Silence is golden.
				$module = [];
			}

			$module['enabled'] = $object_limitations->{$limitation_id}->handle_enabled();

			$module['limit'] = $object_limitations->{$limitation_id}->handle_limit();

			$module = $object_limitations->{$limitation_id}->handle_others($module);

			if ($module) {
				$modules_to_save[ $limitation_id ] = $module;
			}
		}

		if ('product' !== $this->model) {
			/*
			 * Set the new permissions, based on the diff.
			 */
			$limitations = wu_array_recursive_diff($modules_to_save, $current_limitations->to_array());
		} elseif ($this->get_type() !== 'plan') {
			$limitations = wu_array_recursive_diff($modules_to_save, Limitations::get_empty()->to_array());
		} else {
			$limitations = $modules_to_save;
		}

		$this->meta['wu_limitations'] = $limitations;
	}

	/**
	 * @inheritdoc
	 */
	public function get_applicable_product_slugs() {

		if ('product' === $this->model) {
			return [$this->get_slug()];
		}

		$slugs = [];

		if ('membership' === $this->model) {
			$membership = $this;
		} elseif ('site' === $this->model) {
			$membership = $this->get_membership();
		}

		if ( ! empty($membership)) {
			$slugs = array_column(array_map('wu_cast_model_to_array', array_column($membership->get_all_products(), 'product')), 'slug'); // WOW

		}

		return $slugs;
	}
}
