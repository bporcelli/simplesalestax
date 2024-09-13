export const shouldRunBlockTests = () => {
  return Boolean(Cypress.env('RUN_BLOCK_TESTS'));
};

export const getCartTestCases = () => {
  const testCases = [
    {
      label: 'classic cart/checkout',
      useClassicCart: true,
      cartUrl: '/legacy-cart/',
      checkoutUrl: '/legacy-checkout/',
    },
  ];

  if (shouldRunBlockTests()) {
    testCases.push({
      label: 'block cart/checkout',
      useClassicCart: false,
      cartUrl: '/cart/',
      checkoutUrl: '/checkout/',
    });
  }

  return testCases;
};
