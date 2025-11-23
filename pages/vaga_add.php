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
                                <input type="number" name="salario_min" class="form-control" step="0.01">
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Salário Máximo (R$)</label>
                                <input type="number" name="salario_max" class="form-control" step="0.01">
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
    
    setorSelect.innerHTML = '<option value="">Carregando...</option>';
    
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
        setorSelect.innerHTML = '<option value="">Erro ao carregar</option>';
    }
});

// Carrega cargos ao selecionar setor
document.getElementById('setorSelect').addEventListener('change', async function() {
    const setorId = this.value;
    const cargoSelect = document.getElementById('cargoSelect');
    
    cargoSelect.innerHTML = '<option value="">Carregando...</option>';
    
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

// Submete formulário
document.getElementById('formVaga').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    try {
        const response = await fetch('../api/recrutamento/vagas/criar.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Vaga criada com sucesso!');
            window.location.href = 'vagas.php';
        } else {
            alert('Erro: ' + data.message);
        }
    } catch (error) {
        alert('Erro ao criar vaga');
        console.error(error);
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

