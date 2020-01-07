<?php
namespace FlagshipWoocommerce;

use FlagshipWoocommerce\Requests\Export_Order_Request;

class Order_Action_Processor {

    public static $shipmentIdField = 'wc_flagship_shipment_id';

    public static $exportOrderActionName = 'export_to_flagship';

    private $order;

    private $pluginSettings;

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
            $shipmentUrl = sprintf('%s/shipping/%d/convert', FlagshipWoocommerceShipping::getFlagshipUrl(), $shipmentId);

            echo sprintf('<p>%s: <a href="%s" target="_blank">%d</a></p>', __('This order has already been exported to FlagShip', 'flagship-woocommerce-extension'), $shipmentUrl, $shipmentId);

            return;
        }

        echo sprintf('<button type="submit" class="button save_order button-primary" name="%s" value="1">%s </button>', self::$exportOrderActionName, __('Export to FlagShip', 'flagship-woocommerce-extension'));
    }

    public function processOrderActions($request)
    {        
        if (!isset($request[self::$exportOrderActionName]) || $request[self::$exportOrderActionName] != 1) {
            return;
        }

        $this->exportOrder();
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
        $shipmentId = $this->getShipmentIdFromOrder($this->order->get_id());

        if ($shipmentId) {
            return;
        }

        $token = isset($this->pluginSettings['token']) ? $this->pluginSettings['token'] : null;

        if (!$token) {
            return;
        }

        $apiRequest = new Export_Order_Request($token);
        $exportedShipment = $apiRequest->exportOrder($this->order);

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
}