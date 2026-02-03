describe("Free Trial Checkout Flow", () => {
	const timestamp = Date.now();
	const customerData = {
		username: `trialcust${timestamp}`,
		email: `trialcust${timestamp}@test.com`,
		password: "TestPassword123!",
	};
	const siteData = {
		title: "Trial Test Site",
		path: `trialsite${timestamp}`,
	};

	before(() => {
		cy.loginByForm(
			Cypress.env("admin").username,
			Cypress.env("admin").password
		);

		// Enable trial without payment method
		cy.wpCli(
			"eval \"wu_save_setting('allow_trial_without_payment_method', true);\""
		);

		// Create the trial product
		cy.wpCliFile("tests/e2e/cypress/fixtures/setup-trial-product.php", {
			failOnNonZeroExit: false,
		}).then((result) => {
			const productId = result.stdout.trim();
			cy.log(`Created trial product with ID: ${productId}`);
		});
	});

	it("Should complete free trial checkout without payment", {
		retries: 0,
	}, () => {
		cy.clearCookies();
		cy.visit("/register", { failOnStatusCode: false });

		// Wait for checkout form to render
		cy.get("#field-email_address", { timeout: 30000 }).should(
			"be.visible"
		);
		cy.wait(3000);

		// Select the trial plan by name
		cy.get('#wrapper-field-pricing_table label[id^="wu-product-"]', {
			timeout: 15000,
		})
			.contains("Trial Plan")
			.click();

		cy.wait(3000);

		// Fill account details
		cy.get("#field-email_address").clear().type(customerData.email);
		cy.get("#field-username").should("be.visible").clear().type(customerData.username);
		cy.get("#field-password").should("be.visible").clear().type(customerData.password);

		cy.get("body").then(($body) => {
			if ($body.find("#field-password_conf").length > 0) {
				cy.get("#field-password_conf").clear().type(customerData.password);
			}
		});

		// Fill site details
		cy.get("#field-site_title").should("be.visible").clear().type(siteData.title);
		cy.get("#field-site_url").should("be.visible").clear().type(siteData.path);

		// No gateway or billing fields should be required for free trial

		// Submit the checkout form
		cy.get(
			'#wrapper-field-checkout button[type="submit"], button.wu-checkout-submit, #field-checkout, button[type="submit"]',
			{ timeout: 10000 }
		)
			.filter(":visible")
			.last()
			.click();

		// Should redirect to status=done without any payment page
		cy.url({ timeout: 60000 }).should("include", "status=done");
	});

	it("Should verify trial membership state via WP-CLI", () => {
		cy.wpCliFile(
			"tests/e2e/cypress/fixtures/verify-trial-results.php"
		).then((result) => {
			const data = JSON.parse(result.stdout.trim());
			cy.log(`Trial results: ${JSON.stringify(data)}`);

			expect(data.um_membership_status).to.equal("trialing");
			expect(data.um_membership_trial_end).to.not.equal("");
			expect(data.um_payment_status).to.equal("completed");
			expect(data.um_site_type).to.equal("customer_owned");
		});
	});
});
