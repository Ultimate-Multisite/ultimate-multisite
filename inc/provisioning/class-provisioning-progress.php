<?php
/**
 * Customer-facing provisioning progress.
 *
 * @package WP_Ultimo
 * @subpackage Provisioning
 * @since 2.18.0
 */

namespace WP_Ultimo\Provisioning;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Tracks which step of a provisioning pipeline a membership is in.
 *
 * Pipelines ("site", an add-on's "network", or a hosting provider's own
 * pipeline) register their customer-facing steps with the
 * `wu_provisioning_pipelines` filter. Server-side code reports transitions
 * with the wu_provisioning_progress_*() functions; the record is stored in
 * membership meta and returned to the thank-you page pollers with an ETA
 * computed from this install's own history (Provisioning_History).
 *
 * @since 2.18.0
 */
class Provisioning_Progress {

	/**
	 * Membership meta key holding the progress record.
	 *
	 * @since 2.18.0
	 * @var string
	 */
	const META_KEY = 'wu_provisioning_progress';

	/**
	 * Running records older than this are replaced by a new run.
	 *
	 * @since 2.18.0
	 * @var int
	 */
	const STALE_AFTER = HOUR_IN_SECONDS;

	/**
	 * Transitions kept per run (bounds retries).
	 *
	 * @since 2.18.0
	 * @var int
	 */
	const MAX_MARKS = 64;

	/**
	 * Memberships whose provisioning is running in this request.
	 *
	 * @since 2.18.0
	 * @var int[]
	 */
	protected static $context = [];

	/**
	 * Registered pipelines, normalized.
	 *
	 * Each pipeline is `['label' => string, 'steps' => [key => ['label' => string, 'duration' => float]]]`
	 * with steps in display order.
	 *
	 * @since 2.18.0
	 * @return array
	 */
	public static function get_pipelines() {

		$defaults = [
			'site' => [
				'label' => __('Site', 'ultimate-multisite'),
				'steps' => [
					'queued'      => [
						'label'    => __('Waiting to start', 'ultimate-multisite'),
						'duration' => 5,
					],
					'creating'    => [
						'label'    => __('Creating your site', 'ultimate-multisite'),
						'duration' => 3,
					],
					'copy_files'  => [
						'label'    => __('Copying files', 'ultimate-multisite'),
						'duration' => 5,
					],
					'copy_data'   => [
						'label'    => __('Copying your template content', 'ultimate-multisite'),
						'duration' => 10,
					],
					'configuring' => [
						'label'    => __('Configuring your site', 'ultimate-multisite'),
						'duration' => 5,
					],
					'finishing'   => [
						'label'    => __('Finishing up', 'ultimate-multisite'),
						'duration' => 3,
					],
				],
			],
		];

		/**
		 * Registers customer-facing provisioning pipelines.
		 *
		 * Add a pipeline keyed by a short id. Steps are shown in the order given;
		 * `duration` is the default estimate in seconds, used until this install
		 * has recorded enough real runs.
		 *
		 * @since 2.18.0
		 *
		 * @param array $pipelines Pipelines keyed by id.
		 */
		$pipelines = apply_filters('wu_provisioning_pipelines', $defaults);

		$normalized = [];

		foreach ((array) $pipelines as $id => $pipeline) {
			if ( ! is_string($id) || ! preg_match('/\A[a-z0-9][a-z0-9_-]{0,63}\z/', $id) || empty($pipeline['steps']) || ! is_array($pipeline['steps'])) {
				continue;
			}

			$steps = [];

			foreach ($pipeline['steps'] as $key => $step) {
				if ( ! is_string($key) || ! preg_match('/\A[a-z0-9][a-z0-9_-]{0,63}\z/', $key)) {
					continue;
				}

				$steps[ $key ] = [
					'label'    => isset($step['label']) ? (string) $step['label'] : $key,
					'duration' => isset($step['duration']) && is_numeric($step['duration']) ? max(0.0, (float) $step['duration']) : 5.0,
				];
			}

			if ($steps) {
				$normalized[ $id ] = [
					'label' => isset($pipeline['label']) ? (string) $pipeline['label'] : $id,
					'steps' => $steps,
				];
			}
		}

		return $normalized;
	}

	/**
	 * Returns one registered pipeline.
	 *
	 * @since 2.18.0
	 *
	 * @param string $id Pipeline id.
	 * @return array|null
	 */
	public static function get_pipeline($id) {

		$pipelines = self::get_pipelines();

		return $pipelines[ $id ] ?? null;
	}

	/**
	 * Resolves a membership id from a model or id.
	 *
	 * @since 2.18.0
	 *
	 * @param int|object $membership Membership model or id.
	 * @return int
	 */
	protected static function membership_id($membership) {

		if (is_object($membership) && method_exists($membership, 'get_id')) {
			return (int) $membership->get_id();
		}

		return is_numeric($membership) ? (int) $membership : 0;
	}

	/**
	 * Reads the authoritative record, bypassing a stale object cache.
	 *
	 * Background workers write progress from another process (and, on hosts
	 * with per-domain cache prefixes, another cache namespace), so every read
	 * goes to the database.
	 *
	 * @since 2.18.0
	 *
	 * @param int|object $membership Membership model or id.
	 * @return array|null
	 */
	public static function get($membership) {

		$id = self::membership_id($membership);

		if ($id <= 0) {
			return null;
		}

		wp_cache_delete($id, 'wu_membership_meta');

		$record = get_metadata('wu_membership', $id, self::META_KEY, true);

		return is_array($record) && 1 === ($record['schema'] ?? null) && self::get_pipeline($record['pipeline'] ?? '') ? $record : null;
	}

	/**
	 * Saves a record.
	 *
	 * @since 2.18.0
	 *
	 * @param int   $id     Membership id.
	 * @param array $record Record.
	 * @return bool
	 */
	protected static function save($id, array $record) {

		$record['updated'] = microtime(true);

		$saved = (bool) update_metadata('wu_membership', $id, self::META_KEY, $record);

		wp_cache_delete($id, 'wu_membership_meta');

		return $saved;
	}

	/**
	 * Normalizes an event timestamp: never before the previous mark, never in the future.
	 *
	 * @since 2.18.0
	 *
	 * @param mixed      $at       Requested Unix timestamp.
	 * @param float|null $previous Previous mark.
	 * @return float
	 */
	protected static function timestamp($at, $previous = null) {

		$now = microtime(true);
		$at  = is_numeric($at) ? min((float) $at, $now) : $now;

		return null === $previous ? $at : max($at, (float) $previous);
	}

	/**
	 * Starts (or restarts) a run.
	 *
	 * A running record of the same pipeline is kept, so calling this from
	 * several entry points is safe. Completed, failed or stale records start over.
	 *
	 * @since 2.18.0
	 *
	 * @param int|object $membership Membership model or id.
	 * @param string     $pipeline   Registered pipeline id.
	 * @param array      $args {
	 *     Optional.
	 *
	 *     @type string $template    Template key (scopes the ETA history), e.g. "12".
	 *     @type string $step        First step; defaults to the pipeline's first step.
	 *     @type float  $at          Unix timestamp of the start; defaults to now.
	 *     @type int    $queue_ahead Runs ahead of this one, when known.
	 *     @type bool   $restart     Start over even if a run is in progress.
	 * }
	 * @return array|false The record, or false when the pipeline/membership is unknown.
	 */
	public static function start($membership, $pipeline, array $args = []) {

		$id         = self::membership_id($membership);
		$definition = self::get_pipeline($pipeline);

		if ($id <= 0 || ! $definition) {
			return false;
		}

		$existing = self::get($id);
		$template = isset($args['template']) ? substr(preg_replace('/[^a-z0-9_.:-]/i', '', (string) $args['template']), 0, 64) : '';

		if ($existing && empty($args['restart']) && 'running' === $existing['state'] && $existing['pipeline'] === $pipeline
			&& microtime(true) - (float) $existing['started'] < self::STALE_AFTER) {
			$existing_template = $existing['template'] ?? '';

			if ('' !== $template && $existing_template !== $template) {
				$existing['template'] = $template;
				self::save($id, $existing);
			}

			return $existing;
		}

		$steps = array_keys($definition['steps']);
		$step  = isset($args['step']) && in_array($args['step'], $steps, true) ? $args['step'] : $steps[0];
		$at    = self::timestamp($args['at'] ?? null);

		$record = [
			'schema'      => 1,
			'run'         => bin2hex(random_bytes(8)),
			'pipeline'    => $pipeline,
			'template'    => $template,
			'state'       => 'running',
			'started'     => $at,
			'step'        => $step,
			'marks'       => [
				[
					'step' => $step,
					'at'   => $at,
				],
			],
			'queue_ahead' => isset($args['queue_ahead']) ? max(0, (int) $args['queue_ahead']) : null,
			'retrying'    => false,
			'retried'     => false,
			'finished'    => null,
			'error'       => '',
		];

		self::save($id, $record);

		/**
		 * Fires when a provisioning run starts.
		 *
		 * @since 2.18.0
		 *
		 * @param int   $membership_id Membership id.
		 * @param array $record        Progress record.
		 */
		do_action('wu_provisioning_progress_started', $id, $record);

		return $record;
	}

	/**
	 * Moves a running record to a later step.
	 *
	 * Moving to the current or an earlier step does not rewind progress; pass
	 * `retrying` to tell the customer the step is being retried.
	 *
	 * @since 2.18.0
	 *
	 * @param int|object $membership Membership model or id.
	 * @param string     $step       Step key of the record's pipeline.
	 * @param array      $args {
	 *     Optional.
	 *
	 *     @type float $at          Unix timestamp the step started; defaults to now.
	 *     @type int   $queue_ahead Runs ahead of this one (null clears it).
	 *     @type bool  $retrying    Whether the current step is being retried.
	 * }
	 * @return array|false Updated record, or false without a running record.
	 */
	public static function step($membership, $step, array $args = []) {

		$id     = self::membership_id($membership);
		$record = self::get($id);

		if ( ! $record || 'running' !== $record['state']) {
			return false;
		}

		$steps   = array_keys(self::get_pipeline($record['pipeline'])['steps']);
		$target  = array_search($step, $steps, true);
		$current = array_search($record['step'], $steps, true);

		if (false === $target) {
			return false;
		}

		$changed = false;

		if (array_key_exists('queue_ahead', $args)) {
			$queue_ahead = null === $args['queue_ahead'] ? null : max(0, (int) $args['queue_ahead']);
			$changed     = $changed || $queue_ahead !== $record['queue_ahead'];

			$record['queue_ahead'] = $queue_ahead;
		}

		if (array_key_exists('retrying', $args)) {
			$retrying = (bool) $args['retrying'];
			$changed  = $changed || $retrying !== $record['retrying'];

			$record['retrying'] = $retrying;
			$record['retried']  = $record['retried'] || $retrying;
		}

		if (false === $current || $target > $current) {
			$last = end($record['marks']);
			$at   = self::timestamp($args['at'] ?? null, $last ? $last['at'] : null);

			if (count($record['marks']) < self::MAX_MARKS) {
				$record['marks'][] = [
					'step' => $step,
					'at'   => $at,
				];
			}

			$record['step']        = $step;
			$record['retrying']    = ! empty($args['retrying']);
			$record['queue_ahead'] = array_key_exists('queue_ahead', $args) ? $record['queue_ahead'] : null;

			$changed = true;
		}

		if ($changed) {
			self::save($id, $record);
		}

		return $record;
	}

	/**
	 * Marks a run as completed and records its durations in the history.
	 *
	 * @since 2.18.0
	 *
	 * @param int|object $membership Membership model or id.
	 * @param array      $args {
	 *     Optional.
	 *
	 *     @type float $at Unix timestamp the run finished; defaults to now.
	 * }
	 * @return array|false
	 */
	public static function complete($membership, array $args = []) {

		$id     = self::membership_id($membership);
		$record = self::get($id);

		if ( ! $record || 'running' !== $record['state']) {
			return false;
		}

		$last = end($record['marks']);

		$record['finished']    = self::timestamp($args['at'] ?? null, $last ? $last['at'] : null);
		$record['state']       = 'completed';
		$record['retrying']    = false;
		$record['queue_ahead'] = null;

		self::save($id, $record);

		/*
		 * Retried runs include time spent on a failure, which would skew the
		 * estimate for everyone else.
		 */
		if ( ! $record['retried']) {
			Provisioning_History::record($record['pipeline'], $record['template'], self::durations($record));
		}

		/**
		 * Fires when a provisioning run completes.
		 *
		 * @since 2.18.0
		 *
		 * @param int   $membership_id Membership id.
		 * @param array $record        Progress record.
		 */
		do_action('wu_provisioning_progress_completed', $id, $record);

		return $record;
	}

	/**
	 * Marks a run as failed.
	 *
	 * @since 2.18.0
	 *
	 * @param int|object $membership Membership model or id.
	 * @param array      $args {
	 *     Optional.
	 *
	 *     @type string $error Short machine-readable reason (never shown verbatim).
	 *     @type float  $at    Unix timestamp of the failure.
	 * }
	 * @return array|false
	 */
	public static function fail($membership, array $args = []) {

		$id     = self::membership_id($membership);
		$record = self::get($id);

		if ( ! $record || 'running' !== $record['state']) {
			return false;
		}

		$last = end($record['marks']);

		$record['finished']    = self::timestamp($args['at'] ?? null, $last ? $last['at'] : null);
		$record['state']       = 'failed';
		$record['retrying']    = false;
		$record['queue_ahead'] = null;
		$record['error']       = isset($args['error']) ? substr(preg_replace('/[^a-z0-9_.:-]/i', '', (string) $args['error']), 0, 64) : '';

		self::save($id, $record);

		/**
		 * Fires when a provisioning run fails.
		 *
		 * @since 2.18.0
		 *
		 * @param int   $membership_id Membership id.
		 * @param array $record        Progress record.
		 */
		do_action('wu_provisioning_progress_failed', $id, $record);

		return $record;
	}

	/**
	 * Seconds spent in each step of a record (re-entered steps are summed).
	 *
	 * @since 2.18.0
	 *
	 * @param array $record Progress record.
	 * @return array<string, float>
	 */
	public static function durations(array $record) {

		$durations = [];
		$marks     = array_values($record['marks'] ?? []);
		$end       = $record['finished'] ?? microtime(true);

		foreach ($marks as $index => $mark) {
			$until = isset($marks[ $index + 1 ]) ? $marks[ $index + 1 ]['at'] : $end;

			$durations[ $mark['step'] ] = ($durations[ $mark['step'] ] ?? 0) + max(0, (float) $until - (float) $mark['at']);
		}

		return $durations;
	}

	/**
	 * Public, customer-safe progress payload for status pollers.
	 *
	 * @since 2.18.0
	 *
	 * @param int|object $membership Membership model or id.
	 * @return array|null Null when no progress is tracked for the membership.
	 */
	public static function get_payload($membership) {

		$id = self::membership_id($membership);

		if ($id <= 0) {
			return null;
		}

		/**
		 * Lets integrations import progress reported outside WordPress (for
		 * example by a privileged provisioning worker) before it is read.
		 *
		 * @since 2.18.0
		 *
		 * @param int $membership_id Membership id.
		 */
		do_action('wu_provisioning_progress_before_read', $id);

		$record = self::get($id);

		if ( ! $record) {
			return null;
		}

		$pipeline = self::get_pipeline($record['pipeline']);
		$defaults = array_map(fn($step) => $step['duration'], $pipeline['steps']);
		$estimate = Provisioning_History::estimate($record['pipeline'], $record['template'], $defaults);
		$now      = microtime(true);
		$keys     = array_keys($pipeline['steps']);
		$index    = array_search($record['step'], $keys, true);
		$index    = false === $index ? 0 : $index;
		$last     = end($record['marks']);
		$elapsed  = max(0.0, ($record['finished'] ?? $now) - (float) ($last ? $last['at'] : $record['started']));

		$steps = [];
		$total = 0.0;
		$done  = 0.0;

		foreach ($keys as $position => $key) {
			$expected = (float) $estimate['durations'][ $key ];

			if ($position === $index) {
				// A step history says is skipped can still run; never expect zero.
				$expected = max($expected, 1.0);
			}

			$steps[] = [
				'key'      => $key,
				'label'    => $pipeline['steps'][ $key ]['label'],
				'expected' => round($expected, 1),
			];

			$total += $expected;

			if ($position < $index) {
				$done += $expected;
			}
		}

		$current   = $steps[ $index ]['expected'];
		$remaining = max(0.0, $current - $elapsed);

		foreach (array_slice($steps, $index + 1) as $step) {
			$remaining += $step['expected'];
		}

		$percent = $total > 0 ? ($done + min($elapsed, $current * 0.95)) / $total * 100 : 0;

		if ('completed' === $record['state']) {
			$percent   = 100;
			$remaining = 0;
		}

		$payload = [
			'run'             => $record['run'],
			'pipeline'        => $record['pipeline'],
			'state'           => $record['state'],
			'step'            => $record['step'],
			'step_index'      => $index + 1,
			'step_count'      => count($keys),
			'step_label'      => $steps[ $index ]['label'],
			'steps'           => $steps,
			'step_elapsed'    => round($elapsed, 1),
			'elapsed'         => round(max(0.0, ($record['finished'] ?? $now) - (float) $record['started']), 1),
			'eta'             => (int) ceil($remaining),
			'percent'         => 'completed' === $record['state'] ? 100 : (int) min(99, floor($percent)),
			'queue_ahead'     => $record['queue_ahead'],
			'retrying'        => (bool) $record['retrying'],
			'estimate_source' => $estimate['source'],
			'estimate_runs'   => $estimate['runs'],
			'server_time'     => round($now, 3),
		];

		/**
		 * Filters the progress payload returned to the thank-you page.
		 *
		 * @since 2.18.0
		 *
		 * @param array $payload       Payload.
		 * @param array $record        Stored record.
		 * @param int   $membership_id Membership id.
		 */
		return apply_filters('wu_provisioning_progress_payload', $payload, $record, $id);
	}

	/**
	 * Holds a finished run at its last step until the result is actually published.
	 *
	 * A run can complete a moment before the site/network is visible to the
	 * status endpoint (or, after an interruption, without it ever publishing).
	 * Customers must never see "Done" next to a pending site, so the caller's
	 * publication status wins over the progress record.
	 *
	 * @since 2.18.0
	 *
	 * @param array $payload Payload from get_payload().
	 * @return array
	 */
	public static function awaiting_publication(array $payload) {

		if ('completed' !== ($payload['state'] ?? '')) {
			return $payload;
		}

		$steps = isset($payload['steps']) && is_array($payload['steps']) ? $payload['steps'] : [];
		$last  = $steps ? $steps[ count($steps) - 1 ] : [];

		$payload['state']        = 'running';
		$payload['percent']      = 99;
		$payload['eta']          = 0;
		$payload['step_index']   = (int) ($payload['step_count'] ?? 1);
		$payload['step']         = (string) ($last['key'] ?? $payload['step']);
		$payload['step_label']   = (string) ($last['label'] ?? $payload['step_label']);
		$payload['step_elapsed'] = (float) ($last['expected'] ?? 0);

		return $payload;
	}

	/**
	 * Marks a membership's provisioning as running in this request.
	 *
	 * @since 2.18.0
	 *
	 * @param int|object $membership Membership model or id.
	 * @return void
	 */
	public static function push_context($membership) {

		self::$context[] = self::membership_id($membership);
	}

	/**
	 * Ends the innermost provisioning context.
	 *
	 * @since 2.18.0
	 * @return void
	 */
	public static function pop_context() {

		array_pop(self::$context);
	}

	/**
	 * Membership whose provisioning is running in this request, if any.
	 *
	 * @since 2.18.0
	 * @return int
	 */
	public static function current_membership_id() {

		return (int) (end(self::$context) ?: 0);
	}

	/**
	 * Reports a step for the provisioning running in this request.
	 *
	 * Deep code (site duplication, network installers) does not know the
	 * membership; it reports through the context its caller opened. Without
	 * a context, or for a different pipeline, this does nothing.
	 *
	 * @since 2.18.0
	 *
	 * @param string $step     Step key.
	 * @param string $pipeline Only apply to a record of this pipeline ('' for any).
	 * @return void
	 */
	public static function step_current($step, $pipeline = '') {

		$id = self::current_membership_id();

		if ($id <= 0) {
			return;
		}

		$record = self::get($id);

		if ($record && ('' === $pipeline || $record['pipeline'] === $pipeline)) {
			self::step($id, $step);
		}
	}

	/**
	 * Maps core's existing site-creation profiling stages to "site" steps.
	 *
	 * Called when a stage finishes, so it moves to the step that follows it.
	 *
	 * @since 2.18.0
	 *
	 * @param string $stage Profiling stage name, e.g. "um_duplicator.copy_data".
	 * @return void
	 */
	public static function observe_stage($stage) {

		if (self::current_membership_id() <= 0) {
			return;
		}

		/**
		 * Filters which "site" step starts when a profiled stage finishes.
		 *
		 * @since 2.18.0
		 *
		 * @param array $map Stage name => next step key.
		 */
		$map = apply_filters(
			'wu_provisioning_site_stage_steps',
			[
				'um_duplicator.wpmu_create_blog' => 'copy_files',
				'um_duplicator.copy_files'       => 'copy_data',
				'um_duplicator.copy_data'        => 'configuring',
				'um_site.save_duplicate_site'    => 'finishing',
				'um_site.save_wpmu_create_blog'  => 'finishing',
			]
		);

		if (isset($map[ $stage ])) {
			self::step_current($map[ $stage ], 'site');
		}
	}
}
