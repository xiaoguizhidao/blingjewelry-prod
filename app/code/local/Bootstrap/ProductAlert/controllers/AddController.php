<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Mage
 * @package     Mage_ProductAlert
 * @copyright   Copyright (c) 2014 Magento Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * ProductAlert controller
 *
 * @category   Mage
 * @package    Mage_ProductAlert
 * @author      Magento Core Team <core@magentocommerce.com>
 */

// Controllers are not autoloaded so we will have to do it manually:
require_once 'Mage/ProductAlert/controllers/AddController.php';
class Bootstrap_ProductAlert_AddController extends Mage_ProductAlert_AddController
{

    public function preDispatch()
    {
        parent::preDispatch();
        if (!Mage::getSingleton('customer/session')->authenticate($this)) {
            $this->setFlag('', 'no-dispatch', true);
            if(!Mage::getSingleton('customer/session')->getBeforeUrl()) {
                Mage::getSingleton('customer/session')->setBeforeUrl($this->_getRefererUrl());
            }
        }
    }


    public function stockAction()
    {
        //mmc added to store response
        $data  = array();

        $session = Mage::getSingleton('catalog/session');
        /* @var $session Mage_Catalog_Model_Session */
        $backUrl    = $this->getRequest()->getParam(Mage_Core_Controller_Front_Action::PARAM_NAME_URL_ENCODED);
        $productId  = (int) $this->getRequest()->getParam('product_id');
        if (!$backUrl || !$productId) {
            //$this->_redirect('/');

            // mmc error state
            $data['status'] = 'ERROR';
            $data['message'] = 'Not enough parameters.';
            $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($data));

            return ;
        }

        if (!$product = Mage::getModel('catalog/product')->load($productId)) {
            /* @var $product Mage_Catalog_Model_Product */
            //$session->addError($this->__('Not enough parameters.'));
            //$this->_redirectUrl($backUrl);


            // mmc error state
            $data['status'] = 'ERROR';
            $data['message'] = 'Not enough parameters.';
            $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($data));

            return;
        }

        try {
            $model = Mage::getModel('productalert/stock')
                ->setCustomerId(Mage::getSingleton('customer/session')->getId())
                ->setProductId($product->getId())
                ->setWebsiteId(Mage::app()->getStore()->getWebsiteId());
            $model->save();

            // mmc success
            $data['status'] = 'SUCCESS';
            $data['message'] = 'Alert subscription has been saved.';

            //$session->addSuccess($this->__('Alert subscription has been saved.'));
        }
        catch (Exception $e) {

            // mmc error
            $data['status'] = 'ERROR';
            $data['message'] = 'Unable to update the alert subscription.';

            // $session->addException($e, $this->__('Unable to update the alert subscription.'));
        }
        //$this->_redirectReferer();

        // return result
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($data));
        return;
    }
}
