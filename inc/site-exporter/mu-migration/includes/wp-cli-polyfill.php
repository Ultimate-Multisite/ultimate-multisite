<?php
/**
 * Minimal WP-CLI polyfill for web/AJAX MU-Migration runs.
 *
 * @package TenUp\MU_Migration
 */

namespace {
	if (! class_exists('WP_CLI_Command')) {
		/**
		 * Minimal command base class used when WP-CLI is not loaded.
		 */
		class WP_CLI_Command {

			/**
			 * Raise a command error.
			 *
			 * @param string $message Error message.
			 * @return void
			 */
			public static function error($message): void {

				throw new RuntimeException((string) $message);
			}
		}
	}

	if (! class_exists('WP_CLI')) {
		/**
		 * Minimal WP_CLI facade used by bundled MU-Migration command classes.
		 */
		class WP_CLI {

			/**
			 * No-op command registration outside WP-CLI.
			 *
			 * @return void
			 */
			public static function add_command(): void {

				return;
			}

			/**
			 * No-op informational output outside WP-CLI.
			 *
			 * @return void
			 */
			public static function line(): void {

				return;
			}

			/**
			 * No-op log output outside WP-CLI.
			 *
			 * @return void
			 */
			public static function log(): void {

				return;
			}

			/**
			 * No-op success output outside WP-CLI.
			 *
			 * @return void
			 */
			public static function success(): void {

				return;
			}

			/**
			 * No-op warning output outside WP-CLI.
			 *
			 * @return void
			 */
			public static function warning(): void {

				return;
			}

			/**
			 * Raise a command error outside WP-CLI.
			 *
			 * @param string $message Error message.
			 * @return void
			 */
			public static function error($message): void {

				throw new RuntimeException((string) $message);
			}

			/**
			 * Minimal launch result outside WP-CLI.
			 *
			 * @return object
			 */
			public static function launch() {

				return (object) [
					'return_code' => 1,
					'stdout'      => '',
					'stderr'      => '',
				];
			}
		}
	}
}

namespace WP_CLI\Utils {
	if (! function_exists(__NAMESPACE__ . '\\make_progress_bar')) {
		/**
		 * Return a no-op progress bar outside WP-CLI.
		 *
		 * @return object
		 */
		function make_progress_bar() {

			return new class() {

				/**
				 * No-op display.
				 *
				 * @return void
				 */
				public function display(): void {

					return;
				}

				/**
				 * No-op tick.
				 *
				 * @return void
				 */
				public function tick(): void {

					return;
				}

				/**
				 * No-op finish.
				 *
				 * @return void
				 */
				public function finish(): void {

					return;
				}
			};
		}
	}
}
