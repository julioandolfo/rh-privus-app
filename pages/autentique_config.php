<?php
/**
 * Configuração do Autentique
 */

$page_title = 'Configuração Autentique';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Apenas ADMIN pode configurar
if ($_SESSION['usuario']['role'] !== 'ADMIN') {
    redirect('dashboard.php', 'Acesso negado.', 'error');
}

$pdo = getDB();

// Verifica se a tabela existe
$tabela_existe = false;
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'autentique_config'");
    $tabela_existe = (bool)$stmt->fetch();
} catch (Exception $e) {
    $erro_tabela = $e->getMessage();
}

// Processa POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tabela_existe) {
    $api_key = trim($_POST['api_key'] ?? '');
    $sandbox = isset($_POST['sandbox']) ? 1 : 0;
    $webhook_url = trim($_POST['webhook_url'] ?? '');
    
    if (empty($api_key)) {
        redirect('autentique_config.php', 'API Key é obrigatória!', 'error');
    }
    
    try {
        // Verifica se já existe configuração
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM autentique_config");
        $result = $stmt->fetch();
        
        if ($result['total'] > 0) {
            // Atualiza
            $stmt = $pdo->prepare("
                UPDATE autentique_config 
                SET api_key = ?, sandbox = ?, webhook_url = ?, ativo = 1
                WHERE id = (SELECT id FROM (SELECT MIN(id) as id FROM autentique_config) as temp)
            ");
            $stmt->execute([$api_key, $sandbox, $webhook_url]);
        } else {
            // Insere
            $stmt = $pdo->prepare("
                INSERT INTO autentique_config (api_key, sandbox, webhook_url, ativo)
                VALUES (?, ?, ?, 1)
            ");
            $stmt->execute([$api_key, $sandbox, $webhook_url]);
        }
        
        redirect('autentique_config.php', 'Configuração salva com sucesso!', 'success');
    } catch (PDOException $e) {
        redirect('autentique_config.php', 'Erro ao salvar: ' . $e->getMessage(), 'error');
    }
}

// Busca configuração atual
$config = null;
if ($tabela_existe) {
    try {
        $stmt = $pdo->query("SELECT * FROM autentique_config ORDER BY id DESC LIMIT 1");
        $config = $stmt->fetch();
    } catch (Exception $e) {
        $erro_busca = $e->getMessage();
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Configuração Autentique</h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Autentique</li>
            </ul>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <?php if (!$tabela_existe): ?>
        <!--begin::Alert-->
        <div class="alert alert-warning d-flex align-items-center mb-5">
            <i class="ki-duotone ki-information-5 fs-2hx text-warning me-4">
                <span class="path1"></span>
                <span class="path2"></span>
                <span class="path3"></span>
            </i>
            <div class="d-flex flex-column">
                <h4 class="mb-1 text-warning">Tabela não encontrada</h4>
                <span>
                    Execute a migração de contratos primeiro: 
                    <a href="../executar_migracao_contratos.php" target="_blank" class="fw-bold">Executar Migração</a>
                </span>
            </div>
        </div>
        <!--end::Alert-->
        <?php else: ?>
        
        <!--begin::Card-->
        <div class="card">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">Integração com Autentique</span>
                    <span class="text-muted fw-semibold fs-7">Configure sua API Key para enviar contratos para assinatura digital</span>
                </h3>
            </div>
            <div class="card-body pt-5">
                <form method="POST">
                    <div class="row mb-5">
                        <div class="col-md-12 mb-5">
                            <label class="form-label required">API Key do Autentique</label>
                            <input type="text" name="api_key" class="form-control form-control-solid" 
                                   value="<?= htmlspecialchars($config['api_key'] ?? '') ?>" 
                                   placeholder="Sua API Key do Autentique" required>
                            <div class="form-text">
                                Obtenha sua API Key em: <a href="https://autentique.com.br" target="_blank">https://autentique.com.br</a>
                            </div>
                        </div>
                        
                        <div class="col-md-12 mb-5">
                            <label class="form-label">Webhook URL (opcional)</label>
                            <input type="url" name="webhook_url" class="form-control form-control-solid" 
                                   value="<?= htmlspecialchars($config['webhook_url'] ?? '') ?>" 
                                   placeholder="https://seu-dominio.com/rh/api/contratos/webhook.php">
                            <div class="form-text">
                                URL para receber notificações de eventos (assinaturas, visualizações, etc)
                            </div>
                        </div>
                        
                        <div class="col-md-12">
                            <div class="form-check form-switch form-check-custom form-check-solid">
                                <input class="form-check-input" type="checkbox" name="sandbox" id="sandbox" 
                                       value="1" <?= ($config['sandbox'] ?? 1) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="sandbox">
                                    Modo Sandbox (Teste)
                                </label>
                            </div>
                            <div class="form-text">
                                Ative para usar o ambiente de testes do Autentique
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end gap-3">
                        <a href="contratos.php" class="btn btn-light">Voltar</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="ki-duotone ki-check fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Salvar Configuração
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <!--end::Card-->
        
        <?php endif; ?>
        
    </div>
</div>
<!--end::Post-->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
