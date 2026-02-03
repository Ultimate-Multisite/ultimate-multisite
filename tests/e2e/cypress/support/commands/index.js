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
