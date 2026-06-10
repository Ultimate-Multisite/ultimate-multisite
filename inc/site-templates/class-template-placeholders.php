<?php
/**
 * Site Template Placeholders
 *
 * Replaces the content of templates with placeholders.
 *
 * @package WP_Ultimo
 * @subpackage Site_Templates
 * @since 2.0.0
 */

namespace WP_Ultimo\Site_Templates;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Replaces the content of templates with placeholders.
 *
 * @since 2.0.0
 */
class Template_Placeholders {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * Keeps a copy of the placeholders as saved.
	 *
	 * @since 2.0.0
	 * @var array
	 */
	protected $placeholders_as_saved = [];

	/**
	 * Keeps an array of placeholder => value.
	 *
	 * @since 2.0.0
	 * @var array
	 */
	protected $placeholders = [];

	/**
	 * Holds the placeholder tags.
	 *
	 * @since 2.0.0
	 * @var array
	 */
	protected $placeholder_keys = [];

	/**
	 * Holds the placeholder values.
	 *
	 * @since 2.0.0
	 * @var array
	 */
	protected $placeholder_values = [];

	/**
	 * Loads the placeholders and adds the hooks.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function init(): void {

		$this->load_placeholders();

		add_action('wp_ultimo_admin_pages', [$this, 'add_template_placeholders_admin_page']);

		add_action('wp_ajax_wu_get_placeholders', [$this, 'serve_placeholders_via_ajax']);

		add_action('wp_ajax_wu_save_placeholders', [$this, 'save_placeholders']);

		add_filter('the_content', [$this, 'placeholder_replacer']);

		add_filter('the_title', [$this, 'placeholder_replacer']);
	}

	/**
	 * Loads the placeholders to keep them "cached".
	 *
	 * @since 2.0.0
	 * @return void
	 */
	protected function load_placeholders() {

		$placeholders = wu_get_option(
			'template_placeholders',
			[
				'placeholders' => [],
			]
		);

		$this->placeholders_as_saved = $placeholders;

		$placeholders = $placeholders['placeholders'];

		$tags   = array_column($placeholders, 'placeholder');
		$values = array_column($placeholders, 'content');

		$tags   = array_map([$this, 'add_curly_braces'], $tags);
		$values = array_map('nl2br', $values);

		$this->placeholder_keys   = $tags;
		$this->placeholder_values = $values;
		$this->placeholders       = array_combine($this->placeholder_keys, $this->placeholder_values);

		/*
		 * Filter everything.
		 */
		$this->placeholder_keys   = array_filter($this->placeholder_keys);
		$this->placeholder_values = array_filter($this->placeholder_values);
		$this->placeholders       = array_filter($this->placeholders);
	}

	/**
	 * Adds curly braces to the placeholders.
	 *
	 * @since 2.0.0
	 *
	 * @param string $tag The placeholder string.
	 * @return string
	 */
	protected function add_curly_braces($tag) {

		return "{{{$tag}}}";
	}

	/**
	 * Replace the contents with the placeholders.
	 *
	 * @since 2.0.0
	 *
	 * @param string $content The content of the post.
	 */
	public function placeholder_replacer($content): string {

		return str_replace($this->placeholder_keys, $this->placeholder_values, $content);
	}

	/**
	 * Serve placeholders via ajax.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function serve_placeholders_via_ajax(): void {

		if ( ! current_user_can('manage_network')) {
			wp_send_json_error(new \WP_Error('unauthorized', __('You do not have permission to perform this action.', 'ultimate-multisite')));
		}

		wp_send_json_success($this->placeholders_as_saved);
	}

	/**
	 * Save the placeholders.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function save_placeholders(): void {

		if ( ! current_user_can('manage_network')) {
			wp_send_json(
				[
					'code'    => 'not-enough-permissions',
					'message' => __('You don\'t have permission to alter placeholders.', 'ultimate-multisite'),
				]
			);
		}

		if ( ! check_ajax_referer('wu_edit_placeholders_editing', false, false)) {
			wp_send_json(
				[
					'code'    => 'not-enough-permissions',
					'message' => __('You don\'t have permission to alter placeholders.', 'ultimate-multisite'),
				]
			);
		}

		// Init filesystem if not yet initiated.
		WP_Filesystem();

		// Get POST body HTML data.
		global $wp_filesystem;
		$data = json_decode($wp_filesystem->get_contents('php://input'), true);

		if (null === $data) {
			wp_send_json(
				[
					'code'    => 'invalid-input',
					'message' => __('The JSON provided is invalid', 'ultimate-multisite'),
				]
			);
		}
		$data = wu_clean($data);

		$placeholders = $data['placeholders'] ?? [];

		wu_save_option(
			'template_placeholders',
			[
				'placeholders' => $placeholders,
			]
		);

		wp_send_json(
			[
				'code'    => 'success',
				'message' => __('Placeholders successfully updated!', 'ultimate-multisite'),
			]
		);
	}

	/**
	 * Adds the template placeholders admin page.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function add_template_placeholders_admin_page(): void {

		new \WP_Ultimo\Admin_Pages\Placeholders_Admin_Page();
	}
}
