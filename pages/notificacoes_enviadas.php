<?php
/**
 * Notificações Enviadas - Histórico de notificações push enviadas
 */

// Headers anti-cache para evitar problemas de cache do navegador
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Mon, 01 Jan 1990 00:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

// Inicia sessão se não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_page_permission('notificacoes_enviadas.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Filtros
$filtro_data_inicio = $_GET['data_inicio'] ?? '';
$filtro_data_fim = $_GET['data_fim'] ?? '';
$filtro_titulo = $_GET['titulo'] ?? '';
$filtro_sucesso = $_GET['sucesso'] ?? '';

// Monta query com filtros
$where = [];
$params = [];

if ($filtro_data_inicio) {
    $where[] = "DATE(pnh.created_at) >= ?";
    $params[] = $filtro_data_inicio;
}

if ($filtro_data_fim) {
    $where[] = "DATE(pnh.created_at) <= ?";
    $params[] = $filtro_data_fim;
}

if ($filtro_titulo) {
    $where[] = "pnh.titulo LIKE ?";
    $params[] = "%{$filtro_titulo}%";
}

if ($filtro_sucesso !== '') {
    $where[] = "pnh.sucesso = ?";
    $params[] = $filtro_sucesso === '1' ? 1 : 0;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Busca histórico de notificações
$sql = "
    SELECT 
        pnh.*,
        u.nome as usuario_nome,
        u.email as usuario_email,
        c.nome_completo as colaborador_nome,
        enviado_por.nome as enviado_por_nome
    FROM push_notifications_history pnh
    LEFT JOIN usuarios u ON pnh.usuario_id = u.id
    LEFT JOIN colaboradores c ON pnh.colaborador_id = c.id
    LEFT JOIN usuarios enviado_por ON pnh.enviado_por_usuario_id = enviado_por.id
    {$whereClause}
    ORDER BY pnh.created_at DESC
    LIMIT 500
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $notificacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $notificacoes = [];
    $error = 'Erro ao carregar histórico: ' . $e->getMessage();
}

// Estatísticas
$stats = [
    'total' => 0,
    'sucesso' => 0,
    'falhas' => 0,
    'total_dispositivos' => 0
];

try {
    $stmt_stats = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN sucesso = 1 THEN 1 ELSE 0 END) as sucesso,
            SUM(CASE WHEN sucesso = 0 THEN 1 ELSE 0 END) as falhas,
            SUM(total_dispositivos) as total_dispositivos
        FROM push_notifications_history
    ");
    $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC) ?: $stats;
} catch (PDOException $e) {
    // Ignora erro de estatísticas
}

$page_title = 'Notificações Enviadas';

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
        
        <?php if (isset($error)): ?>
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
                    <h2 class="fw-bold">Histórico de Notificações Push</h2>
                </div>
                <div class="card-toolbar">
                    <a href="enviar_notificacao_push.php" class="btn btn-primary">
                        <i class="ki-duotone ki-notification-status fs-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        Nova Notificação
                    </a>
                </div>
            </div>
            <!--end::Card header-->
            
            <!--begin::Card body-->
            <div class="card-body py-4">
                <!--begin::Estatísticas-->
                <div class="row g-3 mb-5">
                    <div class="col-md-3">
                        <div class="card bg-light-primary">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <span class="text-muted fw-semibold d-block">Total Enviadas</span>
                                        <span class="text-gray-800 fw-bold fs-2"><?= number_format($stats['total'] ?? 0) ?></span>
                                    </div>
                                    <i class="ki-duotone ki-notification-status fs-1 text-primary">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-light-success">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <span class="text-muted fw-semibold d-block">Sucesso</span>
                                        <span class="text-gray-800 fw-bold fs-2"><?= number_format($stats['sucesso'] ?? 0) ?></span>
                                    </div>
                                    <i class="ki-duotone ki-check-circle fs-1 text-success">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-light-danger">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <span class="text-muted fw-semibold d-block">Falhas</span>
                                        <span class="text-gray-800 fw-bold fs-2"><?= number_format($stats['falhas'] ?? 0) ?></span>
                                    </div>
                                    <i class="ki-duotone ki-cross-circle fs-1 text-danger">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-light-info">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <span class="text-muted fw-semibold d-block">Dispositivos</span>
                                        <span class="text-gray-800 fw-bold fs-2"><?= number_format($stats['total_dispositivos'] ?? 0) ?></span>
                                    </div>
                                    <i class="ki-duotone ki-devices fs-1 text-info">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!--end::Estatísticas-->
                
                <!--begin::Filtros-->
                <form method="GET" class="mb-5">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Data Início</label>
                            <input type="date" name="data_inicio" class="form-control" value="<?= htmlspecialchars($filtro_data_inicio) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Data Fim</label>
                            <input type="date" name="data_fim" class="form-control" value="<?= htmlspecialchars($filtro_data_fim) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Título</label>
                            <input type="text" name="titulo" class="form-control" placeholder="Buscar por título..." value="<?= htmlspecialchars($filtro_titulo) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="sucesso" class="form-select">
                                <option value="">Todos</option>
                                <option value="1" <?= $filtro_sucesso === '1' ? 'selected' : '' ?>>Sucesso</option>
                                <option value="0" <?= $filtro_sucesso === '0' ? 'selected' : '' ?>>Falhas</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <button type="submit" class="btn btn-primary">Filtrar</button>
                            <a href="notificacoes_enviadas.php" class="btn btn-secondary">Limpar</a>
                        </div>
                    </div>
                </form>
                <!--end::Filtros-->
                
                <!--begin::Table-->
                <div class="table-responsive">
                    <table id="kt_notificacoes_enviadas_table" class="table table-row-bordered table-row-dashed gy-4 align-middle">
                        <thead>
                            <tr class="fw-bold fs-6 text-gray-800">
                                <th>Data/Hora</th>
                                <th>Título</th>
                                <th>Mensagem</th>
                                <th>Destinatário</th>
                                <th>Enviado Por</th>
                                <th>Dispositivos</th>
                                <th>Status</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($notificacoes)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-10">
                                        <i class="ki-duotone ki-information-5 fs-3x mb-3">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                        </i>
                                        <div>Nenhuma notificação encontrada.</div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($notificacoes as $notif): ?>
                                    <tr>
                                        <td>
                                            <span class="text-gray-800 fw-semibold">
                                                <?= date('d/m/Y H:i', strtotime($notif['created_at'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="text-gray-800 fw-bold"><?= htmlspecialchars($notif['titulo']) ?></span>
                                        </td>
                                        <td>
                                            <span class="text-gray-600" title="<?= htmlspecialchars($notif['mensagem']) ?>">
                                                <?= htmlspecialchars(mb_substr($notif['mensagem'], 0, 50)) ?><?= mb_strlen($notif['mensagem']) > 50 ? '...' : '' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($notif['colaborador_nome']): ?>
                                                <span class="text-gray-800"><?= htmlspecialchars($notif['colaborador_nome']) ?></span>
                                                <span class="text-muted d-block fs-7">Colaborador</span>
                                            <?php elseif ($notif['usuario_nome']): ?>
                                                <span class="text-gray-800"><?= htmlspecialchars($notif['usuario_nome']) ?></span>
                                                <span class="text-muted d-block fs-7"><?= htmlspecialchars($notif['usuario_email']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="text-gray-800"><?= htmlspecialchars($notif['enviado_por_nome'] ?? 'Sistema') ?></span>
                                        </td>
                                        <td>
                                            <span class="badge badge-info"><?= intval($notif['total_dispositivos']) ?></span>
                                        </td>
                                        <td>
                                            <?php if ($notif['sucesso']): ?>
                                                <span class="badge badge-success">Sucesso</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger" title="<?= htmlspecialchars($notif['erro_mensagem'] ?? '') ?>">Falha</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-sm btn-light-primary" 
                                                    onclick='verDetalhes(<?= json_encode($notif, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                                <i class="ki-duotone ki-eye fs-5">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                    <span class="path3"></span>
                                                </i>
                                                Ver Detalhes
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

<!--begin::Modal Detalhes-->
<div class="modal fade" id="modal_detalhes" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Detalhes da Notificação</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <div class="mb-5">
                    <label class="form-label fw-bold">Data/Hora:</label>
                    <div class="text-gray-800" id="detalhe_data"></div>
                </div>
                <div class="mb-5">
                    <label class="form-label fw-bold">Título:</label>
                    <div class="text-gray-800" id="detalhe_titulo"></div>
                </div>
                <div class="mb-5">
                    <label class="form-label fw-bold">Mensagem:</label>
                    <div class="text-gray-800" id="detalhe_mensagem"></div>
                </div>
                <div class="mb-5">
                    <label class="form-label fw-bold">Destinatário:</label>
                    <div class="text-gray-800" id="detalhe_destinatario"></div>
                </div>
                <div class="mb-5">
                    <label class="form-label fw-bold">Enviado Por:</label>
                    <div class="text-gray-800" id="detalhe_enviado_por"></div>
                </div>
                <div class="mb-5">
                    <label class="form-label fw-bold">Dispositivos:</label>
                    <div class="text-gray-800" id="detalhe_dispositivos"></div>
                </div>
                <div class="mb-5">
                    <label class="form-label fw-bold">Status:</label>
                    <div id="detalhe_status"></div>
                </div>
                <div class="mb-5" id="detalhe_erro_container" style="display: none;">
                    <label class="form-label fw-bold text-danger">Erro:</label>
                    <div class="text-danger" id="detalhe_erro"></div>
                </div>
                <div class="mb-5" id="detalhe_url_container" style="display: none;">
                    <label class="form-label fw-bold">URL:</label>
                    <div class="text-gray-800">
                        <a href="#" id="detalhe_url" target="_blank" class="text-primary"></a>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>
<!--end::Modal Detalhes-->

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script data-version="<?= $page_version ?>" data-timestamp="<?= time() ?>">
// VERSÃO DA PÁGINA: <?= $page_version ?> - TIMESTAMP: <?= time() ?> 
console.log('[DEBUG_NOTIF_HIST] Script da página iniciado');
console.log('[DEBUG_NOTIF_HIST] Versão da página:', '<?= $page_version ?>', 'Timestamp:', '<?= time() ?>');

// Monitor de erros global para esta página
window.addEventListener('error', function(e) {
    console.error('[DEBUG_NOTIF_HIST] Erro global capturado:', e.message, 'em', e.filename, 'linha', e.lineno);
});

// Aguarda jQuery estar disponível antes de executar qualquer código
(function() {
    'use strict';
    
    // Função auxiliar para aguardar jQuery
    function waitForJQuery(callback) {
        console.log('[DEBUG_NOTIF_HIST] Aguardando jQuery...');
        if (typeof window.jQuery !== 'undefined' && typeof window.jQuery.fn !== 'undefined') {
            console.log('[DEBUG_NOTIF_HIST] jQuery disponível:', window.jQuery.fn.jquery);
            callback(window.jQuery);
        } else {
            setTimeout(function() {
                waitForJQuery(callback);
            }, 50);
        }
    }
    
    // Aguarda jQuery e então inicializa tudo
    waitForJQuery(function($) {
        console.log('[DEBUG_NOTIF_HIST] Callback jQuery executando');
        // Função para ver detalhes
        window.verDetalhes = function(notif) {
            console.log('[DEBUG_NOTIF_HIST] Abrindo modal de detalhes para notificação:', notif.id);
            $('#detalhe_data').text(new Date(notif.created_at).toLocaleString('pt-BR'));
            $('#detalhe_titulo').text(notif.titulo);
            $('#detalhe_mensagem').text(notif.mensagem);
            
            let destinatario = '-';
            if (notif.colaborador_nome) {
                destinatario = notif.colaborador_nome + ' (Colaborador)';
            } else if (notif.usuario_nome) {
                destinatario = notif.usuario_nome + ' (' + (notif.usuario_email || '') + ')';
            }
            $('#detalhe_destinatario').text(destinatario);
            $('#detalhe_enviado_por').text(notif.enviado_por_nome || 'Sistema');
            $('#detalhe_dispositivos').text(notif.total_dispositivos || 0);
            
            if (notif.sucesso) {
                $('#detalhe_status').html('<span class="badge badge-success">Sucesso</span>');
                $('#detalhe_erro_container').hide();
            } else {
                $('#detalhe_status').html('<span class="badge badge-danger">Falha</span>');
                $('#detalhe_erro').text(notif.erro_mensagem || 'Erro desconhecido');
                $('#detalhe_erro_container').show();
            }
            
            if (notif.url) {
                $('#detalhe_url').attr('href', notif.url).text(notif.url);
                $('#detalhe_url_container').show();
            } else {
                $('#detalhe_url_container').hide();
            }
            
            try {
                const modal = new bootstrap.Modal(document.getElementById('modal_detalhes'));
                modal.show();
                console.log('[DEBUG_NOTIF_HIST] Modal de detalhes aberto com sucesso');
            } catch (e) {
                console.error('[DEBUG_NOTIF_HIST] Erro ao abrir modal:', e);
            }
        };
        
        // Inicializa DataTable quando DOM estiver pronto
        $(document).ready(function() {
            console.log('[DEBUG_NOTIF_HIST] Documento pronto, iniciando setup do DataTable');
            // Aguarda DataTables estar disponível
            function waitForDataTables(callback) {
                console.log('[DEBUG_NOTIF_HIST] Verificando disponibilidade do DataTables...');
                if (typeof $.fn.DataTable !== 'undefined') {
                    console.log('[DEBUG_NOTIF_HIST] DataTables encontrado!');
                    callback();
                } else {
                    console.log('[DEBUG_NOTIF_HIST] DataTables ainda não disponível, aguardando...');
                    setTimeout(function() {
                        waitForDataTables(callback);
                    }, 100);
                }
            }
            
            waitForDataTables(function() {
                console.log('[DEBUG_NOTIF_HIST] DataTables disponível, inicializando tabela...');
                const table = $('#kt_notificacoes_enviadas_table');
                
                if (!table.length) {
                    console.warn('[DEBUG_NOTIF_HIST] Tabela não encontrada no DOM');
                    return;
                }
                
                if (table.hasClass('dataTable')) {
                    console.log('[DEBUG_NOTIF_HIST] DataTable já estava inicializado');
                    return;
                }
                
                // Verifica se há pelo menos uma linha de dados (não conta o "Nenhum registro" com colspan)
                const tbody = table.find('tbody');
                const dataRows = tbody.find('tr').filter(function() {
                    return $(this).find('td[colspan]').length === 0;
                });
                
                console.log('[DEBUG_NOTIF_HIST] Linhas de dados encontradas:', dataRows.length);
                
                if (dataRows.length === 0) {
                    console.log('[DEBUG_NOTIF_HIST] Tabela vazia - DataTable não será inicializado');
                    return;
                }
                
                try {
                    console.log('[DEBUG_NOTIF_HIST] Configurando DataTable...');
                    table.DataTable({
                        language: {
                            url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json'
                        },
                        pageLength: 25,
                        order: [[0, 'desc']], // Ordena por data
                        responsive: true,
                        columnDefs: [
                            { orderable: false, targets: 7 } // Coluna de ações não ordenável
                        ],
                        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>'
                    });
                    console.log('[DEBUG_NOTIF_HIST] DataTable inicializado com SUCESSO');
                } catch (e) {
                    console.error('[DEBUG_NOTIF_HIST] EXCEÇÃO ao inicializar DataTable:', e);
                    console.error('[DEBUG_NOTIF_HIST] Stack:', e.stack);
                }
            });
        });
    });
})();
</script>

