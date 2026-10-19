<?php
/**
 * Class used for querying SSO nonce records.
 *
 * @package WP_Ultimo
 * @subpackage Database\SSO_Nonces
 * @since 2.17.4
 */

namespace WP_Ultimo\Database\SSO_Nonces;

use WP_Ultimo\Database\Engine\Table;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Setup the "wu_sso_nonces" database table.
 *
 * @since 2.17.4
 */
final class SSO_Nonces_Table extends Table {

	/**
	 * Table name.
	 *
	 * @since 2.17.4
	 * @var string
	 */
	protected $name = 'sso_nonces';

	/**
	 * Is this table global?
	 *
	 * @since 2.17.4
	 * @var boolean
	 */
	protected $global = true;

	/**
	 * Table current version.
	 *
	 * @since 2.17.4
	 * @var string
	 */
	protected $version = '2.17.4';

	/**
	 * Setup the database schema.
	 *
	 * @access protected
	 * @since  2.17.4
	 * @return void
	 */
	protected function set_schema(): void {

		$this->schema = "id bigint(20) NOT NULL auto_increment,
			jti_hash char(64) NOT NULL,
			blog_id bigint(20) NOT NULL default '0',
			expires_at datetime NULL,
			consumed_at datetime NULL,
			date_created datetime NULL,
			PRIMARY KEY (id),
			UNIQUE KEY jti_hash (jti_hash),
			KEY blog_id (blog_id),
			KEY expires_at (expires_at)";
	}
}
