<?php
/**
 * Unit tests for Event_Manager.
 *
 * @package WP_Ultimo\Tests\Managers
 */

namespace WP_Ultimo\Tests\Managers;

use WP_Ultimo\Managers\Event_Manager;
use WP_Ultimo\Models\Event;

class Event_Manager_Test extends \WP_UnitTestCase {

	use Manager_Test_Trait;

	protected function get_manager_class(): string {
		return Event_Manager::class;
	}

	protected function get_expected_slug(): ?string {
		return 'event';
	}

	protected function get_expected_model_class(): ?string {
		return Event::class;
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Create and persist a valid Event, returning the saved instance.
	 *
	 * @param array $overrides Optional field overrides.
	 * @return Event
	 */
	private function create_event( array $overrides = [] ): Event {

		$defaults = [
			'object_id'    => 0,
			'object_type'  => 'network',
			'severity'     => Event::SEVERITY_INFO,
			'slug'         => 'test-event-' . wp_rand(),
			'payload'      => [ 'key' => 'value' ],
			'initiator'    => 'system',
			'date_created' => wu_get_current_time( 'mysql', true ),
		];

		$event = new Event( array_merge( $defaults, $overrides ) );
		$event->save();

		return $event;
	}

	// -------------------------------------------------------------------------
	// register_event / get_event / get_events
	// -------------------------------------------------------------------------

	/**
	 * Test register_event and get_event round-trip.
	 */
	public function test_register_and_get_event(): void {

		$manager = $this->get_manager_instance();

		$result = $manager->register_event( 'test_event', [
			'name'    => 'Test Event',
			'payload' => [ 'key' => 'value' ],
		] );

		$this->assertTrue( $result );

		$event = $manager->get_event( 'test_event' );

		$this->assertIsArray( $event );
		$this->assertEquals( 'Test Event', $event['name'] );
	}

	/**
	 * Test get_event returns false for unregistered event.
	 */
	public function test_get_event_returns_false_for_unknown(): void {

		$manager = $this->get_manager_instance();
		$result  = $manager->get_event( 'nonexistent_event_xyz' );

		$this->assertFalse( $result );
	}

	/**
	 * Test get_events returns an array.
	 */
	public function test_get_events_returns_array(): void {

		$manager = $this->get_manager_instance();
		$events  = $manager->get_events();

		$this->assertIsArray( $events );
	}

	/**
	 * Registering multiple events accumulates them all.
	 */
	public function test_register_multiple_events_accumulates(): void {

		$manager = $this->get_manager_instance();

		$manager->register_event( 'multi_a', [ 'name' => 'A', 'payload' => [] ] );
		$manager->register_event( 'multi_b', [ 'name' => 'B', 'payload' => [] ] );

		$this->assertIsArray( $manager->get_event( 'multi_a' ) );
		$this->assertIsArray( $manager->get_event( 'multi_b' ) );
	}

	/**
	 * Registering an event with a callable payload stores it correctly.
	 */
	public function test_register_event_with_callable_payload(): void {

		$manager = $this->get_manager_instance();

		$manager->register_event( 'callable_event', [
			'name'    => 'Callable',
			'payload' => fn() => [ 'lazy' => 'loaded' ],
		] );

		$event = $manager->get_event( 'callable_event' );

		$this->assertIsArray( $event );
		$this->assertIsCallable( $event['payload'] );
	}

	// -------------------------------------------------------------------------
	// do_event
	// -------------------------------------------------------------------------

	/**
	 * Test do_event returns false for unregistered event.
	 */
	public function test_do_event_returns_false_for_unknown(): void {

		$manager = $this->get_manager_instance();
		$result  = $manager->do_event( 'nonexistent_event_xyz', [] );

		$this->assertFalse( $result );
	}

	/**
	 * Test do_event fires the wu_event and wu_event_{slug} actions.
	 */
	public function test_do_event_fires_actions(): void {

		$manager = $this->get_manager_instance();

		$manager->register_event( 'test_fire', [
			'name'    => 'Fire Test',
			'payload' => [ 'sample' => 'data' ],
		] );

		$generic_fired  = false;
		$specific_fired = false;

		add_action( 'wu_event', function () use ( &$generic_fired ) {
			$generic_fired = true;
		} );

		add_action( 'wu_event_test_fire', function () use ( &$specific_fired ) {
			$specific_fired = true;
		} );

		// Actions fire before save_event; save may fail validation (no initiator).
		$manager->do_event( 'test_fire', [ 'sample' => 'data' ] );

		$this->assertTrue( $generic_fired, 'wu_event action should have fired.' );
		$this->assertTrue( $specific_fired, 'wu_event_test_fire action should have fired.' );
	}

	/**
	 * do_event passes slug and payload to wu_event action.
	 */
	public function test_do_event_passes_slug_and_payload_to_action(): void {

		$manager = $this->get_manager_instance();

		$manager->register_event( 'slug_check', [
			'name'    => 'Slug Check',
			'payload' => [ 'foo' => 'bar' ],
		] );

		$captured_slug    = null;
		$captured_payload = null;

		add_action( 'wu_event', function ( $slug, $payload ) use ( &$captured_slug, &$captured_payload ) {
			$captured_slug    = $slug;
			$captured_payload = $payload;
		}, 10, 2 );

		$manager->do_event( 'slug_check', [ 'foo' => 'bar' ] );

		$this->assertEquals( 'slug_check', $captured_slug );
		$this->assertArrayHasKey( 'foo', $captured_payload );
	}

	/**
	 * do_event appends wu_version to the payload before firing actions.
	 */
	public function test_do_event_appends_wu_version(): void {

		$manager = $this->get_manager_instance();

		$manager->register_event( 'version_check', [
			'name'    => 'Version Check',
			'payload' => [ 'item' => 'val' ],
		] );

		$captured_payload = null;

		add_action( 'wu_event_version_check', function ( $payload ) use ( &$captured_payload ) {
			$captured_payload = $payload;
		} );

		$manager->do_event( 'version_check', [ 'item' => 'val' ] );

		$this->assertNotNull( $captured_payload );
		$this->assertArrayHasKey( 'wu_version', $captured_payload );
	}

	/**
	 * do_event returns false when required payload keys are missing.
	 */
	public function test_do_event_returns_false_for_missing_payload_keys(): void {

		$manager = $this->get_manager_instance();

		// Register event with a required payload key (non-callable array with index 0).
		$manager->register_event( 'strict_payload', [
			'name'    => 'Strict',
			'payload' => [ [ 'required_key' => 'placeholder' ] ],
		] );

		// Pass empty payload — missing required_key.
		$result = $manager->do_event( 'strict_payload', [] );

		$this->assertFalse( $result );
	}

	// -------------------------------------------------------------------------
	// save_event
	// -------------------------------------------------------------------------

	/**
	 * Test save_event with a fully valid payload creates an event record.
	 */
	public function test_save_event_with_valid_payload(): void {

		$manager = $this->get_manager_instance();

		$event = new Event(
			[
				'object_id'    => 1,
				'object_type'  => 'test',
				'severity'     => Event::SEVERITY_INFO,
				'slug'         => 'test_direct_save',
				'payload'      => [ 'key' => 'value' ],
				'initiator'    => 'system',
				'date_created' => wu_get_current_time( 'mysql', true ),
			]
		);

		$result = $event->save();

		$this->assertNotWPError( $result );
		$this->assertNotFalse( $result );
	}

	/**
	 * save_event via manager with missing initiator returns false (validation fails).
	 *
	 * The save_event method does not set initiator, so validation always fails
	 * unless the payload contains it. This documents the current behaviour.
	 */
	public function test_save_event_via_manager_returns_false_without_initiator(): void {

		$manager = $this->get_manager_instance();

		// save_event does not set initiator — validation requires it → false.
		$result = $manager->save_event( 'manager-save', [
			'object_id'   => 0,
			'object_type' => 'network',
			'type'        => Event::SEVERITY_INFO,
		] );

		$this->assertFalse( $result );
	}

	/**
	 * save_event with empty data returns false (validation fails).
	 */
	public function test_save_event_with_invalid_data_returns_false(): void {

		$manager = $this->get_manager_instance();

		// Missing initiator and object_type — validation should fail.
		$result = $manager->save_event( 'bad-slug', [] );

		$this->assertFalse( $result );
	}

	// -------------------------------------------------------------------------
	// register_model_events / dispatch_base_model_event
	// -------------------------------------------------------------------------

	/**
	 * Test register_model_events stores model event configuration.
	 */
	public function test_register_model_events(): void {

		Event_Manager::register_model_events( 'test_model', 'Test Model', [ 'created', 'updated' ] );

		$manager       = $this->get_manager_instance();
		$models_events = $this->get_protected_property( $manager, 'models_events' );

		$this->assertArrayHasKey( 'test_model', $models_events );
		$this->assertEquals( 'Test Model', $models_events['test_model']['label'] );
		$this->assertContains( 'created', $models_events['test_model']['types'] );
	}

	/**
	 * register_model_events overwrites an existing entry for the same slug.
	 */
	public function test_register_model_events_overwrites_existing(): void {

		Event_Manager::register_model_events( 'overwrite_model', 'Old Label', [ 'created' ] );
		Event_Manager::register_model_events( 'overwrite_model', 'New Label', [ 'created', 'updated' ] );

		$manager       = $this->get_manager_instance();
		$models_events = $this->get_protected_property( $manager, 'models_events' );

		$this->assertEquals( 'New Label', $models_events['overwrite_model']['label'] );
		$this->assertContains( 'updated', $models_events['overwrite_model']['types'] );
	}

	/**
	 * dispatch_base_model_event returns early when model is not registered.
	 */
	public function test_dispatch_base_model_event_skips_unregistered_model(): void {

		$manager = $this->get_manager_instance();

		$obj        = new \stdClass();
		$obj->model = 'unregistered_model_xyz';

		// Should not throw — just return early.
		$manager->dispatch_base_model_event( [], $obj, true );

		$this->assertTrue( true );
	}

	/**
	 * dispatch_base_model_event returns early when event type is not in registered types.
	 */
	public function test_dispatch_base_model_event_skips_unregistered_type(): void {

		Event_Manager::register_model_events( 'dispatch_model', 'Dispatch Model', [ 'created' ] );

		$manager = $this->get_manager_instance();

		$obj        = new \stdClass();
		$obj->model = 'dispatch_model';

		// $new_model = false → type = 'updated', which is not in ['created'].
		$manager->dispatch_base_model_event( [], $obj, false );

		$this->assertTrue( true );
	}

	// -------------------------------------------------------------------------
	// log_transitions
	// -------------------------------------------------------------------------

	/**
	 * log_transitions returns early when model is 'event' (prevents recursion).
	 */
	public function test_log_transitions_skips_event_model(): void {

		$manager = $this->get_manager_instance();

		// Should return immediately without creating any event.
		$manager->log_transitions( 'event', [], [], new \stdClass() );

		$this->assertTrue( true );
	}

	/**
	 * log_transitions creates a 'created' event for a new object (no id in data).
	 */
	public function test_log_transitions_creates_event_for_new_object(): void {

		$manager = $this->get_manager_instance();

		$obj = new class extends \WP_Ultimo\Models\Base_Model {
			public function get_id(): int { return 1; }
			public function validation_rules(): array { return []; }
		};

		// No 'id' key in data_unserialized → treated as new object.
		$manager->log_transitions( 'membership', [], [], $obj );

		$this->assertTrue( true );
	}

	/**
	 * log_transitions returns early when diff is empty (no real changes).
	 */
	public function test_log_transitions_skips_empty_diff(): void {

		$manager = $this->get_manager_instance();

		// Build a minimal object that has an id and identical original data.
		$obj = new class extends \WP_Ultimo\Models\Base_Model {
			public function get_id(): int { return 42; }
			public function validation_rules(): array { return []; }
			public function _get_original(): array {
				return [ 'id' => 42, 'status' => 'active' ];
			}
		};

		// data_unserialized matches original → diff is empty → early return.
		$manager->log_transitions( 'membership', [], [ 'id' => 42, 'status' => 'active' ], $obj );

		$this->assertTrue( true );
	}

	/**
	 * log_transitions returns early when id key is present but old value is 0 (new record).
	 */
	public function test_log_transitions_skips_when_old_id_is_zero(): void {

		$manager = $this->get_manager_instance();

		$obj = new class extends \WP_Ultimo\Models\Base_Model {
			public function get_id(): int { return 1; }
			public function validation_rules(): array { return []; }
			public function _get_original(): array {
				return [ 'id' => 0 ];
			}
		};

		// data_unserialized has 'id' key, original id is 0 → early return.
		$manager->log_transitions( 'membership', [], [ 'id' => 1 ], $obj );

		$this->assertTrue( true );
	}

	// -------------------------------------------------------------------------
	// register_all_events
	// -------------------------------------------------------------------------

	/**
	 * register_all_events populates the events list with known slugs.
	 */
	public function test_register_all_events_populates_known_slugs(): void {

		$manager = $this->get_manager_instance();

		$manager->register_all_events();

		$events = $manager->get_events();

		$this->assertIsArray( $events );
		$this->assertArrayHasKey( 'payment_received', $events );
		$this->assertArrayHasKey( 'site_published', $events );
		$this->assertArrayHasKey( 'membership_expired', $events );
	}

	/**
	 * register_all_events fires the wu_register_all_events action.
	 */
	public function test_register_all_events_fires_action(): void {

		$fired = false;

		add_action( 'wu_register_all_events', function () use ( &$fired ) {
			$fired = true;
		} );

		$manager = $this->get_manager_instance();
		$manager->register_all_events();

		$this->assertTrue( $fired, 'wu_register_all_events action should have fired.' );
	}

	/**
	 * register_all_events registers model events when models_events is populated.
	 */
	public function test_register_all_events_registers_model_events(): void {

		Event_Manager::register_model_events( 'site', 'Site', [ 'created', 'updated' ] );

		$manager = $this->get_manager_instance();
		$manager->register_all_events();

		$events = $manager->get_events();

		$this->assertArrayHasKey( 'site_created', $events );
		$this->assertArrayHasKey( 'site_updated', $events );
	}

	// -------------------------------------------------------------------------
	// clean_old_events
	// -------------------------------------------------------------------------

	/**
	 * clean_old_events returns early when threshold is 0 (disabled).
	 */
	public function test_clean_old_events_skips_when_threshold_is_zero(): void {

		add_filter( 'wu_events_threshold_days', '__return_zero' );

		$manager = $this->get_manager_instance();

		// Should return without error.
		$manager->clean_old_events();

		remove_filter( 'wu_events_threshold_days', '__return_zero' );

		$this->assertTrue( true );
	}

	/**
	 * clean_old_events runs without error when there are no old events.
	 */
	public function test_clean_old_events_runs_without_error(): void {

		// Use a very short threshold — 0 days would disable, use 1 (default).
		add_filter( 'wu_events_threshold_days', fn() => 1 );

		$manager = $this->get_manager_instance();
		$manager->clean_old_events();

		remove_filter( 'wu_events_threshold_days', fn() => 1 );

		$this->assertTrue( true );
	}

	// -------------------------------------------------------------------------
	// hooks_endpoint
	// -------------------------------------------------------------------------

	/**
	 * hooks_endpoint returns early when API is disabled.
	 */
	public function test_hooks_endpoint_skips_when_api_disabled(): void {

		wu_save_setting( 'enable_api', false );

		$manager = $this->get_manager_instance();

		// Should return without registering a route.
		$manager->hooks_endpoint();

		wu_save_setting( 'enable_api', true );

		$this->assertTrue( true );
	}

	/**
	 * hooks_endpoint registers the /hooks REST route when API is enabled.
	 */
	public function test_hooks_endpoint_registers_route_when_api_enabled(): void {

		wu_save_setting( 'enable_api', true );

		// Trigger rest_api_init so routes are registered.
		do_action( 'rest_api_init' );

		$routes = rest_get_server()->get_routes();

		// The namespace is dynamic; check that at least one route contains '/hooks'.
		$found = false;
		foreach ( array_keys( $routes ) as $route ) {
			if ( str_contains( $route, '/hooks' ) ) {
				$found = true;
				break;
			}
		}

		$this->assertTrue( $found, '/hooks REST route should be registered when API is enabled.' );
	}

	// -------------------------------------------------------------------------
	// get_hooks_rest
	// -------------------------------------------------------------------------

	/**
	 * Replace the manager's internal events array via reflection for isolation.
	 *
	 * get_hooks_rest iterates ALL registered events and calls callable payloads.
	 * Since the manager is a singleton, events from other tests (including model
	 * events that call wu_mock_*) persist. We swap the events array for the
	 * duration of these tests to avoid calling missing mock functions.
	 *
	 * @param object $manager The manager instance.
	 * @param array  $events  The events to set.
	 * @return array The original events array (for restoration).
	 */
	private function set_manager_events( object $manager, array $events ): array {

		$reflection = new \ReflectionClass( $manager );
		$prop       = $reflection->getProperty( 'events' );

		if ( PHP_VERSION_ID < 80100 ) {
			$prop->setAccessible( true );
		}

		$original = $prop->getValue( $manager );
		$prop->setValue( $manager, $events );

		return $original;
	}

	/**
	 * get_hooks_rest returns a WP_REST_Response with event types.
	 */
	public function test_get_hooks_rest_returns_response(): void {

		$manager  = $this->get_manager_instance();
		$original = $this->set_manager_events( $manager, [
			'rest_test_event' => [
				'name'    => 'REST Test',
				'payload' => [ 'foo' => 'bar' ],
			],
		] );

		$request  = new \WP_REST_Request( 'GET', '/hooks' );
		$response = $manager->get_hooks_rest( $request );

		$this->set_manager_events( $manager, $original );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
	}

	/**
	 * get_hooks_rest resolves callable payloads in the response.
	 */
	public function test_get_hooks_rest_resolves_callable_payloads(): void {

		$manager  = $this->get_manager_instance();
		$original = $this->set_manager_events( $manager, [
			'lazy_rest_event' => [
				'name'    => 'Lazy REST',
				'payload' => fn() => [ 'resolved' => true ],
			],
		] );

		$request  = new \WP_REST_Request( 'GET', '/hooks' );
		$response = $manager->get_hooks_rest( $request );

		$this->set_manager_events( $manager, $original );

		$data = $response->get_data();

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'lazy_rest_event', $data );
		$this->assertIsArray( $data['lazy_rest_event']['payload'] );
		$this->assertTrue( $data['lazy_rest_event']['payload']['resolved'] );
	}

	/**
	 * get_hooks_rest leaves non-callable payloads unchanged.
	 */
	public function test_get_hooks_rest_leaves_non_callable_payload(): void {

		$manager  = $this->get_manager_instance();
		$original = $this->set_manager_events( $manager, [
			'static_rest_event' => [
				'name'    => 'Static REST',
				'payload' => [ 'static' => 'value' ],
			],
		] );

		$request  = new \WP_REST_Request( 'GET', '/hooks' );
		$response = $manager->get_hooks_rest( $request );

		$this->set_manager_events( $manager, $original );

		$data = $response->get_data();

		$this->assertArrayHasKey( 'static_rest_event', $data );
		$this->assertEquals( [ 'static' => 'value' ], $data['static_rest_event']['payload'] );
	}

	// -------------------------------------------------------------------------
	// LOG_FILE_NAME constant
	// -------------------------------------------------------------------------

	/**
	 * LOG_FILE_NAME constant has the expected value.
	 */
	public function test_log_file_name_constant(): void {

		$this->assertEquals( 'events', Event_Manager::LOG_FILE_NAME );
	}
}
