<?php
namespace FlagshipWoocommerce\Requests;

use Flagship\Shipping\Flagship;

class Export_Order_Request extends Abstract_Flagship_Api_Request {

    private $fullAddressFields = array();

    public function __construct($token)
    {
    	$this->token = $token;
    	$this->apiUrl = $this->getApiUrl();
        $this->fullAddressFields = array_merge($this->requiredAddressFields, array('address', 'suite', 'first_name', 'last_name'));
    }
    
    public function exportOrder($order, $options)
    {
        $storeAddress = $this->getStoreAddress(true);
        $apiRequest = $this->makeApiRequest($storeAddress, $order, $options);
        $apiClient = new Flagship($this->token, $this->apiUrl);

        return $apiClient->prepareShipmentRequest($apiRequest)->execute();
    }

    public function isOrderShippingAddressValid($order)
    {
        $address = $this->getDestinationAddress($order->get_address('shipping'), $this->requiredAddressFields);

        return count(array_filter($address)) == count($address);
    }

    protected function makeApiRequest($storeAddress, $order, $options)
    {
        $orderOptions = $this->getOrderOptions($order);

        $destinationAddress = $this->getFullDestinationAddress($order);
        $orderItems = $order->get_items();
        $packages = $this->makePackages($orderItems);
        $trackingEmails = $this->makeTrackingEmails($destinationAddress, $options, $orderOptions);
        unset($destinationAddress['email']);

        $shippingOptions = array();

        if ($trackingEmails) {
            $shippingOptions['shipment_tracking_emails'] = $trackingEmails;
        }

        $request = array(
            'from' => $storeAddress,
            'to' => $destinationAddress,
            'packages' => $packages,
            'options' => $shippingOptions,
        );

        if (get_array_value($orderOptions, 'residential_receiver_address', false)) {
            $request['to']['is_commercial'] = false;
        }

        if (get_array_value($orderOptions, 'signature_required', false)) {
            $request['options']['signature_required'] = true;
        }

        return $request;
    }

    protected function extractOrderItems($items)
    {
        $orderItems = array();

        foreach ( $items as $items_key => $item_data ) {
            $item = array();
            $item['product'] = $item_data->get_product();
            $item['quantity'] = $item_data->get_quantity();
            $orderItems[] = $item;
        }

        return $orderItems;
    }

    protected function getFullDestinationAddress($order)
    {
        $shippingAddress = $order->get_address('shipping');
        $billingAddress = $order->get_address('billing');

        $fullAddress = $this->getDestinationAddress($shippingAddress, $this->fullAddressFields);
        $fullAddress['attn'] = trim($fullAddress['first_name'].' '.$fullAddress['last_name']);
        unset($fullAddress['first_name']);
        unset($fullAddress['last_name']);
        $fullAddress['phone'] = trim($billingAddress['phone']);
        $fullAddress['email'] = trim($billingAddress['email']);

        if ($this->getOrderShippingMeta($order, 'residential_receiver_address') == 'yes') {
            $fullAddress['is_commercial'] = false;
        }

        return $fullAddress;
    }

    protected function getOrderOptions($order)
    {
        $optionKeys = array(
            'send_tracking_emails',
            'residential_receiver_address',
            'signature_required',
        );
        $options = array();

        foreach ($optionKeys as $key => $value) {
            if ($this->getOrderShippingMeta($order, $value) === 'yes') {
                $options[$value] = true;
            }
        }

        return $options;
    }

    protected function getOrderShippingMeta($order, $key)
    {
        return reset($order->get_items('shipping'))->get_meta($key);
    }

    protected function makeTrackingEmails($destinationAddress, $options, $orderOptions)
    {
        $adminEmail = get_array_value($options, 'tracking_emails');
        $customerEmail = get_array_value($orderOptions, 'send_tracking_emails', false) ? $destinationAddress['email'] : null;
        $trackingEmails = array_filter(array($adminEmail, $customerEmail));

        return implode(';', $trackingEmails);
    }
}