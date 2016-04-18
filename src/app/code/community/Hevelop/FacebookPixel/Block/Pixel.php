<?php

/**
 * Class Hevelop_FacebookPixel_Block_Pixel
 *
 * @category Magento_Module
 * @package  Hevelop_FacebookPixel
 * @author   Simone Marcato <simone@hevelop.com>
 * @license  http://opensource.org/licenses/agpl-3.0  GNU Affero General Public License v3 (AGPL-3.0)
 * @link     https://github.com/Hevelop/Facebookpixel
 */
class Hevelop_FacebookPixel_Block_Pixel extends
 Mage_Core_Block_Template
{

    protected $helper;


    /**
     * Hevelop_FacebookPixel_Block_Pixel constructor.
     */
    public function __construct()
    {
        $this->helper = Mage::helper('hevelop_facebookpixel');

    }//end __construct()


    /**
     * Get a specific page name (may be customized via layout)
     *
     * @return string|null
     */
    public function getPageName()
    {
        return $this->_getData('page_name');

    }//end getPageName()


    /**
     * getCategoryPixelCode
     *
     * @return string
     */
    protected function getCategoryPixelCode()
    {
        $pixelCat      = '';
        $attributeCode = $this->helper->getAttributeCodeForCatalog();
        $currCat       = $this->getCurrentCategory();

        if ($currCat && !Mage::registry('product')) {
            $products   = $this->getProducts();
            $productIds = array();
            foreach ($products as $product) {
                if ($attributeCode === false) {
                    $productIds[] = $product->getId();
                } else {
                    $productIds[] = $product->getData($attributeCode);
                }
            }//end foreach

            $pixelCat = "fbq('track', 'ViewContent', {content_category: '" . $currCat->getName()
                . "', content_ids: ['" . implode("','", $productIds) . "'], content_type: 'product', product_catalog_id: " . Mage::helper('hevelop_facebookpixel')->getProductCatalogId() . "});";
        }

        return $pixelCat;

    }//end getCategoryPixelCode()


    /**
     * getSearchPixelCode
     *
     * @return string
     */
    protected function getSearchPixelCode()
    {
        $pixelSearch   = '';
        $attributeCode = $this->helper->getAttributeCodeForCatalog();
        $term          = Mage::helper('catalogsearch')->getQueryText();
        if ($term) {
            $products   = $this->getProductCollection();
            $productIds = array();
            foreach ($products as $product) {
                if ($attributeCode === false) {
                    $productIds[] = $product->getId();
                } else {
                    $productIds[] = $product->getData($attributeCode);
                }
            }//end foreach

            $pixelSearch = "fbq('track', 'Search', {content_ids: " . json_encode($productIds) . ", content_type: 'product_group', search_string: " . json_encode($term) . ", product_catalog_id: " . Mage::helper('hevelop_facebookpixel')->getProductCatalogId() . "});";
        }

        return $pixelSearch;

    }//end getSearchPixelCode()


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

    }//end getCurrentCategory()


    /**
     * Retrieve loaded category collection
     *
     * @return Mage_Catalog_Model_Resource_Collection_Abstract | null
     */
    protected function getProducts()
    {
        /** @var Mage_Catalog_Model_Category $category */
        $category = $this->getCurrentCategory();
        if ($category && ($category->getDisplayMode() == Mage_Catalog_Model_Category::DM_MIXED ||
                $category->getDisplayMode() == Mage_Catalog_Model_Category::DM_PRODUCT)
        ) {
            return $this->getProductCollection();
        }

        return null;

    }//end getProducts()


    /**
     * Retrieve loaded category collection
     *
     * @return Mage_Catalog_Model_Resource_Collection_Abstract | null
     */
    protected function getProductCollection()
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
        $limit = (int)$this->getListBlock()->getToolbarBlock()->getLimit();
        if ($limit) {
            $this->_productCollection->setPageSize($limit);
        }

        return $this->_productCollection;

    }//end getProductCollection()


    /**
     * getListBlock
     *
     * @return mixed
     */
    public function getListBlock()
    {
        return $this->getLayout()->getBlock($this->getData('block_name'));

    }//end getListBlock()


    /**
     * getCurrentPage
     *
     * @return int
     */
    public function getCurrentPage()
    {
        if ($page = (int)$this->getRequest()->getParam($this->getPageVarName())) {
            return $page;
        }

        return 1;

    }//end getCurrentPage()


    /**
     * getProductPixelCode
     *
     * @return string
     */
    protected function getProductPixelCode()
    {
        $pixelProd = '';
        if ($product = Mage::registry('product')) {

            $attributeCode = $this->helper->getAttributeCodeForCatalog();
            $pixelProd     = "fbq('track', 'ViewContent', {content_name: '" . $product->getName() . "'";
            if ($currCat = $this->getCurrentCategory()) {
                $pixelProd .= ", content_category: '" . $currCat->getName() . "'";
            }

            if ($attributeCode === false) {
                $productId = $product->getId();
            } else {
                $productId = $product->getData($attributeCode);
            }

            $pixelProd .= ", content_ids: ['" . $productId . "'], content_type: 'product', value: '" . $product->getPrice() . "', currency: '" . Mage::app()->getStore()->getBaseCurrencyCode() . "', product_catalog_id: " . Mage::helper('hevelop_facebookpixel')->getProductCatalogId() . "});";
        }

        return $pixelProd;

    }//end getProductPixelCode()


    /**
     * Render information about specified orders and their items
     *
     * @link http://code.google.com/apis/analytics/docs/gaJS/gaJSApiEcommerce.html#_gat.GA_Tracker_._addTrans
     * @return string
     */
    protected function getOrdersTrackingCode()
    {
        $orderIds = $this->getOrderIds();
        if (empty($orderIds) || !is_array($orderIds)) {
            return '';
        }
        $collection = Mage::getResourceModel('sales/order_collection')
            ->addFieldToFilter('entity_id', array('in' => $orderIds));
        $result = array();
        $attributeCode = $this->helper->getAttributeCodeForCatalog();
        foreach ($collection as $order) {
            $productIds = array();
            foreach ($order->getAllVisibleItems() as $item) {
                if ($attributeCode === false) {
                    $productIds[] = $item->getProductId();
                } else {
                    $productIds[] = $item->getProduct()->getData($attributeCode);
                }
            }
            $result[] = sprintf("fbq('track', 'Purchase', {%s: %s, %s: '%s', %s: '%s', %s: '%s', %s: '%s', %s: '%s'})",
                'content_ids', json_encode($productIds),
                'content_type', 'product',
                'value', $order->getBaseGrandTotal(),
                'currency', Mage::app()->getStore()->getBaseCurrencyCode(),
                'num_items', count($order->getAllVisibleItems()),
                'order_id', $order->getIncrementId(),
                'product_catalog_id', Mage::helper('hevelop_facebookpixel')->getProductCatalogId()
            );
        }

        return implode("\n", $result);

    }//end getOrdersTrackingCode()


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

    }//end _toHtml()


}//end class
