<?php
// phpcs:disable
// encrypt-secrets.php
$filename = 'inc/class-addon-repository.php';
$key      = hash_file('sha256', $filename);

$client_id     = getenv('MU_CLIENT_ID') ?: '';
$client_secret = getenv('MU_CLIENT_SECRET') ?: '';

if (!$client_id || !$client_secret) {
	fwrite(STDERR, "Missing MU_CLIENT_ID or MU_CLIENT_SECRET env vars\n");
	if (!file_exists('inc/stuff.php')) {
		file_put_contents('inc/stuff.php', "<?php\nreturn " . var_export(['', ''], true) . ';');
	}
	exit(0);
}

function encryptValue($plaintext, $key) {
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($plaintext, 'aes-256-cbc', $key, 0, $iv);
    return base64_encode($iv . $encrypted);
}

$target_file = 'inc/stuff.php';

if (!file_exists($target_file) || filemtime($filename) > filemtime($target_file)) {
	file_put_contents($target_file, "<?php\nreturn ".var_export([
		encryptValue($client_id, $key),
		encryptValue($client_secret, $key),
	], true).';');
	echo "Updated $target_file\n";
} else {
	echo "$target_file is up to date\n";
}
