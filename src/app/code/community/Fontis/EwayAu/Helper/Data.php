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
 * @copyright  Copyright (c) 2014 Fontis Pty. Ltd. (http://www.fontis.com.au)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Fontis_EwayAu_Helper_Data extends Mage_Core_Helper_Abstract
{
    const DEFAULT_TIMEOUT = 30;

    const ERROR_MSG_DONOTHONOUR = '05,do not honour';

    /**
     * @param Mage_Sales_Model_Order_Address $billing
     * @return string
     */
    public function getOrderAddressString($billing)
    {
        $address = clone $billing;
        $address->unsFirstname();
        $address->unsLastname();
        $address->unsPostcode();

        // Strip blank lines from the address
        $tmpAddress = preg_replace('/^\n+|^[\t\s]*\n+/m', '', trim($address->format('text')));
        return str_replace("\n", ' ', $tmpAddress);
    }

    /**
     * @param Fontis_EwayAu_Model_Sales_Object $salesObject
     * @param int $limit Soft maximum limit on description length.
     * @return string
     */
    public function getInvoiceDescription($salesObject, $limit = 255)
    {
        /** @var $stringHelper Mage_Core_Helper_String */
        $stringHelper = Mage::helper('core/string');

        $invoiceDesc = '';
        foreach ($salesObject->getAllItems() as $item) {
            if ($item->getParentItem()) {
                continue;
            }
            if ($stringHelper->strlen($invoiceDesc . $item->getName()) > $limit) {
                break;
            }
            $invoiceDesc .= $item->getName() . ', ';
        }
        return $stringHelper->substr($invoiceDesc, 0, -2);
    }
}
