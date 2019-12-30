<?php
class Order_Action_Processor {

    public static $shipmentIdField = 'wc_flagship_shipment_id';

    private $order;

    private $pluginSettings;

    public function __construct($order, $pluginSettings) 
    {
        $this->order = $order;
        $this->pluginSettings = $pluginSettings;
    }

    public function addExportAction($actions)
    {
        $orderMeta = get_post_meta($this->order->get_id());

        if (isset($orderMeta[self::$shipmentIdField]) || !(new Export_Order_Request(null))->isOrderShippingAddressValid($this->order)) {
            return $actions;
        }

        $actions[FlagshipWoocommerceShipping::$exportAction] = __('Export to FlagShip', 'flagship');

        return $actions;
    }

    public function exportOrder()
    {
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

    public function addMetaBoxes()
    {
        if (!$this->getShipmentIdFromOrder($this->order->get_id())) {
            return;
        }

        add_meta_box( 'mv_other_fields', __('FlagShip Shipping','flagship'), array($this, 'addShipmentIdBox'), 'shop_order', 'side', 'core' );
    }

    public function addShipmentIdBox($post)
    {        
        $shipmentId = $this->getShipmentIdFromOrder($post->ID);
        $shipmentUrl = sprintf('%s/shipping/%d/convert', FlagshipWoocommerceShipping::getFlagshipUrl(), $shipmentId);

        echo sprintf('<p>This order has already been exported to FlagShip: <a href="%s" target="_blank">%d</a></p>', $shipmentUrl, $shipmentId);
    }

    protected function getShipmentIdFromOrder($orderId)
    {
        $orderMeta = get_post_meta($orderId);

        if (!isset($orderMeta[self::$shipmentIdField])) {
            return;
        }

        return reset($orderMeta[self::$shipmentIdField]);
    }
}