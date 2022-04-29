<?php
class Cammino_Googleanalytics_Block_Ga extends Mage_GoogleAnalytics_Block_Ga
{

    public function getVersion() {

    }

    public function getAccountId() {
        return Mage::getStoreConfig(Mage_GoogleAnalytics_Helper_Data::XML_PATH_ACCOUNT);
    }

    public function _getPageTrackingCode() {
        if ($this->getVersion() == 'G4') {
            $block = Mage::getBlockSingleton('googleanalytics/ga_g4');
            return $gModel->_getPageTrackingCode();
        } else {
            $block = Mage::getBlockSingleton('googleanalytics/ga_ua');
            return $gModel->_getPageTrackingCode();
        }
    }

}
