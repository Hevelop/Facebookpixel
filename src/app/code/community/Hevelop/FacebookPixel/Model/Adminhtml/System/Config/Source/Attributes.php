<?php

/**
 * Attributes.php
 *
 * @category Magento_Module
 * @package  Hevelop_FacebookPixel
 * @author   Simone Marcato <simone@hevelop.com>
 * @license  http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @link     https://github.com/Hevelop/Facebookpixel
 *
 */
class Hevelop_FacebookPixel_Model_Adminhtml_System_Config_Source_Attributes
 extends Mage_Eav_Model_Entity_Attribute_Source_Abstract
{


    /**
     * get all catalog attributes
     *
     * @return array
     */
    public function getAllOptions()
    {
        if (is_array($this->_options) === false) {
            $resourceKey = 'catalog/product_attribute_collection';
            $resource    = Mage::getResourceModel($resourceKey);
            $attributes  = $resource->getItems();
            $helper      = Mage::helper('hevelop_facebookpixel');

            $this->_options[]
                = array(
                   'label' => $helper->__('Default product ID'),
                   'value' => '',
                  );

            foreach ($attributes as $attribute) {
                $this->_options[]
                    = array(
                       'label' => $attribute->getFrontendLabel(),
                       'value' => $attribute->getAttributeCode(),
                      );
            }//end foreach
        }

        return $this->_options;

    }//end getAllOptions()


    /**
     * getAllOptions method
     *
     * @return array
     */
    public function toOptionArray()
    {
        return $this->getAllOptions();

    }//end toOptionArray()


}//end class