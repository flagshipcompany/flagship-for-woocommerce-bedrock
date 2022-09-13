<?php

use FlagshipWoocommerceBedrock\Requests\Get_Shipment_Request;

class GetShipmentTest extends FlagshipShippingUnitTestCase
{
    public function setUp() : void
    {
        parent::setUp();
    }

    public function testGetShipmentById()
    {
        $request = new Get_Shipment_Request('cQcoa5tK7F9HBmbx8cqlXBMFxEP3Tfb---mzKlBIM3Q', 1);
        $shipment = $request->getShipmentById(1499520);
        if (is_string($shipment)) {
            $errorArray = json_decode($shipment, true);
            $this->assertArrayHasKey('error', $errorArray);
            return;
        }
        $this->assertNotEmpty($shipment->getSenderAddress());
        $this->assertNotEmpty($shipment->getReceiverPostalCode());
    }
}
