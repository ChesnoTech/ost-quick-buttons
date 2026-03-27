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
                elapsed: 'elapsed', waiting: 'waiting' },
        perms: { canAssign: true, canTransfer: true, canRelease: true, canManage: true },
        executing: {},
        timerInterval: null,

        START_ICON: 'icon-play',
        START_COLOR: '#128DBE',
        PARTIAL_ICON: 'icon-check',
        PARTIAL_COLOR: '#e67e22',
        START2_ICON: 'icon-play',
        START2_COLOR: '#2980b9',
        STOP_ICON: 'icon-check+icon-share',
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
                    if (data.i18n) QA.i18n = $.extend(QA.i18n, data.i18n);
                    if (data.perms) QA.perms = data.perms;
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

                var iconMap = { start: QA.START_ICON, partial: QA.PARTIAL_ICON, start2: QA.START2_ICON, stop: QA.STOP_ICON };
                var colorMap = {
                    start: resolved.startColor || QA.START_COLOR,
                    partial: QA.PARTIAL_COLOR,
                    start2: QA.START2_COLOR,
                    stop: resolved.stopColor || QA.STOP_COLOR
                };
                var labelMap = {
                    start:   resolved.labels.start || QA.i18n.start,
                    partial: resolved.labels.partial || QA.i18n.partialReady,
                    start2:  resolved.labels.start2 || QA.i18n.startStep2,
                    stop:    resolved.labels.finish || resolved.labels.stop || QA.i18n.done
                };
                var icon = iconMap[resolved.action];
                var color = colorMap[resolved.action];
                var label = labelMap[resolved.action];

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
                if (info && info.updated) {
                    var timerClass = (resolved.action === 'stop' || resolved.action === 'partial')
                        ? 'qa-timer-badge qa-timer-working'
                        : 'qa-timer-badge qa-timer-waiting';
                    var $timer = $('<span class="' + timerClass + '" data-since="' + info.updated + '" data-server="' + (info.serverNow || '') + '"></span>');
                    $link.data('timer-el', $timer);
                    $link.attr('data-timer', '1');
                }

                if (isMobile) {
                    var $actions = $('<div class="qa-row-actions"></div>').append($link);
                    $row.addClass('has-qa-inline').prepend($actions);
                } else {
                    var $td = $('<td class="qa-actions-cell"></td>');
                    var $actions = $('<div class="qa-row-actions"></div>');
                    // Add timer badge above button if present
                    var $timerEl = $link.data('timer-el');
                    if ($timerEl) $actions.append($timerEl);
                    $actions.append($link);
                    $td.append($actions);
                    $row.addClass('has-qa-inline').append($td);
                }
            });

            if (hasAny && !isMobile) {
                var $headerRow = $('form#tickets thead tr').first();
                if ($headerRow.length)
                    $headerRow.append('<th class="qa-actions-header"></th>');
            }

            if (!isMobile) {
                $('.qa-inline-btn[title]').tooltip({ placement: 'left', container: 'body' });
            }
        },

        // ================================================================
        //  Live timer
        // ================================================================

        startTimers: function() {
            QA.updateTimers();
            QA.timerInterval = setInterval(QA.updateTimers, 1000);
        },

        updateTimers: function() {
            $('.qa-timer-badge').each(function() {
                var $el = $(this);
                var since = $el.data('since');
                var serverNow = $el.data('server');
                if (!since) return;

                // Calculate offset between server time and client time
                var serverMs = new Date(since.replace(' ', 'T') + 'Z').getTime();
                var nowMs = Date.now();
                if (serverNow) {
                    var serverNowMs = new Date(serverNow.replace(' ', 'T') + 'Z').getTime();
                    var offset = nowMs - serverNowMs;
                    nowMs = nowMs - offset; // adjust to server time
                }

                var diffSec = Math.max(0, Math.floor((nowMs - serverMs) / 1000));
                var formatted = QA.formatDuration(diffSec);
                $el.text(formatted);
                // Update tooltip — "waiting" for Start, "elapsed" for Done
                var isWaiting = $el.hasClass('qa-timer-waiting');
                var suffix = isWaiting ? (QA.i18n.waiting || 'waiting') : (QA.i18n.elapsed || 'elapsed');
                $el.siblings('.qa-inline-btn').attr('title', formatted + ' ' + suffix);
            });
        },

        formatDuration: function(totalSec) {
            var h = Math.floor(totalSec / 3600);
            var m = Math.floor((totalSec % 3600) / 60);
            var s = totalSec % 60;
            if (h > 0)
                return h + 'h ' + (m < 10 ? '0' : '') + m + 'm';
            if (m > 0)
                return m + 'm ' + (s < 10 ? '0' : '') + s + 's';
            return s + 's';
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
                error: function(xhr) {
                    var msg = '';
                    try { msg = JSON.parse(xhr.responseText).error || xhr.responseText; }
                    catch (ex) { msg = xhr.responseText || 'Unknown error'; }
                    $.sysAlert(QA.i18n.error, msg);
                }
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
                error: function(xhr) {
                    var msg = '';
                    try { msg = JSON.parse(xhr.responseText).error || xhr.responseText; }
                    catch (ex) { msg = xhr.responseText || 'Unknown error'; }
                    $.sysAlert(QA.i18n.error, msg);
                }
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
                        var msg = '';
                        try { msg = JSON.parse(xhr.responseText).error || xhr.responseText; }
                        catch (ex) { msg = xhr.responseText || 'Unknown error'; }
                        $.sysAlert(QA.i18n.error, msg);
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
                    if (!cancelled) {
                        $popup.remove();
                        onExecute();
                    }
                } else {
                    $number.text(remaining);
                }
            }, 1000);

            // Cancel button
            $cancelBtn.on('click', function(e) {
                e.stopPropagation();
                cancelled = true;
                clearInterval(intervalId);
                $popup.addClass('qa-cd-cancelled');
                setTimeout(function() { $popup.remove(); }, 250);
            });

            // Click outside to cancel
            setTimeout(function() {
                $(document).one('click.qa-countdown', function(e) {
                    if (!$(e.target).closest('.qa-countdown-popup').length && !cancelled) {
                        cancelled = true;
                        clearInterval(intervalId);
                        $popup.remove();
                    }
                });
            }, 100);
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

    $(function() { QA.init(); });
    $(document).on('pjax:end', '#pjax-container', function() { QA.init(); });

})(jQuery);
