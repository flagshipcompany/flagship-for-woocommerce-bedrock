<?php
namespace FlagshipWoocommerce\Commands;

use FlagshipWoocommerce\WC_Flagship_Shipping_Method;
use FlagshipWoocommerce\FlagshipWoocommerceShipping;

class Fcs_Command {

    /**
    * Update a FlagShip Woocommerce plugin setting.
    *
    * ## OPTIONS
    *
    * [<key>]
    * : The setting name.
    *
    * [<value>]
    * : The value of setting.
    *
    * [--<field>=<value>]
    * : Associative args for shipping zone.
    *
    * ## EXAMPLES
    *
    *     # Update a general setting.
    *     $ wp fcs update_setting token xxxxxxxxxxxx
    *     Success: Setting saved!
    *
    *     # Update one setting of a shipping zone (if zone name contains space, replace it with underscore).
    *     $ wp fcs update_setting disable_courier_dhl --zone=United_States
    *     Success: Setting saved!
    */
    public function update_setting($args, $assoc_args) {
        $zoneName = isset($assoc_args['zone']) ? $assoc_args['zone'] : null;

        if (!$zoneName) {
            $this->update_general_setting($args);

            return;
        }

        $this->update_zone_setting($zoneName, $args);
    }

    /**
    * List FlagShip Woocommerce plugin settings.
    *
    * ## OPTIONS
    *
    * [--<field>=<value>]
    * : Associative args for shipping zone.
    *
    * ## EXAMPLES
    *
    *     # List all the settings.
    *     $ wp fcs list_settings [--zone=<zone-name>] 
    *     allow_standard_rates/disable_courier_ups
    */
    public function list_settings($args, $assoc_args) {
        $zoneName = isset($assoc_args['zone']) ? $assoc_args['zone'] : null;

        if (!$zoneName) {
            $fields = $this->getSettingFields();

            array_walk($fields, function($val) {
                \WP_CLI::line($val);
            });

            return;
        }

        $instanceId = $this->getInstanceIdByZoneName($zoneName);

        if (is_null($instanceId)) {
            \WP_CLI::error('Invalid zone');

            return;
        }

        $fields = $this->getSettingFields($instanceId);

        array_walk($fields, function($val) {
            \WP_CLI::line($val);
        });
    }

    /**
    * List all the shipping zones with FlagSHip enabled.
    *
    * ## EXAMPLES
    *
    *     # List all the shipping zones with FlagShip enabled.
    *     $ wp fcs list_zones 
    *     Canada/United States
    */
    public function list_zones() {
        $enabledZones = $this->getEnabledShippingZones();

        array_walk($enabledZones, function($zone) {
            \WP_CLI::line($zone->get_zone_name());
        });
    }

    protected function update_general_setting($args) {
        if (!isset($args[0]) || !isset($args[1])) {
            \WP_CLI::error('Invalid arguments!');

            return;
        }

        $generalSettingFields = $this->getSettingFields();

        if (!in_array($args[0], $generalSettingFields)) {
            $msg = sprintf('%s is not a valid setting', $args[0]);
            \WP_CLI::error($msg);

            return;
        }

        $optionName = 'woocommerce_'.FlagshipWoocommerceShipping::$methodId.'_settings';
        $this->updateInstanceOption($optionName, $args[0], $args[1]);

        \WP_CLI::success('Setting saved!');
    }

    protected function update_zone_setting($zoneName, $args) {
        if (!isset($args[0]) || !isset($args[1])) {
            \WP_CLI::error('Invalid arguments!');

            return;
        }

        $instanceId = $this->getInstanceIdByZoneName($zoneName);
        $settingFields = $this->getSettingFields($instanceId);

        if (!in_array($args[0], $settingFields)) {
            $msg = sprintf('%s is not a valid setting', $args[0]);
            \WP_CLI::error($msg);

            return;
        }

        $instanceOptionName = 'woocommerce_'.FlagshipWoocommerceShipping::$methodId.'_'.$instanceId.'_settings';
        $this->updateInstanceOption($instanceOptionName, $args[0], $args[1]);

        \WP_CLI::success('Setting saved!');
    }

    protected function updateInstanceOption($instanceName, $key, $value) {
        $settings = get_option($instanceName, array());
        $settings[$key] = $value;

        return update_option($instanceName, $settings);
    }

    protected function getEnabledShippingZones() {
        $flagshipMethod = FlagshipWoocommerceShipping::$methodId;
        $shippingZones = array_map(function($zone) {
            return new \WC_Shipping_Zone($zone);
        }, \WC_Data_Store::load( 'shipping-zone' )->get_zones());

        $enabledZones = array_filter($shippingZones, function($zone) use ($flagshipMethod) {
            $methods = $zone->get_shipping_methods();
            $methods = array_filter($methods, function($val) {
                return $val->is_enabled();
            });
            $methodNames = array_map(function($val) {
                return $val->id;
            }, $methods);

            return in_array($flagshipMethod, $methodNames);
        });

        return $enabledZones;
    }

    protected function getInstanceIdByZoneName($name) {
        $shippingZone = $this->getShippingZoneByName($name);

        if (!$shippingZone) {
            return;
        }

        $shipmentMethods = $shippingZone->get_shipping_methods();
        $instanceId = null;

        while (is_null($instanceId) && count($shipmentMethods) > 0) {
            $method = array_shift($shipmentMethods);

            if ($method->id == FlagshipWoocommerceShipping::$methodId) {
                $instanceId = $method->get_instance_id();
            }
        }

        return $instanceId;
    }

    protected function getShippingZoneByName($name) {
        $matchedZone = null;
        $shippingZones = \WC_Data_Store::load( 'shipping-zone' )->get_zones();

        while (is_null($matchedZone) && count($shippingZones) > 0) {
            $newZone = new \WC_Shipping_Zone(array_shift($shippingZones));

            if (str_replace(' ', '_', $newZone->get_zone_name()) == $name) {
                $matchedZone = $newZone;
            }            
        }

        return $matchedZone;
    }

    protected function getSettingFields($zoneId = null) {
        $property = $zoneId ? 'instance_form_fields' : 'form_fields';
        $fields = (new WC_Flagship_Shipping_Method())->{$property};
        $fields = array_filter($fields, function($val) {
            return $val['type'] != 'title';
        });

        return array_keys($fields);
    }
}