<?php
if (!defined('ABSPATH')) exit;

/*
    Plugin Name: FTP Stock Renovus
    Description: Descarga un archivo CSV desde un servidor FTP y actualiza los productos diariamente.
    Version: 1.0
    Author: utxra
    Author URI: https://github.com/utxra
    Description: Plugin para actualizar el stock de los productos para la página web Renovus. Este plugin añade boostrap 5.3.3. a todo el sitio web.
*/
define('PLUGIN_PATH', plugin_dir_url(__FILE__));
define('PLUGIN_NAME', 'ftp-stock');

// Create a table in the database to store the ftp configuration. (id, host, user, password, path)

register_activation_hook(__FILE__, 'my_activation');

register_deactivation_hook(__FILE__, 'my_deactivation');

function my_activation()
{

    date_default_timezone_set('GMT');

    if (!wp_next_scheduled('ftp_stock_cronjob')) {
        wp_schedule_event(strtotime('07:45:00'), 'daily', 'ftp_stock_cronjob');

        write_log('CronJob scheduled successfully');
    }

    write_log('Plugin activated');

    ftp_stock_create_table();
}


function my_deactivation() {
    wp_clear_scheduled_hook( 'ftp_stock_cronjob' );

    ftp_stock_delete_table();

    write_log('Plugin deactivated');
}

// Create a CronJob to update the stock daily at 07:45 AM.

add_action('ftp_stock_cronjob', 'ftp_stock_cronjob');

function ftp_stock_cronjob()
{
    ftp_stock_download();
    write_log('CronJob executed successfully');
}


function write_log($log)
{
    $logFile = plugin_dir_path(__FILE__) . 'logs/logs.log';
    $logMessage = date('Y-m-d H:i:s'). ' | ' . $log;

    // Open the log file in append mode
    $fileHandle = fopen($logFile, 'a');
    // Write the log message to the file
    fwrite($fileHandle, $logMessage . PHP_EOL);

    // Close the file handle
    fclose($fileHandle);
}

function ftp_stock_create_table()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'ftp_stock_connection';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id INT NOT NULL,
        server VARCHAR(100) NOT NULL,
        user VARCHAR(100) NOT NULL,
        passwd VARCHAR(100) NOT NULL,
        port INT DEFAULT 21,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    write_log('Table created successfully');
}

// Insert data into the table via a form in the admin panel (/wp-admin/edit.php?post_type=product&page=custom-page).
// In the table, only one row will be stored.

if (isset($_POST['server']) && isset($_POST['user']) && isset($_POST['passwd'])) {
    ftp_stock_form();
}

add_action('admin_post_ftp_stock_form', 'ftp_stock_form');

function ftp_stock_form()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'ftp_stock_connection';

    $server = $_POST['server'];
    $user = $_POST['user'];
    $passwd = $_POST['passwd'];
    $port = isset($_POST['port']) ? 21 : $_POST['port'];

    $wpdb->query("TRUNCATE TABLE $table_name");

    $wpdb->insert($table_name, array(
        'id' => 1,
        'server' => $server,
        'user' => $user,
        'passwd' => $passwd,
        'port' => $port
    ));

    write_log('FTP Configuration saved successfully');
}

function ftp_stock_delete_table()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'ftp_stock_connection';
    $sql = "DROP TABLE IF EXISTS $table_name";
    $wpdb->query($sql);

    write_log('Table deleted successfully');
}

// FTP Connection and download the CSV file (Executed via custom page).

add_action('admin_post_ftp_stock_download', 'ftp_stock_download');

function ftp_stock_download()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'ftp_stock_connection';
    $result = $wpdb->get_row("SELECT * FROM $table_name WHERE id = 1");

    $server = $result->server;
    $user = $result->user;
    $passwd = $result->passwd;
    $port = $result->port;

    $conn_id = ftp_connect($server, $port);
    $login_result = ftp_login($conn_id, $user, $passwd);

    if ($login_result) {
        $local_file = plugin_dir_path(__FILE__) . './stock/stock.csv';

        // Delete local file if exists to avoid conflicts
        if (file_exists($local_file)) {
            unlink($local_file);
        }

        $server_file = '+34910014300.csv';

        if (ftp_get($conn_id, $local_file, $server_file, FTP_BINARY)) {
            $alert = 0;
            write_log('File downloaded successfully');
            // ftp_stock_create_csv();
            ftp_stock_update();
        } else {
            $alert = 1;
            write_log('Error downloading the file');
        }

        ftp_close($conn_id);
    } else {
        $alert = 2;
        write_log('Error connecting to the FTP server');
    }

    header('Location: edit.php?post_type=product&page=custom-page&alert=' . $alert);
}

// Update the stock of the products with the CSV file.

add_action('admin_post_ftp_stock_update', 'ftp_stock_update');

function ftp_stock_update()
{
    $csvFile = plugin_dir_path(__FILE__) . './stock/stock.csv';
    $separator = ';';

    if (($handle = fopen($csvFile, 'r')) !== false) {
        while (($data = fgetcsv($handle, 1000, $separator)) !== false) {
            $product_sku = $data[0];
            $product_stock = $data[3];

            // Update the stock of the product with the given SKU
            // You can use the $product_sku and $product_stock variables to update the stock

            // Example code to update the stock using WooCommerce API
            $product = wc_get_product_id_by_sku($product_sku);
            if ($product) {
                wc_update_product_stock($product, $product_stock);
            }
            $product_stock = 0;
        }
        fclose($handle);
    }

    write_log('Stock updated successfully');
}

// Add a new page to the WooCommerce Products menu to configure the FTP connection.

add_action('admin_menu', 'woocommerce_custom_page_menu');

function woocommerce_custom_page_menu()
{
    add_submenu_page(
        'edit.php?post_type=product',   // Parent slug (WooCommerce Products menu)
        'FTP Stock Configuration',      // Page title
        'FTP Stock',                    // Menu title
        'manage_woocommerce',           // Capability
        'custom-page',                  // Menu slug
        'conf_menu_page_callback'          // Callback function
    );
}

function conf_menu_page_callback()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'ftp_stock_connection';
    $result = $wpdb->get_row("SELECT * FROM $table_name WHERE id = 1");
    require_once 'conf/ftp-stock-configuration-menu.php';
}

// Add Bootstrap 5.3.3

add_action('wp_enqueue_scripts', 'enqueue_bootstrap_cdn');
add_action('admin_enqueue_scripts', 'enqueue_bootstrap_cdn');

function enqueue_bootstrap_cdn()
{
    // Enqueue Bootstrap CSS
    wp_enqueue_style('bootstrap-css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css', array(), '5.3.3');

    // Enqueue Bootstrap JS
    wp_enqueue_script('bootstrap-js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js', array('jquery'), '5.3.3', true);
}
