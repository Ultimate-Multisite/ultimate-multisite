<?php
/**
 * Class used for querying email OTP attempts.
 *
 * @package WP_Ultimo
 * @subpackage Database\Email_OTP_Attempts
 * @since 2.13.2
 */

namespace WP_Ultimo\Database\Email_OTP_Attempts;

use WP_Ultimo\Database\Engine\Table;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Setup the "wu_email_otp_attempts" database table.
 *
 * @since 2.13.2
 */
final class Email_OTP_Attempts_Table extends Table {

	/**
	 * Table name.
	 *
	 * @since 2.13.2
	 * @var string
	 */
	protected $name = 'email_otp_attempts';

	/**
	 * Is this table global?
	 *
	 * @since 2.13.2
	 * @var boolean
	 */
	protected $global = true;

	/**
	 * Table current version.
	 *
	 * @since 2.13.2
	 * @var string
	 */
	protected $version = '2.13.2';

	/**
	 * Setup the database schema.
	 *
	 * @access protected
	 * @since  2.13.2
	 * @return void
	 */
	protected function set_schema(): void {

		$this->schema = "id bigint(20) NOT NULL auto_increment,
			email varchar(190) NOT NULL default '',
			user_id bigint(20) NOT NULL default '0',
			token_hash char(64) NOT NULL,
			code_hash varchar(255) NOT NULL default '',
			ip_hash char(64) NOT NULL default '',
			attempts tinyint(4) NOT NULL default '0',
			expires_at datetime NULL,
			consumed_at datetime NULL,
			date_created datetime NULL,
			PRIMARY KEY (id),
			UNIQUE KEY token_hash (token_hash),
			KEY email (email),
			KEY user_id (user_id),
			KEY ip_hash (ip_hash),
			KEY expires_at (expires_at)";
	}
}
