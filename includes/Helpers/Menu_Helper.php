<?php
namespace FlagshipWoocommerce\Helpers;

use FlagshipWoocommerce\FlagshipWoocommerceShipping;
use FlagshipWoocommerce\REST_Controllers\Package_Box_Controller;

class Menu_Helper {

    public static $menuItemUri = 'flagship/ship';

    protected $flagshipUrlMap = array(
        'flagship_shipment' => 'shipping/ship',
        'flagship_manage_shipment' => 'shipping/manage',
    );

    public function add_flagship_to_menu($items) 
    {
        add_menu_page( 'FlagShip', 'FlagShip', 'manage_options', self::$menuItemUri, '', plugin_dir_url(FLAGSHIP_PLUGIN_FILE).'assets/images/flagship_logo.svg', 56.6);

        add_submenu_page(self::$menuItemUri, __( 'Shipment', 'flagship-woocommerce-extension'), __( 'Shipment', 'flagship-woocommerce-extension'), 'manage_options', self::$menuItemUri, array($this, 'load_flagship_shipment_page'));
        add_submenu_page(self::$menuItemUri, __( 'Manage shipment', 'flagship-woocommerce-extension'), __( 'Manage shipment', 'flagship-woocommerce-extension'), 'manage_options', 'flagship/manage', array($this, 'load_flagship_manage_shipment_page'));
        add_submenu_page(self::$menuItemUri, __( 'Packing boxes', 'flagship-woocommerce-extension'), __( 'Packing boxes', 'flagship-woocommerce-extension'), 'manage_options', 'flagship/boxes', array($this, 'list_boxes'));

        $this->add_settings_link();
        $this->add_flagship_link();
    }

    public function __call($function, $args)
    {
        $matched = preg_match('/^load_(\w+)_page$/', $function, $matches);

        if ($matched && isset($matches[1])) {
            $this->load_page($matches[1]);

            return;
        }

        $this->$function($args);
    }

    public function load_page($pageName)
    {
        $flagshipUrl = FlagshipWoocommerceShipping::getFlagshipUrl();
        $pageUri =!empty($_GET['flagship_uri']) ? $_GET['flagship_uri'] : $this->flagshipUrlMap[$pageName];
        $iframePageUrl = $flagshipUrl.'/'.$pageUri.'?ex-iframe=true';

        Template_Helper::render_html('flagship_page.html', array(
            'iframePageUrl' => $iframePageUrl,
        ));
    }

    public function add_flagship_link()
    {
        global $submenu;

        $submenu[self::$menuItemUri][] = array(
            sprintf(
                '<a href="%s" target="_blank">%s <span class="dashicons dashicons-external"></span></a>',
                FlagshipWoocommerceShipping::getFlagshipUrl().'?ex-iframe=false',
                __('Visit FlagShip site', 'flagship-woocommerce-extension'),
            ),
            "manage_options",
            "flagship\/site",
            "Visit FlagShip site"
        );
    }

    public function add_settings_link()
    {
        global $submenu;

        $settingsUrl = 'admin.php?page=wc-settings&tab=shipping&section='.FlagshipWoocommerceShipping::$methodId;

        $submenu[self::$menuItemUri][] = array(
            sprintf(
                '<a href="%s">%s </a>',
                $settingsUrl,
                __('Settings', 'flagship-woocommerce-extension'),
            ),
            "manage_options",
            "flagship\/settings",
            "Settings"
        );
    }

    public function list_boxes()
    {
        Template_Helper::render_php('list_boxes.php', array(
            'get_boxes_url' => rest_url(Package_Box_Controller::get_namespace().'/package_boxes/get'),
            'save_boxes_url' => rest_url(Package_Box_Controller::get_namespace().'/package_boxes/save'),
        ));
    }
}