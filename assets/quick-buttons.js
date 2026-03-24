/**
 * Quick Buttons Plugin - Frontend v2.0
 *
 * Widget-based Start/Stop buttons on each ticket row in the queue view.
 * Buttons are filtered by: agent dept access (server), topic + status (client).
 *
 * Start (Play icon, blue): visible when ticket status matches trigger status.
 * Stop (Done+Share icon, green): visible when ticket status matches working status.
 */
(function($) {
    'use strict';

    var QA = {
        widgets: null,
        tickets: null,

        // Fixed button definitions
        START_ICON: 'icon-play',
        START_COLOR: '#128DBE',
        STOP_ICON: 'icon-check+icon-share',
        STOP_COLOR: '#27ae60',

        init: function() {
            if (!$('form#tickets').length)
                return;

            // Cleanup previous render
            $('.qa-row-actions').remove();
            $('td.qa-actions-cell').remove();
            $('th.qa-actions-header').remove();
            $('tr.has-qa-inline').removeClass('has-qa-inline');

            // Collect ticket IDs
            var tids = [];
            $('form#tickets tbody tr input.ckb').each(function() {
                var tid = $(this).val();
                if (tid) tids.push(tid);
            });

            if (!tids.length) return;

            // Fetch widgets + ticket metadata
            $.ajax({
                url: 'ajax.php/quick-buttons/widgets',
                type: 'POST',
                data: { tids: tids },
                dataType: 'json',
                cache: false,
                success: function(data) {
                    QA.widgets = data.widgets || [];
                    QA.tickets = data.tickets || {};
                    if (QA.widgets.length) {
                        QA.renderButtons();
                    }
                },
                error: function() {}
            });
        },

        /**
         * Determine which button (start/stop/none) to show for a ticket.
         * Returns { action, widgetId, deptId } or null.
         */
        resolveButton: function(ticketId) {
            var info = QA.tickets[ticketId];
            if (!info || !info.topic || !info.dept || !info.status)
                return null;

            var ticketTopic = String(info.topic);
            var ticketDept = String(info.dept);
            var ticketStatus = String(info.status);

            for (var i = 0; i < QA.widgets.length; i++) {
                var w = QA.widgets[i];

                // Topic must match
                if (String(w.topic) !== ticketTopic)
                    continue;

                // Department must be configured in this widget
                var deptCfg = w.depts[ticketDept];
                if (!deptCfg)
                    continue;

                // Check Start: ticket status == start_trigger
                if (deptCfg.start_trigger && ticketStatus === String(deptCfg.start_trigger)) {
                    return {
                        action: 'start',
                        widgetId: w.id,
                        deptId: ticketDept
                    };
                }

                // Check Stop: ticket status == start_target (which is the stop trigger)
                if (deptCfg.start_target && ticketStatus === String(deptCfg.start_target)) {
                    return {
                        action: 'stop',
                        widgetId: w.id,
                        deptId: ticketDept
                    };
                }
            }

            return null;
        },

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
                var color = resolved.action === 'start' ? QA.START_COLOR : QA.STOP_COLOR;
                var label = resolved.action === 'start' ? 'Start' : 'Done';

                var $link = $('<a href="#"></a>')
                    .addClass('qa-inline-btn')
                    .attr({
                        'data-widget-id': resolved.widgetId,
                        'data-action': resolved.action,
                        'data-dept-id': resolved.deptId,
                        'data-ticket-id': ticketId,
                        'title': label
                    })
                    .css('background-color', color)
                    .html(QA.renderIcon(icon));

                if (isMobile) {
                    var $actions = $('<div class="qa-row-actions"></div>').append($link);
                    $row.addClass('has-qa-inline').prepend($actions);
                } else {
                    var $td = $('<td class="qa-actions-cell"></td>');
                    var $actions = $('<div class="qa-row-actions"></div>').append($link);
                    $td.append($actions);
                    $row.addClass('has-qa-inline').append($td);
                }
            });

            // Desktop header
            if (hasAny && !isMobile) {
                var $headerRow = $('form#tickets thead tr').first();
                if ($headerRow.length) {
                    $headerRow.append('<th class="qa-actions-header"></th>');
                }
            }

            // Tooltips
            if (!isMobile) {
                $('.qa-inline-btn[title]').tooltip({
                    placement: 'left',
                    container: 'body'
                });
            }
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

        handleInlineClick: function(e) {
            e.preventDefault();
            e.stopPropagation();

            var $btn = $(this);
            if ($btn.hasClass('qa-loading'))
                return false;

            var widgetId = $btn.data('widget-id');
            var action = $btn.data('action');
            var deptId = $btn.data('dept-id');
            var ticketId = $btn.data('ticket-id');

            if (!widgetId || !action || !ticketId)
                return false;

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
                success: function() {
                    $.pjax.reload('#pjax-container');
                },
                error: function(xhr) {
                    var msg = '';
                    try {
                        var resp = JSON.parse(xhr.responseText);
                        msg = resp.error || resp.message || xhr.responseText;
                    } catch (ex) {
                        msg = xhr.responseText || 'Unknown error';
                    }
                    $.sysAlert(__('Error'), msg);
                    $btn.removeClass('qa-loading');
                    $btn.html(originalHtml);
                }
            });

            return false;
        },

        escapeHtml: function(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    };

    // Event bindings
    $(document).on('click.quick-buttons', '.qa-inline-btn', QA.handleInlineClick);

    $(function() {
        QA.init();
    });

    $(document).on('pjax:end', '#pjax-container', function() {
        QA.init();
    });

})(jQuery);
