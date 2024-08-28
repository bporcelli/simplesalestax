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
    cy.findByRole('button', {name: 'Save changes'}).click({ force: true });
  });

  beforeEach(() => {
    cy.loginAsAdmin();
    cy.emptyCart();
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
      if ($body.find('#certificate_id').length) {
        cy.get('#certificate_id').select('', {force: true});
        cy.waitForBlockedElements();
      }
    });

    toggleShipToAnotherAddress(true);

    cy.get('#shipping_country').select('US', {force: true});
    cy.get('#shipping_address_1').clear().type('206 Washington St SW');
    cy.get('#shipping_city').clear().type('Atlanta');
    cy.get('#shipping_state').select('Georgia', {force: true});
    cy.get('#shipping_postcode').clear().type('30334');

    cy.waitForBlockedElements();

    cy.getTaxTotal().then((origTaxAmount) => {
      toggleShipToAnotherAddress(false);
      cy.waitForBlockedElements();

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

  describe('when a negative fee is applied', () => {
    it('calculates tax for single taxable product', () => {
      cy.addProductToCart('General Product');
      cy.visit('/cart/?add_negative_fee=yes');
      cy.selectShippingMethod('Free shipping');

      // Negative fee amount: 19.99 * 0.1 = 1.999
      // Discount on simple product: 1.999 * (19.99 / 19.99) = 1.999
      // Expected tax: (19.99 - 1.999) * 0.08625 = 1.55
      assertTaxTotal(1.55);
    });

    it('calculates tax for multiple taxable products', () => {
      cy.addProductToCart('General Product');
      cy.addProductToCart('Variable Product', 'A');
      cy.visit('/cart/?add_negative_fee=yes');
      cy.selectShippingMethod('Free shipping');

      // Negative fee amount: 24.99 * 0.1 = 2.499
      // Discount on simple product: 2.499 * (19.99 / 24.99) = 1.999
      // Discount on variable product: 2.499 * (5 / 24.99) = 0.5
      // Expected tax: ((19.99 - 1.999) + (5 - 0.5)) * 0.08625 = 1.94
      assertTaxTotal(1.94);
    });

    it('calculatex tax for mix of taxable and non-taxable products', () => {
      cy.addProductToCart('General Product');
      cy.addProductToCart('eBook');
      cy.visit('/cart/?add_negative_fee=yes');
      cy.selectShippingMethod('Free shipping');

      // Negative fee amount: 29.98 * 0.1 = 2.998
      // Discount on simple product: 2.998 * (19.99 / 29.98) = 1.999
      // Expected tax: (19.99 - 1.999) * 0.08625 = 1.55
      assertTaxTotal(1.55);
    });
  });

  it('does not show zero tax total if "Show Zero Tax?" is set to "No"', () => {
    cy.goToSettingsPage();
    cy.get('#woocommerce_wootax_show_zero_tax').select('No');
    cy.findByRole('button', {name: 'Save changes'}).click({ force: true });

    cy.addProductToCart('eBook');
    cy.visit('/cart/');

    cy.get('[data-title="Sales Tax"]').should('not.exist');
  });

  it('shows zero tax total if "Show Zero Tax?" is set to "Yes"', () => {
    cy.goToSettingsPage();
    cy.get('#woocommerce_wootax_show_zero_tax').select('Yes');
    cy.findByRole('button', {name: 'Save changes'}).click({ force: true });

    cy.addProductToCart('eBook');
    cy.visit('/cart/');

    cy.get('[data-title="Sales Tax"]').should('exist');
  });
});
