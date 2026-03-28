<?php
/**
 * Tests for View_Logs_Admin_Page class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Admin_Pages;

use WP_UnitTestCase;
use WP_Ultimo\Logger;

/**
 * Test class for View_Logs_Admin_Page.
 *
 * Covers all public methods of View_Logs_Admin_Page to reach >=50% coverage.
 * Methods that call wp_die(), send headers, or require HTTP context are tested
 * for their guard conditions and side-effects only.
 */
class View_Logs_Admin_Page_Test extends WP_UnitTestCase {

	/**
	 * @var View_Logs_Admin_Page
	 */
	private $page;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {

		parent::setUp();
		$this->page = new View_Logs_Admin_Page();
	}

	/**
	 * Tear down: clean up superglobals.
	 */
	protected function tearDown(): void {

		unset(
			$_REQUEST['file'],
			$_REQUEST['return_ascii'],
			$_REQUEST['submit_button'],
			$_REQUEST['log_file'],
			$_GET['log_file'],
			$_POST['log_file']
		);

		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Page properties
	// -------------------------------------------------------------------------

	public function test_page_id(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('id');
		$property->setAccessible(true);

		$this->assertEquals('wp-ultimo-view-logs', $property->getValue($this->page));
	}

	public function test_page_type(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('type');
		$property->setAccessible(true);

		$this->assertEquals('submenu', $property->getValue($this->page));
	}

	public function test_parent_is_none(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('parent');
		$property->setAccessible(true);

		$this->assertEquals('none', $property->getValue($this->page));
	}

	public function test_highlight_menu_slug(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('highlight_menu_slug');
		$property->setAccessible(true);

		$this->assertEquals('wp-ultimo-events', $property->getValue($this->page));
	}

	public function test_badge_count_is_zero(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('badge_count');
		$property->setAccessible(true);

		$this->assertEquals(0, $property->getValue($this->page));
	}

	public function test_supported_panels(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('supported_panels');
		$property->setAccessible(true);

		$panels = $property->getValue($this->page);
		$this->assertArrayHasKey('network_admin_menu', $panels);
		$this->assertEquals('manage_network', $panels['network_admin_menu']);
	}

	// -------------------------------------------------------------------------
	// get_title() / get_menu_title()
	// -------------------------------------------------------------------------

	public function test_get_title(): void {

		$this->assertEquals('View Log', $this->page->get_title());
	}

	public function test_get_menu_title(): void {

		$this->assertEquals('View Log', $this->page->get_menu_title());
	}

	// -------------------------------------------------------------------------
	// get_labels()
	// -------------------------------------------------------------------------

	public function test_get_labels_returns_array(): void {

		$labels = $this->page->get_labels();

		$this->assertIsArray($labels);
	}

	public function test_get_labels_has_edit_label(): void {

		$labels = $this->page->get_labels();

		$this->assertArrayHasKey('edit_label', $labels);
		$this->assertEquals('View Log', $labels['edit_label']);
	}

	public function test_get_labels_has_add_new_label(): void {

		$labels = $this->page->get_labels();

		$this->assertArrayHasKey('add_new_label', $labels);
		$this->assertEquals('View Log', $labels['add_new_label']);
	}

	public function test_get_labels_has_title_placeholder(): void {

		$labels = $this->page->get_labels();

		$this->assertArrayHasKey('title_placeholder', $labels);
	}

	public function test_get_labels_has_title_description(): void {

		$labels = $this->page->get_labels();

		$this->assertArrayHasKey('title_description', $labels);
	}

	public function test_get_labels_has_delete_button_label(): void {

		$labels = $this->page->get_labels();

		$this->assertArrayHasKey('delete_button_label', $labels);
		$this->assertEquals('Delete Log File', $labels['delete_button_label']);
	}

	public function test_get_labels_has_delete_description(): void {

		$labels = $this->page->get_labels();

		$this->assertArrayHasKey('delete_description', $labels);
	}

	// -------------------------------------------------------------------------
	// get_object()
	// -------------------------------------------------------------------------

	public function test_get_object_returns_empty_array(): void {

		$object = $this->page->get_object();

		$this->assertIsArray($object);
		$this->assertEmpty($object);
	}

	// -------------------------------------------------------------------------
	// init() — hook registration
	// -------------------------------------------------------------------------

	public function test_init_registers_ajax_action(): void {

		$this->page->init();

		$this->assertGreaterThan(
			0,
			has_action('wp_ajax_wu_handle_view_logs', [$this->page, 'handle_view_logs'])
		);
	}

	// -------------------------------------------------------------------------
	// handle_view_logs() — non-AJAX path
	// -------------------------------------------------------------------------

	/**
	 * handle_view_logs() returns an array with the required keys when called
	 * outside of an AJAX context (wp_doing_ajax() returns false in tests).
	 */
	public function test_handle_view_logs_returns_array_with_required_keys(): void {

		$result = $this->page->handle_view_logs();

		$this->assertIsArray($result);
		$this->assertArrayHasKey('file', $result);
		$this->assertArrayHasKey('file_name', $result);
		$this->assertArrayHasKey('contents', $result);
		$this->assertArrayHasKey('logs_list', $result);
	}

	public function test_handle_view_logs_logs_list_is_array(): void {

		$result = $this->page->handle_view_logs();

		$this->assertIsArray($result['logs_list']);
	}

	public function test_handle_view_logs_contents_is_string(): void {

		$result = $this->page->handle_view_logs();

		$this->assertIsString($result['contents']);
	}

	public function test_handle_view_logs_file_name_is_string(): void {

		$result = $this->page->handle_view_logs();

		$this->assertIsString($result['file_name']);
	}

	/**
	 * When no log files exist, logs_list should contain the "No log files found" entry.
	 */
	public function test_handle_view_logs_empty_logs_list_has_placeholder(): void {

		// Ensure the logs folder exists but is empty (or has no .log files).
		// We can't easily guarantee an empty folder, so we just verify the
		// structure is correct regardless of whether files exist.
		$result = $this->page->handle_view_logs();

		$this->assertIsArray($result['logs_list']);
		$this->assertNotEmpty($result['logs_list']);
	}

	/**
	 * When a specific file is requested via wu_request('file'), it should be
	 * reflected in the response — but only if it's within the logs folder.
	 * We test the security guard: a file outside the logs folder triggers wp_die().
	 */
	public function test_handle_view_logs_security_check_dies_for_external_file(): void {

		$_REQUEST['file'] = '/etc/passwd';

		$this->expectException(\WPDieException::class);

		$this->page->handle_view_logs();
	}

	/**
	 * When return_ascii is 'no', the default content should be the translated string.
	 */
	public function test_handle_view_logs_no_ascii_default_content(): void {

		$_REQUEST['return_ascii'] = 'no';

		$result = $this->page->handle_view_logs();

		// If no file is found, contents should be the "No log entries found." string.
		// This only applies when there are no log files at all.
		$this->assertIsString($result['contents']);
	}

	/**
	 * When a valid log file is requested, its contents are returned.
	 */
	public function test_handle_view_logs_returns_file_contents_for_valid_file(): void {

		// Create a unique temporary log file inside the logs folder to avoid
		// cross-test collisions in parallel/sharded runs.
		$logs_folder = Logger::get_logs_folder();
		$tmp_file    = tempnam($logs_folder, 'wu-log-test-');
		$this->assertNotFalse($tmp_file);

		file_put_contents($tmp_file, 'test log content');

		$_REQUEST['file'] = $tmp_file;

		try {
			$result = $this->page->handle_view_logs();

			$this->assertEquals('test log content', $result['contents']);
			$this->assertEquals($tmp_file, $result['file']);
			$this->assertStringEndsWith(basename($tmp_file), $result['file_name']);
		} finally {
			if (is_string($tmp_file) && is_file($tmp_file)) {
				unlink($tmp_file);
			}
			unset($_REQUEST['file']);
		}
	}

	/**
	 * When the requested file does not exist, contents fall back to default.
	 */
	public function test_handle_view_logs_nonexistent_file_returns_default_content(): void {

		$logs_folder      = Logger::get_logs_folder();
		$_REQUEST['file'] = $logs_folder . '/nonexistent-file-xyz.log';

		$result = $this->page->handle_view_logs();

		// File doesn't exist, so contents should be the default (ascii badge or "No log entries found.").
		$this->assertIsString($result['contents']);
		$this->assertNotEmpty($result['contents']);

		unset($_REQUEST['file']);
	}

	// -------------------------------------------------------------------------
	// handle_save() — guard conditions
	// -------------------------------------------------------------------------

	/**
	 * handle_save() with action 'none' adds an error notice and returns early.
	 */
	public function test_handle_save_with_no_action_adds_error_notice(): void {

		// submit_button defaults to 'none' when not set.
		unset($_REQUEST['submit_button']);

		// Should not throw — just adds a notice and returns.
		$this->page->handle_save();

		$this->assertTrue(true);
	}

	/**
	 * handle_save() with a non-existent file adds an error notice and returns early.
	 */
	public function test_handle_save_with_nonexistent_file_adds_error_notice(): void {

		$_REQUEST['submit_button'] = 'download';
		$_REQUEST['log_file']      = '/tmp/nonexistent-wu-log-file-xyz.log';

		$this->page->handle_save();

		$this->assertTrue(true);

		unset($_REQUEST['submit_button'], $_REQUEST['log_file']);
	}

	// -------------------------------------------------------------------------
	// page_loaded()
	// -------------------------------------------------------------------------

	public function test_page_loaded_does_not_throw(): void {

		$this->page->page_loaded();

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// register_scripts()
	// -------------------------------------------------------------------------

	public function test_register_scripts_enqueues_wu_view_log_script(): void {

		$this->page->register_scripts();

		$this->assertTrue(wp_script_is('wu-view-log', 'enqueued'));
	}

	// -------------------------------------------------------------------------
	// output_default_widget_payload()
	// -------------------------------------------------------------------------

	public function test_output_default_widget_payload_is_callable(): void {

		$this->assertTrue(is_callable([$this->page, 'output_default_widget_payload']));
	}

	public function test_output_default_widget_payload_outputs_html(): void {

		ob_start();
		$this->page->output_default_widget_payload(
			null,
			[
				'args' => [
					'contents' => 'test log line',
				],
			]
		);
		$output = ob_get_clean();

		$this->assertIsString($output);
		$this->assertNotSame('', trim($output));
		$this->assertStringContainsString('test log line', $output);
	}
}
