/**
 * Workflow Builder — Admin UI v5.0
 * Card-based department configuration with dynamic N-step workflows
 * All user-facing strings sourced from D.i18n (server-side __() translations)
 */
(function() {
    'use strict';

    var D = WB_DATA;
    var T = D.i18n || {};  // Translations
    var existing = (D.config && D.config.departments) ? D.config.departments : {};
    var dirty = false;
    var MAX_STEPS = 10;

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

    // Pre-build options HTML once (without selected attribute)
    var _statusOptionsCache = null;
    var _deptOptionsCache = null;

    function buildStatusOptionsCache() {
        if (_statusOptionsCache) return;
        _statusOptionsCache = '';
        D.statuses.forEach(function(s) {
            _statusOptionsCache += '<option value="' + esc(s.id) + '">' +
                esc(s.name) + ' (' + esc(s.state) + ')</option>';
        });
    }

    function buildDeptOptionsCache() {
        if (_deptOptionsCache) return;
        _deptOptionsCache = '';
        D.departments.forEach(function(d) {
            _deptOptionsCache += '<option value="' + esc(d.id) + '">' + esc(d.name) + '</option>';
        });
    }

    function statusOptions(selected) {
        buildStatusOptionsCache();
        var html = '<option value="">' + esc(t('selectStatus')) + '</option>' + _statusOptionsCache;
        if (selected) {
            html = html.replace('value="' + selected + '"', 'value="' + selected + '" selected');
        }
        return html;
    }

    function deptOptions(selected) {
        buildDeptOptionsCache();
        var html = '<option value="">' + esc(t('selectNone')) + '</option>' + _deptOptionsCache;
        if (selected) {
            html = html.replace('value="' + selected + '"', 'value="' + selected + '" selected');
        }
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

    /** Resolve a status name by ID for display purposes */
    function statusName(id) {
        if (!id) return '';
        for (var i = 0; i < D.statuses.length; i++) {
            if (String(D.statuses[i].id) === String(id))
                return D.statuses[i].name;
        }
        return '';
    }

    /** Arrow symbol based on behavior */
    function behaviorArrow(behavior) {
        switch (behavior) {
            case 'claim':   return '\u25B6'; // right-pointing triangle
            case 'release': return '\u2714'; // check mark
            default:        return '\u2192'; // right arrow
        }
    }

    /** Create a default blank step, optionally chaining from a previous step */
    function defaultStep(prevStep) {
        return {
            trigger_status: prevStep ? (prevStep.target_status || '') : '',
            target_status: '',
            behavior: 'none',
            transfer_dept: '',
            clear_team: false,
            label: '',
            icon: '',
            color: ''
        };
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
            cards.appendChild(renderCard(dept, cfg));
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
    }

    function renderCard(dept, cfg) {
        var enabled = !!cfg.enabled;
        var steps = (cfg.steps && cfg.steps.length) ? cfg.steps : [defaultStep()];

        var card = document.createElement('div');
        card.className = 'wb-card' + (enabled ? ' wb-card-enabled' : '');
        card.dataset.deptId = dept.id;
        card.dataset.deptName = dept.name.toLowerCase();

        var html =
            '<div class="wb-card-header">' +
            '<div class="wb-card-dot"></div>' +
            '<div class="wb-card-name">' + esc(dept.name) + '</div>' +
            '<label class="wb-toggle">' +
            '<input type="checkbox" class="wb-enabled-cb"' + (enabled ? ' checked' : '') + '>' +
            '<div class="wb-toggle-track"></div>' +
            '</label>' +
            '</div>' +
            '<div class="wb-card-body">';

        // Steps container
        html += '<div class="wb-steps-container">';
        for (var i = 0; i < steps.length; i++) {
            html += renderStepRow(steps[i], i, steps.length);
        }
        html += '</div>';

        // Add Step button
        var addDisabled = steps.length >= MAX_STEPS;
        html += '<button class="wb-step-add-btn"' + (addDisabled ? ' disabled' : '') + '>' +
            '+ ' + esc(t('addStep')) + '</button>';

        // Validation area
        html += '<div class="wb-validation"></div>';

        // Card actions
        html += '<div class="wb-card-actions">' +
            '<button class="wb-card-action-btn wb-clone-btn" data-dept-id="' + dept.id + '">' + esc(t('copyTo')) + '</button>' +
            '<select class="wb-card-action-btn wb-template-sel" data-dept-id="' + dept.id + '">' +
            '<option value="">' + esc(t('applyTemplate')) + '</option>' +
            '<option value="single">' + esc(t('tplSingleStep')) + '</option>' +
            '<option value="twostep">' + esc(t('tplTwoStep')) + '</option>' +
            '</select>' +
            '</div>';

        html += '</div>'; // .wb-card-body

        card.innerHTML = html;
        return card;
    }

    function renderStepRow(step, index, totalSteps) {
        step = step || defaultStep();
        var stepNum = index + 1;
        var arrow = behaviorArrow(step.behavior || 'none');
        var canRemove = totalSteps > 1;
        var canMoveUp = index > 0;
        var canMoveDown = index < totalSteps - 1;

        var html = '<div class="wb-step-row" data-step-index="' + index + '">';

        // Step header: number + action buttons
        html += '<div class="wb-step-header">' +
            '<span class="wb-step-number">' + esc(t('stepN', { n: stepNum })) + '</span>' +
            '<div class="wb-step-actions">' +
            '<button class="wb-step-move-btn wb-step-move-up" title="' + esc(t('moveUp')) + '"' +
            (canMoveUp ? '' : ' disabled') + '>\u2191</button>' +
            '<button class="wb-step-move-btn wb-step-move-down" title="' + esc(t('moveDown')) + '"' +
            (canMoveDown ? '' : ' disabled') + '>\u2193</button>' +
            '<button class="wb-step-remove-btn" title="' + esc(t('removeStep')) + '"' +
            (canRemove ? '' : ' disabled') + '>\u2715</button>' +
            '</div>' +
            '</div>';

        // Flow visualization: trigger pill -> arrow -> target pill
        html += '<div class="wb-flow">' +
            '<div class="wb-flow-pill wb-pill-trigger">' +
            '<span class="wb-flow-label">' + esc(t('triggerStatus')) + '</span>' +
            '<select class="wb-sel-trigger">' + statusOptions(step.trigger_status || '') + '</select>' +
            '</div>' +
            '<div class="wb-flow-arrow" data-behavior="' + esc(step.behavior || 'none') + '">' + arrow + '</div>' +
            '<div class="wb-flow-pill wb-pill-target">' +
            '<span class="wb-flow-label">' + esc(t('targetStatus')) + '</span>' +
            '<select class="wb-sel-target">' + statusOptions(step.target_status || '') + '</select>' +
            '</div>' +
            '</div>';

        // Step config: behavior, transfer, label
        html += '<div class="wb-step-config">';

        // Behavior
        html += '<div class="wb-step-field">' +
            '<label class="wb-step-field-label">' + esc(t('behavior')) + '</label>' +
            '<select class="wb-sel-behavior">' +
            '<option value="claim"' + (step.behavior === 'claim' ? ' selected' : '') + '>' + esc(t('behaviorClaim')) + '</option>' +
            '<option value="release"' + (step.behavior === 'release' ? ' selected' : '') + '>' + esc(t('behaviorRelease')) + '</option>' +
            '<option value="none"' + (step.behavior === 'none' || !step.behavior ? ' selected' : '') + '>' + esc(t('behaviorNone')) + '</option>' +
            '</select>' +
            '</div>';

        // Transfer (collapsible)
        var hasTransfer = !!(step.transfer_dept || step.clear_team);
        html += '<details class="wb-step-transfer-details"' + (hasTransfer ? ' open' : '') + '>' +
            '<summary class="wb-step-transfer-summary">' + esc(t('transferTo')) + '</summary>' +
            '<div class="wb-step-transfer-body">' +
            '<select class="wb-sel-transfer">' + deptOptions(step.transfer_dept || '') + '</select>' +
            '<label class="wb-transfer-check">' +
            '<input type="checkbox" class="wb-clear-team"' + (step.clear_team ? ' checked' : '') + '>' +
            ' ' + esc(t('clearTeam')) +
            '</label>' +
            '</div>' +
            '</details>';

        // Label
        html += '<div class="wb-step-field">' +
            '<label class="wb-step-field-label">' + esc(t('label')) + '</label>' +
            '<input type="text" class="wb-label-input wb-lbl-step" maxlength="12" ' +
            'placeholder="' + esc(t('label')) + '" value="' + esc(step.label || '') + '">' +
            '</div>';

        html += '</div>'; // .wb-step-config
        html += '</div>'; // .wb-step-row

        return html;
    }

    // ================================================================
    //  Step Manipulation
    // ================================================================

    function getCardSteps(card) {
        var steps = [];
        card.querySelectorAll('.wb-step-row').forEach(function(row) {
            steps.push({
                trigger_status: row.querySelector('.wb-sel-trigger').value,
                target_status: row.querySelector('.wb-sel-target').value,
                behavior: row.querySelector('.wb-sel-behavior').value,
                transfer_dept: row.querySelector('.wb-sel-transfer').value,
                clear_team: row.querySelector('.wb-clear-team').checked,
                label: row.querySelector('.wb-lbl-step').value,
                icon: '',
                color: ''
            });
        });
        return steps;
    }

    function setCardSteps(card, steps) {
        var container = card.querySelector('.wb-steps-container');
        if (!container) return;
        container.innerHTML = '';
        for (var i = 0; i < steps.length; i++) {
            container.innerHTML += renderStepRow(steps[i], i, steps.length);
        }
        // Update add-step button state
        var addBtn = card.querySelector('.wb-step-add-btn');
        if (addBtn) addBtn.disabled = steps.length >= MAX_STEPS;
    }

    function addStep(card) {
        var steps = getCardSteps(card);
        if (steps.length >= MAX_STEPS) {
            toast(t('maxStepsReached'), 'error');
            return;
        }
        var prevStep = steps.length > 0 ? steps[steps.length - 1] : null;
        steps.push(defaultStep(prevStep));
        setCardSteps(card, steps);
        validateCard(card);
        serializeAllDebounced();
        markDirty();
    }

    function removeStep(card, index) {
        var steps = getCardSteps(card);
        if (steps.length <= 1) {
            toast(t('minStepsRequired'), 'error');
            return;
        }
        steps.splice(index, 1);
        setCardSteps(card, steps);
        validateCard(card);
        serializeAllDebounced();
        markDirty();
    }

    function moveStep(card, index, direction) {
        var steps = getCardSteps(card);
        var newIndex = index + direction;
        if (newIndex < 0 || newIndex >= steps.length) return;
        // Swap
        var temp = steps[index];
        steps[index] = steps[newIndex];
        steps[newIndex] = temp;
        setCardSteps(card, steps);
        validateCard(card);
        serializeAllDebounced();
        markDirty();
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
            serializeAllDebounced();
            markDirty();
            updateBadge();
        });

        document.getElementById('wb-disable-all').addEventListener('click', function() {
            document.querySelectorAll('.wb-enabled-cb').forEach(function(cb) {
                cb.checked = false;
                updateCard(cb.closest('.wb-card'));
            });
            serializeAllDebounced();
            markDirty();
            updateBadge();
        });

        // Delegated change events on cards container
        document.getElementById('wb-cards').addEventListener('change', function(e) {
            var card = e.target.closest('.wb-card');
            if (!card) return;

            if (e.target.classList.contains('wb-enabled-cb')) {
                updateCard(card);
                updateBadge();
            }

            if (e.target.classList.contains('wb-template-sel')) {
                var tpl = e.target.value;
                if (tpl) {
                    applyTemplate(card, tpl);
                    e.target.value = '';
                }
            }

            // Update flow arrow when behavior changes
            if (e.target.classList.contains('wb-sel-behavior')) {
                var arrowEl = e.target.closest('.wb-step-row').querySelector('.wb-flow-arrow');
                if (arrowEl) {
                    var beh = e.target.value;
                    arrowEl.textContent = behaviorArrow(beh);
                    arrowEl.dataset.behavior = beh;
                }
            }

            validateCard(card);
            serializeAllDebounced();
            markDirty();
        });

        // Delegated input events (for label text fields)
        document.getElementById('wb-cards').addEventListener('input', function(e) {
            var card = e.target.closest('.wb-card');
            if (!card) return;
            serializeAllDebounced();
            markDirty();
        });

        // Delegated click events on cards container
        document.getElementById('wb-cards').addEventListener('click', function(e) {
            var card = e.target.closest('.wb-card');
            if (!card) return;

            // Clone button
            if (e.target.classList.contains('wb-clone-btn')) {
                showCloneDialog(e.target.dataset.deptId);
                return;
            }

            // Add step
            if (e.target.classList.contains('wb-step-add-btn')) {
                addStep(card);
                return;
            }

            // Remove step
            if (e.target.classList.contains('wb-step-remove-btn')) {
                var row = e.target.closest('.wb-step-row');
                if (row) {
                    removeStep(card, parseInt(row.dataset.stepIndex, 10));
                }
                return;
            }

            // Move up
            if (e.target.classList.contains('wb-step-move-up')) {
                var row = e.target.closest('.wb-step-row');
                if (row) {
                    moveStep(card, parseInt(row.dataset.stepIndex, 10), -1);
                }
                return;
            }

            // Move down
            if (e.target.classList.contains('wb-step-move-down')) {
                var row = e.target.closest('.wb-step-row');
                if (row) {
                    moveStep(card, parseInt(row.dataset.stepIndex, 10), 1);
                }
                return;
            }

            // Click on card header (outside toggle) to enable
            if (e.target.closest('.wb-toggle')) return;
            var header = e.target.closest('.wb-card-header');
            if (header) {
                var cb = header.querySelector('.wb-enabled-cb');
                if (!cb.checked) {
                    cb.checked = true;
                    updateCard(card);
                    validateCard(card);
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

        var warnings = [];
        var triggers = {};

        // Clear all pill styling
        card.querySelectorAll('.wb-flow-pill').forEach(function(pill) {
            pill.classList.remove('wb-invalid');
        });

        var rows = card.querySelectorAll('.wb-step-row');
        rows.forEach(function(row, idx) {
            var stepNum = idx + 1;
            var triggerSel = row.querySelector('.wb-sel-trigger');
            var targetSel = row.querySelector('.wb-sel-target');
            var trigger = triggerSel ? triggerSel.value : '';
            var target = targetSel ? targetSel.value : '';
            var triggerPill = row.querySelector('.wb-pill-trigger');
            var targetPill = row.querySelector('.wb-pill-target');

            // Required fields
            if (!trigger) {
                warnings.push(t('triggerRequired', { n: stepNum }));
                if (triggerPill) triggerPill.classList.add('wb-invalid');
            }
            if (!target) {
                warnings.push(t('targetRequired', { n: stepNum }));
                if (targetPill) targetPill.classList.add('wb-invalid');
            }

            // Trigger must not equal target
            if (trigger && target && trigger === target) {
                warnings.push(t('triggerEqualsTarget', { n: stepNum }));
                if (triggerPill) triggerPill.classList.add('wb-invalid');
                if (targetPill) targetPill.classList.add('wb-invalid');
            }

            // Duplicate trigger detection
            if (trigger) {
                if (triggers[trigger] !== undefined) {
                    warnings.push(t('duplicateTrigger', { n: stepNum }));
                    if (triggerPill) triggerPill.classList.add('wb-invalid');
                }
                triggers[trigger] = idx;
            }
        });

        // Loop detection: last step's target should not equal first step's trigger
        if (rows.length >= 2) {
            var firstTrigger = rows[0].querySelector('.wb-sel-trigger');
            var lastTarget = rows[rows.length - 1].querySelector('.wb-sel-target');
            var ft = firstTrigger ? firstTrigger.value : '';
            var lt = lastTarget ? lastTarget.value : '';
            if (ft && lt && ft === lt) {
                warnings.push(t('loopDetected'));
            }
        }

        // Render warnings
        warnings.forEach(function(w) {
            warnEl.innerHTML += '<div class="wb-warning">' + esc(w) + '</div>';
        });
    }

    // ================================================================
    //  Templates
    // ================================================================

    function applyTemplate(card, template) {
        if (!template) return;

        var steps;
        switch (template) {
            case 'single':
                steps = [
                    { trigger_status: '', target_status: '', behavior: 'claim', transfer_dept: '', clear_team: false, label: '', icon: '', color: '' },
                    { trigger_status: '', target_status: '', behavior: 'release', transfer_dept: '', clear_team: false, label: '', icon: '', color: '' }
                ];
                break;
            case 'twostep':
                steps = [
                    { trigger_status: '', target_status: '', behavior: 'claim', transfer_dept: '', clear_team: false, label: '', icon: '', color: '' },
                    { trigger_status: '', target_status: '', behavior: 'release', transfer_dept: '', clear_team: false, label: '', icon: '', color: '' },
                    { trigger_status: '', target_status: '', behavior: 'claim', transfer_dept: '', clear_team: false, label: '', icon: '', color: '' },
                    { trigger_status: '', target_status: '', behavior: 'release', transfer_dept: '', clear_team: false, label: '', icon: '', color: '' }
                ];
                break;
            default:
                return;
        }

        setCardSteps(card, steps);
        validateCard(card);
        serializeAllDebounced();
        markDirty();
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

        // Copy steps from source to target
        var sourceSteps = getCardSteps(sourceCard);
        targetCard.querySelector('.wb-enabled-cb').checked = true;
        setCardSteps(targetCard, sourceSteps);
        updateCard(targetCard);
        validateCard(targetCard);
        serializeAll();
        markDirty();
        updateBadge();

        var deptName = '';
        D.departments.forEach(function(d) {
            if (d.id === targetId) deptName = d.name;
        });
        var msg = (T.copiedTo || 'Copied to %s').replace('%s', deptName);
        toast(msg, 'success');
    }

    // ================================================================
    //  Serialize & Save
    // ================================================================

    var serializeTimer = null;
    function serializeAllDebounced() {
        clearTimeout(serializeTimer);
        serializeTimer = setTimeout(serializeAll, 150);
    }

    function serializeAll() {
        existing = {};
        document.querySelectorAll('.wb-card').forEach(function(card) {
            var deptId = card.dataset.deptId;
            var enabled = card.querySelector('.wb-enabled-cb').checked;
            var steps = getCardSteps(card);

            existing[deptId] = {
                enabled: enabled,
                schema_version: 2,
                steps: steps
            };
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
        // Send CSRF token both as header AND POST body param for maximum compatibility
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
                // Server returned non-JSON (HTML error page, session timeout, etc.)
                // Extract a readable snippet from the response for debugging
                var snippet = xhr.responseText.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().substring(0, 120);
                toast(t('saveFailed') + (snippet ? ': ' + snippet : ' (HTTP ' + xhr.status + ')'), 'error');
            }
        };

        xhr.onerror = function() {
            saveBtn.disabled = false;
            saveBtn.textContent = t('saveChanges');
            toast(t('networkError'), 'error');
        };

        // Send CSRF token in body as well (osTicket fallback: __CSRFToken__ POST param)
        xhr.send('widget_config=' + encodeURIComponent(json) +
                 '&__CSRFToken__=' + encodeURIComponent(D.csrfToken));
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
