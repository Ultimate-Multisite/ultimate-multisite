<?php
/**
 * Test image editor with WebP support.
 *
 * @package WP_Ultimo\Tests\Helpers
 */

namespace WP_Ultimo\Helpers;

/**
 * Simulates an Imagick editor with WebP support.
 */
class Imagick_WebP_Test_Editor {

	/**
	 * Returns whether this editor supports the requested MIME type.
	 *
	 * @param array $args Editor capability arguments.
	 * @return bool
	 */
	public static function test($args) {

		return 'image/webp' === ($args['mime_type'] ?? '');
	}

	/**
	 * Returns whether this editor supports the requested MIME type.
	 *
	 * @param string $mime_type MIME type to test.
	 * @return bool
	 */
	public static function supports_mime_type($mime_type) {

		return 'image/webp' === $mime_type;
	}
}
