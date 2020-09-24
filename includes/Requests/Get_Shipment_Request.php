<?php
namespace FlagshipWoocommerceBedrock\Requests;

use Flagship\Shipping\Flagship;
use FlagshipWoocommerceBedrock\FlagshipWoocommerceBedrockShipping;

class Get_Shipment_Request extends Abstract_Flagship_Api_Request {

    public function __construct($token, $testEnv=0)
    {
    	$this->token = $token;
    	$this->apiUrl = $this->getApiUrl($testEnv);
    }

    public function getShipmentById($id)
    {
        $apiClient = new Flagship($this->token, $this->apiUrl, 'woocommerce', FlagshipWoocommerceBedrockShipping::$version);
        try{
            $shipment = $apiClient->getShipmentByIdRequest($id)->execute();
            return $shipment;
        } catch(\Exception $e){
            return $e->getMessage();
        }
    }
}
