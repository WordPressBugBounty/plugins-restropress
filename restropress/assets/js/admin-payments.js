jQuery(document).ready(function($){
    var placeOrderDetailsNotices = function () {
        var $header = $('.rp-order-header');
        if (!$header.length) {
            return;
        }
        $('.rp-order-detail-title-row .notice, .rp-order-detail-title-row .updated, .rp-order-detail-title-row .error').insertBefore($header);
    };
    placeOrderDetailsNotices();
    window.setTimeout(placeOrderDetailsNotices, 100);

    $('#cb-select-all-1, #cb-select-all-2').attr('aria-label', 'Select all orders');

    // Phase C - "More filters" toggle for the advanced filter panel.
    $(document).on('click', '.rp-filters-more-toggle', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var targetId = $btn.attr('aria-controls');
        if (!targetId) return;
        var $panel = $('#' + targetId);
        var isOpen = !$panel.prop('hidden');
        $panel.prop('hidden', isOpen);
        $btn.toggleClass('is-open', !isOpen).attr('aria-expanded', isOpen ? 'false' : 'true');
    });

    // Past mode #1 - dismiss the "viewing past orders" info notice. The
    // dismissal is purely cosmetic for this session - next reload re-renders it.
    $(document).on('click', '.rp-orders-pastnotice-close', function (e) {
        e.preventDefault();
        $(this).closest('.rp-orders-pastnotice').slideUp(150);
    });

    // Today's view #5 - Column-toggle icon proxies to WP's Screen Options tab,
    // which already provides the canonical column-visibility controls.
    $(document).on('click', '.rp-orders-toolbar-columns', function (e) {
        e.preventDefault();
        var $btn = $('#show-settings-link');
        if ($btn.length) {
            $btn.trigger('click');
            // Scroll to the top so the panel is visible.
            $('html, body').animate({ scrollTop: 0 }, 200);
        }
    });

    // Date row - "Custom" opens the inline date range panel directly under
    // the date shortcuts, keeping date selection near the control itself.
    $(document).on('click', '.rp-datechip-custom', function (e) {
        e.preventDefault();
        var $button = $(this);
        var $panel = $('#rpress-payment-custom-date-panel');
        var shouldOpen = $panel.prop('hidden');

        $panel.prop('hidden', !shouldOpen);
        $button
            .attr('aria-expanded', shouldOpen ? 'true' : 'false')
            .toggleClass('is-open', shouldOpen)
            .toggleClass('is-active', $button.attr('data-current-custom') === '1');

        if (shouldOpen) {
            window.setTimeout(function () { $('#start-date').trigger('focus'); }, 50);
        }
    });

    $(document).on('click', '.rp-date-custom-cancel', function (e) {
        e.preventDefault();
        $('#rpress-payment-custom-date-panel').prop('hidden', true);
        $('.rp-datechip-custom')
            .attr('aria-expanded', 'false')
            .removeClass('is-open')
            .each(function () {
                var $button = $(this);
                $button.toggleClass('is-active', $button.attr('data-current-custom') === '1');
            });
    });

    // Redesign Phase 3 - Date preset dropdown. Maps a preset key to actual
    // mm/dd/yyyy values which it writes into start-date / end-date inputs and
    // then submits the form. "custom" opens the inline custom date panel.
    $(document).on('change', '#rp-date-preset', function () {
        var preset = $(this).val();
        var $form  = $(this).closest('form');
        var pad    = function (n) { return n < 10 ? '0' + n : '' + n; };
        var fmt    = function (d) { return pad(d.getMonth() + 1) + '/' + pad(d.getDate()) + '/' + d.getFullYear(); };

        var today          = new Date();
        var yesterday      = new Date(); yesterday.setDate(today.getDate() - 1);
        var d7             = new Date(); d7.setDate(today.getDate() - 6);
        var d30            = new Date(); d30.setDate(today.getDate() - 29);
        var monthStart     = new Date(today.getFullYear(), today.getMonth(), 1);
        var lastMonthStart = new Date(today.getFullYear(), today.getMonth() - 1, 1);
        var lastMonthEnd   = new Date(today.getFullYear(), today.getMonth(), 0);

        var start = '', end = '';
        switch (preset) {
            case 'today':      start = end = fmt(today); break;
            case 'yesterday':  start = end = fmt(yesterday); break;
            case 'last7days':  start = fmt(d7);             end = fmt(today); break;
            case 'last30days': start = fmt(d30);            end = fmt(today); break;
            case 'thismonth':  start = fmt(monthStart);     end = fmt(today); break;
            case 'lastmonth':  start = fmt(lastMonthStart); end = fmt(lastMonthEnd); break;
            case 'custom':
                // Open the inline custom date panel and focus the start-date input.
                $('#rpress-payment-custom-date-panel').prop('hidden', false);
                $('.rp-datechip-custom').attr('aria-expanded', 'true').addClass('is-open');
                window.setTimeout(function () { $('#start-date').trigger('focus'); }, 50);
                return;
            case '':
            default:
                start = ''; end = '';
        }

        $form.find('input[name="start-date"]').val(start);
        $form.find('input[name="end-date"]').val(end);
        $form.find('input[name="paged"]').val('');
        $form.trigger('submit');
    });

    // Auto-submit on change for filter dropdowns marked .rp-filter-autosubmit
    // (Type dropdown today; any future top-bar dropdowns will follow the same pattern).
    $(document).on('change', '.rp-filter-autosubmit', function () {
        $(this).closest('form').trigger('submit');
    });

    // Redesign Phase 5 - row action overflow (⋮) menu.
    $(document).on('click', '.rp-action-overflow-toggle', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var $btn  = $(this);
        var $menu = $btn.siblings('.rp-action-overflow-menu');
        var open  = !$menu.prop('hidden');
        // Close any other open overflow menus.
        $('.rp-action-overflow-menu').prop('hidden', true);
        $('.rp-action-overflow-toggle').attr('aria-expanded', 'false');
        $menu.prop('hidden', open);
        $btn.attr('aria-expanded', open ? 'false' : 'true');
    });
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.rp-action-overflow').length) {
            $('.rp-action-overflow-menu').prop('hidden', true);
            $('.rp-action-overflow-toggle').attr('aria-expanded', 'false');
        }
    });

    $(document).on('click', '.open-quick-edit', function(e) {
        e.preventDefault();
        $(this).parents('td').find('.sidenav').css("width", 550);
        document.getElementById("main").style.marginLeft = "0px";
        document.getElementById("overlay").style.display = "block";
    });
    $(document).on('click', '.close-quick-edit', function(e) {
        e.preventDefault();
        $(this).parents('div.sidenav').css("width", 0);
        document.getElementById("main").style.marginLeft = "0";
        document.getElementById("overlay").style.display = "none";
    });
    $(document).on('click', 'div#overlay', function(e) {
        e.preventDefault();
        $('div.sidenav').css("width", 0);
        document.getElementById("main").style.marginLeft = "0";
        document.getElementById("overlay").style.display = "none";
    });
    $('select#rpress_filter_order_date').on('change', function() {
        $('div.rpress-date-filters').removeClass('show-filters');
        if ( this.value === 'yesterday' ) {
            var d = new Date();
            var strDate = (d.getMonth()+1) + "/" + (d.getDate()-1) + "/" + d.getFullYear();
            $('div.rpress-date-filters').find('#start-date').val(strDate);
            $('div.rpress-date-filters').find('#end-date').val('');
            $('div.rpress-date-filters').find('#btn_submit').trigger('click');
        }
        if ( this.value === '7days' ) {
            var d = new Date();
            var date = new Date(new Date().setDate(new Date().getDate() - 7));
            var strDate = (date.getMonth()+1) + "/" + date.getDate() + "/" + date.getFullYear();
            var endDate = (d.getMonth()+1) + "/" + d.getDate() + "/" + d.getFullYear();
            $('div.rpress-date-filters').find('#start-date').val(strDate);
            $('div.rpress-date-filters').find('#end-date').val(endDate);
            $('div.rpress-date-filters').find('#btn_submit').trigger('click');
        }
        if ( this.value === '30days' ) {
            var d = new Date();
            var date = new Date(new Date().setDate(new Date().getDate() - 30));
            var strDate = (date.getMonth()+1) + "/" + date.getDate() + "/" + date.getFullYear();
            var endDate = (d.getMonth()+1) + "/" + d.getDate() + "/" + d.getFullYear();
            $('div.rpress-date-filters').find('#start-date').val(strDate);
            $('div.rpress-date-filters').find('#end-date').val(endDate);
            $('div.rpress-date-filters').find('#btn_submit').trigger('click');
        }
        if ( this.value === 'today' ) {
            var d = new Date();
            var strDate = (d.getMonth()+1) + "/" + d.getDate() + "/" + d.getFullYear();
            $('div.rpress-date-filters').find('#start-date').val(strDate);
            $('div.rpress-date-filters').find('#end-date').val('');
            $('div.rpress-date-filters').find('#btn_submit').trigger('click');
        }
        if ( this.value === 'startend' ) {
            $('div.rpress-date-filters').addClass('show-filters');
        }
    });
    $('select#order_status').on('change', function() {
        setTimeout(() => {
            $('div.rpress-date-filters').find('#btn_submit').trigger('click');
        }, 100);
    });

    // Order details v2 - section-level editing without changing the existing
    // save endpoint or legacy field names.
    var snapshotSection = function ($section) {
        var values = {};
        $section.find(':input').each(function () {
            var $field = $(this);
            var key = $field.attr('name') || $field.attr('id');
            if (!key) {
                return;
            }
            if ($field.is(':checkbox,:radio')) {
                values[key] = $field.prop('checked');
            } else {
                values[key] = $field.val();
            }
        });
        $section.data('rp-original-values', values);
    };

    var restoreSection = function ($section) {
        var values = $section.data('rp-original-values') || {};
        $section.find(':input').each(function () {
            var $field = $(this);
            var key = $field.attr('name') || $field.attr('id');
            if (!key || typeof values[key] === 'undefined') {
                return;
            }
            if ($field.is(':checkbox,:radio')) {
                $field.prop('checked', values[key]);
            } else {
                $field.val(values[key]);
            }
            if ($field.is('select')) {
                $field.trigger('chosen:updated');
            }
        });
    };

    $(document).on('click', '.rp-order-edit-toggle', function (e) {
        e.preventDefault();
        var key = $(this).data('rp-edit-section');
        var $section = $('.rp-order-section[data-rp-section="' + key + '"]');
        if (!$section.length) {
            return;
        }
        snapshotSection($section);
        $section.addClass('is-editing');
        $section.find('.rp-order-edit').prop('hidden', false);
        $section.find('.rp-order-read').attr('aria-hidden', 'true');
    });

    $(document).on('click', '.rp-order-cancel-edit', function (e) {
        e.preventDefault();
        var key = $(this).data('rp-cancel-section');
        var $section = $('.rp-order-section[data-rp-section="' + key + '"]');
        if (!$section.length) {
            return;
        }
        restoreSection($section);
        $section.removeClass('is-editing');
        $section.find('.rp-order-edit').prop('hidden', true);
        $section.find('.rp-order-read').attr('aria-hidden', 'false');
    });

    $(document).on('click', '.rp-order-next-submit', function (e) {
        e.preventDefault();
        var nextStatus = $(this).data('next-status');
        if (nextStatus) {
            $('select[name="rpress_order_status"]').val(nextStatus).trigger('change');
        }
        $('#rpress-edit-order-form').trigger('submit');
    });

    $(document).on('click', '.rp-order-copy-key', function (e) {
        e.preventDefault();

        var $button = $(this);
        var value = $button.data('copy-value');

        if (!value || !window.navigator || !window.navigator.clipboard) {
            return;
        }

        window.navigator.clipboard.writeText(value).then(function () {
            $button.addClass('is-copied');
            window.setTimeout(function () {
                $button.removeClass('is-copied');
            }, 900);
        });
    });

    $(document).on('click', '.rp-order-billing-toggle', function (e) {
        e.preventDefault();

        var $button = $(this);
        var $billing = $button.closest('#rpress-billing-details');
        var $fields = $billing.find('> .inside');

        $billing.removeClass('is-billing-collapsed');
        $fields.prop('hidden', false);
        $button.attr('aria-expanded', 'true').hide();
        $billing.find('.rp-order-billing-empty').hide();
        $fields.find('input:visible, select:visible, textarea:visible').first().trigger('focus');
    });

    var orderItemModal = {
        key: null,
        mode: 'edit',
        $modal: $('#rp-order-item-modal'),

        money: function (amount) {
            amount = parseFloat(amount);
            if (isNaN(amount)) {
                amount = 0;
            }
            var decimals = window.rpress_vars && rpress_vars.currency_decimals ? parseInt(rpress_vars.currency_decimals, 10) : 2;
            return (this.$modal.data('currency-symbol') || '₹') + amount.toFixed(decimals);
        },

        parseAmount: function (value) {
            value = String(value || '').replace(/[^0-9.-]/g, '');
            var amount = parseFloat(value);
            return isNaN(amount) ? 0 : amount;
        },

        hiddenRow: function (key) {
            return $('#rpress-purchased-items .rp-order-items-edit-list .rpress-purchased-items-list-wrapper.' + key).closest('.rpress-purchased-row');
        },

        readRow: function (key) {
            return $('#rpress-purchased-items .rp-order-item-read-row[data-rp-order-item-key="' + key + '"]');
        },

        getFooditemOption: function (fooditemId) {
            return $('#rp-order-item-modal-fooditem').find('option[value="' + fooditemId + '"]');
        },

        getVariationPrices: function ($option) {
            var raw = $option.attr('data-prices') || '[]';
            try {
                return JSON.parse(raw) || [];
            } catch (e) {
                return [];
            }
        },

        getSelectedVariation: function () {
            var $field = this.$modal.find('.rp-order-item-modal__variation-field');
            var $selected = $('#rp-order-item-modal-variation').find('.rp-order-item-modal__variation-input:checked');
            if ($field.prop('hidden') || !$selected.length) {
                return {
                    id: '',
                    name: '',
                    amount: ''
                };
            }

            return {
                id: $selected.val() || '',
                name: $selected.data('name') || '',
                amount: $selected.data('amount') || ''
            };
        },

        populateVariations: function (fooditemId, selectedPriceId) {
            var $option = this.getFooditemOption(fooditemId);
            var prices = this.getVariationPrices($option);
            var $field = this.$modal.find('.rp-order-item-modal__variation-field');
            var $group = $('#rp-order-item-modal-variation').empty();

            if (!prices.length) {
                $field.prop('hidden', true);
                return;
            }

            var label = $option.data('variation-label') || 'Variation';
            $field.find('.rp-order-item-modal__variation-label').text(label);
            $group.attr('aria-label', label);

            prices.forEach(function (price) {
                var id = 'rp-order-item-modal-variation-' + String(price.id).replace(/[^a-zA-Z0-9_-]/g, '');
                var $input = $('<input type="radio" class="rp-order-item-modal__variation-input" name="rp_order_item_modal_variation" />')
                    .attr('id', id)
                    .val(price.id)
                    .attr('data-name', price.name)
                    .attr('data-amount', price.amount);
                var $button = $('<label class="rp-order-item-modal__variation-option" />').attr('for', id);

                $button.append($input);
                $button.append($('<span class="rp-order-item-modal__variation-name" />').text(price.name));
                $button.append($('<strong class="rp-order-item-modal__variation-price" />').text(orderItemModal.money(price.amount)));
                $group.append($button);
            });

            if (selectedPriceId !== null && typeof selectedPriceId !== 'undefined' && '' !== String(selectedPriceId)) {
                $group.find('.rp-order-item-modal__variation-input[value="' + String(selectedPriceId) + '"]').prop('checked', true);
            }

            if (!$group.find('.rp-order-item-modal__variation-input:checked').length && prices[0]) {
                $group.find('.rp-order-item-modal__variation-input').first().prop('checked', true);
            }

            $field.prop('hidden', false);
            this.applySelectedVariation();
        },

        applySelectedVariation: function () {
            var variation = this.getSelectedVariation();
            if (variation.amount !== '') {
                $('#rp-order-item-modal-price').val(variation.amount);
            }
        },

        getDisplayTitle: function (baseTitle) {
            var variation = this.getSelectedVariation();
            baseTitle = $.trim(baseTitle || '');
            if (variation.name) {
                return baseTitle + ' - ' + variation.name;
            }
            return baseTitle;
        },

        calculate: function () {
            var price = this.parseAmount($('#rp-order-item-modal-price').val());
            var qty = parseInt($('#rp-order-item-modal-qty').val(), 10);
            if (isNaN(qty) || qty < 1) {
                qty = 1;
                $('#rp-order-item-modal-qty').val(qty);
            }
            var addonTotal = 0;
            this.$modal.find('.rp-order-item-modal__addon-check:checked').each(function () {
                addonTotal += orderItemModal.parseAmount($(this).data('price'));
            });
            $('#rp-order-item-modal-subtotal').text(this.money((price * qty) + addonTotal));
        },

        buildAddons: function ($select, preserveSelected) {
            preserveSelected = preserveSelected !== false;
            var $list = this.$modal.find('.rp-order-item-modal__addon-list').empty();
            if (!$select.length || !$select.find('option').length) {
                $list.append('<p class="rp-order-item-modal__empty">No add-ons available for this item.</p>');
                return;
            }

            var addons = {};
            $select.find('option').each(function () {
                var $option = $(this);
                var value = $option.val();
                if (!value) {
                    return;
                }
                var parts = String(value).split('|');
                var addonId = parts[1] || value;
                var name = parts[0] || $.trim($option.text()).replace(/^\d+\s*x\s*/i, '');
                var rawPrice = parts[2] || $option.data('price') || '';
                var price = rawPrice ? Math.abs(orderItemModal.parseAmount(rawPrice)) : 0;

                var isSelected = preserveSelected && $option.is(':selected');

                if (!addons[addonId] || isSelected) {
                    addons[addonId] = {
                        value: value,
                        name: name,
                        price: price,
                        selected: isSelected
                    };
                }
            });

            $.each(addons, function (addonId, addon) {
                var index = $list.children().length;
                var id = 'rp-order-item-addon-' + orderItemModal.key + '-' + index;
                var text = addon.name + (addon.price ? ' +' + orderItemModal.money(addon.price) : '');
                var $label = $('<label class="rp-order-item-modal__addon" />');
                $label.append('<input type="checkbox" class="rp-order-item-modal__addon-check" id="' + id + '" value="" />');
                $label.find('input').val(addon.value).prop('checked', addon.selected).attr('data-price', addon.price);
                $label.append('<span>' + text + '</span>');
                $list.append($label);
            });
        },

        buildAddonsFromOptions: function ($options) {
            var $scratch = $('<select multiple />');
            $options.each(function () {
                $scratch.append($(this).clone().prop('selected', false));
            });
            this.buildAddons($scratch, false);
        },

        getAddonIdFromValue: function (value) {
            var parts = String(value || '').split('|');
            return parts[1] || '';
        },

        getSelectedAddonValues: function ($select) {
            var values = [];
            $select.find('option:selected').each(function () {
                values.push($(this).val());
            });
            return values;
        },

        getModalSelectedAddonValues: function () {
            var values = [];
            this.$modal.find('.rp-order-item-modal__addon-check:checked').each(function () {
                values.push($(this).val());
            });
            return values;
        },

        getAddonDisplayLabel: function ($input) {
            var $label = $input.closest('label');
            var name = $.trim($label.find('.rp-order-item-modal__addon-name').text()) || $.trim($label.find('span').first().text());
            var price = this.parseAmount($input.data('price'));
            return name + (price ? ' +' + this.money(price) : '');
        },

        buildGroupedAddons: function (groups, selectedValues) {
            var $list = this.$modal.find('.rp-order-item-modal__addon-list').empty();
            selectedValues = selectedValues || [];
            var selectedIds = {};

            selectedValues.forEach(function (value) {
                var addonId = orderItemModal.getAddonIdFromValue(value);
                if (addonId) {
                    selectedIds[addonId] = true;
                }
            });

            if (!groups || !groups.length) {
                $list.append('<p class="rp-order-item-modal__empty">No add-ons available for this item.</p>');
                return;
            }

            groups.forEach(function (group, groupIndex) {
                var inputType = group.input_type === 'radio' ? 'radio' : 'checkbox';
                var $group = $('<div class="rp-order-item-modal__addon-group" />');
                var $header = $('<div class="rp-order-item-modal__addon-group-header" />');
                var help = [];

                if (group.required) {
                    help.push('Required');
                }
                if (group.max) {
                    help.push('Max ' + group.max);
                }
                if (group.min) {
                    help.push('Min ' + group.min);
                }

                $header.append($('<strong />').text(group.name || 'Add-ons'));
                if (help.length) {
                    $header.append($('<span />').text(help.join(' · ')));
                } else {
                    $header.append($('<span />').text(inputType === 'radio' ? 'Choose one' : 'Choose any'));
                }

                $group.append($header);

                (group.items || []).forEach(function (addon, addonIndex) {
                    var checked = selectedValues.length ? !!selectedIds[String(addon.id)] : !!addon.selected;
                    var id = 'rp-order-item-addon-' + (orderItemModal.key || 'new') + '-' + groupIndex + '-' + addonIndex;
                    var price = orderItemModal.parseAmount(addon.price);
                    var $label = $('<label class="rp-order-item-modal__addon" />').attr('for', id);
                    var $input = $('<input class="rp-order-item-modal__addon-check" />')
                        .attr('type', inputType)
                        .attr('id', id)
                        .attr('name', 'rp_order_item_addon_group_' + group.id)
                        .val(addon.value)
                        .attr('data-price', price)
                        .prop('checked', checked);

                    $label.append($input);
                    $label.append($('<span class="rp-order-item-modal__addon-name" />').text(addon.name));
                    $label.append($('<strong class="rp-order-item-modal__addon-price" />').text('+' + orderItemModal.money(price)));
                    $group.append($label);
                });

                $list.append($group);
            });
        },

        loadAddonsForFooditem: function (fooditemId, priceId, selectedValues) {
            var $list = this.$modal.find('.rp-order-item-modal__addon-list');
            if (!fooditemId || !window.ajaxurl || !window.rpress_vars || !rpress_vars.load_admin_addon_nonce) {
                $list.html('<p class="rp-order-item-modal__empty">No add-ons available for this item.</p>');
                return;
            }

            $list.html('<p class="rp-order-item-modal__empty">Loading add-ons...</p>');
            $.post(window.ajaxurl, {
                action: 'rpress_admin_order_addon_items',
                fooditem_id: fooditemId,
                price_id: priceId || '',
                format: 'json',
                security: rpress_vars.load_admin_addon_nonce
            }).done(function (response) {
                if (response && response.success && response.data && response.data.groups && response.data.groups.length) {
                    orderItemModal.buildGroupedAddons(response.data.groups, selectedValues || []);
                } else {
                    $list.html('<p class="rp-order-item-modal__empty">No add-ons available for this item.</p>');
                }
                orderItemModal.calculate();
            }).fail(function () {
                $list.html('<p class="rp-order-item-modal__empty">Unable to load add-ons for this item.</p>');
            });
        },

        openEdit: function (key) {
            var $hidden = this.hiddenRow(key);
            var $read = this.readRow(key);
            if (!$hidden.length || !$read.length) {
                return;
            }

            this.mode = 'edit';
            this.key = key;
            this.$modal.removeAttr('hidden').attr('aria-hidden', 'false').addClass('is-open');
            this.$modal.find('.rp-order-item-modal__add-field').prop('hidden', true);
            this.$modal.find('#rp-order-item-modal-fooditem').trigger('chosen:updated');
            $('#rp-order-item-modal-title').text($.trim($read.find('.rp-order-item-copy strong').text()) || 'Edit item');
            var fooditemId = $hidden.find('.rpress-payment-details-fooditem-id').val() || $read.data('rp-order-item-id');
            var priceId = $hidden.find('.rpress-payment-details-fooditem-price-id').val();
            this.populateVariations(fooditemId, priceId);
            $('#rp-order-item-modal-price').val($hidden.find('.rpress-payment-details-fooditem-item-price').first().val());
            $('#rp-order-item-modal-qty').val($hidden.find('.rpress-payment-details-fooditem-quantity').last().val() || 1);
            $('#rp-order-item-modal-instruction').val($hidden.find('.rpress-payment-details-fooditem-instruction').val() || $.trim($read.find('.rp-order-item-instruction').text()));
            var thumb = $read.find('.rp-order-item-thumb img').attr('src');
            this.$modal.find('.rp-order-item-modal__thumb').html(thumb ? '<img src="' + thumb + '" alt="" />' : '<span class="dashicons dashicons-store" aria-hidden="true"></span>');
            this.loadAddonsForFooditem(fooditemId, this.getSelectedVariation().id || priceId, this.getSelectedAddonValues($hidden.find('.addon-items-list')));
            this.calculate();
        },

        openAdd: function () {
            this.mode = 'add';
            this.key = null;
            this.$modal.removeAttr('hidden').attr('aria-hidden', 'false').addClass('is-open');
            this.$modal.find('.rp-order-item-modal__add-field').prop('hidden', false);
            $('#rp-order-item-modal-title').text('Add item');
            $('#rp-order-item-modal-fooditem').val('');
            $('#rp-order-item-modal-fooditem').trigger('chosen:updated');
            this.populateVariations('', '');
            $('#rp-order-item-modal-price').val('');
            $('#rp-order-item-modal-qty').val(1);
            $('#rp-order-item-modal-instruction').val('');
            this.$modal.find('.rp-order-item-modal__thumb').html('<span class="dashicons dashicons-store" aria-hidden="true"></span>');
            this.$modal.find('.rp-order-item-modal__addon-list').html('<p class="rp-order-item-modal__empty">Choose a menu item first.</p>');
            this.calculate();
        },

        close: function () {
            this.$modal.attr('hidden', 'hidden').attr('aria-hidden', 'true').removeClass('is-open');
            this.key = null;
        },

        applyEdit: function () {
            var key = this.key;
            var $hidden = this.hiddenRow(key);
            var $read = this.readRow(key);
            if (!$hidden.length || !$read.length) {
                return;
            }

            var price = this.parseAmount($('#rp-order-item-modal-price').val());
            var qty = parseInt($('#rp-order-item-modal-qty').val(), 10);
            qty = isNaN(qty) || qty < 1 ? 1 : qty;
            var subtotal = price * qty;
            var selectedAddons = [];
            var selectedAddonLabels = [];
            var variation = this.getSelectedVariation();
            var baseTitle = $read.data('rp-order-item-base-name') || $.trim($read.find('.rp-order-item-copy strong').text());

            this.$modal.find('.rp-order-item-modal__addon-check:checked').each(function () {
                selectedAddons.push($(this).val());
                selectedAddonLabels.push(orderItemModal.getAddonDisplayLabel($(this)));
                subtotal += orderItemModal.parseAmount($(this).data('price'));
            });

            $hidden.find('.rpress-payment-details-fooditem-item-price').val(price.toFixed(2)).trigger('change');
            $hidden.find('.rpress-payment-details-fooditem-price-id').val(variation.id || '');
            $hidden.find('.rpress-payment-details-fooditem-quantity').val(qty).trigger('change');
            $hidden.find('.rpress-payment-details-fooditem-amount').val(subtotal.toFixed(2));
            $hidden.find('span.rpress-payment-details-fooditem-amount').text(subtotal.toFixed(2));
            $hidden.find('.rpress-payment-details-fooditem-instruction').val($('#rp-order-item-modal-instruction').val());
            $hidden.find('.addon-items-list').val(selectedAddons).trigger('chosen:updated').trigger('change');

            $read.find('[data-rp-order-item-field="quantity"]').text(qty);
            $read.find('[data-rp-order-item-field="unit-price"]').text(this.money(price));
            $read.find('[data-rp-order-item-field="subtotal"]').text(this.money(subtotal));
            var $addons = $read.find('.rp-order-item-addons').empty();
            if (selectedAddonLabels.length) {
                selectedAddonLabels.forEach(function (label) {
                    $addons.append($('<span />').text(label));
                });
            } else {
                $addons.append('<span class="rp-order-item-muted">None</span>');
            }

            var instruction = $('#rp-order-item-modal-instruction').val();
            var $copy = $read.find('.rp-order-item-copy');
            $copy.find('strong').text(this.getDisplayTitle(baseTitle));
            $copy.find('.rp-order-item-instruction').remove();
            if ($.trim(instruction)) {
                $copy.append($('<span class="rp-order-item-instruction" />').text(instruction));
            }

            $('#rpress-payment-fooditems-changed').val(1);
            $('.rpress-order-payment-recalc-totals').show();
            // Keep the Total field in sync - a saved order records whatever
            // is in that field, not the sum of its items.
            $('#rpress-order-recalc-total').trigger('click');
            this.close();
        },

        applyAdd: function () {
            var $select = $('#rp-order-item-modal-fooditem');
            var $option = $select.find(':selected');
            var fooditemId = $select.val();
            if (!fooditemId) {
                $select.trigger('focus');
                return;
            }

            var count = $('#rpress-purchased-items .rp-order-items-edit-list .rpress-purchased-row').length;
            var $template = $('#rpress-purchased-items .rp-order-items-edit-list .rpress-purchased-row:last').clone();
            var price = this.parseAmount($('#rp-order-item-modal-price').val() || $option.data('price'));
            var variation = this.getSelectedVariation();
            var qty = parseInt($('#rp-order-item-modal-qty').val(), 10);
            qty = isNaN(qty) || qty < 1 ? 1 : qty;
            var subtotal = price * qty;
            var title = this.getDisplayTitle($.trim($option.text()));
            var thumb = $option.data('thumb');
            var selectedAddons = [];
            var selectedAddonLabels = [];

            this.$modal.find('.rp-order-item-modal__addon-check:checked').each(function () {
                selectedAddons.push($(this).val());
                selectedAddonLabels.push(orderItemModal.getAddonDisplayLabel($(this)));
                subtotal += orderItemModal.parseAmount($(this).data('price'));
            });

            $template.find('input, select').each(function () {
                var name = $(this).attr('name');
                if (name) {
                    $(this).attr('name', name.replace(/\[(\d+)\]/, '[' + count + ']'));
                }
            });
            $template.find('.rpress-payment-details-fooditem-has-log').val(0);
            $template.find('.rpress-payment-details-fooditem-id').val(fooditemId);
            $template.find('.rpress-payment-details-fooditem-price-id').val(variation.id || $option.data('price-id') || '');
            $template.find('.rpress-payment-details-fooditem-item-price').val(price.toFixed(2));
            $template.find('.rpress-payment-details-fooditem-quantity').val(qty);
            $template.find('.rpress-payment-details-fooditem-amount').val(subtotal.toFixed(2));
            $template.find('span.rpress-payment-details-fooditem-amount').text(subtotal.toFixed(2));
            $template.find('.rpress-payment-details-fooditem-instruction').val($('#rp-order-item-modal-instruction').val());
            var $addonSelect = $template.find('.addon-items-list').empty();
            this.$modal.find('.rp-order-item-modal__addon-check').each(function () {
                var $check = $(this);
                var $option = $('<option />').val($check.val()).text(orderItemModal.getAddonDisplayLabel($check));
                if ($check.is(':checked')) {
                    $option.prop('selected', true);
                }
                $addonSelect.append($option);
            });
            $addonSelect.val(selectedAddons);
            $template.find('.rpress-order-remove-fooditem').attr('data-key', count);
            $template.find('.rpress-purchased-items-list-wrapper').removeClass().addClass('rpress-purchased-items-list-wrapper ' + count);
            $template.find('.rpress-purchased-fooditem-title').html('<a href="post.php?post=' + fooditemId + '&action=edit">' + title + '</a>');
            $template.find('.rp-order-edit-item-thumb').html(thumb ? '<img src="' + thumb + '" alt="" />' : '<span class="rp-order-edit-item-thumb-placeholder"><span class="dashicons dashicons-store" aria-hidden="true"></span></span>');
            $template.insertAfter('#rpress-purchased-items .rp-order-items-edit-list .rpress-purchased-row:last');

            var $readTemplate = $('#rpress-purchased-items .rp-order-item-read-row:last').clone();
            $readTemplate.attr('data-rp-order-item-key', count);
            $readTemplate.attr('data-rp-order-item-id', fooditemId);
            $readTemplate.attr('data-rp-order-item-base-name', $.trim($option.text()));
            $readTemplate.find('.rp-order-item-thumb').html(thumb ? '<img src="' + thumb + '" alt="" />' : '<span class="rp-order-item-thumb-placeholder"><span class="dashicons dashicons-store" aria-hidden="true"></span></span>');
            $readTemplate.find('.rp-order-item-copy strong').text(title);
            $readTemplate.find('.rp-order-item-copy .rp-order-item-instruction, .rp-order-item-mobile-addons').remove();
            if ($.trim($('#rp-order-item-modal-instruction').val())) {
                $readTemplate.find('.rp-order-item-copy').append($('<span class="rp-order-item-instruction" />').text($('#rp-order-item-modal-instruction').val()));
            }
            $readTemplate.find('[data-rp-order-item-field="quantity"]').text(qty);
            var $readAddons = $readTemplate.find('.rp-order-item-addons').empty();
            if (selectedAddonLabels.length) {
                selectedAddonLabels.forEach(function (label) {
                    $readAddons.append($('<span />').text(label));
                });
            } else {
                $readAddons.html('<span class="rp-order-item-muted">None</span>');
            }
            $readTemplate.find('[data-rp-order-item-field="unit-price"]').text(this.money(price));
            $readTemplate.find('[data-rp-order-item-field="subtotal"]').text(this.money(subtotal));
            $readTemplate.find('.rp-order-item-modal-trigger').attr('data-rp-order-item-key', count);
            $readTemplate.insertAfter('#rpress-purchased-items .rp-order-item-read-row:last');

            $('#rpress-payment-fooditems-changed').val(1);
            $('.rpress-order-payment-recalc-totals').show();
            $('#rpress-order-recalc-total').trigger('click');
            this.close();
        }
    };

    $(document).on('click', '.rp-order-item-modal-trigger', function (e) {
        e.preventDefault();
        var mode = $(this).data('rp-order-item-mode');
        if (mode === 'add') {
            orderItemModal.openAdd();
        } else {
            orderItemModal.openEdit($(this).data('rp-order-item-key'));
        }
    });

    $(document).on('click', '[data-rp-order-item-close]', function (e) {
        e.preventDefault();
        orderItemModal.close();
    });

    $(document).on('input change', '#rp-order-item-modal-price, #rp-order-item-modal-qty, .rp-order-item-modal__addon-check', function () {
        orderItemModal.calculate();
    });

    $(document).on('click', '[data-rp-order-item-qty]', function () {
        var delta = parseInt($(this).data('rp-order-item-qty'), 10);
        var $qty = $('#rp-order-item-modal-qty');
        var next = parseInt($qty.val(), 10) + delta;
        $qty.val(Math.max(1, isNaN(next) ? 1 : next)).trigger('change');
    });

    $(document).on('change', '#rp-order-item-modal-fooditem', function () {
        var $option = $(this).find(':selected');
        $('#rp-order-item-modal-title').text($option.text() || 'Add item');
        $('#rp-order-item-modal-price').val($option.data('price') || '');
        var thumb = $option.data('thumb');
        orderItemModal.$modal.find('.rp-order-item-modal__thumb').html(thumb ? '<img src="' + thumb + '" alt="" />' : '<span class="dashicons dashicons-store" aria-hidden="true"></span>');
        orderItemModal.populateVariations($(this).val(), $option.data('price-id') || '');
        orderItemModal.loadAddonsForFooditem($(this).val(), orderItemModal.getSelectedVariation().id);
        orderItemModal.calculate();
    });

    $(document).on('change', '.rp-order-item-modal__variation-input', function () {
        var fooditemId = orderItemModal.mode === 'add'
            ? $('#rp-order-item-modal-fooditem').val()
            : orderItemModal.hiddenRow(orderItemModal.key).find('.rpress-payment-details-fooditem-id').val();

        orderItemModal.applySelectedVariation();
        orderItemModal.loadAddonsForFooditem(fooditemId, $(this).val(), orderItemModal.getModalSelectedAddonValues());
        orderItemModal.calculate();
    });

    $(document).on('click', '#rp-order-item-modal-apply', function (e) {
        e.preventDefault();
        if (orderItemModal.mode === 'add') {
            orderItemModal.applyAdd();
        } else {
            orderItemModal.applyEdit();
        }
    });

    if ($.fn.chosen) {
        $('#rp-order-item-modal-fooditem').chosen({
            width: '100%',
            inherit_select_classes: true,
            placeholder_text_single: 'Choose a menu item',
            no_results_text: 'No menu items found'
        });
    }

    $(document).ajaxComplete(function () {
        var $notes = $('#rpress-payment-notes-inner');

        if (!$notes.length) {
            return;
        }

        var hasNotes = $notes.find('> .rpress-payment-note').length > 0;
        var $empty = $notes.find('> .rpress-no-payment-notes');

        $empty.prop('hidden', hasNotes).toggle(!hasNotes);
    });
});
