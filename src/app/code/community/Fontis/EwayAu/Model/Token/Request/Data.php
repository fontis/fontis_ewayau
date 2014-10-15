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

class Fontis_EwayAu_Model_Token_Request_Data extends Varien_Object
{
    const EWAY_CUSTOMER_ID_FIELD = 'managedCustomerID';

    const DEFAULT_CUSTOMER_TITLE = 'Mr.';

    /**
     * @var string
     */
    protected $_method  = null;

    /**
     * @var Varien_Object
     */
    protected $_paymentData = null;

    /**
     * @var Mage_Customer_Model_Address_Abstract
     */
    protected $_billing = null;

    /**
     * @var Fontis_EwayAu_Model_Sales_Object
     */
    protected $_salesObject = null;

    /**
     * @var int
     */
    protected $_amount = 0;

    public function __construct($data = array())
    {
        if (!isset($data['method'])) {
            Mage::throwException('Checkout request is invalid.');
        }

        $this->_method = $data['method'];

        if (!isset($data['sales_object'])) {
            Mage::throwException('Order information is invalid.');
        }

        $this->_salesObject = $data['sales_object'];

        if (!isset($data['payment_data']) && $this->isPaymentTransaction()) {
            Mage::throwException('Payment data is invalid.');
        } elseif (isset($data['payment_data'])) {
            $this->_paymentData = $data['payment_data'];
        }

        if (!isset($data['billing'])) {
            Mage::throwException('Billing information is invalid.');
        }

        $this->_billing = $data['billing'];

        if (isset($data['amount'])) {
            $this->setAmount($data['amount']);
        }
    }

    /**
     * @param string $method
     */
    public function setMethod($method)
    {
        $this->_method = $method;
    }

    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->_method;
    }

    /**
     * @param Varien_Object $paymentData
     */
    public function setPaymentData($paymentData)
    {
        $this->_paymentData = $paymentData;
    }

    /**
     * @return Varien_Object
     */
    public function getPaymentData()
    {
        return $this->_paymentData;
    }

    /**
     * @return Fontis_EwayAu_Model_Sales_Object
     */
    public function getSalesObject()
    {
        return $this->_salesObject;
    }

    /**
     * @param float $amount
     * @return int
     */
    public function setAmount($amount)
    {
        $this->_amount = (int) bcmul($amount, 100);
    }

    /**
     * @return int
     */
    public function getAmount()
    {
        return $this->_amount;
    }

    /**
     * @return Mage_Customer_Model_Address_Abstract
     */
    public function getBilling()
    {
        return $this->_billing;
    }

    /**
     * @return bool
     */
    public function isPaymentTransaction()
    {
        $method = $this->getMethod();
        $paymentRequests = array(
            Fontis_EwayAu_Model_Token_Request::PROCESS_PAYMENT,
        );

        if (in_array($method, $paymentRequests)) {
            return true;
        }

        return false;
    }

    /**
     * Returns the eWAY customer ID for the customer.
     *
     * @return int
     */
    public function getCustomerId()
    {
        return Mage::helper('ewayau/token')->getCustomerId();
    }

    /**
     * @return string[]
     */
    public function getValidCustomerTitles()
    {
        return array('Mr.', 'Ms.', 'Mrs.', 'Miss', 'Dr.', 'Sir.', 'Prof.');
    }

    /**
     * @param string $title
     * @return bool
     */
    public function validCustomerTitle($title)
    {
        if (in_array($title, $this->getValidCustomerTitles())) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Returns the quote/order ID, used as the merchant reference when passing data to eWAY.
     *
     * @return string
     */
    public function getCustomerRef()
    {
        return $this->getSalesObject()->getId();
    }

    /**
     * @return string
     */
    public function getFormattedAddress()
    {
        $address = '';
        $billing = $this->getBilling();
        $addressLines = $billing->getStreet();

        if (!empty($addressLines)) {
            foreach ($addressLines as $line) {
                $address .= trim(str_replace("\n", ' ', $line)) . ' ';
            }
        }

        return trim($address);
    }

    /**
     * @return array
     */
    public function getUsedCustomerFields()
    {
        return array(
            'Title', 'FirstName', 'LastName', 'Email', 'Country',
            'CCNameOnCard', 'CCExpiryMonth', 'CCExpiryYear', 'CCNumber',
        );
    }

    /**
     * @return array
     */
    public function createCustomerDataArray()
    {
        $requestData = array();
        $billing = $this->getBilling();
        $paymentData = $this->getPaymentData();

        $title = $billing->getPrefix();

        if (empty($title)) {
            $title = self::DEFAULT_CUSTOMER_TITLE;
        } elseif (!$this->validCustomerTitle($title)) {
            if ($this->validCustomerTitle($title . '.')) {
                $title = $title . '.';
            } else {
                $title = self::DEFAULT_CUSTOMER_TITLE;
            }
        }

        $requestData['Title'] = $title;
        $requestData['FirstName'] = $billing->getFirstname();
        $requestData['LastName'] = $billing->getLastname();
        $requestData['Email'] = '';
        $requestData['Address'] = '';
        $requestData['Suburb'] = '';
        $requestData['State'] = '';
        $requestData['PostCode'] = '';
        // eWAY expects the country code to be lowercase
        $requestData['Country'] = strtolower($billing->getCountryId());
        // API call will not work if these fields are not specified.
        $requestData['Company'] = '';
        $requestData['JobDesc'] = '';
        $requestData['Phone'] = '';
        $requestData['Mobile'] = '';
        $requestData['Fax'] = '';
        $requestData['Comments'] = '';
        $requestData['URL'] = '';
        $requestData['CustomerRef'] = $this->getCustomerRef();

        $requestData['CCNumber'] = $paymentData->getCcNumber();
        $requestData['CCNameOnCard'] = $paymentData->getCcOwner();
        $requestData['CCExpiryMonth'] = $paymentData->getCcExpMonth();

        $ccYear = $paymentData->getCcExpYear();
        if (strlen($ccYear) > 2) {
            // Assume the four digits were entered for the year, and take the last two.
            // eg. convert '2014' to '14'
            $ccYear = substr($paymentData->getCcExpYear(), -2);
        }

        $requestData['CCExpiryYear'] = $ccYear;

        return $requestData;
    }

    /**
     * @return array
     */
    public function getCreateCustomerArray()
    {
        return $this->createCustomerDataArray();
    }

    /**
     * @return array
     */
    public function getUpdateCustomerArray()
    {
        $requestArray = $this->createCustomerDataArray();
        $requestArray[self::EWAY_CUSTOMER_ID_FIELD] = $this->getCustomerId();

        return $requestArray;
    }

    /**
     * @return array
     */
    public function getQueryCustomerByReferenceArray()
    {
        return array('CustomerReference' => $this->getCustomerRef());
    }

    /**
     * @return array
     */
    public function getProcessPaymentArray()
    {
        $customerId = $this->getCustomerId();

        if (!$customerId) {
            Mage::throwException('Unable to process payment.');
        }

        return array(
            self::EWAY_CUSTOMER_ID_FIELD    =>  $customerId,
            'amount'                        =>  $this->getAmount(),
            'invoiceReference'              =>  $this->getSalesObject()->getIncrementId(), // Only present on the order object
            'invoiceDescription'            =>  Mage::helper('ewayau')->getInvoiceDescription($this->getSalesObject()),
        );
    }
}
