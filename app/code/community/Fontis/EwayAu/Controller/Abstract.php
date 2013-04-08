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

abstract class Fontis_EwayAu_Controller_Abstract extends Mage_Core_Controller_Front_Action
{
    protected function _expireAjax()
    {
        if (!$this->getCheckout()->getQuote()->hasItems()) {
            $this->getResponse()->setHeader('HTTP/1.1','403 Session Expired');
            exit;
        }
    }

    /**
     * Redirect Block
     * need to be redeclared
     */
    protected $_redirectBlockType;

    /**
     * Get singleton of Checkout Session Model
     *
     * @return Mage_Checkout_Model_Session
     */
    public function getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * when customer select eWay payment method
     */
    public function redirectAction()
    {
        $session = $this->getCheckout();
        $session->setEwayQuoteId($session->getQuoteId());
        $session->setEwayRealOrderId($session->getLastRealOrderId());

        $order = Mage::getModel('sales/order');
        $order->loadByIncrementId($session->getLastRealOrderId());
        $order->addStatusToHistory($order->getStatus(), Mage::helper('ewayau')->__('Customer was redirected to eWAY.'));
        $order->save();

        $this->getResponse()->setBody(
            $this->getLayout()
                ->createBlock($this->_redirectBlockType)
                ->setOrder($order)
                ->toHtml()
        );

        $session->unsQuoteId();
    }

    /**
     * eWay returns POST variables to this action
     */
    public function  successAction()
    {
        $status = $this->_checkReturnedPost();

        $session = $this->getCheckout();

        $session->unsEwayRealOrderId();
        $session->setQuoteId($session->getEwayQuoteId(true));
        $session->getQuote()->setIsActive(false)->save();

        $order = Mage::getModel('sales/order');
        $order->load($this->getCheckout()->getLastOrderId());
        if($order->getId()) {
            $order->sendNewOrderEmail();
        }

        if ($status) {
            $this->_redirect('checkout/onepage/success');
        } else {
            $this->_redirect('*/*/failure');
        }
    }

    /**
     * Display failure page if error
     *
     */
    public function failureAction()
    {
        if (!$this->getCheckout()->getEwayErrorMessage()) {
            $this->norouteAction();
            return;
        }

        $this->getCheckout()->clear();

        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * Checking POST variables.
     * Creating invoice if payment was successfull or cancel order if payment was declined
     */
    protected function _checkReturnedPost()
    {
        if (!$this->getRequest()->isPost()) {
            $this->norouteAction();
            return;
        }
        $status = true;
        $response = $this->getRequest()->getPost();

        if ($this->getCheckout()->getEwayRealOrderId() != $response['ewayTrxnNumber'] ||
                $this->getCheckout()->getEwayRealOrderId() != Mage::helper('core')->decrypt($response['eWAYoption2'])) {
            $this->norouteAction();
            return;
        }

        $order = Mage::getModel('sales/order');
        $order->loadByIncrementId($response['ewayTrxnNumber']);

        $paymentInst = $order->getPayment()->getMethodInstance();
        $paymentInst->setResponse($response);

        if ($paymentInst->parseResponse()) {

            if ($order->canInvoice()) {
                $invoice = $order->prepareInvoice();
                $invoice->register()->capture();
                Mage::getModel('core/resource_transaction')
                    ->addObject($invoice)
                    ->addObject($invoice->getOrder())
                    ->save();

                $paymentInst->setLastTransId($response['ewayTrxnNumber']);
                $paymentInst->setTransactionId($response['ewayTrxnReference']);
                $order->addStatusToHistory($order->getStatus(), Mage::helper('ewayau')->__('Customer successfully returned from eWAY'));
            }
        } else {
            $paymentInst->setLastTransId($response['ewayTrxnNumber']);
            $paymentInst->setTransactionId($response['ewayTrxnReference']);
            $order->cancel();
            $order->addStatusToHistory($order->getStatus(), Mage::helper('ewayau')->__('Customer was rejected by eWAY'));
            $status = false;
            $this->getCheckout()->setEwayErrorMessage($response['eWAYresponseText']);
        }

        $order->save();

        return $status;
    }

}
