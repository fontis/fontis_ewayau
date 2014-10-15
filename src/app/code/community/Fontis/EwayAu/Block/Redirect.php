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

/**
 * @method Mage_Sales_Model_Order getOrder()
 */
abstract class Fontis_EwayAu_Block_Redirect extends Mage_Core_Block_Abstract
{
    /**
     * @var string
     */
    protected $_formId;

    /**
     * @var string
     */
    protected $_redirectlabel;

    /**
     * @return string
     */
    protected function _toHtml()
    {
        $form = new Varien_Data_Form();
        $form->setAction($this->getRedirectUrl())
            ->setId($this->_formId)
            ->setName($this->_formId)
            ->setMethod('POST')
            ->setUseContainer(true);
        foreach ($this->getFormData() as $field => $value) {
            $form->addField($field, 'hidden', array('name' => $field, 'value' => $value));
        }

        $html = '<html><body>';
        $html.= $this->__('You will be redirected to %s in a few seconds.', $this->_redirectlabel);
        $html.= $form->toHtml();
        $html.= '<script type="text/javascript">document.getElementById("' . $this->_formId . '").submit();</script>';
        $html.= '</body></html>';

        return $html;
    }

    /**
     * @return string
     */
    abstract protected function getRedirectUrl();

    /**
     * @return array
     */
    abstract protected function getFormData();
}
