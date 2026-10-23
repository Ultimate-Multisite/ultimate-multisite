<?php
/**
 * Testable Sunrise implementation.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo;

/**
 * Records file operations performed by Sunrise::try_upgrade().
 */
class Testable_Sunrise extends Sunrise {

	/**
	 * Last copy arguments.
	 *
	 * @var array|null
	 */
	public static $copied;

	/**
	 * Last invalidated path.
	 *
	 * @var string|null
	 */
	public static $invalidated;

	/**
	 * Records the sunrise copy without touching the test installation.
	 *
	 * @param string $source Source file path.
	 * @param string $destination Destination file path.
	 * @return bool
	 */
	protected static function copy_sunrise_file($source, $destination) {

		self::$copied = [$source, $destination];

		return true;
	}

	/**
	 * Records the invalidated path.
	 *
	 * @param string $file_path Invalidated file path.
	 * @return bool
	 */
	protected static function invalidate_opcode_cache($file_path) {

		self::$invalidated = $file_path;

		return true;
	}
}
