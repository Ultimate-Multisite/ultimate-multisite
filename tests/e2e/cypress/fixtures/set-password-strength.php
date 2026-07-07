<?php
/**
 * Set the minimum password strength setting for e2e testing.
 *
 * Usage: wp eval-file set-password-strength.php -- <strength>
 * Where <strength> is one of: medium, strong, super_strong
 *
 * Outputs the new setting value as confirmation.
 */

$args = $GLOBALS['argv'] ?? [];

// The strength value is passed as a positional argument after '--'.
$strength = end($args);

$valid = ['medium', 'strong', 'super_strong'];

if (! in_array($strength, $valid, true)) {
	echo wp_json_encode([
		'error'   => 'Invalid strength value',
		'value'   => $strength,
		'allowed' => $valid,
	]);
	exit(1);
}

wu_save_setting('minimum_password_strength', $strength);

echo wp_json_encode([
	'success' => true,
	'setting' => wu_get_setting('minimum_password_strength'),
]);
