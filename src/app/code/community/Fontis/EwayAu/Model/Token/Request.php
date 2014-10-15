<?php
/**
 * Fontis eWAY Australia Extension
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category   Fontis
 * @package    Fontis_EwayAu
 * @author     Chris Norton
 * @author     Matthew Gamble
 * @author     Ron Carr
 * @copyright  Copyright (c) 2014 Fontis Pty. Ltd. (http://www.fontis.com.au)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Fontis_EwayAu_Model_Token_Request
{
    // Soap API method names
    const CREATE_CUSTOMER               =   'CreateCustomer';
    const UPDATE_CUSTOMER               =   'UpdateCustomer';
    const QUERY_CUSTOMER_BY_REFERENCE   =   'QueryCustomerByReference';
    const PROCESS_PAYMENT               =   'ProcessPayment';

    const SOAP_NAMESPACE = 'https://www.eway.com.au/gateway/managedpayment';

    /**
     * @var SoapClient
     */
    protected $_client = null;

    /**
     * @var string
     */
    protected $_endpoint = null;

    protected $_header = array();

    /**
     * @var Fontis_EwayAu_Helper_Token
     */
    protected $_tokenHelper = null;

    /**
     * @param array $options
     */
    public function __construct($options = array())
    {
        if (!isset($options['wsdl']) && !is_string($options['wsdl'])) {
            Mage::throwException('Invalid payment gateway endpoint');
        }

        if ($this->validateHeader($options['header'])) {
            $this->_header = $options['header'];
        }

        if (isset($options['endpoint']) && is_string($options['endpoint'])) {
            $this->_endpoint = $options['endpoint'];
        }

        $soapOptions = array();
        if (isset($options['soap_options']) && is_array($options['soap_options'])) {
            $soapOptions = $options['soap_options'];
        }

        $this->_client = new SoapClient($options['wsdl'], $soapOptions);
    }

    /**
     * @return Fontis_EwayAu_Helper_Token
     */
    protected function getTokenHelper()
    {
        if ($this->_tokenHelper === null) {
            $this->_tokenHelper = Mage::helper('ewayau/token');
        }

        return $this->_tokenHelper;
    }

    /**
     * Perform eWAY token payments API request.
     *
     * @param string $soapMethod
     * @param array $data
     * @return Fontis_EwayAu_Model_Token_Response|SoapFault
     * @throws Mage_Core_Exception
     */
    public function execute($soapMethod, $data)
    {
        if (empty($data)) {
            Mage::throwException('Invalid checkout information.');
        }

        $this->getTokenHelper()->logMessage('request', $data);
        $this->getTokenHelper()->logMessage('header', $this->_header);

        $client = $this->getClient();

        try {
            $response = $client->__soapCall(
                $soapMethod,
                array($data),
                array(
                    'location' => $this->getEndpoint(),
                ),
                $this->getSoapHeader()
            );
        } catch (Exception $e) {
            $this->getTokenHelper()->logMessage($e->getMessage());
            Mage::throwException('There was an error when attempting to process your checkout request.');
        }

        return $response;
    }

    /**
     * Get eWAY token payment endpoint URL.
     *
     * @return bool|string
     */
    public function getEndpoint()
    {
        return $this->_endpoint;
    }

    /**
     * Get the Client object.
     *
     * @return SoapClient
     */
    public function getClient()
    {
        return $this->_client;
    }

    /**
     * Get the raw header array.
     *
     * @return array
     */
    public function getHeader()
    {
        return $this->_header;
    }

    /**
     * Get the SOAP header used to perform a request on the eWAY token payments API.
     *
     * @return SoapHeader
     */
    public function getSoapHeader()
    {
        $data = $this->getHeader();
        $namespace = self::SOAP_NAMESPACE;

        return new SoapHeader(
            $namespace,
            'eWAYHeader',
            new SoapVar($data, SOAP_ENC_OBJECT, 'eWAYHeader', $namespace)
        );
    }

    /**
     * Determine if the SOAP header data is valid.
     *
     * @param array $header
     * @return bool
     */
    public function validateHeader($header = array())
    {
        if (empty($header)) {
            $header = $this->getHeader();
        }

        if (!is_array($header)) {
            Mage::throwException('Invalid request header format.');
        }

        if (!isset($header['eWAYCustomerID']) && !strlen($header['eWAYCustomerID']) <= 8) {
            Mage::throwException('Invalid eWAY customer ID.');
        }

        if (!isset($header['Username']) && !strlen($header['Username']) <= 100) {
            Mage::throwException('Invalid eWAY username.');
        }

        if (!isset($header['Password']) && !strlen($header['Password']) <= 50) {
            Mage::throwException('Invalid eWAY password.');
        }

        return true;
    }
}
