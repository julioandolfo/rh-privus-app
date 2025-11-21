<?php
/**
 * Enviar Notificação Push - Lista usuários e permite enviar notificações
 */

// Headers anti-cache para evitar problemas de cache do navegador
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Mon, 01 Jan 1990 00:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/push_notifications.php';

if (!function_exists('log_push_debug')) {
    function log_push_debug($message)
    {
        $timestamp = date('Y-m-d H:i:s');
        $formatted = "[{$timestamp}] {$message}";
        error_log($formatted);
        
        $projectRoot = dirname(__DIR__);
        $logDir = $projectRoot . '/logs';
        
        if (!is_dir($logDir)) {
            $created = @mkdir($logDir, 0775, true);
            if (!$created && !is_dir($logDir)) {
                error_log("[LOG_PUSH_DEBUG] Falha ao criar diretório de logs em: {$logDir}");
                return;
            }
        }
        
        $logFile = $logDir . '/enviar_notificacao_push.log';
        $result = @file_put_contents($logFile, $formatted . PHP_EOL, FILE_APPEND | LOCK_EX);
        if ($result === false) {
            error_log("[LOG_PUSH_DEBUG] Falha ao escrever no arquivo de log: {$logFile}");
        }
    }
}

// Inicia sessão se não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_login();

// Apenas ADMIN e RH podem acessar
if (!in_array($_SESSION['usuario']['role'], ['ADMIN', 'RH'])) {
    redirect('dashboard.php', 'Você não tem permissão para acessar esta página.', 'error');
}

$pdo = getDB();
$usuario = $_SESSION['usuario'];
$success = $_GET['success'] ?? '';
$error = '';

// Verifica se a tabela onesignal_subscriptions existe
try {
    $pdo->query("SELECT 1 FROM onesignal_subscriptions LIMIT 1");
} catch (PDOException $e) {
    $error = 'Tabela onesignal_subscriptions não encontrada. Execute a migração primeiro.';
    $subscriptions = [];
    $total_usuarios = 0;
}

// Processa envio de notificação
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_notificacao'])) {
    // Configura tratamento de erros para capturar erros fatais
    set_error_handler(function($errno, $errstr, $errfile, $errline) {
        log_push_debug("ERRO FATAL PHP: [{$errno}] {$errstr} em {$errfile}:{$errline}");
        return false; // Continua execução normal
    });
    
    register_shutdown_function(function() {
        $error = error_get_last();
        if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            log_push_debug("SHUTDOWN ERROR: " . print_r($error, true));
        }
    });
    
    log_push_debug('=== INÍCIO PROCESSAMENTO NOTIFICAÇÃO ===');
    log_push_debug('POST data: ' . print_r($_POST, true));
    
    try {
        // Verifica se as funções necessárias existem
        if (!function_exists('enviar_push_usuario')) {
            throw new Exception('Função enviar_push_usuario não encontrada. Verifique se includes/push_notifications.php está sendo carregado.');
        }
        
        if (!function_exists('enviar_push_colaborador')) {
            throw new Exception('Função enviar_push_colaborador não encontrada. Verifique se includes/push_notifications.php está sendo carregado.');
        }
        
        $usuario_id = !empty($_POST['usuario_id']) ? intval($_POST['usuario_id']) : null;
        $colaborador_id = !empty($_POST['colaborador_id']) ? intval($_POST['colaborador_id']) : null;
        $titulo = trim($_POST['titulo'] ?? '');
        $mensagem = trim($_POST['mensagem'] ?? '');
        $url = trim($_POST['url'] ?? '');
        
        log_push_debug("Valores processados - usuario_id: {$usuario_id}, colaborador_id: {$colaborador_id}, titulo: {$titulo}, mensagem: " . substr($mensagem, 0, 50));
        
        if (empty($titulo) || empty($mensagem)) {
            $error = 'Título e mensagem são obrigatórios!';
            log_push_debug('ERRO: Título ou mensagem vazios');
        } elseif (!$usuario_id && !$colaborador_id) {
            $error = 'Selecione um destinatário!';
            log_push_debug('ERRO: Nenhum destinatário selecionado');
        } else {
            log_push_debug('Iniciando envio de notificação...');
            
            if ($colaborador_id) {
                log_push_debug("Enviando para colaborador_id: {$colaborador_id}");
                $resultado = enviar_push_colaborador($colaborador_id, $titulo, $mensagem, $url ?: null);
            } else {
                log_push_debug("Enviando para usuario_id: {$usuario_id}");
                $resultado = enviar_push_usuario($usuario_id, $titulo, $mensagem, $url ?: null);
            }
            
            log_push_debug('Resultado do envio: ' . print_r($resultado, true));
            
            if (isset($resultado['success']) && $resultado['success']) {
                $success = "Notificação enviada com sucesso para {$resultado['enviadas']} dispositivo(s)!";
                log_push_debug("SUCESSO: {$success}");
                // Recarrega a página após 1 segundo para atualizar a lista
                header('Location: ' . $_SERVER['PHP_SELF'] . '?success=' . urlencode($success));
                exit;
            } else {
                $error = $resultado['message'] ?? 'Erro desconhecido ao enviar notificação';
                log_push_debug("ERRO no resultado: {$error}");
            }
        }
    } catch (Throwable $e) {
        $error = 'Erro ao enviar notificação: ' . $e->getMessage();
        log_push_debug('EXCEÇÃO ao enviar push notification: ' . $e->getMessage());
        log_push_debug('Arquivo: ' . $e->getFile() . ':' . $e->getLine());
        log_push_debug('Stack trace: ' . $e->getTraceAsString());
        
        // Se for um erro fatal, mostra mais detalhes
        if ($e instanceof Error) {
            log_push_debug('ERRO FATAL: ' . get_class($e));
        }
    } finally {
        restore_error_handler();
    }
    
    log_push_debug('=== FIM PROCESSAMENTO NOTIFICAÇÃO ===');
}

// Busca subscriptions com informações dos usuários
$filtro_nome = $_GET['filtro_nome'] ?? '';
$filtro_email = $_GET['filtro_email'] ?? '';
$filtro_role = $_GET['filtro_role'] ?? '';

$where = [];
$params = [];

if ($filtro_nome) {
    $where[] = "(u.nome LIKE ? OR c.nome_completo LIKE ?)";
    $params[] = "%{$filtro_nome}%";
    $params[] = "%{$filtro_nome}%";
}

if ($filtro_email) {
    $where[] = "u.email LIKE ?";
    $params[] = "%{$filtro_email}%";
}

if ($filtro_role) {
    $where[] = "u.role = ?";
    $params[] = $filtro_role;
}

$whereClause = !empty($where) ? 'AND ' . implode(' AND ', $where) : '';

// Inicializa variáveis
$subscriptions = [];
$total_usuarios = 0;

// Só executa queries se não houver erro anterior
if (empty($error)) {
    // Query reescrita para compatibilidade com sql_mode=only_full_group_by
    // Usa subquery para garantir que todos os campos estejam agregados corretamente
    $sql = "
        SELECT 
            usuario_id,
            colaborador_id,
            GROUP_CONCAT(DISTINCT player_id SEPARATOR ',') as player_ids,
            GROUP_CONCAT(DISTINCT device_type SEPARATOR ',') as device_types,
            MAX(created_at) as created_at,
            MAX(usuario_nome) as usuario_nome,
            MAX(usuario_email) as usuario_email,
            MAX(usuario_role) as usuario_role,
            MAX(colaborador_nome) as colaborador_nome,
            COUNT(*) as total_dispositivos
        FROM (
            SELECT 
                COALESCE(os.usuario_id, 0) as usuario_id,
                COALESCE(os.colaborador_id, 0) as colaborador_id,
                os.player_id,
                os.device_type,
                os.created_at,
                u.nome as usuario_nome,
                u.email as usuario_email,
                u.role as usuario_role,
                c.nome_completo as colaborador_nome
            FROM onesignal_subscriptions os
            LEFT JOIN usuarios u ON os.usuario_id = u.id
            LEFT JOIN colaboradores c ON os.colaborador_id = c.id
            WHERE 1=1 {$whereClause}
        ) as subquery
        GROUP BY usuario_id, colaborador_id
        ORDER BY MAX(created_at) DESC
    ";

    try {
        // Log da query para debug (apenas em desenvolvimento)
        if (defined('DEBUG') && DEBUG) {
            log_push_debug('Query SQL: ' . $sql);
            log_push_debug('Parâmetros: ' . print_r($params, true));
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Converte 0 para NULL nos IDs para manter compatibilidade
        foreach ($subscriptions as &$sub) {
            if ($sub['usuario_id'] == 0) {
                $sub['usuario_id'] = null;
            }
            if ($sub['colaborador_id'] == 0) {
                $sub['colaborador_id'] = null;
            }
        }
        unset($sub);
        
    } catch (PDOException $e) {
        log_push_debug('Erro na query de subscriptions: ' . $e->getMessage());
        log_push_debug('SQL: ' . $sql);
        log_push_debug('Params: ' . print_r($params, true));
        $subscriptions = [];
        $error = 'Erro ao carregar lista de usuários: ' . $e->getMessage();
    }
}

// Conta total de usuários únicos (só se não houver erro)
if (empty($error)) {
    try {
        $stmt_count = $pdo->query("
            SELECT COUNT(DISTINCT CONCAT(COALESCE(usuario_id, 0), '-', COALESCE(colaborador_id, 0))) as total 
            FROM onesignal_subscriptions
        ");
        $total_usuarios = $stmt_count->fetch()['total'] ?? 0;
    } catch (PDOException $e) {
        log_push_debug('Erro ao contar subscriptions: ' . $e->getMessage());
        $total_usuarios = 0;
    }
}

$page_title = 'Enviar Notificação Push';

// Cache busting - versão baseada na última modificação deste arquivo
$page_version = filemtime(__FILE__);

include __DIR__ . '/../includes/header.php';
?>
<!-- Versão da página: <?= $page_version ?> -->
<!-- Force reload: <?= time() ?> -->

<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">

<!--begin::Content-->
<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    <!--begin::Container-->
    <div id="kt_content_container" class="container-xxl">
        <?= get_session_alert(); ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success d-flex align-items-center p-5 mb-10">
                <i class="ki-duotone ki-check-circle fs-2hx text-success me-4">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <div class="d-flex flex-column">
                    <h4 class="mb-1 text-success">Sucesso!</h4>
                    <span><?= htmlspecialchars($success) ?></span>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger d-flex align-items-center p-5 mb-10">
                <i class="ki-duotone ki-information-5 fs-2hx text-danger me-4">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                </i>
                <div class="d-flex flex-column">
                    <h4 class="mb-1 text-danger">Erro!</h4>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            </div>
        <?php endif; ?>
        
        <!--begin::Card-->
        <div class="card">
            <!--begin::Card header-->
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <div class="d-flex align-items-center position-relative my-1">
                        <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <input type="text" id="filtro_nome" class="form-control form-control-solid w-250px ps-13" placeholder="Buscar por nome..." value="<?= htmlspecialchars($filtro_nome) ?>">
                    </div>
                </div>
                <div class="card-toolbar">
                    <div class="d-flex justify-content-end" data-kt-subscription-table-toolbar="base">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal_enviar_notificacao">
                            <i class="ki-duotone ki-notification-status fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                            Enviar Notificação
                        </button>
                    </div>
                </div>
            </div>
            <!--end::Card header-->
            
            <!--begin::Card body-->
            <div class="card-body py-4">
                <!--begin::Filtros-->
                <form method="GET" class="mb-5">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Nome</label>
                            <input type="text" name="filtro_nome" class="form-control" placeholder="Nome do usuário..." value="<?= htmlspecialchars($filtro_nome) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Email</label>
                            <input type="text" name="filtro_email" class="form-control" placeholder="Email..." value="<?= htmlspecialchars($filtro_email) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Perfil</label>
                            <select name="filtro_role" class="form-select">
                                <option value="">Todos</option>
                                <option value="ADMIN" <?= $filtro_role === 'ADMIN' ? 'selected' : '' ?>>Admin</option>
                                <option value="RH" <?= $filtro_role === 'RH' ? 'selected' : '' ?>>RH</option>
                                <option value="GESTOR" <?= $filtro_role === 'GESTOR' ? 'selected' : '' ?>>Gestor</option>
                                <option value="COLABORADOR" <?= $filtro_role === 'COLABORADOR' ? 'selected' : '' ?>>Colaborador</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <button type="submit" class="btn btn-primary">Filtrar</button>
                            <a href="enviar_notificacao_push.php" class="btn btn-secondary">Limpar</a>
                        </div>
                    </div>
                </form>
                <!--end::Filtros-->
                
                <!--begin::Estatísticas-->
                <div class="row g-3 mb-5">
                    <div class="col-md-4">
                        <div class="card bg-light-primary">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <span class="text-muted fw-semibold d-block">Total de Usuários</span>
                                        <span class="text-gray-800 fw-bold fs-2"><?= count($subscriptions) ?></span>
                                    </div>
                                    <i class="ki-duotone ki-profile-user fs-1 text-primary">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-light-success">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <span class="text-muted fw-semibold d-block">Dispositivos Registrados</span>
                                        <span class="text-gray-800 fw-bold fs-2"><?= count($subscriptions) ?></span>
                                    </div>
                                    <i class="ki-duotone ki-devices fs-1 text-success">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-light-info">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <span class="text-muted fw-semibold d-block">Último Registro</span>
                                        <span class="text-gray-800 fw-bold fs-6">
                                            <?php if (!empty($subscriptions)): ?>
                                                <?= date('d/m/Y H:i', strtotime($subscriptions[0]['created_at'])) ?>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <i class="ki-duotone ki-calendar fs-1 text-info">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!--end::Estatísticas-->
                
                <!--begin::Table-->
                <div class="table-responsive">
                    <table id="kt_notificacoes_table" class="table table-row-bordered table-row-dashed gy-4 align-middle">
                        <thead>
                            <tr class="fw-bold fs-6 text-gray-800">
                                <th>Usuário</th>
                                <th>Email</th>
                                <th>Perfil</th>
                                <th>Dispositivo</th>
                                <th>Player ID</th>
                                <th>Registrado em</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($subscriptions)): ?>
                                <?php foreach ($subscriptions as $sub): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="symbol symbol-45px me-5">
                                                    <div class="symbol-label fs-3 fw-semibold bg-primary text-white">
                                                        <?= strtoupper(substr(($sub['usuario_nome'] ?? '') ?: ($sub['colaborador_nome'] ?? '') ?: 'U', 0, 1)) ?>
                                                    </div>
                                                </div>
                                                <div class="d-flex flex-column">
                                                    <span class="text-gray-800 fw-bold"><?= htmlspecialchars(($sub['usuario_nome'] ?? '') ?: ($sub['colaborador_nome'] ?? '') ?: 'N/A') ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($sub['usuario_email'] ?? '-') ?></td>
                                        <td>
                                            <span class="badge badge-<?= ($sub['usuario_role'] ?? '') === 'ADMIN' ? 'danger' : (($sub['usuario_role'] ?? '') === 'RH' ? 'primary' : 'success') ?>">
                                                <?= htmlspecialchars($sub['usuario_role'] ?? 'Colaborador') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $deviceTypesStr = $sub['device_types'] ?? 'web';
                                            $deviceTypes = !empty($deviceTypesStr) ? explode(',', $deviceTypesStr) : ['web'];
                                            $uniqueDevices = array_unique(array_filter(array_map('trim', $deviceTypes)));
                                            if (empty($uniqueDevices)) {
                                                $uniqueDevices = ['web'];
                                            }
                                            foreach ($uniqueDevices as $dt): 
                                            ?>
                                                <span class="badge badge-info me-1">
                                                    <?= ucfirst($dt ?: 'web') ?>
                                                </span>
                                            <?php endforeach; ?>
                                            <span class="text-muted">(<?= intval($sub['total_dispositivos'] ?? 0) ?>)</span>
                                        </td>
                                        <td>
                                            <span class="text-muted" style="font-size: 11px;" title="<?= htmlspecialchars($sub['player_ids'] ?? '') ?>">
                                                <?= intval($sub['total_dispositivos'] ?? 0) ?> dispositivo(s)
                                            </span>
                                        </td>
                                        <td><?= !empty($sub['created_at']) ? date('d/m/Y H:i', strtotime($sub['created_at'])) : '-' ?></td>
                                        <td class="text-end">
                                            <?php 
                                            $usuario_id_val = (!empty($sub['usuario_id']) && $sub['usuario_id'] != 0) ? intval($sub['usuario_id']) : 'null';
                                            $colaborador_id_val = (!empty($sub['colaborador_id']) && $sub['colaborador_id'] != 0) ? intval($sub['colaborador_id']) : 'null';
                                            ?>
                                            <button type="button" class="btn btn-sm btn-primary" 
                                                    onclick="enviarParaUsuario(<?= $usuario_id_val ?>, <?= $colaborador_id_val ?>, '<?= htmlspecialchars(addslashes(($sub['usuario_nome'] ?? '') ?: ($sub['colaborador_nome'] ?? '') ?: 'Usuário')) ?>')">
                                                <i class="ki-duotone ki-notification-status fs-5">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                    <span class="path3"></span>
                                                </i>
                                                Enviar
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-10">
                                        <i class="ki-duotone ki-information-5 fs-3x mb-3">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                        </i>
                                        <div>Nenhum usuário com dispositivo registrado encontrado.</div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <!--end::Table-->
            </div>
            <!--end::Card body-->
        </div>
        <!--end::Card-->
    </div>
    <!--end::Container-->
</div>
<!--end::Content-->

<!--begin::Modal Enviar Notificação-->
<div class="modal fade" id="modal_enviar_notificacao" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Enviar Notificação Push</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form method="POST" id="form_enviar_notificacao" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <input type="hidden" name="enviar_notificacao" value="1">
                    <input type="hidden" name="usuario_id" id="modal_usuario_id" value="">
                    <input type="hidden" name="colaborador_id" id="modal_colaborador_id" value="">
                    
                    <div class="mb-5">
                        <label class="form-label required">Destinatário</label>
                        <input type="text" class="form-control" id="modal_destinatario" readonly placeholder="Selecione um usuário da tabela ou preencha manualmente">
                        <div class="form-text">Deixe vazio para enviar para o usuário selecionado na tabela</div>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label required">Título</label>
                        <input type="text" name="titulo" class="form-control" placeholder="Ex: Nova Mensagem" required>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label required">Mensagem</label>
                        <textarea name="mensagem" class="form-control" rows="4" placeholder="Digite a mensagem da notificação..." required></textarea>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label">URL (opcional)</label>
                        <input type="text" name="url" class="form-control" placeholder="Ex: /rh/pages/dashboard.php">
                        <div class="form-text">URL para abrir quando o usuário clicar na notificação</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ki-duotone ki-notification-status fs-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        Enviar Notificação
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!--end::Modal Enviar Notificação-->


<script data-version="<?= $page_version ?>" data-timestamp="<?= time() ?>">
// VERSÃO DA PÁGINA: <?= $page_version ?> - TIMESTAMP: <?= time() ?> 
console.log('[DEBUG_PUSH] Script da página iniciado (posicionado ANTES do footer)');
console.log('[DEBUG_PUSH] Versão da página:', '<?= $page_version ?>', 'Timestamp:', '<?= time() ?>');

// Monitor de erros global para esta página
window.addEventListener('error', function(e) {
    console.error('[DEBUG_PUSH] Erro global capturado:', e.message, 'em', e.filename, 'linha', e.lineno);
});

// Define a função globalmente IMEDIATAMENTE
window.enviarParaUsuario = function(usuarioId, colaboradorId, nome) {
    console.log('[DEBUG_PUSH] Clique em enviarParaUsuario detectado', { usuarioId, colaboradorId, nome });
    
    // Se a implementação real ainda não carregou
    if (typeof window.appEnviarParaUsuario === 'function') {
        window.appEnviarParaUsuario(usuarioId, colaboradorId, nome);
    } else {
        console.warn('[DEBUG_PUSH] Função appEnviarParaUsuario ainda não está pronta');
        alert('Aguarde um momento, o sistema está carregando os componentes...');
        
        // Tenta novamente em breve (uma única vez)
        setTimeout(function() {
            if (typeof window.appEnviarParaUsuario === 'function') {
                window.appEnviarParaUsuario(usuarioId, colaboradorId, nome);
            }
        }, 1000);
    }
};

(function() {
    'use strict';
    
    console.log('[DEBUG_PUSH] Função auto-executável iniciada');
    
    function waitForResources(resources, callback) {
        console.log('[DEBUG_PUSH] Iniciando espera por recursos:', resources);
        
        var attempts = 0;
        var maxAttempts = 100; // 10 segundos
        
        var interval = setInterval(function() {
            attempts++;
            var allReady = true;
            var debugStatus = {};
            
            // Verifica jQuery
            if (typeof window.jQuery === 'undefined') {
                allReady = false;
                debugStatus.jQuery = 'missing';
            } else {
                debugStatus.jQuery = 'OK (' + window.jQuery.fn.jquery + ')';
            }
            
            // Verifica DataTable se solicitado
            if (resources.includes('DataTable')) {
                if (typeof window.jQuery === 'undefined' || typeof window.jQuery.fn.DataTable === 'undefined') {
                    allReady = false;
                    debugStatus.DataTable = 'missing';
                } else {
                    debugStatus.DataTable = 'OK';
                }
            }
            
            // Log a cada 5 tentativas ou quando mudar algo importante
            if (attempts === 1 || attempts % 10 === 0 || allReady) {
                console.log('[DEBUG_PUSH] Tentativa ' + attempts + ' status:', debugStatus);
            }
            
            if (allReady) {
                console.log('[DEBUG_PUSH] Todos os recursos carregados após ' + attempts + ' tentativas');
                clearInterval(interval);
                try {
                    callback(window.jQuery);
                } catch (e) {
                    console.error('[DEBUG_PUSH] Erro ao executar callback principal:', e);
                }
            } else if (attempts >= maxAttempts) {
                console.error('[DEBUG_PUSH] Timeout aguardando recursos. Status final:', debugStatus);
                clearInterval(interval);
                alert('Erro ao carregar componentes da página (Timeout). Verifique o console.');
            }
        }, 100);
    }
    
    // Aguarda jQuery E DataTable
    waitForResources(['DataTable'], function($) {
        console.log('[DEBUG_PUSH] Callback de inicialização executando');
        
        // Implementação real
        window.appEnviarParaUsuario = function(usuarioId, colaboradorId, nome) {
            console.log('[DEBUG_PUSH] Abrindo modal para', nome);
            $('#modal_usuario_id').val(usuarioId || '');
            $('#modal_colaborador_id').val(colaboradorId || '');
            $('#modal_destinatario').val(nome || '');
            
            var modalEl = document.getElementById('modal_enviar_notificacao');
            
            // Tenta abrir usando Bootstrap 5 API
            try {
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    var modal = bootstrap.Modal.getInstance(modalEl);
                    if (!modal) {
                        modal = new bootstrap.Modal(modalEl);
                    }
                    modal.show();
                } else {
                    console.log('[DEBUG_PUSH] Bootstrap global não encontrado, tentando via jQuery');
                    $(modalEl).modal('show');
                }
            } catch (e) {
                console.error('[DEBUG_PUSH] Erro ao abrir modal:', e);
                // Fallback desesperado
                $(modalEl).show(); 
                $(modalEl).addClass('show');
                $('body').addClass('modal-open');
                $('body').append('<div class="modal-backdrop fade show"></div>');
            }
        };
        
        // Limpa formulário quando modal fecha
        $('#modal_enviar_notificacao').on('hidden.bs.modal', function() {
            $('#form_enviar_notificacao')[0].reset();
            $('#modal_usuario_id').val('');
            $('#modal_colaborador_id').val('');
            $('#modal_destinatario').val('');
            
            const submitBtn = $('#form_enviar_notificacao button[type="submit"]');
            submitBtn.prop('disabled', false);
            submitBtn.html(submitBtn.html().replace('<span class="spinner-border spinner-border-sm me-2"></span>Enviando...', 'Enviar Notificação').replace('Enviando...', 'Enviar Notificação'));
        });
        
        // Handler de submit
        $('#form_enviar_notificacao').on('submit', function(e) {
            console.log('[DEBUG_PUSH] Submit do formulário iniciado');
            // ... lógica de submit mantém a mesma ...
            const titulo = $(this).find('input[name="titulo"]').val().trim();
            const mensagem = $(this).find('textarea[name="mensagem"]').val().trim();
            const usuarioId = $(this).find('input[name="usuario_id"]').val().trim();
            const colaboradorId = $(this).find('input[name="colaborador_id"]').val().trim();
            
            if (!titulo || !mensagem) {
                e.preventDefault();
                alert('Por favor, preencha o título e a mensagem!');
                return false;
            }
            
            if (!usuarioId && !colaboradorId) {
                e.preventDefault();
                alert('Por favor, selecione um destinatário da tabela!');
                return false;
            }
            
            const submitBtn = $(this).find('button[type="submit"]');
            const originalHtml = submitBtn.html();
            submitBtn.prop('disabled', true);
            submitBtn.html('<span class="spinner-border spinner-border-sm me-2"></span>Enviando...');
            
            setTimeout(function() {
                if (submitBtn.prop('disabled')) {
                    submitBtn.prop('disabled', false);
                    submitBtn.html(originalHtml);
                }
            }, 30000);
            
            return true;
        });
        
        // Inicializa DataTable
        function initDataTable() {
            console.log('[DEBUG_PUSH] Iniciando setup do DataTable');
            const tableEl = $('#kt_notificacoes_table');
            
            if (!tableEl.length) {
                console.warn('[DEBUG_PUSH] Tabela não encontrada no DOM');
                return;
            }
            
            if ($.fn.DataTable.isDataTable(tableEl)) {
                console.log('[DEBUG_PUSH] DataTable já estava inicializado');
                return;
            }
            
            // Verifica se há pelo menos uma linha de dados (não conta o "Nenhum registro" com colspan)
            const tbody = tableEl.find('tbody');
            const dataRows = tbody.find('tr').filter(function() {
                return $(this).find('td[colspan]').length === 0;
            });
            
            if (dataRows.length === 0) {
                console.log('[DEBUG_PUSH] Tabela vazia - DataTable não será inicializado');
                return;
            }
            
            try {
                console.log('[DEBUG_PUSH] Configurando DataTable...');
                tableEl.DataTable({
                    language: {
                        url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json'
                    },
                    pageLength: 25,
                    order: [[5, 'desc']],
                    responsive: true,
                    columnDefs: [
                        { orderable: false, targets: 6 }
                    ],
                    dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>'
                });
                console.log('[DEBUG_PUSH] DataTable inicializado com SUCESSO');
            } catch (e) {
                console.error('[DEBUG_PUSH] EXCEÇÃO ao inicializar DataTable:', e);
                console.error('[DEBUG_PUSH] Stack:', e.stack);
            }
        }
        
        // Tenta inicializar
        initDataTable();
    });
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>


