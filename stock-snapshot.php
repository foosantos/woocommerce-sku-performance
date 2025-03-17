<?php
if (!defined('ABSPATH')) exit;

// Register Custom Post Type
function wcsp_register_stock_snapshot_cpt() {
    register_post_type('stock_snapshot', [
        'labels' => ['name' => 'Stock Snapshots', 'singular_name' => 'Stock Snapshot'],
        'public' => false,
        'show_ui' => true,
        'supports' => ['title'],
        'capability_type' => 'post',
        'capabilities' => ['create_posts' => 'do_not_allow'],
        'map_meta_cap' => true
    ]);
}
add_action('init', 'wcsp_register_stock_snapshot_cpt');

// Store stock snapshot
function wcsp_store_stock_snapshot() {
    $snapshot_id = wp_insert_post([
        'post_title' => 'Stock Snapshot - ' . current_time('Y-m-d'),
        'post_type' => 'stock_snapshot',
        'post_status' => 'publish'
    ]);
    
    if (!$snapshot_id) return;
    
    $products = wc_get_products(['limit' => -1]);
    $stock_data = [];
    
    foreach ($products as $product) {
        if ($product->is_type('variable')) {
            foreach ($product->get_children() as $variation_id) {
                $variation = wc_get_product($variation_id);
                if ($variation) {
                    $stock_data[] = [
                        'sku' => $variation->get_sku(),
                        'stock_quantity' => $variation->get_stock_quantity()
                    ];
                }
            }
        } else {
            $stock_data[] = [
                'sku' => $product->get_sku(),
                'stock_quantity' => $product->get_stock_quantity()
            ];
        }
    }
    update_post_meta($snapshot_id, '_stock_snapshot_data', $stock_data);
}
add_action('wcsp_daily_stock_snapshot', 'wcsp_store_stock_snapshot');

// Add Meta Box
function wcsp_add_stock_snapshot_meta_box() {
    add_meta_box('wc_stock_snapshot_meta', 'Stock Levels', 
        'wcsp_display_stock_snapshot_meta_box', 'stock_snapshot');
}
add_action('add_meta_boxes', 'wcsp_add_stock_snapshot_meta_box');

// Display Meta Box
function wcsp_display_stock_snapshot_meta_box($post) {
    $stock_data = get_post_meta($post->ID, '_stock_snapshot_data', true);
    
    if (empty($stock_data)) {
        echo '<p>No stock data available.</p>';
        return;
    }
    
    echo '<table style="width:100%; border-collapse:collapse;">';
    echo '<tr><th style="border:1px solid #ccc; padding:5px;">SKU</th>
          <th style="border:1px solid #ccc; padding:5px;">Stock</th></tr>';
    
    foreach ($stock_data as $stock) {
        echo '<tr><td style="border:1px solid #ccc; padding:5px;">' . 
             esc_html($stock['sku']) . '</td><td style="border:1px solid #ccc; padding:5px;">' . 
             esc_html($stock['stock_quantity']) . '</td></tr>';
    }
    echo '</table>';
}