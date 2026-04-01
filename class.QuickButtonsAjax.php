<?php
/**
 * Quick Buttons Plugin - AJAX Controller
 *
 * @author  ChesnoTech
 * @version 2.2.0
 * @link    https://github.com/ChesnoTech/ost-quick-buttons
 */

require_once INCLUDE_DIR . 'class.ajax.php';
require_once INCLUDE_DIR . 'class.ticket.php';
require_once INCLUDE_DIR . 'class.forms.php';
require_once INCLUDE_DIR . 'class.dept.php';
require_once INCLUDE_DIR . 'class.list.php';
require_once INCLUDE_DIR . 'class.plugin.php';

class QuickButtonsAjax extends AjaxController {

    // Action type constants
    const ACTION_START   = 'start';
    const ACTION_STOP    = 'stop';
    const ACTION_PARTIAL = 'partial';
    const ACTION_START2  = 'start2';

    const VALID_ACTIONS = array(
        self::ACTION_START, self::ACTION_STOP,
        self::ACTION_PARTIAL, self::ACTION_START2,
    );

    // Variant constants
    const VARIANT_SINGLE  = 'single';
    const VARIANT_TWOSTEP = 'twostep';

    // osTicket thread event IDs
    const EVENT_STATUS_CHANGE = 9;
    const EVENT_CLAIM         = 4;

    private static function choiceKey($value) {
        if (!$value) return $value;
        if (is_scalar($value)) {
            $decoded = @json_decode($value, true);
            if (is_array($decoded) && count($decoded) === 1)
                return (string) key($decoded);
            return (string) $value;
        }
        if (is_array($value) && count($value) === 1)
            return (string) key($value);
        return (string) $value;
    }

    private static function parseWidgetConfig($config) {
        if (method_exists($config, 'getWidgetConfig'))
            return $config->getWidgetConfig();
        $raw = strip_tags($config->get('widget_config') ?: '');
        $data = @json_decode($raw, true);
        return is_array($data) ? $data : array();
    }

    private static $pluginCache = null;
    private static function findPlugin() {
        if (self::$pluginCache === null)
            self::$pluginCache = Plugin::objects()->findFirst(
                array('install_path' => 'plugins/quick-buttons'));
        return self::$pluginCache;
    }

    private static function getDeptList() {
        $departments = array();
        foreach (Dept::getDepartments() as $id => $name)
            $departments[] = array('id' => (string) $id, 'name' => $name);
        return $departments;
    }

    private static function getStatusList() {
        $statuses = array();
        if ($items = TicketStatusList::getStatuses(array('enabled' => true))) {
            foreach ($items as $s) {
                $state = $s->getState();
                $statuses[] = array(
                    'id'    => (string) $s->getId(),
                    'name'  => $s->getName(),
                    'state' => $state ? ucfirst($state) : __('Custom'),
                );
            }
        }
        return $statuses;
    }

    private function requireStaff() {
        global $thisstaff;
        if (!$thisstaff || !$thisstaff->isValid())
            Http::response(403, __('Access Denied'));
        return $thisstaff;
    }

    // ================================================================
    //  Workflow Builder — Full-page editor
    // ================================================================

    function serveWorkflowBuilder() {
        $this->requireStaff();

        $iid = (int) ($_GET['iid'] ?? 0);
        if (!$iid)
            Http::response(400, __('Instance ID required'));

        $plugin = self::findPlugin();
        if (!$plugin)
            Http::response(404, __('Plugin not found'));

        // Find the instance
        $instance = PluginInstance::lookup($iid);
        if (!$instance || $instance->plugin_id != $plugin->getId())
            Http::response(404, __('Instance not found'));

        $config = $instance->getConfig();

        $departments = self::getDeptList();
        $statuses = self::getStatusList();

        // Current widget config
        $widgetConfig = self::parseWidgetConfig($config);

        // Topic name for header
        $topicId = self::choiceKey($config->get('topic_id'));
        $topicName = '';
        if ($topicId) {
            $topic = Topic::lookup($topicId);
            if ($topic) $topicName = $topic->getFullName();
        }

        $data = array(
            'instanceId'   => $iid,
            'instanceName' => $instance->getName(),
            'topicName'    => $topicName,
            'departments'  => $departments,
            'statuses'     => $statuses,
            'config'       => $widgetConfig,
            'csrfToken'    => $this->getCsrfToken(),
            'saveUrl'      => ROOT_PATH . 'scp/ajax.php/quick-buttons/workflow-builder-save?iid=' . $iid,
            'backUrl'      => ROOT_PATH . 'scp/plugins.php?id=' . $plugin->getId() . '&xid=' . $iid . '#config',
            'i18n'         => $this->getWorkflowBuilderI18n(),
        );

        // Serve HTML page
        header('Content-Type: text/html; charset=utf-8');
        $this->renderWorkflowBuilderHtml($data);
        exit;
    }

    function saveWorkflowBuilder() {
        try {
            return $this->_saveWorkflowBuilderInner();
        } catch (\Throwable $e) {
            return $this->json_encode(array(
                'error' => $e->getMessage() . ' [' . basename($e->getFile()) . ':' . $e->getLine() . ']'
            ));
        }
    }

    private function _saveWorkflowBuilderInner() {
        $this->requireStaff();

        $iid = (int) ($_GET['iid'] ?? 0);
        if (!$iid)
            return $this->json_encode(array('error' => __('Instance ID required')));

        $plugin = self::findPlugin();
        if (!$plugin)
            return $this->json_encode(array('error' => __('Plugin not found')));

        $instance = PluginInstance::lookup($iid);
        if (!$instance || $instance->plugin_id != $plugin->getId())
            return $this->json_encode(array('error' => __('Instance not found')));

        $json = $_POST['widget_config'] ?? '';
        $data = @json_decode($json, true);
        if (!is_array($data))
            return $this->json_encode(array('error' => __('Invalid JSON')));

        // Validate via config class — use the already-instantiated config object
        // (PluginConfig constructor takes a namespace string, not a PluginInstance)
        $config = $instance->getConfig();
        $errors = array();
        $testConfig = array(
            'topic_id'      => $config->get('topic_id'),
            'widget_config' => $json,
        );

        if (!$config->pre_save($testConfig, $errors))
            return $this->json_encode(array('error' => $errors['err'] ?? __('Validation failed')));

        // Save directly to DB
        // Note: db_input() already wraps the value in quotes — do NOT add extra quotes in sprintf
        $ns = 'plugin.' . $plugin->getId() . '.instance.' . $iid;
        $result = db_query(sprintf(
            "INSERT INTO %s (namespace, `key`, value, updated) VALUES (%s, 'widget_config', %s, NOW())
             ON DUPLICATE KEY UPDATE value=VALUES(value), updated=NOW()",
            CONFIG_TABLE,
            db_input($ns),
            db_input($json)
        ));
        if (!$result)
            return $this->json_encode(array('error' => 'DB error: ' . db_error()));

        return $this->json_encode(array('success' => true, 'message' => __('Configuration saved')));
    }

    private function getCsrfToken() {
        global $ost;
        if ($ost && $ost->getCSRF())
            return $ost->getCSRF()->getToken();
        return '';
    }

    private function getWorkflowBuilderI18n() {
        return array(
            // Header
            'workflowBuilder'   => __('Workflow Builder'),
            'back'              => __('Back'),

            // Toolbar
            'searchDepts'       => __('Search departments...'),
            'enableAll'         => __('Enable All'),
            'disableAll'        => __('Disable All'),
            'enabledCount'      => __('%d / %d enabled'),

            // Card
            'trigger'           => __('Trigger'),
            'working'           => __('Working'),
            'done'              => __('Done'),
            'transferTo'        => __('Transfer to:'),
            'clearTeam'         => __('Also clear team assignment'),
            'selectStatus'      => __('-- Select --'),
            'selectNone'        => __('-- None --'),

            // Variant
            'variant'           => __('Variant'),
            'variantSingle'     => __('Single Step'),
            'variantTwostep'    => __('Two Step'),
            'step1'             => __('Step 1'),
            'step2'             => __('Step 2'),
            'step2Trigger'      => __('Step 2 Trigger'),
            'step2Working'      => __('Step 2 Working'),
            'finalDone'         => __('Final Done'),
            'partialReady'      => __('Next'),
            'startStep2'        => __('Start Step 2'),

            // Actions
            'copyTo'            => __('Copy to...'),
            'applyTemplate'     => __('Apply template...'),
            'tplSingleStep'     => __('Single Step'),
            'tplStep1'          => __('Assembly Step 1 (no transfer)'),
            'tplStep2'          => __('Assembly Step 2 (with transfer)'),

            // Validation
            'triggerRequired'   => __('Trigger status is required'),
            'workingRequired'   => __('Working status is required'),
            'doneRequired'      => __('Done status is required'),
            'triggerEqualsWorking' => __('Trigger and Working are the same status (Start button will do nothing visible)'),
            'doneEqualsTrigger' => __('Done status equals Trigger — this creates an infinite loop'),
            'workingEqualsDone' => __('Working and Done are the same status (Stop button will do nothing visible)'),
            'step2TriggerRequired' => __('Step 2 trigger status is required'),
            'step2WorkingRequired' => __('Step 2 working status is required'),
            'finalDoneRequired' => __('Final done status is required'),

            // Footer
            'noUnsaved'         => __('No unsaved changes'),
            'unsavedChanges'    => __('Unsaved changes'),
            'allSaved'          => __('All changes saved'),
            'cancel'            => __('Cancel'),
            'saveChanges'       => __('Save Changes'),
            'saving'            => __('Saving...'),
            'saved'             => __('Saved!'),
            'saveFailed'        => __('Save failed'),
            'networkError'      => __('Network error'),

            // Dialogs
            'discardChanges'    => __('Discard unsaved changes?'),
            'copyPrompt'        => __('Copy this configuration to which department?'),
            'deptNotFound'      => __('Department not found: %s'),
            'copiedTo'          => __('Copied to %s'),
            'templateApplied'   => __('Template applied — select statuses for each step'),
            'loading'           => __('Loading dashboard...'),
        );
    }

    private function renderWorkflowBuilderHtml($data) {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        $assetBase = ROOT_PATH . 'scp/ajax.php/quick-buttons/assets';
        $dir = dirname(__FILE__) . '/assets/';
        $v = @filemtime($dir . 'quick-buttons-admin.css') ?: time();

        echo '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Workflow Builder — ' . htmlspecialchars($data['instanceName']) . '</title>
<link rel="stylesheet" href="' . $assetBase . '/admin-css?v=' . $v . '">
<style>' . $this->getWorkflowBuilderCss() . '</style>
</head>
<body>
<div id="wb-app"></div>
<script>var WB_DATA = ' . $json . ';</script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>' . $this->getWorkflowBuilderJs() . '</script>
</body>
</html>';
    }

    private function getWorkflowBuilderCss() {
        $file = dirname(__FILE__) . '/assets/workflow-builder.css';
        return file_exists($file) ? file_get_contents($file) : '';
    }

    private function getWorkflowBuilderJs() {
        $file = dirname(__FILE__) . '/assets/workflow-builder.js';
        return file_exists($file) ? file_get_contents($file) : 'console.error("workflow-builder.js not found");';
    }

    // ================================================================
    //  API: Get Widgets (with permissions)
    // ================================================================

    function getWidgets() {
        $thisstaff = $this->requireStaff();

        $agentDepts = array_map('strval', $thisstaff->getDepts());
        $widgets = array();

        $plugin = self::findPlugin();
        $emptyResponse = array(
            'widgets' => array(),
            'tickets' => new \stdClass(),
            'i18n'    => $this->getI18nStrings(),
            'perms'   => $this->getAgentPerms($thisstaff),
        );

        if (!$plugin)
            return $this->json_encode($emptyResponse);

        foreach ($plugin->getActiveInstances() as $instance) {
            $config = $instance->getConfig();
            if (!$config) continue;

            $topicId = self::choiceKey($config->get('topic_id'));
            if (!$topicId) continue;

            $data = self::parseWidgetConfig($config);
            if (empty($data['departments'])) continue;

            $deptConfigs = array();
            foreach ($data['departments'] as $deptId => $deptCfg) {
                $deptId = (string) $deptId;
                if (empty($deptCfg['enabled'])) continue;
                if (!in_array($deptId, $agentDepts)) continue;

                $variant = $deptCfg['variant'] ?? self::VARIANT_SINGLE;
                $deptConfigs[$deptId] = array(
                    'start_trigger'     => (string) ($deptCfg['start_trigger_status'] ?? ''),
                    'start_target'      => (string) ($deptCfg['start_target_status'] ?? ''),
                    'stop_target'       => (string) ($deptCfg['stop_target_status'] ?? ''),
                    'stop_transfer'     => (string) ($deptCfg['stop_transfer_dept'] ?? ''),
                    'clear_team'        => !empty($deptCfg['clear_team']),
                    'variant'           => $variant,
                    // Two-step fields
                    'step2_trigger'     => (string) ($deptCfg['step2_trigger_status'] ?? ''),
                    'step2_target'      => (string) ($deptCfg['step2_target_status'] ?? ''),
                    'step2_stop_target' => (string) ($deptCfg['step2_stop_target_status'] ?? ''),
                    'step2_clear_team'  => !empty($deptCfg['step2_clear_team']),
                    // Per-department labels
                    'start_label'       => $deptCfg['start_label'] ?? '',
                    'stop_label'        => $deptCfg['stop_label'] ?? '',
                    'partial_label'     => $deptCfg['partial_label'] ?? '',
                    'start2_label'      => $deptCfg['start2_label'] ?? '',
                    'finish_label'      => $deptCfg['finish_label'] ?? '',
                );
            }

            if (empty($deptConfigs)) continue;

            // Colors (widget-level, shared across departments)
            $startColor = $config->get('start_color') ?: '';
            $stopColor  = $config->get('stop_color') ?: '';
            // Confirmation mode: none, confirm, countdown
            $confirmMode = $config->get('confirm_mode') ?: 'confirm';
            // Handle legacy BooleanField migration (1 = confirm, '' = none)
            if ($confirmMode === '1') $confirmMode = 'confirm';
            if ($confirmMode === '' || $confirmMode === '0') $confirmMode = 'none';
            $countdownSec = max(3, min(30, (int) ($config->get('countdown_seconds') ?: 5)));

            $widgets[] = array(
                'id'               => $instance->getId(),
                'topic'            => $topicId,
                'depts'            => $deptConfigs,
                'startColor'       => $startColor,
                'stopColor'        => $stopColor,
                'confirm'          => $confirmMode !== 'none',
                'confirmMode'      => $confirmMode,
                'countdownSeconds' => $countdownSec,
                'showDeadline'     => !empty($config->get('show_deadline')),
            );
        }

        // Build ticket metadata
        $tickets = new \stdClass();
        $tids = $_REQUEST['tids'] ?? array();
        if (is_array($tids) && count($tids)) {
            $ids = array_filter(array_map('intval', $tids));
            if ($ids) {
                $in = implode(',', $ids);
                // elapsed_secs: seconds since the ticket's LAST STATUS CHANGE event.
                // We find this from ost_thread_event (event_id=9 / 'edited', data contains "status").
                // Falls back to lastupdate if no status-change event exists (e.g. very old tickets).
                $res = db_query(
                    "SELECT t.ticket_id, t.topic_id, t.dept_id, t.status_id, t.staff_id,
                            TIMESTAMPDIFF(SECOND,
                                COALESCE(
                                    (SELECT te.timestamp
                                     FROM ost_thread_event te
                                     JOIN ost_thread th ON th.id = te.thread_id
                                     WHERE th.object_id = t.ticket_id
                                       AND th.object_type = 'T'
                                       AND te.event_id = 9
                                       AND te.data LIKE '%\"status\"%'
                                     ORDER BY te.timestamp DESC
                                     LIMIT 1),
                                    t.lastupdate
                                ),
                                NOW()
                            ) AS elapsed_secs,
                            TIMESTAMPDIFF(SECOND, NOW(), COALESCE(t.duedate, t.est_duedate)) AS deadline_secs,
                            t.isoverdue
                     FROM " . TICKET_TABLE . " t WHERE t.ticket_id IN ($in)");
                if ($res) {
                    $map = array();
                    while ($row = db_fetch_array($res)) {
                        $tid = (string) $row['ticket_id'];
                        $map[$tid] = array(
                            'topic'      => $row['topic_id'] ? (string) $row['topic_id'] : null,
                            'dept'       => $row['dept_id'] ? (string) $row['dept_id'] : null,
                            'status'     => $row['status_id'] ? (string) $row['status_id'] : null,
                            'staff'      => $row['staff_id'] ? (string) $row['staff_id'] : null,
                            'since_secs'    => isset($row['elapsed_secs']) ? (int) $row['elapsed_secs'] : null,
                            'deadline_secs' => isset($row['deadline_secs']) ? (int) $row['deadline_secs'] : null,
                            'isoverdue'     => !empty($row['isoverdue']),
                        );
                    }
                    $tickets = (object) $map;
                }
            }
        }

        return $this->json_encode(array(
            'widgets'   => $widgets,
            'tickets'   => $tickets,
            'serverNow' => date('Y-m-d H:i:s'),
            'i18n'      => $this->getI18nStrings(),
            'perms'     => $this->getAgentPerms($thisstaff),
        ));
    }

    private function getI18nStrings() {
        return array(
            'start'        => __('Start'),
            'done'         => __('Done'),
            'error'        => __('Error'),
            'confirm'      => __('Confirm'),
            'cancel'       => __('Cancel'),
            'confirmStart' => __('Start working on ticket #%s?'),
            'confirmStop'  => __('Complete and hand off ticket #%s?'),
            'countdownStart'    => __('Claim ticket and change status to working'),
            'countdownStop'     => __('Change status, release agent and transfer'),
            'partialReady'      => __('Next'),
            'startStep2'        => __('Start Step 2'),
            'confirmPartial'    => __('Mark ticket #%s as partially ready?'),
            'confirmStart2'     => __('Start step 2 on ticket #%s?'),
            'countdownPartial'  => __('Release agent and mark partially ready'),
            'countdownStart2'   => __('Claim ticket for step 2'),
            'executingIn'       => __('Executing in %ss...'),
            'undo'         => __('Undo'),
            'undoExpired'  => __('Undo expired'),
            'bulkStart'    => __('Start Selected'),
            'bulkStop'     => __('Complete Selected'),
            'elapsed'      => __('elapsed'),
            'waiting'      => __('waiting'),
            'labelH'       => __('H'),
            'labelM'       => __('M'),
            'labelS'       => __('S'),
            'deadline'     => __('deadline'),
            'overdue'      => __('overdue'),
        );
    }

    private function getAgentPerms($thisstaff) {
        return array(
            'canAssign'   => $thisstaff->hasPerm(Ticket::PERM_ASSIGN, false),
            'canTransfer' => $thisstaff->hasPerm(Ticket::PERM_TRANSFER, false),
            'canRelease'  => $thisstaff->hasPerm(Ticket::PERM_RELEASE, false),
            'canManage'   => $thisstaff->canManageTickets(),
            'staffId'     => (string) $thisstaff->getId(),
            'isAdmin'     => (bool) $thisstaff->isAdmin(),
        );
    }

    // ================================================================
    //  API: Execute Action
    // ================================================================

    function execute() {
        $thisstaff = $this->requireStaff();

        $widgetId = (int) $_POST['widget_id'];
        $action   = $_POST['action'] ?? '';
        $deptId   = (string) ($_POST['dept_id'] ?? '');
        $tids     = $_POST['tids'] ?? array();

        if (!$widgetId)
            Http::response(400, $this->json_encode(
                array('error' => __('Invalid widget'))));
        if (!in_array($action, self::VALID_ACTIONS))
            Http::response(400, $this->json_encode(
                array('error' => __('Invalid action type'))));
        if (!$tids || !is_array($tids) || !count($tids))
            Http::response(400, $this->json_encode(
                array('error' => __('No tickets selected'))));

        // Validate widget instance
        $instance = PluginInstance::lookup($widgetId);
        if (!$instance || !$instance->isEnabled())
            Http::response(400, $this->json_encode(
                array('error' => __('Invalid or disabled widget'))));

        $plugin = self::findPlugin();
        if (!$plugin || $instance->getPluginId() != $plugin->getId())
            Http::response(403, $this->json_encode(
                array('error' => __('Invalid plugin instance'))));

        $config = $instance->getConfig();
        if (!$config)
            Http::response(500, $this->json_encode(
                array('error' => __('Configuration error'))));

        $data = self::parseWidgetConfig($config);
        if (empty($data['departments'][$deptId]))
            Http::response(400, $this->json_encode(
                array('error' => __('No configuration for this department'))));

        $deptCfg = $data['departments'][$deptId];
        if (empty($deptCfg['enabled']))
            Http::response(400, $this->json_encode(
                array('error' => __('Department not enabled in this widget'))));

        $agentDepts = array_map('strval', $thisstaff->getDepts());
        if (!in_array($deptId, $agentDepts))
            Http::response(403, $this->json_encode(
                array('error' => __('Access Denied'))));

        // Resolve targets based on action type
        $variant = $deptCfg['variant'] ?? self::VARIANT_SINGLE;

        // Reject two-step actions on single-step configs
        if (in_array($action, array(self::ACTION_PARTIAL, self::ACTION_START2))
                && $variant !== self::VARIANT_TWOSTEP)
            Http::response(400, $this->json_encode(
                array('error' => __('Two-step actions require two-step variant'))));

        switch ($action) {
            case self::ACTION_START:
                $targetStatusId = $deptCfg['start_target_status'] ?? null;
                $shouldClaim = true;
                $shouldRelease = false;
                $transferDept = null;
                $clearTeam = false;
                break;
            case self::ACTION_PARTIAL:
                $targetStatusId = $deptCfg['step2_trigger_status'] ?? null;
                $shouldClaim = false;
                $shouldRelease = true;
                $transferDept = null;
                $clearTeam = false;
                break;
            case self::ACTION_START2:
                $targetStatusId = $deptCfg['step2_target_status'] ?? null;
                $shouldClaim = true;
                $shouldRelease = false;
                $transferDept = null;
                $clearTeam = false;
                break;
            case self::ACTION_STOP:
                $targetStatusId = ($variant === self::VARIANT_TWOSTEP)
                    ? ($deptCfg['step2_stop_target_status'] ?? null)
                    : ($deptCfg['stop_target_status'] ?? null);
                $shouldClaim = false;
                $shouldRelease = true;
                $clearTeam = ($variant === self::VARIANT_TWOSTEP)
                    ? !empty($deptCfg['step2_clear_team'])
                    : !empty($deptCfg['clear_team']);
                // Transfer on stop
                $transferDept = null;
                if (!empty($deptCfg['stop_transfer_dept'])) {
                    $transferDept = Dept::lookup($deptCfg['stop_transfer_dept']);
                    if (!$transferDept)
                        Http::response(400, $this->json_encode(
                            array('error' => __('Configured transfer department not found'))));
                }
                break;
        }

        $targetStatus = null;
        if ($targetStatusId) {
            $targetStatus = TicketStatus::lookup($targetStatusId);
            if (!$targetStatus)
                Http::response(400, $this->json_encode(
                    array('error' => __('Configured target status not found'))));
        }

        // Process tickets — capture undo state
        $success = 0;
        $failed = 0;
        $errors_list = array();
        $undoData = array();

        foreach ($tids as $tid) {
            $tid = (int) $tid;
            if (!$tid) continue;

            $ticket = Ticket::lookup($tid);
            if (!$ticket) {
                $failed++;
                $errors_list[] = sprintf(__('Ticket #%d: not found'), $tid);
                continue;
            }

            $ticketNum = $ticket->getNumber();

            // Capture pre-action state for undo
            $prevState = array(
                'status_id' => $ticket->getStatusId(),
                'staff_id'  => $ticket->getStaffId(),
                'team_id'   => $ticket->getTeamId(),
                'dept_id'   => $ticket->getDeptId(),
            );

            // For release/stop actions: ticket must be assigned to this agent.
            // Only system administrators (isAdmin) are exempt — regular agents
            // with canManageTickets (e.g. Expanded Access role) are NOT exempt.
            if ($shouldRelease && $ticket->getStaffId()
                    && (int) $ticket->getStaffId() !== (int) $thisstaff->getId()
                    && !$thisstaff->isAdmin()) {
                $failed++;
                $errors_list[] = sprintf(__('Ticket #%s is assigned to another agent'), $ticketNum);
                continue;
            }

            if ($shouldClaim) {
                $ok = $this->doStart($ticket, $thisstaff, $targetStatus, $ticketNum, $errors_list);
            } else {
                $ok = $this->doStop($ticket, $thisstaff, array(
                    'targetStatus' => $targetStatus,
                    'transferDept' => $transferDept,
                    'clearTeam'    => $clearTeam,
                    'ticketNum'    => $ticketNum,
                ), $errors_list);
            }

            if ($ok) {
                $success++;
                $undoData[$tid] = $prevState;
            } else {
                $failed++;
            }
        }

        // Store undo data in session (last action only)
        if (!empty($undoData)) {
            $_SESSION['quick_buttons_undo'] = array(
                'action'   => $action,
                'tickets'  => $undoData,
                'time'     => time(),
                'staff_id' => $thisstaff->getId(),
            );
        }

        // Response
        $total = $success + $failed;
        if ($failed == 0) {
            $message = sprintf(
                _N('%d ticket processed successfully',
                   '%d tickets processed successfully', $success),
                $success);
        } else {
            $message = sprintf(
                __('%d of %d ticket(s) processed. %d failed.'),
                $success, $total, $failed);
        }

        if ($success > 0)
            $_SESSION['::sysmsgs']['msg'] = $message;
        elseif ($failed > 0)
            $_SESSION['::sysmsgs']['error'] = $message;

        Http::response(200, $this->json_encode(array(
            'success'  => $success,
            'failed'   => $failed,
            'errors'   => $errors_list,
            'message'  => $message,
            'canUndo'  => !empty($undoData),
        )));
    }

    /**
     * POST /quick-buttons/undo
     *
     * Reverse the last Quick Buttons action (within 60 seconds).
     */
    function undo() {
        $thisstaff = $this->requireStaff();

        $undo = $_SESSION['quick_buttons_undo'] ?? null;
        if (!$undo || empty($undo['tickets']))
            Http::response(400, $this->json_encode(
                array('error' => __('Nothing to undo'))));

        // Only the same agent can undo, within 60 seconds
        if ($undo['staff_id'] != $thisstaff->getId())
            Http::response(403, $this->json_encode(
                array('error' => __('Only the original agent can undo'))));

        if (time() - $undo['time'] > 60)
            Http::response(400, $this->json_encode(
                array('error' => __('Undo expired (60 second limit)'))));

        $restored = 0;
        $errors_list = array();

        foreach ($undo['tickets'] as $tid => $prevState) {
            $ticket = Ticket::lookup($tid);
            if (!$ticket) {
                $errors_list[] = sprintf(__('Ticket #%d: not found'), $tid);
                continue;
            }

            // Restore previous status
            $prevStatus = TicketStatus::lookup($prevState['status_id']);
            if ($prevStatus) {
                $errors = array();
                $ticket->setStatus($prevStatus,
                    __('Status restored via Quick Buttons undo'), $errors);
            }

            // Restore previous assignment
            if ($prevState['staff_id'])
                $ticket->setStaffId($prevState['staff_id']);
            else
                $ticket->setStaffId(0);

            // Restore department if changed
            if ($prevState['dept_id'] != $ticket->getDeptId()) {
                $prevDept = Dept::lookup($prevState['dept_id']);
                if ($prevDept) {
                    $form = TransferForm::instantiate(array('dept' => $prevDept->getId()));
                    $errors = array();
                    $ticket->transfer($form, $errors);
                }
            }

            $ticket->save();
            $restored++;
        }

        // Clear undo data
        unset($_SESSION['quick_buttons_undo']);

        $message = sprintf(
            _N('%d ticket restored',
               '%d tickets restored', $restored),
            $restored);

        $_SESSION['::sysmsgs']['msg'] = $message;

        Http::response(200, $this->json_encode(array(
            'restored' => $restored,
            'errors'   => $errors_list,
            'message'  => $message,
        )));
    }

    // ================================================================
    //  Dashboard Page (standalone, agent-accessible)
    // ================================================================

    function serveDashboardPage() {
        $this->requireStaff();

        $data = array(
            'apiUrl'    => ROOT_PATH . 'scp/ajax.php/quick-buttons/dashboard',
            'csrfToken' => $this->getCsrfToken(),
            'i18n'      => array(
                'workflowDashboard' => __('Workflow Dashboard'),
                'realtimeMetrics'   => __('Real-time workflow metrics'),
                'last7'             => __('7 Days'),
                'last30'            => __('30 Days'),
                'last90'            => __('90 Days'),
                'loading'           => __('Loading...'),
                'processedToday'    => __('Processed Today'),
                'ticketsToday'      => __('tickets today'),
                'avgPerDay'         => __('Avg Per Day'),
                'dayPeriod'         => __('day period'),
                'activeAgents'      => __('Active Agents'),
                'agentsWithActions' => __('agents with actions'),
                'queueDepth'        => __('Queue Depth'),
                'ticketsInPipeline' => __('tickets in pipeline'),
                'totalProcessed'    => __('Total Processed'),
                'days'              => __('days'),
                'dailyThroughput'   => __('Daily Throughput'),
                'avgTimePerStep'    => __('Average Time Per Step'),
                'status'            => __('Status'),
                'avgTime'           => __('Avg Time'),
                'transitions'       => __('Transitions'),
                'agentLeaderboard'  => __('Agent Leaderboard'),
                'myPerformance'     => __('My Performance'),
                'currentQueue'      => __('Current Queue'),
                'ticketsPerDay'     => __('Tickets Processed Per Day'),
                'ticketsPerWeek'    => __('Tickets Processed Per Week'),
                'openTickets'       => __('Open Tickets'),
                'fieldValues'       => __('Field Values'),
                'agent'             => __('Agent'),
                'claimed'           => __('Claimed'),
                'tickets'           => __('Tickets'),
                'totalValue'        => __('Total'),
                'ticketCount'       => __('Tickets'),
                'average'           => __('Average'),
                'total'             => __('Total'),
                'from'              => __('From'),
                'to'                => __('To'),
                'apply'             => __('Apply'),
                'noData'            => __('No data for this period'),
                'agentPerformance'  => __('Agent Performance by Status'),
                'allDepartments'    => __('All Departments'),
                'allAgents'         => __('All Agents'),
                'allTopics'         => __('All Topics'),
                'teamAvg'           => __('Team avg'),
                'vsAvg'             => __('vs Avg'),
                'department'        => __('Department'),
            ),
        );

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
        $dir = dirname(__FILE__) . '/assets/';
        $css = file_exists($dir . 'workflow-dashboard.css') ? file_get_contents($dir . 'workflow-dashboard.css') : '';
        $js  = file_exists($dir . 'workflow-dashboard.js') ? file_get_contents($dir . 'workflow-dashboard.js') : '';

        // Detect system language for date picker locale (week starts Monday in ru)
        $lang = 'en';
        if (class_exists('Internationalization')) {
            $sysLang = Internationalization::getCurrentLanguage();
            if ($sysLang) $lang = str_replace('_', '-', $sysLang);
        }

        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html>
<html lang="' . htmlspecialchars($lang) . '">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>' . __('Workflow Dashboard') . '</title>
<style>' . $css . '</style>
</head>
<body>
<div id="wd-app"></div>
<script>var WD_DATA = ' . $json . ';</script>
<script>' . $js . '</script>
</body>
</html>';
        exit;
    }

    function serveAgentPerfPage() {
        $this->requireStaff();

        $data = array(
            'apiUrl'    => ROOT_PATH . 'scp/ajax.php/quick-buttons/dashboard',
            'csrfToken' => $this->getCsrfToken(),
            'mode'      => 'agent-perf',
            'i18n'      => array(
                'agentPerformance'  => __('Agent Performance by Status'),
                'allDepartments'    => __('All Departments'),
                'allAgents'         => __('All Agents'),
                'allTopics'         => __('All Topics'),
                'teamAvg'           => __('Team avg'),
                'vsAvg'             => __('vs Avg'),
                'agent'             => __('Agent'),
                'avgTime'           => __('Avg Time'),
                'tickets'           => __('Tickets'),
                'noData'            => __('No data for this period'),
                'loading'           => __('Loading...'),
                'last7'             => __('7 Days'),
                'last30'            => __('30 Days'),
                'last90'            => __('90 Days'),
                'from'              => __('From'),
                'to'                => __('To'),
                'apply'             => __('Apply'),
            ),
        );

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
        $dir = dirname(__FILE__) . '/assets/';
        $css = file_exists($dir . 'workflow-dashboard.css') ? file_get_contents($dir . 'workflow-dashboard.css') : '';
        $js  = file_exists($dir . 'workflow-dashboard.js') ? file_get_contents($dir . 'workflow-dashboard.js') : '';

        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>' . __('Agent Performance') . '</title>
<style>' . $css . '</style>
</head>
<body>
<div id="wd-app"></div>
<script>var WD_DATA = ' . $json . ';</script>
<script>' . $js . '</script>
</body>
</html>';
        exit;
    }

    // ================================================================
    //  Dashboard API (JSON data)
    // ================================================================

    /**
     * GET /quick-buttons/dashboard
     *
     * Returns workflow metrics: throughput, avg time per step, agent stats.
     * Query params: days (default 7)
     */
    function dashboard() {
        $this->requireStaff();
        global $thisstaff;

        // Date range: support both ?days=N and ?from=YYYY-MM-DD&to=YYYY-MM-DD
        $from = $_GET['from'] ?? null;
        $to   = $_GET['to'] ?? null;
        $fromTs = $from ? strtotime($from) : false;
        $toTs   = $to ? strtotime($to) : false;
        if ($fromTs !== false && $toTs !== false && $fromTs > 0 && $toTs > 0) {
            $from = date('Y-m-d', $fromTs);
            $to   = date('Y-m-d', $toTs);
            if ($from > $to) { $tmp = $from; $from = $to; $to = $tmp; }
            $since = $from . ' 00:00:00';
            $until = $to . ' 23:59:59';
            $days = (int) ((strtotime($to) - strtotime($from)) / 86400) + 1;
        } else {
            $days = max(1, min(365, (int) ($_GET['days'] ?? 7)));
            $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));
            $until = date('Y-m-d H:i:s');
            $from = date('Y-m-d', strtotime($since));
            $to = date('Y-m-d');
        }

        // Access control: filter by agent's accessible departments
        $deptFilter = '';     // for queries using tk.dept_id
        $deptFilterE = '';    // for queries using e.dept_id (event-time dept)
        $isLimited = $thisstaff->isAccessLimited();
        $accessDepts = $thisstaff->getDepts();
        if (!$thisstaff->isAdmin() && $accessDepts) {
            $deptIds = array_map('intval', $accessDepts);
            $deptFilter  = ' AND tk.dept_id IN (' . implode(',', $deptIds) . ')';
            $deptFilterE = ' AND e.dept_id IN (' . implode(',', $deptIds) . ')';
        }
        // If access-limited, also filter by event performer (not ticket assignment)
        $staffFilter = '';
        $staffFilterE = '';
        if ($isLimited) {
            $myId = (int) $thisstaff->getId();
            $staffFilter  = ' AND (tk.staff_id = ' . $myId . ')';
            $staffFilterE = ' AND (e.staff_id = ' . $myId . ')';
        }

        // 1. Tickets processed per day (status change events)
        // Use weekly rollup if range > 60 days
        $groupExpr = $days > 60 ? "DATE_FORMAT(e.timestamp, '%Y-W%v')" : "DATE(e.timestamp)";
        $dailyRes = db_query(
            "SELECT {$groupExpr} as day, COUNT(*) as cnt
             FROM " . THREAD_EVENT_TABLE . " e
             JOIN " . THREAD_TABLE . " t ON e.thread_id = t.id AND t.object_type = 'T'
             JOIN " . TICKET_TABLE . " tk ON t.object_id = tk.ticket_id
             WHERE e.event_id = " . self::EVENT_STATUS_CHANGE . " AND e.timestamp >= '" . db_input($since, false) . "'
               AND e.timestamp <= '" . db_input($until, false) . "'
               AND e.data LIKE '%\"status\"%'
               {$deptFilter} {$staffFilter}
             GROUP BY day
             ORDER BY day");
        $daily = array();
        if ($dailyRes)
            while ($row = db_fetch_array($dailyRes))
                $daily[] = array('day' => $row['day'], 'count' => (int) $row['cnt']);

        // 2. Average time per step (time between consecutive status changes per ticket)
        //    Also accumulates per-agent/dept/topic breakdown in $agentGrid.
        $stepTimesRes = db_query(
            "SELECT t.object_id as ticket_id, e.data, e.timestamp,
                    e.staff_id, e.topic_id, e.dept_id
             FROM " . THREAD_EVENT_TABLE . " e
             JOIN " . THREAD_TABLE . " t ON e.thread_id = t.id AND t.object_type = 'T'
             WHERE e.event_id = " . self::EVENT_STATUS_CHANGE . " AND e.timestamp >= '" . db_input($since, false) . "'
               AND e.timestamp <= '" . db_input($until, false) . "'
               AND e.data LIKE '%\"status\"%'
               {$deptFilterE} {$staffFilterE}
             ORDER BY t.object_id, e.timestamp");
        $stepDurations = array();
        $agentGrid     = array(); // [$statusKey][$deptId][$topicId][$staffId] = [total, count]
        $prevByTicket  = array();
        if ($stepTimesRes) {
            while ($row = db_fetch_array($stepTimesRes)) {
                $tid = $row['ticket_id'];
                $eventData = @json_decode($row['data'], true);
                if (!is_array($eventData)) continue;
                $statusId = $eventData['status'] ?? null;
                if (is_array($statusId)) $statusId = $statusId[0]; // handle [id,"name"] format
                if (!$statusId) continue;

                if (isset($prevByTicket[$tid])) {
                    $prev     = $prevByTicket[$tid];
                    $duration = strtotime($row['timestamp']) - strtotime($prev['time']);
                    if ($duration <= 0) { $prevByTicket[$tid] = array('status' => (string) $statusId, 'time' => $row['timestamp']); continue; }
                    $key      = $prev['status'];

                    // avgTimes accumulation (existing)
                    if (!isset($stepDurations[$key]))
                        $stepDurations[$key] = array('total' => 0, 'count' => 0);
                    $stepDurations[$key]['total'] += $duration;
                    $stepDurations[$key]['count']++;

                    // agentGrid accumulation — agent = exit event's staff_id
                    $agentId = (int) $row['staff_id'];
                    $topicId = (int) $row['topic_id'];
                    $deptId  = (int) $row['dept_id'];
                    if ($agentId > 0) {
                        if (!isset($agentGrid[$key][$deptId][$topicId][$agentId]))
                            $agentGrid[$key][$deptId][$topicId][$agentId] = array('total' => 0, 'count' => 0);
                        $agentGrid[$key][$deptId][$topicId][$agentId]['total'] += $duration;
                        $agentGrid[$key][$deptId][$topicId][$agentId]['count']++;
                    }
                }
                $prevByTicket[$tid] = array('status' => (string) $statusId, 'time' => $row['timestamp']);
            }
        }

        // Build avg times with status names
        $avgTimes = array();
        foreach ($stepDurations as $statusId => $data) {
            $status = TicketStatus::lookup($statusId);
            $avgSec = $data['count'] > 0 ? round($data['total'] / $data['count']) : 0;
            $avgTimes[] = array(
                'statusId'   => (string) $statusId,
                'statusName' => $status ? $status->getName() : __('Unknown'),
                'avgSeconds' => $avgSec,
                'avgDisplay' => self::formatDuration($avgSec),
                'count'      => $data['count'],
            );
        }

        // Build agent performance flat array from $agentGrid
        // Pre-load lookup maps (one query each — small tables)
        $staffNames = array();
        $snRes = db_query("SELECT staff_id, firstname, lastname FROM " . STAFF_TABLE);
        if ($snRes) while ($r = db_fetch_array($snRes))
            $staffNames[(int)$r['staff_id']] = trim($r['firstname'] . ' ' . $r['lastname']) ?: __('Unknown');

        require_once INCLUDE_DIR . 'class.topic.php';
        $topicNames = array();
        $tnRes = db_query("SELECT topic_id FROM " . TOPIC_TABLE);
        if ($tnRes) while ($r = db_fetch_array($tnRes)) {
            $tObj = Topic::lookup((int) $r['topic_id']);
            $topicNames[(int)$r['topic_id']] = $tObj ? ($tObj->getLocalName() ?: $tObj->getName()) : ('Topic #' . $r['topic_id']);
        }

        $deptNames = array();
        $dnRes = db_query("SELECT id FROM " . DEPT_TABLE);
        if ($dnRes) while ($r = db_fetch_array($dnRes)) {
            $dObj = Dept::lookup((int) $r['id']);
            $deptNames[(int)$r['id']] = $dObj ? ($dObj->getLocalName() ?: $dObj->getName()) : ('Dept #' . $r['id']);
        }

        $agentStats = array();

        foreach ($agentGrid as $statusKey => $byDept) {
            $statusObj  = TicketStatus::lookup($statusKey);
            $statusName = $statusObj ? $statusObj->getName() : __('Unknown');
            foreach ($byDept as $deptId => $byTopic) {
                $deptName = isset($deptNames[$deptId]) ? $deptNames[$deptId] : __('Unknown');
                foreach ($byTopic as $topicId => $byAgent) {
                    $topicName = isset($topicNames[$topicId]) ? $topicNames[$topicId] : __('Unknown');
                    foreach ($byAgent as $agentId => $data) {
                        $agentName = isset($staffNames[$agentId]) ? $staffNames[$agentId] : (__('Agent') . ' #' . $agentId);
                        $avgSec    = $data['count'] > 0 ? round($data['total'] / $data['count']) : 0;
                        $agentStats[] = array(
                            'statusId'   => (string) $statusKey,
                            'statusName' => $statusName,
                            'deptId'     => (string) $deptId,
                            'deptName'   => $deptName,
                            'topicId'    => (string) $topicId,
                            'topicName'  => $topicName,
                            'agentId'    => (string) $agentId,
                            'agentName'  => $agentName,
                            'avgSeconds' => $avgSec,
                            'avgDisplay' => self::formatDuration($avgSec),
                            'count'      => $data['count'],
                        );
                    }
                }
            }
        }

        // Build dropdown lists from FULL lookup tables (not just data in period)
        $deptList = array();
        foreach ($deptNames as $id => $name)
            $deptList[] = array('id' => (string) $id, 'name' => $name);
        $topicList = array();
        foreach ($topicNames as $id => $name)
            $topicList[] = array('id' => (string) $id, 'name' => $name);
        $agentList = array();
        foreach ($staffNames as $id => $name)
            $agentList[] = array('id' => (string) $id, 'name' => $name);
        usort($deptList,  function($a, $b) { return strcmp($a['name'], $b['name']); });
        usort($topicList, function($a, $b) { return strcmp($a['name'], $b['name']); });
        usort($agentList, function($a, $b) { return strcmp($a['name'], $b['name']); });

        // 3. Agent leaderboard (claims in period)
        // Access-limited agents only see their own stats
        $agentLimit = '';
        if ($isLimited) {
            $agentLimit = ' AND e.staff_id = ' . (int) $thisstaff->getId();
        }
        $agentRes = db_query(
            "SELECT e.staff_id, s.firstname, s.lastname, COUNT(*) as cnt
             FROM " . THREAD_EVENT_TABLE . " e
             JOIN " . THREAD_TABLE . " t ON e.thread_id = t.id AND t.object_type = 'T'
             JOIN " . TICKET_TABLE . " tk ON t.object_id = tk.ticket_id
             LEFT JOIN " . STAFF_TABLE . " s ON e.staff_id = s.staff_id
             WHERE e.event_id = " . self::EVENT_CLAIM . " AND e.timestamp >= '" . db_input($since, false) . "'
               AND e.timestamp <= '" . db_input($until, false) . "'
               AND e.staff_id > 0
               {$deptFilter} {$agentLimit}
             GROUP BY e.staff_id
             ORDER BY cnt DESC
             LIMIT 20");
        $agents = array();
        if ($agentRes)
            while ($row = db_fetch_array($agentRes))
                $agents[] = array(
                    'name'  => trim($row['firstname'] . ' ' . $row['lastname']) ?: __('Unknown'),
                    'count' => (int) $row['cnt'],
                );

        // 4. Current queue snapshot (reuse existing dept/staff filters)
        $queueRes = db_query(
            "SELECT s.id, s.name, COUNT(*) as cnt
             FROM " . TICKET_TABLE . " tk
             JOIN " . TICKET_STATUS_TABLE . " s ON tk.status_id = s.id
             WHERE s.state = 'open'
               {$deptFilter} {$staffFilter}
             GROUP BY s.id
             ORDER BY s.sort");
        $queue = array();
        if ($queueRes)
            while ($row = db_fetch_array($queueRes))
                $queue[] = array(
                    'statusId'   => (string) $row['id'],
                    'statusName' => $row['name'],
                    'count'      => (int) $row['cnt'],
                );

        // 5. Calculated field values per agent (if CF log table exists)
        $cfValues = array();
        $prefix = TABLE_PREFIX;
        $cfTableExists = db_query("SHOW TABLES LIKE '{$prefix}calculated_fields_log'");
        if ($cfTableExists && db_num_rows($cfTableExists) > 0) {
            $cfAgentFilter = $isLimited
                ? ' AND l.staff_id = ' . (int) $thisstaff->getId()
                : '';
            $cfDeptJoin = $deptFilter ? " JOIN {$prefix}ticket tk ON l.ticket_id = tk.ticket_id" : '';
            $cfDeptFilter = $deptFilter; // Reuse existing dept filter (same tk alias)
            $cfRes = db_query(
                "SELECT l.staff_id, s.firstname, s.lastname,
                        SUM(COALESCE(l.credited_value, l.field_value)) AS total,
                        COUNT(DISTINCT l.ticket_id) AS ticket_count,
                        COUNT(*) AS cnt
                 FROM {$prefix}calculated_fields_log l
                 LEFT JOIN {$prefix}staff s ON l.staff_id = s.staff_id
                 {$cfDeptJoin}
                 WHERE l.created >= '" . db_input($since, false) . "' AND l.created <= '" . db_input($until, false) . "'
                   {$cfAgentFilter} {$cfDeptFilter}
                 GROUP BY l.staff_id
                 ORDER BY total DESC"
            );
            if ($cfRes) {
                while ($row = db_fetch_array($cfRes)) {
                    $cfValues[] = array(
                        'name'  => trim($row['firstname'] . ' ' . $row['lastname']) ?: 'Agent #' . $row['staff_id'],
                        'total' => round((float) $row['total'], 2),
                        'count' => (int) $row['ticket_count'],
                    );
                }
            }
        }

        // KPI summaries
        $totalProcessed = array_sum(array_column($daily, 'count'));
        $avgPerDay = $days > 0 ? round($totalProcessed / $days, 1) : 0;
        $totalQueue = array_sum(array_column($queue, 'count'));
        $activeAgents = count($agents);

        // Load dept→status mapping for report filtering
        $deptStatusMap = self::loadDeptStatusMap();

        return $this->json_encode(array(
            'days'     => $days,
            'from'     => $from,
            'to'       => $to,
            'weekly'   => $days > 60,
            'daily'       => $daily,
            'avgTimes'    => $avgTimes,
            'agentStats'  => $agentStats,
            'departments' => $deptList,
            'topics'      => $topicList,
            'agentList'   => $agentList,
            'agents'      => $agents,
            'queue'       => $queue,
            'cfValues'    => $cfValues,
            'isLimited'   => $isLimited,
            'deptStatusMap' => $deptStatusMap,
            'kpi'      => array(
                'totalProcessed' => $totalProcessed,
                'avgPerDay'      => $avgPerDay,
                'totalQueue'     => $totalQueue,
                'activeAgents'   => $activeAgents,
            ),
        ));
    }

    private static function formatDuration($totalSec) {
        if ($totalSec < 60) return $totalSec . 's';
        if ($totalSec < 3600) return round($totalSec / 60) . 'm';
        if ($totalSec < 86400) return round($totalSec / 3600, 1) . 'h';
        return round($totalSec / 86400, 1) . 'd';
    }

    // ================================================================
    //  Action Sequences (with error recovery)
    // ================================================================

    private function doStart($ticket, $thisstaff, $targetStatus, $ticketNum, &$errors_list) {
        // Save original state for rollback
        $origStaffId = $ticket->getStaffId();

        // 1. Claim
        $result = $this->actionClaim($ticket, $thisstaff);
        if ($result !== true) {
            $errors_list[] = sprintf(__('Ticket #%s: Claim failed — %s'), $ticketNum, $result);
            return false;
        }

        // 2. Change status
        if ($targetStatus) {
            $result = $this->actionChangeStatus($ticket, $targetStatus, $thisstaff);
            if ($result !== true) {
                // Rollback: release the claim we just made
                $ticket->release(array('sid' => true), $rollbackErrors = array());
                if ($origStaffId)
                    $ticket->setStaffId($origStaffId);
                $errors_list[] = sprintf(__('Ticket #%s: Status change failed (claim rolled back) — %s'), $ticketNum, $result);
                return false;
            }
        }

        return true;
    }

    /**
     * @param array $ctx Keys: targetStatus, transferDept, clearTeam, ticketNum
     */
    private function doStop($ticket, $thisstaff, $ctx, &$errors_list) {
        $targetStatus = $ctx['targetStatus'] ?? null;
        $transferDept = $ctx['transferDept'] ?? null;
        $clearTeam    = $ctx['clearTeam'] ?? false;
        $ticketNum    = $ctx['ticketNum'] ?? '';
        // 1. Change status
        if ($targetStatus) {
            $result = $this->actionChangeStatus($ticket, $targetStatus, $thisstaff);
            if ($result !== true) {
                $errors_list[] = sprintf(__('Ticket #%s: Status change failed — %s'), $ticketNum, $result);
                return false;
            }
        }

        // 2. Release agent (and optionally team)
        if ($ticket->getStaffId()) {
            if ($clearTeam) {
                $result = $this->actionReleaseAll($ticket, $thisstaff);
            } else {
                $result = $this->actionReleaseStaff($ticket, $thisstaff);
            }
            if ($result !== true)
                $errors_list[] = sprintf(__('Ticket #%s: Release warning — %s'), $ticketNum, $result);
        }

        // 3. Transfer
        if ($transferDept) {
            $result = $this->actionTransfer($ticket, $transferDept, $thisstaff);
            if ($result !== true) {
                $errors_list[] = sprintf(__('Ticket #%s: Transfer failed — %s'), $ticketNum, $result);
                return false;
            }
        }

        return true;
    }

    // ================================================================
    //  Atomic Actions
    // ================================================================

    private function actionClaim($ticket, $thisstaff) {
        if (!$ticket->checkStaffPerm($thisstaff, Ticket::PERM_ASSIGN))
            return __('Permission denied');
        if (!$ticket->isOpen())
            return __('Ticket is not open');
        if ($ticket->getStaff()) {
            if ($ticket->getStaffId() == $thisstaff->getId())
                return true;
            return __('Ticket is already assigned');
        }

        $id = sprintf('s%d', $thisstaff->getId());
        $form = ClaimForm::instantiate(array('assignee' => array($id)));
        $form->setAssignees(array($id => $thisstaff->getName()));

        $errors = array();
        if (!$ticket->claim($form, $errors))
            return $errors['err'] ?: $errors['assign'] ?: __('Unknown error');
        return true;
    }

    private function actionChangeStatus($ticket, $targetStatus, $thisstaff) {
        if (!$thisstaff->canManageTickets())
            return __('Permission denied');

        $errors = array();
        $comment = sprintf(__('Status changed via Quick Buttons to: %s'), $targetStatus->getName());
        if (!$ticket->setStatus($targetStatus, $comment, $errors))
            return $errors['err'] ?: __('Unable to change status');
        return true;
    }

    private function actionTransfer($ticket, $targetDept, $thisstaff) {
        if (!$ticket->checkStaffPerm($thisstaff, Ticket::PERM_TRANSFER))
            return __('Permission denied');
        if ($ticket->getDeptId() == $targetDept->getId())
            return true;

        $form = TransferForm::instantiate(array('dept' => $targetDept->getId()));
        $errors = array();
        if (!$ticket->transfer($form, $errors))
            return $errors['err'] ?: $errors['dept'] ?: __('Unable to transfer');
        return true;
    }

    private function actionReleaseStaff($ticket, $thisstaff) {
        if (!$ticket->checkStaffPerm($thisstaff, Ticket::PERM_RELEASE))
            return __('Permission denied');
        if (!$ticket->isOpen())
            return __('Ticket is not open');
        if (!$ticket->getStaffId())
            return true;

        $errors = array();
        if (!$ticket->release(array('sid' => true), $errors))
            return $errors['err'] ?: __('Unable to release agent assignment');
        return true;
    }

    private function actionReleaseAll($ticket, $thisstaff) {
        if (!$ticket->checkStaffPerm($thisstaff, Ticket::PERM_RELEASE))
            return __('Permission denied');
        if (!$ticket->isOpen())
            return __('Ticket is not open');

        $info = array();
        if ($ticket->getStaffId()) $info['sid'] = true;
        if ($ticket->getTeamId()) $info['tid'] = true;
        if (empty($info))
            return true;

        $errors = array();
        if (!$ticket->release($info, $errors))
            return $errors['err'] ?: __('Unable to release assignment');
        return true;
    }

    // ================================================================
    //  Static Asset Serving
    // ================================================================

    private function serveFile($file, $contentType, $maxAge = 86400) {
        if (!file_exists($file))
            Http::response(404, 'Not found');

        $etag = '"qb-' . md5($file) . '-' . filemtime($file) . '"';
        header('Content-Type: ' . $contentType);
        header('Cache-Control: public, max-age=' . $maxAge);
        header('ETag: ' . $etag);
        if (isset($_SERVER['HTTP_IF_NONE_MATCH'])
                && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
            Http::response(304, '');
            exit;
        }
        readfile($file);
        exit;
    }

    function serveJs() {
        $this->serveFile(dirname(__FILE__) . '/assets/quick-buttons.js',
            'application/javascript; charset=UTF-8');
    }

    function serveCss() {
        $isOSTA = is_dir(rtrim(ROOT_DIR, '/') . '/osta');
        $cssFile = $isOSTA ? 'quick-buttons.css' : 'quick-buttons-default.css';
        $this->serveFile(dirname(__FILE__) . '/assets/' . $cssFile,
            'text/css; charset=UTF-8');
    }

    function serveAdminJs() {
        $this->serveFile(dirname(__FILE__) . '/assets/quick-buttons-admin.js',
            'application/javascript; charset=UTF-8', 3600);
    }

    function serveAdminCss() {
        $this->serveFile(dirname(__FILE__) . '/assets/quick-buttons-admin.css',
            'text/css; charset=UTF-8', 3600);
    }

    // ================================================================
    //  Department → Status Mapping (for Agent Performance filtering)
    // ================================================================

    private static function loadDeptStatusMap() {
        $plugin = self::findPlugin();
        if (!$plugin) return (object) array();
        $ns = 'plugin.' . $plugin->getId();
        $row = db_fetch_array(db_query(sprintf(
            "SELECT value FROM %s WHERE namespace=%s AND `key`='dept_status_map'",
            CONFIG_TABLE, db_input($ns)
        )));
        if ($row && $row['value']) {
            $map = @json_decode($row['value'], true);
            if (is_array($map)) return $map;
        }
        return (object) array();
    }

    function getDeptStatusMap() {
        $this->requireStaff();
        global $thisstaff;
        if (!$thisstaff->isAdmin())
            return $this->json_encode(array('error' => __('Admin access required')));

        $map = self::loadDeptStatusMap();

        // All departments (Dept::getName() returns translated name)
        $depts = array();
        $dRes = db_query("SELECT id FROM " . DEPT_TABLE . " ORDER BY name");
        if ($dRes) while ($r = db_fetch_array($dRes)) {
            $dept = Dept::lookup((int) $r['id']);
            $depts[] = array('id' => (string) $r['id'], 'name' => $dept ? ($dept->getLocalName() ?: $dept->getName()) : ('Dept #' . $r['id']));
        }
        usort($depts, function($a, $b) { return strcmp($a['name'], $b['name']); });

        // All open statuses (use TicketStatus ORM for translated names)
        $statuses = array();
        if ($items = TicketStatusList::getStatuses(array('enabled' => true))) {
            foreach ($items as $s) {
                if ($s->getState() === 'open') {
                    $statuses[] = array('id' => (string) $s->getId(), 'name' => $s->getName());
                }
            }
        }

        return $this->json_encode(array(
            'map'          => $map,
            'departments'  => $depts,
            'statuses'     => $statuses,
        ));
    }

    function saveDeptStatusMap() {
        $this->requireStaff();
        global $thisstaff;
        if (!$thisstaff->isAdmin())
            return $this->json_encode(array('error' => __('Admin access required')));

        $json = $_POST['dept_status_map'] ?? '';
        $data = @json_decode($json, true);
        if (!is_array($data))
            return $this->json_encode(array('error' => __('Invalid JSON')));

        // Validate: keys must be integers, values must be arrays of integers
        $clean = array();
        foreach ($data as $deptId => $statusIds) {
            $deptId = (int) $deptId;
            if ($deptId <= 0) continue;
            if (!is_array($statusIds)) continue;
            $ids = array_values(array_unique(array_filter(
                array_map('intval', $statusIds),
                function($v) { return $v > 0; }
            )));
            if (!empty($ids))
                $clean[(string) $deptId] = array_map('strval', $ids);
        }

        $plugin = self::findPlugin();
        if (!$plugin)
            return $this->json_encode(array('error' => __('Plugin not found')));

        $ns = 'plugin.' . $plugin->getId();
        $jsonClean = json_encode($clean, JSON_UNESCAPED_UNICODE);
        $result = db_query(sprintf(
            "INSERT INTO %s (namespace, `key`, value, updated)
             VALUES (%s, 'dept_status_map', %s, NOW())
             ON DUPLICATE KEY UPDATE value=VALUES(value), updated=NOW()",
            CONFIG_TABLE, db_input($ns), db_input($jsonClean)
        ));
        if (!$result)
            return $this->json_encode(array('error' => 'DB error: ' . db_error()));

        return $this->json_encode(array('success' => true, 'message' => __('Mapping saved')));
    }

    // ================================================================
    //  Plugin Upgrade (admin-confirmed)
    // ================================================================

    function executeUpgradeAjax() {
        $this->requireStaff();
        global $thisstaff;
        if (!$thisstaff || !$thisstaff->isAdmin())
            return $this->json_encode(array('success' => false, 'error' => __('Admin access required')));

        require_once dirname(__FILE__) . '/class.QuickButtonsPlugin.php';

        if (!QuickButtonsPlugin::isUpgradePending())
            return $this->json_encode(array('success' => true, 'version' => QuickButtonsPlugin::CURRENT_SCHEMA, 'msg' => 'Already up to date'));

        $result = QuickButtonsPlugin::executeUpgrade();
        return $this->json_encode($result);
    }
}
