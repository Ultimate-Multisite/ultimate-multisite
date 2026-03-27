<?php
/**
 * Product schema class
 *
 * @package WP_Ultimo
 * @subpackage Database\Products
 * @since 2.0.0
 */

namespace WP_Ultimo\Database\Products;

use WP_Ultimo\Database\Engine\Schema;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Products Schema Class.
 *
 * @since 2.0.0
 */
class Products_Schema extends Schema {

	/**
	 * Array of database column objects
	 *
	 * @since  2.0.0
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
			'name'       => 'slug',
			'type'       => 'varchar',
			'searchable' => true,
			'sortable'   => true,
		],

		[
			'name'       => 'parent_id',
			'type'       => 'bigint',
			'length'     => '20',
			'unsigned'   => true,
			'sortable'   => true,
			'transition' => true,
			'allow_null' => true,
		],

		[
			'name'       => 'migrated_from_id',
			'type'       => 'bigint',
			'length'     => '20',
			'unsigned'   => true,
			'sortable'   => true,
			'allow_null' => true,
		],

		[
			'name'       => 'name',
			'type'       => 'varchar',
			'searchable' => true,
			'sortable'   => true,
		],

		[
			'name'       => 'description',
			'type'       => 'longtext',
			'default'    => '',
			'searchable' => true,
		],

		[
			'name'       => 'product_group',
			'type'       => 'varchar',
			'searchable' => true,
			'sortable'   => true,
			'allow_null' => true,
		],

		[
			'name'     => 'currency',
			'type'     => 'varchar',
			'length'   => '10',
			'default'  => 'USD',
			'sortable' => true,
		],

		[
			'name'     => 'pricing_type',
			'type'     => 'varchar',
			'length'   => '20',
			'default'  => 'paid',
			'sortable' => true,
		],

		[
			'name'       => 'amount',
			'type'       => 'decimal(13,4)',
			'default'    => '',
			'sortable'   => true,
			'transition' => true,
		],

		[
			'name'       => 'setup_fee',
			'type'       => 'decimal(13,4)',
			'default'    => '',
			'sortable'   => true,
			'transition' => true,
		],

		[
			'name'       => 'recurring',
			'type'       => 'tinyint',
			'length'     => '4',
			'unsigned'   => true,
			'default'    => 1,
			'transition' => true,
			'sortable'   => true,
		],

		[
			'name'       => 'trial_duration',
			'type'       => 'smallint',
			'unsigned'   => true,
			'default'    => '0',
			'sortable'   => true,
			'transition' => true,
		],

		[
			'name'    => 'trial_duration_unit',
			'type'    => 'enum(\'day\', \'month\', \'week\', \'year\')',
			'default' => 'none',
		],

		[
			'name'       => 'duration',
			'type'       => 'smallint',
			'unsigned'   => true,
			'default'    => '0',
			'sortable'   => true,
			'transition' => true,
		],

		[
			'name'    => 'duration_unit',
			'type'    => 'enum(\'day\', \'month\', \'week\', \'year\')',
			'default' => 'none',
		],

		[
			'name'       => 'billing_cycles',
			'type'       => 'smallint',
			'unsigned'   => true,
			'default'    => '0',
			'sortable'   => true,
			'transition' => true,
		],

		[
			'name'       => 'list_order',
			'type'       => 'tinyint',
			'length'     => '4',
			'unsigned'   => true,
			'default'    => 10,
			'transition' => true,
			'sortable'   => true,
		],

		[
			'name'       => 'active',
			'type'       => 'tinyint',
			'length'     => '4',
			'unsigned'   => true,
			'default'    => 1,
			'transition' => true,
			'sortable'   => true,
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
			'name'       => 'date_modified',
			'type'       => 'datetime',
			'default'    => null,
			'modified'   => true,
			'date_query' => true,
			'sortable'   => true,
			'allow_null' => true,
		],

		[
			'name'       => 'type',
			'type'       => 'varchar',
			'searchable' => true,
			'sortable'   => true,
		],

	];
}
