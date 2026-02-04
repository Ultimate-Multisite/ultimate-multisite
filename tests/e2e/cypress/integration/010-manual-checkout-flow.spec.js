describe("Manual Gateway Checkout Flow", () => {
	const timestamp = Date.now();
	const customerData = {
		username: `manualcust${timestamp}`,
		email: `manualcust${timestamp}@test.com`,
		password: "TestPassword123!",
	};
	const siteData = {
		title: "Manual Test Site",
		path: `manualsite${timestamp}`,
	};

	it("Should complete the UM checkout form with manual gateway", {
		retries: 0,
	}, () => {
		cy.clearCookies();
		cy.visit("/register", { failOnStatusCode: false });

		// Wait for checkout form to render
		cy.get("#field-email_address", { timeout: 30000 }).should(
			"be.visible"
		);
		cy.wait(3000);

		// Select the plan
		cy.get('#wrapper-field-pricing_table label[id^="wu-product-"]', {
			timeout: 15000,
		})
			.first()
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

		// Select manual gateway if visible radio
		cy.get("body").then(($body) => {
			const radioSelector = 'input[type="radio"][name="gateway"][value="manual"]';
			if ($body.find(radioSelector).length > 0) {
				cy.get(radioSelector).check({ force: true });
			}
		});

		// Fill billing address if present
		cy.get("body").then(($body) => {
			if ($body.find("#field-billing_country").length > 0) {
				cy.get("#field-billing_country").select("US");
			} else if ($body.find('[name="billing_address[billing_country]"]').length > 0) {
				cy.get('[name="billing_address[billing_country]"]').select("US");
			}

			if ($body.find("#field-billing_zip_code").length > 0) {
				cy.get("#field-billing_zip_code").clear().type("94105");
			} else if ($body.find('[name="billing_address[billing_zip_code]"]').length > 0) {
				cy.get('[name="billing_address[billing_zip_code]"]').clear().type("94105");
			}
		});

		// Submit the UM checkout form
		cy.get(
			'#wrapper-field-checkout button[type="submit"], button.wu-checkout-submit, #field-checkout, button[type="submit"]',
			{ timeout: 10000 }
		)
			.filter(":visible")
			.last()
			.click();

		// Should redirect to status=done
		cy.url({ timeout: 60000 }).should("include", "status=done");
	});

	it("Should verify checkout state via WP-CLI", () => {
		cy.wpCliFile("tests/e2e/cypress/fixtures/verify-manual-checkout-results.php").then(
			(result) => {
				const data = JSON.parse(result.stdout.trim());
				cy.log(`Results: ${JSON.stringify(data)}`);

				// Manual gateway: payment should be pending
				expect(data.um_payment_status).to.equal("pending");
				expect(data.um_payment_gateway).to.equal("manual");
				expect(data.um_membership_status).to.equal("pending");
			}
		);
	});
});
