<?php
/*
Plugin Name: SMTP Config for Mailpit
Description: Route WordPress emails through local Mailpit SMTP server in wp-env multisite environment
Version: 1.0
Author: Test
*/

function TestConfigMailpit($phpmailer) {
	error_log('📬 PHPMailer init hook triggered');

	$phpmailer->isSMTP();
	$phpmailer->Host       = 'wp-multisite-waas-mailpit'; // Mailpit SMTP host
	$phpmailer->Port       = 1025;                        // Mailpit SMTP port
	$phpmailer->SMTPAuth   = false;                       // No auth for Mailpit by default
	$phpmailer->SMTPSecure = false;                       // No encryption for local SMTP
	$phpmailer->Username   = null;                        // Leave empty
	$phpmailer->Password   = null;                        // Leave empty

	// Optional: set the default From address and name for all outgoing emails
	$phpmailer->From     = 'test@example.local';
	$phpmailer->FromName = 'Test Site';

	// SMTP debug output disabled to prevent corruption of AJAX responses
	$phpmailer->SMTPDebug = 0;
}
add_action('phpmailer_init', 'TestConfigMailpit', 10, 1);
