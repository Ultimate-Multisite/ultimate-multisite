<?php
/**
 * Provisioning duration history used for progress estimates.
 *
 * @package WP_Ultimo
 * @subpackage Provisioning
 * @since 2.18.0
 */

namespace WP_Ultimo\Provisioning;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Stores per-step durations of successful provisioning runs on this install.
 *
 * Estimates therefore reflect the hosting the plugin actually runs on: the
 * average of each step over the last runs of the same pipeline and template,
 * falling back to every template of the pipeline, then to registered defaults.
 *
 * Only server-side completions call record(); nothing a customer sends can
 * change the averages.
 *
 * @since 2.18.0
 */
class Provisioning_History {

	/**
	 * Network option holding the history on the main network.
	 *
	 * @since 2.18.0
	 * @var string
	 */
	const OPTION = 'wu_provisioning_history';

	/**
	 * Runs kept per template and per pipeline.
	 *
	 * @since 2.18.0
	 * @var int
	 */
	const MAX_RUNS = 20;

	/**
	 * Templates kept per pipeline (least recently used are dropped).
	 *
	 * @since 2.18.0
	 * @var int
	 */
	const MAX_TEMPLATES = 50;

	/**
	 * Runs needed before a history level is trusted.
	 *
	 * @since 2.18.0
	 * @var int
	 */
	const MIN_RUNS = 3;

	/**
	 * Runs slower than this multiple of the median are ignored.
	 *
	 * @since 2.18.0
	 * @var int
	 */
	const OUTLIER_FACTOR = 3;

	/**
	 * Returns the stored history.
	 *
	 * @since 2.18.0
	 *
	 * @param bool $fresh Bypass the object cache (used before writing).
	 * @return array
	 */
	public static function get_all($fresh = false) {

		$network_id = get_main_network_id();

		if ($fresh) {
			wp_cache_delete($network_id . ':' . self::OPTION, 'site-options');
		}

		$history = get_network_option($network_id, self::OPTION, []);

		return is_array($history) ? $history : [];
	}

	/**
	 * Records the step durations of one successful run.
	 *
	 * @since 2.18.0
	 *
	 * @param string $pipeline  Pipeline id.
	 * @param string $template  Template key ('' when the pipeline has none).
	 * @param array  $durations Map of step key => seconds.
	 * @return bool
	 */
	public static function record($pipeline, $template, array $durations) {

		$run = [];

		foreach ($durations as $step => $seconds) {
			if (is_string($step) && is_numeric($seconds) && $seconds >= 0 && $seconds <= DAY_IN_SECONDS) {
				$run[ $step ] = round((float) $seconds, 1);
			}
		}

		if ( ! $run || ! is_string($pipeline) || '' === $pipeline) {
			return false;
		}

		$history = self::get_all(true);
		$entry   = isset($history[ $pipeline ]) && is_array($history[ $pipeline ]) ? $history[ $pipeline ] : [];
		$all     = isset($entry['all']) && is_array($entry['all']) ? $entry['all'] : [];
		$all[]   = $run;

		$entry['all'] = array_slice($all, -self::MAX_RUNS);

		if ('' !== $template) {
			$templates = isset($entry['templates']) && is_array($entry['templates']) ? $entry['templates'] : [];
			$current   = isset($templates[ $template ]['runs']) && is_array($templates[ $template ]['runs']) ? $templates[ $template ]['runs'] : [];
			$current[] = $run;

			$templates[ $template ] = [
				'runs'    => array_slice($current, -self::MAX_RUNS),
				'updated' => time(),
			];

			if (count($templates) > self::MAX_TEMPLATES) {
				uasort($templates, fn($a, $b) => ($b['updated'] ?? 0) <=> ($a['updated'] ?? 0));
				$templates = array_slice($templates, 0, self::MAX_TEMPLATES, true);
			}

			$entry['templates'] = $templates;
		}

		$history[ $pipeline ] = $entry;

		return update_network_option(get_main_network_id(), self::OPTION, $history);
	}

	/**
	 * Average that ignores runs over OUTLIER_FACTOR times the median.
	 *
	 * @since 2.18.0
	 *
	 * @param float[] $values Durations in seconds.
	 * @return float
	 */
	public static function average(array $values) {

		$values = array_values(array_filter(array_map('floatval', $values), fn($value) => $value >= 0));

		if ( ! $values) {
			return 0.0;
		}

		sort($values);

		$count  = count($values);
		$middle = intdiv($count, 2);
		$median = $count % 2 ? $values[ $middle ] : ($values[ $middle - 1 ] + $values[ $middle ]) / 2;

		if ($median > 0) {
			$values = array_filter($values, fn($value) => $value <= $median * self::OUTLIER_FACTOR);
		}

		return array_sum($values) / count($values);
	}

	/**
	 * Expected duration of every step of a pipeline.
	 *
	 * @since 2.18.0
	 *
	 * @param string $pipeline Pipeline id.
	 * @param string $template Template key.
	 * @param array  $defaults Map of step key => default seconds, in order.
	 * @return array{durations: array<string, float>, source: string, runs: int}
	 */
	public static function estimate($pipeline, $template, array $defaults) {

		$history = self::get_all();
		$entry   = isset($history[ $pipeline ]) && is_array($history[ $pipeline ]) ? $history[ $pipeline ] : [];
		$runs    = [];
		$source  = 'default';

		$template_runs = '' !== $template && isset($entry['templates'][ $template ]['runs']) ? $entry['templates'][ $template ]['runs'] : [];

		if (is_array($template_runs) && count($template_runs) >= self::MIN_RUNS) {
			$runs   = $template_runs;
			$source = 'template';
		} elseif (isset($entry['all']) && is_array($entry['all']) && count($entry['all']) >= self::MIN_RUNS) {
			$runs   = $entry['all'];
			$source = 'pipeline';
		}

		$durations = [];

		foreach ($defaults as $step => $default) {
			if ( ! $runs) {
				$durations[ $step ] = (float) $default;
				continue;
			}

			// A step a run never reached took no time in that run (for example a
			// template without media skips the file copy).
			$durations[ $step ] = self::average(array_map(fn($run) => is_array($run) ? (float) ($run[ $step ] ?? 0) : 0.0, $runs));
		}

		return [
			'durations' => $durations,
			'source'    => $source,
			'runs'      => count($runs),
		];
	}
}
