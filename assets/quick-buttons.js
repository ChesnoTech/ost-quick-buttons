/**
 * Quick Buttons Plugin - Frontend v2.3
 *
 * @author  ChesnoTech
 * @version 2.3.0
 */
(function($) {
    'use strict';

    var QA = {
        widgets: null,
        tickets: null,
        i18n: { start: 'Start', done: 'Done', error: 'Error', confirm: 'Confirm',
                cancel: 'Cancel', confirmStart: 'Start working on ticket #%s?',
                confirmStop: 'Complete and hand off ticket #%s?',
                countdownStart: 'Claim ticket and change status to working',
                countdownStop: 'Change status, release agent and transfer',
                executingIn: 'Executing in %ss...',
                undo: 'Undo', bulkStart: 'Start Selected', bulkStop: 'Complete Selected',
                elapsed: 'elapsed', waiting: 'waiting' },
        perms: { canAssign: true, canTransfer: true, canRelease: true, canManage: true },
        executing: {},
        timerInterval: null,

        START_ICON: 'icon-play',
        START_COLOR: '#128DBE',
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
                if (deptCfg.start_trigger && ticketStatus === String(deptCfg.start_trigger))
                    action = 'start';
                else if (deptCfg.start_target && ticketStatus === String(deptCfg.start_target))
                    action = 'stop';

                if (!action) continue;
                if (action === 'start' && !QA.perms.canAssign) continue;
                if (action === 'stop' && !QA.perms.canManage) continue;

                return {
                    action: action, widgetId: w.id, deptId: ticketDept,
                    startLabel: w.startLabel, stopLabel: w.stopLabel,
                    startColor: w.startColor, stopColor: w.stopColor,
                    confirm: w.confirm,
                    confirmMode: w.confirmMode || (w.confirm ? 'confirm' : 'none'),
                    countdownSeconds: w.countdownSeconds || 5
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

                var icon = resolved.action === 'start' ? QA.START_ICON : QA.STOP_ICON;
                var color = resolved.action === 'start'
                    ? (resolved.startColor || QA.START_COLOR)
                    : (resolved.stopColor || QA.STOP_COLOR);
                var label = resolved.action === 'start'
                    ? (resolved.startLabel || QA.i18n.start)
                    : (resolved.stopLabel || QA.i18n.done);

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
                    var timerClass = resolved.action === 'stop'
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
            var confirmMode = $btn.data('confirm-mode') || 'none';
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
                var template = action === 'start' ? QA.i18n.confirmStart : QA.i18n.confirmStop;
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
            // Remove any existing countdown popup
            $('.qa-countdown-popup').remove();

            var remaining = seconds;
            var cancelled = false;
            var icon = action === 'start' ? QA.START_ICON : QA.STOP_ICON;
            var color = action === 'start' ? QA.START_COLOR : QA.STOP_COLOR;
            var title = (action === 'start' ? '▶ ' + QA.i18n.start : '✓ ' + QA.i18n.done) +
                        ' — Ticket #' + QA.escapeHtml(ticketNum);
            var desc = action === 'start' ? QA.i18n.countdownStart : QA.i18n.countdownStop;

            var $popup = $('<div class="qa-countdown-popup"></div>');
            var $title = $('<div class="qa-cd-title"></div>').text(title);
            var $desc = $('<div class="qa-cd-desc"></div>').text(desc);
            var $timer = $('<div class="qa-cd-timer"></div>');
            var $timerText = $('<span class="qa-cd-timer-text"></span>');
            var $cancelBtn = $('<button class="qa-cd-cancel"></button>').text(QA.i18n.cancel);
            var $bar = $('<div class="qa-cd-bar"><div class="qa-cd-bar-fill"></div></div>');

            $timer.append($timerText).append($cancelBtn);
            $popup.append($title).append($desc).append($timer).append($bar);
            $popup.css('border-left-color', color);

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

            // Animate progress bar
            var $fill = $popup.find('.qa-cd-bar-fill');
            $fill.css({ width: '100%', backgroundColor: color });

            // Update timer text
            var updateTimer = function() {
                $timerText.text(QA.i18n.executingIn.replace('%s', remaining));
            };
            updateTimer();

            // Start CSS transition for progress bar
            requestAnimationFrame(function() {
                $fill.css({
                    transition: 'width ' + seconds + 's linear',
                    width: '0%'
                });
            });

            // Countdown interval
            var intervalId = setInterval(function() {
                remaining--;
                if (remaining <= 0) {
                    clearInterval(intervalId);
                    if (!cancelled) {
                        $popup.remove();
                        onExecute();
                    }
                } else {
                    updateTimer();
                }
            }, 1000);

            // Cancel button
            $cancelBtn.on('click', function() {
                cancelled = true;
                clearInterval(intervalId);
                $popup.addClass('qa-cd-cancelled');
                setTimeout(function() { $popup.remove(); }, 300);
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
    $(document).on('click.quick-buttons', '.qa-bulk-stop', function() { QA.handleBulkAction('stop'); });

    // Expose for external integration
    window.QuickButtons = {
        bulkStart: function() { QA.handleBulkAction('start'); },
        bulkStop:  function() { QA.handleBulkAction('stop'); }
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

        // Add our tab
        $tabList.append(
            '<li><a href="#qa-workflow"><i class="icon-bar-chart"></i> Workflow</a></li>'
        );

        // Create content container as sibling of existing tab_content divs
        var $container = $('<div id="qa-workflow" class="tab_content" style="display:none;"></div>');
        $tabList.siblings('.tab_content').last().after($container);
        // Fallback: if no siblings found, try parent
        if (!$container.parent().length) {
            $tabList.parent().append($container);
        }

        // Handle tab click
        $tabList.on('click', 'a[href="#qa-workflow"]', function(e) {
            e.preventDefault();
            // Deactivate all tabs
            $tabList.find('li').removeClass('active');
            $(this).parent().addClass('active');
            // Hide all tab content, show ours
            $tabList.siblings('.tab_content').hide();
            $container.show();
            loadWorkflowDashboard($container);
        });
    }

    function loadWorkflowDashboard($container) {
        if ($container.data('loaded')) return;
        $container.html(
            '<div style="text-align:center;padding:40px;color:#888;">' +
            '<div style="font-size:15px;">Loading Workflow Dashboard...</div>' +
            '</div>'
        );

        // Load via iframe for full standalone dashboard experience
        var url = 'ajax.php/quick-buttons/dashboard-page';
        $container.html(
            '<iframe src="' + url + '" ' +
            'style="width:100%;border:none;min-height:800px;border-radius:8px;" ' +
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
