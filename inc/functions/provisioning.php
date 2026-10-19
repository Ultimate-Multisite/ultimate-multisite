<?php
/**
 * Provisioning Progress Functions
 *
 * Public API for reporting customer-facing provisioning progress. Pipelines
 * register their steps with the `wu_provisioning_pipelines` filter; see
 * \WP_Ultimo\Provisioning\Provisioning_Progress.
 *
 * @package WP_Ultimo\Functions
 * @since   2.18.0
 */

use WP_Ultimo\Provisioning\Provisioning_Progress;

// Exit if accessed directly
defined('ABSPATH') || exit;

// Loaded explicitly so add-ons can rely on the API before any classmap rebuild.
require_once wu_path('inc/provisioning/class-provisioning-history.php');
require_once wu_path('inc/provisioning/class-provisioning-progress.php');

/**
 * Starts tracking a provisioning run for a membership.
 *
 * @since 2.18.0
 *
 * @param int|\WP_Ultimo\Models\Membership $membership Membership model or id.
 * @param string                           $pipeline   Registered pipeline id.
 * @param array                            $args       See Provisioning_Progress::start().
 * @return array|false
 */
function wu_provisioning_progress_start($membership, $pipeline, $args = []) {

	return Provisioning_Progress::start($membership, $pipeline, (array) $args);
}

/**
 * Moves a membership's provisioning run to a later step.
 *
 * @since 2.18.0
 *
 * @param int|\WP_Ultimo\Models\Membership $membership Membership model or id.
 * @param string                           $step       Step key.
 * @param array                            $args       See Provisioning_Progress::step().
 * @return array|false
 */
function wu_provisioning_progress_step($membership, $step, $args = []) {

	return Provisioning_Progress::step($membership, $step, (array) $args);
}

/**
 * Completes a membership's provisioning run and records its timing history.
 *
 * @since 2.18.0
 *
 * @param int|\WP_Ultimo\Models\Membership $membership Membership model or id.
 * @param array                            $args       See Provisioning_Progress::complete().
 * @return array|false
 */
function wu_provisioning_progress_complete($membership, $args = []) {

	return Provisioning_Progress::complete($membership, (array) $args);
}

/**
 * Marks a membership's provisioning run as failed.
 *
 * @since 2.18.0
 *
 * @param int|\WP_Ultimo\Models\Membership $membership Membership model or id.
 * @param array                            $args       See Provisioning_Progress::fail().
 * @return array|false
 */
function wu_provisioning_progress_fail($membership, $args = []) {

	return Provisioning_Progress::fail($membership, (array) $args);
}

/**
 * Returns the customer-safe progress payload (step x of y, ETA, percent).
 *
 * @since 2.18.0
 *
 * @param int|\WP_Ultimo\Models\Membership $membership Membership model or id.
 * @return array|null
 */
function wu_get_provisioning_progress($membership) {

	return Provisioning_Progress::get_payload($membership);
}

/**
 * Runs a callback with a membership's provisioning context open, so deep code
 * can report steps with wu_provisioning_progress_step_current().
 *
 * @since 2.18.0
 *
 * @param int|\WP_Ultimo\Models\Membership $membership Membership model or id.
 * @param callable                         $callback   Work to run.
 * @return mixed The callback's return value.
 */
function wu_with_provisioning_progress($membership, callable $callback) {

	Provisioning_Progress::push_context($membership);

	try {
		return $callback();
	} finally {
		Provisioning_Progress::pop_context();
	}
}

/**
 * Reports a step for the provisioning run open in this request, if any.
 *
 * @since 2.18.0
 *
 * @param string $step     Step key.
 * @param string $pipeline Only apply to a run of this pipeline ('' for any).
 * @return void
 */
function wu_provisioning_progress_step_current($step, $pipeline = '') {

	Provisioning_Progress::step_current($step, $pipeline);
}
