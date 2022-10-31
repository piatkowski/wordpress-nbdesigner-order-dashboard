<?php

use NBDesignerOrderDashboard\Filter;

defined('ABSPATH') || die('No direct script access');
?>

<div class="filters">
    <form id="nbdod_filter_form">
        <input type="hidden" name="action" value="nbdod_list_orders" autocomplete="off" />
        <input type="hidden" name="page" value="1" autocomplete="off" />
        <input type="hidden" name="orderby" value="date_created" autocomplete="off" />
        <input type="hidden" name="order" value="DESC" autocomplete="off" />

        <?php wp_nonce_field(\NBDesignerOrderDashboard\AjaxOrders::NONCE); ?>
        <div class="row">
            <div class="col w-25">
                <label>
                    <span>Data zamówienia od:</span>
                    <input type="date" name="date_from" autocomplete="off">
                </label>
            </div>
            <div class="col w-25">
                <label>
                    <span>Data zamówienia do:</span>
                    <input type="date" name="date_to" autocomplete="off">
                </label>
            </div>
            <div class="col w-25">
                <label>
                    <span>Status zamówienia:</span>
                    <?php Filter::select_field_statuses(); ?>
                </label>
            </div>
            <div class="col w-25">
                <label>
                    <span>Pracownik:</span>
                    <?php Filter::select_field_users(); ?>
                </label>
            </div>
        </div>

        <div class="row">
            <div class="col w-3-8">
                <label>
                    <span>Wyszukaj:</span>
                    <input type="text" name="text_search" autocomplete="off">
                </label>
            </div>
            <div class="col w-2-8">
                <label>
                    <span>Opłata za zamówienie</span>
                    <?php Filter::select_field_payment(); ?>
                </label>
            </div>
            <div class="col w-2-8">
                <div class="nav">
                    <button type="submit" class="button prev" id="filter-prev-page" disabled>&lt;</button>
                    <span class="text" id="navigation_text">Strona 1 z 1</span>
                    <button type="submit" class="button next" id="filter-next-page" disabled>&gt;</button>
                </div>
            </div>
            <div class="col w-1-8">
                <button type="submit" class="button button-primary filter-button" id="filter-button">
                    <span class="dashicons dashicons-update"></span> Filtruj
                </button>
            </div>
        </div>

        <div class="row">
            <div class="col w-25">
                <label>
                    <span>Proj. uzup. od:</span>
                    <input type="date" name="date_saved_from" autocomplete="off">
                </label>
            </div>
            <div class="col w-25">
                <label>
                    <span>Proj. uzup.  do:</span>
                    <input type="date" name="date_saved_to" autocomplete="off">
                </label>
            </div>
            <div class="col w-50"></div>
        </div>
    </form>
</div>

