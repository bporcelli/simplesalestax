import config from 'config';
import chai from 'chai';
import chaiAsPromised from 'chai-as-promised';
import test from 'selenium-webdriver/testing';
import { By } from 'selenium-webdriver';
import { WebDriverManager, WebDriverHelper as helper } from 'wp-e2e-webdriver';
import { CustomerFlow, Helper, CheckoutOrderReceivedPage } from 'wc-e2e-page-objects';

let manager;
let driver;
let customer;

chai.use( chaiAsPromised );
const assert = chai.assert;

const SIMPLE_PRODUCT_NAME = 'General Product';
const SALES_TAX_LABEL = 'Sales Tax';

const hasSalesTaxLineItem = () => {
    const selector = By.xpath( `//tr[th[contains(., "${ SALES_TAX_LABEL }")]]` );
    return helper.isEventuallyPresentAndDisplayed(
        driver,
        selector
    );
};

test.describe( 'Calculation Tests', function() {

    // Set up the driver and manager before testing starts.
    test.before( () => {
        this.timeout( config.get( 'startBrowserTimeoutMs' ) );

        manager = new WebDriverManager( 'chrome', { baseUrl: config.get( 'url' ) } );        
        driver = manager.getDriver();
        customer = new CustomerFlow( driver, {
            baseUrl: config.get( 'url' ),
            username: config.get( 'users.customer.username' ),
            password: config.get( 'users.customer.password' )
        } );
    } );

    this.timeout( config.get( 'mochaTimeoutMs' ) );

    test.it( 'Calculates tax for simple products', () => {
        customer.fromShopAddProductsToCart( SIMPLE_PRODUCT_NAME );
        
        // Check for "Sales Tax" item on Checkout page
        const checkoutPage = customer.openCheckout();
        checkoutPage.uncheckShipToDifferentAddress();
        assert.eventually.ok( Helper.waitTillUIBlockNotPresent( driver ) );
        assert.eventually.equal( hasSalesTaxLineItem(), true );

        // Reset to initial state
        const cartPage = customer.openCart();
        cartPage.getItem( SIMPLE_PRODUCT_NAME ).remove();
        assert.eventually.equal( cartPage.hasNoItem(), true );
    } );

    test.it( 'Calculates tax for variable products', () => {
        // todo (corresponds to checklist item 2)
    } );

    test.it( 'Respects applied coupons', () => {
        // todo (corresponds to checklist item 4)
    } );

    test.it( 'Accounts for products that ship from more than one origin', () => {
        // todo (corresponds to checklist item 10)
    } );

    // Close the browser after finished testing.
    test.after( () => {
        manager.quitBrowser();
    } );

} );