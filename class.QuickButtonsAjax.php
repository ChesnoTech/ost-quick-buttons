<?php
/**
 * Quick Buttons Plugin - AJAX Controller
 *
 * Handles all API endpoints: widget data, action execution,
 * admin config data, and static asset serving.
 *
 * @author  ChesnoTech
 * @version 2.1.0
 * @link    https://github.com/ChesnoTech/ost-quick-buttons
 */

require_once INCLUDE_DIR . 'class.ajax.php';
require_once INCLUDE_DIR . 'class.ticket.php';
require_once INCLUDE_DIR . 'class.forms.php';
require_once INCLUDE_DIR . 'class.dept.php';
require_once INCLUDE_DIR . 'class.list.php';
require_once INCLUDE_DIR . 'class.plugin.php';

class QuickButtonsAjax extends AjaxController {

    /**
     * Extract the key (ID) from a ChoiceField config value.
     *
     * ChoiceField stores values as JSON: {"9":"Platform Build (Open)"}
     * This returns just the key (e.g. "9").
     */
    private static function choiceKey($value) {
        if (!$value)
            return $value;
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

    /**
     * Parse widget_config JSON from a plugin instance config.
     * Strips any HTML tags left by Redactor WYSIWYG.
     */
    private static function parseWidgetConfig($config) {
        $raw = strip_tags($config->get('widget_config') ?: '');
        $data = @json_decode($raw, true);
        return is_array($data) ? $data : array();
    }

    /**
     * Find the Quick Buttons plugin record by install path.
     */
    private static function findPlugin() {
        return Plugin::objects()->findFirst(
            array('install_path' => 'plugins/quick-buttons'));
    }

    /**
     * Require authenticated staff. Returns the staff object or sends 403.
     */
    private function requireStaff() {
        global $thisstaff;
        if (!$thisstaff || !$thisstaff->isValid())
            Http::response(403, __('Access Denied'));
        return $thisstaff;
    }

    // ================================================================
    //  API Endpoints
    // ================================================================

    /**
     * GET /quick-buttons/admin-config-data
     *
     * Returns departments, statuses, and i18n strings for the admin
     * config matrix UI.
     */
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
                'select'        => __('-- Select --'),
            ),
        ));
    }

    /**
     * POST /quick-buttons/widgets
     *
     * Returns widget configs visible to the current agent,
     * plus ticket metadata (topic, dept, status) for client-side filtering.
     *
     * POST param: tids[] — ticket IDs to fetch metadata for.
     */
    function getWidgets() {
        $thisstaff = $this->requireStaff();

        $agentDepts = array_map('strval', $thisstaff->getDepts());
        $widgets = array();

        $plugin = self::findPlugin();
        if (!$plugin)
            return $this->json_encode(array(
                'widgets' => $widgets,
                'tickets' => new \stdClass(),
                'i18n'    => array('start' => __('Start'), 'done' => __('Done'), 'error' => __('Error'))));

        foreach ($plugin->getActiveInstances() as $instance) {
            $config = $instance->getConfig();
            if (!$config)
                continue;

            $topicId = self::choiceKey($config->get('topic_id'));
            if (!$topicId)
                continue;

            $data = self::parseWidgetConfig($config);
            if (empty($data['departments']))
                continue;

            $deptConfigs = array();
            foreach ($data['departments'] as $deptId => $deptCfg) {
                $deptId = (string) $deptId;
                if (empty($deptCfg['enabled']))
                    continue;
                if (!in_array($deptId, $agentDepts))
                    continue;

                $deptConfigs[$deptId] = array(
                    'start_trigger' => (string) ($deptCfg['start_trigger_status'] ?? ''),
                    'start_target'  => (string) ($deptCfg['start_target_status'] ?? ''),
                    'stop_target'   => (string) ($deptCfg['stop_target_status'] ?? ''),
                    'stop_transfer' => (string) ($deptCfg['stop_transfer_dept'] ?? ''),
                );
            }

            if (empty($deptConfigs))
                continue;

            $widgets[] = array(
                'id'    => $instance->getId(),
                'topic' => $topicId,
                'depts' => $deptConfigs,
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
                    "SELECT ticket_id, topic_id, dept_id, status_id FROM "
                    . TICKET_TABLE . " WHERE ticket_id IN ($in)");
                if ($res) {
                    $map = array();
                    while ($row = db_fetch_array($res)) {
                        $tid = (string) $row['ticket_id'];
                        $map[$tid] = array(
                            'topic'  => $row['topic_id'] ? (string) $row['topic_id'] : null,
                            'dept'   => $row['dept_id'] ? (string) $row['dept_id'] : null,
                            'status' => $row['status_id'] ? (string) $row['status_id'] : null,
                        );
                    }
                    $tickets = (object) $map;
                }
            }
        }

        return $this->json_encode(array(
            'widgets' => $widgets,
            'tickets' => $tickets,
            'i18n'    => array(
                'start' => __('Start'),
                'done'  => __('Done'),
                'error' => __('Error'),
            ),
        ));
    }

    /**
     * POST /quick-buttons/execute
     *
     * Execute Start or Stop action on selected tickets.
     *
     * POST params:
     *   widget_id  - Plugin instance ID
     *   action     - "start" or "stop"
     *   dept_id    - Department ID for config lookup
     *   tids[]     - Ticket IDs
     */
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

        // Validate department config
        $data = self::parseWidgetConfig($config);
        if (empty($data['departments'][$deptId]))
            Http::response(400, $this->json_encode(
                array('error' => __('No configuration for this department'))));

        $deptCfg = $data['departments'][$deptId];
        if (empty($deptCfg['enabled']))
            Http::response(400, $this->json_encode(
                array('error' => __('Department not enabled in this widget'))));

        // Validate agent access
        $agentDepts = array_map('strval', $thisstaff->getDepts());
        if (!in_array($deptId, $agentDepts))
            Http::response(403, $this->json_encode(
                array('error' => __('Access Denied'))));

        // Resolve target status
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

        // Resolve transfer department (stop only)
        $transferDept = null;
        if ($action === 'stop' && !empty($deptCfg['stop_transfer_dept'])) {
            $transferDept = Dept::lookup($deptCfg['stop_transfer_dept']);
            if (!$transferDept)
                Http::response(400, $this->json_encode(
                    array('error' => __('Configured transfer department not found'))));
        }

        // Process tickets
        $success = 0;
        $failed = 0;
        $errors_list = array();

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
            $ticketFailed = false;

            if ($action === 'start') {
                $ticketFailed = !$this->doStart($ticket, $thisstaff, $targetStatus, $ticketNum, $errors_list);
            } else {
                $ticketFailed = !$this->doStop($ticket, $thisstaff, $targetStatus, $transferDept, $ticketNum, $errors_list);
            }

            if ($ticketFailed) $failed++;
            else $success++;
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
            'success' => $success,
            'failed'  => $failed,
            'errors'  => $errors_list,
            'message' => $message,
        )));
    }

    // ================================================================
    //  Action Sequences
    // ================================================================

    /**
     * Execute Start sequence: Claim + Change Status.
     * Returns true on success, false on failure.
     */
    private function doStart($ticket, $thisstaff, $targetStatus, $ticketNum, &$errors_list) {
        $result = $this->actionClaim($ticket, $thisstaff);
        if ($result !== true) {
            $errors_list[] = sprintf(__('Ticket #%s: Claim failed — %s'), $ticketNum, $result);
            return false;
        }

        if ($targetStatus) {
            $result = $this->actionChangeStatus($ticket, $targetStatus, $thisstaff);
            if ($result !== true) {
                $errors_list[] = sprintf(__('Ticket #%s: Status change failed — %s'), $ticketNum, $result);
                return false;
            }
        }

        return true;
    }

    /**
     * Execute Stop sequence: Change Status + Release Agent + Transfer.
     * Returns true on success, false on failure.
     */
    private function doStop($ticket, $thisstaff, $targetStatus, $transferDept, $ticketNum, &$errors_list) {
        if ($targetStatus) {
            $result = $this->actionChangeStatus($ticket, $targetStatus, $thisstaff);
            if ($result !== true) {
                $errors_list[] = sprintf(__('Ticket #%s: Status change failed — %s'), $ticketNum, $result);
                return false;
            }
        }

        if ($ticket->getStaffId()) {
            $result = $this->actionReleaseStaff($ticket, $thisstaff);
            if ($result !== true)
                $errors_list[] = sprintf(__('Ticket #%s: Agent release warning — %s'), $ticketNum, $result);
        }

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
        $this->serveFile(
            dirname(__FILE__) . '/assets/quick-buttons.js',
            'application/javascript; charset=UTF-8');
    }

    function serveCss() {
        $isOSTA = is_dir(rtrim(ROOT_DIR, '/') . '/osta');
        $cssFile = $isOSTA ? 'quick-buttons.css' : 'quick-buttons-default.css';
        $this->serveFile(
            dirname(__FILE__) . '/assets/' . $cssFile,
            'text/css; charset=UTF-8');
    }

    function serveAdminJs() {
        $this->serveFile(
            dirname(__FILE__) . '/assets/quick-buttons-admin.js',
            'application/javascript; charset=UTF-8',
            3600);
    }

    function serveAdminCss() {
        $this->serveFile(
            dirname(__FILE__) . '/assets/quick-buttons-admin.css',
            'text/css; charset=UTF-8',
            3600);
    }
}
