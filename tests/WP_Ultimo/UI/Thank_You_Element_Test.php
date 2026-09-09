<?php
/**
 * Tests for the thank-you element's gateway-aware pending messages.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\UI;

use WP_UnitTestCase;

/**
 * Test class for thank-you pending payment messages.
 */
class Thank_You_Element_Test extends WP_UnitTestCase {

	/**
	 * Provides pending payment message scenarios.
	 *
	 * @return array
	 */
	public function pending_message_provider(): array {

		$default_message = 'Thank you for your order! We are waiting on the payment processor to confirm your payment, which can take up to 5 minutes. We will notify you via email when your site is ready.';

		return [
			'pending manual payment'     => [
				'manual',
				'pending',
				$default_message,
				'Thank you for your order! Please follow the payment instructions below. An administrator will confirm your payment and notify you by email when your site is ready.',
			],
			'pending electronic payment' => [
				'stripe',
				'pending',
				$default_message,
				$default_message,
			],
			'completed manual payment'   => [
				'manual',
				'completed',
				$default_message,
				$default_message,
			],
			'custom pending message'     => [
				'manual',
				'pending',
				'Your custom payment confirmation message.',
				'Your custom payment confirmation message.',
			],
		];
	}

	/**
	 * Renders the appropriate pending message for each gateway and payment status.
	 *
	 * @dataProvider pending_message_provider
	 */
	public function test_get_pending_thank_you_message(string $gateway, string $status, string $message, string $expected): void {

		$payment = new class($gateway, $status) {

			/**
			 * The gateway identifier.
			 *
			 * @var string
			 */
			private $gateway;

			/**
			 * The payment status.
			 *
			 * @var string
			 */
			private $status;

			/**
			 * Creates the payment double.
			 *
			 * @param string $gateway The gateway identifier.
			 * @param string $status The payment status.
			 */
			public function __construct(string $gateway, string $status) {

				$this->gateway = $gateway;
				$this->status  = $status;
			}

			/**
			 * Gets the gateway identifier.
			 *
			 * @return string
			 */
			public function get_gateway(): string {

				return $this->gateway;
			}

			/**
			 * Gets the payment status.
			 *
			 * @return string
			 */
			public function get_status(): string {

				return $this->status;
			}
		};

		$message = Thank_You_Element::get_instance()->get_pending_thank_you_message($payment, $message);

		$this->assertSame($expected, $message);
	}
}
