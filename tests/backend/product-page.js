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

const SELECT_TIC_BTN_SELECTOR = By.css( 'button.sst-select-tic' );
const SELECT_TIC_MODAL_SELECTOR = By.css( '.sst-select-tic-modal-content' );
const TIC_INPUT_SELECTOR = By.css( 'input.sst-tic-search' );
const CHOOSE_TIC_SELECTOR = By.xpath( '//tr[@class="tic-row" and not(@style="display: none;")][1]//button' );
const TIC_FIELD_SELECTOR = By.css( 'input[type="hidden"][name*="wootax_tic"]' );
const ORIGIN_ADDRESSES_FIELD_ID = '_wootax_origin_addresses[]';

test.describe( 'Product Page Tests', function() {
    const getTabSelector = tab => {
        return By.xpath( `//ul[contains(@class,"product_data_tabs")]//li/a[contains(., "${ tab }")]` );
    };
    const clickTab = tab => {
        // WPAdminProductEdit isn't exported so we need to redefine this
        return helper.clickWhenClickable( driver, getTabSelector( tab ) );
    }

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

    // Open the Product Edit screen before each test
    test.beforeEach( () => {
        page = owner.openProducts().editPostWithTitle( 'General Product' );
    } );

    test.it( 'allows user to assign TIC', () => {
        helper.clickWhenClickable( driver, SELECT_TIC_BTN_SELECTOR ).then( () => {
            helper.waitTillPresentAndDisplayed( 
                driver,
                SELECT_TIC_MODAL_SELECTOR
            ).then( () => {
                assert.eventually.ok( helper.setWhenSettable( driver, TIC_INPUT_SELECTOR, 'Lumber' ) );
                assert.eventually.ok( helper.clickWhenClickable( driver, CHOOSE_TIC_SELECTOR ) );
                assert.eventually.ok( page.publish() );

                driver.wait( () => {
                    return driver.findElement( TIC_FIELD_SELECTOR ).then( element => {
                        assert.eventually.equal( element.getAttribute( 'value' ), '94002' );
                        return true;
                    } );
                }, 10000, 'Timed out waiting for product to be saved.' );
            } );
        } );
    } );

    test.it( 'allows user to set origin addresses', () => {
        assert.eventually.ok( clickTab( 'Shipping' ) );

        const selected = SSTHelper.select2Option( 
            driver,
            ORIGIN_ADDRESSES_FIELD_ID,
            '206 Washington St SW, Atlanta, GA 30334',
            true,
            'name'
        );
        
        assert.eventually.ok( selected );
        assert.eventually.ok( page.publish() );
    } );

    // Close the browser after finished testing.
    test.after( () => {
        manager.quitBrowser();
    } );

} );