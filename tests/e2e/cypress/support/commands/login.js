Cypress.Commands.add("loginByApi", (username, password) => {
  cy.request({
    url: "/wp-login.php",
    method: "POST",
    form: true,
    body: {
      log: username,
      pwd: password,
      rememberme: "forever",
      testcookie: 1,
    },
  }).then((response) => {
    expect(response.status).to.eq(200);
    window.localStorage.setItem(
      "WP_DATA_USER_1", // Investigate why WP_DATA_USER_1.
      JSON.stringify({
        "core/edit-post": {
          preferences: {
            features: {
              welcomeGuide: false,
            },
          },
        },
      })
    );
  });
});

Cypress.Commands.add("loginByForm", (username, password) => {
  cy.session(["loginByForm", username, password], () => {
    cy.visit("/wp-login.php");
    // Handle both default wp-login.php and custom login page (/login/)
    cy.location("pathname").should("satisfy", (path) => {
      return (
        path.includes("/wp-login.php") ||
        path.includes("/login") ||
        path.includes("/wp-admin")
      );
    });
    cy.location("pathname").then((path) => {
      if (path.includes("/wp-admin")) {
        return;
      }

      cy.get("#rememberme").should("be.visible").and("not.be.checked").click();
      cy.get("#user_login").should("be.visible").setValue(username);
      cy.get("#user_pass")
        .should("be.visible")
        .setValue(password)
        .type("{enter}");
    });
    cy.location("pathname")
      .should("not.contain", "/wp-login.php")
      .and("not.contain", "/login")
      .and("equal", "/wp-admin/");
  });
});
