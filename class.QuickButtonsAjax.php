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
        $raw = strip_tags($config->get('widget_config') ?: '');
        $data = @json_decode($raw, true);
        return is_array($data) ? $data : array();
    }

    private static function findPlugin() {
        return Plugin::objects()->findFirst(
            array('install_path' => 'plugins/quick-buttons'));
    }

    private function requireStaff() {
        global $thisstaff;
        if (!$thisstaff || !$thisstaff->isValid())
            Http::response(403, __('Access Denied'));
        return $thisstaff;
    }

    // ================================================================
    //  API: Admin Config Data
    // ================================================================

    function getAdminConfigData() {
        $this->requireStaff();

        $departments = array();
        foreach (Dept::getDepartments() as $id => $name)
            $departments[] = array('id' => (string) $id, 'name' => $name);

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

        return $this->json_encode(array(
            'departments' => $departments,
            'statuses'    => $statuses,
            'i18n'        => array(
                'department'    => __('Department'),
                'enabled'       => __('Enabled'),
                'start_trigger' => __('Start: Trigger Status'),
                'start_target'  => __('Start: Target Status'),
                'stop_target'   => __('Stop: Target Status'),
                'stop_transfer' => __('Stop: Transfer To'),
                'clear_team'    => __('Clear Team'),
                'select'        => __('-- Select --'),
            ),
        ));
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

        // Load departments
        $departments = array();
        foreach (Dept::getDepartments() as $id => $name)
            $departments[] = array('id' => (string) $id, 'name' => $name);

        // Load statuses
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

        // Validate via config class
        $config = $instance->getConfig();
        $errors = array();
        $testConfig = array(
            'topic_id'      => $config->get('topic_id'),
            'widget_config' => $json,
        );

        $configObj = new QuickButtonsConfig($instance);
        if (!$configObj->pre_save($testConfig, $errors))
            return $this->json_encode(array('error' => $errors['err'] ?? __('Validation failed')));

        // Save directly to DB
        $ns = 'plugin.' . $plugin->getId() . '.instance.' . $iid;
        db_query(sprintf(
            "INSERT INTO %s (namespace, `key`, value, updated) VALUES ('%s', 'widget_config', '%s', NOW())
             ON DUPLICATE KEY UPDATE value=VALUES(value), updated=NOW()",
            CONFIG_TABLE,
            db_input($ns),
            db_input($json)
        ));

        return $this->json_encode(array('success' => true, 'message' => __('Configuration saved')));
    }

    private function getCsrfToken() {
        return csrf_token();
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
            'clearTeam'         => __('Clear team on transfer'),
            'selectStatus'      => __('-- Select --'),
            'selectNone'        => __('-- None --'),

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

                $deptConfigs[$deptId] = array(
                    'start_trigger' => (string) ($deptCfg['start_trigger_status'] ?? ''),
                    'start_target'  => (string) ($deptCfg['start_target_status'] ?? ''),
                    'stop_target'   => (string) ($deptCfg['stop_target_status'] ?? ''),
                    'stop_transfer' => (string) ($deptCfg['stop_transfer_dept'] ?? ''),
                    'clear_team'    => !empty($deptCfg['clear_team']),
                );
            }

            if (empty($deptConfigs)) continue;

            // Custom labels and colors
            $startLabel = $config->get('start_label') ?: '';
            $stopLabel  = $config->get('stop_label') ?: '';
            $startColor = $config->get('start_color') ?: '';
            $stopColor  = $config->get('stop_color') ?: '';
            // Confirmation mode: none, confirm, countdown
            $confirmMode = $config->get('confirm_mode') ?: 'confirm';
            // Handle legacy BooleanField migration (1 = confirm, '' = none)
            if ($confirmMode === '1') $confirmMode = 'confirm';
            if ($confirmMode === '' || $confirmMode === '0') $confirmMode = 'none';
            $countdownSec = max(3, min(10, (int) ($config->get('countdown_seconds') ?: 5)));

            $widgets[] = array(
                'id'             => $instance->getId(),
                'topic'          => $topicId,
                'depts'          => $deptConfigs,
                'startLabel'     => $startLabel,
                'stopLabel'      => $stopLabel,
                'startColor'     => $startColor,
                'stopColor'      => $stopColor,
                'confirm'        => $confirmMode !== 'none', // backward compat
                'confirmMode'    => $confirmMode,
                'countdownSeconds' => $countdownSec,
            );
        }

        // Build ticket metadata
        $tickets = new \stdClass();
        $tids = $_REQUEST['tids'] ?? array();
        if (is_array($tids) && count($tids)) {
            $ids = array_filter(array_map('intval', $tids));
            if ($ids) {
                $in = implode(',', $ids);
                $res = db_query(
                    "SELECT ticket_id, topic_id, dept_id, status_id, staff_id, lastupdate FROM "
                    . TICKET_TABLE . " WHERE ticket_id IN ($in)");
                if ($res) {
                    $map = array();
                    while ($row = db_fetch_array($res)) {
                        $tid = (string) $row['ticket_id'];
                        $map[$tid] = array(
                            'topic'     => $row['topic_id'] ? (string) $row['topic_id'] : null,
                            'dept'      => $row['dept_id'] ? (string) $row['dept_id'] : null,
                            'status'    => $row['status_id'] ? (string) $row['status_id'] : null,
                            'staff'     => $row['staff_id'] ? (string) $row['staff_id'] : null,
                            'updated'   => $row['lastupdate'] ?: null,
                            'serverNow' => date('Y-m-d H:i:s'),
                        );
                    }
                    $tickets = (object) $map;
                }
            }
        }

        return $this->json_encode(array(
            'widgets' => $widgets,
            'tickets' => $tickets,
            'i18n'    => $this->getI18nStrings(),
            'perms'   => $this->getAgentPerms($thisstaff),
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
            'executingIn'       => __('Executing in %ss...'),
            'undo'         => __('Undo'),
            'undoExpired'  => __('Undo expired'),
            'bulkStart'    => __('Start Selected'),
            'bulkStop'     => __('Complete Selected'),
            'elapsed'      => __('elapsed'),
        );
    }

    private function getAgentPerms($thisstaff) {
        return array(
            'canAssign'   => $thisstaff->hasPerm(Ticket::PERM_ASSIGN, false),
            'canTransfer' => $thisstaff->hasPerm(Ticket::PERM_TRANSFER, false),
            'canRelease'  => $thisstaff->hasPerm(Ticket::PERM_RELEASE, false),
            'canManage'   => $thisstaff->canManageTickets(),
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
        if (!in_array($action, array('start', 'stop')))
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

        // Resolve targets
        $targetStatusId = ($action === 'start')
            ? ($deptCfg['start_target_status'] ?? null)
            : ($deptCfg['stop_target_status'] ?? null);

        $targetStatus = null;
        if ($targetStatusId) {
            $targetStatus = TicketStatus::lookup($targetStatusId);
            if (!$targetStatus)
                Http::response(400, $this->json_encode(
                    array('error' => __('Configured target status not found'))));
        }

        $transferDept = null;
        if ($action === 'stop' && !empty($deptCfg['stop_transfer_dept'])) {
            $transferDept = Dept::lookup($deptCfg['stop_transfer_dept']);
            if (!$transferDept)
                Http::response(400, $this->json_encode(
                    array('error' => __('Configured transfer department not found'))));
        }

        $clearTeam = ($action === 'stop' && !empty($deptCfg['clear_team']));

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

            if ($action === 'start') {
                $ok = $this->doStart($ticket, $thisstaff, $targetStatus, $ticketNum, $errors_list);
            } else {
                $ok = $this->doStop($ticket, $thisstaff, $targetStatus, $transferDept, $clearTeam, $ticketNum, $errors_list);
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
                'weeklyThroughput'  => __('Weekly Throughput'),
                'avgTimePerStep'    => __('Average Time Per Step'),
                'status'            => __('Status'),
                'avgTime'           => __('Avg Time'),
                'transitions'       => __('Transitions'),
                'agentLeaderboard'  => __('Agent Leaderboard'),
                'myPerformance'     => __('My Performance'),
                'currentQueue'      => __('Current Queue'),
                'myQueue'           => __('My Assigned Tickets'),
                'claimed'           => __('Claimed'),
                'noData'            => __('No data for this period'),
                'apply'             => __('Apply'),
            ),
        );

        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        $dir = dirname(__FILE__) . '/assets/';
        $css = file_exists($dir . 'workflow-dashboard.css') ? file_get_contents($dir . 'workflow-dashboard.css') : '';
        $js  = file_exists($dir . 'workflow-dashboard.js') ? file_get_contents($dir . 'workflow-dashboard.js') : '';

        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html>
<html lang="en">
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

    // ================================================================
    //  Dashboard API (JSON data)
    // ================================================================

    /**
     * GET /quick-buttons/dashboard
     *
     * Returns workflow metrics: throughput, avg time per step, agent stats.
     * Query params: days (default 30), OR from/to (YYYY-MM-DD)
     */
    function dashboard() {
        $thisstaff = $this->requireStaff();

        // Support custom date range (from/to) or quick period (days)
        $from = $_GET['from'] ?? '';
        $to   = $_GET['to'] ?? '';

        if ($from && $to && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)
                         && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $since = db_input($from) . ' 00:00:00';
            $until = db_input($to) . ' 23:59:59';
            $days  = max(1, (int) ((strtotime($to) - strtotime($from)) / 86400));
        } else {
            $days  = max(1, min(365, (int) ($_GET['days'] ?? 30)));
            $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));
            $until = date('Y-m-d H:i:s');
        }

        // Access control: match osTicket's built-in visibility rules
        // See Staff::getTicketsVisibility() in class.staff.php
        $visibilityWhere = self::buildVisibilitySQL($thisstaff);

        // 1. Tickets processed per day — filtered by dept
        $dailyRes = db_query(
            "SELECT DATE(e.timestamp) as day, COUNT(*) as cnt
             FROM ost_thread_event e
             JOIN ost_thread th ON e.thread_id = th.id AND th.object_type = 'T'
             JOIN ost_ticket tk ON th.object_id = tk.ticket_id
             WHERE e.event_id = 9 AND e.timestamp >= '{$since}' AND e.timestamp <= '{$until}'
               AND e.data LIKE '%\"status\"%'
               AND ({$visibilityWhere})
             GROUP BY DATE(e.timestamp)
             ORDER BY day");
        $daily = array();
        if ($dailyRes)
            while ($row = db_fetch_array($dailyRes))
                $daily[] = array('day' => $row['day'], 'count' => (int) $row['cnt']);

        // 2. Average time per step — filtered by dept
        $stepTimesRes = db_query(
            "SELECT th.object_id as ticket_id, e.data, e.timestamp
             FROM ost_thread_event e
             JOIN ost_thread th ON e.thread_id = th.id AND th.object_type = 'T'
             JOIN ost_ticket tk ON th.object_id = tk.ticket_id
             WHERE e.event_id = 9 AND e.timestamp >= '{$since}' AND e.timestamp <= '{$until}'
               AND e.data LIKE '%\"status\"%'
               AND ({$visibilityWhere})
             ORDER BY th.object_id, e.timestamp");
        $stepDurations = array();
        $prevByTicket = array();
        if ($stepTimesRes) {
            while ($row = db_fetch_array($stepTimesRes)) {
                $tid = $row['ticket_id'];
                $eventData = @json_decode($row['data'], true);
                $statusId = $eventData['status'] ?? null;
                if (!$statusId) continue;

                if (isset($prevByTicket[$tid])) {
                    $prev = $prevByTicket[$tid];
                    $duration = strtotime($row['timestamp']) - strtotime($prev['time']);
                    $key = $prev['status'];
                    if (!isset($stepDurations[$key]))
                        $stepDurations[$key] = array('total' => 0, 'count' => 0);
                    $stepDurations[$key]['total'] += $duration;
                    $stepDurations[$key]['count']++;
                }
                $prevByTicket[$tid] = array('status' => (string) $statusId, 'time' => $row['timestamp']);
            }
        }

        $avgTimes = array();
        foreach ($stepDurations as $statusId => $data) {
            $status = TicketStatus::lookup($statusId);
            $avgSec = $data['count'] > 0 ? round($data['total'] / $data['count']) : 0;
            $avgTimes[] = array(
                'statusId'   => $statusId,
                'statusName' => $status ? $status->getName() : __('Unknown'),
                'avgSeconds' => $avgSec,
                'avgDisplay' => self::formatDuration($avgSec),
                'count'      => $data['count'],
            );
        }

        // 3. Agent leaderboard — only agents in accessible depts
        $agentRes = db_query(
            "SELECT e.staff_id, s.firstname, s.lastname, COUNT(*) as cnt
             FROM ost_thread_event e
             JOIN ost_thread th ON e.thread_id = th.id AND th.object_type = 'T'
             JOIN ost_ticket tk ON th.object_id = tk.ticket_id
             LEFT JOIN ost_staff s ON e.staff_id = s.staff_id
             WHERE e.event_id = 4 AND e.timestamp >= '{$since}' AND e.timestamp <= '{$until}'
               AND ({$visibilityWhere})
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

        // 4. Current queue — only tickets in accessible depts
        $queueRes = db_query(
            "SELECT s.id, s.name, COUNT(*) as cnt
             FROM ost_ticket tk
             JOIN ost_ticket_status s ON tk.status_id = s.id
             WHERE s.state = 'open'
               AND ({$visibilityWhere})
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

        // Access mode flag — tells JS how to render
        $accessMode = 'full'; // admin or normal agent
        if ($thisstaff->isAccessLimited())
            $accessMode = 'limited'; // assigned_only agent

        return $this->json_encode(array(
            'days'       => $days,
            'daily'      => $daily,
            'avgTimes'   => $avgTimes,
            'agents'     => $agents,
            'queue'      => $queue,
            'accessMode' => $accessMode,
            'staffName'  => $thisstaff->getName()->getOriginal(),
            'i18n'       => array(
                'dashboard'      => __('Workflow Dashboard'),
                'ticketsPerDay'  => __('Tickets Processed Per Day'),
                'avgTimePerStep' => __('Average Time Per Step'),
                'agentLeader'    => __('Agent Leaderboard'),
                'myPerformance'  => __('My Performance'),
                'currentQueue'   => __('Current Queue'),
                'myQueue'        => __('My Assigned Tickets'),
                'status'         => __('Status'),
                'avgTime'        => __('Avg Time'),
                'transitions'    => __('Transitions'),
                'agent'          => __('Agent'),
                'claimed'        => __('Claimed'),
                'tickets'        => __('Tickets'),
                'last7days'      => __('Last 7 Days'),
                'last30days'     => __('Last 30 Days'),
                'last90days'     => __('Last 90 Days'),
            ),
        ));
    }

    /**
     * Build SQL WHERE clause matching osTicket's Staff::getTicketsVisibility()
     *
     * Replicates the ORM logic from class.staff.php in raw SQL:
     * - If access-limited (assigned_only): only assigned tickets
     * - Otherwise: dept-accessible tickets + assigned tickets + team tickets
     *
     * Expects the ticket table aliased as `tk` in the outer query.
     */
    private static function buildVisibilitySQL($staff) {
        $staffId = (int) $staff->getId();
        $conditions = array();

        // Always include tickets assigned to this agent
        $conditions[] = "tk.staff_id = {$staffId}";

        // Include tickets assigned to agent's teams
        $teams = array_filter($staff->getTeams());
        if ($teams) {
            $teamIn = implode(',', array_map('intval', $teams));
            $conditions[] = "tk.team_id IN ({$teamIn})";
        }

        // If NOT access-limited, also include all tickets in accessible depts
        if (!$staff->isAccessLimited()) {
            $depts = $staff->getDepts();
            if ($depts && count($depts)) {
                $deptIn = implode(',', array_map('intval', $depts));
                $conditions[] = "tk.dept_id IN ({$deptIn})";
            }
        }

        return implode(' OR ', $conditions);
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

    private function doStop($ticket, $thisstaff, $targetStatus, $transferDept, $clearTeam, $ticketNum, &$errors_list) {
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
}
