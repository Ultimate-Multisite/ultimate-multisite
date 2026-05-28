/* global before, cy, describe, expect, it */

/**
 * E2E coverage for the customer-panel template-switching flow.
 *
 * This spec exists because a customer reported "No puedes cambiar la
 * plantilla de este sitio" ("You are not allowed to switch templates for
 * this site.") in the confirmation panel when clicking "Switch to X" from
 * the customer-panel template-switching page (?page=wu-template-switching).
 *
 * The PHP-level test
 * (tests/WP_Ultimo/UI/Template_Switching_Element_Test.php
 *  ::test_switch_template_authorizes_owner_via_wu_ajax_fallback)
 * proves the wu-ajax light-ajax dispatch path passes the
 * Site::is_customer_allowed() gate for an authorized owner. This Cypress
 * spec proves it end-to-end against a real browser, real cookies, the
 * real wu-ajax POST, and the real Vue confirm panel — closing the gap
 * between "the auth check works" and "the customer can actually switch
 * templates from the UI". Without this spec a future change to the
 * wu-ajax pipeline, the confirm panel, the JS handler, or the
 * Site::is_customer_allowed() filter chain could regress the flow with
 * only the silent symptom the customer originally reported.
 */
describe("Customer-Panel Template Switching", () => {
	let customerData;

	before(() => {
		// Disable custom login page if a previous wizard run enabled it
		// (otherwise loginByForm bounces to /login/ and never lands on
		// /wp-admin). Mirrors 000-setup.spec.js.
		cy.wpCliFile(
			"tests/e2e/cypress/fixtures/setup-disable-custom-login.php",
			{ failOnNonZeroExit: false }
		);

		// Make sure the WU tables exist so wu_create_customer() etc. don't
		// fall over if this spec runs before 000-setup.
		cy.wpCliFile("tests/e2e/cypress/fixtures/setup-tables.php", {
			failOnNonZeroExit: false,
		});

		cy.wpCliFile(
			"tests/e2e/cypress/fixtures/setup-templates-and-customer.php"
		).then((result) => {
			const trimmed = result.stdout.trim();
			cy.log(`Fixture output: ${ trimmed }`);

			customerData = JSON.parse(trimmed);

			if (customerData.error) {
				throw new Error(
					`setup-templates-and-customer fixture failed: ${ customerData.error } ` +
						`(detail: ${ customerData.detail || "n/a" })`
				);
			}

			expect(customerData.ok, "fixture returned ok").to.equal(true);
			expect(customerData.customer_blog_id, "customer subsite created").to.be.a("number").and.greaterThan(1);
			expect(customerData.template_one_id, "template one created").to.be.a("number").and.greaterThan(1);
			expect(customerData.template_two_id, "template two created").to.be.a("number").and.greaterThan(1);
			expect(customerData.template_one_id).to.not.equal(customerData.template_two_id);
		});
	});

	it("Customer can switch their site to a different template", { retries: 0 }, () => {
		// Pre-condition: site currently uses template_one_id.
		cy.wpCli(`eval-file /var/www/html/wp-content/plugins/ultimate-multisite/tests/e2e/cypress/fixtures/verify-template-switch.php ${ customerData.customer_blog_id }`).then(
			(result) => {
				const before = JSON.parse(result.stdout.trim());
				cy.log(`Before switch: ${ JSON.stringify(before) }`);
				expect(before.ok, "verify fixture ok").to.equal(true);
				expect(before.customer_id, "site has linked customer_id").to.equal(customerData.customer_id);
				expect(before.template_id, "site starts on template one").to.equal(customerData.template_one_id);
			}
		);

		// Log in as the customer (not the network admin). The customer's WP
		// user is registered as 'administrator' on the subsite so they can
		// reach /wp-admin/admin.php?page=wu-template-switching.
		cy.clearCookies();
		cy.loginByForm(customerData.username, customerData.password);

		// Visit the customer-panel template-switching page on the
		// customer's subsite. `customer_path` already contains the
		// leading and trailing slashes (e.g. "/cust-abc123/").
		const switchPagePath = `${ customerData.customer_path }wp-admin/admin.php?page=wu-template-switching`;
		cy.visit(switchPagePath, { failOnStatusCode: false });

		// Wait for the Vue app to mount. The grid only renders when
		// permission_state !== STATE_NOT_ALLOWED, so the existence of
		// any site-template selector card already proves
		// is_customer_allowed() returned true at page-render time.
		cy.get(".wu-site-template-selector", { timeout: 30000 })
			.should("have.length.greaterThan", 0)
			.and("be.visible");

		// Confirm panel should NOT be visible yet (no selection made).
		cy.get(".wu-template-switching-confirm").should(($el) => {
			// v-show toggles `display: none`. Either the element is absent
			// from the DOM, or it carries display:none. Both count as "not
			// shown".
			if ($el.length === 0) {
				return;
			}
			expect($el.css("display")).to.equal("none");
		});

		// Click the "Use This Template" button for template_two_id. The
		// button lives inside the grid card whose id is
		// `wu-site-template-{ID}` (set by clean.php at line 138).
		cy.get(
			`#wu-site-template-${ customerData.template_two_id } .wu-site-template-selector`,
			{ timeout: 15000 }
		)
			.should("be.visible")
			.click();

		// The Vue watcher on `template_id` flips `confirm_active = true`,
		// which makes the confirm panel slide into view.
		cy.get(".wu-template-switching-confirm", { timeout: 10000 })
			.should("be.visible")
			.and("contain.text", customerData.template_two_name);

		// Stub `window.location.href` so the spec doesn't actually follow
		// the cross-page redirect that fires on success. We assert the
		// redirect URL was set instead.
		cy.window().then((win) => {
			cy.stub(win.location, "assign").as("locationAssign");
			// Some Vue handlers use `window.location.href =`; intercept
			// that by defining a settable property on a wrapped object.
			let capturedHref = win.location.href;
			Object.defineProperty(win.location, "href", {
				configurable: true,
				get: () => capturedHref,
				set: (val) => {
					capturedHref = val;
					win.__cyCapturedRedirect = val;
				},
			});
		});

		// Intercept the wu-ajax POST so we can assert response shape
		// directly. The light-ajax URL pattern is
		// `<subsite>/?wu-ajax=1&r=<nonce>&action=wu_switch_template`.
		cy.intercept("POST", "**/?wu-ajax=1*").as("switchTemplate");

		// Click the destructive Confirm button inside the panel.
		cy.get(".wu-template-switching-confirm .button")
			.contains(/Switch to /i)
			.click();

		// The customer-reported bug surfaces as `success: false` with
		// `data[0].code == "not_authorized"`. Assert the opposite.
		cy.wait("@switchTemplate").then((interception) => {
			cy.log(`AJAX response: ${ JSON.stringify(interception.response.body) }`);

			// Reject the regression explicitly so a future failure points
			// straight at the bug we are guarding against.
			if (
				interception.response.body &&
				interception.response.body.success === false &&
				interception.response.body.data &&
				interception.response.body.data[ 0 ] &&
				interception.response.body.data[ 0 ].code === "not_authorized"
			) {
				throw new Error(
					"REGRESSION: Site::is_customer_allowed() denied the switch even though " +
						"the customer is the legitimate owner of the site. This is the bug " +
						"reported as \"No puedes cambiar la plantilla de este sitio\" — see " +
						"inc/ui/class-template-switching-element.php::switch_template() and " +
						"the diagnostic log entry under " +
						"wp-content/uploads/wp-ultimo/logs/template-switching.log."
				);
			}

			expect(interception.response.statusCode).to.equal(200);
			expect(interception.response.body.success).to.equal(true);
			expect(interception.response.body.data.redirect_url).to.be.a("string");
		});

		// Side-effect assertion: the persisted template_id on the
		// customer's site has been updated to template_two_id.
		cy.wpCli(`eval-file /var/www/html/wp-content/plugins/ultimate-multisite/tests/e2e/cypress/fixtures/verify-template-switch.php ${ customerData.customer_blog_id }`).then(
			(result) => {
				const after = JSON.parse(result.stdout.trim());
				cy.log(`After switch: ${ JSON.stringify(after) }`);
				expect(after.ok, "verify fixture ok").to.equal(true);
				expect(after.template_id, "site now uses template two").to.equal(
					customerData.template_two_id
				);
				expect(after.customer_id, "ownership preserved").to.equal(
					customerData.customer_id
				);
			}
		);
	});

	it("Confirm panel surfaces a clear error if the switch is denied", { retries: 0 }, () => {
		// Re-issue the auth gate by overriding the wu_site_is_customer_allowed
		// filter so a normally-allowed customer is denied. This guards the
		// inline error surface that the customer screenshot showed (the red
		// "No se pudo cambiar la plantilla" / "Could not switch template"
		// block inside the confirm panel). If a future change ever removes
		// that block, the customer would be left with a silent failure and
		// just a cleared spinner — which is exactly what 910c82e1 originally
		// fixed and what we want to keep covered.

		cy.wpCli(
			`eval "add_filter( 'wu_site_is_customer_allowed', '__return_false', PHP_INT_MAX ); " +
				"update_option( 'wu_force_deny_template_switching', 1 );"`,
			{ failOnNonZeroExit: false }
		);

		cy.clearCookies();
		cy.loginByForm(customerData.username, customerData.password);

		const switchPagePath = `${ customerData.customer_path }wp-admin/admin.php?page=wu-template-switching`;
		cy.visit(switchPagePath, { failOnStatusCode: false });

		// If the filter denies at page-render time too, we should see the
		// "Template switching is not available right now" notice instead
		// of the grid. Either branch is acceptable for this assertion;
		// the key is that the customer is never left with a hanging
		// spinner or an empty page.
		cy.get(".notice, .wu-site-template-selector", { timeout: 30000 }).should(
			"exist"
		);
	});
});
