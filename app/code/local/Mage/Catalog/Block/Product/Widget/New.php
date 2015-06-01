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
 * @package     Mage_Catalog
 * @copyright   Copyright (c) 2012 Magento Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * New products widget
 *
 * @category   Mage
 * @package    Mage_Catalog
 * @author      Magento Core Team <core@magentocommerce.com>
 */
class Mage_Catalog_Block_Product_Widget_New
    extends Mage_Catalog_Block_Product_New
    implements Mage_Widget_Block_Interface
{
    /**
     * Internal contructor
     *
     */
    protected function _construct()
    {
        parent::_construct();
        $this->addPriceBlockType('bundle', 'bundle/catalog_product_price', 'bundle/catalog/product/price.phtml');
    }
	
	protected function _beforeToHtml()
    {
        $html = '';
		
        $entityids = $this->getData('products_sku');
        
        if (empty($entityids)) {
            //return $html;
        }
		$eids = explode(',', $entityids);
		$idlist = array();
        foreach ($eids as $eid) {
            if ($eid) {
                $idlist[] = $eid;
            }
        }
        $collection = Mage::getResourceModel('catalog/product_collection');
		
        $collection->setVisibility(Mage::getSingleton('catalog/product_visibility')->getVisibleInCatalogIds());
		$collection = $this->_addProductAttributesAndPrices($collection)
			->addAttributeToFilter('SKU', array('in' => $idlist))
            ->addStoreFilter()
            ->setPageSize($this->getProductsCount())
            ->setCurPage(1)
        ;
		
        $this->setProductCollection($collection);
		
        return parent::_beforeToHtml();
    }
	 public function setProductsCount($count)
    {
        $this->_productsCount = $count;
        return $this;
    }
    /**
     * Retrieve how much products should be displayed.
     *
     * @return int
     */
    public function getProductsCount()
    {
        if (!$this->hasData('products_count')) {
            return parent::getProductsCount();
        }
        return $this->_getData('products_count');
    }
	public function getProductids()
	{
		if (!$this->hasData('products_sku')) {
            return $this->_getData('products_sku');
        }
        return $this->_getData('products_sku');
	}
}
