<?php

namespace FlagshipWoocommerceBedrock\Requests;

use Flagship\Shipping\Flagship;
use FlagshipWoocommerceBedrock\FlagshipWoocommerceBedrockShipping;

class Pickup_Request extends Abstract_Flagship_Api_Request {

    protected $debugMode = false;

    public function __construct($token, $debugMode = false, $testEnv = 0)
    {
        $this->token = $token;
        $this->apiUrl = $this->getApiUrl($testEnv);
        $this->debugMode = $debugMode;
    }

    public function create_pickup_request($flagship_shipment_id, $date, $from_time, $until_time) {
        $payload = $this->create_pickup_payload($flagship_shipment_id, $date, $from_time, $until_time);
        $apiClient = new Flagship($this->token, $this->apiUrl, 'woocommerce', FlagshipWoocommerceBedrockShipping::$version);
        try{
            $pickupRequest = $apiClient->createPickupRequest($payload)->execute();
            return $pickupRequest;  
        } catch(\Exception $e) {
            return $e->getMessage();
        }  
    }

    public function create_pickup_payload($flagship_shipment_id, $date, $from_time, $until_time) {
        $payload = [
            "from" => $from_time,
            "until" => $until_time,
            "date" => $date,
            "shipments" => $flagship_shipment_id
        ];
        return $payload;
    }
}