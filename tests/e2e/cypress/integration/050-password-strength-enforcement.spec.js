describe("Password Strength Enforcement", () => {
	/**
	 * Helper: set the password strength setting via WP-CLI fixture.
	 */
	function setPasswordStrength(level) {
		const containerPath =
			"/var/www/html/wp-content/plugins/ultimate-multisite/tests/e2e/cypress/fixtures/set-password-strength.php";
		cy.exec(
			`pnpm exec wp-env run tests-cli wp eval-file ${containerPath} -- ${level}`,
			{ timeout: 60000 }
		).then((result) => {
			const data = JSON.parse(result.stdout.trim());
			expect(data.setting).to.equal(level);
		});
	}

	/**
	 * Helper: visit the register page with a fresh browser state.
	 */
	function visitRegisterPage() {
		cy.clearCookies();
		cy.visit("/register", { failOnStatusCode: false });

		// Wait for checkout form to render
		cy.get("#field-password", { timeout: 30000 }).should("be.visible");
		cy.wait(2000);
	}

	/**
	 * Helper: type a password and return validation state from Vue.
	 */
	function typePasswordAndGetState(password) {
		cy.get("#field-password").clear().type(password);
		cy.wait(500); // Allow strength checker to process

		return cy.window().then((win) => {
			const vue = win.document.querySelector("#wu_form").__vue__;
			const score = win.wp.passwordStrength.meter(password, [], "");
			const label = win.document.querySelector(
				"#pass-strength-result"
			).textContent;

			return {
				valid_password: vue.valid_password,
				zxcvbn_score: score,
				strength_label: label,
				minStrength: vue.password_strength_checker
					? vue.password_strength_checker.options.minStrength
					: null,
			};
		});
	}

	// ─────────────────────────────────────────────────
	// Test: Strong setting (default)
	// ─────────────────────────────────────────────────
	describe("Strong setting (zxcvbn score >= 4)", () => {
		before(() => {
			setPasswordStrength("strong");
		});

		beforeEach(() => {
			visitRegisterPage();
		});

		it("should reject a medium-strength password (score 3)", () => {
			typePasswordAndGetState("Summer2025!xyz").then((state) => {
				expect(state.zxcvbn_score).to.equal(3);
				expect(state.valid_password).to.equal(false);
				expect(state.minStrength).to.equal(4);
				expect(state.strength_label).to.equal("Medium");
			});
		});

		it("should accept a strong password (score 4)", () => {
			typePasswordAndGetState("correct horse battery").then((state) => {
				expect(state.zxcvbn_score).to.equal(4);
				expect(state.valid_password).to.equal(true);
				expect(state.strength_label).to.equal("Strong");
			});
		});

		it("should reject a weak password (score 2)", () => {
			typePasswordAndGetState("Butterfly923!").then((state) => {
				expect(state.zxcvbn_score).to.be.lessThan(3);
				expect(state.valid_password).to.equal(false);
			});
		});
	});

	// ─────────────────────────────────────────────────
	// Test: Super Strong setting (score >= 4 + char rules)
	// ─────────────────────────────────────────────────
	describe("Super Strong setting (score >= 4 + character rules)", () => {
		before(() => {
			setPasswordStrength("super_strong");
		});

		beforeEach(() => {
			visitRegisterPage();
		});

		it("should reject a score-3 password even with all character types", () => {
			typePasswordAndGetState("Summer2025!xyz").then((state) => {
				expect(state.zxcvbn_score).to.equal(3);
				expect(state.valid_password).to.equal(false);
				// Should show Medium, not Super Strong
				expect(state.strength_label).to.not.contain("Super Strong");
			});
		});

		it("should reject a score-4 password missing character types", () => {
			// score 4 but no uppercase, numbers, or special chars
			typePasswordAndGetState("correct horse battery").then((state) => {
				expect(state.zxcvbn_score).to.equal(4);
				expect(state.valid_password).to.equal(false);
				expect(state.strength_label).to.contain("Required:");
			});
		});

		it("should reject a password shorter than 12 characters", () => {
			// Strong score but too short for super_strong
			typePasswordAndGetState("xK9#mL2$vN").then((state) => {
				expect(state.valid_password).to.equal(false);
			});
		});

		it("should accept a password with score 4 and all character types", () => {
			typePasswordAndGetState("xK9#mL2$vN5@qR").then((state) => {
				expect(state.zxcvbn_score).to.equal(4);
				expect(state.valid_password).to.equal(true);
				expect(state.strength_label).to.equal("Super Strong");
			});
		});
	});

	// ─────────────────────────────────────────────────
	// Test: Medium setting (score >= 3)
	// ─────────────────────────────────────────────────
	describe("Medium setting (zxcvbn score >= 3)", () => {
		before(() => {
			setPasswordStrength("medium");
		});

		beforeEach(() => {
			visitRegisterPage();
		});

		it("should accept a medium-strength password (score 3)", () => {
			typePasswordAndGetState("Summer2025!xyz").then((state) => {
				expect(state.zxcvbn_score).to.equal(3);
				expect(state.valid_password).to.equal(true);
				expect(state.minStrength).to.equal(3);
			});
		});

		it("should reject a weak password (score 2)", () => {
			typePasswordAndGetState("Butterfly923!").then((state) => {
				expect(state.zxcvbn_score).to.be.lessThan(3);
				expect(state.valid_password).to.equal(false);
			});
		});
	});

	// ─────────────────────────────────────────────────
	// Cleanup: restore to strong (default)
	// ─────────────────────────────────────────────────
	after(() => {
		setPasswordStrength("strong");
	});
});
