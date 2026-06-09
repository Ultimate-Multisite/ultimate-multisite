<?php
/**
 * Class used for querying passkey credentials.
 *
 * @package WP_Ultimo
 * @subpackage Database\Passkey_Credentials
 * @since 2.13.2
 */

namespace WP_Ultimo\Database\Passkey_Credentials;

use WP_Ultimo\Database\Engine\Table;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Setup the "wu_passkey_credentials" database table.
 *
 * @since 2.13.2
 */
final class Passkey_Credentials_Table extends Table {

	/**
	 * Table name.
	 *
	 * @since 2.13.2
	 * @var string
	 */
	protected $name = 'passkey_credentials';

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
			user_id bigint(20) NOT NULL default '0',
			credential_id varchar(255) NOT NULL,
			public_key longtext NOT NULL,
			sign_count bigint(20) unsigned NOT NULL default '0',
			aaguid varchar(64) NOT NULL default '',
			transports varchar(255) NOT NULL default '',
			active tinyint(1) NOT NULL default '1',
			date_created datetime NULL,
			date_updated datetime NULL,
			date_last_used datetime NULL,
			PRIMARY KEY (id),
			UNIQUE KEY credential_id (credential_id),
			KEY user_id (user_id),
			KEY active (active)";
	}
}
