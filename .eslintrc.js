module.exports = {
	root: true,
	extends: ['plugin:@wordpress/eslint-plugin/recommended-with-formatting'],
	env: {
		browser: true,
		jquery: true,
	},
	globals: {
		// WordPress globals
		wp: 'readonly',
		ajaxurl: 'readonly',
		pagenow: 'readonly',
		typenow: 'readonly',
		adminpage: 'readonly',
		// Vue.js
		Vue: 'readonly',
		// Ultimate Multisite globals
		wu_moment: 'readonly',
		wu_activity_stream_nonce: 'readonly',
		wu_checkout: 'readonly',
		wu_checkout_form: 'readonly',
		wu_block_ui: 'readonly',
		wu_block_ui_legacy: 'readonly',
		wu_format_money: 'readonly',
		wu_template_previewer: 'readonly',
		wu_fields: 'readonly',
		wu_settings: 'readonly',
		wu_addons: 'readonly',
		wu_ajax_error: 'readonly',
		wu_color_field_ids: 'readonly',
		wu_coupon_data: 'readonly',
		wu_default_pricing_option: 'readonly',
		wu_initialize_editors: 'readonly',
		wu_paypal_setup_wizard: 'readonly',
		wu_visits_counter: 'readonly',
		accounting: 'readonly',
		wpu: 'readonly',
		wubox: 'readonly',
		wuboxL10n: 'readonly',
		settings_loader: 'writable',
		Swal: 'readonly',
		ClipboardJS: 'readonly',
		Shepherd: 'readonly',
		ApexCharts: 'readonly',
		tippy: 'readonly',
	},
	rules: {
		// Allow tabs for indentation (matches PHP coding standards)
		indent: ['error', 'tab', { SwitchCase: 1 }],
		// Disable prettier - too strict for legacy code
		'prettier/prettier': 'off',
		// Relax some rules for legacy code compatibility
		'no-unused-vars': ['warn', { vars: 'all', args: 'none', ignoreRestSiblings: true }],
		// Allow console for development
		'no-console': 'warn',
		// Allow var for legacy code
		'no-var': 'warn',
		// Prefer const but don't enforce strictly for legacy code
		'prefer-const': 'warn',
		// Object shorthand is nice but not required for legacy code
		'object-shorthand': 'warn',
		// Allow snake_case for WP compatibility
		camelcase: 'off',
		// Allow redeclaring globals (we define them above)
		'no-redeclare': 'off',
		// Disable strict formatting rules for legacy code
		'space-in-parens': 'off',
		'comma-dangle': 'off',
		quotes: 'off',
		semi: 'off',
		'padded-blocks': 'off',
		'eol-last': 'off',
		'space-before-function-paren': 'off',
		'space-before-blocks': 'off',
	},
	ignorePatterns: [
		'**/*.min.js',
		'node_modules/',
		'vendor/',
		'lib/',
		'assets/js/lib/',
	],
	overrides: [
		// Baseline legacy files narrowly so new files remain fully checked.
		{
			files: ['assets/js/flags.js'],
			rules: {
				'import/no-unresolved': 'off',
			},
		},
		{
			files: [
				'assets/js/command-palette.js',
				'assets/js/wu-password-reset.js',
			],
			rules: {
				'jsdoc/require-param-type': 'off',
			},
		},
		{
			files: [
				'assets/js/network-activate.js',
				'assets/js/wu-password-toggle.js',
			],
			rules: {
				'jsdoc/check-tag-names': 'off',
			},
		},
		{
			files: ['assets/js/settings-loader.js'],
			rules: {
				'jsdoc/require-returns-check': 'off',
				'jsdoc/require-returns-type': 'off',
			},
		},
		{
			files: [
				'assets/js/coupon-code.js',
				'assets/js/template-previewer.js',
				'assets/js/visits-counter.js',
				'assets/js/wubox.js',
			],
			rules: {
				eqeqeq: 'off',
				'no-mixed-operators': 'off',
				'no-mixed-spaces-and-tabs': 'off',
				'no-nested-ternary': 'off',
				'no-unused-expressions': 'off',
			},
		},
		{
			files: [
				'assets/js/wu-password-reset.js',
				'assets/js/wu-password-toggle.js',
				'assets/js/wubox.js',
			],
			rules: {
				'@wordpress/no-unused-vars-before-return': 'off',
			},
		},
	],
};
