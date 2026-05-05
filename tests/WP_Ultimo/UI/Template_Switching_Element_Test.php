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

	/**
	 * Capture the rendered output of the element with a usable site/customer
	 * context. Used by the layout tests below.
	 *
	 * @return string
	 */
	private function render_element_with_context(): string {

		$user_id  = $this->factory()->user->create( [ 'role' => 'subscriber' ] );
		$customer = wu_create_customer(
			[
				'user_id'       => $user_id,
				'email_address' => 'render-' . uniqid() . '@example.com',
			]
		);

		if ( is_wp_error( $customer ) ) {
			$this->markTestSkipped( 'Customer creation failed: ' . $customer->get_error_message() );
		}

		$site_id = $this->factory()->blog->create();
		$site    = wu_get_site( $site_id );
		$site->set_type( 'customer_owned' );
		$site->set_customer_id( $customer->get_id() );
		$site->save();

		WP_Ultimo()->currents->set_customer( $customer );
		wp_set_current_user( $user_id );

		$element = Template_Switching_Element::get_instance();

		// Inject site and a state so output() proceeds.
		$site_ref  = new \ReflectionProperty( $element, 'site' );
		$state_ref = new \ReflectionProperty( $element, 'permission_state' );

		if ( PHP_VERSION_ID < 80100 ) {
			$site_ref->setAccessible( true );
			$state_ref->setAccessible( true );
		}

		$site_ref->setValue( $element, $site );
		$state_ref->setValue( $element, Template_Switching_Element::STATE_NO_MEMBERSHIP );

		ob_start();
		$element->output( $element->defaults() );

		return (string) ob_get_clean();
	}

	/**
	 * The current-template summary card must be present at the top of the
	 * rendered output so the customer can see "what they're on" before
	 * scrolling through the grid.
	 *
	 * Regression guard for the 2.9.4 redesign that introduced the card.
	 */
	public function test_render_includes_current_template_card(): void {

		$html = $this->render_element_with_context();

		$this->assertStringContainsString(
			'wu-template-switching-current',
			$html,
			'Rendered output must include the current-template card wrapper.'
		);

		$this->assertStringContainsString(
			'Available Templates',
			$html,
			'Rendered output must include the "Available Templates" heading that visually separates the card from the grid.'
		);
	}

	/**
	 * The grid wrapper must NOT carry a v-show that hides it when the
	 * customer picks a different template. Hiding the grid mid-selection
	 * was the UX regression this change fixes — the grid stays visible so
	 * the customer can change their mind without scrolling away from a
	 * disappeared list.
	 *
	 * We assert this by checking that the rendered template_element field
	 * does not contain `v-show="template_id == original_template_id"` —
	 * the exact directive that previously hid the grid.
	 */
	public function test_render_grid_is_not_hidden_during_selection(): void {

		$html = $this->render_element_with_context();

		$this->assertStringNotContainsString(
			'v-show="template_id == original_template_id"',
			$html,
			'Grid must not be hidden when template_id != original_template_id; the customer needs to see the grid to change their mind.'
		);
	}

	/**
	 * The standalone "reset_current_template" red-link row must no longer
	 * be emitted — the Reset action lives inside the current-template card
	 * at the top of the page now. A duplicate row at the bottom would be
	 * confusing.
	 */
	public function test_render_does_not_emit_legacy_bottom_reset_link(): void {

		$html = $this->render_element_with_context();

		// Legacy bottom-row container had this exact class chain.
		$this->assertStringNotContainsString(
			'wu-text-red-600 hover:wu-text-red-800',
			$html,
			'The legacy bottom-right "Reset current template" red link must be gone; Reset lives in the top card now.'
		);
	}

}
