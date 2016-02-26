<?php

class Hevelop_FacebookPixel_Helper_Data extends Mage_Core_Helper_Abstract
{

    const COOKIE_CART_ADD = 'facebookpixel_cart_add';
    const COOKIE_CART_REMOVE = 'facebookpixel_cart_remove';

    const COOKIE_WISHLIST_ADD = 'facebookpixel_wishlist_add';

    const XML_PATH_ENABLE = 'hevelopfacebookpixel/general/enabled';
    const XML_PATH_PIXEL_ID = 'hevelopfacebookpixel/general/pixelid';
    const XML_PATH_PRODUCT_CATALOG_ID = 'hevelopfacebookpixel/general/product_catalog_id';

    const PRODUCT_QUANTITIES_BEFORE_ADDTOCART = 'prev_product_qty';
    const PRODUCT_QUANTITIES_BEFORE_ADDTOWISHLIST = 'wishlist_prev_product_qty';

    /**
     * If Facebook Pixel is enable return pixel id
     * @return mixed|null|int
     */
    public function getPixelId()
    {
        $pixelId = null;

        if ($this->isEnabled()) {
            $pixelId = Mage::getStoreConfig(self::XML_PATH_PIXEL_ID);
        }

        return $pixelId;
    }

    /**
     * If Facebook Pixel is enable return product catalog id
     * @return mixed|null|int
     */
    public function getProductCatalogId()
    {
        $productCatalogId = null;

        if ($this->isEnabled()) {
            $productCatalogId = Mage::getStoreConfig(self::XML_PATH_PRODUCT_CATALOG_ID);
        }

        return $productCatalogId;
    }

    /**
     * Return true if plugin enabled
     * @return true|false
     */
    public function isEnabled()
    {
        return Mage::getStoreConfig(self::XML_PATH_ENABLE);
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
     * check if the current url is checkout
     *
     * @return bool
     */
    public function isCheckout()
    {
        $controller = Mage::app()->getRequest()->getControllerName();
        $action = Mage::app()->getRequest()->getActionName();
        $route = Mage::app()->getRequest()->getRouteName();

        $fullActionName = $route . '/' . $controller . '/' . $action;

        $checkoutUrls = array(
            'checkout/onepage/index',
            'firecheckout/index/index',
            'onestepcheckout/index/index',
        );

        $isCheckout = (in_array($fullActionName, $checkoutUrls)) ? true : false;
        return $isCheckout;
    }

}
