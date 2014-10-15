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
 
class Fontis_EwayAu_Block_Shared_Redirect extends Fontis_EwayAu_Block_Redirect
{
    /**
     * @var string
     */
    protected $_formId = 'ewayau_shared_checkout';

    /**
     * @var string
     */
    protected $_redirectlabel = 'eWAY';

    /**
     * @return Fontis_EwayAu_Model_Shared
     */
    protected function getMethodInstance()
    {
        return $this->getOrder()->getPayment()->getMethodInstance();
    }

    /**
     * @return string
     */
    protected function getRedirectUrl()
    {
        return $this->getMethodInstance()->getEwaySharedUrl();
    }

    /**
     * @return array
     */
    protected function getFormData()
    {
        return $this->getMethodInstance()->getFormFields();
    }
}
