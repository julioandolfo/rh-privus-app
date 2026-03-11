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
        $notif_ativas       = isset($_POST['notificacoes_whatsapp_ativas']) ? 1 : 0;
        $ativo              = isset($_POST['ativo']) ? 1 : 0;
        $intervalo_msgs     = max(3, (int)($_POST['intervalo_entre_mensagens'] ?? 7));
        $max_msgs_hora      = max(0, (int)($_POST['max_mensagens_por_hora'] ?? 80));

        if (empty($api_url) || empty($api_key) || empty($instance)) {
            $error = 'URL da API, API Key e Nome da Instância são obrigatórios!';
        } else {
            try {
                $existing = $pdo->query("SELECT id FROM evolution_config ORDER BY id DESC LIMIT 1")->fetch();

                if ($existing) {
                    $stmt = $pdo->prepare("
                        UPDATE evolution_config
                        SET api_url = ?, api_key = ?, instance_name = ?,
                            notificacoes_whatsapp_ativas = ?, ativo = ?,
                            intervalo_entre_mensagens = ?, max_mensagens_por_hora = ?,
                            updated_by = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$api_url, $api_key, $instance, $notif_ativas, $ativo, $intervalo_msgs, $max_msgs_hora, $usuario['id'], $existing['id']]);
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO evolution_config (api_url, api_key, instance_name, notificacoes_whatsapp_ativas, ativo, intervalo_entre_mensagens, max_mensagens_por_hora, updated_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$api_url, $api_key, $instance, $notif_ativas, $ativo, $intervalo_msgs, $max_msgs_hora, $usuario['id']]);
                }

                // Tenta criar a instância na Evolution API automaticamente (silencioso se já existir)
                $config_temp = [
                    'api_url'       => $api_url,
                    'api_key'       => $api_key,
                    'instance_name' => $instance,
                ];
                evolution_request('POST', 'instance/create', [
                    'instanceName' => $instance,
                    'qrcode'       => true,
                    'integration'  => 'WHATSAPP-BAILEYS',
                ], $config_temp);

                // Redireciona para aba de conexão com instrução clara
                redirect('configuracoes_evolution.php?aba=conexao', '✅ Configurações salvas! Agora escaneie o QR Code abaixo para conectar o WhatsApp.', 'success');

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

// ─── Status da conexão (silencioso — apenas para exibir badge no topo) ────────
$status_conexao = null;
if ($config && $config['ativo']) {
    try {
        $status_conexao = evolution_verificar_conexao($config);
    } catch (Exception $e) {
        $status_conexao = ['connected' => false, 'state' => 'unknown'];
    }
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
                    <a href="?aba=conexao" class="text-decoration-none">
                    <div class="card card-flush h-100 <?= ($config && $config['ativo'] && !($status_conexao['connected'] ?? false)) ? 'border border-warning border-dashed' : '' ?>">
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
                                        <span class="text-success">Conectado ✅</span>
                                    <?php else: ?>
                                        <span class="text-warning">Desconectado ⚠️</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($config && $config['ativo'] && !($status_conexao['connected'] ?? false)): ?>
                                <div class="fs-8 text-warning mt-1">Clique para conectar</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    </a>
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
                    <a class="nav-link <?= $aba_ativa === 'conexao' ? 'active' : '' ?>" href="?aba=conexao">
                        <i class="ki-duotone ki-phone fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                        Conexão / QR Code
                        <?php if ($status_conexao && $status_conexao['connected']): ?>
                        <span class="badge badge-circle badge-success ms-2 w-10px h-10px p-0"></span>
                        <?php elseif ($config && $config['ativo']): ?>
                        <span class="badge badge-circle badge-danger ms-2 w-10px h-10px p-0"></span>
                        <?php endif; ?>
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

                    <!-- Guia de passos -->
                    <div class="row g-4 mb-8">
                        <div class="col-md-4">
                            <div class="d-flex align-items-start gap-3 p-4 rounded bg-light-primary border border-primary border-dashed h-100">
                                <span class="badge badge-circle badge-primary fs-5 flex-shrink-0 mt-1">1</span>
                                <div>
                                    <div class="fw-bold text-primary mb-1">Preencha e salve</div>
                                    <div class="text-muted fs-7">Informe a URL da sua Evolution API, a API Key e escolha um nome para a instância (ex: <code>rh-privus</code>). Clique em <strong>Salvar</strong>.</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex align-items-start gap-3 p-4 rounded bg-light-success border border-success border-dashed h-100">
                                <span class="badge badge-circle badge-success fs-5 flex-shrink-0 mt-1">2</span>
                                <div>
                                    <div class="fw-bold text-success mb-1">Escaneie o QR Code</div>
                                    <div class="text-muted fs-7">Após salvar, você será redirecionado para a aba <strong>Conexão / QR Code</strong>. Escaneie com o WhatsApp do número que enviará as mensagens.</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex align-items-start gap-3 p-4 rounded bg-light-warning border border-warning border-dashed h-100">
                                <span class="badge badge-circle badge-warning fs-5 flex-shrink-0 mt-1">3</span>
                                <div>
                                    <div class="fw-bold text-warning mb-1">Pronto para usar</div>
                                    <div class="text-muted fs-7">Com o WhatsApp conectado, as notificações e a pesquisa de humor passarão a funcionar automaticamente.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info mb-6">
                        <i class="ki-duotone ki-information-5 fs-2 me-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                        A <strong>Evolution API</strong> é um servidor auto-hospedado que conecta ao WhatsApp Web/Business.
                        A instância será criada automaticamente ao salvar — você <strong>não precisa criá-la</strong> manualmente na Evolution API.
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

                        <div class="separator my-5"></div>
                        <h5 class="fw-bold mb-1">⏱️ Rate Limiting — Proteção Anti-Bloqueio</h5>
                        <p class="text-muted fs-7 mb-5">Controla o ritmo de disparos para evitar que o número seja bloqueado pelo WhatsApp. As mensagens são enfileiradas e processadas pelo cron <code>processar_fila_whatsapp.php</code> com os intervalos abaixo.</p>
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Intervalo entre mensagens (segundos)</label>
                                <input type="number" name="intervalo_entre_mensagens" class="form-control"
                                       min="3" max="60" step="1"
                                       value="<?= (int)($config['intervalo_entre_mensagens'] ?? 7) ?>">
                                <div class="form-text">Mínimo recomendado: <strong>5s</strong>. Pausa + jitter aleatório de ±40%.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Limite máximo por hora</label>
                                <input type="number" name="max_mensagens_por_hora" class="form-control"
                                       min="0" max="500" step="5"
                                       value="<?= (int)($config['max_mensagens_por_hora'] ?? 80) ?>">
                                <div class="form-text">0 = sem limite. Recomendado: <strong>80</strong> mensagens/hora.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Situação atual da fila</label>
                                <?php
                                    try {
                                        $fila_pendente = $pdo->query("SELECT COUNT(*) FROM evolution_fila_mensagens WHERE status = 'pendente'")->fetchColumn();
                                        $fila_hora = $pdo->query("SELECT COUNT(*) FROM evolution_fila_mensagens WHERE status = 'enviado' AND enviado_em >= DATE_SUB(NOW(), INTERVAL 1 HOUR)")->fetchColumn();
                                    } catch (Exception $e) { $fila_pendente = '?'; $fila_hora = '?'; }
                                ?>
                                <div class="d-flex flex-wrap gap-2 mt-2">
                                    <span class="badge badge-light-warning fs-7 px-3 py-2" id="badge_pendentes">⏳ <?= $fila_pendente ?> pendentes</span>
                                    <span class="badge badge-light-success fs-7 px-3 py-2" id="badge_hora">✅ <?= $fila_hora ?> enviadas/hora</span>
                                </div>
                                <div class="mt-3">
                                    <button type="button" class="btn btn-sm btn-warning" id="btn_processar_fila" title="Processa até 20 mensagens da fila agora, com o intervalo configurado entre cada envio">
                                        <span class="indicator-label">
                                            <i class="ki-duotone ki-send fs-5 me-1"><span class="path1"></span><span class="path2"></span></i>
                                            Processar Fila Agora
                                        </span>
                                        <span class="indicator-progress">Enviando...
                                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                        </span>
                                    </button>
                                </div>
                                <div class="form-text mt-1" id="resultado_fila"></div>
                            </div>
                        </div>

                        <?php
                        // ─── Status do Cron ─────────────────────────────────────────────
                        $status_file = __DIR__ . '/../storage/cron/fila_whatsapp_status.json';
                        $cron_status = file_exists($status_file)
                            ? (json_decode(file_get_contents($status_file), true) ?? [])
                            : [];
                        $historico   = $cron_status['historico'] ?? [];
                        $ultima      = $historico[0] ?? null;

                        $status_badge = [
                            'ok'         => ['class' => 'badge-light-success', 'label' => '✅ OK'],
                            'parcial'    => ['class' => 'badge-light-warning', 'label' => '⚠️ Parcial'],
                            'erro'       => ['class' => 'badge-light-danger',  'label' => '❌ Erro'],
                            'fila_vazia' => ['class' => 'badge-light-info',    'label' => '📭 Fila vazia'],
                            'desconectado' => ['class' => 'badge-light-danger','label' => '🔴 WA desconectado'],
                            'sem_config' => ['class' => 'badge-light-danger',  'label' => '⚙️ Sem config'],
                            'limite_hora'=> ['class' => 'badge-light-warning', 'label' => '🛑 Limite/hora'],
                        ];
                        ?>

                        <div class="separator my-5"></div>
                        <h5 class="fw-bold mb-1">🕐 Status do Cron <code>processar_fila_whatsapp.php</code></h5>
                        <p class="text-muted fs-7 mb-4">
                            Histórico das últimas execuções do processador de fila.
                            Configure no sistema operacional: <code>* * * * * php <?= realpath(__DIR__ . '/../cron/processar_fila_whatsapp.php') ?></code>
                        </p>

                        <?php if (empty($ultima)): ?>
                        <div class="alert alert-warning d-flex align-items-center">
                            <i class="ki-duotone ki-information-5 fs-2 text-warning me-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                            <div>
                                <strong>Cron nunca executou</strong> ou arquivo de status não encontrado.<br>
                                <small>O arquivo é criado em <code>storage/cron/fila_whatsapp_status.json</code> após a primeira execução.</small>
                            </div>
                        </div>
                        <?php else:
                            $st = $ultima['status'] ?? 'ok';
                            $badge = $status_badge[$st] ?? ['class' => 'badge-light-secondary', 'label' => $st];

                            // Calcula há quantos minutos foi a última execução
                            $diff_min = '?';
                            if (!empty($ultima['iniciado_em'])) {
                                $ts = strtotime($ultima['iniciado_em']);
                                $diff_sec = time() - $ts;
                                if ($diff_sec < 60)       $diff_min = "há {$diff_sec}s";
                                elseif ($diff_sec < 3600) $diff_min = 'há ' . floor($diff_sec/60) . 'min';
                                else                       $diff_min = 'há ' . floor($diff_sec/3600) . 'h';
                            }
                        ?>

                        <!-- Resumo da última execução -->
                        <div class="d-flex flex-wrap gap-3 mb-4">
                            <div class="border rounded p-3 text-center" style="min-width:110px">
                                <div class="fs-7 text-muted mb-1">Última execução</div>
                                <div class="fw-bold fs-7"><?= htmlspecialchars($ultima['iniciado_em'] ?? '—') ?></div>
                                <small class="text-muted"><?= $diff_min ?></small>
                            </div>
                            <div class="border rounded p-3 text-center" style="min-width:100px">
                                <div class="fs-7 text-muted mb-1">Status</div>
                                <span class="badge <?= $badge['class'] ?> px-3 py-2"><?= $badge['label'] ?></span>
                            </div>
                            <div class="border rounded p-3 text-center" style="min-width:90px">
                                <div class="fs-7 text-muted mb-1">Enviados</div>
                                <div class="fw-bold fs-4 text-success"><?= (int)($ultima['enviados'] ?? 0) ?></div>
                            </div>
                            <div class="border rounded p-3 text-center" style="min-width:90px">
                                <div class="fs-7 text-muted mb-1">Erros</div>
                                <div class="fw-bold fs-4 <?= ($ultima['erros'] ?? 0) > 0 ? 'text-danger' : 'text-muted' ?>"><?= (int)($ultima['erros'] ?? 0) ?></div>
                            </div>
                            <div class="border rounded p-3 text-center" style="min-width:90px">
                                <div class="fs-7 text-muted mb-1">Na fila</div>
                                <div class="fw-bold fs-4 <?= ($ultima['pendentes'] ?? 0) > 0 ? 'text-warning' : 'text-muted' ?>"><?= (int)($ultima['pendentes'] ?? 0) ?></div>
                            </div>
                            <div class="border rounded p-3 text-center" style="min-width:90px">
                                <div class="fs-7 text-muted mb-1">Duração</div>
                                <div class="fw-bold fs-7"><?= htmlspecialchars($ultima['duracao_s'] ?? '—') ?>s</div>
                            </div>
                            <div class="border rounded p-3 text-center" style="min-width:90px">
                                <div class="fs-7 text-muted mb-1">Total exec.</div>
                                <div class="fw-bold fs-7"><?= (int)($cron_status['total_execucoes'] ?? 0) ?></div>
                            </div>
                        </div>

                        <!-- Histórico das últimas execuções -->
                        <?php if (count($historico) > 1): ?>
                        <div class="mb-3">
                            <button class="btn btn-sm btn-light" type="button" data-bs-toggle="collapse" data-bs-target="#historico_cron">
                                📋 Ver histórico (<?= count($historico) ?> execuções)
                            </button>
                            <div class="collapse mt-3" id="historico_cron">
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered table-striped fs-7">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Data/Hora</th>
                                                <th>Status</th>
                                                <th class="text-center">Enviados</th>
                                                <th class="text-center">Erros</th>
                                                <th class="text-center">Pendentes</th>
                                                <th>Duração</th>
                                                <th>Detalhe</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($historico as $exec): ?>
                                            <?php
                                                $st_h  = $exec['status'] ?? 'ok';
                                                $bg_h  = $status_badge[$st_h] ?? ['class' => 'badge-light-secondary', 'label' => $st_h];
                                            ?>
                                            <tr>
                                                <td class="text-nowrap"><?= htmlspecialchars($exec['iniciado_em'] ?? '—') ?></td>
                                                <td><span class="badge <?= $bg_h['class'] ?>"><?= $bg_h['label'] ?></span></td>
                                                <td class="text-center text-success fw-bold"><?= (int)($exec['enviados'] ?? 0) ?></td>
                                                <td class="text-center <?= ($exec['erros'] ?? 0) > 0 ? 'text-danger fw-bold' : 'text-muted' ?>"><?= (int)($exec['erros'] ?? 0) ?></td>
                                                <td class="text-center <?= ($exec['pendentes'] ?? 0) > 0 ? 'text-warning' : '' ?>"><?= (int)($exec['pendentes'] ?? 0) ?></td>
                                                <td><?= htmlspecialchars($exec['duracao_s'] ?? '—') ?>s</td>
                                                <td class="text-muted"><?= htmlspecialchars($exec['detalhe'] ?? '') ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="alert alert-light-info fs-7 mb-0">
                            <strong>Nota:</strong> Notificações individuais (ocorrências, documentos, aprovações) são enviadas <strong>diretamente</strong>, sem passar pela fila.
                            A fila é usada para <strong>comunicados em massa</strong> e <strong>pesquisa de humor</strong>.
                        </div>

                        <?php endif; ?>

                        <div class="d-flex justify-content-between align-items-center">
                            <a href="?aba=conexao" class="btn btn-light-success">
                                <i class="ki-duotone ki-scan-barcode fs-2 me-1"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></i>
                                Ir para QR Code
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="ki-duotone ki-check fs-2 me-1"><span class="path1"></span><span class="path2"></span></i>
                                Salvar e Conectar →
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

            <!-- ═══ ABA: Conexão / QR Code ════════════════════════════════════════════ -->
            <?php elseif ($aba_ativa === 'conexao'): ?>
            <?php if (!$config): ?>
            <div class="alert alert-warning">
                <i class="ki-duotone ki-information-5 fs-2 me-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                Salve as configurações da API antes de conectar ao WhatsApp.
            </div>
            <?php else: ?>
            <div class="row g-5">

                <!-- Painel de status e QR Code -->
                <div class="col-md-7">
                    <div class="card h-100">
                        <div class="card-header">
                            <h3 class="card-title">Conexão WhatsApp</h3>
                            <div class="card-toolbar">
                                <span id="status_badge" class="badge badge-light-secondary fs-7">
                                    <span class="spinner-border spinner-border-sm me-1" style="width:10px;height:10px;"></span>
                                    Verificando...
                                </span>
                            </div>
                        </div>
                        <div class="card-body text-center">

                            <!-- Estado: desconectado → exibe QR Code -->
                            <div id="area_qrcode" style="display:none;">
                                <div class="alert alert-info text-start mb-5">
                                    <i class="ki-duotone ki-information-5 fs-2 me-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                    <strong>Como conectar:</strong>
                                    Abra o WhatsApp no seu celular → <strong>Aparelhos conectados</strong> → <strong>Conectar aparelho</strong> → escaneie o QR Code abaixo.
                                </div>

                                <div class="d-flex justify-content-center mb-4">
                                    <div class="border border-2 border-success rounded p-3 bg-white shadow-sm position-relative" style="display:inline-block;">
                                        <img id="qrcode_img" src="" alt="QR Code WhatsApp"
                                             style="width:260px;height:260px;display:block;" />
                                        <!-- Overlay de expiração -->
                                        <div id="qr_expired_overlay"
                                             style="display:none;position:absolute;inset:0;background:rgba(0,0,0,0.7);border-radius:8px;align-items:center;justify-content:center;flex-direction:column;gap:8px;">
                                            <span style="color:#fff;font-size:14px;font-weight:600;">QR Code expirado</span>
                                            <button onclick="carregarQRCode()" class="btn btn-sm btn-success">
                                                <i class="ki-duotone ki-arrows-circle fs-4"><span class="path1"></span><span class="path2"></span></i>
                                                Renovar
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div id="qr_timer" class="text-muted fs-7 mb-4">
                                    QR Code expira em <strong id="qr_countdown">60</strong>s
                                </div>

                                <button onclick="carregarQRCode()" class="btn btn-light-primary btn-sm">
                                    <i class="ki-duotone ki-arrows-circle fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                                    Gerar novo QR Code
                                </button>
                            </div>

                            <!-- Estado: conectado -->
                            <div id="area_conectado" style="display:none;">
                                <div class="mb-5">
                                    <span style="font-size:80px;">✅</span>
                                </div>
                                <h4 class="text-success fw-bold mb-2">WhatsApp Conectado!</h4>
                                <p class="text-muted mb-5">A instância <strong><?= htmlspecialchars($config['instance_name']) ?></strong> está ativa e pronta para enviar mensagens.</p>
                                <div class="d-flex justify-content-center gap-3">
                                    <button onclick="reiniciarInstancia()" class="btn btn-light-warning btn-sm">
                                        <i class="ki-duotone ki-arrows-circle fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                                        Reiniciar
                                    </button>
                                    <button onclick="desconectarInstancia()" class="btn btn-light-danger btn-sm">
                                        <i class="ki-duotone ki-cross-circle fs-4 me-1"><span class="path1"></span><span class="path2"></span></i>
                                        Desconectar
                                    </button>
                                </div>
                            </div>

                            <!-- Estado: carregando / aguardando -->
                            <div id="area_aguardando">
                                <div class="spinner-border text-success mb-4" style="width:3rem;height:3rem;" role="status"></div>
                                <p class="text-muted" id="aguardando_msg">Verificando status da conexão...</p>
                            </div>

                            <!-- Estado: erro -->
                            <div id="area_erro" style="display:none;">
                                <div class="mb-3" style="font-size:50px;">⚠️</div>
                                <p class="text-danger fw-semibold mb-4" id="erro_msg">Erro ao conectar.</p>
                                <div class="d-flex gap-2 flex-wrap justify-content-center mb-4">
                                    <button onclick="verificarStatus()" class="btn btn-light-primary btn-sm">
                                        🔄 Verificar novamente
                                    </button>
                                    <button onclick="carregarQRCode()" class="btn btn-success btn-sm">
                                        📱 Gerar QR Code
                                    </button>
                                    <button onclick="rodarDiagnostico()" class="btn btn-light-warning btn-sm">
                                        🔍 Diagnóstico
                                    </button>
                                </div>
                                <!-- Resultado do diagnóstico -->
                                <div id="area_diagnostico" style="display:none; width:100%; text-align:left;">
                                    <div class="separator my-3"></div>
                                    <h6 class="fw-bold mb-3 text-center">🔍 Resultado do Diagnóstico</h6>
                                    <div id="diag_loading" class="text-center text-muted fs-7">
                                        <span class="spinner-border spinner-border-sm me-2"></span>Rodando testes...
                                    </div>
                                    <div id="diag_resultado"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Informações da instância -->
                <div class="col-md-5">
                    <div class="card mb-5">
                        <div class="card-header">
                            <h3 class="card-title">Detalhes da Instância</h3>
                        </div>
                        <div class="card-body">
                            <table class="table table-borderless fs-7 mb-0">
                                <tr>
                                    <td class="text-muted fw-semibold w-50">URL da API</td>
                                    <td class="text-break"><code><?= htmlspecialchars($config['api_url']) ?></code></td>
                                </tr>
                                <tr>
                                    <td class="text-muted fw-semibold">Instância</td>
                                    <td><span class="badge badge-light-primary"><?= htmlspecialchars($config['instance_name']) ?></span></td>
                                </tr>
                                <tr>
                                    <td class="text-muted fw-semibold">Estado</td>
                                    <td><span id="estado_detalhe" class="badge badge-light-secondary">—</span></td>
                                </tr>
                                <tr>
                                    <td class="text-muted fw-semibold">Notificações WA</td>
                                    <td>
                                        <?php if ($config['notificacoes_whatsapp_ativas']): ?>
                                        <span class="badge badge-light-success">Ativas</span>
                                        <?php else: ?>
                                        <span class="badge badge-light-danger">Inativas</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted fw-semibold">Pesquisa humor</td>
                                    <td>
                                        <?php if ($config['pesquisa_humor_ativa']): ?>
                                        <span class="badge badge-light-success">Ativa às <?= substr($config['horario_pesquisa_humor'] ?? '09:00', 0, 5) ?></span>
                                        <?php else: ?>
                                        <span class="badge badge-light-secondary">Inativa</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Ações</h3>
                        </div>
                        <div class="card-body d-flex flex-column gap-3">
                            <button onclick="verificarStatus()" class="btn btn-light-primary w-100">
                                <i class="ki-duotone ki-wifi fs-4 me-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                Verificar Status
                            </button>
                            <button onclick="carregarQRCode()" class="btn btn-success w-100" id="btn_gerar_qr">
                                <i class="ki-duotone ki-scan-barcode fs-4 me-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></i>
                                Conectar via QR Code
                            </button>
                            <button onclick="reiniciarInstancia()" class="btn btn-light-warning w-100">
                                <i class="ki-duotone ki-arrows-circle fs-4 me-2"><span class="path1"></span><span class="path2"></span></i>
                                Reiniciar Instância
                            </button>
                            <button onclick="desconectarInstancia()" class="btn btn-light-danger w-100">
                                <i class="ki-duotone ki-cross-circle fs-4 me-2"><span class="path1"></span><span class="path2"></span></i>
                                Desconectar WhatsApp
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <script>
            const API_BASE = '../api/evolution/qrcode.php';
            let qrInterval   = null;
            let qrCountdown  = 60;
            let countTimer   = null;
            let statusPoller = null;

            // ── Helpers de exibição ──────────────────────────────────────────────────
            function mostrarArea(area) {
                ['area_qrcode','area_conectado','area_aguardando','area_erro'].forEach(id => {
                    document.getElementById(id).style.display = 'none';
                });
                document.getElementById(area).style.display = area === 'area_aguardando' ? 'block' : 'flex';
                document.getElementById(area).style.flexDirection = 'column';
                document.getElementById(area).style.alignItems = 'center';
            }

            function setStatusBadge(estado) {
                const badge = document.getElementById('status_badge');
                const detalhe = document.getElementById('estado_detalhe');
                const map = {
                    'open'        : ['badge-light-success', '🟢 Conectado'],
                    'connecting'  : ['badge-light-warning', '🟡 Conectando...'],
                    'close'       : ['badge-light-danger',  '🔴 Desconectado'],
                    'qrcode'      : ['badge-light-warning', '🟡 Aguardando QR'],
                    'unknown'     : ['badge-light-secondary','⚪ Desconhecido'],
                };
                const [cls, label] = map[estado] ?? map['unknown'];
                badge.className   = 'badge fs-7 ' + cls;
                badge.innerHTML   = label;
                if (detalhe) {
                    detalhe.className = 'badge ' + cls;
                    detalhe.textContent = label;
                }
            }

            // ── Verifica status ──────────────────────────────────────────────────────
            function verificarStatus() {
                mostrarArea('area_aguardando');
                document.getElementById('aguardando_msg').textContent = 'Verificando status...';
                // Oculta diagnóstico anterior
                const areaDiag = document.getElementById('area_diagnostico');
                if (areaDiag) areaDiag.style.display = 'none';

                fetch(API_BASE + '?action=status')
                    .then(r => r.json())
                    .then(data => {
                        if (data.connected) {
                            setStatusBadge('open');
                            mostrarArea('area_conectado');
                            pararPolling();
                            return;
                        }

                        const estado = data.state ?? 'unknown';
                        setStatusBadge(in_array(estado, ['connecting','qrcode']) ? 'connecting' : 'close');
                        mostrarArea('area_erro');

                        // Monta mensagem de erro com detalhes úteis
                        let msg = '';
                        if (data.error) {
                            msg = data.error;
                        } else if (estado === 'unknown') {
                            msg = 'A Evolution API respondeu mas o estado da instância não foi reconhecido.';
                            if (data.raw) {
                                msg += ' Resposta bruta: ' + data.raw.substring(0, 200);
                            }
                        } else {
                            msg = 'WhatsApp desconectado (estado: ' + estado + '). Clique em "Gerar QR Code" para conectar.';
                        }

                        document.getElementById('erro_msg').textContent = msg;

                        // Se estado unknown, sugere diagnóstico automaticamente
                        if (estado === 'unknown') {
                            setTimeout(() => {
                                const btn = document.querySelector('button[onclick="rodarDiagnostico()"]');
                                if (btn) btn.classList.add('btn-warning');
                            }, 500);
                        }
                    })
                    .catch(err => {
                        setStatusBadge('unknown');
                        mostrarArea('area_erro');
                        document.getElementById('erro_msg').textContent =
                            'Não foi possível comunicar com a Evolution API. Verifique se o servidor está online. Detalhe: ' + err.message;
                    });
            }

            function in_array(val, arr) { return arr.includes(val); }

            // ── Carrega QR Code ──────────────────────────────────────────────────────
            function carregarQRCode() {
                pararPolling();
                mostrarArea('area_aguardando');
                document.getElementById('aguardando_msg').textContent = 'Gerando QR Code...';
                setStatusBadge('qrcode');

                fetch(API_BASE + '?action=qrcode')
                    .then(r => r.json())
                    .then(data => {
                        if (data.connected) {
                            setStatusBadge('open');
                            mostrarArea('area_conectado');
                            return;
                        }

                        if (!data.success || !data.base64) {
                            mostrarArea('area_erro');
                            document.getElementById('erro_msg').textContent = data.error ?? 'Não foi possível gerar o QR Code.';
                            return;
                        }

                        // Exibe QR Code
                        document.getElementById('qrcode_img').src = data.base64;
                        document.getElementById('qr_expired_overlay').style.display = 'none';
                        mostrarArea('area_qrcode');
                        setStatusBadge('qrcode');

                        // Inicia countdown de 60s
                        iniciarCountdown(60);

                        // Polling: verifica a cada 4s se conectou
                        iniciarPollingConexao();
                    })
                    .catch(() => {
                        mostrarArea('area_erro');
                        document.getElementById('erro_msg').textContent = 'Erro de comunicação ao buscar QR Code.';
                    });
            }

            // ── Countdown de expiração do QR ─────────────────────────────────────────
            function iniciarCountdown(segundos) {
                clearInterval(countTimer);
                qrCountdown = segundos;
                document.getElementById('qr_countdown').textContent = qrCountdown;
                document.getElementById('qr_timer').style.display = 'block';

                countTimer = setInterval(() => {
                    qrCountdown--;
                    document.getElementById('qr_countdown').textContent = qrCountdown;
                    if (qrCountdown <= 0) {
                        clearInterval(countTimer);
                        clearInterval(qrInterval);
                        // Mostra overlay de expirado
                        const overlay = document.getElementById('qr_expired_overlay');
                        overlay.style.display = 'flex';
                        document.getElementById('qr_timer').style.display = 'none';
                        document.getElementById('qr_countdown').textContent = '60';
                    }
                }, 1000);
            }

            // ── Polling de conexão após exibir QR ────────────────────────────────────
            function iniciarPollingConexao() {
                clearInterval(qrInterval);
                qrInterval = setInterval(() => {
                    fetch(API_BASE + '?action=status')
                        .then(r => r.json())
                        .then(data => {
                            if (data.connected) {
                                pararPolling();
                                setStatusBadge('open');
                                mostrarArea('area_conectado');

                                // Toast de sucesso
                                if (typeof Swal !== 'undefined') {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'WhatsApp Conectado! 🎉',
                                        text: 'A instância foi conectada com sucesso.',
                                        timer: 3000,
                                        showConfirmButton: false,
                                    });
                                }
                            }
                        })
                        .catch(() => {});
                }, 4000);
            }

            function pararPolling() {
                clearInterval(qrInterval);
                clearInterval(countTimer);
            }

            // ── Desconectar ──────────────────────────────────────────────────────────
            function desconectarInstancia() {
                if (!confirm('Tem certeza que deseja desconectar o WhatsApp? Será necessário escanear o QR Code novamente para reconectar.')) return;

                mostrarArea('area_aguardando');
                document.getElementById('aguardando_msg').textContent = 'Desconectando...';

                fetch(API_BASE + '?action=logout')
                    .then(r => r.json())
                    .then(data => {
                        setStatusBadge('close');
                        mostrarArea('area_erro');
                        document.getElementById('erro_msg').textContent = data.message ?? 'Desconectado.';
                    })
                    .catch(() => {
                        mostrarArea('area_erro');
                        document.getElementById('erro_msg').textContent = 'Erro ao desconectar.';
                    });
            }

            // ── Reiniciar ────────────────────────────────────────────────────────────
            function reiniciarInstancia() {
                mostrarArea('area_aguardando');
                document.getElementById('aguardando_msg').textContent = 'Reiniciando instância...';

                fetch(API_BASE + '?action=restart')
                    .then(r => r.json())
                    .then(() => setTimeout(verificarStatus, 3000))
                    .catch(() => setTimeout(verificarStatus, 3000));
            }

                            // ── Diagnóstico ──────────────────────────────────────────────────────────
            function rodarDiagnostico() {
                const areaDiag  = document.getElementById('area_diagnostico');
                const diagLoad  = document.getElementById('diag_loading');
                const diagRes   = document.getElementById('diag_resultado');

                areaDiag.style.display = 'block';
                diagLoad.style.display = 'block';
                diagRes.innerHTML = '';

                fetch(API_BASE + '?action=diagnostico')
                    .then(r => r.json())
                    .then(data => {
                        diagLoad.style.display = 'none';

                        if (!data.success) {
                            diagRes.innerHTML = `<div class="alert alert-danger">${data.error ?? 'Erro desconhecido'}</div>`;
                            return;
                        }

                        const d = data.diagnostico;
                        let html = '';

                        // Mapa de ícone/cor por resultado
                        function badge(ok) {
                            return ok
                                ? '<span class="badge badge-light-success">✅ OK</span>'
                                : '<span class="badge badge-light-danger">❌ Falha</span>';
                        }

                        function httpBadge(code) {
                            const cls = code >= 200 && code < 300 ? 'success' : (code === 0 ? 'danger' : 'warning');
                            return `<span class="badge badge-light-${cls}">HTTP ${code || 'sem resposta'}</span>`;
                        }

                        // Config
                        const cfg = d['05_config_salva'] ?? {};
                        html += `
                        <div class="card mb-3">
                            <div class="card-header py-3"><h6 class="mb-0">⚙️ Configuração Salva</h6></div>
                            <div class="card-body p-3 fs-7">
                                <div><strong>URL:</strong> <code>${cfg.api_url ?? '—'}</code></div>
                                <div><strong>Instância:</strong> <code>${cfg.instance_name ?? '—'}</code></div>
                                <div><strong>API Key:</strong> <code>${cfg.api_key_len ?? '—'}</code></div>
                            </div>
                        </div>`;

                        // Cada teste
                        const testes = [
                            { key: '01_api_raiz',          label: '1. Acesso à Evolution API (raiz)' },
                            { key: '02_listar_instancias', label: '2. Listar instâncias' },
                            { key: '03_connection_state',  label: '3. Estado da instância' },
                            { key: '04_qrcode',            label: '4. Buscar QR Code' },
                        ];

                        testes.forEach(t => {
                            const r = d[t.key] ?? {};
                            const rawStr = r.raw ? `<details class="mt-2"><summary class="text-muted fs-8 cursor-pointer">Ver resposta bruta</summary><pre class="bg-light p-2 rounded fs-8 mt-1" style="max-height:120px;overflow:auto;word-break:break-all">${escapeHtml(r.raw)}</pre></details>` : '';
                            const dataStr = r.data ? `<details class="mt-1"><summary class="text-muted fs-8 cursor-pointer">Ver data</summary><pre class="bg-light p-2 rounded fs-8 mt-1" style="max-height:120px;overflow:auto">${escapeHtml(JSON.stringify(r.data, null, 2))}</pre></details>` : '';
                            const erroStr = r.error ? `<div class="text-danger fs-8 mt-1">Erro: ${escapeHtml(r.error)}</div>` : '';

                            html += `
                            <div class="card mb-2">
                                <div class="card-body p-3 fs-7">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <strong>${t.label}</strong>
                                        <div class="d-flex gap-2">${httpBadge(r.http_code)} ${badge(r.success)}</div>
                                    </div>
                                    <div class="text-muted">URL: <code>${r.url ?? '—'}</code></div>
                                    ${erroStr}${rawStr}${dataStr}
                                </div>
                            </div>`;
                        });

                        // Diagnóstico automático de causas comuns
                        const raiz  = d['01_api_raiz']  ?? {};
                        const lista = d['02_listar_instancias'] ?? {};
                        const state = d['03_connection_state']  ?? {};

                        html += '<div class="card mb-2 border-warning"><div class="card-header py-2 bg-light-warning"><h6 class="mb-0">💡 Possíveis Causas</h6></div><div class="card-body p-3 fs-7"><ul class="mb-0 ps-3">';

                        if (!raiz.success && raiz.http_code === 0) {
                            html += '<li class="text-danger">❌ <strong>Servidor inacessível</strong> — Verifique se a Evolution API está online e se a URL está correta (incluindo porta, se necessário). Ex: <code>https://api.seudominio.com.br</code></li>';
                        } else if (!raiz.success && raiz.http_code === 401) {
                            html += '<li class="text-danger">❌ <strong>API Key inválida</strong> — A chave de autenticação foi recusada. Verifique a variável <code>AUTHENTICATION_API_KEY</code> no servidor da Evolution API.</li>';
                        } else if (raiz.success && !lista.success) {
                            html += '<li class="text-warning">⚠️ A API responde mas não lista instâncias — pode ser problema de permissão ou versão diferente da Evolution API.</li>';
                        } else if (lista.success && !state.success) {
                            html += `<li class="text-warning">⚠️ A instância "<strong><?= htmlspecialchars($config['instance_name'] ?? '') ?></strong>" não foi encontrada ou não existe ainda. Clique em "Gerar QR Code" para criá-la automaticamente.</li>`;
                        } else if (state.success) {
                            const estadoRaw = JSON.stringify(state.data ?? {});
                            html += `<li class="text-info">ℹ️ A instância existe. Estado retornado: <code>${escapeHtml(estadoRaw)}</code>. O sistema pode estar interpretando um campo diferente do esperado.</li>`;
                        }

                        html += '</ul></div></div>';
                        diagRes.innerHTML = html;
                    })
                    .catch(e => {
                        diagLoad.style.display = 'none';
                        diagRes.innerHTML = `<div class="alert alert-danger">Erro ao executar diagnóstico: ${e.message}</div>`;
                    });
            }

            function escapeHtml(str) {
                if (!str) return '';
                return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
            }

            // ── Auto-inicialização ───────────────────────────────────────────────────
                            // Se vier de um redirect após salvar, vai direto para o QR Code
                            document.addEventListener('DOMContentLoaded', function() {
                                const params = new URLSearchParams(window.location.search);
                                if (params.get('aba') === 'conexao') {
                                    verificarStatus();
                                } else {
                                    verificarStatus();
                                }
                            });
            </script>
            <?php endif; ?>

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

// ─── Processar Fila Agora ────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('btn_processar_fila');
    if (!btn) return;

    btn.addEventListener('click', function () {
        btn.setAttribute('data-kt-indicator', 'on');
        btn.disabled = true;

        const resultado = document.getElementById('resultado_fila');
        resultado.innerHTML = '';

        fetch('../api/evolution/processar_fila.php', {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            btn.removeAttribute('data-kt-indicator');
            btn.disabled = false;

            if (data.success) {
                resultado.innerHTML = '<span class="text-success fw-semibold">'
                    + '✅ ' + data.message
                    + (data.ainda_pendente > 0 ? ' | <strong>' + data.ainda_pendente + '</strong> ainda na fila.' : '')
                    + '</span>';

                // Atualiza badges
                if (document.getElementById('badge_pendentes')) {
                    document.getElementById('badge_pendentes').textContent = '⏳ ' + (data.ainda_pendente ?? '?') + ' pendentes';
                }
            } else {
                resultado.innerHTML = '<span class="text-danger fw-semibold">⚠️ ' + (data.error || 'Erro desconhecido') + '</span>';
            }
        })
        .catch(err => {
            btn.removeAttribute('data-kt-indicator');
            btn.disabled = false;
            resultado.innerHTML = '<span class="text-danger fw-semibold">⚠️ Falha na requisição: ' + err + '</span>';
        });
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
