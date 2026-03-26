<?php return array(
  '' => 'Project-Id-Version: ost-quick-buttons
Language: ru
MIME-Version: 1.0
Content-Type: text/plain; charset=UTF-8
Content-Transfer-Encoding: 8bit
Plural-Forms: nplurals=4; plural=((n%10==1 && n%100!=11) ? 0 : ((n%10 >= 2 && n%10 <=4 && (n%100 < 12 || n%100 > 14)) ? 1 : ((n%10 == 0 || (n%10 >= 5 && n%10 <=9)) || (n%100 >= 11 && n%100 <= 14)) ? 2 : 3));
',

  // Config labels
  'Help Topic' => 'Тема помощи',
  'Start Button Label' => 'Метка кнопки Старт',
  'Stop Button Label' => 'Метка кнопки Стоп',
  'Start Button Color' => 'Цвет кнопки Старт',
  'Stop Button Color' => 'Цвет кнопки Стоп',
  'Confirmation Mode' => 'Режим подтверждения',
  'None — Execute immediately' => 'Без подтверждения — Выполнить сразу',
  'Confirm Dialog — Requires explicit click' => 'Диалог — Требуется подтверждение',
  'Countdown — Auto-execute with cancel window' => 'Обратный отсчёт — Автовыполнение с возможностью отмены',
  'How to confirm actions before execution.' => 'Как подтверждать действия перед выполнением.',
  'Countdown Seconds' => 'Секунды обратного отсчёта',
  'Widget Configuration' => 'Конфигурация виджета',

  // Frontend buttons
  'Start' => 'Старт',
  'Done' => 'Готово',
  'Error' => 'Ошибка',
  'Confirm' => 'Подтвердить',
  'Cancel' => 'Отмена',
  'Start working on ticket #%s?' => 'Начать работу над заявкой #%s?',
  'Complete and hand off ticket #%s?' => 'Завершить и передать заявку #%s?',
  'Claim ticket and change status to working' => 'Взять заявку и изменить статус на «В работе»',
  'Change status, release agent and transfer' => 'Изменить статус, освободить агента и передать',
  'Executing in %ss...' => 'Выполнение через %s сек...',
  'Undo' => 'Отменить',
  'Undo expired' => 'Время отмены истекло',
  'Start Selected' => 'Начать выбранные',
  'Complete Selected' => 'Завершить выбранные',
  'elapsed' => 'прошло',

  // Admin matrix
  'Department' => 'Отдел',
  'Enabled' => 'Включено',
  'Start: Trigger Status' => 'Старт: Статус-триггер',
  'Start: Target Status' => 'Старт: Целевой статус',
  'Stop: Target Status' => 'Стоп: Целевой статус',
  'Stop: Transfer To' => 'Стоп: Передать в',
  'Clear Team' => 'Очистить команду',
  '-- Select --' => '-- Выбрать --',
  '-- None --' => '-- Нет --',

  // Workflow Builder
  'Workflow Builder' => 'Конструктор процессов',
  'Back' => 'Назад',
  'Search departments...' => 'Поиск отделов...',
  'Enable All' => 'Включить все',
  'Disable All' => 'Отключить все',
  '%d / %d enabled' => '%d / %d включено',
  'Trigger' => 'Триггер',
  'Working' => 'В работе',
  'Transfer to:' => 'Передать в:',
  'Clear team on transfer' => 'Очистить команду при передаче',
  'Copy to...' => 'Копировать в...',
  'Apply template...' => 'Применить шаблон...',
  'Single Step' => 'Один этап',
  'Assembly Step 1 (no transfer)' => 'Сборка, этап 1 (без передачи)',
  'Assembly Step 2 (with transfer)' => 'Сборка, этап 2 (с передачей)',

  // Validation
  'Trigger status is required' => 'Статус-триггер обязателен',
  'Working status is required' => 'Статус «В работе» обязателен',
  'Done status is required' => 'Статус «Готово» обязателен',
  'Trigger and Working are the same status (Start button will do nothing visible)' => 'Триггер и «В работе» — одинаковый статус (кнопка Старт ничего не сделает)',
  'Done status equals Trigger — this creates an infinite loop' => 'Статус «Готово» совпадает с триггером — это создаёт бесконечный цикл',
  'Working and Done are the same status (Stop button will do nothing visible)' => '«В работе» и «Готово» — одинаковый статус (кнопка Стоп ничего не сделает)',

  // Footer / Save
  'No unsaved changes' => 'Нет несохранённых изменений',
  'Unsaved changes' => 'Есть несохранённые изменения',
  'All changes saved' => 'Все изменения сохранены',
  'Save Changes' => 'Сохранить',
  'Saving...' => 'Сохранение...',
  'Saved!' => 'Сохранено!',
  'Save failed' => 'Ошибка сохранения',
  'Network error' => 'Ошибка сети',

  // Dialogs
  'Discard unsaved changes?' => 'Отменить несохранённые изменения?',
  'Copy this configuration to which department?' => 'В какой отдел скопировать эту конфигурацию?',
  'Department not found: %s' => 'Отдел не найден: %s',
  'Copied to %s' => 'Скопировано в %s',
  'Template applied — select statuses for each step' => 'Шаблон применён — выберите статусы для каждого этапа',
  'Loading dashboard...' => 'Загрузка панели...',

  // Errors
  'Access Denied' => 'Доступ запрещён',
  'Instance ID required' => 'Требуется ID экземпляра',
  'Plugin not found' => 'Плагин не найден',
  'Instance not found' => 'Экземпляр не найден',
  'Invalid JSON' => 'Некорректный JSON',
  'Validation failed' => 'Ошибка валидации',
  'Configuration saved' => 'Конфигурация сохранена',
  'Invalid widget' => 'Некорректный виджет',
  'Invalid action type' => 'Некорректный тип действия',
  'No tickets selected' => 'Заявки не выбраны',
  'Invalid or disabled widget' => 'Некорректный или отключённый виджет',
  'Invalid plugin instance' => 'Некорректный экземпляр плагина',
  'Configuration error' => 'Ошибка конфигурации',
  'Custom' => 'Пользовательский',

  // Config hints
  'Each widget handles one help topic. Tickets with this topic will show Start/Stop buttons.' => 'Каждый виджет обрабатывает одну тему помощи. Заявки с этой темой будут показывать кнопки Старт/Стоп.',
  'Custom label for the Start button tooltip. Leave empty for default ("Start").' => 'Пользовательская метка подсказки кнопки Старт. Оставьте пустым для значения по умолчанию («Старт»).',
  'Custom label for the Stop button tooltip. Leave empty for default ("Done").' => 'Пользовательская метка подсказки кнопки Стоп. Оставьте пустым для значения по умолчанию («Готово»).',
  'Hex color for Start button (e.g. #128DBE). Leave empty for default blue.' => 'Цвет кнопки Старт в формате HEX (напр. #128DBE). Оставьте пустым для синего по умолчанию.',
  'Hex color for Stop button (e.g. #27ae60). Leave empty for default green.' => 'Цвет кнопки Стоп в формате HEX (напр. #27ae60). Оставьте пустым для зелёного по умолчанию.',
  'Per-department button configuration (managed by the UI below).' => 'Конфигурация кнопок по отделам (управляется интерфейсом ниже).',
);
