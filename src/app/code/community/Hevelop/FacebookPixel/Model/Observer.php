<?php

/**
 * Class Hevelop_FacebookPixel_Model_Observer
 *
 * @category Magento_Module
 * @package  Hevelop_FacebookPixel
 * @author   Simone Marcato <simone@hevelop.com>
 * @license  http://opensource.org/licenses/agpl-3.0  GNU Affero General Public License v3 (AGPL-3.0)
 * @link     https://github.com/Hevelop/Facebookpixel
 */
class Hevelop_FacebookPixel_Model_Observer
{

    protected $fpcBlockPositions = array();

    protected $blockPromotions = null;

    /** @var Hevelop_FacebookPixel_Helper_Data */
    protected $helper;


    /**
     * Hevelop_FacebookPixel_Model_Observer constructor.
     */
    public function __construct()
    {
        $this->helper = Mage::helper('hevelop_facebookpixel');

    }//end __construct()


    /**
     * Add order information into GA block to render on checkout success pages
     * The method overwrites the FacebookPixel observer method by the
     * system.xml event settings
     *
     * Fired by the checkout_onepage_controller_success_action and
     * checkout_multishipping_controller_success_action events
     *
     * @param Varien_Event_Observer $observer Magento observer object
     *
     * @return $this
     */
    public function setFacebookPixelOnOrderSuccessPageView(
        Varien_Event_Observer $observer
    ) {
        if (Mage::helper('hevelop_facebookpixel')->isEnabled() === false) {
            return $this;
        }

        $orderIds = $observer->getEvent()->getOrderIds();
        if (empty($orderIds) === true || is_array($orderIds) === false) {
            return $this;
        }

        $action = Mage::app()->getFrontController()->getAction();
        $block  = $action->getLayout()->getBlock('facebookpixel');

        if ($block instanceof Mage_Core_Block_Template) {
            $block->setOrderIds($orderIds);
        }

        return $this;

    }//end setFacebookPixelOnOrderSuccessPageView()


    /**
     * Save previous cart quantities on add to cart action to find the
     * delta on load page
     * Fired by sales_quote_load_after event
     *
     * @param Varien_Event_Observer $observer Magento observer object
     *
     * @return $this
     */
    public function rememberCartQuantity(Varien_Event_Observer $observer)
    {
        if (Mage::helper('hevelop_facebookpixel')->isEnabled() === false) {
            return $this;
        }

        $quote       = $observer->getEvent()->getQuote();
        $session     = Mage::getSingleton('checkout/session');
        $productQtys = array();
        foreach ($quote->getAllItems() as $quoteItem) {
            $parentQty = 1;
            switch ($quoteItem->getProductType()) {
                case 'bundle':
                case 'configurable':
                    break;
                case 'grouped':
                    $option = $quoteItem->getOptionByCode('product_type');
                    $id = $option->getProductId();
                    $id = $id.'-'.$quoteItem->getProductId();
                    $productQtys[$id] = $quoteItem->getQty();
                    break;
                case 'giftcard':
                    $id = $quoteItem->getId().'-'.$quoteItem->getProductId();
                    $productQtys[$id] = $quoteItem->getQty();
                    break;
                default:
                    if ($quoteItem->getParentItem()) {
                        $parentQty = $quoteItem->getParentItem()->getQty();

                        $id  = $quoteItem->getId().'-';
                        $id .= $quoteItem->getParentItem()->getProductId().'-';
                        $id .= $quoteItem->getProductId();
                    } else {
                        $id = $quoteItem->getProductId();
                    }

                    $productQtys[$id] = ($quoteItem->getQty() * $parentQty);
            }//end switch
        }//end foreach

        $dataKeyBeforeAddToCart
            = Hevelop_FacebookPixel_Helper_Data::PRODUCT_QUANTITIES_BEFORE_ADDTOCART;

        if ($session->hasData(
                Hevelop_FacebookPixel_Helper_Data::PRODUCT_QUANTITIES_BEFORE_ADDTOCART
            ) === false
        ) {
            $session->setData(
                $dataKeyBeforeAddToCart,
                $productQtys
            );
        }

        return $this;

    }//end rememberCartQuantity()


    /**
     * Returns product info from a given item (quote or wishlist)
     *
     * @param mixed $item       item to get product from
     * @param array $lastValues reference to parent code
     *
     * @return mixed $product
     * @throws Mage_Core_Model_Store_Exception
     */
    protected function getProductFromItem($item, $lastValues)
    {
        $product          = false;
        $id               = $item->getProductId();
        $parentQty        = 1;
        $price            = $item->getProduct()->getPrice();
        $baseCurrencyCode = Mage::app()->getStore()->getBaseCurrencyCode();
        //$productCatalogId = $this->helper->getProductCatalogId();
        $attributeCode    = $this->helper->getAttributeCodeForCatalog();

        switch ($item->getProductType()) {
            case 'configurable':
            case 'bundle':
                break;
            case 'grouped':
                $id  = $item->getOptionByCode('product_type')->getProductId().'-';
                $id .= $item->getProductId();
            // no break;
            default:

                if ($attributeCode === false) {
                    $productId = $item->getProduct()->getId();
                } else {
                    $productId = $item->getProduct()->getData($attributeCode);
                }

                if ($item->getParentItem()) {
                    $parentQty = $item->getParentItem()->getQty();
                    $id        = $item->getId().'-';
                    $id       .= $item->getParentItem()->getProductId().'-';
                    $id       .= $item->getProductId();

                    if ($attributeCode === false) {
                        $productId = $item->getParentItem()->getProduct()->getId();
                    } else {
                        $productId = $item->getParentItem()->getProduct()->getData(
                            $attributeCode
                        );
                    }

                    $parentProductType = $item->getParentItem()->getProductType();
                    if ($parentProductType === 'configurable') {
                        $price = $item->getParentItem()->getProduct()->getPrice();
                    }
                }
                if ($item->getProductType() === 'giftcard') {
                    $price = $item->getProduct()->getFinalPrice();
                }

                $check  = array_key_exists($id, $lastValues) === true;
                $oldQty = ($check === true) ? $lastValues[$id] : 0;

                $finalQty = (($parentQty * $item->getQty()) - $oldQty);
                if ($finalQty !== 0) {



                    $product = array(
                        'id'                 => $productId,
                        'sku'                => $item->getSku(),
                        'name'               => $item->getName(),
                        'price'              => $price,
                        'qty'                => $finalQty,
                        'currency'           => $baseCurrencyCode
                        //'product_catalog_id' => $productCatalogId,
                    );
                }//end if
        }//end switch

        return $product;

    }//end getProductFromItem()


    /**
     * When shopping cart is cleaned the remembered quantities in a
     * session needs also to be deleted
     *
     * Fired by controller_action_postdispatch_checkout_cart_updatePost event
     *
     * @param Varien_Event_Observer $observer Magento observer object
     *
     * @return $this
     */
    public function clearSessionCartQuantity(Varien_Event_Observer $observer)
    {
        if (Mage::helper('hevelop_facebookpixel')->isEnabled() === false) {
            return $this;
        }

        $dataKeyBeforeAddToCart
            = Hevelop_FacebookPixel_Helper_Data::PRODUCT_QUANTITIES_BEFORE_ADDTOCART;

        $controllerAction = $observer->getEvent()->getControllerAction();
        $request          = $controllerAction->getRequest();
        $updateAction     = (string) $request->getParam('update_cart_action');
        if ($updateAction === 'empty_cart') {
            $session = Mage::getSingleton('checkout/session');
            $session->unsetData($dataKeyBeforeAddToCart);
        }

        return $this;

    }//end clearSessionCartQuantity()


    /**
     * Fired by sales_quote_product_add_after event
     *
     * @param Varien_Event_Observer $observer Magento observer object
     *
     * @return $this
     */
    public function setFacebookPixelOnCartAdd(Varien_Event_Observer $observer)
    {
        if (Mage::helper('hevelop_facebookpixel')->isEnabled() === false) {
            return $this;
        }

        $dataKeyBeforeAddToCart
            = Hevelop_FacebookPixel_Helper_Data::PRODUCT_QUANTITIES_BEFORE_ADDTOCART;

        $products = Mage::registry('facebookpixel_products_addtocart');
        if (is_array($products) === false) {
            $products = array();
        }

        $lastValues = array();
        $session    = Mage::getSingleton('checkout/session');
        if ($session->hasData($dataKeyBeforeAddToCart) === true) {
            $lastValues = $session->getData($dataKeyBeforeAddToCart);
        }

        $items = $observer->getEvent()->getItems();
        foreach ($items as $quoteItem) {
            $product = $this->getProductFromItem($quoteItem, $lastValues);
            if ($product !== false) {
                $products[] = $product;
            }
        }//end foreach

        Mage::unregister('facebookpixel_products_addtocart');
        Mage::register('facebookpixel_products_addtocart', $products);
        $session->unsetData($dataKeyBeforeAddToCart);

        return $this;

    }//end setFacebookPixelOnCartAdd()


    /**
     * Fired by sales_quote_remove_item event
     *
     * @param Varien_Event_Observer $observer Magento observer object
     *
     * @return $this
     */
    public function setFacebookPixelOnCartRemove(Varien_Event_Observer $observer)
    {
        if (Mage::helper('hevelop_facebookpixel')->isEnabled() === false) {
            return $this;
        }

        $products = Mage::registry('facebookpixel_products_to_remove');
        if (is_array($products) === false) {
            $products = array();
        }

        $quoteItem = $observer->getEvent()->getQuoteItem();
        $simples   = $quoteItem->getChildren();
        //$catalogId = Mage::helper('hevelop_facebookpixel')->getProductCatalogId();

        if (is_array($simples) === true
            && count($simples) > 0
            && $quoteItem->getProductType() !== 'configurable'
        ) {
            foreach ($simples as $item) {
                $products[] = array(
                    'sku'                => $item->getSku(),
                    'name'               => $item->getName(),
                    'price'              => $item->getPrice(),
                    'qty'                => $item->getQty(),
                    //'product_catalog_id' => $catalogId,
                );
            }
        } else {
            $price      = $quoteItem->getProduct()->getPrice();
            $products[] = array(
                'sku'                => $quoteItem->getSku(),
                'name'               => $quoteItem->getName(),
                'price'              => $price,
                'qty'                => $quoteItem->getQty(),
                //'product_catalog_id' => $catalogId,
            );
        }

        Mage::unregister('facebookpixel_products_to_remove');
        Mage::register('facebookpixel_products_to_remove', $products);

        return $this;

    }//end setFacebookPixelOnCartRemove()


    /**
     * Fired by wishlist_add_product event
     *
     * @param Varien_Event_Observer $observer Magento observer object
     *
     * @return $this
     */
    public function setFacebookPixelOnWishlistAdd(Varien_Event_Observer $observer)
    {
        if (Mage::helper('hevelop_facebookpixel')->isEnabled() === false) {
            return $this;
        }

        Mage::register('wishlist_add_product', $observer->getProduct());

        $products = Mage::registry('facebookpixel_products_addtowishlist');
        if (is_array($products) === false) {
            $products = array();
        }

        $dataKeyBeforeAddToWishlist
            = Hevelop_FacebookPixel_Helper_Data::PRODUCT_QUANTITIES_BEFORE_ADDTOWISHLIST;

        $lastValues = array();
        $session    = Mage::getSingleton('checkout/session');
        if ($session->hasData($dataKeyBeforeAddToWishlist) === true) {
            $lastValues = $session->getData($dataKeyBeforeAddToWishlist);
        }

        $items = $observer->getWishlist()->getItemCollection();
        foreach ($items as $item) {
            $product = $this->getProductFromItem($item, $lastValues);
            if ($product !== false) {
                $products[] = $product;
            }
        }//end foreach

        Mage::unregister('facebookpixel_products_addtowishlist');
        Mage::register('facebookpixel_products_addtowishlist', $products);
        $session->unsetData($dataKeyBeforeAddToWishlist);

        return $this;

    }//end setFacebookPixelOnWishlistAdd()


    /**
     * Send cookies after cart action
     *
     * @param Varien_Event_Observer $observer Magento observer object
     *
     * @return $this
     */
    public function sendCookieOnCartActionComplete(Varien_Event_Observer $observer)
    {
        if (Mage::helper('hevelop_facebookpixel')->isEnabled() === false) {
            return $this;
        }

        $dataKeyAddToCart      = Hevelop_FacebookPixel_Helper_Data::COOKIE_CART_ADD;
        $dataKeyRemoveFromCart = Hevelop_FacebookPixel_Helper_Data::COOKIE_CART_REMOVE;

        $productsToAdd = Mage::registry('facebookpixel_products_addtocart');

        if (empty($productsToAdd) === false) {
            Mage::app()->getCookie()->set(
                $dataKeyAddToCart,
                json_encode($productsToAdd),
                0,
                '/',
                null,
                null,
                false
            );
        }

        $productsToRemove = Mage::registry('facebookpixel_products_to_remove');
        if (empty($productsToRemove) === false) {
            Mage::app()->getCookie()->set(
                $dataKeyRemoveFromCart,
                rawurlencode(Mage::helper('core')->jsonEncode($productsToRemove)),
                0,
                '/',
                null,
                null,
                false
            );
        }

        return $this;

    }//end sendCookieOnCartActionComplete()


    /**
     * Send cookies after wishlist action
     *
     * @param Varien_Event_Observer $observer Magento observer object
     *
     * @return $this
     */
    public function sendCookieOnWishlistActionComplete(
        Varien_Event_Observer $observer
    ) {
        if (Mage::helper('hevelop_facebookpixel')->isEnabled() === false) {
            return $this;
        }

        $dataKeyAddToWishlist
            = Hevelop_FacebookPixel_Helper_Data::COOKIE_WISHLIST_ADD;

        $productsToAdd = Mage::registry('facebookpixel_products_addtowishlist');
        if (empty($productsToAdd) === false) {
            Mage::app()->getCookie()->set(
                $dataKeyAddToWishlist,
                rawurlencode(json_encode($productsToAdd)),
                0,
                '/',
                null,
                null,
                false
            );
        }

        return $this;

    }//end sendCookieOnWishlistActionComplete()


    /**
     * Fired by customer_register_success event
     *
     * @param Varien_Event_Observer $observer Magento observer object
     *
     * @return $this
     */
    public function setFacebookPixelOnCustomerRegisterSuccess(
        Varien_Event_Observer $observer
    ) {
        if (Mage::helper('hevelop_facebookpixel')->isEnabled() === false) {
            return $this;
        }

        $customer = $observer->getCustomer();
        Mage::unregister('facebookpixel_customer_registered');
        Mage::register('facebookpixel_customer_registered', $customer);

        return $this;

    }//end setFacebookPixelOnCustomerRegisterSuccess()


    /**
     * Send cookies after new customer registration
     *
     * @param Varien_Event_Observer $observer Magento observer object
     *
     * @return $this
     */
    public function sendCookieOnCustomerRegisterSuccess(
        Varien_Event_Observer $observer
    ) {
        if (Mage::helper('hevelop_facebookpixel')->isEnabled() === false) {
            return $this;
        }

        $customer = Mage::registry('facebookpixel_customer_registered');
        if ($customer instanceof Mage_Customer_Model_Customer) {
            Mage::app()->getCookie()->set(
                Hevelop_FacebookPixel_Helper_Data::COOKIE_CUSTOMER_REGISTER,
                rawurlencode(json_encode($customer->getId())),
                0,
                '/',
                null,
                null,
                false
            );
        }

        return $this;

    }//end sendCookieOnCustomerRegisterSuccess()


    /**
     * Excute on post dispatch event
     *
     * @param Varien_Event_Observer $observer Magento observer object
     *
     * @return $this
     */
    public function facebookPixelPostDispatch(Varien_Event_Observer $observer)
    {
        $this->sendCookieOnCartActionComplete($observer);
        $this->sendCookieOnWishlistActionComplete($observer);
        $this->sendCookieOnCustomerRegisterSuccess($observer);

        return $this;

    }//end facebookPixelPostDispatch()


}//end class