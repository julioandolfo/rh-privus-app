<?php
/**
 * Controle de Horas Online (sugestão / ferramenta auxiliar)
 * NÃO ARMAZENA NADA NO BANCO DE DADOS — usa apenas localStorage do navegador.
 * É apenas um auxílio para o colaborador controlar suas horas e exportar como CSV.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_login();

$pdo = getDB();
$usuario = $_SESSION['usuario'];

if (($usuario['role'] ?? '') !== 'COLABORADOR' || empty($usuario['colaborador_id'])) {
    $_SESSION['error'] = 'Apenas colaboradores podem acessar esta página.';
    header('Location: dashboard.php');
    exit;
}

$colaborador_id = (int)$usuario['colaborador_id'];

// Busca apenas valor_hora para sugerir cálculo (não usa para nada além de exibição)
$stmt = $pdo->prepare("SELECT valor_hora FROM colaboradores WHERE id = ?");
$stmt->execute([$colaborador_id]);
$colab = $stmt->fetch();
$valor_hora_sugestao = (float)($colab['valor_hora'] ?? 0);

$page_title = 'Controle de Horas Online';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="toolbar mb-5">
    <div class="container-fluid d-flex flex-stack">
        <div class="page-title">
            <h1 class="text-gray-900 fw-bold fs-3 mb-0">Controle de Horas Online</h1>
            <span class="text-muted fs-7">Ferramenta auxiliar para registrar suas horas trabalhadas</span>
        </div>
        <a href="solicitar_pagamento_pj.php" class="btn btn-light">
            <i class="ki-duotone ki-arrow-left fs-2"><span class="path1"></span><span class="path2"></span></i>
            Voltar para Solicitar Pagamento
        </a>
    </div>
</div>

<div class="container-fluid">

    <!-- Aviso destacado -->
    <div class="alert alert-warning d-flex align-items-start mb-5">
        <i class="ki-duotone ki-information-5 fs-2x me-3 mt-1">
            <span class="path1"></span><span class="path2"></span><span class="path3"></span>
        </i>
        <div>
            <h4 class="mb-2 text-warning">⚠️ Atenção — Leia antes de usar</h4>
            <ul class="mb-0">
                <li><strong>Esta página é apenas uma sugestão / ferramenta auxiliar</strong> para te ajudar a controlar suas horas trabalhadas durante o mês.</li>
                <li><strong>Os dados NÃO são armazenados no sistema</strong>. Tudo fica salvo apenas no seu navegador (localStorage).</li>
                <li><strong>O uso desta ferramenta é totalmente opcional</strong>. Você pode controlar suas horas como preferir (planilha própria, anotação, app de terceiros, etc).</li>
                <li><strong>O fechamento mensal continua sendo de sua inteira responsabilidade</strong>. Ao final do mês, você deve enviar a planilha CSV final em "Solicitar Pagamento".</li>
                <li>Se você limpar o cache do navegador, trocar de máquina ou usar navegação privada, <strong>os dados serão perdidos</strong>.</li>
                <li>Você pode exportar os registros como CSV a qualquer momento e enviar diretamente em "Solicitar Pagamento".</li>
            </ul>
        </div>
    </div>

    <!-- Cabeçalho do mês + ações -->
    <div class="card mb-5">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-bold">Mês de Referência</label>
                    <input type="month" id="filtro_mes" class="form-control form-control-solid" value="<?= date('Y-m') ?>" />
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Valor da Hora (R$)</label>
                    <input type="text" id="valor_hora_calc" class="form-control form-control-solid" value="<?= $valor_hora_sugestao > 0 ? number_format($valor_hora_sugestao, 2, ',', '.') : '' ?>" placeholder="0,00" />
                    <small class="text-muted">Apenas para cálculo do total</small>
                </div>
                <div class="col-md-6 text-end">
                    <button type="button" class="btn btn-light-success me-2" onclick="exportarCsv()">
                        <i class="ki-duotone ki-file-down fs-2"><span class="path1"></span><span class="path2"></span></i>
                        Exportar CSV
                    </button>
                    <button type="button" class="btn btn-light-danger" onclick="limparMes()">
                        <i class="ki-duotone ki-trash fs-2"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></i>
                        Limpar este mês
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Resumo -->
    <div class="row mb-5">
        <div class="col-md-4">
            <div class="card bg-light-primary">
                <div class="card-body">
                    <div class="text-muted fs-7">Total de Registros</div>
                    <div class="fw-bold fs-2" id="resumo_registros">0</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-light-info">
                <div class="card-body">
                    <div class="text-muted fs-7">Total de Horas</div>
                    <div class="fw-bold fs-2 text-info" id="resumo_horas">0,00 h</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-light-success">
                <div class="card-body">
                    <div class="text-muted fs-7">Valor Total Estimado</div>
                    <div class="fw-bold fs-2 text-success" id="resumo_valor">R$ 0,00</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Formulário de novo registro -->
    <div class="card mb-5">
        <div class="card-header"><h3 class="card-title">Adicionar Registro</h3></div>
        <div class="card-body">
            <form id="form_registro" onsubmit="adicionarRegistro(event)">
                <input type="hidden" id="registro_id" value="" />
                <div class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label required">Data</label>
                        <input type="date" id="r_data" class="form-control form-control-solid" required />
                    </div>
                    <div class="col-md-2">
                        <label class="form-label required">Início</label>
                        <input type="time" id="r_inicio" class="form-control form-control-solid" required />
                    </div>
                    <div class="col-md-2">
                        <label class="form-label required">Fim</label>
                        <input type="time" id="r_fim" class="form-control form-control-solid" required />
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Pausa (min)</label>
                        <input type="number" id="r_pausa" class="form-control form-control-solid" value="0" min="0" />
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Projeto</label>
                        <input type="text" id="r_projeto" class="form-control form-control-solid" placeholder="Opcional" />
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Horas calc.</label>
                        <input type="text" id="r_horas_calc" class="form-control form-control-solid" readonly />
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Descrição da atividade</label>
                        <textarea id="r_descricao" class="form-control form-control-solid" rows="2" placeholder="O que foi feito..."></textarea>
                    </div>
                </div>
                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary" id="btn_salvar">
                        <i class="ki-duotone ki-plus fs-2"></i> Adicionar
                    </button>
                    <button type="button" class="btn btn-light" onclick="cancelarEdicao()" id="btn_cancelar" style="display:none;">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabela de registros -->
    <div class="card">
        <div class="card-header"><h3 class="card-title">Registros do Mês</h3></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-row-bordered align-middle" id="tabela_registros">
                    <thead>
                        <tr class="fw-bold text-muted">
                            <th>Data</th>
                            <th>Início</th>
                            <th>Fim</th>
                            <th>Pausa</th>
                            <th>Horas</th>
                            <th>Projeto</th>
                            <th>Descrição</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="tabela_registros_body"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
const STORAGE_KEY = 'controle_horas_online_v1';

function getDados() {
    try {
        return JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
    } catch (e) { return {}; }
}
function salvarDados(d) {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(d));
}
function getMesAtual() {
    return document.getElementById('filtro_mes').value;
}
function getRegistrosDoMes() {
    const dados = getDados();
    return dados[getMesAtual()] || [];
}
function setRegistrosDoMes(registros) {
    const dados = getDados();
    dados[getMesAtual()] = registros;
    salvarDados(dados);
}

function calcularHoras(inicio, fim, pausaMin) {
    if (!inicio || !fim) return 0;
    const [hi, mi] = inicio.split(':').map(Number);
    const [hf, mf] = fim.split(':').map(Number);
    const totalMin = (hf * 60 + mf) - (hi * 60 + mi) - (parseInt(pausaMin) || 0);
    return totalMin > 0 ? (totalMin / 60) : 0;
}

function formatarHoras(h) {
    return h.toFixed(2).replace('.', ',') + ' h';
}
function formatarData(d) {
    if (!d) return '';
    const [a, m, dia] = d.split('-');
    return `${dia}/${m}/${a}`;
}
function parseValor(v) {
    if (!v) return 0;
    return parseFloat(String(v).replace(/\./g, '').replace(',', '.')) || 0;
}

function renderizar() {
    const registros = getRegistrosDoMes();
    registros.sort((a, b) => a.data.localeCompare(b.data));

    const tbody = document.getElementById('tabela_registros_body');
    if (registros.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-5">Nenhum registro neste mês ainda. Adicione acima!</td></tr>';
    } else {
        tbody.innerHTML = registros.map((r, idx) => `
            <tr>
                <td><strong>${formatarData(r.data)}</strong></td>
                <td>${r.inicio}</td>
                <td>${r.fim}</td>
                <td>${r.pausa || 0} min</td>
                <td><strong>${formatarHoras(r.horas)}</strong></td>
                <td>${r.projeto || '-'}</td>
                <td><small>${r.descricao || '-'}</small></td>
                <td class="text-end">
                    <button class="btn btn-sm btn-icon btn-light-warning me-1" onclick="editarRegistro(${idx})" title="Editar">
                        <i class="ki-duotone ki-pencil fs-5"><span class="path1"></span><span class="path2"></span></i>
                    </button>
                    <button class="btn btn-sm btn-icon btn-light-danger" onclick="removerRegistro(${idx})" title="Remover">
                        <i class="ki-duotone ki-trash fs-5"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></i>
                    </button>
                </td>
            </tr>
        `).join('');
    }

    // Resumo
    const totalHoras = registros.reduce((s, r) => s + r.horas, 0);
    const valorHora = parseValor(document.getElementById('valor_hora_calc').value);
    const valorTotal = totalHoras * valorHora;

    document.getElementById('resumo_registros').textContent = registros.length;
    document.getElementById('resumo_horas').textContent = formatarHoras(totalHoras);
    document.getElementById('resumo_valor').textContent = 'R$ ' + valorTotal.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function adicionarRegistro(e) {
    e.preventDefault();
    const data = document.getElementById('r_data').value;
    const inicio = document.getElementById('r_inicio').value;
    const fim = document.getElementById('r_fim').value;
    const pausa = parseInt(document.getElementById('r_pausa').value) || 0;
    const projeto = document.getElementById('r_projeto').value.trim();
    const descricao = document.getElementById('r_descricao').value.trim();

    if (!data || !inicio || !fim) {
        Swal.fire({icon:'warning', title:'Campos obrigatórios', text:'Data, Início e Fim são obrigatórios'});
        return;
    }

    // Valida que data está no mês selecionado
    if (data.substring(0, 7) !== getMesAtual()) {
        Swal.fire({icon:'warning', title:'Data fora do mês', text:'A data deve estar no mês de referência selecionado'});
        return;
    }

    const horas = calcularHoras(inicio, fim, pausa);
    if (horas <= 0) {
        Swal.fire({icon:'warning', title:'Horário inválido', text:'Hora fim deve ser maior que hora início (após descontar a pausa)'});
        return;
    }

    const registros = getRegistrosDoMes();
    const editId = document.getElementById('registro_id').value;

    const novo = { data, inicio, fim, pausa, horas, projeto, descricao };

    if (editId !== '') {
        registros[parseInt(editId)] = novo;
    } else {
        registros.push(novo);
    }

    setRegistrosDoMes(registros);
    cancelarEdicao();
    renderizar();
}

function editarRegistro(idx) {
    const registros = getRegistrosDoMes();
    const r = registros[idx];
    if (!r) return;
    document.getElementById('registro_id').value = idx;
    document.getElementById('r_data').value = r.data;
    document.getElementById('r_inicio').value = r.inicio;
    document.getElementById('r_fim').value = r.fim;
    document.getElementById('r_pausa').value = r.pausa || 0;
    document.getElementById('r_projeto').value = r.projeto || '';
    document.getElementById('r_descricao').value = r.descricao || '';
    document.getElementById('r_horas_calc').value = formatarHoras(r.horas);
    document.getElementById('btn_salvar').innerHTML = '<i class="ki-duotone ki-check fs-2"></i> Salvar Alteração';
    document.getElementById('btn_cancelar').style.display = '';
    window.scrollTo({top: 0, behavior: 'smooth'});
}

function cancelarEdicao() {
    document.getElementById('registro_id').value = '';
    document.getElementById('form_registro').reset();
    document.getElementById('r_pausa').value = 0;
    document.getElementById('r_horas_calc').value = '';
    document.getElementById('btn_salvar').innerHTML = '<i class="ki-duotone ki-plus fs-2"></i> Adicionar';
    document.getElementById('btn_cancelar').style.display = 'none';
}

function removerRegistro(idx) {
    Swal.fire({
        title: 'Remover registro?',
        text: 'Esta ação não pode ser desfeita',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sim, remover',
        cancelButtonText: 'Cancelar'
    }).then(result => {
        if (!result.isConfirmed) return;
        const registros = getRegistrosDoMes();
        registros.splice(idx, 1);
        setRegistrosDoMes(registros);
        renderizar();
    });
}

function limparMes() {
    Swal.fire({
        title: 'Limpar todos os registros deste mês?',
        text: 'Esta ação não pode ser desfeita',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sim, limpar tudo',
        cancelButtonText: 'Cancelar'
    }).then(result => {
        if (!result.isConfirmed) return;
        setRegistrosDoMes([]);
        renderizar();
    });
}

function exportarCsv() {
    const registros = getRegistrosDoMes();
    if (registros.length === 0) {
        Swal.fire({icon:'info', title:'Nada para exportar', text:'Não há registros neste mês'});
        return;
    }

    const linhas = [
        ['Data', 'Hora Inicio', 'Hora Fim', 'Pausa (min)', 'Horas Trabalhadas', 'Projeto', 'Descricao']
    ];
    registros.sort((a, b) => a.data.localeCompare(b.data)).forEach(r => {
        linhas.push([
            formatarData(r.data),
            r.inicio,
            r.fim,
            r.pausa || 0,
            r.horas.toFixed(2),
            r.projeto || '',
            (r.descricao || '').replace(/[\r\n]+/g, ' ')
        ]);
    });

    const csv = '\uFEFF' + linhas.map(l => l.map(c => {
        const s = String(c);
        return s.includes(';') || s.includes('"') || s.includes('\n') ? '"' + s.replace(/"/g, '""') + '"' : s;
    }).join(';')).join('\n');

    const blob = new Blob([csv], {type: 'text/csv;charset=utf-8;'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `horas_trabalhadas_${getMesAtual()}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

// Calcula horas em tempo real ao digitar
function recalcularHorasForm() {
    const inicio = document.getElementById('r_inicio').value;
    const fim = document.getElementById('r_fim').value;
    const pausa = parseInt(document.getElementById('r_pausa').value) || 0;
    if (inicio && fim) {
        document.getElementById('r_horas_calc').value = formatarHoras(calcularHoras(inicio, fim, pausa));
    } else {
        document.getElementById('r_horas_calc').value = '';
    }
}
['r_inicio', 'r_fim', 'r_pausa'].forEach(id => {
    document.getElementById(id).addEventListener('input', recalcularHorasForm);
});

document.getElementById('filtro_mes').addEventListener('change', renderizar);
document.getElementById('valor_hora_calc').addEventListener('input', renderizar);

// Inicializa data padrão para hoje
document.getElementById('r_data').value = new Date().toISOString().substring(0, 10);

renderizar();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
