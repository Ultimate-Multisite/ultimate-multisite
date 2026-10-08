<?php
/**
 * Membership queries for read-only dashboard statistics.
 *
 * @package WP_Ultimo
 * @subpackage Database\Memberships
 */

namespace WP_Ultimo\Database\Memberships;

defined('ABSPATH') || exit;

/**
 * Retain membership query selection, raw-row caching and filters, but skip the
 * eager product snapshot used only by editable models' change detection.
 */
class Membership_Statistics_Query extends Membership_Query {

	/**
	 * Read-only membership result type; still an instance of Models\Membership.
	 *
	 * @var string
	 */
	protected $item_shape = \WP_Ultimo\Models\Statistics\Membership::class;
}
