<?php
/**
 * Lista de Candidaturas
 */

$page_title = 'Candidaturas';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/recrutamento_functions.php';

require_page_permission('candidaturas.php');

require_once __DIR__ . '/../includes/header.php';

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Filtros
$where = ["1=1"];
$params = [];

if ($usuario['role'] === 'RH') {
    if (isset($usuario['empresas_ids']) && is_array($usuario['empresas_ids'])) {
        $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
        $where[] = "v.empresa_id IN ($placeholders)";
        $params = array_merge($params, $usuario['empresas_ids']);
    }
}

$filtro_status = $_GET['status'] ?? '';
if ($filtro_status) {
    $where[] = "c.status = ?";
    $params[] = $filtro_status;
}

$filtro_vaga = $_GET['vaga_id'] ?? '';
if ($filtro_vaga) {
    $where[] = "c.vaga_id = ?";
    $params[] = (int)$filtro_vaga;
}

$filtro_busca = trim($_GET['busca'] ?? '');
if ($filtro_busca) {
    $where[] = "(cand.nome_completo LIKE ? OR cand.email LIKE ? OR v.titulo LIKE ?)";
    $params[] = "%$filtro_busca%";
    $params[] = "%$filtro_busca%";
    $params[] = "%$filtro_busca%";
}

// Paginação
$por_pagina = 25;
$pagina_atual = max(1, (int)($_GET['pagina'] ?? 1));
$offset = ($pagina_atual - 1) * $por_pagina;

$where_str = implode(' AND ', $where);

// Total de registros
$sql_count = "
    SELECT COUNT(*) 
    FROM candidaturas c
    INNER JOIN candidatos cand ON c.candidato_id = cand.id
    INNER JOIN vagas v ON c.vaga_id = v.id
    LEFT JOIN empresas e ON v.empresa_id = e.id
    WHERE $where_str
";
$stmt = $pdo->prepare($sql_count);
$stmt->execute($params);
$total_registros = (int)$stmt->fetchColumn();
$total_paginas = max(1, (int)ceil($total_registros / $por_pagina));
if ($pagina_atual > $total_paginas) $pagina_atual = $total_paginas;

$sql = "
    SELECT c.*,
           cand.nome_completo,
           cand.email,
           v.titulo as vaga_titulo,
           e.nome_fantasia as empresa_nome,
           u.nome as recrutador_nome,
           (SELECT COUNT(*) FROM entrevistas WHERE candidatura_id = c.id) as total_entrevistas,
           (SELECT COUNT(*) FROM candidaturas_etapas WHERE candidatura_id = c.id) as total_etapas,
           (SELECT COUNT(*) FROM candidaturas_anexos WHERE candidatura_id = c.id) as total_anexos,
           (SELECT COUNT(*) FROM onboarding WHERE candidatura_id = c.id) as tem_onboarding
    FROM candidaturas c
    INNER JOIN candidatos cand ON c.candidato_id = cand.id
    INNER JOIN vagas v ON c.vaga_id = v.id
    LEFT JOIN empresas e ON v.empresa_id = e.id
    LEFT JOIN usuarios u ON c.recrutador_responsavel = u.id
    WHERE $where_str
    ORDER BY c.created_at DESC
    LIMIT $por_pagina OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$candidaturas = $stmt->fetchAll();

// Helper para montar URL de paginação mantendo filtros
function url_pagina(int $pagina): string {
    $params = $_GET;
    $params['pagina'] = $pagina;
    unset($params['pagina']);
    $qs = http_build_query(array_filter(['status' => $params['status'] ?? '', 'vaga_id' => $params['vaga_id'] ?? '', 'busca' => $params['busca'] ?? '', 'pagina' => $pagina]));
    return 'candidaturas.php?' . $qs;
}

// Busca vagas para filtro
$stmt = $pdo->query("SELECT id, titulo FROM vagas ORDER BY titulo");
$vagas = $stmt->fetchAll();
?>
<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_content" class="content d-flex flex-column flex-column-fluid">
        <div class="post d-flex flex-column-fluid" id="kt_post">
            <div id="kt_content_container" class="container-xxl">
                
                <div class="card">
                    <div class="card-header border-0 pt-6">
                        <div class="card-title">
                            <h2>Candidaturas</h2>
                        </div>
                        <div class="card-toolbar">
                            <a href="kanban_selecao.php" class="btn btn-primary">
                                <i class="ki-duotone ki-chart-simple fs-2"></i>
                                Ver Kanban
                            </a>
                        </div>
                    </div>
                    
                    <div class="card-body pt-0">
                        <!-- Filtros -->
                        <div class="mb-5">
                            <form method="GET" class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold text-gray-600 fs-7">Buscar</label>
                                    <div class="position-relative">
                                        <i class="ki-duotone ki-magnifier fs-3 position-absolute top-50 translate-middle-y ms-3">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        <input type="text" name="busca" class="form-control form-control-solid ps-10"
                                               placeholder="Nome, e-mail ou vaga..."
                                               value="<?= htmlspecialchars($filtro_busca) ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold text-gray-600 fs-7">Status</label>
                                    <select name="status" class="form-select form-select-solid">
                                        <option value="">Todos os status</option>
                                        <option value="nova" <?= $filtro_status === 'nova' ? 'selected' : '' ?>>Nova</option>
                                        <option value="triagem" <?= $filtro_status === 'triagem' ? 'selected' : '' ?>>Triagem</option>
                                        <option value="entrevista" <?= $filtro_status === 'entrevista' ? 'selected' : '' ?>>Entrevista</option>
                                        <option value="avaliacao" <?= $filtro_status === 'avaliacao' ? 'selected' : '' ?>>Avaliação</option>
                                        <option value="aprovada" <?= $filtro_status === 'aprovada' ? 'selected' : '' ?>>Aprovada</option>
                                        <option value="reprovada" <?= $filtro_status === 'reprovada' ? 'selected' : '' ?>>Reprovada</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold text-gray-600 fs-7">Vaga</label>
                                    <select name="vaga_id" class="form-select form-select-solid">
                                        <option value="">Todas as vagas</option>
                                        <?php foreach ($vagas as $vaga): ?>
                                        <option value="<?= $vaga['id'] ?>" <?= $filtro_vaga == $vaga['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($vaga['titulo']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2 d-flex align-items-end gap-2">
                                    <button type="submit" class="btn btn-primary flex-grow-1">
                                        <i class="ki-duotone ki-magnifier fs-4"><span class="path1"></span><span class="path2"></span></i>
                                        Filtrar
                                    </button>
                                    <a href="candidaturas.php" class="btn btn-light" title="Limpar filtros">
                                        <i class="ki-duotone ki-arrows-circle fs-4"><span class="path1"></span><span class="path2"></span></i>
                                    </a>
                                </div>
                            </form>
                        </div>

                        <!-- Resumo dos resultados -->
                        <div class="d-flex align-items-center justify-content-between mb-4">
                            <span class="text-muted fs-7">
                                Mostrando <strong class="text-gray-800"><?= count($candidaturas) ?></strong> de <strong class="text-gray-800"><?= $total_registros ?></strong> candidatura(s)
                                <?php if ($filtro_busca || $filtro_status || $filtro_vaga): ?>
                                <span class="badge badge-light-primary ms-2">Filtros ativos</span>
                                <?php endif; ?>
                            </span>
                            <span class="text-muted fs-7">Página <?= $pagina_atual ?> de <?= $total_paginas ?></span>
                        </div>
                        
                        <!-- Tabela -->
                        <div class="table-responsive" id="tabelaCandidaturas">
                            <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                                <thead>
                                    <tr class="fw-bold text-muted">
                                        <th>Candidato</th>
                                        <th>Vaga</th>
                                        <th>Status</th>
                                        <th>Nota</th>
                                        <th>Recrutador</th>
                                        <th>Data</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($candidaturas as $candidatura): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($candidatura['nome_completo']) ?></strong>
                                            <br>
                                            <small class="text-muted"><?= htmlspecialchars($candidatura['email']) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($candidatura['vaga_titulo']) ?></td>
                                        <td>
                                            <?php
                                            $status_colors = [
                                                'nova' => 'primary',
                                                'triagem' => 'warning',
                                                'entrevista' => 'info',
                                                'avaliacao' => 'secondary',
                                                'aprovada' => 'success',
                                                'reprovada' => 'danger'
                                            ];
                                            $color = $status_colors[$candidatura['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge badge-light-<?= $color ?>"><?= ucfirst($candidatura['status']) ?></span>
                                        </td>
                                        <td>
                                            <?php if ($candidatura['nota_geral']): ?>
                                            <strong><?= $candidatura['nota_geral'] ?>/10</strong>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($candidatura['recrutador_nome'] ?? '-') ?></td>
                                        <td><?= date('d/m/Y', strtotime($candidatura['data_candidatura'])) ?></td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <a href="candidatura_view.php?id=<?= $candidatura['id'] ?>" class="btn btn-sm btn-light-primary">
                                                    Ver Detalhes
                                                </a>
                                                <button class="btn btn-sm btn-light-danger btn-excluir-candidatura" 
                                                        data-candidatura-id="<?= $candidatura['id'] ?>"
                                                        data-candidato-nome="<?= htmlspecialchars($candidatura['nome_completo']) ?>"
                                                        data-vaga-titulo="<?= htmlspecialchars($candidatura['vaga_titulo']) ?>"
                                                        data-status="<?= htmlspecialchars($candidatura['status']) ?>"
                                                        data-total-entrevistas="<?= $candidatura['total_entrevistas'] ?? 0 ?>"
                                                        data-total-etapas="<?= $candidatura['total_etapas'] ?? 0 ?>"
                                                        data-total-anexos="<?= $candidatura['total_anexos'] ?? 0 ?>"
                                                        data-tem-onboarding="<?= ($candidatura['tem_onboarding'] ?? 0) > 0 ? '1' : '0' ?>"
                                                        title="Excluir Candidatura">
                                                    <i class="ki-duotone ki-trash fs-6">
                                                        <span class="path1"></span>
                                                        <span class="path2"></span>
                                                        <span class="path3"></span>
                                                        <span class="path4"></span>
                                                        <span class="path5"></span>
                                                    </i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Paginação -->
                        <?php if ($total_paginas > 1): ?>
                        <div class="d-flex align-items-center justify-content-between mt-5 flex-wrap gap-3">
                            <div class="text-muted fs-7">
                                Total: <strong class="text-gray-800"><?= $total_registros ?></strong> candidatura(s)
                            </div>
                            <ul class="pagination pagination-outline mb-0">
                                <!-- Primeira / Anterior -->
                                <?php if ($pagina_atual > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?= url_pagina(1) ?>" title="Primeira">
                                        <i class="ki-duotone ki-double-left fs-6"><span class="path1"></span><span class="path2"></span></i>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="<?= url_pagina($pagina_atual - 1) ?>">
                                        <i class="ki-duotone ki-left fs-6"><span class="path1"></span></i>
                                    </a>
                                </li>
                                <?php endif; ?>

                                <!-- Páginas numéricas -->
                                <?php
                                $inicio = max(1, $pagina_atual - 2);
                                $fim = min($total_paginas, $pagina_atual + 2);
                                if ($inicio > 1): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                                <?php endif; ?>
                                <?php for ($p = $inicio; $p <= $fim; $p++): ?>
                                <li class="page-item <?= $p === $pagina_atual ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= url_pagina($p) ?>"><?= $p ?></a>
                                </li>
                                <?php endfor; ?>
                                <?php if ($fim < $total_paginas): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                                <?php endif; ?>

                                <!-- Próxima / Última -->
                                <?php if ($pagina_atual < $total_paginas): ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?= url_pagina($pagina_atual + 1) ?>">
                                        <i class="ki-duotone ki-right fs-6"><span class="path1"></span></i>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="<?= url_pagina($total_paginas) ?>" title="Última">
                                        <i class="ki-duotone ki-double-right fs-6"><span class="path1"></span><span class="path2"></span></i>
                                    </a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        <?php endif; ?>

                    </div>
                </div>
                
            </div>
        </div>
    </div>
</div>

<script>
// Excluir candidatura
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.btn-excluir-candidatura').forEach(btn => {
        btn.addEventListener('click', async function() {
            const candidaturaId = this.dataset.candidaturaId;
            const candidatoNome = this.dataset.candidatoNome;
            const vagaTitulo = this.dataset.vagaTitulo;
            const status = this.dataset.status;
            const totalEntrevistas = parseInt(this.dataset.totalEntrevistas || 0);
            const totalEtapas = parseInt(this.dataset.totalEtapas || 0);
            const totalAnexos = parseInt(this.dataset.totalAnexos || 0);
            const temOnboarding = this.dataset.temOnboarding === '1';
            
            let mensagemConfirmacao = `Deseja realmente excluir a candidatura?\n\n`;
            mensagemConfirmacao += `Candidato: ${candidatoNome}\n`;
            mensagemConfirmacao += `Vaga: ${vagaTitulo}\n`;
            mensagemConfirmacao += `Status: ${status}\n\n`;
            
            mensagemConfirmacao += `⚠️ ATENÇÃO: Esta ação excluirá permanentemente:\n`;
            mensagemConfirmacao += `- A candidatura\n`;
            
            if (totalEtapas > 0) {
                mensagemConfirmacao += `- ${totalEtapas} etapa(s) do processo\n`;
            }
            if (totalEntrevistas > 0) {
                mensagemConfirmacao += `- ${totalEntrevistas} entrevista(s)\n`;
            }
            if (totalAnexos > 0) {
                mensagemConfirmacao += `- ${totalAnexos} anexo(s) (currículos, etc.)\n`;
            }
            mensagemConfirmacao += `- Histórico e comentários\n`;
            mensagemConfirmacao += `- Respostas de formulários de cultura\n`;
            
            if (temOnboarding) {
                mensagemConfirmacao += `\n⚠️ Há um processo de onboarding vinculado (será mantido, mas ficará órfão)\n`;
            }
            
            if (status === 'aprovada') {
                mensagemConfirmacao += `\n🚨 CRÍTICO: Esta candidatura está APROVADA!\n\n`;
            }
            
            mensagemConfirmacao += `\nEsta ação não pode ser desfeita!`;
            
            if (!confirm(mensagemConfirmacao)) {
                return;
            }
            
            // Confirmação adicional se estiver aprovada
            if (status === 'aprovada') {
                if (!confirm(`ATENÇÃO: Você está prestes a excluir uma candidatura APROVADA!\n\nTem certeza absoluta que deseja continuar?`)) {
                    return;
                }
            }
            
            const btnOriginal = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
            
            try {
                const response = await fetch(`../api/recrutamento/candidaturas/excluir.php?id=${candidaturaId}`, {
                    method: 'DELETE'
                });
                
                const data = await response.json();
                
                if (data.success) {
                    let mensagemSucesso = 'Candidatura excluída com sucesso!';
                    if (data.dados_excluidos) {
                        const info = [];
                        if (data.dados_excluidos.entrevistas > 0) {
                            info.push(`${data.dados_excluidos.entrevistas} entrevista(s)`);
                        }
                        if (data.dados_excluidos.etapas > 0) {
                            info.push(`${data.dados_excluidos.etapas} etapa(s)`);
                        }
                        if (info.length > 0) {
                            mensagemSucesso += `\n\nTambém foram excluídos: ${info.join(', ')}.`;
                        }
                    }
                    alert(mensagemSucesso);
                    location.reload();
                } else {
                    alert('Erro: ' + data.message);
                    this.disabled = false;
                    this.innerHTML = btnOriginal;
                }
            } catch (error) {
                console.error('Erro ao excluir candidatura:', error);
                alert('Erro ao excluir candidatura. Verifique o console para mais detalhes.');
                this.disabled = false;
                this.innerHTML = btnOriginal;
            }
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

