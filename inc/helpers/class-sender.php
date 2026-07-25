<?php
/**
 * Handles the action o send a admin notice to a sub-site or a email.
 *
 * @package WP_Ultimo
 * @subpackage Helper
 * @since 2.0.0
 */

namespace WP_Ultimo\Helpers;

use Psr\Log\LogLevel;
use WP_Ultimo\Models\Email_Template;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Handles hashing to encode ids and prevent spoofing due to auto-increments.
 *
 * @since 2.0.0
 */
class Sender {

	/**
	 * Parse attributes against the defaults.
	 *
	 * @since 2.0.0
	 *
	 * @param array $args The args passed.
	 * @return array
	 */
	public static function parse_args($args = []) {

		$default_args = [
			'from'        => [
				'name'  => wu_get_setting('from_name', ''),
				'email' => wu_get_setting('from_email', ''),
			],
			'content'     => '',
			'subject'     => '',
			'bcc'         => [],
			'payload'     => [],
			'attachments' => [],
			'style'       => wu_get_setting('email_template_type', 'html'),
		];

		$args = wp_parse_args($args, $default_args);

		return $args;
	}

	/**
	 * Send an email to one or more users.
	 *
	 * @since 2.0.0
	 *
	 * @param array  $from From whom will be send this mail.
	 * @param string $to   To who this email is.
	 * @param array  $args With content, subject and other arguments, has shortcodes, mail type.
	 * @return array With the send response.
	 */
	public static function send_mail($from = [], $to = [], $args = []) {

		if ( ! $from) {
			$from = [
				'email' => wu_get_setting('from_email', ''),
				'name'  => wu_get_setting('from_name', ''),
			];
		}

		$args = self::parse_args($args);

		/*
		 * First, replace shortcodes.
		 */
		$payload = wu_get_isset($args, 'payload', []);

		$subject = self::process_shortcodes(wu_get_isset($args, 'subject', ''), $payload);

		$content = self::process_shortcodes(wu_get_isset($args, 'content', ''), $payload);

		/*
		 * Content type and template
		 */
		$headers = [];

		if (wu_get_isset($args, 'style', 'html') === 'html') {
			$headers[] = 'Content-Type: text/html; charset=UTF-8';

			$default_settings = \WP_Ultimo\Admin_Pages\Email_Template_Customize_Admin_Page::get_default_settings();

			$template_settings = wu_get_option('email_template', $default_settings);

			$template_settings = wp_parse_args($template_settings, $default_settings);

			$template = wu_get_template_contents(
				'broadcast/emails/base',
				[
					'site_name'         => get_network_option(null, 'site_name'),
					'site_url'          => get_site_url(wu_get_main_site_id()),
					'logo_url'          => wu_get_network_logo(),
					'is_editor'         => false,
					'subject'           => $subject,
					'content'           => $content,
					'template_settings' => $template_settings,
				]
			);
		} else {
			$headers[] = 'Content-Type: text/html; charset=UTF-8';

			$template = nl2br(strip_tags($content, '<p><a><br>')); // by default, set the plain email content.

		}

		$bcc = '';

		/*
		 * Build From early — needed both for the From: header and as a safe, real
		 * fallback for the visible To: header in the BCC strategy below (avoids
		 * passing an invalid address such as 'undisclosed-recipients:;' through
		 * wp_mail/PHPMailer address validation).
		 */
		$from_email  = wu_get_isset($from, 'email', wu_get_setting('from_email'));
		$from_name   = wu_get_isset($from, 'name');
		$from_string = wu_format_email_string($from_email, $from_name);

		/*
		 * Build the recipients list.
		 */
		if (count($to) > 1) {
			$to = array_map(fn($item) => wu_format_email_string(wu_get_isset($item, 'email'), wu_get_isset($item, 'name')), $to);

			/*
			 * Decide which strategy to use, BCC or multiple "to"s.
			 *
			 * Default strategy is BCC: all recipients are placed in the Bcc header so
			 * no individual address is exposed in the To: field (GDPR/privacy
			 * compliance — see #1195).
			 *
			 * The visible To: header is set to the sender's own From address. This is
			 * the conventional pattern for BCC broadcasts (the sender mails themselves
			 * with everyone else BCC'd) and, unlike the RFC 2822 §3.6.3 group syntax
			 * "undisclosed-recipients:;", it passes wp_mail / PHPMailer address
			 * validation (FILTER_VALIDATE_EMAIL) and the PHP mail() / SMTP envelope
			 * checks on every common host configuration. Using the group syntax made
			 * WordPress silently drop the To: recipient, which on some SMTP plugins
			 * triggered a fall-back to PHP mail() and a fatal on hosts where mail()
			 * is in disable_functions.
			 *
			 * Depending on the SMTP solution being used, BCC vs individual sends can
			 * make a difference in the number of outbound connections made.
			 */
			if (apply_filters('wu_sender_recipients_strategy', 'bcc') === 'bcc') {
				// Place every recipient in BCC — do not expose $to[0] in the visible To: header.
				$bcc_array = array_map(
					function ($item) {

						$email = is_array($item) ? $item['email'] : $item;

						preg_match('/<([^>]+)>/', $email, $matches);

						return ! empty($matches[1]) ? $matches[1] : $email;
					},
					$to
				);

				$bcc_array = array_filter($bcc_array);

				/*
				 * Defensive guard: if every recipient was filtered out, abort instead
				 * of calling wp_mail() with no valid recipients.
				 */
				if (empty($bcc_array)) {
					wu_log_add('mailer', __('No valid recipients after filtering BCC list; aborting send.', 'ultimate-multisite'), LogLevel::WARNING);

					return false;
				}

				$bcc = implode(', ', $bcc_array);

				$headers[] = "Bcc: $bcc";

				/*
				 * Use the From address (or admin_email as a last resort) as the
				 * visible To: header — RFC-compliant, GDPR-safe (no recipient
				 * exposed) and validates as a real email so PHPMailer/SMTP plugins
				 * accept it.
				 */
				$to = $from_string ? $from_string : get_option('admin_email');
			}
		} else {
			$to = [
				wu_format_email_string(wu_get_isset($to[0], 'email'), wu_get_isset($to[0], 'name')),
			];
		}

		$headers[] = "From: {$from_string}";

		$attachments = $args['attachments'];

		/*
		 * Send the actual email.
		 *
		 * Wrap the wp_mail() call so exceptions thrown by third-party SMTP plugins
		 * (e.g. WP Mail SMTP, FluentSMTP, Easy WP SMTP) inside phpmailer_init or
		 * wp_mail hooks — or PHPMailer fatals such as a missing native mail()
		 * function on hosts that put it in disable_functions — do not abort the
		 * surrounding event/action pipeline. A delivery failure must be logged,
		 * not allowed to fatal an unrelated WP_Hook callback chain that may have
		 * pending follow-up work (membership status updates, site provisioning,
		 * webhook fan-out, etc.).
		 */
		try {
			$sent = wp_mail($to, $subject, $template, $headers, $attachments);
		} catch (\Throwable $e) {
			wu_log_add(
				'mailer',
				// translators: %s is the exception class and message reported by wp_mail or an SMTP plugin.
				sprintf(__('wp_mail() threw %1$s: %2$s', 'ultimate-multisite'), get_class($e), $e->getMessage()),
				LogLevel::ERROR
			);

			return false;
		}

		return $sent;
	}

	/**
	 * Change the shortcodes for values in the content.
	 *
	 * @since 2.0.0
	 *
	 * @param string $content   Content to be rendered.
	 * @param array  $payload   Payload with the values to render in the content.
	 * @return string
	 */
	public static function process_shortcodes($content, $payload = []) {

		if (empty($payload)) {
			return $content;
		}

		/*
		 * Allow templates to include a block only when the matching payload
		 * value is truthy. This keeps a single transactional email template
		 * useful for related outcomes, such as paid checkouts and free trials.
		 */
		$content = preg_replace_callback(
			'/{{#([a-zA-Z0-9_]+)}}(.*?){{\/\1}}/s',
			function ($matches) use ($payload) {
				return ! empty($payload[ $matches[1] ]) ? $matches[2] : '';
			},
			$content
		);

		$match = [];

		preg_match_all('/{{(.*?)}}/', $content, $match);

		$shortcodes = shortcode_atts(array_flip($match[1]), $payload);

		foreach ($shortcodes as $shortcode_key => $shortcode_value) {
			$shortcode_str = '{{' . $shortcode_key . '}}';

			$content = str_replace($shortcode_str, nl2br((string) $shortcode_value), $content);
		}

		return $content;
	}
}
