<?php
/**
 * Custom Table Loader
 *
 * Registers our custom tables.
 *
 * @package WP_Ultimo
 * @subpackage Loaders
 * @since 2.0.0
 */

namespace WP_Ultimo\Loaders;

// Exit if accessed directly
use WP_Ultimo\Database\Engine\Table;

defined('ABSPATH') || exit;

/**
 * Registers our custom tables.
 *
 * @since 2.0.0
 */
class Table_Loader {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * The Domain Mappings Table
	 *
	 * @since 2.2.0
	 * @var \WP_Ultimo\Database\Domains\Domains_Table
	 */
	public $domain_table;

	/**
	 * The Domain Meta Table
	 *
	 * @since 2.4.0
	 * @var \WP_Ultimo\Database\Domains\Domains_Meta_Table
	 */
	public $domainmeta_table;

	/**
	 * The Products Table
	 *
	 * @since 2.2.0
	 * @var \WP_Ultimo\Database\Products\Products_Table
	 */
	public $product_table;

	/**
	 * Loads the Products Meta Table
	 *
	 * @since 2.2.0
	 * @var \WP_Ultimo\Database\Products\Products_Meta_Table
	 */
	public $productmeta_table;

	/**
	 * The Discount Codes Table
	 *
	 * @since 2.2.0
	 * @var \WP_Ultimo\Database\Discount_Codes\Discount_Codes_Table
	 */
	public $discount_code_table;

	/**
	 * The Discount Codes Meta Table
	 *
	 * @since 2.2.0
	 * @var \WP_Ultimo\Database\Discount_Codes\Discount_Codes_Meta_Table
	 */
	public $discount_codemeta_table;

	/**
	 * The Sites Table
	 *
	 * @since 2.2.0
	 * @var \WP_Ultimo\Database\Sites\Sites_Table
	 */
	public $site_table;

	/**
	 * The Sites Meta Table
	 *
	 * @since 2.2.0
	 * @var \WP_Ultimo\Database\Sites\Sites_Meta_Table
	 */
	public $sitemeta_table;

	/**
	 * The Customer Table
	 *
	 * @since 2.2.0
	 * @var \WP_Ultimo\Database\Customers\Customers_Table
	 */
	public $customer_table;

	/**
	 * The Customer Meta Table
	 *
	 * @since 2.2.0
	 * @var \WP_Ultimo\Database\Customers\Customers_Meta_Table
	 */
	public $customermeta_table;

	/**
	 * The Memberships Table
	 *
	 * @since 2.2.0
	 * @var \WP_Ultimo\Database\Memberships\Memberships_Table
	 */
	public $membership_table;

	/**
	 * The Memberships Meta Table
	 *
	 * @since 2.2.0
	 * @var \WP_Ultimo\Database\Memberships\Memberships_Meta_Table
	 */
	public $membershipmeta_table;

	/**
	 * The Payments Table
	 *
	 * @since 2.2.0
	 * @var \WP_Ultimo\Database\Payments\Payments_Table
	 */
	public $payment_table;

	/**
	 * The Payments Meta Table
	 *
	 * @since 2.2.0
	 * @var \WP_Ultimo\Database\Payments\Payments_Meta_Table
	 */
	public $paymentmeta_table;

	/**
	 * The Posts Table
	 *
	 * @since 2.2.0
	 * @var \WP_Ultimo\Database\Posts\Posts_Table
	 */
	public $post_table;

	/**
	 * The Posts Meta Table
	 *
	 * @since 2.2.0
	 * @var \WP_Ultimo\Database\Posts\Posts_Meta_Table
	 */
	public $postmeta_table;

	/**
	 * The Webhook Table
	 *
	 * @since 2.2.0
	 * @var \WP_Ultimo\Database\Webhooks\Webhooks_Table
	 */
	public $webhook_table;

	/**
	 * The Event Table
	 *
	 * @since 2.2.0
	 * @var \WP_Ultimo\Database\Events\Events_Table
	 */
	public $event_table;

	/**
	 * The Checkout Forms Table
	 *
	 * @since 2.2.0
	 * @var \WP_Ultimo\Database\Checkout_Forms\Checkout_Forms_Table
	 */
	public $checkout_form_table;

	/**
	 * The Checkout Forms Meta Table
	 *
	 * @since 2.2.0
	 * @var \WP_Ultimo\Database\Checkout_Forms\Checkout_Forms_Meta_Table
	 */
	public $checkout_formmeta_table;

	/**
	 * The Passkey Credentials Table.
	 *
	 * @since 2.13.2
	 * @var \WP_Ultimo\Database\Passkey_Credentials\Passkey_Credentials_Table
	 */
	public $passkey_credential_table;

	/**
	 * The WebAuthn Challenges Table.
	 *
	 * @since 2.13.2
	 * @var \WP_Ultimo\Database\WebAuthn_Challenges\WebAuthn_Challenges_Table
	 */
	public $webauthn_challenge_table;

	/**
	 * The Email OTP Attempts Table.
	 *
	 * @since 2.13.2
	 * @var \WP_Ultimo\Database\Email_OTP_Attempts\Email_OTP_Attempts_Table
	 */
	public $email_otp_attempt_table;

	/**
	 * Loads the table objects for our custom tables.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function init(): void {
		/**
		 * Loads the Domain Mappings (and Meta) Tables
		 */
		$this->domain_table     = new \WP_Ultimo\Database\Domains\Domains_Table();
		$this->domainmeta_table = new \WP_Ultimo\Database\Domains\Domains_Meta_Table();

		/**
		 * Loads the Products (and Meta) Tables
		 */
		$this->product_table     = new \WP_Ultimo\Database\Products\Products_Table();
		$this->productmeta_table = new \WP_Ultimo\Database\Products\Products_Meta_Table();

		/**
		 * Loads the Discount Codes Table
		 */
		$this->discount_code_table     = new \WP_Ultimo\Database\Discount_Codes\Discount_Codes_Table();
		$this->discount_codemeta_table = new \WP_Ultimo\Database\Discount_Codes\Discount_Codes_Meta_Table();

		/**
		 * Loads the Sites (and Meta) Tables
		 */
		$this->site_table     = new \WP_Ultimo\Database\Sites\Sites_Table();
		$this->sitemeta_table = new \WP_Ultimo\Database\Sites\Sites_Meta_Table();

		/**
		 * Loads the Customer Table
		 */
		$this->customer_table     = new \WP_Ultimo\Database\Customers\Customers_Table();
		$this->customermeta_table = new \WP_Ultimo\Database\Customers\Customers_Meta_Table();

		/**
		 * Loads the Memberships Table
		 */
		$this->membership_table     = new \WP_Ultimo\Database\Memberships\Memberships_Table();
		$this->membershipmeta_table = new \WP_Ultimo\Database\Memberships\Memberships_Meta_Table();

		/**
		 * Loads the Payments Table
		 */
		$this->payment_table     = new \WP_Ultimo\Database\Payments\Payments_Table();
		$this->paymentmeta_table = new \WP_Ultimo\Database\Payments\Payments_Meta_Table();

		/**
		 * Loads the Posts (and Meta) Tables
		 */
		$this->post_table     = new \WP_Ultimo\Database\Posts\Posts_Table();
		$this->postmeta_table = new \WP_Ultimo\Database\Posts\Posts_Meta_Table();

		/**
		 * Loads the Webhook Table
		 */
		$this->webhook_table = new \WP_Ultimo\Database\Webhooks\Webhooks_Table();

		/**
		 * Loads the Webhook Table
		 */
		$this->event_table = new \WP_Ultimo\Database\Events\Events_Table();

		/**
		 * Loads the Checkout Forms Table
		 */
		$this->checkout_form_table     = new \WP_Ultimo\Database\Checkout_Forms\Checkout_Forms_Table();
		$this->checkout_formmeta_table = new \WP_Ultimo\Database\Checkout_Forms\Checkout_Forms_Meta_Table();

		/**
		 * Loads passwordless authentication tables.
		 */
		$this->passkey_credential_table = new \WP_Ultimo\Database\Passkey_Credentials\Passkey_Credentials_Table();
		$this->webauthn_challenge_table = new \WP_Ultimo\Database\WebAuthn_Challenges\WebAuthn_Challenges_Table();
		$this->email_otp_attempt_table  = new \WP_Ultimo\Database\Email_OTP_Attempts\Email_OTP_Attempts_Table();
	}

	/**
	 * Returns all the table objects.
	 *
	 * @since 2.0.0
	 * @return Table[]
	 */
	public function get_tables() {

		return get_object_vars($this);
	}

	/**
	 * Checks if we have all the tables installed.
	 *
	 * @since 2.0.0
	 * @return boolean
	 */
	public function is_installed() {

		$all_installed = true;

		$tables = $this->get_tables();

		foreach ($tables as $table) {
			if ( ! $table->exists()) {
				$all_installed = false;
			}
		}

		return $all_installed;
	}
}
