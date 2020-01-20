<?php
namespace FlagshipWoocommerce\Requests;

use Flagship\Shipping\Flagship;

class Get_Shipment_Request extends Abstract_Flagship_Api_Request {

    public function __construct($token)
    {
    	$this->token = $token;
    	$this->apiUrl = $this->getApiUrl();
    }
    
    public function getShipmentById($id)
    {
        $apiClient = new Flagship($this->token, $this->apiUrl);

        return $apiClient->getShipmentByIdRequest($id)->execute();
    }
}