<?php
/*
Plugin Name: SKU Performance for WooCommerce
Description: Tracks WooCommerce stock levels and provides performance insights
Version: 0.1
Author: Felipe Santos
License: GPL2 or later
*/

if (!defined('ABSPATH')) exit;

// Include required files
require_once plugin_dir_path(__FILE__) . 'stock-snapshot.php';
require_once plugin_dir_path(__FILE__) . 'sku-performance-table.php';

// Plugin activation
function wcsp_activate_plugin() {
    if (!wp_next_scheduled('wcsp_daily_stock_snapshot')) {
        wp_schedule_event(time(), 'daily', 'wcsp_daily_stock_snapshot');
    }
    wcsp_store_stock_snapshot();
}
register_activation_hook(__FILE__, 'wcsp_activate_plugin');

// Plugin deactivation
function wcsp_deactivate_plugin() {
    wp_clear_scheduled_hook('wcsp_daily_stock_snapshot');
}
register_deactivation_hook(__FILE__, 'wcsp_deactivate_plugin');