/**
 * Test helpers.
 * @module SSTHelper
 */

import { WebDriverHelper as helper } from 'wp-e2e-webdriver';
import { By } from 'selenium-webdriver';

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