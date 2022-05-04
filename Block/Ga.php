<?php
class Cammino_Googleanalytics_Block_Ga extends Mage_GoogleAnalytics_Block_Ga
{

    public $_orderIds;

    public function setOrderIds($orderIds) {
        $this->_orderIds = $orderIds;
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
            $block->setOrderIds($this->getOrderIds());
            return $block->_getPageTrackingCode($accountId);
        } else {
            $block = Mage::getBlockSingleton('googleanalytics/ga_ua');
            $block->setOrderIds($this->getOrderIds());
            return $block->_getPageTrackingCode($accountId);
        }
    }

}
