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
            $confirm    = (bool) $config->get('confirm_actions');

            $widgets[] = array(
                'id'          => $instance->getId(),
                'topic'       => $topicId,
                'depts'       => $deptConfigs,
                'startLabel'  => $startLabel,
                'stopLabel'   => $stopLabel,
                'startColor'  => $startColor,
                'stopColor'   => $stopColor,
                'confirm'     => $confirm,
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
                    "SELECT ticket_id, topic_id, dept_id, status_id, staff_id FROM "
                    . TICKET_TABLE . " WHERE ticket_id IN ($in)");
                if ($res) {
                    $map = array();
                    while ($row = db_fetch_array($res)) {
                        $tid = (string) $row['ticket_id'];
                        $map[$tid] = array(
                            'topic'  => $row['topic_id'] ? (string) $row['topic_id'] : null,
                            'dept'   => $row['dept_id'] ? (string) $row['dept_id'] : null,
                            'status' => $row['status_id'] ? (string) $row['status_id'] : null,
                            'staff'  => $row['staff_id'] ? (string) $row['staff_id'] : null,
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

            if ($action === 'start') {
                $ok = $this->doStart($ticket, $thisstaff, $targetStatus, $ticketNum, $errors_list);
            } else {
                $ok = $this->doStop($ticket, $thisstaff, $targetStatus, $transferDept, $clearTeam, $ticketNum, $errors_list);
            }

            if ($ok) $success++;
            else $failed++;
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
