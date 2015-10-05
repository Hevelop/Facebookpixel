<?php

class Hevelop_FacebookPixel_Helper_Data extends Mage_Core_Helper_Abstract
{

    const COOKIE_ADD = 'facebookpixel_add';
    const COOKIE_REMOVE = 'facebookpixel_remove';

    const XML_PATH_ENABLE = 'hevelopfacebookpixel/general/enabled';
    const XML_PATH_PIXEL_ID = 'hevelopfacebookpixel/general/pixelid';

    const PRODUCT_QUANTITIES_BEFORE_ADDTOCART = 'prev_product_qty';

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
        } else if(Mage::registry('current_category')){
            $category = Mage::registry('current_category');
        }
        return $category;
    }
}
