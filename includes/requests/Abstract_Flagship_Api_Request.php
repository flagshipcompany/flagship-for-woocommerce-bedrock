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

    protected function makeItemsPerProduct($unit, $numItems, $product)
    {
        $weight = $product->get_weight() ? round(wc_get_weight($product->get_weight(), $unit, 'lbs')) : 1;
        $description = $product->get_short_description();
        $withDimensions = $product->get_length() && $product->get_width() && $product->get_height();

        if (!$withDimensions) {
            $item = array(
                'length' => 1,
                'width' => 1,
                'height' => 1,
                'weight' => $weight,
                'description' => $description,
            );

            return array_fill(0, $numItems, $item);
        }

        $item = array(
            'length' => max(1, round(wc_get_dimension($product->get_length(), $unit, 'in'))),
            'width' => max(1, round(wc_get_dimension($product->get_width(), $unit, 'in'))),
            'height' => max(1, round(wc_get_dimension($product->get_height(), $unit, 'in'))),
            'weight' => $weight,
            'description' => $description,
        );

        return array_fill(0, $numItems, $item);
    }
}