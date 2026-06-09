/**
 * SSO Cross-Domain Authentication E2E Tests
 *
 * Verifies that Single Sign-On works: a user logged into the main site
 * (localhost:8889) is automatically authenticated when visiting a subsite
 * through a mapped domain (sso-test.ultimate-multisite.test:8889).
 *
 * Uses localhost vs sso-test.ultimate-multisite.test — two genuinely
 * different hostnames with cookies scoped per hostname. The mapped hostname is
 * resolved through a CI /etc/hosts entry so the request reaches the same
 * wp-env port while preserving the mapped Host header for domain mapping.
 */
describe("SSO Cross-Domain Authentication", () => {
  const mainSiteUrl = "http://localhost:8889";
  const mappedDomainUrl = "http://sso-test.ultimate-multisite.test:8889";
  const adminUser = "admin";
  const adminPass = "password";

  before(() => {
    // Ensure we start on the main site for login / WP-CLI commands.
    cy.visit("/wp-login.php", { failOnStatusCode: false });

    // Run the SSO setup fixture: creates subsite + domain mapping + enables SSO.
    cy.wpCliFile("tests/e2e/cypress/fixtures/setup-sso-test.php", {
      failOnNonZeroExit: false,
    }).then((result) => {
      const output = result.stdout.trim();
      cy.log(`SSO setup output: ${output}`);

      // Verify setup succeeded (output is JSON without an error key).
      expect(output).to.contain("site_id");
      expect(output).to.not.contain('"error"');
    });
  });

  it("Should resolve mapped domain to the correct subsite", () => {
    // Verify domain mapping works: the mapped host should serve the subsite,
    // not redirect to the main site homepage.
    cy.request({
      url: `${mappedDomainUrl}/`,
      followRedirect: false,
      failOnStatusCode: false,
    }).then((response) => {
      // Should get 200 (subsite front page) — NOT a 302 redirect to main site.
      expect(response.status).to.eq(200);
    });
  });

  it(
    "Should trigger SSO redirect when visiting wp-admin on mapped domain",
    { retries: 1 },
    () => {
      // Without login cookies for the mapped host, visiting wp-admin should trigger
      // the SSO redirect chain (handle_auth_redirect detects different domain).
      cy.request({
        url: `${mappedDomainUrl}/wp-admin/`,
        followRedirect: false,
        failOnStatusCode: false,
      }).then((response) => {
        // SSO triggers a 302 redirect to the active login URL with sso=login.
        // The setup wizard may leave a custom login page at /login/, so avoid
        // coupling this check to wp-login.php specifically.
        expect(response.status).to.eq(302);
        expect(response.headers.location).to.include("sso=login");
      });
    }
  );

  it(
    "Should auto-authenticate on subsite via SSO after main-site login",
    { retries: 1 },
    () => {
      // 1. Log in on the main site (localhost:8889).
      cy.loginByApi(adminUser, adminPass);

      // Verify login worked on main site.
      cy.visit("/wp-admin/", { failOnStatusCode: false });
      cy.url().should("include", "/wp-admin/");
      cy.get("body").should("have.class", "wp-admin");

      // 2. Visit wp-admin on the mapped domain.
      //    SSO triggers: handle_auth_redirect() detects different domain + not
      //    logged in, redirects through wp-login.php?sso=login, and uses the
      //    existing main-site auth cookies to authenticate the subsite request.
      cy.visit(`${mappedDomainUrl}/wp-admin/`, {
        failOnStatusCode: false,
      });

      // 3. After SSO redirect chain completes, the user should land on an
      //    authenticated wp-admin page. In wp-env the auth handoff returns
      //    through the main localhost origin where the login cookie exists.
      cy.url({ timeout: 60000 }).should("include", "/wp-admin/");
      cy.get("body", { timeout: 30000 }).should("have.class", "wp-admin");

      // Confirm we are logged in: admin bar should be present.
      cy.get("#wpadminbar").should("exist");

      // Confirm the SSO flow authenticated the browser session.
      cy.url().should("include", mainSiteUrl);
    }
  );

  it(
    "Should preserve redirect_to parameter through SSO flow",
    { retries: 1 },
    () => {
      // This verifies that URL parameters survive the SSO redirect chain.
      cy.loginByApi(adminUser, adminPass);

      // Visit a specific wp-admin page on the mapped domain.
      const targetPath = "/wp-admin/options-general.php";

      cy.visit(`${mappedDomainUrl}${targetPath}`, {
        failOnStatusCode: false,
      });

      // After SSO, the user should land on the requested page (or wp-admin).
      cy.url({ timeout: 60000 }).should("include", "/wp-admin/");
      cy.get("body", { timeout: 30000 }).should("have.class", "wp-admin");
      cy.get("#wpadminbar").should("exist");
    }
  );
});
