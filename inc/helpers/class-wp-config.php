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
	 * @param string          $constant The name of the constant. e.g. WP_ULTIMO_CONSTANT.
	 * @param string|int|bool $value The value of that constant.
	 * @return bool|\WP_Error
	 */
	public function inject_wp_config_constant($constant, $value) {

		return $this->inject_wp_config_constants([$constant => $value]);
	}

	/**
	 * Inject multiple constants into wp-config.php as one transaction.
	 *
	 * Existing definitions are removed before the authoritative definitions are
	 * added. Transformations happen on a temporary copy so a parser or write
	 * failure cannot leave a partially updated wp-config.php file behind.
	 *
	 * @since 2.15.2
	 *
	 * @param array<string, string|int|bool> $constants Constants and their values.
	 * @return bool|\WP_Error
	 */
	public function inject_wp_config_constants($constants) {

		return $this->transform_wp_config_constants($constants, false);
	}

	/**
	 * Transform constants on a temporary copy and atomically replace wp-config.php.
	 *
	 * @since 2.15.2
	 *
	 * @param array<string, string|int|bool|null> $constants Constants and their values.
	 * @param bool                                $remove_only Whether constants should only be removed.
	 * @throws \RuntimeException When the temporary transformation cannot be completed.
	 * @return bool|\WP_Error
	 */
	private function transform_wp_config_constants($constants, $remove_only) {

		if ( ! class_exists('WPConfigTransformer')) {
			return new \WP_Error('missing-wp-config-transformer', __('The wp-config.php transformer is not available.', 'ultimate-multisite'));
		}

		if (empty($constants)) {
			return false;
		}

		foreach (array_keys($constants) as $constant) {
			if ( ! is_string($constant) || ! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $constant)) {
				return new \WP_Error('invalid-wp-config-constant', __('An invalid wp-config.php constant name was provided.', 'ultimate-multisite'));
			}
		}

		$config_path          = $this->get_wp_config_path();
		$resolved_config_path = realpath($config_path);

		if (false === $resolved_config_path || ! is_writable($resolved_config_path)) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
			// translators: %s is the file name.
			return new \WP_Error('not-writeable', sprintf(__('The file %s is not writable', 'ultimate-multisite'), $config_path));
		}

		$original_contents = file_get_contents($resolved_config_path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if (false === $original_contents || '' === trim($original_contents)) {
			return new \WP_Error('invalid-wp-config', __('The wp-config.php file could not be read or is empty.', 'ultimate-multisite'));
		}

		$temporary_path = tempnam(dirname($resolved_config_path), '.wu-wp-config-');

		if (false === $temporary_path) {
			return new \WP_Error('wp-config-temp-file', __('A temporary wp-config.php file could not be created.', 'ultimate-multisite'));
		}

		try {
			// Direct filesystem access is required to lock and atomically replace this local PHP configuration file.
			$bytes_written = file_put_contents($temporary_path, $original_contents, LOCK_EX); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

			if (strlen($original_contents) !== $bytes_written) {
				throw new \RuntimeException(__('The temporary wp-config.php file could not be written completely.', 'ultimate-multisite'));
			}

			$file_permissions = fileperms($resolved_config_path);

			if (false !== $file_permissions) {
				chmod($temporary_path, $file_permissions & 0777); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod
			}

			$normalized_contents = str_replace(["\r\n", "\n\r", "\r"], "\n", $original_contents);
			$transformer         = new \WPConfigTransformer($temporary_path);
			$anchor              = $remove_only ? [] : $this->get_transformer_anchor($normalized_contents);

			if (is_wp_error($anchor)) {
				return $anchor;
			}

			foreach ($constants as $constant => $value) {
				$definition_count = $this->count_constant_definitions($original_contents, $constant);
				$exists           = $transformer->exists('constant', $constant);

				if ($definition_count && ! $exists) {
					// translators: %s is a PHP constant name.
					throw new \RuntimeException(sprintf(__('The existing %s definition uses an unsupported format and was not changed.', 'ultimate-multisite'), $constant));
				}

				if ($exists) {
					$transformer->remove('constant', $constant);
				}

				if ( ! $remove_only) {
					[$transformer_value, $raw] = $this->format_transformer_value($value);

					$transformer->add(
						'constant',
						$constant,
						$transformer_value,
						[
							'raw'       => $raw,
							'anchor'    => $anchor['anchor'],
							'separator' => "\n",
							'placement' => $anchor['placement'],
						]
					);
				}
			}

			$transformed_contents = file_get_contents($temporary_path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

			if (false === $transformed_contents) {
				throw new \RuntimeException(__('The transformed wp-config.php file could not be read.', 'ultimate-multisite'));
			}

			if (str_contains($original_contents, "\r\n")) {
				$transformed_contents = str_replace("\r\n", "\n", $transformed_contents);
				$transformed_contents = str_replace("\n", "\r\n", $transformed_contents);
				$bytes_written        = file_put_contents($temporary_path, $transformed_contents, LOCK_EX); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

				if (strlen($transformed_contents) !== $bytes_written) {
					throw new \RuntimeException(__('The temporary wp-config.php file could not be written completely.', 'ultimate-multisite'));
				}
			}

			$this->validate_transformed_constants($transformed_contents, array_keys($constants), ! $remove_only);

			if ($original_contents === $transformed_contents) {
				return false;
			}

			if (file_get_contents($resolved_config_path) !== $original_contents) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				throw new \RuntimeException(__('The wp-config.php file changed while it was being updated. No changes were applied.', 'ultimate-multisite'));
			}

			// The temporary file is in the same directory, making this an atomic replacement on supported filesystems.
			if ( ! rename($temporary_path, $resolved_config_path)) { // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
				throw new \RuntimeException(__('The transformed wp-config.php file could not replace the original file.', 'ultimate-multisite'));
			}

			if (function_exists('opcache_invalidate')) {
				opcache_invalidate($resolved_config_path, true);
			}

			return true;
		} catch (\Throwable $exception) {
			return new \WP_Error('wp-config-transform-failed', $exception->getMessage());
		} finally {
			if (file_exists($temporary_path)) {
				wp_delete_file($temporary_path);
			}
		}
	}

	/**
	 * Format a value for WPConfigTransformer.
	 *
	 * @since 2.15.2
	 *
	 * @param string|int|bool|null $value Constant value.
	 * @return array{0: string, 1: bool}
	 */
	private function format_transformer_value($value) {

		if (is_bool($value)) {
			return [$value ? 'true' : 'false', true];
		}

		if (is_int($value)) {
			return [(string) $value, true];
		}

		return [(string) $value, false];
	}

	/**
	 * Find a safe placement anchor supported by WPConfigTransformer.
	 *
	 * @since 2.15.2
	 *
	 * @param string $contents wp-config.php contents.
	 * @return array{anchor: string, placement: string}|\WP_Error
	 */
	private function get_transformer_anchor($contents) {

		$default_anchor = "/* That's all, stop editing!";

		if (str_contains($contents, $default_anchor)) {
			return [
				'anchor'    => $default_anchor,
				'placement' => 'before',
			];
		}

		if (preg_match('/^[\t ]*\$table_prefix\s*=.*;[\t ]*$/m', $contents, $matches)) {
			return [
				'anchor'    => $matches[0],
				'placement' => 'after',
			];
		}

		if (preg_match('/^[\t ]*(?:require|require_once)\s+ABSPATH\s*\.\s*[\'\"]wp-settings\.php[\'\"]\s*;[\t ]*$/m', $contents, $matches)) {
			return [
				'anchor'    => $matches[0],
				'placement' => 'before',
			];
		}

		return new \WP_Error('unknown-wpconfig', __("Ultimate Multisite can't recognize your wp-config.php. No changes were applied.", 'ultimate-multisite'));
	}

	/**
	 * Validate PHP syntax and the resulting constant counts.
	 *
	 * @since 2.15.2
	 *
	 * @param string   $contents wp-config.php contents.
	 * @param string[] $constants Constant names.
	 * @param bool     $should_exist Whether each constant should exist once.
	 * @throws \RuntimeException When the transformed constants fail validation.
	 * @return void
	 */
	private function validate_transformed_constants($contents, $constants, $should_exist) {

		token_get_all($contents, TOKEN_PARSE);

		$expected_count = $should_exist ? 1 : 0;

		foreach ($constants as $constant) {
			if ($expected_count !== $this->count_constant_definitions($contents, $constant)) {
				// translators: %s is a PHP constant name.
				throw new \RuntimeException(sprintf(esc_html__('The transformed wp-config.php file has an unexpected number of %s definitions.', 'ultimate-multisite'), esc_html($constant)));
			}
		}
	}

	/**
	 * Count define() calls for a constant without executing wp-config.php.
	 *
	 * @since 2.15.2
	 *
	 * @param string $contents wp-config.php contents.
	 * @param string $constant Constant name.
	 * @return int
	 */
	private function count_constant_definitions($contents, $constant) {

		$tokens = token_get_all($contents, TOKEN_PARSE);
		$count  = 0;

		foreach ($tokens as $index => $token) {
			if ( ! is_array($token) || T_STRING !== $token[0] || 'define' !== strtolower($token[1])) {
				continue;
			}

			$opening_parenthesis = $this->next_code_token($tokens, $index + 1);

			if (false === $opening_parenthesis || '(' !== $tokens[ $opening_parenthesis ]) {
				continue;
			}

			$name_index = $this->next_code_token($tokens, $opening_parenthesis + 1);

			if (false === $name_index || ! is_array($tokens[ $name_index ]) || T_CONSTANT_ENCAPSED_STRING !== $tokens[ $name_index ][0]) {
				continue;
			}

			$name = substr($tokens[ $name_index ][1], 1, -1);

			if ($constant === $name) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Find the next non-whitespace, non-comment PHP token.
	 *
	 * @since 2.15.2
	 *
	 * @param array $tokens PHP tokens.
	 * @param int   $start Starting index.
	 * @return int|false
	 */
	private function next_code_token($tokens, $start) {

		$token_count = count($tokens);

		for ($index = $start; $index < $token_count; ++$index) {
			$token = $tokens[ $index ];

			if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
				continue;
			}

			return $index;
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

		$config_path = ABSPATH . 'wp-config.php';

		if (file_exists(ABSPATH . 'wp-config.php')) {
			$config_path = ABSPATH . 'wp-config.php';
		} elseif (file_exists(dirname(ABSPATH) . '/wp-config.php') && ! file_exists(dirname(ABSPATH) . '/wp-settings.php')) {
			$config_path = dirname(ABSPATH) . '/wp-config.php';
		} elseif (defined('WP_TESTS_MULTISITE') && constant('WP_TESTS_MULTISITE') === true) {
			$tests_dir = getenv('WP_TESTS_DIR');

			if (! $tests_dir) {
				$tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
			}

			$config_path = $tests_dir . '/wp-tests-config.php';
		}

		/**
		 * Filters the wp-config.php path used for configuration updates.
		 *
		 * @since 2.15.2
		 *
		 * @param string $config_path Absolute path to wp-config.php.
		 */
		return (string) apply_filters('wu_wp_config_path', $config_path);
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

		return $this->transform_wp_config_constants([$constant => null], true);
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
