/// <reference types="cypress" />

describe('Exemption certificates', () => {
  beforeEach(() => {
    cy.loginAsAdmin();
  });

  describe('when tax exemptions are enabled', () => {
    const alwaysRequiredFields = [
      'exempt_state',
      'tax_type',
      'id_number',
      'purchaser_business_type',
      'purchaser_exemption_reason',
    ];

    const fillBasicFields = () => {
      cy.get('#exempt_state').select('New York', {force: true});
      cy.get('#tax_type').select('Federal Employer ID', {force: true});
      cy.get('#id_number').type('123-45-6789');
      cy.get('#purchaser_business_type').select('Construction', {force: true});
      cy.get('#purchaser_exemption_reason').select('Federal Government Department', {force: true});
    };

    const validateRequiredField = (id) => {
      cy.get(`#${id}`)
        .closest('.form-row')
        .should('have.class', 'validate-required');
      cy.get(`label[for="${id}"]`)
        .invoke('text')
        .should('match', /.*\*$/);
    };

    const validateFieldHasError = (id) => {
      cy.get(`#${id}`)
        .closest('.form-row')
        .should('have.class', 'woocommerce-invalid');
    };

    before(() => {
      cy.loginAsAdmin();
      cy.goToSettingsPage();
      cy.get('#woocommerce_wootax_show_exempt').select('Yes', {force: true});
      cy.get('#woocommerce_wootax_restrict_exempt').select('No', {force: true});
      cy.findByRole('button', {name: 'Save changes'}).click();
    });

    describe('checkout', () => {
      const selectCertificate = (id) => {
        cy.get('select#certificate_id').select(id, {force: true});
        cy.wait('@updateOrderReview', {timeout: 15000});
      };

      beforeEach(() => {
        cy.intercept('POST', '/?wc-ajax=update_order_review').as('updateOrderReview');
        cy.addProductToCart('General Product');
        cy.visit('/checkout/');
      });

      it('renders tax exemption form', () => {
        cy.findByRole('heading', {name: 'Tax exemption'}).should('be.visible');
        cy.emptyCart();
      });

      it('applies a zero rate when a certificate is selected', () => {
        // Tax is applied when no certificate is selected
        selectCertificate('');
        cy.getTaxTotal().then((total) => {
          expect(total).not.to.match(/0\.00$/);
        });

        // Tax is zero when saved certificate is selected
        cy.get('select#certificate_id').then(($select) => {
          // Select first cert after "None" and "New"
          const $option = $select.find('option').eq(2);
          selectCertificate($option.val());
        });
        cy.getTaxTotal().then((total) => {
          expect(total).to.match(/0\.00$/);
        });

        // Tax is zero when a new certificate is being added
        selectCertificate('new');
        cy.getTaxTotal().then((total) => {
          expect(total).to.match(/0\.00$/);
        });

        cy.emptyCart();
      });

      const certificateTypes = [
        ['single-purchase', /single\-purchase/],
        ['entity-based', /^[0-9a-fA-F]{8}\b-[0-9a-fA-F]{4}\b-[0-9a-fA-F]{4}\b-[0-9a-fA-F]{4}\b-[0-9a-fA-F]{12}$/]
      ];

      certificateTypes.forEach(([certificateType, idPattern]) => {
        it(`allows user to add a new ${certificateType} certificate`, () => {
          selectCertificate('new');

          // Fill out form
          if (certificateType === 'single-purchase') {
            cy.get('#single_purchase').check();
          }

          fillBasicFields();

          // Checkout
          cy.intercept('POST', '/?wc-ajax=checkout').as('doCheckout');
          cy.findByRole('button', {name: 'Place order'}).click({force: true});
          cy.wait('@doCheckout', {timeout: 60000});

          // Edit order
          cy.url().then((url) => {
            const orderId = /\/(\d+)\//.exec(url)[1];
            cy.visit(`/wp-admin/admin.php?page=wc-orders&action=edit&id=${orderId}`);
          });

          // Confirm tax is zero
          cy.contains('Sales Tax:')
            .siblings('.total')
            .invoke('text')
            .should('match', /0\.00/);

          // Confirm exemption certifiate was saved
          cy.get('#exempt_cert', {timeout: 30000})
            .invoke('val')
            .should('match', idPattern);
        });
      });

      describe('validation', () => {
        beforeEach(() => {
          selectCertificate('new');
          fillBasicFields();
        });

        afterEach(() => {
          cy.emptyCart();
        });

        it('requires basic fields', () => {
          for (const field of alwaysRequiredFields) {
            // Should be labeled as required in UI
            validateRequiredField(field);

            // Should show error if field is empty
            cy.get(`#${field}`).invoke('val').then((origVal) => {
              cy.get(`#${field}`).invoke('val', '');

              cy.findByRole('button', {name: 'Place order'}).click({force: true});
              cy.waitForBlockedElements();
              cy.contains('Required exemption certificate fields are missing').should('be.visible');
              validateFieldHasError(field);

              cy.get(`#${field}`).invoke('val', origVal);
            });
          }
        });

        it('requires state when id type is state', () => {
          cy.get('#state_of_issue_field').should('not.be.visible');
          cy.get('#tax_type').select('State Issued Exemption ID or Drivers License', {force: true});
          cy.get('#state_of_issue_field').should('be.visible');

          validateRequiredField('state_of_issue');

          cy.findByRole('button', {name: 'Place order'}).click({force: true});
          cy.waitForBlockedElements();
          cy.contains('Required exemption certificate fields are missing').should('be.visible');
          validateFieldHasError('state_of_issue');
        });

        it('requires other type value when business type is other', () => {
          cy.get('#purchase_business_type_other_value').should('not.be.visible');
          cy.get('#purchaser_business_type').select('Other', {force: true});
          cy.get('#purchase_business_type_other_value').should('be.visible');

          validateRequiredField('purchase_business_type_other_value');

          cy.findByRole('button', {name: 'Place order'}).click({force: true});
          cy.waitForBlockedElements();
          cy.contains('Required exemption certificate fields are missing').should('be.visible');
          validateFieldHasError('purchase_business_type_other_value');
        });

        it('requires other reason value when exemption reason is other', () => {
          cy.get('#purchaser_exemption_reason').select('Other', {force: true});

          validateRequiredField('purchaser_exemption_reason_value');

          cy.findByRole('button', {name: 'Place order'}).click({force: true});
          cy.waitForBlockedElements();
          cy.contains('Required exemption certificate fields are missing').should('be.visible');
          validateFieldHasError('purchaser_exemption_reason_value');
        });
      });
    });

    describe('my account', () => {
      it('renders exemption certificates menu item', () => {
        cy.visit('/my-account/');
        cy.findByRole('link', {name: 'Exemption certificates'}).click();
        cy.url().should('contain', '/my-account/exemption-certificates/');
      });

      describe('exemption certificates page', () => {
        beforeEach(() => {
          cy.visit('/my-account/exemption-certificates/');
        });

        it('allows user to add a cerificate', () => {
          cy.findByRole('link', {name: 'Add Certificate'}).click();
          fillBasicFields();
          cy.get('#sst-certificates tbody tr[data-id]').its('length').as('origNumCerts');

          cy.findByRole('button', {name: 'Add certificate'}).click();
          cy.waitForBlockedElements(60000);

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
          cy.waitForBlockedElements(60000);
          cy.get('#sst-certificates tbody tr[data-id]').its('length').then(function(newNumCerts) {
            expect(newNumCerts).to.eq(this.origNumCerts - 1);
          });
        });

        describe('validation', () => {
          beforeEach(() => {
            cy.findByRole('link', {name: 'Add Certificate'}).click();
            fillBasicFields();
          });

          it('requires basic fields', () => {
            for (const field of alwaysRequiredFields) {
              // Should be labeled as required in UI
              validateRequiredField(field);

              // Should show error if field is empty
              cy.get(`#${field}`).invoke('val').then((origVal) => {
                cy.get(`#${field}`).invoke('val', '');

                cy.findByRole('button', {name: 'Add certificate'}).click();
                cy.waitForBlockedElements(60000);
                cy.get('.blockOverlay').should('not.exist');
                validateFieldHasError(field);

                cy.get(`#${field}`).invoke('val', origVal);
              });
            }
          });

          it('requires state when id type is state', () => {
            cy.get('#state_of_issue_field').should('not.be.visible');
            cy.get('#tax_type').select('State Issued Exemption ID or Drivers License', {force: true});
            cy.get('#state_of_issue_field').should('be.visible');

            validateRequiredField('state_of_issue');

            cy.findByRole('button', {name: 'Add certificate'}).click();
            cy.get('.blockOverlay').should('not.exist');
            validateFieldHasError('state_of_issue');
          });

          it('requires other type value when business type is other', () => {
            cy.get('#purchase_business_type_other_value').should('not.be.visible');
            cy.get('#purchaser_business_type').select('Other', {force: true});
            cy.get('#purchase_business_type_other_value').should('be.visible');

            validateRequiredField('purchase_business_type_other_value');

            cy.findByRole('button', {name: 'Add certificate'}).click();
            cy.get('.blockOverlay').should('not.exist');
            validateFieldHasError('purchase_business_type_other_value');
          });

          it('requires other reason value when exemption reason is other', () => {
            cy.get('#purchaser_exemption_reason').select('Other', {force: true});

            validateRequiredField('purchaser_exemption_reason_value');

            cy.findByRole('button', {name: 'Add certificate'}).click();
            cy.get('.blockOverlay').should('not.exist');
            validateFieldHasError('purchaser_exemption_reason_value');
          });
        });
      });
    });
  });

  describe('when "restrict to exempt roles" is enabled', () => {
    describe('when user has exempt role', () => {
      before(() => {
        cy.loginAsAdmin();
        cy.goToSettingsPage();
        cy.get('#woocommerce_wootax_show_exempt').select('Yes', {force: true});
        cy.get('#woocommerce_wootax_restrict_exempt').select('Yes', {force: true});
        cy.get('#woocommerce_wootax_exempt_roles').select('Administrator', {force: true});
        cy.findByRole('button', {name: 'Save changes'}).click();
      });

      it('renders tax exemption form on checkout page', () => {
        cy.addProductToCart('General Product');
        cy.visit('/checkout/');
        cy.findByRole('heading', {name: 'Tax exemption'}).should('be.visible');
        cy.emptyCart();
      });

      it('renders exemption certificates menu item in my account', () => {
        cy.visit('/my-account/');
        cy.findByRole('link', {name: 'Exemption certificates'}).should('be.visible');
      });
    });

    describe('when user does not have exempt role', () => {
      before(() => {
        cy.loginAsAdmin();
        cy.goToSettingsPage();
        cy.get('#woocommerce_wootax_show_exempt').select('Yes', {force: true});
        cy.get('#woocommerce_wootax_restrict_exempt').select('Yes', {force: true});
        cy.get('#woocommerce_wootax_exempt_roles').select('Exempt Customer', {force: true});
        cy.findByRole('button', {name: 'Save changes'}).click();
      });

      it('does not render exemption form on checkout page', () => {
        cy.addProductToCart('General Product');
        cy.visit('/checkout/');
        cy.findByRole('heading', {name: 'Tax exemption'}).should('not.exist');
        cy.emptyCart();
      });

      it('does not render exemption certificates menu item in my account', () => {
        cy.visit('/my-account/');
        cy.findByRole('link', {name: 'Exemption certificates'}).should('not.exist');
      });
    });
  });

  describe('when tax exemptions are disabled', () => {
    before(() => {
      cy.loginAsAdmin();
      cy.goToSettingsPage();
      cy.get('#woocommerce_wootax_show_exempt').select('No', {force: true});
      cy.findByRole('button', {name: 'Save changes'}).click();
    });

    it('does not render tax exemption form on checkout page', () => {
      cy.addProductToCart('General Product');
      cy.visit('/checkout/');
      cy.findByRole('heading', {name: 'Tax exemption'}).should('not.exist');
      cy.emptyCart();
    });

    it('does not render exemption certificates menu item in my account', () => {
      cy.visit('/my-account/');
      cy.findByRole('link', {name: 'Exemption certificates'}).should('not.exist');
    });
  });
});
