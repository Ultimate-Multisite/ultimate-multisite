<?php
/**
 * Tests for the Sender helper class.
 *
 * @package WP_Ultimo\Tests\Helpers
 */

namespace WP_Ultimo\Helpers;

use WP_UnitTestCase;

/**
 * Tests for the Sender helper class.
 *
 * @group sender
 */
class Sender_Test extends WP_UnitTestCase {

	// ------------------------------------------------------------------
	// parse_args
	// ------------------------------------------------------------------

	/**
	 * Test parse_args returns an array.
	 */
	public function test_parse_args_returns_array() {
		$result = Sender::parse_args();
		$this->assertIsArray($result);
	}

	/**
	 * Test parse_args has default keys.
	 */
	public function test_parse_args_has_default_keys() {
		$result = Sender::parse_args();

		$this->assertArrayHasKey('from', $result);
		$this->assertArrayHasKey('content', $result);
		$this->assertArrayHasKey('subject', $result);
		$this->assertArrayHasKey('bcc', $result);
		$this->assertArrayHasKey('payload', $result);
		$this->assertArrayHasKey('attachments', $result);
		$this->assertArrayHasKey('style', $result);
	}

	/**
	 * Test parse_args from has name and email.
	 */
	public function test_parse_args_from_has_name_and_email() {
		$result = Sender::parse_args();

		$this->assertArrayHasKey('name', $result['from']);
		$this->assertArrayHasKey('email', $result['from']);
	}

	/**
	 * Test parse_args merges custom values.
	 */
	public function test_parse_args_merges_custom_values() {
		$result = Sender::parse_args(
			[
				'subject' => 'Custom Subject',
				'content' => 'Custom Content',
			]
		);

		$this->assertEquals('Custom Subject', $result['subject']);
		$this->assertEquals('Custom Content', $result['content']);
	}

	/**
	 * Test parse_args preserves defaults for missing keys.
	 */
	public function test_parse_args_preserves_defaults_for_missing_keys() {
		$result = Sender::parse_args(
			[
				'subject' => 'Only Subject',
			]
		);

		$this->assertEquals('Only Subject', $result['subject']);
		$this->assertEquals('', $result['content']);
		$this->assertIsArray($result['bcc']);
	}

	// ------------------------------------------------------------------
	// process_shortcodes
	// ------------------------------------------------------------------

	/**
	 * Test process_shortcodes replaces placeholders.
	 */
	public function test_process_shortcodes_replaces_placeholders() {
		$content = 'Hello {{name}}, welcome to {{site}}!';
		$payload = [
			'name' => 'John',
			'site' => 'My Site',
		];

		$result = Sender::process_shortcodes($content, $payload);
		$this->assertEquals('Hello John, welcome to My Site!', $result);
	}

	/**
	 * Test process_shortcodes returns original with empty payload.
	 */
	public function test_process_shortcodes_returns_original_with_empty_payload() {
		$content = 'Hello {{name}}!';
		$result  = Sender::process_shortcodes($content, []);

		$this->assertEquals($content, $result);
	}

	/**
	 * Test process_shortcodes handles missing placeholders.
	 */
	public function test_process_shortcodes_handles_missing_placeholders() {
		$content = 'Hello {{name}}, your email is {{email}}';
		$payload = [
			'name' => 'Jane',
		];

		$result = Sender::process_shortcodes($content, $payload);
		$this->assertStringContainsString('Jane', $result);
	}

	/**
	 * Test process_shortcodes handles no placeholders.
	 */
	public function test_process_shortcodes_handles_no_placeholders() {
		$content = 'No placeholders here';
		$payload = ['key' => 'value'];

		$result = Sender::process_shortcodes($content, $payload);
		$this->assertEquals('No placeholders here', $result);
	}

	/**
	 * Test process_shortcodes handles multiple same placeholder.
	 */
	public function test_process_shortcodes_handles_multiple_same_placeholder() {
		$content = '{{name}} is {{name}}';
		$payload = ['name' => 'Bob'];

		$result = Sender::process_shortcodes($content, $payload);
		$this->assertEquals('Bob is Bob', $result);
	}

	/**
	 * Test process_shortcodes handles numeric values.
	 */
	public function test_process_shortcodes_handles_numeric_values() {
		$content = 'Amount: {{amount}}';
		$payload = ['amount' => 42];

		$result = Sender::process_shortcodes($content, $payload);
		$this->assertStringContainsString('42', $result);
	}

	/**
	 * Test process_shortcodes renders conditional blocks for truthy payload values.
	 */
	public function test_process_shortcodes_renders_truthy_conditional_blocks() {
		$content = 'Hello {{#payment_is_paid}}payment received{{/payment_is_paid}}!';

		$result = Sender::process_shortcodes($content, ['payment_is_paid' => true]);

		$this->assertEquals('Hello payment received!', $result);
	}

	/**
	 * Test process_shortcodes removes conditional blocks for false payload values.
	 */
	public function test_process_shortcodes_removes_false_conditional_blocks() {
		$content = 'Hello {{#payment_is_paid}}payment received{{/payment_is_paid}}!';

		$result = Sender::process_shortcodes($content, ['payment_is_paid' => false]);

		$this->assertEquals('Hello !', $result);
	}

	// ------------------------------------------------------------------
	// send_mail — BCC strategy regression coverage (#1195 / GDPR fix +
	// recipient validation fix for the 'undisclosed-recipients:;'
	// regression that triggered fatal mail() calls on hosts where the
	// native mail() function is disabled).
	// ------------------------------------------------------------------

	/**
	 * Capture wp_mail() args via the pre_wp_mail short-circuit filter so the
	 * actual SMTP/mail() path is never reached in tests.
	 *
	 * @param array $captured Reference array populated with wp_mail args.
	 * @return \Closure The filter callback (also returned so it can be removed).
	 */
	private function capture_wp_mail(array &$captured) {
		$callback = function ($short_circuit, $atts) use (&$captured) {
			$captured = $atts;
			return true; // Short-circuit — pretend wp_mail succeeded.
		};

		add_filter( 'pre_wp_mail', $callback, 10, 2 );

		return $callback;
	}

	/**
	 * BCC strategy must NOT set the To: header to the invalid RFC 2822
	 * group-syntax placeholder 'undisclosed-recipients:;' — that value
	 * fails FILTER_VALIDATE_EMAIL and was silently dropped by wp_mail,
	 * which on some SMTP plugins triggered fallback to PHP mail() and
	 * fatal'd on hosts with mail() in disable_functions.
	 */
	public function test_send_mail_bcc_strategy_uses_from_address_not_undisclosed_recipients() {
		$captured = [];
		$callback = $this->capture_wp_mail( $captured );

		$from = [
			'email' => 'sender@example.test',
			'name'  => 'Sender',
		];
		$to   = [
			[
				'email' => 'one@example.test',
				'name'  => 'One',
			],
			[
				'email' => 'two@example.test',
				'name'  => 'Two',
			],
		];

		Sender::send_mail( $from, $to, [
			'subject' => 'Test',
			'content' => 'Body',
		] );

		remove_filter( 'pre_wp_mail', $callback, 10 );

		$this->assertNotEmpty( $captured, 'wp_mail() was not invoked.' );

		// The To: arg must be a real, RFC-valid address — the From address.
		$this->assertIsString( $captured['to'] );
		$this->assertStringNotContainsString( 'undisclosed-recipients', $captured['to'] );
		$this->assertStringContainsString( 'sender@example.test', $captured['to'] );

		// Validate it would pass PHPMailer's address validation.
		$bare_to = $captured['to'];
		if ( preg_match( '/<([^>]+)>/', $bare_to, $m ) ) {
			$bare_to = $m[1];
		}
		$this->assertNotFalse( filter_var( $bare_to, FILTER_VALIDATE_EMAIL ), 'To: header is not a valid email.' );

		// All actual recipients must be in Bcc:.
		$headers = is_array( $captured['headers'] ) ? implode( "\n", $captured['headers'] ) : (string) $captured['headers'];
		$this->assertMatchesRegularExpression( '/^Bcc:.*one@example\.test/mi', $headers );
		$this->assertMatchesRegularExpression( '/^Bcc:.*two@example\.test/mi', $headers );
	}

	/**
	 * When the BCC recipient list filters down to nothing, send_mail must
	 * abort cleanly instead of calling wp_mail() with no valid recipients.
	 */
	public function test_send_mail_returns_false_when_all_bcc_recipients_are_filtered_out() {
		$wp_mail_called = false;
		$callback       = function ($short_circuit, $atts) use (&$wp_mail_called) {
			unset($short_circuit, $atts);
			$wp_mail_called = true;
			return true;
		};
		add_filter( 'pre_wp_mail', $callback, 10, 2 );

		// Two recipients, both produce empty strings after wu_format_email_string.
		$to = [
			[
				'email' => '',
				'name'  => '',
			],
			[
				'email' => '',
				'name'  => '',
			],
		];

		$result = Sender::send_mail(
			[
				'email' => 'sender@example.test',
				'name'  => 'Sender',
			],
			$to,
			[
				'subject' => 'Test',
				'content' => 'Body',
			]
		);

		remove_filter( 'pre_wp_mail', $callback, 10 );

		$this->assertFalse( $result );
		$this->assertFalse( $wp_mail_called, 'wp_mail() must not be called with no valid recipients.' );
	}

	/**
	 * Exceptions thrown by SMTP plugins inside wp_mail (phpmailer_init,
	 * wp_mail filters, etc.) must be caught so an email delivery failure
	 * never aborts the surrounding event/action callback chain.
	 */
	public function test_send_mail_catches_throwable_from_wp_mail() {
		$callback = function ($short_circuit, $atts) {
			unset($short_circuit, $atts);
			throw new \RuntimeException( 'Simulated SMTP plugin failure' );
		};
		add_filter( 'pre_wp_mail', $callback, 10, 2 );

		$result = Sender::send_mail(
			[
				'email' => 'sender@example.test',
				'name'  => 'Sender',
			],
			[
				[
					'email' => 'one@example.test',
					'name'  => 'One',
				],
			],
			[
				'subject' => 'Test',
				'content' => 'Body',
			]
		);

		remove_filter( 'pre_wp_mail', $callback, 10 );

		// Must return false instead of letting the exception bubble up.
		$this->assertFalse( $result );
	}
}
