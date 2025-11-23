<?php
/**
 * Editar Vaga
 */

$page_title = 'Editar Vaga';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('vaga_edit.php');

require_once __DIR__ . '/../includes/header.php';

$pdo = getDB();
$usuario = $_SESSION['usuario'];

$vaga_id = (int)($_GET['id'] ?? 0);

if (!$vaga_id) {
    redirect('vagas.php', 'Vaga não encontrada', 'error');
}

// Busca vaga
$stmt = $pdo->prepare("SELECT * FROM vagas WHERE id = ?");
$stmt->execute([$vaga_id]);
$vaga = $stmt->fetch();

if (!$vaga || !can_access_empresa($vaga['empresa_id'])) {
    redirect('vagas.php', 'Sem permissão', 'error');
}

// Processa benefícios
$beneficios_selecionados = [];
if ($vaga['beneficios']) {
    $beneficios_selecionados = json_decode($vaga['beneficios'], true) ?: [];
}

// Busca empresas
require_once __DIR__ . '/../includes/select_colaborador.php';
$empresas = get_empresas_disponiveis($pdo, $usuario);

// Busca setores e cargos da empresa
$setores = [];
$cargos = [];
if ($vaga['empresa_id']) {
    $stmt = $pdo->prepare("SELECT * FROM setores WHERE empresa_id = ? AND status = 'ativo' ORDER BY nome_setor");
    $stmt->execute([$vaga['empresa_id']]);
    $setores = $stmt->fetchAll();
    
    if ($vaga['setor_id']) {
        $stmt = $pdo->prepare("SELECT * FROM cargos WHERE setor_id = ? ORDER BY nome_cargo");
        $stmt->execute([$vaga['setor_id']]);
        $cargos = $stmt->fetchAll();
    }
}

// Busca etapas padrão e etapas da vaga
$stmt = $pdo->query("SELECT * FROM processo_seletivo_etapas WHERE vaga_id IS NULL AND ativo = 1 ORDER BY ordem ASC");
$etapas_padrao = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT etapa_id FROM vagas_etapas WHERE vaga_id = ?");
$stmt->execute([$vaga_id]);
$etapas_vaga = $stmt->fetchAll(PDO::FETCH_COLUMN);

$beneficios_padrao = [
    'Vale Transporte',
    'Vale Alimentação',
    'Vale Refeição',
    'Plano de Saúde',
    'Plano Odontológico',
    'Gympass',
    'Bônus/PLR',
    'Auxílio Home Office',
    'Seguro de Vida',
    'Participação nos Lucros'
];
?>
<div class="d-flex flex-column flex-column-fluid">
    <div id="kt_content" class="content d-flex flex-column flex-column-fluid">
        <div class="post d-flex flex-column-fluid" id="kt_post">
            <div id="kt_content_container" class="container-xxl">
                
                <div class="card">
                    <div class="card-header border-0 pt-6">
                        <div class="card-title">
                            <h2>Editar Vaga</h2>
                        </div>
                        <div class="card-toolbar">
                            <a href="vaga_view.php?id=<?= $vaga_id ?>" class="btn btn-light">Voltar</a>
                        </div>
                    </div>
                    
                    <div class="card-body pt-0">
                        <form id="formVaga" class="row g-5">
                            <input type="hidden" name="vaga_id" value="<?= $vaga_id ?>">
                            
                            <!-- Informações Básicas -->
                            <div class="col-12">
                                <h3>Informações Básicas</h3>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Empresa *</label>
                                <select name="empresa_id" class="form-select" required id="empresaSelect">
                                    <?php foreach ($empresas as $empresa): ?>
                                    <option value="<?= $empresa['id'] ?>" <?= $vaga['empresa_id'] == $empresa['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($empresa['nome_fantasia']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Título da Vaga *</label>
                                <input type="text" name="titulo" class="form-control" required value="<?= htmlspecialchars($vaga['titulo']) ?>">
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Setor</label>
                                <select name="setor_id" class="form-select" id="setorSelect">
                                    <option value="">Selecione...</option>
                                    <?php foreach ($setores as $setor): ?>
                                    <option value="<?= $setor['id'] ?>" <?= $vaga['setor_id'] == $setor['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($setor['nome_setor']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Cargo</label>
                                <select name="cargo_id" class="form-select" id="cargoSelect">
                                    <option value="">Selecione...</option>
                                    <?php foreach ($cargos as $cargo): ?>
                                    <option value="<?= $cargo['id'] ?>" <?= $vaga['cargo_id'] == $cargo['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cargo['nome_cargo']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Quantidade de Vagas</label>
                                <input type="number" name="quantidade_vagas" class="form-control" value="<?= $vaga['quantidade_vagas'] ?>" min="1">
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label">Descrição *</label>
                                <textarea name="descricao" class="form-control" rows="5" required><?= htmlspecialchars($vaga['descricao']) ?></textarea>
                            </div>
                            
                            <!-- Remuneração -->
                            <div class="col-12">
                                <h3>Remuneração</h3>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Salário Mínimo (R$)</label>
                                <input type="number" name="salario_min" class="form-control" step="0.01" value="<?= $vaga['salario_min'] ?>">
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Salário Máximo (R$)</label>
                                <input type="number" name="salario_max" class="form-control" step="0.01" value="<?= $vaga['salario_max'] ?>">
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Tipo de Contrato</label>
                                <select name="tipo_contrato" class="form-select">
                                    <option value="CLT" <?= $vaga['tipo_contrato'] === 'CLT' ? 'selected' : '' ?>>CLT</option>
                                    <option value="PJ" <?= $vaga['tipo_contrato'] === 'PJ' ? 'selected' : '' ?>>PJ</option>
                                    <option value="Estágio" <?= $vaga['tipo_contrato'] === 'Estágio' ? 'selected' : '' ?>>Estágio</option>
                                    <option value="Temporário" <?= $vaga['tipo_contrato'] === 'Temporário' ? 'selected' : '' ?>>Temporário</option>
                                    <option value="Freelance" <?= $vaga['tipo_contrato'] === 'Freelance' ? 'selected' : '' ?>>Freelance</option>
                                </select>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label">Benefícios</label>
                                <div class="row">
                                    <?php foreach ($beneficios_padrao as $beneficio): ?>
                                    <div class="col-md-3 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="beneficios[]" 
                                                   value="<?= htmlspecialchars($beneficio) ?>" 
                                                   id="beneficio_<?= md5($beneficio) ?>"
                                                   <?= in_array($beneficio, $beneficios_selecionados) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="beneficio_<?= md5($beneficio) ?>">
                                                <?= htmlspecialchars($beneficio) ?>
                                            </label>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <!-- Requisitos -->
                            <div class="col-12">
                                <h3>Requisitos</h3>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Requisitos Obrigatórios</label>
                                <textarea name="requisitos_obrigatorios" class="form-control" rows="5"><?= htmlspecialchars($vaga['requisitos_obrigatorios'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Requisitos Desejáveis</label>
                                <textarea name="requisitos_desejaveis" class="form-control" rows="5"><?= htmlspecialchars($vaga['requisitos_desejaveis'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Competências Técnicas</label>
                                <textarea name="competencias_tecnicas" class="form-control" rows="5"><?= htmlspecialchars($vaga['competencias_tecnicas'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Competências Comportamentais</label>
                                <textarea name="competencias_comportamentais" class="form-control" rows="5"><?= htmlspecialchars($vaga['competencias_comportamentais'] ?? '') ?></textarea>
                            </div>
                            
                            <!-- Outras Informações -->
                            <div class="col-12">
                                <h3>Outras Informações</h3>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Modalidade</label>
                                <select name="modalidade" class="form-select">
                                    <option value="Presencial" <?= $vaga['modalidade'] === 'Presencial' ? 'selected' : '' ?>>Presencial</option>
                                    <option value="Remoto" <?= $vaga['modalidade'] === 'Remoto' ? 'selected' : '' ?>>Remoto</option>
                                    <option value="Híbrido" <?= $vaga['modalidade'] === 'Híbrido' ? 'selected' : '' ?>>Híbrido</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Localização</label>
                                <input type="text" name="localizacao" class="form-control" value="<?= htmlspecialchars($vaga['localizacao'] ?? '') ?>">
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="aberta" <?= $vaga['status'] === 'aberta' ? 'selected' : '' ?>>Aberta</option>
                                    <option value="pausada" <?= $vaga['status'] === 'pausada' ? 'selected' : '' ?>>Pausada</option>
                                    <option value="fechada" <?= $vaga['status'] === 'fechada' ? 'selected' : '' ?>>Fechada</option>
                                    <option value="cancelada" <?= $vaga['status'] === 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
                                </select>
                            </div>
                            
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="publicar_portal" id="publicar_portal" value="1" <?= $vaga['publicar_portal'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="publicar_portal">Publicar no Portal</label>
                                </div>
                            </div>
                            
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="usar_landing_page_customizada" id="usar_landing_page_customizada" value="1" <?= $vaga['usar_landing_page_customizada'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="usar_landing_page_customizada">Usar Landing Page Customizada</label>
                                </div>
                            </div>
                            
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                                <a href="vaga_view.php?id=<?= $vaga_id ?>" class="btn btn-light">Cancelar</a>
                            </div>
                        </form>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
</div>

<script>
// Carrega setores ao selecionar empresa
document.getElementById('empresaSelect').addEventListener('change', async function() {
    const empresaId = this.value;
    const setorSelect = document.getElementById('setorSelect');
    
    if (!empresaId) {
        setorSelect.innerHTML = '<option value="">Selecione...</option>';
        return;
    }
    
    try {
        const response = await fetch(`../api/get_setores.php?empresa_id=${empresaId}`);
        const data = await response.json();
        
        setorSelect.innerHTML = '<option value="">Selecione...</option>';
        if (data.setores) {
            data.setores.forEach(setor => {
                const option = document.createElement('option');
                option.value = setor.id;
                option.textContent = setor.nome_setor;
                setorSelect.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Erro ao carregar setores:', error);
    }
});

// Carrega cargos ao selecionar setor
document.getElementById('setorSelect').addEventListener('change', async function() {
    const setorId = this.value;
    const cargoSelect = document.getElementById('cargoSelect');
    
    if (!setorId) {
        cargoSelect.innerHTML = '<option value="">Selecione...</option>';
        return;
    }
    
    try {
        const response = await fetch(`../api/get_cargos.php?setor_id=${setorId}`);
        const data = await response.json();
        
        cargoSelect.innerHTML = '<option value="">Selecione...</option>';
        if (data.cargos) {
            data.cargos.forEach(cargo => {
                const option = document.createElement('option');
                option.value = cargo.id;
                option.textContent = cargo.nome_cargo;
                cargoSelect.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Erro ao carregar cargos:', error);
    }
});

// Submete formulário
document.getElementById('formVaga').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    try {
        const response = await fetch('../api/recrutamento/vagas/editar.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Vaga atualizada com sucesso!');
            window.location.href = 'vaga_view.php?id=<?= $vaga_id ?>';
        } else {
            alert('Erro: ' + data.message);
        }
    } catch (error) {
        alert('Erro ao atualizar vaga');
        console.error(error);
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

