/**
 * Workflow Dashboard — Modern KPI Design
 */
(function() {
    'use strict';

    var D = WD_DATA;
    var T = D.i18n || {};
    var currentDays = 30;
    var customFrom = '';
    var customTo = '';

    function t(key) { return T[key] || key; }

    function esc(str) {
        if (!str) return '';
        var d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    function todayStr() {
        return new Date().toISOString().substring(0, 10);
    }

    function daysAgoStr(n) {
        var d = new Date();
        d.setDate(d.getDate() - n);
        return d.toISOString().substring(0, 10);
    }

    function render() {
        var app = document.getElementById('wd-app');

        var today = todayStr();
        var defaultFrom = daysAgoStr(30);

        app.innerHTML =
            '<div class="wd-header">' +
            '<div>' +
            '<h1>' + esc(t('workflowDashboard')) + '</h1>' +
            '<div class="wd-header-sub">' + esc(t('realtimeMetrics')) + '</div>' +
            '</div>' +
            '<div class="wd-controls">' +
            '<div class="wd-period-btns">' +
            '<button class="wd-period-btn" data-days="7">' + esc(t('last7')) + '</button>' +
            '<button class="wd-period-btn active" data-days="30">' + esc(t('last30')) + '</button>' +
            '<button class="wd-period-btn" data-days="90">' + esc(t('last90')) + '</button>' +
            '</div>' +
            '<div class="wd-date-range">' +
            '<input type="date" class="wd-date-input" id="wd-from" value="' + defaultFrom + '">' +
            '<span class="wd-date-sep">\u2014</span>' +
            '<input type="date" class="wd-date-input" id="wd-to" value="' + today + '">' +
            '<button class="wd-date-go" id="wd-date-go">' + esc(t('apply')) + '</button>' +
            '</div>' +
            '</div>' +
            '</div>' +
            '<div id="wd-content"><div class="wd-loading">' + esc(t('loading')) + '</div></div>';

        // Period quick buttons
        app.querySelectorAll('.wd-period-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                app.querySelectorAll('.wd-period-btn').forEach(function(b) { b.classList.remove('active'); });
                this.classList.add('active');
                currentDays = parseInt(this.dataset.days);
                customFrom = '';
                customTo = '';
                // Update date inputs to match
                document.getElementById('wd-from').value = daysAgoStr(currentDays);
                document.getElementById('wd-to').value = todayStr();
                loadData();
            });
        });

        // Custom date range
        document.getElementById('wd-date-go').addEventListener('click', function() {
            customFrom = document.getElementById('wd-from').value;
            customTo = document.getElementById('wd-to').value;
            if (!customFrom || !customTo) return;
            // Deactivate period buttons
            app.querySelectorAll('.wd-period-btn').forEach(function(b) { b.classList.remove('active'); });
            currentDays = 0;
            loadData();
        });

        loadData();
    }

    function loadData() {
        var content = document.getElementById('wd-content');
        content.innerHTML = '<div class="wd-loading">' + esc(t('loading')) + '</div>';

        var params = '';
        if (customFrom && customTo) {
            params = '?from=' + encodeURIComponent(customFrom) + '&to=' + encodeURIComponent(customTo);
        } else {
            params = '?days=' + currentDays;
        }

        var xhr = new XMLHttpRequest();
        xhr.open('GET', D.apiUrl + params, true);
        xhr.setRequestHeader('X-CSRFToken', D.csrfToken);

        xhr.onload = function() {
            try {
                var data = JSON.parse(xhr.responseText);
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

        // ── KPI Cards ──
        var totalToday = 0, totalPeriod = 0;
        if (data.daily && data.daily.length) {
            totalPeriod = data.daily.reduce(function(s, d) { return s + d.count; }, 0);
            totalToday = data.daily[data.daily.length - 1].count;
        }
        var avgPerDay = data.daily && data.daily.length
            ? Math.round(totalPeriod / data.daily.length) : 0;

        var activeAgents = data.agents ? data.agents.length : 0;
        var queueDepth = 0;
        if (data.queue) {
            queueDepth = data.queue.reduce(function(s, q) { return s + q.count; }, 0);
        }

        html += '<div class="wd-kpis">';
        html += kpiCard('blue', t('processedToday'), totalToday, t('ticketsToday'));
        html += kpiCard('green', t('avgPerDay'), avgPerDay, currentDays + ' ' + t('dayPeriod'));
        html += kpiCard('purple', t('activeAgents'), activeAgents, t('agentsWithActions'));
        html += kpiCard('orange', t('queueDepth'), queueDepth, t('ticketsInPipeline'));
        html += kpiCard('red', t('totalProcessed'), totalPeriod, currentDays + ' ' + t('days'));
        html += '</div>';

        // ── Charts Grid ──
        html += '<div class="wd-grid">';

        // Throughput bar chart — adaptive: daily for 7/30d, weekly for 90d
        html += '<div class="wd-card wd-card-full">';
        var isWeekly = currentDays >= 60;
        html += '<h3>' + esc(isWeekly ? t('weeklyThroughput') : t('dailyThroughput')) + '</h3>';
        if (data.daily && data.daily.length) {
            var chartData;
            if (isWeekly) {
                // Roll up into weeks
                chartData = rollupWeekly(data.daily);
            } else {
                // Show last 14 days for daily view
                chartData = data.daily.slice(-14).map(function(d) {
                    return { label: d.day.substring(5), count: d.count };
                });
            }
            var maxCount = Math.max.apply(null, chartData.map(function(d) { return d.count; }));
            chartData.forEach(function(d) {
                var pct = maxCount > 0 ? Math.round((d.count / maxCount) * 100) : 0;
                html += '<div class="wd-bar-row">';
                html += '<span class="wd-bar-label">' + d.label + '</span>';
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

        // Agent leaderboard OR My Performance (access-limited)
        var isLimited = data.accessMode === 'limited';
        html += '<div class="wd-card">';
        if (isLimited) {
            // Access-limited: show only own stats, no leaderboard
            html += '<h3>' + esc(t('myPerformance')) + '</h3>';
            var myCount = 0;
            if (data.agents && data.agents.length) {
                // The API already filtered to assigned tickets only,
                // so there should be only the current agent's data
                myCount = data.agents.reduce(function(s, a) { return s + a.count; }, 0);
            }
            html += '<div style="text-align:center;padding:20px 0;">';
            html += '<div style="font-size:48px;font-weight:700;color:#128DBE;">' + myCount + '</div>';
            html += '<div style="font-size:13px;color:#888;margin-top:4px;">' + esc(t('claimed')) + '</div>';
            if (data.staffName) {
                html += '<div style="font-size:12px;color:#aaa;margin-top:8px;">' + esc(data.staffName) + '</div>';
            }
            html += '</div>';
        } else {
            // Full leaderboard
            html += '<h3>' + esc(t('agentLeaderboard')) + '</h3>';
            if (data.agents && data.agents.length) {
                data.agents.forEach(function(a, idx) {
                    var rankClass = idx < 3 ? 'wd-rank-' + (idx + 1) : 'wd-rank-n';
                    var rankLabel = idx < 3 ? ['1','2','3'][idx] : (idx + 1);
                    html += '<div class="wd-agent-row">';
                    html += '<div class="wd-agent-rank ' + rankClass + '">' + rankLabel + '</div>';
                    html += '<div class="wd-agent-name">' + esc(a.name) + '</div>';
                    html += '<div class="wd-agent-count">' + a.count + '</div>';
                    html += '</div>';
                });
            } else {
                html += '<div style="color:#999;padding:20px;text-align:center;">' + esc(t('noData')) + '</div>';
            }
        }
        html += '</div>';

        // Current queue / My assigned tickets
        html += '<div class="wd-card">';
        html += '<h3>' + esc(isLimited ? t('myQueue') : t('currentQueue')) + '</h3>';
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

    function rollupWeekly(daily) {
        var weeks = [];
        var current = { label: '', count: 0, start: '' };
        daily.forEach(function(d, i) {
            if (i % 7 === 0 && i > 0) {
                current.label = current.start + '\u2013' + daily[i - 1].day.substring(5);
                weeks.push(current);
                current = { label: '', count: 0, start: d.day.substring(5) };
            }
            if (!current.start) current.start = d.day.substring(5);
            current.count += d.count;
        });
        // Push remaining
        if (current.count > 0 || current.start) {
            current.label = current.start + '\u2013' + daily[daily.length - 1].day.substring(5);
            weeks.push(current);
        }
        return weeks;
    }

    function kpiCard(color, label, value, sub) {
        return '<div class="wd-kpi wd-kpi-' + color + '">' +
            '<div class="wd-kpi-label">' + esc(label) + '</div>' +
            '<div class="wd-kpi-value">' + value + '</div>' +
            '<div class="wd-kpi-sub">' + esc(sub) + '</div>' +
            '</div>';
    }

    render();
})();
