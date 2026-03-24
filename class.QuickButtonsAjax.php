<?php

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
     * ChoiceField stores values as JSON objects like {"9":"Platform Build (Open)"}
     * or as plain scalars. This returns just the key (e.g. "9").
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
     * GET /quick-buttons/admin-config-data
     *
     * Returns departments and statuses for the admin config matrix UI.
     * Only accessible to staff with admin access.
     */
    function getAdminConfigData() {
        global $thisstaff;

        if (!$thisstaff || !$thisstaff->isValid())
            Http::response(403, __('Access Denied'));

        // Build departments list
        $departments = array();
        foreach (Dept::getDepartments() as $id => $name) {
            $departments[] = array('id' => (string) $id, 'name' => $name);
        }

        // Build statuses list (enabled only)
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
                'department'        => __('Department'),
                'enabled'           => __('Enabled'),
                'start_trigger'     => __('Start: Trigger Status'),
                'start_target'      => __('Start: Target Status'),
                'stop_target'       => __('Stop: Target Status'),
                'stop_transfer'     => __('Stop: Transfer To'),
                'select'            => __('-- Select --'),
                'load_error'        => __('Failed to load departments/statuses. Save the instance first, then reload.'),
            ),
        ));
    }

    /**
     * GET/POST /quick-buttons/widgets
     *
     * Returns widget configs visible to the current agent,
     * plus ticket metadata (topic, dept, status) for client-side filtering.
     *
     * Optional param: tids[] — ticket IDs to fetch metadata for.
     */
    function getWidgets() {
        global $thisstaff;

        if (!$thisstaff || !$thisstaff->isValid())
            Http::response(403, __('Access Denied'));

        // Agent's accessible department IDs
        $agentDepts = array_map('strval', $thisstaff->getDepts());

        $widgets = array();

        // Find Quick Buttons plugin by install path
        $plugin = Plugin::objects()->findFirst(
            array('install_path' => 'plugins/quick-buttons'));

        if (!$plugin)
            return $this->json_encode(array(
                'widgets' => $widgets,
                'tickets' => new \stdClass()));

        // Iterate all active instances (widgets)
        foreach ($plugin->getActiveInstances() as $instance) {
            $config = $instance->getConfig();
            if (!$config)
                continue;

            $topicId = self::choiceKey($config->get('topic_id'));
            if (!$topicId)
                continue;

            // Parse widget config JSON (strip any stale HTML wrapping)
            $raw = strip_tags($config->get('widget_config') ?: '');
            $data = @json_decode($raw, true);
            if (!is_array($data) || empty($data['departments']))
                continue;

            // Filter departments: only enabled ones the agent can access
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

        // Build ticket metadata: topic_id, dept_id, status_id
        $tickets = new \stdClass();
        $tids = $_REQUEST['tids'] ?? array();
        if (is_array($tids) && count($tids)) {
            $ids = array_map('intval', $tids);
            $ids = array_filter($ids);
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
                'start'   => __('Start'),
                'done'    => __('Done'),
                'error'   => __('Error'),
            ),
        ));
    }

    /**
     * POST /quick-buttons/execute
     *
     * Execute Start or Stop action on selected tickets.
     *
     * POST params:
     *   widget_id  - ID of the plugin instance (widget)
     *   action     - "start" or "stop"
     *   dept_id    - Department ID for config lookup
     *   tids[]     - Array of ticket IDs
     */
    function execute() {
        global $thisstaff;

        if (!$thisstaff || !$thisstaff->isValid())
            Http::response(403, __('Access Denied'));

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

        // Load the widget instance
        $instance = PluginInstance::lookup($widgetId);
        if (!$instance || !$instance->isEnabled())
            Http::response(400, $this->json_encode(
                array('error' => __('Invalid or disabled widget'))));

        // Verify this instance belongs to the Quick Buttons plugin
        $plugin = Plugin::objects()->findFirst(
            array('install_path' => 'plugins/quick-buttons'));
        if (!$plugin || $instance->getPluginId() != $plugin->getId())
            Http::response(403, $this->json_encode(
                array('error' => __('Invalid plugin instance'))));

        $config = $instance->getConfig();
        if (!$config)
            Http::response(500, $this->json_encode(
                array('error' => __('Configuration error'))));

        // Parse widget config and find dept config
        $raw = strip_tags($config->get('widget_config') ?: '');
        $data = @json_decode($raw, true);
        if (!is_array($data) || empty($data['departments'][$deptId]))
            Http::response(400, $this->json_encode(
                array('error' => __('No configuration for this department'))));

        $deptCfg = $data['departments'][$deptId];
        if (empty($deptCfg['enabled']))
            Http::response(400, $this->json_encode(
                array('error' => __('Department not enabled in this widget'))));

        // Verify agent has access to this department
        $agentDepts = array_map('strval', $thisstaff->getDepts());
        if (!in_array($deptId, $agentDepts))
            Http::response(403, $this->json_encode(
                array('error' => __('Access Denied'))));

        // Resolve target statuses and departments
        if ($action === 'start') {
            $targetStatusId = $deptCfg['start_target_status'] ?? null;
        } else {
            $targetStatusId = $deptCfg['stop_target_status'] ?? null;
        }

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
                // --- START: Claim + Change Status ---

                // 1. Claim ticket
                $result = $this->actionClaim($ticket, $thisstaff);
                if ($result !== true) {
                    $ticketFailed = true;
                    $errors_list[] = sprintf(
                        __('Ticket #%s: Claim failed — %s'),
                        $ticketNum, $result);
                }

                // 2. Change status
                if (!$ticketFailed && $targetStatus) {
                    $result = $this->actionChangeStatus($ticket, $targetStatus, $thisstaff);
                    if ($result !== true) {
                        $ticketFailed = true;
                        $errors_list[] = sprintf(
                            __('Ticket #%s: Status change failed — %s'),
                            $ticketNum, $result);
                    }
                }

            } else {
                // --- STOP: Change Status + Release + Transfer ---

                // 1. Change status
                if ($targetStatus) {
                    $result = $this->actionChangeStatus($ticket, $targetStatus, $thisstaff);
                    if ($result !== true) {
                        $ticketFailed = true;
                        $errors_list[] = sprintf(
                            __('Ticket #%s: Status change failed — %s'),
                            $ticketNum, $result);
                    }
                }

                // 2. Release agent
                if (!$ticketFailed && $ticket->getStaffId()) {
                    $result = $this->actionReleaseStaff($ticket, $thisstaff);
                    if ($result !== true) {
                        // Non-fatal warning
                        $errors_list[] = sprintf(
                            __('Ticket #%s: Agent release warning — %s'),
                            $ticketNum, $result);
                    }
                }

                // 3. Transfer
                if (!$ticketFailed && $transferDept) {
                    $result = $this->actionTransfer($ticket, $transferDept, $thisstaff);
                    if ($result !== true) {
                        $ticketFailed = true;
                        $errors_list[] = sprintf(
                            __('Ticket #%s: Transfer failed — %s'),
                            $ticketNum, $result);
                    }
                }
            }

            if ($ticketFailed)
                $failed++;
            else
                $success++;
        }

        // Build response
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

    // ---- Action Helpers (unchanged from v1.3) ----

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
        $vars = array('assignee' => array($id));
        $form = ClaimForm::instantiate($vars);
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
        $comment = sprintf(__('Status changed via Quick Buttons to: %s'),
            $targetStatus->getName());

        if (!$ticket->setStatus($targetStatus, $comment, $errors))
            return $errors['err'] ?: __('Unable to change status');

        return true;
    }

    private function actionTransfer($ticket, $targetDept, $thisstaff) {
        if (!$ticket->checkStaffPerm($thisstaff, Ticket::PERM_TRANSFER))
            return __('Permission denied');

        if ($ticket->getDeptId() == $targetDept->getId())
            return true;

        $vars = array('dept' => $targetDept->getId());
        $form = TransferForm::instantiate($vars);

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

    // ---- Static Asset Serving ----

    function serveJs() {
        $file = dirname(__FILE__) . '/assets/quick-buttons.js';
        if (!file_exists($file))
            Http::response(404, 'Not found');

        $etag = '"qa-js-' . filemtime($file) . '-' . filesize($file) . '"';
        header('Content-Type: application/javascript; charset=UTF-8');
        header('Cache-Control: public, max-age=86400');
        header('ETag: ' . $etag);
        if (isset($_SERVER['HTTP_IF_NONE_MATCH'])
                && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
            Http::response(304, '');
            exit;
        }
        readfile($file);
        exit;
    }

    function serveCss() {
        // Auto-detect theme: osTicketAwesome uses SVG icons, default uses Font Awesome
        $isOSTA = is_dir(rtrim(ROOT_DIR, '/') . '/osta');
        $cssFile = $isOSTA ? 'quick-buttons.css' : 'quick-buttons-default.css';
        $file = dirname(__FILE__) . '/assets/' . $cssFile;
        if (!file_exists($file))
            Http::response(404, 'Not found');

        $etag = '"qa-css-' . filemtime($file) . '-' . filesize($file) . '"';
        header('Content-Type: text/css; charset=UTF-8');
        header('Cache-Control: public, max-age=86400');
        header('ETag: ' . $etag);
        if (isset($_SERVER['HTTP_IF_NONE_MATCH'])
                && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
            Http::response(304, '');
            exit;
        }
        readfile($file);
        exit;
    }

    function serveAdminJs() {
        $file = dirname(__FILE__) . '/assets/quick-buttons-admin.js';
        if (!file_exists($file))
            Http::response(404, 'Not found');

        $etag = '"qa-ajs-' . filemtime($file) . '-' . filesize($file) . '"';
        header('Content-Type: application/javascript; charset=UTF-8');
        header('Cache-Control: public, max-age=3600');
        header('ETag: ' . $etag);
        if (isset($_SERVER['HTTP_IF_NONE_MATCH'])
                && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
            Http::response(304, '');
            exit;
        }
        readfile($file);
        exit;
    }

    function serveAdminCss() {
        $file = dirname(__FILE__) . '/assets/quick-buttons-admin.css';
        if (!file_exists($file))
            Http::response(404, 'Not found');

        $etag = '"qa-acss-' . filemtime($file) . '-' . filesize($file) . '"';
        header('Content-Type: text/css; charset=UTF-8');
        header('Cache-Control: public, max-age=3600');
        header('ETag: ' . $etag);
        if (isset($_SERVER['HTTP_IF_NONE_MATCH'])
                && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
            Http::response(304, '');
            exit;
        }
        readfile($file);
        exit;
    }
}
