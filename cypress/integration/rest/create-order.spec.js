/// <reference types="cypress" />

import mockOrder from '../../fixtures/mockOrder.json';

describe('REST API order calculations', () => {
  it('calculates tax for orders created through the REST API', () => {
    const requestOptions = {
      'url': '/wp-json/wc/v3/orders/',
      'method': 'POST',
      'auth': {
        'username': 'admin',
        'password': 'password',
      },
      'body': mockOrder,
    };

    cy.request(requestOptions).then((response) => {
      expect(response.status).to.eq(201);
      expect(parseFloat(response.body.total_tax)).to.be.gt(0);
    });
  });
});
