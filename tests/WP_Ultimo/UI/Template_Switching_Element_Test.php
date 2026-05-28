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
		foreach ( ['template_id'] as $key ) {
			unset( $_REQUEST[ $key ], $_GET[ $key ], $_POST[ $key ] );
		}
	}

	/**
	 * Tear down.
	 */
	public function tear_down(): void {

		foreach ( ['template_id'] as $key ) {
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
			return function ($message) {
				throw new \WPAjaxDieContinueException( esc_html( (string) $message ) );
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
	private function remove_ajax_die_handler(callable $handler): void {

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
	private function decode_json(string $output): array {

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
		$user_id = $this->factory()->user->create( ['role' => 'administrator'] );
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
	 * Authorized callers must pass the is_customer_allowed() gate in the
	 * wu-ajax light-ajax dispatch path — that is, when the Current singleton
	 * has NOT been pre-populated and the customer must be resolved via the
	 * `wu_get_current_customer()` fallback inside Site::is_customer_allowed().
	 *
	 * Regression guard for the "No puedes cambiar la plantilla de este
	 * sitio" customer report: page render passed (Current singleton loaded
	 * by `init`/`wp` actions) but the wu-ajax POST dispatched at
	 * plugins_loaded:20 — before `init` — was returning `not_authorized`.
	 * If this test fails, the fallback chain in Site::is_customer_allowed()
	 * has regressed. We assert specifically that the failure is NOT the
	 * `not_authorized` code; an empty template_id (template_id_required) is
	 * acceptable here because the goal is to prove the auth check itself
	 * passes for a legitimate owner whose Current singleton is unloaded.
	 */
	public function test_switch_template_authorizes_owner_via_wu_ajax_fallback(): void {

		$owner_user_id = $this->factory()->user->create( ['role' => 'subscriber'] );

		$owner_customer = wu_create_customer(
			[
				'user_id'       => $owner_user_id,
				'email_address' => 'wu-ajax-owner-' . uniqid() . '@example.com',
			]
		);

		if ( is_wp_error( $owner_customer ) ) {
			$this->markTestSkipped( 'Customer creation failed: ' . $owner_customer->get_error_message() );
		}

		$site_id = $this->factory()->blog->create();
		$site    = wu_get_site( $site_id );
		$site->set_type( 'customer_owned' );
		$site->set_customer_id( $owner_customer->get_id() );
		$site->save();

		/*
		 * Simulate the wu-ajax pipeline: log the user in but leave the
		 * Current singleton UNLOADED. This mirrors light-ajax dispatch at
		 * plugins_loaded:20, before `init` fires Current::load_currents().
		 * The auth check must succeed via the wu_get_current_customer()
		 * fallback inside Site::is_customer_allowed().
		 */
		wp_set_current_user( $owner_user_id );
		WP_Ultimo()->currents->set_customer( false );

		$loaded_ref = new \ReflectionProperty( WP_Ultimo()->currents, 'loaded' );

		if ( PHP_VERSION_ID < 80100 ) {
			$loaded_ref->setAccessible( true );
		}

		$loaded_ref->setValue( WP_Ultimo()->currents, false );

		$element  = Template_Switching_Element::get_instance();
		$site_ref = new \ReflectionProperty( $element, 'site' );

		if ( PHP_VERSION_ID < 80100 ) {
			$site_ref->setAccessible( true );
		}

		$site_ref->setValue( $element, $site );

		// No template_id: stops the switch BEFORE override_site() runs but
		// only after the is_customer_allowed() gate, which is what we test.
		$result = $this->call_switch_template();

		$this->assertTrue(
			$result['exception'],
			'wp_send_json_error must be reached; the AJAX call must always emit JSON.'
		);

		$decoded = $this->decode_json( $result['output'] );

		$this->assertSame( false, $decoded['success'] );
		$this->assertNotEmpty( $decoded['data'], 'Error payload must include a code/message.' );

		$this->assertNotSame(
			'not_authorized',
			$decoded['data'][0]['code'],
			'Authorized owner must pass the is_customer_allowed() gate in the wu-ajax fallback path. '
				. 'Got not_authorized — Site::is_customer_allowed() did not honour the wu_get_current_customer() fallback.'
		);
	}

	/**
	 * Unauthorized callers must be rejected before template override runs.
	 */
	public function test_switch_template_rejects_unauthorized_caller(): void {

		$owner_user_id = $this->factory()->user->create( ['role' => 'subscriber'] );
		$other_user_id = $this->factory()->user->create( ['role' => 'subscriber'] );

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

		// Capture the diagnostic log entry: when the auth gate trips, the
		// handler must record the resolved IDs so the network admin can
		// triage data-shape problems (missing wu_customer_id blogmeta,
		// detached customer record, wrong-site resolution) without having
		// to instrument the AJAX call themselves.
		$log_messages  = [];
		$log_collector = function ($type, $message) use (&$log_messages) {
			if ( 'template-switching' === $type ) {
				$log_messages[] = $message;
			}
		};
		add_action( 'wu_log_add', $log_collector, 10, 2 );

		$result = $this->call_switch_template();

		remove_action( 'wu_log_add', $log_collector, 10 );

		$this->assertTrue(
			$result['exception'],
			'wp_send_json_error must be reached for unauthorized template switching.'
		);

		$decoded = $this->decode_json( $result['output'] );

		$this->assertSame( false, $decoded['success'] );
		$this->assertSame( 'not_authorized', $decoded['data'][0]['code'] );

		$this->assertNotEmpty(
			$log_messages,
			'Diagnostic log entry must be recorded under the template-switching handle when the auth gate denies a switch.'
		);
		$this->assertStringContainsString(
			'not_authorized',
			$log_messages[0],
			'Log line must include the not_authorized marker.'
		);
		$this->assertStringContainsString(
			'site_customer_id=' . $owner_customer->get_id(),
			$log_messages[0],
			'Log line must include the site_customer_id so the admin can triage data-shape issues.'
		);
	}

	/**
	 * Capture the rendered output of the element with a usable site/customer
	 * context. Used by the layout tests below.
	 *
	 * @return string
	 */
	private function render_element_with_context(): string {

		$user_id  = $this->factory()->user->create( ['role' => 'subscriber'] );
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

	/**
	 * Templates the network admin has marked unavailable (inactive,
	 * archived, deleted, or spam) must not appear in the customer-panel
	 * "Available Templates" grid.
	 *
	 * Regression guard for the customer report that "templates that should
	 * not be available are still listed". `wu_get_site_templates()` only
	 * filters by `wu_type = site_template`, so without the additional
	 * filter in Template_Switching_Element::output() any template whose
	 * availability flags the admin has cleared still ended up rendered.
	 *
	 * The assertion looks for `id="wu-site-template-{ID}"` because the
	 * grid card wrapper uses that exact attribute (see
	 * views/checkout/templates/template-selection/clean.php). A simple
	 * presence check on the title would false-positive on the
	 * current-template card or hidden DOM markers.
	 */
	public function test_render_excludes_unavailable_templates_from_grid(): void {

		// Active + available → must render.
		$active_id = $this->factory()->blog->create();
		$active    = wu_get_site( $active_id );
		$active->set_type( 'site_template' );
		$active->set_active( true );
		$active->set_archived( false );
		$active->set_deleted( false );
		$active->set_spam( false );
		$active->save();

		// Inactive (network admin disabled) → must NOT render.
		$inactive_id = $this->factory()->blog->create();
		$inactive    = wu_get_site( $inactive_id );
		$inactive->set_type( 'site_template' );
		$inactive->set_active( false );
		$inactive->save();

		// Archived → must NOT render.
		$archived_id = $this->factory()->blog->create();
		$archived    = wu_get_site( $archived_id );
		$archived->set_type( 'site_template' );
		$archived->set_active( true );
		$archived->set_archived( true );
		$archived->save();

		// Deleted → must NOT render.
		$deleted_id = $this->factory()->blog->create();
		$deleted    = wu_get_site( $deleted_id );
		$deleted->set_type( 'site_template' );
		$deleted->set_active( true );
		$deleted->set_deleted( true );
		$deleted->save();

		// Spam → must NOT render.
		$spam_id = $this->factory()->blog->create();
		$spam    = wu_get_site( $spam_id );
		$spam->set_type( 'site_template' );
		$spam->set_active( true );
		$spam->set_spam( true );
		$spam->save();

		$html = $this->render_element_with_context();

		/*
		 * The grid is rendered client-side by Vue from the JSON payload
		 * embedded in the `<dynamic :template="...">` tag. Server-side
		 * HTML therefore does not contain `id="wu-site-template-X"` —
		 * we have to inspect the encoded `sites` array in the dynamic
		 * tag's attribute. `wp_json_encode()` then `esc_attr()` produce
		 * `&quot;sites&quot;:[&quot;2&quot;,...]` in the output.
		 */
		if ( ! preg_match( '/get_template\\(\'template-selection\\/clean\',\\s*({.*?})\\)/', $html, $matches ) ) {
			$this->fail( 'Could not find the dynamic-template JSON payload in the rendered output.' );
		}

		$decoded_attrs = html_entity_decode( $matches[1], ENT_QUOTES );
		$attrs         = json_decode( $decoded_attrs, true );

		$this->assertIsArray( $attrs, 'Dynamic-template payload must decode to an array. Got: ' . $decoded_attrs );
		$this->assertArrayHasKey( 'sites', $attrs );

		$sites = array_map( 'intval', $attrs['sites'] );

		$this->assertContains(
			(int) $active_id,
			$sites,
			'Active, non-archived, non-deleted, non-spam template must appear in the grid sites array.'
		);

		$this->assertNotContains(
			(int) $inactive_id,
			$sites,
			'Inactive template must not appear in the grid sites array.'
		);

		$this->assertNotContains(
			(int) $archived_id,
			$sites,
			'Archived template must not appear in the grid sites array.'
		);

		$this->assertNotContains(
			(int) $deleted_id,
			$sites,
			'Deleted template must not appear in the grid sites array.'
		);

		$this->assertNotContains(
			(int) $spam_id,
			$sites,
			'Spam template must not appear in the grid sites array.'
		);
	}
}
