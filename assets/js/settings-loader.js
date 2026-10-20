settings_loader = wu_block_ui('#wp-ultimo-wizard-body');

/**
 * Remove the block ui after the settings loaded.
 *
 * @since 2.0.0
 */
window.remove_block_ui = function() {

	settings_loader.unblock();

}
