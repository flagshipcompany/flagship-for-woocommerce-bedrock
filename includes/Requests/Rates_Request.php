<?php
namespace FlagshipWoocommerce\Requests;

use Flagship\Shipping\Flagship;
use Flagship\Shipping\Collections\RatesCollection;
use FlagshipWoocommerce\FlagshipWoocommerceShipping;

class Rates_Request extends Abstract_Flagship_Api_Request {

    protected $debugMode = false;
    
    public function __construct($token, $debugMode = false)
    {
    	$this->token = $token;
    	$this->apiUrl = $this->getApiUrl();
        $this->debugMode = $debugMode;
    }

    public function getRates($package, $options = array())
    {
        $apiRequest = $this->makeApiRequest($package, $options);
    	$apiClient = new Flagship($this->token, $this->apiUrl, 'woocommerce', FlagshipWoocommerceShipping::$version);

    	try{
		    $rates = $apiClient->createQuoteRequest($apiRequest)->execute();
		}
		catch(\Exception $e){
			$this->debug($e->getMessage(), 'error');
			$rates = new RatesCollection();
		}

		return $rates;
    }

    protected function makeApiRequest($package, $options = array())
    {
        $storeAddress = $this->getStoreAddress();
        $destinationAddress = $this->getDestinationAddress($package['destination'], $this->requiredAddressFields, $options);
        $packages = $this->makePackages($package);
        $shippingOptions = $this->makeShippingOptions($options);

        $request = array(
            'from' => $storeAddress,
            'to' => $destinationAddress,
            'packages' => $packages
        );

        if ($shippingOptions) {
            $request['options'] = $shippingOptions;
        }

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
        if (FlagshipWoocommerceShipping::isDebugMode() || $this->debugMode) {
            wc_add_notice($message, $type);
        }
    }
}