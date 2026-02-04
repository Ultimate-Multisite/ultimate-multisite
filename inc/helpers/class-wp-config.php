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

		if ( ! is_writable($config_path)) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable

			// translators: %s is the file name.
			return new \WP_Error('not-writeable', sprintf(__('The file %s is not writable', 'ultimate-multisite'), $config_path));
		}

		$config = file($config_path);

		$line = $this->find_injected_line($config, $constant);

		$content = str_pad(sprintf("define( '%s', '%s' );", $constant, $value), 50) . '// Automatically injected by Ultimate Multisite;';

		if (false === $line) {

			// no defined, we just need to inject
			$hook_line = $this->find_reference_hook_line($config);

			if (false === $hook_line) {
				return new \WP_Error('unknown-wpconfig', __("Ultimate Multisite can't recognize your wp-config.php, please revert it to original state for further process.", 'ultimate-multisite'));
			}

			$config = $this->inject_contents($config, $hook_line + 1, PHP_EOL . $content . PHP_EOL);

			return file_put_contents($config_path, implode('', $config), LOCK_EX);
		} else {
			[$value, $line] = $line;

			if (true !== $value) {
				$config[ $line ] = $content . PHP_EOL;

				return file_put_contents($config_path, implode('', $config), LOCK_EX);
			}
		}

		return false;
	}

	/**
	 * Actually inserts the new lines into the array of wp-config.php lines.
	 *
	 * @since 2.0.0
	 *
	 * @param array  $content_array Array containing the original lines of the file being edited.
	 * @param int    $line Line number to inject the new content at.
	 * @param string $value Value to add to that specific line.
	 * @return array New array containing the lines of the modified file.
	 */
	public function inject_contents($content_array, $line, $value) {

		if ( ! is_array($value)) {
			$value = [$value];
		}

		array_splice($content_array, $line, 0, $value);

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
		} elseif (@file_exists(dirname(ABSPATH) . '/wp-config.php') && ! @file_exists(dirname(ABSPATH) . '/wp-settings.php')) {
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
	 * Find reference line for injection.
	 *
	 * We need a hook point we can use as reference to inject our constants.
	 * For now, we are using the line defining the $table_prefix.
	 * e.g. $table_prefix = 'wp_';
	 * We retrieve that line via RegEx.
	 *
	 * @since 2.0.0
	 *
	 * @param array $config Array containing the lines of the config file, for searching.
	 * @return false|int Line number.
	 */
	public function find_reference_hook_line($config) {

		global $wpdb;

		/**
		 * We check for three patterns when trying to figure our
		 * where we can inject our constants:
		 *
		 * 1. We search for the $table_prefix variable definition;
		 * 2. We search for more complex $table_prefix definitions - the ones that
		 *    use env variables, for example;
		 * 3. If that's not available, we look for the 'Happy Publishing' comment;
		 * 4. If that's also not available, we look for the beginning of the file.
		 *
		 * The key represents the pattern and the value the number of lines to add.
		 * A negative number of lines can be passed to write before the found line,
		 * instead of writing after it.
		 */
		$patterns = apply_filters(
			'wu_wp_config_reference_hook_line_patterns',
			[
				'/^\$table_prefix\s*=\s*[\'|\"]' . $wpdb->prefix . '[\'|\"]/' => 0,
				'/^( ){0,}\$table_prefix\s*=.*[\'|\"]' . $wpdb->prefix . '[\'|\"]/' => 0,
				'/(\/\* That\'s all, stop editing! Happy publishing\. \*\/)/' => -2,
				'/<\?php/' => 0,
			]
		);

		$line = 1;

		foreach ($patterns as $pattern => $lines_to_add) {
			foreach ($config as $k => $line) {
				if (preg_match($pattern, (string) $line)) {
					$line = $k + $lines_to_add;

					break 2;
				}
			}
		}

		return $line;
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

		if ( ! is_writable($config_path)) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable

			// translators: %s is the file name.
			return new \WP_Error('not-writeable', sprintf(__('The file %s is not writable', 'ultimate-multisite'), $config_path));
		}

		$config = file($config_path);

		$line = $this->find_injected_line($config, $constant);

		if (false === $line) {
			return;
		} else {
			$value = $line[0];

			$line = $line[1];

			if ('true' === $value || '1' === $value) {

				// value is true, we will remove this
				unset($config[ $line ]);

				// save it
				return file_put_contents($config_path, implode('', $config), LOCK_EX);
			}
		}
	}

	/**
	 * Checks for the injected line inside of the wp-config.php file.
	 *
	 * @since 2.0.0
	 *
	 * @param array  $config Array containing the lines of the config file, for searching.
	 * @param string $constant The constant name.
	 * @return mixed[]|bool
	 */
	public function find_injected_line($config, $constant) {

		$pattern = "/^define\(\s*['|\"]" . $constant . "['|\"],(.*)\)/";

		foreach ($config as $k => $line) {
			if (preg_match($pattern, (string) $line, $matches)) {
				return [trim($matches[1]), $k];
			}
		}

		return false;
	}
}
