<?php
/**
 * Adicionar Nova Vaga
 */

$page_title = 'Nova Vaga';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('vaga_add.php');

require_once __DIR__ . '/../includes/header.php';

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Busca empresas
require_once __DIR__ . '/../includes/select_colaborador.php';
$empresas = get_empresas_disponiveis($pdo, $usuario);

// Busca setores e cargos (serão carregados via AJAX)
$setores = [];
$cargos = [];

// Busca etapas padrão
$stmt = $pdo->query("SELECT * FROM processo_seletivo_etapas WHERE vaga_id IS NULL AND ativo = 1 ORDER BY ordem ASC");
$etapas_padrao = $stmt->fetchAll();

// Benefícios padrão
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
                            <h2>Nova Vaga</h2>
                        </div>
                        <div class="card-toolbar">
                            <a href="vagas.php" class="btn btn-light">Voltar</a>
                        </div>
                    </div>
                    
                    <div class="card-body pt-0">
                        <form id="formVaga" class="row g-5">
                            <!-- Informações Básicas -->
                            <div class="col-12">
                                <h3>Informações Básicas</h3>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Empresa *</label>
                                <select name="empresa_id" class="form-select" required id="empresaSelect">
                                    <option value="">Selecione...</option>
                                    <?php foreach ($empresas as $empresa): ?>
                                    <option value="<?= $empresa['id'] ?>"><?= htmlspecialchars($empresa['nome_fantasia']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Título da Vaga *</label>
                                <input type="text" name="titulo" class="form-control" required>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Setor</label>
                                <select name="setor_id" class="form-select" id="setorSelect">
                                    <option value="">Selecione...</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Cargo</label>
                                <select name="cargo_id" class="form-select" id="cargoSelect">
                                    <option value="">Selecione...</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Quantidade de Vagas</label>
                                <input type="number" name="quantidade_vagas" class="form-control" value="1" min="1">
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label">Descrição *</label>
                                <textarea name="descricao" class="form-control" rows="5" required></textarea>
                            </div>
                            
                            <!-- Remuneração -->
                            <div class="col-12">
                                <h3>Remuneração</h3>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Salário Mínimo (R$)</label>
                                <input type="text" name="salario_min" id="salario_min" class="form-control" placeholder="0,00">
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Salário Máximo (R$)</label>
                                <input type="text" name="salario_max" id="salario_max" class="form-control" placeholder="0,00">
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Tipo de Contrato</label>
                                <select name="tipo_contrato" class="form-select">
                                    <option value="CLT">CLT</option>
                                    <option value="PJ">PJ</option>
                                    <option value="Estágio">Estágio</option>
                                    <option value="Temporário">Temporário</option>
                                    <option value="Freelance">Freelance</option>
                                </select>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label">Benefícios</label>
                                <div class="row">
                                    <?php foreach ($beneficios_padrao as $beneficio): ?>
                                    <div class="col-md-3 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="beneficios[]" 
                                                   value="<?= htmlspecialchars($beneficio) ?>" id="beneficio_<?= md5($beneficio) ?>">
                                            <label class="form-check-label" for="beneficio_<?= md5($beneficio) ?>">
                                                <?= htmlspecialchars($beneficio) ?>
                                            </label>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="mt-2">
                                    <input type="text" class="form-control" id="beneficioCustom" placeholder="Outro benefício (pressione Enter)">
                                </div>
                            </div>
                            
                            <!-- Requisitos -->
                            <div class="col-12">
                                <h3>Requisitos</h3>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Requisitos Obrigatórios</label>
                                <textarea name="requisitos_obrigatorios" class="form-control" rows="5"></textarea>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Requisitos Desejáveis</label>
                                <textarea name="requisitos_desejaveis" class="form-control" rows="5"></textarea>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Competências Técnicas</label>
                                <textarea name="competencias_tecnicas" class="form-control" rows="5"></textarea>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Competências Comportamentais</label>
                                <textarea name="competencias_comportamentais" class="form-control" rows="5"></textarea>
                            </div>
                            
                            <!-- Outras Informações -->
                            <div class="col-12">
                                <h3>Outras Informações</h3>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Modalidade</label>
                                <select name="modalidade" class="form-select">
                                    <option value="Presencial">Presencial</option>
                                    <option value="Remoto">Remoto</option>
                                    <option value="Híbrido">Híbrido</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Localização</label>
                                <input type="text" name="localizacao" class="form-control">
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Horário de Trabalho</label>
                                <input type="text" name="horario_trabalho" class="form-control" placeholder="Ex: 08:00 às 18:00">
                                <small class="form-text text-muted">Ex: 08:00 às 18:00</small>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Dias de Trabalho</label>
                                <input type="text" name="dias_trabalho" class="form-control" placeholder="Ex: Segunda a Sexta">
                                <small class="form-text text-muted">Ex: Segunda a Sexta</small>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="aberta">Aberta</option>
                                    <option value="pausada">Pausada</option>
                                </select>
                            </div>
                            
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="publicar_portal" id="publicar_portal" value="1" checked>
                                    <label class="form-check-label" for="publicar_portal">Publicar no Portal</label>
                                </div>
                            </div>
                            
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="usar_landing_page_customizada" id="usar_landing_page_customizada" value="1">
                                    <label class="form-check-label" for="usar_landing_page_customizada">Usar Landing Page Customizada</label>
                                </div>
                            </div>
                            
                            <!-- Etapas do Processo -->
                            <div class="col-12">
                                <h3>Etapas do Processo Seletivo</h3>
                                <p class="text-muted">Selecione as etapas que farão parte desta vaga. Se não selecionar, serão usadas as etapas padrão.</p>
                                <div class="row">
                                    <?php foreach ($etapas_padrao as $etapa): ?>
                                    <div class="col-md-3 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="etapas[]" 
                                                   value="<?= $etapa['id'] ?>" id="etapa_<?= $etapa['id'] ?>" checked>
                                            <label class="form-check-label" for="etapa_<?= $etapa['id'] ?>">
                                                <?= htmlspecialchars($etapa['nome']) ?>
                                            </label>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">Salvar Vaga</button>
                                <a href="vagas.php" class="btn btn-light">Cancelar</a>
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
    cargoSelect.innerHTML = '<option value="">Selecione...</option>';
    
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
    // Cargos já são carregados quando empresa muda, mas podemos recarregar se necessário
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

// Adiciona benefício customizado
document.getElementById('beneficioCustom').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        const valor = this.value.trim();
        if (valor) {
            const container = this.previousElementSibling;
            const col = document.createElement('div');
            col.className = 'col-md-3 mb-2';
            col.innerHTML = `
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="beneficios[]" value="${valor}" checked>
                    <label class="form-check-label">${valor}</label>
                </div>
            `;
            container.appendChild(col);
            this.value = '';
        }
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
            const response = await fetch('../api/recrutamento/vagas/criar.php', {
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
                alert('Vaga criada com sucesso!');
                window.location.href = 'vagas.php';
            } else {
                alert('Erro: ' + (data.message || 'Erro desconhecido'));
                isSubmitting = false;
                if (btnSubmit) {
                    btnSubmit.disabled = false;
                    btnSubmit.innerHTML = btnOriginalText;
                }
            }
        } catch (error) {
            console.error('Erro ao criar vaga:', error);
            alert('Erro ao criar vaga. Verifique o console para mais detalhes.');
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

