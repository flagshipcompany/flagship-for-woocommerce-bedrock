<?php

use Flagship\Shipping\Flagship;
use Flagship\Shipping\Collections\RatesCollection;

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
			$rates = new RatesCollection();
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

    protected function extractOrderItems($items)
    {
        $orderItems = array();

        foreach ( $items['contents'] as $item_id => $values ) {
            $item = array();
            $item['product'] = $values['data'];
            $item['quantity'] = $values['quantity'];
            $orderItems[] = $item;
        }

        return $orderItems;
    }

    protected function debug($message, $type = 'notice')
    {
        if (FLAGSHIP_DEBUG_MODE == true) {
            wc_add_notice($message, $type);
        }
    }
}