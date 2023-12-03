<?php

/**
 * Plugin Name: STDEV WooCommerce Custom Shipping
 * Plugin URI: 
 * Author: STDEV
 * Author URI: 
 * Description: STDEV WooCommerce Custom Shipping for Cusp
 * Version: 1.0.1
 */
 
 if ( ! defined( 'WPINC' ) ) {
    die;
}

/* 
* Check if WooCommerce is active 
*/
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

    add_action('woocommerce_shipping_init', 'stdev_custom_shipping_init');

    function stdev_custom_shipping_init() {
        if (!class_exists('WC_STDEV_CUSTOM_CUPS_SHIPPING')) {
            class WC_STDEV_CUSTOM_CUPS_SHIPPING extends WC_Shipping_Method {

                public function __construct() {
                    $this->id                 = 'stdev_custom_cups_shipping'; // Unique ID for cups shipping method
                    $this->method_title       = __('WC STDEV Custom Cups Shipping');  // Title shown in admin
                    $this->method_description = __('Description of your WC STDEV Custom Cups Shipping'); // Description shown in admin
                    $this->title              = __('Livraison (Tasses)'); // Displayed title

                    $this->init();
                }

                public function init() {
                    $this->init_form_fields(); // Load the settings API
                    $this->init_settings(); // Load the settings

                    add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
                }

                public function calculate_shipping($package = array()) {
                    $quantity = 0;
                    $has_cups = false;

                    // Get the quantity and check for products with the "Cups" class
                    foreach ($package['contents'] as $item) {
                        $product = wc_get_product($item['data']->get_id());

                        if ($product) {
                            $quantity += $item['quantity'];

                            if (in_array('Cups', wc_get_product_terms($product->get_id(), 'product_shipping_class', array('fields' => 'names')))) {
                                $has_cups = true;
                            }
                        }
                    }

                    // Calculate cups cost based on quantity
                    $cups_cost = 0;

                    if ($has_cups) {
                        if ($quantity >= 36) {
                            $cups_cost = 15 + (($quantity - 36) * 0.4);
                        } elseif ($quantity >= 25) {
                            $cups_cost = 15 + (($quantity - 24) * 0.42);
                        } elseif ($quantity >= 13) {
                            $cups_cost = 12 + (($quantity - 12) * 0.43);
                        } elseif ($quantity >= 7) {
                            $cups_cost = 12 + (($quantity - 6) * 0.45);
                        } elseif ($quantity >= 6) {
                            $cups_cost = 12;
                        }
                    }

                    // Get the fixed cost from WooCommerce settings
                    $flat_rate_cost = floatval(get_option('woocommerce_flat_rate_13_cost'));

                    // Add custom and flat rate cost to the shipping package
                    $rate = array(
                        'id'       => $this->id,
                        'label'    => $this->title,
                        'cost'     => $cups_cost + $flat_rate_cost,
                        'calc_tax' => 'per_item',
                    );

                    $this->add_rate($rate);
                }
            }
        }
    }
}

add_filter('woocommerce_shipping_methods', 'add_stdev_custom_method');

function add_stdev_custom_method($methods) {
    $methods['stdev_custom_cups_shipping'] = 'WC_STDEV_CUSTOM_CUPS_SHIPPING';
    return $methods;
}

add_filter('woocommerce_package_rates', 'filter_shipping_methods_based_on_cart', 10, 2);

function filter_shipping_methods_based_on_cart($rates, $package) {
    $has_cups = false;
    $quantity = 0;

    // Check if there are cups in the cart
    foreach ($package['contents'] as $item) {
        $product = wc_get_product($item['data']->get_id());

        if ($product) {
            $quantity += $item['quantity'];

            if (in_array('Cups', wc_get_product_terms($product->get_id(), 'product_shipping_class', array('fields' => 'names')))) {
                $has_cups = true;
            }
        }
    }

    // If there are cups in the cart, enable only the custom shipping method, otherwise enable only the "Flat Rate" method
    if ($has_cups) {
        $custom_cups_shipping_rate_id = 'stdev_custom_cups_shipping'; // Replace with the actual rate ID for your custom cups method
        $custom_cups_shipping_rate    = $rates[$custom_cups_shipping_rate_id];

        // Clear other shipping methods
        $rates = array();
        $rates[$custom_cups_shipping_rate_id] = $custom_cups_shipping_rate;
    } else {
        // Enable only the "Flat Rate" method with ID 'flat_rate:13' if there are other products in the cart
        if ($quantity > 0) {
            $flat_rate_shipping_rate_id = 'flat_rate:13'; // Replace with the actual rate ID for your flat rate method
            $flat_rate_shipping_rate    = $rates[$flat_rate_shipping_rate_id];
            $rates                      = array();
            $rates[$flat_rate_shipping_rate_id] = $flat_rate_shipping_rate;
        }
    }

    return $rates;
}