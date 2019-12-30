<?php

use Flagship\Shipping\Flagship;

class Rates_Request extends Abstract_Flagship_Api_Request {

    public function __construct($token)
    {
    	$this->token = $token;
    	$this->apiUrl = $this->getApiUrl();
    }

    public function getRates($package)
    {
    	$storeAddress = $this->getStoreAddress();

        $apiRequest = $this->makeApiRequest($storeAddress, $package);
    	$apiClient = new Flagship($this->token, $this->apiUrl);

    	try{
		    $rates = $apiClient->createQuoteRequest($apiRequest)->execute();
		}
		catch(Exception $e){
			$this->debug($e->getMessage(), 'error');
			$rates = array();
		}

		return $rates;
    }

    protected function makeApiRequest($storeAddress, $package)
    {
        $destinationAddress = $this->getDestinationAddress($package['destination'], $this->requiredAddressFields);
        $packages = $this->makePackages($package);

        $request = array(
            'from' => $storeAddress,
            'to' => $destinationAddress,
            'packages' => $packages
        );

        return $request;
    }

    protected function makePackages($package)
    {
        $items = array();
        $unit = get_option('woocommerce_weight_unit');

        foreach ( $package['contents'] as $item_id => $values ) { 
            $_product = $values['data'];
            $newItems = $this->makeItemsPerProduct($unit, $values['quantity'], $_product);
            $items = array_merge($items, $newItems);
        }

        return array(
            'items' => $items,
            'units' => 'imperial',
            'type' => 'package',
        );
    }

    protected function debug($message, $type = 'notice')
    {
        if (FLAGSHIP_DEBUG_MODE == true) {
            wc_add_notice($message, $type);
        }
    }
}