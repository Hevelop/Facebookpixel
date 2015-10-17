<?php


/**
 * Hevelop Fcebook Pixel Block
 *
 * @category   Hevelop
 * @package    Hevelop_FacebookPixel
 * @author     Hevelop Team <systems@hevelop.com>
 */
class Hevelop_FacebookPixel_Block_Pixel extends Mage_Core_Block_Template
{

    /**
     * Get a specific page name (may be customized via layout)
     *
     * @return string|null
     */
    public function getPageName()
    {
        return $this->_getData('page_name');
    }

    protected function _getCategoryPixelCode()
    {
        $pixelCat = '';
        $currCat = $this->getCurrentCategory();
        if ($currCat && !Mage::registry('product')) {
            $products = $this->_getProducts();
            $productIds = array();
            foreach ($products as $product) {
                array_push($productIds, $product->getId());
            }
            $pixelCat = "fbq('track', 'ViewContent', {content_category: '" . $currCat->getName()
                . "', content_ids: [" . implode(',', $productIds) . "], content_type: 'product_group', product_catalog_id: " . Mage::helper('hevelop_facebookpixel')->getProductCatalogId() . "});";
        }
        return $pixelCat;
    }

    /**
     * Retrieves a current category
     *
     * @return Mage_Catalog_Model_Category
     */
    public function getCurrentCategory()
    {
        /** @var Mage_Catalog_Model_Category $category */
        $category = null;

        if (Mage::getSingleton('catalog/layer')) {
            $category = Mage::getSingleton('catalog/layer')->getCurrentCategory();
        } else if (Mage::registry('current_category')) {
            $category = Mage::registry('current_category');
        }
        return $category;
    }

    /**
     * Retrieve loaded category collection
     *
     * @return Mage_Catalog_Model_Resource_Collection_Abstract | null
     */
    protected function _getProducts()
    {
        /** @var Mage_Catalog_Model_Category $category */
        $category = $this->getCurrentCategory();
        if ($category && ($category->getDisplayMode() == Mage_Catalog_Model_Category::DM_MIXED ||
                $category->getDisplayMode() == Mage_Catalog_Model_Category::DM_PRODUCT)
        ) {
            return $this->_getProductCollection();
        }
        return null;
    }

    /**
     * Retrieve loaded category collection
     *
     * @return Mage_Catalog_Model_Resource_Collection_Abstract | null
     */
    protected function _getProductCollection()
    {
        /* For catalog list and search results
         * Expects getListBlock as Mage_Catalog_Block_Product_List
         */
        if (is_null($this->_productCollection)) {
            $this->_productCollection = $this->getListBlock()->getLoadedProductCollection();
        }

        /* For collections of cross/up-sells and related
         * Expects getListBlock as one of the following:
         * Enterprise_TargetRule_Block_Catalog_Product_List_Upsell | _linkCollection
         * Enterprise_TargetRule_Block_Catalog_Product_List_Related | _items
         * Enterprise_TargetRule_Block_Checkout_Cart_Crosssell | _items
         * Mage_Catalog_Block_Product_List_Related | _itemCollection
         * Mage_Catalog_Block_Product_List_Upsell | _itemCollection
         * Mage_Checkout_Block_Cart_Crosssell, | setter items
         */
        if ($this->_showCrossSells && is_null($this->_productCollection)) {
            $this->_productCollection = $this->getListBlock()->getItemCollection();
        }

        // Support for CE
        if (is_null($this->_productCollection)
            && ($this->getBlockName() == 'catalog.product.related'
                || $this->getBlockName() == 'checkout.cart.crosssell')
        ) {
            $this->_productCollection = $this->getListBlock()->getItems();
        }

        //limit collection for page product
        $this->_productCollection->setCurPage($this->getCurrentPage());

        // we need to set pagination only if passed value integer and more that 0
        $limit = (int)Mage_Catalog_Block_Product_List_Toolbar::getLimit();
        if ($limit) {
            $this->_productCollection->setPageSize($limit);
        }

        return $this->_productCollection;
    }

    public function getListBlock()
    {
        Mage::log('block name  ' . $this->getBlockName());
        Mage::log('block name  ' . $this->getData('block_name'));
        return $this->getLayout()->getBlock($this->getData('block_name'));
    }

    public function getCurrentPage()
    {
        if ($page = (int)$this->getRequest()->getParam($this->getPageVarName())) {
            return $page;
        }
        return 1;
    }

    protected function _getProductPixelCode()
    {
        $pixelProd = '';
        if ($product = Mage::registry('product')) {

            $pixelProd = "fbq('track', 'ViewContent', {content_name: '" . $product->getName() . "'";
            if ($currCat = $this->getCurrentCategory()) {
                $pixelProd .= ", content_category: '" . $currCat->getName() . "'";
            }
            $pixelProd .= ", content_ids: [" . $product->getId() . "], content_type: 'product', value: '" . $product->getPrice() . "', currency: '" . Mage::app()->getStore()->getBaseCurrencyCode() . "', product_catalog_id: " . Mage::helper('hevelop_facebookpixel')->getProductCatalogId() . "});";
        }
        return $pixelProd;
    }

    /**
     * Render information about specified orders and their items
     *
     * @link http://code.google.com/apis/analytics/docs/gaJS/gaJSApiEcommerce.html#_gat.GA_Tracker_._addTrans
     * @return string
     */
    protected function _getOrdersTrackingCode()
    {
        $orderIds = $this->getOrderIds();
        if (empty($orderIds) || !is_array($orderIds)) {
            return '';
        }
        $collection = Mage::getResourceModel('sales/order_collection')
            ->addFieldToFilter('entity_id', array('in' => $orderIds));
        $result = array();
        foreach ($collection as $order) {
            $productIds = array();
            foreach ($order->getAllVisibleItems() as $item) {
                $productIds[] = $item->getProductId();
            }
            $result[] = sprintf("fbq('track', 'Purchase', {%s: '%s', %s: '%s', %s: '%s', %s: '%s', %s: '%s', %s: '%s'})",
                'content_ids', '[' . implode(',', $productIds) . ']',
                'content_type', 'product',
                'value', $order->getBaseGrandTotal(),
                'currency', Mage::app()->getStore()->getBaseCurrencyCode(),
                'num_items', count($order->getAllVisibleItems()),
                'order_id', $order->getIncrementId(),
                'product_catalog_id', Mage::helper('hevelop_facebookpixel')->getProductCatalogId()
            );
        }
        return implode("\n", $result);
    }

    /**
     * Render facebook pixel scripts
     *
     * @return string
     */
    protected function _toHtml()
    {
        if (!Mage::helper('hevelop_facebookpixel')->isEnabled()) {
            return '';
        }
        return parent::_toHtml();
    }
}
