/**
 * Quick Buttons Plugin - Frontend v2.3
 *
 * @author  ChesnoTech
 * @version 4.0.0
 */
(function($) {
    'use strict';

    var QA = {
        widgets: null,
        tickets: null,
        i18n: { start: 'Start', done: 'Done', error: 'Error', confirm: 'Confirm',
                cancel: 'Cancel', confirmStart: 'Start working on ticket #%s?',
                confirmStop: 'Complete and hand off ticket #%s?',
                confirmPartial: 'Mark ticket #%s as partially ready?',
                confirmStart2: 'Start step 2 on ticket #%s?',
                countdownStart: 'Claim ticket and change status to working',
                countdownStop: 'Change status, release agent and transfer',
                countdownPartial: 'Release agent and mark partially ready',
                countdownStart2: 'Claim ticket for step 2',
                executingIn: 'Executing in %ss...',
                partialReady: 'Next', startStep2: 'Start Step 2',
                undo: 'Undo', bulkStart: 'Start Selected', bulkStop: 'Complete Selected',
                elapsed: 'elapsed', waiting: 'waiting',
                labelH: 'H', labelM: 'M', labelS: 'S',
                deadline: 'deadline', overdue: 'overdue' },
        perms: { canAssign: true, canTransfer: true, canRelease: true, canManage: true },
        executing: {},
        timerInterval: null,

        // Action type constants
        ACTION_START: 'start',
        ACTION_PARTIAL: 'partial',
        ACTION_START2: 'start2',
        ACTION_STOP: 'stop',

        // Icon/color defaults per action
        ICONS: {
            start: 'icon-play',
            partial: 'icon-arrow-right',
            start2: 'icon-play',
            stop: 'emoji:\u2714'
        },
        COLORS: {
            start: '#128DBE',
            partial: '#e67e22',
            start2: '#2980b9',
            stop: '#27ae60'
        },

        // Legacy aliases (backward compat)
        START_ICON: 'icon-play',
        START_COLOR: '#128DBE',
        STOP_ICON: 'emoji:\u2714',
        STOP_COLOR: '#27ae60',

        init: function() {
            if (!$('form#tickets').length) return;

            // Cleanup
            $('.qa-row-actions').remove();
            $('td.qa-actions-cell').remove();
            $('th.qa-actions-header').remove();
            $('tr.has-qa-inline').removeClass('has-qa-inline');
            $('.qa-bulk-toolbar').remove();
            if (QA.timerInterval) { clearInterval(QA.timerInterval); QA.timerInterval = null; }
            QA.deadlineBadges = [];

            var tids = [];
            $('form#tickets tbody tr input.ckb').each(function() {
                var tid = $(this).val();
                if (tid) tids.push(tid);
            });
            if (!tids.length) return;

            $.ajax({
                url: 'ajax.php/quick-buttons/widgets',
                type: 'POST',
                data: { tids: tids },
                dataType: 'json',
                cache: false,
                success: function(data) {
                    QA.widgets = data.widgets || [];
                    QA.tickets = data.tickets || {};
                    // Record the JS timestamp at the moment the API response arrives.
                    // Combined with server-computed elapsed_secs (TIMESTAMPDIFF in MySQL),
                    // this lets us show correct elapsed time with zero timezone guesswork.
                    QA.fetchMs = Date.now();
                    if (data.i18n) QA.i18n = $.extend(QA.i18n, data.i18n);
                    if (data.perms) QA.perms = data.perms;
                    // Build lookup of topics that have deadline countdown enabled
                    QA.deadlineTopics = {};
                    $.each(QA.widgets, function(_, w) {
                        if (w.showDeadline) QA.deadlineTopics[String(w.topic)] = true;
                    });
                    if (QA.widgets.length) {
                        QA.renderButtons();
                        QA.renderBulkToolbar();
                        QA.startTimers();
                    }
                },
                error: function() {}
            });
        },

        resolveButton: function(ticketId) {
            var info = QA.tickets[ticketId];
            if (!info || !info.topic || !info.dept || !info.status) return null;

            var ticketTopic = String(info.topic);
            var ticketDept = String(info.dept);
            var ticketStatus = String(info.status);

            for (var i = 0; i < QA.widgets.length; i++) {
                var w = QA.widgets[i];
                if (String(w.topic) !== ticketTopic) continue;

                var deptCfg = w.depts[ticketDept];
                if (!deptCfg) continue;

                var action = null;
                var variant = deptCfg.variant || 'single';

                if (deptCfg.start_trigger && ticketStatus === String(deptCfg.start_trigger))
                    action = 'start';
                else if (deptCfg.start_target && ticketStatus === String(deptCfg.start_target))
                    action = (variant === 'twostep') ? 'partial' : 'stop';
                else if (variant === 'twostep' && deptCfg.step2_trigger && ticketStatus === String(deptCfg.step2_trigger))
                    action = 'start2';
                else if (variant === 'twostep' && deptCfg.step2_target && ticketStatus === String(deptCfg.step2_target))
                    action = 'stop';

                if (!action) continue;
                if ((action === 'start' || action === 'start2') && !QA.perms.canAssign) continue;
                if ((action === 'stop' || action === 'partial') && !QA.perms.canManage) continue;
                // Release actions: only show if the ticket is assigned to this agent.
                // System administrators (isAdmin) can see and act on any claimed ticket.
                if ((action === 'stop' || action === 'partial') && info.staff
                        && String(info.staff) !== String(QA.perms.staffId || '')
                        && !QA.perms.isAdmin)
                    continue;

                return {
                    action: action, widgetId: w.id, deptId: ticketDept,
                    startColor: w.startColor, stopColor: w.stopColor,
                    confirm: w.confirm,
                    confirmMode: w.confirmMode || (w.confirm ? 'confirm' : 'none'),
                    countdownSeconds: w.countdownSeconds || 5,
                    labels: {
                        start: deptCfg.start_label || '',
                        stop: deptCfg.stop_label || '',
                        partial: deptCfg.partial_label || '',
                        start2: deptCfg.start2_label || '',
                        finish: deptCfg.finish_label || ''
                    }
                };
            }
            return null;
        },

        // ================================================================
        //  Render buttons
        // ================================================================

        renderButtons: function() {
            var isMobile = window.matchMedia('(max-width: 760px)').matches;
            var $rows = $('form#tickets tbody tr').filter(function() {
                return $(this).find('input.ckb').length > 0;
            });
            var hasAny = false;

            $rows.each(function() {
                var $row = $(this);
                var ticketId = $row.find('input.ckb').val();
                if (!ticketId) return;

                var resolved = QA.resolveButton(ticketId);
                if (!resolved) return;
                hasAny = true;

                var icon = QA.ICONS[resolved.action];
                var color = (resolved.action === 'start' && resolved.startColor)
                    ? resolved.startColor
                    : (resolved.action === 'stop' && resolved.stopColor)
                        ? resolved.stopColor
                        : QA.COLORS[resolved.action];
                var labelDefaults = {
                    start: QA.i18n.start, partial: QA.i18n.partialReady,
                    start2: QA.i18n.startStep2, stop: QA.i18n.done
                };
                var label = (resolved.labels && resolved.labels[resolved.action])
                    || labelDefaults[resolved.action];

                var $link = $('<a href="#"></a>')
                    .addClass('qa-inline-btn')
                    .attr({
                        'data-widget-id': resolved.widgetId,
                        'data-action': resolved.action,
                        'data-dept-id': resolved.deptId,
                        'data-ticket-id': ticketId,
                        'data-confirm-mode': resolved.confirmMode,
                        'data-countdown': resolved.countdownSeconds,
                        'title': label
                    })
                    .css('background-color', color)
                    .html(QA.renderIcon(icon));

                // Live timer — show as badge above button + tooltip
                // Stop = "working time" (green), Start = "waiting time" (orange)
                var info = QA.tickets[ticketId];
                if (info && info.since_secs != null) {
                    var timerClass = (resolved.action === 'stop' || resolved.action === 'partial')
                        ? 'qa-timer-badge qa-timer-working'
                        : 'qa-timer-badge qa-timer-waiting';
                    // since_secs: seconds elapsed at API-fetch time (computed by MySQL TIMESTAMPDIFF).
                    // Timezone-agnostic: both operands are in MySQL's own timezone.
                    var sinceMs = QA.fetchMs - (info.since_secs * 1000);
                    var $timer = $('<span class="' + timerClass + '" data-since-ms="' + sinceMs + '"></span>');
                    $link.data('timer-el', $timer);
                    $link.attr('data-timer', '1');
                }

                // Deadline countdown badge (below button on desktop and mobile)
                if (info && QA.deadlineTopics[String(info.topic)] && info.deadline_secs != null) {
                    var deadlineTargetMs = QA.fetchMs + (info.deadline_secs * 1000);
                    var dlClass = info.deadline_secs <= 0
                        ? 'qa-deadline-badge qa-deadline-overdue'
                        : 'qa-deadline-badge';
                    var $deadline = $('<span class="' + dlClass + '" data-deadline-ms="' + deadlineTargetMs + '">'
                        + '<span class="qa-dl-icon">\u23F3</span>'
                        + '<span class="qa-dl-text"></span>'
                        + '</span>');
                    $link.data('deadline-el', $deadline);
                }

                if (isMobile) {
                    var $actions = $('<div class="qa-row-actions"></div>');
                    // Mobile: vertical stack — timer on top, button in middle, deadline on bottom
                    // All share button color as one unified block
                    $actions.css('background-color', color);
                    var $timerElM = $link.data('timer-el');
                    if ($timerElM) $actions.append($timerElM);  // timer ABOVE button
                    $actions.append($link);                      // button icon only
                    var $dlElM = $link.data('deadline-el');
                    if ($dlElM) $actions.append($dlElM);         // deadline BELOW button
                    $row.addClass('has-qa-inline').prepend($actions);
                } else {
                    // Desktop: everything INSIDE the button — fills the row height
                    var $timerEl = $link.data('timer-el');
                    if ($timerEl) $link.prepend($timerEl);  // elapsed at top
                    var $dlEl = $link.data('deadline-el');
                    if ($dlEl) $link.append($dlEl);         // deadline at bottom
                    var $td = $('<td class="qa-actions-cell"></td>');
                    $td.append($link);
                    $row.addClass('has-qa-inline').append($td);
                }
            });

            if (hasAny && !isMobile) {
                var $headerRow = $('form#tickets thead tr').first();
                if ($headerRow.length)
                    $headerRow.append('<th class="qa-actions-header">' + (QA.i18n.actions || 'Actions') + '</th>');
            }

            if (!isMobile) {
                $('.qa-inline-btn[title]').tooltip({ placement: 'left', container: 'body' });
            }
        },

        // ================================================================
        //  Live timer
        // ================================================================

        startTimers: function() {
            // Cache badge references for efficient per-second updates
            QA.timerBadges = [];
            $('.qa-timer-badge').each(function() {
                var $el = $(this);
                var sinceMs = parseInt($el.data('since-ms'), 10);
                if (!sinceMs) return;
                QA.timerBadges.push({
                    $el: $el,
                    sinceMs: sinceMs,
                    isWaiting: $el.hasClass('qa-timer-waiting'),
                    $btn: $el.closest('.qa-inline-btn').length
                        ? $el.closest('.qa-inline-btn')
                        : $el.siblings('.qa-inline-btn')
                });
            });
            // Cache deadline countdown badges
            QA.deadlineBadges = [];
            $('.qa-deadline-badge').each(function() {
                var $el = $(this);
                var deadlineMs = parseInt($el.data('deadline-ms'), 10);
                if (!deadlineMs) return;
                QA.deadlineBadges.push({ $el: $el, deadlineMs: deadlineMs });
            });
            if (QA.timerBadges.length || QA.deadlineBadges.length) {
                QA.updateTimers();
                QA.timerInterval = setInterval(QA.updateTimers, 1000);
            }
        },

        renderTimerHtml: function(h, m, s) {
            var lD = QA.i18n.labelD || 'D';
            var lH = QA.i18n.labelH || 'H';
            var lM = QA.i18n.labelM || 'M';
            var lS = QA.i18n.labelS || 'S';
            var d = Math.floor(h / 24);
            var rh = h % 24;
            var R = function(v, l) { return '<span class="qa-t-row"><span class="qa-tv">' + v + '</span><span class="qa-tl">' + l + '</span></span>'; };
            var parts = [];
            parts.push('<span class="qa-ti">\u23F1</span>');
            if (d > 0) {
                parts.push(R(rh, lH) + '<span class="qa-ts"></span>' + R(d, lD));
            } else if (h > 0) {
                parts.push(R(m < 10 ? '0' + m : m, lM) + '<span class="qa-ts"></span>' + R(h, lH));
            } else if (m > 0) {
                parts.push(R(s < 10 ? '0' + s : s, lS) + '<span class="qa-ts"></span>' + R(m, lM));
            } else {
                parts.push(R(s, lS));
            }
            return parts.join('');
        },

        // Mobile vertical deadline layout — mirrors renderTimerHtml but with ⏳
        renderDeadlineHtml: function(totalSec, isOverdue) {
            var lH = QA.i18n.labelH || 'H';
            var lM = QA.i18n.labelM || 'M';
            var lS = QA.i18n.labelS || 'S';
            var lD = QA.i18n.labelD || 'D';
            var d = Math.floor(totalSec / 86400);
            var h = Math.floor((totalSec % 86400) / 3600);
            var m = Math.floor((totalSec % 3600) / 60);
            var s = totalSec % 60;
            var R = function(v, l) { return '<span class="qa-dl-row"><span class="qa-dl-value">' + v + '</span><span class="qa-dl-label">' + l + '</span></span>'; };
            var parts = [];
            parts.push('<span class="qa-dl-icon">\u23F3</span>');
            if (d > 0) {
                parts.push(R(h, lH) + '<span class="qa-ts"></span>' + R(d, lD));
            } else if (h > 0) {
                parts.push(R(m < 10 ? '0' + m : m, lM) + '<span class="qa-ts"></span>' + R(h, lH));
            } else if (m > 0) {
                parts.push(R(s < 10 ? '0' + s : s, lS) + '<span class="qa-ts"></span>' + R(m, lM));
            } else {
                parts.push(R(s, lS));
            }
            return parts.join('');
        },

        updateTimers: function() {
            var nowMs = Date.now();
            var isMobile = window.matchMedia('(max-width: 760px)').matches;
            for (var i = 0; i < QA.timerBadges.length; i++) {
                var badge = QA.timerBadges[i];
                var diffSec = Math.max(0, Math.floor((nowMs - badge.sinceMs) / 1000));
                var h = Math.floor(diffSec / 3600);
                var m = Math.floor((diffSec % 3600) / 60);
                var s = diffSec % 60;
                if (isMobile) {
                    badge.$el.html(QA.renderTimerHtml(h, m, s));
                } else {
                    badge.$el.html('<span class="qa-ti">\u23F1</span><span class="qa-tt">' + QA.formatDuration(diffSec) + '</span>');
                }
                var formatted = QA.formatDuration(diffSec);
                var suffix = badge.isWaiting ? (QA.i18n.waiting || 'waiting') : (QA.i18n.elapsed || 'elapsed');
                badge.$btn.attr('title', formatted + ' ' + suffix);
            }
            // Deadline countdown badges
            for (var j = 0; j < QA.deadlineBadges.length; j++) {
                var dl = QA.deadlineBadges[j];
                var remainSec = Math.floor((dl.deadlineMs - nowMs) / 1000);
                var isOverdue = remainSec < 0;
                var absSec = Math.abs(remainSec);
                dl.$el.toggleClass('qa-deadline-overdue', isOverdue);
                if (isMobile) {
                    dl.$el.html(QA.renderDeadlineHtml(absSec, isOverdue));
                } else {
                    dl.$el.find('.qa-dl-text').text((isOverdue ? '- ' : '') + QA.formatDuration(absSec));
                }
            }
        },

        handleAjaxError: function(xhr) {
            var msg = '';
            try { msg = JSON.parse(xhr.responseText).error || xhr.responseText; }
            catch (ex) { msg = xhr.responseText || 'Unknown error'; }
            $.sysAlert(QA.i18n.error, msg);
        },

        formatDuration: function(totalSec) {
            var d = Math.floor(totalSec / 86400);
            var h = Math.floor((totalSec % 86400) / 3600);
            var m = Math.floor((totalSec % 3600) / 60);
            var s = totalSec % 60;
            if (d > 0)
                return h + ' H - ' + d + ' D';
            if (h > 0)
                return (m < 10 ? '0' : '') + m + ' M - ' + h + ' H';
            if (m > 0)
                return (s < 10 ? '0' : '') + s + ' S - ' + m + ' M';
            return s + ' S';
        },

        // ================================================================
        //  Bulk toolbar
        // ================================================================

        renderBulkToolbar: function() {
            if ($('.qa-bulk-toolbar').length) return;

            var $toolbar = $('<div class="qa-bulk-toolbar" style="display:none;"></div>');
            $toolbar.append(
                $('<button type="button" class="qa-bulk-btn qa-bulk-start"></button>')
                    .css('background-color', QA.START_COLOR)
                    .html(QA.renderIcon(QA.START_ICON) + ' <span>' + QA.escapeHtml(QA.i18n.bulkStart) + '</span>')
            );
            $toolbar.append(
                $('<button type="button" class="qa-bulk-btn qa-bulk-stop"></button>')
                    .css('background-color', QA.STOP_COLOR)
                    .html(QA.renderIcon(QA.STOP_ICON) + ' <span>' + QA.escapeHtml(QA.i18n.bulkStop) + '</span>')
            );

            // Insert after the queue toolbar
            $('form#tickets .sticky.bar, form#tickets table.sticky-header').first().after($toolbar);

            // Show/hide based on checkbox selection
            $('form#tickets').on('change', 'input.ckb', function() {
                var checked = $('form#tickets tbody input.ckb:checked').length;
                $toolbar.toggle(checked > 0);
            });
        },

        handleBulkAction: function(action) {
            var tids = [];
            $('form#tickets tbody tr input.ckb:checked').each(function() {
                var tid = $(this).val();
                if (tid) {
                    var resolved = QA.resolveButton(tid);
                    if (resolved && resolved.action === action)
                        tids.push({ tid: tid, resolved: resolved });
                }
            });

            if (!tids.length) return;

            var firstResolved = tids[0].resolved;
            var ticketIds = tids.map(function(t) { return t.tid; });

            var message = (action === 'start')
                ? QA.i18n.bulkStart + ' (' + tids.length + ')?'
                : QA.i18n.bulkStop + ' (' + tids.length + ')?';

            if (!confirm(message)) return;

            $.ajax({
                url: 'ajax.php/quick-buttons/execute',
                type: 'POST',
                data: {
                    widget_id: firstResolved.widgetId,
                    action: action,
                    dept_id: firstResolved.deptId,
                    tids: ticketIds
                },
                dataType: 'json',
                success: function(resp) {
                    if (resp.canUndo) QA.showUndoBar();
                    $.pjax.reload('#pjax-container');
                },
                error: QA.handleAjaxError
            });
        },

        // ================================================================
        //  Undo
        // ================================================================

        showUndoBar: function() {
            $('.qa-undo-bar').remove();
            var $bar = $('<div class="qa-undo-bar">' +
                '<span class="qa-undo-msg">' + QA.escapeHtml(QA.i18n.done) + '</span>' +
                '<a href="#" class="qa-undo-link">' + QA.escapeHtml(QA.i18n.undo) + '</a>' +
                '<span class="qa-undo-countdown">60s</span>' +
                '</div>');
            $('body').append($bar);

            var remaining = 60;
            var interval = setInterval(function() {
                remaining--;
                $bar.find('.qa-undo-countdown').text(remaining + 's');
                if (remaining <= 0) {
                    clearInterval(interval);
                    $bar.fadeOut(300, function() { $bar.remove(); });
                }
            }, 1000);

            $bar.find('.qa-undo-link').on('click', function(e) {
                e.preventDefault();
                clearInterval(interval);
                $bar.remove();
                QA.executeUndo();
            });

            // Auto-dismiss on click elsewhere
            setTimeout(function() {
                $(document).one('click', function() {
                    clearInterval(interval);
                    $bar.fadeOut(300, function() { $bar.remove(); });
                });
            }, 500);
        },

        executeUndo: function() {
            $.ajax({
                url: 'ajax.php/quick-buttons/undo',
                type: 'POST',
                dataType: 'json',
                success: function() {
                    $.pjax.reload('#pjax-container');
                },
                error: QA.handleAjaxError
            });
        },

        // ================================================================
        //  Click handler
        // ================================================================

        handleInlineClick: function(e) {
            e.preventDefault();
            e.stopPropagation();

            var $btn = $(this);
            if ($btn.hasClass('qa-loading')) return false;

            var widgetId = $btn.data('widget-id');
            var action = $btn.data('action');
            var deptId = $btn.data('dept-id');
            var ticketId = $btn.data('ticket-id');
            var confirmMode = $btn.data('confirmMode') || 'none';
            var countdownSec = parseInt($btn.data('countdown'), 10) || 5;

            if (!widgetId || !action || !ticketId) return false;
            if (QA.executing[ticketId]) return false;

            var doExecute = function() {
                QA.executing[ticketId] = true;
                $btn.addClass('qa-loading');
                var originalHtml = $btn.html();
                $btn.html('<i class="icon-spinner icon-spin"></i>');

                $.ajax({
                    url: 'ajax.php/quick-buttons/execute',
                    type: 'POST',
                    data: {
                        widget_id: widgetId,
                        action: action,
                        dept_id: deptId,
                        tids: [ticketId]
                    },
                    dataType: 'json',
                    success: function(resp) {
                        delete QA.executing[ticketId];
                        if (resp.canUndo) QA.showUndoBar();
                        $.pjax.reload('#pjax-container');
                    },
                    error: function(xhr) {
                        delete QA.executing[ticketId];
                        QA.handleAjaxError(xhr);
                        $btn.removeClass('qa-loading');
                        $btn.html(originalHtml);
                    }
                });
            };

            var $row = $btn.closest('tr');
            var ticketNum = $row.find('a[href*="tickets.php"]').first().text().trim() || ticketId;

            if (confirmMode === 'confirm') {
                var msgMap = {
                    start: QA.i18n.confirmStart, stop: QA.i18n.confirmStop,
                    partial: QA.i18n.confirmPartial, start2: QA.i18n.confirmStart2
                };
                var template = msgMap[action] || QA.i18n.confirmStart;
                var message = template.replace('%s', ticketNum);

                var $dlg = $('<div>').text(message);
                $dlg.dialog({
                    title: QA.i18n.confirm,
                    modal: true,
                    width: 350,
                    buttons: [
                        { text: QA.i18n.confirm, click: function() { $(this).dialog('close'); doExecute(); } },
                        { text: QA.i18n.cancel, click: function() { $(this).dialog('close'); } }
                    ],
                    close: function() { $(this).remove(); }
                });

            } else if (confirmMode === 'countdown') {
                QA.showCountdown($btn, ticketNum, action, countdownSec, doExecute);

            } else {
                doExecute();
            }

            return false;
        },

        renderIcon: function(iconClass) {
            if (!iconClass) return '';
            // Unicode icon shorthand: "emoji:✔" renders as a styled span
            if (iconClass.indexOf('emoji:') === 0) {
                var ch = iconClass.substring(6);
                return '<span class="qa-emoji-icon">' + QA.escapeHtml(ch) + '</span>';
            }
            if (iconClass.indexOf('+') > -1) {
                var parts = iconClass.split('+');
                return '<span class="icon-stack qa-icon-stack">' +
                       '<i class="' + QA.escapeHtml(parts[0]) + ' icon-stack-base"></i>' +
                       '<i class="' + QA.escapeHtml(parts[1]) + ' icon-light"></i>' +
                       '</span>';
            }
            return '<i class="' + QA.escapeHtml(iconClass) + '"></i>';
        },

        escapeHtml: function(str) {
            if (!str) return '';
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        },

        // ================================================================
        //  Countdown confirmation popup
        // ================================================================

        showCountdown: function($btn, ticketNum, action, seconds, onExecute) {
            $('.qa-countdown-popup').remove();
            $(document).off('click.qa-countdown');

            var remaining = seconds;
            var cancelled = false;
            var colorMap = { start: QA.START_COLOR, partial: QA.PARTIAL_COLOR, start2: QA.START2_COLOR, stop: QA.STOP_COLOR };
            var descMap = {
                start: QA.i18n.countdownStart, stop: QA.i18n.countdownStop,
                partial: QA.i18n.countdownPartial, start2: QA.i18n.countdownStart2
            };
            var color = colorMap[action] || QA.START_COLOR;
            var title = '#' + QA.escapeHtml(ticketNum);
            var desc = descMap[action] || '';

            // SVG circular ring (r=16, circumference=2*pi*16=100.53)
            var circumference = 100.53;
            var ringHTML =
                '<div class="qa-cd-ring">' +
                '<svg viewBox="0 0 40 40">' +
                '<circle class="qa-cd-ring-bg" cx="20" cy="20" r="16"/>' +
                '<circle class="qa-cd-ring-fill" cx="20" cy="20" r="16" ' +
                'stroke="' + color + '" style="stroke-dasharray:' + circumference +
                '; stroke-dashoffset:0; transition: stroke-dashoffset ' + seconds + 's linear"/>' +
                '</svg>' +
                '<span class="qa-cd-ring-number">' + remaining + '</span>' +
                '</div>';

            var $popup = $('<div class="qa-countdown-popup"></div>');
            var $ring = $(ringHTML);
            var $content = $('<div class="qa-cd-content"></div>');
            var $title = $('<div class="qa-cd-title"></div>').text(title);
            var $desc = $('<div class="qa-cd-desc"></div>').text(desc);
            var $cancelBtn = $('<button class="qa-cd-cancel"></button>').text(QA.i18n.cancel);

            $content.append($title).append($desc).append($cancelBtn);
            $popup.append($ring).append($content);

            // Position near the button
            var btnRect = $btn[0].getBoundingClientRect();
            var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            $popup.css({
                position: 'absolute',
                top: (btnRect.top + scrollTop - 10) + 'px',
                right: '50px',
                zIndex: 10000
            });

            $('body').append($popup);

            // Start ring animation
            requestAnimationFrame(function() {
                $popup.find('.qa-cd-ring-fill').css('stroke-dashoffset', circumference);
            });

            // Countdown interval
            var $number = $popup.find('.qa-cd-ring-number');
            var intervalId = setInterval(function() {
                remaining--;
                if (remaining <= 0) {
                    clearInterval(intervalId);
                    $(document).off('mousedown.qa-countdown');
                    if (!cancelled) {
                        cancelled = true;
                        $popup.remove();
                        onExecute();
                    }
                } else {
                    $number.text(remaining);
                }
            }, 1000);

            // Shared cleanup
            var cleanup = function() {
                cancelled = true;
                clearInterval(intervalId);
                $(document).off('mousedown.qa-countdown');
                $popup.addClass('qa-cd-cancelled');
                setTimeout(function() { $popup.remove(); }, 250);
            };

            // Cancel button
            $cancelBtn.on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                if (!cancelled) cleanup();
            });

            // Click outside to cancel — use mousedown (not click) with 300ms delay
            // to avoid capturing the original button click that spawned this popup
            setTimeout(function() {
                $(document).one('mousedown.qa-countdown', function(e) {
                    if (!cancelled && !$(e.target).closest('.qa-countdown-popup').length) {
                        cleanup();
                    }
                });
            }, 300);
        }
    };

    // Event bindings
    $(document).on('click.quick-buttons', '.qa-inline-btn', QA.handleInlineClick);
    $(document).on('click.quick-buttons', '.qa-bulk-start', function() { QA.handleBulkAction('start'); });
    $(document).on('click.quick-buttons', '.qa-bulk-partial', function() { QA.handleBulkAction('partial'); });
    $(document).on('click.quick-buttons', '.qa-bulk-start2', function() { QA.handleBulkAction('start2'); });
    $(document).on('click.quick-buttons', '.qa-bulk-stop', function() { QA.handleBulkAction('stop'); });

    // Expose for external integration
    window.QuickButtons = {
        bulkStart:   function() { QA.handleBulkAction('start'); },
        bulkPartial: function() { QA.handleBulkAction('partial'); },
        bulkStart2:  function() { QA.handleBulkAction('start2'); },
        bulkStop:    function() { QA.handleBulkAction('stop'); }
    };

    // ================================================================
    //  Dashboard tab injection on statistics page
    // ================================================================

    function initDashboardTab() {
        // Detect the statistics/dashboard page by looking for its tab structure
        // osTicket stats page has tabs like "Department", "Topics", "Agent"
        var $tabList = $('ul.clean.tabs').filter(function() {
            return $(this).find('a[href="#department"], a[href="#topic"]').length > 0;
        }).first();
        if (!$tabList.length) return;
        if ($tabList.find('a[href="#qa-workflow"]').length) return; // already added

        var iconStyle = 'display:inline-block;vertical-align:middle;position:static;top:auto;margin-right:4px;';

        // Add two tabs: Workflow + Agent Performance
        $tabList.append(
            '<li><a href="#qa-workflow"><i class="icon-bar-chart" style="' + iconStyle + '"></i>Workflow</a></li>' +
            '<li><a href="#qa-agent-perf"><i class="icon-user" style="' + iconStyle + '"></i>Agent Performance</a></li>'
        );

        // Create content containers as siblings of existing tab_content divs
        var containerStyle = 'display:none;padding:0;border:none;border-radius:0;background:none;min-height:0;';
        var $wfContainer = $('<div id="qa-workflow" class="tab_content" style="' + containerStyle + '"></div>');
        var $apContainer = $('<div id="qa-agent-perf" class="tab_content" style="' + containerStyle + '"></div>');
        var $lastTab = $tabList.siblings('.tab_content').last();
        if ($lastTab.length) {
            $lastTab.after($wfContainer).after($apContainer);
        } else {
            $tabList.parent().append($wfContainer).append($apContainer);
        }

        // Generic tab click handler for our custom tabs
        $tabList.on('click', 'a[href="#qa-workflow"], a[href="#qa-agent-perf"]', function(e) {
            e.preventDefault();
            var target = $(this).attr('href');
            $tabList.find('li').removeClass('active');
            $(this).parent().addClass('active');
            $tabList.siblings('.tab_content').hide();
            $(target).show();
            if (target === '#qa-workflow') loadIframeTab($wfContainer, 'dashboard-page');
            if (target === '#qa-agent-perf') loadIframeTab($apContainer, 'agent-perf-page');
        });
    }

    function loadIframeTab($container, page) {
        if ($container.data('loaded')) return;
        var url = 'ajax.php/quick-buttons/' + page;
        $container.html(
            '<iframe src="' + url + '" ' +
            'style="width:100%;border:none;min-height:800px;" ' +
            'onload="this.style.height=this.contentWindow.document.body.scrollHeight+40+\'px\'">' +
            '</iframe>'
        );
        $container.data('loaded', true);
    }

    $(function() {
        QA.init();
        initDashboardTab();
    });
    $(document).on('pjax:end', '#pjax-container', function() {
        QA.init();
        initDashboardTab();
    });

})(jQuery);
