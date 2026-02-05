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
                
                <!-- Card de Geração com IA -->
                <?php
                // Verifica se OpenAI está configurada
                $openai_configurada = false;
                try {
                    $stmt = $pdo->query("SELECT ativo FROM openai_config WHERE ativo = 1 LIMIT 1");
                    $openai_config = $stmt->fetch();
                    $openai_configurada = $openai_config ? true : false;
                } catch (PDOException $e) {
                    // Tabela não existe ainda
                }
                ?>
                
                <?php if ($openai_configurada): ?>
                <div class="card mb-5 shadow-sm border-primary" id="ia_card_gerador">
                    <div class="card-header border-0 pt-5" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <div class="card-title">
                            <h2 class="text-white mb-0">
                                <i class="ki-duotone ki-robot fs-2hx text-white me-3">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Criar Vaga com IA
                            </h2>
                        </div>
                        <div class="card-toolbar">
                            <button type="button" class="btn btn-sm btn-light-primary" data-bs-toggle="collapse" data-bs-target="#ia_collapse">
                                <i class="ki-duotone ki-down fs-3"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="collapse show" id="ia_collapse">
                        <div class="card-body">
                            <div class="alert alert-primary d-flex align-items-center p-5 mb-5">
                                <i class="ki-duotone ki-information-5 fs-2hx text-primary me-4">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                </i>
                                <div class="d-flex flex-column flex-grow-1">
                                    <h4 class="mb-2 text-primary">Como Funciona?</h4>
                                    <span>Descreva brevemente a vaga que você quer criar e deixe a Inteligência Artificial preencher todos os campos automaticamente! Você poderá revisar e editar tudo antes de salvar.</span>
                                </div>
                            </div>
                            
                            <div class="row g-5">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold fs-6 required">Tipo de Vaga</label>
                                    <select class="form-select form-select-solid" id="ia_template_select">
                                        <option value="">Carregando...</option>
                                    </select>
                                    <div id="ia_template_info"></div>
                                </div>
                                
                                <div class="col-md-8">
                                    <label class="form-label fw-semibold fs-6 required">Descreva a Vaga</label>
                                    <textarea class="form-control form-control-solid" id="ia_descricao_input" 
                                              rows="4" 
                                              placeholder="Ex: Desenvolvedor Full Stack Python/React para startup de fintech. Remoto, salário entre 8k-12k, experiência mínima 3 anos com AWS e Docker. Benefícios: VA, VR, plano de saúde..."></textarea>
                                    <div class="form-text">
                                        Descreva: cargo, área, tipo de empresa, modalidade, faixa salarial, requisitos principais, benefícios, etc.
                                        (mínimo 20 caracteres)
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mt-5">
                                <div class="col-12">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div id="ia_limite_info">
                                            <small class="text-muted">Carregando...</small>
                                        </div>
                                        <div>
                                            <button type="button" class="btn btn-light me-2" data-bs-toggle="modal" data-bs-target="#modalExemploIA">
                                                <i class="ki-duotone ki-information-5 fs-2">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                    <span class="path3"></span>
                                                </i>
                                                Ver Exemplo
                                            </button>
                                            <button type="button" class="btn btn-primary" id="btnGerarIA">
                                                <i class="ki-duotone ki-stars fs-2">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                                Gerar Vaga com IA
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Resultado da Geração -->
                            <div id="ia_resultado_geracao"></div>
                            
                            <!-- Botões de Refinamento -->
                            <div id="ia_botoes_refinamento" class="d-none">
                                <div class="separator separator-dashed my-7"></div>
                                <h4 class="mb-4">
                                    <i class="ki-duotone ki-wrench fs-2 me-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    Refinar Vaga
                                </h4>
                                <div class="d-flex flex-wrap gap-2 mb-4">
                                    <button type="button" class="btn btn-sm btn-light-primary btn-refinar-ia" data-acao="Tornar a descrição mais formal e profissional">
                                        <i class="ki-duotone ki-profile-circle fs-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                        Mais Formal
                                    </button>
                                    <button type="button" class="btn btn-sm btn-light-primary btn-refinar-ia" data-acao="Adicionar mais requisitos técnicos detalhados">
                                        <i class="ki-duotone ki-code fs-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span></i>
                                        + Requisitos Técnicos
                                    </button>
                                    <button type="button" class="btn btn-sm btn-light-primary btn-refinar-ia" data-acao="Simplificar a linguagem para torná-la mais acessível">
                                        <i class="ki-duotone ki-message-text-2 fs-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                        Simplificar
                                    </button>
                                    <button type="button" class="btn btn-sm btn-light-primary btn-refinar-ia" data-acao="Aumentar a faixa salarial em 20%">
                                        <i class="ki-duotone ki-chart-simple fs-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span></i>
                                        Aumentar Salário
                                    </button>
                                    <button type="button" class="btn btn-sm btn-light-primary btn-refinar-ia" data-acao="Adicionar mais benefícios atrativos">
                                        <i class="ki-duotone ki-gift fs-3"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                        + Benefícios
                                    </button>
                                </div>
                                
                                <div class="input-group">
                                    <input type="text" class="form-control" id="ia_instrucao_refinamento" 
                                           placeholder="Ou descreva como quer refinar a vaga...">
                                    <button type="button" class="btn btn-primary" id="btnRefinarCustom">
                                        <i class="ki-duotone ki-arrow-right fs-3">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        Refinar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header border-0 pt-6">
                        <div class="card-title">
                            <h2>
                                Nova Vaga
                                <?php if ($openai_configurada): ?>
                                <small class="text-muted fs-7 ms-2">(Preencha manualmente ou use IA acima)</small>
                                <?php endif; ?>
                            </h2>
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
            console.log('Dados do formulário:', Object.fromEntries(formData));
            
            const response = await fetch('../api/recrutamento/vagas/criar.php', {
                method: 'POST',
                body: formData
            });
            
            console.log('Resposta recebida:', response.status, response.statusText);
            
            let data;
            const contentType = response.headers.get('content-type');
            
            if (contentType && contentType.includes('application/json')) {
                data = await response.json();
            } else {
                const text = await response.text();
                console.error('Resposta não é JSON:', text);
                throw new Error('Resposta inválida do servidor: ' + text.substring(0, 200));
            }
            
            console.log('Dados recebidos:', data);
            
            if (data.success) {
                alert('Vaga criada com sucesso!');
                window.location.href = 'vagas.php';
            } else {
                const mensagemErro = data.message || 'Erro desconhecido ao criar vaga';
                console.error('Erro retornado pela API:', mensagemErro);
                alert('Erro: ' + mensagemErro);
                isSubmitting = false;
                if (btnSubmit) {
                    btnSubmit.disabled = false;
                    btnSubmit.innerHTML = btnOriginalText;
                }
            }
        } catch (error) {
            console.error('Erro ao criar vaga:', error);
            console.error('Stack trace:', error.stack);
            alert('Erro ao criar vaga: ' + error.message + '\n\nVerifique o console (F12) para mais detalhes.');
            isSubmitting = false;
            if (btnSubmit) {
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = btnOriginalText;
            }
        }
    });
});
</script>

<!-- Script de Geração com IA -->
<?php
$openai_check = false;
try {
    $stmt = $pdo->query("SELECT ativo FROM openai_config WHERE ativo = 1 LIMIT 1");
    $openai_check = $stmt->fetch() ? true : false;
} catch (PDOException $e) {}
?>
<?php if ($openai_check): ?>
<script src="../assets/js/vaga_ia_generator.js"></script>
<?php endif; ?>

<!-- Modal: Exemplo de Uso da IA -->
<div class="modal fade" id="modalExemploIA" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">
                    <i class="ki-duotone ki-information-5 fs-2 me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    Como Usar a IA para Gerar Vagas
                </h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body">
                <div class="mb-7">
                    <h4 class="mb-3">1. Selecione o Tipo de Vaga</h4>
                    <p class="text-muted">Escolha entre Tecnologia, Administrativa, Vendas, Operacional ou Genérica. Cada tipo otimiza a geração para a área específica.</p>
                </div>
                
                <div class="mb-7">
                    <h4 class="mb-3">2. Descreva a Vaga</h4>
                    <p class="text-muted mb-3">Quanto mais detalhes, melhor! Inclua informações como:</p>
                    <ul class="text-muted">
                        <li><strong>Cargo/Função:</strong> "Desenvolvedor Full Stack", "Analista Financeiro", "Vendedor Externo"</li>
                        <li><strong>Tipo de Empresa:</strong> "startup de fintech", "indústria automotiva", "varejo de moda"</li>
                        <li><strong>Modalidade:</strong> "Remoto", "Presencial em São Paulo", "Híbrido"</li>
                        <li><strong>Faixa Salarial:</strong> "de 5k a 8k", "até 12 mil", "salário competitivo + comissões"</li>
                        <li><strong>Experiência:</strong> "experiência mínima 3 anos", "júnior/pleno", "sem experiência"</li>
                        <li><strong>Requisitos Principais:</strong> "conhecimento em Python e React", "CNH B obrigatória", "inglês fluente"</li>
                        <li><strong>Benefícios:</strong> "VA, VR, plano de saúde, gympass"</li>
                    </ul>
                </div>
                
                <div class="mb-7">
                    <h4 class="mb-3">3. Exemplos Práticos</h4>
                    
                    <div class="card bg-light-primary mb-3">
                        <div class="card-body">
                            <h5 class="text-primary mb-2">Exemplo 1: Vaga de Tecnologia</h5>
                            <p class="mb-0"><em>"Desenvolvedor Full Stack Python/React para startup de fintech em crescimento. Remoto, salário entre 8k-12k, experiência mínima 3 anos com AWS, Docker e microsserviços. Benefícios: VA, VR, plano de saúde, stock options, ambiente inovador."</em></p>
                        </div>
                    </div>
                    
                    <div class="card bg-light-success mb-3">
                        <div class="card-body">
                            <h5 class="text-success mb-2">Exemplo 2: Vaga Administrativa</h5>
                            <p class="mb-0"><em>"Assistente Administrativo para empresa de logística. Presencial em São Paulo, salário 3k-4k, experiência com Excel avançado, atendimento telefônico e gestão de documentos. Benefícios: VA, VR, convênio médico."</em></p>
                        </div>
                    </div>
                    
                    <div class="card bg-light-warning mb-3">
                        <div class="card-body">
                            <h5 class="text-warning mb-2">Exemplo 3: Vaga de Vendas</h5>
                            <p class="mb-0"><em>"Vendedor Externo para revenda de veículos. Atuação em Campo Grande/MS, salário fixo 2.5k + comissões agressivas (média 5k-10k/mês), carteira própria de clientes desejável, experiência em vendas B2C. Benefícios: VA, carro da empresa, celular."</em></p>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-info d-flex align-items-center p-5">
                    <i class="ki-duotone ki-shield-tick fs-2hx text-info me-4">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    <div>
                        <h4 class="mb-1 text-info">Dica Importante</h4>
                        <span>A IA irá gerar uma vaga completa, mas você sempre pode revisar e editar todos os campos antes de salvar. Use os botões de refinamento para ajustar a vaga rapidamente!</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Entendi!</button>
            </div>
        </div>
    </div>
</div>

<!--begin::Tutorial System-->
<link href="https://cdn.jsdelivr.net/npm/intro.js@7.2.0/introjs.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/intro.js@7.2.0/intro.min.js"></script>
<script src="../assets/js/tutorial-system.js"></script>
<script>
// Configuração do tutorial para esta página
window.pageTutorial = {
    pageId: 'vaga_add',
    steps: [
        {
            title: 'Bem-vindo ao Cadastro de Vaga',
            intro: 'Este tutorial vai te guiar pelas principais seções do formulário de cadastro de vaga. Vamos começar!'
        },
        {
            element: '#empresaSelect',
            title: 'Informações Básicas',
            intro: 'Comece selecionando a empresa e preenchendo as informações básicas da vaga: título, setor, cargo e quantidade de vagas disponíveis.'
        },
        {
            element: 'textarea[name="descricao"]',
            title: 'Descrição da Vaga',
            intro: 'Descreva detalhadamente a vaga, incluindo responsabilidades principais, objetivos do cargo e contexto da posição. Este campo é obrigatório.'
        },
        {
            element: '#salario_min',
            title: 'Remuneração',
            intro: 'Informe a faixa salarial da vaga (mínimo e máximo). Use o formato brasileiro (ex: 5.000,00). O sistema aplica máscara automaticamente.'
        },
        {
            element: '#tipo_contrato',
            title: 'Tipo de Contrato',
            intro: 'Selecione o tipo de contrato oferecido: CLT, PJ, Estágio, Temporário ou Freelance.'
        },
        {
            element: 'input[name="beneficios[]"]:first-of-type',
            title: 'Benefícios',
            intro: 'Marque os benefícios oferecidos pela vaga. Você pode selecionar múltiplos benefícios padrão ou adicionar benefícios customizados no campo abaixo.'
        },
        {
            element: '#beneficioCustom',
            title: 'Benefícios Customizados',
            intro: 'Digite um benefício personalizado e pressione Enter para adicioná-lo à lista. Isso é útil para benefícios específicos da empresa.'
        },
        {
            element: 'textarea[name="requisitos_obrigatorios"]',
            title: 'Requisitos',
            intro: 'Preencha os requisitos obrigatórios e desejáveis para a vaga. Seja específico sobre formação, experiência, conhecimentos técnicos, etc.'
        },
        {
            element: 'textarea[name="competencias_tecnicas"]',
            title: 'Competências',
            intro: 'Descreva as competências técnicas e comportamentais necessárias para o cargo. Isso ajuda os candidatos a entenderem o perfil procurado.'
        },
        {
            element: 'select[name="modalidade"]',
            title: 'Modalidade e Localização',
            intro: 'Informe a modalidade de trabalho (Presencial, Remoto ou Híbrido), localização e horário de trabalho.'
        },
        {
            element: 'input[name="etapas[]"]:first-of-type',
            title: 'Etapas do Processo',
            intro: 'Selecione as etapas que farão parte do processo seletivo desta vaga. Se não selecionar nenhuma, serão usadas as etapas padrão do sistema.'
        },
        {
            element: '#publicar_portal',
            title: 'Publicação',
            intro: 'Marque "Publicar no Portal" para que a vaga apareça no portal de vagas. Você também pode optar por usar uma landing page customizada.'
        },
        {
            element: '#formVaga button[type="submit"]',
            title: 'Salvar Vaga',
            intro: 'Após preencher todas as informações, clique em "Salvar Vaga" para criar a vaga. O sistema validará os dados antes de salvar.'
        }
    ]
};
</script>
<!--end::Tutorial System-->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

