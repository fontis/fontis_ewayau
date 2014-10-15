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

class Fontis_EwayAu_System_Config_Test_LoginController extends Mage_Adminhtml_Controller_Action
{
    const ERR_LOGIN_FAILED = 'Login failed';

    /**
     * Tests if the credentials present in the admin panel fields are valid. This doesn't
     * check the saved values from config but instead uses whatever it typed into the fields
     * at the time the button is pressed.
     * This will use the eWay API set up in the extension, ie: it will take into account test
     * mode if that is set to be on.
     */
    public function testTokenAction()
    {
        // Set up the SOAP object with the eway credentials in the admin panel fields.
        $postData = $this->getRequest()->getPost();

        $testGateway = $postData['test_gateway'] ? true : false;
        $endpoint = Mage::getModel('ewayau/token')->getApiGatewayUrl($testGateway);

        $request = Mage::getModel('ewayau/token_request', array(
            'wsdl'          =>  $endpoint . '?WSDL',
            'endpoint'      =>  $endpoint,
            'header'        =>  array(
                'eWAYCustomerID'    => $postData['customer_id'],
                'Username'          => $postData['username'],
                'Password'          => $postData['password'],
            ),
            'soap_options'  =>  array(
                'exceptions'    => false,
                'soap_version'  => SOAP_1_2,
                'compression'   => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP
            )
        ));

        // Test the API using QueryCustomer. If it fails to login (ie: incorrect credentials) it will return
        // the string "Login failed" as part of the exception. In other cases it will either succeed (if customer ID
        // 1 exists or return an exception that doesn't include "Login failed".
        try {
            $return = $request->execute('QueryCustomer', '1');
            echo $this->processTokenResponse($return);
        } catch (Exception $e) {
            echo $this->processTokenResponse($e);
        }
    }

    /**
     * @param object $responseObject
     * @return int
     */
    protected function processTokenResponse($responseObject)
    {
        if ($responseObject instanceof Exception) {
            if (strpos($responseObject->getMessage(), self::ERR_LOGIN_FAILED) === false) {
                return 1;
            } else {
                return 0;
            }
        } else {
            return 1;
        }
    }
}
