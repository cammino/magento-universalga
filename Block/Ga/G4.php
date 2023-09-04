<?php
class Cammino_Googleanalytics_Block_Ga_G4 extends Cammino_Googleanalytics_Block_Ga
{

    public function _getPageTrackingCode($accountId)
    {
        $custom = new Cammino_Googleanalytics_Model_Custom();

        $pageName   = trim($this->getPageName());
        $optPageURL = '';

        if ($pageName && preg_match('/^\/.*/i', $pageName)) {
            $optPageURL = ", '{$this->jsQuoteEscape($pageName)}'";
        }

        $result[] = "gtag('config', '{$this->jsQuoteEscape($accountId)}');";

        $result[] = $custom->getCustomEvents();
        $result[] = $this->_getProductDetailsCode();
        $result[] = $this->_getCartCode();
        $result[] = $this->_getCheckoutCode();
        $result[] = $this->_getPurchaseCode();
        
        $category = Mage::registry('current_category');
        $this->getRequest()->getControllerName();

        if(Mage::getBlockSingleton('page/html_header')->getIsHomePage()) {

            $result[] = sprintf("
                var google_tag_params = {
                    ecomm_pagetype: \"home\"
                };");

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

    public function _getCartCode()
    {
        $result = array();

        if ((Mage::app()->getFrontController()->getRequest()->getControllerName() == "cart") ||
            (strrpos(Mage::helper('core/url')->getCurrentUrl(), 'amcart/ajax') !== false)) {

            if (Mage::getModel('core/session')->getGaAddProductToCart() != null) {
                $result[] = $this->_getAddToCartCode();
            } else if (Mage::getModel('core/session')->getGaDeleteProductFromCart() != null) {
                $result[] = $this->_getDeleteFromCartCode();
            }

            $result[] = $this->_getDataLayerCartItems();
        }

        return implode("\n", $result);
    }

    public function _getCheckoutCode()
    {
        $result = array();

        if (Mage::app()->getRequest()->getRouteName() == "onestepcheckout") {
            $result[] = $this->_getDataLayerCartItems('checkout');
        }

        return implode("\n", $result);
    }

    public function _getDataLayerCartItems($page = 'cart') {
        $cart = Mage::getSingleton('checkout/cart');

        $result[] = "var mage_data_layer_products = [];";
        $productIds = [];

        if ($cart != null) {
            $cartItems = $cart->getQuote()->getAllVisibleItems();

            foreach ($cartItems as $item) {
                $dataLayerSku = Mage::getStoreConfig('google/analytics/googleanalyticssku');
                $productId = ($dataLayerSku ? $item->getSku() : $item->getProductId());
                $productIds[] = $this->jsQuoteEscape($productId);

                $result[] = sprintf("mage_data_layer_products.push({
                    item_id: \"%s\",
                    item_name: \"%s\",
                    item_category: \"\",
                    price: %s,
                    currency: \"BRL\",
                    quantity: %s
                });", $this->jsQuoteEscape($productId),
                    $this->jsQuoteEscape($item->getName()),
                    number_format($item->getBasePrice(), 2, '.', ''),
                    number_format($item->getQty(), 0, '', '')
                );
            }

            if ($page == 'cart') {
                $result[] = sprintf("gtag('event', 'view_cart', { 'currency': '%s', 'value': %s, 'items': mage_data_layer_products });",
                    'BRL',
                    number_format($cart->getQuote()->getBaseGrandTotal(), 2, '.', '')
                );
            } else if ($page == 'checkout') {
                $result[] = sprintf("gtag('event', 'begin_checkout', { 'currency': '%s', 'value': %s, 'items': mage_data_layer_products });",
                    'BRL',
                    number_format($cart->getQuote()->getBaseGrandTotal(), 2, '.', '')
                );
            }

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

    public function _getAddToCartCode()
    {
        $cart = Mage::getSingleton('checkout/cart');

        $result = array();
        $itemSession = Mage::getModel('core/session')->getGaAddProductToCart();

        $product = Mage::getModel('catalog/product')->load($itemSession->getId());

        $result[] = sprintf("gtag('event', 'add_to_cart', { 'currency': '%s', 'value': %s, 'items': [
            { 'item_id': '%s', 'item_name': '%s', 'quantity': %s }
        ] });",
            'BRL',
            number_format($cart->getQuote()->getBaseGrandTotal(), 2, '.', ''),
            $this->jsQuoteEscape($product->getId()),
            $this->jsQuoteEscape($product->getName()),
            $itemSession->getQty()
        );

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

    public function _getDeleteFromCartCode()
    {
        $cart = Mage::getSingleton('checkout/cart');
        $result = array();
        $itemSession = Mage::getModel('core/session')->getGaDeleteProductFromCart();

        $product = Mage::getModel('catalog/product')->load($itemSession->getId());

        $result[] = sprintf("gtag('event', 'remove_from_cart', { 'currency': '%s', 'value': %s, 'items': [
            { 'item_id': '%s', 'item_name': '%s', 'quantity': %s }
        ] });",
            'BRL',
            number_format($cart->getQuote()->getBaseGrandTotal(), 2, '.', ''),
            $this->jsQuoteEscape($product->getId()),
            $this->jsQuoteEscape($product->getName()),
            $itemSession->getQty()
        );

        Mage::getModel('core/session')->unsGaDeleteProductFromCart();

        return implode("\n", $result);
    }

    public function _getProductDetailsCode()
    {
        $cart = Mage::getSingleton('checkout/cart');
        $result = array();

        if ((Mage::app()->getFrontController()->getRequest()->getControllerName() == "product") &&
            (Mage::registry('current_product') != null)) {

            $product = Mage::registry('current_product');

            if (Mage::registry('current_category') != null) {
                $category = Mage::registry('current_category');

                $result[] = sprintf("gtag('event', 'view_item', { 'currency': '%s', 'value': %s, 'items': [
                    { 'item_id': '%s', 'item_name': '%s', 'category': '%s' }
                ] });",
                    'BRL',
                    number_format($cart->getQuote()->getBaseGrandTotal(), 2, '.', ''),
                    $this->jsQuoteEscape($product->getId()),
                    $this->jsQuoteEscape($product->getName()),
                    $this->jsQuoteEscape($category->getName())
                );
            } else {
                $result[] = sprintf("gtag('event', 'view_item', { 'currency': '%s', 'value': %s, 'items': [
                    { 'item_id': '%s', 'item_name': '%s' }
                ] });",
                    'BRL',
                    number_format($cart->getQuote()->getBaseGrandTotal(), 2, '.', ''),
                    $this->jsQuoteEscape($product->getId()),
                    $this->jsQuoteEscape($product->getName())
                );
            }

            $productPrice = $this->getProductPrice($product);

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

    public function _getPurchaseCode()
    {
        $orderIds = $this->_getOrderIds();

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
                $dataLayerSku = Mage::getStoreConfig('google/analytics/googleanalyticssku');
                $productId = ($dataLayerSku ? $item->getSku() : $item->getProductId());
                $productIds[] = $this->jsQuoteEscape($productId);

                $result[] = sprintf("mage_data_layer_products.push({
                    item_id: \"%s\",
                    item_name: \"%s\",
                    item_category: \"\",
                    price: %s,
                    currency: \"BRL\",
                    quantity: %s
                });", $this->jsQuoteEscape($productId),
                    $this->jsQuoteEscape($item->getName()),
                    number_format($item->getBasePrice(), 2, '.', ''),
                    number_format($item->getQtyOrdered(), 0, '', '')
                );
            }

            $result[] = sprintf(
                "gtag('event', 'purchase', {
                    currency: \"%s\",
                    transaction_id: \"%s\",
                    value: %s,
                    affiliation: \"%s\",
                    coupon: \"%s\",
                    shipping: %s,
                    tax: %s,
                    items: mage_data_layer_products
                });",
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
            $customerEmail = $order->getBillingAddress()->getEmail();
            $customer = Mage::getModel('customer/customer')->load($order->getCustomerId());
            $customerDob = $customer->getDob();

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
                        shipping: %s,
                        customer: {
                            type: \"%s\",
                            email: \"%s\",
                            birthday: \"%s\"
                        },
                        items: mage_data_layer_products
                    }
                };", $this->jsQuoteEscape($order->getIncrementId()),
                    number_format($order->getBaseGrandTotal(), 2, '.', ''),
                    number_format($order->getDiscountAmount(), 2, '.', ''),
                    number_format($order->getBaseShippingAmount(), 2, '.', ''),
                    $customerType,
                    $customerEmail,
                    $customerDob
                );

            $result[] = sprintf("
                dataLayer.push({
                    event: \"purchase\",
                    value: %s,
                    transactionID: \"%s\",
                    email: \"%s\"
                });", number_format($order->getBaseGrandTotal(), 2, '.', ''),
                    $this->jsQuoteEscape($order->getIncrementId()),
                    $this->jsQuoteEscape($order->getCustomerEmail())
                );

        }
        return implode("\n", $result);
    } 

}