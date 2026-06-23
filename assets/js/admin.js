(function ($) {
    'use strict';

    var settings = window.edhForminatorAttachments || {};
    var selectedAttachments = {};

    function renderSelectedFiles() {
        var $list = $('#edh-selected-files-list');
        $list.empty();

        $.each(selectedAttachments, function (id) {
            var name = selectedAttachments[id];
            var $item = $('<li/>').text(name + ' ');
            var $remove = $('<a href="#" />').text(settings.i18n.remove);
            $remove.on('click', function (e) {
                e.preventDefault();
                delete selectedAttachments[id];
                renderSelectedFiles();
            });
            $item.append($remove);
            $list.append($item);
        });

        $('#edh-attachment-ids').val(Object.keys(selectedAttachments).join(','));
    }

    function updateDynamicFlag() {
        var $selected = $('#edh-recipient-select option:selected');
        $('#edh-recipient-dynamic').val($selected.data('dynamic') ? '1' : '0');
    }

    function loadNotifications(formId, preselectRecipient) {
        var $select = $('#edh-recipient-select');
        $select.prop('disabled', true).empty();
        $select.append($('<option/>').val('').text(settings.i18n.loading));

        if (!formId) {
            $select.empty().append($('<option/>').val('').text(settings.i18n.selectForm));
            $select.prop('disabled', false);
            updateDynamicFlag();
            return;
        }

        $.post(settings.ajaxUrl, {
            action: 'edh_get_form_notifications',
            nonce: settings.nonce,
            form_id: formId
        }).done(function (response) {
            $select.empty();

            if (!response.success || !response.data.options || !response.data.options.length) {
                $select.append($('<option/>').val('').text(settings.i18n.noEmails));
                $select.prop('disabled', false);
                updateDynamicFlag();
                return;
            }

            $select.append($('<option/>').val('').text(settings.i18n.selectForm));

            $.each(response.data.options, function (i, option) {
                var $option = $('<option/>').val(option.value).text(option.label);
                $option.data('dynamic', !!option.dynamic);
                if (preselectRecipient && option.value === preselectRecipient) {
                    $option.prop('selected', true);
                }
                $select.append($option);
            });

            $select.prop('disabled', false);
            updateDynamicFlag();
        }).fail(function () {
            $select.empty().append($('<option/>').val('').text(settings.i18n.noEmails));
            $select.prop('disabled', false);
            updateDynamicFlag();
        });
    }

    $(function () {
        var $form = $('#edh-attachment-rule-form');
        if (!$form.length) {
            return;
        }

        var editAttachmentIds = ($form.data('edit-attachment-ids') || '').toString();
        var editAttachmentNames = $form.data('edit-attachment-names') || {};
        var editRecipient = $form.data('edit-recipient') || '';

        if (editAttachmentIds) {
            $.each(editAttachmentIds.split(','), function (i, id) {
                if (!id) {
                    return;
                }
                selectedAttachments[id] = editAttachmentNames[id] || ('#' + id);
            });
            renderSelectedFiles();
        }

        $('#edh-form-select').on('change', function () {
            loadNotifications($(this).val(), editRecipient);
            editRecipient = '';
        });

        $('#edh-recipient-select').on('change', updateDynamicFlag);

        if ($('#edh-form-select').val()) {
            loadNotifications($('#edh-form-select').val(), editRecipient);
        }

        var mediaFrame;
        $('#edh-select-files-button').on('click', function (e) {
            e.preventDefault();

            if (mediaFrame) {
                mediaFrame.open();
                return;
            }

            mediaFrame = wp.media({
                title: settings.i18n.chooseFiles,
                button: { text: settings.i18n.useFiles },
                multiple: true
            });

            mediaFrame.on('select', function () {
                var selection = mediaFrame.state().get('selection');
                selection.each(function (attachment) {
                    var data = attachment.toJSON();
                    selectedAttachments[data.id] = data.filename || data.title;
                });
                renderSelectedFiles();
            });

            mediaFrame.open();
        });
    });
})(jQuery);
