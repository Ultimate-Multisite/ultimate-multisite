<?php
/**
 * Passkey credentials schema class.
 *
 * @package WP_Ultimo
 * @subpackage Database\Passkey_Credentials
 * @since 2.13.2
 */

namespace WP_Ultimo\Database\Passkey_Credentials;

use WP_Ultimo\Database\Engine\Schema;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Passkey credentials schema class.
 *
 * @since 2.13.2
 */
class Passkey_Credentials_Schema extends Schema {

	/**
	 * Array of database column objects.
	 *
	 * @since  2.13.2
	 * @access public
	 * @var array
	 */
	public $columns = [

		[
			'name'     => 'id',
			'type'     => 'bigint',
			'length'   => '20',
			'unsigned' => true,
			'extra'    => 'auto_increment',
			'primary'  => true,
			'sortable' => true,
		],

		[
			'name'       => 'user_id',
			'type'       => 'bigint',
			'length'     => '20',
			'unsigned'   => true,
			'sortable'   => true,
			'transition' => true,
		],

		[
			'name'       => 'credential_id',
			'type'       => 'varchar',
			'length'     => '255',
			'searchable' => true,
		],

		[
			'name'    => 'public_key',
			'type'    => 'longtext',
			'default' => '',
		],

		[
			'name'     => 'sign_count',
			'type'     => 'bigint',
			'length'   => '20',
			'unsigned' => true,
			'sortable' => true,
		],

		[
			'name'   => 'aaguid',
			'type'   => 'varchar',
			'length' => '64',
		],

		[
			'name'   => 'transports',
			'type'   => 'varchar',
			'length' => '255',
		],

		[
			'name'     => 'active',
			'type'     => 'tinyint',
			'length'   => '1',
			'unsigned' => true,
			'sortable' => true,
		],

		[
			'name'       => 'date_created',
			'type'       => 'datetime',
			'default'    => null,
			'created'    => true,
			'date_query' => true,
			'sortable'   => true,
			'allow_null' => true,
		],

		[
			'name'       => 'date_updated',
			'type'       => 'datetime',
			'default'    => null,
			'modified'   => true,
			'date_query' => true,
			'sortable'   => true,
			'allow_null' => true,
		],

		[
			'name'       => 'date_last_used',
			'type'       => 'datetime',
			'default'    => null,
			'date_query' => true,
			'sortable'   => true,
			'allow_null' => true,
		],

	];
}
