<?php
/**
 * Gerenciamento de Permissões de Páginas - Metronic Theme
 * Permite visualizar e editar quais roles podem acessar cada página
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('permissoes.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Arquivo JSON para armazenar permissões customizadas
$permissions_file = __DIR__ . '/../config/permissions.json';

// Garante que o diretório existe
$config_dir = dirname($permissions_file);
if (!is_dir($config_dir)) {
    mkdir($config_dir, 0755, true);
}

// Carrega permissões customizadas (se existir)
$custom_permissions = [];
if (file_exists($permissions_file)) {
    $custom_permissions = json_decode(file_get_contents($permissions_file), true) ?? [];
}

// Permissões padrão do sistema
$default_permissions = get_page_permissions();

// Merge: customizadas têm prioridade sobre padrões
$all_permissions = array_merge($default_permissions, $custom_permissions);

// Arquivo JSON para armazenar permissões de cards do dashboard
$dashboard_cards_file = __DIR__ . '/../config/dashboard_cards.json';

// Carrega permissões de cards do dashboard (se existir)
$dashboard_cards_permissions = [];
if (file_exists($dashboard_cards_file)) {
    $dashboard_cards_permissions = json_decode(file_get_contents($dashboard_cards_file), true) ?? [];
}

// Cards disponíveis no dashboard
$dashboard_cards = [
    'card_emocao_diaria' => 'Análise de Emoções Diária',
    'card_historico_emocoes' => 'Histórico de Emoções',
    'card_media_emocoes' => 'Média das Emoções',
    'card_ultimas_emocoes' => 'Últimas Emoções Registradas',
    'card_ranking_pontos' => 'Ranking de Pontos',
    'card_ocorrencias_mes' => 'Ocorrências por Mês',
    'card_colaboradores_status' => 'Colaboradores por Status',
    'card_estatisticas_colaboradores' => 'Estatísticas de Colaboradores',
];

// Processa POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'salvar_permissoes') {
        $permissions_to_save = [];
        
        // Processa cada página enviada
        foreach ($_POST['permissions'] ?? [] as $page => $roles) {
            if (is_array($roles) && !empty($roles)) {
                $permissions_to_save[$page] = $roles;
            }
        }
        
        // Salva no arquivo JSON
        $json_data = json_encode($permissions_to_save, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        $saved_pages = file_put_contents($permissions_file, $json_data) !== false;
        
        // Processa cards do dashboard se enviados
        $saved_cards = true;
        if (!empty($_POST['dashboard_cards'])) {
            $cards_to_save = [];
            foreach ($_POST['dashboard_cards'] ?? [] as $card => $roles) {
                if (is_array($roles) && !empty($roles)) {
                    $cards_to_save[$card] = $roles;
                }
            }
            $cards_json = json_encode($cards_to_save, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $saved_cards = file_put_contents($dashboard_cards_file, $cards_json) !== false;
        }
        
        if ($saved_pages && $saved_cards) {
            redirect('permissoes.php', 'Permissões salvas com sucesso!', 'success');
        } else {
            redirect('permissoes.php', 'Erro ao salvar permissões. Verifique permissões do arquivo.', 'error');
        }
    } elseif ($action === 'salvar_dashboard_cards') {
        $cards_to_save = [];
        
        // Processa cada card enviado
        foreach ($_POST['dashboard_cards'] ?? [] as $card => $roles) {
            if (is_array($roles) && !empty($roles)) {
                $cards_to_save[$card] = $roles;
            }
        }
        
        // Salva no arquivo JSON
        $json_data = json_encode($cards_to_save, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        if (file_put_contents($dashboard_cards_file, $json_data) !== false) {
            redirect('permissoes.php', 'Permissões de cards do dashboard salvas com sucesso!', 'success');
        } else {
            redirect('permissoes.php', 'Erro ao salvar permissões de cards. Verifique permissões do arquivo.', 'error');
        }
    } elseif ($action === 'restaurar_padrao') {
        // Remove arquivo de permissões customizadas
        if (file_exists($permissions_file)) {
            @unlink($permissions_file);
        }
        if (file_exists($dashboard_cards_file)) {
            @unlink($dashboard_cards_file);
        }
        redirect('permissoes.php', 'Permissões restauradas para o padrão do sistema!', 'success');
    }
}

// Agrupa páginas por categoria para melhor visualização
$paginas_por_categoria = [
    'Dashboard' => ['dashboard.php'],
    'Emoções' => ['emocoes.php', 'emocoes_analise.php'],
    'Feed' => ['feed.php'],
    'Estrutura' => ['empresas.php', 'setores.php', 'cargos.php', 'hierarquia.php', 'niveis_hierarquicos.php'],
    'Colaboradores' => ['colaboradores.php', 'colaborador_add.php', 'colaborador_view.php', 'colaborador_edit.php', 'promocoes.php', 'horas_extras.php', 'fechamento_pagamentos.php', 'tipos_bonus.php'],
    'Ocorrências' => ['ocorrencias_list.php', 'ocorrencias_add.php', 'tipos_ocorrencias.php'],
    'Pagamentos' => ['meus_pagamentos.php'],
    'Usuários' => ['usuarios.php'],
    'Notificações' => ['enviar_notificacao_push.php', 'notificacoes_enviadas.php'],
    'Perfil/Conta' => ['minha_conta.php'],
    'Configurações' => ['configuracoes_email.php', 'configuracoes_onesignal.php', 'configuracoes_pontos.php', 'templates_email.php'],
    'Endomarketing' => ['endomarketing_datas_comemorativas.php', 'endomarketing_acoes.php'],
    'Relatórios' => ['relatorio_ocorrencias.php'],
];

// Todas as páginas disponíveis
$todas_paginas = array_keys($all_permissions);

// Roles disponíveis
$roles_disponiveis = ['ADMIN', 'RH', 'GESTOR', 'COLABORADOR'];

$page_title = 'Gerenciar Permissões';
require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Gerenciar Permissões</h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-muted">Configurações</li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Permissões</li>
            </ul>
        </div>
        <div class="d-flex align-items-center gap-2 gap-lg-3">
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="restaurar_padrao">
                <button type="submit" class="btn btn-light" onclick="return confirm('Tem certeza que deseja restaurar as permissões padrão? Isso irá remover todas as customizações.');">
                    <i class="ki-duotone ki-arrows-circle fs-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Restaurar Padrão
                </button>
            </form>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <!--begin::Alert Info-->
        <div class="alert alert-info d-flex align-items-center p-5 mb-10">
            <i class="ki-duotone ki-information-5 fs-2hx text-info me-4">
                <span class="path1"></span>
                <span class="path2"></span>
                <span class="path3"></span>
            </i>
            <div class="d-flex flex-column">
                <h4 class="mb-1 text-info">Informação</h4>
                <span>As permissões customizadas são salvas em <code>config/permissions.json</code>. Se este arquivo não existir, o sistema usa as permissões padrão definidas em <code>includes/permissions.php</code>.</span>
            </div>
        </div>
        <!--end::Alert Info-->
        
        <form method="POST" id="form_permissoes">
            <input type="hidden" name="action" value="salvar_permissoes">
            
            <?php foreach ($paginas_por_categoria as $categoria => $paginas): ?>
            <!--begin::Card-->
            <div class="card mb-5">
                <div class="card-header border-0 pt-5">
                    <h3 class="card-title align-items-start flex-column">
                        <span class="card-label fw-bold fs-3 mb-1"><?= htmlspecialchars($categoria) ?></span>
                        <span class="text-muted fw-semibold fs-7"><?= count($paginas) ?> página(s)</span>
                    </h3>
                </div>
                <div class="card-body pt-5">
                    <div class="table-responsive">
                        <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                            <thead>
                                <tr class="fw-bold text-muted">
                                    <th class="min-w-200px">Página</th>
                                    <th class="min-w-100px text-center">ADMIN</th>
                                    <th class="min-w-100px text-center">RH</th>
                                    <th class="min-w-100px text-center">GESTOR</th>
                                    <th class="min-w-100px text-center">COLABORADOR</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($paginas as $pagina): ?>
                                    <?php if (isset($all_permissions[$pagina])): ?>
                                    <tr>
                                        <td>
                                            <span class="text-gray-900 fw-bold d-block fs-6"><?= htmlspecialchars($pagina) ?></span>
                                            <?php if (isset($custom_permissions[$pagina])): ?>
                                            <span class="badge badge-light-warning fs-7">Customizado</span>
                                            <?php endif; ?>
                                        </td>
                                        <?php foreach ($roles_disponiveis as $role): ?>
                                        <td class="text-center">
                                            <div class="form-check form-check-custom form-check-solid">
                                                <input class="form-check-input" type="checkbox" 
                                                       name="permissions[<?= htmlspecialchars($pagina) ?>][]" 
                                                       value="<?= htmlspecialchars($role) ?>"
                                                       <?= in_array($role, $all_permissions[$pagina]) ? 'checked' : '' ?> />
                                            </div>
                                        </td>
                                        <?php endforeach; ?>
                                    </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <!--end::Card-->
            <?php endforeach; ?>
            
            <!--begin::Card - Permissões de Cards do Dashboard -->
            <div class="card mb-5">
                <div class="card-header border-0 pt-5">
                    <h3 class="card-title align-items-start flex-column">
                        <span class="card-label fw-bold fs-3 mb-1">Cards do Dashboard</span>
                        <span class="text-muted fw-semibold fs-7">Controle quais cards cada role pode ver no dashboard</span>
                    </h3>
                </div>
                <div class="card-body pt-5">
                    <div class="table-responsive">
                        <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                            <thead>
                                <tr class="fw-bold text-muted">
                                    <th class="min-w-250px">Card</th>
                                    <th class="min-w-100px text-center">ADMIN</th>
                                    <th class="min-w-100px text-center">RH</th>
                                    <th class="min-w-100px text-center">GESTOR</th>
                                    <th class="min-w-100px text-center">COLABORADOR</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dashboard_cards as $card_key => $card_name): 
                                    $card_permissions = $dashboard_cards_permissions[$card_key] ?? [];
                                ?>
                                <tr>
                                    <td>
                                        <span class="text-gray-900 fw-bold d-block fs-6"><?= htmlspecialchars($card_name) ?></span>
                                        <span class="text-muted fs-7"><?= htmlspecialchars($card_key) ?></span>
                                    </td>
                                    <?php foreach ($roles_disponiveis as $role): ?>
                                    <td class="text-center">
                                        <div class="form-check form-check-custom form-check-solid">
                                            <input class="form-check-input" type="checkbox" 
                                                   name="dashboard_cards[<?= htmlspecialchars($card_key) ?>][]" 
                                                   value="<?= htmlspecialchars($role) ?>"
                                                   <?= in_array($role, $card_permissions) ? 'checked' : '' ?> />
                                        </div>
                                    </td>
                                    <?php endforeach; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <!--end::Card-->
            
            <!--begin::Actions-->
            <div class="card">
                <div class="card-footer d-flex justify-content-end py-6 px-9">
                    <button type="reset" class="btn btn-light btn-active-light-primary me-2">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <span class="indicator-label">Salvar Permissões</span>
                        <span class="indicator-progress">Salvando...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                </div>
            </div>
            <!--end::Actions-->
        </form>
        
    </div>
</div>
<!--end::Post-->

<script>
// Submit com loading - salva permissões de páginas e cards do dashboard
document.getElementById('form_permissoes')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const submitBtn = this.querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.setAttribute('data-kt-indicator', 'on');
        submitBtn.disabled = true;
    }
    
    // Coleta dados dos cards do dashboard
    const dashboardCardsData = {};
    const dashboardCardsInputs = document.querySelectorAll('input[name^="dashboard_cards["]');
    dashboardCardsInputs.forEach(input => {
        const match = input.name.match(/dashboard_cards\[(.+?)\]\[\]/);
        if (match) {
            const cardKey = match[1];
            if (!dashboardCardsData[cardKey]) {
                dashboardCardsData[cardKey] = [];
            }
            if (input.checked) {
                dashboardCardsData[cardKey].push(input.value);
            }
        }
    });
    
    // Adiciona dados dos cards ao formulário principal
    Object.keys(dashboardCardsData).forEach(cardKey => {
        dashboardCardsData[cardKey].forEach(role => {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = `dashboard_cards[${cardKey}][]`;
            hiddenInput.value = role;
            this.appendChild(hiddenInput);
        });
    });
    
    // Submete o formulário
    this.submit();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

