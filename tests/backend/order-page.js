import config from 'config';
import chai from 'chai';
import chaiAsPromised from 'chai-as-promised';
import test from 'selenium-webdriver/testing';
import { By } from 'selenium-webdriver';
import { WebDriverManager, WebDriverHelper as helper } from 'wp-e2e-webdriver';
import { StoreOwnerFlow, Helper } from 'wc-e2e-page-objects';
import * as SSTHelper from '../helper';

chai.use( chaiAsPromised );
const assert = chai.assert;

let manager;
let driver;
let owner;
let page;

const SHIPPING_ROW_SELECTOR = By.css( 'tr.shipping' );
const EDIT_SHIPPING_SELECTOR = By.css( 'tr.shipping a.edit-order-item' );
const SHIPPING_COST_SELECTOR = By.css( 'tr.shipping input.line_total' );
const CALC_BUTTON_SELECTOR = By.css( 'button.calculate-action' );
const TAX_ROW_SELECTOR = By.xpath( '//td[@class="label" and contains(text(), "Sales Tax:")]' );
const ADD_IN_DIALOG_SELECTOR = By.css( '#btn-ok' );
const ITEM_SEARCH_ID = 'add_item_id';
const TAXCLOUD_STATUS_SELECTOR = By.xpath( '//div[@id="sales_tax_meta"]//div[@class="inside"]/p' );

test.describe( 'Order Page Tests', function() {
    const addProduct = ( itemsBox, productTitle ) => {
        // WC's implementation of addProduct is broken so we define our own
        itemsBox.clickAddItems();
        itemsBox.clickAddProducts();
        SSTHelper.select2Option( driver, ITEM_SEARCH_ID, productTitle, true );
        helper.clickWhenClickable( driver, ADD_IN_DIALOG_SELECTOR );
        return Helper.waitTillUIBlockNotPresent( driver );
    };
    const recalculate = () => {
        return helper.clickWhenClickable( driver, CALC_BUTTON_SELECTOR ).then( () => {
            return Helper.waitTillAlertAccepted( driver ).then( () => {
                return Helper.waitTillUIBlockNotPresent( driver );
            } );
        } );
    };
    const createNewOrder = () => {
        const page = owner.openNewOrder();

        const orderDataBox = page.components.metaBoxOrderData;
        
        assert.eventually.ok( orderDataBox.selectCustomer( 
            'brett', 
            'Brett Porcelli (#1 – brettporcelli@gmail.com)' 
        ) );

        const orderItemsBox = page.components.metaBoxOrderItems;

        assert.eventually.ok( addProduct( orderItemsBox, 'General Product' ) );
        assert.eventually.ok( orderItemsBox.clickAddItems() );
        assert.eventually.ok( orderItemsBox.clickAddShippingCost() );
        assert.eventually.ok( helper.mouseMoveTo( driver, SHIPPING_ROW_SELECTOR ) );
        assert.eventually.ok( helper.clickWhenClickable( driver, EDIT_SHIPPING_SELECTOR ) );
        assert.eventually.ok( helper.setWhenSettable( driver, SHIPPING_COST_SELECTOR, '9.99' ) );
        assert.eventually.ok( orderItemsBox.clickSave() );
        
        assert.eventually.ok( recalculate() );
        return page;
    };
    const getTaxCloudStatus = () => {
        return driver.findElement( TAXCLOUD_STATUS_SELECTOR ).then( element => {
            return element.getAttribute( 'textContent' ).then( textContent => {
                textContent = textContent.replace( 'TaxCloud Status', '' ).trim();
                return textContent;
            } );
        } );
    };
    const changeOrderStatus = status => {
        const dataBox = page.components.metaBoxOrderData;
        const actionsBox = page.components.metaBoxOrderActions;

        return dataBox.selectOrderStatus( status ).then( () => {
            return actionsBox.saveOrder();
        } );
    };

    // Set up the driver and manager before testing starts.
    test.before( () => {
        this.timeout( config.get( 'startBrowserTimeoutMs' ) );

        manager = new WebDriverManager( 'chrome', { baseUrl: config.get( 'url' ) } );
        driver = manager.getDriver();
    } );

    this.timeout( config.get( 'mochaTimeoutMs' ) );

    // Login before testing starts.
    test.before( () => {
        owner = new StoreOwnerFlow( driver, {
            baseUrl: config.get( 'url' ),
            username: config.get( 'users.admin.username' ),
            password: config.get( 'users.admin.password' )
        } );
    } );

    // Create a new order before each test
    test.beforeEach( () => {
        page = createNewOrder();
    } );

    // After each test, refund the created order if it wasn't refunded already
    test.afterEach( () => {
        getTaxCloudStatus().then( status => {
            if ( status === 'Refunded' ) {
                return;
            }
            assert.eventually.ok( changeOrderStatus( 'Refunded' ) );
        } );
    } );

    test.it( 'calculates tax for manual orders', () => {
        assert.eventually.ok( helper.isEventuallyPresentAndDisplayed( driver, TAX_ROW_SELECTOR ) );
    } );

    test.it( 'captures completed orders', () => {
        assert.eventually.ok( changeOrderStatus( 'Completed' ) );
        assert.eventually.equal( getTaxCloudStatus(), 'Captured' );
    } );

    test.it( 'refunds refunded orders', () => {
        // Must capture before refunding
        assert.eventually.ok( changeOrderStatus( 'Completed' ) );

        assert.eventually.ok( changeOrderStatus( 'Refunded' ) );
        assert.eventually.equal( getTaxCloudStatus(), 'Refunded' );
    } );

    // Close the browser after finished testing.
    test.after( () => {
        manager.quitBrowser();
    } );

} );