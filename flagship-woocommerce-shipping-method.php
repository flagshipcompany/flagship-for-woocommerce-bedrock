<?php
/**
 * Plugin Name: Flagship
 * Plugin URI: https://www.flagshipcompany.com
 * Description: Woocommerce plugin for flagship.
 * Version: 1.0.0
 * Author: Flagship
 * Author URI: https://www.flagshipcompany.com
 * Text Domain: Flagship
 * Domain Path: /i18n/languages/
 *
 * @package WooCommerce
 */

defined( 'ABSPATH' ) || exit;

if (!defined('WC_FLAGSHIP_ID')){
	define("WC_FLAGSHIP_ID", "wc_flagship_shipping");
}

if (!defined('FLAGSHIP_DEBUG_MODE')){
	define("FLAGSHIP_DEBUG_MODE", true);
}

if (!defined('FLAGSHIP_PLUGIN_NAME')){
	define("FLAGSHIP_PLUGIN_NAME", plugin_basename( __FILE__ ));
}

if (!class_exists( 'FlagshipWoocommerceShipping', false)) {
	include_once dirname( __FILE__ ) . '/includes/FlagshipWoocommerceShipping.php';
	include_once dirname( __FILE__ ) . '/includes/UserFunctions.php';
}

if (in_array('woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option('active_plugins')))) {
	
	function flagshipWoocommerceShipping() {
		return FlagshipWoocommerceShipping::instance();
	}

	$GLOBALS['flagship-woocommerce-shipping'] = flagshipWoocommerceShipping();
}