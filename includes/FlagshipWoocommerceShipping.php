<?php
class FlagshipWoocommerceShipping {
	
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
		if (!class_exists('Flagship\Shipping\Flagship')) {
			$this->showSdkNotice();

		    return;
		}

	    add_action( 'woocommerce_shipping_init', array($this, 'include_method_classes'));	 
	    add_filter( 'woocommerce_shipping_methods', array($this, 'add_flagship_shipping_method'));
	    add_filter( 'plugin_action_links_' . FLAGSHIP_PLUGIN_NAME, array( $this, 'plugin_action_links' ) );
	    add_action( 'add_meta_boxes', array($this, 'add_custom_meta_box'));
	    add_action( 'woocommerce_process_shop_order_meta', array($this, 'save_meta_box'));				
	}

	public function showSdkNotice() {
		add_action( 'admin_notices', array($this, 'add_flagship_sdk_missing_notice'));
	}

	public function add_flagship_sdk_missing_notice() {
		?>
		  	<div class="update-nag notice">
		      <p><?php _e( 'To ensure the FlagShip WooCommerce Shipping plugin function properly, please run "composer require flagshipcompany/flagship-api-sdk" to install the required classes', 'flagship-woocommerce-extension'); ?></p>
		  	</div>
		 <?php
	}

	public function plugin_action_links($links) {
		$settingsUrl = 'admin.php?page=wc-settings&tab=shipping&section='.WC_FLAGSHIP_ID;
		$plugin_links = array(
			'<a href="' . admin_url($settingsUrl) . '">' . __( 'Settings', 'flagship-woocommerce-extension' ) . '</a>',
		);

		return array_merge($plugin_links, $links);
	}

	public function save_meta_box($orderId) {
		$orderActionProcessor = $this->init_order_action_processor(wc_get_order($orderId));
        
        return $orderActionProcessor->processOrderActions($_POST);
	}
	
	public function add_flagship_shipping_method($methods) {
		$methods[WC_FLAGSHIP_ID] = 'WC_Flagship_Shipping_Method';

		return $methods;
	}

    public function add_custom_meta_box() {
    	global $post;

    	$order = wc_get_order($post->ID);

    	if (!$order) {
    		return;
    	}

    	$orderActionProcessor = $this->init_order_action_processor($order);
    	$orderActionProcessor->addMetaBoxes($order);
    }

	public function include_method_classes()
	{
		include_once('WC_Flagship_Shipping_Method.php');
		include_once('requests/Abstract_Flagship_Api_Request.php');
		include_once('requests/Rates_Request.php');
		include_once('requests/ECommerce_Request.php');
		include_once('Cart_Rates_Processor.php');
	}

	public function include_admin_classes()
	{
		include_once('requests/Abstract_Flagship_Api_Request.php');
		include_once('requests/Export_Order_Request.php');
		include_once('Order_Action_Processor.php');
	}

	protected function init_order_action_processor($order)
	{
		$this->include_admin_classes();        
        $settings = get_option(self::getSettingsOptionKey());

        return new Order_Action_Processor($order, $settings);
	}
}
