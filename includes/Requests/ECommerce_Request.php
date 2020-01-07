<?php
namespace FlagshipWoocommerce\Requests;

use Flagship\Shipping\Flagship;
use Flagship\Shipping\Collections\RatesCollection;

class ECommerce_Request extends Rates_Request {
    public static $maxWeight = 2000; // in gram

    public function getRates($package)
    {
        $apiRequest = $this->makeApiRequest($package);

        if (!$this->isRateAvailable($apiRequest)) {
            return new RatesCollection();
        }

    	$apiClient = new Flagship($this->token, $this->apiUrl);

    	try{
		    $rates = $apiClient->getDhlEcommRatesRequest($apiRequest)->execute();
		}
		catch(Exception $e){
			$this->debug($e->getMessage(), 'error');
			$rates = new RatesCollection();
		}

		return $rates;
    }

    protected function makeApiRequest($package)
    {
        $request = parent::makeApiRequest($package);
        $request['packages']['items'][0]['weight'] = wc_get_weight($request['packages']['items'][0]['weight'], 'g', 'lbs');
        $request['packages']['units'] = 'metric';

        return $request;
    }

    protected function isRateAvailable($request)
    {
        return $request['from']['country'] == 'CA' && $request['to']['country'] != 'CA' && array_sum(array_column($request['packages']['items'], 'weight')) <= self::$maxWeight;
    }
}