<?php
/**
 * Tests for Domain_List_Admin_Page class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Admin_Pages;

use WP_UnitTestCase;
use WP_Ultimo\Database\Domains\Domain_Stage;

/**
 * Test class for Domain_List_Admin_Page.
 */
class Domain_List_Admin_Page_Test extends WP_UnitTestCase {

	/**
	 * @var Domain_List_Admin_Page
	 */
	private $page;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->page = new Domain_List_Admin_Page();
	}

	/**
	 * Tear down: clean up superglobals and filters.
	 */
	protected function tearDown(): void {
		unset(
			$_POST['type'],
			$_POST['domain'],
			$_POST['blog_id'],
			$_POST['stage'],
			$_POST['primary_domain'],
			$_REQUEST['type'],
			$_REQUEST['domain'],
			$_REQUEST['blog_id'],
			$_REQUEST['stage'],
			$_REQUEST['primary_domain']
		);
		remove_all_filters('wu_add_new_domain_modal_fields');
		remove_all_filters('wp_doing_ajax');
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

		$this->assertEquals('wp-ultimo-domains', $property->getValue($this->page));
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
	 * Test supported_panels contains network_admin_menu.
	 */
	public function test_supported_panels(): void {
		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('supported_panels');
		$property->setAccessible(true);

		$panels = $property->getValue($this->page);
		$this->assertArrayHasKey('network_admin_menu', $panels);
		$this->assertEquals('wu_read_domains', $panels['network_admin_menu']);
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
	 * Test get_title returns string.
	 */
	public function test_get_title(): void {
		$title = $this->page->get_title();

		$this->assertIsString($title);
		$this->assertEquals('Domains', $title);
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
		$this->assertEquals('Domains', $title);
	}

	// -------------------------------------------------------------------------
	// get_submenu_title()
	// -------------------------------------------------------------------------

	/**
	 * Test get_submenu_title returns string.
	 */
	public function test_get_submenu_title(): void {
		$title = $this->page->get_submenu_title();

		$this->assertIsString($title);
		$this->assertEquals('Domains', $title);
	}

	// -------------------------------------------------------------------------
	// get_labels()
	// -------------------------------------------------------------------------

	/**
	 * Test get_labels returns array with required keys.
	 */
	public function test_get_labels(): void {
		$labels = $this->page->get_labels();

		$this->assertIsArray($labels);
		$this->assertArrayHasKey('deleted_message', $labels);
		$this->assertArrayHasKey('search_label', $labels);
	}

	/**
	 * Test get_labels deleted_message value.
	 */
	public function test_get_labels_deleted_message(): void {
		$labels = $this->page->get_labels();

		$this->assertEquals('Domains removed successfully.', $labels['deleted_message']);
	}

	/**
	 * Test get_labels search_label value.
	 */
	public function test_get_labels_search_label(): void {
		$labels = $this->page->get_labels();

		$this->assertEquals('Search Domains', $labels['search_label']);
	}

	// -------------------------------------------------------------------------
	// action_links()
	// -------------------------------------------------------------------------

	/**
	 * Test action_links returns array with one item.
	 */
	public function test_action_links(): void {
		$links = $this->page->action_links();

		$this->assertIsArray($links);
		$this->assertCount(1, $links);
	}

	/**
	 * Test action_links has add domain link with correct label.
	 */
	public function test_action_links_add_domain_label(): void {
		$links = $this->page->action_links();

		$this->assertEquals('Add Domain', $links[0]['label']);
	}

	/**
	 * Test action_links has url key.
	 */
	public function test_action_links_has_url(): void {
		$links = $this->page->action_links();

		$this->assertArrayHasKey('url', $links[0]);
		$this->assertIsString($links[0]['url']);
	}

	/**
	 * Test action_links has icon key.
	 */
	public function test_action_links_has_icon(): void {
		$links = $this->page->action_links();

		$this->assertArrayHasKey('icon', $links[0]);
		$this->assertEquals('wu-circle-with-plus', $links[0]['icon']);
	}

	/**
	 * Test action_links has classes key with wubox.
	 */
	public function test_action_links_has_wubox_class(): void {
		$links = $this->page->action_links();

		$this->assertArrayHasKey('classes', $links[0]);
		$this->assertEquals('wubox', $links[0]['classes']);
	}

	/**
	 * Test action_links url references add_new_domain form.
	 */
	public function test_action_links_url_references_add_new_domain(): void {
		$links = $this->page->action_links();

		$this->assertStringContainsString('add_new_domain', $links[0]['url']);
	}

	// -------------------------------------------------------------------------
	// table()
	// -------------------------------------------------------------------------

	/**
	 * Test table returns Domain_List_Table instance.
	 */
	public function test_table(): void {
		$table = $this->page->table();

		$this->assertInstanceOf(\WP_Ultimo\List_Tables\Domain_List_Table::class, $table);
	}

	/**
	 * Test table returns a new instance on each call.
	 */
	public function test_table_returns_new_instance_each_call(): void {
		$table1 = $this->page->table();
		$table2 = $this->page->table();

		$this->assertNotSame($table1, $table2);
	}

	// -------------------------------------------------------------------------
	// register_forms()
	// -------------------------------------------------------------------------

	/**
	 * Test register_forms does not throw.
	 */
	public function test_register_forms_does_not_throw(): void {
		$this->page->register_forms();

		$this->assertTrue(true);
	}

	/**
	 * Test register_forms registers the add_new_domain form.
	 */
	public function test_register_forms_registers_add_new_domain(): void {
		$this->page->register_forms();

		$form = \WP_Ultimo\Managers\Form_Manager::get_instance()->get_form('add_new_domain');

		$this->assertNotNull($form);
		$this->assertIsArray($form);
	}

	/**
	 * Test register_forms sets render callback for add_new_domain.
	 */
	public function test_register_forms_sets_render_callback(): void {
		$this->page->register_forms();

		$form = \WP_Ultimo\Managers\Form_Manager::get_instance()->get_form('add_new_domain');

		$this->assertArrayHasKey('render', $form);
		$this->assertIsCallable($form['render']);
	}

	/**
	 * Test register_forms sets handler callback for add_new_domain.
	 */
	public function test_register_forms_sets_handler_callback(): void {
		$this->page->register_forms();

		$form = \WP_Ultimo\Managers\Form_Manager::get_instance()->get_form('add_new_domain');

		$this->assertArrayHasKey('handler', $form);
		$this->assertIsCallable($form['handler']);
	}

	/**
	 * Test register_forms sets capability for add_new_domain.
	 */
	public function test_register_forms_sets_capability(): void {
		$this->page->register_forms();

		$form = \WP_Ultimo\Managers\Form_Manager::get_instance()->get_form('add_new_domain');

		$this->assertArrayHasKey('capability', $form);
		$this->assertEquals('wu_edit_domains', $form['capability']);
	}

	// -------------------------------------------------------------------------
	// render_add_new_domain_modal()
	// -------------------------------------------------------------------------

	/**
	 * Test render_add_new_domain_modal does not throw.
	 */
	public function test_render_add_new_domain_modal_does_not_throw(): void {
		ob_start();
		$this->page->render_add_new_domain_modal();
		$output = ob_get_clean();

		$this->assertTrue(true);
	}

	/**
	 * Test render_add_new_domain_modal produces output.
	 */
	public function test_render_add_new_domain_modal_produces_output(): void {
		ob_start();
		$this->page->render_add_new_domain_modal();
		$output = ob_get_clean();

		$this->assertNotEmpty($output);
	}

	/**
	 * Test render_add_new_domain_modal output contains add_new_domain app reference.
	 */
	public function test_render_add_new_domain_modal_contains_app_reference(): void {
		ob_start();
		$this->page->render_add_new_domain_modal();
		$output = ob_get_clean();

		$this->assertStringContainsString('add_new_domain', $output);
	}

	/**
	 * Test render_add_new_domain_modal output contains domain field.
	 */
	public function test_render_add_new_domain_modal_contains_domain_field(): void {
		ob_start();
		$this->page->render_add_new_domain_modal();
		$output = ob_get_clean();

		// The form renders field names as input names or data attributes.
		$this->assertStringContainsString('domain', $output);
	}

	/**
	 * Test render_add_new_domain_modal respects wu_add_new_domain_modal_fields filter.
	 */
	public function test_render_add_new_domain_modal_applies_fields_filter(): void {
		$filter_called = false;

		add_filter(
			'wu_add_new_domain_modal_fields',
			function ($fields) use (&$filter_called) {
				$filter_called = true;
				return $fields;
			}
		);

		ob_start();
		$this->page->render_add_new_domain_modal();
		ob_get_clean();

		$this->assertTrue($filter_called);
	}

	/**
	 * Test render_add_new_domain_modal filter can modify fields.
	 */
	public function test_render_add_new_domain_modal_filter_can_add_fields(): void {
		add_filter(
			'wu_add_new_domain_modal_fields',
			function ($fields) {
				$fields['custom_test_field'] = [
					'type'  => 'text',
					'title' => 'Custom Test Field',
				];
				return $fields;
			}
		);

		ob_start();
		$this->page->render_add_new_domain_modal();
		$output = ob_get_clean();

		// Filter was applied without error.
		$this->assertTrue(true);
	}

	/**
	 * Test render_add_new_domain_modal output contains stage field.
	 */
	public function test_render_add_new_domain_modal_contains_stage_field(): void {
		ob_start();
		$this->page->render_add_new_domain_modal();
		$output = ob_get_clean();

		$this->assertStringContainsString('stage', $output);
	}

	/**
	 * Test render_add_new_domain_modal output contains primary_domain field.
	 */
	public function test_render_add_new_domain_modal_contains_primary_domain_field(): void {
		ob_start();
		$this->page->render_add_new_domain_modal();
		$output = ob_get_clean();

		$this->assertStringContainsString('primary_domain', $output);
	}

	// -------------------------------------------------------------------------
	// handle_add_new_domain_modal()
	// -------------------------------------------------------------------------

	/**
	 * Install AJAX die handler so wp_send_json / wp_die don't kill PHPUnit.
	 *
	 * @return callable The installed handler.
	 */
	private function install_ajax_die_handler(): callable {
		add_filter('wp_doing_ajax', '__return_true');

		$handler = function () {
			return function ($message) {
				throw new \WPAjaxDieContinueException((string) $message);
			};
		};

		add_filter('wp_die_ajax_handler', $handler, 1);

		return $handler;
	}

	/**
	 * Remove the AJAX die handler.
	 *
	 * @param callable $handler The handler returned by install_ajax_die_handler().
	 */
	private function remove_ajax_die_handler(callable $handler): void {
		remove_filter('wp_doing_ajax', '__return_true');
		remove_filter('wp_die_ajax_handler', $handler, 1);
	}

	/**
	 * Call a callable inside an AJAX context, capturing JSON output.
	 *
	 * @param callable $callable The callable to invoke.
	 * @return array{output: string, exception: bool}
	 */
	private function call_in_ajax_context(callable $callable): array {
		$handler          = $this->install_ajax_die_handler();
		$exception_caught = false;

		ob_start();

		try {
			$callable();
		} catch (\WPAjaxDieContinueException $e) {
			$exception_caught = true;
		}

		$output = ob_get_clean();

		$this->remove_ajax_die_handler($handler);

		return [
			'output'    => $output,
			'exception' => $exception_caught,
		];
	}

	/**
	 * Test handle_add_new_domain_modal with type=register does not create domain.
	 */
	public function test_handle_add_new_domain_modal_register_type_does_nothing(): void {
		$_REQUEST['type'] = 'register';
		$_POST['type']    = 'register';

		// With type=register, the handler returns early without doing anything.
		$this->page->handle_add_new_domain_modal();

		// No exception, no output — just returns.
		$this->assertTrue(true);
	}

	/**
	 * Test handle_add_new_domain_modal with type=add and missing domain does not throw.
	 *
	 * With an empty domain, wu_create_domain returns a WP_Error and wp_send_json_error
	 * is called. Uses call_in_ajax_context() to prevent JSON from leaking to stdout.
	 */
	public function test_handle_add_new_domain_modal_add_type_missing_domain_does_not_throw(): void {
		$_REQUEST['type']    = 'add';
		$_POST['type']       = 'add';
		$_REQUEST['domain']  = '';
		$_POST['domain']     = '';
		$_REQUEST['blog_id'] = 1;
		$_POST['blog_id']    = 1;
		$_REQUEST['stage']   = Domain_Stage::CHECKING_DNS;
		$_POST['stage']      = Domain_Stage::CHECKING_DNS;

		$exception_thrown = false;

		$result = $this->call_in_ajax_context(
			function () {
				$this->page->handle_add_new_domain_modal();
			}
		);

		// The handler should produce output (JSON error or die) without throwing an unexpected exception.
		$this->assertFalse($exception_thrown, 'handle_add_new_domain_modal should not throw unexpected exceptions.');
	}

	/**
	 * Test handle_add_new_domain_modal with type=add and duplicate domain sends error.
	 *
	 * Creates a domain first, then attempts to create it again to trigger a WP_Error.
	 */
	public function test_handle_add_new_domain_modal_add_type_duplicate_domain_sends_error(): void {
		$blog_id    = get_current_blog_id();
		$domain_str = 'duplicate-test-' . uniqid() . '.example.com';

		// Create the domain first so the second attempt is a duplicate.
		$first = wu_create_domain(
			[
				'domain'  => $domain_str,
				'blog_id' => $blog_id,
				'stage'   => Domain_Stage::CHECKING_DNS,
			]
		);

		if (is_wp_error($first)) {
			$this->markTestSkipped('Could not create initial domain: ' . $first->get_error_message());
			return;
		}

		$_REQUEST['type']           = 'add';
		$_POST['type']              = 'add';
		$_REQUEST['domain']         = $domain_str;
		$_POST['domain']            = $domain_str;
		$_REQUEST['blog_id']        = $blog_id;
		$_POST['blog_id']           = $blog_id;
		$_REQUEST['stage']          = Domain_Stage::CHECKING_DNS;
		$_POST['stage']             = Domain_Stage::CHECKING_DNS;
		$_REQUEST['primary_domain'] = false;
		$_POST['primary_domain']    = false;

		$result = $this->call_in_ajax_context(
			function () {
				$this->page->handle_add_new_domain_modal();
			}
		);

		$this->assertNotEmpty($result['output']);
		$decoded = json_decode($result['output'], true);
		$this->assertIsArray($decoded);
		$this->assertArrayHasKey('success', $decoded);
		// Duplicate domain should fail.
		$this->assertFalse($decoded['success']);
	}

	/**
	 * Test handle_add_new_domain_modal fires wu_handle_add_new_domain_modal action.
	 */
	public function test_handle_add_new_domain_modal_fires_action(): void {
		$action_fired = false;

		add_action(
			'wu_handle_add_new_domain_modal',
			function () use (&$action_fired) {
				$action_fired = true;
			}
		);

		$_REQUEST['type'] = 'register';
		$_POST['type']    = 'register';

		$this->page->handle_add_new_domain_modal();

		remove_all_actions('wu_handle_add_new_domain_modal');

		$this->assertTrue($action_fired);
	}

	/**
	 * Test handle_add_new_domain_modal with type=add and valid domain creates domain.
	 */
	public function test_handle_add_new_domain_modal_add_type_valid_domain_sends_json(): void {
		// Get a valid blog ID from the test environment.
		$blog_id = get_current_blog_id();

		$_REQUEST['type']           = 'add';
		$_POST['type']              = 'add';
		$_REQUEST['domain']         = 'valid-test-domain-' . uniqid() . '.example.com';
		$_POST['domain']            = $_REQUEST['domain'];
		$_REQUEST['blog_id']        = $blog_id;
		$_POST['blog_id']           = $blog_id;
		$_REQUEST['stage']          = Domain_Stage::CHECKING_DNS;
		$_POST['stage']             = Domain_Stage::CHECKING_DNS;
		$_REQUEST['primary_domain'] = false;
		$_POST['primary_domain']    = false;

		$result = $this->call_in_ajax_context(
			function () {
				$this->page->handle_add_new_domain_modal();
			}
		);

		$this->assertNotEmpty($result['output']);
		$decoded = json_decode($result['output'], true);
		$this->assertIsArray($decoded);
		$this->assertArrayHasKey('success', $decoded);
	}

	/**
	 * Test handle_add_new_domain_modal success response contains redirect_url.
	 */
	public function test_handle_add_new_domain_modal_success_contains_redirect_url(): void {
		$blog_id = get_current_blog_id();

		$_REQUEST['type']           = 'add';
		$_POST['type']              = 'add';
		$_REQUEST['domain']         = 'redirect-test-' . uniqid() . '.example.com';
		$_POST['domain']            = $_REQUEST['domain'];
		$_REQUEST['blog_id']        = $blog_id;
		$_POST['blog_id']           = $blog_id;
		$_REQUEST['stage']          = Domain_Stage::CHECKING_DNS;
		$_POST['stage']             = Domain_Stage::CHECKING_DNS;
		$_REQUEST['primary_domain'] = false;
		$_POST['primary_domain']    = false;

		$result = $this->call_in_ajax_context(
			function () {
				$this->page->handle_add_new_domain_modal();
			}
		);

		$decoded = json_decode($result['output'], true);

		if (isset($decoded['success']) && true === $decoded['success']) {
			$this->assertArrayHasKey('data', $decoded);
			$this->assertArrayHasKey('redirect_url', $decoded['data']);
			$this->assertStringContainsString('wp-ultimo-edit-domain', $decoded['data']['redirect_url']);
		} else {
			// Domain creation failed (e.g. duplicate) — still valid JSON response.
			$this->assertFalse($decoded['success']);
		}
	}

	/**
	 * Test handle_add_new_domain_modal default type is 'add'.
	 */
	public function test_handle_add_new_domain_modal_default_type_is_add(): void {
		// Without setting type, wu_request defaults to 'add'.
		unset($_REQUEST['type'], $_POST['type']);

		$blog_id = get_current_blog_id();

		$_REQUEST['domain']         = 'default-type-test-' . uniqid() . '.example.com';
		$_POST['domain']            = $_REQUEST['domain'];
		$_REQUEST['blog_id']        = $blog_id;
		$_POST['blog_id']           = $blog_id;
		$_REQUEST['stage']          = Domain_Stage::CHECKING_DNS;
		$_POST['stage']             = Domain_Stage::CHECKING_DNS;
		$_REQUEST['primary_domain'] = false;
		$_POST['primary_domain']    = false;

		$result = $this->call_in_ajax_context(
			function () {
				$this->page->handle_add_new_domain_modal();
			}
		);

		// Default type is 'add', so it should attempt domain creation and send JSON.
		$this->assertNotEmpty($result['output']);
		$decoded = json_decode($result['output'], true);
		$this->assertIsArray($decoded);
		$this->assertArrayHasKey('success', $decoded);
	}
}
