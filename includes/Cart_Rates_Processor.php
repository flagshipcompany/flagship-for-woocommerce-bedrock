<?php
class Cart_Rates_Processor {
    private $methodId;

    public function __construct($methodId) {
        $this->methodId = $methodId;
    }

    public function processRates($rates)
    {
        return array_map(array($this, 'makeCartRate'), $rates);
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
}