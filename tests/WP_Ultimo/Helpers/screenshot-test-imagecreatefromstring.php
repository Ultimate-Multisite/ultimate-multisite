<?php
/**
 * Test override for GD image decoding.
 *
 * @package WP_Ultimo\Tests\Helpers
 */

namespace WP_Ultimo\Helpers;

/**
 * Allows tests to simulate GD failing to decode an image.
 *
 * @param string $body Image body.
 * @return \GdImage|false
 */
function imagecreatefromstring($body) {

	$image = apply_filters('wu_screenshot_test_imagecreatefromstring', null, $body);

	return null === $image ? \imagecreatefromstring($body) : $image;
}
