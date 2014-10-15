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

class Fontis_EwayAu_Model_Shared extends Mage_Payment_Model_Method_Abstract
{
    const DEFAULT_REDIRECT_URL = 'https://www.eway.com.au/gateway/payment.asp';

    protected $_code = 'ewayau_shared';

    protected $_isGateway               = false;
    protected $_canAuthorize            = false;
    protected $_canCapture              = true;
    protected $_canCapturePartial       = false;
    protected $_canRefund               = false;
    protected $_canVoid                 = false;
    protected $_canUseInternal          = false;
    protected $_canUseCheckout          = true;
    protected $_canUseForMultishipping  = false;

    protected $_formBlockType = 'ewayau/shared_form';
    protected $_paymentMethod = 'shared';

    /**
     * @var Mage_Sales_Model_Order
     */
    protected $_order = null;

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
     * Get order model
     *
     * @return Mage_Sales_Model_Order
     */
    public function getOrder()
    {
        if ($this->_order === null) {
            $paymentInfo = $this->getInfoInstance();
            $orderId = $paymentInfo->getOrder()->getRealOrderId();
            $this->_order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
        }
        return $this->_order;
    }

    /**
     * Get Customer ID
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
     * @return Fontis_EwayAu_Model_Shared
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
     * @return string
     */
    public function getOrderPlaceRedirectUrl()
    {
        $url = Mage::getUrl('ewayau/' . $this->_paymentMethod . '/redirect');
        if (!$url) {
            $url = self::DEFAULT_REDIRECT_URL;
        }
        return $url;
    }

    /**
     * @return string
     */
    public function getOrderReturnSuccessUrl()
    {
        return Mage::getUrl('ewayau/' . $this->_paymentMethod . '/success', array('_secure' => true));
    }

    /**
     * Prepare parameters array to send it to gateway page via POST
     *
     * @return array
     */
    public function getFormFields()
    {
        $order = $this->getOrder();
        $billing = $order->getBillingAddress();
        $formattedAddress = $this->getEwayHelper()->getOrderAddressString($billing);
        $invoiceDesc = $this->getEwayHelper()->getInvoiceDescription($order, 10000);

        $paymentInfo = $this->getInfoInstance();
        $fieldsArr = array();
        $fieldsArr['ewayCustomerID'] = $this->getCustomerId();
        $fieldsArr['ewayTotalAmount'] = ($this->getOrder()->getBaseGrandTotal() * 100);
        $fieldsArr['ewayCustomerFirstName'] = $billing->getFirstname();
        $fieldsArr['ewayCustomerLastName'] = $billing->getLastname();
        $fieldsArr['ewayCustomerEmail'] = $this->getOrder()->getCustomerEmail();
        $fieldsArr['ewayCustomerAddress'] = trim($formattedAddress);
        $fieldsArr['ewayCustomerPostcode'] = $billing->getPostcode();
        $fieldsArr['ewayCustomerInvoiceDescription'] = $invoiceDesc;
        $fieldsArr['ewaySiteTitle'] = Mage::app()->getStore()->getName();
        $fieldsArr['ewayAutoRedirect'] = 1;
        $fieldsArr['ewayURL'] = $this->getOrderReturnSuccessUrl();
        $fieldsArr['ewayCustomerInvoiceRef'] = $paymentInfo->getOrder()->getRealOrderId();
        $fieldsArr['ewayTrxnNumber'] = $paymentInfo->getOrder()->getRealOrderId();
        $fieldsArr['ewayOption1'] = '';
        $fieldsArr['ewayOption2'] = Mage::helper('core')->encrypt($paymentInfo->getOrder()->getRealOrderId());
        $fieldsArr['ewayOption3'] = '';

        return $fieldsArr;
    }

    /**
     * Get url of eWAY Shared Payment
     *
     * @return string
     */
    public function getEwaySharedUrl()
    {
         if (!$url = $this->getConfigData('api_url')) {
             $url = 'https://www.eway.com.au/gateway/payment.asp';
         }
         return $url;
    }

    /**
     * Get debug flag
     *
     * @return string
     */
    public function getDebug()
    {
        return $this->getConfigData('debug_flag');
    }

    /**
     * @param Varien_Object $payment
     * @param float $amount
     * @return Fontis_EwayAu_Model_Shared
     */
    public function capture(Varien_Object $payment, $amount)
    {
        $payment->setStatus(self::STATUS_APPROVED)
            ->setLastTransId($this->getTransactionId());

        return $this;
    }

    /**
     * @param Varien_Object $payment
     * @return Fontis_EwayAu_Model_Shared
     */
    public function cancel(Varien_Object $payment)
    {
        $payment->setStatus(self::STATUS_DECLINED)
            ->setLastTransId($this->getTransactionId());

        return $this;
    }

    /**
     * Parse response POST array from gateway page and return payment status
     *
     * @return bool
     */
    public function parseResponse()
    {
        $response = $this->getResponse();

        if ($response['ewayTrxnStatus'] == 'True') {
            return true;
        }
        return false;
    }

    /**
     * Return redirect block type
     *
     * @return string
     */
    public function getRedirectBlockType()
    {
        return $this->_redirectBlockType;
    }

    /**
     * Return payment method type string
     *
     * @return string
     */
    public function getPaymentMethodType()
    {
        return $this->_paymentMethod;
    }
}
