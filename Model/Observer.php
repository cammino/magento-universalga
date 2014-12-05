<?php
class Cammino_Googleanalytics_Model_Observer {

	public function addToCart() {
		$productId = Mage::app()->getRequest()->getParam('product', 0);
		$quantity = Mage::app()->getRequest()->getParam('qty', 1);

		Mage::getModel('core/session')->setGaAddProductToCart(
			new Varien_Object(array(
				'id' => $productId,
				'qty' => $quantity
			))
		);
	}

	public function deleteFromCart() {
		$cart = Mage::getSingleton('checkout/cart');
		$itemId = Mage::app()->getRequest()->getParam('id', 0);
		$item = $cart->getQuote()->getItemById($itemId);
		$product = $item->getProduct();

		Mage::getModel('core/session')->setGaDeleteProductFromCart(
			new Varien_Object(array(
				'id' => $product->getId(),
				'qty' => $item->getQty()
			))
		);
	}

}