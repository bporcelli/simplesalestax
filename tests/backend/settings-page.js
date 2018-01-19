import config from 'config';
import chai from 'chai';
import chaiAsPromised from 'chai-as-promised';
import test from 'selenium-webdriver/testing';
import { By } from 'selenium-webdriver';
import { WebDriverManager, WebDriverHelper as helper } from 'wp-e2e-webdriver';
import { WPLogin } from 'wp-e2e-page-objects';
import { StoreOwnerFlow, Helper } from 'wc-e2e-page-objects';
import * as SSTHelper from '../helper';

chai.use( chaiAsPromised );
const assert = chai.assert;

let manager;
let driver;
let owner;
let page;

const VERIFY_SETTINGS_SELECTOR = By.css( '#verifySettings' );
const ADDRESS_SELECTOR = By.css( '#address_table tbody tr[data-id]' );
const FIRST_ADDRESS_SELECTOR = By.xpath(  `(//tr[@data-id])[1]` );
const REMOVE_BTN_SELECTOR = By.xpath(  `(//a[@class="sst-address-delete"])[1]` );
const ADD_BTN_SELECTOR = By.css( 'button.sst-address-add' );
const DOWNLOAD_BTN_SELECTOR = By.css( '#download_log_button' );
const SUCCESS_MSG_SELECTOR = By.xpath( '//div[@id="message"]//strong[contains(text(), "Your settings have been saved.")]' );

test.describe( 'Settings Page Tests', function() {
    const getFieldId = field => {
        return 'woocommerce_wootax_' + field;
    };
    const getFieldSelector = id => {
        return By.css( '#' + getFieldId( id ) );
    };
    const setFieldWhenSettable = ( id, value ) => {
        return helper.setWhenSettable( driver, getFieldSelector( id ), value );
    };
    const selectOption = ( id, optionText ) => {
        return helper.selectOption( driver, getFieldSelector( id ), optionText );
    };
    const select2Option = ( id, optionText, withSearch = false ) => {
        const fieldId = getFieldId( id );
        const selector = By.xpath( `//span[contains(@class, "select2") and ../select[@id="${ fieldId }"]]//input` );

        if ( withSearch ) {
            return Helper.select2OptionWithSearch( driver, selector, optionText, optionText );
        } else {
            return Helper.select2Option( driver, selector, optionText );
        }
    };
    const setCheckbox = id => {
        return helper.setCheckbox( driver, getFieldSelector( id ) );
    };
    const waitTillAlertAppears = ( waitMs = 10000 ) => {
        return driver.wait( () => {
            return driver.switchTo().alert();
        }, waitMs, 'Time out waiting for alert to appear' );
    };
    const getTabSelector = tab => {
        let exp = `//nav[contains(@class, "woo-nav-tab-wrapper")]//a[contains(@class, "nav-tab") and contains(text(), "${ tab }")]`;
        return By.xpath( exp );
    };
    const clickTab = tab => {
        // Workaround for bug in WPAdminWCSettings::clickTab
        return helper.clickWhenClickable( driver, getTabSelector( tab ) );
    };
    const getNumAddresses = () => {
        return driver.findElements( ADDRESS_SELECTOR ).then( addresses => {
            return addresses.length;
        } );
    };
    const addressChildSelector = ( index, selector ) => {
        if ( index == -1 ) {
            index = 'last()';
        }
        return By.xpath( `(//tr[@data-id])[${ index }]//${ selector }` );
    };
    const getInputSelector = ( address, id ) => {
        return addressChildSelector( address, `input[@data-attribute="${ id }"]` );
    };
    const getDropdownSelector = ( address, id ) => {
        return addressChildSelector( address, `select[@data-attribute="${ id }"]` );
    };
    const setAddressField = ( address, id, value ) => {
        return helper.setWhenSettable( driver, getInputSelector( address, id ), value );
    };
    const selectAddressOption = ( address, id, optionText ) => {
        return helper.selectOption( driver, getDropdownSelector( address, id ), optionText );
    };
    const setAddressCheckbox = ( address, id ) => {
        return helper.setCheckbox( driver, getInputSelector( address, id ) );
    };

    // Set up the driver and manager before testing starts.
    test.before( () => {
        this.timeout( config.get( 'startBrowserTimeoutMs' ) );

        manager = new WebDriverManager( 'chrome', { baseUrl: config.get( 'url' ) } );
        driver = manager.getDriver();
    } );

    this.timeout( config.get( 'mochaTimeoutMs' ) );

    // Login and navigate to the settings page before testing starts.
    test.before( () => {
        owner = new StoreOwnerFlow( driver, {
            baseUrl: config.get( 'url' ),
            username: config.get( 'users.admin.username' ),
            password: config.get( 'users.admin.password' )
        } );

        page = owner.openGeneralSettings();

        // Assumes there are no other WC integrations active
        clickTab( 'Integration' );
    } );

    test.it( 'allows user to verify TaxCloud settings', () => {
        assert.eventually.ok( setFieldWhenSettable( 'tc_id', config.get( 'taxcloud.apiId' ) ) );
        assert.eventually.ok( setFieldWhenSettable( 'tc_key', config.get( 'taxcloud.apiKey'  ) ) );
        
        helper
        .clickWhenClickable( driver, VERIFY_SETTINGS_SELECTOR )
        .then( () => {
            // Give TaxCloud a few seconds to process the Ping request
            driver.sleep( 3000 );

            waitTillAlertAppears().then( alert => {
                alert.getText().then( alertText => {
                    assert.equal( alertText, 'Success! Your TaxCloud settings are valid.' );
                    assert.eventually.ok( Helper.waitTillAlertAccepted( driver ) );
                } );
            } );
        } );
    } );

    test.it( 'allows user to remove an address', () => {
        getNumAddresses().then( numAddressesBefore => {
            helper.mouseMoveTo( 
                driver,
                FIRST_ADDRESS_SELECTOR
            ).then( () => {
                helper.clickWhenClickable(
                    driver,
                    REMOVE_BTN_SELECTOR
                ).then( () => {
                    getNumAddresses().then( numAddressesAfter => {
                        assert.equal( numAddressesAfter, numAddressesBefore - 1 );
                    } );
                } );
            } );
        } );
    } );

    test.it( 'allows user to add an address', () => {
        assert.eventually.ok( helper.clickWhenClickable( driver, ADD_BTN_SELECTOR ) );

        const index = -1;

        assert.eventually.ok( setAddressField( index, 'Address1', 'Washington Ave and State St' ) );
        assert.eventually.ok( setAddressField( index, 'Address2', '' ) );
        assert.eventually.ok( setAddressField( index, 'City', 'Albany' ) );
        assert.eventually.ok( selectAddressOption( index, 'State', 'New York' ) );
        assert.eventually.ok( setAddressField( index, 'Zip5', '12242' ) );
        assert.eventually.ok( setAddressField( index, 'Zip4', '' ) );
        assert.eventually.ok( setAddressCheckbox( index, 'Default' ) );
    } );

    test.it( 'allows the user to change the exemption settings', () => {
        assert.eventually.ok( selectOption( 'show_exempt', 'Yes' ) );
        assert.eventually.ok( setFieldWhenSettable( 'company_name', 'Simple Sales Tax' ) );
        assert.eventually.ok( select2Option( 'exempt_roles', 'Customer', true ) );
        assert.eventually.ok( selectOption( 'restrict_exempt', 'No' ) );
    } );

    test.it( 'allows the user to change the display settings', () => {
        assert.eventually.ok( selectOption( 'show_zero_tax', 'No' ) );
    } );

    test.it( 'allows the user to change the advanced settings', () => {
        assert.eventually.ok( setCheckbox( 'log_requests' ) );
        assert.eventually.ok( setCheckbox( 'capture_immediately' ) );
        assert.eventually.ok( selectOption( 'tax_based_on', 'Item Price' ) );
        assert.eventually.ok( setCheckbox( 'remove_all_data' ) );
    } );

    test.it( 'allows the user to download the log file', () => {
        assert.eventually.ok( helper.clickWhenClickable( driver, DOWNLOAD_BTN_SELECTOR ) );

        // Wait for download
        driver.sleep( 3000 );
    } );

    test.it( 'allows the user to save their changes', () => {
        assert.eventually.ok( page.saveChanges() );
        assert.eventually.ok( helper.isEventuallyPresentAndDisplayed( driver, SUCCESS_MSG_SELECTOR ) );
    } );

    // Close the browser after finished testing.
    test.after( () => {
        manager.quitBrowser();
    } );

} );