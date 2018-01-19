import config from 'config';
import chai from 'chai';
import chaiAsPromised from 'chai-as-promised';
import test from 'selenium-webdriver/testing';
import { By } from 'selenium-webdriver';
import { WebDriverManager, WebDriverHelper as helper } from 'wp-e2e-webdriver';
import { StoreOwnerFlow, WPAdminWCSettingsTaxRates } from 'wc-e2e-page-objects';
import * as SSTHelper from '../helper';

chai.use( chaiAsPromised );
const assert = chai.assert;

let manager;
let driver;
let owner;

const DELETE_SELECTOR = By.xpath( '//div[@id="message"]/p/a[contains(text(), "delete them")]' );
const TAX_RATE_SELECTOR = By.css( 'tbody#rates tr[data-id]' );

test.describe( 'Installation Tests', function() {
    const taxRateCount = () => {
        return driver.findElements( TAX_RATE_SELECTOR ).then( elements => {
            return elements.length;
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

    test.it( 'allows the user to remove existing tax rates after installation', () => {
        /**
         * Assumptions:
         *  - The option wootax_keep_rates has been deleted
         *  - The tax rate removal notice is being displayed
         *  - One tax rate is in the standard rates table
         */
        const ratesPage = new WPAdminWCSettingsTaxRates( driver, {
            url: manager.getPageUrl( '/wp-admin/admin.php?page=wc-settings&tab=tax&section=standard' )
        } );

        assert.eventually.equal( taxRateCount(), 1, 'exactly 1 rate should be in the standard rates table' );
        assert.eventually.ok( helper.clickWhenClickable( driver, DELETE_SELECTOR ) );
        assert.eventually.equal( taxRateCount(), 0, 'all tax rates should be removed' );
    } );

    // Close the browser after finished testing.
    test.after( () => {
        manager.quitBrowser();
    } );

} );