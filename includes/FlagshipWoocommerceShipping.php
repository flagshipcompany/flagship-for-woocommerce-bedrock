<?php
namespace FlagshipWoocommerce;

class FlagshipWoocommerceShipping {
	
	public static $methodId = 'flagship_shipping_method';

	public static $couriers = array(
		'UPS' => 'ups',
        'DHL' => 'dhl',
        'FedEx' => 'fedex',
        'Purolator' => 'purolator',
        'Canpar' => 'canpar',
	);

	protected static $_instance = null;

	public static function instance() {
		if (is_null( self::$_instance )) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public static function isDebugMode() {
		return getenv('FLAGSHIP_DEBUG_MODE') === 'yes';
	}

	public static function getSettingsOptionKey() {
		return 'woocommerce_'.self::$methodId.'_settings';
	}

	public static function getFlagshipUrl() {
		return self::isDebugMode() ? getenv('FLAGSHIP_URL') : 'https://smartship-ng.flagshipcompany.com';
	}
	
	public function __construct() {
		$this->handleThirdPartyLibraries();

		$this->hooks();			
	}

	//Handle the scenario that the plugin is installed without composer require
	public function handleThirdPartyLibraries() {
		if (!class_exists('Flagship\Shipping\Flagship')) {
			include_once realpath(dirname(__FILE__) . '/..'). '/vendor/autoload.php';
		}

		if (!class_exists('Flagship\Shipping\Flagship')) {
			$this->showSdkNotice();

		    return;
		}
	}

	public function hooks() {
		add_filter( 'woocommerce_shipping_methods', array($this, 'add_flagship_shipping_method'));
	    add_filter( 'plugin_action_links_' . FLAGSHIP_PLUGIN_NAME, array( $this, 'plugin_action_links' ) );
	    add_action( 'add_meta_boxes', array($this, 'add_custom_meta_box'));
	    add_action( 'woocommerce_process_shop_order_meta', array($this, 'save_meta_box'));
	    add_action('admin_notices', array($this, 'flagship_warning_in_notice'));
	}   

	public function showSdkNotice() {
		add_action( 'admin_notices', array($this, 'add_flagship_sdk_missing_notice'));
	}

	public function flagship_warning_in_notice() {

	    if (!isset($_REQUEST['flagship_warning']) || empty($_REQUEST['flagship_warning'])) {
	        return;
	    }

	    $message = trim($_REQUEST['flagship_warning']);

	    echo '<div class="notice notice-error is-dismissible"><p><strong>'
	    .$message.
	    '</strong></p></div>';
	}

	public function add_flagship_sdk_missing_notice() {
		?>
		  	<div class="update-nag notice">
		      <p><?php _e( 'To ensure the FlagShip WooCommerce Shipping plugin function properly, please run "composer require flagshipcompany/flagship-api-sdk" to install the required classes', 'flagship-woocommerce-extension'); ?></p>
		  	</div>
		 <?php
	}

	public function plugin_action_links($links) {
		$settingsUrl = 'admin.php?page=wc-settings&tab=shipping&section='.self::$methodId;
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
		$methods[self::$methodId] = new WC_Flagship_Shipping_Method();

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

	protected function init_order_action_processor($order)
	{      
        $settings = get_option(self::getSettingsOptionKey());

        return new Order_Action_Processor($order, $settings);
	}
}
