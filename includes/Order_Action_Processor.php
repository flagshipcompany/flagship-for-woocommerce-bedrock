<?php
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

    public function addMetaBox()
    {
        if (!$this->getShipmentIdFromOrder($this->order->get_id()) && !(new Export_Order_Request(null))->isOrderShippingAddressValid($this->order)) {
            return;
        }

        add_meta_box( 'flagship_shipping', __('FlagShip Shipping','flagship'), array($this, 'addFlagshipMetaBox'), 'shop_order', 'side', 'core' );
    }

    public function addFlagshipMetaBox($post)
    {        
        $shipmentId = $this->getShipmentIdFromOrder($post->ID);

        if ($shipmentId) {
            $shipmentUrl = sprintf('%s/shipping/%d/convert', FlagshipWoocommerceShipping::getFlagshipUrl(), $shipmentId);

            echo sprintf('<p>This order has already been exported to FlagShip: <a href="%s" target="_blank">%d</a></p>', $shipmentUrl, $shipmentId);

            return;
        }

        echo '<button type="submit" class="button save_order button-primary" name="'.self::$exportOrderActionName.'" value="1"> Export to FlagShip</button>';
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
}