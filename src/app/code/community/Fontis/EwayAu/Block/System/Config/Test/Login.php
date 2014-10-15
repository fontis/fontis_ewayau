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

abstract class Fontis_EwayAu_Block_System_Config_Test_Login extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    /**
     * @var string
     */
    protected $_method;

    /**
     * Prepares the layout for the login test button
     *
     * Sets the template to use the one to generate the test button markup
     *
     * @return Fontis_EwayAu_Block_System_Config_Test_Login
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();
        $this->setTemplate('fontis/ewayau/test/login.phtml');

        return $this;
    }

    /**
     * Generates the Html content for the test button
     *
     * This is called by core magento and needs to be here otherwise the button will not generate
     *
     * @param Varien_Data_Form_Element_Abstract $element The Magento form element (in this case the button)
     * @return string The Html content
     */
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        return $this->_toHtml();
    }

    /**
     * Returns the URL that will hit the controller that handles testing the eWay API
     *
     * @return string The URL of the test controller
     */
    public function getTestUrl()
    {
        return Mage::getSingleton('adminhtml/url')->getUrl('*/system_config_test_login/test' . ucfirst($this->_method));
    }

    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->_method;
    }
}
