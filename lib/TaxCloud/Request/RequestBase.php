<?php

namespace TaxCloud\Request;

use TaxCloud\Exceptions\RequestException;

class RequestBase
{
  protected $apiLoginID;
  protected $apiKey;

  public function __construct($apiLoginID, $apiKey)
  {
    if (empty($apiLoginID)) {
      throw new RequestException('API Login ID not set.');
    }
    elseif (is_string($apiLoginID) === FALSE) {
      throw new RequestException('API Login ID must be a string.');
    }

    if (empty($apiKey)) {
      throw new RequestException('API Key not set.');
    }
    elseif (is_string($apiKey) === FALSE) {
      throw new RequestException('API Key must be a string.');
    }
    $this->apiLoginID = $apiLoginID;
    $this->apiKey = $apiKey;
  }
}
