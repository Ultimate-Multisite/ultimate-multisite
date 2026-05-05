<?php
/**
 * Tests for Template_Switching_Element class.
 *
 * Specifically focused on the AJAX failure paths in switch_template(): the
 * handler must always emit a JSON body (success or error) so the front-end
 * JS in assets/js/template-switching.js can call unblock() and clear its
 * loading spinner. Previously, the failure branch returned void, leaving
 * the customer staring at an indefinite spinner.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\UI;

use WP_UnitTestCase;

/**
 * Test class for Template_Switching_Element AJAX flow.
 *
 * @group ajax
 */
class Template_Switching_Element_Test extends WP_UnitTestCase {

	/**
	 * Set up.
	 */
	public function set_up(): void {

		parent::set_up();

		// Reset request globals between tests.
		foreach ( [ 'template_id' ] as $key ) {
			unset( $_REQUEST[ $key ], $_GET[ $key ], $_POST[ $key ] );
		}
	}

	/**
	 * Tear down.
	 */
	public function tear_down(): void {

		foreach ( [ 'template_id' ] as $key ) {
			unset( $_REQUEST[ $key ], $_GET[ $key ], $_POST[ $key ] );
		}

		// Reset element state — clear any site we set during tests.
		$element = Template_Switching_Element::get_instance();
		$ref     = new \ReflectionProperty( $element, 'site' );

		if ( PHP_VERSION_ID < 80100 ) {
			$ref->setAccessible( true );
		}

		$ref->setValue( $element, null );
		WP_Ultimo()->currents->set_customer( false );

		parent::tear_down();
	}

	/**
	 * Install AJAX die handler so wp_send_json_* don't kill PHPUnit.
	 *
	 * @return callable The installed handler.
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
	 * Call switch_template() inside an AJAX context, capturing JSON output.
	 *
	 * @return array{output: string, exception: bool}
	 */
	private function call_switch_template(): array {

		$handler          = $this->install_ajax_die_handler();
		$exception_caught = false;

		ob_start();

		try {
			Template_Switching_Element::get_instance()->switch_template();
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
	 * Decode a JSON response body, asserting it is a non-empty array.
	 *
	 * @param string $output Raw output from the AJAX handler.
	 * @return array
	 */
	private function decode_json( string $output ): array {

		$this->assertNotEmpty(
			$output,
			'AJAX handler emitted an empty body. The front-end JS will hang on its loading spinner without a parsable JSON response.'
		);

		$decoded = json_decode( $output, true );

		$this->assertIsArray(
			$decoded,
			'AJAX handler emitted output that was not valid JSON: ' . $output
		);

		return $decoded;
	}

	/**
	 * Missing site context must yield a JSON error body, not silence.
	 *
	 * Regression guard for the indefinite-spinner bug: a NULL $this->site
	 * with no current-site fallback used to dereference NULL and produce
	 * no body at all.
	 */
	public function test_switch_template_missing_site_emits_json_error(): void {

		$_REQUEST['template_id'] = '0';

		$result = $this->call_switch_template();

		$this->assertTrue(
			$result['exception'],
			'wp_send_json_error must be reached so the AJAX response terminates cleanly.'
		);

		$decoded = $this->decode_json( $result['output'] );

		$this->assertSame( false, $decoded['success'] );
		$this->assertNotEmpty( $decoded['data'], 'Error payload must include a message for the JS to display.' );
	}

	/**
	 * Empty template_id must yield a JSON error body, not silence.
	 */
	public function test_switch_template_missing_template_id_emits_json_error(): void {
		$user_id = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		grant_super_admin( $user_id );
		wp_set_current_user( $user_id );

		// Force a fake site object so the "missing site context" guard is bypassed
		// and we hit the template_id check.
		$site_id = $this->factory()->blog->create();
		$site    = wu_get_site( $site_id );
		$site->set_type( 'customer_owned' );
		$site->save();

		$element = Template_Switching_Element::get_instance();
		$ref     = new \ReflectionProperty( $element, 'site' );

		if ( PHP_VERSION_ID < 80100 ) {
			$ref->setAccessible( true );
		}

		$ref->setValue( $element, $site );

		// No template_id set in $_REQUEST.
		$result = $this->call_switch_template();

		$this->assertTrue(
			$result['exception'],
			'wp_send_json_error must be reached for missing template_id.'
		);

		$decoded = $this->decode_json( $result['output'] );
		$this->assertSame( false, $decoded['success'] );
	}

	/**
	 * Unauthorized callers must be rejected before template override runs.
	 */
	public function test_switch_template_rejects_unauthorized_caller(): void {

		$owner_user_id = $this->factory()->user->create( [ 'role' => 'subscriber' ] );
		$other_user_id = $this->factory()->user->create( [ 'role' => 'subscriber' ] );

		$owner_customer = wu_create_customer(
			[
				'user_id'       => $owner_user_id,
				'email_address' => 'template-owner@example.com',
			]
		);

		$other_customer = wu_create_customer(
			[
				'user_id'       => $other_user_id,
				'email_address' => 'template-other@example.com',
			]
		);

		if ( is_wp_error( $owner_customer ) || is_wp_error( $other_customer ) ) {
			$this->markTestSkipped( 'Customer creation failed.' );
		}

		$site_id = $this->factory()->blog->create();
		$site    = wu_get_site( $site_id );
		$site->set_type( 'customer_owned' );
		$site->set_customer_id( $owner_customer->get_id() );
		$site->save();

		WP_Ultimo()->currents->set_customer( $other_customer );
		wp_set_current_user( $other_user_id );
		$_REQUEST['template_id'] = '1';

		$element = Template_Switching_Element::get_instance();
		$ref     = new \ReflectionProperty( $element, 'site' );

		if ( PHP_VERSION_ID < 80100 ) {
			$ref->setAccessible( true );
		}

		$ref->setValue( $element, $site );

		$result = $this->call_switch_template();

		$this->assertTrue(
			$result['exception'],
			'wp_send_json_error must be reached for unauthorized template switching.'
		);

		$decoded = $this->decode_json( $result['output'] );

		$this->assertSame( false, $decoded['success'] );
		$this->assertSame( 'not_authorized', $decoded['data'][0]['code'] );
	}

}
