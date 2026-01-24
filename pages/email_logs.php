<?php
/**
 * Logs de Emails Enviados - Metronic Theme
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('email_logs.php');

$pdo = getDB();

// Verifica e cria a tabela se não existir
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'email_logs'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS email_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email_destinatario VARCHAR(255) NOT NULL,
                nome_destinatario VARCHAR(255) NULL,
                assunto VARCHAR(500) NOT NULL,
                template_codigo VARCHAR(50) NULL,
                template_nome VARCHAR(255) NULL,
                status ENUM('sucesso', 'erro') NOT NULL DEFAULT 'sucesso',
                erro_mensagem TEXT NULL,
                origem VARCHAR(100) NULL,
                usuario_id INT NULL,
                colaborador_id INT NULL,
                empresa_id INT NULL,
                ip_origem VARCHAR(45) NULL,
                user_agent TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_email_logs_status (status),
                INDEX idx_email_logs_destinatario (email_destinatario),
                INDEX idx_email_logs_template (template_codigo),
                INDEX idx_email_logs_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
} catch (PDOException $e) {
    // Ignora erro se a tabela já existir
}

// Filtros
$filtro_status = $_GET['status'] ?? '';
$filtro_template = $_GET['template'] ?? '';
$filtro_email = $_GET['email'] ?? '';
$filtro_data_inicio = $_GET['data_inicio'] ?? '';
$filtro_data_fim = $_GET['data_fim'] ?? '';

// Monta query com filtros
$where = [];
$params = [];

if ($filtro_status) {
    $where[] = "l.status = ?";
    $params[] = $filtro_status;
}

if ($filtro_template) {
    $where[] = "l.template_codigo = ?";
    $params[] = $filtro_template;
}

if ($filtro_email) {
    $where[] = "l.email_destinatario LIKE ?";
    $params[] = '%' . $filtro_email . '%';
}

if ($filtro_data_inicio) {
    $where[] = "DATE(l.created_at) >= ?";
    $params[] = $filtro_data_inicio;
}

if ($filtro_data_fim) {
    $where[] = "DATE(l.created_at) <= ?";
    $params[] = $filtro_data_fim;
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Busca logs
$sql = "
    SELECT l.*, 
           u.nome as usuario_nome,
           c.nome_completo as colaborador_nome,
           e.nome_fantasia as empresa_nome
    FROM email_logs l
    LEFT JOIN usuarios u ON l.usuario_id = u.id
    LEFT JOIN colaboradores c ON l.colaborador_id = c.id
    LEFT JOIN empresas e ON l.empresa_id = e.id
    {$where_sql}
    ORDER BY l.created_at DESC
    LIMIT 1000
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Busca templates para filtro
$stmt_templates = $pdo->query("SELECT DISTINCT template_codigo, template_nome FROM email_logs WHERE template_codigo IS NOT NULL ORDER BY template_nome");
$templates = $stmt_templates->fetchAll();

// Estatísticas
$stmt_stats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'sucesso' THEN 1 ELSE 0 END) as sucesso,
        SUM(CASE WHEN status = 'erro' THEN 1 ELSE 0 END) as erro,
        COUNT(DISTINCT DATE(created_at)) as dias_com_envio
    FROM email_logs
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$stats = $stmt_stats->fetch();

// Estatísticas por template (últimos 30 dias)
$stmt_stats_template = $pdo->query("
    SELECT 
        template_codigo,
        template_nome,
        COUNT(*) as total,
        SUM(CASE WHEN status = 'sucesso' THEN 1 ELSE 0 END) as sucesso,
        SUM(CASE WHEN status = 'erro' THEN 1 ELSE 0 END) as erro
    FROM email_logs
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    AND template_codigo IS NOT NULL
    GROUP BY template_codigo, template_nome
    ORDER BY total DESC
    LIMIT 10
");
$stats_template = $stmt_stats_template->fetchAll();

$page_title = 'Logs de Emails';
require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Logs de Emails</h1>
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
                <li class="breadcrumb-item text-gray-900">Logs de Emails</li>
            </ul>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <!--begin::Row - Estatísticas-->
        <div class="row g-5 g-xl-8 mb-5">
            <!--begin::Col-->
            <div class="col-xl-3">
                <div class="card card-flush h-100">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-gray-500 fw-semibold d-block fs-7">Total (30 dias)</span>
                            <span class="text-gray-800 fw-bold d-block fs-2x"><?= number_format($stats['total'] ?? 0, 0, ',', '.') ?></span>
                        </div>
                        <div class="symbol symbol-50px bg-light-primary">
                            <span class="symbol-label">
                                <i class="ki-duotone ki-sms fs-2x text-primary">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <!--end::Col-->
            
            <!--begin::Col-->
            <div class="col-xl-3">
                <div class="card card-flush h-100">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-gray-500 fw-semibold d-block fs-7">Sucesso (30 dias)</span>
                            <span class="text-gray-800 fw-bold d-block fs-2x text-success"><?= number_format($stats['sucesso'] ?? 0, 0, ',', '.') ?></span>
                        </div>
                        <div class="symbol symbol-50px bg-light-success">
                            <span class="symbol-label">
                                <i class="ki-duotone ki-check-circle fs-2x text-success">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <!--end::Col-->
            
            <!--begin::Col-->
            <div class="col-xl-3">
                <div class="card card-flush h-100">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-gray-500 fw-semibold d-block fs-7">Erros (30 dias)</span>
                            <span class="text-gray-800 fw-bold d-block fs-2x text-danger"><?= number_format($stats['erro'] ?? 0, 0, ',', '.') ?></span>
                        </div>
                        <div class="symbol symbol-50px bg-light-danger">
                            <span class="symbol-label">
                                <i class="ki-duotone ki-cross-circle fs-2x text-danger">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <!--end::Col-->
            
            <!--begin::Col-->
            <div class="col-xl-3">
                <div class="card card-flush h-100">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-gray-500 fw-semibold d-block fs-7">Taxa de Sucesso</span>
                            <?php 
                            $taxa_sucesso = ($stats['total'] ?? 0) > 0 
                                ? round(($stats['sucesso'] / $stats['total']) * 100, 1) 
                                : 0;
                            ?>
                            <span class="text-gray-800 fw-bold d-block fs-2x"><?= $taxa_sucesso ?>%</span>
                        </div>
                        <div class="symbol symbol-50px bg-light-info">
                            <span class="symbol-label">
                                <i class="ki-duotone ki-chart-simple fs-2x text-info">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                    <span class="path4"></span>
                                </i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <!--end::Col-->
        </div>
        <!--end::Row-->
        
        <!--begin::Card - Filtros-->
        <div class="card mb-5">
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <i class="ki-duotone ki-filter fs-2 me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Filtros
                </div>
                <div class="card-toolbar">
                    <a href="email_logs.php" class="btn btn-sm btn-light">Limpar Filtros</a>
                </div>
            </div>
            <div class="card-body pt-0">
                <form method="GET" class="row g-4">
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select form-select-solid">
                            <option value="">Todos</option>
                            <option value="sucesso" <?= $filtro_status === 'sucesso' ? 'selected' : '' ?>>Sucesso</option>
                            <option value="erro" <?= $filtro_status === 'erro' ? 'selected' : '' ?>>Erro</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Template</label>
                        <select name="template" class="form-select form-select-solid">
                            <option value="">Todos</option>
                            <?php foreach ($templates as $tpl): ?>
                            <option value="<?= htmlspecialchars($tpl['template_codigo']) ?>" 
                                    <?= $filtro_template === $tpl['template_codigo'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($tpl['template_nome'] ?? $tpl['template_codigo']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Email</label>
                        <input type="text" name="email" class="form-control form-control-solid" 
                               value="<?= htmlspecialchars($filtro_email) ?>" placeholder="Buscar por email...">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Data Início</label>
                        <input type="date" name="data_inicio" class="form-control form-control-solid" 
                               value="<?= htmlspecialchars($filtro_data_inicio) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Data Fim</label>
                        <input type="date" name="data_fim" class="form-control form-control-solid" 
                               value="<?= htmlspecialchars($filtro_data_fim) ?>">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="ki-duotone ki-magnifier fs-3">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Filtrar
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <!--end::Card-->
        
        <!--begin::Row-->
        <div class="row g-5 g-xl-8">
            <!--begin::Col - Tabela-->
            <div class="col-xl-8">
                <!--begin::Card-->
                <div class="card">
                    <!--begin::Card header-->
                    <div class="card-header border-0 pt-6">
                        <div class="card-title">
                            <h3 class="fw-bold">Histórico de Envios</h3>
                        </div>
                        <div class="card-toolbar">
                            <span class="badge badge-light-primary fs-7"><?= count($logs) ?> registros</span>
                        </div>
                    </div>
                    <!--end::Card header-->
                    
                    <!--begin::Card body-->
                    <div class="card-body pt-0">
                        <div class="table-responsive">
                            <table class="table align-middle table-row-dashed fs-6 gy-5" id="kt_email_logs_table">
                                <thead>
                                    <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                                        <th class="min-w-150px">Destinatário</th>
                                        <th class="min-w-200px">Assunto</th>
                                        <th class="min-w-100px">Template</th>
                                        <th class="min-w-80px">Status</th>
                                        <th class="min-w-120px">Data/Hora</th>
                                        <th class="text-end min-w-70px">Ações</th>
                                    </tr>
                                </thead>
                                <tbody class="fw-semibold text-gray-600">
                                    <?php if (empty($logs)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-10">
                                            <i class="ki-duotone ki-sms fs-3x text-gray-300 mb-3">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                            </i>
                                            <p class="mb-0">Nenhum log de email encontrado</p>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <span class="text-gray-800"><?= htmlspecialchars($log['email_destinatario']) ?></span>
                                                <?php if ($log['nome_destinatario']): ?>
                                                <small class="text-muted"><?= htmlspecialchars($log['nome_destinatario']) ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="text-truncate d-inline-block" style="max-width: 250px;" 
                                                  title="<?= htmlspecialchars($log['assunto']) ?>">
                                                <?= htmlspecialchars($log['assunto']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($log['template_codigo']): ?>
                                            <span class="badge badge-light-primary">
                                                <?= htmlspecialchars($log['template_nome'] ?? $log['template_codigo']) ?>
                                            </span>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($log['status'] === 'sucesso'): ?>
                                            <span class="badge badge-light-success">Sucesso</span>
                                            <?php else: ?>
                                            <span class="badge badge-light-danger" 
                                                  title="<?= htmlspecialchars($log['erro_mensagem'] ?? '') ?>">
                                                Erro
                                            </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="text-gray-600">
                                                <?= date('d/m/Y H:i', strtotime($log['created_at'])) ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-sm btn-icon btn-light-primary" 
                                                    onclick="verDetalhes(<?= htmlspecialchars(json_encode($log)) ?>)">
                                                <i class="ki-duotone ki-eye fs-3">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                    <span class="path3"></span>
                                                </i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <!--end::Card body-->
                </div>
                <!--end::Card-->
            </div>
            <!--end::Col-->
            
            <!--begin::Col - Estatísticas por Template-->
            <div class="col-xl-4">
                <div class="card">
                    <div class="card-header border-0 pt-6">
                        <div class="card-title">
                            <h3 class="fw-bold">Por Template (30 dias)</h3>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <?php if (empty($stats_template)): ?>
                        <div class="text-center text-muted py-10">
                            <i class="ki-duotone ki-chart-simple fs-3x text-gray-300 mb-3">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                                <span class="path4"></span>
                            </i>
                            <p class="mb-0">Nenhuma estatística disponível</p>
                        </div>
                        <?php else: ?>
                        <?php foreach ($stats_template as $st): ?>
                        <div class="d-flex align-items-center mb-4">
                            <div class="flex-grow-1">
                                <span class="text-gray-800 fw-semibold d-block fs-6">
                                    <?= htmlspecialchars($st['template_nome'] ?? $st['template_codigo']) ?>
                                </span>
                                <div class="d-flex align-items-center mt-1">
                                    <span class="badge badge-light-success me-2"><?= $st['sucesso'] ?> ok</span>
                                    <?php if ($st['erro'] > 0): ?>
                                    <span class="badge badge-light-danger"><?= $st['erro'] ?> erros</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <span class="text-gray-600 fw-bold fs-4"><?= $st['total'] ?></span>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!--begin::Card - Info-->
                <div class="card mt-5">
                    <div class="card-header border-0 pt-6">
                        <div class="card-title">
                            <h3 class="fw-bold">Informações</h3>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <div class="alert alert-info d-flex align-items-center p-5 mb-0">
                            <i class="ki-duotone ki-information-5 fs-2hx text-info me-4">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                            <div class="d-flex flex-column">
                                <span class="fw-bold">Sobre os Logs</span>
                                <span class="text-gray-700 fs-7">
                                    Todos os emails enviados pelo sistema são registrados aqui. 
                                    Use os filtros para encontrar emails específicos ou identificar problemas de entrega.
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <!--end::Card-->
            </div>
            <!--end::Col-->
        </div>
        <!--end::Row-->
        
    </div>
</div>
<!--end::Post-->

<!--begin::Modal - Detalhes-->
<div class="modal fade" id="kt_modal_detalhes" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Detalhes do Email</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <div id="detalhes_content"></div>
            </div>
        </div>
    </div>
</div>
<!--end::Modal-->

<script>
"use strict";

// Ver detalhes do log
function verDetalhes(log) {
    let html = `
        <div class="mb-5">
            <label class="form-label fw-bold">Status</label>
            <div>
                ${log.status === 'sucesso' 
                    ? '<span class="badge badge-success fs-7">Sucesso</span>'
                    : '<span class="badge badge-danger fs-7">Erro</span>'}
            </div>
        </div>
        
        <div class="mb-5">
            <label class="form-label fw-bold">Destinatário</label>
            <div class="text-gray-600">${escapeHtml(log.email_destinatario)}</div>
            ${log.nome_destinatario ? `<small class="text-muted">${escapeHtml(log.nome_destinatario)}</small>` : ''}
        </div>
        
        <div class="mb-5">
            <label class="form-label fw-bold">Assunto</label>
            <div class="text-gray-600">${escapeHtml(log.assunto)}</div>
        </div>
        
        <div class="mb-5">
            <label class="form-label fw-bold">Template</label>
            <div class="text-gray-600">${log.template_nome ? escapeHtml(log.template_nome) + ' (' + escapeHtml(log.template_codigo) + ')' : '<span class="text-muted">Envio direto (sem template)</span>'}</div>
        </div>
        
        <div class="mb-5">
            <label class="form-label fw-bold">Origem</label>
            <div class="text-gray-600">${log.origem ? escapeHtml(log.origem) : '<span class="text-muted">-</span>'}</div>
        </div>
        
        <div class="mb-5">
            <label class="form-label fw-bold">Data/Hora</label>
            <div class="text-gray-600">${formatDate(log.created_at)}</div>
        </div>
    `;
    
    if (log.status === 'erro' && log.erro_mensagem) {
        html += `
            <div class="mb-5">
                <label class="form-label fw-bold text-danger">Mensagem de Erro</label>
                <div class="alert alert-danger">
                    <code>${escapeHtml(log.erro_mensagem)}</code>
                </div>
            </div>
        `;
    }
    
    if (log.usuario_nome) {
        html += `
            <div class="mb-5">
                <label class="form-label fw-bold">Enviado por</label>
                <div class="text-gray-600">${escapeHtml(log.usuario_nome)}</div>
            </div>
        `;
    }
    
    if (log.colaborador_nome) {
        html += `
            <div class="mb-5">
                <label class="form-label fw-bold">Colaborador Relacionado</label>
                <div class="text-gray-600">${escapeHtml(log.colaborador_nome)}</div>
            </div>
        `;
    }
    
    if (log.empresa_nome) {
        html += `
            <div class="mb-5">
                <label class="form-label fw-bold">Empresa</label>
                <div class="text-gray-600">${escapeHtml(log.empresa_nome)}</div>
            </div>
        `;
    }
    
    if (log.ip_origem) {
        html += `
            <div class="mb-0">
                <label class="form-label fw-bold">IP de Origem</label>
                <div class="text-gray-600">${escapeHtml(log.ip_origem)}</div>
            </div>
        `;
    }
    
    document.getElementById('detalhes_content').innerHTML = html;
    
    const modal = new bootstrap.Modal(document.getElementById('kt_modal_detalhes'));
    modal.show();
}

// Helpers
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleString('pt-BR');
}

// Inicializa DataTable apenas se houver dados
document.addEventListener('DOMContentLoaded', function() {
    var table = document.getElementById('kt_email_logs_table');
    if (!table) return;
    
    // Verifica se há dados reais (não apenas a linha de "nenhum registro")
    var rows = table.querySelectorAll('tbody tr');
    var hasData = rows.length > 0 && !rows[0].querySelector('[colspan]');
    
    if (hasData && typeof $ !== 'undefined' && $.fn.DataTable) {
        $('#kt_email_logs_table').DataTable({
            language: {
                processing: "Processando...",
                search: "Pesquisar:",
                lengthMenu: "Mostrar _MENU_ registros",
                info: "Mostrando _START_ a _END_ de _TOTAL_ registros",
                infoEmpty: "Mostrando 0 a 0 de 0 registros",
                infoFiltered: "(filtrado de _MAX_ registros no total)",
                loadingRecords: "Carregando...",
                zeroRecords: "Nenhum registro encontrado",
                emptyTable: "Nenhum dado disponível na tabela",
                paginate: {
                    first: "Primeiro",
                    previous: "Anterior",
                    next: "Próximo",
                    last: "Último"
                }
            },
            order: [[4, 'desc']],
            pageLength: 25,
            dom: '<"top"f>rt<"bottom"lip>',
            responsive: true
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
