<?php
/**
 * Enviar Notificação Push - Lista usuários e permite enviar notificações
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/push_notifications.php';

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
        error_log("ERRO FATAL PHP: [{$errno}] {$errstr} em {$errfile}:{$errline}");
        return false; // Continua execução normal
    });
    
    register_shutdown_function(function() {
        $error = error_get_last();
        if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            error_log("SHUTDOWN ERROR: " . print_r($error, true));
        }
    });
    
    error_log('=== INÍCIO PROCESSAMENTO NOTIFICAÇÃO ===');
    error_log('POST data: ' . print_r($_POST, true));
    
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
        
        error_log("Valores processados - usuario_id: {$usuario_id}, colaborador_id: {$colaborador_id}, titulo: {$titulo}, mensagem: " . substr($mensagem, 0, 50));
        
        if (empty($titulo) || empty($mensagem)) {
            $error = 'Título e mensagem são obrigatórios!';
            error_log('ERRO: Título ou mensagem vazios');
        } elseif (!$usuario_id && !$colaborador_id) {
            $error = 'Selecione um destinatário!';
            error_log('ERRO: Nenhum destinatário selecionado');
        } else {
            error_log('Iniciando envio de notificação...');
            
            if ($colaborador_id) {
                error_log("Enviando para colaborador_id: {$colaborador_id}");
                $resultado = enviar_push_colaborador($colaborador_id, $titulo, $mensagem, $url ?: null);
            } else {
                error_log("Enviando para usuario_id: {$usuario_id}");
                $resultado = enviar_push_usuario($usuario_id, $titulo, $mensagem, $url ?: null);
            }
            
            error_log('Resultado do envio: ' . print_r($resultado, true));
            
            if (isset($resultado['success']) && $resultado['success']) {
                $success = "Notificação enviada com sucesso para {$resultado['enviadas']} dispositivo(s)!";
                error_log("SUCESSO: {$success}");
                // Recarrega a página após 1 segundo para atualizar a lista
                header('Location: ' . $_SERVER['PHP_SELF'] . '?success=' . urlencode($success));
                exit;
            } else {
                $error = $resultado['message'] ?? 'Erro desconhecido ao enviar notificação';
                error_log("ERRO no resultado: {$error}");
            }
        }
    } catch (Throwable $e) {
        $error = 'Erro ao enviar notificação: ' . $e->getMessage();
        error_log('EXCEÇÃO ao enviar push notification: ' . $e->getMessage());
        error_log('Arquivo: ' . $e->getFile() . ':' . $e->getLine());
        error_log('Stack trace: ' . $e->getTraceAsString());
        
        // Se for um erro fatal, mostra mais detalhes
        if ($e instanceof Error) {
            error_log('ERRO FATAL: ' . get_class($e));
        }
    } finally {
        restore_error_handler();
    }
    
    error_log('=== FIM PROCESSAMENTO NOTIFICAÇÃO ===');
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
    $sql = "
        SELECT 
            os.usuario_id,
            os.colaborador_id,
            GROUP_CONCAT(DISTINCT os.player_id SEPARATOR ',') as player_ids,
            GROUP_CONCAT(DISTINCT os.device_type SEPARATOR ',') as device_types,
            MAX(os.created_at) as created_at,
            MAX(u.nome) as usuario_nome,
            MAX(u.email) as usuario_email,
            MAX(u.role) as usuario_role,
            MAX(c.nome_completo) as colaborador_nome,
            COUNT(os.id) as total_dispositivos
        FROM onesignal_subscriptions os
        LEFT JOIN usuarios u ON os.usuario_id = u.id
        LEFT JOIN colaboradores c ON os.colaborador_id = c.id
        WHERE 1=1 {$whereClause}
        GROUP BY COALESCE(os.usuario_id, 0), COALESCE(os.colaborador_id, 0)
        ORDER BY MAX(os.created_at) DESC
    ";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $subscriptions = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Erro na query de subscriptions: ' . $e->getMessage());
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
        error_log('Erro ao contar subscriptions: ' . $e->getMessage());
        $total_usuarios = 0;
    }
}

$page_title = 'Enviar Notificação Push';
include __DIR__ . '/../includes/header.php';
?>

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
                            <?php if (empty($subscriptions)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-10">
                                        <div class="text-muted">Nenhum usuário com push registrado encontrado.</div>
                                    </td>
                                </tr>
                            <?php else: ?>
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
                                            <button type="button" class="btn btn-sm btn-primary" 
                                                    onclick="enviarParaUsuario(<?= $sub['usuario_id'] ?? 'null' ?>, <?= $sub['colaborador_id'] ?? 'null' ?>, '<?= htmlspecialchars(addslashes(($sub['usuario_nome'] ?? '') ?: ($sub['colaborador_nome'] ?? '') ?: 'Usuário')) ?>')">
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

<script>
function enviarParaUsuario(usuarioId, colaboradorId, nome) {
    document.getElementById('modal_usuario_id').value = usuarioId || '';
    document.getElementById('modal_colaborador_id').value = colaboradorId || '';
    document.getElementById('modal_destinatario').value = nome || '';
    
    const modal = new bootstrap.Modal(document.getElementById('modal_enviar_notificacao'));
    modal.show();
}

// Limpa formulário quando modal fecha
document.getElementById('modal_enviar_notificacao').addEventListener('hidden.bs.modal', function() {
    document.getElementById('form_enviar_notificacao').reset();
    document.getElementById('modal_usuario_id').value = '';
    document.getElementById('modal_colaborador_id').value = '';
    document.getElementById('modal_destinatario').value = '';
    
    // Remove estado de loading do botão
    const submitBtn = document.querySelector('#form_enviar_notificacao button[type="submit"]');
    if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = submitBtn.innerHTML.replace('Enviando...', 'Enviar Notificação');
    }
});

// Handler de submit do formulário - executa quando DOM estiver pronto
(function() {
    var formHandlerInitialized = false;
    
    function initFormHandler() {
        if (formHandlerInitialized) {
            return; // Já foi inicializado
        }
        
        const form = document.getElementById('form_enviar_notificacao');
        if (!form) {
            console.warn('Formulário #form_enviar_notificacao não encontrado ainda, tentando novamente...');
            setTimeout(initFormHandler, 100);
            return;
        }
        
        // Adiciona listener apenas uma vez
        form.addEventListener('submit', function(e) {
            console.log('=== SUBMIT DO FORMULÁRIO DETECTADO ===');
            
            // Validação básica
            const titulo = form.querySelector('input[name="titulo"]').value.trim();
            const mensagem = form.querySelector('textarea[name="mensagem"]').value.trim();
            const usuarioId = form.querySelector('input[name="usuario_id"]').value.trim();
            const colaboradorId = form.querySelector('input[name="colaborador_id"]').value.trim();
            
            console.log('Dados do formulário:', {
                titulo: titulo,
                mensagem: mensagem,
                usuario_id: usuarioId,
                colaborador_id: colaboradorId
            });
            
            if (!titulo || !mensagem) {
                e.preventDefault();
                e.stopPropagation();
                alert('Por favor, preencha o título e a mensagem!');
                return false;
            }
            
            if (!usuarioId && !colaboradorId) {
                e.preventDefault();
                e.stopPropagation();
                alert('Por favor, selecione um destinatário da tabela clicando no botão "Enviar" de um usuário!');
                return false;
            }
            
            // Mostra feedback visual
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                const originalHtml = submitBtn.innerHTML;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enviando...';
                
                // Se o formulário não for submetido em 30 segundos, reabilita o botão
                setTimeout(function() {
                    if (submitBtn.disabled) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalHtml;
                        console.warn('Timeout: Formulário não foi submetido em 30 segundos');
                    }
                }, 30000);
            }
            
            console.log('Formulário validado, permitindo submit...');
            // Permite o submit normal do formulário
            return true;
        });
        
        formHandlerInitialized = true;
        console.log('Handler de submit do formulário inicializado');
    }
    
    // Tenta inicializar imediatamente
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initFormHandler);
    } else {
        // DOM já está pronto
        setTimeout(initFormHandler, 100); // Pequeno delay para garantir que o modal está renderizado
    }
    
    // Também tenta quando o modal é aberto
    const modal = document.getElementById('modal_enviar_notificacao');
    if (modal) {
        modal.addEventListener('shown.bs.modal', function() {
            setTimeout(initFormHandler, 50);
        });
    }
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
// Aguarda o footer carregar completamente antes de executar
(function() {
    'use strict';
    
    // Inicializa DataTables após jQuery estar carregado (no footer)
    var dataTableInstance = null;
    var initialized = false;
    var jQueryInstance = null;
    
    function getJQuery() {
        if (jQueryInstance && typeof jQueryInstance === 'function') {
            return jQueryInstance;
        }
        if (typeof window.jQuery === 'function') {
            jQueryInstance = window.jQuery;
            return jQueryInstance;
        }
        return null;
    }
    
    function initDataTable() {
        var jq = getJQuery();
        if (!jq) {
            console.warn('jQuery não está disponível ainda');
            return false;
        }
        
        if (typeof jq.fn === 'undefined') {
            console.warn('jQuery.fn não está disponível');
            return false;
        }
        
        var tableElement = jq('#kt_notificacoes_table');
        if (!tableElement.length) {
            console.warn('Tabela #kt_notificacoes_table não encontrada');
            return false;
        }
        
        // Evita inicialização duplicada
        if (initialized) {
            console.log('DataTable já foi inicializado');
            return true;
        }
        
        try {
            // Destrói instância anterior se existir
            if (dataTableInstance) {
                try {
                    dataTableInstance.destroy();
                } catch (e) {
                    // Ignora erros ao destruir
                }
                dataTableInstance = null;
            }
            
            // Verifica se já foi inicializado pelo DataTables
            if (tableElement.hasClass('dataTable')) {
                console.log('DataTable já possui classe dataTable, pulando inicialização');
                initialized = true;
                return true;
            }
            
            // Verifica se DataTable está disponível
            if (typeof jq.fn.DataTable === 'undefined') {
                console.error('Plugin DataTable não está disponível');
                return false;
            }
            
            // Inicializa DataTable
            dataTableInstance = tableElement.DataTable({
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json'
                },
                pageLength: 25,
                order: [[5, 'desc']], // Ordena por data de registro
                responsive: true,
                columnDefs: [
                    { orderable: false, targets: 6 } // Coluna de ações não ordenável
                ],
                dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>'
            });
            initialized = true;
            console.log('DataTable inicializado com sucesso');
            return true;
        } catch (e) {
            console.error('Erro ao inicializar DataTable:', e);
            return false;
        }
    }
    
    // Função para aguardar jQuery
    function waitForJQuery(callback, timeout) {
        timeout = timeout || 10000; // 10 segundos padrão
        var startTime = Date.now();
        
        function check() {
            var jq = typeof window.jQuery === 'function' ? window.jQuery : null;
            if (jq && typeof jq.fn !== 'undefined') {
                jQueryInstance = jq;
                console.log('jQuery detectado, executando callback');
                callback(jq);
            } else if (Date.now() - startTime < timeout) {
                setTimeout(check, 100);
            } else {
                console.error('jQuery não foi carregado após ' + (timeout / 1000) + ' segundos');
            }
        }
        
        check();
    }
    
    // Função para aguardar jQuery e DataTables
    function waitForDataTables(callback, timeout) {
        timeout = timeout || 10000;
        var startTime = Date.now();
        
        function check() {
            var jq = getJQuery();
            if (jq && typeof jq.fn !== 'undefined' && typeof jq.fn.DataTable !== 'undefined') {
                console.log('DataTables detectado, executando callback');
                callback(jq);
            } else if (Date.now() - startTime < timeout) {
                setTimeout(check, 100);
            } else {
                console.error('DataTables não foi carregado após ' + (timeout / 1000) + ' segundos');
            }
        }
        
        check();
    }
    
    function scheduleInit(delay) {
        var jq = getJQuery();
        if (!jq) {
            console.warn('jQuery não disponível para agendar init');
            return;
        }
        jq(function() {
            console.log('DOM pronto, inicializando DataTable em ' + delay + 'ms...');
            setTimeout(function() {
                initDataTable();
            }, delay);
        });
    }
    
    // Aguarda window.load para garantir que todos os scripts foram carregados
    if (document.readyState === 'complete') {
        // Página já carregou completamente
        waitForJQuery(function() {
            waitForDataTables(function() {
                scheduleInit(500);
            });
        });
    } else {
        // Aguarda window.load
        window.addEventListener('load', function() {
            console.log('Window load completo, aguardando jQuery...');
            waitForJQuery(function() {
                waitForDataTables(function() {
                    scheduleInit(500);
                });
            });
        });
    }
})();
</script>

