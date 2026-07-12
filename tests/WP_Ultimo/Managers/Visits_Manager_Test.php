<?php
/**
 * Unit tests for Visits_Manager.
 *
 * @package WP_Ultimo\Tests\Managers
 */

namespace WP_Ultimo\Tests\Managers;

use WP_Ultimo\Managers\Visits_Manager;
use WP_Ultimo\Models\Site_Type;
use WP_Ultimo\Objects\Visits;

class Visits_Manager_Test extends \WP_UnitTestCase {

	use Manager_Test_Trait;

	protected function get_manager_class(): string {
		return Visits_Manager::class;
	}

	protected function get_expected_slug(): ?string {
		return null;
	}

	protected function get_expected_model_class(): ?string {
		return null;
	}

	public function test_maybe_lock_site_returns_service_unavailable_status(): void {

		$site = wu_create_site(
			[
				'title'       => 'Visits Limited Site',
				'domain'      => 'visits-limited-' . wp_rand() . '.example.com',
				'template_id' => 1,
				'type'        => Site_Type::CUSTOMER_OWNED,
			]
		);

		$this->assertNotWPError($site);

		$site->update_meta(
			'wu_limitations',
			[
				'visits' => [
					'enabled' => true,
					'limit'   => 1,
				],
			]
		);

		$visits = new Visits($site->get_id());
		$visits->add_visit(2);

		switch_to_blog($site->get_id());

		$die_args = null;
		$handler  = function () use (&$die_args) {
			// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- All arguments are captured for assertions below.
			return function ($message, $title, $args) use (&$die_args) {
				$die_args = compact('message', 'title', 'args');
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Test die handler rethrows the raw wp_die message.
				throw new \WPDieException((string) $message);
			};
		};

		add_filter('wp_die_handler', $handler);

		try {
			Visits_Manager::get_instance()->maybe_lock_site();
			$this->fail('Expected the visits-limited site to terminate through wp_die.');
		} catch (\WPDieException $exception) {
			$this->assertSame('This site is not available at this time.', $exception->getMessage());
		} finally {
			remove_filter('wp_die_handler', $handler);
			restore_current_blog();
		}

		$this->assertSame('Not available', $die_args['title']);
		$this->assertSame(503, $die_args['args']['response']);
	}
}
