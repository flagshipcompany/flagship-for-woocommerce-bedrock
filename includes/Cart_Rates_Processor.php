<?php
class Cart_Rates_Processor {
    private $methodId;

    private $instanceSettings;

    public function __construct($methodId, $instanceSettings) {
        $this->methodId = $methodId;
        $this->instanceSettings = $instanceSettings;
    }

    public function processRates($rates)
    {
        $filteredRates = array_filter($rates, array($this, 'filterRates'));

        return array_map(array($this, 'makeCartRate'), $filteredRates);
    }

    public function makeCartRate($rate)
    {
        $cartRate = array(
            'id' => $this->methodId.'|'.$rate->getCourierName().'|'.$rate->getServiceCode(),
            'label' => $rate->getCourierName().' - '.$rate->getCourierDescription(),
            'cost' => $rate->getSubtotal(),
        );

        return $cartRate;
    }

    public function filterRates($rate)
    {        
        $included = true;

        if (isset($this->instanceSettings['exclude_express_rates']) && $this->instanceSettings['exclude_express_rates'] == 'yes') {
            $included = in_array($rate->getFlagshipCode(), array('standard', 'intlStandard'));
        }

        return $included;        
    }
}