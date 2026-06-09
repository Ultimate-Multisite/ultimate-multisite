<?php
/**
 * Class used for querying WebAuthn challenges.
 *
 * @package WP_Ultimo
 * @subpackage Database\WebAuthn_Challenges
 * @since 2.13.2
 */

namespace WP_Ultimo\Database\WebAuthn_Challenges;

use WP_Ultimo\Database\Engine\Table;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Setup the "wu_webauthn_challenges" database table.
 *
 * @since 2.13.2
 */
final class WebAuthn_Challenges_Table extends Table {

	/**
	 * Table name.
	 *
	 * @since 2.13.2
	 * @var string
	 */
	protected $name = 'webauthn_challenges';

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
			challenge_hash char(64) NOT NULL,
			type enum('registration', 'authentication') NOT NULL,
			user_id bigint(20) NOT NULL default '0',
			rp_id varchar(255) NOT NULL default '',
			origin varchar(255) NOT NULL default '',
			expires_at datetime NULL,
			used_at datetime NULL,
			date_created datetime NULL,
			PRIMARY KEY (id),
			UNIQUE KEY challenge_hash (challenge_hash),
			KEY user_id (user_id),
			KEY type (type),
			KEY expires_at (expires_at)";
	}
}
