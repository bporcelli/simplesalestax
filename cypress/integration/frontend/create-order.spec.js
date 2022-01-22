/// <reference types="cypress" />

describe('Create order', () => {
  before(() => {
    cy.loginAsAdmin();
  });

  const createOrder = () => {
    cy.intercept('POST', '/?wc-ajax=update_order_review').as('updateOrderReview');
    cy.intercept('POST', '/?wc-ajax=checkout').as('doCheckout');
    cy.emptyCart();
    cy.addProductToCart('General Product');
    cy.visit('/cart/');
    cy.selectShippingMethod('Flat rate');
    cy.visit('/checkout/');
    cy.wait('@updateOrderReview', {timeout: 15000});
    cy.findByRole('button', {name: 'Place order'}).click({force: true});
    cy.wait('@doCheckout', {timeout: 30000});

    const orderNumber =
      cy.contains('Order number:')
        .closest('li')
        .find('strong')
        .invoke('text');

    return orderNumber;
  };

  const editOrder = (orderId) => {
    cy.visit(`/wp-admin/post.php?action=edit&post=${orderId}`);
  };

  const round = (number) => {
    return Math.round((number + Number.EPSILON) * 100) / 100;
  };

  const getOrderSummaryTotals = () => {
    const totals = {
      'Subtotal': 'subtotal',
      'Shipping': 'shipping',
      'Sales Tax': 'salesTax',
      'Total': 'total',
    };

    Object.entries(totals).forEach(([label, alias]) => {
      cy.contains('th', `${label}:`)
        .closest('tr')
        .find('td')
        .invoke('text')
        .then((text) => {
          cy.wrap(parseFloat(text.replace(/[^\d.]/, ''))).as(alias);
        });
    });
  };

  it('saves correct tax total for order', () => {
    createOrder().then(function() {
      getOrderSummaryTotals();

      cy.then(function() {
        expect(this.total).to.eq(round(this.subtotal + this.shipping + this.salesTax));
      });
    });
  });

  it('captures Processing orders in TaxCloud if "Capture orders immediately" is enabled', () => {
    cy.goToSettingsPage();
    cy.get('#woocommerce_wootax_capture_immediately').check();
    cy.findByRole('button', {name: 'Save changes'}).click();

    createOrder().then((orderId) => {
      editOrder(orderId);
      cy.contains('TaxCloud Status')
        .closest('div')
        .contains('Captured')
        .should('exist');
    });
  });

  it('does not capture Processing orders in TaxCloud "Capture orders immediately" is disabled', () => {
    cy.goToSettingsPage();
    cy.get('#woocommerce_wootax_capture_immediately').uncheck();
    cy.findByRole('button', {name: 'Save changes'}).click();

    createOrder().then((orderId) => {
      editOrder(orderId);
      cy.contains('TaxCloud Status')
        .closest('div')
        .contains('Pending')
        .should('exist');
    });
  });
});
