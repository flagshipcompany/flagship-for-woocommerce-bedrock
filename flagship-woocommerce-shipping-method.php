<?php
/**
 * Plugin Name: FlagShip WooCommerce Extension
 * Plugin URI: https://github.com/flagshipcompany/flagship-for-woocommerce-bedrock.git
 * Description: Obtain FlagShip shipping rates for orders and export order to FlagShip to dispatch shipment.
 * Version: 1.0.0
 * Author: FlagShip Courier Solutions
 * Author URI: https://www.flagshipcompany.com
 * Text Domain: flagship-woocommerce-extension
 * Domain Path: /languages/
 * Requires PHP: 7.1
 * Requires at least: 4.6
 * Tested up to: 5.3
 * WC requires at least: 3.0.0
 * WC tested up to: 3.8.1
 */

defined( 'ABSPATH' ) || exit;

if (file_exists(dirname( __FILE__ ) . '/env.php')) {
 	include_once dirname( __FILE__ ) . '/env.php';
}

include_once dirname( __FILE__ ) . '/includes/UserFunctions.php';

if (!defined('FLAGSHIP_PLUGIN_NAME')){
	define("FLAGSHIP_PLUGIN_NAME", plugin_basename( __FILE__ ));
}

if (in_array('woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option('active_plugins')))) {

	spl_autoload_register(function ($class) {
		$nameSpace = 'FlagshipWoocommerce\\';

		if (strncmp($nameSpace, $class, strlen($nameSpace)) === 0) {
			$relativeClass = substr($class, strlen($nameSpace));
			$filePath = str_replace('\\', '/', $relativeClass);
			include_once('includes/' . $filePath . '.php');
		}
	});

	$GLOBALS['flagship-woocommerce-shipping'] = FlagshipWoocommerce\FlagshipWoocommerceShipping::instance();

	if (dirname(dirname( __FILE__ )) == WPMU_PLUGIN_DIR) {
		load_muplugin_textdomain( 'flagship-woocommerce-extension', dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}  else {
		load_plugin_textdomain( 'flagship-woocommerce-extension', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}
}