<?php
class Cammino_Googleanalytics_Block_Ga extends Mage_GoogleAnalytics_Block_Ga
{

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
            return $block->_getPageTrackingCode($accountId);
        } else {
            $block = Mage::getBlockSingleton('googleanalytics/ga_ua');
            return $block->_getPageTrackingCode($accountId);
        }
    }

}
