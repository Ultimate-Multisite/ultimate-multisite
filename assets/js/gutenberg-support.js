/* eslint-disable no-multi-str */
/* global wu_gutenberg */
(function($) {

	$(document).ready(function() {

		/**
		 * Filter that receives the content from the preview markup and make modifications to it.
		 *
		 * @param {*} content
		 */
		const custom_gutenberg_preview_message = function(content) {

			content = content.replace(wp.i18n.__('Generating preview…'), wu_gutenberg.replacement_message);

			const img = '<img class="wu-logo" src="' + wu_gutenberg.logo + '"><p>';

			content = content.replace('<p>', img);

			content += '<style> \
        svg { \
          display: none !important; \
        } \
        img.wu-logo { \
          opacity: 0; \
          animation: fade-in-right ease 1s forwards; \
          max-width: 100px; \
          height: auto; \
          padding: 20px; \
        } \
        @keyframes fade-in-right { \
          from { \
            opacity: 0; \
            transform: translateY(-15px); \
          } \
          to { \
            opacity: 1; \
            transform: translateY(0); \
          } \
        } \
      </style>';

			return content;

		};

		/**
		 * Check if the hooks are set to avoid bugs and breaking other scripts.
		 */
		if (typeof wp === 'object' && typeof wp.hooks === 'object') {

			/**
			 * Pass as a wp hook
			 */
			wp.hooks.addFilter('editor.PostPreview.interstitialMarkup', 'wp-ultimo/custom-preview-message', custom_gutenberg_preview_message);

		} // end if;

	});

}(jQuery));
