<?php
class FlagshipWoocommerceShipping {
	
	protected static $_instance = null;

	public static function instance() {
		if (is_null( self::$_instance )) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}
	
	public function __construct() {
	    add_action( 'woocommerce_shipping_init', array($this, 'include_classes'));	 
	    add_filter( 'woocommerce_shipping_methods', array($this, 'add_flagship_shipping_method')); 					
	}
	
	public function add_flagship_shipping_method($methods) {
		$methods[] = 'WC_Flagship_Shipping_Method';

		return $methods;
	}

	public function include_classes()
	{
		include_once('WC_Flagship_Shipping_Method.php');
		include_once('Flagship_Api_Request.php');
		include_once('Cart_Rates_Processor.php');
	}
}
