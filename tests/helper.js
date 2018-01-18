/**
 * Test helpers.
 * @module SSTHelper
 */

import config from 'config';
import { WebDriverHelper as helper } from 'wp-e2e-webdriver';
import { By } from 'selenium-webdriver';
import { CustomerFlow } from 'wc-e2e-page-objects';

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