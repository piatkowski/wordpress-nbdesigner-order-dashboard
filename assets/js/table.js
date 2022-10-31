(function ($) {
    "use strict";

    var max_num_pages = 1;
    var freeze_page_number = false;
    var responseCache;

    $(document).ready(function ($) {

        $('body').on('click', '.refresh-row', function(e){
            e.preventDefault();
            var button = $(this);
            button.addClass('disabled');
            $("#Row_details").css({opacity: 0.5});
            fetch_new_order_details($(this).data('orderId'), function (response) {
                $("#Row_details").replaceWith($(tmpl('order_table_row_details', response)));
                button.removeClass('disabled');
                $("#Row_details").css({opacity: 1});
            });
        })

        $(window).on('message onmessage', function (e) {
            var data = e.originalEvent.data;
            console.log('Message', data);
            if (data.action === 'closeTB') {
                if(data.order_id) {
                    $("#Row_details").css({opacity: 0.5});
                    fetch_new_order_details(data.order_id, function (response) {
                        $("#Row_details").css({opacity: 1});
                        $("#Row_details").replaceWith($(tmpl('order_table_row_details', response)));
                    });
                }
                $('#TB_closeWindowButton').trigger('click');
            } else if (data.action === 'openPersonalization') {
                console.log('Thickbox redirected to Personalization Editor');
                //$('#TB_closeWindowButton').trigger('click');
            }
        });

        $('body').on('click', '.action-toggle-row', function (e) {
            e.preventDefault();
            var currentRow = $(this).closest('tr');
            var orderId = $(this).data('orderId');

            var selfHide = $(this).children('.dashicons').hasClass('dashicons-minus');

            // 1. Hide expanded row
            $('.action-toggle-row .dashicons.dashicons-minus').removeClass('dashicons-minus').addClass('dashicons-plus');
            $('#Row_details').remove();
            $("#nbdod tr.active").removeClass("active");

            if (!selfHide) {
                // 2. Expand current row
                fetch_order_details(orderId, function (response) {
                    $(tmpl('order_table_row_details', response)).insertAfter(currentRow);
                    var nbd_links = [];

                    for (var i = 0; i < response.order_items.length; i++) {
                        var item = response.order_items[i];
                        nbd_links.push(item.nbd_link);
                    }

                    $(document).trigger('row:opened', [nbd_links]);
                    currentRow.addClass("active");
                    setTimeout(function () {
                        $('html').animate({
                            scrollTop: currentRow.offset().top - 40
                        }, 600);
                    }, 100);
                });

                $(this).children('.dashicons').toggleClass('dashicons-plus').toggleClass('dashicons-minus');
            }

        });

        $('body').on('click', '.edit-customer-note', function (e) {
            e.preventDefault();
            var $input = $('textarea.customer_note');
            $input.prop('readonly', false);
            $('.save-customer-note, .cancel-customer-note, .edit-customer-note').toggle();
        }).on('click', '.save-customer-note', function (e) {
            e.preventDefault();
            // do save
            var $input = $('textarea.customer_note');
            $('.save-customer-note, .cancel-customer-note, .saving-text').toggle();
            $('.save-customer-note, .cancel-customer-note, .edit-customer-note').toggleClass('disabled', true);
            post_save_customer_note($(this).data('orderId'), $input.val(), function (response) {
                if (response && response.result === 'success') {
                    $input.val(response.saved);
                } else {
                    $input.val('Wystąpił błąd!');
                }
                $('.save-customer-note, .cancel-customer-note, .edit-customer-note').toggleClass('disabled', false);
                $('.saving-text, .edit-customer-note').toggle();
            });
            $input.prop('readonly', true);
        }).on('click', '.cancel-customer-note', function (e) {
            e.preventDefault();
            var $input = $('textarea.customer_note');
            $input.val(responseCache.preloaded[$(this).data('orderId')].customer_note);
            $input.prop('readonly', true);
            $('.save-customer-note, .cancel-customer-note, .edit-customer-note').toggle();
        }).on('click', '.order_file_submit', function (e) {
            e.preventDefault();
            if (!confirm('Czy potwierdzasz?')) return;
            var buttons = $(this).parent().find('.order_file_submit');
            var status = $(this).parent().parent().parent().find('.approve_status');
            var destinationStatus = $(this).data('approve');

            buttons.toggleClass('disabled', true);
            var data = {
                action: 'nbdesigner_design_approve',
                _nbdesigner_approve_nonce: $('#_nbdesigner_approve_nonce').val(),
                _wp_http_referer: $('[name=_wp_http_referer]').val(),
                _nbdesigner_design_file: [$(this).data('itemId')],
                nbdesigner_order_file_approve: destinationStatus,
                nbdesigner_design_order_id: $(this).data('orderId')
            }
            $.post(nbdod_orders.ajax_url, data, function (response) {
                if (response.mes == 'success') {
                    buttons.fadeOut(200);
                    buttons.toggleClass('disabled', false);
                    if (destinationStatus === 'accept') {
                        status.css({color: 'green'}).html('<br />Projekt zaakceptowany');
                        status.parent().parent().parent().removeClass('row-declined').addClass('row-accepted');
                        if($('#Row_details .row-accepted').length === $('#Row_details .order_file_submit[data-approve=accept]:not(.disabled)').length) {
                            $('#order-list tr.active, #Row_details').fadeOut('fast', function(){
                                $(this).remove();
                            });
                        }
                    } else {
                        status.css({color: 'red'}).html('<br />Projekt odrzucony');
                        status.parent().parent().parent().addClass('row-declined').removeClass('row-accepted');
                    }
                } else {
                    alert(response.mes);
                }
            }, 'json');
        }).on('click', '.remove_pdf', function (e) {
            e.preventDefault();
            var _this = $(this);
            var file = $(this).parent().find('a').prop('download');
            if (!confirm('Czy na pewno usunąć plik ' + file)) return;
            var nbd_item_key = $(this).parent().parent().parent().data('nbdItemKey');
            $.post(nbdod_orders.ajax_url, {
                action: 'nbdod_delete_pdf_file',
                '_wpnonce': nbdod_orders.nonce,
                nbd_item_key: nbd_item_key,
                file: file
            }, function (response) {
                if (response) {
                    _this.parent().remove();
                }
            });
        }).on('click', '#show_order_notes', function (e) {
            var order_id = $(this).data('orderId');
            $.post(nbdod_orders.ajax_url, {
                action: 'nbdod_get_order_notes',
                '_wpnonce': nbdod_orders.nonce,
                order_id: order_id
            }, function (response) {
                if (response) {
                    $("#order-notes-thickbox, #TB_ajaxContent").html(response.order_notes);
                }
            });
        })

        $('#filter-prev-page').click(function () {
            freeze_page_number = true;
            var page = Number($("#nbdod_filter_form [name=page]").val());
            if (page > 1) {
                page = page - 1;
            }
            $("#nbdod_filter_form [name=page]").val(page);
            updateNavigation();
        });

        $('#filter-next-page').click(function () {
            freeze_page_number = true;
            var page = Number($("#nbdod_filter_form [name=page]").val());
            if (page < max_num_pages) {
                page = page + 1;
            }
            $("#nbdod_filter_form [name=page]").val(page);
            updateNavigation();
        });

        $("#nbdod_filter_form").on('submit', function (e) {
            e.preventDefault();
            if (!freeze_page_number) {
                $("#nbdod_filter_form [name=page]").val(1);
            }
            $("#order-list").html($(tmpl('order_table_row_loading', {})));
            $("#filter-button").prop('disabled', true);
            fetch_orders(function (response) {
                if (response.total === 0) {
                    $("#order-list").html($(tmpl('order_table_empty', {})));
                } else {
                    $("#order-list").html('');
                }
                freeze_page_number = false;
                for (const i in response.orders) {
                    var order = response.orders[i];
                    var html = tmpl('order_table_row', {
                        order_id: order.id,
                        status: order.status,
                        status_name: order.status_name,
                        is_allegro: order.is_allegro,
                        date_created: order.date_created,
                        total: order.total,
                        customer_note: (order.customer_note ? '<span class="dashicons dashicons-warning"></span>' : ''),
                        edit_order_link: order.edit_order_link,
                        order_number: order.order_number,
                        last_saved_date: order.nbd_last_saved_date,
                        payment_icon: order.payment_method === 'cod' ? 'cod' : (order.is_paid ? 'paid' : 'not-paid'), //order.is_paid ? 'paid' : (order.payment_method === 'cod' ? 'cod' : 'not-paid'),
                        user: order.user

                    });
                    $("#order-list").append($(html));
                }

                updateNavigation();
                $("#filter-button").prop('disabled', false);
            });
            setTimeout(function () {
                $("#filter-button").prop('disabled', false);
            }, 5000);
        });

        $('.filter-sort').click(function (e) {
            e.preventDefault();
            if ($(this).hasClass('active')) {
                // change order
                var newOrder = $("input[name=order]").val() === 'DESC' ? 'ASC' : 'DESC';
                $("input[name=order]").val(newOrder);
                $(this).toggleClass('desc', newOrder === 'DESC').toggleClass('asc', newOrder === 'ASC');
            } else {
                //change orderby
                var sort_by = $(this).data('sortColumn');
                $('.filter-sort.active').removeClass(['active', 'desc', 'asc']);
                $(this).addClass(['active', 'desc']).removeClass('asc');
                $("input[name=orderby]").val(sort_by);
                $("input[name=order]").val('DESC');
            }
            $("#nbdod_filter_form").trigger('submit');
        });

        /* Initializations */

        init();
    });

    function fetch_orders(callback) {
        var data = $("#nbdod_filter_form").serialize();
        $.post(nbdod_orders.ajax_url, data, function (response) {
            if (response) {
                responseCache = response;
                max_num_pages = response.max_num_pages;
                if (max_num_pages === 0) {
                    max_num_pages = 1;
                    $("#nbdod_filter_form [name=page]").val(1);
                }
                callback(response);
            }
        });
    }

    function fetch_order_details(id, callback) {
        if (responseCache && responseCache.preloaded && responseCache.preloaded[id]) {
            callback(responseCache.preloaded[id]);
        }
        /*$.post(nbdod_orders.ajax_url, {action: 'nbdod_order_details', '_wpnonce': nbdod_orders.nonce, order_id: id}, function (response) {
            if (response) {
                callback(response);
            }
        });*/
    }

    function fetch_new_order_details(id, callback) {
        $.post(nbdod_orders.ajax_url, {action: 'nbdod_order_details', '_wpnonce': nbdod_orders.nonce, order_id: id}, function (response) {
            if (response) {
                callback(response);
            }
        });
    }

    function post_save_customer_note(id, note, callback) {
        $.post(nbdod_orders.ajax_url, {
            action: 'nbdod_save_customer_note',
            '_wpnonce': nbdod_orders.nonce,
            order_id: id,
            note: note
        }, function (response) {
            if (response) {
                callback(response);
            }
        });
    }

    function updateNavigation() {
        var page = Number($("#nbdod_filter_form [name=page]").val());
        $('#filter-prev-page').prop("disabled", max_num_pages === 1 || page === 1);
        $('#filter-next-page').prop("disabled", max_num_pages === 1 || page === max_num_pages);
        $("#navigation_text").text("Strona " + page + " z " + max_num_pages);
    }

    function init() {
        $("#nbdod_filter_form").trigger('submit');
    }

})(jQuery);