<?php
/**
 * Configurações da OpenAI - Geração de Vagas com IA
 */

$page_title = 'Configurações OpenAI';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('configuracoes_openai.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Verifica e cria tabelas se não existirem
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'openai_config'");
    if ($stmt->rowCount() == 0) {
        // Executa migração
        $sql = file_get_contents(__DIR__ . '/../migracao_openai_config.sql');
        $pdo->exec($sql);
    }
} catch (PDOException $e) {
    // Tabelas já existem
}

$success = '';
$error = '';
$aba_ativa = $_GET['aba'] ?? 'configuracoes';

// Processa formulário de configurações
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'salvar_config') {
        $api_key = trim($_POST['api_key'] ?? '');
        $modelo = $_POST['modelo'] ?? 'gpt-4o-mini';
        $temperatura = floatval($_POST['temperatura'] ?? 0.7);
        $max_tokens = intval($_POST['max_tokens'] ?? 2000);
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        
        if (empty($api_key)) {
            $error = 'API Key é obrigatória!';
        } else {
            try {
                // Verifica se já existe configuração
                $stmt = $pdo->query("SELECT id FROM openai_config ORDER BY id DESC LIMIT 1");
                $existing = $stmt->fetch();
                
                if ($existing) {
                    // Atualiza
                    $stmt = $pdo->prepare("
                        UPDATE openai_config 
                        SET api_key = ?, modelo = ?, temperatura = ?, max_tokens = ?, ativo = ?, 
                            updated_by = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$api_key, $modelo, $temperatura, $max_tokens, $ativo, $usuario['id'], $existing['id']]);
                } else {
                    // Cria nova
                    $stmt = $pdo->prepare("
                        INSERT INTO openai_config (api_key, modelo, temperatura, max_tokens, ativo, updated_by)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$api_key, $modelo, $temperatura, $max_tokens, $ativo, $usuario['id']]);
                }
                
                $success = 'Configurações salvas com sucesso!';
            } catch (PDOException $e) {
                $error = 'Erro ao salvar: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'testar_conexao') {
        // Testa conexão com OpenAI
        require_once __DIR__ . '/../includes/openai_service.php';
        
        $resultado = testar_conexao_openai();
        
        if ($resultado['success']) {
            echo json_encode([
                'success' => true,
                'message' => 'Conexão estabelecida com sucesso! Modelo: ' . $resultado['modelo']
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Erro ao conectar: ' . $resultado['message']
            ]);
        }
        exit;
    } elseif ($action === 'salvar_template') {
        $id = intval($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $categoria = trim($_POST['categoria'] ?? '');
        $prompt_sistema = trim($_POST['prompt_sistema'] ?? '');
        $prompt_usuario = trim($_POST['prompt_usuario'] ?? '');
        $exemplo = trim($_POST['exemplo'] ?? '');
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        $ordem = intval($_POST['ordem'] ?? 0);
        
        try {
            $stmt = $pdo->prepare("
                UPDATE openai_prompt_templates 
                SET nome = ?, descricao = ?, categoria = ?, prompt_sistema = ?, 
                    prompt_usuario = ?, exemplo = ?, ativo = ?, ordem = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$nome, $descricao, $categoria, $prompt_sistema, $prompt_usuario, $exemplo, $ativo, $ordem, $id]);
            
            $success = 'Template atualizado com sucesso!';
            $aba_ativa = 'templates';
        } catch (PDOException $e) {
            $error = 'Erro ao salvar template: ' . $e->getMessage();
            $aba_ativa = 'templates';
        }
    }
}

// Busca configurações atuais
$stmt = $pdo->query("SELECT * FROM openai_config ORDER BY id DESC LIMIT 1");
$config = $stmt->fetch();

// Busca templates
$stmt = $pdo->query("SELECT * FROM openai_prompt_templates ORDER BY ordem ASC, nome ASC");
$templates = $stmt->fetchAll();

// Busca estatísticas
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_geracoes,
        SUM(foi_salva) as vagas_criadas,
        SUM(tokens_usados) as total_tokens,
        SUM(custo_estimado) as custo_total,
        AVG(qualidade_score) as qualidade_media,
        AVG(tempo_geracao_ms) as tempo_medio
    FROM vagas_geradas_ia
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$stats = $stmt->fetch();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_content" class="content d-flex flex-column flex-column-fluid">
        <div class="post d-flex flex-column-fluid" id="kt_post">
            <div id="kt_content_container" class="container-xxl">
                
                <!-- Page Title -->
                <div class="page-title d-flex flex-column align-items-start justify-content-center flex-wrap me-lg-2 pb-5">
                    <h1 class="page-heading d-flex text-dark fw-bold fs-3 flex-column justify-content-center my-0">
                        <i class="ki-duotone ki-robot fs-2 me-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Configurações OpenAI
                    </h1>
                    <ul class="breadcrumb fw-semibold fs-7 my-0 pt-1">
                        <li class="breadcrumb-item text-muted">
                            <a href="dashboard.php" class="text-muted text-hover-primary">Dashboard</a>
                        </li>
                        <li class="breadcrumb-item text-muted">Configurações</li>
                        <li class="breadcrumb-item text-dark">OpenAI</li>
                    </ul>
                </div>
                
                <?php if ($success): ?>
                <div class="alert alert-success d-flex align-items-center p-5 mb-10">
                    <i class="ki-duotone ki-check-circle fs-2hx text-success me-4">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    <div class="d-flex flex-column">
                        <h4 class="mb-1 text-success">Sucesso</h4>
                        <span><?= htmlspecialchars($success) ?></span>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div class="alert alert-danger d-flex align-items-center p-5 mb-10">
                    <i class="ki-duotone ki-cross-circle fs-2hx text-danger me-4">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    <div class="d-flex flex-column">
                        <h4 class="mb-1 text-danger">Erro</h4>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Card Principal -->
                <div class="card">
                    <div class="card-header border-0 pt-6">
                        <ul class="nav nav-stretch nav-line-tabs nav-line-tabs-2x border-transparent fs-5 fw-bold">
                            <li class="nav-item mt-2">
                                <a class="nav-link text-active-primary ms-0 me-10 <?= $aba_ativa === 'configuracoes' ? 'active' : '' ?>" 
                                   href="?aba=configuracoes">
                                    <i class="ki-duotone ki-gear fs-2 me-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    Configurações
                                </a>
                            </li>
                            <li class="nav-item mt-2">
                                <a class="nav-link text-active-primary me-10 <?= $aba_ativa === 'templates' ? 'active' : '' ?>" 
                                   href="?aba=templates">
                                    <i class="ki-duotone ki-message-text fs-2 me-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                    Templates
                                </a>
                            </li>
                            <li class="nav-item mt-2">
                                <a class="nav-link text-active-primary me-10 <?= $aba_ativa === 'estatisticas' ? 'active' : '' ?>" 
                                   href="?aba=estatisticas">
                                    <i class="ki-duotone ki-chart-simple fs-2 me-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                        <span class="path4"></span>
                                    </i>
                                    Estatísticas
                                </a>
                            </li>
                        </ul>
                    </div>
                    
                    <div class="card-body pt-0">
                        
                        <!-- Aba: Configurações -->
                        <?php if ($aba_ativa === 'configuracoes'): ?>
                        <div class="mt-7">
                            <form method="POST" id="formConfig">
                                <input type="hidden" name="action" value="salvar_config">
                                
                                <div class="row mb-7">
                                    <label class="col-lg-3 col-form-label required fw-semibold fs-6">API Key</label>
                                    <div class="col-lg-9">
                                        <input type="password" name="api_key" class="form-control form-control-solid" 
                                               value="<?= htmlspecialchars($config['api_key'] ?? '') ?>" 
                                               placeholder="sk-..." required />
                                        <div class="form-text">Obtenha em <a href="https://platform.openai.com/api-keys" target="_blank">platform.openai.com/api-keys</a></div>
                                    </div>
                                </div>
                                
                                <div class="row mb-7">
                                    <label class="col-lg-3 col-form-label required fw-semibold fs-6">Modelo</label>
                                    <div class="col-lg-9">
                                        <select name="modelo" class="form-select form-select-solid" required>
                                            <option value="gpt-4o" <?= ($config['modelo'] ?? '') === 'gpt-4o' ? 'selected' : '' ?>>
                                                GPT-4o (Mais inteligente) - ~$0.015/vaga
                                            </option>
                                            <option value="gpt-4o-mini" <?= ($config['modelo'] ?? 'gpt-4o-mini') === 'gpt-4o-mini' ? 'selected' : '' ?>>
                                                GPT-4o-mini (Recomendado) - ~$0.002/vaga
                                            </option>
                                            <option value="gpt-3.5-turbo" <?= ($config['modelo'] ?? '') === 'gpt-3.5-turbo' ? 'selected' : '' ?>>
                                                GPT-3.5 Turbo (Econômico) - ~$0.0005/vaga
                                            </option>
                                        </select>
                                        <div class="form-text">Modelo de linguagem a ser usado</div>
                                    </div>
                                </div>
                                
                                <div class="row mb-7">
                                    <label class="col-lg-3 col-form-label fw-semibold fs-6">Temperatura</label>
                                    <div class="col-lg-9">
                                        <input type="number" name="temperatura" class="form-control form-control-solid" 
                                               value="<?= $config['temperatura'] ?? 0.7 ?>" 
                                               min="0" max="1" step="0.1" />
                                        <div class="form-text">Criatividade (0.0 = mais preciso, 1.0 = mais criativo)</div>
                                    </div>
                                </div>
                                
                                <div class="row mb-7">
                                    <label class="col-lg-3 col-form-label fw-semibold fs-6">Max Tokens</label>
                                    <div class="col-lg-9">
                                        <input type="number" name="max_tokens" class="form-control form-control-solid" 
                                               value="<?= $config['max_tokens'] ?? 2000 ?>" 
                                               min="500" max="4000" step="100" />
                                        <div class="form-text">Limite de tokens por requisição (recomendado: 2000)</div>
                                    </div>
                                </div>
                                
                                <div class="row mb-7">
                                    <div class="col-lg-9 offset-lg-3">
                                        <div class="form-check form-check-custom form-check-solid">
                                            <input class="form-check-input" type="checkbox" name="ativo" id="ativo" 
                                                   value="1" <?= ($config['ativo'] ?? 1) ? 'checked' : '' ?> />
                                            <label class="form-check-label" for="ativo">
                                                Integração Ativa
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="separator separator-dashed my-10"></div>
                                
                                <div class="d-flex justify-content-end">
                                    <button type="button" class="btn btn-light me-3" id="btnTestarConexao">
                                        <i class="ki-duotone ki-check-circle fs-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        Testar Conexão
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        <span class="indicator-label">Salvar Configurações</span>
                                        <span class="indicator-progress">Salvando...
                                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                                    </button>
                                </div>
                            </form>
                            
                            <!-- Card de Status -->
                            <div class="card mt-10">
                                <div class="card-header">
                                    <div class="card-title">
                                        <h2>Status da Integração</h2>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <?php
                                    $status_config = $config && !empty($config['api_key']) ? 'ok' : 'erro';
                                    $status_ativo = $config && $config['ativo'] ? 'ok' : 'inativo';
                                    ?>
                                    
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="d-flex align-items-center p-4 border rounded">
                                                <i class="ki-duotone ki-<?= $status_config === 'ok' ? 'check-circle' : 'cross-circle' ?> fs-2hx text-<?= $status_config === 'ok' ? 'success' : 'danger' ?> me-4">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                                <div>
                                                    <div class="fw-bold fs-5">Configuração</div>
                                                    <div class="text-muted">
                                                        <?= $status_config === 'ok' ? '✅ Configurada' : '❌ Não configurada' ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="d-flex align-items-center p-4 border rounded">
                                                <i class="ki-duotone ki-<?= $status_ativo === 'ok' ? 'check-circle' : 'information-5' ?> fs-2hx text-<?= $status_ativo === 'ok' ? 'success' : 'warning' ?> me-4">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                                <div>
                                                    <div class="fw-bold fs-5">Status</div>
                                                    <div class="text-muted">
                                                        <?= $status_ativo === 'ok' ? '✅ Ativa' : '⚠️ Inativa' ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="d-flex align-items-center p-4 border rounded">
                                                <i class="ki-duotone ki-chart-simple fs-2hx text-primary me-4">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                    <span class="path3"></span>
                                                    <span class="path4"></span>
                                                </i>
                                                <div>
                                                    <div class="fw-bold fs-5">Modelo</div>
                                                    <div class="text-muted">
                                                        <?= htmlspecialchars($config['modelo'] ?? 'Não configurado') ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Aba: Templates -->
                        <?php if ($aba_ativa === 'templates'): ?>
                        <div class="mt-7">
                            <div class="table-responsive">
                                <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
                                    <thead>
                                        <tr class="fw-bold text-muted">
                                            <th class="min-w-150px">Nome</th>
                                            <th class="min-w-100px">Categoria</th>
                                            <th class="min-w-200px">Descrição</th>
                                            <th class="min-w-80px">Ordem</th>
                                            <th class="min-w-80px">Usado</th>
                                            <th class="min-w-80px">Status</th>
                                            <th class="text-end min-w-100px">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($templates as $template): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <i class="ki-duotone ki-message-text fs-2 text-primary me-3">
                                                        <span class="path1"></span>
                                                        <span class="path2"></span>
                                                        <span class="path3"></span>
                                                    </i>
                                                    <span class="fw-bold"><?= htmlspecialchars($template['nome']) ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge badge-light-primary"><?= htmlspecialchars($template['categoria']) ?></span>
                                            </td>
                                            <td>
                                                <small class="text-muted"><?= htmlspecialchars($template['descricao']) ?></small>
                                            </td>
                                            <td><?= $template['ordem'] ?></td>
                                            <td><span class="badge badge-light"><?= $template['vezes_usado'] ?>x</span></td>
                                            <td>
                                                <span class="badge badge-<?= $template['ativo'] ? 'success' : 'secondary' ?>">
                                                    <?= $template['ativo'] ? 'Ativo' : 'Inativo' ?>
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                <button type="button" class="btn btn-sm btn-light btn-active-light-primary" 
                                                        onclick="editarTemplate(<?= htmlspecialchars(json_encode($template)) ?>)">
                                                    Editar
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Aba: Estatísticas -->
                        <?php if ($aba_ativa === 'estatisticas'): ?>
                        <div class="mt-7">
                            <div class="row g-5 g-xl-8 mb-5">
                                <div class="col-xl-3">
                                    <div class="card card-flush bgi-no-repeat bgi-size-contain bgi-position-x-end h-xl-100" style="background-color: #F1416C">
                                        <div class="card-header pt-5">
                                            <div class="card-title d-flex flex-column">
                                                <span class="fs-2hx fw-bold text-white me-2 lh-1 ls-n2"><?= number_format($stats['total_geracoes'] ?? 0) ?></span>
                                                <span class="text-white opacity-75 pt-1 fw-semibold fs-6">Total de Gerações</span>
                                            </div>
                                        </div>
                                        <div class="card-body d-flex align-items-end pt-0">
                                            <div class="d-flex align-items-center flex-column mt-3 w-100">
                                                <div class="d-flex justify-content-between fw-bold fs-6 text-white opacity-75 w-100 mt-auto mb-2">
                                                    <span>Últimos 30 dias</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-xl-3">
                                    <div class="card card-flush bgi-no-repeat bgi-size-contain bgi-position-x-end h-xl-100" style="background-color: #7239EA">
                                        <div class="card-header pt-5">
                                            <div class="card-title d-flex flex-column">
                                                <span class="fs-2hx fw-bold text-white me-2 lh-1 ls-n2"><?= number_format($stats['vagas_criadas'] ?? 0) ?></span>
                                                <span class="text-white opacity-75 pt-1 fw-semibold fs-6">Vagas Criadas</span>
                                            </div>
                                        </div>
                                        <div class="card-body d-flex align-items-end pt-0">
                                            <div class="d-flex align-items-center flex-column mt-3 w-100">
                                                <div class="d-flex justify-content-between fw-bold fs-6 text-white opacity-75 w-100 mt-auto mb-2">
                                                    <span>Taxa: <?= $stats['total_geracoes'] > 0 ? round(($stats['vagas_criadas'] / $stats['total_geracoes']) * 100, 1) : 0 ?>%</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-xl-3">
                                    <div class="card card-flush bgi-no-repeat bgi-size-contain bgi-position-x-end h-xl-100" style="background-color: #50CD89">
                                        <div class="card-header pt-5">
                                            <div class="card-title d-flex flex-column">
                                                <span class="fs-2hx fw-bold text-white me-2 lh-1 ls-n2"><?= number_format($stats['qualidade_media'] ?? 0, 1) ?></span>
                                                <span class="text-white opacity-75 pt-1 fw-semibold fs-6">Qualidade Média</span>
                                            </div>
                                        </div>
                                        <div class="card-body d-flex align-items-end pt-0">
                                            <div class="d-flex align-items-center flex-column mt-3 w-100">
                                                <div class="d-flex justify-content-between fw-bold fs-6 text-white opacity-75 w-100 mt-auto mb-2">
                                                    <span>Score 0-100</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-xl-3">
                                    <div class="card card-flush bgi-no-repeat bgi-size-contain bgi-position-x-end h-xl-100" style="background-color: #FFC700">
                                        <div class="card-header pt-5">
                                            <div class="card-title d-flex flex-column">
                                                <span class="fs-2hx fw-bold text-white me-2 lh-1 ls-n2">$<?= number_format($stats['custo_total'] ?? 0, 2) ?></span>
                                                <span class="text-white opacity-75 pt-1 fw-semibold fs-6">Custo Total</span>
                                            </div>
                                        </div>
                                        <div class="card-body d-flex align-items-end pt-0">
                                            <div class="d-flex align-items-center flex-column mt-3 w-100">
                                                <div class="d-flex justify-content-between fw-bold fs-6 text-white opacity-75 w-100 mt-auto mb-2">
                                                    <span>Últimos 30 dias</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Histórico Recente -->
                            <div class="card">
                                <div class="card-header">
                                    <div class="card-title">
                                        <h2>Histórico Recente</h2>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <?php
                                    $stmt = $pdo->query("
                                        SELECT 
                                            vgi.*,
                                            u.nome as usuario_nome,
                                            e.nome_fantasia as empresa_nome,
                                            v.titulo as vaga_titulo
                                        FROM vagas_geradas_ia vgi
                                        LEFT JOIN usuarios u ON vgi.usuario_id = u.id
                                        LEFT JOIN empresas e ON vgi.empresa_id = e.id
                                        LEFT JOIN vagas v ON vgi.vaga_id = v.id
                                        ORDER BY vgi.created_at DESC
                                        LIMIT 20
                                    ");
                                    $historico = $stmt->fetchAll();
                                    ?>
                                    
                                    <div class="table-responsive">
                                        <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
                                            <thead>
                                                <tr class="fw-bold text-muted">
                                                    <th>Data</th>
                                                    <th>Usuário</th>
                                                    <th>Empresa</th>
                                                    <th>Modelo</th>
                                                    <th>Tokens</th>
                                                    <th>Custo</th>
                                                    <th>Tempo</th>
                                                    <th>Qualidade</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($historico as $item): ?>
                                                <tr>
                                                    <td><?= date('d/m/Y H:i', strtotime($item['created_at'])) ?></td>
                                                    <td><?= htmlspecialchars($item['usuario_nome']) ?></td>
                                                    <td><?= htmlspecialchars($item['empresa_nome']) ?></td>
                                                    <td><span class="badge badge-light"><?= htmlspecialchars($item['modelo_usado']) ?></span></td>
                                                    <td><?= number_format($item['tokens_usados']) ?></td>
                                                    <td>$<?= number_format($item['custo_estimado'], 4) ?></td>
                                                    <td><?= number_format($item['tempo_geracao_ms']) ?>ms</td>
                                                    <td>
                                                        <?php if ($item['qualidade_score']): ?>
                                                        <span class="badge badge-<?= $item['qualidade_score'] >= 80 ? 'success' : ($item['qualidade_score'] >= 60 ? 'warning' : 'danger') ?>">
                                                            <?= $item['qualidade_score'] ?>
                                                        </span>
                                                        <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-<?= $item['foi_salva'] ? 'success' : 'secondary' ?>">
                                                            <?= $item['foi_salva'] ? 'Salva' : 'Não salva' ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                                
                                                <?php if (empty($historico)): ?>
                                                <tr>
                                                    <td colspan="9" class="text-center text-muted">
                                                        Nenhuma geração encontrada
                                                    </td>
                                                </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                    </div>
                </div>
                
            </div>
        </div>
    </div>
</div>

<!-- Modal: Editar Template -->
<div class="modal fade" id="modalEditarTemplate" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Editar Template</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="salvar_template">
                <input type="hidden" name="id" id="template_id">
                <div class="modal-body">
                    <div class="row mb-5">
                        <div class="col-md-6">
                            <label class="form-label required">Nome</label>
                            <input type="text" name="nome" id="template_nome" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label required">Categoria</label>
                            <input type="text" name="categoria" id="template_categoria" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Ordem</label>
                            <input type="number" name="ordem" id="template_ordem" class="form-control" min="0">
                        </div>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label">Descrição</label>
                        <textarea name="descricao" id="template_descricao" class="form-control" rows="2"></textarea>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label required">Prompt do Sistema</label>
                        <textarea name="prompt_sistema" id="template_prompt_sistema" class="form-control" rows="4" required></textarea>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label required">Prompt do Usuário</label>
                        <textarea name="prompt_usuario" id="template_prompt_usuario" class="form-control" rows="4" required></textarea>
                        <div class="form-text">Use {descricao} onde a descrição do usuário deve ser inserida</div>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label">Exemplo</label>
                        <textarea name="exemplo" id="template_exemplo" class="form-control" rows="2"></textarea>
                    </div>
                    
                    <div class="form-check form-check-custom form-check-solid">
                        <input class="form-check-input" type="checkbox" name="ativo" id="template_ativo" value="1">
                        <label class="form-check-label" for="template_ativo">Template Ativo</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar Template</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Testar conexão
document.getElementById('btnTestarConexao')?.addEventListener('click', async function() {
    const btn = this;
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Testando...';
    
    try {
        const formData = new FormData();
        formData.append('action', 'testar_conexao');
        
        const response = await fetch('configuracoes_openai.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Conexão OK!',
                text: data.message,
                buttonsStyling: false,
                confirmButtonText: 'Ok',
                customClass: {
                    confirmButton: 'btn btn-primary'
                }
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Erro na Conexão',
                text: data.message,
                buttonsStyling: false,
                confirmButtonText: 'Ok',
                customClass: {
                    confirmButton: 'btn btn-primary'
                }
            });
        }
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: 'Erro ao testar conexão: ' + error.message,
            buttonsStyling: false,
            confirmButtonText: 'Ok',
            customClass: {
                confirmButton: 'btn btn-primary'
            }
        });
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    }
});

// Editar template
function editarTemplate(template) {
    document.getElementById('template_id').value = template.id;
    document.getElementById('template_nome').value = template.nome;
    document.getElementById('template_categoria').value = template.categoria;
    document.getElementById('template_descricao').value = template.descricao || '';
    document.getElementById('template_prompt_sistema').value = template.prompt_sistema;
    document.getElementById('template_prompt_usuario').value = template.prompt_usuario;
    document.getElementById('template_exemplo').value = template.exemplo || '';
    document.getElementById('template_ordem').value = template.ordem;
    document.getElementById('template_ativo').checked = template.ativo == 1;
    
    const modal = new bootstrap.Modal(document.getElementById('modalEditarTemplate'));
    modal.show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
