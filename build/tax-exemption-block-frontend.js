/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./node_modules/@wordpress/icons/build-module/icon/index.js":
/*!******************************************************************!*\
  !*** ./node_modules/@wordpress/icons/build-module/icon/index.js ***!
  \******************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/**
 * WordPress dependencies
 */


/** @typedef {{icon: JSX.Element, size?: number} & import('@wordpress/primitives').SVGProps} IconProps */

/**
 * Return an SVG icon.
 *
 * @param {IconProps}                                 props icon is the SVG component to render
 *                                                          size is a number specifiying the icon size in pixels
 *                                                          Other props will be passed to wrapped SVG component
 * @param {import('react').ForwardedRef<HTMLElement>} ref   The forwarded ref to the SVG element.
 *
 * @return {JSX.Element}  Icon component
 */
function Icon({
  icon,
  size = 24,
  ...props
}, ref) {
  return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.cloneElement)(icon, {
    width: size,
    height: size,
    ...props,
    ref
  });
}
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = ((0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.forwardRef)(Icon));
//# sourceMappingURL=index.js.map

/***/ }),

/***/ "./node_modules/@wordpress/icons/build-module/library/chevron-down.js":
/*!****************************************************************************!*\
  !*** ./node_modules/@wordpress/icons/build-module/library/chevron-down.js ***!
  \****************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_primitives__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/primitives */ "@wordpress/primitives");
/* harmony import */ var _wordpress_primitives__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_1__);

/**
 * WordPress dependencies
 */

const chevronDown = (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_1__.SVG, {
  viewBox: "0 0 24 24",
  xmlns: "http://www.w3.org/2000/svg"
}, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_1__.Path, {
  d: "M17.5 11.6L12 16l-5.5-4.4.9-1.2L12 14l4.5-3.6 1 1.2z"
}));
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (chevronDown);
//# sourceMappingURL=chevron-down.js.map

/***/ }),

/***/ "./assets/js/blocks/tax-exemption/block.js":
/*!*************************************************!*\
  !*** ./assets/js/blocks/tax-exemption/block.js ***!
  \*************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _woocommerce_settings__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @woocommerce/settings */ "@woocommerce/settings");
/* harmony import */ var _woocommerce_settings__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_woocommerce_settings__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _woocommerce_base_components_select_index__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @woocommerce/base-components/select/index */ "./includes/vendor/woocommerce/woocommerce/plugins/woocommerce-blocks/assets/js/base/components/select/index.tsx");
/* harmony import */ var _woocommerce_blocks_components__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @woocommerce/blocks-components */ "@woocommerce/blocks-components");
/* harmony import */ var _woocommerce_blocks_components__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(_woocommerce_blocks_components__WEBPACK_IMPORTED_MODULE_5__);
/* harmony import */ var _woocommerce_blocks_checkout__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! @woocommerce/blocks-checkout */ "@woocommerce/blocks-checkout");
/* harmony import */ var _woocommerce_blocks_checkout__WEBPACK_IMPORTED_MODULE_6___default = /*#__PURE__*/__webpack_require__.n(_woocommerce_blocks_checkout__WEBPACK_IMPORTED_MODULE_6__);
/* harmony import */ var _woocommerce_block_data__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! @woocommerce/block-data */ "@woocommerce/block-data");
/* harmony import */ var _woocommerce_block_data__WEBPACK_IMPORTED_MODULE_7___default = /*#__PURE__*/__webpack_require__.n(_woocommerce_block_data__WEBPACK_IMPORTED_MODULE_7__);
/* harmony import */ var _new_certificate_form__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ./new-certificate-form */ "./assets/js/blocks/tax-exemption/new-certificate-form.js");
/* harmony import */ var _use_extension_state__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! ./use-extension-state */ "./assets/js/blocks/tax-exemption/use-extension-state.js");

/**
 * External dependencies
 */







const {
  showExemptionForm,
  certificateOptions,
  selectedCertificate,
  isUserLoggedIn,
  myAccountEndpointUrl
} = (0,_woocommerce_settings__WEBPACK_IMPORTED_MODULE_3__.getSetting)('simple-sales-tax_data', '');
const options = Object.entries(certificateOptions).map(([value, label]) => ({
  value,
  label
}));

/**
 * Internal dependencies
 */


const Block = ({
  className,
  children
}) => {
  const [certificateId, setCertificateId] = (0,_use_extension_state__WEBPACK_IMPORTED_MODULE_9__.useExtensionState)('certificateId', selectedCertificate);
  const {
    __internalIncrementCalculating,
    __internalDecrementCalculating
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.useDispatch)(_woocommerce_block_data__WEBPACK_IMPORTED_MODULE_7__.CHECKOUT_STORE_KEY);
  const onChangeCertificate = value => {
    // Update value
    setCertificateId(value);

    // Recalculate taxes
    __internalIncrementCalculating();
    (0,_woocommerce_blocks_checkout__WEBPACK_IMPORTED_MODULE_6__.extensionCartUpdate)({
      namespace: 'simple-sales-tax',
      data: {
        action: 'recalculate',
        certificate_id: value === 'none' ? '' : value
      }
    }).finally(() => {
      __internalDecrementCalculating();
    });
  };
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: className
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_woocommerce_blocks_components__WEBPACK_IMPORTED_MODULE_5__.Title, {
    headingLevel: "2"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Tax exemption', 'simple-sales-tax')), !isUserLoggedIn && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", null, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Please log in or register to apply an exemption certificate.', 'simple-sales-tax')), isUserLoggedIn && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(react__WEBPACK_IMPORTED_MODULE_0__.Fragment, null, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_woocommerce_base_components_select_index__WEBPACK_IMPORTED_MODULE_4__.Select, {
    id: "exemption-certificate-input",
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Exemption certificate', 'simple-sales-tax'),
    onChange: onChangeCertificate,
    options: options,
    value: certificateId
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("a", {
    href: myAccountEndpointUrl,
    target: "_blank"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Manage exemption certificates →', 'simple-sales-tax')), certificateId === 'new' && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_new_certificate_form__WEBPACK_IMPORTED_MODULE_8__.NewCertificateForm, null)));
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (Block);

/***/ }),

/***/ "./assets/js/blocks/tax-exemption/constants.js":
/*!*****************************************************!*\
  !*** ./assets/js/blocks/tax-exemption/constants.js ***!
  \*****************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   BUSINESS_TYPE_OPTIONS: () => (/* binding */ BUSINESS_TYPE_OPTIONS),
/* harmony export */   EXEMPTION_REASON_OPTIONS: () => (/* binding */ EXEMPTION_REASON_OPTIONS)
/* harmony export */ });
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);
/**
 * External dependencies
 */

const BUSINESS_TYPE_OPTIONS = [{
  value: '',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Select a business type', 'simple-sales-tax'),
  disabled: true
}, {
  value: 'AccommodationAndFoodServices',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Accommodation And Food Services', 'simple-sales-tax')
}, {
  value: 'Agricultural_Forestry_Fishing_Hunting',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Agricultural/Forestry/Fishing/Hunting', 'simple-sales-tax')
}, {
  value: 'Construction',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Construction', 'simple-sales-tax')
}, {
  value: 'FinanceAndInsurance',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Finance or Insurance', 'simple-sales-tax')
}, {
  value: 'Information_PublishingAndCommunications',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Information Publishing and Communications', 'simple-sales-tax')
}, {
  value: 'Manufacturing',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Manufacturing', 'simple-sales-tax')
}, {
  value: 'Mining',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Mining', 'simple-sales-tax')
}, {
  value: 'RealEstate',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Real Estate', 'simple-sales-tax')
}, {
  value: 'RentalAndLeasing',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Rental and Leasing', 'simple-sales-tax')
}, {
  value: 'RetailTrade',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Retail Trade', 'simple-sales-tax')
}, {
  value: 'TransportationAndWarehousing',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Transportation and Warehousing', 'simple-sales-tax')
}, {
  value: 'Utilities',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Utilities', 'simple-sales-tax')
}, {
  value: 'WholesaleTrade',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Wholesale Trade', 'simple-sales-tax')
}, {
  value: 'BusinessServices',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Business Services', 'simple-sales-tax')
}, {
  value: 'ProfessionalServices',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Professional Services', 'simple-sales-tax')
}, {
  value: 'EducationAndHealthCareServices',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Education and Health Care Services', 'simple-sales-tax')
}, {
  value: 'NonprofitOrganization',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Nonprofit Organization', 'simple-sales-tax')
}, {
  value: 'Government',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Government', 'simple-sales-tax')
}, {
  value: 'NotABusiness',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Not a Business', 'simple-sales-tax')
}, {
  value: 'Other',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Other', 'simple-sales-tax')
}];
const EXEMPTION_REASON_OPTIONS = [{
  value: '',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Select a reason', 'simple-sales-tax'),
  disabled: true
}, {
  value: 'FederalGovernmentDepartment',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Federal Government Department', 'simple-sales-tax')
}, {
  value: 'StateOrLocalGovernmentName',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('State Or Local Government', 'simple-sales-tax')
}, {
  value: 'TribalGovernmentName',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Tribal Government', 'simple-sales-tax')
}, {
  value: 'ForeignDiplomat',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Foreign Diplomat', 'simple-sales-tax')
}, {
  value: 'CharitableOrganization',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Charitable Organization', 'simple-sales-tax')
}, {
  value: 'ReligiousOrEducationalOrganization',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Religious or Educational Organization', 'simple-sales-tax')
}, {
  value: 'Resale',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Resale', 'simple-sales-tax')
}, {
  value: 'AgriculturalProduction',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Agricultural Production', 'simple-sales-tax')
}, {
  value: 'IndustrialProductionOrManufacturing',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Industrial Production or Manufacturing', 'simple-sales-tax')
}, {
  value: 'DirectPayPermit',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Direct Pay Permit', 'simple-sales-tax')
}, {
  value: 'DirectMail',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Direct Mail', 'simple-sales-tax')
}, {
  value: 'Other',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Other', 'simple-sales-tax')
}];

/***/ }),

/***/ "./assets/js/blocks/tax-exemption/frontend.js":
/*!****************************************************!*\
  !*** ./assets/js/blocks/tax-exemption/frontend.js ***!
  \****************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _woocommerce_blocks_checkout__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @woocommerce/blocks-checkout */ "@woocommerce/blocks-checkout");
/* harmony import */ var _woocommerce_blocks_checkout__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_woocommerce_blocks_checkout__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _block__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./block */ "./assets/js/blocks/tax-exemption/block.js");
/* harmony import */ var _block_json__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./block.json */ "./assets/js/blocks/tax-exemption/block.json");
/**
 * External dependencies
 */


/**
 * Internal dependencies
 */


(0,_woocommerce_blocks_checkout__WEBPACK_IMPORTED_MODULE_0__.registerCheckoutBlock)({
  metadata: _block_json__WEBPACK_IMPORTED_MODULE_2__,
  component: _block__WEBPACK_IMPORTED_MODULE_1__["default"]
});

/***/ }),

/***/ "./assets/js/blocks/tax-exemption/new-certificate-form.js":
/*!****************************************************************!*\
  !*** ./assets/js/blocks/tax-exemption/new-certificate-form.js ***!
  \****************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   NewCertificateForm: () => (/* binding */ NewCertificateForm)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _woocommerce_block_data__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @woocommerce/block-data */ "@woocommerce/block-data");
/* harmony import */ var _woocommerce_block_data__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_woocommerce_block_data__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _woocommerce_blocks_checkout__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @woocommerce/blocks-checkout */ "@woocommerce/blocks-checkout");
/* harmony import */ var _woocommerce_blocks_checkout__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_woocommerce_blocks_checkout__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _woocommerce_blocks_components__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @woocommerce/blocks-components */ "@woocommerce/blocks-components");
/* harmony import */ var _woocommerce_blocks_components__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(_woocommerce_blocks_components__WEBPACK_IMPORTED_MODULE_5__);
/* harmony import */ var _woocommerce_base_components_select_index__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! @woocommerce/base-components/select/index */ "./includes/vendor/woocommerce/woocommerce/plugins/woocommerce-blocks/assets/js/base/components/select/index.tsx");
/* harmony import */ var _woocommerce_base_components_state_input_index__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! @woocommerce/base-components/state-input/index */ "./includes/vendor/woocommerce/woocommerce/plugins/woocommerce-blocks/assets/js/base/components/state-input/index.ts");
/* harmony import */ var _woocommerce_block_settings__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! @woocommerce/block-settings */ "./includes/vendor/woocommerce/woocommerce/plugins/woocommerce-blocks/assets/js/settings/blocks/index.ts");
/* harmony import */ var _constants__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! ./constants */ "./assets/js/blocks/tax-exemption/constants.js");
/* harmony import */ var _use_extension_state__WEBPACK_IMPORTED_MODULE_10__ = __webpack_require__(/*! ./use-extension-state */ "./assets/js/blocks/tax-exemption/use-extension-state.js");

/**
 * External dependencies
 */









/**
 * Internal dependencies
 */


const DEFAULT_CERTIFICATE = {
  'SinglePurchase': false,
  'ExemptState': '',
  'TaxType': 'StateIssued',
  'StateOfIssue': '',
  'IDNumber': '',
  'PurchaserBusinessType': '',
  'PurchaserBusinessTypeOtherValue': '',
  'PurchaserExemptionReason': '',
  'PurchaserExemptionReasonValue': ''
};
const NewCertificateForm = () => {
  const [certificate, setCertificate] = (0,_use_extension_state__WEBPACK_IMPORTED_MODULE_10__.useExtensionState)('certificate', DEFAULT_CERTIFICATE);
  const updateCertificate = (key, value) => {
    setCertificate({
      ...certificate,
      [key]: value
    });
  };
  const {
    businessTypeOtherError,
    exemptionReasonOtherError
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_2__.useSelect)(select => {
    const store = select(_woocommerce_block_data__WEBPACK_IMPORTED_MODULE_3__.VALIDATION_STORE_KEY);
    return {
      businessTypeOtherError: store.getValidationError('business-type-other-error'),
      exemptionReasonOtherError: store.getValidationError('exemption-reason-other-error')
    };
  });
  const showError = error => !!error?.message && !error?.hidden;
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "sst-tax-exemption__form"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("p", {
    class: "sst-tax-exemption__form-disclaimer"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("strong", null, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('WARNING:', 'simple-sales-tax')), (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('You are responsible for knowing if you qualify to claim exemption from tax in the state that is due tax on this sale. You  will be held liable for any tax and interest, as well as civil and criminal penalties imposed by the member state, if you are not eligible to claim this exemption.', 'simple-sales-tax')), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_woocommerce_blocks_checkout__WEBPACK_IMPORTED_MODULE_4__.CheckboxControl, {
    id: "is-single-purchase",
    checked: certificate.SinglePurchase,
    onChange: value => updateCertificate('SinglePurchase', value)
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('This is a single-purchase exemption certificate', 'simple-sales-tax')), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_woocommerce_base_components_state_input_index__WEBPACK_IMPORTED_MODULE_7__.StateInput, {
    id: "exempt-state",
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Exempt state', 'simple-sales-tax'),
    country: "US",
    states: _woocommerce_block_settings__WEBPACK_IMPORTED_MODULE_8__.ALLOWED_STATES,
    value: certificate.ExemptState,
    onChange: value => updateCertificate('ExemptState', value),
    required: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "sst-radio-group",
    role: "radiogroup",
    ariaLabelledBy: "tax-id-type-label"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("label", {
    id: "tax-id-type-label"
  }, (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Tax ID type', 'simple-sales-tax')), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_woocommerce_blocks_components__WEBPACK_IMPORTED_MODULE_5__.RadioControl, {
    id: "tax-id-type",
    selected: certificate.TaxType,
    options: [{
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('State Issued Exemption ID or Drivers License', 'simple-sales-tax'),
      value: 'StateIssued'
    }, {
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Federal Employer ID', 'simple-sales-tax'),
      value: 'FEIN'
    }],
    onChange: value => updateCertificate('TaxType', value)
  })), certificate.TaxType === 'StateIssued' && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_woocommerce_base_components_state_input_index__WEBPACK_IMPORTED_MODULE_7__.StateInput, {
    id: "issuing-state",
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Issuing state', 'simple-sales-tax'),
    country: "US",
    states: _woocommerce_block_settings__WEBPACK_IMPORTED_MODULE_8__.ALLOWED_STATES,
    value: certificate.StateOfIssue,
    onChange: value => updateCertificate('StateOfIssue', value),
    required: true
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_woocommerce_blocks_components__WEBPACK_IMPORTED_MODULE_5__.ValidatedTextInput, {
    id: "tax-id-number",
    errorId: "tax-id-number-error",
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Tax ID', 'simple-sales-tax'),
    required: true,
    type: "text",
    value: certificate.IDNumber,
    onChange: value => updateCertificate('IDNumber', value)
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_woocommerce_base_components_select_index__WEBPACK_IMPORTED_MODULE_6__.Select, {
    id: "business-type",
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Business type', 'simple-sales-tax'),
    onChange: value => updateCertificate('PurchaserBusinessType', value),
    options: _constants__WEBPACK_IMPORTED_MODULE_9__.BUSINESS_TYPE_OPTIONS,
    value: certificate.PurchaserBusinessType,
    required: true
  }), certificate.PurchaserBusinessType === 'Other' && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_woocommerce_blocks_components__WEBPACK_IMPORTED_MODULE_5__.ValidatedTextInput, {
    id: "business-type-other",
    errorId: "business-type-other-error",
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Explain the nature of your business', 'simple-sales-tax'),
    required: true,
    type: "text",
    value: certificate.PurchaserBusinessTypeOtherValue,
    onChange: value => updateCertificate('PurchaserBusinessTypeOtherValue', value),
    showError: showError(businessTypeOtherError),
    errorMessage: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Please explain the nature of your business', 'simple-sales-tax')
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_woocommerce_base_components_select_index__WEBPACK_IMPORTED_MODULE_6__.Select, {
    id: "exemption-reason",
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Reason for exemption', 'simple-sales-tax'),
    onChange: value => updateCertificate('PurchaserExemptionReason', value),
    options: _constants__WEBPACK_IMPORTED_MODULE_9__.EXEMPTION_REASON_OPTIONS,
    value: certificate.PurchaserExemptionReason,
    required: true
  }), certificate.PurchaserExemptionReason === 'Other' && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_woocommerce_blocks_components__WEBPACK_IMPORTED_MODULE_5__.ValidatedTextInput, {
    id: "exemption-reason-other",
    errorId: "exemption-reason-other-error",
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Please explain', 'simple-sales-tax'),
    required: true,
    type: "text",
    value: certificate.PurchaserExemptionReasonValue,
    onChange: value => updateCertificate('PurchaserExemptionReasonValue', value),
    showError: showError(exemptionReasonOtherError),
    errorMessage: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Please explain why you are tax exempt', 'simple-sales-tax')
  }));
};

/***/ }),

/***/ "./assets/js/blocks/tax-exemption/use-extension-state.js":
/*!***************************************************************!*\
  !*** ./assets/js/blocks/tax-exemption/use-extension-state.js ***!
  \***************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   useExtensionState: () => (/* binding */ useExtensionState)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _woocommerce_block_data__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @woocommerce/block-data */ "@woocommerce/block-data");
/* harmony import */ var _woocommerce_block_data__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_woocommerce_block_data__WEBPACK_IMPORTED_MODULE_2__);
/**
 * External dependencies
 */



const EXTENSION_NAMESPACE = 'simple-sales-tax';
const useExtensionState = (key, initialValue) => {
  const {
    __internalSetExtensionData
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.useDispatch)(_woocommerce_block_data__WEBPACK_IMPORTED_MODULE_2__.CHECKOUT_STORE_KEY);
  const value = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.useSelect)(select => {
    var _extensionData$EXTENS;
    const store = select(_woocommerce_block_data__WEBPACK_IMPORTED_MODULE_2__.CHECKOUT_STORE_KEY);
    const extensionData = store.getExtensionData();
    return (_extensionData$EXTENS = extensionData[EXTENSION_NAMESPACE]?.[key]) !== null && _extensionData$EXTENS !== void 0 ? _extensionData$EXTENS : initialValue;
  });
  const setValue = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(value => {
    __internalSetExtensionData(EXTENSION_NAMESPACE, {
      [key]: value
    });
  }, [__internalSetExtensionData]);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    setValue(initialValue);
  }, [initialValue, setValue]);
  return [value, setValue];
};

/***/ }),

/***/ "./includes/vendor/woocommerce/woocommerce/plugins/woocommerce-blocks/assets/js/base/components/select/index.tsx":
/*!***********************************************************************************************************************!*\
  !*** ./includes/vendor/woocommerce/woocommerce/plugins/woocommerce-blocks/assets/js/base/components/select/index.tsx ***!
  \***********************************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   Select: () => (/* binding */ Select)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/icon/index.js");
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/chevron-down.js");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./style.scss */ "./includes/vendor/woocommerce/woocommerce/plugins/woocommerce-blocks/assets/js/base/components/select/style.scss");

/**
 * External dependencies
 */



/**
 * Internal dependencies
 */

const Select = props => {
  const {
    onChange,
    options,
    label,
    value,
    className,
    size,
    ...restOfProps
  } = props;
  const selectOnChange = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useCallback)(event => {
    onChange(event.target.value);
  }, [onChange]);
  const generatedId = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useId)();
  const inputId = restOfProps.id || `wc-blocks-components-select-${generatedId}`;
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: `wc-blocks-components-select ${className || ''}`
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "wc-blocks-components-select__container"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("label", {
    htmlFor: inputId,
    className: "wc-blocks-components-select__label"
  }, label), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("select", {
    className: "wc-blocks-components-select__select",
    id: inputId,
    size: size !== undefined ? size : 1,
    onChange: selectOnChange,
    value: value,
    ...restOfProps
  }, options.map(option => (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("option", {
    key: option.value,
    value: option.value,
    "data-alternate-values": `[${option.label}]`,
    disabled: option.disabled !== undefined ? option.disabled : false
  }, option.label))), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_icons__WEBPACK_IMPORTED_MODULE_3__["default"], {
    className: "wc-blocks-components-select__expand",
    icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_4__["default"]
  })));
};

/***/ }),

/***/ "./includes/vendor/woocommerce/woocommerce/plugins/woocommerce-blocks/assets/js/base/components/state-input/billing-state-input.tsx":
/*!******************************************************************************************************************************************!*\
  !*** ./includes/vendor/woocommerce/woocommerce/plugins/woocommerce-blocks/assets/js/base/components/state-input/billing-state-input.tsx ***!
  \******************************************************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _woocommerce_block_settings__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @woocommerce/block-settings */ "./includes/vendor/woocommerce/woocommerce/plugins/woocommerce-blocks/assets/js/settings/blocks/index.ts");
/* harmony import */ var _state_input__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./state-input */ "./includes/vendor/woocommerce/woocommerce/plugins/woocommerce-blocks/assets/js/base/components/state-input/state-input.tsx");

/**
 * External dependencies
 */


/**
 * Internal dependencies
 */

const BillingStateInput = props => {
  const {
    ...restOfProps
  } = props;
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_state_input__WEBPACK_IMPORTED_MODULE_2__["default"], {
    states: _woocommerce_block_settings__WEBPACK_IMPORTED_MODULE_1__.ALLOWED_STATES,
    ...restOfProps
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (BillingStateInput);

/***/ }),

/***/ "./includes/vendor/woocommerce/woocommerce/plugins/woocommerce-blocks/assets/js/base/components/state-input/index.ts":
/*!***************************************************************************************************************************!*\
  !*** ./includes/vendor/woocommerce/woocommerce/plugins/woocommerce-blocks/assets/js/base/components/state-input/index.ts ***!
  \***************************************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   BillingStateInput: () => (/* reexport safe */ _billing_state_input__WEBPACK_IMPORTED_MODULE_1__["default"]),
/* harmony export */   ShippingStateInput: () => (/* reexport safe */ _shipping_state_input__WEBPACK_IMPORTED_MODULE_2__["default"]),
/* harmony export */   StateInput: () => (/* reexport safe */ _state_input__WEBPACK_IMPORTED_MODULE_0__["default"])
/* harmony export */ });
/* harmony import */ var _state_input__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./state-input */ "./includes/vendor/woocommerce/woocommerce/plugins/woocommerce-blocks/assets/js/base/components/state-input/state-input.tsx");
/* harmony import */ var _billing_state_input__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./billing-state-input */ "./includes/vendor/woocommerce/woocommerce/plugins/woocommerce-blocks/assets/js/base/components/state-input/billing-state-input.tsx");
/* harmony import */ var _shipping_state_input__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./shipping-state-input */ "./includes/vendor/woocommerce/woocommerce/plugins/woocommerce-blocks/assets/js/base/components/state-input/shipping-state-input.tsx");




/***/ }),

/***/ "./includes/vendor/woocommerce/woocommerce/plugins/woocommerce-blocks/assets/js/base/components/state-input/shipping-state-input.tsx":
/*!*******************************************************************************************************************************************!*\
  !*** ./includes/vendor/woocommerce/woocommerce/plugins/woocommerce-blocks/assets/js/base/components/state-input/shipping-state-input.tsx ***!
  \*******************************************************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _woocommerce_block_settings__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @woocommerce/block-settings */ "./includes/vendor/woocommerce/woocommerce/plugins/woocommerce-blocks/assets/js/settings/blocks/index.ts");
/* harmony import */ var _state_input__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./state-input */ "./includes/vendor/woocommerce/woocommerce/plugins/woocommerce-blocks/assets/js/base/components/state-input/state-input.tsx");

/**
 * External dependencies
 */


/**
 * Internal dependencies
 */

const ShippingStateInput = props => {
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_state_input__WEBPACK_IMPORTED_MODULE_2__["default"], {
    states: _woocommerce_block_settings__WEBPACK_IMPORTED_MODULE_1__.SHIPPING_STATES,
    ...props
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (ShippingStateInput);

/***/ }),

/***/ "./includes/vendor/woocommerce/woocommerce/plugins/woocommerce-blocks/assets/js/base/components/state-input/state-input.tsx":
/*!**********************************************************************************************************************************!*\
  !*** ./includes/vendor/woocommerce/woocommerce/plugins/woocommerce-blocks/assets/js/base/components/state-input/state-input.tsx ***!
  \**********************************************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_html_entities__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/html-entities */ "@wordpress/html-entities");
/* harmony import */ var _wordpress_html_entities__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_html_entities__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _woocommerce_blocks_components__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @woocommerce/blocks-components */ "@woocommerce/blocks-components");
/* harmony import */ var _woocommerce_blocks_components__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_woocommerce_blocks_components__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_5__);
/* harmony import */ var _woocommerce_block_data__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! @woocommerce/block-data */ "@woocommerce/block-data");
/* harmony import */ var _woocommerce_block_data__WEBPACK_IMPORTED_MODULE_6___default = /*#__PURE__*/__webpack_require__.n(_woocommerce_block_data__WEBPACK_IMPORTED_MODULE_6__);
/* harmony import */ var clsx__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! clsx */ "./node_modules/clsx/dist/clsx.mjs");
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ./style.scss */ "./includes/vendor/woocommerce/woocommerce/plugins/woocommerce-blocks/assets/js/base/components/state-input/style.scss");
/* harmony import */ var _select__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! ../select */ "./includes/vendor/woocommerce/woocommerce/plugins/woocommerce-blocks/assets/js/base/components/select/index.tsx");

/**
 * External dependencies
 */








/**
 * Internal dependencies
 */


const optionMatcher = (value, options) => {
  const foundOption = options.find(option => option.label.toLocaleUpperCase() === value.toLocaleUpperCase() || option.value.toLocaleUpperCase() === value.toLocaleUpperCase());
  return foundOption ? foundOption.value : '';
};
const StateInput = ({
  className,
  id,
  states,
  country,
  label,
  onChange,
  autoComplete = 'off',
  value = '',
  required = false,
  errorId
}) => {
  const countryStates = states[country];
  const options = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useMemo)(() => {
    if (countryStates && Object.keys(countryStates).length > 0) {
      const emptyStateOption = {
        value: '',
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.sprintf)( /* translators: %s will be the type of province depending on country, e.g "state" or "state/county" or "department" */
        (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)('Select a %s', 'woocommerce'), label?.toLowerCase()),
        disabled: true
      };
      return [emptyStateOption, ...Object.keys(countryStates).map(key => ({
        value: key,
        label: (0,_wordpress_html_entities__WEBPACK_IMPORTED_MODULE_1__.decodeEntities)(countryStates[key])
      }))];
    }
    return [];
  }, [countryStates, label]);
  const validationError = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_5__.useSelect)(select => {
    const store = select(_woocommerce_block_data__WEBPACK_IMPORTED_MODULE_6__.VALIDATION_STORE_KEY);
    return store.getValidationError(errorId || '') || {
      hidden: true
    };
  });

  /**
   * Handles state selection onChange events. Finds a matching state by key or value.
   */
  const onChangeState = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useCallback)(stateValue => {
    const newValue = options.length > 0 ? optionMatcher(stateValue, options) : stateValue;
    if (newValue !== value) {
      onChange(newValue);
    }
  }, [onChange, options, value]);

  /**
   * Track value changes.
   */
  const valueRef = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useRef)(value);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useEffect)(() => {
    if (valueRef.current !== value) {
      valueRef.current = value;
    }
  }, [value]);

  /**
   * If given a list of options, ensure the value matches those options or trigger change.
   */
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useEffect)(() => {
    if (options.length > 0 && valueRef.current) {
      const match = optionMatcher(valueRef.current, options);
      if (match !== valueRef.current) {
        onChangeState(match);
      }
    }
  }, [options, onChangeState]);
  if (options.length > 0) {
    return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
      className: (0,clsx__WEBPACK_IMPORTED_MODULE_7__.clsx)(className, 'wc-block-components-state-input', {
        'has-error': !validationError.hidden
      })
    }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_select__WEBPACK_IMPORTED_MODULE_9__.Select, {
      options: options,
      label: label || '',
      className: `${className || ''}`,
      id: id,
      onChange: newValue => {
        if (required) {}
        onChangeState(newValue);
      },
      value: value,
      autoComplete: autoComplete,
      required: required
    }), validationError && validationError.hidden !== true && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_woocommerce_blocks_components__WEBPACK_IMPORTED_MODULE_3__.ValidationInputError, {
      errorMessage: validationError.message
    }));
  }
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_woocommerce_blocks_components__WEBPACK_IMPORTED_MODULE_3__.ValidatedTextInput, {
    className: className,
    id: id,
    label: label,
    onChange: onChangeState,
    autoComplete: autoComplete,
    value: value,
    required: required
  });
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (StateInput);

/***/ }),

/***/ "./includes/vendor/woocommerce/woocommerce/plugins/woocommerce-blocks/assets/js/settings/blocks/constants.ts":
/*!*******************************************************************************************************************!*\
  !*** ./includes/vendor/woocommerce/woocommerce/plugins/woocommerce-blocks/assets/js/settings/blocks/constants.ts ***!
  \*******************************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   ADDRESS_FORM_FIELDS: () => (/* binding */ ADDRESS_FORM_FIELDS),
/* harmony export */   ADDRESS_FORM_KEYS: () => (/* binding */ ADDRESS_FORM_KEYS),
/* harmony export */   ALLOWED_COUNTRIES: () => (/* binding */ ALLOWED_COUNTRIES),
/* harmony export */   ALLOWED_STATES: () => (/* binding */ ALLOWED_STATES),
/* harmony export */   CART_PAGE_ID: () => (/* binding */ CART_PAGE_ID),
/* harmony export */   CART_URL: () => (/* binding */ CART_URL),
/* harmony export */   CHECKOUT_PAGE_ID: () => (/* binding */ CHECKOUT_PAGE_ID),
/* harmony export */   CHECKOUT_URL: () => (/* binding */ CHECKOUT_URL),
/* harmony export */   CONTACT_FORM_FIELDS: () => (/* binding */ CONTACT_FORM_FIELDS),
/* harmony export */   CONTACT_FORM_KEYS: () => (/* binding */ CONTACT_FORM_KEYS),
/* harmony export */   COUNTRY_LOCALE: () => (/* binding */ COUNTRY_LOCALE),
/* harmony export */   LOCAL_PICKUP_ENABLED: () => (/* binding */ LOCAL_PICKUP_ENABLED),
/* harmony export */   LOGIN_URL: () => (/* binding */ LOGIN_URL),
/* harmony export */   ORDER_FORM_FIELDS: () => (/* binding */ ORDER_FORM_FIELDS),
/* harmony export */   ORDER_FORM_KEYS: () => (/* binding */ ORDER_FORM_KEYS),
/* harmony export */   PRIVACY_PAGE_NAME: () => (/* binding */ PRIVACY_PAGE_NAME),
/* harmony export */   PRIVACY_URL: () => (/* binding */ PRIVACY_URL),
/* harmony export */   SHIPPING_COUNTRIES: () => (/* binding */ SHIPPING_COUNTRIES),
/* harmony export */   SHIPPING_STATES: () => (/* binding */ SHIPPING_STATES),
/* harmony export */   SHOP_URL: () => (/* binding */ SHOP_URL),
/* harmony export */   TERMS_PAGE_NAME: () => (/* binding */ TERMS_PAGE_NAME),
/* harmony export */   TERMS_URL: () => (/* binding */ TERMS_URL),
/* harmony export */   WC_BLOCKS_BUILD_URL: () => (/* binding */ WC_BLOCKS_BUILD_URL),
/* harmony export */   WC_BLOCKS_IMAGE_URL: () => (/* binding */ WC_BLOCKS_IMAGE_URL),
/* harmony export */   blocksConfig: () => (/* binding */ blocksConfig)
/* harmony export */ });
/* harmony import */ var _woocommerce_settings__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @woocommerce/settings */ "@woocommerce/settings");
/* harmony import */ var _woocommerce_settings__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_woocommerce_settings__WEBPACK_IMPORTED_MODULE_0__);
/**
 * External dependencies
 */

const blocksConfig = (0,_woocommerce_settings__WEBPACK_IMPORTED_MODULE_0__.getSetting)('wcBlocksConfig', {
  pluginUrl: '',
  productCount: 0,
  defaultAvatar: '',
  restApiRoutes: {},
  wordCountType: 'words'
});
const WC_BLOCKS_IMAGE_URL = blocksConfig.pluginUrl + 'assets/images/';
const WC_BLOCKS_BUILD_URL = blocksConfig.pluginUrl + 'assets/client/blocks/';
const SHOP_URL = _woocommerce_settings__WEBPACK_IMPORTED_MODULE_0__.STORE_PAGES.shop?.permalink;
const CHECKOUT_PAGE_ID = _woocommerce_settings__WEBPACK_IMPORTED_MODULE_0__.STORE_PAGES.checkout?.id;
const CHECKOUT_URL = _woocommerce_settings__WEBPACK_IMPORTED_MODULE_0__.STORE_PAGES.checkout?.permalink;
const PRIVACY_URL = _woocommerce_settings__WEBPACK_IMPORTED_MODULE_0__.STORE_PAGES.privacy?.permalink;
const PRIVACY_PAGE_NAME = _woocommerce_settings__WEBPACK_IMPORTED_MODULE_0__.STORE_PAGES.privacy?.title;
const TERMS_URL = _woocommerce_settings__WEBPACK_IMPORTED_MODULE_0__.STORE_PAGES.terms?.permalink;
const TERMS_PAGE_NAME = _woocommerce_settings__WEBPACK_IMPORTED_MODULE_0__.STORE_PAGES.terms?.title;
const CART_PAGE_ID = _woocommerce_settings__WEBPACK_IMPORTED_MODULE_0__.STORE_PAGES.cart?.id;
const CART_URL = _woocommerce_settings__WEBPACK_IMPORTED_MODULE_0__.STORE_PAGES.cart?.permalink;
const LOGIN_URL = _woocommerce_settings__WEBPACK_IMPORTED_MODULE_0__.STORE_PAGES.myaccount?.permalink ? _woocommerce_settings__WEBPACK_IMPORTED_MODULE_0__.STORE_PAGES.myaccount.permalink : (0,_woocommerce_settings__WEBPACK_IMPORTED_MODULE_0__.getSetting)('wpLoginUrl', '/wp-login.php');
const LOCAL_PICKUP_ENABLED = (0,_woocommerce_settings__WEBPACK_IMPORTED_MODULE_0__.getSetting)('localPickupEnabled', false);
// Contains country names.
const countries = (0,_woocommerce_settings__WEBPACK_IMPORTED_MODULE_0__.getSetting)('countries', {});

// Contains country settings.
const countryData = (0,_woocommerce_settings__WEBPACK_IMPORTED_MODULE_0__.getSetting)('countryData', {});
const ALLOWED_COUNTRIES = Object.fromEntries(Object.keys(countryData).filter(countryCode => {
  return countryData[countryCode].allowBilling === true;
}).map(countryCode => {
  return [countryCode, countries[countryCode] || ''];
}));
const ALLOWED_STATES = Object.fromEntries(Object.keys(countryData).filter(countryCode => {
  return countryData[countryCode].allowBilling === true;
}).map(countryCode => {
  return [countryCode, countryData[countryCode].states || []];
}));
const SHIPPING_COUNTRIES = Object.fromEntries(Object.keys(countryData).filter(countryCode => {
  return countryData[countryCode].allowShipping === true;
}).map(countryCode => {
  return [countryCode, countries[countryCode] || ''];
}));
const SHIPPING_STATES = Object.fromEntries(Object.keys(countryData).filter(countryCode => {
  return countryData[countryCode].allowShipping === true;
}).map(countryCode => {
  return [countryCode, countryData[countryCode].states || []];
}));
const COUNTRY_LOCALE = Object.fromEntries(Object.keys(countryData).map(countryCode => {
  return [countryCode, countryData[countryCode].locale || []];
}));
const defaultFieldsLocations = {
  address: ['first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'postcode', 'country', 'state', 'phone'],
  contact: ['email'],
  order: []
};
const ADDRESS_FORM_KEYS = (0,_woocommerce_settings__WEBPACK_IMPORTED_MODULE_0__.getSetting)('addressFieldsLocations', defaultFieldsLocations).address;
const CONTACT_FORM_KEYS = (0,_woocommerce_settings__WEBPACK_IMPORTED_MODULE_0__.getSetting)('addressFieldsLocations', defaultFieldsLocations).contact;
const ORDER_FORM_KEYS = (0,_woocommerce_settings__WEBPACK_IMPORTED_MODULE_0__.getSetting)('addressFieldsLocations', defaultFieldsLocations).order;
const ORDER_FORM_FIELDS = (0,_woocommerce_settings__WEBPACK_IMPORTED_MODULE_0__.getSetting)('additionalOrderFields', {});
const CONTACT_FORM_FIELDS = (0,_woocommerce_settings__WEBPACK_IMPORTED_MODULE_0__.getSetting)('additionalContactFields', {});
const ADDRESS_FORM_FIELDS = (0,_woocommerce_settings__WEBPACK_IMPORTED_MODULE_0__.getSetting)('additionalAddressFields', {});

/***/ }),

/***/ "./includes/vendor/woocommerce/woocommerce/plugins/woocommerce-blocks/assets/js/settings/blocks/feature-flags.ts":
/*!***********************************************************************************************************************!*\
  !*** ./includes/vendor/woocommerce/woocommerce/plugins/woocommerce-blocks/assets/js/settings/blocks/feature-flags.ts ***!
  \***********************************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   isExperimentalBlocksEnabled: () => (/* binding */ isExperimentalBlocksEnabled)
/* harmony export */ });
/* harmony import */ var _woocommerce_settings__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @woocommerce/settings */ "@woocommerce/settings");
/* harmony import */ var _woocommerce_settings__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_woocommerce_settings__WEBPACK_IMPORTED_MODULE_0__);
/**
 * External dependencies
 */


/**
 * Internal dependencies
 */

/**
 * Checks if experimental blocks are enabled.
 *
 * @return {boolean} True if this experimental blocks are enabled.
 */
const isExperimentalBlocksEnabled = () => {
  const {
    experimentalBlocksEnabled
  } = (0,_woocommerce_settings__WEBPACK_IMPORTED_MODULE_0__.getSetting)('wcBlocksConfig', {
    experimentalBlocksEnabled: false
  });
  return experimentalBlocksEnabled;
};

/***/ }),

/***/ "./includes/vendor/woocommerce/woocommerce/plugins/woocommerce-blocks/assets/js/settings/blocks/index.ts":
/*!***************************************************************************************************************!*\
  !*** ./includes/vendor/woocommerce/woocommerce/plugins/woocommerce-blocks/assets/js/settings/blocks/index.ts ***!
  \***************************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   ADDRESS_FORM_FIELDS: () => (/* reexport safe */ _constants__WEBPACK_IMPORTED_MODULE_0__.ADDRESS_FORM_FIELDS),
/* harmony export */   ADDRESS_FORM_KEYS: () => (/* reexport safe */ _constants__WEBPACK_IMPORTED_MODULE_0__.ADDRESS_FORM_KEYS),
/* harmony export */   ALLOWED_COUNTRIES: () => (/* reexport safe */ _constants__WEBPACK_IMPORTED_MODULE_0__.ALLOWED_COUNTRIES),
/* harmony export */   ALLOWED_STATES: () => (/* reexport safe */ _constants__WEBPACK_IMPORTED_MODULE_0__.ALLOWED_STATES),
/* harmony export */   CART_PAGE_ID: () => (/* reexport safe */ _constants__WEBPACK_IMPORTED_MODULE_0__.CART_PAGE_ID),
/* harmony export */   CART_URL: () => (/* reexport safe */ _constants__WEBPACK_IMPORTED_MODULE_0__.CART_URL),
/* harmony export */   CHECKOUT_PAGE_ID: () => (/* reexport safe */ _constants__WEBPACK_IMPORTED_MODULE_0__.CHECKOUT_PAGE_ID),
/* harmony export */   CHECKOUT_URL: () => (/* reexport safe */ _constants__WEBPACK_IMPORTED_MODULE_0__.CHECKOUT_URL),
/* harmony export */   CONTACT_FORM_FIELDS: () => (/* reexport safe */ _constants__WEBPACK_IMPORTED_MODULE_0__.CONTACT_FORM_FIELDS),
/* harmony export */   CONTACT_FORM_KEYS: () => (/* reexport safe */ _constants__WEBPACK_IMPORTED_MODULE_0__.CONTACT_FORM_KEYS),
/* harmony export */   COUNTRY_LOCALE: () => (/* reexport safe */ _constants__WEBPACK_IMPORTED_MODULE_0__.COUNTRY_LOCALE),
/* harmony export */   LOCAL_PICKUP_ENABLED: () => (/* reexport safe */ _constants__WEBPACK_IMPORTED_MODULE_0__.LOCAL_PICKUP_ENABLED),
/* harmony export */   LOGIN_URL: () => (/* reexport safe */ _constants__WEBPACK_IMPORTED_MODULE_0__.LOGIN_URL),
/* harmony export */   ORDER_FORM_FIELDS: () => (/* reexport safe */ _constants__WEBPACK_IMPORTED_MODULE_0__.ORDER_FORM_FIELDS),
/* harmony export */   ORDER_FORM_KEYS: () => (/* reexport safe */ _constants__WEBPACK_IMPORTED_MODULE_0__.ORDER_FORM_KEYS),
/* harmony export */   PRIVACY_PAGE_NAME: () => (/* reexport safe */ _constants__WEBPACK_IMPORTED_MODULE_0__.PRIVACY_PAGE_NAME),
/* harmony export */   PRIVACY_URL: () => (/* reexport safe */ _constants__WEBPACK_IMPORTED_MODULE_0__.PRIVACY_URL),
/* harmony export */   SHIPPING_COUNTRIES: () => (/* reexport safe */ _constants__WEBPACK_IMPORTED_MODULE_0__.SHIPPING_COUNTRIES),
/* harmony export */   SHIPPING_STATES: () => (/* reexport safe */ _constants__WEBPACK_IMPORTED_MODULE_0__.SHIPPING_STATES),
/* harmony export */   SHOP_URL: () => (/* reexport safe */ _constants__WEBPACK_IMPORTED_MODULE_0__.SHOP_URL),
/* harmony export */   TERMS_PAGE_NAME: () => (/* reexport safe */ _constants__WEBPACK_IMPORTED_MODULE_0__.TERMS_PAGE_NAME),
/* harmony export */   TERMS_URL: () => (/* reexport safe */ _constants__WEBPACK_IMPORTED_MODULE_0__.TERMS_URL),
/* harmony export */   WC_BLOCKS_BUILD_URL: () => (/* reexport safe */ _constants__WEBPACK_IMPORTED_MODULE_0__.WC_BLOCKS_BUILD_URL),
/* harmony export */   WC_BLOCKS_IMAGE_URL: () => (/* reexport safe */ _constants__WEBPACK_IMPORTED_MODULE_0__.WC_BLOCKS_IMAGE_URL),
/* harmony export */   blocksConfig: () => (/* reexport safe */ _constants__WEBPACK_IMPORTED_MODULE_0__.blocksConfig),
/* harmony export */   isExperimentalBlocksEnabled: () => (/* reexport safe */ _feature_flags__WEBPACK_IMPORTED_MODULE_1__.isExperimentalBlocksEnabled)
/* harmony export */ });
/* harmony import */ var _constants__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./constants */ "./includes/vendor/woocommerce/woocommerce/plugins/woocommerce-blocks/assets/js/settings/blocks/constants.ts");
/* harmony import */ var _feature_flags__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./feature-flags */ "./includes/vendor/woocommerce/woocommerce/plugins/woocommerce-blocks/assets/js/settings/blocks/feature-flags.ts");
/**
 * Internal dependencies
 */



/***/ }),

/***/ "./includes/vendor/woocommerce/woocommerce/plugins/woocommerce-blocks/assets/js/base/components/select/style.scss":
/*!************************************************************************************************************************!*\
  !*** ./includes/vendor/woocommerce/woocommerce/plugins/woocommerce-blocks/assets/js/base/components/select/style.scss ***!
  \************************************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "./includes/vendor/woocommerce/woocommerce/plugins/woocommerce-blocks/assets/js/base/components/state-input/style.scss":
/*!*****************************************************************************************************************************!*\
  !*** ./includes/vendor/woocommerce/woocommerce/plugins/woocommerce-blocks/assets/js/base/components/state-input/style.scss ***!
  \*****************************************************************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "react":
/*!************************!*\
  !*** external "React" ***!
  \************************/
/***/ ((module) => {

module.exports = window["React"];

/***/ }),

/***/ "@woocommerce/blocks-checkout":
/*!****************************************!*\
  !*** external ["wc","blocksCheckout"] ***!
  \****************************************/
/***/ ((module) => {

module.exports = window["wc"]["blocksCheckout"];

/***/ }),

/***/ "@woocommerce/blocks-components":
/*!******************************************!*\
  !*** external ["wc","blocksComponents"] ***!
  \******************************************/
/***/ ((module) => {

module.exports = window["wc"]["blocksComponents"];

/***/ }),

/***/ "@woocommerce/block-data":
/*!**************************************!*\
  !*** external ["wc","wcBlocksData"] ***!
  \**************************************/
/***/ ((module) => {

module.exports = window["wc"]["wcBlocksData"];

/***/ }),

/***/ "@woocommerce/settings":
/*!************************************!*\
  !*** external ["wc","wcSettings"] ***!
  \************************************/
/***/ ((module) => {

module.exports = window["wc"]["wcSettings"];

/***/ }),

/***/ "@wordpress/data":
/*!******************************!*\
  !*** external ["wp","data"] ***!
  \******************************/
/***/ ((module) => {

module.exports = window["wp"]["data"];

/***/ }),

/***/ "@wordpress/element":
/*!*********************************!*\
  !*** external ["wp","element"] ***!
  \*********************************/
/***/ ((module) => {

module.exports = window["wp"]["element"];

/***/ }),

/***/ "@wordpress/html-entities":
/*!**************************************!*\
  !*** external ["wp","htmlEntities"] ***!
  \**************************************/
/***/ ((module) => {

module.exports = window["wp"]["htmlEntities"];

/***/ }),

/***/ "@wordpress/i18n":
/*!******************************!*\
  !*** external ["wp","i18n"] ***!
  \******************************/
/***/ ((module) => {

module.exports = window["wp"]["i18n"];

/***/ }),

/***/ "@wordpress/primitives":
/*!************************************!*\
  !*** external ["wp","primitives"] ***!
  \************************************/
/***/ ((module) => {

module.exports = window["wp"]["primitives"];

/***/ }),

/***/ "./node_modules/clsx/dist/clsx.mjs":
/*!*****************************************!*\
  !*** ./node_modules/clsx/dist/clsx.mjs ***!
  \*****************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   clsx: () => (/* binding */ clsx),
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
function r(e){var t,f,n="";if("string"==typeof e||"number"==typeof e)n+=e;else if("object"==typeof e)if(Array.isArray(e)){var o=e.length;for(t=0;t<o;t++)e[t]&&(f=r(e[t]))&&(n&&(n+=" "),n+=f)}else for(f in e)e[f]&&(n&&(n+=" "),n+=f);return n}function clsx(){for(var e,t,f=0,n="",o=arguments.length;f<o;f++)(e=arguments[f])&&(t=r(e))&&(n&&(n+=" "),n+=t);return n}/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (clsx);

/***/ }),

/***/ "./assets/js/blocks/tax-exemption/block.json":
/*!***************************************************!*\
  !*** ./assets/js/blocks/tax-exemption/block.json ***!
  \***************************************************/
/***/ ((module) => {

module.exports = /*#__PURE__*/JSON.parse('{"apiVersion":2,"name":"simple-sales-tax/tax-exemption","version":"1.0.0","title":"Tax Exemption Block","category":"woocommerce","description":"Adds a tax exemption form to the checkout.","supports":{"html":false,"align":false,"multiple":false,"reusable":false},"parent":["woocommerce/checkout-fields-block"],"textdomain":"simple-sales-tax"}');

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/******/ 	// expose the modules object (__webpack_modules__)
/******/ 	__webpack_require__.m = __webpack_modules__;
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/chunk loaded */
/******/ 	(() => {
/******/ 		var deferred = [];
/******/ 		__webpack_require__.O = (result, chunkIds, fn, priority) => {
/******/ 			if(chunkIds) {
/******/ 				priority = priority || 0;
/******/ 				for(var i = deferred.length; i > 0 && deferred[i - 1][2] > priority; i--) deferred[i] = deferred[i - 1];
/******/ 				deferred[i] = [chunkIds, fn, priority];
/******/ 				return;
/******/ 			}
/******/ 			var notFulfilled = Infinity;
/******/ 			for (var i = 0; i < deferred.length; i++) {
/******/ 				var [chunkIds, fn, priority] = deferred[i];
/******/ 				var fulfilled = true;
/******/ 				for (var j = 0; j < chunkIds.length; j++) {
/******/ 					if ((priority & 1 === 0 || notFulfilled >= priority) && Object.keys(__webpack_require__.O).every((key) => (__webpack_require__.O[key](chunkIds[j])))) {
/******/ 						chunkIds.splice(j--, 1);
/******/ 					} else {
/******/ 						fulfilled = false;
/******/ 						if(priority < notFulfilled) notFulfilled = priority;
/******/ 					}
/******/ 				}
/******/ 				if(fulfilled) {
/******/ 					deferred.splice(i--, 1)
/******/ 					var r = fn();
/******/ 					if (r !== undefined) result = r;
/******/ 				}
/******/ 			}
/******/ 			return result;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	(() => {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = (module) => {
/******/ 			var getter = module && module.__esModule ?
/******/ 				() => (module['default']) :
/******/ 				() => (module);
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/jsonp chunk loading */
/******/ 	(() => {
/******/ 		// no baseURI
/******/ 		
/******/ 		// object to store loaded and loading chunks
/******/ 		// undefined = chunk not loaded, null = chunk preloaded/prefetched
/******/ 		// [resolve, reject, Promise] = chunk loading, 0 = chunk loaded
/******/ 		var installedChunks = {
/******/ 			"tax-exemption-block-frontend": 0,
/******/ 			"./style-tax-exemption-block": 0
/******/ 		};
/******/ 		
/******/ 		// no chunk on demand loading
/******/ 		
/******/ 		// no prefetching
/******/ 		
/******/ 		// no preloaded
/******/ 		
/******/ 		// no HMR
/******/ 		
/******/ 		// no HMR manifest
/******/ 		
/******/ 		__webpack_require__.O.j = (chunkId) => (installedChunks[chunkId] === 0);
/******/ 		
/******/ 		// install a JSONP callback for chunk loading
/******/ 		var webpackJsonpCallback = (parentChunkLoadingFunction, data) => {
/******/ 			var [chunkIds, moreModules, runtime] = data;
/******/ 			// add "moreModules" to the modules object,
/******/ 			// then flag all "chunkIds" as loaded and fire callback
/******/ 			var moduleId, chunkId, i = 0;
/******/ 			if(chunkIds.some((id) => (installedChunks[id] !== 0))) {
/******/ 				for(moduleId in moreModules) {
/******/ 					if(__webpack_require__.o(moreModules, moduleId)) {
/******/ 						__webpack_require__.m[moduleId] = moreModules[moduleId];
/******/ 					}
/******/ 				}
/******/ 				if(runtime) var result = runtime(__webpack_require__);
/******/ 			}
/******/ 			if(parentChunkLoadingFunction) parentChunkLoadingFunction(data);
/******/ 			for(;i < chunkIds.length; i++) {
/******/ 				chunkId = chunkIds[i];
/******/ 				if(__webpack_require__.o(installedChunks, chunkId) && installedChunks[chunkId]) {
/******/ 					installedChunks[chunkId][0]();
/******/ 				}
/******/ 				installedChunks[chunkId] = 0;
/******/ 			}
/******/ 			return __webpack_require__.O(result);
/******/ 		}
/******/ 		
/******/ 		var chunkLoadingGlobal = globalThis["webpackChunksimplesalestax"] = globalThis["webpackChunksimplesalestax"] || [];
/******/ 		chunkLoadingGlobal.forEach(webpackJsonpCallback.bind(null, 0));
/******/ 		chunkLoadingGlobal.push = webpackJsonpCallback.bind(null, chunkLoadingGlobal.push.bind(chunkLoadingGlobal));
/******/ 	})();
/******/ 	
/************************************************************************/
/******/ 	
/******/ 	// startup
/******/ 	// Load entry module and return exports
/******/ 	// This entry module depends on other loaded chunks and execution need to be delayed
/******/ 	var __webpack_exports__ = __webpack_require__.O(undefined, ["./style-tax-exemption-block"], () => (__webpack_require__("./assets/js/blocks/tax-exemption/frontend.js")))
/******/ 	__webpack_exports__ = __webpack_require__.O(__webpack_exports__);
/******/ 	
/******/ })()
;
//# sourceMappingURL=tax-exemption-block-frontend.js.map