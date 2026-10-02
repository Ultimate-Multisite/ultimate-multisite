<?php
/**
 * Integration-aware clone sanitation selected by template configuration.
 *
 * @package WP_Ultimo
 * @subpackage Compat
 */

namespace WP_Ultimo\Compat;

defined('ABSPATH') || exit;

/** Keep copied delivery identities and subscriber data out of fresh customer sites. */
class Template_Clone_Compat {

	/**
	 * Initialize integrations in the destination site's context.
	 *
	 * @param array $configuration Template-owned clone configuration.
	 * @param array $payload Source and destination IDs.
	 * @return true|\WP_Error Result of integration sanitation.
	 */
	public static function initialize(array $configuration, array $payload) {
		global $wpdb;

		if ( ! empty($configuration['disable_form_notifications'])) {
			foreach (get_posts(
				[
					'post_type'      => 'wpforms',
					'post_status'    => 'any',
					'posts_per_page' => -1,
				]
				) as $form) {
				$data = json_decode($form->post_content, true);
				if ( ! is_array($data)) {
					return new \WP_Error('clone_form_invalid', __('A cloned form definition is invalid.', 'ultimate-multisite'));
				}
				$data['settings']                        = is_array($data['settings'] ?? null) ? $data['settings'] : [];
				$data['settings']['notification_enable'] = '0';
				$content                                 = wp_json_encode($data);
				if ( ! is_string($content) || false === $wpdb->update($wpdb->posts, ['post_content' => $content], ['ID' => $form->ID])) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Clone initialization writes copied form configuration.
					return new \WP_Error('clone_form_update_failed', __('Could not disable cloned form notifications.', 'ultimate-multisite'));
				}
				clean_post_cache($form->ID);
			}
		}

		if ( ! empty($configuration['clear_newsletter_identity'])) {
			$settings = get_option('newsletter_main', []);
			$settings = is_array($settings) ? $settings : [];
			foreach (['sender_name', 'sender_email', 'reply_to'] as $key) {
				$settings[ $key ] = '';
			}
			update_option('newsletter_main', $settings, false);
			$saved = get_option('newsletter_main');
			if ($settings !== $saved) {
				return new \WP_Error('clone_newsletter_update_failed', __('Could not clear the cloned newsletter identity.', 'ultimate-multisite'));
			}
		}

		if ( ! empty($configuration['clear_newsletter_subscribers'])) {
			foreach (['tnp_user_meta', 'tnp_users'] as $suffix) {
				$table = $wpdb->prefix . $suffix;
				if ($table === $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)))) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Clone-time schema inspection, not a cacheable frontend read.
					if (false === $wpdb->query($wpdb->prepare('DELETE FROM %i', $table))) {
						return new \WP_Error('clone_newsletter_cleanup_failed', __('Could not clear cloned newsletter subscribers.', 'ultimate-multisite'));
					}
				}
			}
		}

		// Template plugins need not be active on the checkout/control-plane site.
		// Delegate through their autoloadable service, not request-local plugin hooks.
		$agent_initializer = '\SdAiAgent\Core\CloneInitialization';
		if (class_exists($agent_initializer)) {
			return $agent_initializer::initialize($payload);
		}
		$plugins = get_option('active_plugins', []);
		if (is_array($plugins) && in_array('superdav-ai-agent/superdav-ai-agent.php', $plugins, true)) {
			return new \WP_Error('clone_agent_initializer_unavailable', __('The template AI plugin clone initializer is unavailable. Install its matching version before cloning.', 'ultimate-multisite'));
		}
		return true;
	}
}
