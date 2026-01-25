<?php
/**
 * Admin - Configurações da Loja de Pontos
 */

$page_title = 'Configurações da Loja';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/loja_functions.php';

require_login();

$usuario = $_SESSION['usuario'];
if (!in_array($usuario['role'], ['ADMIN', 'RH'])) {
    redirect('dashboard.php', 'Sem permissão para acessar esta página.', 'danger');
}

$pdo = getDB();

// Processa formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $configs = [
            'loja_ativa' => isset($_POST['loja_ativa']) ? '1' : '0',
            'aprovacao_obrigatoria' => isset($_POST['aprovacao_obrigatoria']) ? '1' : '0',
            'limite_resgates_mes' => intval($_POST['limite_resgates_mes'] ?? 0),
            'mensagem_loja_fechada' => trim($_POST['mensagem_loja_fechada'] ?? ''),
            'notificar_admin_resgate' => isset($_POST['notificar_admin_resgate']) ? '1' : '0',
            'notificar_colaborador_status' => isset($_POST['notificar_colaborador_status']) ? '1' : '0',
            'dias_novidade' => intval($_POST['dias_novidade'] ?? 7),
            'estoque_baixo_limite' => intval($_POST['estoque_baixo_limite'] ?? 5)
        ];
        
        foreach ($configs as $chave => $valor) {
            loja_config_set($chave, $valor);
        }
        
        set_flash('success', 'Configurações salvas com sucesso!');
        redirect('loja_admin_config.php');
        
    } catch (Exception $e) {
        set_flash('danger', 'Erro ao salvar configurações: ' . $e->getMessage());
    }
}

// Obtém configurações atuais
$stmt = $pdo->query("SELECT * FROM loja_config");
$configs = [];
while ($row = $stmt->fetch()) {
    $configs[$row['chave']] = [
        'valor' => $row['valor'],
        'descricao' => $row['descricao'],
        'tipo' => $row['tipo']
    ];
}

// Estatísticas gerais
$estatisticas = loja_get_estatisticas();

require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">
                <i class="ki-duotone ki-setting-2 fs-2 me-2 text-primary">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                Configurações da Loja
            </h1>
            <span class="text-muted mt-1 fw-semibold fs-7">Configure as opções da loja de pontos</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="loja_admin_produtos.php" class="btn btn-sm btn-light">
                <i class="ki-duotone ki-package fs-4"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                Produtos
            </a>
            <a href="loja_admin_categorias.php" class="btn btn-sm btn-light">
                <i class="ki-duotone ki-category fs-4"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span></i>
                Categorias
            </a>
            <a href="loja_admin_resgates.php" class="btn btn-sm btn-light">
                <i class="ki-duotone ki-basket fs-4"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span></i>
                Resgates
            </a>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post fs-6 d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <div class="row g-5">
            <!-- Estatísticas Gerais -->
            <div class="col-lg-4">
                <div class="card card-flush h-100">
                    <div class="card-header">
                        <h3 class="card-title">Resumo da Loja</h3>
                    </div>
                    <div class="card-body pt-0">
                        <div class="d-flex justify-content-between py-3 border-bottom">
                            <span class="text-gray-600">Produtos ativos</span>
                            <span class="fw-bold"><?= $estatisticas['total_produtos_ativos'] ?></span>
                        </div>
                        <div class="d-flex justify-content-between py-3 border-bottom">
                            <span class="text-gray-600">Categorias ativas</span>
                            <span class="fw-bold"><?= $estatisticas['total_categorias_ativas'] ?></span>
                        </div>
                        <div class="d-flex justify-content-between py-3 border-bottom">
                            <span class="text-gray-600">Resgates pendentes</span>
                            <span class="fw-bold text-warning"><?= $estatisticas['resgates_pendentes'] ?></span>
                        </div>
                        <div class="d-flex justify-content-between py-3 border-bottom">
                            <span class="text-gray-600">Resgates hoje</span>
                            <span class="fw-bold text-primary"><?= $estatisticas['resgates_hoje'] ?></span>
                        </div>
                        <div class="d-flex justify-content-between py-3 border-bottom">
                            <span class="text-gray-600">Pontos gastos (mês)</span>
                            <span class="fw-bold text-success"><?= number_format($estatisticas['pontos_gastos_mes'], 0, ',', '.') ?></span>
                        </div>
                        <div class="d-flex justify-content-between py-3">
                            <span class="text-gray-600">Pontos gastos (total)</span>
                            <span class="fw-bold"><?= number_format($estatisticas['pontos_gastos_total'], 0, ',', '.') ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Configurações -->
            <div class="col-lg-8">
                <form method="POST">
                    <div class="card card-flush">
                        <div class="card-header">
                            <h3 class="card-title">Configurações Gerais</h3>
                        </div>
                        <div class="card-body">
                            
                            <!-- Status da Loja -->
                            <div class="row mb-8">
                                <label class="col-lg-4 col-form-label fw-bold fs-6">Status da Loja</label>
                                <div class="col-lg-8 d-flex align-items-center">
                                    <div class="form-check form-switch form-check-custom form-check-solid">
                                        <input class="form-check-input" type="checkbox" name="loja_ativa" id="loja_ativa" 
                                               <?= ($configs['loja_ativa']['valor'] ?? '1') == '1' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="loja_ativa">
                                            Loja ativa para colaboradores
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Mensagem loja fechada -->
                            <div class="row mb-8">
                                <label class="col-lg-4 col-form-label fw-bold fs-6">Mensagem quando fechada</label>
                                <div class="col-lg-8">
                                    <input type="text" name="mensagem_loja_fechada" class="form-control form-control-solid" 
                                           value="<?= htmlspecialchars($configs['mensagem_loja_fechada']['valor'] ?? '') ?>">
                                    <div class="form-text">Mensagem exibida quando a loja está desativada</div>
                                </div>
                            </div>
                            
                            <div class="separator my-8"></div>
                            
                            <!-- Aprovação -->
                            <div class="row mb-8">
                                <label class="col-lg-4 col-form-label fw-bold fs-6">Aprovação de Resgates</label>
                                <div class="col-lg-8 d-flex align-items-center">
                                    <div class="form-check form-switch form-check-custom form-check-solid">
                                        <input class="form-check-input" type="checkbox" name="aprovacao_obrigatoria" id="aprovacao_obrigatoria"
                                               <?= ($configs['aprovacao_obrigatoria']['valor'] ?? '1') == '1' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="aprovacao_obrigatoria">
                                            Resgates precisam de aprovação do admin
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Limite mensal -->
                            <div class="row mb-8">
                                <label class="col-lg-4 col-form-label fw-bold fs-6">Limite de resgates/mês</label>
                                <div class="col-lg-8">
                                    <input type="number" name="limite_resgates_mes" class="form-control form-control-solid w-200px" 
                                           value="<?= $configs['limite_resgates_mes']['valor'] ?? 0 ?>" min="0">
                                    <div class="form-text">0 = sem limite. Quantidade máxima de resgates por colaborador por mês</div>
                                </div>
                            </div>
                            
                            <div class="separator my-8"></div>
                            
                            <!-- Notificações -->
                            <div class="row mb-8">
                                <label class="col-lg-4 col-form-label fw-bold fs-6">Notificações</label>
                                <div class="col-lg-8">
                                    <div class="form-check form-check-custom form-check-solid mb-3">
                                        <input class="form-check-input" type="checkbox" name="notificar_admin_resgate" id="notificar_admin_resgate"
                                               <?= ($configs['notificar_admin_resgate']['valor'] ?? '1') == '1' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="notificar_admin_resgate">
                                            Notificar admins sobre novos resgates
                                        </label>
                                    </div>
                                    <div class="form-check form-check-custom form-check-solid">
                                        <input class="form-check-input" type="checkbox" name="notificar_colaborador_status" id="notificar_colaborador_status"
                                               <?= ($configs['notificar_colaborador_status']['valor'] ?? '1') == '1' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="notificar_colaborador_status">
                                            Notificar colaborador sobre mudanças de status
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="separator my-8"></div>
                            
                            <!-- Outras configurações -->
                            <div class="row mb-8">
                                <label class="col-lg-4 col-form-label fw-bold fs-6">Dias como "Novidade"</label>
                                <div class="col-lg-8">
                                    <input type="number" name="dias_novidade" class="form-control form-control-solid w-150px" 
                                           value="<?= $configs['dias_novidade']['valor'] ?? 7 ?>" min="1" max="90">
                                    <div class="form-text">Produtos são considerados "novidade" por X dias após cadastro</div>
                                </div>
                            </div>
                            
                            <div class="row mb-0">
                                <label class="col-lg-4 col-form-label fw-bold fs-6">Alerta estoque baixo</label>
                                <div class="col-lg-8">
                                    <input type="number" name="estoque_baixo_limite" class="form-control form-control-solid w-150px" 
                                           value="<?= $configs['estoque_baixo_limite']['valor'] ?? 5 ?>" min="1">
                                    <div class="form-text">Exibir alerta quando estoque for igual ou menor que esse valor</div>
                                </div>
                            </div>
                            
                        </div>
                        <div class="card-footer d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="ki-duotone ki-check fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                                Salvar Configurações
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
    </div>
</div>
<!--end::Post-->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
