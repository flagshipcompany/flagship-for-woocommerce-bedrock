<?php
namespace FlagshipWoocommerce\Commands;

use FlagshipWoocommerce\FlagshipWoocommerceShipping;

class Zones_Command {

    /**
    * List all the shipping zones with FlagShip enabled.
    *
    * ## OPTIONS
    *
    * [--enabled]
    * : Return only zones with FlagShip enabled.
    *
    * ## EXAMPLES
    *
    *     # List all the shipping zones (if --enabled is set, only zones with FlagShip enabled).
    *     $ wp fcs zones list [--enabled]
    *     Canada/United States
    */
    public function list($args, $assoc_args) {
        $enabledOnly = isset($assoc_args['enabled']);
        $enabledZones = $this->getShippingZones($enabledOnly);

        array_walk($enabledZones, function($zone) {
            \WP_CLI::line($zone->get_zone_name());
        });
    }

    protected function getShippingZones($enabledOnly = false) {
        $flagshipMethod = FlagshipWoocommerceShipping::$methodId;
        $shippingZones = array_map(function($zone) {
            return new \WC_Shipping_Zone($zone);
        }, \WC_Data_Store::load( 'shipping-zone' )->get_zones());

        if (!$enabledOnly) {
            return $shippingZones;
        }

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
}