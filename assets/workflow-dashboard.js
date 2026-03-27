/**
 * Workflow Dashboard — v2 with KPI cards, date range, access control
 */
(function() {
    'use strict';

    var D = WD_DATA;
    var T = D.i18n || {};
    var currentDays = 30;
    var customFrom = null;
    var customTo = null;

    function t(key) { return T[key] || key; }

    function esc(str) {
        if (!str) return '';
        var d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    function todayStr() {
        var d = new Date();
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }

    function render() {
        var app = document.getElementById('wd-app');

        app.innerHTML =
            '<div class="wd-header">' +
            '<div>' +
            '<h1>' + esc(t('workflowDashboard')) + '</h1>' +
            '</div>' +
            '<div class="wd-controls">' +
            '<div class="wd-period-btns">' +
            '<button class="wd-period-btn" data-days="7">' + esc(t('last7')) + '</button>' +
            '<button class="wd-period-btn active" data-days="30">' + esc(t('last30')) + '</button>' +
            '<button class="wd-period-btn" data-days="90">' + esc(t('last90')) + '</button>' +
            '</div>' +
            '<div class="wd-date-range">' +
            '<input type="date" id="wd-from" class="wd-date-input" title="' + esc(t('from')) + '">' +
            '<span class="wd-date-sep">—</span>' +
            '<input type="date" id="wd-to" class="wd-date-input" title="' + esc(t('to')) + '" value="' + todayStr() + '">' +
            '<button id="wd-apply" class="wd-apply-btn">' + esc(t('apply')) + '</button>' +
            '</div>' +
            '</div>' +
            '</div>' +
            '<div id="wd-content"><div class="wd-loading">' + esc(t('loading')) + '</div></div>';

        // Period buttons
        app.querySelectorAll('.wd-period-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                app.querySelectorAll('.wd-period-btn').forEach(function(b) { b.classList.remove('active'); });
                this.classList.add('active');
                currentDays = parseInt(this.dataset.days);
                customFrom = null;
                customTo = null;
                document.getElementById('wd-from').value = '';
                document.getElementById('wd-to').value = todayStr();
                loadData();
            });
        });

        // Custom date range
        document.getElementById('wd-apply').addEventListener('click', function() {
            var fromVal = document.getElementById('wd-from').value;
            var toVal = document.getElementById('wd-to').value;
            if (fromVal && toVal) {
                customFrom = fromVal;
                customTo = toVal;
                app.querySelectorAll('.wd-period-btn').forEach(function(b) { b.classList.remove('active'); });
                loadData();
            }
        });

        loadData();
    }

    function loadData() {
        var content = document.getElementById('wd-content');
        content.innerHTML = '<div class="wd-loading">' + esc(t('loading')) + '</div>';

        var url = D.apiUrl;
        if (customFrom && customTo) {
            url += '?from=' + customFrom + '&to=' + customTo;
        } else {
            url += '?days=' + currentDays;
        }

        var xhr = new XMLHttpRequest();
        xhr.open('GET', url, true);
        xhr.setRequestHeader('X-CSRFToken', D.csrfToken);

        xhr.onload = function() {
            try {
                var data = JSON.parse(xhr.responseText);
                if (data.i18n) { for (var k in data.i18n) T[k] = data.i18n[k]; }
                renderDashboard(content, data);
            } catch (e) {
                content.innerHTML = '<div class="wd-loading" style="color:#e74c3c;">Failed to load dashboard data</div>';
            }
        };
        xhr.onerror = function() {
            content.innerHTML = '<div class="wd-loading" style="color:#e74c3c;">Network error</div>';
        };
        xhr.send();
    }

    function renderDashboard(container, data) {
        var html = '';
        var kpi = data.kpi || {};
        var isLimited = data.isLimited;

        // ── KPI Cards ──
        html += '<div class="wd-kpis">';
        html += kpiCard('blue', t('totalProcessed'), kpi.totalProcessed || 0, data.days + ' ' + t('days'));
        html += kpiCard('green', t('avgPerDay'), kpi.avgPerDay || 0, t('avgPerDay'));
        html += kpiCard('orange', t('openTickets'), kpi.totalQueue || 0, t('currentQueue'));
        if (!isLimited) {
            html += kpiCard('purple', t('activeAgents'), kpi.activeAgents || 0, t('activeAgents'));
        }
        html += '</div>';

        // ── Charts Grid ──
        html += '<div class="wd-grid">';

        // Throughput chart
        var chartTitle = data.weekly ? t('ticketsPerWeek') : t('ticketsPerDay');
        html += '<div class="wd-card wd-card-full">';
        html += '<h3>' + esc(chartTitle) + '</h3>';
        if (data.daily && data.daily.length) {
            var maxCount = Math.max.apply(null, data.daily.map(function(d) { return d.count; }));
            data.daily.forEach(function(d) {
                var pct = maxCount > 0 ? Math.round((d.count / maxCount) * 100) : 0;
                var label = d.day.length > 7 ? d.day : d.day.substring(5);
                html += '<div class="wd-bar-row">';
                html += '<span class="wd-bar-label">' + esc(label) + '</span>';
                html += '<div class="wd-bar-track"><div class="wd-bar-fill" style="width:' + pct + '%"></div></div>';
                html += '<span class="wd-bar-value">' + d.count + '</span>';
                html += '</div>';
            });
        } else {
            html += '<div style="color:#999;padding:20px;text-align:center;">' + esc(t('noData')) + '</div>';
        }
        html += '</div>';

        // Avg time per step
        html += '<div class="wd-card">';
        html += '<h3>' + esc(t('avgTimePerStep')) + '</h3>';
        html += '<table class="wd-table"><thead><tr>';
        html += '<th>' + esc(t('status')) + '</th>';
        html += '<th class="wd-num">' + esc(t('avgTime')) + '</th>';
        html += '<th class="wd-num">' + esc(t('transitions')) + '</th>';
        html += '</tr></thead><tbody>';
        if (data.avgTimes && data.avgTimes.length) {
            data.avgTimes.forEach(function(s) {
                html += '<tr>';
                html += '<td>' + esc(s.statusName) + '</td>';
                html += '<td class="wd-num wd-time">' + esc(s.avgDisplay) + '</td>';
                html += '<td class="wd-num">' + s.count + '</td>';
                html += '</tr>';
            });
        } else {
            html += '<tr><td colspan="3" style="color:#999;text-align:center;">' + esc(t('noData')) + '</td></tr>';
        }
        html += '</tbody></table></div>';

        // Agent leaderboard / My Performance
        html += '<div class="wd-card">';
        html += '<h3>' + esc(isLimited ? t('myPerformance') : t('agentLeaderboard')) + '</h3>';
        if (data.agents && data.agents.length) {
            data.agents.forEach(function(a, idx) {
                if (isLimited) {
                    // Single agent — just show the count prominently
                    html += '<div class="wd-my-stats">';
                    html += '<div class="wd-my-count">' + a.count + '</div>';
                    html += '<div class="wd-my-label">' + esc(t('claimed')) + '</div>';
                    html += '</div>';
                } else {
                    var rankClass = idx < 3 ? 'wd-rank-' + (idx + 1) : 'wd-rank-n';
                    var rankLabel = idx < 3 ? ['1','2','3'][idx] : (idx + 1);
                    html += '<div class="wd-agent-row">';
                    html += '<div class="wd-agent-rank ' + rankClass + '">' + rankLabel + '</div>';
                    html += '<div class="wd-agent-name">' + esc(a.name) + '</div>';
                    html += '<div class="wd-agent-count">' + a.count + '</div>';
                    html += '</div>';
                }
            });
        } else {
            html += '<div style="color:#999;padding:20px;text-align:center;">' + esc(t('noData')) + '</div>';
        }
        html += '</div>';

        // Current queue
        html += '<div class="wd-card">';
        html += '<h3>' + esc(t('currentQueue')) + '</h3>';
        if (data.queue && data.queue.length) {
            data.queue.forEach(function(q) {
                html += '<div class="wd-queue-row">';
                html += '<div class="wd-queue-name">' + esc(q.statusName) + '</div>';
                html += '<div class="wd-queue-count">' + q.count + '</div>';
                html += '</div>';
            });
        } else {
            html += '<div style="color:#999;padding:20px;text-align:center;">' + esc(t('noData')) + '</div>';
        }
        html += '</div>';

        html += '</div>'; // grid
        container.innerHTML = html;
    }

    function kpiCard(color, label, value, sub) {
        return '<div class="wd-kpi wd-kpi-' + color + '">' +
            '<div class="wd-kpi-value">' + value + '</div>' +
            '<div class="wd-kpi-label">' + esc(label) + '</div>' +
            '<div class="wd-kpi-sub">' + esc(sub) + '</div>' +
            '</div>';
    }

    render();
})();
