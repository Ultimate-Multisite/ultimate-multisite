<?php
/**
 * Class used for querying products.
 *
 * @package WP_Ultimo
 * @subpackage Database\Products
 * @since 2.0.0
 */

namespace WP_Ultimo\Database\Products;

use WP_Ultimo\Database\Engine\Table;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Setup the "wu_product" database table
 *
 * @since 2.0.0
 */
final class Products_Table extends Table {

	/**
	 * Table name
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $name = 'products';

	/**
	 * Is this table global?
	 *
	 * @since 2.0.0
	 * @var boolean
	 */
	protected $global = true;

	/**
	 * Table current version
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $version = '2.0.1-revision.20230601';

	/**
	 * List of table upgrades.
	 *
	 * @var array
	 */
	protected $upgrades = [
		'2.0.1-revision.20210419' => 20_210_419,
		'2.0.1-revision.20210607' => 20_210_607,
		'2.0.1-revision.20230601' => 20_230_601,
	];

	/**
	 * Setup the database schema
	 *
	 * @access protected
	 * @since  2.0.0
	 * @return void
	 */
	protected function set_schema(): void {

		$this->schema = "id bigint(20) NOT NULL AUTO_INCREMENT,
			name tinytext NOT NULL DEFAULT '',
			slug tinytext NOT NULL DEFAULT '',
			parent_id bigint(20),
			migrated_from_id bigint(20) DEFAULT NULL,
			description longtext NOT NULL default '',
			product_group varchar(20) DEFAULT '',
			currency varchar(10) NOT NULL DEFAULT 'USD',
			pricing_type varchar(20) NOT NULL DEFAULT 'paid',
			amount decimal(13,4) default 0,
			setup_fee decimal(13,4) default 0,
			recurring tinyint(4) default 1,
			trial_duration smallint default 0,
			trial_duration_unit enum('day', 'week', 'month', 'year'),
			duration smallint default 0,
			duration_unit enum('day', 'week', 'month', 'year'),
			billing_cycles smallint default 0,
			list_order tinyint default 10,
			active tinyint(4) default 1,
			type tinytext NOT NULL DEFAULT '',
			date_created datetime NULL,
			date_modified datetime NULL,
			PRIMARY KEY (id)";
	}

	/**
	 * Adds the product_group column.
	 *
	 * This does not work on older versions of MySQL, so we needed
	 * the other migration below.
	 *
	 * @since 2.0.0
	 * @return bool
	 */
	protected function __20210419() { // phpcs:ignore

		$result = $this->column_exists('product_group');

		// Maybe add column
		if (empty($result)) {
			$query = "ALTER TABLE {$this->table_name} ADD COLUMN `product_group` varchar(20) default '' AFTER `description`;";

			$result = $this->get_db()->query($query);
		}

		// Return success/fail
		return $this->is_success($result);
	}

	/**
	 * Adds the product_group column.
	 *
	 * @since 2.0.0
	 * @return bool
	 */
	protected function __20210607() { // phpcs:ignore

		$result = $this->column_exists('product_group');

		// Maybe add column
		if (empty($result)) {
			$query_set = "SET sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';";

			$result_set = $this->get_db()->query($query_set);

			if ($this->is_success($result_set) === false) {
				return false;
			}

			$query = "ALTER TABLE {$this->table_name} ADD COLUMN `product_group` varchar(20) default '' AFTER `description`;";

			$result = $this->get_db()->query($query);
		}

		// Return success/fail
		return $this->is_success($result);
	}

	/**
	 * Fixes the datetime columns to accept null.
	 *
	 * @since 2.1.2
	 */
	protected function __20230601(): bool { // phpcs:ignore PHPCompatibility.FunctionNameRestrictions.ReservedFunctionNames.MethodDoubleUnderscore

		$null_columns = [
			'date_created',
			'date_modified',
		];

		foreach ($null_columns as $column) {
			$query = "ALTER TABLE {$this->table_name} MODIFY COLUMN `{$column}` datetime DEFAULT NULL;";

			$result = $this->get_db()->query($query);

			if ( ! $this->is_success($result)) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Expands the pricing_type column from varchar(10) to varchar(20).
	 *
	 * The 'pay_what_you_want' value is 17 characters and was silently
	 * truncated to 'pay_what_y' by MySQL, breaking PWYW product detection.
	 *
	 * @since 2.4.14
	 */
	protected function __20260327(): bool { // phpcs:ignore PHPCompatibility.FunctionNameRestrictions.ReservedFunctionNames.MethodDoubleUnderscore

		$query = "ALTER TABLE {$this->table_name} MODIFY COLUMN `pricing_type` varchar(20) NOT NULL DEFAULT 'paid';";

		$result = $this->get_db()->query($query);

		return $this->is_success($result);
	}
}
