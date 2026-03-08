<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/slack_service.php';

require_login();
require_page_permission('configuracoes_slack.php');

$pdo     = getDB();
$usuario = $_SESSION['usuario'];
$error   = null;
$success = null;
$aba_ativa = $_GET['aba'] ?? 'configuracoes';

// ─── POST ─────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Salvar configurações ─────────────────────────────────────────────────
    if ($action === 'salvar_config') {
        $bot_token           = trim($_POST['bot_token'] ?? '');
        $canal_comunicados   = trim($_POST['canal_comunicados'] ?? '');
        $notif_ativas        = isset($_POST['notificacoes_slack_ativas']) ? 1 : 0;
        $comunicados_canal   = isset($_POST['comunicados_no_canal']) ? 1 : 0;
        $ativo               = isset($_POST['ativo']) ? 1 : 0;
        $intervalo           = max(1, (int)($_POST['intervalo_entre_mensagens'] ?? 2));
        $max_hora            = max(0, (int)($_POST['max_mensagens_por_hora'] ?? 300));

        if (empty($bot_token)) {
            $error = 'O Bot Token é obrigatório.';
        } else {
            try {
                $existing = $pdo->query("SELECT id FROM slack_config ORDER BY id DESC LIMIT 1")->fetch();

                if ($existing) {
                    $pdo->prepare("
                        UPDATE slack_config
                        SET bot_token = ?, canal_comunicados = ?, notificacoes_slack_ativas = ?,
                            comunicados_no_canal = ?, ativo = ?,
                            intervalo_entre_mensagens = ?, max_mensagens_por_hora = ?,
                            updated_by = ?, updated_at = NOW()
                        WHERE id = ?
                    ")->execute([$bot_token, $canal_comunicados, $notif_ativas, $comunicados_canal,
                                 $ativo, $intervalo, $max_hora, $usuario['id'], $existing['id']]);
                } else {
                    $pdo->prepare("
                        INSERT INTO slack_config
                            (bot_token, canal_comunicados, notificacoes_slack_ativas, comunicados_no_canal,
                             ativo, intervalo_entre_mensagens, max_mensagens_por_hora, updated_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ")->execute([$bot_token, $canal_comunicados, $notif_ativas, $comunicados_canal,
                                 $ativo, $intervalo, $max_hora, $usuario['id']]);
                }

                // Testa conexão e salva workspace/bot_user_id
                $config_temp = slack_get_config();
                if ($config_temp) slack_verificar_conexao($config_temp);

                redirect('configuracoes_slack.php?aba=conexao', '✅ Configurações salvas com sucesso!', 'success');

            } catch (PDOException $e) {
                $error = 'Erro ao salvar: ' . $e->getMessage();
            }
        }

    // ── Sincronizar usuários ─────────────────────────────────────────────────
    } elseif ($action === 'sincronizar_usuarios') {
        $config = slack_get_config();
        if (!$config) {
            $error = 'Configure o Slack antes de sincronizar.';
        } else {
            $resultado = slack_sincronizar_todos($config);
            $success   = "Sincronização concluída: {$resultado['sincronizados']} sincronizados, "
                       . "{$resultado['nao_encontrados']} não encontrados no Slack, "
                       . "{$resultado['erros']} erros.";
            $aba_ativa = 'sincronizacao';
        }

    // ── Sincronizar colaborador individual ────────────────────────────────────
    } elseif ($action === 'sincronizar_individual') {
        $colab_id = (int)($_POST['colaborador_id'] ?? 0);
        $email    = trim($_POST['email'] ?? '');
        if ($colab_id && $email) {
            $uid = slack_sincronizar_usuario_por_email($colab_id, $email);
            if ($uid) $success = "Slack User ID sincronizado: {$uid}";
            else $error = "Usuário com e-mail {$email} não encontrado no workspace Slack.";
        } else {
            $error = 'Colaborador e e-mail são obrigatórios.';
        }
        $aba_ativa = 'sincronizacao';

    // ── Enviar mensagem de teste ──────────────────────────────────────────────
    } elseif ($action === 'testar_mensagem') {
        $canal_teste   = trim($_POST['canal_teste'] ?? '');
        $titulo_teste  = trim($_POST['titulo_teste'] ?? 'Mensagem de Teste');
        $mensagem_teste = trim($_POST['mensagem_teste'] ?? 'Isso é uma mensagem de teste enviada pelo RH Privus.');
        $url_teste     = trim($_POST['url_teste'] ?? '');

        if (empty($canal_teste)) {
            $error = 'Informe o canal ou User ID de destino.';
        } else {
            $config = slack_get_config();
            if (!$config) {
                $error = 'Slack não configurado.';
            } else {
                $result = slack_enviar_mensagem($canal_teste, $titulo_teste, $mensagem_teste, $url_teste, null, 'manual', $config);
                if ($result['ok']) $success = "Mensagem enviada com sucesso! (ts: " . ($result['ts'] ?? '-') . ")";
                else $error = "Erro: " . ($result['error'] ?? 'desconhecido');
            }
        }
        $aba_ativa = 'teste';

    // ── Salvar slack_user_id manual ────────────────────────────────────────────
    } elseif ($action === 'salvar_slack_id_manual') {
        $colab_id      = (int)($_POST['colaborador_id'] ?? 0);
        $slack_user_id = trim($_POST['slack_user_id'] ?? '');
        if ($colab_id && $slack_user_id) {
            $pdo->prepare("UPDATE colaboradores SET slack_user_id = ? WHERE id = ?")
                ->execute([$slack_user_id, $colab_id]);
            $success = 'Slack User ID salvo manualmente.';
        } else {
            $error = 'Dados inválidos.';
        }
        $aba_ativa = 'sincronizacao';
    }
}

// ─── Dados da página ──────────────────────────────────────────────────────────
$config = slack_get_config();

// Status de conexão
$conexao_info = null;
if ($config) {
    try { $conexao_info = slack_verificar_conexao($config); } catch (Exception $e) {}
}

// Canais disponíveis
$canais_lista = [];
if ($config) {
    try { $canais_lista = slack_listar_canais($config); } catch (Exception $e) {}
}

// Fila
try {
    $fila_pendente     = $pdo->query("SELECT COUNT(*) FROM slack_fila_mensagens WHERE status = 'pendente'")->fetchColumn();
    $fila_enviada_hora = $pdo->query("SELECT COUNT(*) FROM slack_fila_mensagens WHERE status = 'enviado' AND enviado_em >= DATE_SUB(NOW(), INTERVAL 1 HOUR)")->fetchColumn();
    $fila_erros        = $pdo->query("SELECT COUNT(*) FROM slack_fila_mensagens WHERE status = 'erro'")->fetchColumn();
    $logs_recentes     = $pdo->query("SELECT l.*, c.nome_completo FROM slack_mensagens_log l LEFT JOIN colaboradores c ON c.id = l.colaborador_id ORDER BY l.enviado_em DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $fila_pendente = $fila_enviada_hora = $fila_erros = 0;
    $logs_recentes = [];
}

// Colaboradores com/sem Slack
try {
    $colabs_sem_slack = $pdo->query("
        SELECT id, nome_completo, email_pessoal, slack_user_id
        FROM colaboradores
        WHERE status = 'ativo' AND slack_ativo = 1
          AND (slack_user_id IS NULL OR slack_user_id = '')
        ORDER BY nome_completo
    ")->fetchAll(PDO::FETCH_ASSOC);

    $colabs_com_slack = $pdo->query("
        SELECT id, nome_completo, email_pessoal, slack_user_id
        FROM colaboradores
        WHERE status = 'ativo' AND slack_ativo = 1
          AND slack_user_id IS NOT NULL AND slack_user_id != ''
        ORDER BY nome_completo
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $colabs_sem_slack = $colabs_com_slack = [];
}

$page_title = 'Configurações Slack';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/menu.php';
?>

<div class="app-main flex-column flex-row-fluid">
    <div class="d-flex flex-column flex-column-fluid">
        <div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
            <div class="app-container container-fluid d-flex flex-stack">
                <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
                    <h1 class="page-heading d-flex text-dark fw-bold fs-3 flex-column justify-content-center my-0">
                        <i class="ki-duotone ki-message-notif fs-1 me-2 text-primary"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span></i>
                        Configurações Slack
                    </h1>
                </div>
            </div>
        </div>

        <div id="kt_app_content" class="app-content flex-column-fluid">
            <div class="app-container container-fluid">

                <?php if ($error): ?>
                <div class="alert alert-danger d-flex align-items-center mb-6">
                    <i class="ki-duotone ki-information-5 fs-2hx text-danger me-4"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                    <div><?= htmlspecialchars($error) ?></div>
                </div>
                <?php endif; ?>
                <?php if ($success): ?>
                <div class="alert alert-success d-flex align-items-center mb-6">
                    <i class="ki-duotone ki-check-circle fs-2hx text-success me-4"><span class="path1"></span><span class="path2"></span></i>
                    <div><?= htmlspecialchars($success) ?></div>
                </div>
                <?php endif; ?>

                <!-- Status Cards -->
                <div class="row g-5 mb-6">
                    <div class="col-md-3">
                        <div class="card card-flush h-100">
                            <div class="card-body d-flex align-items-center gap-4 py-5">
                                <div class="symbol symbol-50px symbol-circle <?= ($conexao_info && $conexao_info['ok']) ? 'bg-light-success' : 'bg-light-danger' ?>">
                                    <span class="symbol-label fs-2">🟢</span>
                                </div>
                                <div>
                                    <div class="fs-7 text-muted">Workspace</div>
                                    <div class="fw-bold fs-6">
                                        <?php if ($conexao_info && $conexao_info['ok']): ?>
                                            <?= htmlspecialchars($conexao_info['team'] ?? 'Conectado') ?>
                                        <?php else: ?>
                                            <span class="text-danger">Desconectado</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="fs-8 text-muted">
                                        <?= $conexao_info['ok'] ? ('Bot: ' . htmlspecialchars($conexao_info['user'] ?? '')) : ($config ? 'Verifique o token' : 'Não configurado') ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-flush h-100">
                            <div class="card-body d-flex align-items-center gap-4 py-5">
                                <div class="symbol symbol-50px symbol-circle bg-light-warning">
                                    <span class="symbol-label fs-2">⏳</span>
                                </div>
                                <div>
                                    <div class="fs-7 text-muted">Fila pendente</div>
                                    <div class="fw-bold fs-3"><?= $fila_pendente ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-flush h-100">
                            <div class="card-body d-flex align-items-center gap-4 py-5">
                                <div class="symbol symbol-50px symbol-circle bg-light-success">
                                    <span class="symbol-label fs-2">✅</span>
                                </div>
                                <div>
                                    <div class="fs-7 text-muted">Enviadas (1h)</div>
                                    <div class="fw-bold fs-3"><?= $fila_enviada_hora ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-flush h-100">
                            <div class="card-body d-flex align-items-center gap-4 py-5">
                                <div class="symbol symbol-50px symbol-circle bg-light-primary">
                                    <span class="symbol-label fs-2">👥</span>
                                </div>
                                <div>
                                    <div class="fs-7 text-muted">Com Slack ID</div>
                                    <div class="fw-bold fs-3"><?= count($colabs_com_slack) ?></div>
                                    <div class="fs-8 text-muted"><?= count($colabs_sem_slack) ?> pendentes</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Abas -->
                <ul class="nav nav-tabs nav-line-tabs nav-line-tabs-2x mb-6 fs-6 border-0" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link fw-semibold <?= $aba_ativa === 'configuracoes' ? 'active' : '' ?>" href="?aba=configuracoes">
                            ⚙️ Configurações
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-semibold <?= $aba_ativa === 'conexao' ? 'active' : '' ?>" href="?aba=conexao">
                            🔌 Conexão
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-semibold <?= $aba_ativa === 'sincronizacao' ? 'active' : '' ?>" href="?aba=sincronizacao">
                            🔄 Sincronização de Usuários
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-semibold <?= $aba_ativa === 'teste' ? 'active' : '' ?>" href="?aba=teste">
                            📨 Teste
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-semibold <?= $aba_ativa === 'logs' ? 'active' : '' ?>" href="?aba=logs">
                            📋 Fila &amp; Logs
                        </a>
                    </li>
                </ul>

                <!-- ── ABA: CONFIGURAÇÕES ─────────────────────────────────── -->
                <?php if ($aba_ativa === 'configuracoes'): ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Configurações do Bot Slack</h3>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info d-flex align-items-start mb-8">
                            <i class="ki-duotone ki-information-5 fs-2hx text-info me-4 mt-1"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                            <div>
                                <strong>Como obter o Bot Token:</strong>
                                <ol class="mb-0 mt-2">
                                    <li>Acesse <a href="https://api.slack.com/apps" target="_blank">api.slack.com/apps</a> e crie um novo app</li>
                                    <li>Em <strong>OAuth &amp; Permissions</strong>, adicione os scopes: <code>chat:write</code>, <code>users:read</code>, <code>users:read.email</code>, <code>channels:read</code></li>
                                    <li>Instale o app no workspace e copie o <strong>Bot User OAuth Token</strong> (começa com <code>xoxb-</code>)</li>
                                    <li>Adicione o bot ao canal de comunicados usando <code>/invite @seu-bot</code></li>
                                </ol>
                            </div>
                        </div>

                        <form method="POST">
                            <input type="hidden" name="action" value="salvar_config">

                            <div class="row mb-6">
                                <div class="col-md-12">
                                    <label class="form-label required fw-semibold">Bot User OAuth Token</label>
                                    <div class="input-group">
                                        <input type="password" name="bot_token" id="bot_token" class="form-control font-monospace"
                                               placeholder="xoxb-XXXXXXXXX-XXXXXXXXX-XXXXXXXXXXXXXXXXXXXXXXXX"
                                               value="<?= htmlspecialchars($config['bot_token'] ?? '') ?>" required>
                                        <button type="button" class="btn btn-light" onclick="toggleToken()">
                                            <i class="ki-duotone ki-eye fs-2" id="eye_icon"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                        </button>
                                    </div>
                                    <div class="form-text">Nunca compartilhe este token. Ele tem permissões de envio de mensagens no workspace.</div>
                                </div>
                            </div>

                            <div class="row mb-6">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Canal de Comunicados</label>
                                    <?php if ($canais_lista): ?>
                                    <select name="canal_comunicados" class="form-select">
                                        <option value="">-- Selecione um canal --</option>
                                        <?php foreach ($canais_lista as $ch): ?>
                                        <option value="<?= htmlspecialchars($ch['id']) ?>"
                                            <?= ($config['canal_comunicados'] ?? '') === $ch['id'] ? 'selected' : '' ?>>
                                            #<?= htmlspecialchars($ch['name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php else: ?>
                                    <input type="text" name="canal_comunicados" class="form-control"
                                           placeholder="#geral ou ID do canal (ex: C08XXXXXXX)"
                                           value="<?= htmlspecialchars($config['canal_comunicados'] ?? '') ?>">
                                    <?php endif; ?>
                                    <div class="form-text">Canal onde os comunicados serão postados. Adicione o bot ao canal primeiro.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Workspace</label>
                                    <input type="text" class="form-control" readonly
                                           value="<?= htmlspecialchars($config['workspace_nome'] ?? 'Salve as configurações para detectar automaticamente') ?>">
                                </div>
                            </div>

                            <div class="separator my-5"></div>
                            <h5 class="fw-bold mb-4">Opções de Envio</h5>

                            <div class="row mb-6">
                                <div class="col-md-4">
                                    <div class="form-check form-switch form-check-custom form-check-solid">
                                        <input class="form-check-input" type="checkbox" name="notificacoes_slack_ativas" id="notif_slack"
                                               <?= (!$config || $config['notificacoes_slack_ativas']) ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-semibold ms-3" for="notif_slack">
                                            DMs individuais ativas
                                        </label>
                                    </div>
                                    <div class="form-text">Notificações individuais (feedback, promoção, HE…) via DM no Slack.</div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check form-switch form-check-custom form-check-solid">
                                        <input class="form-check-input" type="checkbox" name="comunicados_no_canal" id="com_canal"
                                               <?= (!$config || $config['comunicados_no_canal']) ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-semibold ms-3" for="com_canal">
                                            Comunicados no canal
                                        </label>
                                    </div>
                                    <div class="form-text">Ao publicar comunicados, posta também no canal configurado acima.</div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check form-switch form-check-custom form-check-solid">
                                        <input class="form-check-input" type="checkbox" name="ativo" id="slack_ativo"
                                               <?= (!$config || $config['ativo']) ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-semibold ms-3" for="slack_ativo">
                                            Integração ativa
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="separator my-5"></div>
                            <h5 class="fw-bold mb-1">⏱️ Rate Limiting</h5>
                            <p class="text-muted fs-7 mb-4">Slack tem limite de ~50 chamadas/min por método (Tier 3). Com 2s de intervalo você fica bem abaixo.</p>
                            <div class="row mb-6">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Intervalo entre mensagens (segundos)</label>
                                    <input type="number" name="intervalo_entre_mensagens" class="form-control"
                                           min="1" max="30" value="<?= (int)($config['intervalo_entre_mensagens'] ?? 2) ?>">
                                    <div class="form-text">Padrão: <strong>2s</strong>. Slack suporta ~50 msgs/min.</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Limite máximo por hora</label>
                                    <input type="number" name="max_mensagens_por_hora" class="form-control"
                                           min="0" max="3000" step="50" value="<?= (int)($config['max_mensagens_por_hora'] ?? 300) ?>">
                                    <div class="form-text">0 = sem limite. Padrão: <strong>300/hora</strong>.</div>
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

                <!-- ── ABA: CONEXÃO ───────────────────────────────────────── -->
                <?php elseif ($aba_ativa === 'conexao'): ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Status da Conexão</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!$config): ?>
                            <div class="alert alert-warning">Configure o bot token primeiro.</div>
                        <?php elseif ($conexao_info && $conexao_info['ok']): ?>
                            <div class="alert alert-success d-flex align-items-center">
                                <span class="fs-2 me-3">✅</span>
                                <div>
                                    <strong>Conectado!</strong> Bot autenticado no workspace
                                    <strong><?= htmlspecialchars($conexao_info['team'] ?? '') ?></strong>.
                                    <br>Bot User ID: <code><?= htmlspecialchars($conexao_info['user_id'] ?? '') ?></code>
                                    &nbsp;|&nbsp; Bot: <strong><?= htmlspecialchars($conexao_info['user'] ?? '') ?></strong>
                                </div>
                            </div>
                            <div class="row mt-4">
                                <div class="col-md-6">
                                    <table class="table table-row-dashed fs-7">
                                        <tbody>
                                            <tr><td class="fw-semibold">Workspace ID</td><td><code><?= htmlspecialchars($conexao_info['team_id'] ?? '') ?></code></td></tr>
                                            <tr><td class="fw-semibold">URL</td><td><?= htmlspecialchars($conexao_info['url'] ?? '') ?></td></tr>
                                            <tr><td class="fw-semibold">Canal comunicados</td><td><?= htmlspecialchars($config['canal_comunicados'] ?? 'Não configurado') ?></td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-danger d-flex align-items-center">
                                <span class="fs-2 me-3">❌</span>
                                <div>
                                    <strong>Falha na autenticação.</strong>
                                    Erro: <code><?= htmlspecialchars($conexao_info['error'] ?? 'desconhecido') ?></code>
                                    <br>Verifique se o Bot Token está correto e se o app foi instalado no workspace.
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ── ABA: SINCRONIZAÇÃO ────────────────────────────────── -->
                <?php elseif ($aba_ativa === 'sincronizacao'): ?>
                <div class="row g-5">
                    <div class="col-md-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h3 class="card-title">Sincronização em Massa</h3>
                            </div>
                            <div class="card-body">
                                <p class="text-muted fs-7">
                                    Busca o Slack User ID de todos os colaboradores ativos com e-mail cadastrado
                                    usando a API <code>users.lookupByEmail</code>.
                                    Os colaboradores já sincronizados são ignorados.
                                </p>
                                <p class="text-muted fs-7">
                                    ⚠️ Este processo pode demorar alguns minutos dependendo da quantidade de colaboradores,
                                    pois respeita o rate limit de 0,5s entre chamadas.
                                </p>
                                <div class="d-flex gap-2 mt-4">
                                    <span class="badge badge-light-success px-3 py-2">✅ <?= count($colabs_com_slack) ?> sincronizados</span>
                                    <span class="badge badge-light-warning px-3 py-2">⏳ <?= count($colabs_sem_slack) ?> pendentes</span>
                                </div>
                                <form method="POST" class="mt-5">
                                    <input type="hidden" name="action" value="sincronizar_usuarios">
                                    <button type="submit" class="btn btn-primary w-100"
                                            onclick="return confirm('Isso pode demorar alguns minutos. Continuar?')">
                                        🔄 Sincronizar Todos Agora
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <!-- Colaboradores sem Slack ID -->
                        <?php if ($colabs_sem_slack): ?>
                        <div class="card mb-5">
                            <div class="card-header">
                                <h3 class="card-title text-warning">⏳ Sem Slack User ID (<?= count($colabs_sem_slack) ?>)</h3>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-row-dashed align-middle gs-0 gy-3 mb-0">
                                        <thead>
                                            <tr class="text-muted fs-7 border-bottom">
                                                <th class="ps-4">Colaborador</th>
                                                <th>E-mail</th>
                                                <th>Ação</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($colabs_sem_slack as $c): ?>
                                            <tr>
                                                <td class="ps-4 fw-semibold"><?= htmlspecialchars($c['nome_completo']) ?></td>
                                                <td class="text-muted fs-7"><?= htmlspecialchars($c['email_pessoal'] ?? '—') ?></td>
                                                <td>
                                                    <div class="d-flex gap-2">
                                                        <?php if (!empty($c['email_pessoal'])): ?>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="action" value="sincronizar_individual">
                                                            <input type="hidden" name="colaborador_id" value="<?= $c['id'] ?>">
                                                            <input type="hidden" name="email" value="<?= htmlspecialchars($c['email_pessoal']) ?>">
                                                            <button type="submit" class="btn btn-sm btn-light-primary">🔍 Buscar</button>
                                                        </form>
                                                        <?php endif; ?>
                                                        <button class="btn btn-sm btn-light-secondary"
                                                                onclick="abrirManual(<?= $c['id'] ?>, '<?= htmlspecialchars($c['nome_completo']) ?>')">
                                                            ✏️ Manual
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Colaboradores com Slack ID -->
                        <?php if ($colabs_com_slack): ?>
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title text-success">✅ Sincronizados (<?= count($colabs_com_slack) ?>)</h3>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-row-dashed align-middle gs-0 gy-2 mb-0">
                                        <thead>
                                            <tr class="text-muted fs-7 border-bottom">
                                                <th class="ps-4">Colaborador</th>
                                                <th>E-mail</th>
                                                <th>Slack User ID</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($colabs_com_slack as $c): ?>
                                            <tr>
                                                <td class="ps-4 fw-semibold"><?= htmlspecialchars($c['nome_completo']) ?></td>
                                                <td class="text-muted fs-7"><?= htmlspecialchars($c['email_pessoal'] ?? '—') ?></td>
                                                <td><code class="text-success"><?= htmlspecialchars($c['slack_user_id']) ?></code></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Modal manual -->
                <div class="modal fade" id="modalManual" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST">
                                <input type="hidden" name="action" value="salvar_slack_id_manual">
                                <input type="hidden" name="colaborador_id" id="modal_colab_id">
                                <div class="modal-header">
                                    <h5 class="modal-title">Informar Slack User ID Manualmente</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <p class="text-muted fs-7">Para encontrar o Slack User ID: no Slack, clique no perfil do usuário → "Ver perfil completo" → "…" → "Copiar ID do membro".</p>
                                    <label class="form-label fw-semibold">Colaborador: <span id="modal_colab_nome" class="text-primary"></span></label>
                                    <input type="text" name="slack_user_id" class="form-control font-monospace mt-3"
                                           placeholder="Uxxxxxxxxxxxxxxxxx" pattern="U[A-Z0-9]+" required>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-primary">Salvar</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- ── ABA: TESTE ─────────────────────────────────────────── -->
                <?php elseif ($aba_ativa === 'teste'): ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Enviar Mensagem de Teste</h3>
                    </div>
                    <div class="card-body">
                        <p class="text-muted fs-7">
                            A mensagem é enviada <strong>imediatamente</strong> (sem passar pela fila) para validar a configuração.
                            Use um User ID (<code>Uxxxxxxx</code>) ou o nome de um canal (<code>#geral</code>).
                        </p>
                        <form method="POST">
                            <input type="hidden" name="action" value="testar_mensagem">
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold required">Destino</label>
                                    <input type="text" name="canal_teste" class="form-control" required
                                           placeholder="#geral ou U08XXXXXXX (User ID)">
                                    <div class="form-text">Para DM, use o Slack User ID. Para canal, use #nome-do-canal.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">URL (botão)</label>
                                    <input type="url" name="url_teste" class="form-control"
                                           placeholder="https://seudominio.com/pages/...">
                                </div>
                            </div>
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Título</label>
                                    <input type="text" name="titulo_teste" class="form-control"
                                           value="🧪 Mensagem de Teste — RH Privus">
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="form-label fw-semibold">Mensagem</label>
                                <textarea name="mensagem_teste" class="form-control" rows="3">Esta é uma mensagem de teste enviada pelo painel do *RH Privus*. Se você está vendo isso, a integração está funcionando! 🎉</textarea>
                                <div class="form-text">Suporta formatação Slack: *negrito*, _itálico_, `código`, listas, etc.</div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                📨 Enviar Agora
                            </button>
                        </form>
                    </div>
                </div>

                <!-- ── ABA: LOGS ──────────────────────────────────────────── -->
                <?php elseif ($aba_ativa === 'logs'): ?>
                <div class="row g-5">
                    <!-- Fila atual -->
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h3 class="card-title">Fila de Mensagens</h3>
                                <div class="d-flex gap-2">
                                    <span class="badge badge-light-warning fs-7">⏳ <?= $fila_pendente ?> pendentes</span>
                                    <span class="badge badge-light-danger fs-7">❌ <?= $fila_erros ?> erros</span>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <?php
                                try {
                                    $fila_items = $pdo->query("
                                        SELECT f.*, c.nome_completo FROM slack_fila_mensagens f
                                        LEFT JOIN colaboradores c ON c.id = f.colaborador_id
                                        WHERE f.status IN ('pendente','erro')
                                        ORDER BY f.id DESC LIMIT 30
                                    ")->fetchAll(PDO::FETCH_ASSOC);
                                } catch (Exception $e) { $fila_items = []; }
                                ?>
                                <?php if ($fila_items): ?>
                                <div class="table-responsive">
                                    <table class="table table-row-dashed align-middle gs-4 gy-2 mb-0 fs-7">
                                        <thead>
                                            <tr class="text-muted border-bottom">
                                                <th>ID</th><th>Tipo</th><th>Destino</th><th>Título</th><th>Status</th><th>Tentativas</th><th>Criado</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($fila_items as $f): ?>
                                            <tr>
                                                <td><?= $f['id'] ?></td>
                                                <td><span class="badge badge-light"><?= $f['tipo'] ?></span></td>
                                                <td><code><?= htmlspecialchars($f['canal_destino']) ?></code>
                                                    <?php if ($f['nome_completo']): ?>
                                                    <br><span class="text-muted"><?= htmlspecialchars($f['nome_completo']) ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= htmlspecialchars(mb_substr($f['titulo'], 0, 40)) ?></td>
                                                <td>
                                                    <?php $badge = ['pendente' => 'warning', 'erro' => 'danger', 'enviado' => 'success', 'enviando' => 'info'][$f['status']] ?? 'secondary'; ?>
                                                    <span class="badge badge-light-<?= $badge ?>"><?= $f['status'] ?></span>
                                                    <?php if ($f['erro_detalhe']): ?>
                                                    <br><small class="text-danger"><?= htmlspecialchars(mb_substr($f['erro_detalhe'], 0, 60)) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= $f['tentativas'] ?>/3</td>
                                                <td><?= date('d/m H:i', strtotime($f['created_at'])) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <div class="text-center py-8 text-muted">Nenhum item pendente ou com erro na fila.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Log recente -->
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Últimas 20 Mensagens Enviadas</h3>
                            </div>
                            <div class="card-body p-0">
                                <?php if ($logs_recentes): ?>
                                <div class="table-responsive">
                                    <table class="table table-row-dashed align-middle gs-4 gy-2 mb-0 fs-7">
                                        <thead>
                                            <tr class="text-muted border-bottom">
                                                <th>Tipo</th><th>Destino</th><th>Título</th><th>Status</th><th>Enviado</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($logs_recentes as $l): ?>
                                            <tr>
                                                <td><span class="badge badge-light"><?= $l['tipo'] ?></span></td>
                                                <td>
                                                    <code><?= htmlspecialchars($l['canal_destino']) ?></code>
                                                    <?php if ($l['nome_completo']): ?>
                                                    <br><span class="text-muted"><?= htmlspecialchars($l['nome_completo']) ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= htmlspecialchars(mb_substr($l['titulo'] ?? '', 0, 50)) ?></td>
                                                <td>
                                                    <span class="badge badge-light-<?= $l['status'] === 'enviado' ? 'success' : 'danger' ?>">
                                                        <?= $l['status'] ?>
                                                    </span>
                                                    <?php if ($l['erro_detalhe']): ?>
                                                    <br><small class="text-danger"><?= htmlspecialchars(mb_substr($l['erro_detalhe'], 0, 60)) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= date('d/m H:i', strtotime($l['enviado_em'])) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <div class="text-center py-8 text-muted">Nenhuma mensagem enviada ainda.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<script>
function toggleToken() {
    const el = document.getElementById('bot_token');
    const icon = document.getElementById('eye_icon');
    el.type = el.type === 'password' ? 'text' : 'password';
    icon.className = el.type === 'password'
        ? 'ki-duotone ki-eye fs-2' : 'ki-duotone ki-eye-slash fs-2';
    icon.innerHTML = '<span class="path1"></span><span class="path2"></span><span class="path3"></span>';
}

function abrirManual(id, nome) {
    document.getElementById('modal_colab_id').value = id;
    document.getElementById('modal_colab_nome').textContent = nome;
    new bootstrap.Modal(document.getElementById('modalManual')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
