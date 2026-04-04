<?php
/**
 * Quick Buttons Plugin - Configuration
 *
 * @author  ChesnoTech
 * @version 4.0.0
 */

require_once INCLUDE_DIR . 'class.forms.php';
require_once INCLUDE_DIR . 'class.list.php';
require_once INCLUDE_DIR . 'class.dept.php';
require_once INCLUDE_DIR . 'class.topic.php';

class QuickButtonsConfig extends PluginConfig {

    function getOptions() {

        $topics = array('' => '— ' . __('Select Help Topic') . ' —');
        global $cfg;
        if ($cfg) {
            foreach (Topic::getHelpTopics() as $id => $name)
                $topics[$id] = $name;
        }

        return array(
            'topic_id' => new ChoiceField(array(
                'label' => __('Help Topic'),
                'required' => true,
                'choices' => $topics,
                'hint' => __('Each widget handles one help topic. Tickets with this topic will show Start/Stop buttons.'),
            )),
            'start_color' => new TextboxField(array(
                'label' => __('Start Button Color'),
                'required' => false,
                'default' => '',
                'hint' => __('Hex color for Start button (e.g. #128DBE). Leave empty for default blue.'),
                'configuration' => array('size' => 10, 'length' => 7),
            )),
            'stop_color' => new TextboxField(array(
                'label' => __('Stop Button Color'),
                'required' => false,
                'default' => '',
                'hint' => __('Hex color for Stop button (e.g. #27ae60). Leave empty for default green.'),
                'configuration' => array('size' => 10, 'length' => 7),
            )),
            'confirm_mode' => new ChoiceField(array(
                'label' => __('Confirmation Mode'),
                'required' => true,
                'default' => 'confirm',
                'choices' => array(
                    'none'      => __('None — Execute immediately'),
                    'confirm'   => __('Confirm Dialog — Requires explicit click'),
                    'countdown' => __('Countdown — Auto-execute with cancel window'),
                ),
                'hint' => __('How to confirm actions before execution.'),
            )),
            'countdown_seconds' => new TextboxField(array(
                'label' => __('Countdown Seconds'),
                'required' => false,
                'default' => '5',
                'hint' => __('Seconds before auto-execute in Countdown mode (3–10). Only used when mode is "Countdown".'),
                'configuration' => array('size' => 5, 'length' => 2),
            )),
            'show_deadline' => new BooleanField(array(
                'label' => __('Show Deadline Countdown'),
                'default' => 0,
                'hint' => __('Display a countdown to the ticket deadline (SLA or manual due date) below each button.'),
            )),
            'widget_config' => new TextboxField(array(
                'label' => __('Widget Configuration'),
                'required' => false,
                'default' => '{}',
                'hint' => __('Per-department button configuration (managed by the UI below).'),
                'configuration' => array('size' => 80, 'length' => 65000),
            )),
        );
    }

    function getWidgetConfig() {
        $raw = $this->get('widget_config');
        if (!$raw)
            return array('departments' => array());
        $raw = strip_tags($raw);
        $data = @json_decode($raw, true);
        if (!is_array($data))
            return array('departments' => array());
        return $data;
    }

    function pre_save(&$config, &$errors) {

        if (empty($config['topic_id'])) {
            $errors['err'] = __('A help topic must be selected');
            return false;
        }

        // Validate colors if provided
        foreach (array('start_color', 'stop_color') as $field) {
            if (!empty($config[$field])) {
                $color = trim($config[$field]);
                if (!preg_match('/^#[0-9A-Fa-f]{3,6}$/', $color)) {
                    $errors['err'] = __('Button color must be a valid hex color (e.g., #128DBE)');
                    return false;
                }
            }
        }

        $raw = $config['widget_config'] ?? '{}';
        $raw = strip_tags($raw);
        $data = @json_decode($raw, true);
        if (!is_array($data)) {
            $errors['err'] = __('Invalid widget configuration JSON');
            return false;
        }

        $depts = $data['departments'] ?? array();

        foreach ($depts as $deptId => $deptCfg) {
            if (empty($deptCfg['enabled']))
                continue;

            // Normalize legacy format before validating
            $deptCfg = QuickButtonsPlugin::normalizeDeptConfig($deptCfg);
            $steps = $deptCfg['steps'] ?? array();

            // Must have at least 1 step
            if (empty($steps)) {
                $errors['err'] = sprintf(
                    __('Department %s: At least one step is required'),
                    $deptId);
                return false;
            }

            // Max 10 steps
            if (count($steps) > 10) {
                $errors['err'] = sprintf(
                    __('Department %s: Maximum 10 steps allowed'),
                    $deptId);
                return false;
            }

            $triggersSeen = array();
            foreach ($steps as $idx => $step) {
                $stepNum = $idx + 1;

                // Required: trigger_status and target_status
                if (empty($step['trigger_status'])) {
                    $errors['err'] = sprintf(
                        __('Department %s, Step %d: Trigger status is required'),
                        $deptId, $stepNum);
                    return false;
                }
                if (empty($step['target_status'])) {
                    $errors['err'] = sprintf(
                        __('Department %s, Step %d: Target status is required'),
                        $deptId, $stepNum);
                    return false;
                }

                // Validate status IDs exist
                if (!TicketStatus::lookup($step['trigger_status'])) {
                    $errors['err'] = sprintf(
                        __('Department %s, Step %d: Trigger status ID %s not found'),
                        $deptId, $stepNum, $step['trigger_status']);
                    return false;
                }
                if (!TicketStatus::lookup($step['target_status'])) {
                    $errors['err'] = sprintf(
                        __('Department %s, Step %d: Target status ID %s not found'),
                        $deptId, $stepNum, $step['target_status']);
                    return false;
                }

                // Trigger != target (no-op check)
                if ((string)$step['trigger_status'] === (string)$step['target_status']) {
                    $errors['err'] = sprintf(
                        __('Department %s, Step %d: Trigger and target status are the same'),
                        $deptId, $stepNum);
                    return false;
                }

                // No duplicate triggers within a department
                $triggerKey = (string)$step['trigger_status'];
                if (isset($triggersSeen[$triggerKey])) {
                    $errors['err'] = sprintf(
                        __('Department %s: Duplicate trigger status in steps %d and %d'),
                        $deptId, $triggersSeen[$triggerKey], $stepNum);
                    return false;
                }
                $triggersSeen[$triggerKey] = $stepNum;

                // Behavior validation
                if (!empty($step['behavior'])
                        && !in_array($step['behavior'], array('claim', 'release', 'none'))) {
                    $errors['err'] = sprintf(
                        __('Department %s, Step %d: Invalid behavior "%s"'),
                        $deptId, $stepNum, $step['behavior']);
                    return false;
                }

                // Transfer dept exists (if set)
                if (!empty($step['transfer_dept'])) {
                    if (!Dept::lookup($step['transfer_dept'])) {
                        $errors['err'] = sprintf(
                            __('Department %s, Step %d: Transfer department not found'),
                            $deptId, $stepNum);
                        return false;
                    }
                }

                // Label length
                if (!empty($step['label']) && mb_strlen($step['label']) > 12) {
                    $errors['err'] = sprintf(
                        __('Department %s, Step %d: Label exceeds 12 characters'),
                        $deptId, $stepNum);
                    return false;
                }
            }

            // Loop detection: last step's target != first step's trigger
            $firstTrigger = (string)$steps[0]['trigger_status'];
            $lastTarget = (string)$steps[count($steps) - 1]['target_status'];
            if ($firstTrigger === $lastTarget) {
                $errors['err'] = sprintf(
                    __('Department %s: Final target status equals initial trigger (creates loop)'),
                    $deptId);
                return false;
            }
        }

        return true;
    }
}
