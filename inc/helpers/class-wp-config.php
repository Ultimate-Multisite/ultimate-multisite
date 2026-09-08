<?php
/**
 * Handles modifications to the wp-config.php file, if permissions allow.
 *
 * @package WP_Ultimo
 * @subpackage Helper
 * @since 2.0.0
 */

namespace WP_Ultimo\Helpers;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Handles modifications to the wp-config.php file, if permissions allow.
 *
 * @since 2.0.0
 */
class WP_Config {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * Inject the constant into the wp-config.php file.
	 *
	 * @since 2.0.0
	 *
	 * @param string     $constant The name of the constant. e.g. WP_ULTIMO_CONSTANT.
	 * @param string|int $value The value of that constant.
	 * @return bool|\WP_Error
	 */
	public function inject_wp_config_constant($constant, $value) {

		$config_path = $this->get_wp_config_path();

		try {
			$wp_config_transformer = new \WPConfigTransformer($config_path);
			$is_raw_value          = is_bool($value) || is_int($value);

			if (is_bool($value)) {
				$value = $value ? 'true' : 'false';
			}

			return $wp_config_transformer->update(
				'constant',
				$constant,
				(string) $value,
				['raw' => $is_raw_value]
			);
		} catch (\Exception $exception) {
			return new \WP_Error('wp-config-update-failed', $exception->getMessage());
		}
	}

	/**
	 * Legacy compatibility method that no longer modifies config contents.
	 *
	 * @since 2.0.0
	 * @deprecated 2.15.2 No replacement.
	 *
	 * @param array  $content_array Array containing the original lines of the file being edited.
	 * @param int    $line Line number to inject the new content at.
	 * @param string $value Value to add to that specific line.
	 * @return array The original content array.
	 */
	public function inject_contents($content_array, $line, $value) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Parameters retained for backwards compatibility.

		_doing_it_wrong(__METHOD__, esc_html__('This method is deprecated and no longer performs any operation.', 'ultimate-multisite'), '2.15.2');

		return $content_array;
	}

	/**
	 * Gets the correct path to the wp-config.php file.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_wp_config_path() {

		if (file_exists(ABSPATH . 'wp-config.php')) {
			return (ABSPATH . 'wp-config.php');
		} elseif (@file_exists(dirname(ABSPATH) . '/wp-config.php') && ! @file_exists(dirname(ABSPATH) . '/wp-settings.php')) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Parent directory access may be restricted.
			return (dirname(ABSPATH) . '/wp-config.php');
		} elseif (defined('WP_TESTS_MULTISITE') && constant('WP_TESTS_MULTISITE') === true) {
			$tests_dir = getenv('WP_TESTS_DIR');

			if (! $tests_dir) {
				$tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
			}

			return $tests_dir . '/wp-tests-config.php';
		}

		return ABSPATH . 'wp-config.php';
	}

	/**
	 * Legacy compatibility method that no longer searches config contents.
	 *
	 * @since 2.0.0
	 * @deprecated 2.15.2 No replacement.
	 *
	 * @param array $config Array containing the lines of the config file, for searching.
	 * @return false
	 */
	public function find_reference_hook_line($config) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Parameter retained for backwards compatibility.

		_doing_it_wrong(__METHOD__, esc_html__('This method is deprecated and no longer performs any operation.', 'ultimate-multisite'), '2.15.2');

		return false;
	}

	/**
	 * Revert the injection of a constant in wp-config.php
	 *
	 * @since 2.0.0
	 *
	 * @param string $constant Constant name.
	 * @return mixed
	 */
	public function revert($constant) {

		$config_path = $this->get_wp_config_path();

		try {
			$wp_config_transformer = new \WPConfigTransformer($config_path);

			return $wp_config_transformer->remove('constant', $constant);
		} catch (\Exception $exception) {
			return new \WP_Error('wp-config-update-failed', $exception->getMessage());
		}
	}

	/**
	 * Legacy compatibility method that no longer searches config contents.
	 *
	 * @since 2.0.0
	 * @deprecated 2.15.2 No replacement.
	 *
	 * @param array  $config Array containing the lines of the config file, for searching.
	 * @param string $constant The constant name.
	 * @return false
	 */
	public function find_injected_line($config, $constant) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Parameters retained for backwards compatibility.

		_doing_it_wrong(__METHOD__, esc_html__('This method is deprecated and no longer performs any operation.', 'ultimate-multisite'), '2.15.2');

		return false;
	}
}
