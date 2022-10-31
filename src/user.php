<?php

namespace NBDesignerOrderDashboard;

class User
{
    public static function init()
    {
        add_action('after_nbd_save_customer_design', [__CLASS__, 'on_update_project'], 1);
        add_action('wp_ajax_nbd_personalization_save_slide', [__CLASS__, 'on_update_personalization'], 1);
        add_action('wp_ajax_nbdesigner_save_design_to_pdf', [__CLASS__, 'on_update_create_pdf'], 1);
    }

    public static function on_update_project()
    {
        self::on_update('Projekt został zapisany przez pracownika ');
    }

    public static function on_update_personalization()
    {
        self::on_update('Personalizacja została zapisany przez pracownika ');
    }

    public static function on_update_create_pdf()
    {
        self::on_update('Projekt został pobrany przez pracownika ');
    }

    public static function on_update($message)
    {
        if (current_user_can('manage_woocommerce')) {
            self::update_user((int)$_POST['order_id'], $message);
        }
    }

    private static function update_user($order_id, $message)
    {
        $user_id = get_current_user_id();
        if ($order_id > 0 && $user_id > 0) {
            $order = wc_get_order($order_id);
            if ($order) {
                $current_user = wp_get_current_user();
                $users = ($order->get_meta('_nbdod_user'));
                if(is_array($users) && !empty($users)) {
                    if(!in_array($user_id, $users)) {
                        $users[] = $user_id;
                    }
                } else {
                    $users = [ $user_id ];
                }
                $order->update_meta_data('_nbdod_user', ($users));
                $order->update_meta_data('_nbdod_user_' . $user_id, '1');
                $order->add_order_note($message . $current_user->display_name);
                $order->save();
                update_user_meta($user_id, 'nbdod_touched', '1');
            }
        }
    }

    public static function list_users()
    {
        return get_users(array(
            'capability' => 'manage_woocommerce',
            'orderby' => 'user_name',
            'order' => 'ASC',
            'meta_key' => 'nbdod_touched',
            'meta_value' => '1'
        ));
    }
}