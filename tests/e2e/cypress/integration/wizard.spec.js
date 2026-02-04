const page_name = "wp-ultimo-setup";

describe("Wizard", () => {
  before(() => {
    cy.loginByApi(Cypress.env("admin").username, Cypress.env("admin").password);

    // Clear setup-finished flag so the wizard is accessible
    cy.wpCli(
      'eval "delete_network_option(null, WP_Ultimo::NETWORK_OPTION_SETUP_FINISHED);"'
    );

    cy.visit(`/wp-admin/network/admin.php?page=${page_name}`);
  });

  it("Should be able to successfully complete the setup wizard", () => {
    /**
     * Steps: Welcome
     */
    cy.assertPageUrl({
      pathname: "/wp-admin/network/admin.php",
      page: page_name,
    });
    cy.clickPrimaryBtnByTxt("Get Started");

    /**
     * Steps: Checks
     */
    cy.assertPageUrl({
      pathname: "/wp-admin/network/admin.php",
      page: page_name,
      step: "checks",
    });
    cy.clickPrimaryBtnByTxt("Go to the Next Step");

    /**
     * Steps: Installation
     * Button text varies ("Install" vs "Go to the Next Step") depending on
     * whether DB tables were already created by a prior spec.
     */
    cy.assertPageUrl({
      pathname: "/wp-admin/network/admin.php",
      page: page_name,
      step: "installation",
    });
    cy.get('button[data-testid="button-primary"]')
      .should("not.be.disabled")
      .click();

    /**
     * Steps: Your Company
     */
    cy.assertPageUrl({
      pathname: "/wp-admin/network/admin.php",
      page: page_name,
      step: "your-company",
    });
    cy.clickPrimaryBtnByTxt("Continue");

    /**
     * Steps: Default Content
     * Creates template site, example products, checkout form, emails, login page.
     * Items already created by prior specs are skipped automatically.
     */
    cy.assertPageUrl({
      pathname: "/wp-admin/network/admin.php",
      page: page_name,
      step: "defaults",
    });
    cy.get('button[data-testid="button-primary"]')
      .should("not.be.disabled")
      .click();

    /**
     * Steps: Recommended Plugins
     * May download plugins from wordpress.org via AJAX; allow extra time.
     */
    cy.url({ timeout: 120000 }).should("include", "step=recommended-plugins");
    cy.get('button[data-testid="button-primary"]')
      .should("not.be.disabled")
      .click();

    /**
     * Steps: Done
     */
    cy.url({ timeout: 120000 }).should("include", "step=done");
    cy.clickPrimaryBtnByTxt("Thanks!");
    cy.assertPageUrl({
      pathname: "/wp-admin/network/index.php",
    });
  });
});
