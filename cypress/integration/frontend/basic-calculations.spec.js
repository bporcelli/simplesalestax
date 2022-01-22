/// <reference types="cypress" />

import products from '../../fixtures/products.json';

describe('Basic calculations', () => {
  const assertTaxTotal = (expectedTax) => {
    cy.contains('[data-title="Sales Tax"]', expectedTax).should('exist');
  };

  before(() => {
    cy.loginAsAdmin();
    cy.goToSettingsPage();
    cy.get('#woocommerce_wootax_show_zero_tax').select('Yes');
    cy.findByRole('button', {name: 'Save changes'}).click();
  });

  beforeEach(() => {
    cy.emptyCart();
    cy.intercept('POST', '/?wc-ajax=get_refreshed_fragments')
      .as('refreshCart');
  });

  it('calculates tax correctly for simple products', () => {
    cy.addProductToCart(products.simpleProduct.name);
    cy.visit('/cart/');
    cy.selectShippingMethod('Free shipping');
    assertTaxTotal(products.simpleProduct.expectedTax);
  });

  it('calculates tax correctly for variable products', () => {
    const variableProduct = products.variableProduct;

    variableProduct.variations.forEach((variation, variationIndex) => {
      cy.addProductToCart(variableProduct.name, variation.name);
      cy.visit('/cart/');
      cy.selectShippingMethod('Free shipping');
      assertTaxTotal(variation.expectedTax);

      if (variationIndex < variableProduct.variations.length - 1) {
        cy.emptyCart();
      }
    });
  });

  it('does not calculate tax for downloadable products', () => {
    cy.addProductToCart(products.downloadableProduct.name);
    cy.visit('/cart/');
    assertTaxTotal(products.downloadableProduct.expectedTax);
  });

  it('uses correct origin address for multi-origin products when calculating tax', () => {
    cy.intercept('POST', '/?wc-ajax=update_order_review').as('updateOrderReview');

    const toggleShipToAnotherAddress = (check) => {
      cy.findByRole('checkbox', {name: 'Ship to a different address?'}).then(($checkbox) => {
        if (check !== $checkbox.prop('checked')) {
          if (check) {
            cy.wrap($checkbox).check();
          } else {
            cy.wrap($checkbox).uncheck();
          }
        }
      });
    };

    cy.addProductToCart('Multi-origin Product');
    cy.visit('/checkout/');

    cy.get('body').then(($body) => {
      if ($body.find('#tax_exempt_checkbox').length) {
        cy.get('#tax_exempt_checkbox').uncheck();
      }
    });

    toggleShipToAnotherAddress(true);

    cy.get('#shipping_country').select('US', {force: true});
    cy.get('#shipping_address_1').clear().type('206 Washington St SW');
    cy.get('#shipping_city').clear().type('Atlanta');
    cy.get('#shipping_state').select('Georgia', {force: true});
    cy.get('#shipping_postcode').clear().type('30334');

    cy.wait('@updateOrderReview');

    cy.getTaxTotal().then((origTaxAmount) => {
      toggleShipToAnotherAddress(false);
      cy.wait('@updateOrderReview');

      cy.getTaxTotal().then((updatedTaxAmount) => {
          expect(updatedTaxAmount).not.to.eq(origTaxAmount);
      });
    });
  });

  it('calculates tax for shipping charges with default shipping TIC', () => {
    cy.addProductToCart('General Product');
    cy.visit('/cart/');
    cy.selectShippingMethod('Free shipping');
    cy.getTaxTotal().then((origTax) => {
      cy.selectShippingMethod('Flat rate');
      cy.getTaxTotal().then((newTax) => {
        expect(newTax).not.to.eq(origTax);
      });
    });
  });

  it('does not show zero tax total if "Show Zero Tax?" is set to "No"', () => {
    cy.goToSettingsPage();
    cy.get('#woocommerce_wootax_show_zero_tax').select('No');
    cy.findByRole('button', {name: 'Save changes'}).click();

    cy.addProductToCart('eBook');
    cy.visit('/cart/');

    cy.get('[data-title="Sales Tax"]').should('not.exist');
  });

  it('shows zero tax total if "Show Zero Tax?" is set to "Yes"', () => {
    cy.goToSettingsPage();
    cy.get('#woocommerce_wootax_show_zero_tax').select('Yes');
    cy.findByRole('button', {name: 'Save changes'}).click();

    cy.addProductToCart('eBook');
    cy.visit('/cart/');

    cy.get('[data-title="Sales Tax"]').should('exist');
  });
});
