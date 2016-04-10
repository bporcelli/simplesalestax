<?php

/**
 * Portions Copyright (c) 2009-2012 The Federal Tax Authority, LLC (FedTax).
 * All Rights Reserved.
 *
 * This file contains Original Code and/or Modifications of Original Code as
 * defined in and that are subject to the FedTax Public Source License (the
 * ‘License’). You may not use this file except in compliance with the License.
 * Please obtain a copy of the License at http://FedTax.net/ftpsl.pdf or
 * http://dev.taxcloud.net/ftpsl/ and read it before using this file.
 *
 * The Original Code and all software distributed under the License are
 * distributed on an ‘AS IS’ basis, WITHOUT WARRANTY OF ANY KIND, EITHER
 * EXPRESS OR IMPLIED, AND FEDTAX  HEREBY DISCLAIMS ALL SUCH WARRANTIES,
 * INCLUDING WITHOUT LIMITATION, ANY WARRANTIES OF MERCHANTABILITY, FITNESS FOR
 * A PARTICULAR PURPOSE, QUIET ENJOYMENT OR NON-INFRINGEMENT.
 *
 * Please see the License for the specific language governing rights and
 * limitations under the License.
 *
 *
 *
 * Modifications made August 20, 2013 by Brian Altenhofel
 */

namespace TaxCloud;

use TaxCloud\Exceptions\AuthorizedException;
use TaxCloud\Exceptions\AuthorizedWithCaptureException;
use TaxCloud\Exceptions\CapturedException;
use TaxCloud\Exceptions\GetTICsException;
use TaxCloud\Exceptions\GetTICsByGroupException;
use TaxCloud\Exceptions\GetTICGroupsException;
use TaxCloud\Exceptions\LookupException;
use TaxCloud\Exceptions\PingException;
use TaxCloud\Exceptions\ReturnedException;
use TaxCloud\Exceptions\USPSIDException;
use TaxCloud\Exceptions\VerifyAddressException;
use TaxCloud\Request\AddExemptCertificate;
use TaxCloud\Request\Authorized;
use TaxCloud\Request\AuthorizedWithCapture;
use TaxCloud\Request\Captured;
use TaxCloud\Request\DeleteExemptCertificate;
use TaxCloud\Request\GetExemptCertificates;
use TaxCloud\Request\GetTICs;
use TaxCloud\Request\GetTICsByGroup;
use TaxCloud\Request\GetTICGroups;
use TaxCloud\Request\Lookup;
use TaxCloud\Request\LookupForDate;
use TaxCloud\Request\Ping;
use TaxCloud\Request\Returned;
use TaxCloud\Request\VerifyAddress;

/**
 * TaxCloud Web Service
 *
 * @author    Brian Altenhofel
 * @package   php-taxcloud
 */
class Client
{
  private static $classmap = array(
    'VerifyAddress' => '\TaxCloud\Request\VerifyAddress',
    'VerifyAddressResponse' => '\TaxCloud\VerifyAddressResponse',
    'VerifiedAddress' => '\TaxCloud\VerifiedAddress',
    'Address' => '\TaxCloud\Address',
    'LookupForDate' => '\TaxCloud\Request\LookupForDate',
    'CartItem' => '\TaxCloud\CartItem',
    'ExemptionCertificate' => '\TaxCloud\ExemptionCertificate',
    'ExemptionCertificateDetail' => '\TaxCloud\ExemptionCertificateDetail',
    'ExemptState' => '\TaxCloud\ExemptState',
    'State' => '\TaxCloud\State',
    'TaxID' => '\TaxCloud\TaxID',
    'TaxIDType' => '\TaxCloud\TaxIDType',
    'BusinessType' => '\TaxCloud\BusinessType',
    'ExemptionReason' => '\TaxCloud\ExemptionReason',
    'LookupForDateResponse' => '\TaxCloud\LookupForDateResponse',
    'LookupRsp' => '\TaxCloud\LookupRsp',
    'ResponseBase' => '\TaxCloud\ResponseBase',
    'MessageType' => '\TaxCloud\MessageType',
    'ResponseMessage' => '\TaxCloud\ResponseMessage',
    'CartItemResponse' => '\TaxCloud\CartItemResponse',
    'Lookup' => '\TaxCloud\Request\Lookup',
    'LookupResponse' => '\TaxCloud\LookupResponse',
    'Authorized' => '\TaxCloud\Request\Authorized',
    'AuthorizedResponse' => '\TaxCloud\AuthorizedResponse',
    'AuthorizedRsp' => '\TaxCloud\AuthorizedRsp',
    'AuthorizedWithCapture' => '\TaxCloud\Request\AuthorizedWithCapture',
    'AuthorizedWithCaptureResponse' => '\TaxCloud\AuthorizedWithCaptureResponse',
    'Captured' => '\TaxCloud\Request\Captured',
    'CapturedResponse' => '\TaxCloud\CapturedResponse',
    'CapturedRsp' => '\TaxCloud\CapturedRsp',
    'Returned' => '\TaxCloud\Request\Returned',
    'ReturnedResponse' => '\TaxCloud\ReturnedResponse',
    'ReturnedRsp' => '\TaxCloud\ReturnedRsp',
    'GetTICGroups' => '\TaxCloud\Request\GetTICGroups',
    'GetTICGroupsResponse' => '\TaxCloud\GetTICGroupsResponse',
    'GetTICGroupsRsp' => '\TaxCloud\GetTICGroupsRsp',
    'TICGroup' => '\TaxCloud\TICGroup',
    'GetTICs' => '\TaxCloud\Request\GetTICs',
    'GetTICsResponse' => '\TaxCloud\GetTICsResponse',
    'GetTICsRsp' => '\TaxCloud\GetTICsRsp',
    'TIC' => '\TaxCloud\TIC',
    'GetTICsByGroup' => '\TaxCloud\Request\GetTICsByGroup',
    'GetTICsByGroupResponse' => '\TaxCloud\GetTICsByGroupResponse',
    'AddExemptCertificate' => '\TaxCloud\Request\AddExemptCertificate',
    'AddExemptCertificateResponse' => '\TaxCloud\AddExemptCertificateResponse',
    'AddCertificateRsp' => '\TaxCloud\AddCertificateRsp',
    'DeleteExemptCertificate' => '\TaxCloud\Request\DeleteExemptCertificate',
    'DeleteExemptCertificateResponse' => '\TaxCloud\DeleteExemptCertificateResponse',
    'DeleteCertificateRsp' => '\TaxCloud\DeleteCertificateRsp',
    'GetExemptCertificates' => '\TaxCloud\Request\GetExemptCertificates',
    'GetExemptCertificatesResponse' => '\TaxCloud\GetExemptCertificatesResponse',
    'GetCertificatesRsp' => '\TaxCloud\GetCertificatesRsp',
    'Ping' => '\TaxCloud\Request\Ping',
    'PingResponse' => '\TaxCloud\PingResponse',
    'PingRsp' => '\TaxCloud\PingRsp',
  );

  public function __construct($wsdl = "https://api.taxcloud.net/1.0/?wsdl", $options = array())
  {
    $this->buildSoapClient($wsdl, $options);
  }

  /**
   *
   *
   * @param VerifyAddress $parameters
   * @return VerifyAddressResponse
   */
  public function VerifyAddress(VerifyAddress $parameters)
  {
    $VerifyAddressResponse = $this->soapClient->__soapCall('VerifyAddress', array($parameters),       array(
            'uri' => 'http://taxcloud.net',
            'soapaction' => ''
           )
         );
    $VerifyAddressResult = $VerifyAddressResponse->getVerifyAddressResult();

    if ($VerifyAddressResult->getErrNumber() == 0) {
      return $VerifyAddressResult->getAddress();
    }
    elseif ($VerifyAddressResult->getErrNumber() == '80040B1A') {
      throw new USPSIDException('Error ' . $VerifyAddressResult->getErrNumber() . ': ' . $VerifyAddressResult->getErrDescription());
    }
    else {
      throw new VerifyAddressException('Error ' . $VerifyAddressResult->getErrNumber() . ': ' . $VerifyAddressResult->getErrDescription());
    }
  }

  /**
   *
   *
   * @param LookupForDate $parameters
   * @return LookupForDateResponse
   */
  public function LookupForDate(LookupForDate $parameters)
  {
    return $this->soapClient->__soapCall('LookupForDate', array($parameters),       array(
            'uri' => 'http://taxcloud.net',
            'soapaction' => ''
           )
      );
  }

  /**
   * Lookup the applicable tax amounts for items in a cart.
   *
   * @param Lookup $parameters
   * @return array
   *   An array of cart items.
   *   The top level key of the array is the cart ID so that applications can
   *   verify that this is indeed the cart they are looking for.
   *
   *   Inside that is an array of tax amounts indexed by the cart item index
   *   (which is the line item ID in some applications).
   */
  public function Lookup(Lookup $parameters)
  {
    $LookupResponse = $this->soapClient->__soapCall('Lookup', array($parameters),       array(
            'uri' => 'http://taxcloud.net',
            'soapaction' => ''
           )
         );

    $LookupResult = $LookupResponse->getLookupResult();

    if ($LookupResult->getResponseType() == 'OK') {
      $return = array();

      foreach ($LookupResult->getCartItemsResponse() as $CartItemResponse) {
        // Single cart items are returned as a CartItem object.
        if (is_object($CartItemResponse)) {
          $return[$LookupResult->getCartID()][$CartItemResponse->getCartItemIndex()] = $CartItemResponse->getTaxAmount();
        }
        // Multiples are returned as an array of CartItem objects.
        else {
          foreach ($CartItemResponse as $CartItem) {
            $return[$LookupResult->getCartID()][$CartItem->getCartItemIndex()] = $CartItem->getTaxAmount();
          }
        }
      }

      return $return;
    }
    else {
      foreach ($LookupResult->getMessages() as $message) {
        throw new LookupException($message->getMessage());
      }
    }
  }

  /**
   *
   *
   * @param Authorized $parameters
   * @return AuthorizedResponse
   */
  public function Authorized(Authorized $parameters)
  {
    $AuthorizedResponse = $this->soapClient->__soapCall('Authorized', array($parameters),       array(
            'uri' => 'http://taxcloud.net',
            'soapaction' => ''
           )
         );

    $AuthorizedResult = $AuthorizedResponse->getAuthorizedResult();

    if ($AuthorizedResult->getResponseType() == 'OK') {
      return TRUE;
    }
    else {
      foreach ($AuthorizedResult->getMessages() as $message) {
        throw new AuthorizedException($message->getMessage());
      }
    }
  }

  /**
   *
   *
   * @param AuthorizedWithCapture $parameters
   * @return AuthorizedWithCaptureResponse
   */
  public function AuthorizedWithCapture(AuthorizedWithCapture $parameters)
  {
    $AuthorizedWithCaptureResponse = $this->soapClient->__soapCall('AuthorizedWithCapture', array($parameters),       array(
            'uri' => 'http://taxcloud.net',
            'soapaction' => ''
           )
         );

    $AuthorizedWithCaptureResult = $AuthorizedWithCaptureResponse->getAuthorizedWithCaptureResult();

    if ($AuthorizedWithCaptureResult->getResponseType() == 'OK') {
      return TRUE;
    }
    else {
      foreach ($AuthorizedWithCaptureResult->getMessages() as $message) {
        throw new AuthorizedWithCaptureException($message->getMessage());
      }
    }
  }

  /**
   *
   *
   * @param Captured $parameters
   * @return CapturedResponse
   */
  public function Captured(Captured $parameters)
  {
    $CapturedResponse = $this->soapClient->__soapCall('Captured', array($parameters),       array(
            'uri' => 'http://taxcloud.net',
            'soapaction' => ''
           )
         );

    $CapturedResult = $CapturedResponse->getCapturedResult();

    if ($CapturedResult->getResponseType() == 'OK') {
      return TRUE;
    }
    else {
      foreach ($CapturedResult->getMessages() as $message) {
        throw new CapturedException($message->getMessage());
      }
    }
  }

  /**
   *
   *
   * @param Returned $parameters
   * @return ReturnedResponse
   */
  public function Returned(Returned $parameters)
  {
    $ReturnedResponse = $this->soapClient->__soapCall('Returned', array($parameters),       array(
            'uri' => 'http://taxcloud.net',
            'soapaction' => ''
           )
         );

    $ReturnedResult = $ReturnedResponse->getReturnedResult();

    if ($ReturnedResult->getResponseType() == 'OK') {
      return TRUE;
    }
    else {
      foreach ($ReturnedResult->getMessages() as $message) {
        throw new ReturnedException($message->getMessage());
      }
    }
  }

  /**
   *
   *
   * @param GetTICGroups $parameters
   * @return GetTICGroupsResponse
   */
  public function GetTICGroups(GetTICGroups $parameters)
  {
    $GetTICGroupsResponse = $this->soapClient->__soapCall('GetTICGroups', array($parameters),       array(
            'uri' => 'http://taxcloud.net',
            'soapaction' => ''
           )
         );

    $GetTICGroupsResult = $GetTICGroupsResponse->getTICGroupsResult();
    if ($GetTICGroupsResult->getResponseType() == 'OK') {
      $TICGroups = $GetTICGroupsResult->getTICGroups();

      $return = array();
      foreach ($TICGroups as $TICGroupsArray) {
        foreach ($TICGroupsArray as $TICGroup) {
          $return[$TICGroup->getGroupID()] = $TICGroup->getDescription();
        }
      }

      return $return;
    }
    else {
      foreach ($GetTICGroupsResult->getMessages() as $message) {
        throw new GetTICGroupsException($message->getMessage());
      }
    }
  }

  /**
   *
   *
   * @param GetTICs $parameters
   * @return GetTICsResponse
   */
  public function GetTICs(GetTICs $parameters)
  {
    $GetTICsResponse = $this->soapClient->__soapCall('GetTICs', array($parameters),       array(
            'uri' => 'http://taxcloud.net',
            'soapaction' => ''
           )
         );

    $GetTICsResult = $GetTICsResponse->getTICsResult();

    if ($GetTICsResult->getResponseType() == 'OK') {
      $TICs = $GetTICsResult->getTICs();

      $return = array();
      foreach ($TICs as $TICArray) {
        foreach ($TICArray as $TIC) {
          $return[$TIC->getTICID()] = $TIC->getDescription();
        }
      }

      return $return;
    }
    else {
      foreach ($GetTICsResult->getMessages() as $message) {
        throw new GetTICsException($message->getMessage());
      }
    }
  }

  /**
   *
   *
   * @param GetTICsByGroup $parameters
   * @return GetTICsByGroupResponse
   */
  public function GetTICsByGroup(GetTICsByGroup $parameters)
  {
    $GetTICsByGroupResponse = $this->soapClient->__soapCall('GetTICsByGroup', array($parameters),       array(
            'uri' => 'http://taxcloud.net',
            'soapaction' => ''
           )
         );

    $GetTICsByGroupResult = $GetTICsByGroupResponse->GetTICsByGroupResult();

    if ($GetTICsByGroupResult->getResponseType() == 'OK') {
      $TICs = $GetTICsByGroupResult->getTICs();

      $return = array();
      foreach ($TICs as $TICArray) {
        foreach ($TICArray as $TIC) {
          $return[$TIC->getTICID()] = $TIC->getDescription();
        }
      }

      return $return;
    }
    else {
      foreach ($GetTICsByGroupResult->getMessages() as $message) {
        throw new GetTICsByGroupException($message->getMessage());
      }
    }
  }

  /**
   *
   *
   * @param AddExemptCertificate $parameters
   * @return AddExemptCertificateResponse
   */
  public function AddExemptCertificate(AddExemptCertificate $parameters)
  {
    return $this->soapClient->__soapCall('AddExemptCertificate', array($parameters),       array(
            'uri' => 'http://taxcloud.net',
            'soapaction' => ''
           )
      );
  }

  /**
   *
   *
   * @param DeleteExemptCertificate $parameters
   * @return DeleteExemptCertificateResponse
   */
  public function DeleteExemptCertificate(DeleteExemptCertificate $parameters)
  {
    return $this->soapClient->__soapCall('DeleteExemptCertificate', array($parameters),       array(
            'uri' => 'http://taxcloud.net',
            'soapaction' => ''
           )
      );
  }

  /**
   *
   *
   * @param GetExemptCertificates $parameters
   * @return GetExemptCertificatesResponse
   */
  public function GetExemptCertificates(GetExemptCertificates $parameters)
  {
    return $this->soapClient->__soapCall('GetExemptCertificates', array($parameters),       array(
            'uri' => 'http://taxcloud.net',
            'soapaction' => ''
           )
      );
  }

  /**
   *
   *
   * @param Ping $parameters
   * @return PingResponse
   */
  public function Ping(Ping $parameters)
  {
    $response = $this->soapClient->__soapCall('Ping', array($parameters),       array(
            'uri' => 'http://taxcloud.net',
            'soapaction' => ''
           )
         );
    $result = $response->getPingResult();

    if ($result->getResponseType() == 'OK') {
      return TRUE;
    }
    else {
      foreach ($result->getMessages() as $message) {
        throw new PingException($message->getMessage());
      }
    }
  }

  private function buildSoapClient($wdsl = "https://api.taxcloud.net/1.0/?wdsl", $options = array())
  {
    foreach (self::$classmap as $key => $value) {
      if (!isset($options['classmap'][$key])) {
        $options['classmap'][$key] = $value;
      }
    }

    $this->setSoapClient(new \SoapClient($wdsl, $options));
  }

  public function setSoapClient(\SoapClient $soapclient)
  {
    $this->soapClient = $soapclient;
  }
}
