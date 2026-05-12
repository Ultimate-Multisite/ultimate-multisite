/**
 * SSO Bootstrap Race — regression guard for PR #1185
 *
 * Bug recap:
 *   The SSO callback `convert_bearer_into_auth_cookies()` is attached to `init`.
 *   On some multisite bootstraps the `$GLOBALS['current_blog']` object exists
 *   but its `registered` property is still an empty string (mapped-domain
 *   subsites right after a cache flush, iframe'd admin requests, hosts that
 *   warm object caches between `sunrise.php` and `ms-settings.php`).
 *
 *   `get_broker()` then called `calculate_secret_from_date('')`, which threw
 *   `SSO_Exception`. The user-visible symptom was admin pages loaded through
 *   an iframe via SSO rendering blank, masked by full-page cache on healthy
 *   requests but reappearing on every cache invalidation. Reproduced
 *   reliably on a 300+ subsite production network (kursopro.com).
 *
 * Fix (merged in 2.11.1):
 *   Two defensive guards in `inc/sso/class-sso.php`:
 *     1. `convert_bearer_into_auth_cookies()` early-returns when
 *        `$current_blog` is missing or its `registered` property is empty.
 *     2. `calculate_secret_from_date($date)` falls back to the main site's
 *        registered date (or '2024-01-01 00:00:00') when `$date` is empty,
 *        instead of throwing.
 *
 * What this spec verifies (so the fix can't silently regress):
 *   - `calculate_secret_from_date('')` does NOT throw and returns a real hash.
 *   - Two consecutive calls with the same empty input return the SAME hash
 *     (deterministic fallback — important so SSO state stays consistent
 *     across requests during a bootstrap window).
 *   - `convert_bearer_into_auth_cookies()` does NOT throw when called with
 *     an emptied `$current_blog->registered`.
 *
 * Driven entirely from PHP via a WP-CLI fixture; no browser interaction
 * is required because the race is at the PHP layer, not in the UI.
 */
describe("SSO Bootstrap Race (PR #1185 regression guard)", () => {
  it("Should not throw and should return a deterministic hash when bootstrap data is incomplete", () => {
    cy.wpCliFile("tests/e2e/cypress/fixtures/setup-sso-bootstrap-race.php", {
      failOnNonZeroExit: false,
    }).then((result) => {
      const output = result.stdout.trim();
      cy.log(`Bootstrap race fixture output: ${output}`);

      // The fixture must always emit valid JSON, even on internal errors.
      let data;
      try {
        data = JSON.parse(output);
      } catch (e) {
        throw new Error(
          `Fixture did not return JSON. Raw output:\n${output}`
        );
      }

      // Bootstrap sanity: SSO class must be loadable in the test environment.
      expect(data, "fixture produced an error").to.not.have.property("error");

      // Guard 2: calculate_secret_from_date('') must not throw.
      expect(data.secret_threw, "calculate_secret_from_date('') threw").to.equal(false);

      // It must return a real hash (32-char hex, like wp_hash output length).
      expect(data.empty_secret_1, "empty_secret_1 is not a string").to.be.a("string");
      expect(data.empty_secret_1, "empty_secret_1 looks like a throw marker")
        .to.not.match(/^THREW:/);
      expect(data.empty_secret_1.length, "empty_secret_1 is too short to be a hash")
        .to.be.greaterThan(16);

      // Determinism: same empty input -> same hash on a repeat call.
      // If the fallback ever switched to a non-deterministic source
      // (e.g. current time), SSO would break in production whenever a
      // subsite hits the empty-`registered` window between requests.
      expect(
        data.empty_secret_1,
        "empty_secret_1 and empty_secret_2 must be equal (deterministic fallback)"
      ).to.equal(data.empty_secret_2);

      // The main-site path is the established baseline. The empty-input
      // fallback should normally match it (both resolve to the main site's
      // registered date), but we don't hard-assert equality because a future
      // fallback constant ('2024-01-01 00:00:00') would diverge while still
      // being correct. We assert it at least produced a hash.
      expect(data.main_secret, "main_secret is not a string").to.be.a("string");
      expect(data.main_secret, "main_secret looks like a throw marker")
        .to.not.match(/^THREW:/);

      // Guard 1: convert_bearer_into_auth_cookies() with empty registered
      // must not throw SSO_Exception (it should early-return).
      expect(
        data.bearer_threw,
        `convert_bearer_into_auth_cookies() threw: ${data.bearer_error || ""}`
      ).to.equal(false);
    });
  });
});
