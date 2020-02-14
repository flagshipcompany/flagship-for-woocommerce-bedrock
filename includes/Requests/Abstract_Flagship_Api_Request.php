<?php
namespace FlagshipWoocommerce\Requests;

use FlagshipWoocommerce\FlagshipWoocommerceShipping;

abstract class Abstract_Flagship_Api_Request {

	private $token;

	private $apiUrl;

    // address field in request => field in woocommerce address
    private $addressFieldMap = array(
        'postal_code' => 'postcode',
        'address' => 'address_1',
        'suite' => 'address_2',
    );

    protected $requiredAddressFields = array(
        'country',
        'state',
        'postal_code',
        'city',
    );

    protected function getApiUrl()
    {
    	return FlagshipWoocommerceShipping::isDebugMode() ? getenv('FLAGSHIP_API_URL') : 'https://api.smartship.io';
    }

    protected function addHeaders($prepareRequest, $storeName, $orderId)
    {
        return $prepareRequest
            ->setStoreName($storeName)
            ->setOrderId($orderId)
            ->setOrderLink(get_edit_post_link($orderId, null));
    }

    protected function getStoreAddress($fullAddress = false, $getEmail = false)
    {
        $storeAddress = array();

        $storeAddress['postal_code'] = trim(get_option('woocommerce_store_postcode', ''));
        $countryState = $this->getCountryState();
        $storeAddress['country'] = $countryState['country'];
        $storeAddress['state'] = $countryState['state'];
        $storeAddress['city'] = trim(get_option('woocommerce_store_city', ''));

        if ($fullAddress) {
            $storeAddress['address'] = trim(get_option('woocommerce_store_address', ''));
            $storeAddress['suite'] = trim(get_option('woocommerce_store_address_2', ''));
            $storeAddress['name'] = trim(get_option('woocommerce_store_name', ''));
            $storeAddress['attn'] = trim(get_option('woocommerce_store_attn', ''));
            $storeAddress['phone'] = trim(get_option('woocommerce_store_phone', ''));
        }

        if ($getEmail) {
            $storeAddress['email'] = trim(WC()->mailer()->get_emails()['WC_Email_New_Order']->recipient);
        }

        return $storeAddress;
    }

    protected function getCountryState()
    {
    	$countryAndState = array(
			'country' => null,
			'state' => null,
		);
    	$countryState = get_option('woocommerce_default_country', '');

    	if (empty($countryState)) {
    		return $countryAndState;
    	}

    	$splitValues = explode(':', $countryState);
    	$country = isset($splitValues[0]) ? $splitValues[0] : null;
    	$state = isset($splitValues[1]) ? $splitValues[1] : null;

    	return array(
			'country' => $country,
			'state' => $state,
		);
    }

    protected function fillAddressField($destination, $fieldName)
    {
        if (isset($destination[$fieldName])) {
            return trim($destination[$fieldName]);
        }

        $alternativeName = $this->addressFieldMap[$fieldName];

        return isset($destination[$alternativeName]) ? trim($destination[$alternativeName]) : '';
    }

    protected function getDestinationAddress($destination, $addressFields, $options = array())
    {
        $destinationAddress = array();

        foreach ($addressFields as $key => $fieldName) {
            $destinationAddress[$fieldName] = $this->fillAddressField($destination, $fieldName);
        }

        if (isset($options['residential_receiver_address'])) {
            $destinationAddress['is_commercial'] = false;
        }

        return $destinationAddress;
    }

    protected function makeShippingOptions($options)
    {
        $shippingOptions = array();

        if (get_array_value($options, 'rsignature_required', false)) {
            $shippingOptions['signature_required'] = true;
        }

        return $shippingOptions;
    }

    protected function debug($message, $type = 'notice')
    {
        if (FlagshipWoocommerceShipping::isDebugMode() || $this->debugMode) {
            wc_add_notice($message, $type);
        }
    }
}