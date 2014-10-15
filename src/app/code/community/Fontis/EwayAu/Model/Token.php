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

class Fontis_EwayAu_Model_Token extends Mage_Payment_Model_Method_Cc
{
    const GATEWAY_URL_MAIN = 'https://www.eway.com.au/gateway/ManagedPaymentService/managedCreditCardPayment.asmx';
    const GATEWAY_URL_TEST = 'https://www.eway.com.au/gateway/ManagedPaymentService/test/managedcreditcardpayment.asmx';

    protected $_code = 'ewayau_token';

    protected $_isGateway               = true;
    protected $_canAuthorize            = false;
    protected $_canCapture              = true;
    protected $_canCapturePartial       = false;
    protected $_canRefund               = false;
    protected $_canVoid                 = false;
    protected $_canUseInternal          = true;
    protected $_canUseCheckout          = false;
    protected $_canUseForMultishipping  = true;
    protected $_canSaveCc               = false;

    protected $_formBlockType = 'ewayau/cc_form';
    protected $_infoBlockType = 'ewayau/cc_info';

    /**
     * @var array
     */
    protected $_soapClassMap = array(
        'CreateCustomerResponse'            => 'ewayau/token_response_createCustomer',
        'UpdateCustomerResponse'            => 'ewayau/token_response_updateCustomer',
        'QueryCustomerByReferenceResult'    => 'ewayau/token_response_queryCustomerByReference',
        'CreditCard'                        => 'varien/object',
        'ProcessPaymentResponse'            => 'ewayau/token_response_processPayment',
        'CCPaymentResponse'                 => 'varien/object',
    );

    /**
     * @var Fontis_EwayAu_Model_Token_Request
     */
    protected $_request = null;

    /**
     * @var Fontis_EwayAu_Model_Token_Request_Data
     */
    protected $_requestData = null;

    /**
     * @var Fontis_EwayAu_Helper_Token
     */
    protected $_tokenHelper = null;

    public function __construct()
    {
        parent::__construct();
        $this->_soapClassMap = array_map(array(Mage::getConfig(), 'getModelClassName'), $this->_soapClassMap);
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
     * @param bool $testGateway
     * @return string
     */
    public function getApiGatewayUrl($testGateway = null)
    {
        if ($testGateway !== null) {
            if ($testGateway === true) {
                return self::GATEWAY_URL_TEST;
            } else {
                return self::GATEWAY_URL_MAIN;
            }
        } elseif ($this->getConfigData('test_gateway', $this->getStoreId())) {
            return self::GATEWAY_URL_TEST;
        } else {
            return self::GATEWAY_URL_MAIN;
        }
    }

    /**
     * @return int
     */
    public function getStoreId()
    {
        if (Mage::registry('payment_store')) {
            return Mage::registry('payment_store');
        }

        $store = $this->getStore();
        $storeId = false;
        if ($store && is_object($store) && $store->getId()) {
            $storeId = $store->getId();
            Mage::register('payment_store', $storeId);
        }

        if (!$storeId) {
            $storeId = Mage::app()->getStore()->getId();
        }

        return $storeId;
    }

    /**
     * Get the eWAY Customer Id
     *
     * @return string
     */
    public function getCustomerId()
    {
        return $this->getConfigData('customer_id', $this->getStoreId());
    }

    /**
     * Get the eWAY Business Center username.
     *
     * @return string
     */
    public function getUsername()
    {
        return $this->getConfigData('username', $this->getStoreId());
    }

    /**
     * Get the eWAY Business Center password.
     *
     * @return string
     */
    public function getPassword()
    {
        return $this->getConfigData('password', $this->getStoreId());
    }

    /**
     * Get the currency that accepted by eWAY account
     *
     * @return string
     */
    public function getAcceptedCurrency()
    {
        return $this->getConfigData('currency', $this->getStoreId());
    }

    /**
     * Determine if this payment method can be shown as an option on checkout payment page.
     *
     * @return bool
     */
    public function canUseCheckout()
    {
        return $this->getConfigData('show_in_checkout', $this->getStoreId());
    }

    /**
     * @return Fontis_EwayAu_Model_Token
     * @throws Mage_Core_Exception
     */
    public function validate()
    {
        parent::validate();

        $paymentInfo = $this->getInfoInstance();
        if ($paymentInfo instanceof Mage_Sales_Model_Order_Payment) {
            $currencyCode = $paymentInfo->getOrder()->getBaseCurrencyCode();
        } else {
            $currencyCode = $paymentInfo->getQuote()->getBaseCurrencyCode();
        }
        if ($currencyCode != $this->getAcceptedCurrency()) {
            Mage::throwException($this->getTokenHelper()->__('Selected currency code (%s) is not compatible with eWAY', $currencyCode));
        }
        return $this;
    }

    /**
     * Assign data to info model instance
     *
     * @param   array|Varien_Object $paymentData
     * @return  Fontis_EwayAu_Model_Token
     */
    public function assignData($paymentData)
    {
        parent::assignData($paymentData);

        if (is_array($paymentData)) {
            $paymentData = new Varien_Object($paymentData);
        }

        $tokenHelper = $this->getTokenHelper();
        $customerId = $tokenHelper->getCustomerId();

        // If customer credit card data has already been stored on eWAY recently skip.
        if (!$customerId) {
            // Determine if customer credit card data already exists on eWAY
            try {
                $query = $this->queryCustomerByReference();

                // Check if customer data requires update
                if ($query->isRequestSuccessful()) {
                    $updateResponse = $this->updateCustomer($paymentData, $query->getProcessedResponse());

                    if ($updateResponse === false) {
                        $tokenHelper->logMessage('Unable to update eWAY token customer.');
                        Mage::throwException('An error occurred during the checkout process.');
                    }
                } else {
                    $createResponse = $this->createCustomer($paymentData);

                    if (!$createResponse->isRequestSuccessful()) {
                        $tokenHelper->logMessage('Unable to create eWAY token customer.');
                        Mage::throwException('An error occurred during the checkout process.');
                    }
                }
            } catch (Exception $e) {
                $tokenHelper->logMessage($e->getMessage());
                Mage::throwException($e->getMessage());
            }
        }

        return $this;
    }

    /**
     * @param Varien_Object $payment
     * @param float $amount
     * @return Fontis_EwayAu_Model_Token
     */
    public function capture(Varien_Object $paymentData, $amount)
    {
        $this->setAmount($amount)->setPayment($paymentData);

        $result = $this->processPayment($paymentData);
        if ($result->isRequestSuccessful() === false) {
            if ($errorMsg = $result->getEwayTrxnError()) {
                if (stristr($errorMsg, Fontis_EwayAu_Helper_Data::ERROR_MSG_DONOTHONOUR)) {
                    $message = $this->getTokenHelper()->__('An error has occurred while processing your payment: Your credit card details are invalid.');
                } else {
                    $message = $this->getTokenHelper()->__('An error has occurred while processing your payment. ') . $errorMsg;
                }
            } else {
                $message = $this->getTokenHelper()->__('An error has occurred while processing your payment. Please try later or contact us for help.');
            }
            Mage::throwException($message);
        } else {
            $paymentData->setStatus(Mage_Payment_Model_Method_Abstract::STATUS_APPROVED)->setLastTransId($result->getEwayTrxnNumber());
            // Clear set eWAY token customer ID on customer's session
            $this->getTokenHelper()->clearCustomerId();
        }

        return $this;
    }

    /**
     * Cancel the current transaction payment being processed.
     *
     * @param Varien_Object $payment
     * @return Fontis_EwayAu_Model_Token
     */
    public function cancel(Varien_Object $payment)
    {
        $payment->setStatus(Mage_Payment_Model_Method_Abstract::STATUS_DECLINED);
        return $this;
    }

    /**
     * Get the request model.
     *
     * @return Fontis_EwayAu_Model_Token_Request
     */
    public function getRequest()
    {
        if ($this->_request === null) {
            $endpoint = $this->getApiGatewayUrl();
            $this->_request = Mage::getModel('ewayau/token_request', array(
                'wsdl'          =>  $endpoint . '?WSDL',
                'endpoint'      =>  $endpoint,
                'header'        =>  array(
                    'eWAYCustomerID'    => $this->getCustomerId(),
                    'Username'          => $this->getUsername(),
                    'Password'          => $this->getPassword(),
                ),
                'soap_options'  =>  array(
                    'exceptions'    => false,
                    'trace'         => (bool) $this->getDebugFlag(),
                    'soap_version'  => SOAP_1_2,
                    'compression'   => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP,
                    'classmap'      => $this->_soapClassMap,
                ),
            ));
        }

        return $this->_request;
    }

    /**
     * @param string $method
     * @param Varien_Object $paymentData
     * @return Fontis_EwayAu_Model_Token_Request_Data
     */
    public function getRequestData($method, $paymentData = null)
    {
        if ($this->_requestData === null) {
            if (empty($paymentData)) {
                $paymentData = $this->getPayment();
            }

            /** @var $salesObject Fontis_EwayAu_Model_Sales_Object */
            $paymentInfo = $this->getInfoInstance();
            if ($paymentInfo instanceof Mage_Sales_Model_Order_Payment) {
                $salesObject = $paymentInfo->getOrder();
            } else {
                $salesObject = $paymentInfo->getQuote();
            }

            if (!$salesObject) {
                Mage::throwException($this->getTokenHelper()->__('An error has occurred while processing your payment. Please try later or contact us for help.'));
            }

            $tokenData = array(
                'payment_data'  =>  $paymentData,
                'billing'       =>  $salesObject->getBillingAddress(),
                'sales_object'  =>  $salesObject,
                'amount'        =>  $this->getAmount(),
                'method'        =>  $method,
            );

            $this->_requestData = Mage::getModel('ewayau/token_request_data', $tokenData);
        }

        $this->_requestData->setMethod($method);
        if (!empty($paymentData)) {
            $this->_requestData->setPaymentData($paymentData);
        }

        return $this->_requestData;
    }

    /**
     * @param Varien_Object $paymentData
     * @return Fontis_EwayAu_Model_Token_Response_CreateCustomer
     */
    public function createCustomer($paymentData)
    {
        $soapMethod = Fontis_EwayAu_Model_Token_Request::CREATE_CUSTOMER;
        $data = $this->getRequestData($soapMethod, $paymentData);
        $response = $this->getRequest()->execute($soapMethod, $data->getCreateCustomerArray());
        if ($response instanceof Fontis_EwayAu_Model_Token_Response) {
            $response->process();
        }
        return $response;
    }

    /**
     * @return Fontis_EwayAu_Model_Token_Response_QueryCustomerByReference
     */
    public function queryCustomerByReference()
    {
        $soapMethod = Fontis_EwayAu_Model_Token_Request::QUERY_CUSTOMER_BY_REFERENCE;
        $data = $this->getRequestData($soapMethod);
        $response = $this->getRequest()->execute($soapMethod, $data->getQueryCustomerByReferenceArray());
        if ($response instanceof Fontis_EwayAu_Model_Token_Response) {
            $response->process();
        } else {
            // If the customer doesn't exist, an instance of SoapFault will be returned instead.
            // This is unhelpful, as determining whether or not a customer already exists should
            // not result in an error, and also because we already have our own logic to determine
            // whether or not a request was successful.
            // To get around this, just return an empty instance of the appropriate object. This
            // will default to the request being unsuccessful, which is what we want in this case.
            $response = Mage::getModel('ewayau/token_response_queryCustomerByReference');
        }
        return $response;
    }

    /**
     * @param Varien_Object $paymentData
     * @return Fontis_EwayAu_Model_Token_Response_ProcessPayment
     */
    public function processPayment($paymentData)
    {
        $soapMethod = Fontis_EwayAu_Model_Token_Request::PROCESS_PAYMENT;
        $data = $this->getRequestData($soapMethod, $paymentData);
        $response = $this->getRequest()->execute($soapMethod, $data->getProcessPaymentArray());
        if ($response instanceof Fontis_EwayAu_Model_Token_Response) {
            $response->process();
        }
        return $response;
    }

    /**
     * Update eWAY token customer record.
     *
     * @param Varien_Object $paymentData
     * @param Varien_Object $storedData
     * @return bool
     */
    public function updateCustomer($paymentData, $storedData = null)
    {
        $soapMethod = Fontis_EwayAu_Model_Token_Request::UPDATE_CUSTOMER;
        $data = $this->getRequestData($soapMethod, $paymentData);

        if (isset($storedData)) {
            $newCustomerData = $data->getUpdateCustomerArray();
            $cardExpired = true;

            if (isset($newCustomerData['CCExpiryMonth']) && isset($newCustomerData['CCExpiryYear'])) {
                $cardExpired = $this->getTokenHelper()->hasCreditCardExpired(
                    $newCustomerData['CCExpiryMonth'],
                    $newCustomerData['CCExpiryYear']
                );

                unset($newCustomerData['CCExpiryMonth']);
                unset($newCustomerData['CCExpiryYear']);
            }

            if (!$cardExpired) {
                $changeFound = false;
                $usedFields = $data->getUsedCustomerFields();

                foreach ($usedFields as $field) {
                    $storedDataField = $storedData->__get($field);
                    if (!isset($newCustomerData[$field]) && !empty($storedDataField)) {
                        $changeFound = true;
                        break;
                    } elseif ($field == 'CCNumber' && $this->getTokenHelper()->hasCreditCardNumberChanged($newCustomerData[$field], $storedDataField)) {
                        $changeFound = true;
                        break;
                    } elseif ($newCustomerData[$field] != $storedDataField) {
                        $changeFound = true;
                        break;
                    }
                }

                if ($changeFound === false) {
                    return false;
                }
            }
        }

        $response = $this->getRequest()->execute($soapMethod, $data->getUpdateCustomerArray());
        if ($response instanceof Fontis_EwayAu_Model_Token_Response) {
            $response->process();
        }
        return $response->isRequestSuccessful();
    }
}
