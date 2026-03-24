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
            '<th class="qa-col-enabled">' + escapeHtml(i18n.clear_team || 'Clear Team') + '</th>' +
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

            // Clear Team checkbox
            $row.append(
                '<td class="qa-cell-enabled">' +
                '<input type="checkbox" class="qa-clear-team"' +
                (cfg.clear_team ? ' checked' : '') + '>' +
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

        // Event: any dropdown or checkbox change
        $container.on('change', 'select, .qa-clear-team', function() {
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
                stop_transfer_dept: $row.find('.qa-stop-transfer').val() || '',
                clear_team: $row.find('.qa-clear-team').is(':checked')
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

    // ================================================================
    //  Dashboard
    // ================================================================

    function initDashboard() {
        // Only show on plugin main page (not instance edit)
        // Detect by: page has "Instances" tab but no "Widget Configuration" field
        var isPluginPage = $('a[href="#instances"]').length > 0;
        var isInstancePage = $('input[type="text"], textarea').filter(function() {
            var $f = $(this).closest('.form-field');
            return $f.length && $f.text().indexOf('Widget Configuration') > -1;
        }).length > 0;

        if (!isPluginPage || isInstancePage) return;
        if ($('.qa-dashboard').length) return;

        // Add Dashboard tab
        var $tabList = $('ul.tabs, .tab_nav ul').first();
        if (!$tabList.length) return;

        $tabList.append('<li><a href="#dashboard">Dashboard</a></li>');

        // Create dashboard container
        var $container = $('<div id="dashboard" class="qa-dashboard" style="display:none;padding:15px;"></div>');
        $container.html('<p style="color:#888;">Loading dashboard...</p>');
        $tabList.closest('.tab_content, .tabber').first().append($container);

        // If no tabber, append after the tab content area
        if (!$container.parent().length) {
            $tabList.parent().after($container);
        }

        // Tab switching
        $tabList.on('click', 'a[href="#dashboard"]', function(e) {
            e.preventDefault();
            $tabList.find('li').removeClass('active');
            $(this).parent().addClass('active');
            $tabList.siblings('div, .tab_content').hide();
            $container.show();
            loadDashboard($container, 7);
        });
    }

    function loadDashboard($container, days) {
        $.ajax({
            url: 'ajax.php/quick-buttons/dashboard?days=' + days,
            type: 'GET',
            dataType: 'json',
            cache: false,
            success: function(data) {
                renderDashboard($container, data);
            },
            error: function() {
                $container.html('<p style="color:red;">Failed to load dashboard data.</p>');
            }
        });
    }

    function renderDashboard($container, data) {
        var i18n = data.i18n || {};
        var html = '';

        // Period selector
        html += '<div class="qa-dash-controls">';
        html += '<button class="qa-dash-period" data-days="7">' + escapeHtml(i18n.last7days || 'Last 7 Days') + '</button>';
        html += '<button class="qa-dash-period" data-days="30">' + escapeHtml(i18n.last30days || 'Last 30 Days') + '</button>';
        html += '<button class="qa-dash-period" data-days="90">' + escapeHtml(i18n.last90days || 'Last 90 Days') + '</button>';
        html += '</div>';

        // Grid layout
        html += '<div class="qa-dash-grid">';

        // Card 1: Daily throughput
        html += '<div class="qa-dash-card">';
        html += '<h3>' + escapeHtml(i18n.ticketsPerDay || 'Tickets Per Day') + '</h3>';
        html += '<div class="qa-dash-chart">';
        if (data.daily && data.daily.length) {
            var maxCount = Math.max.apply(null, data.daily.map(function(d) { return d.count; }));
            data.daily.forEach(function(d) {
                var pct = maxCount > 0 ? Math.round((d.count / maxCount) * 100) : 0;
                html += '<div class="qa-bar-row">';
                html += '<span class="qa-bar-label">' + d.day.substring(5) + '</span>';
                html += '<div class="qa-bar-track"><div class="qa-bar-fill" style="width:' + pct + '%"></div></div>';
                html += '<span class="qa-bar-value">' + d.count + '</span>';
                html += '</div>';
            });
        } else {
            html += '<p style="color:#999;">No data</p>';
        }
        html += '</div></div>';

        // Card 2: Avg time per step
        html += '<div class="qa-dash-card">';
        html += '<h3>' + escapeHtml(i18n.avgTimePerStep || 'Avg Time Per Step') + '</h3>';
        html += '<table class="qa-dash-table"><thead><tr>';
        html += '<th>' + escapeHtml(i18n.status || 'Status') + '</th>';
        html += '<th>' + escapeHtml(i18n.avgTime || 'Avg Time') + '</th>';
        html += '<th>' + escapeHtml(i18n.transitions || 'Transitions') + '</th>';
        html += '</tr></thead><tbody>';
        if (data.avgTimes && data.avgTimes.length) {
            data.avgTimes.forEach(function(s) {
                html += '<tr><td>' + escapeHtml(s.statusName) + '</td>';
                html += '<td><strong>' + escapeHtml(s.avgDisplay) + '</strong></td>';
                html += '<td>' + s.count + '</td></tr>';
            });
        } else {
            html += '<tr><td colspan="3" style="color:#999;">No data</td></tr>';
        }
        html += '</tbody></table></div>';

        // Card 3: Agent leaderboard
        html += '<div class="qa-dash-card">';
        html += '<h3>' + escapeHtml(i18n.agentLeader || 'Agent Leaderboard') + '</h3>';
        html += '<table class="qa-dash-table"><thead><tr>';
        html += '<th>' + escapeHtml(i18n.agent || 'Agent') + '</th>';
        html += '<th>' + escapeHtml(i18n.claimed || 'Claimed') + '</th>';
        html += '</tr></thead><tbody>';
        if (data.agents && data.agents.length) {
            data.agents.forEach(function(a, idx) {
                var medal = idx === 0 ? ' 🥇' : idx === 1 ? ' 🥈' : idx === 2 ? ' 🥉' : '';
                html += '<tr><td>' + escapeHtml(a.name) + medal + '</td>';
                html += '<td><strong>' + a.count + '</strong></td></tr>';
            });
        } else {
            html += '<tr><td colspan="2" style="color:#999;">No data</td></tr>';
        }
        html += '</tbody></table></div>';

        // Card 4: Current queue
        html += '<div class="qa-dash-card">';
        html += '<h3>' + escapeHtml(i18n.currentQueue || 'Current Queue') + '</h3>';
        html += '<table class="qa-dash-table"><thead><tr>';
        html += '<th>' + escapeHtml(i18n.status || 'Status') + '</th>';
        html += '<th>' + escapeHtml(i18n.tickets || 'Tickets') + '</th>';
        html += '</tr></thead><tbody>';
        if (data.queue && data.queue.length) {
            data.queue.forEach(function(q) {
                html += '<tr><td>' + escapeHtml(q.statusName) + '</td>';
                html += '<td><strong>' + q.count + '</strong></td></tr>';
            });
        } else {
            html += '<tr><td colspan="2" style="color:#999;">No data</td></tr>';
        }
        html += '</tbody></table></div>';

        html += '</div>'; // grid

        $container.html(html);

        // Period button handler
        $container.find('.qa-dash-period').on('click', function() {
            var days = $(this).data('days');
            $container.html('<p style="color:#888;">Loading...</p>');
            loadDashboard($container, days);
        });
    }

    // Initialize on DOM ready and after PJAX navigations
    $(function() {
        initWidgetConfig();
        initDashboard();
    });

    $(document).on('pjax:end', function() {
        initWidgetConfig();
    });

})(jQuery);
