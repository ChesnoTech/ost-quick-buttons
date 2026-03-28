<?php return array(
  '' => 'Project-Id-Version: ost-quick-buttons
Language: es
MIME-Version: 1.0
Content-Type: text/plain; charset=UTF-8
Content-Transfer-Encoding: 8bit
Plural-Forms: nplurals=2; plural=(n != 1);
',

  // Config labels
  'Help Topic' => 'Tema de ayuda',
  'Start Button Label' => 'Etiqueta del botón Iniciar',
  'Stop Button Label' => 'Etiqueta del botón Finalizar',
  'Start Button Color' => 'Color del botón Iniciar',
  'Stop Button Color' => 'Color del botón Finalizar',
  'Confirmation Mode' => 'Modo de confirmación',
  'None — Execute immediately' => 'Ninguno — Ejecutar de inmediato',
  'Confirm Dialog — Requires explicit click' => 'Diálogo — Requiere confirmación explícita',
  'Countdown — Auto-execute with cancel window' => 'Cuenta regresiva — Ejecución automática con opción de cancelar',
  'How to confirm actions before execution.' => 'Cómo confirmar las acciones antes de ejecutarlas.',
  'Countdown Seconds' => 'Segundos de cuenta regresiva',
  'Widget Configuration' => 'Configuración del widget',

  // Frontend buttons
  'Start' => 'Iniciar',
  'Done' => 'Listo',
  'Error' => 'Error',
  'Confirm' => 'Confirmar',
  'Cancel' => 'Cancelar',
  'Start working on ticket #%s?' => '¿Comenzar a trabajar en el ticket #%s?',
  'Complete and hand off ticket #%s?' => '¿Completar y transferir el ticket #%s?',
  'Claim ticket and change status to working' => 'Tomar el ticket y cambiar el estado a «En curso»',
  'Change status, release agent and transfer' => 'Cambiar estado, liberar agente y transferir',
  'Executing in %ss...' => 'Ejecutando en %s s...',
  'Undo' => 'Deshacer',
  'Undo expired' => 'Tiempo de deshacer agotado',
  'Start Selected' => 'Iniciar seleccionados',
  'Complete Selected' => 'Completar seleccionados',
  'elapsed' => 'transcurrido',
  'waiting' => 'esperando',
  'H' => 'H',
  'M' => 'M',
  'S' => 'S',

  // Admin matrix
  'Department' => 'Departamento',
  'Enabled' => 'Habilitado',
  'Start: Trigger Status' => 'Inicio: Estado disparador',
  'Start: Target Status' => 'Inicio: Estado destino',
  'Stop: Target Status' => 'Fin: Estado destino',
  'Stop: Transfer To' => 'Fin: Transferir a',
  'Clear Team' => 'Quitar equipo',
  '-- Select --' => '-- Seleccionar --',
  '-- None --' => '-- Ninguno --',

  // Workflow Builder
  'Workflow Builder' => 'Constructor de flujos',
  'Back' => 'Volver',
  'Search departments...' => 'Buscar departamentos...',
  'Enable All' => 'Habilitar todos',
  'Disable All' => 'Deshabilitar todos',
  '%d / %d enabled' => '%d / %d habilitados',
  'Trigger' => 'Disparador',
  'Working' => 'En curso',
  'Transfer to:' => 'Transferir a:',
  'Also clear team assignment' => 'También limpiar asignación de equipo',
  'Copy to...' => 'Copiar a...',
  'Apply template...' => 'Aplicar plantilla...',
  'Single Step' => 'Paso único',
  'Assembly Step 1 (no transfer)' => 'Ensamblaje, paso 1 (sin transferencia)',
  'Assembly Step 2 (with transfer)' => 'Ensamblaje, paso 2 (con transferencia)',

  // Validation
  'Trigger status is required' => 'El estado disparador es obligatorio',
  'Working status is required' => 'El estado «En curso» es obligatorio',
  'Done status is required' => 'El estado «Listo» es obligatorio',
  'Trigger and Working are the same status (Start button will do nothing visible)' => 'Disparador y «En curso» son el mismo estado (el botón Iniciar no hará nada visible)',
  'Done status equals Trigger — this creates an infinite loop' => 'El estado «Listo» coincide con el disparador — esto crea un bucle infinito',
  'Working and Done are the same status (Stop button will do nothing visible)' => '«En curso» y «Listo» son el mismo estado (el botón Finalizar no hará nada visible)',

  // Footer / Save
  'No unsaved changes' => 'Sin cambios pendientes',
  'Unsaved changes' => 'Cambios sin guardar',
  'All changes saved' => 'Todos los cambios guardados',
  'Save Changes' => 'Guardar cambios',
  'Saving...' => 'Guardando...',
  'Saved!' => '¡Guardado!',
  'Save failed' => 'Error al guardar',
  'Network error' => 'Error de red',

  // Dialogs
  'Discard unsaved changes?' => '¿Descartar los cambios sin guardar?',
  'Copy this configuration to which department?' => '¿A qué departamento copiar esta configuración?',
  'Department not found: %s' => 'Departamento no encontrado: %s',
  'Copied to %s' => 'Copiado a %s',
  'Template applied — select statuses for each step' => 'Plantilla aplicada — seleccione los estados para cada paso',
  'Loading dashboard...' => 'Cargando panel...',

  // Errors
  'Access Denied' => 'Acceso denegado',
  'Instance ID required' => 'Se requiere el ID de instancia',
  'Plugin not found' => 'Plugin no encontrado',
  'Instance not found' => 'Instancia no encontrada',
  'Invalid JSON' => 'JSON no válido',
  'Validation failed' => 'Error de validación',
  'Configuration saved' => 'Configuración guardada',
  'Invalid widget' => 'Widget no válido',
  'Invalid action type' => 'Tipo de acción no válido',
  'No tickets selected' => 'No se seleccionaron tickets',
  'Invalid or disabled widget' => 'Widget no válido o deshabilitado',
  'Invalid plugin instance' => 'Instancia de plugin no válida',
  'Configuration error' => 'Error de configuración',
  'Custom' => 'Personalizado',

  // Config hints
  'Each widget handles one help topic. Tickets with this topic will show Start/Stop buttons.' => 'Cada widget gestiona un tema de ayuda. Los tickets con este tema mostrarán los botones Iniciar/Finalizar.',
  'Custom label for the Start button tooltip. Leave empty for default ("Start").' => 'Etiqueta personalizada para el tooltip del botón Iniciar. Dejar vacío para el valor predeterminado («Iniciar»).',
  'Custom label for the Stop button tooltip. Leave empty for default ("Done").' => 'Etiqueta personalizada para el tooltip del botón Finalizar. Dejar vacío para el valor predeterminado («Listo»).',
  'Hex color for Start button (e.g. #128DBE). Leave empty for default blue.' => 'Color hexadecimal del botón Iniciar (ej. #128DBE). Dejar vacío para el azul predeterminado.',
  'Hex color for Stop button (e.g. #27ae60). Leave empty for default green.' => 'Color hexadecimal del botón Finalizar (ej. #27ae60). Dejar vacío para el verde predeterminado.',
  'Per-department button configuration (managed by the UI below).' => 'Configuración de botones por departamento (gestionada por la interfaz a continuación).',
);
