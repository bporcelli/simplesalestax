import config from 'config';
import chai from 'chai';
import chaiAsPromised from 'chai-as-promised';
import test from 'selenium-webdriver/testing';
import { By } from 'selenium-webdriver';
import { WebDriverManager, WebDriverHelper as helper } from 'wp-e2e-webdriver';
import { CustomerFlow, Helper, SingleProductPage } from 'wc-e2e-page-objects';

chai.use( chaiAsPromised );
const assert = chai.assert;

const TAX_ROW_SELECTOR = By.xpath( `//tr[th[contains(., "Sales Tax")]]` );
const COUPON_CODE_SELECTOR = By.css( 'input[name="coupon_code"]' );
const APPLY_COUPON_SELECTOR = By.css( 'input[type="submit"][name="apply_coupon"]' );
const REMOVE_COUPON_SELECTOR = By.css( '.woocommerce-remove-coupon' );
const TAX_SELECTOR = By.css( 'tr.tax-rate td' );

let manager;
let driver;
let customer;

test.describe( 'Basic Calculation Tests', function() {
    const loginAsCustomer = () => {
        return new CustomerFlow( driver, {
            baseUrl: config.get( 'url' ),
            username: config.get( 'users.customer.username' ),
            password: config.get( 'users.customer.password' )
        } );
    };
    const assertCheckoutPageLoaded = ( uncheck = true ) => {
        const checkoutPage = customer.openCheckout();
        if ( uncheck ) {
            checkoutPage.uncheckShipToDifferentAddress();
        }
        assert.eventually.ok( Helper.waitTillUIBlockNotPresent( driver ) );
    };
    const assertTaxApplied = () => {
        const applied = helper.isEventuallyPresentAndDisplayed(
            driver,
            TAX_ROW_SELECTOR
        );
        assert.eventually.ok( applied );
    };
    const assertTaxNotApplied = () => {
        const applied = helper.isEventuallyPresentAndDisplayed(
            driver,
            TAX_ROW_SELECTOR
        );
        assert.eventually.equal( applied, false );
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
    };
    const assertCouponRemoved = code => {
        customer.openCart();
       
        const selector = By.css( '.coupon-' + code + ' .woocommerce-remove-coupon' );
       
        assert.eventually.ok( helper.clickWhenClickable( driver, selector ) );
        assert.eventually.ok( Helper.waitTillUIBlockNotPresent( driver ) );
    };
    const removeProductsFromCart = ( ...products ) => {
        const cartPage = customer.openCart();
        for ( let product of products ) {
            cartPage.getItem( product ).remove();
        }
    };
    const getSalesTax = () => {
        return driver.findElement( TAX_SELECTOR ).then( element => {
            return element.getAttribute( 'textContent' );
        } );
    };
    const visitProductByPath = path => {
        return new SingleProductPage( driver, { url: manager.getPageUrl( path ) } );
    };

    // Set up the driver and manager before testing starts.
    test.before( () => {
        this.timeout( config.get( 'startBrowserTimeoutMs' ) );

        manager = new WebDriverManager( 'chrome', { baseUrl: config.get( 'url' ) } );        
        driver = manager.getDriver();
        customer = loginAsCustomer();
    } );

    this.timeout( config.get( 'mochaTimeoutMs' ) );

    test.ignore( 'calculates tax', () => {
        customer.fromShopAddProductsToCart( 'General Product', 'Lumber', 'eBook' );

        assertCheckoutPageLoaded();
        assertTaxApplied();

        removeProductsFromCart( 'General Product', 'Lumber', 'eBook' );
    } );

    test.ignore( 'uses assigned TICs for simple products', () => {
        // Assumes eBook is assigned a nontaxable TIC
        customer.fromShopAddProductsToCart( 'eBook' );

        assertCheckoutPageLoaded( false );
        assertTaxNotApplied();

        removeProductsFromCart( 'eBook' );
    } );

    test.ignore( 'uses assigned TICs for variable products', () => {
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

    test.ignore( 'respects applied coupons', () => {
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
        assert.eventually.ok( Helper.waitTillUIBlockNotPresent( driver ) );

        const taxAmountChanged = getSalesTax().then( taxForShipping => {
            checkoutPage.uncheckShipToDifferentAddress();
            assert.eventually.ok( Helper.waitTillUIBlockNotPresent( driver ) );

            return getSalesTax().then( taxForBilling => {
                return taxForShipping !== taxForBilling;
            } );
        } );

        assert.eventually.equal( taxAmountChanged, true );

        // Reset
        removeProductsFromCart( 'Multi-origin Product' );
    } );

    // Close the browser after finished testing.
    test.after( () => {
        manager.quitBrowser();
    } );

} );