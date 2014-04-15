<?php

/**
 * Fontis eWAY Australia payment gateway
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so you can be sent a copy immediately.
 *
 * Original code copyright (c) 2008 Irubin Consulting Inc. DBA Varien
 *
 * @category   Fontis
 * @package    Fontis_EwayAu
 * @copyright  Copyright (c) 2010 Fontis (http://www.fontis.com.au)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Observer to handle invoice email sending after payment was completed
 * 
 * @author     Lucas van Staden (support@proxiblue.com.au)
 * */
class Fontis_EwayAu_Model_Observer {

    /**
     * Email invoice when all order objects have been completed
     * 
     * @param type $observer
     */
    public function checkout_submit_all_after($observer) {
        $order = $observer->getOrder();
        if ($order->hasInvoices()) {
            foreach ($order->getInvoiceCollection() as $invoice) {
                // email out the invoices 
                try {
                    $storeId = $invoice->getOrder()->getStoreId();
                    if (Mage::helper('sales')->canSendNewInvoiceEmail($storeId)) {
                        $invoice->sendEmail();
                    }
                } catch (Exception $e) {
                    mage::logException($e);
                }
            }
        }
        return $this;
    }

}
