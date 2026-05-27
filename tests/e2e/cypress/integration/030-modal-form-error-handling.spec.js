describe("Modal Form Error Handling", () => {
	before(() => {
		cy.loginByApi(
			Cypress.env("admin").username,
			Cypress.env("admin").password
		);
	});

	beforeEach(() => {
		cy.loginByApi(
			Cypress.env("admin").username,
			Cypress.env("admin").password
		);
	});

	/**
	 * Helper: open the Add Site modal, wait for the form to load,
	 * then scroll to and click the submit button.
	 */
	const openSiteModalAndSubmit = () => {
		cy.contains("a.wubox", "Add Site").click();

		cy.get("#WUB_window", { timeout: 10000 }).should("be.visible");
		cy.get("#WUB_ajaxContent .wu_form", { timeout: 10000 }).should("exist");

		// The submit button may be below the fold in the modal
		cy.get('#WUB_ajaxContent button[type="submit"]').scrollIntoView().click();
	};

	it("Should show an error message when the server returns a 500 on Add Site modal", {
		retries: 0,
	}, () => {
		cy.visit("/wp-admin/network/admin.php?page=wp-ultimo-sites", {
			failOnStatusCode: false,
		});

		cy.get("a.wubox", { timeout: 15000 }).should("exist");

		// Intercept the WU AJAX form handler POST to simulate a 500 error
		cy.intercept("POST", "**/\\?wu-ajax=1*action=wu_form_handler*", {
			statusCode: 500,
			body: "<html><body><h1>500 Internal Server Error</h1></body></html>",
			headers: { "Content-Type": "text/html" },
		}).as("formHandler500");

		openSiteModalAndSubmit();

		cy.wait("@formHandler500");

		// The error message should appear in the modal error app
		cy.get('#WUB_ajaxContent [data-wu-app$="_errors"]', { timeout: 10000 })
			.should("contain.text", "An unexpected error occurred");

		// The form should be unblocked (not stuck loading)
		cy.get('#WUB_ajaxContent button[type="submit"]')
			.scrollIntoView()
			.should("not.be.disabled");

		// The modal should still be open (not closed silently)
		cy.get("#WUB_window").should("be.visible");
	});

	it("Should show an error message when the server returns invalid JSON", {
		retries: 0,
	}, () => {
		cy.visit("/wp-admin/network/admin.php?page=wp-ultimo-sites", {
			failOnStatusCode: false,
		});

		cy.get("a.wubox", { timeout: 15000 }).should("exist");

		// Return a 200 with non-JSON body (e.g. PHP fatal error output)
		cy.intercept("POST", "**/\\?wu-ajax=1*action=wu_form_handler*", {
			statusCode: 200,
			body: "<br />\n<b>Fatal error</b>: Uncaught Error in /var/www/html/wp-content/plugins/...",
			headers: { "Content-Type": "text/html" },
		}).as("formHandlerBadJson");

		openSiteModalAndSubmit();

		cy.wait("@formHandlerBadJson");

		// Should show an error, not spin forever
		cy.get('#WUB_ajaxContent [data-wu-app$="_errors"]', { timeout: 10000 })
			.should("contain.text", "An unexpected error occurred");

		cy.get('#WUB_ajaxContent button[type="submit"]')
			.scrollIntoView()
			.should("not.be.disabled");
	});

	it("Should show an error message when the network request fails", {
		retries: 0,
	}, () => {
		cy.visit("/wp-admin/network/admin.php?page=wp-ultimo-sites", {
			failOnStatusCode: false,
		});

		cy.get("a.wubox", { timeout: 15000 }).should("exist");

		// Simulate a network failure
		cy.intercept("POST", "**/\\?wu-ajax=1*action=wu_form_handler*", {
			forceNetworkError: true,
		}).as("formHandlerNetworkError");

		openSiteModalAndSubmit();

		// Should show an error for network failure
		cy.get('#WUB_ajaxContent [data-wu-app$="_errors"]', { timeout: 15000 })
			.should("contain.text", "An unexpected error occurred");

		cy.get('#WUB_ajaxContent button[type="submit"]')
			.scrollIntoView()
			.should("not.be.disabled");
	});

	it("Should still handle normal validation errors correctly", {
		retries: 0,
	}, () => {
		cy.visit("/wp-admin/network/admin.php?page=wp-ultimo-customers", {
			failOnStatusCode: false,
		});

		cy.get("a.wubox", { timeout: 15000 }).should("exist");

		// Do NOT intercept — let the real server respond with validation errors
		cy.contains("a.wubox", "Add Customer").click();

		cy.get("#WUB_window", { timeout: 10000 }).should("be.visible");
		cy.get("#WUB_ajaxContent .wu_form", { timeout: 10000 }).should("exist");

		// Submit the form empty to trigger server-side validation
		cy.get('#WUB_ajaxContent button[type="submit"]')
			.scrollIntoView()
			.click();

		// Validation errors should appear (not our generic server error)
		cy.get('#WUB_ajaxContent [data-wu-app$="_errors"]', { timeout: 10000 })
			.should("be.visible");

		// The error should NOT be the generic server error
		cy.get('#WUB_ajaxContent [data-wu-app$="_errors"]')
			.should("not.contain.text", "An unexpected error occurred");

		// The form should remain open and usable
		cy.get("#WUB_window").should("be.visible");
		cy.get('#WUB_ajaxContent button[type="submit"]')
			.scrollIntoView()
			.should("not.be.disabled");
	});

	it("Should recover and allow resubmission after a server error", {
		retries: 0,
	}, () => {
		cy.visit("/wp-admin/network/admin.php?page=wp-ultimo-sites", {
			failOnStatusCode: false,
		});

		cy.get("a.wubox", { timeout: 15000 }).should("exist");

		let interceptCount = 0;

		// First submission returns 500, second goes through to the real server
		cy.intercept("POST", "**/\\?wu-ajax=1*action=wu_form_handler*", (req) => {
			interceptCount++;
			if (interceptCount === 1) {
				req.reply({
					statusCode: 500,
					body: "Internal Server Error",
				});
			}
			// On second call, let it pass through to the real server
		}).as("formHandlerRecovery");

		openSiteModalAndSubmit();

		// Error message appears after first 500
		cy.get('#WUB_ajaxContent [data-wu-app$="_errors"]', { timeout: 10000 })
			.should("contain.text", "An unexpected error occurred");

		// Second submit: goes through to the real server
		cy.get('#WUB_ajaxContent button[type="submit"]')
			.scrollIntoView()
			.should("not.be.disabled")
			.click();

		// The server error should be cleared (replaced by real validation errors or success)
		cy.get('#WUB_ajaxContent [data-wu-app$="_errors"]', { timeout: 10000 })
			.should("not.contain.text", "An unexpected error occurred");
	});
});
