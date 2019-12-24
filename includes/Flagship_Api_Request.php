<?php
use Flagship\Shipping\Flagship;
use Flagship\Shipping\Exceptions\QuoteException;

class Flagship_Api_Request {
	private $token;

	private $apiUrl;

	private $storeAddress = array();

    public function __construct($token)
    {
    	$this->token = $token;
    	$this->apiUrl = $this->getApiUrl();
    	$this->initStoreAddress();
    }

    public function getRates($package)
    {
    	$apiRequest = $this->makeApiRequest($package);
    	$apiClient = new Flagship($this->token, $this->apiUrl);

    	try{
		    $rates = $apiClient->createQuoteRequest($apiRequest)->execute();
		}
		catch(QuoteException $e){
			$this->debug($e->getMessage(), 'error');
			$rates = array();
		}

		return $rates;
    }

    protected function getApiUrl()
    {
    	return FLAGSHIP_DEBUG_MODE == true ? 'http://127.0.0.1:3002' : 'https://api.smartship.io';
    }

    protected function debug($message, $type = 'notice')
    {
    	if (FLAGSHIP_DEBUG_MODE == true) {
    		wc_add_notice($message, $type);
    	}
    }

    protected function initStoreAddress()
    {
    	$this->storeAddress['postal_code'] = get_option('woocommerce_store_postcode', '');
    	$countryState = $this->getCountryState();
    	$this->storeAddress['country'] = $countryState['country'];
    	$this->storeAddress['state'] = $countryState['state'];
    	$this->storeAddress['city'] = get_option('woocommerce_store_city', '');
    	$this->storeAddress['address'] = trim(get_option('woocommerce_store_address', '').' '.get_option('woocommerce_store_address2', ''));
    }

    protected function makeApiRequest($package)
    {
    	$destinationAddress = $this->getDestinationAddress($package['destination']);
    	$item = $this->makeItem($package);
    	$packages = array(
    		'items' => array($item),
    		'units' => 'imperial',
    		'type' => 'package',
    	);
    	
    	$request = array(
    		'from' => $this->storeAddress,
    		'to' => $destinationAddress,
    		'packages' => $packages
    	);

    	return $request;
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

    protected function getDestinationAddress($destination)
    {
    	$country = get_array_value($destination, 'country', '');
    	$state = get_array_value($destination, 'state', '');
    	$postalCode = get_array_value($destination, 'postcode', '');
    	$city = get_array_value($destination, 'city', '');
    	$address = trim(get_array_value($destination, 'address', '').' '.get_array_value($destination, 'address2', ''));

    	return array(
    		'country' => $country,
    		'state' => $state,
    		'postal_code' => $postalCode,
    		'city' => $city,
    		'address' => $address,
    	);
    }

    protected function makeItem($package)
    {
    	$weight = 0;
    	$productDimensions = array();
    	$productDescriptions = array();

    	foreach ( $package['contents'] as $item_id => $values ) { 
	        $_product = $values['data'];
	        $weight = $weight + $_product->get_weight() * $values['quantity'];

	        if ($_product->get_length() && $_product->get_width() && $_product->get_height()) {
	        	$productDimensions[$item_id] = array(
	        		'length' => $_product->get_length(),
	        		'width' => $_product->get_width(),
	        		'height' => $_product->get_height(),
	        	);
	        }

	        $productDescriptions[$item_id] = $_product->get_short_description();
	    }

	    $unit = get_option('woocommerce_weight_unit');
        $packageWeight = round(wc_get_weight($weight, $unit, 'lbs'));
        $item = $this->makeDimensions($productDimensions);
        $item['weight'] = $packageWeight;
        $item['description'] = implode(';', array_filter($productDescriptions));

        return $item;
    }

    protected function makeDimensions($productDimensions)
    {
    	if (!$productDimensions) {
    		return array(1, 1, 1);
    	}

    	$length = array_column($productDimensions, 'length');
    	$width = array_column($productDimensions, 'width');
    	$height = array_column($productDimensions, 'height');
    	$unit = get_option('woocommerce_dimension_unit');

    	$dimensions = array(
    		'length' => max(1, round(wc_get_dimension($length, $unit, 'in'))),
    		'width' => max(1, round(wc_get_dimension($width, $unit, 'in'))),
    		'height' => max(1, round(wc_get_dimension($height, $unit, 'in')))
    	);

    	return $dimensions;
    }
}