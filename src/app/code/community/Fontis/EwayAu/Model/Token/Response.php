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

abstract class Fontis_EwayAu_Model_Token_Response extends Varien_Object
{
    const CUSTOMER_ID_FIELD = 'ManagedCustomerID';

    /**
     * @var bool
     */
    protected $_isRequestSuccessful = false;

    /**
     * @var Fontis_EwayAu_Helper_Token
     */
    protected $_tokenHelper = null;

    /**
     * @return bool
     */
    public function process()
    {
        $this->getTokenHelper()->logMessage(get_class($this), $this->getData());
        return $this->_isRequestSuccessful;
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
     * @return bool
     */
    public function isRequestSuccessful()
    {
        return $this->_isRequestSuccessful;
    }
}
