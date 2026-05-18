<?php
/**
 * Minimal in-tree copy of the `withProperty` helper from jasny/immutable.
 *
 * Only `withProperty()` is exercised by the vendored Broker/Server pair, so
 * we ship just that method here rather than the full jasny/immutable trait.
 *
 * @package WP_Ultimo
 * @subpackage SSO\Jasny
 * @since 2.0.11
 */

declare(strict_types=1);

namespace WP_Ultimo\SSO\Jasny;

defined('ABSPATH') || exit;

/**
 * Trait providing a `withProperty` clone-and-modify helper for immutable objects.
 */
trait With {

	/**
	 * Return a clone of this object with a single property replaced.
	 *
	 * Returns the same instance when the new value is identical to the
	 * existing one (or both are null and the property is unset).
	 *
	 * @since 2.0.11
	 *
	 * @param string $property Property name on the current object.
	 * @param mixed  $value    New value to assign on the clone.
	 * @return static
	 * @throws \BadMethodCallException If the property doesn't exist on this class.
	 */
	private function withProperty(string $property, $value) {
		if ( ! property_exists($this, $property)) {
			throw new \BadMethodCallException(sprintf('%s has no property "%s"', get_class($this), $property));
		}

		if (
			(isset($this->{$property}) && $this->{$property} === $value) ||
			( ! isset($this->{$property}) && null === $value)
		) {
			return $this;
		}

		$clone              = clone $this;
		$clone->{$property} = $value;

		return $clone;
	}
}
