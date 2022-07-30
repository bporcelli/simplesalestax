/// <reference types="cypress" />

describe('Settings page', () => {
  before(() => {
    cy.goToSettingsPage();
  });

  it('has a heading Simple Sales Tax', () => {
    cy.findByRole('heading', {name: 'Simple Sales Tax'}).should('exist');
  });

  it('has a working Verify Settings button', () => {
    cy.intercept('POST', '/wp-admin/admin-ajax.php', (req) => {
      if (req.body.includes('sst_verify_taxcloud')) {
        req.alias = 'verifyRequest';
      }
    });
    cy.findByRole('button', {name: 'Verify Settings'}).click();
    cy.wait('@verifyRequest', {timeout: 20000});
    cy.on('window:alert', (text) => {
      expect(text).to.eq('Success! Your TaxCloud settings are valid.');
    });
  });

  it('has a working Download Log button', () => {
    cy.intercept('*&download_debug_report=1').as('downloadRequest');
    cy.findByRole('link', {name: 'Download'}).click();
    cy.wait('@downloadRequest', {timeout: 20000}).then((intercepted) => {
      expect(intercepted.response.statusCode).to.eq(200);
      expect(intercepted.response.headers['content-disposition']).to.match(/filename=sst_debug_report_(.*).txt$/);
    });
  });
});
