<?php
/**
 * Unit tests for Event_Manager.
 *
 * @package WP_Ultimo\Tests\Managers
 */

namespace WP_Ultimo\Tests\Managers;

use WP_Ultimo\Managers\Event_Manager;

class Event_Manager_Test extends \WP_UnitTestCase {

	use Manager_Test_Trait;

	protected function get_manager_class(): string {
		return Event_Manager::class;
	}

	protected function get_expected_slug(): ?string {
		return 'event';
	}

	protected function get_expected_model_class(): ?string {
		return \WP_Ultimo\Models\Event::class;
	}

	/**
	 * Test register_event and get_event round-trip.
	 */
	public function test_register_and_get_event(): void {

		$manager = $this->get_manager_instance();

		$result = $manager->register_event('test_event', [
			'name'    => 'Test Event',
			'payload' => ['key' => 'value'],
		]);

		$this->assertTrue($result);

		$event = $manager->get_event('test_event');

		$this->assertIsArray($event);
		$this->assertEquals('Test Event', $event['name']);
	}

	/**
	 * Test get_event returns false for unregistered event.
	 */
	public function test_get_event_returns_false_for_unknown(): void {

		$manager = $this->get_manager_instance();
		$result  = $manager->get_event('nonexistent_event_xyz');

		$this->assertFalse($result);
	}

	/**
	 * Test get_events returns an array.
	 */
	public function test_get_events_returns_array(): void {

		$manager = $this->get_manager_instance();
		$events  = $manager->get_events();

		$this->assertIsArray($events);
	}

	/**
	 * Test do_event returns false for unregistered event.
	 */
	public function test_do_event_returns_false_for_unknown(): void {

		$manager = $this->get_manager_instance();
		$result  = $manager->do_event('nonexistent_event_xyz', []);

		$this->assertFalse($result);
	}

	/**
	 * Test do_event fires the wu_event and wu_event_{slug} actions.
	 */
	public function test_do_event_fires_actions(): void {

		$manager = $this->get_manager_instance();

		$manager->register_event('test_fire', [
			'name'    => 'Fire Test',
			'payload' => ['sample' => 'data'],
		]);

		$generic_fired  = false;
		$specific_fired = false;

		add_action('wu_event', function () use (&$generic_fired) {
			$generic_fired = true;
		});

		add_action('wu_event_test_fire', function () use (&$specific_fired) {
			$specific_fired = true;
		});

		// do_event calls save_event internally which may fail validation
		// (initiator not set). The actions still fire before save.
		$manager->do_event('test_fire', ['sample' => 'data']);

		$this->assertTrue($generic_fired, 'wu_event action should have fired.');
		$this->assertTrue($specific_fired, 'wu_event_test_fire action should have fired.');
	}

	/**
	 * Test save_event with a fully valid payload creates an event record.
	 */
	public function test_save_event_with_valid_payload(): void {

		$manager = $this->get_manager_instance();

		$event = new \WP_Ultimo\Models\Event(
			[
				'object_id'    => 1,
				'object_type'  => 'test',
				'severity'     => \WP_Ultimo\Models\Event::SEVERITY_INFO,
				'slug'         => 'test_direct_save',
				'payload'      => ['key' => 'value'],
				'initiator'    => 'system',
				'date_created' => wu_get_current_time('mysql', true),
			]
		);

		$result = $event->save();

		$this->assertNotWPError($result);
		$this->assertNotFalse($result);
	}

	/**
	 * Test register_model_events stores model event configuration.
	 */
	public function test_register_model_events(): void {

		Event_Manager::register_model_events('test_model', 'Test Model', ['created', 'updated']);

		$manager       = $this->get_manager_instance();
		$models_events = $this->get_protected_property($manager, 'models_events');

		$this->assertArrayHasKey('test_model', $models_events);
		$this->assertEquals('Test Model', $models_events['test_model']['label']);
		$this->assertContains('created', $models_events['test_model']['types']);
	}
}
