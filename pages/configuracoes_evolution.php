<?php
/**
 * Configurações - Integração Evolution API (WhatsApp)
 */

$page_title = 'Configurações WhatsApp (Evolution API)';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/evolution_service.php';

require_page_permission('configuracoes_evolution.php');

$pdo     = getDB();
$usuario = $_SESSION['usuario'];

// Garante que as tabelas existam
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'evolution_config'");
    if ($stmt->rowCount() === 0) {
        $sql = file_get_contents(__DIR__ . '/../migracao_evolution_api.sql');
        $pdo->exec($sql);
    }
} catch (PDOException $e) {
    // Já existem
}

$success   = '';
$error     = '';
$aba_ativa = $_GET['aba'] ?? 'configuracoes';

// ─── Processar POST ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'salvar_config') {
        $api_url      = rtrim(trim($_POST['api_url'] ?? ''), '/');
        $api_key      = trim($_POST['api_key'] ?? '');
        $instance     = trim($_POST['instance_name'] ?? '');
        $notif_ativas = isset($_POST['notificacoes_whatsapp_ativas']) ? 1 : 0;
        $ativo        = isset($_POST['ativo']) ? 1 : 0;

        if (empty($api_url) || empty($api_key) || empty($instance)) {
            $error = 'URL da API, API Key e Nome da Instância são obrigatórios!';
        } else {
            try {
                $existing = $pdo->query("SELECT id FROM evolution_config ORDER BY id DESC LIMIT 1")->fetch();

                if ($existing) {
                    $stmt = $pdo->prepare("
                        UPDATE evolution_config
                        SET api_url = ?, api_key = ?, instance_name = ?,
                            notificacoes_whatsapp_ativas = ?, ativo = ?, updated_by = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$api_url, $api_key, $instance, $notif_ativas, $ativo, $usuario['id'], $existing['id']]);
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO evolution_config (api_url, api_key, instance_name, notificacoes_whatsapp_ativas, ativo, updated_by)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$api_url, $api_key, $instance, $notif_ativas, $ativo, $usuario['id']]);
                }

                $success = 'Configurações salvas com sucesso!';
            } catch (PDOException $e) {
                $error = 'Erro ao salvar: ' . $e->getMessage();
            }
        }

    } elseif ($action === 'salvar_pesquisa_humor') {
        $horario          = $_POST['horario_pesquisa_humor'] ?? '09:00';
        $pesquisa_ativa   = isset($_POST['pesquisa_humor_ativa']) ? 1 : 0;
        $dias             = implode(',', array_filter(array_map('intval', $_POST['dias_semana'] ?? [])));
        $mensagem_custom  = sanitize($_POST['mensagem_pesquisa_humor'] ?? '');

        try {
            $existing = $pdo->query("SELECT id FROM evolution_config ORDER BY id DESC LIMIT 1")->fetch();

            if (!$existing) {
                $error = 'Salve primeiro as configurações da API antes de configurar a pesquisa de humor.';
            } else {
                $stmt = $pdo->prepare("
                    UPDATE evolution_config
                    SET horario_pesquisa_humor = ?, pesquisa_humor_ativa = ?,
                        dias_pesquisa_humor = ?, mensagem_pesquisa_humor = ?,
                        updated_by = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$horario . ':00', $pesquisa_ativa, $dias ?: '1,2,3,4,5', $mensagem_custom, $usuario['id'], $existing['id']]);
                $success = 'Configurações da pesquisa de humor salvas!';
            }
        } catch (PDOException $e) {
            $error = 'Erro ao salvar: ' . $e->getMessage();
        }

        $aba_ativa = 'pesquisa_humor';

    } elseif ($action === 'testar_conexao') {
        $resultado = evolution_verificar_conexao();

        if ($resultado['success'] && $resultado['connected']) {
            $success = '✅ Conexão OK! Instância conectada ao WhatsApp (estado: ' . ($resultado['state'] ?? 'open') . ').';
        } elseif ($resultado['success']) {
            $error = '⚠️ API acessível mas instância desconectada. Estado: ' . ($resultado['state'] ?? 'desconhecido') . '. Verifique o QR Code na Evolution API.';
        } else {
            $error = '❌ Falha na conexão: ' . ($resultado['error'] ?? 'Erro desconhecido');
        }

    } elseif ($action === 'enviar_teste') {
        $numero_teste  = preg_replace('/\D/', '', $_POST['numero_teste'] ?? '');
        $mensagem_test = trim($_POST['mensagem_teste'] ?? 'Teste de conexão do RH Privus via Evolution API. ✅');

        if (strlen($numero_teste) < 10) {
            $error = 'Número de teste inválido.';
        } else {
            $result = evolution_enviar_texto($numero_teste, $mensagem_test, null, 'manual');
            if ($result['success']) {
                $success = '✅ Mensagem de teste enviada com sucesso!';
            } else {
                $error = '❌ Falha no envio: ' . ($result['error'] ?? $result['raw'] ?? 'Erro desconhecido');
            }
        }
    }
}

// ─── Busca configuração atual ────────────────────────────────────────────────
$config = null;
try {
    $config = $pdo->query("SELECT * FROM evolution_config ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// ─── Status da conexão ───────────────────────────────────────────────────────
$status_conexao = null;
if ($config && $config['ativo']) {
    $status_conexao = evolution_verificar_conexao($config);
}

// ─── Colaboradores com/sem WhatsApp ─────────────────────────────────────────
$stats_whatsapp = [];
try {
    $row = $pdo->query("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN whatsapp_numero IS NOT NULL AND whatsapp_numero != '' THEN 1 ELSE 0 END) as com_numero,
            SUM(CASE WHEN whatsapp_ativo = 1 AND whatsapp_numero IS NOT NULL AND whatsapp_numero != '' THEN 1 ELSE 0 END) as ativos
        FROM colaboradores WHERE status = 'ativo'
    ")->fetch(PDO::FETCH_ASSOC);
    $stats_whatsapp = $row;
} catch (Exception $e) {}

// ─── Log recente ────────────────────────────────────────────────────────────
$log_recente = [];
try {
    $log_recente = $pdo->query("
        SELECT l.*, c.nome_completo
        FROM evolution_mensagens_log l
        LEFT JOIN colaboradores c ON l.colaborador_id = c.id
        ORDER BY l.created_at DESC
        LIMIT 20
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$dias_map = [0 => 'Dom', 1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb'];
$dias_ativos = $config ? array_map('intval', explode(',', $config['dias_pesquisa_humor'] ?? '1,2,3,4,5')) : [1,2,3,4,5];

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
        <div id="kt_app_toolbar_container" class="app-container container-xxl d-flex flex-stack">
            <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
                <h1 class="page-heading d-flex text-dark fw-bold fs-3 flex-column justify-content-center my-0">
                    <i class="ki-duotone ki-whatsapp fs-2 me-2 text-success">
                        <span class="path1"></span><span class="path2"></span>
                    </i>
                    Configurações WhatsApp
                </h1>
                <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0 pt-1">
                    <li class="breadcrumb-item text-muted"><a href="dashboard.php" class="text-muted text-hover-primary">Início</a></li>
                    <li class="breadcrumb-item"><span class="bullet bg-gray-400 w-5px h-2px"></span></li>
                    <li class="breadcrumb-item text-muted">Configurações</li>
                    <li class="breadcrumb-item"><span class="bullet bg-gray-400 w-5px h-2px"></span></li>
                    <li class="breadcrumb-item text-muted">WhatsApp</li>
                </ul>
            </div>
        </div>
    </div>

    <div id="kt_app_content" class="app-content flex-column-fluid">
        <div id="kt_app_content_container" class="app-container container-xxl">

            <?php if ($success): ?>
            <div class="alert alert-success d-flex align-items-center mb-5">
                <i class="ki-duotone ki-check-circle fs-2 me-3 text-success"><span class="path1"></span><span class="path2"></span></i>
                <?= htmlspecialchars($success) ?>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="alert alert-danger d-flex align-items-center mb-5">
                <i class="ki-duotone ki-cross-circle fs-2 me-3 text-danger"><span class="path1"></span><span class="path2"></span></i>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <!-- Cards de Status -->
            <div class="row g-5 mb-6">
                <div class="col-md-3">
                    <div class="card card-flush h-100">
                        <div class="card-body d-flex align-items-center py-6">
                            <div class="symbol symbol-50px me-4">
                                <span class="symbol-label bg-light-<?= ($config && $config['ativo']) ? 'success' : 'danger' ?>">
                                    <i class="ki-duotone ki-wifi fs-2 text-<?= ($config && $config['ativo']) ? 'success' : 'danger' ?>">
                                        <span class="path1"></span><span class="path2"></span><span class="path3"></span>
                                    </i>
                                </span>
                            </div>
                            <div>
                                <div class="fs-7 text-muted fw-semibold">Evolution API</div>
                                <div class="fw-bold fs-6 <?= ($config && $config['ativo']) ? 'text-success' : 'text-danger' ?>">
                                    <?= ($config && $config['ativo']) ? 'Ativa' : 'Inativa' ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card card-flush h-100">
                        <div class="card-body d-flex align-items-center py-6">
                            <div class="symbol symbol-50px me-4">
                                <span class="symbol-label bg-light-<?= ($status_conexao && $status_conexao['connected']) ? 'success' : 'warning' ?>">
                                    <i class="ki-duotone ki-phone fs-2 text-<?= ($status_conexao && $status_conexao['connected']) ? 'success' : 'warning' ?>">
                                        <span class="path1"></span><span class="path2"></span>
                                    </i>
                                </span>
                            </div>
                            <div>
                                <div class="fs-7 text-muted fw-semibold">WhatsApp</div>
                                <div class="fw-bold fs-6">
                                    <?php if ($status_conexao === null): ?>
                                        <span class="text-muted">Não verificado</span>
                                    <?php elseif ($status_conexao['connected']): ?>
                                        <span class="text-success">Conectado</span>
                                    <?php else: ?>
                                        <span class="text-warning"><?= htmlspecialchars($status_conexao['state'] ?? 'Desconectado') ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card card-flush h-100">
                        <div class="card-body d-flex align-items-center py-6">
                            <div class="symbol symbol-50px me-4">
                                <span class="symbol-label bg-light-primary">
                                    <i class="ki-duotone ki-people fs-2 text-primary">
                                        <span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span>
                                    </i>
                                </span>
                            </div>
                            <div>
                                <div class="fs-7 text-muted fw-semibold">Colaboradores com WA</div>
                                <div class="fw-bold fs-6"><?= ($stats_whatsapp['ativos'] ?? 0) ?> / <?= ($stats_whatsapp['total'] ?? 0) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card card-flush h-100">
                        <div class="card-body d-flex align-items-center py-6">
                            <div class="symbol symbol-50px me-4">
                                <span class="symbol-label bg-light-<?= ($config && $config['pesquisa_humor_ativa']) ? 'success' : 'secondary' ?>">
                                    <i class="ki-duotone ki-emoji-happy fs-2 text-<?= ($config && $config['pesquisa_humor_ativa']) ? 'success' : 'secondary' ?>">
                                        <span class="path1"></span><span class="path2"></span><span class="path3"></span>
                                    </i>
                                </span>
                            </div>
                            <div>
                                <div class="fs-7 text-muted fw-semibold">Pesquisa de Humor</div>
                                <div class="fw-bold fs-6 <?= ($config && $config['pesquisa_humor_ativa']) ? 'text-success' : 'text-muted' ?>">
                                    <?= ($config && $config['pesquisa_humor_ativa']) ? 'Ativa às ' . substr($config['horario_pesquisa_humor'] ?? '09:00', 0, 5) : 'Inativa' ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <ul class="nav nav-tabs nav-line-tabs nav-line-tabs-2x fs-6 mb-6 border-0">
                <li class="nav-item">
                    <a class="nav-link <?= $aba_ativa === 'configuracoes' ? 'active' : '' ?>" href="?aba=configuracoes">
                        <i class="ki-duotone ki-setting-2 fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                        Configurações da API
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $aba_ativa === 'pesquisa_humor' ? 'active' : '' ?>" href="?aba=pesquisa_humor">
                        <i class="ki-duotone ki-emoji-happy fs-4 me-1"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                        Pesquisa de Humor
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $aba_ativa === 'teste' ? 'active' : '' ?>" href="?aba=teste">
                        <i class="ki-duotone ki-send fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                        Testar Envio
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $aba_ativa === 'log' ? 'active' : '' ?>" href="?aba=log">
                        <i class="ki-duotone ki-time fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                        Log de Mensagens
                    </a>
                </li>
            </ul>

            <!-- ═══ ABA: Configurações da API ════════════════════════════════════════════ -->
            <?php if ($aba_ativa === 'configuracoes'): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Conexão com a Evolution API</h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-info mb-6">
                        <i class="ki-duotone ki-information-5 fs-2 me-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                        A <strong>Evolution API</strong> é um servidor auto-hospedado que conecta ao WhatsApp Web.
                        Você precisa ter uma instância rodando e um número de WhatsApp conectado (via QR Code) antes de configurar aqui.
                    </div>

                    <form method="POST">
                        <input type="hidden" name="action" value="salvar_config">

                        <div class="row mb-6">
                            <div class="col-md-8">
                                <label class="form-label required">URL da Evolution API</label>
                                <input type="url" name="api_url" class="form-control"
                                       placeholder="https://api.suaempresa.com.br"
                                       value="<?= htmlspecialchars($config['api_url'] ?? '') ?>" required>
                                <div class="form-text">URL base sem barra no final. Ex: <code>https://evolution.suaempresa.com.br</code></div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label required">Nome da Instância</label>
                                <input type="text" name="instance_name" class="form-control"
                                       placeholder="rh-privus"
                                       value="<?= htmlspecialchars($config['instance_name'] ?? '') ?>" required>
                            </div>
                        </div>

                        <div class="row mb-6">
                            <div class="col-md-12">
                                <label class="form-label required">API Key</label>
                                <div class="input-group">
                                    <input type="password" name="api_key" id="api_key" class="form-control"
                                           placeholder="Sua API Key global da Evolution API"
                                           value="<?= htmlspecialchars($config['api_key'] ?? '') ?>" required>
                                    <button type="button" class="btn btn-light-secondary" onclick="toggleApiKey()">
                                        <i class="ki-duotone ki-eye fs-2" id="eye_icon"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                    </button>
                                </div>
                                <div class="form-text">Encontrado no arquivo de configuração da sua Evolution API (<code>AUTHENTICATION_API_KEY</code>)</div>
                            </div>
                        </div>

                        <div class="separator my-6"></div>

                        <div class="row mb-6">
                            <div class="col-md-6">
                                <div class="form-check form-switch form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" name="notificacoes_whatsapp_ativas" id="notif_wa"
                                           <?= (!$config || $config['notificacoes_whatsapp_ativas']) ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold ms-3" for="notif_wa">
                                        Enviar notificações do sistema via WhatsApp
                                    </label>
                                </div>
                                <div class="form-text">Quando ativo, eventos como feedback, promoções e documentos também serão enviados ao WA do colaborador.</div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" name="ativo" id="evolution_ativo"
                                           <?= (!$config || $config['ativo']) ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold ms-3" for="evolution_ativo">
                                        Integração ativa
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <form method="POST" class="m-0">
                                <input type="hidden" name="action" value="testar_conexao">
                                <button type="submit" class="btn btn-light-primary">
                                    <i class="ki-duotone ki-wifi fs-2 me-1"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                    Verificar Conexão
                                </button>
                            </form>
                            <button type="submit" form="" onclick="this.closest('form').submit()" class="btn btn-primary">
                                <i class="ki-duotone ki-check fs-2 me-1"><span class="path1"></span><span class="path2"></span></i>
                                Salvar Configurações
                            </button>
                        </div>
                    </form>

                    <!-- Webhook Info -->
                    <div class="separator my-8"></div>
                    <h5 class="mb-4">Configuração do Webhook</h5>
                    <div class="alert alert-light-primary border border-primary border-dashed">
                        <p class="mb-2">Configure na sua Evolution API o seguinte URL de webhook para receber respostas dos colaboradores:</p>
                        <code class="fs-6"><?= get_base_url() ?>/api/evolution/webhook.php</code>
                        <button class="btn btn-sm btn-light ms-3" onclick="navigator.clipboard.writeText('<?= get_base_url() ?>/api/evolution/webhook.php')">
                            <i class="ki-duotone ki-copy fs-4"><span class="path1"></span><span class="path2"></span></i>
                            Copiar
                        </button>
                        <p class="mt-2 mb-0 text-muted fs-7">Eventos necessários: <code>MESSAGES_UPSERT</code></p>
                    </div>
                </div>
            </div>

            <!-- ═══ ABA: Pesquisa de Humor ════════════════════════════════════════════ -->
            <?php elseif ($aba_ativa === 'pesquisa_humor'): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Pesquisa de Humor Diário</h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-info mb-6">
                        <i class="ki-duotone ki-information-5 fs-2 me-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                        A pesquisa de humor é enviada automaticamente via WhatsApp no horário configurado.
                        O colaborador responde com um botão (1 a 5) e o sistema registra e exibe no
                        <a href="relatorio_humor_whatsapp.php" class="fw-bold">Relatório de Humor</a>.
                        Configure o cron: <code>php <?= realpath(__DIR__ . '/../cron/enviar_pesquisa_humor_whatsapp.php') ?></code>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="action" value="salvar_pesquisa_humor">

                        <div class="row mb-6">
                            <div class="col-md-4">
                                <label class="form-label">Horário de Envio</label>
                                <input type="time" name="horario_pesquisa_humor" class="form-control"
                                       value="<?= substr($config['horario_pesquisa_humor'] ?? '09:00:00', 0, 5) ?>">
                                <div class="form-text">Horário de Brasília (America/Sao_Paulo)</div>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Dias da Semana</label>
                                <div class="d-flex flex-wrap gap-3 mt-1">
                                    <?php foreach ($dias_map as $num => $nome): ?>
                                    <div class="form-check form-check-custom form-check-solid">
                                        <input class="form-check-input" type="checkbox" name="dias_semana[]"
                                               value="<?= $num ?>" id="dia_<?= $num ?>"
                                               <?= in_array($num, $dias_ativos) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="dia_<?= $num ?>"><?= $nome ?></label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div class="mb-6">
                            <label class="form-label">Mensagem Personalizada</label>
                            <textarea name="mensagem_pesquisa_humor" class="form-control" rows="4"
                                      placeholder="Use {nome} para inserir o primeiro nome do colaborador. Deixe em branco para usar a mensagem padrão."><?= htmlspecialchars($config['mensagem_pesquisa_humor'] ?? '') ?></textarea>
                            <div class="form-text">
                                Padrão: <em>"Bom dia, {nome}! 😊 Como você está se sentindo hoje? Sua resposta nos ajuda a cuidar melhor do time!"</em>
                            </div>
                        </div>

                        <div class="mb-8">
                            <div class="form-check form-switch form-check-custom form-check-solid">
                                <input class="form-check-input" type="checkbox" name="pesquisa_humor_ativa" id="pesquisa_ativa"
                                       <?= ($config && $config['pesquisa_humor_ativa']) ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold ms-3" for="pesquisa_ativa">
                                    Pesquisa de humor ativa
                                </label>
                            </div>
                        </div>

                        <!-- Preview da pesquisa -->
                        <div class="card bg-light-success border border-success border-dashed mb-6">
                            <div class="card-body">
                                <h6 class="mb-3">📱 Preview da mensagem</h6>
                                <div class="bg-white rounded p-4 shadow-sm" style="max-width: 380px; font-family: inherit;">
                                    <div class="fw-semibold text-dark mb-2">🌡️ Pesquisa de Humor Diário</div>
                                    <div class="text-muted fs-7 mb-3">Bom dia, <strong>Maria</strong>! 😊<br>Como você está se sentindo hoje?</div>
                                    <div class="d-flex flex-column gap-2">
                                        <button type="button" class="btn btn-sm btn-light-success text-start">😄 Ótimo</button>
                                        <button type="button" class="btn btn-sm btn-light-primary text-start">🙂 Bem</button>
                                        <button type="button" class="btn btn-sm btn-light-warning text-start">😐 Regular</button>
                                        <button type="button" class="btn btn-sm btn-light-danger text-start">😕 Mal</button>
                                        <button type="button" class="btn btn-sm btn-danger text-start">😞 Muito mal</button>
                                    </div>
                                    <div class="text-muted fs-8 mt-2">RH Privus</div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="ki-duotone ki-check fs-2 me-1"><span class="path1"></span><span class="path2"></span></i>
                                Salvar Configurações
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ═══ ABA: Testar Envio ════════════════════════════════════════════ -->
            <?php elseif ($aba_ativa === 'teste'): ?>
            <div class="row g-5">
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h3 class="card-title">Enviar Mensagem de Teste</h3>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="enviar_teste">
                                <div class="mb-5">
                                    <label class="form-label required">Número de Destino</label>
                                    <input type="text" name="numero_teste" class="form-control"
                                           placeholder="11999999999" required>
                                    <div class="form-text">Somente dígitos, com DDD. Ex: 11999999999</div>
                                </div>
                                <div class="mb-5">
                                    <label class="form-label">Mensagem</label>
                                    <textarea name="mensagem_teste" class="form-control" rows="4">Teste de conexão do RH Privus via Evolution API. ✅</textarea>
                                </div>
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="ki-duotone ki-send fs-2 me-1"><span class="path1"></span><span class="path2"></span></i>
                                    Enviar Mensagem de Teste
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h3 class="card-title">Verificar Conexão</h3>
                        </div>
                        <div class="card-body">
                            <p class="text-muted">Verifica se a instância do WhatsApp está conectada na Evolution API.</p>
                            <?php if ($status_conexao): ?>
                            <div class="alert alert-<?= $status_conexao['connected'] ? 'success' : 'warning' ?>">
                                <strong>Estado:</strong> <?= htmlspecialchars($status_conexao['state'] ?? 'desconhecido') ?>
                                <?php if ($status_conexao['connected']): ?>
                                <br><i class="ki-duotone ki-check-circle fs-2 text-success"><span class="path1"></span><span class="path2"></span></i>
                                WhatsApp conectado e funcionando!
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            <form method="POST">
                                <input type="hidden" name="action" value="testar_conexao">
                                <button type="submit" class="btn btn-light-primary w-100">
                                    <i class="ki-duotone ki-wifi fs-2 me-1"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                    Verificar Status da Instância
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ═══ ABA: Log ════════════════════════════════════════════ -->
            <?php elseif ($aba_ativa === 'log'): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Log de Mensagens WhatsApp</h3>
                    <div class="card-toolbar">
                        <span class="badge badge-light-primary">Últimas 20</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-row-bordered table-row-gray-100 align-middle gs-4 gy-3">
                            <thead>
                                <tr class="fw-bold text-muted">
                                    <th>Data/Hora</th>
                                    <th>Colaborador</th>
                                    <th>Número</th>
                                    <th>Tipo</th>
                                    <th>Status</th>
                                    <th>Mensagem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($log_recente)): ?>
                                <tr><td colspan="6" class="text-center py-8 text-muted">Nenhuma mensagem enviada ainda.</td></tr>
                                <?php else: ?>
                                <?php foreach ($log_recente as $log): ?>
                                <tr>
                                    <td class="text-nowrap"><?= formatar_data($log['created_at'], 'd/m/Y H:i') ?></td>
                                    <td><?= htmlspecialchars($log['nome_completo'] ?? '—') ?></td>
                                    <td><code><?= htmlspecialchars($log['numero_destino']) ?></code></td>
                                    <td>
                                        <?php $tipo_badges = ['notificacao' => 'primary', 'pesquisa_humor' => 'success', 'manual' => 'warning', 'resposta' => 'info']; ?>
                                        <span class="badge badge-light-<?= $tipo_badges[$log['tipo']] ?? 'secondary' ?>">
                                            <?= ucfirst(str_replace('_', ' ', $log['tipo'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-light-<?= $log['status'] === 'enviado' ? 'success' : ($log['status'] === 'erro' ? 'danger' : 'warning') ?>">
                                            <?= ucfirst($log['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="text-truncate" style="max-width: 250px;"
                                             title="<?= htmlspecialchars($log['mensagem']) ?>">
                                            <?= htmlspecialchars($log['mensagem']) ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script>
function toggleApiKey() {
    const input = document.getElementById('api_key');
    const icon  = document.getElementById('eye_icon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'ki-duotone ki-eye-slash fs-2';
    } else {
        input.type = 'password';
        icon.className = 'ki-duotone ki-eye fs-2';
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
