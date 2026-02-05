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

// Verifica se as novas colunas existem e adiciona se necessário
if ($tabela_existe) {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM autentique_config LIKE 'representante_nome'");
        if (!$stmt->fetch()) {
            // Adiciona as novas colunas
            $pdo->exec("
                ALTER TABLE autentique_config 
                ADD COLUMN representante_nome VARCHAR(255) NULL COMMENT 'Nome do representante/sócio que assina contratos',
                ADD COLUMN representante_email VARCHAR(255) NULL COMMENT 'Email do representante para assinatura',
                ADD COLUMN representante_cpf VARCHAR(14) NULL COMMENT 'CPF do representante',
                ADD COLUMN representante_cargo VARCHAR(100) NULL COMMENT 'Cargo do representante (ex: Sócio, Diretor, RH)',
                ADD COLUMN empresa_cnpj VARCHAR(18) NULL COMMENT 'CNPJ da empresa contratante'
            ");
        }
    } catch (Exception $e) {
        // Ignora se colunas já existem
    }
}

// Processa POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tabela_existe) {
    $api_key = trim($_POST['api_key'] ?? '');
    $sandbox = isset($_POST['sandbox']) ? 1 : 0;
    $webhook_url = trim($_POST['webhook_url'] ?? '');
    
    // Dados do representante
    $representante_nome = trim($_POST['representante_nome'] ?? '');
    $representante_email = trim($_POST['representante_email'] ?? '');
    $representante_cpf = trim($_POST['representante_cpf'] ?? '');
    $representante_cargo = trim($_POST['representante_cargo'] ?? '');
    $empresa_cnpj = trim($_POST['empresa_cnpj'] ?? '');
    
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
                SET api_key = ?, sandbox = ?, webhook_url = ?, ativo = 1,
                    representante_nome = ?, representante_email = ?, 
                    representante_cpf = ?, representante_cargo = ?, empresa_cnpj = ?
                WHERE id = (SELECT id FROM (SELECT MIN(id) as id FROM autentique_config) as temp)
            ");
            $stmt->execute([
                $api_key, $sandbox, $webhook_url,
                $representante_nome, $representante_email, 
                $representante_cpf, $representante_cargo, $empresa_cnpj
            ]);
        } else {
            // Insere
            $stmt = $pdo->prepare("
                INSERT INTO autentique_config 
                (api_key, sandbox, webhook_url, ativo, representante_nome, representante_email, representante_cpf, representante_cargo, empresa_cnpj)
                VALUES (?, ?, ?, 1, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $api_key, $sandbox, $webhook_url,
                $representante_nome, $representante_email, 
                $representante_cpf, $representante_cargo, $empresa_cnpj
            ]);
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
                <li class="breadcrumb-item text-muted">
                    <a href="contratos.php" class="text-muted text-hover-primary">Contratos</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Configuração</li>
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
        
        <form method="POST">
            <div class="row">
                <!--begin::Col - API Config-->
                <div class="col-lg-6">
                    <!--begin::Card - API-->
                    <div class="card mb-5">
                        <div class="card-header border-0 pt-5">
                            <h3 class="card-title align-items-start flex-column">
                                <span class="card-label fw-bold fs-3 mb-1">
                                    <i class="ki-duotone ki-setting-2 fs-2 me-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    Configuração da API
                                </span>
                                <span class="text-muted fw-semibold fs-7">Credenciais de acesso ao Autentique</span>
                            </h3>
                        </div>
                        <div class="card-body pt-5">
                            <div class="mb-5">
                                <label class="form-label required">API Key do Autentique</label>
                                <input type="text" name="api_key" class="form-control form-control-solid" 
                                       value="<?= htmlspecialchars($config['api_key'] ?? '') ?>" 
                                       placeholder="Sua API Key do Autentique" required>
                                <div class="form-text">
                                    Obtenha sua API Key em: <a href="https://autentique.com.br" target="_blank">autentique.com.br</a>
                                </div>
                            </div>
                            
                            <div class="mb-5">
                                <label class="form-label">Webhook URL (opcional)</label>
                                <input type="url" name="webhook_url" class="form-control form-control-solid" 
                                       value="<?= htmlspecialchars($config['webhook_url'] ?? '') ?>" 
                                       placeholder="https://seu-dominio.com/rh/api/contratos/webhook.php">
                                <div class="form-text">
                                    URL para receber notificações de eventos
                                </div>
                            </div>
                            
                            <div>
                                <div class="form-check form-switch form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" name="sandbox" id="sandbox" 
                                           value="1" <?= ($config['sandbox'] ?? 1) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="sandbox">
                                        Modo Sandbox (Teste)
                                    </label>
                                </div>
                                <div class="form-text">
                                    Ative para usar o ambiente de testes
                                </div>
                            </div>
                        </div>
                    </div>
                    <!--end::Card-->
                </div>
                <!--end::Col-->
                
                <!--begin::Col - Representante-->
                <div class="col-lg-6">
                    <!--begin::Card - Representante-->
                    <div class="card mb-5">
                        <div class="card-header border-0 pt-5">
                            <h3 class="card-title align-items-start flex-column">
                                <span class="card-label fw-bold fs-3 mb-1">
                                    <i class="ki-duotone ki-user-tick fs-2 me-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                    Representante da Empresa
                                </span>
                                <span class="text-muted fw-semibold fs-7">Sócio/RH que assina os contratos pela empresa</span>
                            </h3>
                        </div>
                        <div class="card-body pt-5">
                            <div class="alert alert-info d-flex align-items-center mb-5">
                                <i class="ki-duotone ki-information fs-2hx text-info me-3">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                </i>
                                <div class="d-flex flex-column">
                                    <span class="fs-7">
                                        Este representante será incluído automaticamente como signatário em todos os contratos. 
                                        Você poderá editar os dados no momento do envio se necessário.
                                    </span>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-8 mb-5">
                                    <label class="form-label">Nome do Representante</label>
                                    <input type="text" name="representante_nome" class="form-control form-control-solid" 
                                           value="<?= htmlspecialchars($config['representante_nome'] ?? '') ?>" 
                                           placeholder="Ex: João da Silva">
                                </div>
                                <div class="col-md-4 mb-5">
                                    <label class="form-label">Cargo</label>
                                    <input type="text" name="representante_cargo" class="form-control form-control-solid" 
                                           value="<?= htmlspecialchars($config['representante_cargo'] ?? '') ?>" 
                                           placeholder="Ex: Sócio, Diretor">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-5">
                                    <label class="form-label">Email do Representante</label>
                                    <input type="email" name="representante_email" class="form-control form-control-solid" 
                                           value="<?= htmlspecialchars($config['representante_email'] ?? '') ?>" 
                                           placeholder="email@empresa.com">
                                    <div class="form-text">Email para receber o link de assinatura</div>
                                </div>
                                <div class="col-md-6 mb-5">
                                    <label class="form-label">CPF do Representante</label>
                                    <input type="text" name="representante_cpf" class="form-control form-control-solid" 
                                           value="<?= htmlspecialchars($config['representante_cpf'] ?? '') ?>" 
                                           placeholder="000.000.000-00">
                                </div>
                            </div>
                            
                            <div class="mb-0">
                                <label class="form-label">CNPJ da Empresa</label>
                                <input type="text" name="empresa_cnpj" class="form-control form-control-solid" 
                                       value="<?= htmlspecialchars($config['empresa_cnpj'] ?? '') ?>" 
                                       placeholder="00.000.000/0000-00">
                                <div class="form-text">CNPJ da empresa contratante</div>
                            </div>
                        </div>
                    </div>
                    <!--end::Card-->
                </div>
                <!--end::Col-->
            </div>
            
            <!--begin::Actions-->
            <div class="card">
                <div class="card-footer d-flex justify-content-end gap-3 py-6 px-9">
                    <a href="contratos.php" class="btn btn-light">Voltar</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="ki-duotone ki-check fs-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Salvar Configuração
                    </button>
                </div>
            </div>
            <!--end::Actions-->
        </form>
        
        <?php endif; ?>
        
    </div>
</div>
<!--end::Post-->

<script>
// Máscaras para CPF e CNPJ
document.querySelector('[name="representante_cpf"]')?.addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length <= 11) {
        value = value.replace(/(\d{3})(\d)/, '$1.$2');
        value = value.replace(/(\d{3})(\d)/, '$1.$2');
        value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
    }
    e.target.value = value;
});

document.querySelector('[name="empresa_cnpj"]')?.addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length <= 14) {
        value = value.replace(/^(\d{2})(\d)/, '$1.$2');
        value = value.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
        value = value.replace(/\.(\d{3})(\d)/, '.$1/$2');
        value = value.replace(/(\d{4})(\d)/, '$1-$2');
    }
    e.target.value = value;
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
