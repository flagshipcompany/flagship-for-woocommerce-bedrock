<?php
namespace FlagshipWoocommerce;

use FlagshipWoocommerce\Requests\Rates_Request;
use FlagshipWoocommerce\Requests\ECommerce_Request;

class Cart_Rates_Processor {
    private $methodId;

    private $token;

    private $instanceSettings;

    public function __construct($methodId, $token, $instanceSettings) {
        $this->methodId = $methodId;
        $this->token = $token;
        $this->instanceSettings = $instanceSettings;
    }

    public function fetchRates($package)
    {
        $ratesRequest = new Rates_Request($this->token);
        $rates = $ratesRequest->getRates($package)->all();

        if (get_array_value($this->instanceSettings, 'offer_dhl_ecommerce_rates', null) == 'yes') {
            $eCommerceRequest = new ECommerce_Request($this->token);
            $eCommerceRates = $eCommerceRequest->getRates($package)->all();
            $rates = array_merge($eCommerceRates, $rates);
        }

        return $rates;
    }

    public function processRates($rates)
    {
        if (count($rates) == 0) {
            return array();
        }

        $filteredRates = $this->filterRates($rates);
        $cartRates = array_map(array($this, 'makeCartRate'), $filteredRates);
        usort($cartRates, array($this, 'sortRates'));

        return $cartRates;
    }

    protected function filterRates($rates)
    {        
        $filteredRates = array_filter($rates, array($this, 'filterRate'));

        if ($this->isSettingChecked('only_show_cheapest', 'yes')) {
            $filteredRates = array($this->findCheapest($filteredRates));
        }

        return $filteredRates;
    }

    protected function makeCartRate($rate)
    {
        $cartRate = array(
            'id' => $this->methodId.'|'.$rate->getCourierName().'|'.$rate->getServiceCode(),
            'label' => $rate->getCourierName().' - '.$rate->getCourierDescription(),
            'cost' => $this->markupCost($rate->getSubtotal()),
        );

        return $cartRate;
    }

    protected function markupCost($cost)
    {
        if (!isset($this->instanceSettings['shipping_cost_markup']) || !$this->instanceSettings['shipping_cost_markup']) {
            return $cost;
        }

        $markedUpCost = round($cost * (1 + $this->instanceSettings['shipping_cost_markup']/100), 2);

        return $markedUpCost;
    }

    protected function filterRate($rate)
    {        
        $included = true;

        $settings = array(
            'offer_standard_rates',
            'offer_express_rates',
        );

        while ($included && $setting = array_shift($settings)) {
            preg_match('/offer_([a-zA-Z]+)_rates/', $setting, $matches);

            if ($matches[1] && $this->isSettingChecked($setting, 'no')) {
                $included = !($this->removeRateByCodeType($rate->getFlagshipCode(), $matches[1]));
            }
        }

        return $included;       
    }

    protected function sortRates($a, $b)
    {
        if ($a['cost'] == $b['cost']) {
            return 0;
        }

        return ($a['cost'] < $b['cost']) ? -1 : 1;
    }

    protected function findCheapest($rates)
    {
        $cheapest = array_shift($rates);

        while ($nextRate = array_shift($rates)) {
            if ($nextRate->getTotal() < $cheapest->getTotal()) {
                $cheapest = $nextRate;
            }
        }

        return $cheapest;
    }

    protected function removeRateByCodeType($rateCode, $codeType)
    {
        $remove = false;

        switch ($codeType) {
            case 'standard':
                $removed = in_array($rateCode, array('standard', 'intlStandard'));
                break;
            case 'express':
                $removed = !in_array($rateCode, array('standard', 'intlStandard'));
                break;
        }

        return $removed;
    }

    protected function isSettingChecked($settingName, $checkedValue)
    {
        return isset($this->instanceSettings[$settingName]) && $this->instanceSettings[$settingName] == $checkedValue;
    }
}