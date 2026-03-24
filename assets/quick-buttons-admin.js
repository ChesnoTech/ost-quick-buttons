/**
 * Quick Buttons Plugin - Admin Config v2.0
 *
 * Builds a per-department configuration matrix for Start/Stop widgets.
 * Replaces the hidden widget_config textarea with a visual table UI.
 */
(function($) {
    'use strict';

    function initWidgetConfig() {
        // Prevent double initialization
        if ($('.qa-widget-matrix').length) return;
        // Find the widget_config input by scanning all text inputs
        // and checking their parent container for the "Widget Configuration" label.
        // osTicket renders config fields in either <tr><td> or <div class="form-field"> layouts.
        var $textarea = null;
        var $formRow = null;

        $('input[type="text"], textarea').each(function() {
            // Try <div class="form-field"> layout first
            var $field = $(this).closest('.form-field');
            if ($field.length) {
                var fieldText = $field.text().trim();
                if (fieldText.indexOf('Widget Configuration') > -1) {
                    $textarea = $(this);
                    $formRow = $field;
                    return false;
                }
            }
            // Try <tr><td> layout
            var $td = $(this).closest('td');
            if ($td.length) {
                var $row = $td.closest('tr');
                if ($row.length) {
                    var label = $row.find('td:first').text().trim();
                    if (label.indexOf('Widget Configuration') > -1) {
                        $textarea = $(this);
                        $formRow = $row;
                        return false;
                    }
                }
            }
        });

        if (!$textarea || !$textarea.length) return;

        // Fetch departments and statuses from the server
        $.ajax({
            url: 'ajax.php/quick-buttons/admin-config-data',
            type: 'GET',
            dataType: 'json',
            cache: false,
            success: function(data) {
                var strings = data.i18n || {};
                buildMatrix($textarea, $formRow, data.departments, data.statuses, strings);
            },
            error: function() {
                $formRow.after(
                    '<tr><td colspan="2" style="color:red;padding:10px;">' +
                    'Failed to load configuration data. Save the instance first, then reload.' +
                    '</td></tr>'
                );
            }
        });
    }

    function buildMatrix($textarea, $formRow, departments, statuses, i18n) {
        // Parse existing config from textarea
        var existing = {};
        try {
            var parsed = JSON.parse($textarea.val() || '{}');
            existing = parsed.departments || {};
        } catch (e) {
            existing = {};
        }

        var selectLabel = i18n.select || '-- Select --';

        // Build status options HTML
        var statusOptions = '<option value="">' + escapeHtml(selectLabel) + '</option>';
        $.each(statuses, function(i, s) {
            statusOptions += '<option value="' + s.id + '">' +
                escapeHtml(s.name) + ' (' + escapeHtml(s.state) + ')</option>';
        });

        // Build department options HTML (for transfer dropdown)
        var deptOptions = '<option value="">' + escapeHtml(selectLabel) + '</option>';
        $.each(departments, function(i, d) {
            deptOptions += '<option value="' + d.id + '">' +
                escapeHtml(d.name) + '</option>';
        });

        // Build the matrix container
        var $container = $('<div class="qa-widget-matrix"></div>');

        var $table = $(
            '<table class="qa-matrix-table">' +
            '<thead><tr>' +
            '<th class="qa-col-dept">' + escapeHtml(i18n.department || 'Department') + '</th>' +
            '<th class="qa-col-enabled">' + escapeHtml(i18n.enabled || 'Enabled') + '</th>' +
            '<th class="qa-col-status">' + escapeHtml(i18n.start_trigger || 'Start: Trigger Status') + '</th>' +
            '<th class="qa-col-status">' + escapeHtml(i18n.start_target || 'Start: Target Status') + '</th>' +
            '<th class="qa-col-status">' + escapeHtml(i18n.stop_target || 'Stop: Target Status') + '</th>' +
            '<th class="qa-col-dept-sel">' + escapeHtml(i18n.stop_transfer || 'Stop: Transfer To') + '</th>' +
            '</tr></thead>' +
            '<tbody></tbody>' +
            '</table>'
        );

        var $tbody = $table.find('tbody');

        $.each(departments, function(i, dept) {
            var cfg = existing[dept.id] || {};
            var enabled = !!cfg.enabled;

            var $row = $('<tr class="qa-matrix-row" data-dept-id="' + dept.id + '"></tr>');

            // Department name
            $row.append('<td class="qa-cell-dept">' + escapeHtml(dept.name) + '</td>');

            // Enabled checkbox
            $row.append(
                '<td class="qa-cell-enabled">' +
                '<input type="checkbox" class="qa-dept-enabled"' +
                (enabled ? ' checked' : '') + '>' +
                '</td>'
            );

            // Start Trigger Status
            $row.append(
                '<td class="qa-cell-select">' +
                '<select class="qa-start-trigger">' + statusOptions + '</select>' +
                '</td>'
            );

            // Start Target Status
            $row.append(
                '<td class="qa-cell-select">' +
                '<select class="qa-start-target">' + statusOptions + '</select>' +
                '</td>'
            );

            // Stop Target Status
            $row.append(
                '<td class="qa-cell-select">' +
                '<select class="qa-stop-target">' + statusOptions + '</select>' +
                '</td>'
            );

            // Stop Transfer Department
            $row.append(
                '<td class="qa-cell-select">' +
                '<select class="qa-stop-transfer">' + deptOptions + '</select>' +
                '</td>'
            );

            // Set existing values
            if (cfg.start_trigger_status)
                $row.find('.qa-start-trigger').val(cfg.start_trigger_status);
            if (cfg.start_target_status)
                $row.find('.qa-start-target').val(cfg.start_target_status);
            if (cfg.stop_target_status)
                $row.find('.qa-stop-target').val(cfg.stop_target_status);
            if (cfg.stop_transfer_dept)
                $row.find('.qa-stop-transfer').val(cfg.stop_transfer_dept);

            // Toggle disabled state on dropdowns
            toggleRow($row, enabled);

            $tbody.append($row);
        });

        $container.append($table);

        // Insert the matrix after the form row and hide the original field
        $formRow.hide();
        $formRow.after($container);

        // Event: toggle enabled
        $container.on('change', '.qa-dept-enabled', function() {
            var $row = $(this).closest('.qa-matrix-row');
            toggleRow($row, this.checked);
            serializeConfig($textarea, $tbody);
        });

        // Event: any dropdown change
        $container.on('change', 'select', function() {
            serializeConfig($textarea, $tbody);
        });

        // Initial serialize
        serializeConfig($textarea, $tbody);
    }

    function toggleRow($row, enabled) {
        $row.find('select').prop('disabled', !enabled);
        if (enabled) {
            $row.removeClass('qa-row-disabled');
        } else {
            $row.addClass('qa-row-disabled');
        }
    }

    function serializeConfig($textarea, $tbody) {
        var departments = {};

        $tbody.find('.qa-matrix-row').each(function() {
            var $row = $(this);
            var deptId = $row.data('dept-id').toString();
            var enabled = $row.find('.qa-dept-enabled').is(':checked');

            departments[deptId] = {
                enabled: enabled,
                start_trigger_status: $row.find('.qa-start-trigger').val() || '',
                start_target_status: $row.find('.qa-start-target').val() || '',
                stop_target_status: $row.find('.qa-stop-target').val() || '',
                stop_transfer_dept: $row.find('.qa-stop-transfer').val() || ''
            };
        });

        var json = JSON.stringify({ departments: departments });
        $textarea.val(json);
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // Initialize on DOM ready and after PJAX navigations
    $(function() {
        initWidgetConfig();
    });

    $(document).on('pjax:end', function() {
        initWidgetConfig();
    });

})(jQuery);
