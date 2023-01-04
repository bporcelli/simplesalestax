/// <reference types="cypress" />

describe('Edit product page', () => {
  const selectTIC = (name) => {
    cy.contains('label', 'TIC').siblings('.sst-select-tic').click();
    cy.contains('.tic-row', name).find('button').click();
  };

  const saveProduct = () => {
    cy.findByRole('button', {name: 'Update'}).click();
  };

  it('allows user to set the product TIC', () => {
    cy.loginAsAdmin();
    cy.visit('/wp-admin/edit.php?post_type=product');
    cy.findByRole('link', {name: 'General Product'}).click();

    // Make sure we start with a different TIC selected.
    cy.contains('.sst-selected-tic', 'Gift card').should('not.exist');

    selectTIC('Gift card');
    saveProduct();

    // Make sure TIC is saved correctly.
    cy.contains('Product updated').should('be.visible');
    cy.contains('.sst-selected-tic', 'Gift card').should('be.visible');

    // Reset TIC.
    selectTIC('Uncategorized');
    saveProduct();
  });
});
