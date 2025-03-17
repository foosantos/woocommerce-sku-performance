<?php
if (!defined('ABSPATH')) exit;

// Add admin menu
function wcsp_add_admin_menu() {
    add_menu_page('SKU Performance Table', 'SKU Performance', 
        'manage_options', 'sku-performance-table', 'wcsp_table_page', 'dashicons-list-view');
}
add_action('admin_menu', 'wcsp_add_admin_menu');

// Admin page with performance table
function wcsp_table_page() {
    ?>
    <style>
        .sku-table {width:100%; border-collapse:collapse; margin-top:20px;}
        .sku-table th {background:#f2f2f2; font-weight:bold; text-align:left; padding:8px; border:1px solid #ddd;}
        .sku-table td {padding:8px; border:1px solid #ddd;}
        .sku-table tr:nth-child(even) {background:#f9f9f9;}
        .sku-table tr:hover {background:#eaf6ff;}
        .low-stock {background:#fff0f0; color:#d32f2f;}
        .out-of-stock {background:#ffebee; color:#b71c1c; font-weight:bold;}
        .summary-box {background:#f0f8ff; border:1px solid #b3e0ff; padding:15px; margin-bottom:20px; border-radius:4px;}
    </style>
    
    <div class="wrap">
        <h1><?php echo get_admin_page_title(); ?></h1>
        
        <?php
        // Get first snapshot date
        $first_snapshot_query = new WP_Query([
            'post_type' => 'stock_snapshot',
            'posts_per_page' => 1,
            'orderby' => 'date',
            'order' => 'ASC'
        ]);
        
        if (!$first_snapshot_query->have_posts()) {
            echo '<p>No stock snapshots found. The system will create snapshots automatically each day.</p>';
            return;
        }
        
        $first_snapshot = $first_snapshot_query->posts[0]->post_date;
        $total_snapshots = wp_count_posts('stock_snapshot')->publish;
        ?>
        
        <div class="summary-box">
            <h3>Report Summary</h3>
            <p>Performance from <?php echo date('M j, Y', strtotime($first_snapshot)); ?> to present.</p>
            <p><strong>Total Snapshots:</strong> <?php echo $total_snapshots; ?></p>
            <p><a href="<?php echo admin_url('edit.php?post_type=stock_snapshot'); ?>" class="button">View All Snapshots</a></p>
        </div>
        
        <h2>SKU Performance Table</h2>
        <button id="export-csv" class="button button-primary">Export to CSV</button>
        
        <?php
        // Get products
        $products = wc_get_products(['limit' => 1000, 'return' => 'objects']);
        $variations = [];
        
        // Get variations
        foreach ($products as $key => $product) {
            if ($product->is_type('variable')) {
                foreach ($product->get_available_variations() as $variation_data) {
                    $variation = wc_get_product($variation_data['variation_id']);
                    if ($variation) $variations[] = $variation;
                }
                unset($products[$key]);
            }
        }
        
        $all_products = array_merge($products, $variations);
        
        if (empty($all_products)) {
            echo '<p>No products found.</p>';
            return;
        }
        
        // Prepare data
        $sku_data = [];
        foreach ($all_products as $product) {
            $sku = $product->get_sku();
            if (empty($sku)) continue;
            
            $sku_data[$sku] = [
                'name' => $product->get_name(),
                'id' => $product->get_id(),
                'current_stock' => $product->get_stock_quantity(),
                'days_with_stock' => 0,
                'orders' => 0
            ];
        }
        
        // Process snapshots
        $snapshot_ids = get_posts([
            'post_type' => 'stock_snapshot',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ]);
        
        foreach ($snapshot_ids as $snapshot_id) {
            $snapshot_data = get_post_meta($snapshot_id, '_stock_snapshot_data', true);
            if (!is_array($snapshot_data)) continue;
            
            foreach ($snapshot_data as $item) {
                if (isset($item['sku'], $item['stock_quantity'], $sku_data[$item['sku']])) {
                    if ($item['stock_quantity'] > 0) {
                        $sku_data[$item['sku']]['days_with_stock']++;
                    }
                }
            }
        }
        
        // Get orders
        $orders = wc_get_orders([
            'status' => ['wc-completed', 'wc-processing'],
            'limit' => -1,
            'date_after' => $first_snapshot
        ]);
        
        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                if (!$product) continue;
                
                $sku = $product->get_sku();
                if (empty($sku) || !isset($sku_data[$sku])) continue;
                
                $sku_data[$sku]['orders'] += $item->get_quantity();
            }
        }
        
        // Sort by orders (best selling first)
        uasort($sku_data, function($a, $b) {
            return $b['orders'] - $a['orders'];
        });
        ?>
        
        <table class="sku-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>SKU</th>
                    <th>Orders</th>
                    <th>Days In Stock</th>
                    <th>Current Stock</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sku_data as $sku => $data) : 
                    $stock_class = '';
                    if ($data['current_stock'] === 0) {
                        $stock_class = 'out-of-stock';
                    } elseif ($data['current_stock'] < 5) {
                        $stock_class = 'low-stock';
                    }
                ?>
                <tr>
                    <td><a href="<?php echo admin_url('post.php?post=' . $data['id'] . '&action=edit'); ?>">
                        <?php echo $data['name']; ?></a></td>
                    <td><?php echo $sku; ?></td>
                    <td><?php echo $data['orders']; ?></td>
                    <td><?php echo $data['days_with_stock']; ?></td>
                    <td class="<?php echo $stock_class; ?>"><?php echo $data['current_stock']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('export-csv').addEventListener('click', function() {
            const table = document.querySelector('.sku-table');
            if (!table) return;
            
            let csv = [];
            let rows = table.querySelectorAll('tr');
            
            for (let i = 0; i < rows.length; i++) {
                let row = [], cols = rows[i].querySelectorAll('td, th');
                for (let j = 0; j < cols.length; j++) {
                    let text = cols[j].textContent.trim().replace(/"/g, '""');
                    row.push('"' + text + '"');
                }
                csv.push(row.join(','));
            }
            
            let csvFile = new Blob([csv.join('\n')], {type: 'text/csv'});
            let downloadLink = document.createElement('a');
            downloadLink.download = 'sku-performance-data.csv';
            downloadLink.href = window.URL.createObjectURL(csvFile);
            downloadLink.style.display = 'none';
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);
        });
    });
    </script>
    <?php
}