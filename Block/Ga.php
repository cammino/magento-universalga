<?php
class Cammino_Googleanalytics_Block_Ga extends Mage_GoogleAnalytics_Block_Ga
{

    protected function _getPageTrackingCode($accountId)
    {
        $pageName   = trim($this->getPageName());
        $optPageURL = '';

        if ($pageName && preg_match('/^\/.*/i', $pageName)) {
            $optPageURL = ", '{$this->jsQuoteEscape($pageName)}'";
        }

        $result[] = "ga('create','{$this->jsQuoteEscape($accountId)}', 'auto');";
        $result[] = "ga('require', 'displayfeatures');";
        $result[] = "ga('send', 'pageview'{$optPageURL});";
        $result[] = "ga('require', 'ec');";

        $result[] = $this->_getProductDetailsCode();
        $result[] = $this->_getPurchaseCode();
    
        return implode("\n", $result);
    }

    protected function _getProductDetailsCode()
    {
        var_dump(Mage::registry('current_product'));

        //$result[] = sprintf("ga('ec:addImpression', { 'id': 'P12345', 'name': 'Android Warhol T-Shirt', 'category': 'Apparel/T-Shirts', 'brand': 'Google', 'variant': 'Black', 'list': 'Related Products', 'position': 1, });");
        //$result[] = sprintf("ga('ec:addProduct', { 'id': 'P12345', 'name': 'Android Warhol T-Shirt', 'category': 'Apparel', 'brand': 'Google', 'variant': 'Black', 'position': 1, });");
        //$result[] = sprintf("ga('ec:setAction', 'detail');");

        return implode("\n", $result);
    }

    protected function _getPurchaseCode()
    {
        $orderIds = $this->getOrderIds();

        if (empty($orderIds) || !is_array($orderIds)) {
            return;
        }

        $collection = Mage::getResourceModel('sales/order_collection')
            ->addFieldToFilter('entity_id', array('in' => $orderIds));

        foreach ($collection as $order) {

            if ($order->getIsVirtual()) {
                $address = $order->getBillingAddress();
            } else {
                $address = $order->getShippingAddress();
            }

            foreach ($order->getAllVisibleItems() as $item) {
                $result[] = sprintf("ga('ec:addProduct', { 'id': '%s', 'name': '%s', 'price': '%s', 'quantity': %s });",
                    $this->jsQuoteEscape($item->getSku()),
                    $this->jsQuoteEscape($item->getName()),
                    number_format($item->getBasePrice(), 2, '.', ''),
                    number_format($item->getQtyOrdered(), 0, '', '')
                );
            }

            $result[] = sprintf("ga('ec:setAction', 'purchase', { 'id': '%s', 'affiliation': '%s', 'revenue': '%s', 'tax': '%s', 'shipping': '%s', 'coupon': '%s' });",
                $order->getIncrementId(),
                $this->jsQuoteEscape(Mage::app()->getStore()->getFrontendName()),
                number_format($order->getBaseGrandTotal(), 2, '.', ''),
                number_format($order->getBaseTaxAmount(), 2, '.', ''),
                number_format($order->getBaseShippingAmount(), 2, '.', ''),
                $this->jsQuoteEscape($order->getCouponCode())
            );
        }
        return implode("\n", $result);
    }
}
