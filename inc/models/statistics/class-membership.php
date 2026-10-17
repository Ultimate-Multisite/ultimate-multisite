<?php
/**
 * Read-only membership model for dashboard statistics.
 *
 * @package WP_Ultimo
 * @subpackage Models\Statistics
 */

namespace WP_Ultimo\Models\Statistics;

defined('ABSPATH') || exit;

/**
 * Keep the normal membership setters/getters, without an eager product snapshot.
 *
 * The short class name remains Membership so Base_Model's model identifier and
 * metadata/filter names remain unchanged. Product getters still resolve normally
 * if a query filter needs them; only the save-time snapshot is omitted.
 */
final class Membership extends \WP_Ultimo\Models\Membership {

	/**
	 * Statistics never save product changes, so no snapshot needs to be hydrated.
	 *
	 * @var bool
	 */
	protected $compile_product_snapshot = false;

	/**
	 * Prevent saving a model that deliberately omits its change-detection snapshot.
	 *
	 * @return \WP_Error
	 */
	public function save() {

		return new \WP_Error('wu_read_only_membership', __('Statistics memberships are read-only. Reload the membership before modifying it.', 'ultimate-multisite'));
	}

	/**
	 * Prevent deleting a read-only query result.
	 *
	 * @return \WP_Error
	 */
	public function delete() {

		return new \WP_Error('wu_read_only_membership', __('Statistics memberships are read-only. Reload the membership before modifying it.', 'ultimate-multisite'));
	}
}
