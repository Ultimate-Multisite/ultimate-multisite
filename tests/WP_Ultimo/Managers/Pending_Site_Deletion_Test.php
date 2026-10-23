<?php
/**
 * Pending-site deletion regressions.
 *
 * @package WP_Ultimo
 * @subpackage Tests
 * @since 2.5.3
 */

namespace WP_Ultimo\Tests\Managers;

use WP_Ultimo\Managers\Form_Manager;
use WP_Ultimo\Models\Site;

defined('ABSPATH') || exit;

class Pending_Site_Deletion_Test extends \WP_UnitTestCase {

	use \WP_Ultimo\Tests\Ajax_JSON_Test_Trait;

	public function test_pending_listing_uses_metadata_owner_instead_of_serialized_membership_id(): void {
		$site = new Site(
			[
				'title'         => 'Pending deletion regression',
				'membership_id' => 0,
			]
		);
		add_metadata('wu_membership', 999999, 'pending_site', $site);

		$sites = Site::get_all_by_type('pending');
		$found = array_values(array_filter($sites, fn($item) => 'Pending deletion regression' === $item->get_title()));

		$this->assertCount(1, $found);
		$this->assertSame(999999, (int) $found[0]->get_membership_id());
	}

	public function test_orphaned_pending_site_delete_modal_renders(): void {
		add_metadata('wu_membership', 999999, 'pending_site', new Site(['membership_id' => 999999]));
		$_REQUEST['model'] = 'membership_meta_pending_site';
		$_REQUEST['id']    = '999999';

		// Assert before the unavailable-form renderer's bare die can stop PHPUnit.
		$check_object = function ($membership) {
			$this->assertNotFalse($membership, 'Orphaned pending sites must still have a deletion form.');
			return $membership;
		};
		add_filter('wu_delete_form_get_object_membership', $check_object);
		ob_start();
		try {
			Form_Manager::get_instance()->render_model_delete_form();
			$html = ob_get_contents();
		} finally {
			ob_end_clean();
			remove_filter('wu_delete_form_get_object_membership', $check_object);
			unset($_REQUEST['model'], $_REQUEST['id']);
		}

		$this->assertStringContainsString('name="meta_key"', $html);
		$this->assertStringContainsString('value="999999"', $html);
	}

	public function test_orphaned_pending_site_fallback_requires_pending_site_metadata(): void {
		$_REQUEST['model'] = 'membership_meta_pending_site';
		$_REQUEST['id']    = '999999';

		$membership = null;
		$check_object = function ($object) use (&$membership) {
			$membership = $object;

			throw new \RuntimeException('Stop before the unavailable form renderer exits.');
		};
		add_filter('wu_delete_form_get_object_membership', $check_object);
		try {
			Form_Manager::get_instance()->render_model_delete_form();
			$this->fail('The object filter should stop rendering before the unavailable form exits.');
		} catch (\RuntimeException $exception) {
			$this->assertSame('Stop before the unavailable form renderer exits.', $exception->getMessage());
		} finally {
			remove_filter('wu_delete_form_get_object_membership', $check_object);
			unset($_REQUEST['model'], $_REQUEST['id']);
		}

		$this->assertFalse($membership);
	}

	public function test_delete_pending_site_removes_only_pending_metadata(): void {
		add_metadata('wu_membership', 999999, 'pending_site', new Site(['membership_id' => 999999]));
		add_metadata('wu_membership', 999999, 'keep_me', 'untouched');
		$_REQUEST['model']       = 'membership';
		$_REQUEST['id']          = '999999';
		$_REQUEST['meta_key']    = 'pending_site'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		$_REQUEST['confirm']     = '1';
		$_REQUEST['redirect_to'] = wu_network_admin_url('wp-ultimo-sites', ['type' => 'pending']);

		try {
			$result = $this->capture_ajax_json_response([Form_Manager::get_instance(), 'handle_model_delete_form']);
		} finally {
			unset($_REQUEST['model'], $_REQUEST['id'], $_REQUEST['meta_key'], $_REQUEST['confirm'], $_REQUEST['redirect_to']);
		}

		$this->assertTrue($result['decoded']['success']);
		$this->assertFalse(metadata_exists('wu_membership', 999999, 'pending_site'));
		$this->assertSame('untouched', get_metadata('wu_membership', 999999, 'keep_me', true));
	}

	public function test_pending_site_deletion_preserves_existing_membership(): void {
		$user_id  = self::factory()->user->create();
		$customer = wu_create_customer(
			[
				'user_id'       => $user_id,
				'email_address' => 'pending-delete-' . $user_id . '@example.com',
			]
		);
		$this->assertNotWPError($customer);
		$membership = wu_create_membership(
			[
				'customer_id' => $customer->get_id(),
				'plan_id'     => 0,
				'status'      => 'pending',
			]
		);
		$this->assertNotWPError($membership);
		$membership->create_pending_site(['title' => 'Pending deletion']);
		$_REQUEST['model']   = 'membership_meta_pending_site';
		$_REQUEST['id']      = (string) $membership->get_id();
		$_REQUEST['confirm'] = '1';

		ob_start();
		try {
			Form_Manager::get_instance()->render_model_delete_form();
			$html = ob_get_contents();
		} finally {
			ob_end_clean();
		}

		// Submit the actual hidden fields emitted by the deletion modal.
		preg_match_all('/<input\b[^>]*\btype="hidden"[^>]*>/i', $html, $inputs);
		foreach ($inputs[0] as $input) {
			preg_match('/\bname="([^"]+)"/', $input, $name);
			preg_match('/\bvalue="([^"]*)"/', $input, $value);
			if ($name && $value) {
				$_REQUEST[ $name[1] ] = html_entity_decode($value[1], ENT_QUOTES);
			}
		}

		try {
			$result = $this->capture_ajax_json_response([Form_Manager::get_instance(), 'handle_model_delete_form']);
		} finally {
			unset($_REQUEST['model'], $_REQUEST['id'], $_REQUEST['meta_key'], $_REQUEST['confirm'], $_REQUEST['redirect_to']);
		}

		$this->assertTrue($result['decoded']['success']);
		$this->assertFalse(metadata_exists('wu_membership', $membership->get_id(), 'pending_site'));
		$this->assertNotFalse(wu_get_membership($membership->get_id()));
	}
}
