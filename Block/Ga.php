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

        // $result[] = "ga('create','{$this->jsQuoteEscape($accountId)}');";
        $result[] = "gtag('config', '{$this->jsQuoteEscape($accountId)}')";
        // $result[] = "ga('require', 'displayfeatures');";
        // $result[] = "ga('require', 'ec');";

        $result[] = $custom->getCustomEvents();
        $result[] = $this->_getProductDetailsCode();
        $result[] = $this->_getCartCode();
        $result[] = $this->_getCheckoutCode();
        $result[] = $this->_getPurchaseCode();

        // $result[] = "ga('send', 'pageview');";
        $result[] = "gtag('config', '{$this->jsQuoteEscape($accountId)}');";
        
        $category = Mage::registry('current_category');
        $this->getRequest()->getControllerName();

        if(Mage::getBlockSingleton('page/html_header')->getIsHomePage()) {

            $result[] = sprintf("
                var google_tag_params = {
                    ecomm_pagetype: \"home\"
                };");

            // $result[] = sprintf("
            //     var mage_data_layer = {
            //         page: \"home\"
            //     };");
            $result[] = sprintf("
                var mage_data_layer = {
                    page_title: \"home\"
                };");

        } else if ($this->getRequest()->getControllerName()=='category') {
            $result[] = sprintf("
                var google_tag_params = {
                    ecomm_pagetype: \"category\",
                    ecomm_category: \"%s\"
                };", $this->jsQuoteEscape($category->getName()));

            // $result[] = sprintf("
            //     var mage_data_layer = {
            //         page: \"category\",
            //         category: {
            //             name: \"%s\"
            //         }
            //     };", $this->jsQuoteEscape($category->getName()));
            $result[] = sprintf("
                var mage_data_layer = {
                    page_title: \"category\",
                    page_location: \"%s\",
                    page_path: \"%s\"
                };", $this->jsQuoteEscape($category->getName()), $this->jsQuoteEscape($category->getUrl()));
        }

        $result[] = "if ((typeof(mage_data_layer) != 'undefined') && (typeof(dataLayer) != 'undefined')) { dataLayer.push(mage_data_layer); }";

        return implode("\n", $result);
    }

    protected function _getCartCode()
    {
        $result = array();

        if (Mage::app()->getFrontController()->getRequest()->getControllerName() == "cart") {

            if (Mage::getModel('core/session')->getGaAddProductToCart() != null) {
                $result[] = $this->_getAddToCartCode();
            } else if (Mage::getModel('core/session')->getGaDeleteProductFromCart() != null) {
                $result[] = $this->_getDeleteFromCartCode();
            }

            $result[] = $this->_getDataLayerCartItems();
        }

        return implode("\n", $result);
    }

    protected function _getCheckoutCode()
    {
        $result = array();

        if (Mage::app()->getRequest()->getRouteName() == "onestepcheckout") {
            $result[] = $this->_getDataLayerCartItems('checkout');
        }

        return implode("\n", $result);
    }

    protected function _getDataLayerCartItems($page = 'cart') {
        $cart = Mage::getSingleton('checkout/cart');

        $result[] = "var mage_data_layer_products = [];";
        $productIds = [];

        if ($cart != null) {
            $cartItems = $cart->getQuote()->getAllVisibleItems();

            foreach ($cartItems as $item) {
                $productIds[] = $this->jsQuoteEscape($item->getProductId());

                // $result[] = sprintf("mage_data_layer_products.push({
                //     sku: \"%s\",
                //     name: \"%s\",
                //     category: \"\",
                //     price: %s,
                //     quantity: %s
                // });", $this->jsQuoteEscape($item->getProductId()),
                //     $this->jsQuoteEscape($item->getName()),
                //     number_format($item->getBasePrice(), 2, '.', ''),
                //     number_format($item->getQty(), 0, '', '')
                // );
                $result[] = sprintf("mage_data_layer_products.push({
                    item_id: \"%s\",
                    item_name: \"%s\",
                    item_category: \"\",
                    price: %s,
                    quantity: %s
                });", $this->jsQuoteEscape($item->getProductId()),
                    $this->jsQuoteEscape($item->getName()),
                    number_format($item->getBasePrice(), 2, '.', ''),
                    number_format($item->getQty(), 0, '', '')
                );
            }
            // $result[] = sprintf("
            //     var mage_data_layer = {
            //         page: \"$page\",
            //         cart: {
            //             skus: \"%s\",
            //             items: mage_data_layer_products,
            //             total: %s
            //         }
            //     };", $this->jsQuoteEscape(implode(',', $productIds)),
            //          number_format($cart->getQuote()->getBaseGrandTotal(), 2, '.', '')
            // );
            $result[] = sprintf("
                var mage_data_layer = {
                    page_title: \"cart\",
                    cart: {
                        skus: \"%s\",
                        items: mage_data_layer_products,
                        total: %s
                    }
                };", $this->jsQuoteEscape(implode(',', $productIds)),
                     number_format($cart->getQuote()->getBaseGrandTotal(), 2, '.', '')
            );
        }

        return implode("\n", $result);
    }

    protected function _getAddToCartCode()
    {
        $cart = Mage::getSingleton('checkout/cart');

        $result = array();
        $itemSession = Mage::getModel('core/session')->getGaAddProductToCart();

        $product = Mage::getModel('catalog/product')->load($itemSession->getId());

        // $result[] = sprintf("ga('ec:addProduct', { 'id': '%s', 'name': '%s', 'quantity': %s });",
        //     $this->jsQuoteEscape($product->getId()),
        //     $this->jsQuoteEscape($product->getName()),
        //     $itemSession->getQty()
        // );

        $result[] = sprintf("gtag('event', 'add_to_cart', { 'currency': '%s', 'value': '%s', 'items': [
            { 'item_id': '%s', 'item_name': '%s', 'quantity': '%s' }
        ] });",
            'BRL',
            number_format($cart->getQuote()->getBaseGrandTotal(), 2, '.', ''),
            $this->jsQuoteEscape($product->getId()),
            $this->jsQuoteEscape($product->getName()),
            $itemSession->getQty()
        );

        // $result[] = "ga('ec:setAction', 'add');";

        $productPrice = $this->getProductPrice($product);

        $result[] = sprintf("
            var google_tag_params = {
                ecomm_prodid: \"%s\",
                ecomm_pagetype: \"cart\",
                ecomm_totalvalue: %s
            };", $this->jsQuoteEscape($product->getId()), number_format($productPrice, 2, '.', ''));

        Mage::getModel('core/session')->unsGaAddProductToCart();

        return implode("\n", $result);
    }

    protected function _getDeleteFromCartCode()
    {
        $cart = Mage::getSingleton('checkout/cart');
        $result = array();
        $itemSession = Mage::getModel('core/session')->getGaDeleteProductFromCart();

        $product = Mage::getModel('catalog/product')->load($itemSession->getId());

        // $result[] = sprintf("ga('ec:addProduct', { 'id': '%s', 'name': '%s', 'quantity': %s });",
        //     $this->jsQuoteEscape($product->getId()),
        //     $this->jsQuoteEscape($product->getName()),
        //     $itemSession->getQty()
        // );

        $result[] = sprintf("gtag('event', 'remove_from_cart', { 'currency': '%s', 'value': '%s', 'items': [
            { 'item_id': '%s', 'item_name': '%s', 'quantity': '%s' }
        ] });",
            'BRL',
            number_format($cart->getQuote()->getBaseGrandTotal(), 2, '.', ''),
            $this->jsQuoteEscape($product->getId()),
            $this->jsQuoteEscape($product->getName()),
            $itemSession->getQty()
        );

        // $result[] = "ga('ec:setAction', 'remove');";

        Mage::getModel('core/session')->unsGaDeleteProductFromCart();

        return implode("\n", $result);
    }

    protected function _getProductDetailsCode()
    {
        $cart = Mage::getSingleton('checkout/cart');
        $result = array();

        if ((Mage::app()->getFrontController()->getRequest()->getControllerName() == "product") &&
            (Mage::registry('current_product') != null)) {

            $product = Mage::registry('current_product');

            if (Mage::registry('current_category') != null) {
                $category = Mage::registry('current_category');
                // $result[] = sprintf("ga('ec:addProduct', { 'id': '%s', 'name': '%s', 'category': '%s' });",
                //     $this->jsQuoteEscape($product->getId()),
                //     $this->jsQuoteEscape($product->getName()),
                //     $this->jsQuoteEscape($category->getName())
                // );
                $result[] = sprintf("gtag('event', 'view_item', { 'currency': '%s', 'value': '%s', 'items': [
                    { 'item_id': '%s', 'item_name': '%s', 'category': '%s' }
                ] });",
                    'BRL',
                    number_format($cart->getQuote()->getBaseGrandTotal(), 2, '.', ''),
                    $this->jsQuoteEscape($product->getId()),
                    $this->jsQuoteEscape($product->getName()),
                    $this->jsQuoteEscape($category->getName())
                );
            } else {
                // $result[] = sprintf("ga('ec:addProduct', { 'id': '%s', 'name': '%s' });",
                //     $this->jsQuoteEscape($product->getId()),
                //     $this->jsQuoteEscape($product->getName())
                // );
                $result[] = sprintf("gtag('event', 'view_item', { 'currency': '%s', 'value': '%s', 'items': [
                    { 'item_id': '%s', 'item_name': '%s' }
                ] });",
                    'BRL',
                    number_format($cart->getQuote()->getBaseGrandTotal(), 2, '.', ''),
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
        $cart = Mage::getSingleton('checkout/cart');
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
                // $result[] = sprintf("ga('ec:addProduct', { 'id': '%s', 'name': '%s', 'price': '%s', 'quantity': %s });",
                //     $this->jsQuoteEscape($item->getProductId()),
                //     $this->jsQuoteEscape($item->getName()),
                //     number_format($item->getBasePrice(), 2, '.', ''),
                //     number_format($item->getQtyOrdered(), 0, '', '')
                // );
                $result[] = sprintf("gtag('event', 'view_item', { 'currency': '%s', 'value': '%s', 'items': [
                    { 'item_id': '%s', 'item_name': '%s' }
                ] });",
                    'BRL',
                    number_format($order->getBaseGrandTotal(), 2, '.', ''),
                    $this->jsQuoteEscape($item->getId()),
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

            // $result[] = sprintf("ga('ec:setAction', 'purchase', { 'id': '%s', 'affiliation': '%s', 'revenue': '%s', 'tax': '%s', 'shipping': '%s', 'coupon': '%s' });",
            //     $order->getIncrementId(),
            //     $this->jsQuoteEscape(Mage::app()->getStore()->getFrontendName()),
            //     number_format($order->getBaseGrandTotal(), 2, '.', ''),
            //     number_format($order->getBaseTaxAmount(), 2, '.', ''),
            //     number_format($order->getBaseShippingAmount(), 2, '.', ''),
            //     $this->jsQuoteEscape($order->getCouponCode())
            // );

            $result[] = sprintf(
                "gtag('event', 'purchase', {
                    currency: \"%s\",
                    transaction_id: \"%s\",
                    value: \"%s\",
                    affiliation: \"%s\",
                    coupon: \"%s\",
                    shipping: \"%s\",
                    tax: \"%s\",
                })",
                "BRL",
                $order->getIncrementId(),
                number_format($order->getBaseGrandTotal(), 2, '.', ''),
                $this->jsQuoteEscape(Mage::app()->getStore()->getFrontendName()),
                $this->jsQuoteEscape($order->getCouponCode()),
                number_format($order->getBaseShippingAmount(), 2, '.', ''),
                number_format($order->getBaseTaxAmount(), 2, '.', '')
            );

            $productIds = implode(",", $productIds);
            $customerType = $this->getOrderCustomerType($order);

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
                        discount: \"%s\",
                        shipping: \"%s\",
                        customer: {
                            type: \"%s\"
                        },
                        items: mage_data_layer_products
                    }
                };", $this->jsQuoteEscape($order->getIncrementId()),
                    number_format($order->getBaseGrandTotal(), 2, '.', ''),
                    number_format($order->getDiscountAmount(), 2, '.', ''),
                    number_format($order->getBaseShippingAmount(), 2, '.', ''),
                    $customerType
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

    private function getOrderCustomerType($order) {
        try {
            $customerTypeAttribute = Mage::getModel('eav/config')->getAttribute('customer', 'tipopessoa');
            $customerType = $customerTypeAttribute->getSource()->getOptionText($order->getCustomerTipopessoa());
            return $customerType;
        } catch(Exception $ex) {
            return '';
        }   
    }
}
