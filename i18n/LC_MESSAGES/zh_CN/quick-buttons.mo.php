<?php return array(
  '' => 'Project-Id-Version: ost-quick-buttons
Language: zh_CN
MIME-Version: 1.0
Content-Type: text/plain; charset=UTF-8
Content-Transfer-Encoding: 8bit
Plural-Forms: nplurals=1; plural=0;
',

  // Config labels
  'Help Topic' => '帮助主题',
  'Start Button Label' => '开始按钮标签',
  'Stop Button Label' => '完成按钮标签',
  'Start Button Color' => '开始按钮颜色',
  'Stop Button Color' => '完成按钮颜色',
  'Confirmation Mode' => '确认方式',
  'None — Execute immediately' => '无确认 — 立即执行',
  'Confirm Dialog — Requires explicit click' => '确认对话框 — 需要明确点击',
  'Countdown — Auto-execute with cancel window' => '倒计时 — 自动执行，可取消',
  'How to confirm actions before execution.' => '执行前如何确认操作。',
  'Countdown Seconds' => '倒计时秒数',
  'Widget Configuration' => '组件配置',

  // Frontend buttons
  'Start' => '开始',
  'Done' => '完成',
  'Error' => '错误',
  'Confirm' => '确认',
  'Cancel' => '取消',
  'Start working on ticket #%s?' => '开始处理工单 #%s？',
  'Complete and hand off ticket #%s?' => '完成并移交工单 #%s？',
  'Claim ticket and change status to working' => '认领工单并将状态改为"处理中"',
  'Change status, release agent and transfer' => '更改状态、释放客服并转移',
  'Executing in %ss...' => '%s 秒后执行...',
  'Undo' => '撤销',
  'Undo expired' => '撤销已过期',
  'Start Selected' => '开始所选',
  'Complete Selected' => '完成所选',
  'elapsed' => '已用时',

  // Admin matrix
  'Department' => '部门',
  'Enabled' => '已启用',
  'Start: Trigger Status' => '开始：触发状态',
  'Start: Target Status' => '开始：目标状态',
  'Stop: Target Status' => '完成：目标状态',
  'Stop: Transfer To' => '完成：转移至',
  'Clear Team' => '清除团队',
  '-- Select --' => '-- 请选择 --',
  '-- None --' => '-- 无 --',

  // Workflow Builder
  'Workflow Builder' => '流程编辑器',
  'Back' => '返回',
  'Search departments...' => '搜索部门...',
  'Enable All' => '全部启用',
  'Disable All' => '全部禁用',
  '%d / %d enabled' => '已启用 %d / %d',
  'Trigger' => '触发条件',
  'Working' => '处理中',
  'Transfer to:' => '转移至：',
  'Clear team on transfer' => '转移时清除团队',
  'Copy to...' => '复制到...',
  'Apply template...' => '应用模板...',
  'Single Step' => '单步操作',
  'Assembly Step 1 (no transfer)' => '组装步骤 1（不转移）',
  'Assembly Step 2 (with transfer)' => '组装步骤 2（含转移）',

  // Validation
  'Trigger status is required' => '触发状态为必填项',
  'Working status is required' => '"处理中"状态为必填项',
  'Done status is required' => '"完成"状态为必填项',
  'Trigger and Working are the same status (Start button will do nothing visible)' => '触发状态与"处理中"相同（开始按钮不会产生可见效果）',
  'Done status equals Trigger — this creates an infinite loop' => '"完成"状态与触发状态相同 — 这会导致无限循环',
  'Working and Done are the same status (Stop button will do nothing visible)' => '"处理中"与"完成"状态相同（完成按钮不会产生可见效果）',

  // Footer / Save
  'No unsaved changes' => '没有未保存的更改',
  'Unsaved changes' => '有未保存的更改',
  'All changes saved' => '所有更改已保存',
  'Save Changes' => '保存更改',
  'Saving...' => '正在保存...',
  'Saved!' => '已保存！',
  'Save failed' => '保存失败',
  'Network error' => '网络错误',

  // Dialogs
  'Discard unsaved changes?' => '要放弃未保存的更改吗？',
  'Copy this configuration to which department?' => '将此配置复制到哪个部门？',
  'Department not found: %s' => '未找到部门：%s',
  'Copied to %s' => '已复制到 %s',
  'Template applied — select statuses for each step' => '模板已应用 — 请为每个步骤选择状态',
  'Loading dashboard...' => '正在加载面板...',

  // Errors
  'Access Denied' => '拒绝访问',
  'Instance ID required' => '需要实例 ID',
  'Plugin not found' => '未找到插件',
  'Instance not found' => '未找到实例',
  'Invalid JSON' => '无效的 JSON',
  'Validation failed' => '验证失败',
  'Configuration saved' => '配置已保存',
  'Invalid widget' => '无效的组件',
  'Invalid action type' => '无效的操作类型',
  'No tickets selected' => '未选择工单',
  'Invalid or disabled widget' => '无效或已禁用的组件',
  'Invalid plugin instance' => '无效的插件实例',
  'Configuration error' => '配置错误',
  'Custom' => '自定义',

  // Config hints
  'Each widget handles one help topic. Tickets with this topic will show Start/Stop buttons.' => '每个组件处理一个帮助主题。具有该主题的工单将显示开始/完成按钮。',
  'Custom label for the Start button tooltip. Leave empty for default ("Start").' => '开始按钮提示的自定义标签。留空则使用默认值（"开始"）。',
  'Custom label for the Stop button tooltip. Leave empty for default ("Done").' => '完成按钮提示的自定义标签。留空则使用默认值（"完成"）。',
  'Hex color for Start button (e.g. #128DBE). Leave empty for default blue.' => '开始按钮的十六进制颜色（例如 #128DBE）。留空则使用默认蓝色。',
  'Hex color for Stop button (e.g. #27ae60). Leave empty for default green.' => '完成按钮的十六进制颜色（例如 #27ae60）。留空则使用默认绿色。',
  'Per-department button configuration (managed by the UI below).' => '按部门的按钮配置（由下方界面管理）。',
);
