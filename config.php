<?php

require_once INCLUDE_DIR . 'class.forms.php';
require_once INCLUDE_DIR . 'class.list.php';
require_once INCLUDE_DIR . 'class.dept.php';
require_once INCLUDE_DIR . 'class.topic.php';

class QuickButtonsConfig extends PluginConfig {

    function getOptions() {

        // Build help topic choices
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
            'widget_config' => new TextboxField(array(
                'label' => __('Widget Configuration'),
                'required' => false,
                'default' => '{}',
                'hint' => __('Per-department button configuration (managed by the UI below).'),
                'configuration' => array(
                    'size' => 80,
                    'length' => 65000,
                ),
            )),
        );
    }

    /**
     * Parse the widget_config JSON into a PHP array.
     */
    function getWidgetConfig() {
        $raw = $this->get('widget_config');
        if (!$raw)
            return array('departments' => array());
        // Strip any HTML tags (Redactor may have wrapped content in <p> tags)
        $raw = strip_tags($raw);
        $data = @json_decode($raw, true);
        if (!is_array($data))
            return array('departments' => array());
        return $data;
    }

    function pre_save(&$config, &$errors) {

        // Topic is required
        if (empty($config['topic_id'])) {
            $errors['err'] = __('A help topic must be selected');
            return false;
        }

        // Parse widget config JSON (strip any HTML tags from Redactor)
        $raw = $config['widget_config'] ?? '{}';
        $raw = strip_tags($raw);
        $data = @json_decode($raw, true);
        if (!is_array($data)) {
            $errors['err'] = __('Invalid widget configuration JSON');
            return false;
        }

        $depts = $data['departments'] ?? array();

        // Check at least one department is enabled
        $hasEnabled = false;
        foreach ($depts as $deptId => $deptCfg) {
            if (empty($deptCfg['enabled']))
                continue;
            $hasEnabled = true;

            // Validate required fields for enabled departments
            if (empty($deptCfg['start_trigger_status'])) {
                $errors['err'] = sprintf(
                    __('Department %s: Start trigger status is required'),
                    $deptId);
                return false;
            }
            if (empty($deptCfg['start_target_status'])) {
                $errors['err'] = sprintf(
                    __('Department %s: Start target status is required'),
                    $deptId);
                return false;
            }
            if (empty($deptCfg['stop_target_status'])) {
                $errors['err'] = sprintf(
                    __('Department %s: Stop target status is required'),
                    $deptId);
                return false;
            }
            if (empty($deptCfg['stop_transfer_dept'])) {
                $errors['err'] = sprintf(
                    __('Department %s: Stop transfer department is required'),
                    $deptId);
                return false;
            }

            // Validate statuses exist
            foreach (array('start_trigger_status', 'start_target_status', 'stop_target_status') as $field) {
                $sid = $deptCfg[$field];
                if (!TicketStatus::lookup($sid)) {
                    $errors['err'] = sprintf(
                        __('Department %s: Status ID %s not found'),
                        $deptId, $sid);
                    return false;
                }
            }

            // Validate transfer dept exists
            if (!Dept::lookup($deptCfg['stop_transfer_dept'])) {
                $errors['err'] = sprintf(
                    __('Department %s: Transfer department not found'),
                    $deptId);
                return false;
            }
        }

        // Allow saving with no enabled departments (initial setup)
        return true;
    }
}
