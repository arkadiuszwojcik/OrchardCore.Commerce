(function ($) {
    $(document).ready(
        function () {
            console.log('inpost-pl admin.js');
            var mediaUploader;

            $('.woo-inpost-logo-upload-btn').on(
                'click',
                function (e) {
                    e.preventDefault();

                    if (mediaUploader) {
                        mediaUploader.open();
                        return;
                    }

                    mediaUploader = wp.media.frames.file_frame = wp.media(
                        {
                            title: 'Choose a shipping method logo',
                            button: {
                                text: 'Choose Image'
                            },
                            multiple: false
                        }
                    );

                    mediaUploader.on(
                        'select',
                        function () {
                            var attachment = mediaUploader.state().get('selection').first().toJSON();
                            $('#woocommerce_easypack_logo_upload').val(attachment.url);
                            $('#woo-inpost-logo-preview').attr('src', attachment.url);
                            $('#woo-inpost-logo-preview').css('display', 'block');
                            $('#woo-inpost-logo-action').css('display', 'block');
                        }
                    );
                    mediaUploader.open();
                }
            );

            $('#woo-inpost-logo-delete').on(
                'click',
                function (e) {
                    e.preventDefault();
                    $('#woo-inpost-logo-preview').css('display', 'none');
                    $('#woocommerce_easypack_logo_upload').val('');
                    $('#woo-inpost-logo-action').css('display', 'none');
                }
            );

            $('.easypack_parcel').click(
                function () {

                    var allowReturnStickers = $(this).data('allow_return_stickers') === 1;
                    if ($(this).is(':checked')) {
                        if (false === allowReturnStickers) {
                            $('#get_return_stickers').prop('disabled', true);
                        }
                    } else {
                        if (false === allowReturnStickers) {
                            $('#get_return_stickers').removeAttr('disabled');
                        }
                    }
                }
            );

            $('#woo_inpost_dpoint_add').click(
                function (e) {
                    e.preventDefault();
                    var cloned = $('#woo_inpost_dpoint-cell .woo_inpost_dpoint-cell-wraper:last').clone();
                    $('#woo_inpost_dpoint-cell').append(cloned);

                    $('.woo_inpost_dpoint_selected').each(
                        function (index, obj) {
                            $(this).val(index);
                        }
                    );

                }
            );


            $('input.easypack_courier_tmplts_dmtemplate[data-id="name"]').each(
                function () {
                    let slug_input = $(this).siblings('input[data-id="slug"]');
                    if (typeof slug_input != 'undefined' && slug_input !== null) {
                        if (slug_input.val() === '') {
                            slug_input.val(inpost_pl_sanitize_title($(this).val()));
                        }
                    }
                }
            );

            $(document).on(
                'change',
                'input.easypack_courier_tmplts_dmtemplate[data-id="name"]',
                function () {
                    let name_value = $(this).val();
                    // Generate slug from name.
                    let slug = inpost_pl_sanitize_title(name_value);
                    // Find the corresponding slug input (sibling with data-id="slug").
                    $(this).closest('td').find('input[data-id="slug"]').val(slug);
                }
            );

            document.addEventListener(
                'change',
                function (e) {
                    e = e || window.event;
                    var target = e.target || e.srcElement;

                    if (target.hasAttribute('id') && 'inpost_pl_package_size_dimensions' === target.getAttribute('id') ) {

                        let length = '';
                        let width = '';
                        let height = '';
                        let weight = '';
                        let is_not_standard = false;
                        let selected_template = [];
                        let selected_template_ind = target.value;
                        let saved_templates = easypack_settings.courier_templates;
                        console.log('Inpost PL: kurier templates');
                        console.log(saved_templates);

                        // Get selected option text
                        let selectedOption = target.options[target.selectedIndex];
                        console.log("Inpost PL courier_template: " + selectedOption.text);

                        // Find closest repeat block.
                        let repeat_block = target.closest('.easypack-courier-repeat-block');

                        if (typeof saved_templates != 'undefined' && saved_templates !== null && saved_templates.length > 0) {
                            selected_template = saved_templates[selected_template_ind];
                            length = selected_template.length;
                            width = selected_template.width;
                            height = selected_template.height;
                            weight = selected_template.weight;
                            if('not_standard' in selected_template &&  '1' === selected_template.not_standard ) {
                                is_not_standard = true;
                                document.querySelector('#parcel_non_standard').value = 'yes';
                            }

                            if (repeat_block) {
                                // Find and update input values
                                let parcel_length = repeat_block.querySelector('#parcel_length');
                                let parcel_width = repeat_block.querySelector('#parcel_width');
                                let parcel_height = repeat_block.querySelector('#parcel_height');
                                let parcel_weight = repeat_block.querySelector('#parcel_weight');

                                if (parcel_length) parcel_length.value = length;
                                if (parcel_width) parcel_width.value = width;
                                if (parcel_height) parcel_height.value = height;
                                if (parcel_weight) parcel_weight.value = weight;

                                if(is_not_standard ) {
                                    document.querySelector('#parcel_non_standard').value = 'yes';
                                } else {
                                    document.querySelector('#parcel_non_standard').value = 'no';
                                }
                            }
                        }
                    }

                },
                false
            );


            function inpost_pl_sanitize_title(str) {

                if (!str) {
                    return '';
                }

                var char_map = {
                    // Latin.
                    'À': 'A', 'Á': 'A', 'Â': 'A', 'Ã': 'A', 'Ä': 'A', 'Å': 'A', 'Æ': 'AE',
                    'Ç': 'C', 'È': 'E', 'É': 'E', 'Ê': 'E', 'Ë': 'E', 'Ì': 'I', 'Í': 'I',
                    'Î': 'I', 'Ï': 'I', 'Ð': 'D', 'Ñ': 'N', 'Ò': 'O', 'Ó': 'O', 'Ô': 'O',
                    'Õ': 'O', 'Ö': 'O', 'Ø': 'O', 'Ù': 'U', 'Ú': 'U', 'Û': 'U', 'Ü': 'U',
                    'Ý': 'Y', 'Þ': 'TH', 'ß': 'ss', 'à': 'a', 'á': 'a', 'â': 'a', 'ã': 'a',
                    'ä': 'a', 'å': 'a', 'æ': 'ae', 'ç': 'c', 'è': 'e', 'é': 'e', 'ê': 'e',
                    'ë': 'e', 'ì': 'i', 'í': 'i', 'î': 'i', 'ï': 'i', 'ð': 'd', 'ñ': 'n',
                    'ò': 'o', 'ó': 'o', 'ô': 'o', 'õ': 'o', 'ö': 'o', 'ø': 'o', 'ù': 'u',
                    'ú': 'u', 'û': 'u', 'ü': 'u', 'ý': 'y', 'þ': 'th', 'ÿ': 'y',
                    // Polish.
                    'Ł': 'L', 'ł': 'l', 'Ń': 'N', 'ń': 'n', 'Ć': 'C', 'ć': 'c', 'Ź': 'Z',
                    'ź': 'z', 'Ż': 'Z', 'ż': 'z', 'Ś': 'S', 'ś': 's', 'Ą': 'A', 'ą': 'a',
                    'Ę': 'E', 'ę': 'e', 'Ó': 'O', 'ó': 'o'
                };

                // Replace special characters.
                for (var char in char_map) {
                    str = str.replace(new RegExp(char, 'g'), char_map[char]);
                }

                str = str.toLowerCase();

                str = str.replace(/[^a-z0-9]+/g, '-');

                str = str.replace(/^-+|-+$/g, '');

                return str;
            }




            function inpost_pl_update_courier_templates_indices( selected_template ) {

                $('#easypack_courier_tmplts_ctemplate-cell .easypack_courier_tmplts_dmtemplate-cell-wraper').each(function (row_index, row) {

                    // Update the cloned row with the new index BEFORE appending.
                    $(row).attr('id', 'easypack_courier_tmplts_dmtemplate_' + row_index );
                    $(row).find('.easypack_courier_tmplts_dmtemplate-remove').attr('data-remove', row_index);
                    $(row).find('.easypack_courier_tmplts_dmtemplate_selected').val(row_index);


                    $(row).find('input').each(function (input_index, input) {
                        let new_name = '';
                        let name = $(input).attr('name');
                        if( 0 === input_index ) {
                            new_name = name;
                        }
                        if( 1 === input_index ) {
                            // delete button.
                        }
                        if( 2 === input_index ) {
                            new_name = 'easypack_courier_tmplts_dmtemplates[' + row_index + '][name]'
                        }
                        if( 3 === input_index ) {
                            new_name = 'easypack_courier_tmplts_dmtemplates[' + row_index + '][slug]'
                        }
                        if( 4 === input_index ) {
                            new_name = 'easypack_courier_tmplts_dmtemplates[' + row_index + '][length]'
                        }
                        if( 5 === input_index ) {
                            new_name = 'easypack_courier_tmplts_dmtemplates[' + row_index + '][width]'
                        }
                        if( 6 === input_index ) {
                            new_name = 'easypack_courier_tmplts_dmtemplates[' + row_index + '][height]'
                        }
                        if( 7 === input_index ) {
                            new_name = 'easypack_courier_tmplts_dmtemplates[' + row_index + '][weight]'
                        }
                        $(input).attr('name', new_name);
                    });
                });

                let is_selected_found = false;

                $('#easypack_courier_tmplts_ctemplate-cell .easypack_courier_tmplts_dmtemplate-cell-wraper').each(function (row_index, row) {
                    let template_slug = $(row).find('input[name="easypack_courier_tmplts_dmtemplates[' + row_index + '][slug]"]').val();
                    if( template_slug === selected_template ) {
                        let radio = $(row).find('input[type="radio"]');
                        $(radio).prop('checked', true );
                        is_selected_found = true;
                    }
                });

                if( is_selected_found ) {
                    console.log('selected restored');
                } else {
                    console.log('NO selected found any more: set first');
                    $('#easypack_courier_tmplts_ctemplate-cell .easypack_courier_tmplts_dmtemplate-cell-wraper:first').find('input[type="radio"]').prop('checked', true );
                }

            }



            function inpost_pl_update_courier_last_template_indices( current_rows ) {
                $('#easypack_courier_tmplts_ctemplate-cell .easypack_courier_tmplts_dmtemplate-cell-wraper:last').find('input').each(function (input_index, input) {
                    let new_name = '';
                    let name = $(input).attr('name');
                    if( 0 === input_index ) {
                        new_name = name;
                    }
                    if( 1 === input_index ) {
                        // delete button.
                    }
                    if( 2 === input_index ) {
                        new_name = 'easypack_courier_tmplts_dmtemplates[' + current_rows + '][name]'
                    }
                    if( 3 === input_index ) {
                        new_name = 'easypack_courier_tmplts_dmtemplates[' + current_rows + '][slug]'
                    }
                    if( 4 === input_index ) {
                        new_name = 'easypack_courier_tmplts_dmtemplates[' + current_rows + '][length]'
                    }
                    if( 5 === input_index ) {
                        new_name = 'easypack_courier_tmplts_dmtemplates[' + current_rows + '][width]'
                    }
                    if( 6 === input_index ) {
                        new_name = 'easypack_courier_tmplts_dmtemplates[' + current_rows + '][height]'
                    }
                    if( 7 === input_index ) {
                        new_name = 'easypack_courier_tmplts_dmtemplates[' + current_rows + '][weight]'
                    }
                    $(input).attr('name', new_name);
                });
            }



            $('#easypack_courier_tmplts_dmtemplate_add').click(function (e) {
                e.preventDefault();

                // Clone the last row.
                // Get the current number of rows to determine the new index.
                let current_rows = $('#easypack_courier_tmplts_ctemplate-cell .easypack_courier_tmplts_dmtemplate-cell-wraper').length;
                //currentRows = currentRows + 1;

                // Clone the last row.
                let cloned = $('#easypack_courier_tmplts_ctemplate-cell .easypack_courier_tmplts_dmtemplate-cell-wraper:last').clone();

                // Clear input values in the cloned row.
                cloned.find('input[type="text"], input[type="number"]').val('');
                cloned.find('input[type="radio"]').prop('checked', false);

                // Update the cloned row with the new index BEFORE appending.
                cloned.attr('id', 'easypack_courier_tmplts_dmtemplate_' + current_rows);
                cloned.find('.easypack_courier_tmplts_dmtemplate-remove').attr('data-remove', current_rows);
                cloned.find('.easypack_courier_tmplts_dmtemplate_selected').val(current_rows);

                // Now append the properly indexed cloned row,
                $('#easypack_courier_tmplts_ctemplate-cell tbody').append(cloned);


                $( '.easypack_courier_tmplts_dmtemplate-cell-wraper' ).each(
                    function (index, row) {
                        $( row ).attr('data-index', index );
                    }
                );
                inpost_pl_update_courier_last_template_indices( current_rows );

            });

            document.addEventListener(
                'click',
                function (e) {
                    e = e || window.event;
                    var target = e.target || e.srcElement;

                    if (target.classList.contains('woo_inpost_dpoint-remove')) {
                        target.parentNode.parentNode.parentNode.removeChild(target.parentNode.parentNode);
                    }

                    if (target.classList.contains('easypack_courier_tmplts_dmtemplate-remove')) {
                        let current_rows = $('#easypack_courier_tmplts_ctemplate-cell .easypack_courier_tmplts_dmtemplate-cell-wraper').length;
                        if( 1 === current_rows ) {
                            return;
                        }

                        let delete_row = +target.getAttribute('data-remove');
                        let selected_template = 0;
                        $('#easypack_courier_tmplts_ctemplate-cell .easypack_courier_tmplts_dmtemplate-cell-wraper').each(function (row_index, row) {
                            let radio = $(row).find('input[type="radio"]');
                            if( $(radio).is(':checked') ) {
                                selected_template = $(row).find('input[name="easypack_courier_tmplts_dmtemplates[' + row_index + '][slug]"]').val();
                            }
                        });

                        target.parentNode.parentNode.parentNode.removeChild(target.parentNode.parentNode);
                        inpost_pl_update_courier_templates_indices( selected_template);
                    }

                },
                false
            );

            // integration with Flexible Shipping.
            let fs_integration_select = $('#woocommerce_flexible_shipping_fs_inpost_pl_method');
            let fs_integration_insurance_field_1 = $('label[for="woocommerce_flexible_shipping_fs_insurance_inpost_pl"]');
            let fs_integration_insurance_field_2 = $('label[for="woocommerce_flexible_shipping_fs_insurance_value_inpost_pl"]');

            if ($(fs_integration_select).val() !== '0') {
                $(fs_integration_insurance_field_1).closest('tr').show();
                $(fs_integration_insurance_field_2).closest('tr').show();
            } else {
                $(fs_integration_insurance_field_1).closest('tr').hide();
                $(fs_integration_insurance_field_2).closest('tr').hide();
            }

            if ($(fs_integration_select).val() !== 'easypack_parcel_machines_weekend' || $(fs_integration_select).val() !== 'easypack_parcel_machines_weekend_cod') {
                $('.fs-inpost-pl-weekend').each(
                    function (i, elem) {
                        $(elem).closest('tr').hide();
                    }
                );
            }

            $(fs_integration_select).on(
                'change',
                function () {
                    if ($(this).val() === 'easypack_parcel_machines_weekend' || $(this).val() === 'easypack_parcel_machines_weekend_cod') {

                        $('.fs-inpost-pl-weekend').each(
                            function (i, elem) {
                                $(elem).closest('tr').show();
                            }
                        );

                    } else {

                        $('.fs-inpost-pl-weekend').each(
                            function (i, elem) {
                                $(elem).closest('tr').hide();
                            }
                        );
                    }

                    if ($(this).val() !== '0') {
                        $('#woocommerce_flexible_shipping_method_integration').val('').trigger('change');
                        $(fs_integration_insurance_field_1).closest('tr').show();
                        $(fs_integration_insurance_field_2).closest('tr').show();
                    } else {
                        $(fs_integration_insurance_field_1).closest('tr').hide();
                        $(fs_integration_insurance_field_2).closest('tr').hide();
                    }

                    $('#woocommerce_flexible_shipping_method_integration').on(
                        'change',
                        function () {
                            if ($(this).val()) {
                                $(fs_integration_select).val('0').trigger('change');
                            }
                        }
                    )
                }
            );

        }
    );
})(jQuery);