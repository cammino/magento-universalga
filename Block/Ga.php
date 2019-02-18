<?php
// home, product, cart, purchase
class Cammino_Googleanalytics_Block_Ga extends Mage_GoogleAnalytics_Block_Ga
{

    protected function _getPageTrackingCode($accountId)
    {
        $custom = new Cammino_Googleanalytics_Model_Custom();

        $pageName   = trim($this->getPageName());
        $optPageURL = '';

        if ($pageName && preg_match('/^\/.*/i', $pageName)) {
            $optPageURL = ", '{$this->jsQuoteEscape($pageName)}'";
        }

        $result[] = "ga('create','{$this->jsQuoteEscape($accountId)}');";
        $result[] = "ga('require', 'displayfeatures');";
        $result[] = "ga('require', 'ec');";

        $result[] = $custom->getCustomEvents();
        $result[] = $this->_getProductDetailsCode();
        $result[] = $this->_getCartCode();
        $result[] = $this->_getPurchaseCode();

        $result[] = "ga('send', 'pageview');";
        
        $category = Mage::registry('current_category');
        $this->getRequest()->getControllerName();

        if(Mage::getBlockSingleton('page/html_header')->getIsHomePage()) {

            $result[] = sprintf("
                var google_tag_params = {
                    ecomm_pagetype: \"home\"
                };");

            $result[] = sprintf("
                var mage_data_layer = {
                    page: \"home\"
                };");

        } else if ($this->getRequest()->getControllerName()=='category') {

            $result[] = sprintf("
                var google_tag_params = {
                    ecomm_pagetype: \"category\",
                    ecomm_category: \"%s\"
                };", $this->jsQuoteEscape($category->getName()));

            $result[] = sprintf("
                var mage_data_layer = {
                    page: \"category\",
                    category: {
                        name: \"%s\"
                    }
                };", $this->jsQuoteEscape($category->getName()));

        }

        return implode("\n", $result);
    }

    protected function _getCartCode()
    {
        $result = array();

        if (Mage::app()->getFrontController()->getRequest()->getControllerName() == "cart") {

            if (Mage::getModel('core/session')->getGaAddProductToCart() != null) {
                return $this->_getAddToCartCode();
            } else if (Mage::getModel('core/session')->getGaDeleteProductFromCart() != null) {
                return $this->_getDeleteFromCartCode();
            }

        } else {
            return "";
        }
    }

    protected function _getAddToCartCode()
    {
        $result = array();
        $itemSession = Mage::getModel('core/session')->getGaAddProductToCart();

        $product = Mage::getModel('catalog/product')->load($itemSession->getId());

        $result[] = sprintf("ga('ec:addProduct', { 'id': '%s', 'name': '%s', 'quantity': %s });",
            $this->jsQuoteEscape($product->getId()),
            $this->jsQuoteEscape($product->getName()),
            $itemSession->getQty()
        );

        $result[] = "ga('ec:setAction', 'add');";

        $productPrice = $this->getProductPrice($product);

        $result[] = sprintf("
            var google_tag_params = {
                ecomm_prodid: \"%s\",
                ecomm_pagetype: \"cart\",
                ecomm_totalvalue: %s
            };", $this->jsQuoteEscape($product->getId()), number_format($productPrice, 2, '.', ''));

        $result[] = sprintf("
            var mage_data_layer = {
                page: \"cart\",
                cart: {
                    skus: \"%s\"
                }
            };", $this->jsQuoteEscape($product->getId()));

        Mage::getModel('core/session')->unsGaAddProductToCart();

        return implode("\n", $result);
    }

    protected function _getDeleteFromCartCode()
    {
        $result = array();
        $itemSession = Mage::getModel('core/session')->getGaDeleteProductFromCart();

        $product = Mage::getModel('catalog/product')->load($itemSession->getId());

        $result[] = sprintf("ga('ec:addProduct', { 'id': '%s', 'name': '%s', 'quantity': %s });",
            $this->jsQuoteEscape($product->getId()),
            $this->jsQuoteEscape($product->getName()),
            $itemSession->getQty()
        );

        $result[] = "ga('ec:setAction', 'remove');";

        Mage::getModel('core/session')->unsGaDeleteProductFromCart();

        return implode("\n", $result);
    }

    protected function _getProductDetailsCode()
    {
        $result = array();

        if ((Mage::app()->getFrontController()->getRequest()->getControllerName() == "product") &&
            (Mage::registry('current_product') != null)) {

            $product = Mage::registry('current_product');

            if (Mage::registry('current_category') != null) {
                $category = Mage::registry('current_category');
                $result[] = sprintf("ga('ec:addProduct', { 'id': '%s', 'name': '%s', 'category': '%s' });",
                    $this->jsQuoteEscape($product->getId()),
                    $this->jsQuoteEscape($product->getName()),
                    $this->jsQuoteEscape($category->getName())
                );
            } else {
                $result[] = sprintf("ga('ec:addProduct', { 'id': '%s', 'name': '%s' });",
                    $this->jsQuoteEscape($product->getId()),
                    $this->jsQuoteEscape($product->getName())
                );
            }

            $productPrice = $this->getProductPrice($product);

            $result[] = sprintf("ga('ec:setAction', 'detail');");
            $result[] = sprintf("
                var google_tag_params = {
                    ecomm_prodid: \"%s\",
                    ecomm_pagetype: \"product\",
                    ecomm_totalvalue: %s
                };", $this->jsQuoteEscape($product->getId()), number_format($productPrice, 2, '.', ''));

            $result[] = sprintf("
                var mage_data_layer = {
                    page: \"product\",
                    product: {
                        sku: \"%s\",
                        name: \"%s\",
                        price: %s,
                        available: true
                    }
                };", $this->jsQuoteEscape($product->getId()),
                    $this->jsQuoteEscape($product->getName()),
                    number_format($productPrice, 2, '.', '')
                );

        }

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

            $productIds = array();

            $result[] = "var mage_data_layer_products = [];";

            foreach ($order->getAllVisibleItems() as $item) {
                $result[] = sprintf("ga('ec:addProduct', { 'id': '%s', 'name': '%s', 'price': '%s', 'quantity': %s });",
                    $this->jsQuoteEscape($item->getProductId()),
                    $this->jsQuoteEscape($item->getName()),
                    number_format($item->getBasePrice(), 2, '.', ''),
                    number_format($item->getQtyOrdered(), 0, '', '')
                );
                $productIds[] = $this->jsQuoteEscape($item->getProductId());

                $result[] = sprintf("mage_data_layer_products.push({
                    sku: \"%s\",
                    name: \"%s\",
                    category: \"\",
                    price: %s,
                    quantity: %s
                });", $this->jsQuoteEscape($item->getProductId()),
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

            $productIds = implode(",", $productIds);

            $result[] = sprintf("
                var google_tag_params = {
                    ecomm_prodid: [%s],
                    ecomm_pagetype: \"purchase\",
                    ecomm_totalvalue: %s
                };", $productIds, number_format($order->getBaseGrandTotal(), 2, '.', ''));

            $result[] = sprintf("
                var mage_data_layer = {
                    page: \"conversion\",
                    conversion: {
                        transactionId: \"%s\",
                        amount: \"%s\",
                        currency: \"BRL\",
                        paymentType: \"\",
                        items: mage_data_layer_products
                    }
                };", $this->jsQuoteEscape($order->getIncrementId()),
                    number_format($order->getBaseGrandTotal(), 2, '.', '')
                );

        }
        return implode("\n", $result);
    }

    protected function getProductPrice($product) {

        $productType = $product->getTypeId() != NULL ? $product->getTypeId() : $product->product_type;

        if ($productType == "simple" || $productType == "downloadable"){
            return $product->getFinalPrice() ? $product->getFinalPrice() : 0;
        } else if ($productType == "grouped") {
            return $this->getGroupedProductPrice($product);
        } else if ($productType == "configurable") {
            return $this->getConfigurableProductPrice($product);
        } else {
            return 0;
        }
    }

    private function getConfigurableProductPrice($product) {
        $conf = Mage::getModel('catalog/product_type_configurable')->setProduct($product);
        $simple_collection = $conf->getUsedProductCollection()->addAttributeToSelect('*')->addFilterByRequiredOptions();
        $minVal = 9999999;
        
        foreach($simple_collection as $simple_product):
            $price = $simple_product->getPrice();
            if($price < $minVal && $price > 0):
                $minVal = $price;
            endif;
        endforeach;

        return $minVal != 9999999 ? $minVal : 0;
    }    

    private function getGroupedProductPrice($product) {
        $associated = $this->getAssociatedProducts($product);
        $prices = array();
        $minimal = 0;

        foreach($associated as $item) {
            if ($item->getFinalPrice() > 0) {
                array_push($prices, $item->getFinalPrice());
            }
        }

        rsort($prices, SORT_NUMERIC);

        if (count($prices) > 0) {
            $minimal = end($prices);    
        }

        return $minimal;
    }

    protected function getAssociatedProducts($product) {
        $collection = $product->getTypeInstance(true)->getAssociatedProductCollection($product)
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('status', 1);
        return $collection;
    }
}
