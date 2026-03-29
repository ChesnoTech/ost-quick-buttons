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
    // Preserve AP filter selections across re-renders
    var savedApFilters = { dept: '', agent: '', topic: '' };

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

    function pastDateStr(days) {
        var d = new Date();
        d.setDate(d.getDate() - days);
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }

    function fmtDisplay(ymd) {
        if (!ymd) return '';
        var p = ymd.split('-');
        return p[2] + '.' + p[1] + '.' + p[0]; // DD.MM.YYYY
    }

    function parseDisplay(str) {
        if (!str) return '';
        var p = str.split('.');
        if (p.length === 3) return p[2] + '-' + p[1] + '-' + p[0];
        return str;
    }

    // Minimal date picker — Monday-start weeks
    var activePicker = null;
    function openPicker(inputEl, onPick) {
        closePicker();
        var val = inputEl.dataset.value || todayStr();
        var parts = val.split('-');
        var year = parseInt(parts[0]), month = parseInt(parts[1]) - 1;

        var overlay = document.createElement('div');
        overlay.className = 'wd-picker-overlay';
        overlay.addEventListener('click', function(e) { if (e.target === overlay) closePicker(); });

        var box = document.createElement('div');
        box.className = 'wd-picker';

        function draw() {
            var hdr = '<div class="wd-pk-hdr">';
            hdr += '<button class="wd-pk-nav" data-d="-1">&#9664;</button>';
            var mNames = [t('jan')||'Jan',t('feb')||'Feb',t('mar')||'Mar',t('apr')||'Apr',t('may')||'May',t('jun')||'Jun',
                          t('jul')||'Jul',t('aug')||'Aug',t('sep')||'Sep',t('oct')||'Oct',t('nov')||'Nov',t('dec')||'Dec'];
            hdr += '<span class="wd-pk-title">' + esc(mNames[month]) + ' ' + year + '</span>';
            hdr += '<button class="wd-pk-nav" data-d="1">&#9654;</button>';
            hdr += '</div>';

            var days = [t('mo')||'Mo',t('tu')||'Tu',t('we')||'We',t('th')||'Th',t('fr')||'Fr',t('sa')||'Sa',t('su')||'Su'];
            var grid = '<div class="wd-pk-days">';
            days.forEach(function(d) { grid += '<span class="wd-pk-dh">' + esc(d) + '</span>'; });

            var first = new Date(year, month, 1);
            var dow = (first.getDay() + 6) % 7; // Monday=0
            var daysInMonth = new Date(year, month + 1, 0).getDate();
            var today = todayStr();
            var selected = inputEl.dataset.value || '';

            for (var i = 0; i < dow; i++) grid += '<span class="wd-pk-d wd-pk-empty"></span>';
            for (var d = 1; d <= daysInMonth; d++) {
                var ymd = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(d).padStart(2, '0');
                var cls = 'wd-pk-d';
                if (ymd === today) cls += ' wd-pk-today';
                if (ymd === selected) cls += ' wd-pk-sel';
                grid += '<span class="' + cls + '" data-ymd="' + ymd + '">' + d + '</span>';
            }
            grid += '</div>';
            box.innerHTML = hdr + grid;

            box.querySelectorAll('.wd-pk-nav').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    month += parseInt(this.dataset.d);
                    if (month < 0) { month = 11; year--; }
                    if (month > 11) { month = 0; year++; }
                    draw();
                });
            });
            box.querySelectorAll('.wd-pk-d[data-ymd]').forEach(function(cell) {
                cell.addEventListener('click', function(e) {
                    e.stopPropagation();
                    var ymd = this.dataset.ymd;
                    inputEl.dataset.value = ymd;
                    inputEl.textContent = fmtDisplay(ymd);
                    onPick(ymd);
                    closePicker();
                });
            });
        }

        draw();
        overlay.appendChild(box);

        // Position near the input
        var rect = inputEl.getBoundingClientRect();
        box.style.position = 'absolute';
        box.style.top = (rect.bottom + window.scrollY + 4) + 'px';
        box.style.left = (rect.left + window.scrollX) + 'px';
        overlay.style.position = 'absolute';
        overlay.style.top = '0';
        overlay.style.left = '0';
        overlay.style.width = '100%';
        overlay.style.height = Math.max(document.body.scrollHeight, window.innerHeight) + 'px';
        document.body.appendChild(overlay);
        activePicker = overlay;
    }

    function closePicker() {
        if (activePicker && activePicker.parentNode) {
            activePicker.parentNode.removeChild(activePicker);
        }
        activePicker = null;
    }

    var MODE = D.mode || 'workflow'; // 'workflow' or 'agent-perf'

    function render() {
        var app = document.getElementById('wd-app');
        var title = MODE === 'agent-perf' ? esc(t('agentPerformance')) : esc(t('workflowDashboard'));

        app.innerHTML =
            '<div class="wd-header">' +
            '<div>' +
            '<h1>' + title + '</h1>' +
            '</div>' +
            '<div class="wd-controls">' +
            '<div class="wd-period-btns">' +
            '<button class="wd-period-btn" data-days="7">' + esc(t('last7')) + '</button>' +
            '<button class="wd-period-btn active" data-days="30">' + esc(t('last30')) + '</button>' +
            '<button class="wd-period-btn" data-days="90">' + esc(t('last90')) + '</button>' +
            '</div>' +
            '<div class="wd-date-range">' +
            '<span class="wd-date-input wd-date-pick" id="wd-from" data-value="' + pastDateStr(currentDays) + '">' + fmtDisplay(pastDateStr(currentDays)) + '</span>' +
            '<span class="wd-date-sep">—</span>' +
            '<span class="wd-date-input wd-date-pick" id="wd-to" data-value="' + todayStr() + '">' + fmtDisplay(todayStr()) + '</span>' +
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
                var fromEl = document.getElementById('wd-from');
                var toEl = document.getElementById('wd-to');
                fromEl.dataset.value = pastDateStr(currentDays);
                fromEl.textContent = fmtDisplay(pastDateStr(currentDays));
                toEl.dataset.value = todayStr();
                toEl.textContent = fmtDisplay(todayStr());
                loadData();
            });
        });

        // Custom date range
        // Wire date pickers
        document.getElementById('wd-from').addEventListener('click', function() {
            var self = this;
            openPicker(self, function() {});
        });
        document.getElementById('wd-to').addEventListener('click', function() {
            var self = this;
            openPicker(self, function() {});
        });

        document.getElementById('wd-apply').addEventListener('click', function() {
            var fromVal = document.getElementById('wd-from').dataset.value;
            var toVal = document.getElementById('wd-to').dataset.value;
            if (fromVal && toVal) {
                if (fromVal > toVal) { var tmp = fromVal; fromVal = toVal; toVal = tmp; }
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
        // Agent Performance mode — render only the AP card
        if (MODE === 'agent-perf') {
            container.innerHTML = '';
            if (data.agentStats && data.agentStats.length) {
                renderAgentStats(container, data);
            } else {
                container.innerHTML = '<div style="color:#999;padding:40px;text-align:center;">' + esc(t('noData')) + '</div>';
            }
            return;
        }

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
        if (data.cfValues && data.cfValues.length) {
            var cfTotalKpi = data.cfValues.reduce(function(s, a) { return s + a.total; }, 0);
            html += kpiCard('teal', t('fieldValues'), cfTotalKpi.toFixed(1), t('totalValue'));
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

        // Calculated Field Values
        if (data.cfValues && data.cfValues.length) {
            html += '<div class="wd-card">';
            html += '<h3>' + esc(t('fieldValues')) + '</h3>';
            html += '<table class="wd-table"><thead><tr>';
            html += '<th>' + esc(t('agent')) + '</th>';
            html += '<th class="wd-num">' + esc(t('totalValue')) + '</th>';
            html += '<th class="wd-num">' + esc(t('ticketCount')) + '</th>';
            html += '<th class="wd-num">' + esc(t('average')) + '</th>';
            html += '</tr></thead><tbody>';
            var cfTotal = 0, cfCount = 0;
            data.cfValues.forEach(function(a) {
                var avg = a.count > 0 ? (a.total / a.count).toFixed(2) : '0';
                html += '<tr>';
                html += '<td>' + esc(a.name) + '</td>';
                html += '<td class="wd-num">' + a.total + '</td>';
                html += '<td class="wd-num">' + a.count + '</td>';
                html += '<td class="wd-num">' + avg + '</td>';
                html += '</tr>';
                cfTotal += a.total;
                cfCount += a.count;
            });
            html += '</tbody><tfoot><tr>';
            html += '<td><strong>' + esc(t('total')) + '</strong></td>';
            html += '<td class="wd-num"><strong>' + cfTotal.toFixed(2) + '</strong></td>';
            html += '<td class="wd-num"><strong>' + cfCount + '</strong></td>';
            html += '<td class="wd-num"><strong>' + (cfCount > 0 ? (cfTotal / cfCount).toFixed(2) : '0') + '</strong></td>';
            html += '</tr></tfoot></table>';
            html += '</div>';
        }

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

    function fmtSec(totalSec) {
        if (!totalSec || totalSec < 0 || isNaN(totalSec)) return '0s';
        totalSec = Math.round(totalSec);
        if (totalSec < 60)    return totalSec + 's';
        if (totalSec < 3600)  return Math.round(totalSec / 60) + 'm';
        if (totalSec < 86400) return (totalSec / 3600).toFixed(1) + 'h';
        return (totalSec / 86400).toFixed(1) + 'd';
    }

    function renderAgentStats(container, data) {
        var stats = data.agentStats || [];
        if (!stats.length) return;

        var card = document.createElement('div');
        card.className = MODE === 'agent-perf' ? 'wd-ap-card' : 'wd-card wd-card-full wd-ap-card';

        // Build filter dropdowns
        var fh = '<div class="wd-ap-header">';
        if (MODE !== 'agent-perf') {
            fh += '<h3>' + esc(t('agentPerformance')) + '</h3>';
        }
        fh += '<div class="wd-ap-filters">';

        // Date range inputs — only in embedded mode (standalone uses top header)
        if (MODE !== 'agent-perf') {
            var curFrom = customFrom || pastDateStr(currentDays);
            var curTo = customTo || todayStr();
            fh += '<span class="wd-ap-date wd-date-pick" id="wd-ap-from" data-value="' + curFrom + '">' + fmtDisplay(curFrom) + '</span>';
            fh += '<span class="wd-ap-date-sep">—</span>';
            fh += '<span class="wd-ap-date wd-date-pick" id="wd-ap-to" data-value="' + curTo + '">' + fmtDisplay(curTo) + '</span>';
            fh += '<button id="wd-ap-apply" class="wd-ap-apply">' + esc(t('apply')) + '</button>';
        }

        fh += '<select class="wd-ap-select" id="wd-ap-dept"><option value="">' + esc(t('allDepartments')) + '</option>';
        (data.departments || []).forEach(function(d) {
            fh += '<option value="' + esc(d.id) + '">' + esc(d.name) + '</option>';
        });
        fh += '</select>';

        fh += '<select class="wd-ap-select" id="wd-ap-agent"><option value="">' + esc(t('allAgents')) + '</option>';
        (data.agentList || []).forEach(function(a) {
            fh += '<option value="' + esc(a.id) + '">' + esc(a.name) + '</option>';
        });
        fh += '</select>';

        fh += '<select class="wd-ap-select" id="wd-ap-topic"><option value="">' + esc(t('allTopics')) + '</option>';
        (data.topics || []).forEach(function(tp) {
            fh += '<option value="' + esc(tp.id) + '">' + esc(tp.name) + '</option>';
        });
        fh += '</select>';

        fh += '</div></div>';
        fh += '<div id="wd-ap-body"></div>';
        card.innerHTML = fh;

        // Insert after the grid
        var grid = container.querySelector('.wd-grid');
        if (grid && grid.parentNode) {
            grid.parentNode.insertBefore(card, grid.nextSibling);
        } else {
            container.appendChild(card);
        }

        function getFilters() {
            var dEl = document.getElementById('wd-ap-dept');
            var aEl = document.getElementById('wd-ap-agent');
            var tEl = document.getElementById('wd-ap-topic');
            return {
                dept:  dEl  ? dEl.value  : '',
                agent: aEl  ? aEl.value  : '',
                topic: tEl  ? tEl.value  : ''
            };
        }

        function renderTable(filters) {
            // Filter rows
            var filtered = stats.filter(function(r) {
                if (filters.dept  && r.deptId  !== filters.dept)  return false;
                if (filters.agent && r.agentId !== filters.agent) return false;
                if (filters.topic && r.topicId !== filters.topic) return false;
                return true;
            });

            var body = document.getElementById('wd-ap-body');
            if (!body) return;

            if (!filtered.length) {
                body.innerHTML = '<div style="color:#999;padding:20px;text-align:center;">' + esc(t('noData')) + '</div>';
                return;
            }

            // Group by statusId, aggregate by agentId within each group
            // (same agent may appear in multiple dept/topic combos when filters are "All")
            var groupOrder = [];
            var groups = {};
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
            // Build sorted rows array from aggregated agents
            groupOrder.forEach(function(statusId) {
                var ba = groups[statusId].byAgent;
                groups[statusId].rows = Object.keys(ba).map(function(aid) {
                    var a = ba[aid];
                    var avgSec = a.totalCount > 0 ? Math.round(a.totalSec / a.totalCount) : 0;
                    return { agentId: a.agentId, agentName: a.agentName,
                             avgSeconds: avgSec, avgDisplay: fmtSec(avgSec), count: a.totalCount };
                });
            });

            var medals = ['🥇', '🥈', '🥉'];

            var html = '<div class="wd-ap-scroll"><table class="wd-ap-table">';
            html += '<thead><tr>';
            html += '<th class="wd-ap-th-name">' + esc(t('agent')) + '</th>';
            html += '<th class="wd-ap-th-time">' + esc(t('avgTime')) + '</th>';
            html += '<th class="wd-num">' + esc(t('tickets')) + '</th>';
            html += '<th class="wd-num">' + esc(t('vsAvg')) + '</th>';
            html += '</tr></thead><tbody>';

            groupOrder.forEach(function(statusId) {
                var group  = groups[statusId];
                var rows   = group.rows.slice().sort(function(a, b) { return a.avgSeconds - b.avgSeconds || a.agentName.localeCompare(b.agentName); });
                var maxSec = rows[rows.length - 1].avgSeconds || 1;

                // Weighted team average
                var sumW = 0, sumC = 0;
                rows.forEach(function(r) { sumW += r.avgSeconds * r.count; sumC += r.count; });
                var teamAvg = sumC > 0 ? Math.round(sumW / sumC) : 0;

                // Status group header
                html += '<tr class="wd-ap-status-header">';
                html += '<td colspan="2"><strong>' + esc(group.statusName) + '</strong>';
                if (teamAvg > 0 && rows.length > 1) {
                    html += ' <span class="wd-ap-team-avg">' + esc(t('teamAvg')) + ': ' + esc(fmtSec(teamAvg)) + '</span>';
                }
                html += '</td><td></td><td></td></tr>';

                var showMedals = !filters.agent && rows.length >= 2;

                rows.forEach(function(r, idx) {
                    var barPct = Math.round((r.avgSeconds / maxSec) * 100);
                    var vsHtml = '';
                    if (teamAvg > 0 && rows.length > 1) {
                        var pct = Math.round(((r.avgSeconds - teamAvg) / teamAvg) * 100);
                        var cls = pct < 0 ? 'wd-ap-vs-neg' : (pct > 0 ? 'wd-ap-vs-pos' : '');
                        vsHtml = '<span class="' + cls + '">' + (pct > 0 ? '+' : '') + pct + '%</span>';
                    }

                    html += '<tr class="wd-ap-row">';
                    html += '<td class="wd-ap-name-cell">';
                    if (showMedals && idx < 3) {
                        html += '<span class="wd-ap-medal">' + medals[idx] + '</span>';
                    }
                    html += esc(r.agentName) + '</td>';
                    html += '<td class="wd-ap-bar-cell">';
                    html += '<div class="wd-ap-bar-wrap">';
                    html += '<div class="wd-ap-bar-track"><div class="wd-ap-bar-fill" style="width:' + barPct + '%"></div></div>';
                    html += '<span class="wd-ap-bar-lbl">' + esc(r.avgDisplay) + '</span>';
                    html += '</div></td>';
                    html += '<td class="wd-num">' + r.count + '</td>';
                    html += '<td class="wd-num">' + vsHtml + '</td>';
                    html += '</tr>';
                });
            });

            html += '</tbody></table></div>';
            body.innerHTML = html;
        }

        // Contextual dropdown filtering — when dept is selected, only show agents/topics with data in that dept
        function updateDropdownOptions(filters) {
            var deptEl  = document.getElementById('wd-ap-dept');
            var agentEl = document.getElementById('wd-ap-agent');
            var topicEl = document.getElementById('wd-ap-topic');

            // Find which agents/topics/depts have data given current filters
            function getAvailable(filterKey) {
                return stats.filter(function(r) {
                    if (filterKey !== 'dept'  && filters.dept  && r.deptId  !== filters.dept)  return false;
                    if (filterKey !== 'agent' && filters.agent && r.agentId !== filters.agent) return false;
                    if (filterKey !== 'topic' && filters.topic && r.topicId !== filters.topic) return false;
                    return true;
                });
            }

            // Update agent dropdown
            if (agentEl) {
                var availAgents = {};
                getAvailable('agent').forEach(function(r) { availAgents[r.agentId] = r.agentName; });
                var curAgent = agentEl.value;
                // Remove all options except "All"
                while (agentEl.options.length > 1) agentEl.remove(1);
                // Re-add only agents with data
                var sortedAgents = Object.keys(availAgents).sort(function(a, b) {
                    return availAgents[a].localeCompare(availAgents[b]);
                });
                sortedAgents.forEach(function(id) {
                    var opt = document.createElement('option');
                    opt.value = id;
                    opt.textContent = availAgents[id];
                    agentEl.appendChild(opt);
                });
                // Restore selection if still valid
                agentEl.value = availAgents[curAgent] ? curAgent : '';
                if (agentEl.value !== curAgent) filters.agent = '';
            }

            // Update topic dropdown
            if (topicEl) {
                var availTopics = {};
                getAvailable('topic').forEach(function(r) { availTopics[r.topicId] = r.topicName; });
                var curTopic = topicEl.value;
                while (topicEl.options.length > 1) topicEl.remove(1);
                var sortedTopics = Object.keys(availTopics).sort(function(a, b) {
                    return availTopics[a].localeCompare(availTopics[b]);
                });
                sortedTopics.forEach(function(id) {
                    var opt = document.createElement('option');
                    opt.value = id;
                    opt.textContent = availTopics[id];
                    topicEl.appendChild(opt);
                });
                topicEl.value = availTopics[curTopic] ? curTopic : '';
                if (topicEl.value !== curTopic) filters.topic = '';
            }

            // Update dept dropdown
            if (deptEl) {
                var availDepts = {};
                getAvailable('dept').forEach(function(r) { availDepts[r.deptId] = r.deptName; });
                var curDept = deptEl.value;
                while (deptEl.options.length > 1) deptEl.remove(1);
                var sortedDepts = Object.keys(availDepts).sort(function(a, b) {
                    return availDepts[a].localeCompare(availDepts[b]);
                });
                sortedDepts.forEach(function(id) {
                    var opt = document.createElement('option');
                    opt.value = id;
                    opt.textContent = availDepts[id];
                    deptEl.appendChild(opt);
                });
                deptEl.value = availDepts[curDept] ? curDept : '';
                if (deptEl.value !== curDept) filters.dept = '';
            }
        }

        // Restore saved filter selections and render
        var initFilters = { dept: savedApFilters.dept, agent: savedApFilters.agent, topic: savedApFilters.topic };
        updateDropdownOptions(initFilters);
        // Restore selections after dropdown rebuild
        ['dept', 'agent', 'topic'].forEach(function(key) {
            var el = document.getElementById('wd-ap-' + key);
            if (el && initFilters[key]) el.value = initFilters[key];
        });
        renderTable(initFilters);

        // Wire filter changes
        ['wd-ap-dept', 'wd-ap-agent', 'wd-ap-topic'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el) el.addEventListener('change', function() {
                var f = getFilters();
                updateDropdownOptions(f);
                // Re-read filters after dropdown update may have cleared invalid selections
                f = getFilters();
                savedApFilters.dept  = f.dept;
                savedApFilters.agent = f.agent;
                savedApFilters.topic = f.topic;
                renderTable(f);
            });
        });

        // Wire AP date pickers
        var apFrom = document.getElementById('wd-ap-from');
        var apTo = document.getElementById('wd-ap-to');
        if (apFrom) apFrom.addEventListener('click', function() { openPicker(this, function() {}); });
        if (apTo) apTo.addEventListener('click', function() { openPicker(this, function() {}); });

        // Date range Apply — triggers full dashboard reload
        var applyEl = document.getElementById('wd-ap-apply');
        if (applyEl) {
            applyEl.addEventListener('click', function() {
                var fromVal = document.getElementById('wd-ap-from').dataset.value;
                var toVal = document.getElementById('wd-ap-to').dataset.value;
                if (fromVal && toVal) {
                    if (fromVal > toVal) { var tmp = fromVal; fromVal = toVal; toVal = tmp; }
                    customFrom = fromVal;
                    customTo = toVal;
                    var app = document.getElementById('wd-app');
                    app.querySelectorAll('.wd-period-btn').forEach(function(b) { b.classList.remove('active'); });
                    var topFrom = document.getElementById('wd-from');
                    var topTo = document.getElementById('wd-to');
                    topFrom.dataset.value = fromVal;
                    topFrom.textContent = fmtDisplay(fromVal);
                    topTo.dataset.value = toVal;
                    topTo.textContent = fmtDisplay(toVal);
                    loadData();
                }
            });
        }
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
