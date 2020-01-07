<?php
namespace FlagshipWoocommerce;

class WC_Flagship_Shipping_Method extends \WC_Shipping_Method {
    
	private $token;

    /**
     *
     * @access public
     * @return void
     */
    public function __construct($instance_id = 0) {
        parent::__construct($instance_id);

        $this->id = WC_FLAGSHIP_ID; 
        $this->method_title = __('FlagShip Shipping', 'flagship-woocommerce-extension');  
        $this->method_description = __('Obtains real time shipping rates from FlagShip', 'flagship-woocommerce-extension');
        $this->supports = array(
            'shipping-zones',
            'instance-settings',
            'instance-settings-modal',
            'settings',
        );
        $this->init_method_settings();
        $this->init();
        $this->init_instance_settings();
    }

    /**
     *
     * @access public
     * @return void
     */
    public function init() {
        $this->init_form_fields(); 
        $this->init_settings();

        add_action( 'woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
    }

    /**
     * @return void 
     */
    public function init_form_fields() { 

        $this->form_fields = $this->makeGeneralFields();
        $this->instance_form_fields = $this->makeInstanceFields();
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

    	// $apiRequest = new Rates_Request($this->token);
    	// $rates = $apiRequest->getRates($package);
        $ratesProcessor = new Cart_Rates_Processor($this->id, $this->token, $this->instance_settings);
        $rates = $ratesProcessor->fetchRates($package);
        $cartRates = $ratesProcessor->processRates($rates);

        foreach ($cartRates as $key => $rate) {
            $this->add_rate($rate);
        }
    }

    protected function init_method_settings() {
    	$this->enabled = $this->get_option('enabled', 'no');
        $this->title = $this->get_option('title', __('FlagShip Shipping', 'flagship-woocommerce-extension'));
        $this->token = $this->get_option('token', '');
    }

    protected function makeGeneralFields() {
        return array(
            'enabled' => array(
                'title' => __('Enable', 'flagship-woocommerce-extension'),
                'type' => 'checkbox',
                'description' => __( 'Enable this shipping method.', 'flagship-woocommerce-extension'),
                'default' => 'no'
            ),
            'token' => array(
                'title' => __('FlagShip access token', 'flagship-woocommerce-extension'),
                'type' => 'text',
                'description' => sprintf(__('After <a href="%s">signup </a>, <a target="_blank" href="%s">get an access token here </a>.', 'flagship-woocommerce-extension'), 'https://www.flagshipcompany.com/sign-up/', 'https://auth.smartship.io/tokens/'),
            ),
        );
    }

    protected function makeInstanceFields() {
        $ecommerceApplicable = $this->isInstanceForEcommerce(\WC_Shipping_Zones::get_zone_by( 'instance_id', $this->instance_id)->get_zone_locations());

        $fields = array(
            'offer_standard_rates' => array(
                'title' => __('Offer standard rates', 'flagship-woocommerce-extension'),
                'type' => 'checkbox',
                'default' => 'yes'
            ),
            'offer_express_rates' => array(
                'title' => __('Offer express rates', 'flagship-woocommerce-extension'),
                'type' => 'checkbox',
                'default' => 'yes'
            ),
            'offer_dhl_ecommerce_rates' => array(
                'title' => __('Offer DHL ecommerce rates', 'flagship-woocommerce-extension'),
                'type' => 'checkbox',
                'description' => __( 'Available for international destinations when package is less than 2kg', 'flagship-woocommerce-extension'),
                'default' => 'no'
            ),
            'only_show_cheapest' => array(
                'title' => __('Only show the cheapest rate', 'flagship-woocommerce-extension'),
                'type' => 'checkbox',
                'default' => 'no'
            ),
            'shipping_cost_markup' => array(
                'title' => __('Shipping cost markup (%)', 'flagship-woocommerce-extension'),
                'type' => 'decimal',
                'description' => __( 'Shipping cost markup in percentage', 'flagship-woocommerce-extension'),
                'default' => 0
            ),
        );

        if (!$ecommerceApplicable) {
            unset($fields['offer_dhl_ecommerce_rates']);
        }

        return $fields;
    }

    protected function isInstanceForEcommerce($locations)
    {
        if (empty($locations)) {
            return true;
        }

        $location = reset($locations);
        $locationType = $location->type;

        switch ($locationType) {
            case 'country':
                $country = $location->code;
                break;
            case 'state':
                $country = explode(':', $location->code)[0];
                break;            
            default:
                $country = null;
                break;
        }

        return $country != 'CA';
    }
}