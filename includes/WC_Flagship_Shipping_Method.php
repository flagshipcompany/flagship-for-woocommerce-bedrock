<?php
class WC_Flagship_Shipping_Method extends WC_Shipping_Method {
 //    private $found_rates;
	// private $services;

	private $token;

    /**
     *
     * @access public
     * @return void
     */
    public function __construct() {
        $this->id = WC_FLAGSHIP_ID; 
        $this->method_title = __('FlagShip Shipping', 'flagship');  
        $this->method_description = __('Obtains real time shipping rates from FlagShip', 'flagship');
        $this->supports = array(
            'shipping-zones',
            'instance-settings',
            'instance-settings-modal',
            'settings',
        );
        $this->init_method_settings();
        $this->init();
    }

    /**
     *
     * @access public
     * @return void
     */
    public function init() {
        $this->init_form_fields(); 
        $this->init_settings();

        add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
    }

    /**
     * @return void 
     */
    public function init_form_fields() { 

        $this->form_fields = array(
			'enabled' => array(
				'title' => __('Enable', 'flagship'),
				'type' => 'checkbox',
				'description' => __( 'Enable this shipping.', 'flagship'),
				'default' => 'yes'
			),
			'title' => array(
				'title' => __('Title', 'flagship'),
				  'type' => 'text',
				  'description' => __( 'Title to be display on site', 'flagship'),
				  'default' => __('FlagShip Shipping', 'flagship')
			),
			'token' => array(
				'title' => __('FlagShip API token', 'flagship'),
				'type' => 'text',
				'description' => __('After signup, get a access token here.', 'flagship'),
			),
        );
    }

    /**
     * @access public
     * @param mixed $package
     * @return void
     */
    public function calculate_shipping($package = Array()) {
    	if (count($package) == 0 || $this->enabled != 'yes') {
    		return;
    	}

    	$apiRequest = new Flagship_Api_Request($this->token);
    	$rates = $apiRequest->getRates($package);
        // console($rates);
        $ratesProcessor = new Cart_Rates_Processor($this->id);
        $cartRates = $ratesProcessor->processRates($rates->all());

        foreach ($cartRates as $key => $rate) {
            $this->add_rate($rate);
        }
    }

    protected function init_method_settings() {
    	$this->enabled = $this->get_option('enabled', 'no');
        $this->title = $this->get_option('title', __('FlagShip Shipping', 'flagship'));
        $this->token = $this->get_option('token', '');
    }
}