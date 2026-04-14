<?php
/**
 * Set password strength to 'strong' for e2e testing.
 *
 * Using a fixture file instead of inline wp eval to avoid shell-quoting
 * issues through the npx -> wp-env -> docker exec -> wp eval chain.
 */
wu_save_setting( 'password_strength', 'strong' );

$strength = wu_get_setting( 'password_strength', 'none' );

echo 'password_strength:' . esc_html( $strength );
