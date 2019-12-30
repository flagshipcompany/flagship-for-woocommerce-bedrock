<?php
class FlagshipWoocommerceShipping {
	
	public static $exportAction = 'wc_flagship_export_action';
	
	protected static $_instance = null;

	public static function instance() {
		if (is_null( self::$_instance )) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public static function getSettingsOptionKey() {
		return 'woocommerce_'.WC_FLAGSHIP_ID.'_settings';
	}

	public static function getFlagshipUrl() {
		return FLAGSHIP_DEBUG_MODE == true ? 'http://127.0.0.1:3001' : 'https://smartship-ng.flagshipcompany.com';
	}
	
	public function __construct() {
	    add_action( 'woocommerce_shipping_init', array($this, 'include_method_classes'));	 
	    add_filter( 'woocommerce_shipping_methods', array($this, 'add_flagship_shipping_method'));
	    add_filter( 'woocommerce_order_actions',  array($this, 'add_order_meta_box_action'));
	    add_action( 'woocommerce_order_action_'.self::$exportAction,  array($this, 'process_order_meta_box_action'));
	    add_action( 'add_meta_boxes', array($this, 'add_custom_meta_box')); 					
	}
	
	public function add_flagship_shipping_method($methods) {
		$methods[WC_FLAGSHIP_ID] = 'WC_Flagship_Shipping_Method';

		return $methods;
	}

	public function add_order_meta_box_action($actions) {
        $orderActionProcessor = $this->init_order_action_processor();
        
        return $orderActionProcessor->addExportAction($actions);
    }

    public function process_order_meta_box_action($order) {
        $orderActionProcessor = $this->init_order_action_processor($order);
        
        return $orderActionProcessor->exportOrder();
    }

    public function add_custom_meta_box() {
    	global $post;

    	$orderActionProcessor = $this->init_order_action_processor(wc_get_order($post->ID));
    	$orderActionProcessor->addMetaBoxes();
    }

	public function include_method_classes()
	{
		include_once('WC_Flagship_Shipping_Method.php');
		include_once('requests/Abstract_Flagship_Api_Request.php');
		include_once('requests/Rates_Request.php');
		include_once('Cart_Rates_Processor.php');
	}

	public function include_admin_classes()
	{
		include_once('requests/Abstract_Flagship_Api_Request.php');
		include_once('requests/Export_Order_Request.php');
		include_once('Order_Action_Processor.php');
	}

	protected function init_order_action_processor($order = null)
	{
		$this->include_admin_classes();

		if (!$order) {
			global $theorder;
			$order = $theorder;		
		}
        
        $settings = get_option(self::getSettingsOptionKey());

        return new Order_Action_Processor($order, $settings);
	}
}
