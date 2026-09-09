<?php
/**
 * Stripe request and webhook API version regression tests.
 *
 * @package WP_Ultimo
 * @subpackage Tests/Gateways
 * @since 2.15.2
 */

namespace WP_Ultimo\Gateways;

defined('ABSPATH') || exit;

class Stripe_API_Version_Test extends \WP_UnitTestCase {

	public static function client_modes(): array {
		return [
			'stripe direct'   => [Stripe_Gateway::class, false],
			'stripe OAuth'    => [Stripe_Gateway::class, true],
			'checkout direct' => [Stripe_Checkout_Gateway::class, false],
			'checkout OAuth'  => [Stripe_Checkout_Gateway::class, true],
		];
	}

	/**
	 * @dataProvider client_modes
	 */
	public function test_client_uses_pinned_version(string $gateway_class, bool $oauth): void {
		$gateway = $this->getMockBuilder($gateway_class)
			->disableOriginalConstructor()
			->onlyMethods(['is_using_oauth'])
			->getMock();
		$gateway->method('is_using_oauth')->willReturn($oauth);

		$reflection = new \ReflectionClass(Base_Stripe_Gateway::class);
		$reflection->getProperty('secret_key')->setValue($gateway, 'sk_test_version_fixture');
		$reflection->getProperty('oauth_account_id')->setValue($gateway, 'acct_version_fixture');
		$client = $reflection->getMethod('get_stripe_client')->invoke($gateway);
		$config = (new \ReflectionClass(\Stripe\BaseStripeClient::class))->getProperty('config')->getValue($client);

		$this->assertSame('2025-08-27.basil', $config['stripe_version']);
		$this->assertSame($oauth ? 'acct_version_fixture' : null, $client->getStripeAccount());
	}

	public static function webhook_paths(): array {
		return [
			'direct new'      => [false, ''],
			'OAuth new'       => [true, ''],
			'direct enabled'  => [false, 'enabled'],
			'OAuth enabled'   => [true, 'enabled'],
			'direct disabled' => [false, 'disabled'],
			'OAuth disabled'  => [true, 'disabled'],
		];
	}

	/**
	 * @dataProvider webhook_paths
	 */
	public function test_webhook_version_is_pinned_only_on_creation(bool $oauth, string $status): void {
		$gateway = $this->getMockBuilder(Stripe_Gateway::class)
			->disableOriginalConstructor()
			->onlyMethods(['setup_api_keys', 'has_webhook_installed', 'get_webhook_listener_url'])
			->getMock();
		$url     = home_url('/?wu-gateway=stripe');
		$gateway->method('get_webhook_listener_url')->willReturn($url);
		$gateway->method('has_webhook_installed')->willReturn(
			$status ? \Stripe\WebhookEndpoint::constructFrom([
				'id'          => 'we_version_fixture',
				'status'      => $status,
				'api_version' => '2024-06-20',
			]) : false
		);

		$endpoints = $this->getMockBuilder(\Stripe\Service\WebhookEndpointService::class)
			->disableOriginalConstructor()
			->getMock();
		$client    = $this->getMockBuilder(\Stripe\StripeClient::class)
			->disableOriginalConstructor()
			->getMock();
		$client->method('__get')->with('webhookEndpoints')->willReturn($endpoints);
		$gateway->set_stripe_client($client);

		if ('' === $status) {
			$endpoints->expects($this->once())->method('create')->with([
				'api_version'    => '2025-08-27.basil',
				'enabled_events' => ['*'],
				'url'            => $url,
				'description'    => 'Added by Ultimate Multisite. Required to correctly handle changes in subscription status.',
			]);
		} else {
			$endpoints->expects($this->never())->method('create');
		}

		if ('disabled' === $status) {
			$endpoints->expects($this->once())->method('update')->with('we_version_fixture', ['status' => 'enabled']);
		} else {
			$endpoints->expects($this->never())->method('update');
		}

		if ($oauth) {
			(new \ReflectionMethod(Base_Stripe_Gateway::class, 'install_webhook_for_oauth'))->invoke($gateway);
		} else {
			$settings = [
				'active_gateways'     => ['stripe'],
				'stripe_sandbox_mode' => '1',
				'stripe_test_pk_key'  => 'pk_test_version_fixture',
				'stripe_test_sk_key'  => 'sk_test_version_fixture',
				'stripe_live_pk_key'  => '',
				'stripe_live_sk_key'  => '',
			];
			$previous = array_merge($settings, ['stripe_test_pk_key' => 'pk_test_previous_fixture']);
			$gateway->install_webhook($settings, $settings, $previous);
		}
	}
}
