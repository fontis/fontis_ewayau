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
class Fontis_EwayAu_Model_Secure extends Fontis_EwayAu_Model_Shared
{
    protected $_code  = 'ewayau_secure';

    protected $_formBlockType = 'ewayau/secure_form';
    protected $_paymentMethod = 'secure';

    /**
     * Get url of eWAY 3D-Secure Payment
     *
     * @return string
     */
    public function getEwaySecureUrl()
    {
         if (!$url = Mage::getStoreConfig('payment/' . $this->getCode() . '/api_url')) {
             $url = 'https://www.eway.com.au/gateway_3d/payment.asp';
         }
         return $url;
    }

}
