<?php return array(
  '' => 'Project-Id-Version: ost-quick-buttons
Language: de
MIME-Version: 1.0
Content-Type: text/plain; charset=UTF-8
Content-Transfer-Encoding: 8bit
Plural-Forms: nplurals=2; plural=(n != 1);
',

  // Config labels
  'Help Topic' => 'Hilfethema',
  'Start Button Label' => 'Beschriftung der Start-Schaltfläche',
  'Stop Button Label' => 'Beschriftung der Stopp-Schaltfläche',
  'Start Button Color' => 'Farbe der Start-Schaltfläche',
  'Stop Button Color' => 'Farbe der Stopp-Schaltfläche',
  'Confirmation Mode' => 'Bestätigungsmodus',
  'None — Execute immediately' => 'Keine — Sofort ausführen',
  'Confirm Dialog — Requires explicit click' => 'Dialog — Ausdrückliche Bestätigung erforderlich',
  'Countdown — Auto-execute with cancel window' => 'Countdown — Automatische Ausführung mit Abbruchmöglichkeit',
  'How to confirm actions before execution.' => 'Wie Aktionen vor der Ausführung bestätigt werden.',
  'Countdown Seconds' => 'Countdown-Sekunden',
  'Widget Configuration' => 'Widget-Konfiguration',

  // Frontend buttons
  'Start' => 'Starten',
  'Done' => 'Fertig',
  'Error' => 'Fehler',
  'Confirm' => 'Bestätigen',
  'Cancel' => 'Abbrechen',
  'Start working on ticket #%s?' => 'Ticket #%s in Bearbeitung nehmen?',
  'Complete and hand off ticket #%s?' => 'Ticket #%s abschließen und übergeben?',
  'Claim ticket and change status to working' => 'Ticket übernehmen und Status auf „In Bearbeitung" setzen',
  'Change status, release agent and transfer' => 'Status ändern, Bearbeiter freigeben und übergeben',
  'Executing in %ss...' => 'Ausführung in %s s...',
  'Undo' => 'Rückgängig',
  'Undo expired' => 'Rückgängig-Frist abgelaufen',
  'Start Selected' => 'Ausgewählte starten',
  'Complete Selected' => 'Ausgewählte abschließen',
  'elapsed' => 'vergangen',
  'waiting' => 'wartend',
  'H' => 'Std',
  'M' => 'Min',
  'S' => 'Sek',

  // Admin matrix
  'Department' => 'Abteilung',
  'Enabled' => 'Aktiviert',
  'Start: Trigger Status' => 'Start: Auslöser-Status',
  'Start: Target Status' => 'Start: Zielstatus',
  'Stop: Target Status' => 'Stopp: Zielstatus',
  'Stop: Transfer To' => 'Stopp: Übergeben an',
  'Clear Team' => 'Team entfernen',
  '-- Select --' => '-- Auswählen --',
  '-- None --' => '-- Keiner --',

  // Workflow Builder
  'Workflow Builder' => 'Workflow-Editor',
  'Back' => 'Zurück',
  'Search departments...' => 'Abteilungen suchen...',
  'Enable All' => 'Alle aktivieren',
  'Disable All' => 'Alle deaktivieren',
  '%d / %d enabled' => '%d / %d aktiviert',
  'Trigger' => 'Auslöser',
  'Working' => 'In Bearbeitung',
  'Transfer to:' => 'Übergeben an:',
  'Also clear team assignment' => 'Auch Teamzuweisung aufheben',
  'Copy to...' => 'Kopieren nach...',
  'Apply template...' => 'Vorlage anwenden...',
  'Single Step' => 'Einzelschritt',
  'Assembly Step 1 (no transfer)' => 'Montage, Schritt 1 (ohne Übergabe)',
  'Assembly Step 2 (with transfer)' => 'Montage, Schritt 2 (mit Übergabe)',

  // Validation
  'Trigger status is required' => 'Auslöser-Status ist erforderlich',
  'Working status is required' => 'Status „In Bearbeitung" ist erforderlich',
  'Done status is required' => 'Status „Fertig" ist erforderlich',
  'Trigger and Working are the same status (Start button will do nothing visible)' => 'Auslöser und „In Bearbeitung" haben denselben Status (die Start-Schaltfläche bewirkt nichts Sichtbares)',
  'Done status equals Trigger — this creates an infinite loop' => 'Status „Fertig" entspricht dem Auslöser — das erzeugt eine Endlosschleife',
  'Working and Done are the same status (Stop button will do nothing visible)' => '„In Bearbeitung" und „Fertig" haben denselben Status (die Stopp-Schaltfläche bewirkt nichts Sichtbares)',

  // Footer / Save
  'No unsaved changes' => 'Keine ungespeicherten Änderungen',
  'Unsaved changes' => 'Ungespeicherte Änderungen',
  'All changes saved' => 'Alle Änderungen gespeichert',
  'Save Changes' => 'Änderungen speichern',
  'Saving...' => 'Wird gespeichert...',
  'Saved!' => 'Gespeichert!',
  'Save failed' => 'Speichern fehlgeschlagen',
  'Network error' => 'Netzwerkfehler',

  // Dialogs
  'Discard unsaved changes?' => 'Ungespeicherte Änderungen verwerfen?',
  'Copy this configuration to which department?' => 'In welche Abteilung soll diese Konfiguration kopiert werden?',
  'Department not found: %s' => 'Abteilung nicht gefunden: %s',
  'Copied to %s' => 'Kopiert nach %s',
  'Template applied — select statuses for each step' => 'Vorlage angewendet — wählen Sie die Status für jeden Schritt',
  'Loading dashboard...' => 'Dashboard wird geladen...',

  // Errors
  'Access Denied' => 'Zugriff verweigert',
  'Instance ID required' => 'Instanz-ID erforderlich',
  'Plugin not found' => 'Plugin nicht gefunden',
  'Instance not found' => 'Instanz nicht gefunden',
  'Invalid JSON' => 'Ungültiges JSON',
  'Validation failed' => 'Validierung fehlgeschlagen',
  'Configuration saved' => 'Konfiguration gespeichert',
  'Invalid widget' => 'Ungültiges Widget',
  'Invalid action type' => 'Ungültiger Aktionstyp',
  'No tickets selected' => 'Keine Tickets ausgewählt',
  'Invalid or disabled widget' => 'Ungültiges oder deaktiviertes Widget',
  'Invalid plugin instance' => 'Ungültige Plugin-Instanz',
  'Configuration error' => 'Konfigurationsfehler',
  'Custom' => 'Benutzerdefiniert',

  // Config hints
  'Each widget handles one help topic. Tickets with this topic will show Start/Stop buttons.' => 'Jedes Widget verarbeitet ein Hilfethema. Tickets mit diesem Thema zeigen die Start-/Stopp-Schaltflächen an.',
  'Custom label for the Start button tooltip. Leave empty for default ("Start").' => 'Benutzerdefinierte Beschriftung für den Tooltip der Start-Schaltfläche. Leer lassen für den Standardwert („Starten").',
  'Custom label for the Stop button tooltip. Leave empty for default ("Done").' => 'Benutzerdefinierte Beschriftung für den Tooltip der Stopp-Schaltfläche. Leer lassen für den Standardwert („Fertig").',
  'Hex color for Start button (e.g. #128DBE). Leave empty for default blue.' => 'Hex-Farbe der Start-Schaltfläche (z. B. #128DBE). Leer lassen für das Standard-Blau.',
  'Hex color for Stop button (e.g. #27ae60). Leave empty for default green.' => 'Hex-Farbe der Stopp-Schaltfläche (z. B. #27ae60). Leer lassen für das Standard-Grün.',
  'Per-department button configuration (managed by the UI below).' => 'Schaltflächenkonfiguration pro Abteilung (wird über die Oberfläche unten verwaltet).',
);
