/**
 * Test helpers.
 * @module SSTHelper
 */

import config from 'config';
import { WebDriverHelper as helper } from 'wp-e2e-webdriver';
import { By } from 'selenium-webdriver';
import { CustomerFlow, Helper } from 'wc-e2e-page-objects';

export const TAX_ROW_SELECTOR = By.xpath( `//tr[th[contains(., "Sales Tax")]]` );

export function taxApplied( driver ) {
    return helper.isEventuallyPresentAndDisplayed(
        driver,
        TAX_ROW_SELECTOR
    );
}

export function taxNotApplied( driver ) {
    return helper.isEventuallyPresentAndDisplayed(
        driver,
        TAX_ROW_SELECTOR
    ).then( val => {
        return ! val;
    }, err => {
        return true;
    } );
}

export function loginAsCustomer( driver ) {
    return new CustomerFlow( driver, {
        baseUrl: config.get( 'url' ),
        username: config.get( 'users.customer.username' ),
        password: config.get( 'users.customer.password' )
    } );
}

export function removeProductsFromCart( driver, customer, ...products ) {
    const cartPage = customer.openCart();
    for ( let product of products ) {
        cartPage.getItem( product ).remove();
    }
}

export function select2Option( driver, fieldId, optionText, withSearch = false, attr = 'id' ) {
    const selector = By.xpath( `//span[contains(@class, "select2") and ../select[@${ attr }="${ fieldId }"]]//input` );

    if ( withSearch ) {
        return Helper.select2OptionWithSearch( driver, selector, optionText, optionText );
    } else {
        return Helper.select2Option( driver, selector, optionText );
    }
}