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

		/*
		 * Bypass the new is_customer_allowed() authorization gate added to
		 * switch_template(). The freshly-created blog has customer_id 0, so
		 * a normal user would hit the "not_authorized" branch before
		 * reaching the template_id check we want to exercise here. Network
		 * admins short-circuit is_customer_allowed() via the manage_network
		 * capability — that's what we use to land on the template_id path.
		 */
		$super_admin_id = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		grant_super_admin( $super_admin_id );
		wp_set_current_user( $super_admin_id );

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
	 * Unauthorized caller must be rejected with a JSON error body.
	 *
	 * Regression guard for the privilege-escalation hole: the wu-ajax-nonce
	 * shared across all logged-in users meant any non-customer with a valid
	 * nonce could call wu_ajax_wu_switch_template against another customer's
	 * site (or against an orphan customer_owned site with customer_id 0,
	 * which previously satisfied is_customer_allowed() via 0 === 0). The
	 * handler must now refuse to proceed before reaching Site_Duplicator.
	 */
	public function test_switch_template_rejects_unauthorized_caller(): void {

		// A logged-in user who is NOT the site's customer and NOT a network
		// admin. With the security fix in place, is_customer_allowed() must
		// return false for this user.
		$subscriber_id = $this->factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );

		$site_id = $this->factory()->blog->create();
		$site    = wu_get_site( $site_id );
		$site->set_type( 'customer_owned' );
		// customer_id stays at 0 — this is the orphan-site case that used
		// to satisfy is_customer_allowed() via the 0 === 0 comparison.
		$site->save();

		$element = Template_Switching_Element::get_instance();
		$ref     = new \ReflectionProperty( $element, 'site' );

		if ( PHP_VERSION_ID < 80100 ) {
			$ref->setAccessible( true );
		}

		$ref->setValue( $element, $site );

		// Even with a valid template_id, the auth check must fire first.
		$_REQUEST['template_id'] = '1';

		$result = $this->call_switch_template();

		$this->assertTrue(
			$result['exception'],
			'wp_send_json_error must be reached so the unauthorized request terminates with a JSON body.'
		);

		$decoded = $this->decode_json( $result['output'] );
		$this->assertSame( false, $decoded['success'] );

		// The WP_Error code travels in $decoded['data'][0]['code'] when a
		// WP_Error is passed to wp_send_json_error().
		$this->assertNotEmpty(
			$decoded['data'],
			'Unauthorized response must include a payload so the JS can surface the reason.'
		);

		// Be tolerant of payload shape variations across WP versions: search
		// the serialized payload for the not_authorized error code.
		$serialized = wp_json_encode( $decoded );
		$this->assertStringContainsString(
			'not_authorized',
			(string) $serialized,
			'Unauthorized response must carry the not_authorized error code, not a generic failure.'
		);
	}

}
