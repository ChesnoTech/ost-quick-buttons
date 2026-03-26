<?php return array(
  '' => 'Project-Id-Version: ost-quick-buttons
Language: ar
MIME-Version: 1.0
Content-Type: text/plain; charset=UTF-8
Content-Transfer-Encoding: 8bit
Plural-Forms: nplurals=6; plural=(n==0 ? 0 : n==1 ? 1 : n==2 ? 2 : n%100>=3 && n%100<=10 ? 3 : n%100>=11 ? 4 : 5);
',

  // Config labels
  'Help Topic' => 'موضوع المساعدة',
  'Start Button Label' => 'تسمية زر البدء',
  'Stop Button Label' => 'تسمية زر الإيقاف',
  'Start Button Color' => 'لون زر البدء',
  'Stop Button Color' => 'لون زر الإيقاف',
  'Confirmation Mode' => 'وضع التأكيد',
  'None — Execute immediately' => 'بدون — تنفيذ فوري',
  'Confirm Dialog — Requires explicit click' => 'نافذة تأكيد — يتطلب نقرة صريحة',
  'Countdown — Auto-execute with cancel window' => 'عد تنازلي — تنفيذ تلقائي مع إمكانية الإلغاء',
  'How to confirm actions before execution.' => 'كيفية تأكيد الإجراءات قبل التنفيذ.',
  'Countdown Seconds' => 'ثوانٍ العد التنازلي',
  'Widget Configuration' => 'إعدادات الأداة',

  // Frontend buttons
  'Start' => 'بدء',
  'Done' => 'تم',
  'Error' => 'خطأ',
  'Confirm' => 'تأكيد',
  'Cancel' => 'إلغاء',
  'Start working on ticket #%s?' => 'بدء العمل على التذكرة #%s؟',
  'Complete and hand off ticket #%s?' => 'إكمال وتسليم التذكرة #%s؟',
  'Claim ticket and change status to working' => 'استلام التذكرة وتغيير الحالة إلى قيد العمل',
  'Change status, release agent and transfer' => 'تغيير الحالة وإخلاء الوكيل والتحويل',
  'Executing in %ss...' => 'التنفيذ خلال %s ثانية...',
  'Undo' => 'تراجع',
  'Undo expired' => 'انتهت مهلة التراجع',
  'Start Selected' => 'بدء المحددة',
  'Complete Selected' => 'إكمال المحددة',
  'elapsed' => 'مضى',

  // Admin matrix
  'Department' => 'القسم',
  'Enabled' => 'مفعّل',
  'Start: Trigger Status' => 'البدء: حالة التشغيل',
  'Start: Target Status' => 'البدء: الحالة المستهدفة',
  'Stop: Target Status' => 'الإيقاف: الحالة المستهدفة',
  'Stop: Transfer To' => 'الإيقاف: التحويل إلى',
  'Clear Team' => 'إزالة الفريق',
  '-- Select --' => '-- اختر --',
  '-- None --' => '-- لا شيء --',

  // Workflow Builder
  'Workflow Builder' => 'منشئ سير العمل',
  'Back' => 'رجوع',
  'Search departments...' => 'البحث في الأقسام...',
  'Enable All' => 'تفعيل الكل',
  'Disable All' => 'تعطيل الكل',
  '%d / %d enabled' => '%d / %d مفعّل',
  'Trigger' => 'المُشغّل',
  'Working' => 'قيد العمل',
  'Transfer to:' => 'التحويل إلى:',
  'Clear team on transfer' => 'إزالة الفريق عند التحويل',
  'Copy to...' => 'نسخ إلى...',
  'Apply template...' => 'تطبيق قالب...',
  'Single Step' => 'خطوة واحدة',
  'Assembly Step 1 (no transfer)' => 'التجميع، الخطوة 1 (بدون تحويل)',
  'Assembly Step 2 (with transfer)' => 'التجميع، الخطوة 2 (مع تحويل)',

  // Validation
  'Trigger status is required' => 'حالة التشغيل مطلوبة',
  'Working status is required' => 'حالة «قيد العمل» مطلوبة',
  'Done status is required' => 'حالة «تم» مطلوبة',
  'Trigger and Working are the same status (Start button will do nothing visible)' => 'المُشغّل و«قيد العمل» نفس الحالة (زر البدء لن يفعل شيئاً مرئياً)',
  'Done status equals Trigger — this creates an infinite loop' => 'حالة «تم» تساوي المُشغّل — هذا يُنشئ حلقة لا نهائية',
  'Working and Done are the same status (Stop button will do nothing visible)' => '«قيد العمل» و«تم» نفس الحالة (زر الإيقاف لن يفعل شيئاً مرئياً)',

  // Footer / Save
  'No unsaved changes' => 'لا توجد تغييرات غير محفوظة',
  'Unsaved changes' => 'تغييرات غير محفوظة',
  'All changes saved' => 'تم حفظ جميع التغييرات',
  'Save Changes' => 'حفظ التغييرات',
  'Saving...' => 'جارٍ الحفظ...',
  'Saved!' => 'تم الحفظ!',
  'Save failed' => 'فشل الحفظ',
  'Network error' => 'خطأ في الشبكة',

  // Dialogs
  'Discard unsaved changes?' => 'تجاهل التغييرات غير المحفوظة؟',
  'Copy this configuration to which department?' => 'نسخ هذه الإعدادات إلى أي قسم؟',
  'Department not found: %s' => 'القسم غير موجود: %s',
  'Copied to %s' => 'تم النسخ إلى %s',
  'Template applied — select statuses for each step' => 'تم تطبيق القالب — اختر الحالات لكل خطوة',
  'Loading dashboard...' => 'جارٍ تحميل لوحة المعلومات...',

  // Errors
  'Access Denied' => 'تم رفض الوصول',
  'Instance ID required' => 'معرّف المثيل مطلوب',
  'Plugin not found' => 'الإضافة غير موجودة',
  'Instance not found' => 'المثيل غير موجود',
  'Invalid JSON' => 'JSON غير صالح',
  'Validation failed' => 'فشل التحقق',
  'Configuration saved' => 'تم حفظ الإعدادات',
  'Invalid widget' => 'أداة غير صالحة',
  'Invalid action type' => 'نوع إجراء غير صالح',
  'No tickets selected' => 'لم يتم اختيار تذاكر',
  'Invalid or disabled widget' => 'أداة غير صالحة أو معطّلة',
  'Invalid plugin instance' => 'مثيل إضافة غير صالح',
  'Configuration error' => 'خطأ في الإعدادات',
  'Custom' => 'مخصص',

  // Config hints
  'Each widget handles one help topic. Tickets with this topic will show Start/Stop buttons.' => 'كل أداة تتعامل مع موضوع مساعدة واحد. التذاكر بهذا الموضوع ستعرض أزرار البدء/الإيقاف.',
  'Custom label for the Start button tooltip. Leave empty for default ("Start").' => 'تسمية مخصصة لتلميح زر البدء. اتركه فارغاً للقيمة الافتراضية («بدء»).',
  'Custom label for the Stop button tooltip. Leave empty for default ("Done").' => 'تسمية مخصصة لتلميح زر الإيقاف. اتركه فارغاً للقيمة الافتراضية («تم»).',
  'Hex color for Start button (e.g. #128DBE). Leave empty for default blue.' => 'لون زر البدء بتنسيق HEX (مثال #128DBE). اتركه فارغاً للأزرق الافتراضي.',
  'Hex color for Stop button (e.g. #27ae60). Leave empty for default green.' => 'لون زر الإيقاف بتنسيق HEX (مثال #27ae60). اتركه فارغاً للأخضر الافتراضي.',
  'Per-department button configuration (managed by the UI below).' => 'إعدادات الأزرار لكل قسم (تُدار بواسطة الواجهة أدناه).',
);
