/**
 * Quick Buttons Plugin - Admin Config v3.0
 *
 * Instance config page: hides the raw widget_config field and shows a
 * Workflow Builder launcher button with a live config summary.
 *
 * Dashboard: injects a Dashboard tab on the plugin main page.
 */
(function($) {
    'use strict';

    // ================================================================
    //  Instance Config — Workflow Builder launcher
    // ================================================================

    function initWidgetConfig() {
        if ($('.qa-widget-launcher').length) return;

        var $textarea = null;
        var $formRow  = null;

        // Locate the widget_config field (works in both osTicket form layouts)
        $('input[type="text"], textarea').each(function() {
            var $field = $(this).closest('.form-field');
            if ($field.length && $field.text().trim().indexOf('Widget Configuration') > -1) {
                $textarea = $(this);
                $formRow  = $field;
                return false;
            }
            var $td = $(this).closest('td');
            if ($td.length) {
                var $row = $td.closest('tr');
                if ($row.length && $row.find('td:first').text().trim().indexOf('Widget Configuration') > -1) {
                    $textarea = $(this);
                    $formRow  = $row;
                    return false;
                }
            }
        });

        if (!$textarea || !$textarea.length) return;

        // Parse saved JSON to build a human-readable summary (no AJAX needed)
        var summary = buildSummary($textarea.val());
        var instanceId = getInstanceId();

        var $launcher = $('<div class="qa-widget-launcher" style="margin:10px 0 18px;"></div>');

        if (instanceId) {
            $launcher.html(
                '<a href="ajax.php/quick-buttons/workflow-builder?iid=' + instanceId + '" ' +
                'class="action-button" ' +
                'style="display:inline-flex;align-items:center;gap:8px;padding:10px 24px;' +
                'background:#128DBE;color:#fff;border-radius:6px;text-decoration:none;' +
                'font-size:14px;font-weight:500;line-height:1;">' +
                '&#9881; Open Workflow Builder' +
                '</a>' +
                '<span class="qa-config-summary" style="margin-left:14px;color:#666;font-size:13px;">' +
                escapeHtml(summary) +
                '</span>'
            );
        } else {
            // Instance not yet saved OR page URL doesn't expose xid yet.
            // If there's a success notice on the page the instance exists — just needs a reload.
            var justSaved = $('.notice, .success, [class*="success"]').text().toLowerCase().indexOf('success') > -1;
            var msg = justSaved
                ? '&#9881; <a href="javascript:location.reload()" style="color:#128DBE;">Reload the page</a> to open the Workflow Builder.'
                : '&#9888; Save this instance first, then the Workflow Builder button will appear here.';
            $launcher.html('<p style="font-size:13px;margin:0;">' + msg + '</p>');
        }

        $formRow.hide();
        $formRow.before($launcher);
    }

    function buildSummary(raw) {
        try {
            var cfg    = JSON.parse(raw || '{}');
            var depts  = cfg.departments || {};
            var keys   = Object.keys(depts);
            var total  = 0;
            var single = 0;
            var two    = 0;

            keys.forEach(function(k) {
                var d = depts[k];
                if (!d.enabled) return;
                total++;
                if (d.variant === 'twostep') two++;
                else single++;
            });

            if (total === 0) return 'No departments configured yet — click to set up';

            var parts = [];
            if (single > 0) parts.push(single + ' single-step');
            if (two   > 0) parts.push(two   + ' two-step');
            return total + ' department' + (total !== 1 ? 's' : '') + ' configured (' + parts.join(', ') + ')';

        } catch (e) {
            return 'Click to configure';
        }
    }

    function getInstanceId() {
        // 1. URL query string: plugins.php?id=X&xid=Y
        var m = window.location.search.match(/[?&]xid=(\d+)/);
        if (m) return m[1];

        // 2. Form action attribute (osTicket sets this after saving a new instance)
        var action = $('form[method]').first().attr('action') || '';
        m = action.match(/[?&]xid=(\d+)/);
        if (m) return m[1];

        // 3. Hidden input named xid or iid
        var fromInput = $('input[name="xid"], input[name="iid"]').first().val();
        if (fromInput) return fromInput;

        return null;
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
        var isPluginPage   = $('a[href="#instances"]').length > 0;
        var isInstancePage = $('input[type="text"], textarea').filter(function() {
            var $f = $(this).closest('.form-field');
            return $f.length && $f.text().indexOf('Widget Configuration') > -1;
        }).length > 0;

        if (!isPluginPage || isInstancePage) return;
        if ($('.qa-dashboard').length) return;

        var $tabList = $('#plugin-tabs');
        if (!$tabList.length) $tabList = $('ul.tabs, .tab_nav ul').first();
        if (!$tabList.length) return;

        $tabList.append('<li><a href="#dashboard">Dashboard</a></li>');

        var $container = $(
            '<div id="dashboard" class="tab_content qa-dashboard" ' +
            'style="display:none;padding:15px;"></div>'
        );
        $container.html('<p style="color:#888;">Loading dashboard...</p>');

        var $tabContainer = $('#plugin-tabs_container');
        if ($tabContainer.length) {
            $tabContainer.append($container);
        } else {
            $tabList.after($container);
        }

        $tabList.on('click', 'a[href="#dashboard"]', function(e) {
            e.preventDefault();
            $tabList.find('li').removeClass('active');
            $(this).parent().addClass('active');
            $container.siblings('.tab_content').hide();
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
            success: function(data) { renderDashboard($container, data); },
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
        html += '<button class="qa-dash-period" data-days="7">'  + escapeHtml(i18n.last7days  || 'Last 7 Days')  + '</button>';
        html += '<button class="qa-dash-period" data-days="30">' + escapeHtml(i18n.last30days || 'Last 30 Days') + '</button>';
        html += '<button class="qa-dash-period" data-days="90">' + escapeHtml(i18n.last90days || 'Last 90 Days') + '</button>';
        html += '</div>';

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
        html += '<th>' + escapeHtml(i18n.status      || 'Status')      + '</th>';
        html += '<th>' + escapeHtml(i18n.avgTime     || 'Avg Time')    + '</th>';
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
        html += '<th>' + escapeHtml(i18n.agent   || 'Agent')   + '</th>';
        html += '<th>' + escapeHtml(i18n.claimed || 'Claimed') + '</th>';
        html += '</tr></thead><tbody>';
        if (data.agents && data.agents.length) {
            data.agents.forEach(function(a, idx) {
                var medal = idx === 0 ? ' \uD83E\uDD47' : idx === 1 ? ' \uD83E\uDD48' : idx === 2 ? ' \uD83E\uDD49' : '';
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
        html += '<th>' + escapeHtml(i18n.status  || 'Status')  + '</th>';
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

        // Card 5: Calculated Field Values (optional)
        if (data.cfValues && data.cfValues.length) {
            html += '<div class="qa-dash-card">';
            html += '<h3>' + escapeHtml(i18n.fieldValues || 'Field Values') + '</h3>';
            html += '<table class="qa-dash-table"><thead><tr>';
            html += '<th>' + escapeHtml(i18n.agent       || 'Agent')   + '</th>';
            html += '<th>' + escapeHtml(i18n.totalValue  || 'Total')   + '</th>';
            html += '<th>' + escapeHtml(i18n.ticketCount || 'Tickets') + '</th>';
            html += '</tr></thead><tbody>';
            data.cfValues.forEach(function(a, idx) {
                var medal = idx === 0 ? ' \uD83E\uDD47' : idx === 1 ? ' \uD83E\uDD48' : idx === 2 ? ' \uD83E\uDD49' : '';
                html += '<tr><td>' + escapeHtml(a.name) + medal + '</td>';
                html += '<td><strong>' + a.total + '</strong></td>';
                html += '<td>' + a.count + '</td></tr>';
            });
            html += '</tbody></table></div>';
        }

        html += '</div>'; // grid

        $container.html(html);

        $container.find('.qa-dash-period').on('click', function() {
            var days = $(this).data('days');
            $container.html('<p style="color:#888;">Loading...</p>');
            loadDashboard($container, days);
        });

        // Agent performance card (appended after grid)
        renderAgentPerfCard($container, data, i18n);
    }

    function fmtAdminSec(s) {
        if (!s || s < 0 || isNaN(s)) return '0s';
        s = Math.round(s);
        if (s < 60)    return s + 's';
        if (s < 3600)  return Math.round(s / 60) + 'm';
        if (s < 86400) return (s / 3600).toFixed(1) + 'h';
        return (s / 86400).toFixed(1) + 'd';
    }

    function renderAgentPerfCard($container, data, i18n) {
        var stats = data.agentStats || [];
        if (!stats.length) return;

        var medals = ['\uD83E\uDD47', '\uD83E\uDD48', '\uD83E\uDD49'];

        var html = '<div class="qa-dash-card qa-dash-card-full qa-ap-card">';
        html += '<h3>' + escapeHtml(i18n.agentPerformance || 'Agent Performance by Status') + '</h3>';

        // Filter bar
        html += '<div class="qa-ap-filters">';
        html += '<select class="qa-ap-select" id="qa-ap-dept"><option value="">' + escapeHtml(i18n.allDepartments || 'All Departments') + '</option>';
        (data.departments || []).forEach(function(d) {
            html += '<option value="' + escapeHtml(d.id) + '">' + escapeHtml(d.name) + '</option>';
        });
        html += '</select>';
        html += '<select class="qa-ap-select" id="qa-ap-agent"><option value="">' + escapeHtml(i18n.allAgents || 'All Agents') + '</option>';
        (data.agentList || []).forEach(function(a) {
            html += '<option value="' + escapeHtml(a.id) + '">' + escapeHtml(a.name) + '</option>';
        });
        html += '</select>';
        html += '<select class="qa-ap-select" id="qa-ap-topic"><option value="">' + escapeHtml(i18n.allTopics || 'All Topics') + '</option>';
        (data.topics || []).forEach(function(tp) {
            html += '<option value="' + escapeHtml(tp.id) + '">' + escapeHtml(tp.name) + '</option>';
        });
        html += '</select>';
        html += '</div>';
        html += '<div id="qa-ap-body"></div>';
        html += '</div>';

        $container.append(html);

        function getFilters() {
            return {
                dept:  $('#qa-ap-dept').val()  || '',
                agent: $('#qa-ap-agent').val() || '',
                topic: $('#qa-ap-topic').val() || ''
            };
        }

        function renderTable(filters) {
            var filtered = stats.filter(function(r) {
                if (filters.dept  && r.deptId  !== filters.dept)  return false;
                if (filters.agent && r.agentId !== filters.agent) return false;
                if (filters.topic && r.topicId !== filters.topic) return false;
                return true;
            });

            var $body = $('#qa-ap-body');
            if (!filtered.length) {
                $body.html('<p style="color:#999;padding:10px 0;">No data for this period</p>');
                return;
            }

            // Group by status, aggregate by agent within each group
            var groupOrder = [], groups = {};
            filtered.forEach(function(r) {
                if (!groups[r.statusId]) {
                    groups[r.statusId] = { statusName: r.statusName, byAgent: {} };
                    groupOrder.push(r.statusId);
                }
                var ba = groups[r.statusId].byAgent;
                if (!ba[r.agentId]) {
                    ba[r.agentId] = { agentId: r.agentId, agentName: r.agentName, totalSec: 0, totalCount: 0 };
                }
                ba[r.agentId].totalSec   += r.avgSeconds * r.count;
                ba[r.agentId].totalCount += r.count;
            });
            // Build rows from aggregated agents
            groupOrder.forEach(function(statusId) {
                var ba = groups[statusId].byAgent;
                groups[statusId].rows = Object.keys(ba).map(function(aid) {
                    var a = ba[aid];
                    var avgSec = a.totalCount > 0 ? Math.round(a.totalSec / a.totalCount) : 0;
                    return { agentId: a.agentId, agentName: a.agentName,
                             avgSeconds: avgSec, avgDisplay: fmtAdminSec(avgSec), count: a.totalCount };
                });
            });

            var t = '<div style="overflow-x:auto;"><table class="qa-dash-table qa-ap-table">';
            t += '<thead><tr>';
            t += '<th>' + escapeHtml(i18n.agent  || 'Agent')    + '</th>';
            t += '<th>' + escapeHtml(i18n.avgTime || 'Avg Time') + '</th>';
            t += '<th>' + escapeHtml(i18n.tickets || 'Tickets')  + '</th>';
            t += '<th>' + escapeHtml(i18n.vsAvg   || 'vs Avg')   + '</th>';
            t += '</tr></thead><tbody>';

            groupOrder.forEach(function(statusId) {
                var group  = groups[statusId];
                var rows   = group.rows.slice().sort(function(a, b) { return a.avgSeconds - b.avgSeconds || a.agentName.localeCompare(b.agentName); });
                var maxSec = rows[rows.length - 1].avgSeconds || 1;
                var sumW = 0, sumC = 0;
                rows.forEach(function(r) { sumW += r.avgSeconds * r.count; sumC += r.count; });
                var teamAvg = sumC > 0 ? Math.round(sumW / sumC) : 0;

                t += '<tr style="background:#f7f8fa;"><td colspan="4"><strong>' + escapeHtml(group.statusName) + '</strong>';
                if (teamAvg > 0 && rows.length > 1) {
                    t += ' <span style="font-weight:400;color:#999;font-size:11px;">' + escapeHtml(i18n.teamAvg || 'Team avg') + ': ' + escapeHtml(fmtAdminSec(teamAvg)) + '</span>';
                }
                t += '</td></tr>';

                var showMedals = !filters.agent && rows.length >= 2;
                rows.forEach(function(r, idx) {
                    var barPct = Math.round((r.avgSeconds / maxSec) * 100);
                    var vsHtml = '';
                    if (teamAvg > 0 && rows.length > 1) {
                        var pct = Math.round(((r.avgSeconds - teamAvg) / teamAvg) * 100);
                        var color = pct < 0 ? '#27ae60' : (pct > 0 ? '#e74c3c' : '#999');
                        vsHtml = '<span style="color:' + color + ';font-weight:600;">' + (pct > 0 ? '+' : '') + pct + '%</span>';
                    }
                    var nameHtml = (showMedals && idx < 3 ? medals[idx] + ' ' : '') + escapeHtml(r.agentName);
                    var barHtml = '<div style="display:flex;align-items:center;gap:6px;">' +
                        '<div style="flex:1;height:6px;background:#eee;border-radius:3px;min-width:40px;overflow:hidden;">' +
                        '<div style="height:6px;background:#128DBE;border-radius:3px;width:' + barPct + '%;"></div></div>' +
                        '<span style="font-size:12px;font-weight:600;color:#128DBE;white-space:nowrap;">' + escapeHtml(r.avgDisplay) + '</span></div>';

                    t += '<tr><td>' + nameHtml + '</td><td>' + barHtml + '</td>';
                    t += '<td><strong>' + r.count + '</strong></td>';
                    t += '<td>' + vsHtml + '</td></tr>';
                });
            });

            t += '</tbody></table></div>';
            $body.html(t);
        }

        renderTable({ dept: '', agent: '', topic: '' });

        $(document).on('change', '#qa-ap-dept, #qa-ap-agent, #qa-ap-topic', function() {
            renderTable(getFilters());
        });
    }

    // ================================================================
    //  Bootstrap
    // ================================================================

    $(function() {
        initWidgetConfig();
        initDashboard();
    });

    $(document).on('pjax:end', function() {
        initWidgetConfig();
    });

})(jQuery);
