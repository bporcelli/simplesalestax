import config from 'config';
import chai from 'chai';
import chaiAsPromised from 'chai-as-promised';
import test from 'selenium-webdriver/testing';
import { By } from 'selenium-webdriver';
import { WebDriverManager, WebDriverHelper as helper } from 'wp-e2e-webdriver';
import { Helper, SingleProductPage } from 'wc-e2e-page-objects';
import * as SSTHelper from '../helper';

chai.use( chaiAsPromised );
const assert = chai.assert;

const COUPON_CODE_SELECTOR = By.css( 'input[name="coupon_code"]' );
const APPLY_COUPON_SELECTOR = By.css( '[name="apply_coupon"]' );
const TAX_SELECTOR = By.css( 'tr.tax-rate td' );
const FREE_SHIPPING_SELECTOR = By.css( 'input[name*="shipping_method"][value*="free_shipping"]' );

let manager;
let driver;
let customer;

test.describe( 'Basic Calculation Tests', function() {
    const assertTaxApplied = () => {
        assert.eventually.ok( SSTHelper.taxApplied( driver ) );
    };
    const assertTaxNotApplied = () => {
        assert.eventually.ok( SSTHelper.taxNotApplied( driver ) );
    };
    const assertCheckoutPageLoaded = ( uncheck = true ) => {
        const checkoutPage = customer.openCheckout();
        if ( uncheck ) {
            checkoutPage.uncheckShipToDifferentAddress();
        }
        assert.eventually.ok( Helper.waitTillUIBlockNotPresent( driver ) );
    };
    const assertVariationAddedToCart = ( path, attr, variation ) => {
        let productPage = visitProductByPath( path );

        assert.eventually.ok( productPage.selectVariation( attr, variation ) );
        assert.eventually.ok( productPage.addToCart() );
    };
    const assertCouponApplied = code => {
        customer.openCart();

        assert.eventually.ok( helper.setWhenSettable( driver, COUPON_CODE_SELECTOR, code ) );
        assert.eventually.ok( helper.clickWhenClickable( driver, APPLY_COUPON_SELECTOR ) );
        assert.eventually.ok( Helper.waitTillUIBlockNotPresent( driver ) );

        // If free shipping is available, select it
        helper.isEventuallyPresentAndDisplayed(
            driver,
            FREE_SHIPPING_SELECTOR
        ).then( available => {
            if ( ! available ) {
                return;
            }
            assert.eventually.ok( helper.clickWhenClickable( driver, FREE_SHIPPING_SELECTOR ) );
            assert.eventually.ok( Helper.waitTillUIBlockNotPresent( driver ) );
        } );
    };
    const assertCouponRemoved = code => {
        customer.openCart();
       
        const selector = By.css( `.woocommerce-remove-coupon[data-coupon="${ code }"]` );
       
        assert.eventually.ok( helper.clickWhenClickable( driver, selector ) );
        assert.eventually.ok( Helper.waitTillUIBlockNotPresent( driver ) );
    };
    const removeProductsFromCart = ( ...products ) => {
        SSTHelper.removeProductsFromCart( driver, customer, ...products );
    };
    const getSalesTax = () => {
        return driver.findElement( TAX_SELECTOR ).then( element => {
            return element.getAttribute( 'textContent' );
        } );
    };
    const resetShippingAddress = checkoutPage => {
        const shippingDetails = checkoutPage.components.shippingDetails;

        return driver.wait( () => {
            shippingDetails.setFirstName( 'John' );
            shippingDetails.setLastName( 'Doe' );
            shippingDetails.selectCountry( 'united', 'United States (US)' );
            shippingDetails.setAddress1( '206 Washington St SW' );
            shippingDetails.setCity( 'Atlanta' );
            shippingDetails.selectState( 'geo', 'Georgia' );
            shippingDetails.setZip( '30334' );

            return Helper.waitTillUIBlockNotPresent( driver );
        }, 10000, 'Timed out resetting shipping address.' );
    };
    const visitProductByPath = path => {
        return new SingleProductPage( driver, { url: manager.getPageUrl( path ) } );
    };

    // Set up the driver and manager before testing starts.
    test.before( () => {
        this.timeout( config.get( 'startBrowserTimeoutMs' ) );

        manager = new WebDriverManager( 'chrome', { baseUrl: config.get( 'url' ) } );        
        driver = manager.getDriver();
        customer = SSTHelper.loginAsCustomer( driver );
    } );

    this.timeout( config.get( 'mochaTimeoutMs' ) );

    test.it( 'calculates tax', () => {
        customer.fromShopAddProductsToCart( 'General Product', 'Lumber', 'eBook' );

        assertCheckoutPageLoaded();
        assertTaxApplied();

        removeProductsFromCart( 'General Product', 'Lumber', 'eBook' );
    } );

    test.it( 'uses assigned TICs for simple products', () => {
        // Assumes eBook is assigned a nontaxable TIC
        customer.fromShopAddProductsToCart( 'eBook' );

        assertCheckoutPageLoaded( false );
        assertTaxNotApplied();

        removeProductsFromCart( 'eBook' );
    } );

    test.it( 'uses assigned TICs for variable products', () => {
        // Assumes variation A is assigned a taxable TIC and variation B is not
        assertVariationAddedToCart( '/product/variable-product', 'Version', 'A' );
        assertCheckoutPageLoaded();
        assertTaxApplied();
        removeProductsFromCart( 'Variable Product - A' );

        assertVariationAddedToCart( '/product/variable-product', 'Version', 'B' );
        assertCheckoutPageLoaded( false );
        assertTaxNotApplied();
        removeProductsFromCart( 'Variable Product - B' );
    } );

    test.it( 'respects applied coupons', () => {
        customer.fromShopAddProductsToCart( 'General Product' );
        
        // Tax should be added at first
        assertCheckoutPageLoaded();
        assertTaxApplied();

        // Tax should be removed after the 'zero' coupon is applied
        assertCouponApplied( 'zero' );
        assertCheckoutPageLoaded();
        assertTaxNotApplied();
        assertCouponRemoved( 'zero' );
        
        removeProductsFromCart( 'General Product' );
    } );

    test.it( 'uses the correct origin address for multi-origin products', () => {
        /**
         * Assumes:
         *
         *  - Multi-origin Product has two origin addresses with different tax
         *    rates. 
         *  - The customer's shipping and billing address are set such that 
         *    toggling "Ship to a different address" changes the origin address
         *    selected for the product. 
         */
        customer.fromShopAddProductsToCart( 'Multi-origin Product' );

        const checkoutPage = customer.openCheckout();

        checkoutPage.checkShipToDifferentAddress();

        Helper.waitTillUIBlockNotPresent( driver ).then( () => {
            resetShippingAddress( checkoutPage ).then( () => {
                getSalesTax().then( taxForShipping => {
                    checkoutPage.uncheckShipToDifferentAddress();

                    Helper.waitTillUIBlockNotPresent( driver ).then( () => {
                        getSalesTax().then( taxForBilling => {
                            assert.equal( taxForShipping !== taxForBilling, true );
                        } );
                    } );
                } );
            } );
        } ).finally( () => {
            // Reset
            removeProductsFromCart( 'Multi-origin Product' );
        } );
    } );

    // Close the browser after finished testing.
    test.after( () => {
        manager.quitBrowser();
    } );

} );