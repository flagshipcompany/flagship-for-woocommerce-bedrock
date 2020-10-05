<?php
namespace FlagshipWoocommerceBedrock\Helpers;

class Notification_Helper {

    public function flagship_warning_in_notice()
    {

        if (!isset($_REQUEST['flagship_warning']) || empty($_REQUEST['flagship_warning'])) {
            return;
        }

        $message = trim(sanitize_text_field($_REQUEST['flagship_warning']));

        echo '<div class="notice notice-error is-dismissible"><p><strong>'
        .$message.
        '</strong></p></div>';
    }

    public function add_flagship_sdk_missing_notice()
    {
        echo '<div class="update-nag notice">
              <p>'.esc_html(__( 'To ensure the FlagShip WooCommerce Shipping plugin function properly, please run "composer require flagshipcompany/flagship-api-sdk" to install the required classes', 'flagship-shipping-extension-for-woocommerce')).'</p>
            </div>';
    }

    public function add_tracking_email_invalid_notice()
    {
        echo '<div class="updated notice error">
              <p>'.esc_html(__( 'Email addresses for tracking are invalid.', 'flagship-shipping-extension-for-woocommerce')).'</p>
            </div>';
    }

    public function add_token_invalid_notice()
    {
        echo '<div class="updated notice error">
              <p>'.esc_html(__( 'Invalid FlagShip Token', 'flagship-shipping-extension-for-woocommerce')).'</p>
            </div>';
    }

    public function add_test_env_notice()
    {
        echo '<div class="updated notice error">
              <p>'.__( 'You are using FlagShip in test mode. Any shipments made in the test environment will not be processed', 'flagship-for-woocommerce').'</p>
            </div>';
    }
}
