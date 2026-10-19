<?php
/**
 * Tests for customer-facing provisioning progress and its ETA history.
 *
 * @package WP_Ultimo
 * @subpackage Tests
 */

namespace WP_Ultimo\Tests\Provisioning;

use WP_Ultimo\Database\Memberships\Membership_Status;
use WP_Ultimo\Provisioning\Provisioning_History;
use WP_Ultimo\Provisioning\Provisioning_Progress;

/**
 * Provisioning progress tests.
 */
class Provisioning_Progress_Test extends \WP_UnitTestCase {

	/**
	 * Test customer.
	 *
	 * @var \WP_Ultimo\Models\Customer
	 */
	private $customer;

	/**
	 * Test product.
	 *
	 * @var \WP_Ultimo\Models\Product
	 */
	private $product;

	/**
	 * Registers a deterministic test pipeline and clears history.
	 */
	public function setUp(): void {

		parent::setUp();

		delete_network_option(get_main_network_id(), Provisioning_History::OPTION);

		add_filter('wu_provisioning_pipelines', [$this, 'register_test_pipeline']);

		$uid = 'progress' . wp_rand();

		$this->customer = wu_create_customer(
			[
				'username' => $uid,
				'email'    => $uid . '@example.com',
				'password' => 'password123',
			]
		);

		$this->product = wu_create_product(
			[
				'name'          => 'Progress Plan',
				'slug'          => 'progress-plan-' . wp_rand(),
				'type'          => 'plan',
				'amount'        => 10,
				'duration'      => 1,
				'duration_unit' => 'month',
				'pricing_type'  => 'paid',
			]
		);
	}

	/**
	 * Removes fixtures.
	 */
	public function tearDown(): void {

		remove_filter('wu_provisioning_pipelines', [$this, 'register_test_pipeline']);
		delete_network_option(get_main_network_id(), Provisioning_History::OPTION);

		if ($this->customer && ! is_wp_error($this->customer)) {
			$this->customer->delete();
		}

		if ($this->product && ! is_wp_error($this->product)) {
			$this->product->delete();
		}

		parent::tearDown();
	}

	/**
	 * Three-step pipeline with 10/20/30 second defaults.
	 *
	 * @param array $pipelines Registered pipelines.
	 * @return array
	 */
	public function register_test_pipeline($pipelines) {

		$pipelines['test'] = [
			'label' => 'Test',
			'steps' => [
				'one'   => [
					'label'    => 'One',
					'duration' => 10,
				],
				'two'   => [
					'label'    => 'Two',
					'duration' => 20,
				],
				'three' => [
					'label'    => 'Three',
					'duration' => 30,
				],
			],
		];

		return $pipelines;
	}

	/**
	 * Creates a persisted membership.
	 *
	 * @return \WP_Ultimo\Models\Membership
	 */
	private function membership() {

		$membership = wu_create_membership(
			[
				'customer_id'     => $this->customer->get_id(),
				'plan_id'         => $this->product->get_id(),
				'status'          => Membership_Status::ACTIVE,
				'amount'          => 10,
				'currency'        => 'USD',
				'skip_validation' => true,
			]
		);

		$this->assertNotWPError($membership);

		return $membership;
	}

	/**
	 * Completes a run whose steps took the given seconds.
	 *
	 * @param string $template Template key.
	 * @param array  $seconds  Step => seconds.
	 * @return void
	 */
	private function completed_run($template, array $seconds) {

		$membership = $this->membership();
		$at         = microtime(true) - array_sum($seconds) - 1;

		wu_provisioning_progress_start(
			$membership,
			'test',
			[
				'template' => $template,
				'at'       => $at,
			]
		);

		foreach (array_slice(array_keys($seconds), 1, null, true) as $position => $step) {
			$at += array_values($seconds)[ $position - 1 ];
			wu_provisioning_progress_step($membership, $step, ['at' => $at]);
		}

		wu_provisioning_progress_complete($membership, ['at' => $at + end($seconds)]);
	}

	/**
	 * One slow run must not skew the average.
	 */
	public function test_average_ignores_runs_over_three_times_the_median(): void {

		$this->assertEqualsWithDelta(11.0, Provisioning_History::average([10, 11, 12, 100]), 0.001);
		$this->assertEqualsWithDelta(0.0, Provisioning_History::average([]), 0.001);
		$this->assertEqualsWithDelta(0.0, Provisioning_History::average([0, 0, 0]), 0.001);
	}

	/**
	 * Estimates fall back from the template, to the pipeline, to the defaults.
	 */
	public function test_estimate_falls_back_from_template_to_pipeline_to_defaults(): void {

		$defaults = [
			'one'   => 10,
			'two'   => 20,
			'three' => 30,
		];

		$estimate = Provisioning_History::estimate('test', 'big', $defaults);
		$this->assertSame('default', $estimate['source']);
		$this->assertEqualsWithDelta(20.0, $estimate['durations']['two'], 0.001);

		foreach ([4, 5, 6] as $seconds) {
			Provisioning_History::record(
				'test',
				'small',
				[
					'one'   => $seconds,
					'two'   => $seconds,
					'three' => $seconds,
				]
			);
		}

		// Only one run of "big": the pipeline-wide average is used.
		Provisioning_History::record(
			'test',
			'big',
			[
				'one'   => 50,
				'two'   => 50,
				'three' => 50,
			]
		);

		$estimate = Provisioning_History::estimate('test', 'big', $defaults);
		$this->assertSame('pipeline', $estimate['source']);
		$this->assertSame(4, $estimate['runs']);

		// Three runs of "small": its own history wins, and a step it never ran counts as zero.
		Provisioning_History::record(
			'test',
			'small',
			[
				'one' => 5,
				'two' => 5,
			]
		);

		$estimate = Provisioning_History::estimate('test', 'small', $defaults);
		$this->assertSame('template', $estimate['source']);
		$this->assertEqualsWithDelta(5.0, $estimate['durations']['one'], 0.001);
		$this->assertEqualsWithDelta(3.75, $estimate['durations']['three'], 0.001);
	}

	/**
	 * Only the last runs are kept.
	 */
	public function test_history_keeps_the_last_twenty_runs(): void {

		for ($run = 1; $run <= 25; $run++) {
			Provisioning_History::record('test', 't', ['one' => $run]);
		}

		$history = Provisioning_History::get_all(true);

		$this->assertCount(Provisioning_History::MAX_RUNS, $history['test']['all']);
		$this->assertCount(Provisioning_History::MAX_RUNS, $history['test']['templates']['t']['runs']);
		$this->assertEqualsWithDelta(6.0, $history['test']['all'][0]['one'], 0.001);
	}

	/**
	 * A full run records its step durations and reports 100%.
	 */
	public function test_completed_run_records_history_and_reports_done(): void {

		$this->completed_run(
			'tpl',
			[
				'one'   => 2,
				'two'   => 4,
				'three' => 6,
			]
		);

		$history = Provisioning_History::get_all(true);
		$run     = $history['test']['templates']['tpl']['runs'][0];

		$this->assertEqualsWithDelta(2.0, $run['one'], 0.2);
		$this->assertEqualsWithDelta(4.0, $run['two'], 0.2);
		$this->assertEqualsWithDelta(6.0, $run['three'], 0.2);
	}

	/**
	 * The payload reports step x of y and sums the remaining expected time.
	 */
	public function test_payload_reports_step_eta_and_percent(): void {

		$membership = $this->membership();

		wu_provisioning_progress_start($membership, 'test', ['at' => microtime(true) - 15]);
		wu_provisioning_progress_step($membership, 'two', ['at' => microtime(true) - 5]);

		$payload = wu_get_provisioning_progress($membership);

		$this->assertSame('running', $payload['state']);
		$this->assertSame(2, $payload['step_index']);
		$this->assertSame(3, $payload['step_count']);
		$this->assertSame('Two', $payload['step_label']);
		$this->assertSame('default', $payload['estimate_source']);
		// 15s left of "two" plus 30s of "three".
		$this->assertEqualsWithDelta(45, $payload['eta'], 1);
		// (10 done + 5 elapsed) of 60 expected.
		$this->assertEqualsWithDelta(25, $payload['percent'], 1);

		wu_provisioning_progress_complete($membership);

		$payload = wu_get_provisioning_progress($membership);

		$this->assertSame('completed', $payload['state']);
		$this->assertSame(100, $payload['percent']);
		$this->assertSame(0, $payload['eta']);
	}

	/**
	 * Progress never rewinds, and a retried run is not recorded in history.
	 */
	public function test_steps_do_not_rewind_and_retried_runs_are_not_recorded(): void {

		$membership = $this->membership();

		wu_provisioning_progress_start($membership, 'test');
		wu_provisioning_progress_step($membership, 'three');
		wu_provisioning_progress_step($membership, 'one', ['retrying' => true]);

		$record = Provisioning_Progress::get($membership);

		$this->assertSame('three', $record['step']);
		$this->assertTrue($record['retrying']);
		$this->assertTrue(wu_get_provisioning_progress($membership)['retrying']);

		wu_provisioning_progress_complete($membership);

		$this->assertSame([], Provisioning_History::get_all(true));
	}

	/**
	 * Starting again keeps a running run but replaces a finished one.
	 */
	public function test_start_keeps_running_run_and_replaces_finished_run(): void {

		$membership = $this->membership();

		$first = wu_provisioning_progress_start($membership, 'test');
		$again = wu_provisioning_progress_start($membership, 'test');

		$this->assertSame($first['run'], $again['run']);

		wu_provisioning_progress_fail($membership, ['error' => 'boom!']);

		$this->assertSame('failed', wu_get_provisioning_progress($membership)['state']);
		$this->assertSame('boom', Provisioning_Progress::get($membership)['error']);

		$next = wu_provisioning_progress_start($membership, 'test');

		$this->assertNotSame($first['run'], $next['run']);
		$this->assertSame('running', $next['state']);
	}

	/**
	 * Unknown pipelines and steps are ignored.
	 */
	public function test_unknown_pipeline_and_step_are_rejected(): void {

		$membership = $this->membership();

		$this->assertFalse(wu_provisioning_progress_start($membership, 'missing'));
		$this->assertNull(wu_get_provisioning_progress($membership));

		wu_provisioning_progress_start($membership, 'test');

		$this->assertFalse(wu_provisioning_progress_step($membership, 'nope'));
	}

	/**
	 * Deep code reports through the context opened by its caller only.
	 */
	public function test_stage_observation_requires_an_open_context(): void {

		$membership = $this->membership();

		wu_provisioning_progress_start($membership, 'site');

		Provisioning_Progress::observe_stage('um_duplicator.copy_files');
		$this->assertSame('queued', Provisioning_Progress::get($membership)['step']);

		wu_with_provisioning_progress(
			$membership,
			function () {
				Provisioning_Progress::observe_stage('um_duplicator.copy_files');
			}
		);

		$this->assertSame('copy_data', Provisioning_Progress::get($membership)['step']);
		$this->assertSame(0, Provisioning_Progress::current_membership_id());
	}

	/**
	 * Status responses gain a progress key only when progress is tracked.
	 */
	public function test_status_response_adds_progress_only_when_tracked(): void {

		$membership = $this->membership();
		$manager    = \WP_Ultimo\Managers\Membership_Manager::get_instance();
		$status     = ['publish_status' => 'running'];

		$this->assertSame($status, $manager->with_provisioning_progress($status, $membership));

		wu_provisioning_progress_start($membership, 'test');

		$response = $manager->with_provisioning_progress($status, $membership);

		$this->assertSame('running', $response['publish_status']);
		$this->assertSame('one', $response['progress']['step']);
	}

	/**
	 * A finished run is never "Done" while the site is still unpublished.
	 */
	public function test_completed_run_waits_for_publication(): void {

		$membership = $this->membership();
		$manager    = \WP_Ultimo\Managers\Membership_Manager::get_instance();

		wu_provisioning_progress_start($membership, 'test');
		wu_provisioning_progress_complete($membership);

		$waiting = $manager->with_provisioning_progress(['publish_status' => 'running'], $membership)['progress'];

		$this->assertSame('running', $waiting['state']);
		$this->assertSame('three', $waiting['step']);
		$this->assertSame(3, $waiting['step_index']);
		$this->assertSame(99, $waiting['percent']);

		$done = $manager->with_provisioning_progress(['publish_status' => 'completed'], $membership)['progress'];

		$this->assertSame('completed', $done['state']);
		$this->assertSame(100, $done['percent']);
	}

	/**
	 * Publishing a pending site reports the "site" pipeline through to completion.
	 */
	public function test_publishing_a_pending_site_completes_the_site_pipeline(): void {

		$membership = $this->membership();
		$slug       = 'progress' . substr((string) (microtime(true) * 1000), -8);

		$membership->create_pending_site(
			[
				'title' => 'Progress ' . $slug,
				'path'  => '/' . $slug . '/',
			]
		);

		$this->assertTrue($membership->publish_pending_site());

		$record = Provisioning_Progress::get($membership);

		$this->assertSame('site', $record['pipeline']);
		$this->assertSame('completed', $record['state']);
		$this->assertSame('blank', $record['template']);
		$this->assertContains('creating', array_column($record['marks'], 'step'));
		$this->assertContains('finishing', array_column($record['marks'], 'step'));

		$history = Provisioning_History::get_all(true);

		$this->assertArrayHasKey('blank', $history['site']['templates']);
	}
}
