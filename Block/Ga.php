<?php
class Cammino_Googleanalytics_Block_Ga extends Mage_GoogleAnalytics_Block_Ga
{

    public $_orderIds;

    public function _setOrderIds($orderIds) {
        $this->_orderIds = $orderIds;
    }

    public function _getOrderIds() {
        return $this->_orderIds;
    }
    public function getType() {
        return Mage::getStoreConfig(Mage_GoogleAnalytics_Helper_Data::XML_PATH_TYPE);
    }

    public function getAccountId() {
        return Mage::getStoreConfig(Mage_GoogleAnalytics_Helper_Data::XML_PATH_ACCOUNT);
    }

    public function _getPageTrackingCode() {
        $accountId = $this->getAccountId();
        if ($this->getType() == 'G4') {
            $block = Mage::getBlockSingleton('googleanalytics/ga_g4');
            $block->_setOrderIds($this->getOrderIds());
            return $block->_getPageTrackingCode($accountId);
        } else {
            $block = Mage::getBlockSingleton('googleanalytics/ga_ua');
            $block->_setOrderIds($this->getOrderIds());
            return $block->_getPageTrackingCode($accountId);
        }
    }

    public function getProductCategory($item) {
        $product = Mage::getModel('catalog/product')->load($item->getProductId());
        $categoryIds = $product->getCategoryIds();
        $mainCategory = 2;
        foreach($categoryIds as $id) {
            if ((intval($id) != 1) && (intval($id) != 2)) {
                $mainCategory = $id;
                break;
            }
        }
        $category = Mage::getModel('catalog/category')->load($mainCategory);
        if ($category) {
            return $category->getName();    
        } else {
            return '';
        }
    }

}
