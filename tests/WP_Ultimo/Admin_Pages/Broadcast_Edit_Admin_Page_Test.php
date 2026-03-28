<?php
/**
 * Tests for Broadcast_Edit_Admin_Page class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Admin_Pages;

use WP_UnitTestCase;
use WP_Ultimo\Models\Broadcast;

/**
 * Test class for Broadcast_Edit_Admin_Page.
 */
class Broadcast_Edit_Admin_Page_Test extends WP_UnitTestCase {

	/**
	 * @var Broadcast_Edit_Admin_Page
	 */
	private $page;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->page = new Broadcast_Edit_Admin_Page();
	}

	/**
	 * Tear down: clean up superglobals.
	 */
	protected function tearDown(): void {
		unset($_GET['id']);
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

		$this->assertEquals('wp-ultimo-edit-broadcast', $property->getValue($this->page));
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
	 * Test object_id is broadcast.
	 */
	public function test_object_id(): void {
		$this->assertEquals('broadcast', $this->page->object_id);
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

		$this->assertEquals('wp-ultimo-broadcasts', $property->getValue($this->page));
	}

	/**
	 * Test supported_panels contains network_admin_menu.
	 */
	public function test_supported_panels(): void {
		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('supported_panels');
		$property->setAccessible(true);

		$panels = $property->getValue($this->page);
		$this->assertArrayHasKey('network_admin_menu', $panels);
		$this->assertEquals('wu_edit_broadcasts', $panels['network_admin_menu']);
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

	// -------------------------------------------------------------------------
	// get_title()
	// -------------------------------------------------------------------------

	/**
	 * Test get_title returns add new string when not in edit mode.
	 */
	public function test_get_title_add_new(): void {
		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('edit');
		$property->setAccessible(true);
		$property->setValue($this->page, false);

		$title = $this->page->get_title();

		$this->assertIsString($title);
		$this->assertEquals('Add new Broadcast', $title);
	}

	/**
	 * Test get_title returns edit string when in edit mode.
	 */
	public function test_get_title_edit(): void {
		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('edit');
		$property->setAccessible(true);
		$property->setValue($this->page, true);

		$title = $this->page->get_title();

		$this->assertIsString($title);
		$this->assertEquals('Edit Broadcast', $title);
	}

	// -------------------------------------------------------------------------
	// get_menu_title()
	// -------------------------------------------------------------------------

	/**
	 * Test get_menu_title returns string.
	 */
	public function test_get_menu_title(): void {
		$title = $this->page->get_menu_title();

		$this->assertIsString($title);
		$this->assertEquals('Edit Broadcast', $title);
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
	// has_title()
	// -------------------------------------------------------------------------

	/**
	 * Test has_title returns true.
	 */
	public function test_has_title_returns_true(): void {
		$this->assertTrue($this->page->has_title());
	}

	// -------------------------------------------------------------------------
	// has_editor()
	// -------------------------------------------------------------------------

	/**
	 * Test has_editor returns true.
	 */
	public function test_has_editor_returns_true(): void {
		$this->assertTrue($this->page->has_editor());
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

		$this->assertEquals('Edit Broadcast', $labels['edit_label']);
	}

	/**
	 * Test get_labels add_new_label value.
	 */
	public function test_get_labels_add_new_label(): void {
		$labels = $this->page->get_labels();

		$this->assertEquals('Add new Broadcast', $labels['add_new_label']);
	}

	/**
	 * Test get_labels updated_message value.
	 */
	public function test_get_labels_updated_message(): void {
		$labels = $this->page->get_labels();

		$this->assertEquals('Broadcast updated with success!', $labels['updated_message']);
	}

	/**
	 * Test get_labels save_button_label value.
	 */
	public function test_get_labels_save_button_label(): void {
		$labels = $this->page->get_labels();

		$this->assertEquals('Save Broadcast', $labels['save_button_label']);
	}

	/**
	 * Test get_labels delete_button_label value.
	 */
	public function test_get_labels_delete_button_label(): void {
		$labels = $this->page->get_labels();

		$this->assertEquals('Delete Broadcast', $labels['delete_button_label']);
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

		$this->assertEquals('Enter Broadcast Title', $labels['title_placeholder']);
	}

	/**
	 * Test get_labels title_description is a string.
	 */
	public function test_get_labels_title_description_is_string(): void {
		$labels = $this->page->get_labels();

		$this->assertIsString($labels['title_description']);
		$this->assertNotEmpty($labels['title_description']);
	}

	/**
	 * Test get_labels save_description is a string (may be empty).
	 */
	public function test_get_labels_save_description_is_string(): void {
		$labels = $this->page->get_labels();

		$this->assertIsString($labels['save_description']);
	}

	// -------------------------------------------------------------------------
	// get_object()
	// -------------------------------------------------------------------------

	/**
	 * Test get_object returns a Broadcast instance when no id in GET.
	 */
	public function test_get_object_returns_new_broadcast(): void {
		$object = $this->page->get_object();

		$this->assertInstanceOf(Broadcast::class, $object);
	}

	/**
	 * Test get_object returns a new (unsaved) Broadcast when no id in GET.
	 */
	public function test_get_object_returns_unsaved_broadcast_without_get_id(): void {
		$object = $this->page->get_object();

		$this->assertInstanceOf(Broadcast::class, $object);
		$this->assertEquals(0, $object->get_id());
	}

	/**
	 * Test get_object fetches from DB when id is in GET and broadcast exists.
	 */
	public function test_get_object_fetches_from_db_when_id_in_get(): void {
		$broadcast = new Broadcast();
		$broadcast->set_title('Test Broadcast DB Fetch');
		$broadcast->set_type('broadcast_notice');
		$broadcast->set_content('Test content for DB fetch');
		$saved = $broadcast->save();

		if (is_wp_error($saved)) {
			$this->markTestSkipped('Could not save broadcast: ' . $saved->get_error_message());
			return;
		}

		$id = $broadcast->get_id();

		// Fresh page instance to avoid cached object.
		$page = new Broadcast_Edit_Admin_Page();

		$_GET['id'] = $id;

		$result = $page->get_object();

		unset($_GET['id']);

		$this->assertInstanceOf(Broadcast::class, $result);
		$this->assertEquals($id, $result->get_id());
	}

	/**
	 * Test get_object returns a Broadcast fetched by GET id when id is set.
	 *
	 * Note: get_object() does not use $this->object — it always queries by
	 * $_GET['id'] or returns a new Broadcast(). This test verifies the DB
	 * fetch path by saving a broadcast and passing its id via $_GET.
	 */
	public function test_get_object_returns_preset_object(): void {
		$broadcast = new Broadcast();
		$broadcast->set_title('Preset Broadcast');
		$broadcast->set_type('broadcast_notice');
		$broadcast->set_content('Preset content');
		$saved = $broadcast->save();

		if (is_wp_error($saved)) {
			$this->markTestSkipped('Could not save broadcast: ' . $saved->get_error_message());
			return;
		}

		$id = $broadcast->get_id();

		$page = new Broadcast_Edit_Admin_Page();

		$_GET['id'] = $id;

		$result = $page->get_object();

		unset($_GET['id']);

		$this->assertInstanceOf(Broadcast::class, $result);
		$this->assertEquals($id, $result->get_id());
	}

	// -------------------------------------------------------------------------
	// query_filter()
	// -------------------------------------------------------------------------

	/**
	 * Test query_filter merges object_type broadcast into args.
	 */
	public function test_query_filter_merges_object_type(): void {
		$args   = ['some_arg' => 'value'];
		$result = $this->page->query_filter($args);

		$this->assertArrayHasKey('object_type', $result);
		$this->assertEquals('broadcast', $result['object_type']);
	}

	/**
	 * Test query_filter merges object_id from the current object.
	 */
	public function test_query_filter_merges_object_id(): void {
		$args   = [];
		$result = $this->page->query_filter($args);

		$this->assertArrayHasKey('object_id', $result);
		$this->assertIsInt($result['object_id']);
	}

	/**
	 * Test query_filter preserves existing args.
	 */
	public function test_query_filter_preserves_existing_args(): void {
		$args   = ['existing_key' => 'existing_value', 'number' => 10];
		$result = $this->page->query_filter($args);

		$this->assertEquals('existing_value', $result['existing_key']);
		$this->assertEquals(10, $result['number']);
	}

	/**
	 * Test query_filter returns array.
	 */
	public function test_query_filter_returns_array(): void {
		$result = $this->page->query_filter([]);

		$this->assertIsArray($result);
	}

	/**
	 * Test query_filter uses saved object id when fetched via GET id.
	 *
	 * Note: get_object() does not use $this->object — it queries by $_GET['id'].
	 * We pass the id via $_GET to exercise the saved-object path.
	 */
	public function test_query_filter_uses_saved_object_id(): void {
		$broadcast = new Broadcast();
		$broadcast->set_title('Filter Test Broadcast');
		$broadcast->set_type('broadcast_notice');
		$broadcast->set_content('Content for filter test');
		$saved = $broadcast->save();

		if (is_wp_error($saved)) {
			$this->markTestSkipped('Could not save broadcast: ' . $saved->get_error_message());
			return;
		}

		$id = $broadcast->get_id();

		$page = new Broadcast_Edit_Admin_Page();

		$_GET['id'] = $id;

		$result = $page->query_filter([]);

		unset($_GET['id']);

		$this->assertEquals($id, $result['object_id']);
	}

	// -------------------------------------------------------------------------
	// events_query_filter()
	// -------------------------------------------------------------------------

	/**
	 * Test events_query_filter merges object_type broadcast into args.
	 */
	public function test_events_query_filter_merges_object_type(): void {
		$args   = ['some_arg' => 'value'];
		$result = $this->page->events_query_filter($args);

		$this->assertArrayHasKey('object_type', $result);
		$this->assertEquals('broadcast', $result['object_type']);
	}

	/**
	 * Test events_query_filter merges object_id from the current object.
	 */
	public function test_events_query_filter_merges_object_id(): void {
		$args   = [];
		$result = $this->page->events_query_filter($args);

		$this->assertArrayHasKey('object_id', $result);
		$this->assertIsInt($result['object_id']);
	}

	/**
	 * Test events_query_filter preserves existing args.
	 */
	public function test_events_query_filter_preserves_existing_args(): void {
		$args   = ['existing_key' => 'existing_value', 'number' => 5];
		$result = $this->page->events_query_filter($args);

		$this->assertEquals('existing_value', $result['existing_key']);
		$this->assertEquals(5, $result['number']);
	}

	/**
	 * Test events_query_filter returns array.
	 */
	public function test_events_query_filter_returns_array(): void {
		$result = $this->page->events_query_filter([]);

		$this->assertIsArray($result);
	}

	/**
	 * Test events_query_filter uses saved object id when fetched via GET id.
	 *
	 * Note: get_object() does not use $this->object — it queries by $_GET['id'].
	 * We pass the id via $_GET to exercise the saved-object path.
	 */
	public function test_events_query_filter_uses_saved_object_id(): void {
		$broadcast = new Broadcast();
		$broadcast->set_title('Events Filter Test Broadcast');
		$broadcast->set_type('broadcast_notice');
		$broadcast->set_content('Content for events filter test');
		$saved = $broadcast->save();

		if (is_wp_error($saved)) {
			$this->markTestSkipped('Could not save broadcast: ' . $saved->get_error_message());
			return;
		}

		$id = $broadcast->get_id();

		$page = new Broadcast_Edit_Admin_Page();

		$_GET['id'] = $id;

		$result = $page->events_query_filter([]);

		unset($_GET['id']);

		$this->assertEquals($id, $result['object_id']);
	}

	/**
	 * Test events_query_filter and query_filter produce same result for same object.
	 */
	public function test_events_query_filter_matches_query_filter(): void {
		$broadcast = new Broadcast();
		$broadcast->set_title('Matching Filter Test');
		$broadcast->set_type('broadcast_notice');
		$broadcast->set_content('Content for matching filter test');
		$saved = $broadcast->save();

		if (is_wp_error($saved)) {
			$this->markTestSkipped('Could not save broadcast: ' . $saved->get_error_message());
			return;
		}

		$this->page->object = $broadcast;

		$args            = ['number' => 10];
		$query_result    = $this->page->query_filter($args);
		$events_result   = $this->page->events_query_filter($args);

		$this->assertEquals($query_result['object_type'], $events_result['object_type']);
		$this->assertEquals($query_result['object_id'], $events_result['object_id']);
	}

	// -------------------------------------------------------------------------
	// register_widgets()
	// -------------------------------------------------------------------------

	/**
	 * Test register_widgets does not throw for new broadcast.
	 */
	public function test_register_widgets_does_not_throw_for_new_object(): void {
		set_current_screen('dashboard-network');

		$this->page->register_widgets();

		$this->assertTrue(true);
	}

	/**
	 * Test register_widgets does not throw in edit mode with broadcast_notice type.
	 */
	public function test_register_widgets_does_not_throw_in_edit_mode_notice(): void {
		set_current_screen('dashboard-network');

		$broadcast = new Broadcast();
		$broadcast->set_title('Edit Mode Notice Broadcast');
		$broadcast->set_type('broadcast_notice');
		$broadcast->set_notice_type('info');

		$this->page->object = $broadcast;
		$this->page->edit   = true;

		$this->page->register_widgets();

		$this->assertTrue(true);
	}

	/**
	 * Test register_widgets does not throw in edit mode with broadcast_email type.
	 */
	public function test_register_widgets_does_not_throw_in_edit_mode_email(): void {
		set_current_screen('dashboard-network');

		$broadcast = new Broadcast();
		$broadcast->set_title('Edit Mode Email Broadcast');
		$broadcast->set_type('broadcast_email');

		$this->page->object = $broadcast;
		$this->page->edit   = true;

		$this->page->register_widgets();

		$this->assertTrue(true);
	}

	/**
	 * Test register_widgets with success notice type.
	 */
	public function test_register_widgets_with_success_notice_type(): void {
		set_current_screen('dashboard-network');

		$broadcast = new Broadcast();
		$broadcast->set_title('Success Notice Broadcast');
		$broadcast->set_type('broadcast_notice');
		$broadcast->set_notice_type('success');

		$this->page->object = $broadcast;

		$this->page->register_widgets();

		$this->assertTrue(true);
	}

	/**
	 * Test register_widgets with warning notice type.
	 */
	public function test_register_widgets_with_warning_notice_type(): void {
		set_current_screen('dashboard-network');

		$broadcast = new Broadcast();
		$broadcast->set_title('Warning Notice Broadcast');
		$broadcast->set_type('broadcast_notice');
		$broadcast->set_notice_type('warning');

		$this->page->object = $broadcast;

		$this->page->register_widgets();

		$this->assertTrue(true);
	}

	/**
	 * Test register_widgets with error notice type.
	 */
	public function test_register_widgets_with_error_notice_type(): void {
		set_current_screen('dashboard-network');

		$broadcast = new Broadcast();
		$broadcast->set_title('Error Notice Broadcast');
		$broadcast->set_type('broadcast_notice');
		$broadcast->set_notice_type('error');

		$this->page->object = $broadcast;

		$this->page->register_widgets();

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// output_default_widget_customer_targets()
	// -------------------------------------------------------------------------

	/**
	 * Test output_default_widget_customer_targets does not throw with no targets.
	 */
	public function test_output_default_widget_customer_targets_no_targets(): void {
		$broadcast = new Broadcast();
		$broadcast->set_title('No Targets Broadcast');
		$broadcast->set_type('broadcast_notice');

		$this->page->object = $broadcast;

		ob_start();
		$this->page->output_default_widget_customer_targets();
		$output = ob_get_clean();

		$this->assertIsString($output);
	}

	/**
	 * Test output_default_widget_customer_targets outputs HTML container.
	 */
	public function test_output_default_widget_customer_targets_outputs_html(): void {
		$broadcast = new Broadcast();
		$broadcast->set_title('HTML Container Broadcast');
		$broadcast->set_type('broadcast_notice');

		$this->page->object = $broadcast;

		ob_start();
		$this->page->output_default_widget_customer_targets();
		$output = ob_get_clean();

		$this->assertStringContainsString('wu-bg-gray-100', $output);
	}

	/**
	 * Test output_default_widget_customer_targets with empty customer targets.
	 */
	public function test_output_default_widget_customer_targets_with_empty_targets(): void {
		$broadcast = new Broadcast();
		$broadcast->set_title('Empty Targets Broadcast');
		$broadcast->set_type('broadcast_notice');
		$broadcast->set_message_targets(['customers' => '']);

		$this->page->object = $broadcast;

		ob_start();
		$this->page->output_default_widget_customer_targets();
		$output = ob_get_clean();

		$this->assertIsString($output);
	}

	/**
	 * Test output_default_widget_customer_targets with multiple customer targets.
	 */
	public function test_output_default_widget_customer_targets_with_multiple_targets(): void {
		// Create two customers.
		$user1_id = $this->factory->user->create(['user_email' => 'customer1@example.com']);
		$user2_id = $this->factory->user->create(['user_email' => 'customer2@example.com']);

		$customer1 = wu_create_customer(
			[
				'user_id'  => $user1_id,
				'username' => 'customer1_' . uniqid(),
				'email'    => 'customer1@example.com',
			]
		);

		$customer2 = wu_create_customer(
			[
				'user_id'  => $user2_id,
				'username' => 'customer2_' . uniqid(),
				'email'    => 'customer2@example.com',
			]
		);

		if (is_wp_error($customer1) || is_wp_error($customer2)) {
			$this->markTestSkipped('Could not create customers for test.');
			return;
		}

		$broadcast = new Broadcast();
		$broadcast->set_title('Multi Customer Broadcast');
		$broadcast->set_type('broadcast_notice');
		$broadcast->set_message_targets(
			[
				'customers' => $customer1->get_id() . ',' . $customer2->get_id(),
			]
		);

		$this->page->object = $broadcast;

		ob_start();
		$this->page->output_default_widget_customer_targets();
		$output = ob_get_clean();

		$this->assertIsString($output);
	}

	/**
	 * Test output_default_widget_customer_targets with array customer targets.
	 */
	public function test_output_default_widget_customer_targets_with_array_targets(): void {
		$user_id = $this->factory->user->create(['user_email' => 'array_customer@example.com']);

		$customer = wu_create_customer(
			[
				'user_id'  => $user_id,
				'username' => 'array_customer_' . uniqid(),
				'email'    => 'array_customer@example.com',
			]
		);

		if (is_wp_error($customer)) {
			$this->markTestSkipped('Could not create customer for test.');
			return;
		}

		$broadcast = new Broadcast();
		$broadcast->set_title('Array Customer Broadcast');
		$broadcast->set_type('broadcast_notice');
		// Pass as array (the code handles this case by taking [0]).
		$broadcast->set_message_targets(
			[
				'customers' => [(string) $customer->get_id()],
			]
		);

		$this->page->object = $broadcast;

		ob_start();
		$this->page->output_default_widget_customer_targets();
		$output = ob_get_clean();

		$this->assertIsString($output);
	}

	// -------------------------------------------------------------------------
	// output_default_widget_product_targets()
	// -------------------------------------------------------------------------

	/**
	 * Test output_default_widget_product_targets does not throw with no targets.
	 *
	 * Note: get_object() does not use $this->object — it queries by $_GET['id'].
	 * We pass the id via $_GET so get_object() fetches the saved broadcast.
	 * We set empty message_targets so wu_get_broadcast_targets() receives an
	 * array (not false) from get_message_targets().
	 */
	public function test_output_default_widget_product_targets_no_targets(): void {
		$broadcast = new Broadcast();
		$broadcast->set_title('No Product Targets Broadcast');
		$broadcast->set_type('broadcast_notice');
		$broadcast->set_content('Content for no product targets test');
		// Set empty targets so get_message_targets() returns an array, not false.
		$broadcast->set_message_targets(['customers' => '', 'products' => '']);
		$saved = $broadcast->save();

		if (is_wp_error($saved)) {
			$this->markTestSkipped('Could not save broadcast: ' . $saved->get_error_message());
			return;
		}

		$id = $broadcast->get_id();

		$page = new Broadcast_Edit_Admin_Page();

		$_GET['id'] = $id;

		ob_start();
		$page->output_default_widget_product_targets();
		$output = ob_get_clean();

		unset($_GET['id']);

		// With no targets, wu_get_template is called — just verify no exception.
		$this->assertIsString($output);
	}

	// -------------------------------------------------------------------------
	// Integration: page properties consistency
	// -------------------------------------------------------------------------

	/**
	 * Test that get_labels and get_title are consistent in edit mode.
	 */
	public function test_labels_and_title_consistent_in_edit_mode(): void {
		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('edit');
		$property->setAccessible(true);
		$property->setValue($this->page, true);

		$title  = $this->page->get_title();
		$labels = $this->page->get_labels();

		$this->assertEquals($title, $labels['edit_label']);
	}

	/**
	 * Test that get_labels and get_title are consistent in add new mode.
	 */
	public function test_labels_and_title_consistent_in_add_new_mode(): void {
		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('edit');
		$property->setAccessible(true);
		$property->setValue($this->page, false);

		$title  = $this->page->get_title();
		$labels = $this->page->get_labels();

		$this->assertEquals($title, $labels['add_new_label']);
	}

	/**
	 * Test that get_menu_title matches edit_label.
	 */
	public function test_menu_title_matches_edit_label(): void {
		$menu_title = $this->page->get_menu_title();
		$labels     = $this->page->get_labels();

		$this->assertEquals($menu_title, $labels['edit_label']);
	}

	// -------------------------------------------------------------------------
	// Broadcast type handling
	// -------------------------------------------------------------------------

	/**
	 * Test get_object returns broadcast with default type broadcast_notice.
	 */
	public function test_get_object_default_type_is_broadcast_notice(): void {
		$object = $this->page->get_object();

		$this->assertEquals('broadcast_notice', $object->get_type());
	}

	/**
	 * Test setting broadcast type to broadcast_email and fetching via GET id.
	 *
	 * Note: get_object() does not use $this->object — it queries by $_GET['id'].
	 * We save a broadcast_email type and fetch it via $_GET['id'].
	 */
	public function test_broadcast_type_email(): void {
		$broadcast = new Broadcast();
		$broadcast->set_title('Email Type Broadcast');
		$broadcast->set_type('broadcast_email');
		$broadcast->set_content('Email broadcast content');
		$saved = $broadcast->save();

		if (is_wp_error($saved)) {
			$this->markTestSkipped('Could not save broadcast: ' . $saved->get_error_message());
			return;
		}

		$id = $broadcast->get_id();

		$page = new Broadcast_Edit_Admin_Page();

		$_GET['id'] = $id;

		$object = $page->get_object();

		unset($_GET['id']);

		$this->assertEquals('broadcast_email', $object->get_type());
	}

	/**
	 * Test Broadcast model type can be set to broadcast_notice.
	 *
	 * Note: get_object() always returns a new Broadcast() (default type
	 * broadcast_notice) unless $_GET['id'] is set. This test verifies the
	 * Broadcast model's type setter/getter directly.
	 */
	public function test_broadcast_type_notice(): void {
		$broadcast = new Broadcast();
		$broadcast->set_type('broadcast_notice');

		$this->assertEquals('broadcast_notice', $broadcast->get_type());
	}

	// -------------------------------------------------------------------------
	// Notice type handling
	// -------------------------------------------------------------------------

	/**
	 * Test notice type info is preserved.
	 */
	public function test_notice_type_info(): void {
		$broadcast = new Broadcast();
		$broadcast->set_notice_type('info');

		$this->assertEquals('info', $broadcast->get_notice_type());
	}

	/**
	 * Test notice type success is preserved.
	 */
	public function test_notice_type_success(): void {
		$broadcast = new Broadcast();
		$broadcast->set_notice_type('success');

		$this->assertEquals('success', $broadcast->get_notice_type());
	}

	/**
	 * Test notice type warning is preserved.
	 */
	public function test_notice_type_warning(): void {
		$broadcast = new Broadcast();
		$broadcast->set_notice_type('warning');

		$this->assertEquals('warning', $broadcast->get_notice_type());
	}

	/**
	 * Test notice type error is preserved.
	 */
	public function test_notice_type_error(): void {
		$broadcast = new Broadcast();
		$broadcast->set_notice_type('error');

		$this->assertEquals('error', $broadcast->get_notice_type());
	}

	// -------------------------------------------------------------------------
	// query_filter and events_query_filter symmetry
	// -------------------------------------------------------------------------

	/**
	 * Test query_filter object_id is non-negative integer.
	 */
	public function test_query_filter_object_id_is_non_negative(): void {
		$result = $this->page->query_filter([]);

		$this->assertGreaterThanOrEqual(0, $result['object_id']);
	}

	/**
	 * Test events_query_filter object_id is non-negative integer.
	 */
	public function test_events_query_filter_object_id_is_non_negative(): void {
		$result = $this->page->events_query_filter([]);

		$this->assertGreaterThanOrEqual(0, $result['object_id']);
	}

	/**
	 * Test query_filter with empty args returns at least object_type and object_id.
	 */
	public function test_query_filter_with_empty_args_has_required_keys(): void {
		$result = $this->page->query_filter([]);

		$this->assertArrayHasKey('object_type', $result);
		$this->assertArrayHasKey('object_id', $result);
	}

	/**
	 * Test events_query_filter with empty args returns at least object_type and object_id.
	 */
	public function test_events_query_filter_with_empty_args_has_required_keys(): void {
		$result = $this->page->events_query_filter([]);

		$this->assertArrayHasKey('object_type', $result);
		$this->assertArrayHasKey('object_id', $result);
	}

	// -------------------------------------------------------------------------
	// Page structure
	// -------------------------------------------------------------------------

	/**
	 * Test page is an instance of Edit_Admin_Page.
	 */
	public function test_page_extends_edit_admin_page(): void {
		$this->assertInstanceOf(Edit_Admin_Page::class, $this->page);
	}

	/**
	 * Test page has register_widgets method.
	 */
	public function test_page_has_register_widgets_method(): void {
		$this->assertTrue(method_exists($this->page, 'register_widgets'));
	}

	/**
	 * Test page has output_default_widget_customer_targets method.
	 */
	public function test_page_has_output_default_widget_customer_targets_method(): void {
		$this->assertTrue(method_exists($this->page, 'output_default_widget_customer_targets'));
	}

	/**
	 * Test page has output_default_widget_product_targets method.
	 */
	public function test_page_has_output_default_widget_product_targets_method(): void {
		$this->assertTrue(method_exists($this->page, 'output_default_widget_product_targets'));
	}

	/**
	 * Test page has query_filter method.
	 */
	public function test_page_has_query_filter_method(): void {
		$this->assertTrue(method_exists($this->page, 'query_filter'));
	}

	/**
	 * Test page has events_query_filter method.
	 */
	public function test_page_has_events_query_filter_method(): void {
		$this->assertTrue(method_exists($this->page, 'events_query_filter'));
	}

	/**
	 * Test page has get_object method.
	 */
	public function test_page_has_get_object_method(): void {
		$this->assertTrue(method_exists($this->page, 'get_object'));
	}

	/**
	 * Test page has get_title method.
	 */
	public function test_page_has_get_title_method(): void {
		$this->assertTrue(method_exists($this->page, 'get_title'));
	}

	/**
	 * Test page has get_labels method.
	 */
	public function test_page_has_get_labels_method(): void {
		$this->assertTrue(method_exists($this->page, 'get_labels'));
	}

	/**
	 * Test page has has_title method.
	 */
	public function test_page_has_has_title_method(): void {
		$this->assertTrue(method_exists($this->page, 'has_title'));
	}

	/**
	 * Test page has has_editor method.
	 */
	public function test_page_has_has_editor_method(): void {
		$this->assertTrue(method_exists($this->page, 'has_editor'));
	}
}
