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
    
    // Cargos são por empresa, não por setor
    $stmt = $pdo->prepare("SELECT * FROM cargos WHERE empresa_id = ? AND status = 'ativo' ORDER BY nome_cargo");
    $stmt->execute([$vaga['empresa_id']]);
    $cargos = $stmt->fetchAll();
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
                                <input type="text" name="salario_min" id="salario_min" class="form-control" placeholder="0,00" value="<?= $vaga['salario_min'] ? number_format($vaga['salario_min'], 2, ',', '.') : '' ?>">
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Salário Máximo (R$)</label>
                                <input type="text" name="salario_max" id="salario_max" class="form-control" placeholder="0,00" value="<?= $vaga['salario_max'] ? number_format($vaga['salario_max'], 2, ',', '.') : '' ?>">
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
                                <label class="form-label">Horário de Trabalho</label>
                                <input type="text" name="horario_trabalho" class="form-control" placeholder="Ex: 08:00 às 18:00" value="<?= htmlspecialchars($vaga['horario_trabalho'] ?? '') ?>">
                                <small class="form-text text-muted">Ex: 08:00 às 18:00</small>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Dias de Trabalho</label>
                                <input type="text" name="dias_trabalho" class="form-control" placeholder="Ex: Segunda a Sexta" value="<?= htmlspecialchars($vaga['dias_trabalho'] ?? '') ?>">
                                <small class="form-text text-muted">Ex: Segunda a Sexta</small>
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
    const cargoSelect = document.getElementById('cargoSelect');
    
    // Limpa setores e cargos
    setorSelect.innerHTML = '<option value="">Carregando...</option>';
    cargoSelect.innerHTML = '<option value="">Carregando...</option>';
    
    if (!empresaId) {
        setorSelect.innerHTML = '<option value="">Selecione...</option>';
        cargoSelect.innerHTML = '<option value="">Selecione...</option>';
        return;
    }
    
    try {
        // Carrega setores
        const responseSetores = await fetch(`../api/get_setores.php?empresa_id=${empresaId}`);
        const dataSetores = await responseSetores.json();
        
        setorSelect.innerHTML = '<option value="">Selecione...</option>';
        if (dataSetores.success && dataSetores.setores && dataSetores.setores.length > 0) {
            dataSetores.setores.forEach(setor => {
                const option = document.createElement('option');
                option.value = setor.id;
                option.textContent = setor.nome_setor;
                setorSelect.appendChild(option);
            });
        } else {
            setorSelect.innerHTML = '<option value="">Nenhum setor encontrado</option>';
        }
        
        // Carrega cargos da empresa
        const responseCargos = await fetch(`../api/get_cargos.php?empresa_id=${empresaId}`);
        const dataCargos = await responseCargos.json();
        
        cargoSelect.innerHTML = '<option value="">Selecione...</option>';
        if (dataCargos.success && dataCargos.cargos && dataCargos.cargos.length > 0) {
            dataCargos.cargos.forEach(cargo => {
                const option = document.createElement('option');
                option.value = cargo.id;
                option.textContent = cargo.nome_cargo;
                cargoSelect.appendChild(option);
            });
        } else {
            cargoSelect.innerHTML = '<option value="">Nenhum cargo encontrado</option>';
        }
    } catch (error) {
        console.error('Erro ao carregar dados:', error);
        setorSelect.innerHTML = '<option value="">Erro ao carregar</option>';
        cargoSelect.innerHTML = '<option value="">Erro ao carregar</option>';
    }
});

// Carrega cargos ao selecionar setor (opcional - cargos já são carregados quando empresa muda)
document.getElementById('setorSelect').addEventListener('change', async function() {
    const empresaId = document.getElementById('empresaSelect').value;
    const cargoSelect = document.getElementById('cargoSelect');
    
    if (!empresaId) {
        cargoSelect.innerHTML = '<option value="">Selecione...</option>';
        return;
    }
    
    // Recarrega cargos da empresa (opcional, já foram carregados)
    try {
        cargoSelect.innerHTML = '<option value="">Carregando...</option>';
        const response = await fetch(`../api/get_cargos.php?empresa_id=${empresaId}`);
        const data = await response.json();
        
        cargoSelect.innerHTML = '<option value="">Selecione...</option>';
        if (data.success && data.cargos && data.cargos.length > 0) {
            data.cargos.forEach(cargo => {
                const option = document.createElement('option');
                option.value = cargo.id;
                option.textContent = cargo.nome_cargo;
                cargoSelect.appendChild(option);
            });
        } else {
            cargoSelect.innerHTML = '<option value="">Nenhum cargo encontrado</option>';
        }
    } catch (error) {
        console.error('Erro ao carregar cargos:', error);
        cargoSelect.innerHTML = '<option value="">Erro ao carregar</option>';
    }
});

// Carrega setores e cargos ao carregar a página (se já tiver empresa selecionada)
document.addEventListener('DOMContentLoaded', function() {
    const empresaSelect = document.getElementById('empresaSelect');
    const setorSelect = document.getElementById('setorSelect');
    const cargoSelect = document.getElementById('cargoSelect');
    
    // Se já tem empresa selecionada e não tem setores carregados, carrega
    if (empresaSelect.value && setorSelect.options.length <= 1) {
        empresaSelect.dispatchEvent(new Event('change'));
    }
    
    // Se já tem setor selecionado e não tem cargos carregados, carrega
    if (setorSelect.value && cargoSelect.options.length <= 1) {
        setorSelect.dispatchEvent(new Event('change'));
    }
});

// Aplica máscaras de moeda
(function waitForDependencies() {
    if (typeof jQuery === 'undefined' || typeof $ === 'undefined') {
        setTimeout(waitForDependencies, 50);
        return;
    }
    
    $(document).ready(function() {
        // Aguarda jQuery Mask estar disponível
        if (typeof $.fn.mask !== 'undefined') {
            // Máscara para salário mínimo e máximo (moeda brasileira)
            $('#salario_min').mask('#.##0,00', {reverse: true});
            $('#salario_max').mask('#.##0,00', {reverse: true});
        } else {
            setTimeout(waitForDependencies, 100);
        }
    });
})();

// Submete formulário
document.addEventListener('DOMContentLoaded', function() {
    const formVaga = document.getElementById('formVaga');
    if (!formVaga) {
        console.error('Formulário não encontrado!');
        return;
    }
    
    let isSubmitting = false;
    
    formVaga.addEventListener('submit', async function(e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        
        // Previne múltiplos envios
        if (isSubmitting) {
            console.warn('Formulário já está sendo enviado');
            return false;
        }
        
        const btnSubmit = this.querySelector('button[type="submit"]');
        const btnOriginalText = btnSubmit ? btnSubmit.innerHTML : '';
        
        // Marca como enviando
        isSubmitting = true;
        if (btnSubmit) {
            btnSubmit.disabled = true;
            btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Salvando...';
        }
        
        const formData = new FormData(this);
        
        // Converte valores de moeda para formato numérico antes de enviar
        const salarioMin = document.getElementById('salario_min');
        const salarioMax = document.getElementById('salario_max');
        
        if (salarioMin && salarioMin.value) {
            const valorMin = salarioMin.value.replace(/\./g, '').replace(',', '.');
            formData.set('salario_min', valorMin);
        }
        
        if (salarioMax && salarioMax.value) {
            const valorMax = salarioMax.value.replace(/\./g, '').replace(',', '.');
            formData.set('salario_max', valorMax);
        }
        
        try {
            console.log('Enviando formulário...');
            const response = await fetch('../api/recrutamento/vagas/editar.php', {
                method: 'POST',
                body: formData
            });
            
            console.log('Resposta recebida:', response.status);
            
            if (!response.ok) {
                throw new Error('Erro HTTP: ' + response.status);
            }
            
            const data = await response.json();
            console.log('Dados recebidos:', data);
            
            if (data.success) {
                alert('Vaga atualizada com sucesso!');
                window.location.href = 'vaga_view.php?id=<?= $vaga_id ?>';
            } else {
                alert('Erro: ' + (data.message || 'Erro desconhecido'));
                isSubmitting = false;
                if (btnSubmit) {
                    btnSubmit.disabled = false;
                    btnSubmit.innerHTML = btnOriginalText;
                }
            }
        } catch (error) {
            console.error('Erro ao atualizar vaga:', error);
            alert('Erro ao atualizar vaga. Verifique o console para mais detalhes.');
            isSubmitting = false;
            if (btnSubmit) {
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = btnOriginalText;
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

