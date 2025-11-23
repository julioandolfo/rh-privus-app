<?php
/**
 * Configurações de Email/SMTP - Metronic Theme
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('configuracoes_email.php');

$pdo = getDB();

// Verifica e cria a tabela se não existir
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'configuracoes_email'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("
            CREATE TABLE configuracoes_email (
                id INT AUTO_INCREMENT PRIMARY KEY,
                smtp_host VARCHAR(255) NOT NULL DEFAULT 'smtp.gmail.com',
                smtp_port INT NOT NULL DEFAULT 587,
                smtp_secure ENUM('tls', 'ssl') NOT NULL DEFAULT 'tls',
                smtp_auth TINYINT(1) NOT NULL DEFAULT 1,
                smtp_username VARCHAR(255) NOT NULL DEFAULT '',
                smtp_password VARCHAR(255) NOT NULL DEFAULT '',
                from_email VARCHAR(255) NOT NULL DEFAULT 'noreply@privus.com.br',
                from_name VARCHAR(255) NOT NULL DEFAULT 'RH Privus',
                smtp_debug TINYINT(1) NOT NULL DEFAULT 0,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                updated_by INT NULL,
                FOREIGN KEY (updated_by) REFERENCES usuarios(id) ON DELETE SET NULL,
                UNIQUE KEY uk_config_email (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Insere registro padrão
        $pdo->exec("
            INSERT INTO configuracoes_email (id, smtp_host, smtp_port, smtp_secure, smtp_auth, smtp_username, smtp_password, from_email, from_name, smtp_debug)
            VALUES (1, 'smtp.gmail.com', 587, 'tls', 1, '', '', 'noreply@privus.com.br', 'RH Privus', 0)
        ");
    }
} catch (PDOException $e) {
    // Ignora erro se a tabela já existir
}

// Verifica e cria tabela de templates se não existir
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'email_templates'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("
            CREATE TABLE email_templates (
                id INT AUTO_INCREMENT PRIMARY KEY,
                codigo VARCHAR(50) UNIQUE NOT NULL COMMENT 'Código único do template',
                nome VARCHAR(255) NOT NULL,
                assunto VARCHAR(255) NOT NULL,
                corpo_html LONGTEXT NOT NULL,
                corpo_texto TEXT NULL,
                ativo TINYINT(1) NOT NULL DEFAULT 1,
                variaveis_disponiveis TEXT NULL,
                descricao TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_codigo (codigo),
                INDEX idx_ativo (ativo)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Insere templates padrão
        $templates_padrao = [
            ['novo_colaborador', 'Novo Colaborador', 'Bem-vindo ao {empresa_nome}!', '<h2>Olá {nome_completo}!</h2><p>Bem-vindo ao <strong>{empresa_nome}</strong>!</p><p>Estamos felizes em tê-lo(a) em nossa equipe.</p><p><strong>Dados do seu cadastro:</strong></p><ul><li><strong>Cargo:</strong> {cargo_nome}</li><li><strong>Setor:</strong> {setor_nome}</li><li><strong>Data de Início:</strong> {data_inicio}</li><li><strong>Tipo de Contrato:</strong> {tipo_contrato}</li></ul><p>Bem-vindo(a)!</p>', 'Olá {nome_completo}!\n\nBem-vindo ao {empresa_nome}!\n\nDados: Cargo: {cargo_nome}, Setor: {setor_nome}, Data: {data_inicio}', 'Enviado quando um novo colaborador é cadastrado.'],
            ['nova_promocao', 'Nova Promoção', 'Parabéns! Você recebeu uma promoção!', '<h2>Parabéns, {nome_completo}!</h2><p>Temos o prazer de informar que você recebeu uma promoção!</p><p><strong>Detalhes:</strong></p><ul><li><strong>Data:</strong> {data_promocao}</li><li><strong>Salário Anterior:</strong> R$ {salario_anterior}</li><li><strong>Novo Salário:</strong> R$ {salario_novo}</li><li><strong>Motivo:</strong> {motivo}</li></ul><p>Parabéns!</p>', 'Parabéns, {nome_completo}!\n\nVocê recebeu uma promoção!\nData: {data_promocao}\nNovo Salário: R$ {salario_novo}', 'Enviado quando uma promoção é registrada.'],
            ['fechamento_pagamento', 'Fechamento de Pagamento', 'Seu pagamento de {mes_referencia} está disponível', '<h2>Olá {nome_completo}!</h2><p>Informamos que o fechamento do pagamento referente ao mês de <strong>{mes_referencia}</strong> está disponível.</p><p><strong>Resumo:</strong></p><ul><li><strong>Salário Base:</strong> R$ {salario_base}</li><li><strong>Horas Extras:</strong> {horas_extras} horas - R$ {valor_horas_extras}</li><li><strong>Valor Total:</strong> R$ {valor_total}</li></ul>', 'Olá {nome_completo}!\n\nFechamento do mês {mes_referencia} disponível.\nValor Total: R$ {valor_total}', 'Enviado para cada colaborador quando um fechamento é realizado.'],
            ['ocorrencia', 'Ocorrência Registrada', 'Ocorrência registrada - {tipo_ocorrencia}', '<h2>Olá {nome_completo}!</h2><p>Informamos que foi registrada uma ocorrência em seu nome.</p><p><strong>Detalhes:</strong></p><ul><li><strong>Tipo:</strong> {tipo_ocorrencia}</li><li><strong>Data:</strong> {data_ocorrencia}</li><li><strong>Descrição:</strong> {descricao}</li></ul>', 'Olá {nome_completo}!\n\nOcorrência registrada:\nTipo: {tipo_ocorrencia}\nData: {data_ocorrencia}', 'Enviado quando uma ocorrência é registrada para um colaborador.']
        ];
        
        $stmt_ins = $pdo->prepare("INSERT INTO email_templates (codigo, nome, assunto, corpo_html, corpo_texto, descricao) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($templates_padrao as $tpl) {
            try {
                $stmt_ins->execute($tpl);
            } catch (PDOException $e) {
                // Ignora se já existir
            }
        }
    }
} catch (PDOException $e) {
    // Ignora erro se a tabela já existir
}

// Processa ações ANTES de incluir o header
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_template') {
        $id = (int)($_POST['id'] ?? 0);
        $codigo = sanitize($_POST['codigo'] ?? '');
        $nome = sanitize($_POST['nome'] ?? '');
        $assunto = sanitize($_POST['assunto'] ?? '');
        $corpo_html = $_POST['corpo_html'] ?? '';
        $corpo_texto = $_POST['corpo_texto'] ?? '';
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        $descricao = sanitize($_POST['descricao'] ?? '');
        
        if (empty($codigo) || empty($nome) || empty($assunto) || empty($corpo_html)) {
            redirect('configuracoes_email.php?tab=templates', 'Preencha os campos obrigatórios!', 'error');
        }
        
        try {
            if ($id > 0) {
                // Atualiza
                $stmt = $pdo->prepare("
                    UPDATE email_templates 
                    SET nome = ?, assunto = ?, corpo_html = ?, corpo_texto = ?, ativo = ?, descricao = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nome, $assunto, $corpo_html, $corpo_texto, $ativo, $descricao, $id]);
            } else {
                // Insere
                $stmt = $pdo->prepare("
                    INSERT INTO email_templates (codigo, nome, assunto, corpo_html, corpo_texto, ativo, descricao)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$codigo, $nome, $assunto, $corpo_html, $corpo_texto, $ativo, $descricao]);
            }
            
            redirect('configuracoes_email.php?tab=templates', 'Template salvo com sucesso!');
        } catch (PDOException $e) {
            redirect('configuracoes_email.php?tab=templates', 'Erro ao salvar: ' . $e->getMessage(), 'error');
        }
    } elseif ($action === 'toggle_template') {
        $id = (int)($_POST['id'] ?? 0);
        $ativo = (int)($_POST['ativo'] ?? 0);
        
        try {
            $stmt = $pdo->prepare("UPDATE email_templates SET ativo = ? WHERE id = ?");
            $stmt->execute([$ativo, $id]);
            echo json_encode(['success' => true]);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    } elseif ($action === 'save_smtp') {
        $smtp_host = sanitize($_POST['smtp_host'] ?? '');
        $smtp_port = (int)($_POST['smtp_port'] ?? 587);
        $smtp_secure = sanitize($_POST['smtp_secure'] ?? 'tls');
        $smtp_auth = isset($_POST['smtp_auth']) ? 1 : 0;
        $smtp_username = sanitize($_POST['smtp_username'] ?? '');
        $smtp_password = $_POST['smtp_password'] ?? '';
        $from_email = sanitize($_POST['from_email'] ?? '');
        $from_name = sanitize($_POST['from_name'] ?? '');
        $smtp_debug = isset($_POST['smtp_debug']) ? 1 : 0;
        
        if (empty($smtp_host)) {
            redirect('configuracoes_email.php', 'Servidor SMTP é obrigatório!', 'error');
        }
        
        if ($smtp_auth && (empty($smtp_username) || empty($smtp_password))) {
            redirect('configuracoes_email.php', 'Usuário e senha são obrigatórios quando autenticação está ativada!', 'error');
        }
        
        try {
            // Busca configuração existente
            $stmt = $pdo->prepare("SELECT id FROM configuracoes_email WHERE id = 1");
            $stmt->execute();
            $existe = $stmt->fetch();
            
            if ($existe) {
                // Atualiza
                $stmt = $pdo->prepare("
                    UPDATE configuracoes_email 
                    SET smtp_host = ?, smtp_port = ?, smtp_secure = ?, smtp_auth = ?, 
                        smtp_username = ?, smtp_password = ?, from_email = ?, from_name = ?, 
                        smtp_debug = ?, updated_by = ?
                    WHERE id = 1
                ");
                $stmt->execute([
                    $smtp_host, $smtp_port, $smtp_secure, $smtp_auth,
                    $smtp_username, $smtp_password, $from_email, $from_name,
                    $smtp_debug, $_SESSION['usuario']['id']
                ]);
            } else {
                // Insere
                $stmt = $pdo->prepare("
                    INSERT INTO configuracoes_email 
                    (id, smtp_host, smtp_port, smtp_secure, smtp_auth, smtp_username, smtp_password, from_email, from_name, smtp_debug, updated_by)
                    VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $smtp_host, $smtp_port, $smtp_secure, $smtp_auth,
                    $smtp_username, $smtp_password, $from_email, $from_name,
                    $smtp_debug, $_SESSION['usuario']['id']
                ]);
            }
            
            redirect('configuracoes_email.php', 'Configurações de email salvas com sucesso!');
        } catch (PDOException $e) {
            redirect('configuracoes_email.php', 'Erro ao salvar: ' . $e->getMessage(), 'error');
        }
    } elseif ($action === 'test') {
        // Testa envio de email
        require_once __DIR__ . '/../includes/email.php';
        
        $email_teste = sanitize($_POST['email_teste'] ?? '');
        if (empty($email_teste)) {
            redirect('configuracoes_email.php', 'Informe um email para teste!', 'error');
        }
        
        $resultado = enviar_email(
            $email_teste,
            'Teste de Configuração - RH Privus',
            '<h2>Email de Teste</h2><p>Se você recebeu este email, significa que as configurações de SMTP estão corretas!</p><p>Data/Hora: ' . date('d/m/Y H:i:s') . '</p>',
            ['nome_destinatario' => 'Teste']
        );
        
        if ($resultado['success']) {
            redirect('configuracoes_email.php', 'Email de teste enviado com sucesso! Verifique a caixa de entrada.', 'success');
        } else {
            redirect('configuracoes_email.php', 'Erro ao enviar email de teste: ' . $resultado['message'], 'error');
        }
    }
}

// Busca configurações atuais
$stmt = $pdo->prepare("SELECT * FROM configuracoes_email WHERE id = 1");
$stmt->execute();
$config = $stmt->fetch();

// Se não existir, usa valores padrão
if (!$config) {
    $config = [
        'smtp_host' => 'smtp.gmail.com',
        'smtp_port' => 587,
        'smtp_secure' => 'tls',
        'smtp_auth' => 1,
        'smtp_username' => '',
        'smtp_password' => '',
        'from_email' => 'noreply@privus.com.br',
        'from_name' => 'RH Privus',
        'smtp_debug' => 0
    ];
}

// Busca templates
$stmt = $pdo->query("SELECT * FROM email_templates ORDER BY nome");
$templates = $stmt->fetchAll();

// Agora inclui o header
$page_title = 'Configurações de Email';
require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Configurações de Email/SMTP</h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Configurações</li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Email</li>
            </ul>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <!--begin::Card-->
        <div class="card" style="overflow: visible;">
            <!--begin::Card header-->
            <div class="card-header border-0 pt-6" style="overflow: visible;">
                <div class="card-title">
                    <!--begin::Tabs-->
                    <ul class="nav nav-stretch nav-line-tabs nav-line-tabs-2x border-transparent fs-5 fw-bold flex-nowrap" style="overflow-x: auto; overflow-y: visible !important; -webkit-overflow-scrolling: touch;">
                        <li class="nav-item mt-2 flex-shrink-0">
                            <a class="nav-link text-active-primary ms-0 me-5 me-md-10 py-5 <?= (!isset($_GET['tab']) || $_GET['tab'] !== 'templates') ? 'active' : '' ?>" data-bs-toggle="tab" href="#kt_tab_smtp">
                                <i class="ki-duotone ki-setting-2 fs-2 me-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <span class="d-none d-md-inline">Configurações SMTP</span>
                                <span class="d-md-none">SMTP</span>
                            </a>
                        </li>
                        <li class="nav-item mt-2 flex-shrink-0">
                            <a class="nav-link text-active-primary py-5 <?= (isset($_GET['tab']) && $_GET['tab'] === 'templates') ? 'active' : '' ?>" data-bs-toggle="tab" href="#kt_tab_templates">
                                <i class="ki-duotone ki-file fs-2 me-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <span class="d-none d-md-inline">Templates de Email</span>
                                <span class="d-md-none">Templates</span>
                            </a>
                        </li>
                    </ul>
                    <!--end::Tabs-->
                </div>
            </div>
            <!--end::Card header-->
            
            <!--begin::Card body-->
            <div class="card-body pt-0" style="overflow: visible; max-height: none; height: auto;">
                <!--begin::Tab Content-->
                <div class="tab-content" style="overflow: visible;">
                    <!--begin::Tab Pane - SMTP-->
                    <div class="tab-pane fade <?= (!isset($_GET['tab']) || $_GET['tab'] !== 'templates') ? 'show active' : '' ?>" id="kt_tab_smtp" role="tabpanel" style="overflow: visible;">
                        <form id="kt_config_email_form" method="POST" class="form">
                    <input type="hidden" name="action" value="save_smtp">
                    
                    <!--begin::Row-->
                    <div class="row mb-7">
                        <div class="col-md-6">
                            <label class="required fw-semibold fs-6 mb-2">Servidor SMTP (Host)</label>
                            <input type="text" name="smtp_host" id="smtp_host" class="form-control form-control-solid" 
                                   value="<?= htmlspecialchars($config['smtp_host']) ?>" required 
                                   placeholder="Ex: smtp.gmail.com" />
                            <small class="text-muted">Exemplos: smtp.gmail.com, smtp-mail.outlook.com, smtp.sendgrid.net</small>
                        </div>
                        <div class="col-md-3">
                            <label class="required fw-semibold fs-6 mb-2">Porta</label>
                            <input type="number" name="smtp_port" id="smtp_port" class="form-control form-control-solid" 
                                   value="<?= htmlspecialchars($config['smtp_port']) ?>" required 
                                   placeholder="587" min="1" max="65535" />
                            <small class="text-muted">587 (TLS) ou 465 (SSL)</small>
                        </div>
                        <div class="col-md-3">
                            <label class="required fw-semibold fs-6 mb-2">Segurança</label>
                            <select name="smtp_secure" id="smtp_secure" class="form-select form-select-solid" required>
                                <option value="tls" <?= $config['smtp_secure'] === 'tls' ? 'selected' : '' ?>>TLS</option>
                                <option value="ssl" <?= $config['smtp_secure'] === 'ssl' ? 'selected' : '' ?>>SSL</option>
                            </select>
                        </div>
                    </div>
                    <!--end::Row-->
                    
                    <!--begin::Row-->
                    <div class="row mb-7">
                        <div class="col-md-12">
                            <div class="form-check form-check-custom form-check-solid mb-3">
                                <input class="form-check-input" type="checkbox" name="smtp_auth" id="smtp_auth" 
                                       value="1" <?= $config['smtp_auth'] ? 'checked' : '' ?> />
                                <label class="form-check-label" for="smtp_auth">
                                    Requer autenticação (SMTP Auth)
                                </label>
                            </div>
                        </div>
                    </div>
                    <!--end::Row-->
                    
                    <!--begin::Row-->
                    <div class="row mb-7" id="auth_fields">
                        <div class="col-md-6">
                            <label class="fw-semibold fs-6 mb-2">Usuário SMTP</label>
                            <input type="text" name="smtp_username" id="smtp_username" class="form-control form-control-solid" 
                                   value="<?= htmlspecialchars($config['smtp_username']) ?>" 
                                   placeholder="seu_email@gmail.com" />
                        </div>
                        <div class="col-md-6">
                            <label class="fw-semibold fs-6 mb-2">Senha SMTP</label>
                            <div class="input-group">
                                <input type="password" name="smtp_password" id="smtp_password" class="form-control form-control-solid" 
                                       value="<?= htmlspecialchars($config['smtp_password']) ?>" 
                                       placeholder="Sua senha ou senha de app" />
                                <button class="btn btn-icon btn-light" type="button" id="toggle_password">
                                    <i class="ki-duotone ki-eye fs-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                </button>
                            </div>
                            <small class="text-muted">Para Gmail, use uma senha de app, não sua senha normal</small>
                        </div>
                    </div>
                    <!--end::Row-->
                    
                    <!--begin::Row-->
                    <div class="row mb-7">
                        <div class="col-md-6">
                            <label class="required fw-semibold fs-6 mb-2">Email Remetente</label>
                            <input type="email" name="from_email" id="from_email" class="form-control form-control-solid" 
                                   value="<?= htmlspecialchars($config['from_email']) ?>" required 
                                   placeholder="noreply@privus.com.br" />
                            <small class="text-muted">Email que aparecerá como remetente</small>
                        </div>
                        <div class="col-md-6">
                            <label class="required fw-semibold fs-6 mb-2">Nome do Remetente</label>
                            <input type="text" name="from_name" id="from_name" class="form-control form-control-solid" 
                                   value="<?= htmlspecialchars($config['from_name']) ?>" required 
                                   placeholder="RH Privus" />
                        </div>
                    </div>
                    <!--end::Row-->
                    
                    <!--begin::Row-->
                    <div class="row mb-7">
                        <div class="col-md-12">
                            <div class="form-check form-check-custom form-check-solid">
                                <input class="form-check-input" type="checkbox" name="smtp_debug" id="smtp_debug" 
                                       value="1" <?= $config['smtp_debug'] ? 'checked' : '' ?> />
                                <label class="form-check-label" for="smtp_debug">
                                    Modo Debug (mostra mensagens detalhadas de erro)
                                </label>
                                <small class="text-muted d-block">Ative apenas para diagnóstico. Desative em produção.</small>
                            </div>
                        </div>
                    </div>
                    <!--end::Row-->
                    
                    <!--begin::Separator-->
                    <div class="separator separator-dashed my-10"></div>
                    <!--end::Separator-->
                    
                    <!--begin::Actions-->
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-light me-3" id="test_email_btn">Testar Email</button>
                        <button type="submit" class="btn btn-primary">
                            <span class="indicator-label">Salvar Configurações</span>
                            <span class="indicator-progress">Salvando...
                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                        </button>
                    </div>
                    <!--end::Actions-->
                        </form>
                    </div>
                    <!--end::Tab Pane - SMTP-->
                    
                    <!--begin::Tab Pane - Templates-->
                    <div class="tab-pane fade <?= (isset($_GET['tab']) && $_GET['tab'] === 'templates') ? 'show active' : '' ?>" id="kt_tab_templates" role="tabpanel" style="overflow: visible !important;">
                        <div style="overflow-x: auto; overflow-y: visible !important; max-height: none !important;">
                            <table class="table align-middle table-row-dashed fs-6 gy-5" id="kt_templates_table">
                                <thead>
                                    <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                                        <th class="min-w-150px">Nome</th>
                                        <th class="min-w-100px">Código</th>
                                        <th class="min-w-200px">Assunto</th>
                                        <th class="min-w-100px">Status</th>
                                        <th class="text-end min-w-100px">Ações</th>
                                    </tr>
                                </thead>
                                <tbody class="fw-semibold text-gray-600">
                                    <?php foreach ($templates as $template): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <span class="text-gray-800 mb-1"><?= htmlspecialchars($template['nome']) ?></span>
                                                <?php if ($template['descricao']): ?>
                                                <small class="text-muted"><?= htmlspecialchars($template['descricao']) ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <code><?= htmlspecialchars($template['codigo']) ?></code>
                                        </td>
                                        <td><?= htmlspecialchars($template['assunto']) ?></td>
                                        <td>
                                            <div class="form-check form-switch form-check-custom form-check-solid">
                                                <input class="form-check-input toggle-template" type="checkbox" 
                                                       data-id="<?= $template['id'] ?>" 
                                                       <?= $template['ativo'] ? 'checked' : '' ?> />
                                                <label class="form-check-label">
                                                    <?= $template['ativo'] ? 'Ativo' : 'Inativo' ?>
                                                </label>
                                            </div>
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
                        
                        <!--begin::Card - Variáveis Disponíveis-->
                        <div class="card mt-5">
                            <div class="card-header">
                                <div class="card-title">
                                    <h3 class="fw-bold">Variáveis Disponíveis</h3>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h5 class="mb-3">Novo Colaborador</h5>
                                        <ul class="list-unstyled">
                                            <li><code>{nome_completo}</code> - Nome completo do colaborador</li>
                                            <li><code>{empresa_nome}</code> - Nome da empresa</li>
                                            <li><code>{cargo_nome}</code> - Nome do cargo</li>
                                            <li><code>{setor_nome}</code> - Nome do setor</li>
                                            <li><code>{data_inicio}</code> - Data de início</li>
                                            <li><code>{tipo_contrato}</code> - Tipo de contrato</li>
                                            <li><code>{cpf}</code> - CPF formatado</li>
                                            <li><code>{email_pessoal}</code> - Email pessoal</li>
                                            <li><code>{telefone}</code> - Telefone</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <h5 class="mb-3">Nova Promoção</h5>
                                        <ul class="list-unstyled">
                                            <li><code>{nome_completo}</code> - Nome do colaborador</li>
                                            <li><code>{data_promocao}</code> - Data da promoção</li>
                                            <li><code>{salario_anterior}</code> - Salário anterior</li>
                                            <li><code>{salario_novo}</code> - Novo salário</li>
                                            <li><code>{motivo}</code> - Motivo da promoção</li>
                                            <li><code>{observacoes}</code> - Observações</li>
                                            <li><code>{empresa_nome}</code> - Nome da empresa</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6 mt-4">
                                        <h5 class="mb-3">Fechamento de Pagamento</h5>
                                        <ul class="list-unstyled">
                                            <li><code>{nome_completo}</code> - Nome do colaborador</li>
                                            <li><code>{mes_referencia}</code> - Mês de referência</li>
                                            <li><code>{salario_base}</code> - Salário base</li>
                                            <li><code>{horas_extras}</code> - Quantidade de horas extras</li>
                                            <li><code>{valor_horas_extras}</code> - Valor das horas extras</li>
                                            <li><code>{descontos}</code> - Descontos</li>
                                            <li><code>{adicionais}</code> - Adicionais</li>
                                            <li><code>{valor_total}</code> - Valor total</li>
                                            <li><code>{data_fechamento}</code> - Data do fechamento</li>
                                            <li><code>{observacoes}</code> - Observações</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6 mt-4">
                                        <h5 class="mb-3">Ocorrência</h5>
                                        <ul class="list-unstyled">
                                            <li><code>{nome_completo}</code> - Nome do colaborador</li>
                                            <li><code>{tipo_ocorrencia}</code> - Tipo da ocorrência</li>
                                            <li><code>{data_ocorrencia}</code> - Data da ocorrência</li>
                                            <li><code>{hora_ocorrencia}</code> - Hora da ocorrência</li>
                                            <li><code>{tempo_atraso}</code> - Tempo de atraso</li>
                                            <li><code>{descricao}</code> - Descrição</li>
                                            <li><code>{usuario_registro}</code> - Usuário que registrou</li>
                                            <li><code>{data_registro}</code> - Data/hora do registro</li>
                                            <li><code>{empresa_nome}</code> - Nome da empresa</li>
                                            <li><code>{setor_nome}</code> - Nome do setor</li>
                                            <li><code>{cargo_nome}</code> - Nome do cargo</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6 mt-4">
                                        <h5 class="mb-3">Recrutamento - Confirmação/Aprovação/Rejeição</h5>
                                        <ul class="list-unstyled">
                                            <li><code>{nome_completo}</code> ou <code>{nome}</code> - Nome do candidato</li>
                                            <li><code>{email}</code> - Email do candidato</li>
                                            <li><code>{telefone}</code> - Telefone do candidato</li>
                                            <li><code>{vaga_titulo}</code> ou <code>{vaga}</code> - Título da vaga</li>
                                            <li><code>{empresa_nome}</code> - Nome da empresa</li>
                                            <li><code>{data_candidatura}</code> - Data da candidatura</li>
                                            <li><code>{data_aprovacao}</code> - Data da aprovação</li>
                                            <li><code>{data_rejeicao}</code> - Data da rejeição</li>
                                            <li><code>{motivo_rejeicao}</code> - Motivo da rejeição (HTML)</li>
                                            <li><code>{link_acompanhamento}</code> - Link para acompanhar candidatura</li>
                                            <li><code>{status}</code> - Status da candidatura</li>
                                            <li><code>{nota_geral}</code> - Nota geral do candidato</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6 mt-4">
                                        <h5 class="mb-3">Recrutamento - Nova Candidatura (Recrutador)</h5>
                                        <ul class="list-unstyled">
                                            <li><code>{nome_completo}</code> ou <code>{nome}</code> - Nome do candidato</li>
                                            <li><code>{email}</code> - Email do candidato</li>
                                            <li><code>{telefone}</code> - Telefone do candidato</li>
                                            <li><code>{vaga_titulo}</code> ou <code>{vaga}</code> - Título da vaga</li>
                                            <li><code>{empresa_nome}</code> - Nome da empresa</li>
                                            <li><code>{data_candidatura}</code> - Data da candidatura</li>
                                            <li><code>{link_candidatura}</code> - Link para ver candidatura no sistema</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!--end::Card-->
                    </div>
                    <!--end::Tab Pane - Templates-->
                </div>
                <!--end::Tab Content-->
            </div>
            <!--end::Card body-->
        </div>
        <!--end::Card-->
        
        <!--begin::Card - Informações-->
        <div class="card mt-5">
            <div class="card-header">
                <div class="card-title">
                    <h3 class="fw-bold">Informações e Dicas</h3>
                </div>
            </div>
            <div class="card-body">
                <div class="alert alert-info d-flex align-items-center p-5">
                    <i class="ki-duotone ki-information-5 fs-2hx text-info me-4">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    <div class="d-flex flex-column">
                        <h4 class="mb-1 text-info">Configurações Comuns</h4>
                        <ul class="mb-0">
                            <li><strong>Gmail:</strong> smtp.gmail.com, Porta 587 (TLS) ou 465 (SSL), use senha de app</li>
                            <li><strong>Outlook/Hotmail:</strong> smtp-mail.outlook.com, Porta 587 (TLS)</li>
                            <li><strong>SendGrid:</strong> smtp.sendgrid.net, Porta 587, usuário: apikey, senha: sua API key</li>
                            <li><strong>Amazon SES:</strong> email-smtp.regiao.amazonaws.com, Porta 587 (TLS)</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <!--end::Card-->
        
    </div>
</div>
<!--end::Post-->

<!--begin::Modal - Template-->
<div class="modal fade" id="kt_modal_template" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-900px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold" id="template_modal_title">Editar Template</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <form id="kt_template_form" method="POST">
                    <input type="hidden" name="action" value="save_template">
                    <input type="hidden" name="id" id="template_id">
                    
                    <div class="row mb-7">
                        <div class="col-md-6">
                            <label class="required fw-semibold fs-6 mb-2">Código</label>
                            <input type="text" name="codigo" id="template_codigo" class="form-control form-control-solid" required readonly />
                            <small class="text-muted">Código único do template (não pode ser alterado)</small>
                        </div>
                        <div class="col-md-6">
                            <label class="required fw-semibold fs-6 mb-2">Nome</label>
                            <input type="text" name="nome" id="template_nome" class="form-control form-control-solid" required />
                        </div>
                    </div>
                    
                    <div class="mb-7">
                        <label class="fw-semibold fs-6 mb-2">Descrição</label>
                        <textarea name="descricao" id="template_descricao" class="form-control form-control-solid" rows="2"></textarea>
                        <small class="text-muted">Descrição de quando este template é usado</small>
                    </div>
                    
                    <div class="mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Assunto do Email</label>
                        <input type="text" name="assunto" id="template_assunto" class="form-control form-control-solid" required />
                        <small class="text-muted">Use variáveis como {nome_completo}, {empresa_nome}, etc.</small>
                    </div>
                    
                    <div class="mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Corpo do Email (HTML)</label>
                        <textarea name="corpo_html" id="template_corpo_html" class="form-control form-control-solid" rows="15" required></textarea>
                        <small class="text-muted">Use HTML e variáveis entre chaves {variavel}</small>
                    </div>
                    
                    <div class="mb-7">
                        <label class="fw-semibold fs-6 mb-2">Corpo do Email (Texto)</label>
                        <textarea name="corpo_texto" id="template_corpo_texto" class="form-control form-control-solid" rows="10"></textarea>
                        <small class="text-muted">Versão texto alternativo (opcional)</small>
                    </div>
                    
                    <div class="mb-7">
                        <div class="form-check form-check-custom form-check-solid">
                            <input class="form-check-input" type="checkbox" name="ativo" id="template_ativo" value="1" checked />
                            <label class="form-check-label" for="template_ativo">
                                Template Ativo
                            </label>
                        </div>
                    </div>
                    
                    <div class="text-center pt-5">
                        <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <span class="indicator-label">Salvar</span>
                            <span class="indicator-progress">Salvando...
                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!--end::Modal-->

<!--begin::Modal - Teste de Email-->
<div class="modal fade" id="kt_modal_test_email" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-500px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Testar Configuração de Email</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body">
                <form id="kt_test_email_form" method="POST">
                    <input type="hidden" name="action" value="test">
                    <div class="mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Email para Teste</label>
                        <input type="email" name="email_teste" id="email_teste" class="form-control form-control-solid" 
                               required placeholder="seu_email@exemplo.com" />
                        <small class="text-muted">Um email de teste será enviado para este endereço</small>
                    </div>
                    <div class="text-center pt-5">
                        <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <span class="indicator-label">Enviar Teste</span>
                            <span class="indicator-progress">Enviando...
                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!--end::Modal-->

<!--begin::TinyMCE Scripts-->
<script src="../assets/plugins/custom/tinymce/tinymce.bundle.js"></script>
<script>
// Define licença GPL globalmente antes de inicializar qualquer editor
if (typeof tinymce !== 'undefined') {
    // Configuração global de licença para uso open source
    window.tinymceLicenseKey = 'gpl';
}
</script>
<!--end::TinyMCE Scripts-->

<script>
"use strict";

// Mostra/oculta campos de autenticação
document.getElementById('smtp_auth').addEventListener('change', function() {
    const authFields = document.getElementById('auth_fields');
    const usernameField = document.getElementById('smtp_username');
    const passwordField = document.getElementById('smtp_password');
    
    if (this.checked) {
        authFields.style.display = 'block';
        usernameField.required = true;
        passwordField.required = true;
    } else {
        authFields.style.display = 'none';
        usernameField.required = false;
        passwordField.required = false;
    }
});

// Inicializa visibilidade dos campos
document.getElementById('smtp_auth').dispatchEvent(new Event('change'));

// Toggle senha
document.getElementById('toggle_password').addEventListener('click', function() {
    const passwordField = document.getElementById('smtp_password');
    const icon = this.querySelector('i');
    
    if (passwordField.type === 'password') {
        passwordField.type = 'text';
        icon.classList.remove('ki-eye');
        icon.classList.add('ki-eye-slash');
    } else {
        passwordField.type = 'password';
        icon.classList.remove('ki-eye-slash');
        icon.classList.add('ki-eye');
    }
});

// Botão de teste
document.getElementById('test_email_btn').addEventListener('click', function() {
    const modal = new bootstrap.Modal(document.getElementById('kt_modal_test_email'));
    modal.show();
});

// Validação do formulário
document.getElementById('kt_config_email_form').addEventListener('submit', function(e) {
    const smtpAuth = document.getElementById('smtp_auth').checked;
    const username = document.getElementById('smtp_username').value;
    const password = document.getElementById('smtp_password').value;
    
    if (smtpAuth && (!username || !password)) {
        e.preventDefault();
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                text: 'Usuário e senha são obrigatórios quando autenticação está ativada!',
                icon: 'error',
                buttonsStyling: false,
                confirmButtonText: 'Ok, entendi!',
                customClass: {
                    confirmButton: 'btn fw-bold btn-primary'
                }
            });
        } else {
            alert('Usuário e senha são obrigatórios quando autenticação está ativada!');
        }
        return false;
    }
    
    // Mostra loading
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.setAttribute('data-kt-indicator', 'on');
    submitBtn.disabled = true;
});

// Variável global para armazenar conteúdo do template antes de inicializar editor
let templateContentToLoad = '';

// Função para editar template
function editarTemplate(template) {
    document.getElementById('template_modal_title').textContent = 'Editar Template: ' + template.nome;
    document.getElementById('template_id').value = template.id;
    document.getElementById('template_codigo').value = template.codigo;
    document.getElementById('template_nome').value = template.nome || '';
    document.getElementById('template_descricao').value = template.descricao || '';
    document.getElementById('template_assunto').value = template.assunto || '';
    document.getElementById('template_corpo_texto').value = template.corpo_texto || '';
    document.getElementById('template_ativo').checked = template.ativo == 1;
    
    // Armazena conteúdo HTML para carregar no editor
    templateContentToLoad = template.corpo_html || '';
    document.getElementById('template_corpo_html').value = templateContentToLoad;
    
    const modal = new bootstrap.Modal(document.getElementById('kt_modal_template'));
    modal.show();
}

// Toggle ativo/inativo
document.querySelectorAll('.toggle-template').forEach(toggle => {
    toggle.addEventListener('change', function() {
        const id = this.getAttribute('data-id');
        const ativo = this.checked ? 1 : 0;
        
        fetch('configuracoes_email.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=toggle_template&id=' + id + '&ativo=' + ativo
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                this.checked = !this.checked;
                alert('Erro ao atualizar: ' + (data.message || 'Erro desconhecido'));
            } else {
                const label = this.nextElementSibling;
                label.textContent = ativo ? 'Ativo' : 'Inativo';
            }
        })
        .catch(() => {
            this.checked = !this.checked;
            alert('Erro ao atualizar template');
        });
    });
});

// Inicializa TinyMCE
function initTinyMCE() {
    // Verifica se TinyMCE está disponível
    if (typeof tinymce === 'undefined') {
        console.warn('TinyMCE não está carregado. Tentando novamente...');
        setTimeout(initTinyMCE, 200);
        return;
    }
    
    // Remove editor existente se houver
    const editorId = 'template_corpo_html';
    if (tinymce.get(editorId)) {
        tinymce.get(editorId).remove();
    }
    
    // Obtém conteúdo do textarea antes de inicializar
    const textarea = document.getElementById(editorId);
    const content = templateContentToLoad || (textarea ? textarea.value : '');
    
    // Configura base_url e suffix para usar os arquivos diretamente
    const baseUrl = '../assets/plugins/custom/tinymce';
    
    // Inicializa TinyMCE
    tinymce.init({
        selector: '#' + editorId,
        height: 400,
        menubar: false,
        base_url: baseUrl,
        suffix: '.min',
        license_key: 'gpl', // Usa licença GPL (open source) - remove restrições de licença
        plugins: [
            'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
            'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
            'insertdatetime', 'media', 'table', 'help', 'wordcount'
        ],
        toolbar: 'undo redo | blocks | ' +
            'bold italic forecolor | alignleft aligncenter ' +
            'alignright alignjustify | bullist numlist outdent indent | ' +
            'removeformat | help | code',
        content_style: 'body { font-family: Arial, sans-serif; font-size: 14px }',
        language: 'pt_BR',
        promotion: false,
        branding: false,
        skin: 'oxide',
        content_css: baseUrl + '/skins/ui/oxide/content.min.css',
        setup: function(editor) {
            editor.on('change', function() {
                editor.save();
            });
        },
        init_instance_callback: function(editor) {
            // Carrega conteúdo após inicialização
            if (content) {
                editor.setContent(content);
            }
            templateContentToLoad = ''; // Limpa após carregar
        }
    });
}

// Loading no formulário de template
document.getElementById('kt_template_form')?.addEventListener('submit', function(e) {
    // Salva conteúdo do TinyMCE antes de enviar
    if (typeof tinymce !== 'undefined' && tinymce.get('template_corpo_html')) {
        tinymce.get('template_corpo_html').save();
    }
    
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.setAttribute('data-kt-indicator', 'on');
    submitBtn.disabled = true;
});

// Inicializa TinyMCE quando o modal é aberto
document.getElementById('kt_modal_template')?.addEventListener('shown.bs.modal', function() {
    initTinyMCE();
});

// Remove TinyMCE quando o modal é fechado
document.getElementById('kt_modal_template')?.addEventListener('hidden.bs.modal', function() {
    if (typeof tinymce !== 'undefined' && tinymce.get('template_corpo_html')) {
        tinymce.get('template_corpo_html').remove();
    }
    templateContentToLoad = ''; // Limpa conteúdo
});

// Remove scroll desnecessário da tab de templates
document.addEventListener('DOMContentLoaded', function() {
    function removeScroll() {
        const tabTemplates = document.getElementById('kt_tab_templates');
        const cardBody = document.querySelector('.card-body.pt-0');
        const card = document.querySelector('.card');
        const tabContent = document.querySelector('.tab-content');
        
        if (tabTemplates) {
            tabTemplates.style.overflow = 'visible';
            tabTemplates.style.maxHeight = 'none';
            tabTemplates.style.height = 'auto';
        }
        
        if (cardBody) {
            cardBody.style.overflow = 'visible';
            cardBody.style.maxHeight = 'none';
            cardBody.style.height = 'auto';
        }
        
        if (card) {
            card.style.overflow = 'visible';
        }
        
        if (tabContent) {
            tabContent.style.overflow = 'visible';
        }
        
        // Remove scroll de todos os elementos dentro da tab
        if (tabTemplates) {
            const allElements = tabTemplates.querySelectorAll('*');
            allElements.forEach(el => {
                const computedStyle = window.getComputedStyle(el);
                if (computedStyle.overflowY === 'auto' || computedStyle.overflowY === 'scroll') {
                    el.style.overflowY = 'visible';
                }
                if (computedStyle.maxHeight && computedStyle.maxHeight !== 'none') {
                    el.style.maxHeight = 'none';
                }
            });
        }
    }
    
    removeScroll();
    
    // Remove scroll do elemento nav das tabs especificamente
    const navTabs = document.querySelector('.nav.nav-stretch.nav-line-tabs');
    if (navTabs) {
        navTabs.style.overflowY = 'visible';
        navTabs.style.maxHeight = 'none';
        navTabs.style.height = 'auto';
    }
    
    // Reaplica quando a tab é mostrada
    const tabLinks = document.querySelectorAll('[data-bs-toggle="tab"]');
    tabLinks.forEach(link => {
        link.addEventListener('shown.bs.tab', function() {
            setTimeout(removeScroll, 100);
            // Garante que o nav não tenha scroll vertical
            if (navTabs) {
                navTabs.style.overflowY = 'visible';
            }
        });
    });
    
    // Monitora mudanças no DOM para garantir que o scroll não volte
    const observer = new MutationObserver(function(mutations) {
        if (navTabs) {
            const style = window.getComputedStyle(navTabs);
            if (style.overflowY !== 'visible') {
                navTabs.style.overflowY = 'visible';
            }
        }
    });
    
    if (navTabs) {
        observer.observe(navTabs, {
            attributes: true,
            attributeFilter: ['style', 'class']
        });
    }
});
</script>

<style>
/* Remove scroll desnecessário nas tabs e elementos pais */
.card:has(#kt_tab_templates),
#kt_tab_templates,
#kt_tab_smtp,
.tab-content,
.card-body.pt-0,
#kt_content_container {
    overflow: visible !important;
    max-height: none !important;
    height: auto !important;
}

/* Remove scroll do elemento nav das tabs - sobrescreve o CSS do Metronic */
.nav.nav-stretch.nav-line-tabs,
.nav.nav-stretch.nav-line-tabs.nav-line-tabs-2x {
    overflow-y: visible !important;
    overflow-x: auto !important;
    max-height: none !important;
    scrollbar-width: none !important; /* Firefox */
    -ms-overflow-style: none !important; /* IE e Edge */
}

/* Remove scrollbar no Chrome/Safari */
.nav.nav-stretch.nav-line-tabs::-webkit-scrollbar,
.nav.nav-stretch.nav-line-tabs.nav-line-tabs-2x::-webkit-scrollbar {
    display: none !important;
    width: 0 !important;
    height: 0 !important;
}

/* Remove scroll de todos os elementos dentro da tab de templates */
#kt_tab_templates * {
    overflow-y: visible !important;
    max-height: none !important;
}

/* Ajusta apenas para scroll horizontal quando necessário na tabela */
#kt_tab_templates > div:first-child {
    overflow-x: auto;
    overflow-y: visible !important;
    max-height: none !important;
}

/* Remove scroll do card principal */
.card:has(.card-body.pt-0) {
    overflow: visible !important;
}

/* Sobrescreve o CSS do Metronic que adiciona scrollbar em ul */
@media (min-width: 992px) {
    .nav.nav-stretch.nav-line-tabs,
    .nav.nav-stretch.nav-line-tabs.nav-line-tabs-2x,
    .card-header ul,
    .card-header .nav {
        scrollbar-width: none !important;
        scrollbar-color: transparent transparent !important;
        overflow-y: visible !important;
    }
    
    .nav.nav-stretch.nav-line-tabs::-webkit-scrollbar,
    .nav.nav-stretch.nav-line-tabs.nav-line-tabs-2x::-webkit-scrollbar,
    .card-header ul::-webkit-scrollbar,
    .card-header .nav::-webkit-scrollbar {
        display: none !important;
        width: 0 !important;
        height: 0 !important;
    }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

