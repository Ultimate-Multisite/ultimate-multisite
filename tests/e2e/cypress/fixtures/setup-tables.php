<?php
/**
 * Install Ultimate Multisite database tables and mark setup as complete.
 */
$loader = WP_Ultimo\Loaders\Table_Loader::get_instance();
$loader->init();

if ( ! $loader->is_installed() ) {
	$installer = WP_Ultimo\Installers\Core_Installer::get_instance();
	$installer->_install_database_tables();
}

update_network_option(null, WP_Ultimo::NETWORK_OPTION_SETUP_FINISHED, time());

echo $loader->is_installed() ? 'installed' : 'failed';
