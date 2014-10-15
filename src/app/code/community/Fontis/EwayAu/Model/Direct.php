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
 * Original code copyright (c) 2008 Irubin Consulting Inc. DBA Varien
 *
 * @category   Fontis
 * @package    Fontis_EwayAu
 * @author     Chris Norton
 * @author     Matthew Gamble
 * @copyright  Copyright (c) 2014 Fontis Pty. Ltd. (http://www.fontis.com.au)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @method Fontis_EwayAu_Model_Direct setError(array $errorDetails)
 * @method array getError()
 */
class Fontis_EwayAu_Model_Direct extends Mage_Payment_Model_Method_Cc
{
    const GATEWAY_URL_MAIN  = 'https://www.eway.com.au/gateway/xmlpayment.asp';
    const GATEWAY_URL_CVN   = 'https://www.eway.com.au/gateway_cvn/xmlpayment.asp';
    const GATEWAY_URL_TEST  = 'https://www.eway.com.au/gateway/xmltest/testpage.asp';

    const REFUND_URL_MAIN   = 'https://www.eway.com.au/gateway/xmlpaymentrefund.asp';
    const REFUND_URL_TEST   = 'https://www.eway.com.au/gateway/xmltest/refund_test.asp';

    const PREAUTH_PAYMENT_URL_MAIN          = 'https://www.eway.com.au/gateway/xmlauth.asp';
    const PREAUTH_PAYMENT_URL_CVN           = 'https://www.eway.com.au/gateway_cvn/xmlauth.asp';
    const PREAUTH_PAYMENT_URL_MAIN_TEST     = 'https://www.eway.com.au/gateway/xmltest/authtestpage.asp';
    const PREAUTH_PAYMENT_URL_CVN_TEST      = 'https://www.eway.com.au/gateway_cvn/authtestpage.asp';

    const PREAUTH_COMPLETE_URL_MAIN         = 'https://www.eway.com.au/gateway/xmlauthcomplete.asp';
    const PREAUTH_COMPLETE_URL_TEST         = 'https://www.eway.com.au/gateway/xmltest/authcompletetestpage.asp';

    const PREAUTH_VOID_URL_MAIN             = 'https://www.eway.com.au/gateway/xmlauthvoid.asp';
    const PREAUTH_VOID_URL_TEST             = 'https://www.eway.com.au/gateway/xmltest/authvoidtestpage.asp';

    protected $_code = 'ewayau_direct';

    protected $_isGateway               = true;
    protected $_canAuthorize            = true;
    protected $_canCapture              = true;
    protected $_canCapturePartial       = false;
    protected $_canRefund               = true;
    protected $_canRefundInvoicePartial = true;
    protected $_canVoid                 = true;
    protected $_canUseInternal          = true;
    protected $_canUseCheckout          = true;
    protected $_canUseForMultishipping  = true;
    protected $_canSaveCc               = false;

    protected $_formBlockType = 'ewayau/cc_form';
    protected $_infoBlockType = 'ewayau/cc_info';

    /**
     * @var Fontis_EwayAu_Helper_Data
     */
    protected $_ewayHelper = null;

    /**
     * @return Fontis_EwayAu_Helper_Data
     */
    protected function getEwayHelper()
    {
        if ($this->_ewayHelper === null) {
            $this->_ewayHelper = Mage::helper('ewayau');
        }
        return $this->_ewayHelper;
    }

    /**
     * Get API URL of eWAY Direct payment
     *
     * @return string
     */
    public function getApiGatewayUrl()
    {
        if ($this->getConfigData('test_gateway')) {
            return self::GATEWAY_URL_TEST;
        } else {
            if ($this->hasVerification()) {
                return self::GATEWAY_URL_CVN;
            } else {
                return self::GATEWAY_URL_MAIN;
            }
        }
    }

    /**
     * @return string
     */
    public function getApiRefundUrl()
    {
        if ($this->getConfigData('test_gateway')) {
            return self::REFUND_URL_TEST;
        } else {
            return self::REFUND_URL_MAIN;
        }
    }

    /**
     * @return string
     */
    public function getApiPreAuthPaymentUrl()
    {
        if ($this->getConfigData('test_gateway')) {
            if ($this->hasVerification()) {
                return self::PREAUTH_PAYMENT_URL_CVN_TEST;
            } else {
                return self::PREAUTH_PAYMENT_URL_MAIN_TEST;
            }
        } else {
            if ($this->hasVerification()) {
                return self::PREAUTH_PAYMENT_URL_CVN;
            } else {
                return self::PREAUTH_PAYMENT_URL_MAIN;
            }
        }
    }

    /**
     * @return string
     */
    public function getApiPreAuthCompleteUrl()
    {
        if ($this->getConfigData('test_gateway')) {
            return self::PREAUTH_COMPLETE_URL_TEST;
        } else {
            return self::PREAUTH_COMPLETE_URL_MAIN;
        }
    }

    /**
     * @return string
     */
    public function getApiPreAuthVoidUrl()
    {
        if ($this->getConfigData('test_gateway')) {
            return self::PREAUTH_VOID_URL_TEST;
        } else {
            return self::PREAUTH_VOID_URL_MAIN;
        }
    }

    /**
     * Get Customer Id
     *
     * @return string
     */
    public function getCustomerId()
    {
        return $this->getConfigData('customer_id');
    }

    /**
     * Get currency that accepted by eWAY account
     *
     * @return string
     */
    public function getAcceptedCurrency()
    {
        return $this->getConfigData('currency');
    }

    /**
     * @return string
     */
    public function getRefundPassword()
    {
        return $this->getConfigData('refund_password');
    }

    /**
     * @return bool
     */
    public function canCapture()
    {
        if ($this->_isPreauthorizeCapture($this->getInfoInstance())) {
            return true;
        } elseif ($this->getConfigPaymentAction() === Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE_CAPTURE) {
            return $this->_canCapture;
        } else {
            return false;
        }
    }

    /**
     * @param Mage_Sales_Model_Order_Payment $payment
     * @return bool
     */
    protected function _isPreauthorizeCapture($payment)
    {
        $lastTransaction = $payment->getTransaction($payment->getLastTransId());
        if ($lastTransaction && $lastTransaction->getTxnType() == Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH) {
            return true;
        }

        return false;
    }

    /**
     * @return Fontis_EwayAu_Model_Direct
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
            Mage::throwException($this->getEwayHelper()->__('Selected currency code (%s) is not compatible with eWAY', $currencyCode));
        }
        return $this;
    }

    /**
     * @param Varien_Object $payment
     * @param float $amount
     * @return Fontis_EwayAu_Model_Direct
     * @throws Mage_Core_Exception
     */
    public function authorize(Varien_Object $payment, $amount)
    {
        if ($amount <= 0) {
            Mage::throwException($this->getEwayHelper()->__('Invalid amount for authorization.'));
        }

        $this->setAmount($amount)->setPayment($payment);

        $result = $this->callDoAuthorisationPaymentRequest($payment, $amount);

        if ($result === false) {
            $this->processError($this->getError(), 'payment');
        } else {
            if ($result['ewayTrxnStatus'] === 'True') {
                $payment->setStatus(Mage_Payment_Model_Method_Abstract::STATUS_APPROVED)
                    ->setTransactionId($result['ewayTrxnNumber'])
                    ->setLastTransId($result['ewayTrxnNumber'])
                    ->setIsTransactionClosed(0);
            } else {
                Mage::throwException($result['ewayTrxnError']);
            }
        }
        return $this;
    }

    /**
     * @param Varien_Object $payment
     * @param float $amount
     * @return Fontis_EwayAu_Model_Direct
     * @throws Mage_Core_Exception
     */
    public function capture(Varien_Object $payment, $amount)
    {
        if ($amount <= 0) {
            Mage::throwException($this->getEwayHelper()->__('Invalid amount for authorization.'));
        }

        $this->setAmount($amount)->setPayment($payment);

        if ($this->_isPreauthorizeCapture($payment)) {
            $result = $this->callDoCompleteAuthorisedPayment($payment, $amount);
        } else {
            $result = $this->callDoDirectPayment($payment, $amount);
        }

        if ($result === false) {
            $this->processError($this->getError(), 'payment');
        } else {
            if ($result['ewayTrxnStatus'] === 'True') {
                $payment->setStatus(Mage_Payment_Model_Method_Abstract::STATUS_SUCCESS)
                    ->setLastTransId($result['ewayTrxnNumber'])
                    ->setTransactionId($result['ewayTrxnNumber'])
                    ->setIsTransactionClosed(1);
            } else {
                Mage::throwException($result['ewayTrxnError']);
            }
        }
        return $this;
    }

    /**
     * @param Varien_Object $payment
     * @return Fontis_EwayAu_Model_Direct
     */
    public function cancel(Varien_Object $payment)
    {
        $payment->setStatus(Mage_Payment_Model_Method_Abstract::STATUS_DECLINED);
        return $this;
    }

    /**
     * @param Varien_Object $payment
     * @return Fontis_EwayAu_Model_Direct
     * @throws Mage_Core_Exception
     */
    public function void(Varien_Object $payment)
    {
        if (!$this->_isPreauthorizeCapture($payment)) {
            Mage::throwException($this->_getHelper()->__('Void action is not available.'));
        }

        $amount = $payment->getAmountAuthorized();

        $this->setAmount($amount)->setPayment($payment);

        $result = $this->callDoVoidAuthorisedPayment($payment, $amount);

        if ($result === false) {
            $this->processError($this->getError(), 'refund');
        } else {
            if ($result['ewayTrxnStatus'] === 'True' && $result['ewayReturnAmount'] == $this->getFormattedAmount($amount)) {
                $payment
                    ->setStatus(Mage_Payment_Model_Method_Abstract::STATUS_VOID)
                    ->setLastTransId($result['ewayTrxnNumber'])
                    ->setIsTransactionClosed(1);
                ;
            } else {
                Mage::throwException($result['ewayTrxnError']);
            }
        }
        return $this;
    }

    /**
     * @param Varien_Object $payment
     * @param float $amount
     * @return Fontis_EwayAu_Model_Direct
     * @throws Mage_Core_Exception
     */
    public function refund(Varien_Object $payment, $amount)
    {
        $this->setAmount($amount)->setPayment($payment);
        
        $result = $this->callDoRefund($payment, $amount);

        if ($result === false) {
            $this->processError($this->getError(), 'refund');
        } else {
            if ($result['ewayTrxnStatus'] === 'True' && $result['ewayReturnAmount'] == $this->getFormattedAmount($amount)) {
                $payment->setStatus(Mage_Payment_Model_Method_Abstract::STATUS_APPROVED)
                    ->setLastTransId($result['ewayTrxnNumber']);
            } else {
                Mage::throwException($result['ewayTrxnError']);
            }
        }
        return $this;
    }

    /**
     * @param array $error
     * @param string $action payment, refund, etc
     * @throws Mage_Core_Exception
     */
    protected function processError($error, $action)
    {
        if (isset($error['message'])) {
            if (stristr($error['message'], Fontis_EwayAu_Helper_Data::ERROR_MSG_DONOTHONOUR)) {
                $message = $this->getEwayHelper()->__("There has been an error processing your $action: Your credit card details are invalid.");
            } else {
                $message = $this->getEwayHelper()->__("There has been an error processing your $action. ") . $error['message'];
            }
        } else {
            $message = $this->getEwayHelper()->__("There has been an error processing your $action. Please try later or contact us for help.");
        }
        Mage::throwException($message);
    }

    /**
     * Get the Simple XML objected used for pre-auth and capture payment transactions.
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param float $amount
     * @return SimpleXMLElement
     */
    protected function getPaymentXmlObject($payment, $amount)
    {
        $order = $payment->getOrder();
        $billing = $order->getBillingAddress();
        $formattedAddress = $this->getEwayHelper()->getOrderAddressString($billing);
        $invoiceDesc = $this->getEwayHelper()->getInvoiceDescription($order);

        // Build the XML request
        $xml = new SimpleXMLElement('<ewaygateway></ewaygateway>');
        $xml->addChild('ewayCustomerID', $this->getCustomerId());
        $xml->addChild('ewayTotalAmount', $this->getFormattedAmount($amount));
        $xml->addChild('ewayCustomerFirstName', str_replace('&', '&amp;', trim($billing->getFirstname())));
        $xml->addChild('ewayCustomerLastName', str_replace('&', '&amp;', trim($billing->getLastname())));
        $xml->addChild('ewayCustomerEmail', str_replace('&', '&amp;', trim($order->getCustomerEmail())));
        $xml->addChild('ewayCustomerAddress', str_replace('&', '&amp;', trim($formattedAddress)));
        $xml->addChild('ewayCustomerPostcode', str_replace('&', '&amp;', trim($billing->getPostcode())));
        $xml->addChild('ewayCustomerInvoiceDescription', str_replace('&', '&amp;', trim($invoiceDesc)));
        $xml->addChild('ewayCustomerInvoiceRef', '');
        $xml->addChild('ewayCardHoldersName', str_replace('&', '&amp;', $payment->getCcOwner()));
        $xml->addChild('ewayCardNumber', $payment->getCcNumber());
        $xml->addChild('ewayCardExpiryMonth', $payment->getCcExpMonth());
        $xml->addChild('ewayCardExpiryYear', substr($payment->getCcExpYear(), 2, 2));
        $xml->addChild('ewayTrxnNumber', '');

        $xml->addChild('ewayOption1', '');
        $xml->addChild('ewayOption2', '');
        $xml->addChild('ewayOption3', '');

        if ($this->hasVerification()) {
            $xml->addChild('ewayCVN', $payment->getCcCid());
        }

        return $xml;
    }

    /**
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param float $amount
     * @return array|false
     */
    protected function callDoDirectPayment($payment, $amount)
    {
        $xml = $this->getPaymentXmlObject($payment, $amount);
        $url = $this->getApiGatewayUrl();

        // Convert to string before sending to the gateway
        return $this->call($xml->asXML(), $url);
    }

    /**
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param float $amount
     * @return array|false
     */
    protected function callDoAuthorisationPaymentRequest($payment, $amount)
    {
        $xml = $this->getPaymentXmlObject($payment, $amount);
        $url = $this->getApiPreAuthPaymentUrl();

        // Convert to string before sending to the gateway
        return $this->call($xml->asXML(), $url);
    }

    /**
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param float $amount
     * @return array|false
     */
    protected function callDoCompleteAuthorisedPayment($payment, $amount)
    {
        // Build the XML request
        $xml = new SimpleXMLElement('<ewaygateway></ewaygateway>');
        $xml->addChild('ewayCustomerID', $this->getCustomerId());
        $xml->addChild('ewayTotalAmount', $this->getFormattedAmount($amount));
        $xml->addChild('ewayAuthTrxnNumber', $payment->getLastTransId());
        $xml->addChild('ewayCardExpiryMonth', $payment->getCcExpMonth());
        $xml->addChild('ewayCardExpiryYear', $payment->getCcExpYear());

        $xml->addChild('ewayOption1', '');
        $xml->addChild('ewayOption2', '');
        $xml->addChild('ewayOption3', '');

        $url = $this->getApiPreAuthCompleteUrl();

        // Convert to string before sending to the gateway
        return $this->call($xml->asXML(), $url);
    }

    /**
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param float $amount
     * @return array|false
     */
    protected function callDoVoidAuthorisedPayment($payment, $amount)
    {
        // Build the XML request
        $xml = new SimpleXMLElement('<ewaygateway></ewaygateway>');
        $xml->addChild('ewayCustomerID', $this->getCustomerId());
        $xml->addChild('ewayTotalAmount', $this->getFormattedAmount($amount));
        $xml->addChild('ewayAuthTrxnNumber', $this->getOriginalTransactionId($payment->getLastTransId()));

        $xml->addChild('ewayOption1', '');
        $xml->addChild('ewayOption2', '');
        $xml->addChild('ewayOption3', '');

        $url = $this->getApiPreAuthVoidUrl();

        // Convert to string before sending to the gateway
        return $this->call($xml->asXML(), $url);
    }

    /**
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param float $amount
     * @return array|false
     */
    protected function callDoRefund($payment, $amount)
    {
        // Build the XML request
        $xml = new SimpleXMLElement('<ewaygateway></ewaygateway>');
        $xml->addChild('ewayCustomerID', $this->getCustomerId());
        $xml->addChild('ewayTotalAmount', $this->getFormattedAmount($amount));
        $xml->addChild('ewayCardExpiryMonth', $payment->getCcExpMonth());
        $xml->addChild('ewayCardExpiryYear', substr($payment->getCcExpYear(), 2, 2));
        $xml->addChild('ewayOriginalTrxnNumber', $this->getOriginalTransactionId($payment->getLastTransId()));
        $xml->addChild('ewayRefundPassword', $this->getRefundPassword());

        $xml->addChild('ewayOption1', '');
        $xml->addChild('ewayOption2', '');

        $url = $this->getApiRefundUrl();

        // Convert to string before sending to the gateway
        return $this->call($xml->asXML(), $url);
    }

    /**
     * Send parameters to eWay gateway.
     *
     * @param string $xml
     * @param string $url
     * @return array|false
     */
    protected function call($xml, $url)
    {
        $http = new Varien_Http_Adapter_Curl();
        $config = array('timeout' => Fontis_EwayAu_Helper_Data::DEFAULT_TIMEOUT);

        $http->setConfig($config);
        $http->write(Zend_Http_Client::POST, $url, Zend_Http_Client::HTTP_1, array(), $xml);
        $response = $http->read();

        $response = preg_split('/^\r?$/m', $response, 2);
        $response = trim($response[1]);

        if ($http->getErrno()) {
            $http->close();
            $this->setError(array(
                'message' => $http->getErrno() . ' - ' . $http->getError(),
            ));
            return false;
        }
        $http->close();

        if (($parsedResArr = $this->parseXmlResponse($response)) === false) {
            $this->setError(array(
                'message' => 'Invalid response from gateway.',
            ));
            return false;
        }

        if ($parsedResArr['ewayTrxnStatus'] == 'True') {
            $this->unsError();
            return $parsedResArr;
        }

        if (isset($parsedResArr['ewayTrxnError'])) {
            $this->setError(array(
                'message' => $parsedResArr['ewayTrxnError'],
            ));
        }

        return false;
    }

    /**
     * @param float $amount
     * @return float
     */
    protected function getFormattedAmount($amount)
    {
        return $amount * 100;
    }

    /**
     * Get Transaction ID that can be used with eWAY API calls.
     * Remove payment action appended by Magento to the end of transaction ID.
     *
     * @param string $transactionId
     * @return string
     */
    protected function getOriginalTransactionId($transactionId)
    {
        if (strstr($transactionId, '-')) {
            $transactionData = explode('-', $transactionId);
            return $transactionData[0];
        }

        return $transactionId;
    }

    /**
     * Parse response of gateway
     *
     * @param string $xmlResponse
     * @return array
     */
    protected function parseXmlResponse($xmlResponse)
    {
        $xmlObj = simplexml_load_string($xmlResponse);
        
        if ($xmlObj === false) {
            return false;
        }
        
        $newResArr = array();
        foreach ($xmlObj as $key => $val) {
            $newResArr[$key] = (string) $val;
        }

        return $newResArr;
    }

    /**
     * Check if invoice email can be sent, and send it.
     * 
     * @param Mage_Sales_Model_Order_Invoice $invoice
     * @param Mage_Sales_Model_Order_Payment $payment
     * @return Fontis_EwayAu_Model_Direct
     */
    public function processInvoice($invoice, $payment)
    {
        parent::processInvoice($invoice, $payment);

        try {
            $storeId = $invoice->getOrder()->getStoreId();
            if (Mage::helper('sales')->canSendNewInvoiceEmail($storeId)) {
                $invoice->save();
                $invoice->sendEmail();
            }
        } catch (Exception $e) {
            Mage::logException($e);
        }
        return $this;
    }
}
