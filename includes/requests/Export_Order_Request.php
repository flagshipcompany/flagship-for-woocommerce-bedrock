<?php

use Flagship\Shipping\Flagship;

class Export_Order_Request extends Abstract_Flagship_Api_Request {

    private $fullAddressFields = array();

    public function __construct($token)
    {
    	$this->token = $token;
    	$this->apiUrl = $this->getApiUrl();
        $this->fullAddressFields = array_merge($this->requiredAddressFields, array('address', 'suite', 'first_name', 'last_name'));
    }
    
    public function exportOrder($order)
    {
        $storeAddress = $this->getStoreAddress(true);
        $apiRequest = $this->makeApiRequest($storeAddress, $order);
        $apiClient = new Flagship($this->token, $this->apiUrl);

        try{
            $result = $apiClient->prepareShipmentRequest($apiRequest)->execute();
        }
        catch(Exception $e){
            $result = false;

            if (FLAGSHIP_DEBUG_MODE == true) {
                $order->add_order_note($e->getMessage());
            }            
        }

        return $result;
    }

    public function isOrderShippingAddressValid($order)
    {
        $address = $this->getDestinationAddress($order->get_address('shipping'), $this->requiredAddressFields);

        return count(array_filter($address)) == count($address);
    }

    protected function makeApiRequest($storeAddress, $order)
    {
        $destinationAddress = $this->getFullDestinationAddress($order);
        $orderItems = $order->get_items();
        $packages = $this->makePackages($orderItems);

        $request = array(
            'from' => $storeAddress,
            'to' => $destinationAddress,
            'packages' => $packages
        );

        return $request;
    }

    protected function makePackages($orderItems)
    {
        $items = array();
        $unit = get_option('woocommerce_weight_unit');

        foreach ( $orderItems as $items_key => $item_data ) { 
            $newItems = $this->makeItemsPerProduct($unit, $item_data->get_quantity(), $item_data->get_product());
            $items = array_merge($items, $newItems);
        }

        return array(
            'items' => $items,
            'units' => 'imperial',
            'type' => 'package',
        );
    }

    protected function getFullDestinationAddress($order)
    {
        $shippingAddress = $order->get_address('shipping');
        $fullAddress = $this->getDestinationAddress($shippingAddress, $this->fullAddressFields);
        $fullAddress['attn'] = trim($fullAddress['first_name'].' '.$fullAddress['last_name']);
        unset($fullAddress['first_name']);
        unset($fullAddress['last_name']);
        $fullAddress['phone'] = trim($order->get_address('billing')['phone']);

        return $fullAddress;
    }
}