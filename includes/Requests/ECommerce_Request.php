<?php
namespace FlagshipWoocommerceBedrock\Requests;

use Flagship\Shipping\Flagship;
use Flagship\Shipping\Collections\RatesCollection;
use FlagshipWoocommerceBedrock\FlagshipWoocommerceBedrockShipping;

class ECommerce_Request extends Rates_Request
{
    public static $maxWeight = 2000; // in gram

    public function getRates($package, $options = array())
    {
        $apiRequest = $this->makeApiRequest($package, $options);

        if (!$this->isRateAvailable($apiRequest)) {
            return new RatesCollection();
        }

        $apiClient = new Flagship($this->token, $this->apiUrl, 'woocommerce', FlagshipWoocommerceBedrockShipping::$version);

        try {
            $rates = $apiClient->getDhlEcommRatesRequest($apiRequest)->execute();
        } catch (Exception $e) {
            $this->debug($e->getMessage(), 'error');
            $rates = new RatesCollection();
        }

        return $rates;
    }

    protected function makeApiRequest($package, $options = array())
    {
        $options['box_split'] = null; //Everything in one box
        $options['dimension_unit'] = 'cm';
        $options['weight_unit'] = 'g';
        $options['units'] = 'metric';
        $testEnv = get_array_value($this->pluginSettings, 'test_env') == 'no'? 0 : 1;
        $request = parent::makeApiRequest($package, $options, $testEnv);

        return $request;
    }

    protected function isRateAvailable($request)
    {
        return $request['from']['country'] == 'CA' && $request['to']['country'] != 'CA' && array_sum(array_column($request['packages']['items'], 'weight')) <= self::$maxWeight;
    }
}
