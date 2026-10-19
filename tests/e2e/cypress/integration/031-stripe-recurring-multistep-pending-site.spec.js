/* global before, cy, Cypress, describe, expect, it */

const shellQuote = (value) => `'${ String(value).replace(/'/g, "'\"'\"'") }'`;

const runWpCliFixture = (relativePath, args = []) => {
	const localWpPath = Cypress.env("WP_CLI_PATH");
	const wpCommand = localWpPath
		? `wp --path=${ shellQuote(localWpPath) }`
		: "pnpm exec wp-env run tests-cli wp";
	const fixturePath = localWpPath
		? `${ Cypress.config("projectRoot") }/${ relativePath }`
		: `/var/www/html/wp-content/plugins/ultimate-multisite/${ relativePath }`;
	const cliArgs = args.map(shellQuote).join(" ");

	return cy.exec(
		`${ wpCommand } eval-file ${ shellQuote(fixturePath) }${ cliArgs ? ` ${ cliArgs }` : "" }`,
		{
			log: false,
			timeout: 60000,
		}
	);
};

describe("Stripe Recurring Multi-step Pending Site", () => {
	const setupFixture =
		"tests/e2e/cypress/fixtures/setup-stripe-recurring-multistep.php";
	const verifyFixture =
		"tests/e2e/cypress/fixtures/verify-stripe-recurring-multistep-results.php";
	const timestamp = Date.now();
	const customerData = {
		username: `stripe1968${ timestamp }`,
		email: `stripe1968${ timestamp }@test.com`,
		password: "Issue1968Test!42",
	};
	const siteData = {
		title: "Issue 1968 Stripe Site",
		path: `stripe1968site${ timestamp }`,
	};
	const verifyCheckout = (attemptsRemaining = 12) =>
		runWpCliFixture(verifyFixture, [ customerData.username, siteData.path ]).then(
			(result) => {
				const data = JSON.parse(result.stdout.trim());

				if ( ! data.success && attemptsRemaining > 1) {
					cy.wait(5000);
					return verifyCheckout(attemptsRemaining - 1);
				}

				return data;
			}
		);

	before(() => {
		const pkKey = Cypress.env("STRIPE_TEST_PK_KEY");
		const skKey = Cypress.env("STRIPE_TEST_SK_KEY");
		const setupArgs = pkKey && skKey ? [ pkKey, skKey ] : [];

		return runWpCliFixture(setupFixture, setupArgs).then((result) => {
			const data = JSON.parse(result.stdout.trim());

			expect(data.success, data.error || "Stripe setup failed").to.equal(true);
		});
	});

	it("publishes the pending site after a paid recurring multi-step checkout", () => {
		cy.clearCookies();
		cy.visit("/issue-1968-register/", { failOnStatusCode: false });

		cy.get('#wrapper-field-pricing_table label[id^="wu-product-"]', {
			timeout: 30000,
		})
			.contains("Issue 1968 Recurring Plan")
			.click();

		cy.get("#field-site_title", { timeout: 30000 })
			.should("be.visible")
			.clear()
			.type(siteData.title);
		cy.get("#field-site_url").should("be.visible").clear().type(siteData.path);
		cy.contains("button:visible", "Next Step").click();

		cy.get("#field-email_address", { timeout: 30000 })
			.should("be.visible")
			.clear()
			.type(customerData.email);
		cy.get("#field-username").should("be.visible").clear().type(customerData.username);
		cy.get("#field-password").should("be.visible").clear().type(customerData.password);
		cy.get("body").then(($body) => {
			if ($body.find("#field-password_conf:visible").length > 0) {
				cy.get("#field-password_conf").clear().type(customerData.password);
			}
		});
		cy.contains("button:visible", "Next Step").click();

		cy.get('input[name="gateway"][value="stripe"]', { timeout: 30000 }).then(
			($gateway) => {
				if ($gateway.attr("type") === "radio") {
					cy.wrap($gateway).check({ force: true });
				}
			}
		);

		cy.window().then((win) => {
			if (win.wu_checkout_form) {
				win.wu_checkout_form.country = "US";
			}
		});
		cy.get("body").then(($body) => {
			const zip = $body.find(
				'#field-billing_zip_code, [name="billing_address[billing_zip_code]"]'
			);
			if (zip.length > 0) {
				cy.wrap(zip.first()).clear({ force: true }).type("94105", { force: true });
			}
		});

		cy.get("#payment-element iframe", { timeout: 30000 }).should("exist");
		cy.fillStripeCard();

		cy.get('button[type="submit"]', { timeout: 10000 })
			.filter(":visible")
			.last()
			.click();

		cy.url({ timeout: 90000 }).should("include", "status=done");

		verifyCheckout().then((data) => {
			expect(data.success).to.equal(true);
			expect(data.user_found).to.equal(true);
			expect(data.customer_found).to.equal(true);
			expect(data.membership_found).to.equal(true);
			expect(data.membership_status).to.equal("active");
			expect(data.membership_gateway).to.equal("stripe");
			expect(data.membership_recurring).to.equal(true);
			expect(data.gateway_customer_id).to.not.equal("");
			expect(data.gateway_subscription_id).to.not.equal("");
			expect(data.pending_site_exists).to.equal(false);
			expect(data.payment_found).to.equal(true);
			expect(data.payment_status).to.equal("completed");
			expect(data.payment_gateway).to.equal("stripe");
			expect(data.gateway_payment_id).to.not.equal("");
			expect(data.site_created).to.equal(true);
			expect(data.site_path).to.equal(`/${ siteData.path }/`);
			expect(data.site_type).to.equal("customer_owned");
			expect(data.progress_pipeline).to.equal("site");
			expect(data.progress_state).to.equal("completed");
			expect(data.progress_step_count).to.equal(6);
		});
	});
});
