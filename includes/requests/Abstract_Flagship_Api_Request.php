<?php

abstract class Abstract_Flagship_Api_Request {

	private $token;

	private $apiUrl;

    // address field in request => field in woocommerce address
    private $addressFieldMap = array(
        'postal_code' => 'postcode',
        'address' => 'address_1',
        'suite' => 'address_2',
    );

    protected $requiredAddressFields = array(
        'country',
        'state',
        'postal_code',
        'city',
    );

    protected function getApiUrl()
    {
    	return FLAGSHIP_DEBUG_MODE == true ? 'http://127.0.0.1:3002' : 'https://api.smartship.io';
    }

    protected function getStoreAddress($fullAddress = false)
    {
        $storeAddress = array();

        $storeAddress['postal_code'] = trim(get_option('woocommerce_store_postcode', ''));
        $countryState = $this->getCountryState();
        $storeAddress['country'] = $countryState['country'];
        $storeAddress['state'] = $countryState['state'];
        $storeAddress['city'] = trim(get_option('woocommerce_store_city', ''));

        if ($fullAddress) {
            $storeAddress['address'] = trim(get_option('woocommerce_store_address', ''));
            $storeAddress['suite'] = trim(get_option('woocommerce_store_address_2', ''));
        }

        return $storeAddress;
    }

    protected function getCountryState()
    {
    	$countryAndState = array(
			'country' => null,
			'state' => null,
		);
    	$countryState = get_option('woocommerce_default_country', '');

    	if (empty($countryState)) {
    		return $countryAndState;
    	}

    	$splitValues = explode(':', $countryState);
    	$country = isset($splitValues[0]) ? $splitValues[0] : null;
    	$state = isset($splitValues[1]) ? $splitValues[1] : null;

    	return array(
			'country' => $country,
			'state' => $state,
		);
    }

    protected function fillAddressField($destination, $fieldName)
    {
        if (isset($destination[$fieldName])) {
            return trim($destination[$fieldName]);
        }

        $alternativeName = $this->addressFieldMap[$fieldName];

        return isset($destination[$alternativeName]) ? trim($destination[$alternativeName]) : '';
    }

    protected function getDestinationAddress($destination, $addressFields)
    {
        $destinationAddress = array();

        foreach ($addressFields as $key => $fieldName) {
            $destinationAddress[$fieldName] = $this->fillAddressField($destination, $fieldName);
        }

        return $destinationAddress;
    }

    protected function makePackages($orderItems)
    {
        $orderItems = $this->extractOrderItems($orderItems);

        $unit = get_option('woocommerce_weight_unit');
        $totalWeight = 0;
        $productDescriptions = array();

        foreach ( $orderItems as $item_id => $productItem ) { 
            $product = $productItem['product'];
            $weight = $product->get_weight() ? round(wc_get_weight($product->get_weight(), $unit, 'lbs')) : 1;
            $totalWeight += $weight*$productItem['quantity'];
            $productDescriptions[] = $product->get_short_description();
        }

        $description = implode(';', $productDescriptions);

        if (strlen($description) > 35) {
            $description = $productDescriptions[0];
        }

        $item = array(
            'length' => 1,
            'width' => 1,
            'height' => 1,
            'weight' => $totalWeight,
            'description' => $description,
        );

        return array(
            'items' => array($item),
            'units' => 'imperial',
            'type' => 'package',
        );
    }
}