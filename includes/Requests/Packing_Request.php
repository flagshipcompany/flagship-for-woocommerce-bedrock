<?php
namespace FlagshipWoocommerce\Requests;

use Flagship\Shipping\Flagship;
use FlagshipWoocommerce\FlagshipWoocommerceShipping;

class Packing_Request extends Abstract_Flagship_Api_Request {

    protected $debugMode = false;
    
    public function __construct($token, $debugMode = false) {
    	$this->token = $token;
    	$this->apiUrl = $this->getApiUrl();
        $this->debugMode = $debugMode;
    }

    public function pack_boxes($items, $boxes) {
        $apiRequest = $this->make_api_request($items, $boxes);
        $apiClient = new Flagship($this->token, $this->apiUrl, 'woocommerce', FlagshipWoocommerceShipping::$version);

        try{
            $packing_results = $apiClient->packingRequest($apiRequest)->execute();
        }
        catch(\Exception $e){
            $this->debug($e->getMessage(), 'error');

            return false;
        }

        return $this->convert_packing_to_boxes($packing_results);
    }

    protected function make_api_request($items, $boxes) {
        $boxes = $this->make_boxes_request($boxes);

        return array(
            'items' => $items,
            'boxes' => $boxes,
            'units' => 'imperial',
        );
    }

    protected function make_boxes_request($boxes) {
        return array_map(function($box) {
            $box['box_model'] = $box['model'];
            $box['weight'] = 0;
            unset($box['id']);
            unset($box['model']);
            unset($box['extra_charge']);

            return $box;
        }, $boxes);
    }

    protected function convert_packing_to_boxes($packed_boxes) {
        return array_map(function($box) {
            $package_item = array();
            $package_item['length'] = $box->getLength();
            $package_item['width'] = $box->getWidth();
            $package_item['height'] = $box->getHeight();
            $package_item['weight'] = $box->getWeight();
            $descriptions = array_unique($box->getItems());
            $description = implode(';', $descriptions);

            if (strlen($description) > 35) {
                $description = $descriptions[0];
            }

            $package_item['description'] = $description;

            return $package_item;
        }, $packed_boxes->all());
    }
}