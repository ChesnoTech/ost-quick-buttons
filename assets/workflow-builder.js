/**
 * Workflow Builder — Admin UI v3.0
 * Card-based department configuration with status flow diagrams
 * All user-facing strings sourced from D.i18n (server-side __() translations)
 */
(function() {
    'use strict';

    var D = WB_DATA;
    var T = D.i18n || {};  // Translations
    var existing = (D.config && D.config.departments) ? D.config.departments : {};
    var dirty = false;

    // ================================================================
    //  Helpers
    // ================================================================

    function esc(str) {
        if (!str) return '';
        var d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    function t(key, replacements) {
        var str = T[key] || key;
        if (replacements) {
            for (var k in replacements) {
                str = str.replace('%' + k, replacements[k]);
            }
        }
        return str;
    }

    function statusOptions(selected) {
        var html = '<option value="">' + esc(t('selectStatus')) + '</option>';
        D.statuses.forEach(function(s) {
            var sel = s.id === selected ? ' selected' : '';
            html += '<option value="' + esc(s.id) + '"' + sel + '>' +
                esc(s.name) + ' (' + esc(s.state) + ')</option>';
        });
        return html;
    }

    function deptOptions(selected) {
        var html = '<option value="">' + esc(t('selectNone')) + '</option>';
        D.departments.forEach(function(d) {
            var sel = d.id === selected ? ' selected' : '';
            html += '<option value="' + esc(d.id) + '"' + sel + '>' + esc(d.name) + '</option>';
        });
        return html;
    }

    function toast(msg, type) {
        var el = document.createElement('div');
        el.className = 'wb-toast' + (type ? ' wb-toast-' + type : '');
        el.textContent = msg;
        document.body.appendChild(el);
        setTimeout(function() { el.remove(); }, 3000);
    }

    function markDirty() {
        dirty = true;
        var info = document.querySelector('.wb-footer-info');
        if (info) info.textContent = t('unsavedChanges');
    }

    // ================================================================
    //  Render
    // ================================================================

    function render() {
        var app = document.getElementById('wb-app');
        app.innerHTML = '';

        // Header
        var header = document.createElement('div');
        header.className = 'wb-header';
        header.innerHTML =
            '<div class="wb-header-left">' +
            '<h1>' + esc(t('workflowBuilder')) + '</h1>' +
            '<div class="wb-subtitle">' + esc(D.instanceName) + ' \u2014 ' + esc(D.topicName) + '</div>' +
            '</div>' +
            '<div class="wb-header-actions">' +
            '<button class="wb-btn wb-btn-cancel" id="wb-back">\u2190 ' + esc(t('back')) + '</button>' +
            '</div>';
        app.appendChild(header);

        // Toolbar
        var toolbar = document.createElement('div');
        toolbar.className = 'wb-toolbar';
        var enabledCount = 0;
        D.departments.forEach(function(d) {
            if (existing[d.id] && existing[d.id].enabled) enabledCount++;
        });
        var badgeText = (T.enabledCount || '%d / %d enabled')
            .replace('%d', enabledCount)
            .replace('%d', D.departments.length);
        toolbar.innerHTML =
            '<input type="text" class="wb-search" id="wb-search" placeholder="' + esc(t('searchDepts')) + '">' +
            '<button class="wb-toolbar-btn" id="wb-enable-all">' + esc(t('enableAll')) + '</button>' +
            '<button class="wb-toolbar-btn" id="wb-disable-all">' + esc(t('disableAll')) + '</button>' +
            '<span class="wb-badge">' + esc(badgeText) + '</span>';
        app.appendChild(toolbar);

        // Cards
        var cards = document.createElement('div');
        cards.className = 'wb-cards';
        cards.id = 'wb-cards';

        D.departments.forEach(function(dept) {
            var cfg = existing[dept.id] || {};
            var enabled = !!cfg.enabled;

            var card = document.createElement('div');
            card.className = 'wb-card' + (enabled ? ' wb-card-enabled' : '');
            card.dataset.deptId = dept.id;
            card.dataset.deptName = dept.name.toLowerCase();

            card.innerHTML =
                '<div class="wb-card-header">' +
                '<div class="wb-card-dot"></div>' +
                '<div class="wb-card-name">' + esc(dept.name) + '</div>' +
                '<label class="wb-toggle">' +
                '<input type="checkbox" class="wb-enabled-cb"' + (enabled ? ' checked' : '') + '>' +
                '<div class="wb-toggle-track"></div>' +
                '</label>' +
                '</div>' +
                '<div class="wb-card-body">' +
                // Variant selector
                '<div class="wb-variant-row">' +
                '<span class="wb-variant-label">' + esc(t('variant') || 'Variant') + ':</span>' +
                '<select class="wb-sel-variant">' +
                '<option value="single"' + ((cfg.variant || 'single') === 'single' ? ' selected' : '') + '>' + esc(t('variantSingle') || 'Single Step') + '</option>' +
                '<option value="twostep"' + (cfg.variant === 'twostep' ? ' selected' : '') + '>' + esc(t('variantTwostep') || 'Two Step') + '</option>' +
                '</select>' +
                '</div>' +
                // Step 1 flow (always shown)
                '<div class="wb-flow-section">' +
                '<div class="wb-flow-step-label">' + esc(t('step1') || 'Step 1') + '</div>' +
                '<div class="wb-flow">' +
                '<div class="wb-flow-pill wb-pill-trigger">' +
                '<span class="wb-flow-label">' + esc(t('trigger')) + '</span>' +
                '<select class="wb-sel-trigger">' + statusOptions(cfg.start_trigger_status || '') + '</select>' +
                '</div>' +
                '<div class="wb-flow-arrow wb-flow-arrow-start">\u25B6</div>' +
                '<div class="wb-flow-pill wb-pill-working">' +
                '<span class="wb-flow-label">' + esc(t('working')) + '</span>' +
                '<select class="wb-sel-working">' + statusOptions(cfg.start_target_status || '') + '</select>' +
                '</div>' +
                // Single-step: show done pill inline
                '<div class="wb-flow-arrow wb-flow-arrow-stop wb-single-only">\u2714</div>' +
                '<div class="wb-flow-pill wb-pill-done wb-single-only">' +
                '<span class="wb-flow-label">' + esc(t('done')) + '</span>' +
                '<select class="wb-sel-done">' + statusOptions(cfg.stop_target_status || '') + '</select>' +
                '</div>' +
                // Two-step: show partial arrow
                '<div class="wb-flow-arrow wb-flow-arrow-partial wb-twostep-only">\u23E9</div>' +
                '</div>' +
                // Labels row for step 1
                '<div class="wb-labels-row">' +
                '<input type="text" class="wb-label-input wb-lbl-start" maxlength="12" placeholder="' + esc(t('start') || 'Start') + '" value="' + esc(cfg.start_label || '') + '">' +
                '<input type="text" class="wb-label-input wb-lbl-partial wb-twostep-only" maxlength="12" placeholder="' + esc(t('partialReady') || 'Next') + '" value="' + esc(cfg.partial_label || '') + '">' +
                '<input type="text" class="wb-label-input wb-lbl-stop wb-single-only" maxlength="12" placeholder="' + esc(t('done') || 'Done') + '" value="' + esc(cfg.stop_label || '') + '">' +
                '</div>' +
                '</div>' +
                // Step 2 flow (only for two-step)
                '<div class="wb-flow-section wb-twostep-only">' +
                '<div class="wb-flow-step-label">' + esc(t('step2') || 'Step 2') + '</div>' +
                '<div class="wb-flow">' +
                '<div class="wb-flow-pill wb-pill-step2-trigger">' +
                '<span class="wb-flow-label">' + esc(t('step2Trigger') || 'Step 2 Trigger') + '</span>' +
                '<select class="wb-sel-step2-trigger">' + statusOptions(cfg.step2_trigger_status || '') + '</select>' +
                '</div>' +
                '<div class="wb-flow-arrow wb-flow-arrow-start2">\u25B6</div>' +
                '<div class="wb-flow-pill wb-pill-step2-working">' +
                '<span class="wb-flow-label">' + esc(t('step2Working') || 'Step 2 Working') + '</span>' +
                '<select class="wb-sel-step2-working">' + statusOptions(cfg.step2_target_status || '') + '</select>' +
                '</div>' +
                '<div class="wb-flow-arrow wb-flow-arrow-stop">\u2714</div>' +
                '<div class="wb-flow-pill wb-pill-final-done">' +
                '<span class="wb-flow-label">' + esc(t('finalDone') || 'Final Done') + '</span>' +
                '<select class="wb-sel-final-done">' + statusOptions(cfg.step2_stop_target_status || '') + '</select>' +
                '</div>' +
                '</div>' +
                // Labels row for step 2
                '<div class="wb-labels-row">' +
                '<input type="text" class="wb-label-input wb-lbl-start2" maxlength="12" placeholder="' + esc(t('startStep2') || 'Start') + '" value="' + esc(cfg.start2_label || '') + '">' +
                '<input type="text" class="wb-label-input wb-lbl-finish" maxlength="12" placeholder="' + esc(t('done') || 'Done') + '" value="' + esc(cfg.finish_label || '') + '">' +
                '</div>' +
                '</div>' +
                '<div class="wb-validation"></div>' +
                '<div class="wb-transfer">' +
                '<span class="wb-transfer-label">' + esc(t('transferTo')) + '</span>' +
                '<select class="wb-sel-transfer">' + deptOptions(cfg.stop_transfer_dept || '') + '</select>' +
                '<label class="wb-transfer-check">' +
                '<input type="checkbox" class="wb-clear-team"' + (cfg.clear_team ? ' checked' : '') + '>' +
                ' ' + esc(t('clearTeam')) +
                '</label>' +
                '</div>' +
                '<div class="wb-card-actions">' +
                '<button class="wb-card-action-btn wb-clone-btn" data-dept-id="' + dept.id + '">' + esc(t('copyTo')) + '</button>' +
                '<select class="wb-card-action-btn wb-template-sel" data-dept-id="' + dept.id + '">' +
                '<option value="">' + esc(t('applyTemplate')) + '</option>' +
                '<option value="single">' + esc(t('tplSingleStep')) + '</option>' +
                '<option value="twostep">' + esc(t('variantTwostep') || 'Two Step') + '</option>' +
                '</select>' +
                '</div>' +
                '</div>';

            cards.appendChild(card);
        });

        app.appendChild(cards);

        // Sticky footer
        var footer = document.createElement('div');
        footer.className = 'wb-footer';
        footer.innerHTML =
            '<div class="wb-footer-info">' + esc(t('noUnsaved')) + '</div>' +
            '<div class="wb-footer-actions">' +
            '<button class="wb-btn wb-btn-cancel" id="wb-cancel">' + esc(t('cancel')) + '</button>' +
            '<button class="wb-btn wb-btn-save" id="wb-save">' + esc(t('saveChanges')) + '</button>' +
            '</div>';
        app.appendChild(footer);

        bindEvents();

        // Initialize variant visibility on all cards
        document.querySelectorAll('.wb-card').forEach(function(card) {
            updateVariantVisibility(card);
        });
    }

    // ================================================================
    //  Events
    // ================================================================

    function bindEvents() {
        document.getElementById('wb-back').addEventListener('click', function() {
            if (dirty && !confirm(t('discardChanges'))) return;
            window.location.href = D.backUrl;
        });

        document.getElementById('wb-cancel').addEventListener('click', function() {
            if (dirty && !confirm(t('discardChanges'))) return;
            window.location.href = D.backUrl;
        });

        document.getElementById('wb-save').addEventListener('click', saveConfig);

        document.getElementById('wb-search').addEventListener('input', function() {
            var q = this.value.toLowerCase().trim();
            document.querySelectorAll('.wb-card').forEach(function(card) {
                var name = card.dataset.deptName;
                card.classList.toggle('wb-card-hidden', q && name.indexOf(q) === -1);
            });
        });

        document.getElementById('wb-enable-all').addEventListener('click', function() {
            document.querySelectorAll('.wb-enabled-cb').forEach(function(cb) {
                cb.checked = true;
                updateCard(cb.closest('.wb-card'));
            });
            serializeAll();
            markDirty();
            updateBadge();
        });

        document.getElementById('wb-disable-all').addEventListener('click', function() {
            document.querySelectorAll('.wb-enabled-cb').forEach(function(cb) {
                cb.checked = false;
                updateCard(cb.closest('.wb-card'));
            });
            serializeAll();
            markDirty();
            updateBadge();
        });

        document.getElementById('wb-cards').addEventListener('change', function(e) {
            var card = e.target.closest('.wb-card');
            if (!card) return;

            if (e.target.classList.contains('wb-enabled-cb')) {
                updateCard(card);
                updateBadge();
            }

            if (e.target.classList.contains('wb-sel-variant')) {
                updateVariantVisibility(card);
            }

            if (e.target.classList.contains('wb-template-sel')) {
                var tpl = e.target.value;
                if (tpl === 'twostep') {
                    card.querySelector('.wb-sel-variant').value = 'twostep';
                    updateVariantVisibility(card);
                } else if (tpl === 'single') {
                    card.querySelector('.wb-sel-variant').value = 'single';
                    updateVariantVisibility(card);
                }
                applyTemplate(card, tpl);
                e.target.value = '';
            }

            validateCard(card);
            serializeAll();
            markDirty();
        });

        document.getElementById('wb-cards').addEventListener('click', function(e) {
            if (e.target.classList.contains('wb-clone-btn')) {
                showCloneDialog(e.target.dataset.deptId);
            }
        });

        document.getElementById('wb-cards').addEventListener('click', function(e) {
            if (e.target.closest('.wb-toggle')) return;
            var header = e.target.closest('.wb-card-header');
            if (header) {
                var cb = header.querySelector('.wb-enabled-cb');
                if (!cb.checked) {
                    cb.checked = true;
                    updateCard(cb.closest('.wb-card'));
                    validateCard(cb.closest('.wb-card'));
                    serializeAll();
                    markDirty();
                    updateBadge();
                }
            }
        });
    }

    function updateCard(card) {
        var enabled = card.querySelector('.wb-enabled-cb').checked;
        card.classList.toggle('wb-card-enabled', enabled);
        updateVariantVisibility(card);
    }

    function updateVariantVisibility(card) {
        var variant = (card.querySelector('.wb-sel-variant') || {}).value || 'single';
        var isTwostep = variant === 'twostep';
        card.querySelectorAll('.wb-single-only').forEach(function(el) {
            el.style.display = isTwostep ? 'none' : '';
        });
        card.querySelectorAll('.wb-twostep-only').forEach(function(el) {
            el.style.display = isTwostep ? '' : 'none';
        });
    }

    function updateBadge() {
        var count = document.querySelectorAll('.wb-enabled-cb:checked').length;
        var badge = document.querySelector('.wb-badge');
        if (badge) {
            var text = (T.enabledCount || '%d / %d enabled')
                .replace('%d', count)
                .replace('%d', D.departments.length);
            badge.textContent = text;
        }
    }

    // ================================================================
    //  Validation
    // ================================================================

    function validateCard(card) {
        var warnEl = card.querySelector('.wb-validation');
        if (!warnEl) return;
        warnEl.innerHTML = '';

        if (!card.classList.contains('wb-card-enabled')) return;

        var trigger = card.querySelector('.wb-sel-trigger').value;
        var working = card.querySelector('.wb-sel-working').value;
        var done = card.querySelector('.wb-sel-done').value;

        var warnings = [];

        if (!trigger) warnings.push(t('triggerRequired'));
        if (!working) warnings.push(t('workingRequired'));
        if (!done) warnings.push(t('doneRequired'));

        if (trigger && trigger === working)
            warnings.push(t('triggerEqualsWorking'));
        if (done && done === trigger)
            warnings.push(t('doneEqualsTrigger'));
        if (working && working === done)
            warnings.push(t('workingEqualsDone'));

        card.querySelectorAll('.wb-flow-pill').forEach(function(pill) {
            pill.classList.remove('wb-invalid');
        });

        if (!trigger) card.querySelector('.wb-pill-trigger').classList.add('wb-invalid');
        if (!working) card.querySelector('.wb-pill-working').classList.add('wb-invalid');
        if (!done) card.querySelector('.wb-pill-done').classList.add('wb-invalid');

        warnings.forEach(function(w) {
            warnEl.innerHTML += '<div class="wb-warning">' + esc(w) + '</div>';
        });
    }

    // ================================================================
    //  Templates
    // ================================================================

    function applyTemplate(card, template) {
        if (!template) return;

        var transfer = card.querySelector('.wb-sel-transfer');

        switch (template) {
            case 'single':
                break;
            case 'step1':
                transfer.value = '';
                break;
            case 'step2':
                break;
        }

        toast(t('templateApplied'), 'success');
    }

    // ================================================================
    //  Clone
    // ================================================================

    function showCloneDialog(sourceDeptId) {
        var sourceCard = document.querySelector('.wb-card[data-dept-id="' + sourceDeptId + '"]');
        if (!sourceCard) return;

        var target = prompt(t('copyPrompt'));
        if (!target) return;

        var targetId = null;
        D.departments.forEach(function(d) {
            if (d.name.toLowerCase().indexOf(target.toLowerCase()) > -1)
                targetId = d.id;
        });

        if (!targetId) {
            var msg = (T.deptNotFound || 'Department not found: %s').replace('%s', target);
            toast(msg, 'error');
            return;
        }

        var targetCard = document.querySelector('.wb-card[data-dept-id="' + targetId + '"]');
        if (!targetCard) return;

        targetCard.querySelector('.wb-enabled-cb').checked = true;
        targetCard.querySelector('.wb-sel-trigger').value = sourceCard.querySelector('.wb-sel-trigger').value;
        targetCard.querySelector('.wb-sel-working').value = sourceCard.querySelector('.wb-sel-working').value;
        targetCard.querySelector('.wb-sel-done').value = sourceCard.querySelector('.wb-sel-done').value;
        targetCard.querySelector('.wb-sel-transfer').value = sourceCard.querySelector('.wb-sel-transfer').value;
        targetCard.querySelector('.wb-clear-team').checked = sourceCard.querySelector('.wb-clear-team').checked;

        updateCard(targetCard);
        validateCard(targetCard);
        serializeAll();
        markDirty();
        updateBadge();

        var deptName = D.departments.find(function(d) { return d.id === targetId; }).name;
        var msg = (T.copiedTo || 'Copied to %s').replace('%s', deptName);
        toast(msg, 'success');
    }

    // ================================================================
    //  Serialize & Save
    // ================================================================

    function serializeAll() {
        existing = {};
        document.querySelectorAll('.wb-card').forEach(function(card) {
            var deptId = card.dataset.deptId;
            var enabled = card.querySelector('.wb-enabled-cb').checked;
            var variant = (card.querySelector('.wb-sel-variant') || {}).value || 'single';

            var cfg = {
                enabled: enabled,
                variant: variant,
                start_trigger_status: card.querySelector('.wb-sel-trigger').value,
                start_target_status: card.querySelector('.wb-sel-working').value,
                stop_target_status: (card.querySelector('.wb-sel-done') || {}).value || '',
                stop_transfer_dept: card.querySelector('.wb-sel-transfer').value,
                clear_team: card.querySelector('.wb-clear-team').checked,
                // Per-department labels
                start_label: (card.querySelector('.wb-lbl-start') || {}).value || '',
                stop_label: (card.querySelector('.wb-lbl-stop') || {}).value || ''
            };

            if (variant === 'twostep') {
                cfg.step2_trigger_status = (card.querySelector('.wb-sel-step2-trigger') || {}).value || '';
                cfg.step2_target_status = (card.querySelector('.wb-sel-step2-working') || {}).value || '';
                cfg.step2_stop_target_status = (card.querySelector('.wb-sel-final-done') || {}).value || '';
                cfg.step2_clear_team = (card.querySelector('.wb-step2-clear-team') || {}).checked || false;
                cfg.partial_label = (card.querySelector('.wb-lbl-partial') || {}).value || '';
                cfg.start2_label = (card.querySelector('.wb-lbl-start2') || {}).value || '';
                cfg.finish_label = (card.querySelector('.wb-lbl-finish') || {}).value || '';
            }

            existing[deptId] = cfg;
        });
    }

    function saveConfig() {
        serializeAll();

        var json = JSON.stringify({ departments: existing });
        var saveBtn = document.getElementById('wb-save');
        saveBtn.disabled = true;
        saveBtn.textContent = t('saving');

        var xhr = new XMLHttpRequest();
        xhr.open('POST', D.saveUrl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.setRequestHeader('X-CSRFToken', D.csrfToken);

        xhr.onload = function() {
            saveBtn.disabled = false;
            saveBtn.textContent = t('saveChanges');

            try {
                var resp = JSON.parse(xhr.responseText);
                if (resp.success) {
                    dirty = false;
                    var info = document.querySelector('.wb-footer-info');
                    if (info) info.textContent = t('allSaved');
                    toast(resp.message || t('saved'), 'success');
                } else {
                    toast(resp.error || t('saveFailed'), 'error');
                }
            } catch (e) {
                toast(t('saveFailed') + ': ' + xhr.statusText, 'error');
            }
        };

        xhr.onerror = function() {
            saveBtn.disabled = false;
            saveBtn.textContent = t('saveChanges');
            toast(t('networkError'), 'error');
        };

        xhr.send('widget_config=' + encodeURIComponent(json));
    }

    // ================================================================
    //  Init
    // ================================================================

    render();

    window.addEventListener('beforeunload', function(e) {
        if (dirty) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

})();
