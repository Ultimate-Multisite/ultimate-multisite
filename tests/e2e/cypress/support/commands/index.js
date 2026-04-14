import "./login";
import "./wizard";
import "./domain-mapping";

Cypress.Commands.add("wpCli", (command, options = {}) => {
  cy.exec(`npx wp-env run tests-cli wp ${command}`, {
    ...options,
    timeout: options.timeout || 60000,
  });
});

/**
 * Run a PHP file inside the wp-env container via WP-CLI eval-file.
 * Path is relative to the plugin root inside the container.
 */
Cypress.Commands.add("wpCliFile", (filePath, options = {}) => {
  const containerPath = `/var/www/html/wp-content/plugins/ultimate-multisite/${filePath}`;

  cy.exec(`npx wp-env run tests-cli wp eval-file ${containerPath}`, {
    ...options,
    timeout: options.timeout || 60000,
  });
});

Cypress.Commands.add("loginByApi", (username, password) => {
  cy.request({
    method: "POST",
    url: "/wp-login.php",
    form: true,
    body: {
      log: username,
      pwd: password,
      "wp-submit": "Log In",
      redirect_to: "/wp-admin/",
      testcookie: 1,
    },
  });
});

Cypress.Commands.overwrite("type", (originalFn, subject, string, options) =>
  originalFn(subject, string, Object.assign({ delay: 0 }, options))
);

Cypress.Commands.add("setValue", { prevSubject: true }, (subject, value) => {
  subject[0].setAttribute("value", value);
  return subject;
});

Cypress.Commands.add("saveDraft", () => {
  cy.window().then((w) => (w.stillOnCurrentPage = true));
  cy.get("#save-post").should("not.have.class", "disabled").click();
});

Cypress.Commands.add("publishPost", () => {
  cy.window().then((w) => (w.stillOnCurrentPage = true));
  cy.get("#publish").should("not.have.class", "disabled").click();
});

Cypress.Commands.add("waitForPageLoad", () => {
  cy.window().its("stillOnCurrentPage").should("be.undefined");
  cy.get("#message .notice-dismiss").click();
});

Cypress.Commands.add("blockAutosaves", () => {
  cy.intercept("/wp-admin/admin-ajax.php", (req) => {
    if (req.body.includes("wp_autosave")) {
      req.reply({
        status: 400,
      });
    }
  }).as("adminAjax");
});

/**
 * Fill Stripe Payment Element card details inside its iframe.
 * Uses test card 4242424242424242, exp 12/30, CVC 123.
 * Must use explicit delay since Stripe's input masking needs time per keystroke
 * (the global type override sets delay: 0 which breaks Stripe inputs).
 */
Cypress.Commands.add("fillStripeCard", () => {
  // Helper to get an iframe body
  const getIframeBody = (selector) => {
    return cy
      .get(selector, { timeout: 30000 })
      .its("0.contentDocument.body")
      .should("not.be.empty")
      .then(cy.wrap);
  };

  // Stripe Payment Element can render as a single iframe or multiple iframes.
  // Check which layout we have.
  cy.get("#payment-element").then(($el) => {
    const iframes = $el.find("iframe");

    if (iframes.length >= 3) {
      // Multi-iframe layout: separate iframes for number, expiry, cvc
      getIframeBody("#payment-element iframe:eq(0)").within(() => {
        cy.get('input[name="number"], input[name="cardnumber"]')
          .first()
          .type("4242424242424242", { delay: 50 });
      });

      getIframeBody("#payment-element iframe:eq(1)").within(() => {
        cy.get('input[name="expiry"], input[name="exp-date"]')
          .first()
          .type("1230", { delay: 50 });
      });

      getIframeBody("#payment-element iframe:eq(2)").within(() => {
        cy.get('input[name="cvc"]')
          .first()
          .type("123", { delay: 50 });
      });

      // Postal code may be in a 4th iframe or within one of the above
      cy.get("#payment-element").then(($el2) => {
        if ($el2.find("iframe").length > 3) {
          getIframeBody("#payment-element iframe:eq(3)").within(() => {
            cy.get('input[name="postalCode"], input[name="postal"]')
              .first()
              .type("94105", { delay: 50 });
          });
        }
      });
    } else {
      // Single iframe layout (most common with Payment Element)
      getIframeBody("#payment-element iframe").within(() => {
        cy.get('input[name="number"], input[name="cardnumber"]')
          .first()
          .type("4242424242424242", { delay: 50 });

        cy.get('input[name="expiry"], input[name="exp-date"]')
          .first()
          .type("1230", { delay: 50 });

        cy.get('input[name="cvc"]')
          .first()
          .type("123", { delay: 50 });

        // Fill postal/ZIP code if present in the Payment Element
        cy.root().then(($body) => {
          const postalInput = $body.find('input[name="postalCode"], input[name="postal"]');
          if (postalInput.length > 0) {
            cy.wrap(postalInput.first()).type("94105", { delay: 50 });
          }
        });
      });
    }
  });
});
