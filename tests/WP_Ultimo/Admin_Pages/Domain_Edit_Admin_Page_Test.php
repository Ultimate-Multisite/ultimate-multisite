<?php
/**
 * Tests for Domain_Edit_Admin_Page class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Admin_Pages;

use WP_UnitTestCase;
use WP_Ultimo\Models\Domain;
use WP_Ultimo\Database\Domains\Domain_Stage;

/**
 * Concrete subclass that exposes protected methods and allows object injection.
 */
class Testable_Domain_Edit_Admin_Page extends Domain_Edit_Admin_Page {

	/**
	 * Override get_object to allow injecting a domain without DB lookup.
	 *
	 * @return \WP_Ultimo\Models\Domain
	 */
	public function get_object() {

		if (null !== $this->object) {
			return $this->object;
		}

		return parent::get_object();
	}
}

/**
 * Test class for Domain_Edit_Admin_Page.
 */
class Domain_Edit_Admin_Page_Test extends WP_UnitTestCase {

	/**
	 * @var Testable_Domain_Edit_Admin_Page
	 */
	private $page;

	/**
	 * Blog ID used for domain tests.
	 *
	 * @var int
	 */
	private $blog_id;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {

		parent::setUp();

		$this->page    = new Testable_Domain_Edit_Admin_Page();
		// Use the main site blog ID (1) to avoid switch_to_blog() DB connection issues.
		$this->blog_id = 1;
	}

	/**
	 * Tear down: clean up superglobals.
	 */
	protected function tearDown(): void {

		unset(
			$_GET['id'],
			$_POST['primary_domain'],
			$_POST['active'],
			$_POST['secure'],
			$_REQUEST['id'],
			$_REQUEST['primary_domain'],
			$_REQUEST['active'],
			$_REQUEST['secure'],
			$_REQUEST['domain_id'],
			$_REQUEST['record_id'],
			$_REQUEST['set_domain_as_primary'],
			$_REQUEST['nonce']
		);

		parent::tearDown();
	}

	/**
	 * Helper: create and save a domain for testing.
	 *
	 * @param array $overrides Optional attribute overrides.
	 * @return Domain
	 */
	private function create_domain( array $overrides = [] ): Domain {

		$domain = new Domain(
			array_merge(
				[
					'domain'         => 'test-' . uniqid() . '.example.com',
					'blog_id'        => $this->blog_id,
					'active'         => true,
					'primary_domain' => false,
					'secure'         => false,
					'stage'          => Domain_Stage::DONE,
				],
				$overrides
			)
		);

		$saved = $domain->save();

		if (is_wp_error($saved)) {
			$this->markTestSkipped('Could not save domain: ' . $saved->get_error_message());
		}

		return $domain;
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

		$this->assertEquals('wp-ultimo-edit-domain', $property->getValue($this->page));
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
	 * Test object_id is domain.
	 */
	public function test_object_id(): void {

		$this->assertEquals('domain', $this->page->object_id);
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
		$this->assertEquals('wu_edit_domains', $panels['network_admin_menu']);
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
	 * Test highlight_menu_slug is set correctly.
	 */
	public function test_highlight_menu_slug(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('highlight_menu_slug');
		$property->setAccessible(true);

		$this->assertEquals('wp-ultimo-domains', $property->getValue($this->page));
	}

	/**
	 * Test parent property is none.
	 */
	public function test_parent_is_none(): void {

		$reflection = new \ReflectionClass($this->page);
		$property   = $reflection->getProperty('parent');
		$property->setAccessible(true);

		$this->assertEquals('none', $property->getValue($this->page));
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
		$this->assertEquals('Add new Domain', $title);
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
		$this->assertEquals('Edit Domain', $title);
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
		$this->assertEquals('Edit Domain', $title);
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
	 * Test has_title returns false.
	 */
	public function test_has_title_returns_false(): void {

		$this->assertFalse($this->page->has_title());
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

		$this->assertEquals('Edit Domain', $labels['edit_label']);
	}

	/**
	 * Test get_labels add_new_label value.
	 */
	public function test_get_labels_add_new_label(): void {

		$labels = $this->page->get_labels();

		$this->assertEquals('Add new Domain', $labels['add_new_label']);
	}

	/**
	 * Test get_labels updated_message value.
	 */
	public function test_get_labels_updated_message(): void {

		$labels = $this->page->get_labels();

		$this->assertEquals('Domain updated with success!', $labels['updated_message']);
	}

	/**
	 * Test get_labels save_button_label value.
	 */
	public function test_get_labels_save_button_label(): void {

		$labels = $this->page->get_labels();

		$this->assertEquals('Save Domain', $labels['save_button_label']);
	}

	/**
	 * Test get_labels delete_button_label value.
	 */
	public function test_get_labels_delete_button_label(): void {

		$labels = $this->page->get_labels();

		$this->assertEquals('Delete Domain', $labels['delete_button_label']);
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

		$this->assertEquals('Enter Domain', $labels['title_placeholder']);
	}

	// -------------------------------------------------------------------------
	// get_object()
	// -------------------------------------------------------------------------

	/**
	 * Test get_object returns pre-set domain object.
	 */
	public function test_get_object_returns_preset_object(): void {

		$domain = new Domain();
		$domain->set_domain('preset.example.com');
		$domain->set_blog_id($this->blog_id);

		$this->page->object = $domain;

		$result = $this->page->get_object();

		$this->assertSame($domain, $result);
	}

	/**
	 * Test get_object returns same instance on repeated calls (caching).
	 */
	public function test_get_object_caches_instance(): void {

		$domain = new Domain();
		$domain->set_domain('cached.example.com');
		$domain->set_blog_id($this->blog_id);

		$this->page->object = $domain;

		$first  = $this->page->get_object();
		$second = $this->page->get_object();

		$this->assertSame($first, $second);
	}

	/**
	 * Test get_object returns preset object when object property is set (avoids DB lookup).
	 */
	public function test_get_object_returns_preset_when_object_set(): void {

		$domain = new Domain();
		$domain->set_domain('preset2.example.com');
		$domain->set_blog_id($this->blog_id);

		$page         = new Testable_Domain_Edit_Admin_Page();
		$page->object = $domain;

		$result = $page->get_object();

		$this->assertSame($domain, $result);
		$this->assertInstanceOf(Domain::class, $result);
	}

	// -------------------------------------------------------------------------
	// query_filter()
	// -------------------------------------------------------------------------

	/**
	 * Test query_filter merges object_type and object_id into args.
	 */
	public function test_query_filter_merges_object_type(): void {

		$domain = new Domain();
		$domain->set_domain('filter.example.com');
		$domain->set_blog_id($this->blog_id);

		$this->page->object = $domain;

		$args   = ['some_arg' => 'value'];
		$result = $this->page->query_filter($args);

		$this->assertArrayHasKey('object_type', $result);
		$this->assertEquals('domain', $result['object_type']);
	}

	/**
	 * Test query_filter merges object_id from the current object.
	 */
	public function test_query_filter_merges_object_id(): void {

		$domain = $this->create_domain();
		$this->page->object = $domain;

		$result = $this->page->query_filter([]);

		$this->assertArrayHasKey('object_id', $result);
		$this->assertEquals($domain->get_id(), $result['object_id']);
	}

	/**
	 * Test query_filter preserves existing args.
	 */
	public function test_query_filter_preserves_existing_args(): void {

		$domain = new Domain();
		$domain->set_domain('preserve.example.com');
		$domain->set_blog_id($this->blog_id);

		$this->page->object = $domain;

		$args   = ['existing_key' => 'existing_value', 'number' => 10];
		$result = $this->page->query_filter($args);

		$this->assertEquals('existing_value', $result['existing_key']);
		$this->assertEquals(10, $result['number']);
	}

	/**
	 * Test query_filter returns array.
	 */
	public function test_query_filter_returns_array(): void {

		$domain = new Domain();
		$domain->set_domain('array.example.com');
		$domain->set_blog_id($this->blog_id);

		$this->page->object = $domain;

		$result = $this->page->query_filter([]);

		$this->assertIsArray($result);
	}

	// -------------------------------------------------------------------------
	// sites_query_filter()
	// -------------------------------------------------------------------------

	/**
	 * Test sites_query_filter sets blog_id from domain's site_id.
	 */
	public function test_sites_query_filter_sets_blog_id(): void {

		$domain = new Domain();
		$domain->set_domain('sites.example.com');
		$domain->set_blog_id($this->blog_id);

		$this->page->object = $domain;

		$result = $this->page->sites_query_filter([]);

		$this->assertArrayHasKey('blog_id', $result);
		$this->assertEquals($this->blog_id, $result['blog_id']);
	}

	/**
	 * Test sites_query_filter preserves existing args.
	 */
	public function test_sites_query_filter_preserves_existing_args(): void {

		$domain = new Domain();
		$domain->set_domain('sites2.example.com');
		$domain->set_blog_id($this->blog_id);

		$this->page->object = $domain;

		$args   = ['number' => 5, 'orderby' => 'title'];
		$result = $this->page->sites_query_filter($args);

		$this->assertEquals(5, $result['number']);
		$this->assertEquals('title', $result['orderby']);
	}

	/**
	 * Test sites_query_filter returns array.
	 */
	public function test_sites_query_filter_returns_array(): void {

		$domain = new Domain();
		$domain->set_domain('sites3.example.com');
		$domain->set_blog_id($this->blog_id);

		$this->page->object = $domain;

		$result = $this->page->sites_query_filter([]);

		$this->assertIsArray($result);
	}

	// -------------------------------------------------------------------------
	// register_forms()
	// -------------------------------------------------------------------------

	/**
	 * Test register_forms adds the delete domain modal filter.
	 */
	public function test_register_forms_adds_delete_domain_filter(): void {

		$this->page->register_forms();

		$this->assertGreaterThan(
			0,
			has_filter('wu_form_fields_delete_domain_modal')
		);

		remove_all_filters('wu_form_fields_delete_domain_modal');
		remove_all_actions('wu_after_delete_domain_modal');
	}

	/**
	 * Test register_forms adds the after delete domain action.
	 */
	public function test_register_forms_adds_after_delete_action(): void {

		$this->page->register_forms();

		$this->assertGreaterThan(
			0,
			has_action('wu_after_delete_domain_modal')
		);

		remove_all_filters('wu_form_fields_delete_domain_modal');
		remove_all_actions('wu_after_delete_domain_modal');
	}

	// -------------------------------------------------------------------------
	// domain_extra_delete_fields()
	// -------------------------------------------------------------------------

	/**
	 * Test domain_extra_delete_fields returns array with required keys.
	 */
	public function test_domain_extra_delete_fields_returns_required_keys(): void {

		$domain = $this->create_domain(['primary_domain' => false]);

		$result = $this->page->domain_extra_delete_fields([], $domain);

		$this->assertIsArray($result);
		$this->assertArrayHasKey('confirm', $result);
		$this->assertArrayHasKey('submit_button', $result);
		$this->assertArrayHasKey('id', $result);
	}

	/**
	 * Test domain_extra_delete_fields confirm field is toggle type.
	 */
	public function test_domain_extra_delete_fields_confirm_is_toggle(): void {

		$domain = $this->create_domain(['primary_domain' => false]);

		$result = $this->page->domain_extra_delete_fields([], $domain);

		$this->assertEquals('toggle', $result['confirm']['type']);
	}

	/**
	 * Test domain_extra_delete_fields id field contains domain id.
	 */
	public function test_domain_extra_delete_fields_id_field_contains_domain_id(): void {

		$domain = $this->create_domain(['primary_domain' => false]);

		$result = $this->page->domain_extra_delete_fields([], $domain);

		$this->assertEquals('hidden', $result['id']['type']);
		$this->assertEquals($domain->get_id(), $result['id']['value']);
	}

	/**
	 * Test domain_extra_delete_fields merges with existing fields.
	 */
	public function test_domain_extra_delete_fields_merges_with_existing_fields(): void {

		$domain = $this->create_domain(['primary_domain' => false]);

		$existing_fields = ['existing_field' => ['type' => 'text']];
		$result          = $this->page->domain_extra_delete_fields($existing_fields, $domain);

		$this->assertArrayHasKey('existing_field', $result);
	}

	/**
	 * Test domain_extra_delete_fields includes set_domain_as_primary for primary domain.
	 */
	public function test_domain_extra_delete_fields_includes_primary_domain_field(): void {

		$domain = $this->create_domain(['primary_domain' => true]);

		$result = $this->page->domain_extra_delete_fields([], $domain);

		$this->assertArrayHasKey('set_domain_as_primary', $result);
		$this->assertEquals('model', $result['set_domain_as_primary']['type']);
	}

	/**
	 * Test domain_extra_delete_fields submit button is submit type.
	 */
	public function test_domain_extra_delete_fields_submit_button_type(): void {

		$domain = $this->create_domain(['primary_domain' => false]);

		$result = $this->page->domain_extra_delete_fields([], $domain);

		$this->assertEquals('submit', $result['submit_button']['type']);
	}

	/**
	 * Test domain_extra_delete_fields for non-primary domain has v-if false on primary field.
	 */
	public function test_domain_extra_delete_fields_non_primary_hides_primary_field(): void {

		$domain = $this->create_domain(['primary_domain' => false]);

		$result = $this->page->domain_extra_delete_fields([], $domain);

		$this->assertArrayHasKey('set_domain_as_primary', $result);
		$this->assertEquals('false', $result['set_domain_as_primary']['wrapper_html_attr']['v-if']);
	}

	// -------------------------------------------------------------------------
	// domain_after_delete_actions()
	// -------------------------------------------------------------------------

	/**
	 * Test domain_after_delete_actions does nothing when no new primary domain requested.
	 */
	public function test_domain_after_delete_actions_does_nothing_without_request(): void {

		$domain = $this->create_domain();

		unset($_REQUEST['set_domain_as_primary']);

		// Should not throw.
		$this->page->domain_after_delete_actions($domain);

		$this->assertTrue(true);
	}

	/**
	 * Test domain_after_delete_actions sets new primary domain when valid domain id provided.
	 */
	public function test_domain_after_delete_actions_sets_new_primary_domain(): void {

		$new_primary = $this->create_domain(['primary_domain' => false]);

		$_REQUEST['set_domain_as_primary'] = $new_primary->get_id();

		$domain = $this->create_domain(['primary_domain' => true]);

		$this->page->domain_after_delete_actions($domain);

		unset($_REQUEST['set_domain_as_primary']);

		// Reload from DB to verify.
		$reloaded = wu_get_domain($new_primary->get_id());

		$this->assertNotFalse($reloaded);
		$this->assertTrue($reloaded->is_primary_domain());
	}

	// -------------------------------------------------------------------------
	// handle_save()
	// -------------------------------------------------------------------------

	/**
	 * Test handle_save sets primary_domain to false when not in POST.
	 */
	public function test_handle_save_sets_primary_domain_false_when_absent(): void {

		$mock = $this->createMock(Domain::class);
		$mock->method('save')->willReturn(new \WP_Error('test', 'Error'));
		$mock->method('load_attributes_from_post')->willReturn(null);
		$mock->method('get_id')->willReturn(0);

		$this->page->object = $mock;

		unset($_POST['primary_domain']);

		$this->page->handle_save();

		$this->assertFalse($_POST['primary_domain']);
	}

	/**
	 * Test handle_save sets active to false when not in POST.
	 */
	public function test_handle_save_sets_active_false_when_absent(): void {

		$mock = $this->createMock(Domain::class);
		$mock->method('save')->willReturn(new \WP_Error('test', 'Error'));
		$mock->method('load_attributes_from_post')->willReturn(null);
		$mock->method('get_id')->willReturn(0);

		$this->page->object = $mock;

		unset($_POST['active']);

		$this->page->handle_save();

		$this->assertFalse($_POST['active']);
	}

	/**
	 * Test handle_save sets secure to false when not in POST.
	 */
	public function test_handle_save_sets_secure_false_when_absent(): void {

		$mock = $this->createMock(Domain::class);
		$mock->method('save')->willReturn(new \WP_Error('test', 'Error'));
		$mock->method('load_attributes_from_post')->willReturn(null);
		$mock->method('get_id')->willReturn(0);

		$this->page->object = $mock;

		unset($_POST['secure']);

		$this->page->handle_save();

		$this->assertFalse($_POST['secure']);
	}

	/**
	 * Test handle_save returns false when parent save fails.
	 */
	public function test_handle_save_returns_false_on_save_error(): void {

		$mock = $this->createMock(Domain::class);
		$mock->method('save')->willReturn(new \WP_Error('test_error', 'Save failed'));
		$mock->method('load_attributes_from_post')->willReturn(null);
		$mock->method('get_id')->willReturn(0);

		$this->page->object = $mock;

		$result = $this->page->handle_save();

		$this->assertFalse($result);
	}

	/**
	 * Test handle_save preserves primary_domain when set in REQUEST.
	 *
	 * handle_save() reads from wu_request() which reads $_REQUEST, not $_POST.
	 * When primary_domain is present in $_REQUEST, it should not be overridden.
	 */
	public function test_handle_save_preserves_primary_domain_when_set(): void {

		$mock = $this->createMock(Domain::class);
		$mock->method('save')->willReturn(new \WP_Error('test', 'Error'));
		$mock->method('load_attributes_from_post')->willReturn(null);
		$mock->method('get_id')->willReturn(0);

		$this->page->object = $mock;

		// wu_request() reads from $_REQUEST.
		$_REQUEST['primary_domain'] = '1';
		$_POST['primary_domain']    = '1';

		$this->page->handle_save();

		unset($_REQUEST['primary_domain']);

		// primary_domain was present in REQUEST — handle_save should not override it.
		$this->assertNotFalse($_POST['primary_domain']);
	}

	// -------------------------------------------------------------------------
	// register_widgets()
	// -------------------------------------------------------------------------

	/**
	 * Test register_widgets does not throw with a domain object set.
	 */
	public function test_register_widgets_does_not_throw(): void {

		set_current_screen('dashboard-network');

		$domain = new Domain();
		$domain->set_domain('widget.example.com');
		$domain->set_blog_id($this->blog_id);
		$domain->set_stage(Domain_Stage::DONE);
		$domain->set_active(true);
		$domain->set_primary_domain(false);
		$domain->set_secure(false);

		$this->page->object = $domain;

		$this->page->register_widgets();

		$this->assertTrue(true);
	}

	/**
	 * Test register_widgets does not throw in edit mode.
	 */
	public function test_register_widgets_does_not_throw_in_edit_mode(): void {

		set_current_screen('dashboard-network');

		$domain = new Domain();
		$domain->set_domain('edit-mode.example.com');
		$domain->set_blog_id($this->blog_id);
		$domain->set_stage(Domain_Stage::DONE);

		$this->page->object = $domain;
		$this->page->edit   = true;

		$this->page->register_widgets();

		$this->assertTrue(true);
	}

	/**
	 * Test register_widgets with primary domain set.
	 */
	public function test_register_widgets_with_primary_domain(): void {

		set_current_screen('dashboard-network');

		$domain = new Domain();
		$domain->set_domain('primary.example.com');
		$domain->set_blog_id($this->blog_id);
		$domain->set_primary_domain(true);
		$domain->set_stage(Domain_Stage::DONE);

		$this->page->object = $domain;

		$this->page->register_widgets();

		$this->assertTrue(true);
	}

	/**
	 * Test register_widgets with secure domain.
	 */
	public function test_register_widgets_with_secure_domain(): void {

		set_current_screen('dashboard-network');

		$domain = new Domain();
		$domain->set_domain('secure.example.com');
		$domain->set_blog_id($this->blog_id);
		$domain->set_secure(true);
		$domain->set_stage(Domain_Stage::DONE);

		$this->page->object = $domain;

		$this->page->register_widgets();

		$this->assertTrue(true);
	}

	/**
	 * Test register_widgets with inactive domain in checking-dns stage.
	 */
	public function test_register_widgets_with_checking_dns_stage(): void {

		set_current_screen('dashboard-network');

		$domain = new Domain();
		$domain->set_domain('checking.example.com');
		$domain->set_blog_id($this->blog_id);
		$domain->set_active(false);
		$domain->set_stage(Domain_Stage::CHECKING_DNS);

		$this->page->object = $domain;

		$this->page->register_widgets();

		$this->assertTrue(true);
	}

	/**
	 * Test register_widgets with failed stage.
	 */
	public function test_register_widgets_with_failed_stage(): void {

		set_current_screen('dashboard-network');

		$domain = new Domain();
		$domain->set_domain('failed.example.com');
		$domain->set_blog_id($this->blog_id);
		$domain->set_stage(Domain_Stage::FAILED);

		$this->page->object = $domain;

		$this->page->register_widgets();

		$this->assertTrue(true);
	}

	/**
	 * Test register_widgets with done-without-ssl stage.
	 */
	public function test_register_widgets_with_done_without_ssl_stage(): void {

		set_current_screen('dashboard-network');

		$domain = new Domain();
		$domain->set_domain('no-ssl.example.com');
		$domain->set_blog_id($this->blog_id);
		$domain->set_stage(Domain_Stage::DONE_WITHOUT_SSL);

		$this->page->object = $domain;

		$this->page->register_widgets();

		$this->assertTrue(true);
	}

	// -------------------------------------------------------------------------
	// render_dns_widget()
	// -------------------------------------------------------------------------

	/**
	 * Test render_dns_widget does not throw.
	 */
	public function test_render_dns_widget_does_not_throw(): void {

		$domain = new Domain();
		$domain->set_domain('dns-widget.example.com');
		$domain->set_blog_id($this->blog_id);

		$this->page->object = $domain;

		ob_start();
		$this->page->render_dns_widget();
		$output = ob_get_clean();

		// Should produce some output (template rendered).
		$this->assertIsString($output);
	}

	// -------------------------------------------------------------------------
	// render_log_widget()
	// -------------------------------------------------------------------------

	/**
	 * Test render_log_widget does not throw.
	 */
	public function test_render_log_widget_does_not_throw(): void {

		$domain = new Domain();
		$domain->set_domain('log-widget.example.com');
		$domain->set_blog_id($this->blog_id);

		$this->page->object = $domain;

		ob_start();
		$this->page->render_log_widget();
		$output = ob_get_clean();

		$this->assertIsString($output);
	}

	// -------------------------------------------------------------------------
	// render_admin_add_dns_record_modal()
	// -------------------------------------------------------------------------

	/**
	 * Test render_admin_add_dns_record_modal dies when domain not found.
	 */
	public function test_render_admin_add_dns_record_modal_dies_without_domain(): void {

		$_REQUEST['domain_id'] = 999999;

		$this->expectException(\WPDieException::class);

		$this->page->render_admin_add_dns_record_modal();
	}

	/**
	 * Test render_admin_add_dns_record_modal renders when domain exists.
	 */
	public function test_render_admin_add_dns_record_modal_renders_with_domain(): void {

		$domain = $this->create_domain();

		$_REQUEST['domain_id'] = $domain->get_id();

		ob_start();
		$this->page->render_admin_add_dns_record_modal();
		$output = ob_get_clean();

		unset($_REQUEST['domain_id']);

		$this->assertIsString($output);
	}

	// -------------------------------------------------------------------------
	// render_admin_edit_dns_record_modal()
	// -------------------------------------------------------------------------

	/**
	 * Test render_admin_edit_dns_record_modal dies when domain not found.
	 */
	public function test_render_admin_edit_dns_record_modal_dies_without_domain(): void {

		$_REQUEST['domain_id'] = 999999;
		$_REQUEST['record_id'] = 'some-record-id';

		$this->expectException(\WPDieException::class);

		$this->page->render_admin_edit_dns_record_modal();
	}

	/**
	 * Test render_admin_edit_dns_record_modal renders when domain exists.
	 */
	public function test_render_admin_edit_dns_record_modal_renders_with_domain(): void {

		$domain = $this->create_domain();

		$_REQUEST['domain_id'] = $domain->get_id();
		$_REQUEST['record_id'] = 'test-record-id';

		ob_start();
		$this->page->render_admin_edit_dns_record_modal();
		$output = ob_get_clean();

		unset($_REQUEST['domain_id'], $_REQUEST['record_id']);

		$this->assertIsString($output);
	}

	// -------------------------------------------------------------------------
	// render_admin_delete_dns_record_modal()
	// -------------------------------------------------------------------------

	/**
	 * Test render_admin_delete_dns_record_modal dies when domain not found.
	 */
	public function test_render_admin_delete_dns_record_modal_dies_without_domain(): void {

		$_REQUEST['domain_id'] = 999999;
		$_REQUEST['record_id'] = 'some-record-id';

		$this->expectException(\WPDieException::class);

		$this->page->render_admin_delete_dns_record_modal();
	}

	/**
	 * Test render_admin_delete_dns_record_modal renders when domain exists.
	 */
	public function test_render_admin_delete_dns_record_modal_renders_with_domain(): void {

		$domain = $this->create_domain();

		$_REQUEST['domain_id'] = $domain->get_id();
		$_REQUEST['record_id'] = 'test-record-id';

		ob_start();
		$this->page->render_admin_delete_dns_record_modal();
		$output = ob_get_clean();

		unset($_REQUEST['domain_id'], $_REQUEST['record_id']);

		$this->assertIsString($output);
	}

	// -------------------------------------------------------------------------
	// handle_admin_add_dns_record_modal()
	// -------------------------------------------------------------------------

	/**
	 * Test handle_admin_add_dns_record_modal sends JSON error when domain not found.
	 */
	public function test_handle_admin_add_dns_record_modal_error_without_domain(): void {

		$_REQUEST['domain_id'] = 999999;
		$_REQUEST['nonce']     = wp_create_nonce('wu_dns_nonce');

		$this->expectException(\WPDieException::class);

		$this->page->handle_admin_add_dns_record_modal();
	}

	/**
	 * Test handle_admin_add_dns_record_modal sends JSON error when no DNS provider.
	 */
	public function test_handle_admin_add_dns_record_modal_error_without_provider(): void {

		$domain = $this->create_domain();

		$_REQUEST['domain_id'] = $domain->get_id();
		$_REQUEST['nonce']     = wp_create_nonce('wu_dns_nonce');

		$this->expectException(\WPDieException::class);

		$this->page->handle_admin_add_dns_record_modal();
	}

	// -------------------------------------------------------------------------
	// handle_admin_edit_dns_record_modal()
	// -------------------------------------------------------------------------

	/**
	 * Test handle_admin_edit_dns_record_modal sends JSON error when domain not found.
	 */
	public function test_handle_admin_edit_dns_record_modal_error_without_domain(): void {

		$_REQUEST['domain_id'] = 999999;
		$_REQUEST['record_id'] = 'test-id';
		$_REQUEST['nonce']     = wp_create_nonce('wu_dns_nonce');

		$this->expectException(\WPDieException::class);

		$this->page->handle_admin_edit_dns_record_modal();
	}

	/**
	 * Test handle_admin_edit_dns_record_modal sends JSON error when no DNS provider.
	 */
	public function test_handle_admin_edit_dns_record_modal_error_without_provider(): void {

		$domain = $this->create_domain();

		$_REQUEST['domain_id'] = $domain->get_id();
		$_REQUEST['record_id'] = 'test-id';
		$_REQUEST['nonce']     = wp_create_nonce('wu_dns_nonce');

		$this->expectException(\WPDieException::class);

		$this->page->handle_admin_edit_dns_record_modal();
	}

	// -------------------------------------------------------------------------
	// handle_admin_delete_dns_record_modal()
	// -------------------------------------------------------------------------

	/**
	 * Test handle_admin_delete_dns_record_modal sends JSON error when domain not found.
	 */
	public function test_handle_admin_delete_dns_record_modal_error_without_domain(): void {

		$_REQUEST['domain_id'] = 999999;
		$_REQUEST['record_id'] = 'test-id';
		$_REQUEST['nonce']     = wp_create_nonce('wu_dns_nonce');

		$this->expectException(\WPDieException::class);

		$this->page->handle_admin_delete_dns_record_modal();
	}

	/**
	 * Test handle_admin_delete_dns_record_modal sends JSON error when no DNS provider.
	 */
	public function test_handle_admin_delete_dns_record_modal_error_without_provider(): void {

		$domain = $this->create_domain();

		$_REQUEST['domain_id'] = $domain->get_id();
		$_REQUEST['record_id'] = 'test-id';
		$_REQUEST['nonce']     = wp_create_nonce('wu_dns_nonce');

		$this->expectException(\WPDieException::class);

		$this->page->handle_admin_delete_dns_record_modal();
	}
}
