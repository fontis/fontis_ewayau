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

class Fontis_EwayAu_Model_Token_Response_CreateCustomer extends Fontis_EwayAu_Model_Token_Response
{
    /**
     * @return bool
     */
    public function process()
    {
        if ($customerId = $this->getCreateCustomerResult()) {
            $this->_isRequestSuccessful = true;
            $this->getTokenHelper()->setCustomerId($customerId);
        }

        return parent::process();
    }
}
