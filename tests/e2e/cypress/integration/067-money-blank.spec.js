/* global cy, Cypress, describe, beforeEach, it, expect */
describe('Optional money values', () => {
	beforeEach(() => {
		cy.loginByApi(Cypress.env('admin').username, Cypress.env('admin').password);
		cy.visit('/wp-admin/network/admin.php?page=wp-ultimo-edit-product');
		cy.window().should('have.property', 'Vue');
		cy.window().then((win) => {
			const container = win.document.createElement('div');
			win.document.body.appendChild(container);
			win.money_blank_test = new win.Vue({
				el: container,
				data: {
					optional: '',
					zero: 0,
					normal: '',
					amount: 1234.5,
					stored_amount: '1234.5',
					settings: { prefix: '€ ', suffix: '', decimal: '.', thousands: ',', precision: 2 }
				},
				template: `<form id="money-blank-test">
					<money name="optional" v-model="optional" :allow_blank="true" v-bind="settings"></money>
					<money name="zero" v-model="zero" :allow_blank="true" v-bind="settings"></money>
					<money name="normal" v-model="normal" v-bind="settings"></money>
					<money name="amount" v-model="amount" :allow_blank="true" v-bind="settings"></money>
					<money name="stored_amount" :value="stored_amount" :allow_blank="true" v-bind="settings"></money>
				</form>`
			});
			// Keep the component fixture above WordPress's fixed admin UI.
			win.money_blank_test.$el.style.cssText = 'position:fixed;left:200px;top:50px;z-index:100000;display:grid;gap:8px;padding:16px;background:white';
		});
	});

	it('preserves blanks, explicit zero, formatting and ordinary field behavior', () => {
		cy.get('#money-blank-test [name="optional"]').should('have.value', '');
		cy.get('#money-blank-test [name="zero"]').should('have.value', '€ 0.00');
		cy.get('#money-blank-test [name="normal"]').should('have.value', '€ 0.00');
		cy.get('#money-blank-test [name="amount"]').should('have.value', '€ 1,234.50');
		cy.get('#money-blank-test [name="stored_amount"]').should('have.value', '€ 1,234.50');

		cy.get('#money-blank-test [name="optional"]')
			.invoke('val', '1234.50').trigger('input').should('have.value', '€ 1,234.50');
		cy.window().its('money_blank_test.optional').should('equal', 1234.5);
		cy.get('#money-blank-test [name="optional"]').clear().should('have.value', '');
		cy.window().its('money_blank_test.optional').should('equal', '');
		cy.window().then((win) => {
			const submitted = new win.FormData(win.document.getElementById('money-blank-test'));
			expect(submitted.get('optional')).to.equal('');
		});

		cy.get('#money-blank-test [name="optional"]').type('0').should('have.value', '€ 0.00');
		cy.window().its('money_blank_test.optional').should('equal', 0);
		cy.window().then((win) => {
			win.money_blank_test.optional = '';
		});
		cy.get('#money-blank-test [name="optional"]').should('have.value', '');
		cy.window().then((win) => {
			win.money_blank_test.optional = null;
		});
		cy.get('#money-blank-test [name="optional"]').should('have.value', '');
		cy.window().then((win) => {
			win.money_blank_test.optional = undefined;
		});
		cy.get('#money-blank-test [name="optional"]').should('have.value', '');
		cy.get('#money-blank-test [name="normal"]').clear().should('have.value', '€ 0.00');
		cy.window().its('money_blank_test.normal').should('equal', 0);
	});
});
