/**
 * Stripe Subscription Renewal Flow
 *
 * Tests that subscription renewals work correctly using Stripe Test Clocks.
 * Creates a subscription server-side, advances the test clock past the
 * billing cycle, then processes the renewal and verifies the results.
 *
 * Requires STRIPE_TEST_PK_KEY and STRIPE_TEST_SK_KEY environment variables.
 */
describe("Stripe Subscription Renewal Flow", () => {
	const CONTAINER_FIXTURES =
		"/var/www/html/wp-content/plugins/ultimate-multisite/tests/e2e/cypress/fixtures";

	let skKey;
	let setupData = {};

	before(() => {
		const pkKey = Cypress.env("STRIPE_TEST_PK_KEY");
		skKey = Cypress.env("STRIPE_TEST_SK_KEY");

		if (!pkKey || !skKey) {
			throw new Error(
				"Skipping Stripe renewal tests: STRIPE_TEST_PK_KEY and STRIPE_TEST_SK_KEY env vars are required"
			);
		}

		// Ensure Stripe gateway is configured
		cy.exec(
			`pnpm exec wp-env run tests-cli wp eval-file ${CONTAINER_FIXTURES}/setup-stripe-gateway.php '${pkKey}' '${skKey}'`,
			{ timeout: 60000 }
		).then((result) => {
			const data = JSON.parse(result.stdout.trim());
			expect(data.success).to.equal(true);
		});
	});

	it(
		"Should create subscription with Test Clock",
		{ retries: 0 },
		() => {
			cy.exec(
				`pnpm exec wp-env run tests-cli wp eval-file ${CONTAINER_FIXTURES}/setup-stripe-renewal-test.php '${skKey}'`,
				{ timeout: 120000 }
			).then((result) => {
				const data = JSON.parse(result.stdout.trim());
				cy.log(`Setup result: ${JSON.stringify(data)}`);

				expect(data.success).to.equal(true);
				expect(data.test_clock_id).to.match(/^clock_/);
				expect(data.subscription_id).to.match(/^sub_/);
				expect(data.initial_times_billed).to.equal(1);

				// Verify current_period_end is roughly 1 month from now
				const nowSec = Math.floor(Date.now() / 1000);
				const periodEnd = data.current_period_end;
				const diffDays = (periodEnd - nowSec) / 86400;
				expect(diffDays).to.be.within(25, 35);

				// Store data for subsequent tests
				setupData = data;
			});
		}
	);

	it(
		"Should advance clock past renewal date",
		{ retries: 0 },
		() => {
			expect(setupData.test_clock_id).to.exist;
			expect(setupData.current_period_end).to.exist;

			// Advance to 1 day past the billing period end
			const targetTimestamp = setupData.current_period_end + 86400;

			cy.exec(
				`pnpm exec wp-env run tests-cli wp eval-file ${CONTAINER_FIXTURES}/advance-stripe-test-clock.php '${skKey}' '${setupData.test_clock_id}' '${targetTimestamp}'`,
				{ timeout: 180000 }
			).then((result) => {
				const data = JSON.parse(result.stdout.trim());
				cy.log(`Advance result: ${JSON.stringify(data)}`);

				expect(data.success).to.equal(true);
				expect(data.status).to.equal("ready");
			});
		}
	);

	it(
		"Should process renewal",
		{ retries: 0 },
		() => {
			expect(setupData.subscription_id).to.exist;
			expect(setupData.um_membership_id).to.exist;

			cy.exec(
				`pnpm exec wp-env run tests-cli wp eval-file ${CONTAINER_FIXTURES}/process-stripe-renewal.php '${skKey}' '${setupData.subscription_id}' '${setupData.um_membership_id}'`,
				{ timeout: 120000 }
			).then((result) => {
				const data = JSON.parse(result.stdout.trim());
				cy.log(`Renewal result: ${JSON.stringify(data)}`);

				expect(data.success).to.equal(true);
				expect(data.renewal_invoice_id).to.not.be.empty;
				expect(data.new_times_billed).to.equal(2);
				expect(data.membership_status).to.equal("active");

				// Verify the new expiration is roughly 1 month after the old one
				const newPeriodEnd = data.current_period_end;
				const oldPeriodEnd = setupData.current_period_end;
				const diffDays =
					(newPeriodEnd - oldPeriodEnd) / 86400;
				expect(diffDays).to.be.within(25, 35);
			});
		}
	);

	it("Should verify renewal state", () => {
		expect(setupData.um_membership_id).to.exist;

		cy.exec(
			`pnpm exec wp-env run tests-cli wp eval-file ${CONTAINER_FIXTURES}/verify-stripe-renewal-results.php '${setupData.um_membership_id}'`,
			{ timeout: 60000 }
		).then((result) => {
			const data = JSON.parse(result.stdout.trim());
			cy.log(`Verify result: ${JSON.stringify(data)}`);

			// Should have 2 payments total (initial + renewal)
			expect(data.payment_count).to.equal(2);

			// Initial payment
			expect(data.payments[0].status).to.equal("completed");
			expect(data.payments[0].gateway).to.equal("stripe");

			// Renewal payment
			expect(data.payments[1].status).to.equal("completed");
			expect(data.payments[1].gateway).to.equal("stripe");
			expect(data.payments[1].gateway_payment_id).to.not.be.empty;

			// Membership should be active with correct state
			expect(data.membership_status).to.equal("active");
			expect(data.times_billed).to.equal(2);
			expect(data.gateway).to.equal("stripe");
			expect(data.recurring).to.equal(true);
		});
	});

	after(() => {
		if (setupData.test_clock_id) {
			cy.exec(
				`pnpm exec wp-env run tests-cli wp eval-file ${CONTAINER_FIXTURES}/cleanup-stripe-test-clock.php '${skKey}' '${setupData.test_clock_id}'`,
				{ timeout: 60000, failOnNonZeroExit: false }
			).then((result) => {
				try {
					const data = JSON.parse(result.stdout.trim());
					cy.log(`Cleanup result: ${JSON.stringify(data)}`);
				} catch (e) {
					cy.log("Cleanup output: " + result.stdout);
				}
			});
		}
	});
});
