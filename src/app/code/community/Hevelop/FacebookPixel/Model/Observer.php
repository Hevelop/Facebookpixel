<?php

class Hevelop_FacebookPixel_Model_Observer
{
    protected $_fpcBlockPositions = array();

    /** @var null hevelop_facebookpixel_Block_List_Json */
    protected $_blockPromotions = null;

    /**
     * Add order information into GA block to render on checkout success pages
     * The method overwrites the FacebookPixel observer method by the system.xml event settings
     *
     * Fired by the checkout_onepage_controller_success_action and
     * checkout_multishipping_controller_success_action events
     *
     * @param Varien_Event_Observer $observer
     * @return $this
     */
    public function setFacebookPixelOnOrderSuccessPageView(Varien_Event_Observer $observer)
    {
        if (!Mage::helper('hevelop_facebookpixel')->isEnabled()) {
            return $this;
        }

        $orderIds = $observer->getEvent()->getOrderIds();
        if (empty($orderIds) || !is_array($orderIds)) {
            return $this;
        }
        /** @var hevelop_facebookpixel_Block_Ga $block */
        $block = Mage::app()->getFrontController()->getAction()->getLayout()->getBlock('facebookpixel');
        if ($block) {
            $block->setOrderIds($orderIds);
        }
        return $this;
    }


    /**
     * Save previous cart quantities on add to cart action to find the delta on load page
     * Fired by sales_quote_load_after event
     *
     * @param Varien_Event_Observer $observer
     * @return $this
     */
    public function rememberCartQuantity(Varien_Event_Observer $observer)
    {

        if (!Mage::helper('hevelop_facebookpixel')->isEnabled()) {
            return $this;
        }

        /** @var Mage_Sales_Model_Quote $quote */
        $quote = $observer->getEvent()->getQuote();
        $session = Mage::getSingleton('checkout/session');
        $productQtys = array();
        /** @var Mage_Sales_Model_Quote_Item $quoteItem */
        foreach ($quote->getAllItems() as $quoteItem) {
            $parentQty = 1;
            switch ($quoteItem->getProductType()) {
                case 'bundle':
                case 'configurable':
                    break;
                case 'grouped':
                    $id = $quoteItem->getOptionByCode('product_type')->getProductId()
                        . '-' . $quoteItem->getProductId();
                    $productQtys[$id] = $quoteItem->getQty();
                    break;
                case 'giftcard':
                    $id = $quoteItem->getId() . '-' . $quoteItem->getProductId();
                    $productQtys[$id] = $quoteItem->getQty();
                    break;
                default:
                    if ($quoteItem->getParentItem()) {
                        $parentQty = $quoteItem->getParentItem()->getQty();
                        $id = $quoteItem->getId() . '-' .
                            $quoteItem->getParentItem()->getProductId() . '-' .
                            $quoteItem->getProductId();
                    } else {
                        $id = $quoteItem->getProductId();
                    }
                    $productQtys[$id] = $quoteItem->getQty() * $parentQty;
            }
        }
        /** prevent from overwriting on page load */
        if (!$session->hasData(
            Hevelop_FacebookPixel_Helper_Data::PRODUCT_QUANTITIES_BEFORE_ADDTOCART
        )
        ) {
            $session->setData(
                Hevelop_FacebookPixel_Helper_Data::PRODUCT_QUANTITIES_BEFORE_ADDTOCART,
                $productQtys
            );
        }
        return $this;
    }

    /**
     * When shopping cart is cleaned the remembered quantities in a session needs also to be deleted
     *
     * Fired by controller_action_postdispatch_checkout_cart_updatePost event
     *
     * @param Varien_Event_Observer $observer
     * @return $this
     */
    public function clearSessionCartQuantity(Varien_Event_Observer $observer)
    {

        if (!Mage::helper('hevelop_facebookpixel')->isEnabled()) {
            return $this;
        }
        /** @var Mage_Core_Controller_Varien_Action $controllerAction */
        $controllerAction = $observer->getEvent()->getControllerAction();
        $updateAction = (string)$controllerAction->getRequest()->getParam('update_cart_action');
        if ($updateAction == 'empty_cart') {
            $session = Mage::getSingleton('checkout/session');
            $session->unsetData(Hevelop_FacebookPixel_Helper_Data::PRODUCT_QUANTITIES_BEFORE_ADDTOCART);
        }
        return $this;
    }

    /**
     * Fired by sales_quote_product_add_after event
     *
     * @param Varien_Event_Observer $observer
     * @return $this
     */
    public function setFacebookPixelOnCartAdd(Varien_Event_Observer $observer)
    {
        if (!Mage::helper('hevelop_facebookpixel')->isEnabled()) {
            return $this;
        }

        $products = Mage::registry('facebookpixel_products_addtocart');
        if (!$products) {
            $products = array();
        }
        $lastValues = array();
        $session = Mage::getSingleton('checkout/session');
        if ($session->hasData(
            Hevelop_FacebookPixel_Helper_Data::PRODUCT_QUANTITIES_BEFORE_ADDTOCART
        )
        ) {
            $lastValues = $session->getData(
                Hevelop_FacebookPixel_Helper_Data::PRODUCT_QUANTITIES_BEFORE_ADDTOCART
            );
        }

        $items = $observer->getEvent()->getItems();
        /** @var Mage_Sales_Model_Quote_Item $quoteItem */
        foreach ($items as $quoteItem) {
            $id = $quoteItem->getProductId();
            $parentQty = 1;
            $price = $quoteItem->getProduct()->getPrice();
            switch ($quoteItem->getProductType()) {
                case 'configurable':
                case 'bundle':
                    break;
                case 'grouped':
                    $id = $quoteItem->getOptionByCode('product_type')->getProductId() . '-' .
                        $quoteItem->getProductId();
                // no break;
                default:
                    if ($quoteItem->getParentItem()) {
                        $parentQty = $quoteItem->getParentItem()->getQty();
                        $id = $quoteItem->getId() . '-' .
                            $quoteItem->getParentItem()->getProductId() . '-' .
                            $quoteItem->getProductId();

                        if ($quoteItem->getParentItem()->getProductType() == 'configurable') {
                            $price = $quoteItem->getParentItem()->getProduct()->getPrice();
                        }
                    }
                    if ($quoteItem->getProductType() == 'giftcard') {
                        $price = $quoteItem->getProduct()->getFinalPrice();
                    }

                    $oldQty = (array_key_exists($id, $lastValues)) ? $lastValues[$id] : 0;
                    $finalQty = ($parentQty * $quoteItem->getQty()) - $oldQty;
                    if ($finalQty != 0) {
                        $products[] = array(
                            'id' => $quoteItem->getProductId(),
                            'sku' => $quoteItem->getSku(),
                            'name' => $quoteItem->getName(),
                            'price' => $price,
                            'qty' => $finalQty,
                            'currency' => Mage::app()->getStore()->getBaseCurrencyCode(),
                            'product_catalog_id' => Mage::helper('hevelop_facebookpixel')->getProductCatalogId(),
                        );
                    }
            }
        }
        Mage::unregister('facebookpixel_products_addtocart');
        Mage::register('facebookpixel_products_addtocart', $products);
        $session->unsetData(Hevelop_FacebookPixel_Helper_Data::PRODUCT_QUANTITIES_BEFORE_ADDTOCART);
        return $this;
    }

    /**
     * Fired by sales_quote_remove_item event
     *
     * @param Varien_Event_Observer $observer
     * @return $this
     */
    public function setFacebookPixelOnCartRemove(Varien_Event_Observer $observer)
    {
        if (!Mage::helper('hevelop_facebookpixel')->isEnabled()) {
            return $this;
        }
        $products = Mage::registry('facebookpixel_products_to_remove');
        if (!$products) {
            $products = array();
        }
        /** @var Mage_Sales_Model_Quote_Item $quoteItem */
        $quoteItem = $observer->getEvent()->getQuoteItem();
        if ($simples = $quoteItem->getChildren() and $quoteItem->getProductType() != 'configurable') {
            foreach ($simples as $item) {
                $products[] = array(
                    'sku' => $item->getSku(),
                    'name' => $item->getName(),
                    'price' => $item->getPrice(),
                    'qty' => $item->getQty(),
                    'product_catalog_id' => Mage::helper('hevelop_facebookpixel')->getProductCatalogId(),
                );
            }
        } else {
            $products[] = array(
                'sku' => $quoteItem->getSku(),
                'name' => $quoteItem->getName(),
                'price' => $quoteItem->getProduct()->getPrice(),
                'qty' => $quoteItem->getQty(),
                'product_catalog_id' => Mage::helper('hevelop_facebookpixel')->getProductCatalogId(),
            );
        }
        Mage::unregister('facebookpixel_products_to_remove');
        Mage::register('facebookpixel_products_to_remove', $products);

        return $this;
    }

    /**
     * Send cookies after cart action
     *
     * @param Varien_Event_Observer $observer
     * @return $this
     */
    public function sendCookieOnCartActionComplete(Varien_Event_Observer $observer)
    {
        if (!Mage::helper('hevelop_facebookpixel')->isEnabled()) {
            return $this;
        }

        $productsToAdd = Mage::registry('facebookpixel_products_addtocart');
        if (!empty($productsToAdd)) {
            Mage::app()->getCookie()->set(Hevelop_FacebookPixel_Helper_Data::COOKIE_ADD,
                rawurlencode(json_encode($productsToAdd)), 0, '/', null, null, false);
        }
        $productsToRemove = Mage::registry('facebookpixel_products_to_remove');
        if (!empty($productsToRemove)) {
            Mage::app()->getCookie()->set(
                Hevelop_FacebookPixel_Helper_Data::COOKIE_REMOVE,
                rawurlencode(Mage::helper('core')->jsonEncode($productsToRemove)), 0, '/', null, null, false
            );
        }
        return $this;
    }


}
