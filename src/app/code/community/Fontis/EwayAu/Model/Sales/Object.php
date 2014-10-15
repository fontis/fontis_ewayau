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

/**
 * Informal interface for Mage_Sales_Model_Order and Mage_Sales_Model_Quote
 */
interface Fontis_EwayAu_Model_Sales_Object
{
    /**
     * @return array
     */
    public function getAllItems();

    /**
     * @return Mage_Customer_Model_Address_Abstract
     */
    public function getBillingAddress();

    /**
     * @return string
     */
    public function getBaseCurrencyCode();

    /**
     * @return string
     */
    public function getId();
}
