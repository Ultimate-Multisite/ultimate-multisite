<?php
/**
 * WebAuthn challenges schema class.
 *
 * @package WP_Ultimo
 * @subpackage Database\WebAuthn_Challenges
 * @since 2.13.2
 */

namespace WP_Ultimo\Database\WebAuthn_Challenges;

use WP_Ultimo\Database\Engine\Schema;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * WebAuthn challenges schema class.
 *
 * @since 2.13.2
 */
class WebAuthn_Challenges_Schema extends Schema {

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
			'name'   => 'challenge_hash',
			'type'   => 'char',
			'length' => '64',
		],

		[
			'name'    => 'type',
			'type'    => 'enum(\'registration\', \'authentication\')',
			'default' => 'authentication',
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
			'name'   => 'rp_id',
			'type'   => 'varchar',
			'length' => '255',
		],

		[
			'name'   => 'origin',
			'type'   => 'varchar',
			'length' => '255',
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
			'name'       => 'used_at',
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
