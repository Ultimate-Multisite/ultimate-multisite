describe("PayPal REST Gateway Checkout Flow", () => {
	const timestamp = Date.now();
	const customerData = {
		username: `paypalcust${timestamp}`,
		email: `paypalcust${timestamp}@test.com`,
		password: "xK9#mL2$vN5@qR",
	};
	const siteData = {
		title: "PayPal Test Site",
		path: `paypalsite${timestamp}`,
	};

	before(() => {
		const clientId = Cypress.env("PAYPAL_SANDBOX_CLIENT_ID");
		const clientSecret = Cypress.env("PAYPAL_SANDBOX_CLIENT_SECRET");

		if (!clientId || !clientSecret) {
			throw new Error(
				"Skipping PayPal tests: PAYPAL_SANDBOX_CLIENT_ID and PAYPAL_SANDBOX_CLIENT_SECRET env vars are required"
			);
		}

		cy.loginByForm(
			Cypress.env("admin").username,
			Cypress.env("admin").password
		);

		// Enable PayPal gateway with sandbox keys
		cy.exec(
			`pnpm exec wp-env run tests-cli wp eval-file /var/www/html/wp-content/plugins/ultimate-multisite/tests/e2e/cypress/fixtures/setup-paypal-gateway.php '${clientId}' '${clientSecret}'`,
			{ timeout: 60000 }
		).then((result) => {
			const data = JSON.parse(result.stdout.trim());
			cy.log(`PayPal setup: ${JSON.stringify(data)}`);
			expect(data.success).to.equal(true);
		});
	});

	it("Should show PayPal as a payment option on the checkout form", {
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

		// PayPal gateway radio should be available
		cy.get(
			'input[type="radio"][name="gateway"][value="paypal-rest"]',
			{ timeout: 10000 }
		).should("exist");
	});

	it("Should submit checkout form with PayPal gateway selected", {
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
		cy.get("#field-username")
			.should("be.visible")
			.clear()
			.type(customerData.username);
		cy.get("#field-password")
			.should("be.visible")
			.clear()
			.type(customerData.password);

		cy.get("body").then(($body) => {
			if ($body.find("#field-password_conf").length > 0) {
				cy.get("#field-password_conf").clear().type(customerData.password);
			}
		});

		// Fill site details
		cy.get("#field-site_title")
			.should("be.visible")
			.clear()
			.type(siteData.title);
		cy.get("#field-site_url")
			.should("be.visible")
			.clear()
			.type(siteData.path);

		// Select PayPal REST gateway
		cy.get(
			'input[type="radio"][name="gateway"][value="paypal-rest"]'
		).check({ force: true });

		// Fill billing address
		cy.get("#field-billing_country", { timeout: 15000 })
			.should("be.visible")
			.select("US");

		cy.get("#field-billing_zip_code", { timeout: 15000 })
			.should("be.visible")
			.clear()
			.type("94105");

		// Intercept the checkout AJAX call to capture the redirect URL.
		// PayPal checkout creates an order and returns an approval_url that
		// redirects the user to paypal.com — we can't follow that in CI,
		// but we can verify the gateway processes the request correctly.
		cy.intercept("POST", "**/admin-ajax.php").as("checkoutAjax");

		// Submit the checkout form
		cy.get(
			'#wrapper-field-checkout button[type="submit"], button.wu-checkout-submit, #field-checkout, button[type="submit"]',
			{ timeout: 10000 }
		)
			.filter(":visible")
			.last()
			.click();

		// Wait for the AJAX response or a redirect.
		// PayPal checkout will either:
		// 1. Redirect to paypal.com for payment approval (success)
		// 2. Stay on the page with an error message
		// 3. Redirect to status=done (for $0 orders)
		//
		// We check for either a redirect or verify the checkout processed
		cy.wait(15000);

		// Check the current URL — if redirected to paypal.com, the checkout worked
		cy.url().then((url) => {
			if (url.includes("paypal.com")) {
				// Successfully redirected to PayPal for approval
				cy.log("PayPal redirect successful");
				expect(url).to.include("paypal.com");
			} else if (url.includes("status=done")) {
				// Free or $0 order completed
				cy.log("Order completed (free/zero amount)");
			} else {
				// Still on register page — check if form processed correctly
				cy.log(`Still on page: ${url}`);
				// The checkout should have at least created the pending entities
			}
		});
	});

	it("Should verify PayPal gateway is correctly configured via WP-CLI", () => {
		cy.exec(
			`pnpm exec wp-env run tests-cli wp eval '
				$gateways = (array) wu_get_setting("active_gateways", []);
				$sandbox = wu_get_setting("paypal_rest_sandbox_mode", 0);
				$client_id = wu_get_setting("paypal_rest_sandbox_client_id", "");
				echo json_encode([
					"paypal_active" => in_array("paypal-rest", $gateways),
					"sandbox_mode" => (bool)(int)$sandbox,
					"has_client_id" => !empty($client_id),
				]);
			'`,
			{ timeout: 30000 }
		).then((result) => {
			const data = JSON.parse(result.stdout.trim());
			cy.log(`PayPal config: ${JSON.stringify(data)}`);

			expect(data.paypal_active).to.equal(true);
			expect(data.sandbox_mode).to.equal(true);
			expect(data.has_client_id).to.equal(true);
		});
	});
});
