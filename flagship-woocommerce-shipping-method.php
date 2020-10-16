<?php
/**
 * Plugin Name: FlagShip Shipping Extension For WooCommerce
 * Plugin URI: https://github.com/flagshipcompany/flagship-for-woocommerce-bedrock.git
 * Description: Obtain FlagShip shipping rates for orders and export order to FlagShip to dispatch shipment.
 * Version: 1.0.3
 * Author: FlagShip Courier Solutions
 * Author URI: https://www.flagshipcompany.com
 * Text Domain: flagship-shipping-extension-for-woocommerce
 * Domain Path: /languages/
 * Requires PHP: 7.3
 * Requires at least: 4.6
 * Tested up to: 5.5
 * WC requires at least: 3.0.0
 * WC tested up to: 4.5.2
 */

defined( 'ABSPATH' ) || exit;

if (!defined( 'FLAGSHIP_PLUGIN_FILE' )) {
	define( 'FLAGSHIP_PLUGIN_FILE', __FILE__ );
}

if (file_exists(dirname( __FILE__ ) . '/env.php')) {
 	include_once dirname( __FILE__ ) . '/env.php';
}

include_once dirname( __FILE__ ) . '/includes/UserFunctions.php';

if (!defined('FLAGSHIP_PLUGIN_NAME')){
	define("FLAGSHIP_PLUGIN_NAME", plugin_basename( __FILE__ ));
}

spl_autoload_register(function ($class) {
	$nameSpace = 'FlagshipWoocommerceBedrock\\';

	if (strncmp($nameSpace, $class, strlen($nameSpace)) === 0) {
		$relativeClass = substr($class, strlen($nameSpace));		
		$filePath = str_replace('\\', '/', $relativeClass);
		include_once('includes/' . $filePath . '.php');
	}
});

$GLOBALS['flagship-woocommerce-shipping'] = FlagshipWoocommerceBedrock\FlagshipWoocommerceBedrockShipping::instance();

if (!class_exists('Flagship\\Shipping\\Flagship')) {
	include_once dirname(__FILE__). '/vendor/autoload.php';
}

if (dirname(dirname( __FILE__ )) == WPMU_PLUGIN_DIR) {
	load_muplugin_textdomain( 'flagship-shipping-extension-for-woocommerce', dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}  else {
	load_plugin_textdomain( 'flagship-shipping-extension-for-woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}

if (defined( 'WP_CLI' ) && WP_CLI) {
	(new FlagshipWoocommerceBedrock\Commands\Console())->add_commands();
}
