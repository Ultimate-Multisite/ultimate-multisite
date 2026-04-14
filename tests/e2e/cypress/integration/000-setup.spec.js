describe("Ultimate Multisite Setup", () => {
	before(() => {
		// Disable custom login page if a previous wizard run enabled it,
		// otherwise /wp-login.php redirects to /login/ and loginByForm breaks.
		// Uses failOnNonZeroExit: false because the plugin may not yet be
		// network-activated at this point.
		cy.wpCliFile(
			"tests/e2e/cypress/fixtures/setup-disable-custom-login.php",
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
		// Uses a fixture file to avoid shell-quoting issues through the
		// npx -> wp-env -> docker exec -> wp eval chain.
		cy.wpCliFile(
			"tests/e2e/cypress/fixtures/setup-gateway.php"
		).then((result) => {
			expect(result.stdout).to.contain("gateway:manual");
		});
	});

	it("Should disable email verification and enable sync site publish", () => {
		// Uses a fixture file to avoid shell-quoting issues through the
		// npx -> wp-env -> docker exec -> wp eval chain.
		cy.wpCliFile(
			"tests/e2e/cypress/fixtures/setup-email-settings.php"
		).then((result) => {
			expect(result.stdout).to.contain("email_verification:never");
		});
	});

	it("Should reset password strength to default", () => {
		// Uses a fixture file to avoid shell-quoting issues through the
		// npx -> wp-env -> docker exec -> wp eval chain.
		cy.wpCliFile(
			"tests/e2e/cypress/fixtures/setup-password-strength.php"
		).then((result) => {
			expect(result.stdout).to.contain("password_strength:strong");
		});
	});
});
