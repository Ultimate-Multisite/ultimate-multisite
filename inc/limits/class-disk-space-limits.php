<?php
/**
 * Handles limitations to disk space
 *
 * @package WP_Ultimo
 * @subpackage Limits
 * @since 2.0.0
 */

namespace WP_Ultimo\Limits;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Handles limitations to post types, uploads and more.
 *
 * @since 2.0.0
 */
class Disk_Space_Limits {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * Whether or not the disk space limitations should be loaded.
	 * This is for performance reasons, so we don't have to run all the hooks if the site doesn't have limitations.
	 *
	 * @since 2.1.2
	 * @var boolean
	 */
	protected $should_load = false;

	/**
	 * Whether or not the class has started.
	 *
	 * @since 2.1.2
	 * @var boolean
	 */
	protected $started = false;

	/**
	 * Runs on the first and only instantiation.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function init(): void {

		add_filter( 'site_option_upload_space_check_disabled', array( $this, 'upload_space_check_disabled' ) );

		add_filter( 'get_space_allowed', array( $this, 'apply_disk_space_limitations' ) );

		add_action( 'wu_async_after_membership_update_products', array( $this, 'handle_downgrade' ) );
	}

	/**
	 * Disables the upload space check if the site has limitations.
	 * This way we can handle our own checks.
	 *
	 * @since 2.1.2
	 *
	 * @param int $value The current value.
	 * @return int
	 */
	public function upload_space_check_disabled( $value ) {

		if ( ! $this->should_load() ) {
			return $value;
		}

		return 0;
	}

	/**
	 * Checks if the disk space limitations should be loaded.
	 *
	 * @since 2.1.2
	 * @return boolean
	 */
	protected function should_load() {

		if ( $this->started ) {
			return $this->should_load;
		}

		$this->started     = true;
		$this->should_load = true;

		/**
		 * Allow plugin developers to short-circuit the limitations.
		 *
		 * You can use this filter to run arbitrary code before any of the limits get initiated.
		 * If you filter returns any truthy value, the process will move on, if it returns any falsy value,
		 * the code will return and none of the hooks below will run.
		 *
		 * @since 1.7.0
		 * @return bool
		 */
		if ( ! apply_filters( 'wu_apply_plan_limits', wu_get_current_site()->has_limitations() ) ) {
			$this->should_load = false;
		}

		if ( ! wu_get_current_site()->has_module_limitation( 'disk_space' ) ) {
			$this->should_load = false;
		}

		return $this->should_load;
	}

	/**
	 * Handles disk space enforcement after a membership product change (upgrade/downgrade).
	 *
	 * Disk space is a soft-block limit: we cannot safely delete uploaded files on behalf
	 * of the customer. Instead, when the new quota is lower than the current disk usage
	 * we store a transient-based admin notice on each affected site so the site owner
	 * is informed and can take action themselves.
	 *
	 * @since 2.2.0
	 *
	 * @param int $membership_id The membership that was updated.
	 * @return void
	 */
	public function handle_downgrade( $membership_id ): void {

		$membership = wu_get_membership( $membership_id );

		if ( ! $membership ) {
			return;
		}

		$sites = $membership->get_sites( false );

		foreach ( $sites as $site ) {

			$blog_id = $site->get_id();

			switch_to_blog( $blog_id );

			$quota_mb = $membership->get_limitations()->disk_space->get_limit();

			if ( ! is_numeric( $quota_mb ) || (int) $quota_mb <= 0 ) {
				restore_current_blog();
				continue;
			}

			$used_mb = get_space_used();

			if ( $used_mb > (float) $quota_mb ) {

				/**
				 * Fires when a site's disk usage exceeds the new quota after a downgrade.
				 *
				 * @since 2.2.0
				 *
				 * @param int   $blog_id    The site ID.
				 * @param float $used_mb    Current disk usage in MB.
				 * @param int   $quota_mb   New quota in MB.
				 * @param int   $membership_id The membership ID.
				 */
				do_action( 'wu_disk_space_downgrade_exceeded', $blog_id, $used_mb, (int) $quota_mb, $membership_id );

				set_transient(
					'wu_disk_space_over_quota_notice',
					array(
						'used'  => $used_mb,
						'quota' => (int) $quota_mb,
					),
					WEEK_IN_SECONDS
				);
			}

			restore_current_blog();
		}
	}

	/**
	 * Changes the disk_space to the one on the product.
	 *
	 * @since 2.0.0
	 *
	 * @param string $disk_space The new disk space.
	 * @return int
	 */
	public function apply_disk_space_limitations( $disk_space ) {

		if ( ! $this->should_load() ) {
			return $disk_space;
		}

		$modified_disk_space = wu_get_current_site()->get_limitations()->disk_space->get_limit();

		if ( is_numeric( $modified_disk_space ) ) {
			return $modified_disk_space;
		}

		return $disk_space;
	}
}
