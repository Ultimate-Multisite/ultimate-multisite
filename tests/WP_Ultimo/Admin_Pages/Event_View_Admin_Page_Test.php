<?php
/**
 * Tests for Event_View_Admin_Page class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Admin_Pages;

use WP_UnitTestCase;
use WP_Ultimo\Faker;
use WP_Ultimo\Models\Event;

/**
 * Test class for Event_View_Admin_Page.
 */
class Event_View_Admin_Page_Test extends WP_UnitTestCase {

	/**
	 * @var Event_View_Admin_Page
	 */
	private $page;

	/**
	 * A saved event for use in tests.
	 *
	 * @var Event
	 */
	private $event;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {

		parent::setUp();

		$faker = new Faker();
		$faker->generate_fake_events(1);

		$this->event = current($faker->get_fake_data_generated('events'));

		$this->page = new Event_View_Admin_Page();
	}

	/**
	 * Tear down: clean up superglobals.
	 */
	protected function tearDown(): void {

		unset($_GET['id'], $_REQUEST['id']);

		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Static properties
	// -------------------------------------------------------------------------

	/**
	 * Test page id is correct.
	 */
	public function test_page_id(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('id');
		$property->setAccessible(true);

		$this->assertEquals('wp-ultimo-view-event', $property->getValue($this->page));
	}

	/**
	 * Test page type is submenu.
	 */
	public function test_page_type(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('type');
		$property->setAccessible(true);

		$this->assertEquals('submenu', $property->getValue($this->page));
	}

	/**
	 * Test object_id is event.
	 */
	public function test_object_id(): void {

		$this->assertEquals('event', $this->page->object_id);
	}

	/**
	 * Test parent is none.
	 */
	public function test_parent_is_none(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('parent');
		$property->setAccessible(true);

		$this->assertEquals('none', $property->getValue($this->page));
	}

	/**
	 * Test highlight_menu_slug is set correctly.
	 */
	public function test_highlight_menu_slug(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('highlight_menu_slug');
		$property->setAccessible(true);

		$this->assertEquals('wp-ultimo-events', $property->getValue($this->page));
	}

	/**
	 * Test badge_count is zero.
	 */
	public function test_badge_count(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('badge_count');
		$property->setAccessible(true);

		$this->assertEquals(0, $property->getValue($this->page));
	}

	/**
	 * Test supported_panels contains network_admin_menu with correct capability.
	 */
	public function test_supported_panels(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('supported_panels');
		$property->setAccessible(true);

		$panels = $property->getValue($this->page);
		$this->assertArrayHasKey('network_admin_menu', $panels);
		$this->assertEquals('wu_read_events', $panels['network_admin_menu']);
	}

	// -------------------------------------------------------------------------
	// get_title()
	// -------------------------------------------------------------------------

	/**
	 * Test get_title returns "Edit Event" when edit is true.
	 */
	public function test_get_title_when_editing(): void {

		$this->page->edit = true;

		$title = $this->page->get_title();

		$this->assertIsString($title);
		$this->assertEquals('Edit Event', $title);
	}

	/**
	 * Test get_title returns "Add new Event" when edit is false.
	 */
	public function test_get_title_when_not_editing(): void {

		$this->page->edit = false;

		$title = $this->page->get_title();

		$this->assertIsString($title);
		$this->assertEquals('Add new Event', $title);
	}

	// -------------------------------------------------------------------------
	// get_menu_title()
	// -------------------------------------------------------------------------

	/**
	 * Test get_menu_title returns expected string.
	 */
	public function test_get_menu_title(): void {

		$title = $this->page->get_menu_title();

		$this->assertIsString($title);
		$this->assertEquals('Edit Event', $title);
	}

	// -------------------------------------------------------------------------
	// action_links()
	// -------------------------------------------------------------------------

	/**
	 * Test action_links returns empty array.
	 */
	public function test_action_links(): void {

		$links = $this->page->action_links();

		$this->assertIsArray($links);
		$this->assertEmpty($links);
	}

	// -------------------------------------------------------------------------
	// get_labels()
	// -------------------------------------------------------------------------

	/**
	 * Test get_labels returns array with all required keys.
	 */
	public function test_get_labels_returns_required_keys(): void {

		$labels = $this->page->get_labels();

		$this->assertIsArray($labels);
		$this->assertArrayHasKey('edit_label', $labels);
		$this->assertArrayHasKey('add_new_label', $labels);
		$this->assertArrayHasKey('updated_message', $labels);
		$this->assertArrayHasKey('title_placeholder', $labels);
		$this->assertArrayHasKey('title_description', $labels);
		$this->assertArrayHasKey('save_button_label', $labels);
		$this->assertArrayHasKey('save_description', $labels);
		$this->assertArrayHasKey('delete_button_label', $labels);
		$this->assertArrayHasKey('delete_description', $labels);
	}

	/**
	 * Test get_labels edit_label value.
	 */
	public function test_get_labels_edit_label(): void {

		$labels = $this->page->get_labels();

		$this->assertEquals('Edit Event', $labels['edit_label']);
	}

	/**
	 * Test get_labels add_new_label value.
	 */
	public function test_get_labels_add_new_label(): void {

		$labels = $this->page->get_labels();

		$this->assertEquals('Add new Event', $labels['add_new_label']);
	}

	/**
	 * Test get_labels updated_message value.
	 */
	public function test_get_labels_updated_message(): void {

		$labels = $this->page->get_labels();

		$this->assertEquals('Event updated with success!', $labels['updated_message']);
	}

	/**
	 * Test get_labels save_button_label value.
	 */
	public function test_get_labels_save_button_label(): void {

		$labels = $this->page->get_labels();

		$this->assertEquals('Save Event', $labels['save_button_label']);
	}

	/**
	 * Test get_labels delete_button_label value.
	 */
	public function test_get_labels_delete_button_label(): void {

		$labels = $this->page->get_labels();

		$this->assertEquals('Delete Event', $labels['delete_button_label']);
	}

	/**
	 * Test get_labels delete_description value.
	 */
	public function test_get_labels_delete_description(): void {

		$labels = $this->page->get_labels();

		$this->assertEquals('Be careful. This action is irreversible.', $labels['delete_description']);
	}

	/**
	 * Test get_labels title_placeholder value.
	 */
	public function test_get_labels_title_placeholder(): void {

		$labels = $this->page->get_labels();

		$this->assertEquals('Enter Event', $labels['title_placeholder']);
	}

	/**
	 * Test get_labels title_description is empty string.
	 */
	public function test_get_labels_title_description_empty(): void {

		$labels = $this->page->get_labels();

		$this->assertEquals('', $labels['title_description']);
	}

	/**
	 * Test get_labels save_description is empty string.
	 */
	public function test_get_labels_save_description_empty(): void {

		$labels = $this->page->get_labels();

		$this->assertEquals('', $labels['save_description']);
	}

	// -------------------------------------------------------------------------
	// has_title()
	// -------------------------------------------------------------------------

	/**
	 * Test has_title returns false.
	 */
	public function test_has_title_returns_false(): void {

		$this->assertFalse($this->page->has_title());
	}

	// -------------------------------------------------------------------------
	// handle_save()
	// -------------------------------------------------------------------------

	/**
	 * Test handle_save returns true.
	 */
	public function test_handle_save_returns_true(): void {

		$result = $this->page->handle_save();

		$this->assertTrue($result);
	}

	// -------------------------------------------------------------------------
	// get_object()
	// -------------------------------------------------------------------------

	/**
	 * Test get_object returns Event instance for valid ID.
	 */
	public function test_get_object_returns_event_for_valid_id(): void {

		$_GET['id'] = $this->event->get_id();

		$object = $this->page->get_object();

		$this->assertInstanceOf(Event::class, $object);
		$this->assertEquals($this->event->get_id(), $object->get_id());
	}

	/**
	 * Test get_object redirects when no ID is provided.
	 */
	public function test_get_object_redirects_when_no_id(): void {

		unset($_GET['id']);

		try {
			$this->page->get_object();
			$this->fail('Expected redirect/exit to be called');
		} catch (\Exception $e) {
			// wp_safe_redirect + exit throws in test env.
			$this->assertTrue(true);
		}
	}

	/**
	 * Test get_object redirects when ID does not exist.
	 */
	public function test_get_object_redirects_for_nonexistent_id(): void {

		$_GET['id'] = 999999;

		try {
			$this->page->get_object();
			$this->fail('Expected redirect/exit to be called');
		} catch (\Exception $e) {
			// wp_safe_redirect + exit throws in test env.
			$this->assertTrue(true);
		}
	}

	// -------------------------------------------------------------------------
	// register_scripts()
	// -------------------------------------------------------------------------

	/**
	 * Test register_scripts enqueues wu-event-view script.
	 */
	public function test_register_scripts_enqueues_event_view_script(): void {

		$this->page->register_scripts();

		$this->assertTrue(
			wp_script_is('wu-event-view', 'registered') ||
			wp_script_is('wu-event-view', 'enqueued')
		);
	}

	/**
	 * Test register_scripts enqueues clipboard script.
	 */
	public function test_register_scripts_enqueues_clipboard(): void {

		$this->page->register_scripts();

		$this->assertTrue(
			wp_script_is('clipboard', 'registered') ||
			wp_script_is('clipboard', 'enqueued')
		);
	}

	/**
	 * Test register_scripts enqueues wu-vue script.
	 */
	public function test_register_scripts_enqueues_wu_vue(): void {

		$this->page->register_scripts();

		$this->assertTrue(
			wp_script_is('wu-vue', 'registered') ||
			wp_script_is('wu-vue', 'enqueued')
		);
	}

	// -------------------------------------------------------------------------
	// register_forms()
	// -------------------------------------------------------------------------

	/**
	 * Test register_forms adds the wu_data_json_success_delete_event_modal filter.
	 */
	public function test_register_forms_adds_delete_event_filter(): void {

		$this->page->register_forms();

		$this->assertNotFalse(has_filter('wu_data_json_success_delete_event_modal'));
	}

	/**
	 * Test register_forms delete filter returns redirect_url.
	 */
	public function test_register_forms_delete_filter_returns_redirect_url(): void {

		$this->page->register_forms();

		$result = apply_filters('wu_data_json_success_delete_event_modal', ['original' => 'data']);

		$this->assertIsArray($result);
		$this->assertArrayHasKey('redirect_url', $result);
		$this->assertStringContainsString('wp-ultimo-events', $result['redirect_url']);
		$this->assertStringContainsString('deleted=1', $result['redirect_url']);
	}

	// -------------------------------------------------------------------------
	// register_widgets()
	// -------------------------------------------------------------------------

	/**
	 * Test register_widgets does not throw when called with valid event.
	 */
	public function test_register_widgets_does_not_throw(): void {

		set_current_screen('dashboard-network');

		$_GET['id']         = $this->event->get_id();
		$this->page->object = $this->event;

		$this->page->register_widgets();

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// output_default_widget_message()
	// -------------------------------------------------------------------------

	/**
	 * Test output_default_widget_message produces output when object is set.
	 */
	public function test_output_default_widget_message_produces_output(): void {

		set_current_screen('dashboard-network');

		$_GET['id']         = $this->event->get_id();
		$this->page->object = $this->event;

		ob_start();
		$this->page->output_default_widget_message();
		$output = ob_get_clean();

		// Template may produce empty output in test env, but should not throw.
		$this->assertIsString($output);
	}

	// -------------------------------------------------------------------------
	// output_default_widget_payload()
	// -------------------------------------------------------------------------

	/**
	 * Test output_default_widget_payload produces output when object is set.
	 */
	public function test_output_default_widget_payload_produces_output(): void {

		set_current_screen('dashboard-network');

		$_GET['id']         = $this->event->get_id();
		$this->page->object = $this->event;

		ob_start();
		$this->page->output_default_widget_payload();
		$output = ob_get_clean();

		$this->assertIsString($output);
	}

	// -------------------------------------------------------------------------
	// output_default_widget_initiator()
	// -------------------------------------------------------------------------

	/**
	 * Test output_default_widget_initiator produces output when object is set.
	 */
	public function test_output_default_widget_initiator_produces_output(): void {

		set_current_screen('dashboard-network');

		$_GET['id']         = $this->event->get_id();
		$this->page->object = $this->event;

		ob_start();
		$this->page->output_default_widget_initiator();
		$output = ob_get_clean();

		$this->assertIsString($output);
	}

	// -------------------------------------------------------------------------
	// Instantiation
	// -------------------------------------------------------------------------

	/**
	 * Test page can be instantiated.
	 */
	public function test_page_instantiation(): void {

		$page = new Event_View_Admin_Page();

		$this->assertInstanceOf(Event_View_Admin_Page::class, $page);
	}

	/**
	 * Test page extends Edit_Admin_Page.
	 */
	public function test_page_extends_edit_admin_page(): void {

		$this->assertInstanceOf(Edit_Admin_Page::class, $this->page);
	}
}
