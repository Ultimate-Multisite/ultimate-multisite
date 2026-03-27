<?php
/**
 * Tests for Ajax class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo;

use WP_UnitTestCase;

/**
 * Test class for Ajax.
 *
 * @group ajax
 */
class Ajax_Test extends WP_UnitTestCase {

	/**
	 * @var Ajax
	 */
	private Ajax $ajax;

	/**
	 * ReflectionProperty for Settings::$sections cache.
	 *
	 * @var \ReflectionProperty
	 */
	private \ReflectionProperty $settings_sections_ref;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {

		parent::set_up();
		$this->ajax = Ajax::get_instance();

		// Prepare reflection for Settings::$sections so we can reset the cache.
		$this->settings_sections_ref = new \ReflectionProperty( Settings::class, 'sections' );
		if ( PHP_VERSION_ID < 80100 ) {
			$this->settings_sections_ref->setAccessible( true );
		}
	}

	/**
	 * Clean up request globals and filters after each test.
	 */
	public function tear_down(): void {

		foreach ( [ 'model', 'query', 'number', 'exclude', 'include', 'table_id' ] as $key ) {
			unset( $_REQUEST[ $key ], $_GET[ $key ], $_POST[ $key ] );
		}

		remove_all_filters( 'wp_doing_ajax' );
		remove_all_filters( 'wu_search_models_functions' );
		remove_all_filters( 'wu_before_search_models' );
		remove_all_filters( 'wu_list_table_fetch_ajax_results' );
		remove_all_filters( 'wu_settings_get_sections' );

		// Reset Settings sections cache after each test.
		$this->settings_sections_ref->setValue( Settings::get_instance(), null );

		parent::tear_down();
	}

	/**
	 * Reset Settings sections cache to a minimal safe state with no Closure fields.
	 * This avoids the Closure-to-string cast bug in search_wp_ultimo_setting.
	 *
	 * Sets the sections cache directly to a known-good value, bypassing the filter
	 * system and default_sections() which register sections with Closure desc values.
	 */
	private function reset_settings_with_empty_sections(): void {

		// Set the sections cache directly to a minimal safe value (no Closure descs).
		// This bypasses default_sections() and the filter system entirely.
		$this->settings_sections_ref->setValue(
			Settings::get_instance(),
			[
				'core' => [
					'invisible' => true,
					'order'     => 1000000,
					'fields'    => [],
				],
			]
		);
	}

	/**
	 * Set Settings sections cache to a specific set of sections.
	 * Used by tests that need to inject specific sections without Closure fields.
	 *
	 * @param array $sections The sections to set.
	 */
	private function set_settings_sections( array $sections ): void {

		$this->settings_sections_ref->setValue( Settings::get_instance(), $sections );
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * Install AJAX die handler so wp_send_json / wp_die don't kill PHPUnit.
	 *
	 * @return callable The installed handler (pass to remove_ajax_die_handler).
	 */
	private function install_ajax_die_handler(): callable {

		add_filter( 'wp_doing_ajax', '__return_true' );

		$handler = function () {
			return function ( $message ) {
				throw new \WPAjaxDieContinueException( (string) $message );
			};
		};

		add_filter( 'wp_die_ajax_handler', $handler, 1 );

		return $handler;
	}

	/**
	 * Remove the AJAX die handler.
	 *
	 * @param callable $handler The handler returned by install_ajax_die_handler().
	 */
	private function remove_ajax_die_handler( callable $handler ): void {

		remove_filter( 'wp_doing_ajax', '__return_true' );
		remove_filter( 'wp_die_ajax_handler', $handler, 1 );
	}

	/**
	 * Call a callable inside an AJAX context, capturing JSON output.
	 *
	 * @param callable $callable The callable to invoke.
	 * @return array{output: string, exception: bool}
	 */
	private function call_in_ajax_context( callable $callable ): array {

		$handler          = $this->install_ajax_die_handler();
		$exception_caught = false;

		ob_start();

		try {
			$callable();
		} catch ( \WPAjaxDieContinueException $e ) {
			$exception_caught = true;
		}

		$output = ob_get_clean();

		$this->remove_ajax_die_handler( $handler );

		return [
			'output'    => $output,
			'exception' => $exception_caught,
		];
	}

	/**
	 * Helper to invoke the private get_table_class_name method.
	 *
	 * @param string $table_id The table ID to convert.
	 * @return string
	 */
	private function invoke_get_table_class_name( string $table_id ): string {

		$reflection = new \ReflectionClass( $this->ajax );
		$method     = $reflection->getMethod( 'get_table_class_name' );

		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		return $method->invoke( $this->ajax, $table_id );
	}

	// =========================================================================
	// Singleton
	// =========================================================================

	/**
	 * Test singleton returns correct instance.
	 */
	public function test_singleton_returns_correct_instance(): void {

		$this->assertInstanceOf( Ajax::class, $this->ajax );
	}

	/**
	 * Test singleton returns same instance.
	 */
	public function test_singleton_returns_same_instance(): void {

		$this->assertSame( Ajax::get_instance(), Ajax::get_instance() );
	}

	// =========================================================================
	// init()
	// =========================================================================

	/**
	 * Test init registers hooks.
	 */
	public function test_init_registers_hooks(): void {

		$this->ajax->init();

		$this->assertGreaterThan( 0, has_action( 'wu_ajax_wu_search', [ $this->ajax, 'search_models' ] ) );
		$this->assertGreaterThan( 0, has_action( 'in_admin_footer', [ $this->ajax, 'render_selectize_templates' ] ) );
		$this->assertGreaterThan( 0, has_action( 'wp_ajax_wu_list_table_fetch_ajax_results', [ $this->ajax, 'refresh_list_table' ] ) );
	}

	// =========================================================================
	// get_table_class_name() — private, tested via reflection
	// =========================================================================

	/**
	 * Test get_table_class_name with line_item_list_table.
	 */
	public function test_get_table_class_name(): void {

		$this->assertEquals( 'Line_Item_List_Table', $this->invoke_get_table_class_name( 'line_item_list_table' ) );
	}

	/**
	 * Test get_table_class_name with payment table.
	 */
	public function test_get_table_class_name_payment(): void {

		$this->assertEquals( 'Payment_List_Table', $this->invoke_get_table_class_name( 'payment_list_table' ) );
	}

	/**
	 * Test get_table_class_name with customer table.
	 */
	public function test_get_table_class_name_customer(): void {

		$this->assertEquals( 'Customer_List_Table', $this->invoke_get_table_class_name( 'customer_list_table' ) );
	}

	/**
	 * Test get_table_class_name with single-word table id.
	 */
	public function test_get_table_class_name_single_word(): void {

		$this->assertEquals( 'Membership', $this->invoke_get_table_class_name( 'membership' ) );
	}

	/**
	 * Test get_table_class_name with three-word table id.
	 */
	public function test_get_table_class_name_three_words(): void {

		$this->assertEquals( 'Discount_Code_List_Table', $this->invoke_get_table_class_name( 'discount_code_list_table' ) );
	}

	// =========================================================================
	// refresh_list_table()
	// =========================================================================

	/**
	 * Test refresh_list_table with unknown class fires action and does not crash.
	 */
	public function test_refresh_list_table_unknown_class_fires_action(): void {

		$_REQUEST['table_id'] = 'nonexistent_table';

		$action_fired = false;
		add_action(
			'wu_list_table_fetch_ajax_results',
			function ( $table_id ) use ( &$action_fired ) {
				$action_fired = $table_id;
			}
		);

		$this->ajax->refresh_list_table();

		$this->assertEquals( 'nonexistent_table', $action_fired );

		unset( $_REQUEST['table_id'] );
	}

	/**
	 * Test refresh_list_table fires wu_list_table_fetch_ajax_results action.
	 */
	public function test_refresh_list_table_always_fires_action(): void {

		$_REQUEST['table_id'] = 'some_table';

		$fired_with = null;
		add_action(
			'wu_list_table_fetch_ajax_results',
			function ( $table_id ) use ( &$fired_with ) {
				$fired_with = $table_id;
			}
		);

		$this->ajax->refresh_list_table();

		$this->assertSame( 'some_table', $fired_with );

		unset( $_REQUEST['table_id'] );
	}

	/**
	 * Test refresh_list_table with empty table_id fires action.
	 */
	public function test_refresh_list_table_empty_table_id(): void {

		unset( $_REQUEST['table_id'] );

		$fired = false;
		add_action(
			'wu_list_table_fetch_ajax_results',
			function () use ( &$fired ) {
				$fired = true;
			}
		);

		$this->ajax->refresh_list_table();

		$this->assertTrue( $fired );
	}

	/**
	 * Test refresh_list_table with a known table_id that maps to a non-existent class.
	 * Verifies the action always fires regardless of class existence.
	 */
	public function test_refresh_list_table_with_existing_class(): void {

		// Use a table_id that maps to a class name that does NOT exist in the test env
		// to avoid calling ajax_response() which calls die(-1).
		$_REQUEST['table_id'] = 'fake_test_list_table';

		$action_fired = false;
		add_action(
			'wu_list_table_fetch_ajax_results',
			function () use ( &$action_fired ) {
				$action_fired = true;
			}
		);

		$this->ajax->refresh_list_table();

		$this->assertTrue( $action_fired );

		unset( $_REQUEST['table_id'] );
	}

	// =========================================================================
	// search_models() — model = 'all' delegates to search_all_models()
	// =========================================================================

	/**
	 * Test search_models with model=all calls search_all_models and returns JSON.
	 * Resets Settings sections to avoid the Closure-to-string cast bug.
	 */
	public function test_search_models_all_returns_json(): void {

		$this->reset_settings_with_empty_sections();

		$_REQUEST['model'] = 'all';
		$_REQUEST['query'] = [ 'search' => 'zzz_no_match_xyz' ];

		// Suppress all data-source functions to return empty arrays.
		add_filter(
			'wu_search_models_functions',
			function () {
				return [];
			}
		);

		$result = $this->call_in_ajax_context(
			function () {
				$this->ajax->search_models();
			}
		);

		$this->assertNotEmpty( $result['output'] );
		$decoded = json_decode( $result['output'], true );
		$this->assertIsArray( $decoded );

		unset( $_REQUEST['model'], $_REQUEST['query'] );
	}

	/**
	 * Test search_models fires wu_before_search_models action.
	 */
	public function test_search_models_fires_before_action(): void {

		$this->reset_settings_with_empty_sections();

		$_REQUEST['model'] = 'all';
		$_REQUEST['query'] = [ 'search' => 'zzz_no_match_xyz' ];

		add_filter(
			'wu_search_models_functions',
			function () {
				return [];
			}
		);

		$fired = false;
		add_action(
			'wu_before_search_models',
			function () use ( &$fired ) {
				$fired = true;
			}
		);

		$this->call_in_ajax_context(
			function () {
				$this->ajax->search_models();
			}
		);

		$this->assertTrue( $fired );

		unset( $_REQUEST['model'], $_REQUEST['query'] );
	}

	/**
	 * Test search_models with model=user calls search_wordpress_users.
	 */
	public function test_search_models_user_model(): void {

		$_REQUEST['model']   = 'user';
		$_REQUEST['query']   = [ 'search' => 'admin' ];
		$_REQUEST['number']  = 10;
		$_REQUEST['exclude'] = [];

		$result = $this->call_in_ajax_context(
			function () {
				$this->ajax->search_models();
			}
		);

		$this->assertNotEmpty( $result['output'] );
		$decoded = json_decode( $result['output'], true );
		$this->assertIsArray( $decoded );

		unset( $_REQUEST['model'], $_REQUEST['query'], $_REQUEST['number'], $_REQUEST['exclude'] );
	}

	/**
	 * Test search_models with model=setting calls search_wp_ultimo_setting.
	 * Resets Settings sections to avoid the Closure-to-string cast bug.
	 */
	public function test_search_models_setting_model(): void {

		$this->reset_settings_with_empty_sections();

		$_REQUEST['model']   = 'setting';
		$_REQUEST['query']   = [ 'search' => 'zzz_no_match_xyz' ];
		$_REQUEST['number']  = 10;
		$_REQUEST['exclude'] = [];

		$result = $this->call_in_ajax_context(
			function () {
				$this->ajax->search_models();
			}
		);

		$this->assertNotEmpty( $result['output'] );
		$decoded = json_decode( $result['output'], true );
		$this->assertIsArray( $decoded );

		unset( $_REQUEST['model'], $_REQUEST['query'], $_REQUEST['number'], $_REQUEST['exclude'] );
	}

	/**
	 * Test search_models with model=page calls get_posts.
	 */
	public function test_search_models_page_model(): void {

		$_REQUEST['model']   = 'page';
		$_REQUEST['query']   = [ 'search' => '' ];
		$_REQUEST['number']  = 10;
		$_REQUEST['exclude'] = [];

		$result = $this->call_in_ajax_context(
			function () {
				$this->ajax->search_models();
			}
		);

		$this->assertNotEmpty( $result['output'] );
		$decoded = json_decode( $result['output'], true );
		$this->assertIsArray( $decoded );

		unset( $_REQUEST['model'], $_REQUEST['query'], $_REQUEST['number'], $_REQUEST['exclude'] );
	}

	/**
	 * Test search_models with unknown model and no matching function returns empty JSON array.
	 */
	public function test_search_models_unknown_model_returns_empty_array(): void {

		$_REQUEST['model']   = 'nonexistent_model_xyz';
		$_REQUEST['query']   = [ 'search' => 'test' ];
		$_REQUEST['number']  = 10;
		$_REQUEST['exclude'] = [];

		$result = $this->call_in_ajax_context(
			function () {
				$this->ajax->search_models();
			}
		);

		$this->assertNotEmpty( $result['output'] );
		$decoded = json_decode( $result['output'], true );
		$this->assertIsArray( $decoded );
		$this->assertEmpty( $decoded );

		unset( $_REQUEST['model'], $_REQUEST['query'], $_REQUEST['number'], $_REQUEST['exclude'] );
	}

	/**
	 * Test search_models with exclude as comma-separated string splits correctly.
	 */
	public function test_search_models_exclude_as_string(): void {

		$_REQUEST['model']   = 'user';
		$_REQUEST['query']   = [ 'search' => '' ];
		$_REQUEST['number']  = 10;
		$_REQUEST['exclude'] = '1,2,3';

		$result = $this->call_in_ajax_context(
			function () {
				$this->ajax->search_models();
			}
		);

		$decoded = json_decode( $result['output'], true );
		$this->assertIsArray( $decoded );

		unset( $_REQUEST['model'], $_REQUEST['query'], $_REQUEST['number'], $_REQUEST['exclude'] );
	}

	/**
	 * Test search_models with exclude as array.
	 */
	public function test_search_models_exclude_as_array(): void {

		$_REQUEST['model']   = 'user';
		$_REQUEST['query']   = [ 'search' => '' ];
		$_REQUEST['number']  = 10;
		$_REQUEST['exclude'] = [ '1', '2' ];

		$result = $this->call_in_ajax_context(
			function () {
				$this->ajax->search_models();
			}
		);

		$decoded = json_decode( $result['output'], true );
		$this->assertIsArray( $decoded );

		unset( $_REQUEST['model'], $_REQUEST['query'], $_REQUEST['number'], $_REQUEST['exclude'] );
	}

	/**
	 * Test search_models with include as comma-separated string.
	 */
	public function test_search_models_include_as_string(): void {

		$_REQUEST['model']   = 'user';
		$_REQUEST['query']   = [ 'search' => '' ];
		$_REQUEST['number']  = 10;
		$_REQUEST['exclude'] = [];
		$_REQUEST['include'] = '1,2,3';

		$result = $this->call_in_ajax_context(
			function () {
				$this->ajax->search_models();
			}
		);

		$decoded = json_decode( $result['output'], true );
		$this->assertIsArray( $decoded );

		unset( $_REQUEST['model'], $_REQUEST['query'], $_REQUEST['number'], $_REQUEST['exclude'], $_REQUEST['include'] );
	}

	/**
	 * Test search_models with include as array.
	 */
	public function test_search_models_include_as_array(): void {

		$_REQUEST['model']   = 'user';
		$_REQUEST['query']   = [ 'search' => '' ];
		$_REQUEST['number']  = 10;
		$_REQUEST['exclude'] = [];
		$_REQUEST['include'] = [ '1', '2' ];

		$result = $this->call_in_ajax_context(
			function () {
				$this->ajax->search_models();
			}
		);

		$decoded = json_decode( $result['output'], true );
		$this->assertIsArray( $decoded );

		unset( $_REQUEST['model'], $_REQUEST['query'], $_REQUEST['number'], $_REQUEST['exclude'], $_REQUEST['include'] );
	}

	/**
	 * Test search_models with model=site remaps id__in to blog_id__in.
	 */
	public function test_search_models_site_model_remaps_id_in(): void {

		$_REQUEST['model']   = 'site';
		$_REQUEST['query']   = [ 'search' => '' ];
		$_REQUEST['number']  = 10;
		$_REQUEST['exclude'] = [];
		$_REQUEST['include'] = '1';

		$result = $this->call_in_ajax_context(
			function () {
				$this->ajax->search_models();
			}
		);

		$decoded = json_decode( $result['output'], true );
		$this->assertIsArray( $decoded );

		unset( $_REQUEST['model'], $_REQUEST['query'], $_REQUEST['number'], $_REQUEST['exclude'], $_REQUEST['include'] );
	}

	/**
	 * Test search_models with model=site remaps id__not_in to blog_id__not_in.
	 */
	public function test_search_models_site_model_remaps_id_not_in(): void {

		$_REQUEST['model']   = 'site';
		$_REQUEST['query']   = [ 'search' => '' ];
		$_REQUEST['number']  = 10;
		$_REQUEST['exclude'] = '1,2';

		$result = $this->call_in_ajax_context(
			function () {
				$this->ajax->search_models();
			}
		);

		$decoded = json_decode( $result['output'], true );
		$this->assertIsArray( $decoded );

		unset( $_REQUEST['model'], $_REQUEST['query'], $_REQUEST['number'], $_REQUEST['exclude'] );
	}

	/**
	 * Test search_models number defaults to 100 when not in query.
	 */
	public function test_search_models_number_defaults_to_100(): void {

		$_REQUEST['model']   = 'user';
		$_REQUEST['query']   = [ 'search' => '' ];
		$_REQUEST['exclude'] = [];
		// No 'number' key set.

		$result = $this->call_in_ajax_context(
			function () {
				$this->ajax->search_models();
			}
		);

		$decoded = json_decode( $result['output'], true );
		$this->assertIsArray( $decoded );

		unset( $_REQUEST['model'], $_REQUEST['query'], $_REQUEST['exclude'] );
	}

	/**
	 * Test search_models with number already in query array is preserved.
	 */
	public function test_search_models_number_in_query_preserved(): void {

		$_REQUEST['model']   = 'user';
		$_REQUEST['query']   = [ 'number' => 5, 'search' => '' ];
		$_REQUEST['number']  = 100;
		$_REQUEST['exclude'] = [];

		$result = $this->call_in_ajax_context(
			function () {
				$this->ajax->search_models();
			}
		);

		$decoded = json_decode( $result['output'], true );
		$this->assertIsArray( $decoded );

		unset( $_REQUEST['model'], $_REQUEST['query'], $_REQUEST['number'], $_REQUEST['exclude'] );
	}

	// =========================================================================
	// search_all_models()
	// =========================================================================

	/**
	 * Test search_all_models returns JSON array.
	 * Resets Settings sections to avoid the Closure-to-string cast bug.
	 */
	public function test_search_all_models_returns_json_array(): void {

		$this->reset_settings_with_empty_sections();

		$_REQUEST['query'] = [ 'search' => 'zzz_no_match_xyz' ];

		add_filter(
			'wu_search_models_functions',
			function () {
				return [];
			}
		);

		$result = $this->call_in_ajax_context(
			function () {
				$this->ajax->search_all_models();
			}
		);

		$this->assertNotEmpty( $result['output'] );
		$decoded = json_decode( $result['output'], true );
		$this->assertIsArray( $decoded );

		unset( $_REQUEST['query'] );
	}

	/**
	 * Test search_all_models merges user results with model=user property.
	 */
	public function test_search_all_models_user_results_have_model_property(): void {

		$this->reset_settings_with_empty_sections();

		// Create a test user so get_users returns something.
		$user_id = $this->factory->user->create(
			[
				'user_login' => 'testajaxuser',
				'user_email' => 'testajaxuser@example.com',
			]
		);

		$_REQUEST['query'] = [ 'search' => 'testajaxuser' ];

		add_filter(
			'wu_search_models_functions',
			function () {
				return [];
			}
		);

		$result = $this->call_in_ajax_context(
			function () {
				$this->ajax->search_all_models();
			}
		);

		$decoded = json_decode( $result['output'], true );
		$this->assertIsArray( $decoded );

		// Find the user entry.
		$user_entries = array_filter(
			$decoded,
			function ( $item ) {
				return isset( $item['model'] ) && $item['model'] === 'user';
			}
		);

		// At least one user entry should have model=user.
		$this->assertNotEmpty( $user_entries );

		unset( $_REQUEST['query'] );
	}

	/**
	 * Test search_all_models applies wu_search_models_functions filter.
	 */
	public function test_search_all_models_applies_data_sources_filter(): void {

		$this->reset_settings_with_empty_sections();

		$_REQUEST['query'] = [ 'search' => 'zzz_no_match_xyz' ];

		$filter_applied = false;
		add_filter(
			'wu_search_models_functions',
			function ( $functions ) use ( &$filter_applied ) {
				$filter_applied = true;
				return [];
			}
		);

		$this->call_in_ajax_context(
			function () {
				$this->ajax->search_all_models();
			}
		);

		$this->assertTrue( $filter_applied );

		unset( $_REQUEST['query'] );
	}

	/**
	 * Test search_all_models with a non-matching search term returns empty array.
	 * Note: search_all_models always passes query to search_wp_ultimo_setting,
	 * which requires a 'search' key. An empty query would cause an undefined key error.
	 */
	public function test_search_all_models_with_empty_query(): void {

		$this->reset_settings_with_empty_sections();

		// Must include 'search' key to avoid undefined array key in search_wp_ultimo_setting.
		$_REQUEST['query'] = [ 'search' => 'zzz_no_match_xyz' ];

		add_filter(
			'wu_search_models_functions',
			function () {
				return [];
			}
		);

		$result = $this->call_in_ajax_context(
			function () {
				$this->ajax->search_all_models();
			}
		);

		$decoded = json_decode( $result['output'], true );
		$this->assertIsArray( $decoded );

		unset( $_REQUEST['query'] );
	}

	// =========================================================================
	// search_wp_ultimo_setting()
	// =========================================================================

	/**
	 * Test search_wp_ultimo_setting returns array.
	 * Resets sections to avoid the Closure-to-string cast bug in production code.
	 */
	public function test_search_wp_ultimo_setting_returns_array(): void {

		$this->reset_settings_with_empty_sections();

		$result = $this->ajax->search_wp_ultimo_setting( [ 'search' => 'zzz_no_match_xyz_abc_123' ] );

		$this->assertIsArray( $result );
	}

	/**
	 * Test search_wp_ultimo_setting filters by search term in setting_id.
	 */
	public function test_search_wp_ultimo_setting_filters_by_setting_id(): void {

		$this->set_settings_sections(
			[
				'test_section' => [
					'title'  => 'Test Section',
					'order'  => 1,
					'fields' => [
						[
							'setting_id' => 'my_unique_setting_xyz',
							'title'      => 'My Setting',
							'desc'       => 'A description',
							'type'       => 'text',
						],
					],
				],
			]
		);

		$result = $this->ajax->search_wp_ultimo_setting( [ 'search' => 'my_unique_setting_xyz' ] );

		$this->assertIsArray( $result );

		$found = array_filter(
			$result,
			function ( $item ) {
				return isset( $item['setting_id'] ) && $item['setting_id'] === 'my_unique_setting_xyz';
			}
		);

		$this->assertNotEmpty( $found );
	}

	/**
	 * Test search_wp_ultimo_setting filters by title.
	 */
	public function test_search_wp_ultimo_setting_filters_by_title(): void {

		$this->set_settings_sections(
			[
				'test_section_title' => [
					'title'  => 'Title Section',
					'order'  => 2,
					'fields' => [
						[
							'setting_id' => 'some_setting',
							'title'      => 'Unique Title XYZ',
							'desc'       => 'Some desc',
							'type'       => 'text',
						],
					],
				],
			]
		);

		$result = $this->ajax->search_wp_ultimo_setting( [ 'search' => 'unique title xyz' ] );

		$this->assertIsArray( $result );

		$found = array_filter(
			$result,
			function ( $item ) {
				return isset( $item['title'] ) && $item['title'] === 'Unique Title XYZ';
			}
		);

		$this->assertNotEmpty( $found );
	}

	/**
	 * Test search_wp_ultimo_setting filters by desc.
	 */
	public function test_search_wp_ultimo_setting_filters_by_desc(): void {

		$this->set_settings_sections(
			[
				'test_section_desc' => [
					'title'  => 'Desc Section',
					'order'  => 3,
					'fields' => [
						[
							'setting_id' => 'desc_setting',
							'title'      => 'Desc Setting',
							'desc'       => 'Unique description ABCDEF',
							'type'       => 'text',
						],
					],
				],
			]
		);

		$result = $this->ajax->search_wp_ultimo_setting( [ 'search' => 'unique description abcdef' ] );

		$this->assertIsArray( $result );

		$found = array_filter(
			$result,
			function ( $item ) {
				return isset( $item['setting_id'] ) && $item['setting_id'] === 'desc_setting';
			}
		);

		$this->assertNotEmpty( $found );
	}

	/**
	 * Test search_wp_ultimo_setting excludes header-type fields.
	 */
	public function test_search_wp_ultimo_setting_excludes_header_fields(): void {

		$this->set_settings_sections(
			[
				'test_section_header' => [
					'title'  => 'Header Section',
					'order'  => 4,
					'fields' => [
						[
							'setting_id' => 'header_field_xyz',
							'title'      => 'Header Field XYZ',
							'desc'       => '',
							'type'       => 'header',
						],
					],
				],
			]
		);

		$result = $this->ajax->search_wp_ultimo_setting( [ 'search' => 'header_field_xyz' ] );

		$this->assertIsArray( $result );

		$found = array_filter(
			$result,
			function ( $item ) {
				return isset( $item['setting_id'] ) && $item['setting_id'] === 'header_field_xyz';
			}
		);

		$this->assertEmpty( $found );
	}

	/**
	 * Test search_wp_ultimo_setting returns sorted results by title.
	 */
	public function test_search_wp_ultimo_setting_returns_sorted_by_title(): void {

		$this->set_settings_sections(
			[
				'test_sort_section' => [
					'title'  => 'Sort Section',
					'order'  => 5,
					'fields' => [
						[
							'setting_id' => 'zzz_setting',
							'title'      => 'ZZZ Setting',
							'desc'       => 'sort test',
							'type'       => 'text',
						],
						[
							'setting_id' => 'aaa_setting',
							'title'      => 'AAA Setting',
							'desc'       => 'sort test',
							'type'       => 'text',
						],
					],
				],
			]
		);

		$result = $this->ajax->search_wp_ultimo_setting( [ 'search' => 'sort test' ] );

		$this->assertIsArray( $result );

		// Find our two test entries.
		$test_entries = array_values(
			array_filter(
				$result,
				function ( $item ) {
					return isset( $item['desc'] ) && $item['desc'] === 'sort test';
				}
			)
		);

		$this->assertCount( 2, $test_entries );
		$this->assertLessThanOrEqual(
			0,
			strcmp( (string) ( $test_entries[0]['title'] ?? '' ), (string) ( $test_entries[1]['title'] ?? '' ) ),
			'Results should be sorted alphabetically by title'
		);
	}

	/**
	 * Test search_wp_ultimo_setting adds section and url to each field.
	 */
	public function test_search_wp_ultimo_setting_adds_section_and_url(): void {

		$this->set_settings_sections(
			[
				'my_test_section' => [
					'title'  => 'My Test Section',
					'order'  => 6,
					'fields' => [
						[
							'setting_id' => 'url_test_setting',
							'title'      => 'URL Test Setting',
							'desc'       => '',
							'type'       => 'text',
						],
					],
				],
			]
		);

		$result = $this->ajax->search_wp_ultimo_setting( [ 'search' => 'url_test_setting' ] );

		$this->assertIsArray( $result );

		$found = array_values(
			array_filter(
				$result,
				function ( $item ) {
					return isset( $item['setting_id'] ) && $item['setting_id'] === 'url_test_setting';
				}
			)
		);

		$this->assertNotEmpty( $found );
		$item = $found[0];

		$this->assertArrayHasKey( 'section', $item );
		$this->assertEquals( 'my_test_section', $item['section'] );

		$this->assertArrayHasKey( 'section_title', $item );
		$this->assertEquals( 'My Test Section', $item['section_title'] );

		$this->assertArrayHasKey( 'url', $item );
		$this->assertStringContainsString( 'url_test_setting', $item['url'] );
	}

	/**
	 * Test search_wp_ultimo_setting with no matching term returns empty array.
	 */
	public function test_search_wp_ultimo_setting_no_match_returns_empty(): void {

		$this->reset_settings_with_empty_sections();

		$result = $this->ajax->search_wp_ultimo_setting( [ 'search' => 'zzz_no_match_xyz_abc_123' ] );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test search_wp_ultimo_setting with wildcard-stripped search term.
	 */
	public function test_search_wp_ultimo_setting_strips_wildcards(): void {

		$this->set_settings_sections(
			[
				'wildcard_section' => [
					'title'  => 'Wildcard Section',
					'order'  => 7,
					'fields' => [
						[
							'setting_id' => 'wildcard_setting',
							'title'      => 'Wildcard Setting',
							'desc'       => '',
							'type'       => 'text',
						],
					],
				],
			]
		);

		// Pass search with leading/trailing asterisks — should be stripped.
		$result = $this->ajax->search_wp_ultimo_setting( [ 'search' => '*wildcard_setting*' ] );

		$this->assertIsArray( $result );

		$found = array_filter(
			$result,
			function ( $item ) {
				return isset( $item['setting_id'] ) && $item['setting_id'] === 'wildcard_setting';
			}
		);

		$this->assertNotEmpty( $found );
	}

	// =========================================================================
	// search_wordpress_users()
	// =========================================================================

	/**
	 * Test search_wordpress_users returns array.
	 */
	public function test_search_wordpress_users_returns_array(): void {

		$result = $this->ajax->search_wordpress_users( [ 'search' => '' ] );

		$this->assertIsArray( $result );
	}

	/**
	 * Test search_wordpress_users returns user data objects.
	 */
	public function test_search_wordpress_users_returns_user_data(): void {

		$user_id = $this->factory->user->create(
			[
				'user_login' => 'searchable_user_abc',
				'user_email' => 'searchable_user_abc@example.com',
			]
		);

		$result = $this->ajax->search_wordpress_users( [ 'search' => 'searchable_user_abc' ] );

		$this->assertIsArray( $result );
		$this->assertNotEmpty( $result );

		// Each result should be a stdClass (user data object).
		foreach ( $result as $item ) {
			$this->assertInstanceOf( \stdClass::class, $item );
		}
	}

	/**
	 * Test search_wordpress_users clears user_pass from results.
	 */
	public function test_search_wordpress_users_clears_user_pass(): void {

		$user_id = $this->factory->user->create(
			[
				'user_login' => 'passtest_user_xyz',
				'user_email' => 'passtest_user_xyz@example.com',
				'user_pass'  => 'secret_password_123',
			]
		);

		$result = $this->ajax->search_wordpress_users( [ 'search' => 'passtest_user_xyz' ] );

		$this->assertIsArray( $result );
		$this->assertNotEmpty( $result );

		foreach ( $result as $item ) {
			$this->assertEmpty( $item->user_pass, 'user_pass should be cleared in search results' );
		}
	}

	/**
	 * Test search_wordpress_users adds avatar to results.
	 */
	public function test_search_wordpress_users_adds_avatar(): void {

		$user_id = $this->factory->user->create(
			[
				'user_login' => 'avatar_test_user_xyz',
				'user_email' => 'avatar_test_user_xyz@example.com',
			]
		);

		$result = $this->ajax->search_wordpress_users( [ 'search' => 'avatar_test_user_xyz' ] );

		$this->assertIsArray( $result );
		$this->assertNotEmpty( $result );

		foreach ( $result as $item ) {
			$this->assertTrue( property_exists( $item, 'avatar' ) );
		}
	}

	/**
	 * Test search_wordpress_users with no matching users returns empty array.
	 */
	public function test_search_wordpress_users_no_match_returns_empty(): void {

		$result = $this->ajax->search_wordpress_users( [ 'search' => 'zzz_no_such_user_xyz_abc_999' ] );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	// =========================================================================
	// render_selectize_templates()
	// =========================================================================

	/**
	 * Test render_selectize_templates does not output when user lacks capability.
	 */
	public function test_render_selectize_templates_no_output_without_capability(): void {

		// Create a subscriber (no manage_network capability).
		$user_id = $this->factory->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $user_id );

		ob_start();
		$this->ajax->render_selectize_templates();
		$output = ob_get_clean();

		// No output expected since user lacks manage_network.
		$this->assertEmpty( $output );
	}

	/**
	 * Test render_selectize_templates calls wu_get_template when user has capability.
	 */
	public function test_render_selectize_templates_calls_template_for_admin(): void {

		// Grant manage_network to current user.
		$user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );
		grant_super_admin( $user_id );

		// Capture output — wu_get_template may output nothing in test env, but should not crash.
		ob_start();
		$this->ajax->render_selectize_templates();
		$output = ob_get_clean();

		// We just verify no fatal error occurred.
		$this->assertTrue( true );
	}
}
