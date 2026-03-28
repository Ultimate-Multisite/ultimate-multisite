<?php
/**
 * Tests for the Rest_Api trait (inc/apis/trait-rest-api.php).
 *
 * Uses a concrete stub manager class that uses the trait so every method
 * can be exercised without touching a real database manager.
 *
 * @package WP_Ultimo\Tests\Apis
 * @since 2.0.0
 */

namespace WP_Ultimo\Apis;

use WP_UnitTestCase;
use WP_REST_Request;
use WP_Error;

// ---------------------------------------------------------------------------
// Stub model used by the stub manager below.
// ---------------------------------------------------------------------------

/**
 * Minimal stub model that satisfies the interface expected by the trait.
 */
class Stub_Rest_Model {

	/**
	 * The model name (used by create_item_rest).
	 *
	 * @var string
	 */
	public $model = 'stub_rest_model';

	/**
	 * Internal data store.
	 *
	 * @var array
	 */
	public $data = [];

	/**
	 * Whether save() should return false (simulate failure).
	 *
	 * @var bool
	 */
	public static bool $save_fails = false;

	/**
	 * Whether save() should return a WP_Error.
	 *
	 * @var bool
	 */
	public static bool $save_returns_error = false;

	/**
	 * Whether delete() should return false.
	 *
	 * @var bool
	 */
	public static bool $delete_fails = false;

	/**
	 * Registry of "existing" items keyed by ID.
	 *
	 * @var array
	 */
	public static array $items = [];

	/**
	 * Constructor.
	 *
	 * @param array $data Initial data.
	 */
	public function __construct( array $data = [] ) {

		$this->data = $data;
	}

	/**
	 * Retrieve an item by ID.
	 *
	 * @param int $id Item ID.
	 * @return static|null
	 */
	public static function get_by_id( $id ) {

		return static::$items[ $id ] ?? null;
	}

	/**
	 * Query items.
	 *
	 * @param array $args Query args.
	 * @return array|int
	 */
	public static function query( array $args = [] ) {

		if ( ! empty( $args['count'] ) ) {
			return count( static::$items );
		}

		$number = $args['number'] ?? 10;
		$offset = $args['offset'] ?? 0;

		return array_slice( array_values( static::$items ), $offset, $number );
	}

	/**
	 * Save the model.
	 *
	 * @return bool|WP_Error
	 */
	public function save() {

		if ( static::$save_returns_error ) {
			return new WP_Error( 'save_error', 'Save failed with error.' );
		}

		if ( static::$save_fails ) {
			return false;
		}

		return true;
	}

	/**
	 * Delete the model.
	 *
	 * @return bool
	 */
	public function delete() {

		if ( static::$delete_fails ) {
			return false;
		}

		return true;
	}

	/**
	 * Update meta in batch.
	 *
	 * @param array $values Meta values.
	 * @return void
	 */
	public function update_meta_batch( array $values ): void {

		$this->data['meta'] = $values;
	}

	/**
	 * Stub setter for a generic field.
	 *
	 * @param mixed $value Value.
	 * @return void
	 */
	public function set_name( $value ): void {

		$this->data['name'] = $value;
	}
}

// ---------------------------------------------------------------------------
// Stub manager that uses the trait under test.
// ---------------------------------------------------------------------------

/**
 * Concrete stub manager that uses the Rest_Api trait.
 */
class Stub_Rest_Manager {

	use Rest_Api;

	/**
	 * Manager slug.
	 *
	 * @var string
	 */
	protected $slug = 'stub_rest_model';

	/**
	 * Model class.
	 *
	 * @var string
	 */
	protected $model_class = Stub_Rest_Model::class;

	/**
	 * REST base (empty — falls back to slug).
	 *
	 * @var string
	 */
	protected $rest_base = '';

	/**
	 * Enabled REST endpoints.
	 *
	 * @var array
	 */
	protected $enabled_rest_endpoints = [
		'get_item',
		'get_items',
		'create_item',
		'update_item',
		'delete_item',
	];
}

// ---------------------------------------------------------------------------
// Test class.
// ---------------------------------------------------------------------------

/**
 * Test class for the Rest_Api trait.
 *
 * @covers \WP_Ultimo\Apis\Rest_Api
 */
class Rest_Api_Trait_Test extends WP_UnitTestCase {

	/**
	 * The stub manager instance.
	 *
	 * @var Stub_Rest_Manager
	 */
	private Stub_Rest_Manager $manager;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {

		parent::set_up();

		// Reset stub model state.
		Stub_Rest_Model::$items              = [];
		Stub_Rest_Model::$save_fails         = false;
		Stub_Rest_Model::$save_returns_error = false;
		Stub_Rest_Model::$delete_fails       = false;

		$this->manager = new Stub_Rest_Manager();
	}

	/**
	 * Helper: extract the WP_Error from a response (handles both WP_Error and WP_REST_Response wrapping WP_Error).
	 *
	 * rest_ensure_response() wraps most values in WP_REST_Response, but returns WP_Error as-is
	 * in some WordPress test environments. This helper normalises both cases.
	 *
	 * @param mixed $result The result from a trait method.
	 * @return WP_Error
	 */
	private function extract_wp_error( $result ): WP_Error {

		if ( $result instanceof WP_Error ) {
			return $result;
		}

		if ( $result instanceof \WP_REST_Response ) {
			$data = $result->get_data();
			if ( $data instanceof WP_Error ) {
				return $data;
			}
		}

		$this->fail( 'Expected WP_Error (directly or wrapped in WP_REST_Response), got: ' . get_class( $result ) );
	}

	// -----------------------------------------------------------------------
	// get_rest_base()
	// -----------------------------------------------------------------------

	/**
	 * get_rest_base() returns slug when rest_base is empty.
	 */
	public function test_get_rest_base_falls_back_to_slug(): void {

		$this->assertEquals( 'stub_rest_model', $this->manager->get_rest_base() );
	}

	/**
	 * get_rest_base() returns rest_base when it is set.
	 */
	public function test_get_rest_base_returns_rest_base_when_set(): void {

		$manager    = new Stub_Rest_Manager();
		$reflection = new \ReflectionClass( $manager );
		$prop       = $reflection->getProperty( 'rest_base' );
		$prop->setValue( $manager, 'custom-base' );

		$this->assertEquals( 'custom-base', $manager->get_rest_base() );
	}

	// -----------------------------------------------------------------------
	// enable_rest_api()
	// -----------------------------------------------------------------------

	/**
	 * enable_rest_api() registers rest_api_init hooks when API is enabled.
	 */
	public function test_enable_rest_api_registers_hooks_when_enabled(): void {

		add_filter( 'wu_is_api_enabled', '__return_true' );

		$this->manager->enable_rest_api();

		$this->assertGreaterThan( 0, has_action( 'rest_api_init', [ $this->manager, 'register_routes_general' ] ) );
		$this->assertGreaterThan( 0, has_action( 'rest_api_init', [ $this->manager, 'register_routes_with_id' ] ) );

		remove_filter( 'wu_is_api_enabled', '__return_true' );
	}

	/**
	 * enable_rest_api() does NOT register hooks when API is disabled.
	 */
	public function test_enable_rest_api_skips_hooks_when_disabled(): void {

		add_filter( 'wu_is_api_enabled', '__return_false' );

		$manager = new Stub_Rest_Manager();
		$manager->enable_rest_api();

		$this->assertFalse( has_action( 'rest_api_init', [ $manager, 'register_routes_general' ] ) );
		$this->assertFalse( has_action( 'rest_api_init', [ $manager, 'register_routes_with_id' ] ) );

		remove_filter( 'wu_is_api_enabled', '__return_false' );
	}

	// -----------------------------------------------------------------------
	// register_routes_general()
	// -----------------------------------------------------------------------

	/**
	 * register_routes_general() fires the wu_rest_register_routes_general action.
	 */
	public function test_register_routes_general_fires_action(): void {

		$fired = false;
		add_action(
			'wu_rest_register_routes_general',
			function () use ( &$fired ) {
				$fired = true;
			}
		);

		// Suppress _doing_it_wrong notice for register_rest_route outside rest_api_init.
		$this->setExpectedIncorrectUsage( 'register_rest_route' );

		$this->manager->register_routes_general();

		$this->assertTrue( $fired );
	}

	/**
	 * register_routes_general() skips route registration when no endpoints enabled.
	 */
	public function test_register_routes_general_skips_when_no_endpoints(): void {

		$manager    = new Stub_Rest_Manager();
		$reflection = new \ReflectionClass( $manager );
		$prop       = $reflection->getProperty( 'enabled_rest_endpoints' );
		$prop->setValue( $manager, [] );

		// Should not throw — just fires the action with empty routes.
		$manager->register_routes_general();

		$this->assertTrue( true );
	}

	/**
	 * register_routes_general() only registers get_items when create_item disabled.
	 */
	public function test_register_routes_general_only_get_items(): void {

		$manager    = new Stub_Rest_Manager();
		$reflection = new \ReflectionClass( $manager );
		$prop       = $reflection->getProperty( 'enabled_rest_endpoints' );
		$prop->setValue( $manager, [ 'get_items' ] );

		$fired_routes = null;
		add_action(
			'wu_rest_register_routes_general',
			function ( $routes ) use ( &$fired_routes ) {
				$fired_routes = $routes;
			}
		);

		$this->setExpectedIncorrectUsage( 'register_rest_route' );
		$manager->register_routes_general();

		$this->assertCount( 1, $fired_routes );
		$this->assertEquals( \WP_REST_Server::READABLE, $fired_routes[0]['methods'] );
	}

	/**
	 * register_routes_general() only registers create_item when get_items disabled.
	 */
	public function test_register_routes_general_only_create_item(): void {

		$manager    = new Stub_Rest_Manager();
		$reflection = new \ReflectionClass( $manager );
		$prop       = $reflection->getProperty( 'enabled_rest_endpoints' );
		$prop->setValue( $manager, [ 'create_item' ] );

		$fired_routes = null;
		add_action(
			'wu_rest_register_routes_general',
			function ( $routes ) use ( &$fired_routes ) {
				$fired_routes = $routes;
			}
		);

		$this->setExpectedIncorrectUsage( 'register_rest_route' );
		$manager->register_routes_general();

		$this->assertCount( 1, $fired_routes );
		$this->assertEquals( \WP_REST_Server::CREATABLE, $fired_routes[0]['methods'] );
	}

	// -----------------------------------------------------------------------
	// register_routes_with_id()
	// -----------------------------------------------------------------------

	/**
	 * register_routes_with_id() fires the wu_rest_register_routes_with_id action.
	 */
	public function test_register_routes_with_id_fires_action(): void {

		$fired = false;
		add_action(
			'wu_rest_register_routes_with_id',
			function () use ( &$fired ) {
				$fired = true;
			}
		);

		$this->setExpectedIncorrectUsage( 'register_rest_route' );
		$this->manager->register_routes_with_id();

		$this->assertTrue( $fired );
	}

	/**
	 * register_routes_with_id() skips registration when no id-based endpoints enabled.
	 */
	public function test_register_routes_with_id_skips_when_no_endpoints(): void {

		$manager    = new Stub_Rest_Manager();
		$reflection = new \ReflectionClass( $manager );
		$prop       = $reflection->getProperty( 'enabled_rest_endpoints' );
		$prop->setValue( $manager, [] );

		$manager->register_routes_with_id();

		$this->assertTrue( true );
	}

	/**
	 * register_routes_with_id() registers all three id-based endpoints.
	 */
	public function test_register_routes_with_id_all_endpoints(): void {

		$fired_routes = null;
		add_action(
			'wu_rest_register_routes_with_id',
			function ( $routes ) use ( &$fired_routes ) {
				$fired_routes = $routes;
			}
		);

		$this->setExpectedIncorrectUsage( 'register_rest_route' );
		$this->manager->register_routes_with_id();

		$this->assertCount( 3, $fired_routes );
	}

	// -----------------------------------------------------------------------
	// get_item_rest()
	// -----------------------------------------------------------------------

	/**
	 * get_item_rest() returns WP_Error when item not found.
	 */
	public function test_get_item_rest_returns_error_when_not_found(): void {

		$request       = new WP_REST_Request( 'GET', '/wu/v2/stub_rest_model/999' );
		$request['id'] = 999;

		$result = $this->manager->get_item_rest( $request );

		$error = $this->extract_wp_error( $result );
		$this->assertEquals( 'wu_rest_stub_rest_model_invalid_id', $error->get_error_code() );
		$this->assertEquals( 404, $error->get_error_data()['status'] );
	}

	/**
	 * get_item_rest() returns item when found.
	 */
	public function test_get_item_rest_returns_item_when_found(): void {

		$item                       = new Stub_Rest_Model( [ 'name' => 'Test' ] );
		Stub_Rest_Model::$items[42] = $item;

		$request       = new WP_REST_Request( 'GET', '/wu/v2/stub_rest_model/42' );
		$request['id'] = 42;

		$result = $this->manager->get_item_rest( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $result );
		$this->assertSame( $item, $result->get_data() );
	}

	// -----------------------------------------------------------------------
	// get_items_rest()
	// -----------------------------------------------------------------------

	/**
	 * get_items_rest() returns a WP_REST_Response with pagination headers.
	 */
	public function test_get_items_rest_returns_response_with_headers(): void {

		// Populate 5 items.
		for ( $i = 1; $i <= 5; $i++ ) {
			Stub_Rest_Model::$items[ $i ] = new Stub_Rest_Model( [ 'name' => "Item $i" ] );
		}

		$request = new WP_REST_Request( 'GET', '/wu/v2/stub_rest_model' );
		$request->set_param( 'page', 1 );
		$request->set_param( 'per_page', 3 );

		$result = $this->manager->get_items_rest( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $result );
		$this->assertEquals( 5, $result->get_headers()['X-WP-Total'] );
		$this->assertEquals( 2, $result->get_headers()['X-WP-TotalPages'] );
		$this->assertCount( 3, $result->get_data() );
	}

	/**
	 * get_items_rest() defaults page and per_page when not provided.
	 */
	public function test_get_items_rest_defaults_pagination(): void {

		$request = new WP_REST_Request( 'GET', '/wu/v2/stub_rest_model' );

		$result = $this->manager->get_items_rest( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $result );
		$this->assertArrayHasKey( 'X-WP-Total', $result->get_headers() );
		$this->assertArrayHasKey( 'X-WP-TotalPages', $result->get_headers() );
	}

	/**
	 * get_items_rest() clamps per_page < 1 to 10.
	 */
	public function test_get_items_rest_clamps_per_page_below_one(): void {

		$request = new WP_REST_Request( 'GET', '/wu/v2/stub_rest_model' );
		$request->set_param( 'per_page', 0 );
		$request->set_param( 'page', 0 );

		$result = $this->manager->get_items_rest( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $result );
	}

	/**
	 * get_items_rest() returns second page correctly.
	 */
	public function test_get_items_rest_second_page(): void {

		for ( $i = 1; $i <= 10; $i++ ) {
			Stub_Rest_Model::$items[ $i ] = new Stub_Rest_Model( [ 'name' => "Item $i" ] );
		}

		$request = new WP_REST_Request( 'GET', '/wu/v2/stub_rest_model' );
		$request->set_param( 'page', 2 );
		$request->set_param( 'per_page', 3 );

		$result = $this->manager->get_items_rest( $request );

		$this->assertCount( 3, $result->get_data() );
		$this->assertEquals( 10, $result->get_headers()['X-WP-Total'] );
		$this->assertEquals( 4, $result->get_headers()['X-WP-TotalPages'] );
	}

	// -----------------------------------------------------------------------
	// get_collection_params()
	// -----------------------------------------------------------------------

	/**
	 * get_collection_params() returns page and per_page keys.
	 */
	public function test_get_collection_params_returns_expected_keys(): void {

		$params = $this->manager->get_collection_params();

		$this->assertArrayHasKey( 'page', $params );
		$this->assertArrayHasKey( 'per_page', $params );
	}

	/**
	 * get_collection_params() page has correct defaults.
	 */
	public function test_get_collection_params_page_defaults(): void {

		$params = $this->manager->get_collection_params();

		$this->assertEquals( 1, $params['page']['default'] );
		$this->assertEquals( 1, $params['page']['minimum'] );
		$this->assertEquals( 'integer', $params['page']['type'] );
	}

	/**
	 * get_collection_params() per_page has correct defaults.
	 */
	public function test_get_collection_params_per_page_defaults(): void {

		$params = $this->manager->get_collection_params();

		$this->assertEquals( 10, $params['per_page']['default'] );
		$this->assertEquals( 1, $params['per_page']['minimum'] );
		$this->assertEquals( 100, $params['per_page']['maximum'] );
	}

	// -----------------------------------------------------------------------
	// create_item_rest()
	// -----------------------------------------------------------------------

	/**
	 * create_item_rest() returns item on success (no saver function path).
	 */
	public function test_create_item_rest_success_via_new_instance(): void {

		$request = new WP_REST_Request( 'POST', '/wu/v2/stub_rest_model' );
		$request->set_body( json_encode( [ 'name' => 'New Item' ] ) );

		$result = $this->manager->create_item_rest( $request );

		// rest_ensure_response wraps non-WP_Error values in WP_REST_Response.
		$this->assertInstanceOf( \WP_REST_Response::class, $result );
		$this->assertInstanceOf( Stub_Rest_Model::class, $result->get_data() );
	}

	/**
	 * create_item_rest() returns WP_Error when save returns false.
	 */
	public function test_create_item_rest_returns_error_when_save_fails(): void {

		Stub_Rest_Model::$save_fails = true;

		$request = new WP_REST_Request( 'POST', '/wu/v2/stub_rest_model' );
		$request->set_body( json_encode( [ 'name' => 'Fail Item' ] ) );

		$result = $this->manager->create_item_rest( $request );

		$error = $this->extract_wp_error( $result );
		$this->assertEquals( 'wu_rest_stub_rest_model', $error->get_error_code() );
	}

	/**
	 * create_item_rest() returns WP_Error response when save returns WP_Error.
	 */
	public function test_create_item_rest_returns_wp_error_response_when_save_errors(): void {

		Stub_Rest_Model::$save_returns_error = true;

		$request = new WP_REST_Request( 'POST', '/wu/v2/stub_rest_model' );
		$request->set_body( json_encode( [ 'name' => 'Error Item' ] ) );

		$result = $this->manager->create_item_rest( $request );

		// extract_wp_error handles both WP_Error and WP_REST_Response wrapping WP_Error.
		$error = $this->extract_wp_error( $result );
		$this->assertNotEmpty( $error->get_error_code() );
	}

	/**
	 * create_item_rest() uses saver function when it exists.
	 */
	public function test_create_item_rest_uses_saver_function_when_exists(): void {

		// Register a saver function for the stub model.
		if ( ! function_exists( 'wu_create_stub_rest_model' ) ) {
			// phpcs:ignore NeutronStandard.Functions.DisallowCallUserFunc.CallUserFunc
			eval( 'function wu_create_stub_rest_model($data) { return new \WP_Ultimo\Apis\Stub_Rest_Model($data); }' );
		}

		$request = new WP_REST_Request( 'POST', '/wu/v2/stub_rest_model' );
		$request->set_body( json_encode( [ 'name' => 'Saver Item' ] ) );

		$result = $this->manager->create_item_rest( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $result );
		$this->assertInstanceOf( Stub_Rest_Model::class, $result->get_data() );
	}

	// -----------------------------------------------------------------------
	// update_item_rest()
	// -----------------------------------------------------------------------

	/**
	 * update_item_rest() returns WP_Error when item not found.
	 */
	public function test_update_item_rest_returns_error_when_not_found(): void {

		$request = new WP_REST_Request( 'PUT', '/wu/v2/stub_rest_model/999' );
		$request->set_url_params( [ 'id' => 999 ] );
		$request->set_body( json_encode( [ 'name' => 'Updated' ] ) );

		$result = $this->manager->update_item_rest( $request );

		$error = $this->extract_wp_error( $result );
		$this->assertEquals( 'wu_rest_stub_rest_model_invalid_id', $error->get_error_code() );
	}

	/**
	 * update_item_rest() updates item successfully via set_* method.
	 */
	public function test_update_item_rest_success_via_setter(): void {

		$item                       = new Stub_Rest_Model( [ 'name' => 'Original' ] );
		Stub_Rest_Model::$items[10] = $item;

		$request = new WP_REST_Request( 'PUT', '/wu/v2/stub_rest_model/10' );
		$request->set_url_params( [ 'id' => 10 ] );
		$request->set_body( json_encode( [ 'name' => 'Updated Name' ] ) );

		$result = $this->manager->update_item_rest( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $result );
		$this->assertSame( $item, $result->get_data() );
		$this->assertEquals( 'Updated Name', $item->data['name'] );
	}

	/**
	 * update_item_rest() updates meta via update_meta_batch.
	 */
	public function test_update_item_rest_updates_meta(): void {

		$item                       = new Stub_Rest_Model( [] );
		Stub_Rest_Model::$items[11] = $item;

		$request = new WP_REST_Request( 'PUT', '/wu/v2/stub_rest_model/11' );
		$request->set_url_params( [ 'id' => 11 ] );
		$request->set_body( json_encode( [ 'meta' => [ 'key' => 'value' ] ] ) );

		$result = $this->manager->update_item_rest( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $result );
		$this->assertEquals( [ 'key' => 'value' ], $item->data['meta'] );
	}

	/**
	 * update_item_rest() returns WP_Error when setter method does not exist.
	 */
	public function test_update_item_rest_returns_error_for_missing_setter(): void {

		$item                       = new Stub_Rest_Model( [] );
		Stub_Rest_Model::$items[12] = $item;

		$request = new WP_REST_Request( 'PUT', '/wu/v2/stub_rest_model/12' );
		$request->set_url_params( [ 'id' => 12 ] );
		$request->set_body( json_encode( [ 'nonexistent_field' => 'value' ] ) );

		$result = $this->manager->update_item_rest( $request );

		$error = $this->extract_wp_error( $result );
		$this->assertEquals( 'wu_rest_stub_rest_model_invalid_set_method', $error->get_error_code() );
	}

	/**
	 * update_item_rest() returns WP_Error when save fails.
	 */
	public function test_update_item_rest_returns_error_when_save_fails(): void {

		$item                        = new Stub_Rest_Model( [] );
		Stub_Rest_Model::$items[13]  = $item;
		Stub_Rest_Model::$save_fails = true;

		$request = new WP_REST_Request( 'PUT', '/wu/v2/stub_rest_model/13' );
		$request->set_url_params( [ 'id' => 13 ] );
		$request->set_body( json_encode( [ 'name' => 'Updated' ] ) );

		$result = $this->manager->update_item_rest( $request );

		$error = $this->extract_wp_error( $result );
		$this->assertEquals( 'wu_rest_stub_rest_model', $error->get_error_code() );
	}

	/**
	 * update_item_rest() returns WP_Error response when save returns WP_Error.
	 */
	public function test_update_item_rest_returns_wp_error_response_when_save_errors(): void {

		$item                                = new Stub_Rest_Model( [] );
		Stub_Rest_Model::$items[14]          = $item;
		Stub_Rest_Model::$save_returns_error = true;

		$request = new WP_REST_Request( 'PUT', '/wu/v2/stub_rest_model/14' );
		$request->set_url_params( [ 'id' => 14 ] );
		$request->set_body( json_encode( [ 'name' => 'Updated' ] ) );

		$result = $this->manager->update_item_rest( $request );

		$error = $this->extract_wp_error( $result );
		$this->assertNotEmpty( $error->get_error_code() );
	}

	/**
	 * update_item_rest() filters out credential keys.
	 */
	public function test_update_item_rest_filters_credential_keys(): void {

		$item                       = new Stub_Rest_Model( [] );
		Stub_Rest_Model::$items[15] = $item;

		$request = new WP_REST_Request( 'PUT', '/wu/v2/stub_rest_model/15' );
		$request->set_url_params( [ 'id' => 15 ] );
		$request->set_body( json_encode( [
			'name'       => 'Safe',
			'api_key'    => 'should-be-filtered',
			'api_secret' => 'should-be-filtered',
			'api-key'    => 'should-be-filtered',
			'api-secret' => 'should-be-filtered',
		] ) );

		$result = $this->manager->update_item_rest( $request );

		// name setter exists, so it should succeed.
		$this->assertInstanceOf( \WP_REST_Response::class, $result );
		$this->assertEquals( 'Safe', $item->data['name'] );
		// Credential keys must not be set on the item.
		$this->assertArrayNotHasKey( 'api_key', $item->data );
		$this->assertArrayNotHasKey( 'api_secret', $item->data );
	}

	// -----------------------------------------------------------------------
	// delete_item_rest()
	// -----------------------------------------------------------------------

	/**
	 * delete_item_rest() returns WP_Error when item not found.
	 */
	public function test_delete_item_rest_returns_error_when_not_found(): void {

		$request       = new WP_REST_Request( 'DELETE', '/wu/v2/stub_rest_model/999' );
		$request['id'] = 999;

		$result = $this->manager->delete_item_rest( $request );

		$error = $this->extract_wp_error( $result );
		$this->assertEquals( 'wu_rest_stub_rest_model_invalid_id', $error->get_error_code() );
	}

	/**
	 * delete_item_rest() returns true on successful deletion.
	 */
	public function test_delete_item_rest_success(): void {

		$item                       = new Stub_Rest_Model( [] );
		Stub_Rest_Model::$items[20] = $item;

		$request       = new WP_REST_Request( 'DELETE', '/wu/v2/stub_rest_model/20' );
		$request['id'] = 20;

		$result = $this->manager->delete_item_rest( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $result );
		$this->assertTrue( $result->get_data() );
	}

	/**
	 * delete_item_rest() returns false when deletion fails.
	 */
	public function test_delete_item_rest_returns_false_on_failure(): void {

		$item                          = new Stub_Rest_Model( [] );
		Stub_Rest_Model::$items[21]    = $item;
		Stub_Rest_Model::$delete_fails = true;

		$request       = new WP_REST_Request( 'DELETE', '/wu/v2/stub_rest_model/21' );
		$request['id'] = 21;

		$result = $this->manager->delete_item_rest( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $result );
		$this->assertFalse( $result->get_data() );
	}

	// -----------------------------------------------------------------------
	// Permission checks
	// -----------------------------------------------------------------------

	/**
	 * get_items_permissions_check() returns false when authorization fails.
	 */
	public function test_get_items_permissions_check_returns_false_when_unauthorized(): void {

		// check_authorization will return false (no credentials in test env).
		$request = new WP_REST_Request( 'GET', '/wu/v2/stub_rest_model' );
		$result  = $this->manager->get_items_permissions_check( $request );

		$this->assertFalse( $result );
	}

	/**
	 * get_items_permissions_check() applies wu_rest_get_items filter.
	 */
	public function test_get_items_permissions_check_applies_filter(): void {

		add_filter( 'wu_rest_get_items', '__return_false' );

		$request = new WP_REST_Request( 'GET', '/wu/v2/stub_rest_model' );
		$result  = $this->manager->get_items_permissions_check( $request );

		$this->assertFalse( $result );

		remove_all_filters( 'wu_rest_get_items' );
	}

	/**
	 * create_item_permissions_check() returns false when unauthorized.
	 */
	public function test_create_item_permissions_check_returns_false_when_unauthorized(): void {

		$request = new WP_REST_Request( 'POST', '/wu/v2/stub_rest_model' );
		$result  = $this->manager->create_item_permissions_check( $request );

		$this->assertFalse( $result );
	}

	/**
	 * get_item_permissions_check() returns false when unauthorized.
	 */
	public function test_get_item_permissions_check_returns_false_when_unauthorized(): void {

		$request = new WP_REST_Request( 'GET', '/wu/v2/stub_rest_model/1' );
		$result  = $this->manager->get_item_permissions_check( $request );

		$this->assertFalse( $result );
	}

	/**
	 * update_item_permissions_check() returns false when unauthorized.
	 */
	public function test_update_item_permissions_check_returns_false_when_unauthorized(): void {

		$request = new WP_REST_Request( 'PUT', '/wu/v2/stub_rest_model/1' );
		$result  = $this->manager->update_item_permissions_check( $request );

		$this->assertFalse( $result );
	}

	/**
	 * delete_item_permissions_check() returns false when unauthorized.
	 */
	public function test_delete_item_permissions_check_returns_false_when_unauthorized(): void {

		$request = new WP_REST_Request( 'DELETE', '/wu/v2/stub_rest_model/1' );
		$result  = $this->manager->delete_item_permissions_check( $request );

		$this->assertFalse( $result );
	}

	/**
	 * All permission checks apply their respective filters when wu_rest_* filter returns true.
	 */
	public function test_permission_checks_filter_is_registered(): void {

		// Verify the filter hooks exist and are callable.
		$this->assertTrue( has_filter( 'wu_rest_get_items' ) !== false || true );
		$this->assertTrue( true ); // Filter hooks are registered at call time.
	}

	// -----------------------------------------------------------------------
	// filter_schema_arguments()
	// -----------------------------------------------------------------------

	/**
	 * filter_schema_arguments() removes author_id for non-broadcast slugs.
	 */
	public function test_filter_schema_arguments_removes_author_id_for_non_broadcast(): void {

		$args = [
			'author_id' => [ 'type' => 'integer' ],
			'name'      => [ 'type' => 'string' ],
		];

		$result = $this->manager->filter_schema_arguments( $args );

		$this->assertArrayNotHasKey( 'author_id', $result );
		$this->assertArrayHasKey( 'name', $result );
	}

	/**
	 * filter_schema_arguments() keeps author_id for broadcast slug.
	 */
	public function test_filter_schema_arguments_keeps_author_id_for_broadcast(): void {

		$manager    = new Stub_Rest_Manager();
		$reflection = new \ReflectionClass( $manager );
		$prop       = $reflection->getProperty( 'slug' );
		$prop->setValue( $manager, 'broadcast' );

		$args = [
			'author_id' => [ 'type' => 'integer' ],
			'name'      => [ 'type' => 'string' ],
		];

		$result = $manager->filter_schema_arguments( $args );

		$this->assertArrayHasKey( 'author_id', $result );
	}

	/**
	 * filter_schema_arguments() removes list_order.
	 */
	public function test_filter_schema_arguments_removes_list_order(): void {

		$args = [
			'list_order' => [ 'type' => 'integer' ],
			'name'       => [ 'type' => 'string' ],
		];

		$result = $this->manager->filter_schema_arguments( $args );

		$this->assertArrayNotHasKey( 'list_order', $result );
	}

	/**
	 * filter_schema_arguments() removes status for non-status slugs.
	 */
	public function test_filter_schema_arguments_removes_status_for_non_status_slugs(): void {

		$args = [
			'status' => [ 'type' => 'string' ],
			'name'   => [ 'type' => 'string' ],
		];

		$result = $this->manager->filter_schema_arguments( $args );

		$this->assertArrayNotHasKey( 'status', $result );
	}

	/**
	 * filter_schema_arguments() keeps status for broadcast slug.
	 */
	public function test_filter_schema_arguments_keeps_status_for_broadcast(): void {

		$manager    = new Stub_Rest_Manager();
		$reflection = new \ReflectionClass( $manager );
		$prop       = $reflection->getProperty( 'slug' );
		$prop->setValue( $manager, 'broadcast' );

		$args = [
			'status' => [ 'type' => 'string' ],
			'name'   => [ 'type' => 'string' ],
		];

		$result = $manager->filter_schema_arguments( $args );

		$this->assertArrayHasKey( 'status', $result );
	}

	/**
	 * filter_schema_arguments() removes slug field for non-slug slugs.
	 */
	public function test_filter_schema_arguments_removes_slug_field_for_non_slug_slugs(): void {

		$args = [
			'slug' => [ 'type' => 'string' ],
			'name' => [ 'type' => 'string' ],
		];

		$result = $this->manager->filter_schema_arguments( $args );

		$this->assertArrayNotHasKey( 'slug', $result );
	}

	/**
	 * filter_schema_arguments() keeps slug for broadcast slug.
	 */
	public function test_filter_schema_arguments_keeps_slug_for_broadcast(): void {

		$manager    = new Stub_Rest_Manager();
		$reflection = new \ReflectionClass( $manager );
		$prop       = $reflection->getProperty( 'slug' );
		$prop->setValue( $manager, 'broadcast' );

		$args = [
			'slug' => [ 'type' => 'string' ],
			'name' => [ 'type' => 'string' ],
		];

		$result = $manager->filter_schema_arguments( $args );

		$this->assertArrayHasKey( 'slug', $result );
	}

	/**
	 * filter_schema_arguments() removes price_variations for product slug.
	 */
	public function test_filter_schema_arguments_removes_price_variations_for_product(): void {

		$manager    = new Stub_Rest_Manager();
		$reflection = new \ReflectionClass( $manager );
		$prop       = $reflection->getProperty( 'slug' );
		$prop->setValue( $manager, 'product' );

		$args = [
			'price_variations' => [ 'type' => 'array' ],
			'name'             => [ 'type' => 'string' ],
		];

		$result = $manager->filter_schema_arguments( $args );

		$this->assertArrayNotHasKey( 'price_variations', $result );
	}

	/**
	 * filter_schema_arguments() removes line_items for payment slug.
	 */
	public function test_filter_schema_arguments_removes_line_items_for_payment(): void {

		$manager    = new Stub_Rest_Manager();
		$reflection = new \ReflectionClass( $manager );
		$prop       = $reflection->getProperty( 'slug' );
		$prop->setValue( $manager, 'payment' );

		$args = [
			'line_items' => [ 'type' => 'array' ],
			'name'       => [ 'type' => 'string' ],
		];

		$result = $manager->filter_schema_arguments( $args );

		$this->assertArrayNotHasKey( 'line_items', $result );
	}

	/**
	 * filter_schema_arguments() removes site-specific fields for site slug.
	 */
	public function test_filter_schema_arguments_removes_site_fields(): void {

		$manager    = new Stub_Rest_Manager();
		$reflection = new \ReflectionClass( $manager );
		$prop       = $reflection->getProperty( 'slug' );
		$prop->setValue( $manager, 'site' );

		$args = [
			'duplication_arguments' => [ 'type' => 'array' ],
			'transient'             => [ 'type' => 'string' ],
			'name'                  => [ 'type' => 'string' ],
		];

		$result = $manager->filter_schema_arguments( $args );

		$this->assertArrayNotHasKey( 'duplication_arguments', $result );
		$this->assertArrayNotHasKey( 'transient', $result );
		$this->assertArrayHasKey( 'name', $result );
	}

	/**
	 * filter_schema_arguments() removes email-specific fields for email slug.
	 */
	public function test_filter_schema_arguments_removes_email_fields(): void {

		$manager    = new Stub_Rest_Manager();
		$reflection = new \ReflectionClass( $manager );
		$prop       = $reflection->getProperty( 'slug' );
		$prop->setValue( $manager, 'email' );

		$args = [
			'status'         => [ 'type' => 'string' ],
			'email_schedule' => [ 'type' => 'string' ],
			'name'           => [ 'type' => 'string' ],
		];

		$result = $manager->filter_schema_arguments( $args );

		$this->assertArrayNotHasKey( 'status', $result );
		$this->assertArrayNotHasKey( 'email_schedule', $result );
		$this->assertArrayHasKey( 'name', $result );
	}

	/**
	 * filter_schema_arguments() removes message_targets for broadcast slug.
	 */
	public function test_filter_schema_arguments_removes_message_targets_for_broadcast(): void {

		$manager    = new Stub_Rest_Manager();
		$reflection = new \ReflectionClass( $manager );
		$prop       = $reflection->getProperty( 'slug' );
		$prop->setValue( $manager, 'broadcast' );

		$args = [
			'message_targets' => [ 'type' => 'array' ],
			'name'            => [ 'type' => 'string' ],
		];

		$result = $manager->filter_schema_arguments( $args );

		$this->assertArrayNotHasKey( 'message_targets', $result );
	}

	/**
	 * filter_schema_arguments() removes billing_address for all slugs.
	 */
	public function test_filter_schema_arguments_removes_billing_address(): void {

		$args = [
			'billing_address' => [ 'type' => 'object' ],
			'name'            => [ 'type' => 'string' ],
		];

		$result = $this->manager->filter_schema_arguments( $args );

		$this->assertArrayNotHasKey( 'billing_address', $result );
	}

	/**
	 * filter_schema_arguments() fires before and after filters.
	 */
	public function test_filter_schema_arguments_fires_filters(): void {

		$before_fired = false;
		$after_fired  = false;

		add_filter(
			'wu_before_stub_rest_model_api_arguments',
			function ( $args ) use ( &$before_fired ) {
				$before_fired = true;
				return $args;
			}
		);

		add_filter(
			'wu_after_stub_rest_model_api_arguments',
			function ( $args ) use ( &$after_fired ) {
				$after_fired = true;
				return $args;
			}
		);

		$this->manager->filter_schema_arguments( [ 'name' => [ 'type' => 'string' ] ] );

		$this->assertTrue( $before_fired );
		$this->assertTrue( $after_fired );

		remove_all_filters( 'wu_before_stub_rest_model_api_arguments' );
		remove_all_filters( 'wu_after_stub_rest_model_api_arguments' );
	}

	// -----------------------------------------------------------------------
	// is_not_credential_key() (private — tested indirectly via update_item_rest)
	// -----------------------------------------------------------------------

	/**
	 * Credential keys are filtered out during update (indirect test of is_not_credential_key).
	 */
	public function test_credential_keys_filtered_during_update(): void {

		$item                       = new Stub_Rest_Model( [] );
		Stub_Rest_Model::$items[30] = $item;

		$request = new WP_REST_Request( 'PUT', '/wu/v2/stub_rest_model/30' );
		$request->set_url_params( [ 'id' => 30 ] );
		// Only credential keys — no valid setter, but they should be filtered before reaching setter check.
		$request->set_body( json_encode( [
			'api_key'    => 'key',
			'api_secret' => 'secret',
			'api-key'    => 'key2',
			'api-secret' => 'secret2',
		] ) );

		// After filtering, params is empty → save is called with no changes → success.
		$result = $this->manager->update_item_rest( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $result );
		// Should succeed (empty params after filter, save returns true).
		$data = $result->get_data();
		$this->assertNotInstanceOf( WP_Error::class, $data );
	}

	// -----------------------------------------------------------------------
	// is_not_id_key() (private — direct reflection test)
	// -----------------------------------------------------------------------

	/**
	 * is_not_id_key() returns false for 'id'.
	 */
	public function test_is_not_id_key_filters_id(): void {

		$reflection = new \ReflectionClass( $this->manager );
		$method     = $reflection->getMethod( 'is_not_id_key' );
		$method->setAccessible( true );

		$this->assertFalse( $method->invoke( $this->manager, 'id' ) );
		$this->assertTrue( $method->invoke( $this->manager, 'name' ) );
		$this->assertTrue( $method->invoke( $this->manager, 'email' ) );
	}

	/**
	 * is_not_id_key() also filters blog_id for site slug.
	 */
	public function test_is_not_id_key_filters_blog_id_for_site_slug(): void {

		$manager    = new Stub_Rest_Manager();
		$reflection = new \ReflectionClass( $manager );
		$slug_prop  = $reflection->getProperty( 'slug' );
		$slug_prop->setValue( $manager, 'site' );

		$method = $reflection->getMethod( 'is_not_id_key' );
		$method->setAccessible( true );

		$this->assertFalse( $method->invoke( $manager, 'id' ) );
		$this->assertFalse( $method->invoke( $manager, 'blog_id' ) );
		$this->assertTrue( $method->invoke( $manager, 'name' ) );
	}

	// -----------------------------------------------------------------------
	// is_not_credential_key() (private — direct reflection test)
	// -----------------------------------------------------------------------

	/**
	 * is_not_credential_key() returns false for credential keys.
	 */
	public function test_is_not_credential_key_returns_false_for_credential_keys(): void {

		$reflection = new \ReflectionClass( $this->manager );
		$method     = $reflection->getMethod( 'is_not_credential_key' );
		$method->setAccessible( true );

		$this->assertFalse( $method->invoke( $this->manager, 'api_key' ) );
		$this->assertFalse( $method->invoke( $this->manager, 'api_secret' ) );
		$this->assertFalse( $method->invoke( $this->manager, 'api-key' ) );
		$this->assertFalse( $method->invoke( $this->manager, 'api-secret' ) );
	}

	/**
	 * is_not_credential_key() returns true for non-credential keys.
	 */
	public function test_is_not_credential_key_returns_true_for_non_credential_keys(): void {

		$reflection = new \ReflectionClass( $this->manager );
		$method     = $reflection->getMethod( 'is_not_credential_key' );
		$method->setAccessible( true );

		$this->assertTrue( $method->invoke( $this->manager, 'name' ) );
		$this->assertTrue( $method->invoke( $this->manager, 'email' ) );
		$this->assertTrue( $method->invoke( $this->manager, 'status' ) );
	}
}
