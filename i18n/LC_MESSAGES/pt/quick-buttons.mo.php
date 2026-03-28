<?php return array(
  '' => 'Project-Id-Version: ost-quick-buttons
Language: pt_BR
MIME-Version: 1.0
Content-Type: text/plain; charset=UTF-8
Content-Transfer-Encoding: 8bit
Plural-Forms: nplurals=2; plural=(n > 1);
',

  // Config labels
  'Help Topic' => 'Tópico de ajuda',
  'Start Button Label' => 'Rótulo do botão Iniciar',
  'Stop Button Label' => 'Rótulo do botão Concluir',
  'Start Button Color' => 'Cor do botão Iniciar',
  'Stop Button Color' => 'Cor do botão Concluir',
  'Confirmation Mode' => 'Modo de confirmação',
  'None — Execute immediately' => 'Nenhum — Executar imediatamente',
  'Confirm Dialog — Requires explicit click' => 'Diálogo — Requer confirmação explícita',
  'Countdown — Auto-execute with cancel window' => 'Contagem regressiva — Execução automática com opção de cancelar',
  'How to confirm actions before execution.' => 'Como confirmar ações antes da execução.',
  'Countdown Seconds' => 'Segundos da contagem regressiva',
  'Widget Configuration' => 'Configuração do widget',

  // Frontend buttons
  'Start' => 'Iniciar',
  'Done' => 'Concluído',
  'Error' => 'Erro',
  'Confirm' => 'Confirmar',
  'Cancel' => 'Cancelar',
  'Start working on ticket #%s?' => 'Começar a trabalhar no chamado #%s?',
  'Complete and hand off ticket #%s?' => 'Concluir e transferir o chamado #%s?',
  'Claim ticket and change status to working' => 'Assumir o chamado e alterar o status para «Em andamento»',
  'Change status, release agent and transfer' => 'Alterar status, liberar atendente e transferir',
  'Executing in %ss...' => 'Executando em %s s...',
  'Undo' => 'Desfazer',
  'Undo expired' => 'Prazo para desfazer expirou',
  'Start Selected' => 'Iniciar selecionados',
  'Complete Selected' => 'Concluir selecionados',
  'elapsed' => 'decorrido',

  // Admin matrix
  'Department' => 'Departamento',
  'Enabled' => 'Ativado',
  'Start: Trigger Status' => 'Início: Status gatilho',
  'Start: Target Status' => 'Início: Status de destino',
  'Stop: Target Status' => 'Fim: Status de destino',
  'Stop: Transfer To' => 'Fim: Transferir para',
  'Clear Team' => 'Remover equipe',
  '-- Select --' => '-- Selecionar --',
  '-- None --' => '-- Nenhum --',

  // Workflow Builder
  'Workflow Builder' => 'Editor de fluxos',
  'Back' => 'Voltar',
  'Search departments...' => 'Pesquisar departamentos...',
  'Enable All' => 'Ativar todos',
  'Disable All' => 'Desativar todos',
  '%d / %d enabled' => '%d / %d ativados',
  'Trigger' => 'Gatilho',
  'Working' => 'Em andamento',
  'Transfer to:' => 'Transferir para:',
  'Also clear team assignment' => 'Também limpar atribuição de equipe',
  'Copy to...' => 'Copiar para...',
  'Apply template...' => 'Aplicar modelo...',
  'Single Step' => 'Etapa única',
  'Assembly Step 1 (no transfer)' => 'Montagem, etapa 1 (sem transferência)',
  'Assembly Step 2 (with transfer)' => 'Montagem, etapa 2 (com transferência)',

  // Validation
  'Trigger status is required' => 'O status gatilho é obrigatório',
  'Working status is required' => 'O status «Em andamento» é obrigatório',
  'Done status is required' => 'O status «Concluído» é obrigatório',
  'Trigger and Working are the same status (Start button will do nothing visible)' => 'Gatilho e «Em andamento» são o mesmo status (o botão Iniciar não fará nada visível)',
  'Done status equals Trigger — this creates an infinite loop' => 'O status «Concluído» é igual ao gatilho — isso cria um loop infinito',
  'Working and Done are the same status (Stop button will do nothing visible)' => '«Em andamento» e «Concluído» são o mesmo status (o botão Concluir não fará nada visível)',

  // Footer / Save
  'No unsaved changes' => 'Sem alterações pendentes',
  'Unsaved changes' => 'Alterações não salvas',
  'All changes saved' => 'Todas as alterações salvas',
  'Save Changes' => 'Salvar alterações',
  'Saving...' => 'Salvando...',
  'Saved!' => 'Salvo!',
  'Save failed' => 'Falha ao salvar',
  'Network error' => 'Erro de rede',

  // Dialogs
  'Discard unsaved changes?' => 'Descartar alterações não salvas?',
  'Copy this configuration to which department?' => 'Para qual departamento copiar esta configuração?',
  'Department not found: %s' => 'Departamento não encontrado: %s',
  'Copied to %s' => 'Copiado para %s',
  'Template applied — select statuses for each step' => 'Modelo aplicado — selecione os status para cada etapa',
  'Loading dashboard...' => 'Carregando painel...',

  // Errors
  'Access Denied' => 'Acesso negado',
  'Instance ID required' => 'ID da instância obrigatório',
  'Plugin not found' => 'Plugin não encontrado',
  'Instance not found' => 'Instância não encontrada',
  'Invalid JSON' => 'JSON inválido',
  'Validation failed' => 'Falha na validação',
  'Configuration saved' => 'Configuração salva',
  'Invalid widget' => 'Widget inválido',
  'Invalid action type' => 'Tipo de ação inválido',
  'No tickets selected' => 'Nenhum chamado selecionado',
  'Invalid or disabled widget' => 'Widget inválido ou desativado',
  'Invalid plugin instance' => 'Instância de plugin inválida',
  'Configuration error' => 'Erro de configuração',
  'Custom' => 'Personalizado',

  // Config hints
  'Each widget handles one help topic. Tickets with this topic will show Start/Stop buttons.' => 'Cada widget gerencia um tópico de ajuda. Chamados com este tópico exibirão os botões Iniciar/Concluir.',
  'Custom label for the Start button tooltip. Leave empty for default ("Start").' => 'Rótulo personalizado para a dica do botão Iniciar. Deixe vazio para o padrão («Iniciar»).',
  'Custom label for the Stop button tooltip. Leave empty for default ("Done").' => 'Rótulo personalizado para a dica do botão Concluir. Deixe vazio para o padrão («Concluído»).',
  'Hex color for Start button (e.g. #128DBE). Leave empty for default blue.' => 'Cor hexadecimal do botão Iniciar (ex. #128DBE). Deixe vazio para o azul padrão.',
  'Hex color for Stop button (e.g. #27ae60). Leave empty for default green.' => 'Cor hexadecimal do botão Concluir (ex. #27ae60). Deixe vazio para o verde padrão.',
  'Per-department button configuration (managed by the UI below).' => 'Configuração de botões por departamento (gerenciada pela interface abaixo).',
);
