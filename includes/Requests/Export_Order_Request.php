<?php
namespace FlagshipWoocommerceBedrock\Requests;

use Flagship\Shipping\Flagship;
use FlagshipWoocommerceBedrock\FlagshipWoocommerceBedrockShipping;
use FlagshipWoocommerceBedrock\Helpers\Package_Helper;
use FlagshipWoocommerceBedrock\Requests\Confirm_Shipment_Request;

class Export_Order_Request extends Abstract_Flagship_Api_Request
{
    private $fullAddressFields = array();

    private $editShipmentAddressFields = array(
        'postal_code',
        'country',
        'state',
        'city',
        'address',
        'name',
        'attn',
        'phone',
    );

    public function __construct($token, $testEnv=0)
    {
        $this->token = $token;
        $this->apiUrl = $this->getApiUrl($testEnv);
        $this->webUrl = $this->getWebUrl($testEnv);
        $this->fullAddressFields = array_merge($this->requiredAddressFields, array('address', 'suite', 'first_name', 'last_name'));
    }

    public function exportOrder($order, $options)
    {
        $storeAddress = $this->getStoreAddress(true, false, $options);
        $prepareRequest = $this->makePrepareRequest($order, $options);
        $apiClient = new Flagship($this->token, $this->apiUrl, 'woocommerce', FlagshipWoocommerceBedrockShipping::$version);
        
        if(is_string($prepareRequest["packages"]["items"])){
            return $prepareRequest["packages"]["items"];
        }

        try {
            FlagshipWoocommerceBedrockShipping::add_log("Prepare Shipment Request payload:". json_encode($prepareRequest));
            $prepareRequestObj = $apiClient->prepareShipmentRequest($prepareRequest);
            $prepareRequestObj = $this->addHeaders($prepareRequestObj, $storeAddress['name'], $order->get_id());
            $exportedShipment = $prepareRequestObj->execute();
            FlagshipWoocommerceBedrockShipping::add_log("Prepare Shipment Response : ". json_encode($exportedShipment));
            $editShipmentData = $this->makeExtraFieldsForEdit($order, $exportedShipment, $prepareRequest, $options);

            if ($editShipmentData) {
                $exportedShipment = $this->editShipment($order, $exportedShipment, $prepareRequest, $editShipmentData, $options);
            }
            return $exportedShipment;
        } catch (\Exception $e) {
            FlagshipWoocommerceBedrockShipping::add_log($e->getMessage());
            return $e->getMessage();
        }
    }

    public function editShipment($order, $flagshipShipment, $preparePayload, $editShipmentData, $options)
    {
        $storeAddress = $this->getStoreAddress(true, false, $options);
        $apiClient = new Flagship($this->token, $this->apiUrl, 'woocommerce', FlagshipWoocommerceBedrockShipping::$version);
        $editRequest = array_merge($preparePayload, $editShipmentData);
        FlagshipWoocommerceBedrockShipping::add_log("Edit Shipment Request payload:". json_encode($editRequest));
            
        $editRequestObj = $apiClient->editShipmentRequest($editRequest, $flagshipShipment->getId());
        $editRequestObj = $this->addHeaders($editRequestObj, $storeAddress['name'], $order->get_id());
        try {
            $exportedShipment = $editRequestObj->execute();
            FlagshipWoocommerceBedrockShipping::add_log("Edit Shipment Response : ". json_encode($exportedShipment));
            return $exportedShipment;
        } catch (\Exception $e) {
            FlagshipWoocommerceBedrockShipping::add_log($e->getMessage());
            return $e->getMessage();
        }
    }

    public function makeExtraFieldsForEdit($order, $exportedShipment, $prepareRequest, $options)
    {
        $extraFields = array();
        $storeAddress = $this->getStoreAddress(true, false, $options);
        $selectedService = $this->findShippingServiceInOrder($order);
        $nbrOfMissingFields = count($this->findMissingAddressFieldsForEdit($storeAddress));
        $shipmentId = $exportedShipment->getId();
        $isIntl = $this->isIntShipment($prepareRequest);
        $commercialInvFields = array();

        if ($isIntl) {
            $commercialInvFields = (new Commercial_Inv_Request_Helper())->makeIntShpFields($prepareRequest, $order);
        }

        if (!$shipmentId || !$selectedService || $nbrOfMissingFields || ($isIntl && !$commercialInvFields)) {
            return array();
        }

        $extraFields['service'] = $selectedService;

        if ($commercialInvFields) {
            $extraFields = array_merge($extraFields, $commercialInvFields);
        }

        return $extraFields;
    }

    public function isOrderShippingAddressValid($order)
    {
        $address = $this->getDestinationAddress($order->get_address('shipping'), $this->requiredAddressFields);

        $postcode = $order->get_address('shipping')['postcode'];
        $count = count(array_filter($address)) == count($address);
        $postcodeValidation = preg_match('/^[A-Z]\d[A-Z]\s?\d[A-Z]\d$/i', $postcode);
        return $count && $postcodeValidation;
    }

    public function makePrepareRequest($order, $options)
    {
        $storeAddress = $this->getStoreAddress(true, false, $options);
        $orderOptions = $this->getOrderOptions($order);

        $destinationAddress = $this->getFullDestinationAddress($order);
        $packageHelper = new Package_Helper(false, $this->apiUrl);
        $orderItems = $order->get_items();
        $packages = $packageHelper->make_packages($this->extractOrderItems($orderItems), $options);
        $trackingEmails = $this->makeTrackingEmails($destinationAddress, $options, $orderOptions);
        unset($destinationAddress['email']);
        $orderSubtotal = $order->get_subtotal();
        $driverInstructions = $order->get_customer_note();
        $shippingOptions = ['reference' => $storeAddress['name'].'# '.$order->get_id()];

        if ($trackingEmails) {
            $shippingOptions['shipment_tracking_emails'] = $trackingEmails;
        }
        if (get_array_value($options, 'driver_instructions', false) === 'yes' && $driverInstructions) {
            $shippingOptions['driver_instructions'] = $driverInstructions;
        }

        $request = array(
            'from' => $storeAddress,
            'to' => $destinationAddress,
            'packages' => $packages,
            'options' => $shippingOptions,
        );

        if (get_array_value($options, 'residential_receiver_address', false) === 'yes') {
            $request['to']['is_commercial'] = false;
        }

        if (get_array_value($options, 'signature_required', false) === 'yes') {
            $request['options']['signature_required'] = true;
        }

        if (get_array_value($options, 'flagship_insurance', false) === 'yes' && $orderSubtotal >= 101) {
            $request['options']['insurance'] = [
                "value" => $orderSubtotal,
                "description" => $this->getInsuranceDescription($orderItems)
            ];
        }

        return $request;
    }

    public function getFlagshipUrl()
    {
        return $this->webUrl;
    }

    public function confirmShipment($shipmentId)
    {
        $confirmShipmentRequest = new Confirm_Shipment_Request($this->token, $this->apiUrl);
        $confirmedShipment = $confirmShipmentRequest->confirmShipmentById($shipmentId);
        return $confirmedShipment;
    }

    protected function isIntShipment($prepareRequest)
    {
        return ($prepareRequest['from']['country'] == 'CA' && $prepareRequest['to']['country'] != 'CA') || ($prepareRequest['from']['country'] != 'CA' && $prepareRequest['to']['country'] == 'CA');
    }

    public function extractOrderItems($items)
    {
        $orderItems = array();

        foreach ($items as $items_key => $item_data) {
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
        $fullAddress['attn'] = substr(trim($fullAddress['first_name'].' '.$fullAddress['last_name']), 0, 21);
        unset($fullAddress['first_name']);
        unset($fullAddress['last_name']);
        $fullAddress['name'] = $shippingAddress['company'] == NULL ? 
                                $billingAddress['company'] != NULL ? $billingAddress['company'] : substr($fullAddress['attn'],0,30) : 
                                $shippingAddress['company'] ;
        
        $fullAddress['phone'] = trim($billingAddress['phone']);
        $fullAddress['email'] = trim($billingAddress['email']);

        $fullAddress['address'] = substr($fullAddress['address'], 0, 30);
        $fullAddress['suite'] = substr($fullAddress['suite'],0,18);
        
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

    protected function findShippingServiceInOrder($order)
    {
        $selectedService = $this->getOrderShippingMeta($order, 'selected_shipping');
        $courierAndService = array_map('trim', explode('-', $selectedService));
        $fields = array('courier_name', 'courier_code');

        return count($fields) == count($courierAndService) ? array_combine($fields, $courierAndService) : [];
    }

    protected function getOrderShippingMeta($order, $key)
    {
        $shipping = $order->get_items('shipping');

        if (!$shipping) {
            return;
        }

        return reset($shipping)->get_meta($key);
    }

    protected function makeTrackingEmails($destinationAddress, $options, $orderOptions)
    {
        $adminEmail = get_array_value($options, 'tracking_emails');
        $customerEmail = isset($destinationAddress['email']) && (get_array_value($orderOptions, 'send_tracking_emails', false) || get_array_value($options, 'send_tracking_emails', 'no') === 'yes') ? $destinationAddress['email'] : null;
        $trackingEmails = array_filter(array($adminEmail, $customerEmail));

        return implode(';', $trackingEmails);
    }

    protected function findMissingAddressFieldsForEdit($storeAddress)
    {
        $missingFields = array_filter($this->editShipmentAddressFields, function ($val) use ($storeAddress) {
            return !isset($storeAddress[$val]) || empty(trim($storeAddress[$val]));
        });

        return $missingFields;
    }

    protected function getInsuranceDescription($orderItems)
    {
        $insuranceDescription = '';

        foreach ($orderItems as $item) {
            $insuranceDescription .= $item->get_name().',';
        }

        return rtrim($insuranceDescription, ',');
    }
}
