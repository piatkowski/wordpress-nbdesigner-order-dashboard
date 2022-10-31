<?php

/**
 * Plugin Name:       Panel Zamówień
 * Description:       Panel Zamówień + integracja z NBDesigner
 * Version:           1.1.0
 * Author:            Krzysztof Piątkowski
 * License:           GPL v2
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       zk-panel-zamowien
 */

namespace NBDesignerOrderDashboard;

defined('ABSPATH') || die('No direct script access');

require_once __DIR__ . '/loader.php';

class Plugin
{
    public const DEBUG = true;
    private static $instance = null;
    private $slug = 'nbd_order_dashboard';
    private $version = "1.1.0";

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init()
    {
        add_action('admin_init', [$this, 'add_roles']);
        add_action('admin_init', [$this, 'remove_admin_menu']);
        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts'], 11);
        add_filter('woocommerce_screen_ids', [$this, 'add_screen_to_woocommerce']);
        add_action('admin_footer-woocommerce_page_' . $this->slug, [$this, 'add_html_templates']);
        add_action('woocommerce_product_duplicate', [$this, 'product_duplicate'], 10, 2);

        AjaxOrders::init();
        User::init();
    }

    public function remove_admin_menu()
    {
        $user = wp_get_current_user();
        $roles = ( array ) $user->roles;
        if(in_array('panel_zamowien', $roles)) {
            foreach ($GLOBALS['menu'] as $menu_item) {
                if ($menu_item[2] !== 'woocommerce') {
                    remove_menu_page($menu_item[2]);
                }
            }
            remove_submenu_page('woocommerce', 'wc-admin');
            remove_submenu_page('woocommerce', 'wc-admin&path=/customers');
            remove_submenu_page('woocommerce', 'product-layout-templates');
            remove_submenu_page('woocommerce', 'wc-facebook');
            remove_submenu_page('woocommerce', 'wc-reports');
            remove_submenu_page('woocommerce', 'wc-settings');
            remove_submenu_page('woocommerce', 'wc-status');
            remove_submenu_page('woocommerce', 'woocommerce_activepayments');
            remove_submenu_page('woocommerce', 'wc-addons');
        }
    }

    public function add_roles()
    {
        global $wp_roles;
        if (!isset($wp_roles))
            $wp_roles = new WP_Roles();
        if (!$wp_roles->is_role('panel_zamowien')) {
            $sm = $wp_roles->get_role('shop_manager');
            $wp_roles->add_role('panel_zamowien', 'Panel Zamowien', $sm->capabilities);
        }
    }

    public function add_html_templates()
    {
        include __DIR__ . '/views/templates/order_table_row.html';
        include __DIR__ . '/views/templates/order_table_row_details.html';
    }

    public function add_screen_to_woocommerce($screen_ids)
    {
        $screen_ids[] = 'woocommerce_page_' . $this->slug;
        return $screen_ids;
    }

    public function add_menu_page()
    {
        add_submenu_page(
            'woocommerce',
            'Panel Zamówień',
            'Panel Zamówień',
            'manage_woocommerce',
            $this->slug,
            [$this, 'render_view']
        );
    }

    public function render_view()
    {
        if (isset($_REQUEST['download_pdfs']) && (int)$_REQUEST['download_pdfs'] > 0) {
            $order_id = (int)$_REQUEST['download_pdfs'];
            $order = wc_get_order($order_id);
            $output_file_name = 'Projekty_zam_' . $order_id . '.zip';
            if ($order) {
                $temp_zip_file = tempnam(sys_get_temp_dir(), 'Projekty_zam_' . $order_id);
                $zip = new \ZipArchive();
                $zip->open($temp_zip_file, \ZipArchive::OVERWRITE);
                $projekt = 1;
                foreach ($order->get_items(apply_filters('woocommerce_admin_order_item_types', 'line_item')) as $item_id => $item) {
                    $nbd_item_key = $item->get_meta('_nbd');
                    if ($nbd_item_key) {
                        $path = NBDESIGNER_CUSTOMER_DIR . '/' . $nbd_item_key;
                        $_list_pdfs = file_exists($path . '/pdfs') ? \Nbdesigner_IO::get_list_files($path . '/pdfs', 2) : [];
                        foreach ($_list_pdfs as $_pdf) {
                            if (is_dir($_pdf)) continue;
                            $zip->addFile($_pdf, 'Projekty_zam_' . $order_id . '/projekt_' . $projekt . '/' . basename($_pdf));
                        }
                        $projekt++;
                    }
                }
                $zip->close();
                header('Content-Description: File Transfer');
                header('Content-Type: application/octet-stream');
                header("Content-Disposition: attachment; filename=$output_file_name");
                header('Content-Transfer-Encoding: binary');
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header("Content-Length: " . filesize($temp_zip_file));
                while (ob_get_level()) {
                    ob_end_clean();
                }
                readfile($temp_zip_file);
                unlink($temp_zip_file);
                exit;
            } else {
                ob_end_clean();
            }
            exit;
        }
        include __DIR__ . '/views/wrapper.php';
    }

    public function enqueue_scripts($hook)
    {
        if ($hook === 'woocommerce_page_' . $this->slug) {
            add_thickbox();
            //wp_enqueue_script('backbone');
            //wp_enqueue_script('wc-backbone-modal');
            //wp_enqueue_script('wc-orders');
            wp_register_style(
                'nbd-order-dashboard-css',
                plugins_url('assets/css/styles.min.css', __FILE__),
                [],
                $this->version
            );
            wp_enqueue_style('nbd-order-dashboard-css');
            wp_enqueue_script(
                'nbd-order-dashboard-table',
                plugins_url('assets/js/table' . (self::DEBUG ? '' : '.min') . '.js', __FILE__),
                ['jquery'],
                $this->version
            );
            wp_enqueue_script(
                'nbd-order-dashboard-templates',
                plugins_url('assets/js/templates' . (self::DEBUG ? '' : '.min') . '.js', __FILE__),
                ['nbd-order-dashboard-table'],
                $this->version
            );
            wp_enqueue_script(
                'nbd-order-dashboard-pdf-download',
                plugins_url('assets/js/pdf_download' . (self::DEBUG ? '' : '.min') . '.js', __FILE__),
                ['jquery'],
                $this->version
            );
        }
    }

    public function product_duplicate($duplicate, $product)
    {
        $duplicate_id = $duplicate->get_id();
        $product_id = $product->get_id();
        if ($duplicate_id > 0 && $product_id > 0) {
            $this->make_duplicate_template($product_id, $duplicate_id);
        }
    }

    public function make_duplicate_template($product_id, $duplicate_id)
    {
        global $wpdb;
        $items = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}nbdesigner_templates WHERE product_id = $product_id");

        foreach ($items as $item) {

            $folder = substr(md5(uniqid()), 0, 10);
            $src_path = NBDESIGNER_CUSTOMER_DIR . '/' . $item->folder;
            $dist_path = NBDESIGNER_CUSTOMER_DIR . '/' . $folder;
            \Nbdesigner_IO::copy_dir($src_path, $dist_path);
            $created_date = new \DateTime();
            $wpdb->insert("{$wpdb->prefix}nbdesigner_templates", array(
                'product_id' => $duplicate_id,
                'variation_id' => 0,
                'folder' => $folder,
                'user_id' => get_current_user_id(),
                'created_date' => $created_date->format('Y-m-d H:i:s'),
                'publish' => $item->publish,
                'private' => $item->private,
                'priority' => $item->priority,
                'name' => $item->name,
                'type' => $item->type,
                'resource' => $item->resource,
                'tags' => $item->tags,
                'colors' => $item->colors,
                'thumbnail' => $item->thumbnail
            ));

        }
    }

    protected function __construct()
    {
    }

    protected function __clone()
    {
    }

    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize a singleton.");
    }
}

Plugin::getInstance()->init();
