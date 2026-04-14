<?php
/**
 * Enable the manual gateway in Ultimate Multisite for e2e testing.
 *
 * Using a fixture file instead of inline wp eval to avoid shell-quoting
 * issues through the npx -> wp-env -> docker exec -> wp eval chain.
 */
wu_save_setting( 'active_gateways', array( 'manual' ) );

$active = wu_get_setting( 'active_gateways', array() );

if ( in_array( 'manual', (array) $active, true ) ) {
	echo 'gateway:manual';
} else {
	echo 'error:manual gateway not found in active_gateways';
	exit( 1 );
}
