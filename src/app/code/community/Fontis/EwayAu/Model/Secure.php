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

class Fontis_EwayAu_Model_Secure extends Fontis_EwayAu_Model_Shared
{
    protected $_code = 'ewayau_secure';

    protected $_formBlockType = 'ewayau/secure_form';
    protected $_paymentMethod = 'secure';

    /**
     * Get url of eWAY 3D-Secure Payment
     *
     * @return string
     */
    public function getEwaySecureUrl()
    {
         if (!$url = $this->getConfigData('api_url')) {
             $url = 'https://www.eway.com.au/gateway_3d/payment.asp';
         }
         return $url;
    }
}
