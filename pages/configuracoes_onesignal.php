<?php
/**
 * Configurações do OneSignal - Metronic Theme
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('configuracoes_onesignal.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Verifica e cria a tabela se não existir
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'onesignal_config'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("
            CREATE TABLE onesignal_config (
                id INT AUTO_INCREMENT PRIMARY KEY,
                app_id VARCHAR(255) NOT NULL,
                rest_api_key VARCHAR(255) NOT NULL,
                safari_web_id VARCHAR(255) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
} catch (PDOException $e) {
    // Tabela já existe
}

$success = '';
$error = '';
$aba_ativa = $_GET['aba'] ?? 'configuracoes';

// Verifica e cria tabela de preferências se não existir
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'push_notification_preferences'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("
            CREATE TABLE push_notification_preferences (
                id INT AUTO_INCREMENT PRIMARY KEY,
                usuario_id INT NULL,
                colaborador_id INT NULL,
                tipo_notificacao VARCHAR(50) NOT NULL COMMENT 'Ex: feedback_recebido, ocorrencia_criada, etc',
                ativo TINYINT(1) DEFAULT 1 COMMENT '1 = ativo, 0 = desativado',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
                FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
                INDEX idx_usuario (usuario_id),
                INDEX idx_colaborador (colaborador_id),
                INDEX idx_tipo (tipo_notificacao),
                INDEX idx_usuario_tipo (usuario_id, tipo_notificacao),
                INDEX idx_colaborador_tipo (colaborador_id, tipo_notificacao)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
} catch (PDOException $e) {
    // Tabela já existe ou erro
}

// Processa formulário de configurações
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tipo']) && $_POST['tipo'] === 'configuracoes') {
    $app_id = $_POST['app_id'] ?? '';
    $rest_api_key = $_POST['rest_api_key'] ?? '';
    $safari_web_id = $_POST['safari_web_id'] ?? '';
    
    if (empty($app_id) || empty($rest_api_key)) {
        $error = 'App ID e REST API Key são obrigatórios!';
    } else {
        try {
            // Verifica se já existe configuração
            $stmt = $pdo->query("SELECT id FROM onesignal_config ORDER BY id DESC LIMIT 1");
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Atualiza
                $stmt = $pdo->prepare("
                    UPDATE onesignal_config 
                    SET app_id = ?, rest_api_key = ?, safari_web_id = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$app_id, $rest_api_key, $safari_web_id ?: null, $existing['id']]);
            } else {
                // Cria nova
                $stmt = $pdo->prepare("
                    INSERT INTO onesignal_config (app_id, rest_api_key, safari_web_id)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$app_id, $rest_api_key, $safari_web_id ?: null]);
            }
            
            $success = 'Configurações salvas com sucesso!';
        } catch (PDOException $e) {
            $error = 'Erro ao salvar: ' . $e->getMessage();
        }
    }
}

// Busca preferências de notificações do usuário atual
$usuario_id = $usuario['id'] ?? null;
$colaborador_id = $usuario['colaborador_id'] ?? null;
$preferencias = [];

try {
    $where_pref = [];
    $params_pref = [];
    
    if ($usuario_id) {
        $where_pref[] = "usuario_id = ?";
        $params_pref[] = $usuario_id;
    }
    if ($colaborador_id) {
        $where_pref[] = "colaborador_id = ?";
        $params_pref[] = $colaborador_id;
    }
    
    if (!empty($where_pref)) {
        $where_sql = "(" . implode(" OR ", $where_pref) . ")";
        $stmt_pref = $pdo->prepare("
            SELECT tipo_notificacao, ativo 
            FROM push_notification_preferences 
            WHERE $where_sql
        ");
        $stmt_pref->execute($params_pref);
        $prefs = $stmt_pref->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($prefs as $pref) {
            $preferencias[$pref['tipo_notificacao']] = (bool)$pref['ativo'];
        }
    }
} catch (PDOException $e) {
    // Ignora erro
}

// Tipos de notificações disponíveis
$tipos_notificacoes = [
    'feedback_recebido' => [
        'nome' => 'Feedback Recebido',
        'descricao' => 'Receba notificações quando alguém enviar um feedback para você',
        'icone' => 'message-text'
    ],
    'documento_pagamento_enviado' => [
        'nome' => 'Documento de Pagamento Enviado',
        'descricao' => 'Receba notificações quando colaboradores enviarem documentos de pagamento (Admin/RH)',
        'icone' => 'file-up'
    ],
    'documento_pagamento_aprovado' => [
        'nome' => 'Documento de Pagamento Aprovado',
        'descricao' => 'Receba notificações quando seus documentos de pagamento forem aprovados',
        'icone' => 'check-circle'
    ],
    'documento_pagamento_rejeitado' => [
        'nome' => 'Documento de Pagamento Rejeitado',
        'descricao' => 'Receba notificações quando seus documentos de pagamento forem rejeitados',
        'icone' => 'cross-circle'
    ],
    // Adicione mais tipos aqui conforme necessário
];

// Busca configurações atuais
$stmt = $pdo->query("SELECT * FROM onesignal_config ORDER BY id DESC LIMIT 1");
$config = $stmt->fetch();

$page_title = 'Configurações OneSignal';
require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Content-->
<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    <!--begin::Container-->
    <div class="container-xxl">
        <!--begin::Page title-->
        <div class="page-title d-flex flex-column align-items-start justify-content-center flex-wrap me-lg-2 pb-5 pb-lg-0">
            <h1 class="page-heading d-flex text-dark fw-bold fs-3 flex-column justify-content-center my-0">
                Configurações OneSignal
            </h1>
            <ul class="breadcrumb fw-semibold fs-7 my-0 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Dashboard</a>
                </li>
                <li class="breadcrumb-item text-muted">Configurações</li>
                <li class="breadcrumb-item text-dark">OneSignal</li>
            </ul>
        </div>
        <!--end::Page title-->
        
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
        
        <!--begin::Card-->
        <div class="card">
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <h2>Configurações do OneSignal</h2>
                </div>
            </div>
            <div class="card-body pt-0">
                <!--begin::Tabs-->
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
                        <a class="nav-link text-active-primary me-10 <?= $aba_ativa === 'notificacoes' ? 'active' : '' ?>" 
                           href="?aba=notificacoes">
                            <i class="ki-duotone ki-notification-status fs-2 me-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                                <span class="path4"></span>
                            </i>
                            Notificações Push
                        </a>
                    </li>
                </ul>
                <!--end::Tabs-->
                
                <!--begin::Tab Content-->
                <?php if ($aba_ativa === 'configuracoes'): ?>
                <!--begin::Tab: Configurações-->
                <div class="mt-7">
                <form method="POST" id="kt_onesignal_form">
                    <div class="row mb-7">
                        <label class="col-lg-3 col-form-label required fw-semibold fs-6">App ID</label>
                        <div class="col-lg-9">
                            <input type="text" name="app_id" class="form-control form-control-solid" 
                                   value="<?= htmlspecialchars($config['app_id'] ?? '') ?>" 
                                   placeholder="Ex: 12345678-1234-1234-1234-123456789012" required />
                            <div class="form-text">Obtenha no painel do OneSignal em Settings → Keys & IDs</div>
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <label class="col-lg-3 col-form-label required fw-semibold fs-6">REST API Key</label>
                        <div class="col-lg-9">
                            <input type="password" name="rest_api_key" class="form-control form-control-solid" 
                                   value="<?= htmlspecialchars($config['rest_api_key'] ?? '') ?>" 
                                   placeholder="Ex: NGEwOGZmODItODNiYy00Y2Y0LWI..." required />
                            <div class="form-text">Obtenha no painel do OneSignal em Settings → Keys & IDs</div>
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <label class="col-lg-3 col-form-label fw-semibold fs-6">Safari Web ID</label>
                        <div class="col-lg-9">
                            <input type="text" name="safari_web_id" class="form-control form-control-solid" 
                                   value="<?= htmlspecialchars($config['safari_web_id'] ?? '') ?>" 
                                   placeholder="Opcional - Para iOS Safari" />
                            <div class="form-text">Opcional - Necessário apenas para iOS Safari</div>
                        </div>
                    </div>
                    
                    <div class="card-footer d-flex justify-content-end py-6 px-9">
                        <button type="submit" class="btn btn-primary">
                            <span class="indicator-label">Salvar Configurações</span>
                            <span class="indicator-progress">Salvando...
                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                        </button>
                    </div>
                    <input type="hidden" name="tipo" value="configuracoes">
                </form>
                
                <div class="separator separator-dashed my-10"></div>
                
                <div class="alert alert-info d-flex align-items-center p-5 mb-10">
                    <i class="ki-duotone ki-information fs-2hx text-info me-4">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    <div class="d-flex flex-column">
                        <h4 class="mb-1 text-info">Como obter as credenciais?</h4>
                        <ol class="mb-0">
                            <li>Acesse <a href="https://onesignal.com" target="_blank">onesignal.com</a> e crie uma conta</li>
                            <li>Crie um novo app/website (selecione "Web Push")</li>
                            <li>Vá em <strong>Settings → Keys & IDs</strong></li>
                            <li>Copie o <strong>OneSignal App ID</strong> e <strong>REST API Key</strong></li>
                            <li>Cole nos campos acima e salve</li>
                        </ol>
                    </div>
                </div>
                
                <!--begin::Card de Status-->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">
                            <h2>Status da Integração</h2>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php
                        // Verifica status
                        $status_config = $config ? 'ok' : 'erro';
                        
                        // Verifica se tabela existe
                        $total_subs = 0;
                        try {
                            $stmt_subs = $pdo->query("SELECT COUNT(*) as total FROM onesignal_subscriptions");
                            $total_subs = $stmt_subs->fetch()['total'];
                        } catch (PDOException $e) {
                            // Tabela não existe ainda
                            $total_subs = -1;
                        }
                        ?>
                        
                        <div class="row mb-5">
                            <div class="col-md-6">
                                <div class="d-flex align-items-center p-4 border rounded">
                                    <i class="ki-duotone ki-<?= $status_config === 'ok' ? 'check-circle' : 'cross-circle' ?> fs-2hx text-<?= $status_config === 'ok' ? 'success' : 'danger' ?> me-4">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    <div>
                                        <div class="fw-bold fs-5">Configuração</div>
                                        <div class="text-muted">
                                            <?php if ($status_config === 'ok'): ?>
                                                ✅ Configurado corretamente
                                            <?php else: ?>
                                                ❌ Não configurado
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex align-items-center p-4 border rounded">
                                    <i class="ki-duotone ki-<?= $total_subs > 0 ? 'check-circle' : 'information-5' ?> fs-2hx text-<?= $total_subs > 0 ? 'success' : 'warning' ?> me-4">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    <div>
                                        <div class="fw-bold fs-5">Subscriptions</div>
                                        <div class="text-muted">
                                            <?php if ($total_subs > 0): ?>
                                                ✅ <?= $total_subs ?> dispositivo(s) registrado(s)
                                            <?php else: ?>
                                                ⚠️ Nenhum dispositivo registrado ainda
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($total_subs === -1): ?>
                        <div class="alert alert-warning d-flex align-items-center p-5">
                            <i class="ki-duotone ki-information-5 fs-2hx text-warning me-4">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                            <div class="d-flex flex-column">
                                <h4 class="mb-1 text-warning">Tabelas não criadas</h4>
                                <span>Execute a migração do banco de dados primeiro: <a href="../executar_migracao_onesignal.php" class="btn btn-sm btn-warning">Executar Migração</a></span>
                            </div>
                        </div>
                        <?php elseif ($status_config === 'ok' && $total_subs > 0): ?>
                        <div class="alert alert-success d-flex align-items-center p-5">
                            <i class="ki-duotone ki-check-circle fs-2hx text-success me-4">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <div class="d-flex flex-column">
                                <h4 class="mb-1 text-success">OneSignal está funcionando!</h4>
                                <span>Você pode enviar notificações push. Faça um teste usando a função <code>enviar_push_colaborador()</code> no código.</span>
                            </div>
                        </div>
                        <?php elseif ($status_config === 'ok'): ?>
                        <div class="alert alert-warning d-flex align-items-center p-5">
                            <i class="ki-duotone ki-information-5 fs-2hx text-warning me-4">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                            <div class="d-flex flex-column">
                                <h4 class="mb-1 text-warning">Aguardando subscriptions</h4>
                                <span>As configurações estão corretas, mas ainda não há dispositivos registrados. Faça login no sistema e permita notificações para registrar o primeiro dispositivo.</span>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-danger d-flex align-items-center p-5">
                            <i class="ki-duotone ki-cross-circle fs-2hx text-danger me-4">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <div class="d-flex flex-column">
                                <h4 class="mb-1 text-danger">Configuração incompleta</h4>
                                <span>Preencha o App ID e REST API Key acima e salve para ativar o OneSignal.</span>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($total_subs > 0): ?>
                        <div class="separator separator-dashed my-10"></div>
                        <h3 class="mb-5">Dispositivos Registrados</h3>
                        <div class="table-responsive">
                            <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
                                <thead>
                                    <tr class="fw-bold text-muted">
                                        <th>ID</th>
                                        <th>Usuário</th>
                                        <th>Colaborador</th>
                                        <th>Dispositivo</th>
                                        <th>Registrado em</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $stmt_list = $pdo->query("
                                        SELECT os.*, 
                                               u.nome as usuario_nome,
                                               c.nome_completo as colaborador_nome
                                        FROM onesignal_subscriptions os
                                        LEFT JOIN usuarios u ON os.usuario_id = u.id
                                        LEFT JOIN colaboradores c ON os.colaborador_id = c.id
                                        ORDER BY os.created_at DESC
                                        LIMIT 10
                                    ");
                                    $subs = $stmt_list->fetchAll();
                                    foreach ($subs as $sub):
                                    ?>
                                    <tr>
                                        <td>
                                            <span class="text-muted fw-semibold text-muted d-block fs-7">
                                                <?= substr($sub['player_id'], 0, 20) ?>...
                                            </span>
                                        </td>
                                        <td>
                                            <?= $sub['usuario_nome'] ? htmlspecialchars($sub['usuario_nome']) : '<span class="text-muted">-</span>' ?>
                                        </td>
                                        <td>
                                            <?= $sub['colaborador_nome'] ? htmlspecialchars($sub['colaborador_nome']) : '<span class="text-muted">-</span>' ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?= $sub['device_type'] === 'mobile' ? 'primary' : 'info' ?>">
                                                <?= $sub['device_type'] === 'mobile' ? 'Mobile' : 'Web' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="text-muted fw-semibold text-muted d-block fs-7">
                                                <?= date('d/m/Y H:i', strtotime($sub['created_at'])) ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <!--end::Card de Status-->
                </div>
                <!--end::Tab: Configurações-->
                
                <?php elseif ($aba_ativa === 'notificacoes'): ?>
                <!--begin::Tab: Notificações-->
                <div class="mt-7">
                    <div class="alert alert-info d-flex align-items-center p-5 mb-10">
                        <i class="ki-duotone ki-information fs-2hx text-info me-4">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        <div class="d-flex flex-column">
                            <h4 class="mb-1 text-info">Controle suas Notificações Push</h4>
                            <span>Ative ou desative os tipos de notificações push que você deseja receber. As notificações internas do sistema continuarão funcionando normalmente.</span>
                        </div>
                    </div>
                    
                    <form id="form_preferencias_notificacoes">
                        <div class="card">
                            <div class="card-body">
                                <?php foreach ($tipos_notificacoes as $tipo => $info): ?>
                                    <?php 
                                    $ativo = isset($preferencias[$tipo]) ? $preferencias[$tipo] : true; // Por padrão, ativo
                                    ?>
                                    <div class="d-flex align-items-center justify-content-between p-5 border border-dashed rounded mb-5">
                                        <div class="d-flex align-items-center">
                                            <div class="symbol symbol-50px me-5">
                                                <i class="ki-duotone ki-<?= $info['icone'] ?> fs-2hx text-primary">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                            </div>
                                            <div>
                                                <h4 class="fw-bold text-gray-800 mb-1"><?= htmlspecialchars($info['nome']) ?></h4>
                                                <p class="text-muted mb-0"><?= htmlspecialchars($info['descricao']) ?></p>
                                            </div>
                                        </div>
                                        <div class="form-check form-switch form-check-custom form-check-solid">
                                            <input class="form-check-input h-30px w-50px" 
                                                   type="checkbox" 
                                                   name="preferencias[<?= htmlspecialchars($tipo) ?>]" 
                                                   id="pref_<?= htmlspecialchars($tipo) ?>"
                                                   value="1"
                                                   <?= $ativo ? 'checked' : '' ?> />
                                            <label class="form-check-label" for="pref_<?= htmlspecialchars($tipo) ?>">
                                                <?= $ativo ? 'Ativo' : 'Desativado' ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                
                                <?php if (empty($tipos_notificacoes)): ?>
                                    <div class="alert alert-warning">
                                        <i class="ki-duotone ki-information-5 fs-2hx text-warning me-4">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                        </i>
                                        <div class="d-flex flex-column">
                                            <h4 class="mb-1 text-warning">Nenhum tipo de notificação configurado</h4>
                                            <span>Os tipos de notificações serão adicionados automaticamente conforme o sistema é desenvolvido.</span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer d-flex justify-content-end py-6 px-9">
                                <button type="submit" class="btn btn-primary" id="btn_salvar_preferencias">
                                    <span class="indicator-label">Salvar Preferências</span>
                                    <span class="indicator-progress">Salvando...
                                    <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                <!--end::Tab: Notificações-->
                <?php endif; ?>
                <!--end::Tab Content-->
            </div>
        </div>
        <!--end::Card-->
    </div>
    <!--end::Container-->
</div>
<!--end::Content-->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
// Salvar preferências de notificações
document.getElementById('form_preferencias_notificacoes')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const form = this;
    const btn = document.getElementById('btn_salvar_preferencias');
    const formData = new FormData(form);
    
    btn.setAttribute('data-kt-indicator', 'on');
    btn.disabled = true;
    
    fetch('../api/notificacoes/salvar_preferencias.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                text: data.message || 'Preferências salvas com sucesso!',
                icon: "success",
                buttonsStyling: false,
                confirmButtonText: "Ok",
                customClass: {
                    confirmButton: "btn btn-primary"
                }
            });
        } else {
            Swal.fire({
                text: data.message || 'Erro ao salvar preferências',
                icon: "error",
                buttonsStyling: false,
                confirmButtonText: "Ok",
                customClass: {
                    confirmButton: "btn btn-primary"
                }
            });
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        Swal.fire({
            text: 'Erro ao salvar preferências',
            icon: "error",
            buttonsStyling: false,
            confirmButtonText: "Ok",
            customClass: {
                confirmButton: "btn btn-primary"
            }
        });
    })
    .finally(() => {
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;
    });
});
</script>

