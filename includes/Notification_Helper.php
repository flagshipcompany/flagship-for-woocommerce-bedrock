<?php
namespace FlagshipWoocommerce;

class Notification_Helper {

    public function flagship_warning_in_notice() {

        if (!isset($_REQUEST['flagship_warning']) || empty($_REQUEST['flagship_warning'])) {
            return;
        }

        $message = trim($_REQUEST['flagship_warning']);

        echo '<div class="notice notice-error is-dismissible"><p><strong>'
        .$message.
        '</strong></p></div>';
    }

    public function add_flagship_sdk_missing_notice() {
        echo '<div class="update-nag notice">
              <p>'.__( 'To ensure the FlagShip WooCommerce Shipping plugin function properly, please run "composer require flagshipcompany/flagship-api-sdk" to install the required classes', 'flagship-woocommerce-extension').'</p>
            </div>';
    }
    
    public function add_tracking_email_invalid_notice() {
        echo '<div class="updated notice error">
              <p>'.__( 'Email addresses for tracking are invalid.', 'flagship-woocommerce-extension').'</p>
            </div>';
    }
}