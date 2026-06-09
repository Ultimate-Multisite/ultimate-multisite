<?php
/**
 * Email OTP attempts schema class.
 *
 * @package WP_Ultimo
 * @subpackage Database\Email_OTP_Attempts
 * @since 2.13.2
 */

namespace WP_Ultimo\Database\Email_OTP_Attempts;

use WP_Ultimo\Database\Engine\Schema;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Email OTP attempts schema class.
 *
 * @since 2.13.2
 */
class Email_OTP_Attempts_Schema extends Schema {

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
			'name'       => 'email',
			'type'       => 'varchar',
			'length'     => '190',
			'searchable' => true,
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
			'name'   => 'token_hash',
			'type'   => 'char',
			'length' => '64',
		],

		[
			'name'   => 'code_hash',
			'type'   => 'varchar',
			'length' => '255',
		],

		[
			'name'   => 'ip_hash',
			'type'   => 'char',
			'length' => '64',
		],

		[
			'name'     => 'attempts',
			'type'     => 'tinyint',
			'length'   => '4',
			'unsigned' => true,
			'sortable' => true,
		],

		[
			'name'       => 'expires_at',
			'type'       => 'datetime',
			'default'    => null,
			'date_query' => true,
			'sortable'   => true,
			'allow_null' => true,
		],

		[
			'name'       => 'consumed_at',
			'type'       => 'datetime',
			'default'    => null,
			'date_query' => true,
			'sortable'   => true,
			'allow_null' => true,
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

	];
}
