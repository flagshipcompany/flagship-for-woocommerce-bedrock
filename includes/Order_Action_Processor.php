<?php
namespace FlagshipWoocommerceBedrock;

use FlagshipWoocommerceBedrock\Requests\Export_Order_Request;
use FlagshipWoocommerceBedrock\Requests\Get_Shipment_Request;
use FlagshipWoocommerceBedrock\Requests\Rates_Request;
use FlagshipWoocommerceBedrock\Helpers\Menu_Helper;
use FlagshipWoocommerceBedrock\REST_Controllers\Package_Box_Controller;

class Order_Action_Processor {

    public static $shipmentIdField = 'flagship_shipping_shipment_id';
    public static $exportOrderActionName = 'export_to_flagship';
    public static $getAQuoteActionName = 'get_a_quote';
    public static $confirmShipmentActionName = 'confirm_shipment';
    public static $updateShipmentActionName = 'update_shipment';

    private $order;
    private $pluginSettings;
    private $errorMessages = array();
    private $errorCodes = array(
        'shipment_exists' => 401,
        'token_missing' => 402,
    );

    public function __construct($order, $pluginSettings, $debug_mode = false)
    {
        $this->order = $order;
        $this->pluginSettings = $this->set_settings($pluginSettings);
        $this->debug_mode = $debug_mode;
    }

    public function addMetaBoxes()
    {
        $shipmentId = $this->getShipmentIdFromOrder($this->order->get_id());

        if (!$shipmentId && $this->eCommerceShippingChosen($this->order->get_shipping_methods())) {
            add_meta_box( 'flagship_ecommerce_shipping', esc_html(__('FlagShip eCommerce Shipping','flagship-shipping-extension-for-woocommerce')), array($this, 'addECommerceBox'), 'shop_order', 'side', 'default');
        }

        add_meta_box( 'flagship_shipping', esc_html(__('FlagShip Shipping','flagship-shipping-extension-for-woocommerce')), array($this, 'addFlagshipMetaBox'), 'shop_order', 'side', 'default', array($shipmentId));

        add_meta_box( 'flagship_shipping_boxes_used', esc_html(__('FlagShip Shipping Boxes Used','flagship-shipping-extension-for-woocommerce')), array($this, 'addFlagshipBoxesMetaBox'), 'shop_order', 'side', 'default');
    }

    public function addECommerceBox($post)
    {
        echo sprintf('<p>%s.<br>%s, <a href="%s" target="_blank">%s</a>.
        </p>', esc_html(__('This order was fulfilled with our DHL Ecommerce service. This order will need to be bundled together in a bundled shipment in order to be fulfilled by the courier', 'flagship-shipping-extension-for-woocommerce')), esc_html(__('For more information about our DHL Ecommerce service', 'flagship-shipping-extension-for-woocommerce')), 'https://www.flagshipcompany.com/dhl-international-ecommerce/', esc_html(__('Click here', 'flagship-shipping-extension-for-woocommerce')));
    }

    public function addFlagshipBoxesMetaBox($post)
    {
        $boxes = get_post_meta($this->order->get_id(),'boxes');
        if(count($boxes) == 0)
        {
            $boxesUsed = "Get a Quote from FlagShip to see which shipping boxes will be used";
            echo sprintf("<p>%s</p>", esc_html(__($boxesUsed)));
            return;
        }

        $boxes_data = Package_Box_Controller::get_boxes();
        if($boxes_data == null)
        {
            echo sprintf("<p>%s</p>",esc_html(__("No package boxes available")));
            return;
        }

        $boxes = reset($boxes);
        $boxesUsed = implode("<br/>", $boxes);
        echo sprintf('<p>%s</p>',esc_html(__($boxesUsed)));
    }

    public function addFlagshipMetaBox($post, $box)
    {
        $shipmentId = $box['args'][0];
        $rates = null;
        if ($shipmentId) {
            $shipment = $this->getShipmentFromFlagship($shipmentId);
            $shipmentStatus = $shipment->getStatus();
            $shipmentUrl = $shipmentStatus ? $this->makeShipmentUrl($shipmentId, $shipmentStatus) : null;
            $statusDescription = $this->getShipmentStatusDesc($shipmentStatus);
            $flagshipUrl = $this->getFlagshipUrl();

        }

        if (!empty($shipmentUrl)) {

            echo sprintf('<p>%s: <a href="%s" target="_blank">%d</a> <strong>[%s]</strong></p>', esc_html(__('FlagShip Shipment', 'flagship-shipping-extension-for-woocommerce')), $shipmentUrl, $shipmentId, $statusDescription);

            $this->getFlagshipShippingMetaBoxContent($statusDescription, $flagshipUrl, $shipment);

            return;
        }


        if ($shipmentId && empty($shipmentUrl)) {
            echo sprintf('<p>%s.</p>', esc_html(__('Please check the FlagShip token', 'flagship-shipping-extension-for-woocommerce')));
            return;
        }

        if($rates == null)
        {
            $buttonDisabled = (new Export_Order_Request(null))->isOrderShippingAddressValid($this->order) ? '' : 'disabled';
            echo sprintf('<button type="submit" class="button save_order button-primary" name="%s" value="export" '.$buttonDisabled.'>%s </button>', self::$exportOrderActionName, esc_html(__('Send to FlagShip', 'flagship-shipping-extension-for-woocommerce')));
        }
    }

    public function order_custom_warning_filter($location)
    {
        $warning = array_pop($this->errorMessages);
        $location = add_query_arg(array('flagship_warning' => $warning), $location);

        return $location;
    }

    public function processOrderActions($request)
    {
        global $post;
        $order_id = $post->ID;
        $flagship_shipment_id = get_post_meta($order_id, 'flagship_shipping_shipment_id');
        
        if ($request[self::$exportOrderActionName] == 'export') {
            $this->prepareFlagshipShipment();
        }

        if(isset($request[self::$getAQuoteActionName]) && $request[self::$getAQuoteActionName] == 'quote'){
           $this->getPackages();
           $this->getRates();
           return;
        }

        if(isset($request[self::$confirmShipmentActionName])&& stripos($request[self::$confirmShipmentActionName],"confirm") == 0)
        {
            // wordpress order ID
            $orderId = $this->order->get_id();
            $shipmentId = $this->getShipmentIdFromOrder($orderId);
            $token = get_array_value($this->pluginSettings,'token');
            $testEnv = get_array_value($this->pluginSettings,'test_env') == 'no' ? 0 : 1;
            $exportOrder = new Export_Order_Request($token, $testEnv);

            $flagshipShipment = $this->getShipmentFromFlagship($shipmentId);
            $courierDetails = $request['flagship_service'];

            $flagshipShipment = $this->updateShipmentWithCourierDetails($exportOrder,$flagshipShipment,$courierDetails);

            $confirmedShipment = $this->confirmFlagshipShipment($exportOrder,$shipmentId);

            if(!is_string($confirmedShipment)){
                do_action( 'fwb_shipment_is_confirmed', $confirmedShipment);
                update_post_meta($orderId, 'flagship_shipping_shipment_tracking_number', $confirmedShipment->getTrackingNumber());
                update_post_meta($orderId, 'flagship_shipping_courier_name', $confirmedShipment->getCourierName());
                update_post_meta($orderId, 'flagship_shipping_courier_service_code', $confirmedShipment->getCourierCode());    
            }

            return;
        }

        
    }

    protected function prepareFlagshipShipment() {
        try{
                $exportedShipment = $this->exportOrder();
                do_action( 'fwb_shipment_is_exported', $exportedShipment);

            }
            catch(\Exception $e){
                $this->setErrorMessages(esc_html(__('Order not exported to FlagShip')).': '.$e->getMessage());
                add_filter('redirect_post_location', array($this, 'order_custom_warning_filter'));
            }
    }

    protected function getFlagshipShippingMetaBoxContent($statusDescription, $flagshipUrl, $shipment)
    {
        if ($statusDescription != 'Dispatched') {
            $rates = get_post_meta($this->order->get_id(),'rates');

            $ratesDropdownHtml = $this->getRatesDropDownHtml($rates);

           $this->createGetQuoteButton($ratesDropdownHtml);

            echo sprintf('<br/><br/><button type="submit" class="button save_order button-primary" name="%s" value="%s">%s</button>',self::$confirmShipmentActionName,self::$confirmShipmentActionName,esc_html(__('Confirm Shipment','flagship-shipping-extension-for-woocommerce')));
        }

        if ($statusDescription == 'Dispatched') {
            echo sprintf('<a href="'.$flagshipUrl.'/shipping/'.$shipment->getId().'/overview" class="button button-primary" target="_blank"> View Shipment on FlagShip</a>');
            echo sprintf('<br/><br/><a href="'.$shipment->getThermalLabel().'" class="button button-primary" target="_blank"> Get Shipment Label</a>');

            return;
        }
    }

    protected function createGetQuoteButton($ratesDropdownHtml)
    {
        if(empty($ratesDropdownHtml)){
            echo sprintf('&nbsp;&nbsp;<button type="submit" class="button save_order button-primary" name="%s" value="quote">%s </button>', self::$getAQuoteActionName, esc_html(__('Get a Quote', 'flagship-shipping-extension-for-woocommerce')));
            return;
        }

        echo sprintf('&nbsp;&nbsp;<button type="submit" class="button save_order button-primary" name="%s" value="quote">%s </button>', self::$getAQuoteActionName, esc_html(__('Requote', 'flagship-shipping-extension-for-woocommerce')));
        return;
    }

    protected function getRatesDropDownHtml($rates)
    {
        $ratesDropdownHtml = '';
        if($rates != null)
        {
            $ratesDropdownHtml = $this->getRatesDropDown($rates);
            echo '<select id="flagship-rates" style="width:100%" name="flagship_service">'.$ratesDropdownHtml.'</select><br/><br/>';
        }
        return $ratesDropdownHtml;
    }


    protected function getFlagshipUrl()
    {
        $token = get_array_value($this->pluginSettings,'token');
        $testEnv = get_array_value($this->pluginSettings,'test_env') == 'no' ? 0 : 1;
        $exportOrder = new Export_Order_Request($token, $testEnv);
        $url = $exportOrder->getFlagshipUrl();
        return $url;
    }

    protected function confirmFlagshipShipment($exportOrder,int $shipmentId)
    {
        $confirmedShipment = $exportOrder->confirmShipment($shipmentId);
        if(is_string($confirmedShipment)){
            $this->setErrorMessages(esc_html(__($confirmedShipment)));
            add_filter('redirect_post_location',array($this,'order_custom_warning_filter'));
        }

        if (!is_string($confirmedShipment)
            && !is_null($confirmedShipment->getTrackingNumber())
            && $this->order->get_status() == 'processing'
            && get_array_value($this->pluginSettings,'autocomplete_order') == 'yes') {
                $this->order->update_status('completed');
        }
        return $confirmedShipment;
    }

    protected function updateShipmentWithCourierDetails($exportOrder,$flagshipShipment,$courierDetails)
    {
        $prepareRequest = $exportOrder->makePrepareRequest($this->order,$this->pluginSettings);
        $courierCode = substr($courierDetails,0, strpos($courierDetails, "-"));
        $courierName = substr($courierDetails,strpos($courierDetails,"-")+1);

        $service = [
            "courier_code" => $courierCode,
            "courier_name" => $courierName
        ];
        
        //making sure we adapt the date in case it was quoted/exported the day before.
        $updateRequest["options"]['shipping_date'] = date('Y-m-d');
        
        $updateRequest["service"] = $service;
        $updatedShipment = $exportOrder->editShipment($this->order,$flagshipShipment,$prepareRequest,$updateRequest,$this->pluginSettings);

        if(is_string($updatedShipment)){
            $this->setErrorMessages(esc_html(__($updatedShipment)));
            add_filter('redirect_post_location',array($this,'order_custom_warning_filter'));
        }

        return $updatedShipment;
    }

    protected function getShipmentFromFlagship($shipmentId)
    {
        $token = get_array_value($this->pluginSettings,'token');
        $testEnv = get_array_value($this->pluginSettings,'test_env') == 'no' ? 0 : 1;
        $request = new Get_Shipment_Request($token,$testEnv);
        $shipment = $request->getShipmentById($shipmentId);
        if(is_string($shipment)){
            $this->setErrorMessages(esc_html(__($shipment)));
            add_filter('redirect_post_location',array($this,'order_custom_warning_filter'));
        }
        return $shipment;
    }

    protected function getRatesDropDown(array $rates)
    {
        $ratesDropDown = '';

        $rates = reset($rates);
        foreach ($rates as $rate) {
            $ratesDropDown .= '<option value="'.$rate["option_value"].'">'.$rate["option_name"].'</option>';
        }
        return $ratesDropDown;
    }

    protected function getPackages()
    {
        $token = get_array_value($this->pluginSettings, 'token');
        $testEnv = get_array_value($this->pluginSettings,'test_env') == 'no'?0:1;
        $ratesRequest = new Rates_Request($token, false, $testEnv);
        $orderItems = $ratesRequest->getOrderItems($this->order);
        $packages = $ratesRequest->getPackages($orderItems,$this->pluginSettings);

        if($packages != null)
        {
            $boxes = [];
            foreach ($packages["items"] as $package) {
                    $boxes[] = $package["description"];
            }
            update_post_meta($this->order->get_id(),'boxes',$boxes);
        }
    }

    protected function getRates()
    {
        $token = get_array_value($this->pluginSettings, 'token');
        $testEnv = $this->pluginSettings['test_env'] == 'no' ? 0 : 1;

        $ratesRequest = new Rates_Request($token,false,$testEnv);

        $rates = $ratesRequest->getRates([], $this->pluginSettings,1,$this->order);

        if(is_string($rates))
        {
            $this->setErrorMessages(esc_html(__('Unable to get rates from FlagShip')).$rates);
            add_filter('redirect_post_location', array($this, 'order_custom_warning_filter'));
            return;
        }

        $rates = $rates->sortByPrice();
        $percentageMarkup = get_array_value($this->pluginSettings, 'shipping_cost_markup_percentage');
        $flatFeeMarkup = get_array_value($this->pluginSettings, 'shipping_cost_markup_flat');
        $ratesDropDown = [];
        foreach ($rates as $rate) {
            $courierName = strcasecmp($rate->getCourierName(),'FedEx') === 0 ? 'FedEx '.$rate->getCourierDescription() : $rate->getCourierDescription();
            $price = $rate->getTotal();

            $ratesDropDown[] = [
                "option_value" => $rate->getServiceCode().'-'.$rate->getCourierName(),
                "option_name" => $price. ' - '.$courierName
            ];
        }
        update_post_meta($this->order->get_id(),'rates', apply_filters('fwb_get_rates_admin_dropdown', $ratesDropDown));
        return;
    }

    protected function set_settings($settings) {
        $instance_id = $this->get_instance_id($this->order->get_address('shipping'));

        if ($instance_id) {
            $instance_option_key = 'woocommerce_'.FlagshipWoocommerceBedrockShipping::$methodId.'_'.$instance_id.'_settings';
            $instance_settings = get_option($instance_option_key);
            $settings = array_merge($settings, $instance_settings);
        }

        return $settings;
    }

    protected function get_instance_id($shipping_address)
    {
        $fake_package = [];
        $fake_package['destination'] = [
            'country' => isset($shipping_address['country']) ? $shipping_address['country'] : null,
            'state' => isset($shipping_address['state']) ? $shipping_address['state'] : null,
            'postcode' => isset($shipping_address['postcode']) ? $shipping_address['postcode'] : null,
        ];

        $data_store = \WC_Data_Store::load( 'shipping-zone' );
        $zone_id = $data_store->get_zone_id_from_package($fake_package);
        $shipping_methods = (new \WC_Shipping_Zone($zone_id))->get_shipping_methods();
        $filtered_methods = array_filter($shipping_methods, function($method) {
            return $method->id == FlagshipWoocommerceBedrockShipping::$methodId && $method->is_enabled();
        });

        if (count($filtered_methods) == 0) {
            return;
        }

        return reset($filtered_methods)->instance_id;
    }

    protected function setErrorMessages($message, $clearOldMessages = true)
    {
        if ($clearOldMessages) {
            $this->errorMessages = array();
        }

        $this->errorMessages[] = $message;
    }

    protected function getShipmentIdFromOrder($orderId)
    {
        $orderMeta = get_post_meta($orderId);

        if (!isset($orderMeta[self::$shipmentIdField])) {
            return;
        }

        return reset($orderMeta[self::$shipmentIdField]);
    }

    protected function exportOrder()
    {
        if ($this->getShipmentIdFromOrder($this->order->get_id())) {
            throw new \Exception(esc_html(__('This order has already been exported to FlagShip', 'flagship-shipping-extension-for-woocommerce')), $this->errorCodes['shipment_exists']);
        }

        $token = get_array_value($this->pluginSettings, 'token');

        if (!$token) {
            throw new \Exception(esc_html(__('FlagShip API token is missing', 'flagship-shipping-extension-for-woocommerce')), $this->errorCodes['token_missing']);
        }
        $testEnv = get_array_value($this->pluginSettings,'test_env') == 'no' ? 0 : 1;
        $apiRequest = new Export_Order_Request($token, $testEnv);
        $exportedShipment = $apiRequest->exportOrder($this->order, $this->pluginSettings);

        if (is_string($exportedShipment)) {
            $this->setErrorMessages(esc_html(__('Order not exported to FlagShip')).': '.$exportedShipment);
            add_filter('redirect_post_location', array($this, 'order_custom_warning_filter'));
            return;
        }

        update_post_meta($this->order->get_id(), self::$shipmentIdField, $exportedShipment->getId());

        return $exportedShipment;
    }

    protected function eCommerceShippingChosen($shippingMethods)
    {
        $eCommerceRates = array_filter($shippingMethods, function($val) {
            $methodTitleArr = explode('-', $val->get_method_title());

            return isset($methodTitleArr[0]) && trim($methodTitleArr[0]) == 'dhlec';
        });

        return count($eCommerceRates) > 0;
    }

    protected function getShipmentStatus($shipmentId)
    {
        $token = get_array_value($this->pluginSettings, 'token');

        if (!$token) {
            return;
        }
        $testEnv = get_array_value($this->pluginSettings,'test_env') == 'no' ? 0 : 1;
        $apiRequest = new Get_Shipment_Request($token, $testEnv);

        try{
            $shipment = $apiRequest->getShipmentById($shipmentId);
            return $shipment->getStatus();
        }
        catch(\Exception $e){
            return $e->getMessage();
        }
    }

    protected function makeShipmentUrl($shipmentId, $status)
    {
        $flagshipPageUrl = menu_page_url(Menu_Helper::$menuItemUri, false);

        if (in_array($status, array('dispatched',
            'manifested', 'cancelled'))) {
            return sprintf('%s&flagship_uri=shipping/%d/overview', $flagshipPageUrl, $shipmentId);
        }

        return sprintf('%s&flagship_uri=shipping/%d/convert', $flagshipPageUrl, $shipmentId);
    }

    protected function getShipmentStatusDesc($status)
    {
        if (in_array($status, array('dispatched',
            'manifested'))) {
            return esc_html(__('Dispatched', 'flagship-shipping-extension-for-woocommerce'));
        }

        if (in_array($status, array('prequoted',
            'quoted'))) {
            return esc_html(__('NOT dispatched', 'flagship-shipping-extension-for-woocommerce'));
        }

        return esc_html(__($status, 'flagship-shipping-extension-for-woocommerce'));
    }
}
