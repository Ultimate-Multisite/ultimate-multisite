<?php
/**
 * Site_Templates Limit Module.
 *
 * @package WP_Ultimo
 * @subpackage Limitations
 * @since 2.0.0
 */

namespace WP_Ultimo\Limitations;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Site_Templates Limit Module.
 *
 * @since 2.0.0
 */
class Limit_Site_Templates extends Limit {

	/**
	 * Mode: Default - all templates are available.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	const MODE_DEFAULT = 'default';

	/**
	 * Mode: Assign a specific template to be used.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	const MODE_ASSIGN_TEMPLATE = 'assign_template';

	/**
	 * Mode: Customer can choose from available templates.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	const MODE_CHOOSE_AVAILABLE_TEMPLATES = 'choose_available_templates';

	/**
	 * Behavior: Template is available for selection.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	const BEHAVIOR_AVAILABLE = 'available';

	/**
	 * Behavior: Template is not available for selection.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	const BEHAVIOR_NOT_AVAILABLE = 'not_available';

	/**
	 * Behavior: Template is pre-selected and will be used automatically.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	const BEHAVIOR_PRE_SELECTED = 'pre_selected';

	/**
	 * The module id.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $id = 'site_templates';

	/**
	 * The mode of template assignment/selection.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $mode = self::MODE_DEFAULT;

	/**
	 * Sets up the module based on the module data.
	 *
	 * @since 2.0.0
	 *
	 * @param array $data The module data.
	 * @return void
	 */
	public function setup($data): void {

		parent::setup($data);

		$this->mode = wu_get_isset($data, 'mode', self::MODE_DEFAULT);
	}

	/**
	 * Returns the mode. Can be one of three: default, assign_template and choose_available_templates.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_mode() {

		return $this->mode;
	}

	/**
	 * The check method is what gets called when allowed is called.
	 *
	 * Each module needs to implement a check method, that returns a boolean.
	 * This check can take any form the developer wants.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed  $value_to_check Value to check.
	 * @param mixed  $limit The list of limits in this modules.
	 * @param string $type Type for sub-checking.
	 * @return bool
	 */
	public function check($value_to_check, $limit, $type = '') {

		$template = (object) $this->{$value_to_check};

		$types = [
			self::BEHAVIOR_AVAILABLE     => self::BEHAVIOR_AVAILABLE === $template->behavior,
			self::BEHAVIOR_NOT_AVAILABLE => self::BEHAVIOR_NOT_AVAILABLE === $template->behavior,
			self::BEHAVIOR_PRE_SELECTED  => self::BEHAVIOR_PRE_SELECTED === $template->behavior,
		];

		return wu_get_isset($types, $type, true);
	}

	/**
	 * Adds a magic getter for themes.
	 *
	 * @since 2.0.0
	 *
	 * @param string $template_id The template site id.
	 * @return object
	 */
	public function __get($template_id) {

		$template_id = str_replace('site_', '', $template_id);

		$template = (object) wu_get_isset($this->get_limit(), $template_id, $this->get_default_permissions($template_id));

		return (object) wp_parse_args($template, $this->get_default_permissions($template_id));
	}

	/**
	 * Returns default permissions.
	 *
	 * @since 2.0.0
	 *
	 * @param string $type Type for sub-checking.
	 * @return array
	 */
	public function get_default_permissions($type) {

		return [
			'behavior' => self::BEHAVIOR_NOT_AVAILABLE,
		];
	}

	/**
	 * Checks if a theme exists on the current module.
	 *
	 * @since 2.0.0
	 *
	 * @param string $template_id The template site id.
	 * @return bool
	 */
	public function exists($template_id) {

		$template_id = str_replace('site_', '', $template_id);

		$results = wu_get_isset($this->get_limit(), $template_id, []);

		return wu_get_isset($results, 'behavior', 'not-set') !== 'not-set';
	}

	/**
	 * Get all themes.
	 *
	 * @since 2.0.0
	 * @return array List of theme stylesheets.
	 */
	public function get_all_templates(): array {

		$templates = (array) $this->get_limit();

		return array_keys($templates);
	}

	/**
	 * Get available themes.
	 *
	 * @since 2.0.0
	 * @return array|false Array of available template IDs, or false when MODE_DEFAULT (unrestricted).
	 */
	public function get_available_site_templates() {

		/*
		 * MODE_DEFAULT means "allow all templates" — no restriction.
		 * Return false so callers that check is_array() treat this as unrestricted.
		 */
		if (self::MODE_DEFAULT === $this->mode) {
			return false;
		}

		$limits = $this->get_limit();

		if ( ! $limits) {
			return [];
		}

		$limits = (array) $limits;

		$available = [];

		foreach ($limits as $site_id => $site_settings) {
			$site_settings = (object) $site_settings;

			if (self::BEHAVIOR_AVAILABLE === $site_settings->behavior ||
				self::BEHAVIOR_PRE_SELECTED === $site_settings->behavior) {
				// Convert to integer to match type used in validation (absint)
				$available[] = absint($site_id);
			}
		}

		return $available;
	}

	/**
	 * Get the forced active theme for the current limitations.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_pre_selected_site_template() {

		$limits = $this->get_limit();

		$pre_selected_site_template = false;

		if ( ! $limits) {
			return $pre_selected_site_template;
		}

		foreach ($limits as $site_id => $site_settings) {
			$site_settings = (object) $site_settings;

			if (self::BEHAVIOR_PRE_SELECTED === $site_settings->behavior) {
				// Convert to integer to match type used in validation (absint)
				$pre_selected_site_template = absint($site_id);
			}
		}

		return $pre_selected_site_template;
	}

	/**
	 * Handles limits on post submission.
	 *
	 * @since 2.0.0
	 * @return mixed
	 */
	public function handle_limit() {

		$module = wu_get_isset(wu_clean(wp_unslash($_POST['modules'] ?? [])), $this->id, []); // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		return wu_get_isset($module, 'limit', $this->get_limit());
	}

	/**
	 * Handles other elements when saving. Used for custom attributes.
	 *
	 * @since 2.0.0
	 *
	 * @param array $module The current module, extracted from the request.
	 * @return array
	 */
	public function handle_others($module) {

		// Nonce check happened in Edit_Admin_Page::process_save().
		$_module = wu_get_isset(wu_clean(wp_unslash($_POST['modules'] ?? [])), $this->id, []); // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$module['mode'] = wu_get_isset($_module, 'mode', self::MODE_DEFAULT);

		return $module;
	}

	/**
	 * Returns a default state.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public static function default_state() {

		return [
			'enabled' => true,
			'limit'   => null,
			'mode'    => self::MODE_DEFAULT,
		];
	}
}
