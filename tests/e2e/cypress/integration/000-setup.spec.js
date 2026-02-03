describe("Ultimate Multisite Setup", () => {
	before(() => {
		// Disable custom login page if a previous wizard run enabled it,
		// otherwise /wp-login.php redirects to /login/ and loginByForm breaks.
		cy.wpCli(
			'eval "if (function_exists(\'wu_save_setting\')) { wu_save_setting(\'enable_custom_login_page\', 0); }"',
			{ failOnNonZeroExit: false }
		);

		cy.loginByForm(
			Cypress.env("admin").username,
			Cypress.env("admin").password
		);
	});

	it("Should install Ultimate Multisite database tables and mark setup complete", () => {
		cy.wpCliFile("tests/e2e/cypress/fixtures/setup-tables.php").then(
			(result) => {
				expect(result.stdout).to.contain("installed");
			}
		);
	});

	it("Should create a test product/plan", () => {
		cy.wpCliFile("tests/e2e/cypress/fixtures/setup-product.php", {
			failOnNonZeroExit: false,
		}).then((result) => {
			const productId = result.stdout.trim();
			cy.log(`Created test product with ID: ${productId}`);
		});
	});

	it("Should create a checkout form and registration page", () => {
		cy.wpCliFile(
			"tests/e2e/cypress/fixtures/setup-checkout-form.php"
		).then((result) => {
			expect(result.stdout).to.contain("form:");
			expect(result.stdout).to.not.contain("error:");
		});
	});

	it("Should enable the manual gateway in Ultimate Multisite", () => {
		cy.wpCli(
			"eval \"wu_save_setting('active_gateways', ['manual']);\""
		);

		cy.wpCli(
			"eval \"echo json_encode(wu_get_setting('active_gateways', []));\""
		).then((result) => {
			expect(result.stdout).to.contain("manual");
		});
	});

	it("Should disable email verification and enable sync site publish", () => {
		cy.wpCli(
			"eval \"wu_save_setting('enable_email_verification', 'never'); wu_save_setting('force_publish_sites_sync', true);\""
		);

		cy.wpCli(
			"eval \"echo wu_get_setting('enable_email_verification', 'always');\""
		).then((result) => {
			expect(result.stdout).to.contain("never");
		});
	});
});
