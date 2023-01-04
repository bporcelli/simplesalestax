/// <reference types="cypress" />

describe('Certificate settings', () => {
  beforeEach(() => {
    cy.loginAsAdmin();
    cy.addProductToCart('General Product');
  });

  afterEach(() => {
    cy.emptyCart();
  });

  it('shows certificate manager when tax exemptions are enabled', () => {
    cy.goToSettingsPage();
    cy.get('#woocommerce_wootax_show_exempt').select('Yes', {force: true});
    cy.get('#woocommerce_wootax_restrict_exempt').select('No', {force: true});
    cy.findByRole('button', {name: 'Save changes'}).click();
    cy.visit('/checkout/');
    cy.contains('Tax exempt?').should('be.visible');
  });

  it('hides certificate manager when tax exemptions are disabled', () => {
    cy.goToSettingsPage();
    cy.get('#woocommerce_wootax_show_exempt').select('No', {force: true});
    cy.findByRole('button', {name: 'Save changes'}).click();
    cy.visit('/checkout/');
    cy.contains('Tax exempt?').should('not.exist');
  });

  it('respects the "Restrict to exempt roles" setting', () => {
    // Should only show to Exempt Roles if set to "Yes".
    cy.goToSettingsPage();
    cy.get('#woocommerce_wootax_show_exempt').select('Yes', {force: true});
    cy.get('#woocommerce_wootax_restrict_exempt').select('Yes', {force: true});
    cy.get('#woocommerce_wootax_exempt_roles').select(['Exempt Customer'], {force: true});
    cy.findByRole('button', {name: 'Save changes'}).click();
    cy.visit('/checkout/');
    cy.contains('Tax exempt?').should('not.exist');

    // Should show to all users if set to "No".
    cy.goToSettingsPage();
    cy.get('#woocommerce_wootax_restrict_exempt').select('No', {force: true});
    cy.get('#woocommerce_wootax_exempt_roles').select('Administrator', {force: true});
    cy.findByRole('button', {name: 'Save changes'}).click();
    cy.visit('/checkout/');
    cy.contains('Tax exempt?').should('be.visible');
    cy.get('#tax_exempt_checkbox').should('be.checked');

    // Reset when done.
    cy.goToSettingsPage();
    cy.get('#woocommerce_wootax_exempt_roles').select(['Exempt Customer'], {force: true});
    cy.findByRole('button', {name: 'Save changes'}).click();
  });

  describe('Add, edit, and delete exemption certificate', () => {
    beforeEach(() => {
      cy.visit('/checkout/');
      cy.get('#tax_exempt_checkbox').check();
    });

    it('allows user to add a cerificate', () => {
      cy.findByRole('link', {name: 'Add Certificate'}).click();
      cy.get('#ExemptState').select('New York');
      cy.get('#TaxType').select('Federal Employer ID');
      cy.get('#IDNumber').type('123-45-6789');
      cy.get('#PurchaserBusinessType').select('Construction');
      cy.get('#PurchaserExemptionReason').select('Federal Government Department');
      cy.get('#exempt-other-reason').type('Test');
      cy.get('#sst-certificates tbody tr[data-id]').its('length').as('origNumCerts');

      cy.findByRole('button', {name: 'Add certificate'}).click();
      cy.waitForBlockedElements();

      cy.get('#sst-certificates tbody tr[data-id]').its('length').then(function(newNumCerts) {
        expect(newNumCerts).to.eq(parseInt(this.origNumCerts) + 1);
      });
    });

    it('allows user to view the cerificate', () => {
      cy.get('#sst-certificates tbody tr[data-id]')
        .last()
        .findByRole('button', {name: 'View'})
        .click();
      cy.findByRole('heading', {name: 'View certificate'});
      cy.get('button.modal-close').click();
    });

    it('allows user to delete the ceriticate', () => {
      cy.get('#sst-certificates tbody tr[data-id]').its('length').as('origNumCerts');
      cy.get('#sst-certificates tbody tr[data-id]')
        .last()
        .findByRole('button', {name: 'Delete'})
        .click();
      cy.waitForBlockedElements();
      cy.get('#sst-certificates tbody tr[data-id]').its('length').then(function(newNumCerts) {
        expect(newNumCerts).to.eq(this.origNumCerts - 1);
      });
    });
  });

  it('toggles tax calculations based on whether "Tax exempt?" is checked', () => {
    cy.visit('/checkout/');

    cy.get('#tax_exempt_checkbox').uncheck();
    cy.waitForBlockedElements();
    cy.getTaxTotal().then((total) => {
      expect(total).not.to.match(/0\.00$/);
    });

    cy.get('#tax_exempt_checkbox').check();
    cy.waitForBlockedElements();
    cy.getTaxTotal().then((total) => {
      expect(total).to.match(/0\.00$/);
    });
  });
});
