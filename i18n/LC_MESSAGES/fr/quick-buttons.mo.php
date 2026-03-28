<?php return array(
  '' => 'Project-Id-Version: ost-quick-buttons
Language: fr
MIME-Version: 1.0
Content-Type: text/plain; charset=UTF-8
Content-Transfer-Encoding: 8bit
Plural-Forms: nplurals=2; plural=(n > 1);
',

  // Config labels
  'Help Topic' => 'Sujet d\'aide',
  'Start Button Label' => 'Libellé du bouton Démarrer',
  'Stop Button Label' => 'Libellé du bouton Terminer',
  'Start Button Color' => 'Couleur du bouton Démarrer',
  'Stop Button Color' => 'Couleur du bouton Terminer',
  'Confirmation Mode' => 'Mode de confirmation',
  'None — Execute immediately' => 'Aucun — Exécuter immédiatement',
  'Confirm Dialog — Requires explicit click' => 'Dialogue — Confirmation explicite requise',
  'Countdown — Auto-execute with cancel window' => 'Compte à rebours — Exécution auto avec possibilité d\'annuler',
  'How to confirm actions before execution.' => 'Comment confirmer les actions avant l\'exécution.',
  'Countdown Seconds' => 'Secondes du compte à rebours',
  'Widget Configuration' => 'Configuration du widget',

  // Frontend buttons
  'Start' => 'Démarrer',
  'Done' => 'Terminé',
  'Error' => 'Erreur',
  'Confirm' => 'Confirmer',
  'Cancel' => 'Annuler',
  'Start working on ticket #%s?' => 'Commencer à travailler sur le ticket #%s ?',
  'Complete and hand off ticket #%s?' => 'Terminer et transférer le ticket #%s ?',
  'Claim ticket and change status to working' => 'Prendre le ticket et passer le statut à « En cours »',
  'Change status, release agent and transfer' => 'Changer le statut, libérer l\'agent et transférer',
  'Executing in %ss...' => 'Exécution dans %s s...',
  'Undo' => 'Annuler',
  'Undo expired' => 'Délai d\'annulation expiré',
  'Start Selected' => 'Démarrer la sélection',
  'Complete Selected' => 'Terminer la sélection',
  'elapsed' => 'écoulé',

  // Admin matrix
  'Department' => 'Service',
  'Enabled' => 'Activé',
  'Start: Trigger Status' => 'Démarrer : Statut déclencheur',
  'Start: Target Status' => 'Démarrer : Statut cible',
  'Stop: Target Status' => 'Terminer : Statut cible',
  'Stop: Transfer To' => 'Terminer : Transférer à',
  'Clear Team' => 'Retirer l\'équipe',
  '-- Select --' => '-- Sélectionner --',
  '-- None --' => '-- Aucun --',

  // Workflow Builder
  'Workflow Builder' => 'Éditeur de processus',
  'Back' => 'Retour',
  'Search departments...' => 'Rechercher des services...',
  'Enable All' => 'Tout activer',
  'Disable All' => 'Tout désactiver',
  '%d / %d enabled' => '%d / %d activés',
  'Trigger' => 'Déclencheur',
  'Working' => 'En cours',
  'Transfer to:' => 'Transférer à :',
  'Also clear team assignment' => 'Effacer aussi l\'assignation d\'équipe',
  'Copy to...' => 'Copier vers...',
  'Apply template...' => 'Appliquer un modèle...',
  'Single Step' => 'Étape unique',
  'Assembly Step 1 (no transfer)' => 'Assemblage, étape 1 (sans transfert)',
  'Assembly Step 2 (with transfer)' => 'Assemblage, étape 2 (avec transfert)',

  // Validation
  'Trigger status is required' => 'Le statut déclencheur est obligatoire',
  'Working status is required' => 'Le statut « En cours » est obligatoire',
  'Done status is required' => 'Le statut « Terminé » est obligatoire',
  'Trigger and Working are the same status (Start button will do nothing visible)' => 'Déclencheur et « En cours » ont le même statut (le bouton Démarrer ne fera rien de visible)',
  'Done status equals Trigger — this creates an infinite loop' => 'Le statut « Terminé » est identique au déclencheur — cela crée une boucle infinie',
  'Working and Done are the same status (Stop button will do nothing visible)' => '« En cours » et « Terminé » ont le même statut (le bouton Terminer ne fera rien de visible)',

  // Footer / Save
  'No unsaved changes' => 'Aucune modification en attente',
  'Unsaved changes' => 'Modifications non enregistrées',
  'All changes saved' => 'Toutes les modifications enregistrées',
  'Save Changes' => 'Enregistrer',
  'Saving...' => 'Enregistrement...',
  'Saved!' => 'Enregistré !',
  'Save failed' => 'Échec de l\'enregistrement',
  'Network error' => 'Erreur réseau',

  // Dialogs
  'Discard unsaved changes?' => 'Abandonner les modifications non enregistrées ?',
  'Copy this configuration to which department?' => 'Vers quel service copier cette configuration ?',
  'Department not found: %s' => 'Service introuvable : %s',
  'Copied to %s' => 'Copié vers %s',
  'Template applied — select statuses for each step' => 'Modèle appliqué — sélectionnez les statuts pour chaque étape',
  'Loading dashboard...' => 'Chargement du tableau de bord...',

  // Errors
  'Access Denied' => 'Accès refusé',
  'Instance ID required' => 'ID d\'instance requis',
  'Plugin not found' => 'Plugin introuvable',
  'Instance not found' => 'Instance introuvable',
  'Invalid JSON' => 'JSON non valide',
  'Validation failed' => 'Échec de la validation',
  'Configuration saved' => 'Configuration enregistrée',
  'Invalid widget' => 'Widget non valide',
  'Invalid action type' => 'Type d\'action non valide',
  'No tickets selected' => 'Aucun ticket sélectionné',
  'Invalid or disabled widget' => 'Widget non valide ou désactivé',
  'Invalid plugin instance' => 'Instance de plugin non valide',
  'Configuration error' => 'Erreur de configuration',
  'Custom' => 'Personnalisé',

  // Config hints
  'Each widget handles one help topic. Tickets with this topic will show Start/Stop buttons.' => 'Chaque widget gère un sujet d\'aide. Les tickets associés à ce sujet afficheront les boutons Démarrer/Terminer.',
  'Custom label for the Start button tooltip. Leave empty for default ("Start").' => 'Libellé personnalisé pour l\'infobulle du bouton Démarrer. Laisser vide pour la valeur par défaut (« Démarrer »).',
  'Custom label for the Stop button tooltip. Leave empty for default ("Done").' => 'Libellé personnalisé pour l\'infobulle du bouton Terminer. Laisser vide pour la valeur par défaut (« Terminé »).',
  'Hex color for Start button (e.g. #128DBE). Leave empty for default blue.' => 'Couleur hexadécimale du bouton Démarrer (ex. #128DBE). Laisser vide pour le bleu par défaut.',
  'Hex color for Stop button (e.g. #27ae60). Leave empty for default green.' => 'Couleur hexadécimale du bouton Terminer (ex. #27ae60). Laisser vide pour le vert par défaut.',
  'Per-department button configuration (managed by the UI below).' => 'Configuration des boutons par service (gérée par l\'interface ci-dessous).',
);
