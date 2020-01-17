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

        $this->id = FlagshipWoocommerceShipping::$methodId; 
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

        $ratesProcessor = new Cart_Rates_Processor($this->id, $this->token, array_merge($this->instance_settings, array('debug_mode' => $this->debugMode)));
        $rates = $ratesProcessor->fetchRates($package);
        $cartRates = $ratesProcessor->processRates($package, $rates);

        foreach ($cartRates as $key => $rate) {
            $this->add_rate($rate);
        }
    }

    protected function init_method_settings() {
    	$this->enabled = $this->get_option('enabled', 'no');
        $this->title = $this->get_option('title', __('FlagShip Shipping', 'flagship-woocommerce-extension'));
        $this->token = $this->get_option('token', '');
        $this->debugMode = $this->get_option('debug_mode', 'no');
    }

    protected function makeGeneralFields() {
        return array(
            'enabled' => array(
                'title' => __('Enable', 'flagship-woocommerce-extension'),
                'type' => 'checkbox',
                'description' => __( 'Enable this shipping method', 'flagship-woocommerce-extension'),
                'default' => 'no'
            ),
            'token' => array(
                'title' => __('FlagShip access token', 'flagship-woocommerce-extension'),
                'type' => 'text',
                'description' => sprintf(__('After <a href="%s">signup </a>, <a target="_blank" href="%s">get an access token here </a>.', 'flagship-woocommerce-extension'), 'https://www.flagshipcompany.com/sign-up/', 'https://auth.smartship.io/tokens/'),
            ),
            'debug_mode' => array(
                'title' => __('Debug mode', 'flagship-woocommerce-extension'),
                'label' => __( 'Enable debug mode', 'flagship-woocommerce-extension' ),
                'type' => 'checkbox',
                'default' => 'no'
            ),
        );
    }

    protected function makeInstanceFields() {
        $ecommerceApplicable = $this->isInstanceForEcommerce(\WC_Shipping_Zones::get_zone_by( 'instance_id', $this->instance_id)->get_zone_locations());

        $fields = array(
            'shipping_rates_configs' => array(
                'title' => __('Rates', 'flagship-woocommerce-extension'),
                'type' => 'title',
            ),
            'allow_standard_rates' => array(
                'title' => __('Offer standard rates', 'flagship-woocommerce-extension'),
                'type' => 'checkbox',
                'default' => 'yes'
            ),
            'allow_express_rates' => array(
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
            'shipping_markup' => array(
                'title' => __('Markup', 'flagship-woocommerce-extension'),
                'type' => 'title',
                'description' => __('Store owner may apply additional fee for shipping.', 'flagship-woocommerce-extension'),
            ),
            'shipping_cost_markup_percentage' => array(
                'title' => __('Shipping cost markup (%)', 'flagship-woocommerce-extension'),
                'type' => 'decimal',
                'description' => __( 'Shipping cost markup in percentage', 'flagship-woocommerce-extension'),
                'default' => 0
            ),
            'shipping_cost_markup_flat' => array(
                'title' => __('Shipping cost markup in flat fee ($)', 'flagship-woocommerce-extension'),
                'type' => 'decimal',
                'description' => __( 'Shipping cost markup in flat fee (this will be applied after the percentage markup)', 'flagship-woocommerce-extension'),
                'default' => 0
            ),
            'shipping_options' => array(
                'title' => __('Shipping Options', 'flagship-woocommerce-extension'),
                'type' => 'title',
            ),
            'show_transit_time' => array(
                'title' => __('Show transit time in shopping cart', 'flagship-woocommerce-extension'),
                'description' => __('If checked, the transit times of couriers will be shown', 'flagship-woocommerce-extension'),
                'type' => 'checkbox',
                'default' => 'no',
            ),
            'signature_required' => array(
                'title' => __('Signature required on delivery', 'flagship-woocommerce-extension'),
                'description' => __('If checked, all the shipments to this shipping zone will be signature required on delivery', 'flagship-woocommerce-extension'),
                'type' => 'checkbox',
                'default' => 'no',
            ),
            'residential_receiver_address' => array(
                'title' => __('Residential receiver address', 'flagship-woocommerce-extension'),
                'description' => __('If checked, all the receiver addresses in this shipping zone will be considered residential', 'flagship-woocommerce-extension'),
                'type' => 'checkbox',
                'default' => 'no',
            ),
        );

        $disableCourierOptions = $this->makeDisableCourierOptions(FlagshipWoocommerceShipping::$couriers, $ecommerceApplicable);
        $fields = array_slice($fields, 0, 5, true) +
           $disableCourierOptions +
            array_slice($fields, 5, NULL, true);

        if (!$ecommerceApplicable) {
            unset($fields['offer_dhl_ecommerce_rates']);
        }

        $fields = array_merge($fields, $this->makeShippingClassSettings());

        return $fields;
    }

    protected function makeShippingClassSettings()
    {
        $settings = array();
        $shipping_classes = WC()->shipping()->get_shipping_classes();

        if (empty($shipping_classes)) {
            return $settings;
        }

        $settings['class_costs'] = array(
            'title'       => __( 'Shipping class costs', 'woocommerce' ),
            'type'        => 'title',
            'default'     => '',
            'description' => sprintf( __( 'These costs can optionally be added based on the <a href="%s">product shipping class</a>.', 'woocommerce' ) . ' ' . __('It will charge shipping for each shipping class individually.', 'flagship-woocommerce-extension'),  admin_url( 'admin.php?page=wc-settings&tab=shipping&section=classes' ) ),
        );

        foreach ( $shipping_classes as $shipping_class ) {
            if (!isset( $shipping_class->term_id)) {
                continue;
            }

            $settings[ 'class_cost_' . $shipping_class->term_id ] = array(
                'title'             => sprintf( __( '"%s" shipping class cost', 'woocommerce' ), esc_html( $shipping_class->name ) ),
                'type'              => 'decimal',
                'placeholder'       => __( 'N/A', 'woocommerce' ),
                'description'       => 'shipping class cost',
                'default'           => $this->get_option( 'class_cost_' . $shipping_class->slug ),
                'desc_tip'          => true,
                'sanitize_callback' => array( $this, 'sanitize_cost' ),
            );
        }

        return $settings;
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

    protected function makeDisableCourierOptions($couriers, $isInternationalZone = false)
    {
        $disableCourierOptions = array();

        if (!$isInternationalZone) {
            unset($couriers['DHL']);
        }

        foreach ($couriers as $key => $value) {
            $settingName = 'disable_courier_'.$value;
            $settingLabel = sprintf(__('Disable %s rates', 'flagship-woocommerce-extension'), $key);
            $disableCourierOptions[$settingName] = array(
                'title' => __($settingLabel, 'flagship-woocommerce-extension'),
                'type' => 'checkbox',
                'default' => 'no',
            );
        }

        return $disableCourierOptions;
    }
}