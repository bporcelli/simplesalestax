import config from 'config';
import chai from 'chai';
import chaiAsPromised from 'chai-as-promised';
import test from 'selenium-webdriver/testing';

// Helper objects for performing actions.
import { WebDriverManager, WebDriverHelper as helper } from 'wp-e2e-webdriver';

// We're going to use the ShopPage and CartPage objects for this tutorial.
import { ShopPage, CartPage } from 'wc-e2e-page-objects';

chai.use( chaiAsPromised );
const assert = chai.assert;

let manager;
let driver;

test.describe( 'Exemption Tests', function() {

    // Set up the driver and manager before testing starts.
    test.before( () => {
        this.timeout( config.get( 'startBrowserTimeoutMs' ) );

        manager = new WebDriverManager( 'chrome', { baseUrl: config.get( 'url' ) } );        
        driver = manager.getDriver();

        helper.clearCookiesAndDeleteLocalStorage( driver );
    } );

    this.timeout( config.get( 'mochaTimeoutMs' ) );

    test.it( 'Shows the "Tax exempt?" check box when tax exemptions are enabled.', () => {
        // todo: part of checklist item 6
    } );

    test.it( 'Allows the customer to view their exemption certificates.', () => {
        // todo: part of checklist item 7
    } );

    test.it( 'Allows the customer to add an exemption certificate.', () => {
        // todo: part of checklist item 7
    } );

    test.it( 'Allows the customer to edit an exemption certificate.', () => {
        // todo: part of checklist item 7
    } );

    test.it( 'Allows the customer to remove an exemption certificate.', () => {
        // todo: part of checklist item 7
    } );

    test.it( 'Respects the certificate applied by the customer.', () => {
        // todo: part of checklist item 7
    } );

    // Close the browser after finished testing.
    test.after( () => {
        manager.quitBrowser();
    } );

} );