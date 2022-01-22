/// <reference types="cypress" />

import coupons from '../../fixtures/coupons.json';

describe('Coupon calculations', () => {
  const applyCoupon = (couponCode) => {
    cy.intercept('POST', '/cart').as('updateCart');
    cy.get('#coupon_code').type(couponCode);
    cy.findByRole('button', {name: 'Apply coupon'}).click();
    cy.wait('@updateCart', {timeout: 15000});
  };

  const removeCoupon = () => {
    cy.get('body').then(($body) => {
      if ($body.find('.woocommerce-remove-coupon').length) {
        cy.intercept('POST', '/cart').as('updateCart');
        cy.get('.woocommerce-remove-coupon').click();
        cy.wait('@updateCart', {timeout: 15000});
      }
    });
  };

  before(() => {
    cy.addProductToCart('General Product');

    cy.visit('/cart/');

    cy.intercept('POST', '/cart').as('updateCart');
    cy.get('.shipping-calculator-button').click();
    cy.get('#calc_shipping_state').select('NY', {force: true});
    cy.get('#calc_shipping_city').clear().type('West Islip');
    cy.get('#calc_shipping_postcode').clear().type('11795');
    cy.findByRole('button', {name: 'Update'}).click();
    cy.wait('@updateCart');
  });

  beforeEach(() => {
    removeCoupon();
  });

  const couponTypes = {
    'fixed': coupons.fixed,
    'percentage': coupons.percentage,
  };

  Object.entries(couponTypes).forEach(([type, couponCode]) => {
    it(`accounts for ${type} discount coupons when calculating tax`, () => {
      cy.getTaxTotal().then((origTaxAmount) => {
        applyCoupon(couponCode);
        cy.getTaxTotal().then((newTaxAmount) => {
          expect(newTaxAmount).not.to.eq(origTaxAmount);
        });
      });
    });
  });

  it('does not calculate tax when a 100% discount coupon is applied', () => {
    applyCoupon(coupons.zero);
    cy.selectShippingMethod('Free shipping');
    cy.getTaxTotal().then((total) => {
      expect(total).matches(/0\.00$/);
    });
  });

  after(() => {
    cy.emptyCart();
  });
});
