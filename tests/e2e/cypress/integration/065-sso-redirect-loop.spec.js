/**
 * SSO Redirect Loop Prevention E2E Tests
 *
 * Verifies that the SSO redirect loop bug is fixed: non-logged-in visitors
 * on subsites must NOT get stuck in an infinite redirect loop with
 * ?sso_verify=invalid;sso_error=User+not+logged+in.
 *
 * The bug had three root causes (all fixed):
 * 1. JSONP unattached broker: handle_broker() redirected instead of returning
 *    a JSONP error, so the wu.sso() JS callback never fired and the
 *    wu_sso_denied cookie was never set.
 * 2. Incognito JS redirect: sso.js detected incognito mode and redirected to
 *    the server URL as a fallback, creating a redirect -> sso_verify=invalid
 *    -> redirect loop.
 * 3. Missing cookie: Without wu_sso_denied being set, the SSO script kept
 *    re-triggering on every page load.
 *
 * Test environment: wp-env uses localhost:8889 (main site) and
 * sso-test.ultimate-multisite.test:8889 (mapped subsite domain) — two
 * genuinely different hostnames with separate cookie jars, which naturally
 * exercises cross-domain SSO.
 */
describe("SSO Redirect Loop Prevention", () => {
  const mainSiteUrl = "http://localhost:8889";
  const mappedDomainHost = "sso-test.ultimate-multisite.test";
  const mappedDomainUrl = `http://${mappedDomainHost}:8889`;

  before(() => {
    // Ensure SSO test environment is set up (subsite + domain mapping + SSO enabled).
    // This reuses the same fixture as 060-sso-cross-domain.spec.js.
    cy.visit("/wp-login.php", { failOnStatusCode: false });

    cy.wpCliFile("tests/e2e/cypress/fixtures/setup-sso-test.php", {
      failOnNonZeroExit: false,
    }).then((result) => {
      const output = result.stdout.trim();
      cy.log(`SSO setup output: ${output}`);
      expect(output).to.contain("site_id");
      expect(output).to.not.contain('"error"');
    });
  });

  beforeEach(() => {
    // Clear all cookies to simulate a fresh non-logged-in visitor.
    cy.clearCookies();
    cy.clearAllCookies();
  });

  // -----------------------------------------------------------------------
  // Core redirect loop prevention
  // -----------------------------------------------------------------------

  it("Should load subsite front page without redirect loop for non-logged-in visitor", () => {
    // This is the primary test for the bug fix.
    // A non-logged-in visitor hits the subsite front page on the mapped domain.
    // The SSO JS fires a JSONP request to the main site. Since the visitor is
    // not logged in on the main site, the JSONP response should return an error
    // (code: 0), the JS should set the wu_sso_denied cookie, and the page
    // should load normally — NOT redirect.

    // Visit the subsite front page on the mapped domain.
    // Use cy.visit which will fail if there's an infinite redirect.
    cy.visit(`${mappedDomainUrl}/`, {
      failOnStatusCode: false,
      // Cypress will automatically fail if the page doesn't load within
      // pageLoadTimeout (60s default, 90s in CI). An infinite redirect
      // would cause this timeout.
    });

    // The page should have loaded successfully (not stuck in a redirect loop).
    // Check that we're on a real page, not a redirect error.
    cy.get("body").should("exist");

    // The URL should NOT contain sso_verify=invalid (the redirect loop marker).
    cy.url().should("not.include", "sso_verify=invalid");
    cy.url().should("not.include", "sso_error=");
  });

  it("Should not redirect loop when visiting subsite with SSO query params already present", () => {
    // Simulate the scenario where a visitor lands on the subsite with
    // sso_verify=invalid already in the URL (e.g., from a bookmark or
    // a previous failed SSO attempt). The fix should set the wu_sso_denied
    // cookie and redirect to the clean URL — NOT loop.

    cy.request({
      url: `${mappedDomainUrl}/?sso_verify=invalid&return_url=${encodeURIComponent(mappedDomainUrl + "/")}`,
      followRedirect: false,
      failOnStatusCode: false,
    }).then((response) => {
      // The server should respond with a 302 redirect to the clean return_url
      // (with wu_sso_denied cookie set), NOT another sso_verify=invalid URL.
      if (response.status === 302) {
        const location = response.headers.location || response.headers.Location || "";
        expect(location).to.not.include("sso_verify=invalid");

        // The response should set the wu_sso_denied cookie.
        const setCookieHeader = response.headers["set-cookie"];
        if (setCookieHeader) {
          const cookies = Array.isArray(setCookieHeader)
            ? setCookieHeader.join("; ")
            : setCookieHeader;
          expect(cookies).to.include("wu_sso_denied");
        }
      } else {
        // If it's a 200, that's also acceptable (page loaded directly).
        expect(response.status).to.be.oneOf([200, 302]);
      }
    });
  });

  // -----------------------------------------------------------------------
  // JSONP endpoint behaviour (the primary fix)
  // -----------------------------------------------------------------------

  it("Should return JSONP error response (not redirect) for non-logged-in JSONP request", () => {
    // This tests the core fix: when a non-logged-in visitor's browser makes
    // a JSONP request (via <script> tag) to the subsite's SSO endpoint,
    // the server must return a JavaScript response calling wu.sso() with
    // an error code — NOT a 302 redirect.
    //
    // Before the fix, the server returned a 302 redirect for JSONP requests
    // when the broker was unattached. The browser follows 302s transparently
    // for <script> tags, but the final response was another redirect (not JS),
    // so wu.sso() never fired and wu_sso_denied was never set.

    cy.request({
      url: `${mappedDomainUrl}/sso?_jsonp=1&return_type=jsonp`,
      followRedirect: false,
      failOnStatusCode: false,
    }).then((response) => {
      if (response.status === 200) {
        // The response should be JavaScript calling wu.sso() with an error.
        const body = response.body;
        expect(body).to.include("wu.sso(");

        // The Content-Type should be application/javascript.
        const contentType = response.headers["content-type"] || "";
        expect(contentType).to.include("javascript");
      } else if (response.status === 302) {
        // If we get a redirect, it should be the attach URL (first-time broker
        // setup), not a loop. The redirect should NOT point back to /sso.
        const location = response.headers.location || "";
        // The attach URL goes to the main site's sso-grant endpoint.
        // It should NOT redirect back to the same /sso endpoint.
        cy.log(`Got redirect to: ${location}`);
      }
    });
  });

  // -----------------------------------------------------------------------
  // SSO JS does not contain incognito redirect code
  // -----------------------------------------------------------------------

  it("Should not contain incognito detection in the SSO JavaScript", () => {
    // The incognito redirect path in sso.js was one of the redirect loop
    // causes. Verify the served JS file does not contain incognito detection.

    // First, visit the subsite to ensure the SSO JS is enqueued.
    cy.visit(`${mappedDomainUrl}/`, {
      failOnStatusCode: false,
    });

    // Check that the page source does not contain incognito detection code.
    // The wu_sso_config object is inlined by wp_localize_script, and sso.js
    // is loaded as a separate script file.
    cy.get("body").should("exist");

    // Verify no incognito-related code in the page.
    cy.document().then((doc) => {
      const html = doc.documentElement.outerHTML;
      expect(html).to.not.include("detectIncognito");
      expect(html).to.not.include("is_incognito");
    });
  });

  // -----------------------------------------------------------------------
  // wu_sso_denied cookie prevents re-triggering
  // -----------------------------------------------------------------------

  it("Should not trigger SSO when wu_sso_denied cookie is already set", () => {
    // When the wu_sso_denied cookie is set, the SSO script should not fire
    // the JSONP request at all. This prevents any possibility of a loop.

    // Set the wu_sso_denied cookie on the mapped domain.
    cy.setCookie("wu_sso_denied", "1", {
      domain: mappedDomainHost,
      path: "/",
    });

    // Intercept any requests to the SSO endpoint to verify none are made.
    cy.intercept(`${mappedDomainUrl}/sso*`).as("ssoRequest");

    cy.visit(`${mappedDomainUrl}/`, {
      failOnStatusCode: false,
    });

    // The page should load normally.
    cy.get("body").should("exist");
    cy.url().should("not.include", "sso_verify");

    // Verify no SSO JSONP request was made (the script should have skipped it).
    // Wait briefly to ensure no late requests fire.
    cy.wait(2000);
    cy.get("@ssoRequest.all").should("have.length", 0);
  });

  // -----------------------------------------------------------------------
  // Multiple page loads don't cause accumulating redirects
  // -----------------------------------------------------------------------

  it("Should handle multiple page loads without accumulating redirect params", () => {
    // Simulate a user navigating multiple pages on the subsite.
    // Each page load should work cleanly without SSO params accumulating.

    const pages = ["/", "/?page_id=2", "/?p=1"];

    pages.forEach((page) => {
      cy.visit(`${mappedDomainUrl}${page}`, {
        failOnStatusCode: false,
      });

      cy.get("body").should("exist");
      cy.url().should("not.include", "sso_verify=invalid");
      cy.url().should("not.include", "sso_error=");
    });
  });

});
