<?php
namespace FlagshipWoocommerceBedrock\Helpers;

class Product_Helper
{
    public static $fields = array(
        'country' => '_country_of_origin',
        'hs' => '_hs_code',
    );

    protected $exportTabName = 'flagship_export';

    public function add_export_to_product_tabs($tabs)
    {
        $tabs['export'] = array(
            'label'    => __('Export', 'flagship-shipping-extension-for-woocommerce'),
            'target'   => $this->exportTabName,
            'class'    => array(),
            'priority' => 21,
        );

        return $tabs;
    }

    public function display_product_export_tab()
    {
        echo sprintf('<div id="%s" class="panel woocommerce_options_panel hidden">', $this->exportTabName);

        $countries_obj = new \WC_Countries();
        $countries = $countries_obj->__get('countries');
        $countryOfOrigin = get_post_meta(get_the_ID(), self::$fields['country'], true);

        if (!$countryOfOrigin) {
            $countryOfOrigin = 'CA';
        }

        woocommerce_wp_select(array(
            'id' => self::$fields['country'],
            'value' => $countryOfOrigin,
            'label' => __('Country of origin', 'flagship-shipping-extension-for-woocommerce'),
            'options' => $countries,
        ));

        woocommerce_wp_text_input(array(
            'id'  => self::$fields['hs'],
            'value' => get_post_meta(get_the_ID(), self::$fields['hs'], true),
            'label' => __('HS code'),
            'desc_tip'    => true,
            'description' => __('The HS (Harmonized Commodity Description and Coding System) Code is a 6–10 digit number for international shipments', 'flagship-shipping-extension-for-woocommerce'),
        ));
     
        echo '</div>';
    }

    public function save_product_export_data($post_id)
    {
        foreach (self::$fields as $key => $field) {
            $value = ($_POST[$field]);

            if (!empty($value)) {
                update_post_meta($post_id, $field, esc_attr($value));
            }
        }
    }

    public function add_custom_attributes()
    {
        woocommerce_wp_checkbox([
            'id' => '_ship_as_is',
            'label' => __('Ship As Is', 'flagship-shipping-extension-for-woocommerce'),
            'desc_tip' => true, // true or false, show description directly or as tooltip
            'description' => __('If checked, the product will be shipped in it\'s original packing. It will not be packed in another box.' )
        ]);

        woocommerce_wp_checkbox([
            'id' => '_dangerous_goods',
            'label' => __('Dangerous Goods', 'flagship-shipping-extension-for-woocommerce'),
            'desc_tip' => true, // true or false, show description directly or as tooltip
            'description' => __('We do not support shipping dangerous goods. This item will not be shipped with the rest of the order.' )
        ]);

        woocommerce_wp_text_input([
            'id' => '_ltl_qty',
            'label' => __('LTL Quantity'),
            'desc_tip' => true, // true or false, show description directly or as tooltip
            'description' => __('Specify the minimum LTL quantity for product. If x or more of this product are added to the cart, it will be shipped as LTL.' )
        ]);
    }

    public function save_custom_attributes($post_id)
    {
        $product = wc_get_product($post_id);
        $ship_as_is = isset($_POST['_ship_as_is']) ? $_POST['_ship_as_is'] : '';
        $dangerous_goods = isset($_POST['_dangerous_goods']) ? $_POST['_dangerous_goods'] : '';
        $ltl_qty = isset($_POST['_ltl_qty']) ? $_POST['_ltl_qty'] : '';
        $product->update_meta_data('_ltl_qty', $ltl_qty);
        $product->update_meta_data('_ship_as_is', $ship_as_is);
        $product->update_meta_data('_dangerous_goods', $dangerous_goods);
        $product->save();
    }
}
