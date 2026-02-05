<?php
/**
 * Dashboard de Contratos - Kanban
 */

$page_title = 'Contratos';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('contratos.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Processa exclusão de contratos
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    
    if ($acao === 'deletar_contrato') {
        $contrato_id = intval($_POST['contrato_id'] ?? 0);
        if ($contrato_id > 0) {
            try {
                // Verifica se o contrato está cancelado ou expirado
                $stmt = $pdo->prepare("SELECT status, pdf_path FROM contratos WHERE id = ?");
                $stmt->execute([$contrato_id]);
                $contrato = $stmt->fetch();
                
                if ($contrato && in_array($contrato['status'], ['cancelado', 'expirado'])) {
                    // Deleta signatários
                    $stmt = $pdo->prepare("DELETE FROM contratos_signatarios WHERE contrato_id = ?");
                    $stmt->execute([$contrato_id]);
                    
                    // Deleta eventos
                    $stmt = $pdo->prepare("DELETE FROM contratos_eventos WHERE contrato_id = ?");
                    $stmt->execute([$contrato_id]);
                    
                    // Deleta PDF se existir
                    if ($contrato['pdf_path'] && file_exists(__DIR__ . '/../' . $contrato['pdf_path'])) {
                        @unlink(__DIR__ . '/../' . $contrato['pdf_path']);
                    }
                    
                    // Deleta contrato
                    $stmt = $pdo->prepare("DELETE FROM contratos WHERE id = ?");
                    $stmt->execute([$contrato_id]);
                    
                    redirect('contratos.php', 'Contrato excluído com sucesso!', 'success');
                } else {
                    redirect('contratos.php', 'Apenas contratos cancelados ou expirados podem ser excluídos.', 'error');
                }
            } catch (Exception $e) {
                redirect('contratos.php', 'Erro ao excluir contrato: ' . $e->getMessage(), 'error');
            }
        }
    } elseif ($acao === 'limpar_cancelados') {
        try {
            // Busca todos os cancelados
            $stmt = $pdo->query("SELECT id, pdf_path FROM contratos WHERE status = 'cancelado'");
            $cancelados = $stmt->fetchAll();
            
            foreach ($cancelados as $c) {
                // Deleta signatários
                $pdo->prepare("DELETE FROM contratos_signatarios WHERE contrato_id = ?")->execute([$c['id']]);
                // Deleta eventos
                $pdo->prepare("DELETE FROM contratos_eventos WHERE contrato_id = ?")->execute([$c['id']]);
                // Deleta PDF
                if ($c['pdf_path'] && file_exists(__DIR__ . '/../' . $c['pdf_path'])) {
                    @unlink(__DIR__ . '/../' . $c['pdf_path']);
                }
            }
            
            // Deleta contratos
            $pdo->exec("DELETE FROM contratos WHERE status = 'cancelado'");
            
            redirect('contratos.php', count($cancelados) . ' contrato(s) cancelado(s) excluído(s)!', 'success');
        } catch (Exception $e) {
            redirect('contratos.php', 'Erro ao limpar cancelados: ' . $e->getMessage(), 'error');
        }
    } elseif ($acao === 'limpar_expirados') {
        try {
            // Busca todos os expirados
            $stmt = $pdo->query("SELECT id, pdf_path FROM contratos WHERE status = 'expirado'");
            $expirados = $stmt->fetchAll();
            
            foreach ($expirados as $c) {
                // Deleta signatários
                $pdo->prepare("DELETE FROM contratos_signatarios WHERE contrato_id = ?")->execute([$c['id']]);
                // Deleta eventos
                $pdo->prepare("DELETE FROM contratos_eventos WHERE contrato_id = ?")->execute([$c['id']]);
                // Deleta PDF
                if ($c['pdf_path'] && file_exists(__DIR__ . '/../' . $c['pdf_path'])) {
                    @unlink(__DIR__ . '/../' . $c['pdf_path']);
                }
            }
            
            // Deleta contratos
            $pdo->exec("DELETE FROM contratos WHERE status = 'expirado'");
            
            redirect('contratos.php', count($expirados) . ' contrato(s) expirado(s) excluído(s)!', 'success');
        } catch (Exception $e) {
            redirect('contratos.php', 'Erro ao limpar expirados: ' . $e->getMessage(), 'error');
        }
    }
}

// Filtros
$filtro_colaborador = $_GET['colaborador'] ?? '';
$filtro_status = $_GET['status'] ?? '';

// Monta query com filtros
$where = [];
$params = [];

// Restrições por role
if ($usuario['role'] === 'RH') {
    // RH vê apenas colaboradores das empresas dele
    $empresas_ids = $usuario['empresas_ids'] ?? [];
    if (!empty($empresas_ids)) {
        $placeholders = implode(',', array_fill(0, count($empresas_ids), '?'));
        $where[] = "c.empresa_id IN ($placeholders)";
        $params = array_merge($params, $empresas_ids);
    }
} elseif ($usuario['role'] === 'GESTOR') {
    // GESTOR vê apenas colaboradores do seu setor
    $stmt = $pdo->prepare("SELECT setor_id FROM usuarios WHERE id = ?");
    $stmt->execute([$usuario['id']]);
    $user_data = $stmt->fetch();
    if ($user_data && $user_data['setor_id']) {
        $where[] = "c.setor_id = ?";
        $params[] = $user_data['setor_id'];
    }
}

if ($filtro_colaborador) {
    $where[] = "c.colaborador_id = ?";
    $params[] = $filtro_colaborador;
}

if ($filtro_status) {
    $where[] = "c.status = ?";
    $params[] = $filtro_status;
}

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Busca contratos
$sql = "
    SELECT c.*, 
           col.nome_completo as colaborador_nome,
           col.cpf as colaborador_cpf,
           u.nome as criado_por_nome,
           t.nome as template_nome,
           (SELECT COUNT(*) FROM contratos_signatarios cs WHERE cs.contrato_id = c.id AND cs.assinado = 0) as assinaturas_pendentes,
           (SELECT COUNT(*) FROM contratos_signatarios cs WHERE cs.contrato_id = c.id) as total_signatarios
    FROM contratos c
    INNER JOIN colaboradores col ON c.colaborador_id = col.id
    LEFT JOIN usuarios u ON c.criado_por_usuario_id = u.id
    LEFT JOIN contratos_templates t ON c.template_id = t.id
    $where_sql
    ORDER BY c.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$contratos = $stmt->fetchAll();

// Agrupa por status para o Kanban
$kanban = [
    'rascunho' => [],
    'enviado' => [],
    'aguardando' => [],
    'assinado' => [],
    'cancelado' => [],
    'expirado' => []
];

foreach ($contratos as $contrato) {
    $status = $contrato['status'];
    if (isset($kanban[$status])) {
        $kanban[$status][] = $contrato;
    }
}

// Estatísticas
$stats = [
    'total' => count($contratos),
    'aguardando' => count($kanban['aguardando']),
    'assinados' => count($kanban['assinado']),
    'rascunhos' => count($kanban['rascunho'])
];

// Busca colaboradores para filtro
$stmt_colab = $pdo->query("SELECT id, nome_completo FROM colaboradores WHERE status = 'ativo' ORDER BY nome_completo LIMIT 100");
$colaboradores_filtro = $stmt_colab->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Contratos</h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Contratos</li>
            </ul>
        </div>
        <div class="d-flex align-items-center gap-2 gap-lg-3">
            <?php if (can_access_page('contrato_templates.php')): ?>
            <a href="contrato_templates.php" class="btn btn-light-primary">
                <i class="ki-duotone ki-file fs-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                </i>
                Templates
            </a>
            <?php endif; ?>
            <a href="contrato_add.php" class="btn btn-primary">
                <i class="ki-duotone ki-plus fs-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                Novo Contrato
            </a>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <!--begin::Card - Estatísticas-->
        <div class="card mb-5">
            <div class="card-body pt-5">
                <div class="row">
                    <div class="col-md-3">
                        <div class="d-flex flex-column">
                            <span class="text-muted fs-7 fw-semibold mb-2">Total de Contratos</span>
                            <span class="text-gray-900 fw-bold fs-2"><?= $stats['total'] ?></span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="d-flex flex-column">
                            <span class="text-muted fs-7 fw-semibold mb-2">Aguardando Assinatura</span>
                            <span class="text-gray-900 fw-bold fs-2"><?= $stats['aguardando'] ?></span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="d-flex flex-column">
                            <span class="text-muted fs-7 fw-semibold mb-2">Assinados</span>
                            <span class="text-gray-900 fw-bold fs-2"><?= $stats['assinados'] ?></span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="d-flex flex-column">
                            <span class="text-muted fs-7 fw-semibold mb-2">Rascunhos</span>
                            <span class="text-gray-900 fw-bold fs-2"><?= $stats['rascunhos'] ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!--end::Card-->
        
        <!--begin::Card - Filtros-->
        <div class="card mb-5">
            <div class="card-body pt-5">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Colaborador</label>
                        <select name="colaborador" class="form-select form-select-solid">
                            <option value="">Todos</option>
                            <?php foreach ($colaboradores_filtro as $colab): ?>
                            <option value="<?= $colab['id'] ?>" <?= $filtro_colaborador == $colab['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($colab['nome_completo']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select form-select-solid">
                            <option value="">Todos</option>
                            <option value="rascunho" <?= $filtro_status == 'rascunho' ? 'selected' : '' ?>>Rascunho</option>
                            <option value="enviado" <?= $filtro_status == 'enviado' ? 'selected' : '' ?>>Enviado</option>
                            <option value="aguardando" <?= $filtro_status == 'aguardando' ? 'selected' : '' ?>>Aguardando</option>
                            <option value="assinado" <?= $filtro_status == 'assinado' ? 'selected' : '' ?>>Assinado</option>
                            <option value="cancelado" <?= $filtro_status == 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-light-primary me-2">Filtrar</button>
                        <a href="contratos.php" class="btn btn-light">Limpar</a>
                    </div>
                </form>
            </div>
        </div>
        <!--end::Card-->
        
        <!--begin::Card - Kanban-->
        <div class="card">
            <div class="card-body pt-5">
                <div class="row g-5" id="kanban_contratos">
                    <!-- Rascunho -->
                    <div class="col-lg-2">
                        <div class="d-flex flex-column">
                            <h4 class="text-gray-800 fw-bold mb-3">
                                📝 Rascunho
                                <span class="badge badge-light-primary ms-2"><?= count($kanban['rascunho']) ?></span>
                            </h4>
                            <div class="kanban-column" data-status="rascunho">
                                <?php foreach ($kanban['rascunho'] as $contrato): ?>
                                <?php include __DIR__ . '/../includes/contrato_card.php'; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Enviado -->
                    <div class="col-lg-2">
                        <div class="d-flex flex-column">
                            <h4 class="text-gray-800 fw-bold mb-3">
                                📤 Enviado
                                <span class="badge badge-light-info ms-2"><?= count($kanban['enviado']) ?></span>
                            </h4>
                            <div class="kanban-column" data-status="enviado">
                                <?php foreach ($kanban['enviado'] as $contrato): ?>
                                <?php include __DIR__ . '/../includes/contrato_card.php'; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Aguardando -->
                    <div class="col-lg-2">
                        <div class="d-flex flex-column">
                            <h4 class="text-gray-800 fw-bold mb-3">
                                ⏳ Aguardando
                                <span class="badge badge-light-warning ms-2"><?= count($kanban['aguardando']) ?></span>
                            </h4>
                            <div class="kanban-column" data-status="aguardando">
                                <?php foreach ($kanban['aguardando'] as $contrato): ?>
                                <?php include __DIR__ . '/../includes/contrato_card.php'; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Assinado -->
                    <div class="col-lg-2">
                        <div class="d-flex flex-column">
                            <h4 class="text-gray-800 fw-bold mb-3">
                                ✅ Assinado
                                <span class="badge badge-light-success ms-2"><?= count($kanban['assinado']) ?></span>
                            </h4>
                            <div class="kanban-column" data-status="assinado">
                                <?php foreach ($kanban['assinado'] as $contrato): ?>
                                <?php include __DIR__ . '/../includes/contrato_card.php'; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Cancelado -->
                    <div class="col-lg-2">
                        <div class="d-flex flex-column">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h4 class="text-gray-800 fw-bold mb-0">
                                    ❌ Cancelado
                                    <span class="badge badge-light-danger ms-2"><?= count($kanban['cancelado']) ?></span>
                                </h4>
                                <?php if (count($kanban['cancelado']) > 0): ?>
                                <button type="button" class="btn btn-sm btn-light-danger btn-limpar-todos" 
                                        data-acao="limpar_cancelados" 
                                        data-quantidade="<?= count($kanban['cancelado']) ?>"
                                        title="Excluir todos cancelados">
                                    <i class="ki-duotone ki-trash fs-6">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                        <span class="path4"></span>
                                        <span class="path5"></span>
                                    </i>
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="kanban-column" data-status="cancelado">
                                <?php foreach ($kanban['cancelado'] as $contrato): ?>
                                <?php include __DIR__ . '/../includes/contrato_card.php'; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Expirado -->
                    <div class="col-lg-2">
                        <div class="d-flex flex-column">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h4 class="text-gray-800 fw-bold mb-0">
                                    ⚠️ Expirado
                                    <span class="badge badge-light-secondary ms-2"><?= count($kanban['expirado']) ?></span>
                                </h4>
                                <?php if (count($kanban['expirado']) > 0): ?>
                                <button type="button" class="btn btn-sm btn-light-warning btn-limpar-todos" 
                                        data-acao="limpar_expirados" 
                                        data-quantidade="<?= count($kanban['expirado']) ?>"
                                        title="Excluir todos expirados">
                                    <i class="ki-duotone ki-trash fs-6">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                        <span class="path4"></span>
                                        <span class="path5"></span>
                                    </i>
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="kanban-column" data-status="expirado">
                                <?php foreach ($kanban['expirado'] as $contrato): ?>
                                <?php include __DIR__ . '/../includes/contrato_card.php'; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!--end::Card-->
        
    </div>
</div>
<!--end::Post-->

<style>
.kanban-column {
    min-height: 200px;
}

.contrato-card {
    background: var(--bs-body-bg);
    border: 1px solid var(--bs-border-color);
    border-radius: 8px;
    padding: 18px;
    margin-bottom: 14px;
    cursor: pointer;
    transition: all 0.3s;
    min-height: 220px;
    display: flex;
    flex-direction: column;
}

.contrato-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.contrato-card a.text-gray-800 {
    word-wrap: break-word;
    overflow-wrap: break-word;
    line-height: 1.4;
    max-height: 2.8em;
    overflow: hidden;
}

.contrato-card .flex-grow-1 {
    margin-bottom: 8px;
}

.contrato-card .d-flex.gap-2.mt-3 {
    margin-top: auto !important;
    gap: 0.5rem !important;
}

.contrato-card .btn-sm {
    padding: 0.4rem 0.5rem;
    font-size: 0.8rem;
    white-space: nowrap;
    min-width: 60px;
}

.contrato-card .flex-fill {
    flex: 1 1 0;
    min-width: 0;
}

/* Modo escuro */
[data-bs-theme="dark"] .contrato-card {
    background: var(--bs-gray-200);
    border-color: var(--bs-gray-300);
}

[data-bs-theme="dark"] .contrato-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
}
</style>

<!-- Formulário oculto para exclusões -->
<form id="formLimpeza" method="POST" style="display: none;">
    <input type="hidden" name="acao" id="acaoLimpeza" value="">
</form>

<form id="formDeletarContrato" method="POST" style="display: none;">
    <input type="hidden" name="acao" value="deletar_contrato">
    <input type="hidden" name="contrato_id" id="contratoIdDeletar" value="">
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Botões de limpar todos
    document.querySelectorAll('.btn-limpar-todos').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const acao = this.dataset.acao;
            const quantidade = this.dataset.quantidade;
            const tipo = acao === 'limpar_cancelados' ? 'cancelados' : 'expirados';
            
            Swal.fire({
                title: 'Confirmar exclusão',
                html: `Deseja excluir <strong>${quantidade}</strong> contrato(s) ${tipo}?<br><br><small class="text-muted">Esta ação não pode ser desfeita.</small>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sim, excluir todos',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('acaoLimpeza').value = acao;
                    document.getElementById('formLimpeza').submit();
                }
            });
        });
    });
    
    // Função global para deletar contrato individual
    window.deletarContrato = function(id, nome) {
        Swal.fire({
            title: 'Confirmar exclusão',
            html: `Deseja excluir o contrato <strong>${nome}</strong>?<br><br><small class="text-muted">Esta ação não pode ser desfeita.</small>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sim, excluir',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('contratoIdDeletar').value = id;
                document.getElementById('formDeletarContrato').submit();
            }
        });
    };
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

