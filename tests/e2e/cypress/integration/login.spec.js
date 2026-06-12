/* global cy, Cypress, describe, it */

describe("Login", () => {
	describe("User Interface", () => {
		it("Should show the username and password login form by default", () => {
			cy.visit("/wp-login.php");
			cy.get(".wu-passwordless-auth").should("not.exist");
			cy.get("#user_login").should("be.visible");
			cy.get("#user_pass").should("be.visible");
		});

		it("Should be able to login by the user interface", () => {
			cy.loginByForm(
				Cypress.env("admin").username,
				Cypress.env("admin").password
			);
		});

		it("Should be able to logout by the user interface", () => {
			cy.loginByApi(
				Cypress.env("admin").username,
				Cypress.env("admin").password
			);
			cy.visit("/wp-admin/");
			cy.location("pathname")
				.should("not.contain", "/wp-login.php")
				.and("equal", "/wp-admin/");
			cy.get("#wp-admin-bar-logout > a").click({ force: true });
			cy.location("pathname").should("contain", "/wp-login.php");
			cy.location("search").should("contain", "loggedout=true");
		});
	});

	describe("Application Interface", () => {
		it("Should be able to login by the application interface", () => {
			cy.loginByApi(
				Cypress.env("admin").username,
				Cypress.env("admin").password
			);
			cy.visit("/wp-admin/");
			cy.location("pathname")
				.should("not.contain", "/wp-login.php")
				.and("equal", "/wp-admin/");
		});
	});
});
