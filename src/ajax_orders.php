<?php

namespace NBDesignerOrderDashboard;

class AjaxOrders
{

    public const NONCE = 'nbdod_orders_nonce';
    public const ACTION_PREFIX = 'nbdod_';

    public const ACTIONS = [
        'list_orders',
        'order_details',
        'save_customer_note',
        'list_pdfs',
        'impose',
        'delete_pdf_file',
        'get_order_notes'
    ];

    private static $hidden_order_itemmeta;

    public static function init()
    {
        foreach (self::ACTIONS as $action) {
            add_action(
                'wp_ajax_' . self::ACTION_PREFIX . $action,
                [__CLASS__, $action]
            );
        }

        self::$hidden_order_itemmeta = apply_filters(
            'woocommerce_hidden_order_itemmeta',
            array(
                '_qty',
                '_tax_class',
                '_product_id',
                '_variation_id',
                '_line_subtotal',
                '_line_subtotal_tax',
                '_line_total',
                '_line_tax',
                'method_id',
                'cost',
                '_reduced_stock',
                '_restock_refunded_items',
            )
        );

        add_action('admin_enqueue_scripts', [__CLASS__, 'add_scripts'], 11);
        add_filter('woocommerce_order_data_store_cpt_get_orders_query', [__CLASS__, 'handle_empty_date_paid'], 10, 2);
        add_filter('woocommerce_order_data_store_cpt_get_orders_query', [__CLASS__, 'handle_payment_status_exclusion'], 10, 2);
        add_filter('woocommerce_order_data_store_cpt_get_orders_query', [__CLASS__, 'handle_user_touched'], 10, 2);
        add_filter('woocommerce_order_data_store_cpt_get_orders_query', [__CLASS__, 'handle_last_saved_date'], 10, 2);
        add_filter('posts_orderby', [__CLASS__, 'handle_order_status_sorting'], 10, 2);
    }

    public static function handle_empty_date_paid($query, $query_vars)
    {
        if (!empty($query_vars['not_paid'])) {
            $query['meta_query'][] = array(
                'key' => '_date_paid',
                'compare' => 'NOT EXISTS'
            );
        }
        return $query;
    }

    public static function handle_payment_status_exclusion($query, $query_vars)
    {
        if (!empty($query_vars['cod_exclude'])) {
            $query['meta_query'][] = array(
                'key' => '_payment_method',
                'compare' => '!=',
                'value' => 'cod'
            );
        }
        return $query;
    }

    public static function handle_user_touched($query, $query_vars)
    {
        if (!empty($query_vars['nbdod_user_id'])) {
            $query['meta_query'][] = array(
                'key' => '_nbdod_user_' . $query_vars['nbdod_user_id'],
                'compare' => 'EXISTS'
            );
        }
        return $query;
    }

    public static function handle_last_saved_date($query, $query_vars)
    {

        if (!empty($query_vars['nbdod_last_saved_from'])) {
            $query['meta_query'][] = array(
                'key' => '_nbdod_last_saved',
                'compare' => '>=',
                'value' => $query_vars['nbdod_last_saved_from']
            );
        }
        if (!empty($query_vars['nbdod_last_saved_to'])) {
            $query['meta_query'][] = array(
                'key' => '_nbdod_last_saved',
                'compare' => '<=',
                'value' => $query_vars['nbdod_last_saved_to'] + 86399
            );
        }
        return $query;
    }



    public static function add_scripts()
    {
        wp_add_inline_script(
            'nbd-order-dashboard-table',
            'var nbdod_orders = ' . wp_json_encode(
                array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce(self::NONCE)
                )
            ) . ';'
        );
    }


    public static function handle_order_status_sorting($args, $wp_query)
    {
        if ($wp_query->query_vars['orderby'] == 'order_status') {
            if ($wp_query->query_vars['order']) {
                return 'post_status ' . $wp_query->query_vars['order'];
            } else {
                return 'post_status DESC';
            }
        }
        return $args;
    }

    private static function order_status($status_slug)
    {
        if (in_array($status_slug, array('trash', 'draft'), true)) {
            return (get_post_status_object($status_slug))->label;
        } else {
            return wc_get_order_status_name($status_slug);
        }
    }

    private static function column_order_number($order)
    {
        $buyer = '';

        if ($order->get_billing_first_name() || $order->get_billing_last_name()) {
            $buyer = trim(sprintf(_x('%1$s %2$s', 'full name', 'woocommerce'), $order->get_billing_first_name(), $order->get_billing_last_name()));
        } elseif ($order->get_billing_company()) {
            $buyer = trim($order->get_billing_company());
        } elseif ($order->get_customer_id()) {
            $user = get_user_by('id', $order->get_customer_id());
            $buyer = ucwords($user->display_name);
        }

        $buyer = apply_filters('woocommerce_admin_order_buyer_name', $buyer, $order);

        return '<strong>#' . esc_attr($order->get_order_number()) . ' ' . esc_html($buyer) . '</strong>';
        //'<a href="#" class="order-preview" data-order-id="' . absint( $order->get_id() ) . '" title="' . esc_attr( __( 'Preview', 'woocommerce' ) ) . '">' . esc_html( __( 'Preview', 'woocommerce' ) ) . '</a>';
    }

    private static function payment_information($order)
    {
        if (WC()->payment_gateways()) {
            $payment_gateways = WC()->payment_gateways->payment_gateways();
        } else {
            $payment_gateways = array();
        }

        $payment_method = $order->get_payment_method();

        $output = '';

        if ($payment_method && 'other' !== $payment_method) {
            $output .= sprintf(
                __('Payment via %s', 'woocommerce'),
                esc_html(isset($payment_gateways[$payment_method]) ? $payment_gateways[$payment_method]->get_title() : $payment_method)
            );

            $transaction_id = $order->get_transaction_id();
            if ($transaction_id) {

                $to_add = null;
                if (isset($payment_gateways[$payment_method])) {
                    $url = $payment_gateways[$payment_method]->get_transaction_url($order);
                    if ($url) {
                        $to_add .= ' (<a href="' . esc_url($url) . '" target="_blank">' . esc_html($transaction_id) . '</a>)';
                    }
                }

                $to_add = $to_add ?? ' (' . esc_html($transaction_id) . ')';
                $output .= $to_add;
            }
        }

        if ($order->get_date_paid()) {
            $output .= sprintf(
                __('Paid on %1$s @ %2$s', 'woocommerce'),
                wc_format_datetime($order->get_date_paid()),
                wc_format_datetime($order->get_date_paid(), get_option('time_format'))
            );
        }

        return $output;
    }

    /*   [ENDPOINTS]   */

    public static function list_orders()
    {
        if (!current_user_can('manage_woocommerce')) return;
        if (isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], self::NONCE)) {

            $args = [
                'limit' => 30,
                'paged' => absint($_POST['page'] ?? 1),
                'paginate' => true,
                'order' => $_POST['order'] === 'ASC' ? 'ASC' : 'DESC',
            ];

            $orderby = sanitize_text_field($_POST['orderby']);

            if (strlen($orderby) > 0 && $orderby[0] === '_') {
                $args['meta_key'] = $orderby;
                $orderby = 'meta_value_num';
            }

            $args['orderby'] = $orderby;

            /* Get Last Saved Filter */
            $date_saved_from = sanitize_text_field($_POST['date_saved_from']) ?? false;
            $date_saved_to = sanitize_text_field($_POST['date_saved_to']) ?? false;

            if($date_saved_from) {
                $args['nbdod_last_saved_from'] =strtotime($date_saved_from);
            }

            if($date_saved_to) {
                $args['nbdod_last_saved_to'] = strtotime($date_saved_to);
            }

            /* Get Date Filter */
            $date_from = sanitize_text_field($_POST['date_from']) ?? false;
            $date_to = sanitize_text_field($_POST['date_to']) ?? false;
            $date_query = '';
            if ($date_from && $date_to) {
                $date_query = $date_from . '...' . $date_to;
            } elseif ($date_from) {
                $date_query = '>=' . $date_from;
            } elseif ($date_to) {
                $date_query = '<=' . $date_to;
            }

            $args['date_created'] = $date_query;

            /* Get Status Filter */
            $status = $_POST['status'] ?? '';
            if (is_array($status)) {
                $status = [];
                foreach ($_POST['status'] as $slug) {
                    $status[] = sanitize_text_field($slug);
                }
                $args['status'] = $status;
            } else {
                if ($status !== '' && $status !== '0') {
                    $args['status'] = $status;
                }
            }

            /* Get User Filter */
            $user = absint($_POST['user'] ?? 0);
            if ($user > 0)
                $args['nbdod_user_id'] = $user;


            /* Get Text Filter */
            $text_search = sanitize_text_field($_POST['text_search'] ?? '');
            if ($text_search !== '') {
                $search_result = wc_order_search($text_search);
                $args['post__in'] = $search_result;
            }

            /* Get Payment Status Filter */
            $payment_status = sanitize_text_field($_POST['payment_status'] ?? '');

            switch ($payment_status) {
                case 'cod':
                    $args['payment_method'] = 'cod';
                    break;
                case 'paid':
                    $args['date_paid'] = '>0';
                    $args['cod_exclude'] = '1';
                    break;
                case 'not-paid':
                    //$args['not_paid'] = '1';
                    $args['cod_exclude'] = '1';
                    global $wpdb;
                    $rows = $wpdb->get_results("SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = '_date_paid'");
                    $exclude = array_map(function ($row) {
                        return $row->post_id;
                    }, $rows);
                    $args['post__not_in'] = $exclude;
                    break;
            }

            /* Get user */
            $users = [];
            foreach (User::list_users() as $user) {
                $users[$user->ID] = $user->display_name;
            }

            /* Query Orders */
            $result = wc_get_orders($args);
            $orders = [];
            $preloaded = [];

            if ($result->total > 0) {
                foreach ($result->orders as $order) {

                    $users_touched = ($order->get_meta('_nbdod_user'));
                    $users_text = [];
                    if (is_array($users_touched)) {
                        foreach ($users_touched as $user_id) {
                            $users_text[] = $users[$user_id];
                        }
                    }

                    $status = $order->get_status();
                    $id = $order->get_id();
                    $preloaded[$id] = self::_order_details($order);
                    $has_customer_note = trim($order->get_customer_note()) !== '' && !preg_match('/^Oto numereek zamowienia:[0-9]+$/', $order->get_customer_note());
                    $orders[] = [
                        'id' => $order->get_id(),
                        'status' => $status,
                        'status_name' => self::order_status($status),
                        'is_allegro' => strpos($order->get_billing_email(), '@allegromail.pl') !== false,
                        'date_created' => $order->get_date_created()->date("d.m.Y H:i"),
                        'total' => number_format($order->get_total(), 2, ',', ' '),
                        'customer_note' => $has_customer_note,
                        'is_paid' => (bool)$order->get_date_paid(),
                        'payment_method' => $order->get_payment_method(),
                        'edit_order_link' => get_edit_post_link($id),
                        'order_number' => self::column_order_number($order),
                        'nbd_last_saved_date' => $preloaded[$id]['nbd_last_saved_date'],
                        'user' => join(", ", $users_text)
                    ];

                }
            }

            wp_send_json([
                'date_query' => $date_query,
                'total' => $result->total,
                'max_num_pages' => $result->max_num_pages,
                'orders' => $orders,
                'preloaded' => $preloaded
            ]);
        }
    }

    public static function save_customer_note()
    {
        if (!current_user_can('manage_woocommerce')) return;
        if (isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], self::NONCE)) {
            $order_id = (int)$_POST['order_id'];
            if ($order_id > 0) {
                $order = wc_get_order($order_id);
                if ($order) {
                    $order->set_customer_note(sanitize_textarea_field($_POST['note']));
                    $order->save();
                    wp_send_json([
                        'saved' => $order->get_customer_note(),
                        'result' => 'success'
                    ]);
                }
            }
            wp_send_json([
                'saved' => 'Wystąpił błąd podczas zapisu danych!',
                'result' => 'error'
            ]);
        }
    }

    public static function order_details()
    {
        if (!current_user_can('manage_woocommerce')) return;
        if (isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], self::NONCE)) {
            $order_id = (int)$_POST['order_id'];
            if ($order_id) {
                $order = wc_get_order($order_id);
                wp_send_json(self::_order_details($order));
            }
            wp_send_json([]);
        }
    }

    public static function _order_details($order)
    {
        $order_items = [];
        $last_saved_date = 0;

        $data_designs = [];
        $_data_designs = unserialize(get_post_meta($order->get_id(), '_nbdesigner_design_file', true));
        if (isset($_data_designs) && is_array($_data_designs)) {
            $data_designs = $_data_designs;
        }

        $last_saved_date_meta = (int)$order->get_meta('_nbdod_last_saved');
        if ($last_saved_date_meta) {
            $last_saved_date = $last_saved_date_meta;
        }

        foreach ($order->get_items(apply_filters('woocommerce_admin_order_item_types', 'line_item')) as $item_id => $item) {

            $product = $item->get_product();
            $product_link = $product ? admin_url('post.php?post=' . $item->get_product_id() . '&action=edit') : '';
            $thumbnail = $product ? apply_filters('woocommerce_admin_order_item_thumbnail', $product->get_image('thumbnail', array('title' => ''), false), $item_id, $item) : '';

            $qty_refunded = -1 * $order->get_qty_refunded_for_item($item_id);
            if ($qty_refunded === 0) $qty_refunded = '';

            $refund = -1 * $order->get_total_refunded_for_item($item_id);
            $refund = $refund === 0 ? '' : wc_price($refund, array('currency' => $order->get_currency()));

            $nbd_item_key = $item->get_meta('_nbd');

            $nbd_preview = [];
            $link_view_detail = '';
            $project_saved_time = 0;
            $edit_design_link = '';
            $has_personalization = false;
            $edit_personalization_link = '';
            $product_id = '';

            /* Nbd image preview */
            if ($nbd_item_key) {
                $prev_folder = NBDESIGNER_CUSTOMER_DIR . '/' . $nbd_item_key . '/preview';
                $list_images = \Nbdesigner_IO::get_list_images($prev_folder, 1);
                asort($list_images);
                foreach ($list_images as $image) {
                    $nbd_preview[] = \Nbdesigner_IO::convert_path_to_url($image) . '?v=' . time();
                }

                $product_id = $item->get_product_id();
                $variation_id = $item->get_variation_id();

                /* View Order link */
                $link_view_detail = add_query_arg(array(
                    'nbd_item_key' => $nbd_item_key,
                    'order_id' => $order->ID,
                    'product_id' => $product_id,
                    'variation_id' => $variation_id
                ), admin_url('admin.php?page=nbdesigner_detail_order'));

                /* Modification date */

                $modification_file = $prev_folder . '/customer_modified';
                if (file_exists($modification_file)) {
                    $modification_time = filemtime($modification_file);
                } else {
                    $modification_time = 0;
                }

                $project_saved_time = '';
                foreach ($list_images as $file) {
                    $time = $modification_time > 0 ? $modification_time : filemtime($file);
                    if ($time > $last_saved_date) {
                        $last_saved_date = $time;
                    }
                    $project_saved_time = date('d.m.Y', $time) . ' ' . date('H:i', $time);
                }


                /* Editors Link */
                $layout = \nbd_get_product_layout($product_id);
                $edit_design_link = add_query_arg(
                    array(
                        'task' => 'edit',
                        'view' => $layout,
                        'nbd_item_key' => $nbd_item_key,
                        'design_type' => 'edit_order',
                        'order_id' => $order->ID,
                        'product_id' => $product_id,
                        'variation_id' => $variation_id,
                        'rd' => 'admin_order'),
                    getUrlPageNBD('create'));

                $has_personalization = file_exists(NBDESIGNER_CUSTOMER_DIR . '/' . $nbd_item_key . '/has_personalization.json');

                $edit_personalization_link = add_query_arg(
                    array(
                        'task' => 'edit',
                        'view' => 'x',
                        'nbd_item_key' => $nbd_item_key,
                        'design_type' => 'edit_order',
                        'order_id' => $order->ID,
                        'product_id' => $product_id,
                        'variation_id' => $variation_id,
                        'rd' => 'admin_order'),
                    getUrlPageNBD('create'));
            }

            $index_accept = 'nbds_' . $item_id;
            $approve_status = '';
            $approve_status_text = '';
            if (isset($data_designs[$index_accept])) {
                $approve_status = ($data_designs[$index_accept] == 'accept') ? 'approved' : 'declined';
                $approve_status_text = ($data_designs[$index_accept] == 'accept') ? '<br />Projekt zaakceptowany' : '<br />Projekt odrzucony';
            }


            $order_items[] = [
                'id' => $item_id,
                'img' => $thumbnail,
                'name' => $item->get_name(),
                'edit_link' => $product_link,
                'cost' => wc_price($order->get_item_subtotal($item, true, true), array('currency' => $order->get_currency())),
                'qty' => $item->get_quantity(),
                'qty_refunded' => $qty_refunded,
                'total' => wc_price($item->get_total(), array('currency' => $order->get_currency())),
                'total_tax' => wc_price($item->get_total() + $item->get_total_tax(), array('currency' => $order->get_currency())),
                'discount' => $item->get_subtotal() !== $item->get_total() ? '<span class="wc-order-item-discount">' . sprintf(esc_html__('%s discount', 'woocommerce'), wc_price(wc_format_decimal($item->get_subtotal() - $item->get_total(), ''), array('currency' => $order->get_currency()))) . '</span>' : '',
                'refund' => $refund,
                'nbd_item_key' => $nbd_item_key,
                'nbd_preview' => $nbd_preview,
                'nbd_link' => $link_view_detail,
                'nbd_date_saved' => $project_saved_time,
                'nbd_edit_design_link' => $edit_design_link,
                'nbd_edit_personalization_link' => $edit_personalization_link,
                'nbd_has_personalization' => $has_personalization,
                'nbd_list_pdfs' => self::_list_pdfs($nbd_item_key),
                'product_id' => $product_id,
                'approve_status' => $approve_status,
                'approve_status_text' => $approve_status_text
            ];
        }

        if ($last_saved_date > 0 && $last_saved_date_meta !== $last_saved_date) {
            $order->update_meta_data('_nbdod_last_saved', $last_saved_date);
            $order->save();
        }

        return ([
            'order_id' => $order->ID,
            'payment_information' => self::payment_information($order),
            'billing_address' => $order->get_formatted_billing_address(),
            'shipping_address' => $order->get_formatted_shipping_address(),
            'customer_note' => $order->get_customer_note(),
            'phone' => $order->get_billing_phone(),
            'email' => $order->get_billing_email(),
            'nbd_last_saved_date' => $last_saved_date ? date('d.m.Y', $last_saved_date) : '',
            'order_items' => $order_items
        ]);
    }

    public static function _list_pdfs($nbd_item_key)
    {
        $path = NBDESIGNER_CUSTOMER_DIR . '/' . $nbd_item_key;
        $_list_pdfs = file_exists($path . '/pdfs') ? \Nbdesigner_IO::get_list_files($path . '/pdfs', 2) : [];
        $list_pdfs = [];
        foreach ($_list_pdfs as $_pdf) {
            if (is_dir($_pdf)) continue;
            $list_pdfs[] = [
                'title' => basename($_pdf),
                'url' => strtok(\Nbdesigner_IO::wp_convert_path_to_url($_pdf), '?'),
                'path' => $_pdf,
                'date_modified' => date('d.m.Y H:i', filemtime($_pdf))
            ];
        }
        return $list_pdfs;
    }

    public static function list_pdfs()
    {
        if (!current_user_can('manage_woocommerce')) return;
        if (isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], self::NONCE)) {
            $nbd_item_key = str_replace('.', '', sanitize_text_field($_POST['nbd_item_key']));
            $list_pdfs = self::_list_pdfs($nbd_item_key);
            return wp_send_json($list_pdfs);
        }
    }

    public static function impose()
    {
        if (!current_user_can('manage_woocommerce')) return;
        /**
         * product_id
         * nbd_item_key
         * input_file
         */
        if (
            isset($_POST['nbd_item_key'])
            && isset($_POST['input_file'])
            && isset($_POST['_wpnonce'])
            && isset($_POST['product_id'])
            && wp_verify_nonce($_POST['_wpnonce'], self::NONCE)
        ) {
            $nbd_item_key = str_replace('.', '', $_POST['nbd_item_key']);
            $path = NBDESIGNER_CUSTOMER_DIR . '/' . $nbd_item_key . '/pdfs';
            $customer_pdfs = file_exists($path) ? \Nbdesigner_IO::get_list_files($path, 2) : array();
            $product_id = absint($_POST['product_id']);
            $input_file = strtok(urldecode($_POST['input_file']), '?');

            if (substr($input_file, 0, 4) === "http") {
                $input_file = \Nbdesigner_IO::convert_url_to_path($input_file);
            }

            if (in_array($input_file, $customer_pdfs)) {
                $preset_id = unserialize(get_post_meta($product_id, '_nbdi_presets', true));
                if (!$preset_id) {
                    wp_send_json([
                        'response' => 'failed',
                        'message' => 'Brak ustawień impozycji!'
                    ]);
                }
                $preset_id = $preset_id[0];
                $presets = \NBDImposer\PresetPostType::getInstance();
                $values = array();

                foreach ($presets->fields as $field) {
                    $values[$field] = (float)get_post_meta($preset_id, $presets::FIELD_PREFIX . $field, true);
                }

                $options = array();
                $imposer = new \NBDImposer\PDFImposer($values['width'], $values['height'], $input_file, $values['rows'], $values['cols'], $values['spacing'], $options);
                $imposer->impose(
                    $values['scale'],
                    array($values['mode_f'], $values['mode_b']),
                    array($values['rotation_f'], $values['rotation_b'])
                );
                $basename = substr($input_file, strrpos($input_file, '/') + 1);
                $output_file = $path . '/IMP_' . $basename;
                $imposer->output($output_file, 'F');
                wp_send_json([
                    'response' => 'success',
                    'preset_id' => $preset_id,
                    'parameters' => $values,
                    'output' => strtok(\NBDesigner_IO::convert_path_to_url($output_file), '?')
                ]);
            }
        }
    }

    public static function delete_pdf_file()
    {
        if (!current_user_can('manage_woocommerce')) return;

        if (isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], self::NONCE) && !empty($_POST['file']) && !empty($_POST['nbd_item_key'])) {
            $nbd_item_key = str_replace('.', '', sanitize_text_field($_POST['nbd_item_key']));
            $list_pdfs = self::_list_pdfs($nbd_item_key);
            foreach ($list_pdfs as $file) {
                if ($file['title'] === urldecode($_POST['file']) || $file['title'] === $_POST['file']) {
                    unlink($file['path']);
                    wp_send_json([
                        'response' => 'success'
                    ]);
                    exit;
                }
            }
        }
        wp_send_json([
            'response' => 'not found'
        ]);
    }

    public static function get_order_notes()
    {
        if (!current_user_can('manage_woocommerce')) return;

        if (isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], self::NONCE) && !empty($_POST['order_id']) && (int)$_POST['order_id'] > 0) {
            ob_start();
            $notes = wc_get_order_notes(['order_id' => (int)$_POST['order_id']]);
            ?>
            <ul class="order_notes">
                <?php
                if ($notes) {
                    foreach ($notes as $note) {
                        $css_class = array('note');
                        $css_class[] = $note->customer_note ? 'customer-note' : '';
                        $css_class[] = 'system' === $note->added_by ? 'system-note' : '';
                        $css_class = apply_filters('woocommerce_order_note_class', array_filter($css_class), $note);
                        ?>
                        <li rel="<?php echo absint($note->id); ?>"
                            class="<?php echo esc_attr(implode(' ', $css_class)); ?>">
                            <div class="note_content">
                                <abbr class="exact-date"
                                      title="<?php echo esc_attr($note->date_created->date('Y-m-d H:i:s')); ?>">
                                    <?php
                                    /* translators: %1$s: note date %2$s: note time */
                                    echo esc_html(sprintf(__('%1$s at %2$s', 'woocommerce'), $note->date_created->date_i18n(wc_date_format()), $note->date_created->date_i18n(wc_time_format())));
                                    ?>
                                </abbr>
                                <?php
                                if ('system' !== $note->added_by) :
                                    /* translators: %s: note author */
                                    echo esc_html(sprintf(' ' . __('by %s', 'woocommerce'), $note->added_by));
                                endif;
                                ?>

                                <?php echo wpautop(wptexturize(wp_kses_post($note->content))); // @codingStandardsIgnoreLine ?>
                            </div>

                        </li>
                        <?php
                    }
                } else {
                    ?>
                    <li class="no-items"><?php esc_html_e('There are no notes yet.', 'woocommerce'); ?></li>
                    <?php
                }
                ?>
            </ul>
            <?php
            return wp_send_json([
                'order_notes' => ob_get_clean()
            ]);
        }
    }

}