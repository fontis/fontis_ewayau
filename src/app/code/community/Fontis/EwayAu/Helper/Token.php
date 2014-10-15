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

class Fontis_EwayAu_Helper_Token extends Mage_Core_Helper_Data
{
    const CUSTOMER_ID_FIELD = 'ewayau_token_customer_id';

    const LOG_FILE = 'ewayau_token.log';

    /**
     * @param int $storeId
     * @return bool
     */
    public function isLogEnabled($storeId = null)
    {
        return Mage::getStoreConfigFlag('payment/ewayau_token/debug', $storeId);
    }

    /**
     * @param string $message
     */
    public function logMessage($message, $data = null)
    {
        if ($this->isLogEnabled() === true) {
            if ($data !== null) {
                $message = Zend_Debug::dump($data, $message, false);
            }
            Mage::log($message, null, self::LOG_FILE);
        }
    }

    /**
     * @return string
     */
    public function getCustomerId()
    {
        return Mage::getSingleton("checkout/session")->getData(self::CUSTOMER_ID_FIELD);
    }

    /**
     * @param string $customerId
     */
    public function setCustomerId($customerId)
    {
        return Mage::getSingleton("checkout/session")->setData(self::CUSTOMER_ID_FIELD, $customerId);
    }

    /**
     * @return Mage_Checkout_Model_Session
     */
    public function clearCustomerId()
    {
        return Mage::getSingleton("checkout/session")->unsetData(self::CUSTOMER_ID_FIELD);
    }

    /**
     * Verify if the partially eWAY returned credit card number matches inputted value.
     *
     * @param string $new
     * @param string $stored
     * @return bool
     */
    public function hasCreditCardNumberChanged($new, $stored)
    {
        if (strstr($stored, 'XXXXXX')) {
            $numbersToValidate = explode('XXXXXX', $stored);

            $newFirstSix = substr($new, 0, 6);
            $newLastFour = substr($new, -4);

            if ($numbersToValidate[0] == $newFirstSix && $numbersToValidate[1] == $newLastFour) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param int $month
     * @param int $year
     * @return bool
     */
    public function hasCreditCardExpired($month, $year)
    {
        /** @var $dateModel Mage_Core_Model_Date */
        $dateModel = Mage::getModel('core/date');
        $currentDate = (int) $dateModel->gmtTimestamp();
        $cardExpiry = (int) $dateModel->gmtTimestamp(mktime(0, 0, 0, $month, $year));

        if ($currentDate > $cardExpiry) {
            return true;
        }

        return false;
    }
}
