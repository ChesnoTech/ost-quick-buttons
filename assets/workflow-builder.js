/**
 * Workflow Builder — Admin UI v3.0
 * Card-based department configuration with status flow diagrams
 */
(function() {
    'use strict';

    var D = WB_DATA;
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

    function statusOptions(selected) {
        var html = '<option value="">-- Select --</option>';
        D.statuses.forEach(function(s) {
            var sel = s.id === selected ? ' selected' : '';
            html += '<option value="' + esc(s.id) + '"' + sel + '>' +
                esc(s.name) + ' (' + esc(s.state) + ')</option>';
        });
        return html;
    }

    function deptOptions(selected) {
        var html = '<option value="">-- None --</option>';
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
        if (info) info.textContent = 'Unsaved changes';
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
            '<h1>Workflow Builder</h1>' +
            '<div class="wb-subtitle">' + esc(D.instanceName) + ' \u2014 ' + esc(D.topicName) + '</div>' +
            '</div>' +
            '<div class="wb-header-actions">' +
            '<button class="wb-btn wb-btn-cancel" id="wb-back">\u2190 Back</button>' +
            '</div>';
        app.appendChild(header);

        // Toolbar
        var toolbar = document.createElement('div');
        toolbar.className = 'wb-toolbar';
        var enabledCount = 0;
        D.departments.forEach(function(d) {
            if (existing[d.id] && existing[d.id].enabled) enabledCount++;
        });
        toolbar.innerHTML =
            '<input type="text" class="wb-search" id="wb-search" placeholder="Search departments...">' +
            '<button class="wb-toolbar-btn" id="wb-enable-all">Enable All</button>' +
            '<button class="wb-toolbar-btn" id="wb-disable-all">Disable All</button>' +
            '<span class="wb-badge">' + enabledCount + ' / ' + D.departments.length + ' enabled</span>';
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

            // Header
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
                // Flow diagram
                '<div class="wb-flow">' +
                '<div class="wb-flow-pill wb-pill-trigger">' +
                '<span class="wb-flow-label">Trigger</span>' +
                '<select class="wb-sel-trigger">' + statusOptions(cfg.start_trigger_status || '') + '</select>' +
                '</div>' +
                '<div class="wb-flow-arrow wb-flow-arrow-start">\u25B6</div>' +
                '<div class="wb-flow-pill wb-pill-working">' +
                '<span class="wb-flow-label">Working</span>' +
                '<select class="wb-sel-working">' + statusOptions(cfg.start_target_status || '') + '</select>' +
                '</div>' +
                '<div class="wb-flow-arrow wb-flow-arrow-stop">\u2714</div>' +
                '<div class="wb-flow-pill wb-pill-done">' +
                '<span class="wb-flow-label">Done</span>' +
                '<select class="wb-sel-done">' + statusOptions(cfg.stop_target_status || '') + '</select>' +
                '</div>' +
                '</div>' +
                // Validation warnings
                '<div class="wb-validation"></div>' +
                // Transfer
                '<div class="wb-transfer">' +
                '<span class="wb-transfer-label">Transfer to:</span>' +
                '<select class="wb-sel-transfer">' + deptOptions(cfg.stop_transfer_dept || '') + '</select>' +
                '<label class="wb-transfer-check">' +
                '<input type="checkbox" class="wb-clear-team"' + (cfg.clear_team ? ' checked' : '') + '>' +
                ' Clear team on transfer' +
                '</label>' +
                '</div>' +
                // Card actions
                '<div class="wb-card-actions">' +
                '<button class="wb-card-action-btn wb-clone-btn" data-dept-id="' + dept.id + '">Copy to\u2026</button>' +
                '<select class="wb-card-action-btn wb-template-sel" data-dept-id="' + dept.id + '">' +
                '<option value="">Apply template\u2026</option>' +
                '<option value="single">Single Step</option>' +
                '<option value="step1">Assembly Step 1 (no transfer)</option>' +
                '<option value="step2">Assembly Step 2 (with transfer)</option>' +
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
            '<div class="wb-footer-info">No unsaved changes</div>' +
            '<div class="wb-footer-actions">' +
            '<button class="wb-btn wb-btn-cancel" id="wb-cancel">Cancel</button>' +
            '<button class="wb-btn wb-btn-save" id="wb-save">Save Changes</button>' +
            '</div>';
        app.appendChild(footer);

        // Bind events
        bindEvents();
    }

    // ================================================================
    //  Events
    // ================================================================

    function bindEvents() {
        // Back / Cancel
        document.getElementById('wb-back').addEventListener('click', function() {
            if (dirty && !confirm('Discard unsaved changes?')) return;
            window.location.href = D.backUrl;
        });

        document.getElementById('wb-cancel').addEventListener('click', function() {
            if (dirty && !confirm('Discard unsaved changes?')) return;
            window.location.href = D.backUrl;
        });

        // Save
        document.getElementById('wb-save').addEventListener('click', saveConfig);

        // Search
        document.getElementById('wb-search').addEventListener('input', function() {
            var q = this.value.toLowerCase().trim();
            document.querySelectorAll('.wb-card').forEach(function(card) {
                var name = card.dataset.deptName;
                card.classList.toggle('wb-card-hidden', q && name.indexOf(q) === -1);
            });
        });

        // Enable / Disable All
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

        // Per-card events (delegated)
        document.getElementById('wb-cards').addEventListener('change', function(e) {
            var card = e.target.closest('.wb-card');
            if (!card) return;

            if (e.target.classList.contains('wb-enabled-cb')) {
                updateCard(card);
                updateBadge();
            }

            // Template selector
            if (e.target.classList.contains('wb-template-sel')) {
                applyTemplate(card, e.target.value);
                e.target.value = '';
            }

            validateCard(card);
            serializeAll();
            markDirty();
        });

        // Clone button
        document.getElementById('wb-cards').addEventListener('click', function(e) {
            if (e.target.classList.contains('wb-clone-btn')) {
                showCloneDialog(e.target.dataset.deptId);
            }
        });

        // Toggle header click (not the toggle switch)
        document.getElementById('wb-cards').addEventListener('click', function(e) {
            if (e.target.closest('.wb-toggle')) return;
            var header = e.target.closest('.wb-card-header');
            if (header) {
                var cb = header.querySelector('.wb-enabled-cb');
                // Only toggle body visibility if already enabled
                // If disabled, enable it
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
    }

    function updateBadge() {
        var count = document.querySelectorAll('.wb-enabled-cb:checked').length;
        var badge = document.querySelector('.wb-badge');
        if (badge) badge.textContent = count + ' / ' + D.departments.length + ' enabled';
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

        if (!trigger) warnings.push('Trigger status is required');
        if (!working) warnings.push('Working status is required');
        if (!done) warnings.push('Done status is required');

        if (trigger && trigger === working)
            warnings.push('Trigger and Working are the same status (Start button will do nothing visible)');
        if (done && done === trigger)
            warnings.push('Done status equals Trigger — this creates an infinite loop');
        if (working && working === done)
            warnings.push('Working and Done are the same status (Stop button will do nothing visible)');

        // Mark invalid fields
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

        // Templates use status positions in the list
        // Users should select actual statuses, but we can pre-suggest
        var trigger = card.querySelector('.wb-sel-trigger');
        var working = card.querySelector('.wb-sel-working');
        var done = card.querySelector('.wb-sel-done');
        var transfer = card.querySelector('.wb-sel-transfer');

        switch (template) {
            case 'single':
                // Single step: keep trigger/working as-is, clear transfer
                // Just ensure all three dropdowns have different values
                break;
            case 'step1':
                // Assembly step 1: no transfer (intermediate step)
                transfer.value = '';
                break;
            case 'step2':
                // Assembly step 2: with transfer
                break;
        }

        toast('Template applied — select statuses for each step', 'success');
    }

    // ================================================================
    //  Clone
    // ================================================================

    function showCloneDialog(sourceDeptId) {
        var sourceCard = document.querySelector('.wb-card[data-dept-id="' + sourceDeptId + '"]');
        if (!sourceCard) return;

        var opts = '';
        D.departments.forEach(function(d) {
            if (d.id === sourceDeptId) return;
            opts += '<option value="' + d.id + '">' + esc(d.name) + '</option>';
        });

        var msg = 'Copy this configuration to which department?';
        var target = prompt(msg);
        if (!target) return;

        // Find by name match
        var targetId = null;
        D.departments.forEach(function(d) {
            if (d.name.toLowerCase().indexOf(target.toLowerCase()) > -1)
                targetId = d.id;
        });

        if (!targetId) {
            toast('Department not found: ' + target, 'error');
            return;
        }

        var targetCard = document.querySelector('.wb-card[data-dept-id="' + targetId + '"]');
        if (!targetCard) return;

        // Copy values
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

        toast('Copied to ' + esc(D.departments.find(function(d) { return d.id === targetId; }).name), 'success');
    }

    // ================================================================
    //  Serialize & Save
    // ================================================================

    function serializeAll() {
        existing = {};
        document.querySelectorAll('.wb-card').forEach(function(card) {
            var deptId = card.dataset.deptId;
            var enabled = card.querySelector('.wb-enabled-cb').checked;

            existing[deptId] = {
                enabled: enabled,
                start_trigger_status: card.querySelector('.wb-sel-trigger').value,
                start_target_status: card.querySelector('.wb-sel-working').value,
                stop_target_status: card.querySelector('.wb-sel-done').value,
                stop_transfer_dept: card.querySelector('.wb-sel-transfer').value,
                clear_team: card.querySelector('.wb-clear-team').checked
            };
        });
    }

    function saveConfig() {
        serializeAll();

        var json = JSON.stringify({ departments: existing });
        var saveBtn = document.getElementById('wb-save');
        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';

        var xhr = new XMLHttpRequest();
        xhr.open('POST', D.saveUrl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.setRequestHeader('X-CSRFToken', D.csrfToken);

        xhr.onload = function() {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save Changes';

            try {
                var resp = JSON.parse(xhr.responseText);
                if (resp.success) {
                    dirty = false;
                    var info = document.querySelector('.wb-footer-info');
                    if (info) info.textContent = 'All changes saved';
                    toast(resp.message || 'Saved!', 'success');
                } else {
                    toast(resp.error || 'Save failed', 'error');
                }
            } catch (e) {
                toast('Save failed: ' + xhr.statusText, 'error');
            }
        };

        xhr.onerror = function() {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save Changes';
            toast('Network error', 'error');
        };

        xhr.send('widget_config=' + encodeURIComponent(json));
    }

    // ================================================================
    //  Init
    // ================================================================

    render();

    // Warn on page leave with unsaved changes
    window.addEventListener('beforeunload', function(e) {
        if (dirty) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

})();
