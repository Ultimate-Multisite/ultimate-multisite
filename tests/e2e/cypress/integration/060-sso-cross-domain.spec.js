/**
 * SSO Cross-Domain Authentication E2E Tests
 *
 * Verifies that Single Sign-On works: a user logged into the main site
 * (localhost:8889) is automatically authenticated when visiting a subsite
 * through a mapped domain (127.0.0.1:8889).
 *
 * Uses localhost vs 127.0.0.1 — two genuinely different hostnames that
 * both resolve without DNS/hosts changes, with cookies scoped per hostname.
 *
 * Environment note: wp-env receives browser requests on a non-standard port
 * (8889), while WordPress core's early multisite bootstrap strips the port
 * before domain lookup. The fixture stores the test subsite on the bare
 * 127.0.0.1 host and keeps the browser-facing URL with the port, so this still
 * exercises SSO across distinct cookie domains without external DNS.
 */
describe("SSO Cross-Domain Authentication", () => {
  const mainSiteUrl = "http://localhost:8889";
  const mappedDomainUrl = "http://127.0.0.1:8889";
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
    // Verify domain mapping works: 127.0.0.1:8889 should serve the subsite,
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
      // Without login cookies for 127.0.0.1, visiting wp-admin should trigger
      // the SSO redirect chain (handle_auth_redirect detects different domain).
      cy.request({
        url: `${mappedDomainUrl}/wp-admin/`,
        followRedirect: false,
        failOnStatusCode: false,
      }).then((response) => {
        // SSO triggers a 302 redirect to wp-login.php?sso=login
        expect(response.status).to.eq(302);
        expect(response.headers.location).to.include("sso=login");
        expect(response.headers.location).to.include("wp-login.php");
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

      // 2. Visit wp-admin on the mapped domain (127.0.0.1:8889).
      //    SSO triggers: handle_auth_redirect() detects different domain + not
      //    logged in, redirects to wp-login.php?sso=login. Because this wp-env
      //    uses port 8889, the redirect goes through localhost:8889 where auth
      //    cookies exist, so the user is immediately authenticated.
      //
      //    The final landing page is the subsite's wp-admin on the mapped host.
      cy.visit(`${mappedDomainUrl}/wp-admin/`, {
        failOnStatusCode: false,
      });

      // 3. After SSO redirect chain completes, the user should land on the
      //    subsite's wp-admin dashboard (authenticated).
      cy.url({ timeout: 60000 }).should("include", "/wp-admin/");
      cy.get("body", { timeout: 30000 }).should("have.class", "wp-admin");

      // Confirm we are logged in: admin bar should be present.
      cy.get("#wpadminbar").should("exist");

      // Confirm we stayed on the mapped subsite host (not the main-site host).
      cy.url().should("include", mappedDomainUrl);
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

      // After SSO, the user should land on the requested admin page.
      cy.url({ timeout: 60000 }).should("include", "/wp-admin/");
      cy.url().should("include", targetPath);
      cy.get("body", { timeout: 30000 }).should("have.class", "wp-admin");
      cy.get("#wpadminbar").should("exist");
    }
  );
});
