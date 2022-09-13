<?php

use FlagshipWoocommerceBedrock\Requests\Confirm_Shipment_Request;

class ConfirmShipmentTest extends FlagshipShippingUnitTestCase
{
    public function setUp() : void
    {
        parent::setUp();
    }

    public function testConfirmShipment()
    {
        $request = new Confirm_Shipment_Request('cQcoa5tK7F9HBmbx8cqlXBMFxEP3Tfb---mzKlBIM3Q', 'https://test-api.smartship.io');
        $shipmentId = 1499520;
        $shipment = $request->confirmShipmentById($shipmentId);
        if (is_string($shipment)) {
            $errorArray = explode(":",$shipment);
            $this->assertIsArray($errorArray);
            $this->assertNotEmpty($errorArray);
            return;
        }
        $this->assertEquals('dispatched', $shipment->getStatus());
    }
}
