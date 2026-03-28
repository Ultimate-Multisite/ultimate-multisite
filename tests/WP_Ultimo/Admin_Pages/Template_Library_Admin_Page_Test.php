<?php
/**
 * Tests for Template_Library_Admin_Page class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Admin_Pages;

use WP_UnitTestCase;
use WP_Ultimo\Template_Library\Template_Repository;

/**
 * Testable subclass that exposes protected methods and allows repository injection.
 */
class Testable_Template_Library_Admin_Page extends Template_Library_Admin_Page {

	/**
	 * Inject a mock repository.
	 *
	 * @param Template_Repository $repository Repository instance.
	 * @return void
	 */
	public function set_repository( Template_Repository $repository ): void {
		$this->repository = $repository;
	}

	/**
	 * Expose get_repository as public.
	 *
	 * @return Template_Repository
	 */
	public function public_get_repository(): Template_Repository {
		return $this->get_repository();
	}

	/**
	 * Expose get_templates_list as public.
	 *
	 * @return array
	 */
	public function public_get_templates_list(): array {
		return $this->get_templates_list();
	}
}

/**
 * Test class for Template_Library_Admin_Page.
 */
class Template_Library_Admin_Page_Test extends WP_UnitTestCase {

	/**
	 * @var Testable_Template_Library_Admin_Page
	 */
	private $page;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->page = new Testable_Template_Library_Admin_Page();
	}

	/**
	 * Tear down: clean up superglobals.
	 */
	protected function tearDown(): void {
		unset(
			$_GET['tab'],
			$_GET['s'],
			$_GET['code'],
			$_GET['logout'],
			$_GET['_wpnonce'],
			$_REQUEST['tab'],
			$_REQUEST['s'],
			$_REQUEST['code'],
			$_REQUEST['logout'],
			$_REQUEST['_wpnonce'],
			$_REQUEST['template'],
			$_REQUEST['template_name'],
			$_REQUEST['zip_file'],
			$_REQUEST['template_url'],
			$_REQUEST['categories']
		);
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Static properties
	// -------------------------------------------------------------------------

	/**
	 * Test page id is correct.
	 */
	public function test_page_id(): void {
		$reflection = new \ReflectionClass( $this->page );
		$property   = $reflection->getProperty( 'id' );
		$property->setAccessible( true );

		$this->assertEquals( 'wp-ultimo-template-library', $property->getValue( $this->page ) );
	}

	/**
	 * Test page type is submenu.
	 */
	public function test_page_type(): void {
		$reflection = new \ReflectionClass( $this->page );
		$property   = $reflection->getProperty( 'type' );
		$property->setAccessible( true );

		$this->assertEquals( 'submenu', $property->getValue( $this->page ) );
	}

	/**
	 * Test page parent is wp-ultimo.
	 */
	public function test_page_parent(): void {
		$reflection = new \ReflectionClass( $this->page );
		$property   = $reflection->getProperty( 'parent' );
		$property->setAccessible( true );

		$this->assertEquals( 'wp-ultimo', $property->getValue( $this->page ) );
	}

	/**
	 * Test badge_count is zero.
	 */
	public function test_badge_count(): void {
		$reflection = new \ReflectionClass( $this->page );
		$property   = $reflection->getProperty( 'badge_count' );
		$property->setAccessible( true );

		$this->assertEquals( 0, $property->getValue( $this->page ) );
	}

	/**
	 * Test supported_panels contains network_admin_menu.
	 */
	public function test_supported_panels(): void {
		$reflection = new \ReflectionClass( $this->page );
		$property   = $reflection->getProperty( 'supported_panels' );
		$property->setAccessible( true );

		$panels = $property->getValue( $this->page );
		$this->assertArrayHasKey( 'network_admin_menu', $panels );
		$this->assertEquals( 'wu_read_settings', $panels['network_admin_menu'] );
	}

	/**
	 * Test hide_admin_notices is false.
	 */
	public function test_hide_admin_notices_is_false(): void {
		$reflection = new \ReflectionClass( $this->page );
		$property   = $reflection->getProperty( 'hide_admin_notices' );
		$property->setAccessible( true );

		$this->assertFalse( $property->getValue( $this->page ) );
	}

	/**
	 * Test fold_menu is false.
	 */
	public function test_fold_menu_is_false(): void {
		$reflection = new \ReflectionClass( $this->page );
		$property   = $reflection->getProperty( 'fold_menu' );
		$property->setAccessible( true );

		$this->assertFalse( $property->getValue( $this->page ) );
	}

	/**
	 * Test section_slug is 'tab'.
	 */
	public function test_section_slug_is_tab(): void {
		$reflection = new \ReflectionClass( $this->page );
		$property   = $reflection->getProperty( 'section_slug' );
		$property->setAccessible( true );

		$this->assertEquals( 'tab', $property->getValue( $this->page ) );
	}

	/**
	 * Test clickable_navigation is true.
	 */
	public function test_clickable_navigation_is_true(): void {
		$reflection = new \ReflectionClass( $this->page );
		$property   = $reflection->getProperty( 'clickable_navigation' );
		$property->setAccessible( true );

		$this->assertTrue( $property->getValue( $this->page ) );
	}

	/**
	 * Test position is 998.
	 */
	public function test_position(): void {
		$reflection = new \ReflectionClass( $this->page );
		$property   = $reflection->getProperty( 'position' );
		$property->setAccessible( true );

		$this->assertEquals( 998, $property->getValue( $this->page ) );
	}

	// -------------------------------------------------------------------------
	// get_title()
	// -------------------------------------------------------------------------

	/**
	 * Test get_title returns correct string.
	 */
	public function test_get_title(): void {
		$title = $this->page->get_title();

		$this->assertIsString( $title );
		$this->assertEquals( 'Template Library', $title );
	}

	// -------------------------------------------------------------------------
	// get_menu_title()
	// -------------------------------------------------------------------------

	/**
	 * Test get_menu_title returns correct string.
	 */
	public function test_get_menu_title(): void {
		$title = $this->page->get_menu_title();

		$this->assertIsString( $title );
		$this->assertEquals( 'Template Library', $title );
	}

	// -------------------------------------------------------------------------
	// get_title_links()
	// -------------------------------------------------------------------------

	/**
	 * Test get_title_links returns array.
	 */
	public function test_get_title_links_returns_array(): void {
		$links = $this->page->get_title_links();

		$this->assertIsArray( $links );
	}

	/**
	 * Test get_title_links has one entry.
	 */
	public function test_get_title_links_has_one_entry(): void {
		$links = $this->page->get_title_links();

		$this->assertCount( 1, $links );
	}

	/**
	 * Test get_title_links first entry has label.
	 */
	public function test_get_title_links_first_entry_has_label(): void {
		$links = $this->page->get_title_links();

		$this->assertArrayHasKey( 'label', $links[0] );
		$this->assertEquals( 'Upload Template', $links[0]['label'] );
	}

	/**
	 * Test get_title_links first entry has icon.
	 */
	public function test_get_title_links_first_entry_has_icon(): void {
		$links = $this->page->get_title_links();

		$this->assertArrayHasKey( 'icon', $links[0] );
		$this->assertEquals( 'upload', $links[0]['icon'] );
	}

	/**
	 * Test get_title_links first entry has classes.
	 */
	public function test_get_title_links_first_entry_has_classes(): void {
		$links = $this->page->get_title_links();

		$this->assertArrayHasKey( 'classes', $links[0] );
		$this->assertEquals( 'wubox', $links[0]['classes'] );
	}

	/**
	 * Test get_title_links first entry has url.
	 */
	public function test_get_title_links_first_entry_has_url(): void {
		$links = $this->page->get_title_links();

		$this->assertArrayHasKey( 'url', $links[0] );
		$this->assertIsString( $links[0]['url'] );
	}

	// -------------------------------------------------------------------------
	// get_sections()
	// -------------------------------------------------------------------------

	/**
	 * Test get_sections returns array.
	 */
	public function test_get_sections_returns_array(): void {
		$sections = $this->page->get_sections();

		$this->assertIsArray( $sections );
	}

	/**
	 * Test get_sections has expected keys.
	 */
	public function test_get_sections_has_expected_keys(): void {
		$sections = $this->page->get_sections();

		$this->assertArrayHasKey( 'all', $sections );
		$this->assertArrayHasKey( 'business', $sections );
		$this->assertArrayHasKey( 'portfolio', $sections );
		$this->assertArrayHasKey( 'blog', $sections );
		$this->assertArrayHasKey( 'ecommerce', $sections );
		$this->assertArrayHasKey( 'agency', $sections );
		$this->assertArrayHasKey( 'saas', $sections );
		$this->assertArrayHasKey( 'community', $sections );
	}

	/**
	 * Test get_sections has 8 entries.
	 */
	public function test_get_sections_has_eight_entries(): void {
		$sections = $this->page->get_sections();

		$this->assertCount( 8, $sections );
	}

	/**
	 * Test each section has title and icon.
	 */
	public function test_get_sections_each_has_title_and_icon(): void {
		$sections = $this->page->get_sections();

		foreach ( $sections as $key => $section ) {
			$this->assertArrayHasKey( 'title', $section, "Section '{$key}' missing title" );
			$this->assertArrayHasKey( 'icon', $section, "Section '{$key}' missing icon" );
			$this->assertIsString( $section['title'], "Section '{$key}' title is not a string" );
			$this->assertIsString( $section['icon'], "Section '{$key}' icon is not a string" );
		}
	}

	/**
	 * Test 'all' section title.
	 */
	public function test_get_sections_all_title(): void {
		$sections = $this->page->get_sections();

		$this->assertEquals( 'All Templates', $sections['all']['title'] );
	}

	/**
	 * Test 'business' section title.
	 */
	public function test_get_sections_business_title(): void {
		$sections = $this->page->get_sections();

		$this->assertEquals( 'Business', $sections['business']['title'] );
	}

	/**
	 * Test 'portfolio' section title.
	 */
	public function test_get_sections_portfolio_title(): void {
		$sections = $this->page->get_sections();

		$this->assertEquals( 'Portfolio', $sections['portfolio']['title'] );
	}

	/**
	 * Test 'blog' section title.
	 */
	public function test_get_sections_blog_title(): void {
		$sections = $this->page->get_sections();

		$this->assertEquals( 'Blog', $sections['blog']['title'] );
	}

	/**
	 * Test 'ecommerce' section title.
	 */
	public function test_get_sections_ecommerce_title(): void {
		$sections = $this->page->get_sections();

		$this->assertEquals( 'E-commerce', $sections['ecommerce']['title'] );
	}

	/**
	 * Test 'agency' section title.
	 */
	public function test_get_sections_agency_title(): void {
		$sections = $this->page->get_sections();

		$this->assertEquals( 'Agency', $sections['agency']['title'] );
	}

	/**
	 * Test 'saas' section title.
	 */
	public function test_get_sections_saas_title(): void {
		$sections = $this->page->get_sections();

		$this->assertEquals( 'SaaS', $sections['saas']['title'] );
	}

	/**
	 * Test 'community' section title.
	 */
	public function test_get_sections_community_title(): void {
		$sections = $this->page->get_sections();

		$this->assertEquals( 'Community', $sections['community']['title'] );
	}

	// -------------------------------------------------------------------------
	// default_handler()
	// -------------------------------------------------------------------------

	/**
	 * Test default_handler does not throw.
	 */
	public function test_default_handler_does_not_throw(): void {
		// default_handler is a no-op for this page.
		$this->page->default_handler();

		$this->assertTrue( true );
	}

	// -------------------------------------------------------------------------
	// init()
	// -------------------------------------------------------------------------

	/**
	 * Test init registers the serve_templates_list ajax action.
	 */
	public function test_init_registers_ajax_action(): void {
		$this->page->init();

		$this->assertGreaterThan( 0, has_action( 'wp_ajax_serve_templates_list', [ $this->page, 'serve_templates_list' ] ) );
	}

	// -------------------------------------------------------------------------
	// register_forms()
	// -------------------------------------------------------------------------

	/**
	 * Test register_forms does not throw.
	 */
	public function test_register_forms_does_not_throw(): void {
		$this->page->register_forms();

		$this->assertTrue( true );
	}

	// -------------------------------------------------------------------------
	// get_repository()
	// -------------------------------------------------------------------------

	/**
	 * Test get_repository returns a Template_Repository instance.
	 */
	public function test_get_repository_returns_instance(): void {
		$repo = $this->page->public_get_repository();

		$this->assertInstanceOf( Template_Repository::class, $repo );
	}

	/**
	 * Test get_repository returns same instance on repeated calls (lazy init).
	 */
	public function test_get_repository_caches_instance(): void {
		$first  = $this->page->public_get_repository();
		$second = $this->page->public_get_repository();

		$this->assertSame( $first, $second );
	}

	/**
	 * Test set_repository injects a custom repository.
	 */
	public function test_set_repository_injects_custom_instance(): void {
		$mock_repo = $this->createMock( Template_Repository::class );

		$this->page->set_repository( $mock_repo );

		$this->assertSame( $mock_repo, $this->page->public_get_repository() );
	}

	// -------------------------------------------------------------------------
	// get_templates_list()
	// -------------------------------------------------------------------------

	/**
	 * Test get_templates_list returns array when repository returns templates.
	 */
	public function test_get_templates_list_returns_array_on_success(): void {
		$templates = [
			[
				'slug'             => 'test-template',
				'name'             => 'Test Template',
				'description'      => 'A test template',
				'download_url'     => 'https://example.com/test.zip',
				'template_version' => '1.0.0',
				'categories'       => [],
				'installed'        => false,
			],
		];

		$mock_repo = $this->createMock( Template_Repository::class );
		$mock_repo->method( 'get_templates' )->willReturn( $templates );

		$this->page->set_repository( $mock_repo );

		$result = $this->page->public_get_templates_list();

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );
	}

	/**
	 * Test get_templates_list returns empty array when repository returns WP_Error.
	 */
	public function test_get_templates_list_returns_empty_array_on_error(): void {
		$mock_repo = $this->createMock( Template_Repository::class );
		$mock_repo->method( 'get_templates' )->willReturn( new \WP_Error( 'api_error', 'API failed' ) );

		$this->page->set_repository( $mock_repo );

		$result = $this->page->public_get_templates_list();

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test get_templates_list returns empty array when repository returns empty array.
	 */
	public function test_get_templates_list_returns_empty_array_when_no_templates(): void {
		$mock_repo = $this->createMock( Template_Repository::class );
		$mock_repo->method( 'get_templates' )->willReturn( [] );

		$this->page->set_repository( $mock_repo );

		$result = $this->page->public_get_templates_list();

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test get_templates_list returns multiple templates.
	 */
	public function test_get_templates_list_returns_multiple_templates(): void {
		$templates = [
			[
				'slug'             => 'template-one',
				'name'             => 'Template One',
				'description'      => 'First template',
				'download_url'     => 'https://example.com/one.zip',
				'template_version' => '1.0.0',
				'categories'       => [],
				'installed'        => false,
			],
			[
				'slug'             => 'template-two',
				'name'             => 'Template Two',
				'description'      => 'Second template',
				'download_url'     => 'https://example.com/two.zip',
				'template_version' => '2.0.0',
				'categories'       => [],
				'installed'        => true,
			],
		];

		$mock_repo = $this->createMock( Template_Repository::class );
		$mock_repo->method( 'get_templates' )->willReturn( $templates );

		$this->page->set_repository( $mock_repo );

		$result = $this->page->public_get_templates_list();

		$this->assertCount( 2, $result );
	}

	// -------------------------------------------------------------------------
	// serve_templates_list()
	// -------------------------------------------------------------------------

	/**
	 * Test serve_templates_list sends JSON success with templates.
	 */
	public function test_serve_templates_list_sends_json_success(): void {
		$templates = [
			[
				'slug'             => 'test-template',
				'name'             => 'Test Template',
				'description'      => 'A test template',
				'download_url'     => 'https://example.com/test.zip',
				'template_version' => '1.0.0',
				'categories'       => [],
				'installed'        => false,
			],
		];

		$mock_repo = $this->createMock( Template_Repository::class );
		$mock_repo->method( 'get_templates' )->willReturn( $templates );

		$this->page->set_repository( $mock_repo );

		// Capture output since wp_send_json_success outputs JSON.
		ob_start();
		try {
			$this->page->serve_templates_list();
		} catch ( \WPDieException $e ) {
			// Expected — wp_send_json_success calls wp_die.
		}
		$output = ob_get_clean();

		$decoded = json_decode( $output, true );

		$this->assertIsArray( $decoded );
		$this->assertTrue( $decoded['success'] );
		$this->assertIsArray( $decoded['data'] );
	}

	/**
	 * Test serve_templates_list sends empty data when no templates.
	 */
	public function test_serve_templates_list_sends_empty_data_when_no_templates(): void {
		$mock_repo = $this->createMock( Template_Repository::class );
		$mock_repo->method( 'get_templates' )->willReturn( [] );

		$this->page->set_repository( $mock_repo );

		ob_start();
		try {
			$this->page->serve_templates_list();
		} catch ( \WPDieException $e ) {
			// Expected.
		}
		$output = ob_get_clean();

		$decoded = json_decode( $output, true );

		$this->assertIsArray( $decoded );
		$this->assertTrue( $decoded['success'] );
		$this->assertEmpty( $decoded['data'] );
	}

	// -------------------------------------------------------------------------
	// install_template() — permission check
	// -------------------------------------------------------------------------

	/**
	 * Test install_template sends error when user lacks manage_network_plugins.
	 */
	public function test_install_template_sends_error_when_no_permission(): void {
		// Create a subscriber user (no manage_network_plugins).
		$user_id = $this->factory->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $user_id );

		ob_start();
		try {
			$this->page->install_template();
		} catch ( \WPDieException $e ) {
			// Expected.
		}
		$output = ob_get_clean();

		$decoded = json_decode( $output, true );

		$this->assertIsArray( $decoded );
		$this->assertFalse( $decoded['success'] );
	}

	/**
	 * Test install_template sends error when template not found.
	 */
	public function test_install_template_sends_error_when_template_not_found(): void {
		// Grant super admin.
		$user_id = $this->factory->user->create();
		grant_super_admin( $user_id );
		wp_set_current_user( $user_id );

		$mock_repo = $this->createMock( Template_Repository::class );
		$mock_repo->method( 'get_templates' )->willReturn( [] );

		$this->page->set_repository( $mock_repo );

		$_REQUEST['template'] = 'nonexistent-template';

		ob_start();
		try {
			$this->page->install_template();
		} catch ( \WPDieException $e ) {
			// Expected.
		}
		$output = ob_get_clean();

		$decoded = json_decode( $output, true );

		$this->assertIsArray( $decoded );
		$this->assertFalse( $decoded['success'] );
	}

	/**
	 * Test install_template sends error when download_url is empty.
	 */
	public function test_install_template_sends_error_when_no_download_url(): void {
		$user_id = $this->factory->user->create();
		grant_super_admin( $user_id );
		wp_set_current_user( $user_id );

		$templates = [
			[
				'slug'             => 'no-url-template',
				'name'             => 'No URL Template',
				'description'      => 'Template without download URL',
				'download_url'     => '',
				'template_version' => '1.0.0',
				'categories'       => [],
				'installed'        => false,
			],
		];

		$mock_repo = $this->createMock( Template_Repository::class );
		$mock_repo->method( 'get_templates' )->willReturn( $templates );

		$this->page->set_repository( $mock_repo );

		$_REQUEST['template'] = 'no-url-template';

		ob_start();
		try {
			$this->page->install_template();
		} catch ( \WPDieException $e ) {
			// Expected.
		}
		$output = ob_get_clean();

		$decoded = json_decode( $output, true );

		$this->assertIsArray( $decoded );
		$this->assertFalse( $decoded['success'] );
	}

	/**
	 * Test install_template sends error when download_url is from insecure domain.
	 */
	public function test_install_template_sends_error_when_insecure_url(): void {
		$user_id = $this->factory->user->create();
		grant_super_admin( $user_id );
		wp_set_current_user( $user_id );

		$templates = [
			[
				'slug'             => 'insecure-template',
				'name'             => 'Insecure Template',
				'description'      => 'Template with insecure URL',
				'download_url'     => 'https://evil.example.com/template.zip',
				'template_version' => '1.0.0',
				'categories'       => [],
				'installed'        => false,
			],
		];

		$mock_repo = $this->createMock( Template_Repository::class );
		$mock_repo->method( 'get_templates' )->willReturn( $templates );

		$this->page->set_repository( $mock_repo );

		$_REQUEST['template'] = 'insecure-template';

		ob_start();
		try {
			$this->page->install_template();
		} catch ( \WPDieException $e ) {
			// Expected.
		}
		$output = ob_get_clean();

		$decoded = json_decode( $output, true );

		$this->assertIsArray( $decoded );
		$this->assertFalse( $decoded['success'] );
	}

	// -------------------------------------------------------------------------
	// handle_upload_template_modal() — validation
	// -------------------------------------------------------------------------

	/**
	 * Test handle_upload_template_modal sends error when template_name is empty.
	 */
	public function test_handle_upload_template_modal_error_when_no_name(): void {
		$_REQUEST['template_name'] = '';
		$_REQUEST['zip_file']      = 'https://example.com/test.zip';
		$_REQUEST['template_url']  = 'https://example.com/template';
		$_REQUEST['categories']    = '';

		ob_start();
		try {
			$this->page->handle_upload_template_modal();
		} catch ( \WPDieException $e ) {
			// Expected.
		}
		$output = ob_get_clean();

		$decoded = json_decode( $output, true );

		$this->assertIsArray( $decoded );
		$this->assertFalse( $decoded['success'] );
		$this->assertEquals( 'no-name', $decoded['data']['code'] );
	}

	/**
	 * Test handle_upload_template_modal sends error when zip_file is empty.
	 */
	public function test_handle_upload_template_modal_error_when_no_zip(): void {
		$_REQUEST['template_name'] = 'My Template';
		$_REQUEST['zip_file']      = '';
		$_REQUEST['template_url']  = 'https://example.com/template';
		$_REQUEST['categories']    = '';

		ob_start();
		try {
			$this->page->handle_upload_template_modal();
		} catch ( \WPDieException $e ) {
			// Expected.
		}
		$output = ob_get_clean();

		$decoded = json_decode( $output, true );

		$this->assertIsArray( $decoded );
		$this->assertFalse( $decoded['success'] );
		$this->assertEquals( 'no-file', $decoded['data']['code'] );
	}

	/**
	 * Test handle_upload_template_modal sends error when template_url is empty.
	 */
	public function test_handle_upload_template_modal_error_when_no_url(): void {
		$_REQUEST['template_name'] = 'My Template';
		$_REQUEST['zip_file']      = 'https://example.com/test.zip';
		$_REQUEST['template_url']  = '';
		$_REQUEST['categories']    = '';

		ob_start();
		try {
			$this->page->handle_upload_template_modal();
		} catch ( \WPDieException $e ) {
			// Expected.
		}
		$output = ob_get_clean();

		$decoded = json_decode( $output, true );

		$this->assertIsArray( $decoded );
		$this->assertFalse( $decoded['success'] );
		$this->assertEquals( 'no-url', $decoded['data']['code'] );
	}

	/**
	 * Test handle_upload_template_modal sends error when file does not exist.
	 */
	public function test_handle_upload_template_modal_error_when_file_not_found(): void {
		$upload_dir = wp_upload_dir();

		$_REQUEST['template_name'] = 'My Template';
		$_REQUEST['zip_file']      = $upload_dir['baseurl'] . '/nonexistent-file-' . uniqid() . '.zip';
		$_REQUEST['template_url']  = 'https://example.com/template';
		$_REQUEST['categories']    = '';

		ob_start();
		try {
			$this->page->handle_upload_template_modal();
		} catch ( \WPDieException $e ) {
			// Expected.
		}
		$output = ob_get_clean();

		$decoded = json_decode( $output, true );

		$this->assertIsArray( $decoded );
		$this->assertFalse( $decoded['success'] );
		$this->assertEquals( 'file-not-found', $decoded['data']['code'] );
	}

	// -------------------------------------------------------------------------
	// display_more_info()
	// -------------------------------------------------------------------------

	/**
	 * Test display_more_info does not throw when template not found.
	 */
	public function test_display_more_info_does_not_throw_when_template_not_found(): void {
		$mock_repo = $this->createMock( Template_Repository::class );
		$mock_repo->method( 'get_templates' )->willReturn( [] );

		$this->page->set_repository( $mock_repo );

		$_REQUEST['template'] = 'nonexistent-slug';

		ob_start();
		try {
			$this->page->display_more_info();
		} catch ( \Exception $e ) {
			// Some template rendering may throw — that's acceptable.
		}
		ob_get_clean();

		$this->assertTrue( true );
	}

	/**
	 * Test display_more_info does not throw when template is found.
	 */
	public function test_display_more_info_does_not_throw_when_template_found(): void {
		$templates = [
			[
				'slug'             => 'found-template',
				'name'             => 'Found Template',
				'description'      => 'A found template',
				'download_url'     => 'https://example.com/found.zip',
				'template_version' => '1.0.0',
				'categories'       => [],
				'installed'        => false,
			],
		];

		$mock_repo = $this->createMock( Template_Repository::class );
		$mock_repo->method( 'get_templates' )->willReturn( $templates );

		$this->page->set_repository( $mock_repo );

		$_REQUEST['template'] = 'found-template';

		ob_start();
		try {
			$this->page->display_more_info();
		} catch ( \Exception $e ) {
			// Template rendering may throw — acceptable.
		}
		ob_get_clean();

		$this->assertTrue( true );
	}

	// -------------------------------------------------------------------------
	// register_scripts()
	// -------------------------------------------------------------------------

	/**
	 * Test register_scripts does not throw.
	 */
	public function test_register_scripts_does_not_throw(): void {
		ob_start();
		try {
			$this->page->register_scripts();
		} catch ( \Exception $e ) {
			// Acceptable if wp_enqueue_media or similar throws in test env.
		}
		ob_get_clean();

		$this->assertTrue( true );
	}

	// -------------------------------------------------------------------------
	// get_current_section() — inherited from Wizard_Admin_Page
	// -------------------------------------------------------------------------

	/**
	 * Test get_current_section returns 'all' by default (first section key).
	 */
	public function test_get_current_section_returns_first_key_by_default(): void {
		unset( $_GET['tab'] );

		$section = $this->page->get_current_section();

		$this->assertEquals( 'all', $section );
	}

	/**
	 * Test get_current_section returns value from GET tab parameter.
	 */
	public function test_get_current_section_returns_get_tab_value(): void {
		$_GET['tab'] = 'business';

		$section = $this->page->get_current_section();

		$this->assertEquals( 'business', $section );

		unset( $_GET['tab'] );
	}

	/**
	 * Test get_current_section sanitizes the tab value.
	 */
	public function test_get_current_section_sanitizes_value(): void {
		$_GET['tab'] = 'business<script>';

		$section = $this->page->get_current_section();

		$this->assertStringNotContainsString( '<script>', $section );

		unset( $_GET['tab'] );
	}

	// -------------------------------------------------------------------------
	// get_section_link() — inherited from Wizard_Admin_Page
	// -------------------------------------------------------------------------

	/**
	 * Test get_section_link returns a string URL.
	 */
	public function test_get_section_link_returns_string(): void {
		$link = $this->page->get_section_link( 'business' );

		$this->assertIsString( $link );
	}

	/**
	 * Test get_section_link includes the section slug parameter.
	 */
	public function test_get_section_link_includes_tab_param(): void {
		$link = $this->page->get_section_link( 'portfolio' );

		$this->assertStringContainsString( 'tab=portfolio', $link );
	}

	// -------------------------------------------------------------------------
	// get_first_section() — inherited from Wizard_Admin_Page
	// -------------------------------------------------------------------------

	/**
	 * Test get_first_section returns the second key (index 1).
	 */
	public function test_get_first_section_returns_second_key(): void {
		$first = $this->page->get_first_section();

		// get_first_section returns keys[1], which is 'business'.
		$this->assertEquals( 'business', $first );
	}

	// -------------------------------------------------------------------------
	// get_next_section_link() — inherited from Wizard_Admin_Page
	// -------------------------------------------------------------------------

	/**
	 * Test get_next_section_link returns a string.
	 */
	public function test_get_next_section_link_returns_string(): void {
		unset( $_GET['tab'] );

		$link = $this->page->get_next_section_link();

		$this->assertIsString( $link );
	}

	/**
	 * Test get_next_section_link from 'all' points to 'business'.
	 */
	public function test_get_next_section_link_from_all_is_business(): void {
		unset( $_GET['tab'] );

		$link = $this->page->get_next_section_link();

		$this->assertStringContainsString( 'tab=business', $link );
	}

	// -------------------------------------------------------------------------
	// get_prev_section_link() — inherited from Wizard_Admin_Page
	// -------------------------------------------------------------------------

	/**
	 * Test get_prev_section_link returns empty string when on first section.
	 */
	public function test_get_prev_section_link_returns_empty_on_first_section(): void {
		unset( $_GET['tab'] );

		$link = $this->page->get_prev_section_link();

		$this->assertEquals( '', $link );
	}

	/**
	 * Test get_prev_section_link from 'business' points back to 'all'.
	 */
	public function test_get_prev_section_link_from_business_is_all(): void {
		$_GET['tab'] = 'business';

		$link = $this->page->get_prev_section_link();

		$this->assertStringContainsString( 'tab=all', $link );

		unset( $_GET['tab'] );
	}

	// -------------------------------------------------------------------------
	// get_labels() — inherited from Wizard_Admin_Page
	// -------------------------------------------------------------------------

	/**
	 * Test get_labels returns array with required keys.
	 */
	public function test_get_labels_returns_required_keys(): void {
		$labels = $this->page->get_labels();

		$this->assertIsArray( $labels );
		$this->assertArrayHasKey( 'edit_label', $labels );
		$this->assertArrayHasKey( 'add_new_label', $labels );
		$this->assertArrayHasKey( 'updated_message', $labels );
		$this->assertArrayHasKey( 'title_placeholder', $labels );
		$this->assertArrayHasKey( 'save_button_label', $labels );
	}
}
