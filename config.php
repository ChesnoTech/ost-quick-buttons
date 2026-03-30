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

            $variant = $deptCfg['variant'] ?? 'single';

            // Required for all variants: trigger and target statuses
            // stop_target_status is only used in single-step (two-step uses step2_stop_target_status)
            $requiredFields = array('start_trigger_status', 'start_target_status');
            if ($variant !== 'twostep')
                $requiredFields[] = 'stop_target_status';
            foreach ($requiredFields as $field) {
                if (empty($deptCfg[$field])) {
                    $errors['err'] = sprintf(
                        __('Department %s: %s is required'),
                        $deptId, $field);
                    return false;
                }
                if (!TicketStatus::lookup($deptCfg[$field])) {
                    $errors['err'] = sprintf(
                        __('Department %s: Status ID %s not found'),
                        $deptId, $deptCfg[$field]);
                    return false;
                }
            }

            // Two-step variant: validate additional status fields
            if ($variant === 'twostep') {
                foreach (array('step2_trigger_status', 'step2_target_status', 'step2_stop_target_status') as $field) {
                    if (empty($deptCfg[$field])) {
                        $errors['err'] = sprintf(
                            __('Department %s: %s is required for two-step variant'),
                            $deptId, $field);
                        return false;
                    }
                    if (!TicketStatus::lookup($deptCfg[$field])) {
                        $errors['err'] = sprintf(
                            __('Department %s: Status ID %s not found'),
                            $deptId, $deptCfg[$field]);
                        return false;
                    }
                }

                // Validate no loops: final done != trigger
                if ($deptCfg['step2_stop_target_status'] === $deptCfg['start_trigger_status']) {
                    $errors['err'] = sprintf(
                        __('Department %s: Final done status cannot equal trigger (creates loop)'),
                        $deptId);
                    return false;
                }
            }

            // Transfer dept is optional (empty = no transfer)
            if (!empty($deptCfg['stop_transfer_dept'])) {
                if (!Dept::lookup($deptCfg['stop_transfer_dept'])) {
                    $errors['err'] = sprintf(
                        __('Department %s: Transfer department not found'),
                        $deptId);
                    return false;
                }
            }

            // Validate label lengths (max 12 chars)
            foreach (array('start_label', 'stop_label', 'partial_label', 'start2_label', 'finish_label') as $labelField) {
                if (!empty($deptCfg[$labelField]) && mb_strlen($deptCfg[$labelField]) > 12) {
                    $errors['err'] = sprintf(
                        __('Department %s: Button label "%s" exceeds 12 characters'),
                        $deptId, $labelField);
                    return false;
                }
            }
        }

        return true;
    }
}
