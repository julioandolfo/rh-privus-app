<?php
/**
 * Painel de Engajamento - Página Principal
 */

$page_title = 'Painel de Engajamento';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('gestao_engajamento.php');

require_once __DIR__ . '/../includes/header.php';

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Filtros padrão
$empresa_id = $_GET['empresa_id'] ?? '';
$setor_id = $_GET['setor_id'] ?? '';
$lider_id = $_GET['lider_id'] ?? '';
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01'); // Primeiro dia do mês
$data_fim = $_GET['data_fim'] ?? date('Y-m-t'); // Último dia do mês
$status_colaboradores = $_GET['status_colaboradores'] ?? '';

// Busca empresas para filtro
$empresas = [];
if ($usuario['role'] === 'ADMIN') {
    $stmt = $pdo->query("SELECT id, nome_fantasia FROM empresas WHERE status = 'ativo' ORDER BY nome_fantasia");
    $empresas = $stmt->fetchAll();
} elseif ($usuario['role'] === 'RH') {
    if (isset($usuario['empresas_ids']) && is_array($usuario['empresas_ids'])) {
        $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
        $stmt = $pdo->prepare("SELECT id, nome_fantasia FROM empresas WHERE id IN ($placeholders) AND status = 'ativo' ORDER BY nome_fantasia");
        $stmt->execute($usuario['empresas_ids']);
        $empresas = $stmt->fetchAll();
    }
}

// Busca setores para filtro
$setores = [];
if ($empresa_id) {
    $stmt = $pdo->prepare("SELECT id, nome_setor FROM setores WHERE empresa_id = ? AND status = 'ativo' ORDER BY nome_setor");
    $stmt->execute([$empresa_id]);
    $setores = $stmt->fetchAll();
}

// Busca líderes para filtro
$lideres = [];
if ($setor_id || $empresa_id) {
    $where_lider = ["c.status = 'ativo'", "c.lider_id IS NOT NULL"];
    $params_lider = [];
    
    if ($setor_id) {
        $where_lider[] = "c.setor_id = ?";
        $params_lider[] = $setor_id;
    } elseif ($empresa_id) {
        $where_lider[] = "c.empresa_id = ?";
        $params_lider[] = $empresa_id;
    }
    
    $sql_lider = "SELECT DISTINCT c.lider_id, cl.nome_completo 
                  FROM colaboradores c
                  INNER JOIN colaboradores cl ON c.lider_id = cl.id
                  WHERE " . implode(' AND ', $where_lider) . "
                  ORDER BY cl.nome_completo";
    $stmt = $pdo->prepare($sql_lider);
    $stmt->execute($params_lider);
    $lideres = $stmt->fetchAll();
}

// Busca histórico anual para gráfico (últimos 12 meses)
$historico_mensal = [];
for ($i = 11; $i >= 0; $i--) {
    $mes = date('Y-m', strtotime("-$i months"));
    $mes_inicio = date('Y-m-01', strtotime("-$i months"));
    $mes_fim = date('Y-m-t', strtotime("-$i months"));
    
    // Monta condições WHERE
    $where_hist = ["c.status = 'ativo'"];
    $params_hist = [];
    
    if ($empresa_id) {
        $where_hist[] = "c.empresa_id = ?";
        $params_hist[] = $empresa_id;
    }
    if ($setor_id) {
        $where_hist[] = "c.setor_id = ?";
        $params_hist[] = $setor_id;
    }
    if ($lider_id) {
        $where_hist[] = "c.lider_id = ?";
        $params_hist[] = $lider_id;
    }
    
    $where_sql_hist = implode(' AND ', $where_hist);
    
    // Total de colaboradores no mês
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM colaboradores c WHERE $where_sql_hist");
    $stmt->execute($params_hist);
    $total_colab_mes = $stmt->fetch()['total'];
    
    // Colaboradores que acessaram no mês
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT c.id) as total
        FROM colaboradores c
        INNER JOIN acessos_historico ah ON (c.id = ah.colaborador_id OR EXISTS (
            SELECT 1 FROM usuarios u WHERE u.id = ah.usuario_id AND u.colaborador_id = c.id
        ))
        WHERE $where_sql_hist AND ah.data_acesso BETWEEN ? AND ?
    ");
    $params_acesso = array_merge($params_hist, [$mes_inicio, $mes_fim]);
    $stmt->execute($params_acesso);
    $acessaram_mes = $stmt->fetch()['total'];
    
    $percentual = $total_colab_mes > 0 ? round(($acessaram_mes / $total_colab_mes) * 100, 1) : 0;
    
    $historico_mensal[] = [
        'mes' => date('M/Y', strtotime("-$i months")),
        'mes_codigo' => $mes,
        'percentual' => $percentual,
        'acessaram' => $acessaram_mes,
        'total' => $total_colab_mes
    ];
}

// Busca engajamento por líder
$engajamento_lideres = [];
if ($lider_id) {
    // Se filtrou por líder específico, mostra só ele
    $where_lider_eng = ["c.lider_id = ?"];
    $params_lider_eng = [$lider_id];
} else {
    // Busca todos os líderes
    $where_lider_eng = ["c.lider_id IS NOT NULL"];
    $params_lider_eng = [];
    
    if ($empresa_id) {
        $where_lider_eng[] = "c.empresa_id = ?";
        $params_lider_eng[] = $empresa_id;
    }
    if ($setor_id) {
        $where_lider_eng[] = "c.setor_id = ?";
        $params_lider_eng[] = $setor_id;
    }
}

$sql_lider_eng = "
    SELECT 
        cl.id as lider_id,
        cl.nome_completo as lider_nome,
        s.nome_setor,
        COUNT(DISTINCT c.id) as total_liderados,
        COUNT(DISTINCT CASE 
            WHEN EXISTS (
                SELECT 1 FROM acessos_historico ah 
                WHERE (ah.colaborador_id = c.id OR EXISTS (
                    SELECT 1 FROM usuarios u WHERE u.id = ah.usuario_id AND u.colaborador_id = c.id
                ))
                AND ah.data_acesso BETWEEN ? AND ?
            ) THEN c.id 
        END) as liderados_acessaram,
        COUNT(DISTINCT CASE 
            WHEN NOT EXISTS (
                SELECT 1 FROM acessos_historico ah 
                WHERE (ah.colaborador_id = c.id OR EXISTS (
                    SELECT 1 FROM usuarios u WHERE u.id = ah.usuario_id AND u.colaborador_id = c.id
                ))
            ) THEN c.id 
        END) as liderados_nunca_acessaram
    FROM colaboradores c
    INNER JOIN colaboradores cl ON c.lider_id = cl.id
    LEFT JOIN setores s ON c.setor_id = s.id
    WHERE " . implode(' AND ', $where_lider_eng) . "
    GROUP BY cl.id, cl.nome_completo, s.nome_setor
    ORDER BY cl.nome_completo
";

$params_lider_eng_final = array_merge($params_lider_eng, [$data_inicio, $data_fim]);
$stmt = $pdo->prepare($sql_lider_eng);
$stmt->execute($params_lider_eng_final);
$engajamento_lideres = $stmt->fetchAll();
?>

<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_content" class="content d-flex flex-column flex-column-fluid">
        <div class="post d-flex flex-column-fluid" id="kt_post">
            <div id="kt_content_container" class="container-xxl">
                
                <!-- Card de Filtros -->
                <div class="card mb-5">
                    <div class="card-body">
                        <h1 class="mb-3">Painel de Engajamento</h1>
                        <p class="text-muted mb-4">O painel de engajamento calcula, em tempo real, o nível de engajamento da sua empresa, com relação aos feedbacks enviados, celebrações realizadas, humores respondidos, pesquisas criadas/respondidas e evolução de objetivos.</p>
                        
                        <hr>
                        
                        <form method="GET" id="form-filtros" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Unidade</label>
                                <select name="empresa_id" class="form-select" id="filtro-empresa">
                                    <option value="">Todos</option>
                                    <?php foreach ($empresas as $emp): ?>
                                    <option value="<?= $emp['id'] ?>" <?= $empresa_id == $emp['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($emp['nome_fantasia']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">Departamento</label>
                                <select name="setor_id" class="form-select" id="filtro-setor">
                                    <option value="">Todos</option>
                                    <?php foreach ($setores as $setor): ?>
                                    <option value="<?= $setor['id'] ?>" <?= $setor_id == $setor['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($setor['nome_setor']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">Liderados de:</label>
                                <select name="lider_id" class="form-select" id="filtro-lider">
                                    <option value="">Todos</option>
                                    <?php foreach ($lideres as $lider): ?>
                                    <option value="<?= $lider['lider_id'] ?>" <?= $lider_id == $lider['lider_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($lider['nome_completo']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">Data Inicial</label>
                                <input type="date" name="data_inicio" class="form-control" value="<?= htmlspecialchars($data_inicio) ?>">
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">Data Final</label>
                                <input type="date" name="data_fim" class="form-control" value="<?= htmlspecialchars($data_fim) ?>">
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">Colaboradores</label>
                                <select name="status_colaboradores" class="form-select">
                                    <option value="" <?= $status_colaboradores === '' ? 'selected' : '' ?>>Ativos e Desligados</option>
                                    <option value="0" <?= $status_colaboradores === '0' ? 'selected' : '' ?>>Ativos</option>
                                    <option value="2" <?= $status_colaboradores === '2' ? 'selected' : '' ?>>Desligados</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="button" class="btn btn-secondary me-2" id="btn-limpar">Limpar</button>
                                <button type="submit" class="btn btn-primary">Filtrar</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Card de Eficiência -->
                <div class="card mb-5">
                    <div class="card-body">
                        <h2 class="mb-4">Eficiência</h2>
                        <div class="row" id="eficiencia-container">
                            <div class="col-md-12 text-center">
                                <div class="spinner-border" role="status">
                                    <span class="visually-hidden">Carregando...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Cards de Dados -->
                <div class="row g-5 mb-5" id="cards-container">
                    <div class="col-md-12 text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                    </div>
                </div>
                
                <!-- Engajamento por Módulo -->
                <div class="card mb-5">
                    <div class="card-body">
                        <h2 class="mb-4">Engajamento por módulo</h2>
                        <div class="row" id="modulos-container">
                            <div class="col-md-12 text-center">
                                <div class="spinner-border" role="status">
                                    <span class="visually-hidden">Carregando...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Gráfico de Histórico Anual -->
                <div class="card mb-5">
                    <div class="card-body">
                        <h2 class="mb-4">Histórico anual de engajamento</h2>
                        <div class="chart-container" style="position: relative; height: 400px;">
                            <canvas id="kt_chart_historico_engajamento"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Tabela Engajamento por Líder -->
                <div class="card">
                    <div class="card-header border-0 pt-6">
                        <div class="card-title">
                            <h2>Engajamento por líder</h2>
                        </div>
                        <div class="card-toolbar">
                            <button type="button" class="btn btn-primary" id="btn-exportar">Exportar</button>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <div class="table-responsive">
                            <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3" id="tabela-lideres">
                                <thead>
                                    <tr class="fw-bold text-muted">
                                        <th>Nome</th>
                                        <th>Departamento</th>
                                        <th>Total liderados</th>
                                        <th>Liderados que acessaram</th>
                                        <th>Porcentagem (%)</th>
                                        <th>Liderados que nunca acessaram</th>
                                        <th>Porcentagem (%)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($engajamento_lideres as $lider_eng): ?>
                                    <?php
                                    $percent_acessaram = $lider_eng['total_liderados'] > 0 
                                        ? round(($lider_eng['liderados_acessaram'] / $lider_eng['total_liderados']) * 100, 1) 
                                        : 0;
                                    $percent_nunca = $lider_eng['total_liderados'] > 0 
                                        ? round(($lider_eng['liderados_nunca_acessaram'] / $lider_eng['total_liderados']) * 100, 1) 
                                        : 0;
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($lider_eng['lider_nome']) ?></td>
                                        <td><?= htmlspecialchars($lider_eng['nome_setor'] ?? '-') ?></td>
                                        <td><?= $lider_eng['total_liderados'] ?></td>
                                        <td><?= $lider_eng['liderados_acessaram'] ?></td>
                                        <td>
                                            <span class="badge badge-<?= $percent_acessaram >= 70 ? 'success' : ($percent_acessaram >= 50 ? 'warning' : 'danger') ?>">
                                                <?= $percent_acessaram ?>%
                                            </span>
                                        </td>
                                        <td><?= $lider_eng['liderados_nunca_acessaram'] ?></td>
                                        <td>
                                            <span class="badge badge-<?= $percent_nunca <= 10 ? 'success' : ($percent_nunca <= 30 ? 'warning' : 'danger') ?>">
                                                <?= $percent_nunca ?>%
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
</div>

<!--begin::Chart Scripts-->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Carrega dados do painel
function carregarDadosPainel() {
    const form = document.getElementById('form-filtros');
    const formData = new FormData(form);
    const params = new URLSearchParams();
    
    for (const [key, value] of formData.entries()) {
        if (value) params.append(key, value);
    }
    
    fetch('<?= get_base_url() ?>/api/engajamento/dados.php?' + params.toString())
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            renderizarEficiencia(data.data);
            renderizarCards(data.data);
            renderizarModulos(data.data);
        }
    })
    .catch(error => {
        console.error('Erro ao carregar dados:', error);
    });
}

// Renderiza seção de eficiência
function renderizarEficiencia(dados) {
    const container = document.getElementById('eficiencia-container');
    const totalColab = dados.total_colaboradores || 0;
    
    const eficiencia = dados.eficiencia || {};
    
    container.innerHTML = `
        <div class="col-md-2 text-center">
            <h4>Seu time</h4>
            <div style="margin-top: 10px">
                <span>Colaboradores:</span>
                <span class="fw-bold">${totalColab}</span>
            </div>
        </div>
        <div class="col-md-2 text-center">
            <h4>Feedbacks</h4>
            <div style="margin-top: 10px">
                <span>Eficiência: </span>
                <span class="fw-bold">${eficiencia.feedbacks || 0}%</span>
            </div>
        </div>
        <div class="col-md-2 text-center">
            <h4>1:1</h4>
            <div style="margin-top: 10px">
                <span>Eficiência: </span>
                <span class="fw-bold">${eficiencia['1on1'] || 0}%</span>
            </div>
        </div>
        <div class="col-md-2 text-center">
            <h4>Celebrações</h4>
            <div style="margin-top: 10px">
                <span>Eficiência: </span>
                <span class="fw-bold">${eficiencia.celebracoes || 0}%</span>
            </div>
        </div>
        <div class="col-md-2 text-center">
            <h4>Desenvolvimento</h4>
            <div style="margin-top: 10px">
                <span>Eficiência: </span>
                <span class="fw-bold">${eficiencia.pdi || 0}%</span>
            </div>
        </div>
    `;
}

// Renderiza cards de dados
function renderizarCards(dados) {
    const container = document.getElementById('cards-container');
    const cards = dados.cards || {};
    
    container.innerHTML = `
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h3 class="mb-3">${cards.humores?.total || 0}</h3>
                    <p class="text-muted mb-0">Humores Respondidos</p>
                    <small class="text-muted">por ${cards.humores?.colaboradores || 0} Colaboradores</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h3 class="mb-3">${cards.celebracoes?.total || 0}</h3>
                    <p class="text-muted mb-0">Celebrações</p>
                    <small class="text-muted">por ${cards.celebracoes?.colaboradores || 0} Colaboradores</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h3 class="mb-3">${cards.feedbacks?.total || 0}</h3>
                    <p class="text-muted mb-0">Feedbacks</p>
                    <small class="text-muted">por ${cards.feedbacks?.colaboradores || 0} Colaboradores</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h3 class="mb-3">${cards.engajados?.percentual || 0}%</h3>
                    <p class="text-muted mb-0">Engajados</p>
                    <small class="text-muted">por ${cards.engajados?.colaboradores || 0} Colaboradores</small>
                </div>
            </div>
        </div>
    `;
}

// Renderiza engajamento por módulo
function renderizarModulos(dados) {
    const container = document.getElementById('modulos-container');
    const modulos_data = dados.modulos || {};
    
    const modulos = [
        { nome: 'Acessos', valor: modulos_data.acessos || 0 },
        { nome: 'Feedbacks', valor: modulos_data.feedbacks || 0 },
        { nome: 'Celebrações', valor: modulos_data.celebracoes || 0 },
        { nome: 'Reuniões 1:1', valor: modulos_data['1on1'] || 0 },
        { nome: 'Humores Respondidos', valor: modulos_data.humores || 0 },
        { nome: 'Pesquisa de Satisfação', valor: modulos_data.pesquisas_satisfacao || 0 },
        { nome: 'Pesquisa Rápida', valor: modulos_data.pesquisas_rapidas || 0 },
        { nome: 'PDI', valor: modulos_data.pdi || 0 }
    ];
    
    let html = '<div class="col-md-6">';
    modulos.slice(0, 4).forEach(modulo => {
        html += `
            <div class="row mb-3">
                <div class="col-md-4">
                    <div class="fw-bold">${modulo.nome}</div>
                </div>
                <div class="col-md-8">
                    <div class="progress" style="height: 25px;">
                        <div class="progress-bar" role="progressbar" 
                             style="width: ${modulo.valor}%; min-width: 20px;" 
                             aria-valuenow="${modulo.valor}" 
                             aria-valuemin="0" 
                             aria-valuemax="100">
                            ${modulo.valor}%
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    html += '</div><div class="col-md-6">';
    modulos.slice(4).forEach(modulo => {
        html += `
            <div class="row mb-3">
                <div class="col-md-4">
                    <div class="fw-bold">${modulo.nome}</div>
                </div>
                <div class="col-md-8">
                    <div class="progress" style="height: 25px;">
                        <div class="progress-bar" role="progressbar" 
                             style="width: ${modulo.valor}%; min-width: 20px;" 
                             aria-valuenow="${modulo.valor}" 
                             aria-valuemin="0" 
                             aria-valuemax="100">
                            ${modulo.valor}%
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    html += '</div>';
    
    container.innerHTML = html;
}

// Gráfico de histórico anual
const historicoData = <?= json_encode($historico_mensal) ?>;
const ctxHistorico = document.getElementById('kt_chart_historico_engajamento');
if (ctxHistorico) {
    new Chart(ctxHistorico, {
        type: 'line',
        data: {
            labels: historicoData.map(item => item.mes),
            datasets: [{
                label: 'Engajamento (%)',
                data: historicoData.map(item => item.percentual),
                borderColor: 'rgb(0, 158, 247)',
                backgroundColor: 'rgba(0, 158, 247, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        afterLabel: function(context) {
                            const index = context.dataIndex;
                            return `Acessaram: ${historicoData[index].acessaram} / Total: ${historicoData[index].total}`;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    ticks: {
                        callback: function(value) {
                            return value + '%';
                        }
                    }
                }
            }
        }
    });
}

// Carrega setores quando muda empresa
document.getElementById('filtro-empresa').addEventListener('change', function() {
    const empresaId = this.value;
    const selectSetor = document.getElementById('filtro-setor');
    
    if (!empresaId) {
        selectSetor.innerHTML = '<option value="">Todos</option>';
        return;
    }
    
    fetch('<?= get_base_url() ?>/api/get_setores.php?empresa_id=' + empresaId)
    .then(response => response.json())
    .then(data => {
        selectSetor.innerHTML = '<option value="">Todos</option>';
        data.forEach(setor => {
            const option = document.createElement('option');
            option.value = setor.id;
            option.textContent = setor.nome_setor;
            selectSetor.appendChild(option);
        });
    });
});

// Limpar filtros
document.getElementById('btn-limpar').addEventListener('click', function() {
    window.location.href = 'gestao_engajamento.php';
});

// Carrega dados ao carregar página
document.addEventListener('DOMContentLoaded', function() {
    carregarDadosPainel();
});

// Exportar tabela
document.getElementById('btn-exportar').addEventListener('click', function() {
    // TODO: Implementar exportação para Excel/CSV
    alert('Funcionalidade de exportação será implementada em breve!');
});
</script>
<!--end::Chart Scripts-->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

