<?php
namespace FlagshipWoocommerce;

use FlagshipWoocommerce\Requests\Export_Order_Request;
use FlagshipWoocommerce\Requests\Get_Shipment_Request;

class Order_Action_Processor {

    public static $shipmentIdField = 'flagship_shipping_shipment_id';

    public static $exportOrderActionName = 'export_to_flagship';

    private $order;

    private $pluginSettings;

    private $errorMessages = array();

    private $errorCodes = array(
        'shipment_exists' => 401,
        'token_missing' => 402,
    );

    public function __construct($order, $pluginSettings) 
    {
        $this->order = $order;
        $this->pluginSettings = $pluginSettings;
    }

    public function addMetaBoxes()
    {
        $shipmentId = $this->getShipmentIdFromOrder($this->order->get_id());

        if (!$shipmentId && $this->eCommerceShippingChosen($this->order->get_shipping_methods())) {
            add_meta_box( 'flagship_ecommerce_shipping', __('FlagShip eCommerce Shipping','flagship-woocommerce-extension'), array($this, 'addECommerceBox'), 'shop_order', 'side', 'default');
        }

        if ($shipmentId || (new Export_Order_Request(null))->isOrderShippingAddressValid($this->order)) {            
            add_meta_box( 'flagship_shipping', __('FlagShip Shipping','flagship-woocommerce-extension'), array($this, 'addFlagshipMetaBox'), 'shop_order', 'side', 'default', array($shipmentId));            
        }
    }

    public function addECommerceBox($post)
    {        
        echo sprintf('<p>%s.<br>%s, <a href="%s" target="_blank">%s</a>.                        
        </p>', __('This order was fulfilled with our DHL Ecommerce service. This order will need to be bundled together in a bundled shipment in order to be fulfilled by the courier', 'flagship-woocommerce-extension'), __('For more information about our DHL Ecommerce service', 'flagship-woocommerce-extension'), 'https://www.flagshipcompany.com/dhl-international-ecommerce/', __('Click here', 'flagship-woocommerce-extension'));
    }

    public function addFlagshipMetaBox($post, $box)
    {
        $shipmentId = $box['args'][0];

        if ($shipmentId) {
            $shipmentStatus = $this->getShipmentStatus($shipmentId);
            $shipmentUrl = $shipmentStatus ? $this->makeShipmentUrl($shipmentId, $shipmentStatus) : null;
        }

        if (!empty($shipmentUrl)) {
            $statusDescription = $this->getShipmentStatusDesc($shipmentStatus);

            echo sprintf('<p>%s: <a href="%s">%d</a> <strong>[%s]</strong></p>', __('This order has already been exported to FlagShip', 'flagship-woocommerce-extension'), $shipmentUrl, $shipmentId, $statusDescription);

            return;
        }

        if ($shipmentId && empty($shipmentUrl)) {
            echo sprintf('<p>%s.</p>', __('Please check the FlagShip token', 'flagship-woocommerce-extension'));

            return;
        }

        echo sprintf('<button type="submit" class="button save_order button-primary" name="%s" value="1">%s </button>', self::$exportOrderActionName, __('Export to FlagShip', 'flagship-woocommerce-extension'));
    }

    public function processOrderActions($request)
    {        
        if (!isset($request[self::$exportOrderActionName]) || $request[self::$exportOrderActionName] != 1) {
            return;
        }

        try{
            $this->exportOrder();
        }
        catch(\Exception $e){
            if (in_array($e->getCode(), $this->errorCodes)) {
                $this->setErrorMessages(__('Order not exported to FlagShip').': '.$e->getMessage());
                add_filter('redirect_post_location', array($this, 'order_custom_warning_filter'));   
            }     
        }
    }

    public function order_custom_warning_filter($location)
    {
        $warning = array_pop($this->errorMessages);
        $location = add_query_arg(array('flagship_warning' => $warning), $location);

        return $location;
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
            throw new \Exception(__('This order has already been exported to FlagShip', 'flagship-woocommerce-extension'), $this->errorCodes['shipment_exists']);
        }

        $token = get_array_value($this->pluginSettings, 'token');

        if (!$token) {
            throw new \Exception(__('FlagShip API token is missing', 'flagship-woocommerce-extension'), $this->errorCodes['token_missing']);
        }

        $apiRequest = new Export_Order_Request($token);
        $exportedShipment = $apiRequest->exportOrder($this->order, $this->pluginSettings);

        if ($exportedShipment) {
            update_post_meta($this->order->get_id(), self::$shipmentIdField, $exportedShipment->getId());
        }
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

        $apiRequest = new Get_Shipment_Request($token);

        try{
            $shipment = $apiRequest->getShipmentById($shipmentId);
        }
        catch(\Exception $e){
            return;         
        }

        return $shipment->getStatus();
    }

    protected function makeShipmentUrl($shipmentId, $status)
    {
        $flagshipPageUrl = menu_page_url('flagship', false);

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
            return __('Dispatched', 'flagship-woocommerce-extension');
        }

        if (in_array($status, array('prequoted', 
            'quoted'))) {
            return __('NOT dispatched', 'flagship-woocommerce-extension');
        }

        return __($status, 'flagship-woocommerce-extension');
    }
}