/// <reference types="cypress" />

import products from '../../fixtures/products.json';

describe('Coupon calculations', () => {
  const testCases = [
    {
      label: 'classic cart/checkout',
      useClassicCart: true,
      cartUrl: '/legacy-cart/',
    },
    {
      label: 'block cart/checkout',
      useClassicCart: false,
      cartUrl: '/cart/',
    },
  ];

  testCases.forEach(({ label, useClassicCart, cartUrl }) => {
    describe(label, () => {
      before(() => {
        cy.loginAsAdmin();
        cy.useClassicCart(useClassicCart);
        cy.goToSettingsPage();

        if (useClassicCart) {
          cy.get('#woocommerce_wootax_show_zero_tax').select('Yes');
          cy.findByRole('button', {name: 'Save changes'}).click({ force: true });
        }
      });

      beforeEach(() => {
        cy.loginAsAdmin();
        cy.resetCart();
        cy.addProductToCart(products.simpleProduct.id);
      });

      const coupons = [
        {
          type: 'fixed',
          code: '5off',
          expectedTax: 1.29,
        },
        {
          type: 'percentage',
          code: '50off',
          expectedTax: 0.86,
        }
      ];

      coupons.forEach(({ type, code, expectedTax }) => {
        it(`accounts for ${type} discount coupons when calculating tax`, () => {
          cy.applyCoupon(code);
          cy.visit(cartUrl);
          cy.selectShippingMethod('Free shipping');
          cy.assertTaxTotal(expectedTax);
        });
      });

      it('does not calculate tax when a 100% discount coupon is applied', () => {
        cy.applyCoupon('zero');
        cy.visit(cartUrl);
        cy.selectShippingMethod('Free shipping');

        if (useClassicCart) {
          cy.assertTaxTotal(0.00);
        } else {
          cy.findByText('Sales Tax').should('not.exist');
        }
      });
    });
  });
});
