<?php
namespace FlagshipWoocommerceBedrock\Requests;

use Flagship\Shipping\Flagship;
use FlagshipWoocommerceBedrock\FlagshipWoocommerceBedrockShipping;

class Packing_Request extends Abstract_Flagship_Api_Request
{
    protected $debugMode = false;

    public function __construct($token, $debugMode = false, $apiUrl)
    {
        $this->token = $token;
        $this->apiUrl = $apiUrl;
        $this->debugMode = $debugMode;
    }

    public function pack_boxes($items, $boxes)
    {
        $packageBoxes = [];
        $shipAsIsItems = [];
        $shipAsIsItems = $this->getShipAsIsItems($items, $shipAsIsItems);
        $apiRequests = $this->make_api_request($items, $boxes);
        $apiClient = new Flagship($this->token, $this->apiUrl, 'woocommerce', FlagshipWoocommerceBedrockShipping::$version);
        try {
            foreach ($apiRequests as $apiRequest) {                
                $packageBoxes = $this->getPackageBoxes($apiClient,$apiRequest, $packageBoxes);
            }
            
            $packageBoxes = $this->addShipAsIsItems($shipAsIsItems,$packageBoxes);
            return $packageBoxes;
        } catch (\Exception $e) {
            FlagshipWoocommerceBedrockShipping::add_log($e->getMessage());
            return $e->getMessage();
        }
    }

    protected function getPackageBoxes($apiClient,$apiRequest, $packageBoxes) {
        
        if(count($apiRequest['items']) > 0){
            FlagshipWoocommerceBedrockShipping::add_log("Packing Request payload: ". json_encode($apiRequest));
    
            $packing_results = $apiClient->packingRequest($apiRequest)->execute();
            FlagshipWoocommerceBedrockShipping::add_log("Packing Response : ". json_encode($packing_results));
            $packageBoxes = $this->prepareBoxesFromPackages($packing_results,$packageBoxes); 
        }
        return $packageBoxes;
    }

    protected function prepareBoxesFromPackages($packages,$packageBoxes)
    {
        foreach ($packages as $key => $package) {
            $weight = json_decode(json_encode($package), true)["packing"]["weight"];
            $packageItemsFormatted = $this->getPackageItemsFormattedAsList($package);
            
            $packageBoxes[] = [
                "description" => $package->getBoxModel().' : '.$packageItemsFormatted,
                "length" => $package->getLength(),
                "width" => $package->getWidth(),
                "height" => $package->getHeight(),
                "weight" =>  max($weight,1),
            ];
        }

        return $packageBoxes;
    }

    protected function make_api_request($items, $boxes)
    {
        $shipping_classes = get_terms(array('taxonomy' => 'product_shipping_class', 'hide_empty' => false ));      
        $packageItems = [];
        if (count($shipping_classes) == 0) {
            $boxes = $this->make_boxes_request($boxes);
            
            foreach ($items as $item) {
                
                $packageItems[] = strcasecmp($item['ship_as_is'],'Yes') !== 0 ? $this->getShippingClassItem($item) : [];
            }

            $packageItems = array_values(array_filter($packageItems, function($value) { return $value != NULL; } ));

            return [[
                'items' => $packageItems,
                'boxes' => $boxes,
                'units' => 'imperial',
            ]];
        }

        foreach ($items as $item) {
            if(strcasecmp($item['ship_as_is'],'Yes') == 0) {
                continue;
            }
            if ($item['shipping_class'] != null ) { 
                $packages[$item['shipping_class']]['items'][] = $this->getShippingClassItem($item);
                continue;
            }
            $packages['no_shipping_class']['items'][] = $this->getShippingClassItem($item);
        }

        foreach ($boxes as $box) {
            if (array_key_exists('shipping_class', $box) && $box['shipping_class'] != null) {
                $packages[$box['shipping_class']]['boxes'][] = $this->getShippingClassBox($box);
                continue;
            }
            $packages['no_shipping_class']['boxes'][] = $this->getShippingClassBox($box);
        }

        $packages = $this->addUnits($packages, $boxes);

        return $packages;        
    }

    protected function addUnits($packages, $boxes)
    {
        $packages = array_map(function ($arr) use ($boxes) {
            $arr['units'] = 'imperial';
            if (!array_key_exists('boxes', $arr)) {
                $arr['boxes'] = $this->make_boxes_request($boxes);
            }
            if (!array_key_exists('items', $arr)) {
                $arr = [];
            }

            return $arr;
        }, $packages);

        $packages = array_filter($packages, function ($value) {
            return count($value)>0;
        });
        return $packages;
    }

    protected function getShippingClassBox($box)
    {
        $box['box_model'] = $box['model'];
        $box['weight'] = 0;
        unset($box['id']);
        unset($box['model']);
        unset($box['extra_charge']);
        unset($box['shipping_class']);
        return $box;
    }

    protected function getShippingClassItem($item)
    {
        unset($item['shipping_class']);
        unset($item['ship_as_is']);
        return $item;
    }

    protected function make_boxes_request($boxes)
    {
        return array_map(function ($box) {
            $box['box_model'] = $box['model'];
            $box['weight'] = array_key_exists("weight", $box) ? $box['weight'] : 0;
            unset($box['id']);
            unset($box['model']);
            unset($box['extra_charge']);
            unset($box['shipping_class']);
            return $box;
        }, $boxes);
    }

    protected function addShipAsIsItems($shipAsIsItems, $packageBoxes) {
        return array_merge($packageBoxes,$shipAsIsItems);
    }

    protected function getShipAsIsItems($items, $shipAsIsItems)
    {
        foreach ($items as $item) {
            unset($item['shipping_class']);
            $shipAsIsItems[] = strcasecmp($item['ship_as_is'],'Yes') == 0 ? $item : [];   
        }
        $shipAsIsItems = array_filter($shipAsIsItems, function($value) { return $value != NULL; } );
        return $shipAsIsItems;
    }

    protected function getPackageItemsFormattedAsList($package) {
        $items = array_count_values($package->getItems());
        $formattedList = '';
        foreach ($items as $item => $count) {
            $formattedList .= $item.' x '.$count.',';
        }
        return $formattedList;
    }
}
