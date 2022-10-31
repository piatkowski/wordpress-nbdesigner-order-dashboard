<?php wp_nonce_field( 'approve-designs', '_nbdesigner_approve_nonce' ); ?>
<table class="wp-list-table widefat table-view-list posts striped">
    <thead>
    <tr>
        <td class="check-column"></td>
        <th scope="col" class="manage-column sortable column-primary">
            <a href="#" class="filter-sort" data-sort-column="ID">
                <span>Zamówienie</span>
                <span class="sort-asc dashicons dashicons-arrow-up-alt2"></span>
                <span class="sort-desc dashicons dashicons-arrow-down-alt2"></span>
            </a>
        </th>
        <th scope="col" class="manage-column sortable">
            <a href="#" class="filter-sort active desc" data-sort-column="date">
                <span>Data zam.</span>
                <span class="sort-asc dashicons dashicons-arrow-up-alt2"></span>
                <span class="sort-desc dashicons dashicons-arrow-down-alt2"></span>
            </a>
        </th>
        <th scope="col" class="manage-column sortable">
            <a href="#" class="filter-sort" data-sort-column="order_status">
                <span>Status</span>
                <span class="sort-asc dashicons dashicons-arrow-up-alt2"></span>
                <span class="sort-desc dashicons dashicons-arrow-down-alt2"></span>
            </a>
        </th>
        <th scope="col" class="manage-column">
            <span>Pracownik</span>
        </th>
        <th scope="col" class="manage-column sortable">
            <a href="#" class="filter-sort" data-sort-column="_nbdod_last_saved">
                <span>Data<br/>uzup. proj.</span>
                <span class="sort-asc dashicons dashicons-arrow-up-alt2"></span>
                <span class="sort-desc dashicons dashicons-arrow-down-alt2"></span>
            </a>
        </th>
        <th scope="col" class="manage-column sortable">
            <a href="#" class="filter-sort" data-sort-column="_order_total">
                <span>Wartość</span>
                <span class="sort-asc dashicons dashicons-arrow-up-alt2"></span>
                <span class="sort-desc dashicons dashicons-arrow-down-alt2"></span>
            </a>
        </th>
        <th scope="col" class="manage-column">
            <span>Uwagi</span>
        </th>
        <th scope="col" class="manage-column"></th>
    </tr>
    </thead>

    <tbody id="order-list"></tbody>
</table>
