<?php
/**
 * Fontis eWAY Australia Extension
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you are unable to obtain it through the world-wide-web, please send
 * an email to license@magentocommerce.com so you can be sent a copy.
 *
  * Original code copyright (c) 2008 Irubin Consulting Inc. DBA Varien
 *
 * @category   Fontis
 * @package    Fontis_EwayAu
 * @author     Chris Norton
 * @copyright  Copyright (c) 2010 Fontis Pty. Ltd. (http://www.fontis.com.au)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Fontis_EwayAu_Model_Direct extends Mage_Payment_Model_Method_Cc
{
    protected $_code  = 'ewayau_direct';

    protected $_isGateway               = true;
    protected $_canAuthorize            = false;
    protected $_canCapture              = true;
    protected $_canCapturePartial       = false;
    protected $_canRefund               = true;
    protected $_canVoid                 = false;
    protected $_canUseInternal          = true;
    protected $_canUseCheckout          = true;
    protected $_canUseForMultishipping  = true;
    protected $_canSaveCc               = false;

    protected $_formBlockType = 'ewayau/form';
    protected $_infoBlockType = 'ewayau/info';

    /**
     * Get flag to use CCV or not
     *
     * @return string
     */
    public function getUseccv()
    {
        return Mage::getStoreConfig('payment/ewayau_direct/useccv');
    }

    /**
     * Get api url of eWAY Direct payment
     *
     * @return string
     */
    public function getApiGatewayUrl()
    {
        if(Mage::getStoreConfig('payment/ewayau_direct/test_gateway')) {
            return 'https://www.eway.com.au/gateway/xmltest/testpage.asp';
        } else {
            if($this->getUseccv()) {
                return 'https://www.eway.com.au/gateway_cvn/xmlpayment.asp';
            } else {
                return 'https://www.eway.com.au/gateway/xmlpayment.asp';
            }
        }
    }

    /**
     * Get Customer Id
     *
     * @return string
     */
    public function getCustomerId()
    {
        return Mage::getStoreConfig('payment/ewayau_direct/customer_id');
    }

    /**
     * Get currency that accepted by eWAY account
     *
     * @return string
     */
    public function getAcceptedCurrency()
    {
        return Mage::getStoreConfig('payment/' . $this->getCode() . '/currency');
    }
    
    public function getRefundPassword()
    {
        return Mage::getStoreConfig('payment/ewayau_direct/refund_password');
    }

    public function validate()
    {
        parent::validate();
        $paymentInfo = $this->getInfoInstance();
        if ($paymentInfo instanceof Mage_Sales_Model_Order_Payment) {
            $currency_code = $paymentInfo->getOrder()->getBaseCurrencyCode();
        } else {
            $currency_code = $paymentInfo->getQuote()->getBaseCurrencyCode();
        }
        if ($currency_code != $this->getAcceptedCurrency()) {
            Mage::throwException(Mage::helper('ewayau')->__('Selected currency code ('.$currency_code.') is not compatabile with eWAY'));
        }
        return $this;
    }

    public function capture(Varien_Object $payment, $amount)
    {
        $this->setAmount($amount)
            ->setPayment($payment);

        $result = $this->callDoDirectPayment($payment);

        if ($result === false) {
            $e = $this->getError();
            if (isset($e['message'])) {
                $message = Mage::helper('ewayau')->__('There has been an error processing your payment. ') . $e['message'];
            } else {
                $message = Mage::helper('ewayau')->__('There has been an error processing your payment. Please try later or contact us for help.');
            }
            Mage::throwException($message);
        } else {
            if ($result['ewayTrxnStatus'] === 'True') {
                $payment->setStatus(self::STATUS_APPROVED)->setLastTransId($result['ewayTrxnNumber']);
            }
            else {
                Mage::throwException($result['ewayTrxnError']);
            }
        }
        return $this;
    }

    public function cancel(Varien_Object $payment)
    {
        $payment->setStatus(self::STATUS_DECLINED);
        return $this;
    }

    public function refund(Varien_Object $payment, $amount)
    {
        $this->setAmount($amount)->setPayment($payment);
        
        $result = $this->callDoRefund();

        if($result === false) {
            $e = $this->getError();
            if (isset($e['message'])) {
                $message = Mage::helper('ewayau')->__('There has been an error processing your refund.') . ' ' . $e['message'];
            } else {
                $message = Mage::helper('ewayau')->__('There has been an error processing your refund. Please try later or contact us for help.');
            }
            Mage::throwException($message);
        }
        else {
            if ($result['ewayTrxnStatus'] === 'True' && $result['ewayReturnAmount'] == ($amount * 100)) {
                $payment->setStatus(self::STATUS_APPROVED)->setLastTransId($result['ewayTrxnNumber']);
            }
            else {
                Mage::throwException($result['ewayTrxnError']);
            }
        }
        return $this;
    }

    /**
     * prepare params to send to gateway
     *
     * @return bool | array
     */
    public function callDoDirectPayment()
    {
        $payment = $this->getPayment();
        $billing = $payment->getOrder()->getBillingAddress();

        $invoiceDesc = '';
        $lengs = 0;
        foreach ($payment->getOrder()->getAllItems() as $item) {
            if ($item->getParentItem()) {
                continue;
            }
            if (Mage::helper('core/string')->strlen($invoiceDesc.$item->getName()) > 10000) {
                break;
            }
            $invoiceDesc .= $item->getName() . ', ';
        }
        $invoiceDesc = Mage::helper('core/string')->substr($invoiceDesc, 0, -2);

        $address = clone $billing;
        $address->unsFirstname();
        $address->unsLastname();
        $address->unsPostcode();
        $formatedAddress = '';
        $tmpAddress = explode(' ', str_replace("\n", ' ', trim($address->format('text'))));
        foreach ($tmpAddress as $part) {
            if (strlen($part) > 0) $formatedAddress .= $part . ' ';
        }
        
        // Build the XML request
        $xml = new SimpleXMLElement('<ewaygateway></ewaygateway>');
 
        $xml->addChild('ewayCustomerID', $this->getCustomerId() );
        $xml->addChild('ewayTotalAmount', ($this->getAmount()*100) );
        $xml->addChild('ewayCardHoldersName', str_replace('&', '&amp;', $payment->getCcOwner() ) );
        $xml->addChild('ewayCardNumber', $payment->getCcNumber() );
        $xml->addChild('ewayCardExpiryMonth', $payment->getCcExpMonth() );
        $xml->addChild('ewayCardExpiryYear', substr($payment->getCcExpYear(), 2, 2) );
        $xml->addChild('ewayTrxnNumber', '');
        $xml->addChild('ewayCustomerInvoiceDescription', str_replace('&', '&amp;', trim($invoiceDesc) ) );
        $xml->addChild('ewayCustomerFirstName', str_replace('&', '&amp;', trim( $billing->getFirstname() ) ) );
        $xml->addChild('ewayCustomerLastName', str_replace('&', '&amp;', trim( $billing->getLastname() ) ) );
        $xml->addChild('ewayCustomerEmail', str_replace('&', '&amp;', trim($payment->getOrder()->getCustomerEmail() ) ) );
        $xml->addChild('ewayCustomerAddress', str_replace('&', '&amp;', trim($formatedAddress) ) );
        $xml->addChild('ewayCustomerPostcode', str_replace('&', '&amp;', trim($billing->getPostcode()) ) );
        $xml->addChild('ewayCustomerInvoiceRef', $payment->getOrder()->getRealOrderId());

        if ($this->getUseccv()) {
            $xml->addChild('ewayCVN',  $payment->getCcCid() );
        }
               
        $xml->addChild('ewayOption1', '');
        $xml->addChild('ewayOption2', '');
        $xml->addChild('ewayOption3', '');


        //convert to string before sending to the gateway
        $resultArr = $this->call( $xml->asXML() );
                   
        if ($resultArr === false) {
            return false;
        }

        return $resultArr;
       
    }
    
    /**
     * prepare params to send to gateway
     *
     * @return bool | array
     */
    public function callDoRefund()
    {
        $payment = $this->getPayment();
        $billing = $payment->getOrder()->getBillingAddress();
        
        $xml = new SimpleXMLElement('<ewaygateway></ewaygateway>');

        $xml->addChild('ewayCustomerID', $this->getCustomerId());
        $xml->addChild('ewayTotalAmount', $this->getAmount()*100);
        $xml->addChild('ewayCardExpiryMonth', $payment->getCcExpMonth());
        $xml->addChild('ewayCardExpiryYear', substr($payment->getCcExpYear(), 2, 2));
        $xml->addChild('ewayOriginalTrxnNumber', $payment->getLastTransId());
        $xml->addChild('ewayRefundPassword', $this->getRefundPassword());
               
        $xml->addChild('ewayOption1', '');
        $xml->addChild('ewayOption2', '');
        $xml->addChild('ewayOption3', '');
        
        $http = new Varien_Http_Adapter_Curl();
        $config = array('timeout' => 30);

        $http->setConfig($config);
        $http->write(Zend_Http_Client::POST, 'https://www.eway.com.au/gateway/xmlpaymentrefund.asp', '1.1', array(), $xml->asXML());
        $response = $http->read();

        $response = preg_split('/^\r?$/m', $response, 2);
        $response = trim($response[1]);

        if ($http->getErrno()) {
            $http->close();
            $this->setError(array(
                'message' => $http->getError()
            ));
            return false;
        }
        $http->close();

        if( ($resultArr = $this->parseXmlResponse($response)) === false ) {
            $this->setError(array(
                'message' => 'Invalid response from gateway.'
            ));
            return false;
        }

        if ($resultArr['ewayTrxnStatus'] == 'True') {
            $this->unsError();
            return $resultArr;
        }

        if (isset($resultArr['ewayTrxnError'])) {
            $this->setError(array(
                'message' => $resultArr['ewayTrxnError']
            ));
        }

        $this->setTransactionId($resultArr['ewayTrxnNumber']);

        return $resultArr;
    }

    /**
     * Send params to gateway
     *
     * @param string $xml
     * @return bool | array
     */
    public function call($xml)
    {
        $http = new Varien_Http_Adapter_Curl();
        $config = array('timeout' => 30);

        $http->setConfig($config);
        $http->write(Zend_Http_Client::POST, $this->getApiGatewayUrl(), '1.1', array(), $xml);
        $response = $http->read();

        $response = preg_split('/^\r?$/m', $response, 2);
        $response = trim($response[1]);

        if ($http->getErrno()) {
            $http->close();
            $this->setError(array(
                'message' => $http->getError()
            ));
            return false;
        }
        $http->close();

        if( ($parsedResArr = $this->parseXmlResponse($response)) === false ) {
            $this->setError(array(
                'message' => 'Invalid response from gateway.'
            ));
            return false;
        }

        if ($parsedResArr['ewayTrxnStatus'] == 'True') {
            $this->unsError();
            return $parsedResArr;
        }

        if (isset($parsedResArr['ewayTrxnError'])) {
            $this->setError(array(
                'message' => $parsedResArr['ewayTrxnError']
            ));
        }

        return false;
    }

    /**
     * parse response of gateway
     *
     * @param string $xmlResponse
     * @return array
     */
    public function parseXmlResponse($xmlResponse)
    {
        $xmlObj = simplexml_load_string($xmlResponse);
        
        if($xmlObj === false) {
            return false;
        }
        
        $newResArr = array();
        foreach ($xmlObj as $key => $val) {
            $newResArr[$key] = (string)$val;
        }

        return $newResArr;
    }
    
    /**
     * Check if invoice email can be sent, and send it
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
            mage::logException($e);
        }    
        return $this;
    }
}
