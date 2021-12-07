<?php
namespace FlagshipWoocommerceBedrock\Requests;

use Flagship\Shipping\Flagship;
use FlagshipWoocommerceBedrock\FlagshipWoocommerceBedrockShipping;

class Confirm_Shipment_Request extends Abstract_Flagship_Api_Request
{
    public function __construct($token, $apiUrl)
    {
        $this->token = $token;
        $this->apiUrl = $apiUrl;
    }

    public function confirmShipmentById($id)
    {
        $apiClient = new Flagship($this->token, $this->apiUrl, 'woocommerce', FlagshipWoocommerceBedrockShipping::$version);

        try {
            $shipment = $apiClient->confirmShipmentByIdRequest($id)->execute();
            return $shipment;
        } catch (\Exception $e) {
            FlagshipWoocommerceBedrockShipping::add_log("Confirm shipment response: ".$e->getMessage());
            return $e->getMessage();
        }
    }
}
