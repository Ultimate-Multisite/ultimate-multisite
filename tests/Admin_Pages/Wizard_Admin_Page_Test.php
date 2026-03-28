<?php
/**
 * Unit tests for Wizard_Admin_Page abstract class.
 *
 * @package WP_Ultimo\Admin_Pages
 * @since 2.0.0
 */

namespace WP_Ultimo\Admin_Pages;

use WP_UnitTestCase;

/**
 * Minimal concrete implementation of Wizard_Admin_Page for testing.
 *
 * Provides the smallest possible implementation of all abstract methods so
 * the abstract class behaviour can be exercised in isolation.
 */
class Concrete_Wizard_Page extends Wizard_Admin_Page {

	public $id   = 'test-wizard';
	public $type = 'submenu';

	protected $supported_panels = [
		'network_admin_menu' => 'manage_network',
	];

	public function get_title(): string {
		return 'Test Wizard Title';
	}

	public function get_menu_title(): string {
		return 'Test Wizard Menu';
	}

	/**
	 * Returns a minimal set of sections for navigation tests.
	 *
	 * Keys: 'step-one', 'step-two', 'step-three'.
	 *
	 * @return array
	 */
	public function get_sections(): array {
		return [
			'step-one'   => [
				'title'       => 'Step One',
				'description' => 'First step',
			],
			'step-two'   => [
				'title'       => 'Step Two',
				'description' => 'Second step',
			],
			'step-three' => [
				'title'       => 'Step Three',
				'description' => 'Third step',
			],
		];
	}
}

/**
 * Concrete wizard with an addon section to test get_current_section() filtering.
 */
class Concrete_Wizard_Page_With_Addon extends Wizard_Admin_Page {

	public $id   = 'test-wizard-addon';
	public $type = 'submenu';

	protected $supported_panels = [
		'network_admin_menu' => 'manage_network',
	];

	public function get_title(): string {
		return 'Addon Wizard';
	}

	public function get_menu_title(): string {
		return 'Addon Wizard Menu';
	}

	public function get_sections(): array {
		return [
			'main'  => ['title' => 'Main'],
			'extra' => ['title' => 'Extra', 'addon' => true],
		];
	}
}

/**
 * Test suite for Wizard_Admin_Page.
 *
 * Tests all public methods of the abstract class using concrete test doubles.
 * The constructor is bypassed via disableOriginalConstructor() where needed to
 * avoid WordPress hook side-effects in isolated unit tests.
 */
class Wizard_Admin_Page_Test extends WP_UnitTestCase {

	/**
	 * Concrete wizard page instance (constructor bypassed).
	 *
	 * @var Concrete_Wizard_Page
	 */
	protected Concrete_Wizard_Page $page;

	/**
	 * Set up a fresh page instance before each test.
	 */
	public function setUp(): void {
		parent::setUp();

		// Bypass constructor to avoid hook registration side-effects.
		$this->page = $this->getMockBuilder(Concrete_Wizard_Page::class)
			->disableOriginalConstructor()
			->setMethods(null)
			->getMock();
	}

	/**
	 * Tear down: clean up superglobals modified during tests.
	 */
	public function tearDown(): void {
		unset($_GET['step'], $_REQUEST['step']);
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Default property values
	// -------------------------------------------------------------------------

	/**
	 * hide_admin_notices defaults to true on Wizard_Admin_Page.
	 */
	public function test_hide_admin_notices_defaults_to_true(): void {
		$ref = new \ReflectionProperty(Wizard_Admin_Page::class, 'hide_admin_notices');
		$ref->setAccessible(true);

		$this->assertTrue($ref->getValue($this->page));
	}

	/**
	 * fold_menu defaults to true on Wizard_Admin_Page.
	 */
	public function test_fold_menu_defaults_to_true(): void {
		$ref = new \ReflectionProperty(Wizard_Admin_Page::class, 'fold_menu');
		$ref->setAccessible(true);

		$this->assertTrue($ref->getValue($this->page));
	}

	/**
	 * section_slug defaults to 'step'.
	 */
	public function test_section_slug_defaults_to_step(): void {
		$ref = new \ReflectionProperty(Wizard_Admin_Page::class, 'section_slug');
		$ref->setAccessible(true);

		$this->assertSame('step', $ref->getValue($this->page));
	}

	/**
	 * clickable_navigation defaults to false.
	 */
	public function test_clickable_navigation_defaults_to_false(): void {
		$ref = new \ReflectionProperty(Wizard_Admin_Page::class, 'clickable_navigation');
		$ref->setAccessible(true);

		$this->assertFalse($ref->getValue($this->page));
	}

	/**
	 * form_id defaults to empty string.
	 */
	public function test_form_id_defaults_to_empty_string(): void {
		$ref = new \ReflectionProperty(Wizard_Admin_Page::class, 'form_id');
		$ref->setAccessible(true);

		$this->assertSame('', $ref->getValue($this->page));
	}

	// -------------------------------------------------------------------------
	// get_labels()
	// -------------------------------------------------------------------------

	/**
	 * get_labels() returns an array with all required keys.
	 */
	public function test_get_labels_returns_array_with_required_keys(): void {
		$labels = $this->page->get_labels();

		$this->assertIsArray($labels);
		$this->assertArrayHasKey('edit_label', $labels);
		$this->assertArrayHasKey('add_new_label', $labels);
		$this->assertArrayHasKey('updated_message', $labels);
		$this->assertArrayHasKey('title_placeholder', $labels);
		$this->assertArrayHasKey('title_description', $labels);
		$this->assertArrayHasKey('save_button_label', $labels);
		$this->assertArrayHasKey('save_description', $labels);
	}

	/**
	 * get_labels() returns non-empty strings for translatable labels.
	 */
	public function test_get_labels_values_are_strings(): void {
		$labels = $this->page->get_labels();

		$this->assertIsString($labels['edit_label']);
		$this->assertIsString($labels['add_new_label']);
		$this->assertIsString($labels['updated_message']);
		$this->assertIsString($labels['save_button_label']);
	}

	// -------------------------------------------------------------------------
	// get_logo()
	// -------------------------------------------------------------------------

	/**
	 * get_logo() returns an empty string by default.
	 */
	public function test_get_logo_returns_empty_string(): void {
		$this->assertSame('', $this->page->get_logo());
	}

	// -------------------------------------------------------------------------
	// get_classes()
	// -------------------------------------------------------------------------

	/**
	 * get_classes() returns a non-empty CSS class string.
	 */
	public function test_get_classes_returns_non_empty_string(): void {
		$ref = new \ReflectionMethod(Wizard_Admin_Page::class, 'get_classes');
		$ref->setAccessible(true);

		$classes = $ref->invoke($this->page);

		$this->assertIsString($classes);
		$this->assertNotEmpty($classes);
	}

	/**
	 * get_classes() includes expected Tailwind-style utility classes.
	 */
	public function test_get_classes_contains_expected_classes(): void {
		$ref = new \ReflectionMethod(Wizard_Admin_Page::class, 'get_classes');
		$ref->setAccessible(true);

		$classes = $ref->invoke($this->page);

		$this->assertStringContainsString('wu-w-full', $classes);
		$this->assertStringContainsString('wu-mx-auto', $classes);
	}

	// -------------------------------------------------------------------------
	// get_sections() — abstract, tested via concrete implementation
	// -------------------------------------------------------------------------

	/**
	 * get_sections() returns an array with the expected keys.
	 */
	public function test_get_sections_returns_array(): void {
		$sections = $this->page->get_sections();

		$this->assertIsArray($sections);
		$this->assertNotEmpty($sections);
	}

	/**
	 * get_sections() returns the concrete page's sections.
	 */
	public function test_get_sections_contains_expected_keys(): void {
		$sections = $this->page->get_sections();

		$this->assertArrayHasKey('step-one', $sections);
		$this->assertArrayHasKey('step-two', $sections);
		$this->assertArrayHasKey('step-three', $sections);
	}

	// -------------------------------------------------------------------------
	// get_current_section()
	// -------------------------------------------------------------------------

	/**
	 * get_current_section() returns the first section key when no GET param is set.
	 */
	public function test_get_current_section_returns_first_section_by_default(): void {
		unset($_GET['step']);

		$current = $this->page->get_current_section();

		$this->assertSame('step-one', $current);
	}

	/**
	 * get_current_section() returns the section from the GET param when present.
	 */
	public function test_get_current_section_returns_get_param_value(): void {
		$_GET['step'] = 'step-two';

		$current = $this->page->get_current_section();

		$this->assertSame('step-two', $current);
	}

	/**
	 * get_current_section() sanitizes the GET param value.
	 */
	public function test_get_current_section_sanitizes_get_param(): void {
		$_GET['step'] = 'step-two<script>';

		$current = $this->page->get_current_section();

		// sanitize_key strips non-alphanumeric/dash/underscore characters.
		$this->assertStringNotContainsString('<', $current);
		$this->assertStringNotContainsString('>', $current);
	}

	/**
	 * get_current_section() excludes addon sections from the default selection.
	 */
	public function test_get_current_section_excludes_addon_sections(): void {
		unset($_GET['step']);

		$page = $this->getMockBuilder(Concrete_Wizard_Page_With_Addon::class)
			->disableOriginalConstructor()
			->setMethods(null)
			->getMock();

		$current = $page->get_current_section();

		// 'extra' has addon=true and should be excluded; 'main' should be first.
		$this->assertSame('main', $current);
	}

	// -------------------------------------------------------------------------
	// get_first_section()
	// -------------------------------------------------------------------------

	/**
	 * get_first_section() returns the second key in the sections array (index 1).
	 */
	public function test_get_first_section_returns_second_key(): void {
		$first = $this->page->get_first_section();

		// The concrete page has keys: step-one, step-two, step-three.
		// get_first_section() returns keys[1] = 'step-two'.
		$this->assertSame('step-two', $first);
	}

	/**
	 * get_first_section() returns false when sections has only one entry.
	 */
	public function test_get_first_section_returns_false_when_only_one_section(): void {
		$page = $this->getMockBuilder(Concrete_Wizard_Page::class)
			->disableOriginalConstructor()
			->setMethods(['get_sections'])
			->getMock();

		$page->method('get_sections')->willReturn(['only' => ['title' => 'Only']]);

		$this->assertFalse($page->get_first_section());
	}

	// -------------------------------------------------------------------------
	// get_section_link()
	// -------------------------------------------------------------------------

	/**
	 * get_section_link() returns a URL containing the section slug and value.
	 */
	public function test_get_section_link_returns_url_with_section_param(): void {
		$link = $this->page->get_section_link('step-two');

		$this->assertStringContainsString('step=step-two', $link);
	}

	/**
	 * get_section_link() uses the section_slug property as the query key.
	 */
	public function test_get_section_link_uses_section_slug_as_key(): void {
		$ref = new \ReflectionProperty(Wizard_Admin_Page::class, 'section_slug');
		$ref->setAccessible(true);
		$ref->setValue($this->page, 'wizard_step');

		$link = $this->page->get_section_link('my-section');

		$this->assertStringContainsString('wizard_step=my-section', $link);

		// Restore default.
		$ref->setValue($this->page, 'step');
	}

	// -------------------------------------------------------------------------
	// get_next_section_link()
	// -------------------------------------------------------------------------

	/**
	 * get_next_section_link() returns a URL pointing to the section after the current one.
	 */
	public function test_get_next_section_link_returns_next_section(): void {
		$_GET['step'] = 'step-one';

		$link = $this->page->get_next_section_link();

		$this->assertStringContainsString('step=step-two', $link);
	}

	/**
	 * get_next_section_link() advances correctly from the second section.
	 */
	public function test_get_next_section_link_from_second_section(): void {
		$_GET['step'] = 'step-two';

		$link = $this->page->get_next_section_link();

		$this->assertStringContainsString('step=step-three', $link);
	}

	// -------------------------------------------------------------------------
	// get_prev_section_link()
	// -------------------------------------------------------------------------

	/**
	 * get_prev_section_link() returns a URL pointing to the section before the current one.
	 */
	public function test_get_prev_section_link_returns_previous_section(): void {
		$_GET['step'] = 'step-two';

		$link = $this->page->get_prev_section_link();

		$this->assertStringContainsString('step=step-one', $link);
	}

	/**
	 * get_prev_section_link() returns empty string when on the first section.
	 */
	public function test_get_prev_section_link_returns_empty_for_first_section(): void {
		$_GET['step'] = 'step-one';

		$link = $this->page->get_prev_section_link();

		$this->assertSame('', $link);
	}

	/**
	 * get_prev_section_link() returns empty string when current section is not found.
	 */
	public function test_get_prev_section_link_returns_empty_when_section_not_found(): void {
		$_GET['step'] = 'nonexistent-section';

		$link = $this->page->get_prev_section_link();

		$this->assertSame('', $link);
	}

	// -------------------------------------------------------------------------
	// page_loaded()
	// -------------------------------------------------------------------------

	/**
	 * page_loaded() sets current_section to the array for the current step.
	 */
	public function test_page_loaded_sets_current_section(): void {
		unset($_GET['step']);

		$this->page->page_loaded();

		$this->assertIsArray($this->page->current_section);
		$this->assertArrayHasKey('title', $this->page->current_section);
		$this->assertSame('Step One', $this->page->current_section['title']);
	}

	/**
	 * page_loaded() sets current_section to the section matching the GET param.
	 */
	public function test_page_loaded_sets_current_section_from_get_param(): void {
		$_GET['step'] = 'step-two';

		$this->page->page_loaded();

		$this->assertIsArray($this->page->current_section);
		$this->assertSame('Step Two', $this->page->current_section['title']);
	}

	// -------------------------------------------------------------------------
	// process_save()
	// -------------------------------------------------------------------------

	/**
	 * process_save() does nothing when the saving tag is not in the request.
	 */
	public function test_process_save_does_nothing_without_saving_tag(): void {
		unset($_REQUEST['saving_step-one']);

		// Set current_section so process_save() can reference it.
		$this->page->current_section = $this->page->get_sections()['step-one'];

		$this->page->process_save();

		// No exception or error means the guard condition worked.
		$this->assertTrue(true);
	}

	/**
	 * process_save() calls the section handler when saving tag is present and nonce is valid.
	 */
	public function test_process_save_calls_handler_when_saving_tag_present(): void {
		unset($_GET['step']);

		$handler_called = false;

		$this->page->current_section = [
			'title'   => 'Step One',
			'handler' => function () use (&$handler_called) {
				$handler_called = true;
			},
		];

		$saving_tag = 'saving_step-one';

		// Set the saving tag in the request.
		$_REQUEST[ $saving_tag ] = '1';

		// Create a valid nonce.
		$_REQUEST['_wpultimo_nonce'] = wp_create_nonce($saving_tag);

		$this->page->process_save();

		$this->assertTrue($handler_called, 'Section handler should have been called');

		unset($_REQUEST[ $saving_tag ], $_REQUEST['_wpultimo_nonce']);
	}

	/**
	 * process_save() calls default_handler when no handler is set in the section.
	 *
	 * default_handler() calls wp_safe_redirect() which in the test environment
	 * triggers a "headers already sent" PHP error followed by exit. We verify
	 * the handler path is taken by confirming process_save() does not return
	 * normally (it exits via the redirect). We use a custom handler that sets a
	 * flag to confirm the fallback path is exercised.
	 */
	public function test_process_save_calls_default_handler_when_no_handler_set(): void {
		unset($_GET['step']);

		$default_handler_called = false;

		// Override default_handler on the page instance to avoid the redirect.
		$page = $this->getMockBuilder(Concrete_Wizard_Page::class)
			->disableOriginalConstructor()
			->setMethods(['default_handler'])
			->getMock();

		$page->expects($this->once())
			->method('default_handler');

		$page->current_section = ['title' => 'Step One'];

		$saving_tag = 'saving_step-one';

		$_REQUEST[ $saving_tag ]     = '1';
		$_REQUEST['_wpultimo_nonce'] = wp_create_nonce($saving_tag);

		$page->process_save();

		unset($_REQUEST[ $saving_tag ], $_REQUEST['_wpultimo_nonce']);
	}

	// -------------------------------------------------------------------------
	// default_handler()
	// -------------------------------------------------------------------------

	/**
	 * default_handler() calls wp_safe_redirect() to the next section URL.
	 *
	 * wp_safe_redirect() in the test environment triggers a "headers already sent"
	 * PHP error and then calls exit. We verify the redirect target by intercepting
	 * the wp_redirect filter, which fires before the header is sent.
	 */
	public function test_default_handler_redirects_to_next_section(): void {
		$_GET['step'] = 'step-one';

		$redirect_url = null;

		add_filter(
			'wp_redirect',
			function ($location) use (&$redirect_url) {
				$redirect_url = $location;
				// Return false to prevent the actual redirect (and the headers-sent error).
				return false;
			}
		);

		// default_handler calls wp_safe_redirect() which calls wp_redirect().
		// With the filter returning false, wp_redirect() returns false and does
		// NOT call exit, so the method returns normally.
		$this->page->default_handler();

		remove_all_filters('wp_redirect');

		$this->assertNotNull($redirect_url, 'wp_redirect should have been called');
		$this->assertStringContainsString('step=step-two', $redirect_url);
	}

	// -------------------------------------------------------------------------
	// register_widgets()
	// -------------------------------------------------------------------------

	/**
	 * register_widgets() returns early when current_section has a separator key.
	 */
	public function test_register_widgets_returns_early_for_separator_section(): void {
		$this->page->current_section = ['separator' => true, 'title' => 'Sep'];

		$GLOBALS['current_screen'] = \WP_Screen::get('dashboard-network');

		// Should not add any meta box — just returns early.
		$this->page->register_widgets();

		// Verify no meta box was added for this page.
		global $wp_meta_boxes;
		$screen_id = $GLOBALS['current_screen']->id;
		$has_box   = isset($wp_meta_boxes[ $screen_id ]['normal']['']['wp-ultimo-wizard-body']);

		$this->assertFalse((bool) $has_box, 'No meta box should be added for separator sections');

		unset($GLOBALS['current_screen']);
	}

	/**
	 * register_widgets() adds a meta box when current_section is not a separator.
	 */
	public function test_register_widgets_adds_meta_box_for_normal_section(): void {
		$this->page->current_section = ['title' => 'Step One'];

		$GLOBALS['current_screen'] = \WP_Screen::get('dashboard-network');

		$this->page->register_widgets();

		global $wp_meta_boxes;
		$screen_id = $GLOBALS['current_screen']->id;

		// WordPress converts null priority to 'default' in add_meta_box().
		// Search all priority buckets to be resilient to WordPress version differences.
		$has_box = false;
		if (isset($wp_meta_boxes[ $screen_id ]['normal'])) {
			foreach ($wp_meta_boxes[ $screen_id ]['normal'] as $priority_boxes) {
				if (isset($priority_boxes['wp-ultimo-wizard-body'])) {
					$has_box = true;
					break;
				}
			}
		}

		$this->assertTrue($has_box, 'A meta box should be added for normal sections');

		unset($GLOBALS['current_screen']);
	}

	// -------------------------------------------------------------------------
	// output_default_widget_body()
	// -------------------------------------------------------------------------

	/**
	 * output_default_widget_body() wraps output in the expected div.
	 */
	public function test_output_default_widget_body_wraps_in_div(): void {
		$this->page->current_section = [
			'view' => function () {
				echo 'section content';
			},
		];

		ob_start();
		$this->page->output_default_widget_body();
		$output = ob_get_clean();

		$this->assertStringContainsString('wu-p-4', $output);
		$this->assertStringContainsString('data-testid="wizard-content-body"', $output);
		$this->assertStringContainsString('section content', $output);
	}

	/**
	 * output_default_widget_body() calls default_view when no view is set.
	 */
	public function test_output_default_widget_body_calls_default_view_when_no_view(): void {
		// Provide a section without a 'view' key; default_view will be called.
		// default_view calls wu_get_template — just verify no fatal error.
		$this->page->current_section = ['title' => 'Step One'];

		ob_start();
		$this->page->output_default_widget_body();
		$output = ob_get_clean();

		// The wrapper div should always be present.
		$this->assertStringContainsString('wu-p-4', $output);
	}

	// -------------------------------------------------------------------------
	// default_view()
	// -------------------------------------------------------------------------

	/**
	 * default_view() runs without fatal error when current_section has no fields.
	 */
	public function test_default_view_runs_without_error_for_section_without_fields(): void {
		$this->page->current_section = [
			'title'       => 'Step One',
			'description' => 'A description',
		];

		ob_start();
		$this->page->default_view();
		ob_get_clean();

		$this->assertTrue(true, 'default_view() ran without error');
	}

	/**
	 * default_view() runs without fatal error when current_section has callable fields.
	 */
	public function test_default_view_runs_without_error_for_callable_fields(): void {
		$this->page->current_section = [
			'title'  => 'Step One',
			'fields' => function () {
				return [];
			},
		];

		ob_start();
		$this->page->default_view();
		ob_get_clean();

		$this->assertTrue(true, 'default_view() with callable fields ran without error');
	}

	// -------------------------------------------------------------------------
	// render_submit_box()
	// -------------------------------------------------------------------------

	/**
	 * render_submit_box() runs without fatal error.
	 */
	public function test_render_submit_box_runs_without_error(): void {
		$GLOBALS['current_screen'] = \WP_Screen::get('dashboard-network');

		ob_start();
		$this->page->render_submit_box();
		ob_get_clean();

		$this->assertTrue(true, 'render_submit_box() ran without error');

		unset($GLOBALS['current_screen']);
	}

	// -------------------------------------------------------------------------
	// render_installation_steps()
	// -------------------------------------------------------------------------

	/**
	 * render_installation_steps() returns a string.
	 *
	 * The template requires a 'done' key in each step entry.
	 */
	public function test_render_installation_steps_returns_string(): void {
		$steps = [
			'step-a' => [
				'name'   => 'Step A',
				'action' => 'do_something',
				'status' => 'pending',
				'done'   => false,
			],
		];

		$result = $this->page->render_installation_steps($steps);

		$this->assertIsString($result);
	}

	/**
	 * render_installation_steps() accepts a checks=false argument.
	 */
	public function test_render_installation_steps_accepts_checks_false(): void {
		$steps = [
			'step-b' => [
				'name'   => 'Step B',
				'action' => 'do_other',
				'status' => 'pending',
				'done'   => true,
			],
		];

		$result = $this->page->render_installation_steps($steps, false);

		$this->assertIsString($result);
	}

	// -------------------------------------------------------------------------
	// output()
	// -------------------------------------------------------------------------

	/**
	 * output() runs without fatal error (calls wu_get_template internally).
	 */
	public function test_output_runs_without_error(): void {
		unset($_GET['step']);

		$this->page->current_section = $this->page->get_sections()['step-one'];

		$GLOBALS['current_screen'] = \WP_Screen::get('dashboard-network');

		ob_start();
		$this->page->output();
		ob_get_clean();

		$this->assertTrue(true, 'output() ran without error');

		unset($GLOBALS['current_screen']);
	}
}
