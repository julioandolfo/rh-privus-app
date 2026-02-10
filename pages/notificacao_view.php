<?php
/**
 * Visualização de Notificação Push
 * Página para exibir detalhes completos de uma notificação
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notificacoes.php';

// Verifica se tem token de autenticação automática
$token = $_GET['token'] ?? null;
$notificacao_id = $_GET['id'] ?? null;

if ($token && $notificacao_id) {
    // Busca notificação pelo token
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT n.*, np.token, np.expira_em
        FROM notificacoes_push np
        INNER JOIN notificacoes_sistema n ON np.notificacao_id = n.id
        WHERE np.token = ? AND np.notificacao_id = ? AND np.expira_em > NOW()
    ");
    $stmt->execute([$token, $notificacao_id]);
    $notificacao_token = $stmt->fetch();
    
    if ($notificacao_token) {
        // Token válido - faz login automático
        session_start();
        
        // Busca dados do usuário/colaborador
        if ($notificacao_token['usuario_id']) {
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
            $stmt->execute([$notificacao_token['usuario_id']]);
            $usuario = $stmt->fetch();
            
            if ($usuario) {
                // Login automático
                $_SESSION['usuario'] = $usuario;
                $_SESSION['logado'] = true;
                
                // Marca notificação como lida
                marcar_notificacao_lida($notificacao_id, $usuario['id'], null);
            }
        } elseif ($notificacao_token['colaborador_id']) {
            // Busca usuário do colaborador
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE colaborador_id = ?");
            $stmt->execute([$notificacao_token['colaborador_id']]);
            $usuario = $stmt->fetch();
            
            if ($usuario) {
                // Login automático
                $_SESSION['usuario'] = $usuario;
                $_SESSION['logado'] = true;
                
                // Marca notificação como lida
                marcar_notificacao_lida($notificacao_id, $usuario['id'], $notificacao_token['colaborador_id']);
            }
        }
    }
}

// Agora verifica autenticação normal
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

$pdo = getDB();
$usuario = $_SESSION['usuario'];
$notificacao_id = $_GET['id'] ?? 0;

if (empty($notificacao_id)) {
    redirect('notificacoes.php', 'Notificação não encontrada!', 'error');
}

// Busca detalhes da notificação
$stmt = $pdo->prepare("
    SELECT n.*,
           u.nome as usuario_nome,
           c.nome_completo as colaborador_nome
    FROM notificacoes_sistema n
    LEFT JOIN usuarios u ON n.usuario_id = u.id
    LEFT JOIN colaboradores c ON n.colaborador_id = c.id
    WHERE n.id = ?
");
$stmt->execute([$notificacao_id]);
$notificacao = $stmt->fetch();

if (!$notificacao) {
    redirect('notificacoes.php', 'Notificação não encontrada!', 'error');
}

// Verifica se a notificação pertence ao usuário
$pertence = false;
if ($usuario['id'] && $notificacao['usuario_id'] == $usuario['id']) {
    $pertence = true;
} elseif (!empty($usuario['colaborador_id']) && $notificacao['colaborador_id'] == $usuario['colaborador_id']) {
    $pertence = true;
}

if (!$pertence && $usuario['role'] !== 'ADMIN') {
    redirect('notificacoes.php', 'Acesso negado!', 'error');
}

// Marca como lida
if (!$notificacao['lida']) {
    marcar_notificacao_lida($notificacao_id, $usuario['id'], $usuario['colaborador_id'] ?? null);
}

// Ícones por tipo de notificação
$icones = [
    'promocao' => '<i class="ki-duotone ki-medal-star fs-3x text-success"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span></i>',
    'ocorrencia' => '<i class="ki-duotone ki-information-5 fs-3x text-warning"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>',
    'fechamento_pagamento' => '<i class="ki-duotone ki-dollar fs-3x text-primary"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>',
    'horas_extras' => '<i class="ki-duotone ki-time fs-3x text-info"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>',
    'evento' => '<i class="ki-duotone ki-calendar-8 fs-3x text-primary"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span><span class="path6"></span></i>',
    'comunicado' => '<i class="ki-duotone ki-notification-bing fs-3x text-info"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>',
    'curtida' => '<i class="ki-duotone ki-heart fs-3x text-danger"><span class="path1"></span><span class="path2"></span></i>',
    'comentario' => '<i class="ki-duotone ki-message-text-2 fs-3x text-primary"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>',
];

$icone = $icones[$notificacao['tipo']] ?? '<i class="ki-duotone ki-notification-bing fs-3x text-primary"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>';

$page_title = 'Detalhes da Notificação';
require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Detalhes da Notificação</h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-muted">
                    <a href="notificacoes.php" class="text-muted text-hover-primary">Notificações</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Detalhes</li>
            </ul>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <?= get_session_alert() ?>
        
        <!--begin::Card-->
        <div class="card">
            <!--begin::Card body-->
            <div class="card-body p-lg-10">
                
                <!--begin::Layout-->
                <div class="d-flex flex-column flex-xl-row">
                    
                    <!--begin::Sidebar-->
                    <div class="flex-column flex-lg-row-auto w-100 w-xl-300px mb-10 mb-xl-0 me-xl-10">
                        
                        <!--begin::Card-->
                        <div class="card card-flush">
                            <!--begin::Card body-->
                            <div class="card-body text-center pt-10 pb-10">
                                <!--begin::Icon-->
                                <div class="mb-7">
                                    <?= $icone ?>
                                </div>
                                <!--end::Icon-->
                                
                                <!--begin::Title-->
                                <h3 class="fs-4 text-gray-800 fw-bold mb-3"><?= htmlspecialchars($notificacao['tipo']) ?></h3>
                                <!--end::Title-->
                                
                                <!--begin::Separator-->
                                <div class="separator separator-dashed my-5"></div>
                                <!--end::Separator-->
                                
                                <!--begin::Details-->
                                <div class="text-start">
                                    <div class="mb-5">
                                        <span class="fw-semibold text-gray-600 d-block fs-7 mb-1">Data/Hora:</span>
                                        <span class="fw-bold text-gray-800 fs-6">
                                            <?= date('d/m/Y H:i', strtotime($notificacao['created_at'])) ?>
                                        </span>
                                    </div>
                                    
                                    <?php if ($notificacao['referencia_tipo']): ?>
                                    <div class="mb-5">
                                        <span class="fw-semibold text-gray-600 d-block fs-7 mb-1">Tipo de Referência:</span>
                                        <span class="fw-bold text-gray-800 fs-6">
                                            <?= htmlspecialchars($notificacao['referencia_tipo']) ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($notificacao['referencia_id']): ?>
                                    <div class="mb-5">
                                        <span class="fw-semibold text-gray-600 d-block fs-7 mb-1">ID da Referência:</span>
                                        <span class="fw-bold text-gray-800 fs-6">
                                            #<?= htmlspecialchars($notificacao['referencia_id']) ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <!--end::Details-->
                                
                                <!--begin::Separator-->
                                <div class="separator separator-dashed my-5"></div>
                                <!--end::Separator-->
                                
                                <!--begin::Actions-->
                                <?php if ($notificacao['link']): ?>
                                <a href="<?= htmlspecialchars($notificacao['link']) ?>" class="btn btn-primary w-100 mb-3">
                                    <i class="ki-duotone ki-arrow-right fs-3"><span class="path1"></span><span class="path2"></span></i>
                                    Ir para Item
                                </a>
                                <?php endif; ?>
                                
                                <a href="notificacoes.php" class="btn btn-light-primary w-100">
                                    <i class="ki-duotone ki-arrow-left fs-3"><span class="path1"></span><span class="path2"></span></i>
                                    Voltar
                                </a>
                                <!--end::Actions-->
                            </div>
                            <!--end::Card body-->
                        </div>
                        <!--end::Card-->
                        
                    </div>
                    <!--end::Sidebar-->
                    
                    <!--begin::Content-->
                    <div class="flex-lg-row-fluid">
                        
                        <!--begin::Order details-->
                        <div class="card card-flush py-4">
                            <!--begin::Card header-->
                            <div class="card-header">
                                <div class="card-title">
                                    <h2><?= htmlspecialchars($notificacao['titulo']) ?></h2>
                                </div>
                            </div>
                            <!--end::Card header-->
                            
                            <!--begin::Card body-->
                            <div class="card-body pt-0">
                                <div class="fs-5 text-gray-800">
                                    <?= nl2br(htmlspecialchars($notificacao['mensagem'])) ?>
                                </div>
                                
                                <?php if ($notificacao['link']): ?>
                                <div class="mt-10">
                                    <a href="<?= htmlspecialchars($notificacao['link']) ?>" class="btn btn-primary">
                                        <i class="ki-duotone ki-arrow-right fs-3"><span class="path1"></span><span class="path2"></span></i>
                                        Ver Detalhes Completos
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                            <!--end::Card body-->
                        </div>
                        <!--end::Order details-->
                        
                    </div>
                    <!--end::Content-->
                    
                </div>
                <!--end::Layout-->
                
            </div>
            <!--end::Card body-->
        </div>
        <!--end::Card-->
        
    </div>
</div>
<!--end::Post-->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
