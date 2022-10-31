<?php

namespace NBDesignerOrderDashboard;

class Filter
{
    public static function select_field_statuses()
    {
        $statuses = wc_get_order_statuses();
        $excluded = ['wc-refunded', 'wc-failed', 'wc-nbdq-new', 'wc-nbdq-pending', 'wc-nbdq-expired', 'wc-nbdq-accepted', 'wc-nbdq-rejected', 'wc-custom1'];

        echo '<select name="status" autocomplete="off">';
        echo '<option value="0">- wszystkie -</option>';
        foreach ($statuses as $status => $status_name) {
            if (in_array($status, $excluded)) continue;
            echo '<option value="' . esc_attr($status) . '">' . esc_html($status_name) . '</option>';
        }
        echo '</select>';
    }

    public static function select_field_users()
    {
        $users = User::list_users();
        echo '<select name="user" autocomplete="off">';
        echo '<option value="0">- wszyscy -</option>';
        foreach ($users as $user) {
            echo '<option value="' . $user->ID . '">' . esc_html($user->display_name) . '</option>';
        }
        echo '</select>';
    }

    public static function select_field_payment()
    {
        ?>
        <select name="payment_status" autocomplete="off">
            <option value="0">- wszystkie -</option>
            <option value="paid">opłacone</option>
            <option value="not-paid">nieopłacone</option>
            <option value="cod">za pobraniem</option>
        </select>
        <?php
    }
}