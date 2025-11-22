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
        
        if (file_put_contents($permissions_file, $json_data) !== false) {
            redirect('permissoes.php', 'Permissões salvas com sucesso!', 'success');
        } else {
            redirect('permissoes.php', 'Erro ao salvar permissões. Verifique permissões do arquivo.', 'error');
        }
    } elseif ($action === 'restaurar_padrao') {
        // Remove arquivo de permissões customizadas
        if (file_exists($permissions_file)) {
            @unlink($permissions_file);
        }
        redirect('permissoes.php', 'Permissões restauradas para o padrão do sistema!', 'success');
    }
}

// Agrupa páginas por categoria para melhor visualização
$paginas_por_categoria = [
    'Dashboard' => ['dashboard.php'],
    'Estrutura' => ['empresas.php', 'setores.php', 'cargos.php', 'hierarquia.php', 'niveis_hierarquicos.php'],
    'Colaboradores' => ['colaboradores.php', 'colaborador_add.php', 'colaborador_view.php', 'colaborador_edit.php', 'promocoes.php', 'horas_extras.php', 'fechamento_pagamentos.php', 'tipos_bonus.php'],
    'Ocorrências' => ['ocorrencias_list.php', 'ocorrencias_add.php', 'tipos_ocorrencias.php'],
    'Pagamentos' => ['meus_pagamentos.php'],
    'Usuários' => ['usuarios.php'],
    'Notificações' => ['enviar_notificacao_push.php', 'notificacoes_enviadas.php'],
    'Perfil/Conta' => ['minha_conta.php'],
    'Configurações' => ['configuracoes_email.php', 'configuracoes_onesignal.php', 'templates_email.php'],
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
// Submit com loading
document.getElementById('form_permissoes')?.addEventListener('submit', function(e) {
    const submitBtn = this.querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.setAttribute('data-kt-indicator', 'on');
        submitBtn.disabled = true;
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

