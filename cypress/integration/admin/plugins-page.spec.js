/// <reference types="cypress" />

describe('Plugins page', () => {
  it('has a settings link on the plugins page', () => {
    cy.visitAdminPage('/wp-admin/plugins.php');
    cy.get('[data-slug="simple-sales-tax"]')
      .findByRole('link', {name: 'Settings'})
      .click();
    cy.findByRole('heading', {name: 'Simple Sales Tax'}).should('exist');
  });
});
