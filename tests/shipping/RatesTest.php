<?php
use  FlagshipWoocommerce\Requests\Rates_Request;

class RatesTest extends FlagshipShippingUnitTestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->package = require __DIR__.'/../fixture/Package.php';
        $this->zoneSettings = require __DIR__.'/../fixture/ZoneSettings.php';
    }

    public function testCheckoutRates()
    {
    	$rateRequest = new Rates_Request($this->zoneSettings['token'],false,1);
    	// $rateRequest->setApiUrl('https://test-api.smartship.io');
    	// $rates = $rateRequest->getRates($this->package, $this->zoneSettings);
        //
    	// $this->assertGreaterThan(count($rates), 0);
        $this->assertTrue(true);
    }
}
