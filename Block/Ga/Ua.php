<?php
class Cammino_Googleanalytics_Block_Ga_Ua extends Cammino_Googleanalytics_Block_Ga
{

    public function _getPageTrackingCode($accountId)
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
        $result[] = $this->_getCheckoutCode();
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
                    number_format($item->getQty(), 0, '', '')
                );
            }
            $result[] = sprintf("
                var mage_data_layer = {
                    page: \"$page\",
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

        Mage::getModel('core/session')->unsGaAddProductToCart();

        return implode("\n", $result);
    }

    public function _getDeleteFromCartCode()
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

    public function _getProductDetailsCode()
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
                $result[] = sprintf("ga('ec:addProduct', { 'id': '%s', 'name': '%s', 'price': '%s', 'quantity': %s });",
                    $this->jsQuoteEscape($item->getProductId()),
                    $this->jsQuoteEscape($item->getName()),
                    number_format($item->getBasePrice(), 2, '.', ''),
                    number_format($item->getQtyOrdered(), 0, '', '')
                );
                $productIds[] = $this->jsQuoteEscape($item->getProductId());
                $productCategory = $this->getProductCategory($item);

                $result[] = sprintf("mage_data_layer_products.push({
                    sku: \"%s\",
                    name: \"%s\",
                    category: \"%s\",
                    price: %s,
                    quantity: %s
                });", $this->jsQuoteEscape($item->getProductId()),
                    $this->jsQuoteEscape($item->getName()),
                    $this->jsQuoteEscape($productCategory),
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
                        paymentType:\"%s\",
                        discount: \"%s\",
                        shipping: \"%s\",
                        customer: {
                            type: \"%s\",
                            email: \"%s\",
                            birthday: \"%s\"
                        },
                        items: mage_data_layer_products
                    }
                };", $this->jsQuoteEscape($order->getIncrementId()),
                    number_format($order->getBaseGrandTotal(), 2, '.', ''),
                    $order->getPayment()->getMethodInstance()->getTitle(),
                    number_format($order->getDiscountAmount(), 2, '.', ''),
                    number_format($order->getBaseShippingAmount(), 2, '.', ''),
                    $customerType,
                    $customerEmail,
                    $customerDob
                );

            $result[] = sprintf("
                dataLayer.push({
                    event: \"purchase\",
                    value: \"%s\",
                    transactionID: \"%s\",
                    email: \"%s\",
                    first_name: \"%s\",
                    last_name: \"%s\",
                    dob: \"%s\",
                    cep: \"%s\",
                    phone: \"%s\",
                    city: \"%s\",
                    state: \"%s\",
                    country: \"br\"
                });", number_format($order->getBaseGrandTotal(), 2, '.', ''),
                    $this->jsQuoteEscape($order->getIncrementId()),
                    $this->jsQuoteEscape($order->getCustomerEmail()),
                    self::formatStringAcentosLowercase($customer->getFirstname()),
                    self::formatStringAcentosLowercase($customer->getLastname()),
                    self::formatRemoveSpecialCharacters($customerDob),
                    self::formatRemoveSpecialCharacters($address->getPostcode()),
                    self::formatRemoveSpecialCharacters($address->getTelephone()),
                    self::formatStringAcentosLowercase($address->getCity()),
                    self::getRegionSigla($address->getRegion())
                );
    
            }
            Mage::log($result, null, 'analytics.log');
        return implode("\n", $result);
    }

    private function formatStringAcentosLowercase($string){
        return strtolower(preg_replace(array("/(á|à|ã|â|ä)/","/(Á|À|Ã|Â|Ä)/","/(é|è|ê|ë)/","/(É|È|Ê|Ë)/","/(í|ì|î|ï)/","/(Í|Ì|Î|Ï)/","/(ó|ò|õ|ô|ö)/","/(Ó|Ò|Õ|Ô|Ö)/","/(ú|ù|û|ü)/","/(Ú|Ù|Û|Ü)/","/(ñ)/","/(Ñ)/"),explode(" ","a A e E i I o O u U n N"),$string));
    }
    
    private function formatRemoveSpecialCharacters($string) {
        $string = str_replace(' ', '', $string);
        $string = str_replace('-', '', $string);
        return preg_replace('/[^A-Za-z0-9\-]/', '', $string);
    }

    private function getRegionSigla($needle) {
       $regions = [
         ["Sigla" => "ac", "Nome" => "Acre"],
         ["Sigla" => "al", "Nome" => "Alagoas"],
         ["Sigla" => "ap", "Nome" => "Amapá"],
         ["Sigla" => "am", "Nome" => "Amazonas"],
         ["Sigla" => "ba", "Nome" => "Bahia"],
         ["Sigla" => "ce", "Nome" => "Ceará"],
         ["Sigla" => "df", "Nome" => "Distrito Federal"],
         ["Sigla" => "es", "Nome" => "Espírito Santo"],
         ["Sigla" => "go", "Nome" => "Goiás"],
         ["Sigla" => "ma", "Nome" => "Maranhão"],
         ["Sigla" => "mt", "Nome" => "Mato Grosso"],
         ["Sigla" => "ms", "Nome" => "Mato Grosso do Sul"],
         ["Sigla" => "mg", "Nome" => "Minas Gerais"],
         ["Sigla" => "pa", "Nome" => "Pará"],
         ["Sigla" => "pb", "Nome" => "Paraíba"],
         ["Sigla" => "pr", "Nome" => "Paraná"],
         ["Sigla" => "pe", "Nome" => "Pernambuco"],
         ["Sigla" => "pi", "Nome" => "Piauí"],
         ["Sigla" => "rj", "Nome" => "Rio de Janeiro"],
         ["Sigla" => "rn", "Nome" => "Rio Grande do Norte"],
         ["Sigla" => "rs", "Nome" => "Rio Grande do Sul"],
         ["Sigla" => "ro", "Nome" => "Rondônia"],
         ["Sigla" => "rr", "Nome" => "Roraima"],
         ["Sigla" => "sc", "Nome" => "Santa Catarina"],
         ["Sigla" => "sp", "Nome" => "São Paulo"],
         ["Sigla" => "se", "Nome" => "Sergipe"],
         ["Sigla" => "to", "Nome" => "Tocantins"]
       ];
       $sigla = '';
       foreach($regions as $region) {
         if($region['Nome'] == $needle) {
           $sigla = $region['Sigla'];
         }
       }
       return $sigla;
    }


    public function getProductPrice($product) {

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

    public function getConfigurableProductPrice($product) {
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

    public function getGroupedProductPrice($product) {
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

    public function getAssociatedProducts($product) {
        $collection = $product->getTypeInstance(true)->getAssociatedProductCollection($product)
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('status', 1);
        return $collection;
    }

}