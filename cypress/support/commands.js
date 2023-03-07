import '@testing-library/cypress/add-commands';

Cypress.Commands.add('visitAdminPage', (pagePath = '/wp-admin/') => {
  cy.session('admin', () => {
    cy.visit(pagePath);
    cy.get('body').then(($body) => {
      if ($body.hasClass('login')) {
        cy.findByRole('textbox', {name: 'Username or Email Address'})
          .clear()
          .type('admin');
        cy.findByRole('button', {name: 'Show password'}).click();
        cy.findByRole('textbox', {name: 'Password'}).type('password');
        cy.findByRole('button', {name: 'Log In'}).click();
      }
    });
  });
});

Cypress.Commands.add('loginAsAdmin', () => {
  cy.session('admin', () => {
    cy.visit('/wp-login.php');
    cy.findByRole('textbox', {name: 'Username or Email Address'})
      .clear()
      .type('admin');
    cy.findByRole('button', {name: 'Show password'}).click();
    cy.findByRole('textbox', {name: 'Password'}).type('password');
    cy.findByRole('button', {name: 'Log In'}).click();
  }, {cacheAcrossSpecs: true});
});

Cypress.Commands.add('goToSettingsPage', () => {
  cy.visit('/wp-admin/admin.php?page=wc-settings&tab=integration&section=wootax');
});

Cypress.Commands.add('addProductToCart', (productName, variation = '') => {
  cy.visit('/');
  cy.get('main').contains('a', productName).click();

  if (variation) {
    cy.findByRole('combobox', {name: 'Variation'}).select(variation);
  }

  cy.findByRole('button', {name: 'Add to cart'}).click();
});

Cypress.Commands.add('emptyCart', () => {
  cy.visit('/cart/');
  cy.get('body').then(($body) => {
    const $qtyInputs = $body.find('input.qty');
    if ($qtyInputs.length > 0) {
      $qtyInputs.each(function() {
        cy.wrap(this).clear().type('0');
      });
      cy.findByRole('button', {name: 'Update cart'}).click();
    }
  });
});

Cypress.Commands.add('getTaxTotal', () => {
  return cy.contains('th', 'Sales Tax').next('td').invoke('text');
});

Cypress.Commands.add('selectShippingMethod', (methodName) => {
  cy.contains('label', methodName).prev('input').then(($input) => {
    if (!$input.is(':checked')) {
      cy.intercept('POST', '/?wc-ajax=update_shipping_method')
        .as('updateShippingMethod');
      cy.wrap($input).check();
      cy.wait('@updateShippingMethod');
      cy.waitForBlockedElements();
    }
  });
});

Cypress.Commands.add('waitForBlockedElements', (timeout = 30000) => {
  cy.get('.blockOverlay', {timeout}).should('not.exist');
});
