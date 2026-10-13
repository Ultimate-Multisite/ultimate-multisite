<?php
/**
 * SSO nonce schema class.
 *
 * @package WP_Ultimo
 * @subpackage Database\SSO_Nonces
 * @since 2.17.4
 */

namespace WP_Ultimo\Database\SSO_Nonces;

use WP_Ultimo\Database\Engine\Schema;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * SSO nonce schema class.
 *
 * @since 2.17.4
 */
class SSO_Nonces_Schema extends Schema {

	/**
	 * Array of database column objects.
	 *
	 * @since 2.17.4
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
			'name'   => 'jti_hash',
			'type'   => 'char',
			'length' => '64',
		],

		[
			'name'       => 'blog_id',
			'type'       => 'bigint',
			'length'     => '20',
			'unsigned'   => true,
			'sortable'   => true,
			'transition' => true,
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
