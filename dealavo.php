<?php
/*
Plugin Name: Dynamic price with Dealavo by [Ramil]
Description: Connection Dealavo and update sales price
Version: 2.0
*/

set_time_limit(6600);

function dealavo_get_csv_data($api_key, $account_id)
{
    $csv_url = "https://app.dealavo.com/files/repricing/repricing.csv?account_id={$account_id}&api_key={$api_key}";
    $csv_data = [];
    $csv_file = fopen($csv_url, 'r');
    if ($csv_file !== false) {
        $header = fgetcsv($csv_file);
        while (($row = fgetcsv($csv_file)) !== false) {
            $csv_data[] = array_combine($header, $row);
        }
        fclose($csv_file);
    }
    return $csv_data;
}

function dealavo_update_sales_prices()
{
    global $wpdb;

    $api_key = get_option('dealavo_api_key');
    $account_id = get_option('dealavo_account_id');

    $csv_data = dealavo_get_csv_data($api_key, $account_id);
    
    $total_products = count($csv_data);
    $updated_products_count = 0;


    foreach ($csv_data as $rowData) {
        $sku = $rowData['ID'];
        $product_id = get_product_id_by_sku($sku);
        $new_price = !empty($rowData['Recommended price for channel: e-shop']) ? floatval($rowData['Recommended price for channel: e-shop']) : floatval($rowData['Price']);

        if ($product_id) {
            $wpdb->update(
                $wpdb->postmeta,
                array('meta_value' => $new_price),
                array(
                    'post_id' => $product_id,
                    'meta_key' => '_sale_price'
                )
            );
            $wpdb->update(
                $wpdb->postmeta,
                array('meta_value' => $new_price),
                array(
                    'post_id' => $product_id,
                    'meta_key' => '_price'
                )
            );
            $updated_products_count++;
            error_log('| Product ID: ' . $product_id . ' New price: ' . $new_price . '|');
        }

    }

    send_tg("[Dealavo]: $updated_products_count products prices updated out of $total_products");
}

add_action('update_dealavo_prices', 'dealavo_update_sales_prices');

function dealavo_activate_plugin()
{
    dealavo_update_sales_prices();
    if (!wp_next_scheduled('update_dealavo_prices')) {
        wp_schedule_event(strtotime('22:00:00'), 'daily', 'update_dealavo_prices');
    }
}
register_activation_hook(__FILE__, 'dealavo_activate_plugin');

function get_product_id_by_sku($sku)
{
    global $wpdb;

    $product_id = $wpdb->get_var($wpdb->prepare("
        SELECT p.ID
        FROM {$wpdb->posts} p
        JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        WHERE pm.meta_key = '_sku' AND pm.meta_value = %s
        LIMIT 1
    ", $sku));

    return $product_id;
}

function dealavo_create_admin_page()
{
    add_menu_page(
        'Dealavo Settings',
        'Dealavo',
        'manage_options',
        'dealavo',
        'dealavo_settings_page_html',
        'dashicons-admin-generic',
        20
    );
}
add_action('admin_menu', 'dealavo_create_admin_page');

function dealavo_settings_page_html()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    if (isset($_POST['dealavo_update_settings'])) {
        update_option('dealavo_api_key', sanitize_text_field($_POST['dealavo_api_key']));
        update_option('dealavo_account_id', sanitize_text_field($_POST['dealavo_account_id']));
    }

    $api_key = get_option('dealavo_api_key', '');
    $account_id = get_option('dealavo_account_id', '');

    echo '<div class="wrap">';
    echo '<h1>Dealavo Settings</h1>';
    echo '<a href="https://t.me/ramil_x">Ramil B. - contact with author</a>';
    echo '<form method="post" action="">';
    echo '<table class="form-table">';
    echo '<tr valign="top">';
    echo '<th scope="row">API Key</th>';
    echo '<td><input type="text" name="dealavo_api_key" value="' . esc_attr($api_key) . '" size="50"/></td>';
    echo '</tr>';
    echo '<tr valign="top">';
    echo '<th scope="row">Account ID</th>';
    echo '<td><input type="text" name="dealavo_account_id" value="' . esc_attr($account_id) . '" size="50"/></td>';
    echo '</tr>';
    echo '</table>';
    echo '<p><input type="submit" name="dealavo_update_settings" class="button button-primary" value="Save Settings"/></p>';
    echo '</form>';

    echo '<h2>Manual Update</h2>';
    echo '<form method="post" action="">';
    echo '<p><input type="submit" name="dealavo_manual_update" class="button button-primary" value="Update Prices Now"/></p>';
    echo '</form>';

    if (isset($_POST['dealavo_manual_update'])) {
        dealavo_update_sales_prices();
        echo '<p>Prices updated successfully!</p>';
    }

    echo '</div>';
}

