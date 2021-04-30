<?php
namespace FlagshipWoocommerceBedrock\Requests;

use Flagship\Shipping\Flagship;
use Flagship\Shipping\Collections\RatesCollection;
use FlagshipWoocommerceBedrock\FlagshipWoocommerceBedrockShipping;
use FlagshipWoocommerceBedrock\Helpers\Package_Helper;

class Rates_Request extends Abstract_Flagship_Api_Request {

    protected $debugMode = false;

    public function __construct($token, $debugMode = false, $testEnv = 0)
    {
        $this->token = $token;
        $this->apiUrl = $this->getApiUrl($testEnv);
        $this->debugMode = $debugMode;
    }

    public function getRates($package, $options = array(), $admin=0, $order=null)
    {
        $this->functionCallFromAdmin = (bool) $admin;

        if($admin==1)
        {
            $orderItems = $this->getOrderItems($order);
            $packages = $this->getPackages($orderItems,$options);
            $sourceAddress = $this->getStoreAddress();

            $shippingAddress = $this->getOrderShippingAddressForRates($order);
            $apiRequest = $this->getRequest($sourceAddress,$shippingAddress,$packages, $options, $order);
        }
        if($admin==0)
        {
            $apiRequest = $this->makeApiRequest($package, $options, $order);
        }

        $apiClient = new Flagship($this->token, $this->apiUrl, 'woocommerce', FlagshipWoocommerceBedrockShipping::$version);

        try{
            $rates = $apiClient->createQuoteRequest(apply_filters( 'fwb_get_rates_request', $apiRequest))->execute();
        }
        catch(\Exception $e){
            $this->debug($e->getMessage());
            $rates = new RatesCollection();
        }
        return apply_filters( 'fwb_get_rates', $rates, $admin);
    }

    public function getOrderItems($order)
    {
        $orderItems = [];
        $items = $order->get_items();
        foreach ($items as $item_id => $value) {
            $item = [];
            $item["product"] = $value->get_product();
            $item["quantity"] = $value->get_quantity();
            $orderItems[] = $item;
        }

        return apply_filters( 'fwb_get_order_items', $orderItems);
    }

    protected function makeApiRequest($package, $options = array(), $order)
    {
        $storeAddress = $this->getStoreAddress();
        $destinationAddress = $this->getDestinationAddress($package['destination'], $this->requiredAddressFields, $options);

        $packages = $this->getPackages($this->extractOrderItems($package),$options);

        $request = $this->getRequest($storeAddress,$destinationAddress,$packages, $options, $order);
        return $request;
    }

    protected function getRequest($sourceAddress, $destinationAddress, $packages, $options, $order)
    {
        $shippingOptions = $this->makeShippingOptions($options);

        $request = array(
            'from' => $sourceAddress,
            'to' => $destinationAddress,
            'packages' => $packages
        );

        $cartSubTotal = $this->functionCallFromAdmin ? $order->get_subtotal() : WC()->cart->subtotal;
        $cartItems = $this->functionCallFromAdmin ? $order->get_items() : WC()->cart->get_cart();

        if (get_array_value($options, 'flagship_insurance', false) && $cartSubTotal >= 101) {
            $shippingOptions['insurance'] = [
                "value" => $cartSubTotal,
                "description" => $this->getInsuranceDescription($cartItems)
            ];
        }

        if ($shippingOptions) {
            $request['options'] = $shippingOptions;
        }

        return $request;

    }

    public function getPackages($orderItems,$options)
    {
        $packageHelper = new Package_Helper($this->debugMode,$this->apiUrl);
        $packages = $packageHelper->make_packages($orderItems,$options);

        return apply_filters( 'fwb_get_packages', $orderItems);
    }

    protected function extractOrderItems($items)
    {
        $orderItems = array();

        foreach ( $items['contents'] as $item_id => $values ) {
            $item = array();
            $item['product'] = $values['data'];
            $item['quantity'] = $values['quantity'];
            $orderItems[] = $item;
        }

        return $orderItems;
    }

    protected function debug($message, $type = 'notice')
    {
        if (FlagshipWoocommerceBedrockShipping::isDebugMode() || $this->debugMode) {
            wc_add_notice($message, $type);
        }
    }

    protected function getInsuranceDescription($items)
    {
        $insuranceDescription = '';
        foreach ( $items as $item) {
            $insuranceDescription .= ($this->functionCallFromAdmin ? $item->get_name() : $item['data']->get_title()).',';

        }
        return rtrim($insuranceDescription,',');
    }
}
