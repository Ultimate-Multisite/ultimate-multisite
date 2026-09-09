describe("Manual Gateway Checkout Flow", () => {
	const timestamp = Date.now();
	const customerData = {
		username: `manualcust${timestamp}`,
		email: `manualcust${timestamp}@test.com`,
		password: "xK9#mL2$vN5@qR",
	};
	const siteData = {
		title: "Manual Test Site",
		path: `manualsite${timestamp}`,
	};

	before(() => {
		// The setup wizard/regression specs run before checkout specs in CI and can
		// rewrite gateway settings. Re-enable manual immediately before this flow.
		cy.wpCliFile("tests/e2e/cypress/fixtures/setup-gateway.php").then((result) => {
			expect(result.stdout).to.contain("gateway:manual");
		});
	});

	it("Preserves plan focus after checkout refreshes", () => {
		const planSelector = '#wrapper-field-pricing_table input[type="radio"][name="products[]"]';

		cy.intercept("POST", "**/admin-ajax.php*", (req) => {
			if (req.body.action === "wu_create_order" || req.url.includes("action=wu_create_order")) {
				req.alias = "createOrder";
				req.continue((response) => {
					response.setDelay(500);
				});
			}
		});

		cy.clearCookies();
		cy.visit("/register", { failOnStatusCode: false });
		cy.get("#field-email_address", { timeout: 30000 }).should("be.visible");
		cy.wait("@createOrder");

		cy.get(planSelector).should("have.length.at.least", 2).then(($plans) => {
			const unselectedPlan = Array.from($plans).find((plan) => ! plan.checked);

			expect(unselectedPlan).to.exist;
			cy.wrap(unselectedPlan).focus().type(" ");
			cy.wait("@createOrder");
			cy.get(`${planSelector}[value="${unselectedPlan.value}"]`).should("be.focused");
		});

		cy.get(`${planSelector}:checked`).type("{rightarrow}");
		cy.wait("@createOrder");
		cy.get(`${planSelector}:checked`).should("be.focused");

		cy.get(planSelector).then(($plans) => {
			const unselectedPlan = Array.from($plans).find((plan) => ! plan.checked);

			expect(unselectedPlan).to.exist;
			cy.wrap(unselectedPlan).click();
			cy.wait("@createOrder");
			cy.get(`${planSelector}[value="${unselectedPlan.value}"]`).should("be.focused");
		});

		cy.get(planSelector).then(($plans) => {
			const unselectedPlan = Array.from($plans).find((plan) => ! plan.checked);

			expect(unselectedPlan).to.exist;
			cy.wrap(unselectedPlan).click();
			cy.get("#field-email_address").focus().type("focus").should("be.focused");
			cy.wait("@createOrder");
			cy.get("#field-email_address").should("be.focused");
		});
	});

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

		// Select the plan by name. Do NOT use `.first()` — after the Setup
		// Wizard runs (wizard.spec.js), the wizard creates Free/Premium/SEO
		// products with list_order=0, which sort ahead of `Test Plan`
		// (default list_order=10). `.first()` would pick "Free" (no payment
		// required → manual gateway radio absent) or "Premium" (recurring →
		// rejected because Manual_Gateway::supports_recurring() returns false).
		// Either path means the form never reaches status=done. Selecting by
		// name pins to the one-time-payment plan that 000-setup created.
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

		// Select manual gateway if visible radio
		cy.get("body").then(($body) => {
			const radioSelector = 'input[type="radio"][name="gateway"][value="manual"]';
			if ($body.find(radioSelector).length > 0) {
				cy.get(radioSelector).check({ force: true });
			}
		});

		// Fill billing address — fields are controlled by v-show on order.should_collect_payment
		// After plan selection, create_order AJAX fires and fields become visible
		cy.get("#field-billing_country", { timeout: 15000 })
			.should("be.visible")
			.select("US");

		cy.get("#field-billing_zip_code", { timeout: 15000 })
			.should("be.visible")
			.clear()
			.type("94105");

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
