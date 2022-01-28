<?php
namespace FlagshipWoocommerceBedrock;

use FlagshipWoocommerceBedrock\Helpers\Notification_Helper;
use FlagshipWoocommerceBedrock\Helpers\Validation_Helper;
use FlagshipWoocommerceBedrock\Helpers\Template_Helper;
use FlagshipWoocommerceBedrock\Helpers\Menu_Helper;

class WC_Flagship_Shipping_Method extends \WC_Shipping_Method
{
    private $token;

    /**
     * @access public
     * @return void
     */
    public function __construct($instance_id = 0)
    {
        parent::__construct($instance_id);

        $this->white_label_title = WHITELABEL_PLUGIN == 1 ? WHITELABEL_TEXT : 'FlagShip';//get title based on whitelabel settings

        $this->id = FlagshipWoocommerceBedrockShipping::$methodId;
        $this->method_title = __($this->white_label_title.' Shipping', 'flagship-shipping-extension-for-woocommerce');
        $this->method_description = __('Obtain '.$this->white_label_title.' shipping rates for orders and export order to '.$this->white_label_title.' to dispatch shipment', 'flagship-shipping-extension-for-woocommerce');
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
    public function init()
    {
        $this->init_form_fields();
        $this->init_settings();

        add_action(
            'woocommerce_update_options_shipping_' . $this->id, 
            array($this, 'process_admin_options')
        );
        add_filter(
            'woocommerce_settings_api_sanitized_fields_' . $this->id, 
            array($this, 'validate_admin_options')
        );
        add_filter(
            'woocommerce_shipping_' . $this->id . '_instance_settings_values', 
            array($this, 'validate_shipping_zone_options')
        );
    }
    /**
     * @return void
     */
    public function init_form_fields()
    {
        $this->form_fields = $this->makeGeneralFields();
        $this->instance_form_fields = $this->makeInstanceFields();
    }

    // remove redundant/ old shipping zone settings in case the option is removed or moved to the general settings.
    // only return values of options that match options on the shipping zone form.
    public function validate_shipping_zone_options($shippingZoneOptions)
    {
        $currentFormFields = array_keys($this->instance_form_fields);
        $formattedOptions = [];

        foreach (array_keys($this->instance_form_fields) as $formField) {
            $formattedOptions[$formField] = $shippingZoneOptions[$formField];
        }

        return $formattedOptions;
    }

    /**
     * @access public
     * @param mixed $package
     * @return void
     */
    public function calculate_shipping($package = array())
    {
        if (count($package) == 0 || $this->enabled != 'yes') {
            return;
        }

        $settings = array_merge($this->settings, $this->instance_settings);
        $ratesProcessor = new Cart_Rates_Processor($this->id, $this->token, array_merge($settings, array('debug_mode' => $this->debugMode)));
        $rates = $ratesProcessor->fetchRates($package);
        $cartRates = $ratesProcessor->processRates($package, $rates);

        foreach ($cartRates as $key => $rate) {
            $this->add_rate($rate);
        }
    }

    public function get_option($key, $empty_value = null)
    {
        if ($key === 'tracking_emails' && !array_key_exists('tracking_emails', $this->settings)) {
            return trim(WC()->mailer()->get_emails()['WC_Email_New_Order']->recipient);
        }

        return parent::get_option($key, $empty_value);
    }

    public function validate_admin_options($settings)
    {
        $testEnv = $settings['test_env'] == 'no' ? 0 : 1;
        $validationHelper = new Validation_Helper($testEnv);

        if (isset($settings['token']) && !empty(trim($settings['token'])) && !$validationHelper->validateToken($settings['token'])) {
            $settings['token'] = '';
            add_action(
                'admin_notices', 
                array((new Notification_Helper()),'add_token_invalid_notice')
            );
        }


        if (isset($settings['tracking_emails']) && !empty(trim($settings['tracking_emails'])) && !Validation_Helper::validateMultiEmails($settings['tracking_emails'])) {
            $settings = get_option($this->get_option_key(), array());
            $settings['tracking_emails'] = get_array_value($settings, 'tracking_emails', '');

            add_action(
                'admin_notices', 
                array((new Notification_Helper()), 'add_tracking_email_invalid_notice')
            );
        }

        return $settings;
    }

    public function generate_radio_html($key, $data)
    {
        $data['field_name'] = 'woocommerce_'.FlagshipWoocommerceBedrockShipping::$methodId.'_'.$key;
        $data['value'] = $this->get_option($key, null);

        return Template_Helper::render_embedded_php('_radio_field.php', $data);
    }

    protected function init_method_settings()
    {
        $this->enabled = $this->get_option('enabled', 'no');
        $this->title = $this->get_option('title', __($this->white_label_title.' Shipping', 'flagship-shipping-extension-for-woocommerce'));
        $this->token = $this->get_option('token', '');
        $this->debugMode = $this->get_option('debug_mode', 'no');
    }

    protected function makeGeneralFields()
    {
        return array(
            'enabled' => array(
                'title' => esc_html(__('Enable', 'flagship-shipping-extension-for-woocommerce')),
                'type' => 'checkbox',
                'description' =>esc_html(__('Enable this shipping method', 'flagship-shipping-extension-for-woocommerce')),
                'default' => 'no'
            ),
            'test_env' => array(
                'title' => esc_html(__('Enable Test Environment', 'flagship-shipping-extension-for-woocommerce')),
                'type' => 'checkbox',
                'description' => esc_html(__('Use '.$this->white_label_title.'\'s test environment. Any shipments made in the test environment will not be shipped', 'flagship-shipping-extension-for-woocommerce')),
                'default' => 'no'
            ),
            'token' => array(
                'title' => esc_html(__(''.$this->white_label_title.' access token', 'flagship-shipping-extension-for-woocommerce')),
                'type' => 'password',
                'description' => sprintf(__('After <a href="%s" target="_blank">signup </a>, <a target="_blank" href="%s">get an access token here </a>.', 'flagship-shipping-extension-for-woocommerce'), 'https://www.flagshipcompany.com/sign-up/', 'https://auth.smartship.io/tokens/'),
            ),
            'tracking_emails' => array(
                'title' => esc_html(__('Tracking emails', 'flagship-shipping-extension-for-woocommerce')),
                'type' => 'text',
                'description' => esc_html(__('The emails (separated by ;) to receive tracking information of shipments.', 'flagship-shipping-extension-for-woocommerce')),
            ),
            'send_tracking_emails' => array(
                'title' => esc_html(__('Send tracking emails', 'flagship-shipping-extension-for-woocommerce')),
                'description' => esc_html(__('If checked, customers will receive the tracking emails of a shipment.', 'flagship-shipping-extension-for-woocommerce')),
                'type' => 'checkbox',
                'default' => 'no',
            ),
            'estimated_delivery_date' => array(
                'title' => esc_html(__('Show estimated delivery date', 'flagship-shipping-extension-for-woocommerce')),
                'type' => 'checkbox',
                'default' => 'no',
            ),
            'flagship_insurance' => array(
                'title' => esc_html(__('Insurance', 'flagship-shipping-extension-for-woocommerce')),
                'label' => esc_html(__('Add Insurance', 'flagship-shipping-extension-for-woocommerce')),
                'type' => 'checkbox',
                'default' => 'no',
            ),
            'box_split' => array(
                'title' => esc_html(__('Box split', 'flagship-shipping-extension-for-woocommerce')),
                'type' => 'radio',
                'description' => esc_html(__('If enabled, errors will be displayed in the pages showing shipping rates', 'flagship-shipping-extension-for-woocommerce')),
                'default' => 'one_box',
                'options' => array(
                    'one_box' => 'Everything in one box',
                    'box_per_item' => 'One box per item',
                    'by_weight' => 'Split by weight',
                    'packing_api' => esc_html(__('Use automated Packing API to pack items into','flagship-shipping-extension-for-woocommerce')),
                ),
                'extra_note' =>  array(
                    'packing_api' => sprintf('<a href="%s" target="_blank">%s</a>', admin_url('admin.php?page=flagship/boxes'), __('Boxes', 'flagship-shipping-extension-for-woocommerce')),
                ),
            ),
            'box_split_weight' => array(
                'title' =>esc_html(__('Box split weight', 'flagship-shipping-extension-for-woocommerce')),
                'type' => 'decimal',
                'description' => esc_html(__("Maximum weight in each box (only used when 'Split by weight' is chosen for box split.", 'flagship-shipping-extension-for-woocommerce')),
                'css' => 'width:70px;',
            ),
            'debug_mode' => array(
                'title' => esc_html(__('Debug mode', 'flagship-shipping-extension-for-woocommerce')),
                'label' => esc_html(__('Enable debug mode', 'flagship-shipping-extension-for-woocommerce')),
                'type' => 'checkbox',
                'description' => esc_html(__('If enabled, errors will be displayed in the pages showing shipping rates', 'flagship-shipping-extension-for-woocommerce')),
                'default' => 'no'
            ),
            'autocomplete_order' => array(
                'title' => esc_html(__('Auto Complete "Processing" Orders', 'flagship-shipping-extension-for-woocommerce')),
                'label' => esc_html(__('Auto complete "Processing" orders when '.$this->white_label_title.' shipment is confirmed', 'flagship-shipping-extension-for-woocommerce')),
                'type' => 'checkbox',
                'description' => esc_html(__('If enabled, "Processing" order will be automatically set to "Completed" when '.$this->white_label_title.' Shipment is confirmed', 'flagship-shipping-extension-for-woocommerce')),
                'default' => 'no'
            ),
        );
    }

    protected function makeInstanceFields()
    {
        $ecommerceApplicable = $this->isInstanceForEcommerce(\WC_Shipping_Zones::get_zone_by('instance_id', $this->instance_id)->get_zone_locations());

        $fields = array(
            'shipping_rates_configs' => array(
                'title' => esc_html(__('Rates', 'flagship-shipping-extension-for-woocommerce')),
                'type' => 'title',
            ),
            'allow_standard_rates' => array(
                'title' => esc_html(__('Offer standard rates', 'flagship-shipping-extension-for-woocommerce')),
                'type' => 'checkbox',
                'default' => 'yes'
            ),
            'allow_express_rates' => array(
                'title' => esc_html(__('Offer express rates', 'flagship-shipping-extension-for-woocommerce')),
                'type' => 'checkbox',
                'default' => 'yes'
            ),
            'offer_dhl_ecommerce_rates' => array(
                'title' => esc_html(__('Offer DHL ecommerce rates', 'flagship-shipping-extension-for-woocommerce')),
                'type' => 'checkbox',
                'description' => esc_html(__('Available for international destinations when package is less than 2kg', 'flagship-shipping-extension-for-woocommerce')),
                'default' => 'no'
            ),
            'only_show_cheapest' => array(
                'title' => esc_html(__('Only show the cheapest rate', 'flagship-shipping-extension-for-woocommerce')),
                'type' => 'checkbox',
                'default' => 'no'
            ),
            
            'shipping_markup' => array(
                'title' => esc_html(__('Markup', 'flagship-shipping-extension-for-woocommerce')),
                'type' => 'title',
                'description' => esc_html(__('Store owner may apply additional fee for shipping.', 'flagship-shipping-extension-for-woocommerce')),
            ),
            'shipping_cost_markup_percentage' => array(
                'title' => esc_html(__('Shipping cost markup (%)', 'flagship-shipping-extension-for-woocommerce')),
                'type' => 'decimal',
                'description' => esc_html(__('Shipping cost markup in percentage', 'flagship-shipping-extension-for-woocommerce')),
                'default' => 0
            ),
            'shipping_cost_markup_flat' => array(
                'title' => esc_html(__('Shipping cost markup in flat fee ($)', 'flagship-shipping-extension-for-woocommerce')),
                'type' => 'decimal',
                'description' => esc_html(__('Shipping cost markup in flat fee (this will be applied after the percentage markup)', 'flagship-shipping-extension-for-woocommerce')),
                'default' => 0
            ),
            'shipping_options' => array(
                'title' => esc_html(__('Shipping Options', 'flagship-shipping-extension-for-woocommerce')),
                'type' => 'title',
            ),
            'show_transit_time' => array(
                'title' => esc_html(__('Show transit time in shopping cart', 'flagship-shipping-extension-for-woocommerce')),
                'description' => esc_html(__('If checked, the transit times of couriers will be shown', 'flagship-shipping-extension-for-woocommerce')),
                'type' => 'checkbox',
                'default' => 'no',
            ),
            'signature_required' => array(
                'title' => esc_html(__('Signature required on delivery', 'flagship-shipping-extension-for-woocommerce')),
                'description' => esc_html(__('If checked, all the shipments to this shipping zone will be signature required on delivery', 'flagship-shipping-extension-for-woocommerce')),
                'type' => 'checkbox',
                'default' => 'no',
            ),
            'residential_receiver_address' => array(
                'title' => esc_html(__('Residential receiver address', 'flagship-shipping-extension-for-woocommerce')),
                'description' => esc_html(__('If checked, all the receiver addresses in this shipping zone will be considered residential', 'flagship-shipping-extension-for-woocommerce')),
                'type' => 'checkbox',
                'default' => 'no',
            ),
        );

        $disableCourierOptions = $this->makeDisableCourierOptions(FlagshipWoocommerceBedrockShipping::$couriers, $ecommerceApplicable);
        $fields = array_slice($fields, 0, 5, true) +
           $disableCourierOptions +
            array_slice($fields, 5, null, true);

        if (!$ecommerceApplicable) {
            unset($fields['offer_dhl_ecommerce_rates']);
        }

        $fields = array_merge($fields, $this->makeShippingClassSettings());

        $fields = array_slice($fields, 0, 10, true) + $this->makeDropShippingAddressFields() + array_slice($fields, 10, null, true);

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
            'title'       => esc_html(__('Shipping class costs', 'woocommerce')),
            'type'        => 'title',
            'default'     => '',
            'description' => sprintf(__('These costs can optionally be added based on the <a href="%s">product shipping class</a>.', 'woocommerce') . ' ' . __('This cost will be applied only once per shipment, regardless of the number of products belonging to that shipping class.', 'flagship-shipping-extension-for-woocommerce'), admin_url('admin.php?page=wc-settings&tab=shipping&section=classes')),
        );

        foreach ($shipping_classes as $shipping_class) {
            if (!isset($shipping_class->term_id)) {
                continue;
            }

            $settings[ 'class_cost_' . $shipping_class->term_id ] = array(
                'title'             => sprintf(__('"%s" shipping class cost', 'woocommerce'), esc_html($shipping_class->name)),
                'type'              => 'decimal',
                'placeholder'       => __('N/A', 'woocommerce'),
                'description'       => 'shipping class cost',
                'default'           => $this->get_option('class_cost_' . $shipping_class->slug),
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

    protected function makeDropShippingAddressFields()
    {
        $addressFieldsOptions = [];
        $addressFieldsOptions['dropshipping_address'] = array(
                'title' => esc_html(__('Dropship Address', 'flagship-shipping-extension-for-woocommerce')),
                'type' => 'title',
                'description' => esc_html(__('Store owner may ship from a warehouse')),
            );
        $addressFields = FlagshipWoocommerceBedrockShipping::$dropShippingAddressFields;
        foreach ($addressFields as $key => $addressField) {
            $label = sprintf(__('Shipper %s', 'flagship-shipping-extension-for-woocommerce'), $addressField);
            $addressFieldsOptions['dropshipping_address_'.$key] = array(
                'title' => __($label, 'flagship-shipping-extension-for-woocommerce'),
                'type' => $key == 'state' ? 'select' : 'text',
                'options' => $this->getStates()
            );
        }
        return $addressFieldsOptions;
    }

    protected function getStates()
    {
        return [
            "AB" => "Alberta",
            "BC" => "British Columbia",
            "MB" => "Manitoba",
            "NB" => "New Brunswick",
            "NL" => "Newfoundland and Labrador",
            "NT" => "Northwest Territories",
            "NS" => "Nova Scotia",
            "NU" => "Nunavut",
            "ON" => "Ontario",
            "PE" => "Prince Edward Island",
            "QC" => "Quebec",
            "SK" => "Saskatchewan",
            "YT" => "Yukon",
        ];
    }

    protected function makeDisableCourierOptions($couriers, $isInternationalZone = false)
    {
        $disableCourierOptions = array();

        if (!$isInternationalZone) {
            unset($couriers['DHL']);
        }

        foreach ($couriers as $key => $value) {
            $settingName = 'disable_courier_'.$value;
            $settingLabel = sprintf(__('Disable %s rates', 'flagship-shipping-extension-for-woocommerce'), $key);
            $disableCourierOptions[$settingName] = array(
                'title' => esc_html(__($settingLabel, 'flagship-shipping-extension-for-woocommerce')),
                'type' => 'checkbox',
                'default' => 'no',
            );
        }

        return $disableCourierOptions;
    }
}
