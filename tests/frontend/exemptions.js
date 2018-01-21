import config from 'config';
import chai from 'chai';
import chaiAsPromised from 'chai-as-promised';
import test from 'selenium-webdriver/testing';
import { By } from 'selenium-webdriver';
import { WebDriverManager, WebDriverHelper as helper } from 'wp-e2e-webdriver';
import { CustomerFlow, GuestCustomerFlow, Helper } from 'wc-e2e-page-objects';
import * as SSTHelper from '../helper';

chai.use( chaiAsPromised );
const assert = chai.assert;

let manager;
let driver;

const CHECKBOX_SELECTOR = By.css( '#tax_exempt_checkbox' );
const MESSAGE_SELECTOR = By.css( '#tax_details p' );
const TABLE_SELECTOR = By.css( '#tax_details table' );
const ADD_BTN_SELECTOR = By.css( '.sst-certificate-add' );
const VIEW_BTN_SELECTOR = By.xpath( `(//a[@class="sst-certificate-view"])[last()]` );
const DELETE_BTN_SELECTOR = By.xpath( `(//a[@class="sst-certificate-delete"])[last()]` );
const MODAL_SELECTOR = By.css( 'div.sst-certificate-modal-content' );
const STATE_SELECTOR = By.css( 'select#ExemptState' );
const TAX_ID_TYPE_SELECTOR = By.css( 'select#TaxType' );
const TAX_ID_SELECTOR = By.css( 'input#IDNumber' );
const ISSUING_STATE_SELECTOR = By.css( 'select#issuing-state' );
const BIZ_TYPE_SELECTOR = By.css( 'select#PurchaserBusinessType' );
const EXEMPT_REASON_SELECTOR = By.css( 'select#PurchaserExemptionReason' );
const REASON_VALUE_SELECTOR = By.css( 'input#exempt-other-reason' );
const SUBMIT_BTN_SELECTOR = By.css( 'button#btn-ok' );
const CERTIFICATE_SELECTOR = By.css( 'table#sst-certificates tbody tr[data-id]' );
const RADIO_SELECTOR = By.css( 'input[type="radio"][name="certificate_id"]:not(:checked)' );

test.describe( 'Exemption Tests', function() {
    const assertTaxApplied = () => {
        assert.eventually.ok( SSTHelper.taxApplied( driver ) );
    };
    const assertTaxNotApplied = () => {
        assert.eventually.ok( SSTHelper.taxNotApplied( driver ) );
    };
    const removeProductsFromCart = ( customer, ...products ) => {
        SSTHelper.removeProductsFromCart( driver, customer, products );
    };
    const populateAndSubmitForm = () => {
        assert.eventually.ok( helper.selectOption( driver, STATE_SELECTOR, 'New York' ) );
        assert.eventually.ok( helper.selectOption( driver, TAX_ID_TYPE_SELECTOR, 'State Issued Exemption ID or Drivers License' ) );
        assert.eventually.ok( helper.setWhenSettable( driver, TAX_ID_SELECTOR, '12-34-5678' ) );
        assert.eventually.ok( helper.selectOption( driver, ISSUING_STATE_SELECTOR, 'New York' ) );
        assert.eventually.ok( helper.selectOption( driver, BIZ_TYPE_SELECTOR, 'Construction' ) );
        assert.eventually.ok( helper.selectOption( driver, EXEMPT_REASON_SELECTOR, 'Resale' ) );
        assert.eventually.ok( helper.setWhenSettable( driver, REASON_VALUE_SELECTOR, 'R-123456' ) );
        assert.eventually.ok( helper.clickWhenClickable( driver, SUBMIT_BTN_SELECTOR ) );
    };
    const toggleTaxExempt = () => {
        assert.eventually.ok( helper.setCheckbox( driver, CHECKBOX_SELECTOR ) );
        assert.eventually.ok( Helper.waitTillUIBlockNotPresent( driver ) );
    };
    const openModal = ( modal ) => {
        let btnSelector;
        
        if ( 'add' === modal ) {
            btnSelector = ADD_BTN_SELECTOR;
        } else if ( 'view' === modal ) {
            btnSelector = VIEW_BTN_SELECTOR;
        }
        
        toggleTaxExempt();

        assert.eventually.ok( helper.clickWhenClickable( driver, btnSelector ) );
        assert.eventually.ok( helper.isEventuallyPresentAndDisplayed( driver, MODAL_SELECTOR ) );
    };
    const getNumCertificates = () => {
        toggleTaxExempt();

        return driver.findElements( CERTIFICATE_SELECTOR ).then( ( elements ) => {
            return elements.length;
        } );
    };
    const ensureAtLeastOneCertificate = () => {
        return getNumCertificates().then( num => {
            if ( num < 1 ) {  // No certificates added -- add one
                openModal( 'add' );
                populateAndSubmitForm();                
                return Helper.waitTillUIBlockNotPresent( driver ).then( () => {
                    return true;
                }, () => {
                    return false;
                } );
            }
            return true;
        }, () => {
            return false;
        } );
    };

    // Set up the driver and manager before testing starts.
    test.before( () => {
        this.timeout( config.get( 'startBrowserTimeoutMs' ) );

        manager = new WebDriverManager( 'chrome', { baseUrl: config.get( 'url' ) } );        
        driver = manager.getDriver();

        helper.clearCookiesAndDeleteLocalStorage( driver );
    } );

    this.timeout( config.get( 'mochaTimeoutMs' ) );

    test.it( 'requires the user to log in to claim a tax exemption', () => {
        let guest = new GuestCustomerFlow( driver, { baseUrl: config.get( 'url' ) } );
        
        guest.fromShopAddProductsToCart( 'General Product' );
        guest.openCheckout();

        const msgShown = helper.setCheckbox( driver, CHECKBOX_SELECTOR ).then( () => {
            return driver.findElement( MESSAGE_SELECTOR ).then( element => {
                return element.getAttribute( 'textContent' ).then( text => {
                    return text === 'Please log in or register.';
                } );
            } );
        } );
        assert.eventually.equal( msgShown, true, 'checkbox hidden or message wrong' );

        helper.clearCookiesAndDeleteLocalStorage( driver );
    } );

    test.it( 'displays a table of exemption certificates', () => {
        let customer = SSTHelper.loginAsCustomer( driver );

        customer.fromShopAddProductsToCart( 'General Product' );
        customer.openCheckout();

        const tableShown = helper
            .setCheckbox( driver, CHECKBOX_SELECTOR )
            .then( () => {
                return helper.isEventuallyPresentAndDisplayed( driver, TABLE_SELECTOR );
            } );
        assert.eventually.equal( tableShown, true, 'certificate table missing' );

        removeProductsFromCart( customer, 'General Product' );
    } );

    test.it( 'allows the customer to add an exemption certificate', () => {
        let customer = SSTHelper.loginAsCustomer( driver );

        customer.fromShopAddProductsToCart( 'General Product' );
        customer.openCheckout();
        assert.eventually.ok( Helper.waitTillUIBlockNotPresent( driver ) );
        
        assertTaxApplied();
        openModal( 'add' );
        populateAndSubmitForm();

        Helper.waitTillUIBlockNotPresent( driver ).then( () => {
            assertTaxNotApplied();
        } ).finally( () => {
            removeProductsFromCart( customer, 'General Product' );
        } );
    } );

    test.it( 'allows the customer to view an exemption certificate', () => {
        let customer = SSTHelper.loginAsCustomer( driver );

        customer.fromShopAddProductsToCart( 'General Product' );
        customer.openCheckout();

        assert.eventually.ok( Helper.waitTillUIBlockNotPresent( driver ) );
        assert.eventually.ok( ensureAtLeastOneCertificate() );
        
        openModal( 'view' );

        removeProductsFromCart( customer, 'General Product' );
    } );

    test.it( 'allows the customer to remove an exemption certificate', () => {
        let customer = SSTHelper.loginAsCustomer( driver );

        customer.fromShopAddProductsToCart( 'General Product' );
        customer.openCheckout();

        assert.eventually.ok( Helper.waitTillUIBlockNotPresent( driver ) );
        assert.eventually.ok( ensureAtLeastOneCertificate() );

        const deleted = getNumCertificates().then( initNum => {
            assert.eventually.ok( helper.clickWhenClickable( driver, DELETE_BTN_SELECTOR ) );
            assert.eventually.ok( Helper.waitTillAlertAccepted( driver ) );

            return Helper.waitTillUIBlockNotPresent( driver ).then( () => {
                return getNumCertificates().then( finalNum => {
                    return finalNum === initNum - 1;
                } );
            } );
        } );
        
        assert.eventually.ok( deleted );
    } );

    test.it( 'allows the customer to change the applied certificate', () => {
        let customer = SSTHelper.loginAsCustomer( driver );

        customer.fromShopAddProductsToCart( 'General Product' );
        customer.openCheckout();
        assert.eventually.ok( Helper.waitTillUIBlockNotPresent( driver ) );

        // Ensure at least 2 certificates are available
        assert.eventually.ok( ensureAtLeastOneCertificate() );        
        
        const hasAtLeastTwoCerts = getNumCertificates().then( num => {
            if ( num < 2 ) {
                openModal( 'add' );
                populateAndSubmitForm();
            }
            return true;
        } );
        assert.eventually.equal( hasAtLeastTwoCerts, true );

        // Confirm that tax is still removed after selecting a new certificate
        assert.eventually.ok( Helper.waitTillUIBlockNotPresent( driver ) );
        assert.eventually.ok( helper.clickWhenClickable( driver, RADIO_SELECTOR ) );
        assert.eventually.ok( Helper.waitTillUIBlockNotPresent( driver ) );

        assertTaxNotApplied();
    } );

    // Close the browser after finished testing.
    test.after( () => {
        manager.quitBrowser();
    } );

} );