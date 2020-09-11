<?php
use  FlagshipWoocommerce\Requests\Rates_Request;

class RatesTest extends FlagshipShippingUnitTestCase
{
    public function setUp()
    {
        parent::setUp();
    }

    public function testCheckoutRates()
    {
        $rateRequest = new Rates_Request($this->zoneSettings['token'],false,0);

        $this->assertNotEmpty($rateRequest);
    }
}
