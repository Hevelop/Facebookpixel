<?php

/**
 * Data.php
 *
 * @category Magento_Module
 * @package  Hevelop_FacebookPixel
 * @author   Simone Marcato <simone@hevelop.com>
 * @license  http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @link     https://github.com/Hevelop/Facebookpixel
 *
 */
class Hevelop_FacebookPixel_Helper_Data extends
 Mage_Core_Helper_Abstract
{

    const COOKIE_CART_ADD          = 'facebookpixel_cart_add';
    const COOKIE_CART_REMOVE       = 'facebookpixel_cart_remove';
    const COOKIE_WISHLIST_ADD      = 'facebookpixel_wishlist_add';
    const COOKIE_CUSTOMER_REGISTER = 'facebookpixel_customer_register';

    const XML_PATH_ENABLE   = 'hevelopfacebookpixel/general/enabled';
    const XML_PATH_PIXEL_ID = 'hevelopfacebookpixel/general/pixelid';
    const XML_PATH_ATTRIBUTE_CODE
        = 'hevelopfacebookpixel/general/catalog_id_attribute_code';
    const XML_PATH_PRODUCT_CATALOG_ID
        = 'hevelopfacebookpixel/general/product_catalog_id';

    const PRODUCT_QUANTITIES_BEFORE_ADDTOCART     = 'prev_product_qty';
    const PRODUCT_QUANTITIES_BEFORE_ADDTOWISHLIST = 'wishlist_prev_product_qty';


    /**
     * If Facebook Pixel is enable return pixel id
     *
     * @return mixed|null|int
     */
    public function getPixelId()
    {
        $pixelId = null;

        if ($this->isEnabled() === true) {
            $pixelId = Mage::getStoreConfig(self::XML_PATH_PIXEL_ID);
        }

        return $pixelId;

    }//end getPixelId()


    /**
     * If Facebook Pixel is enable return product catalog id
     *
     * @return mixed|null|int
     */
    public function getProductCatalogId()
    {
        $productCatalogId = null;

        if ($this->isEnabled() === true) {
            $productCatalogId
                = Mage::getStoreConfig(self::XML_PATH_PRODUCT_CATALOG_ID);
        }

        return $productCatalogId;

    }//end getProductCatalogId()


    /**
     * checks if plugin is enabled
     *
     * @return true|false
     */
    public function isEnabled()
    {
        return (bool) Mage::getStoreConfig(self::XML_PATH_ENABLE);

    }//end isEnabled()


    /**
     * Retrieves a current category
     *
     * @return Mage_Catalog_Model_Category
     */
    public function getCurrentCategory()
    {
        $category   = null;
        $isCategory = (bool) (Mage::registry('current_category')
            instanceof Mage_Catalog_Model_Category);
        $isLayer    = (bool) (Mage::getSingleton('catalog/layer')
            instanceof Mage_Catalog_Model_Layer);

        if ($isLayer === true) {
            $category = Mage::getSingleton('catalog/layer')->getCurrentCategory();
        } else if ($isCategory === true) {
            $category = Mage::registry('current_category');
        }

        return $category;

    }//end getCurrentCategory()


    /**
     * check if the current url is checkout
     *
     * @return bool
     */
    public function isCheckout()
    {
        $controller = Mage::app()->getRequest()->getControllerName();
        $action     = Mage::app()->getRequest()->getActionName();
        $route      = Mage::app()->getRequest()->getRouteName();

        $fullActionName = $route.'/'.$controller.'/'.$action;

        $checkoutUrls = array(
                         'checkout/onepage/index',
                         'firecheckout/index/index',
                         'onestepcheckout/index/index',
                        );

        $isCheckout = in_array(
            $fullActionName,
            $checkoutUrls,
            null
        );
        return $isCheckout;

    }//end isCheckout()


    /**
     * get configured attribute code to use as catalog product id
     *
     * @return string
     */
    public function getAttributeCodeForCatalog()
    {
        $attributeCode = Mage::getStoreConfig(
            self::XML_PATH_ATTRIBUTE_CODE
        );

        if (empty($attributeCode) === true) {
            $attributeCode = false;
        }

        return $attributeCode;

    }//end getAttributeCodeForCatalog()


}//end class
