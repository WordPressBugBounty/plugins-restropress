(function ($) {
    $(document).ready(function () {
        const base_index = parseInt(rp_addon_sorting_data.paged) > 0 ? (parseInt(rp_addon_sorting_data.paged) - 1) * parseInt($('#' + rp_addon_sorting_data.per_page_id).val()) : 0;
        const tax_table = $('#rp-addon-item-list').length ? $('#rp-addon-item-list') : $('#the-list');

        const fooditem_id = rp_addon_sorting_data.fooditem_id;
        // Get initial category order


        // If the tax table contains items
        if (!tax_table.find('tr:first-child').hasClass('no-items')) {
            tax_table.sortable({
                placeholder: "rp-addon-drag-drop-placeholder",
                axis: "y",
                // On start, set a height for the placeholder to prevent table jumps
                start: function (event, ui) {
                    const item = $(ui.item[0]);
                    const index = item.index();
                    const colspan = item.children('th,td').filter(':visible').length;
                    $('.rp-addon-drag-drop-placeholder')
                        .css('height', item.css('height'))
                        .css('display', 'flex')
                        .css('width', '0');
                },
                // Update callback
                update: function (event, ui) {
                    const item = $(ui.item[0]);
                    // Hide checkbox, append a preloader
                    item.find('input[type="checkbox"]').hide().after('<img src="' + rp_addon_sorting_data.preloader_url + '" class="rp-addon-drag-drop-preloader" />');

                    const taxonomy_ordering_data = [];
                    tax_table.find('tr.ui-sortable-handle').each(function () {
                        const ele = $(this);
                        const term_data = {
                            term_id: ele.attr('id').replace('tag-', ''),
                            order: parseInt(ele.index()) + 1
                        }
                        taxonomy_ordering_data.push(term_data);
                    });

                    // AJAX Data
                    const data = {
                        'action': 'rp_update_addon_category_order',
                        'taxonomy_ordering_data': taxonomy_ordering_data,
                        'base_index': base_index,
                        'term_order_nonce': rp_addon_sorting_data.term_order_nonce,
                        "fooditem_id": fooditem_id
                    };
                    console.log(data);

                    // Run the ajax request
                    $.ajax({
                        type: 'POST',
                        url: window.ajaxurl,
                        data: data,
                        dataType: 'JSON',
                        success: function (response) {
                            $('.rp-addon-drag-drop-preloader').remove();
                            item.find('input[type="checkbox"]').show();
                        }
                    });
                }
            });
        } 
        if (rp_addon_sorting_data.is_variable_fooditem.trim()) {
            const addon_table = $("table.rp-addon-items");
            // Make tbody sortable
            addon_table.sortable({
                items: "tbody.rp-addon-variation-group",
                axis: "y",
                placeholder: "rp-addon-drag-drop-placeholder",

                helper: function (event, item) {
                    // Clone tbody to preserve table layout
                    const original = item;
                    const clone = original.clone();

                    // Force equal column widths
                    clone.find("tr:first").children().each(function (i) {
                        $(this).width(original.find("tr:first").children().eq(i).width());
                    });

                    clone.addClass("ui-sortable-helper-tbody");
                    return clone;
                },

                start: function (event, ui) {
                    // Keep your old placeholder styling
                    const item = ui.item;
                    $('.rp-addon-drag-drop-placeholder')
                        .css('height', item.height())
                        .css('display', 'flex')
                        .css('width', '0');
                },

                update: function (event, ui) {
                    const tbody = ui.item;

                    // Extract correct term order like before
                    const taxonomy_ordering_data = [];
                    addon_table.find("tbody.rp-addon-variation-group").each(function (index) {
                        taxonomy_ordering_data.push({
                            term_id: $(this).attr("id").replace("addon-group-", ""),
                            order: index + 1
                        });
                    });

                    // AJAX
                    $.ajax({
                        type: "POST",
                        url: window.ajaxurl,
                        data: {
                            'action': 'rp_update_addon_category_order',
                            'taxonomy_ordering_data': taxonomy_ordering_data,
                            'base_index': base_index,
                            'term_order_nonce': rp_addon_sorting_data.term_order_nonce,
                            "fooditem_id": fooditem_id
                        },
                        dataType: "JSON",
                        success: function () {
                            $('.rp-addon-drag-drop-preloader').remove();
                        }
                    });
                }
            });
        }


    });
})(jQuery);