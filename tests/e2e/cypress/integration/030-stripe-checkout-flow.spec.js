describe("Stripe Gateway Checkout Flow", () => {
	const timestamp = Date.now();
	const customerData = {
		username: `stripecust${timestamp}`,
		email: `stripecust${timestamp}@test.com`,
		password: "xK9#mL2$vN5@qR",
	};
	const siteData = {
		title: "Stripe Test Site",
		path: `stripesite${timestamp}`,
	};

	before(() => {
		const pkKey = Cypress.env("STRIPE_TEST_PK_KEY");
		const skKey = Cypress.env("STRIPE_TEST_SK_KEY");

		if (!pkKey || !skKey) {
			throw new Error(
				"Skipping Stripe tests: STRIPE_TEST_PK_KEY and STRIPE_TEST_SK_KEY env vars are required"
			);
		}

		cy.loginByForm(
			Cypress.env("admin").username,
			Cypress.env("admin").password
		);

		// Enable Stripe gateway with test keys
		cy.exec(
			`npx wp-env run tests-cli wp eval-file /var/www/html/wp-content/plugins/ultimate-multisite/tests/e2e/cypress/fixtures/setup-stripe-gateway.php '${pkKey}' '${skKey}'`,
			{ timeout: 60000 }
		).then((result) => {
			const data = JSON.parse(result.stdout.trim());
			cy.log(`Stripe setup: ${JSON.stringify(data)}`);
			expect(data.success).to.equal(true);
		});
	});

	it("Should complete checkout with Stripe payment", {
		retries: 0,
	}, () => {
		cy.clearCookies();
		cy.visit("/register", { failOnStatusCode: false });

		// Wait for checkout form to render
		cy.get("#field-email_address", { timeout: 30000 }).should(
			"be.visible"
		);
		cy.wait(3000);

		// Select the plan (first non-trial plan)
		cy.get('#wrapper-field-pricing_table label[id^="wu-product-"]', {
			timeout: 15000,
		})
			.contains("Test Plan")
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

		// Select Stripe gateway. When Stripe is the only active gateway, the
		// checkout template renders it as a hidden input instead of a radio.
		cy.get('input[name="gateway"][value="stripe"]', { timeout: 10000 }).then(
			($gateway) => {
				if ($gateway.attr("type") === "radio") {
					cy.wrap($gateway).check({ force: true });
				} else {
					expect($gateway).to.have.attr("type", "hidden");
				}
			}
		);

		// Set billing address via Vue model (fields are hidden when Stripe is selected,
		// but values are still sent to server and passed to Stripe's confirmPayment)
		cy.window().then((win) => {
			if (win.wu_checkout_form) {
				win.wu_checkout_form.country = "US";
			}
		});
		// Also set the zip code DOM value for form serialization
		cy.get("body").then(($body) => {
			if ($body.find("#field-billing_zip_code").length > 0) {
				$body.find("#field-billing_zip_code").val("94105");
			} else if ($body.find('[name="billing_address[billing_zip_code]"]').length > 0) {
				$body.find('[name="billing_address[billing_zip_code]"]').val("94105");
			}
		});

		// Wait for Stripe Payment Element iframe to load
		cy.get("#payment-element iframe", { timeout: 30000 }).should("exist");
		cy.wait(2000); // Give Payment Element time to fully render

		// Fill Stripe card details inside the iframe
		cy.fillStripeCard();

		// Submit the checkout form
		cy.get(
			'#wrapper-field-checkout button[type="submit"], button.wu-checkout-submit, #field-checkout, button[type="submit"]',
			{ timeout: 10000 }
		)
			.filter(":visible")
			.last()
			.click();

		// Stripe adds network roundtrips, so use a longer timeout
		cy.url({ timeout: 90000 }).should("include", "status=done");
	});

	it("Should verify Stripe payment state via WP-CLI", () => {
		cy.wpCliFile(
			"tests/e2e/cypress/fixtures/verify-stripe-checkout-results.php"
		).then((result) => {
			const data = JSON.parse(result.stdout.trim());
			cy.log(`Stripe results: ${JSON.stringify(data)}`);

			// Payment should be completed with Stripe gateway
			expect(data.um_payment_status).to.equal("completed");
			expect(data.um_payment_gateway).to.equal("stripe");
			expect(data.um_payment_total).to.be.greaterThan(0);

			// Membership should be active
			expect(data.um_membership_status).to.equal("active");

			// Site should exist
			expect(data.um_site_type).to.equal("customer_owned");

			// Stripe IDs should be populated
			expect(data.gateway_payment_id).to.not.be.empty;
			expect(data.gateway_customer_id).to.not.be.empty;
		});
	});
});
