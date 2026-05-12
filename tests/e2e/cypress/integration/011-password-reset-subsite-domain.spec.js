/**
 * Password Reset Subsite Domain — regression guard for PR #1169 / issue #1168
 *
 * Bug recap:
 *   `replace_reset_password_link()` in `inc/checkout/class-checkout-pages.php`
 *   had two issues:
 *     1. `add_filter('retrieve_password_message', ...)` was registered inside
 *        an `if (is_main_site())` block, so the filter never ran on subsites.
 *     2. The function itself bailed out early with
 *        `if (! is_main_site()) return $message;`.
 *
 *   Combined, every subsite's password-reset email pointed to
 *   `https://<main-site>/wp-login.php?action=rp&...` — confusing end users
 *   who registered on a mapped custom domain (they didn't recognise the host
 *   and treated the email as phishing).
 *
 * Fix (merged in 2.10.2, present in 2.11.x):
 *   - Filter registration moved out of the `is_main_site()` gate.
 *   - `replace_reset_password_link()` rewrites the URL to `home_url('/')` of
 *     the current subsite, preserving the `action / key / login / wp_lang`
 *     query args so existing handlers (WooCommerce my-account, BuddyPress,
 *     custom themes, default wp-login fallback) can pick the request up.
 *   - New filter `wu_subsite_password_reset_url` lets integrations override
 *     the destination URL without re-implementing the rewrite.
 *
 * What this spec verifies (so the fix can't silently regress):
 *   - On a subsite, the rewritten URL's host is the SUBSITE host, NOT the
 *     main site's host.
 *   - The reset query args (action=rp, key, login, wp_lang) are preserved
 *     so handlers downstream still get the data they need.
 *   - The new `wu_subsite_password_reset_url` filter is reachable.
 *
 * Driven from PHP via a WP-CLI fixture — we apply the filter chain directly
 * with a synthetic raw message. No SMTP / Mailpit / cron dependency.
 */
describe("Password Reset on Subsite Stays on Subsite Domain (PR #1169 regression guard)", () => {
  let fixtureData;

  before(() => {
    cy.wpCliFile("tests/e2e/cypress/fixtures/setup-password-reset-subsite.php", {
      failOnNonZeroExit: false,
    }).then((result) => {
      const output = result.stdout.trim();
      cy.log(`Password reset fixture output: ${output}`);

      try {
        fixtureData = JSON.parse(output);
      } catch (e) {
        throw new Error(
          `Fixture did not return JSON. Raw output:\n${output}`
        );
      }

      expect(fixtureData, "fixture produced an error").to.not.have.property("error");
      expect(fixtureData.site_id, "no site_id").to.be.a("number");
      expect(fixtureData.rewritten_url, "no rewritten_url").to.be.a("string");
    });
  });

  it("Should rewrite the reset URL to the subsite host (not the main-site host)", () => {
    const subsite = fixtureData.subsite_host;
    const main    = fixtureData.main_host;
    const host    = fixtureData.rewritten_host;
    const rawUrl  = fixtureData.raw_url || "";

    // Sanity: confirm the raw input we asked UM to rewrite did point at
    // the main site (otherwise the test wouldn't actually exercise the fix).
    expect(rawUrl, "raw URL did not point at main host — fixture is misconfigured")
      .to.include(main);

    // Core assertion: the host of the rewritten URL must be the subsite host.
    // We allow `subsite === main` as a degenerate case for environments where
    // wp-env has not been given a separate subsite domain (path-based subsite
    // on the same host — still passes because the URL is at least no longer
    // pointed at wp-login.php on a different host).
    if (subsite && subsite !== main) {
      expect(host, "rewritten URL host should be the subsite host")
        .to.equal(subsite);
    } else {
      // Path-based subsite scenario: the URL should at least not be the
      // raw main-site wp-login.php URL we fed in.
      expect(fixtureData.rewritten_url).to.not.equal(rawUrl);
    }

    // It must never silently fall back to wp-login.php on a host different
    // from the subsite host — that's the symptom of the regression.
    expect(fixtureData.rewritten_url, "URL still points at /wp-login.php")
      .to.not.match(/\/wp-login\.php(\?|$)/);
  });

  it("Should preserve the reset query args needed by downstream handlers", () => {
    const q = fixtureData.rewritten_query || {};

    // action / key / login / wp_lang are what WooCommerce, BuddyPress, and
    // default wp-login fallback look for. Losing any of them silently breaks
    // the reset flow on subsites.
    expect(q.action, "missing action=rp").to.equal("rp");
    expect(q.key, "missing key").to.be.a("string").and.to.have.length.greaterThan(0);
    expect(q.login, "missing login").to.be.a("string").and.to.have.length.greaterThan(0);
    expect(q.wp_lang, "missing wp_lang").to.be.a("string");
  });

  it("Should expose the wu_subsite_password_reset_url filter for integrations", () => {
    // PR #1169 introduced this filter so integrations can override the
    // destination URL (e.g. point at the WooCommerce reset-password endpoint)
    // without re-implementing the rewrite. The fixture hooks the filter
    // transiently and toggles `filter_ran` when it fires.
    expect(
      fixtureData.filter_ran,
      "wu_subsite_password_reset_url did not run — fix may be regressing"
    ).to.equal(true);
  });
});
