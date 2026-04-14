<?php
/**
 * Disable email verification and enable sync site publish for e2e testing.
 *
 * Using a fixture file instead of inline wp eval to avoid shell-quoting
 * issues through the npx -> wp-env -> docker exec -> wp eval chain.
 */
wu_save_setting( 'enable_email_verification', 'never' );
wu_save_setting( 'force_publish_sites_sync', true );

$email_verification = wu_get_setting( 'enable_email_verification', 'always' );

echo 'email_verification:' . esc_html( $email_verification );
