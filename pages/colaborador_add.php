<?php
/**
 * Adicionar Colaborador
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/upload_foto.php';

require_page_permission('colaborador_add.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Processa POST ANTES de incluir o header (para evitar erro de headers already sent)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Para RH, valida se a empresa selecionada está nas empresas permitidas
    $empresa_id = $_POST['empresa_id'] ?? null;
    if ($usuario['role'] === 'RH' && $empresa_id) {
        if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
            if (!in_array($empresa_id, $usuario['empresas_ids'])) {
                redirect('colaborador_add.php', 'Você não tem permissão para cadastrar colaboradores nesta empresa!', 'error');
            }
        } elseif (isset($usuario['empresa_id']) && $empresa_id != $usuario['empresa_id']) {
            redirect('colaborador_add.php', 'Você não tem permissão para cadastrar colaboradores nesta empresa!', 'error');
        }
    } elseif ($usuario['role'] !== 'ADMIN' && empty($empresa_id)) {
        // Se não for ADMIN e não tiver empresa_id no POST, usa a primeira empresa disponível
        if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
            $empresa_id = $usuario['empresas_ids'][0];
        } else {
            $empresa_id = $usuario['empresa_id'] ?? null;
        }
    }
    $setor_id = $_POST['setor_id'] ?? null;
    $cargo_id = $_POST['cargo_id'] ?? null;
    $nome_completo = sanitize($_POST['nome_completo'] ?? '');
    $cpf = preg_replace('/[^0-9]/', '', $_POST['cpf'] ?? '');
    $rg = sanitize($_POST['rg'] ?? '');
    $data_nascimento = $_POST['data_nascimento'] ?? null;
    $estado_civil = $_POST['estado_civil'] ?? null;
    $telefone = sanitize($_POST['telefone'] ?? '');
    $email_pessoal = sanitize($_POST['email_pessoal'] ?? '');
    $data_inicio = $_POST['data_inicio'] ?? null;
    $status = $_POST['status'] ?? 'ativo';
    $tipo_contrato = $_POST['tipo_contrato'] ?? 'PJ';
    $observacoes = sanitize($_POST['observacoes'] ?? '');
    $nivel_hierarquico_id = !empty($_POST['nivel_hierarquico_id']) ? (int)$_POST['nivel_hierarquico_id'] : null;
    $lider_id = !empty($_POST['lider_id']) ? (int)$_POST['lider_id'] : null;
    $senha = $_POST['senha'] ?? '';
    $salario = !empty($_POST['salario']) ? str_replace(['.', ','], ['', '.'], $_POST['salario']) : null;
    $pix = sanitize($_POST['pix'] ?? '');
    $banco = sanitize($_POST['banco'] ?? '');
    $agencia = sanitize($_POST['agencia'] ?? '');
    $conta = sanitize($_POST['conta'] ?? '');
    $tipo_conta = $_POST['tipo_conta'] ?? null;
    $cnpj = preg_replace('/[^0-9]/', '', $_POST['cnpj'] ?? '');
    $cep = preg_replace('/[^0-9]/', '', $_POST['cep'] ?? '');
    $logradouro = sanitize($_POST['logradouro'] ?? '');
    $numero = sanitize($_POST['numero'] ?? '');
    $complemento = sanitize($_POST['complemento'] ?? '');
    $bairro = sanitize($_POST['bairro'] ?? '');
    $cidade_endereco = sanitize($_POST['cidade_endereco'] ?? '');
    $estado_endereco = strtoupper(sanitize($_POST['estado_endereco'] ?? ''));
    
    if (empty($nome_completo) || empty($empresa_id) || empty($setor_id) || empty($cargo_id) || empty($data_inicio)) {
        redirect('colaborador_add.php', 'Preencha os campos obrigatórios!', 'error');
    }
    
    try {
        // Processa upload de foto se houver
        $foto_path = null;
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $upload_result = upload_foto_perfil($_FILES['foto'], 'colaborador');
            if ($upload_result['success']) {
                $foto_path = $upload_result['path'];
            } else {
                redirect('colaborador_add.php', 'Erro no upload da foto: ' . $upload_result['error'], 'error');
            }
        }
        
        // Processa senha se fornecida
        $senha_hash = null;
        if (!empty($senha)) {
            if (strlen($senha) < 6) {
                redirect('colaborador_add.php', 'A senha deve ter no mínimo 6 caracteres!', 'error');
            }
            $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO colaboradores 
            (empresa_id, setor_id, cargo_id, nivel_hierarquico_id, lider_id, nome_completo, cpf, cnpj, rg, data_nascimento, estado_civil, telefone, email_pessoal, data_inicio, status, tipo_contrato, salario, pix, banco, agencia, conta, tipo_conta, cep, logradouro, numero, complemento, bairro, cidade_endereco, estado_endereco, observacoes, foto, senha_hash)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $empresa_id, $setor_id, $cargo_id, $nivel_hierarquico_id, $lider_id, $nome_completo, $cpf, 
            !empty($cnpj) ? $cnpj : null, $rg, $data_nascimento ?: null, $estado_civil ?: null, $telefone, $email_pessoal, $data_inicio, 
            $status, $tipo_contrato, $salario, $pix, $banco, $agencia, $conta, $tipo_conta, 
            !empty($cep) ? $cep : null, $logradouro, $numero, $complemento, $bairro, $cidade_endereco, 
            !empty($estado_endereco) ? $estado_endereco : null, $observacoes, $foto_path, $senha_hash
        ]);
        
        $colaborador_id = $pdo->lastInsertId();
        
        // Processa filhos
        if (isset($_POST['filhos']) && is_array($_POST['filhos'])) {
            foreach ($_POST['filhos'] as $filho) {
                if (!empty($filho['nome'])) {
                    $nome_filho = sanitize($filho['nome']);
                    $data_nasc_filho = !empty($filho['data_nascimento']) ? $filho['data_nascimento'] : null;
                    $idade_filho = !empty($filho['idade']) ? (int)$filho['idade'] : null;
                    
                    // Calcula idade se tiver data de nascimento
                    if ($data_nasc_filho && !$idade_filho) {
                        $data_nasc = new DateTime($data_nasc_filho);
                        $hoje = new DateTime();
                        $idade_filho = $hoje->diff($data_nasc)->y;
                    }
                    
                    $stmt_filho = $pdo->prepare("
                        INSERT INTO colaboradores_filhos (colaborador_id, nome, data_nascimento, idade)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt_filho->execute([$colaborador_id, $nome_filho, $data_nasc_filho, $idade_filho]);
                }
            }
        }
        
        // Processa formações
        if (isset($_POST['formacoes']) && is_array($_POST['formacoes'])) {
            foreach ($_POST['formacoes'] as $formacao) {
                if (!empty($formacao['nome'])) {
                    $stmt_formacao = $pdo->prepare("
                        INSERT INTO colaboradores_formacoes 
                        (colaborador_id, tipo, nome, instituicao, data_inicio, data_conclusao, carga_horaria, status, observacoes)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt_formacao->execute([
                        $colaborador_id,
                        $formacao['tipo'] ?? 'curso',
                        sanitize($formacao['nome']),
                        !empty($formacao['instituicao']) ? sanitize($formacao['instituicao']) : null,
                        !empty($formacao['data_inicio']) ? $formacao['data_inicio'] : null,
                        !empty($formacao['data_conclusao']) ? $formacao['data_conclusao'] : null,
                        !empty($formacao['carga_horaria']) ? (int)$formacao['carga_horaria'] : null,
                        $formacao['status'] ?? 'concluido',
                        !empty($formacao['observacoes']) ? sanitize($formacao['observacoes']) : null
                    ]);
                }
            }
        }
        
        // Envia email de boas-vindas se solicitado e template estiver ativo
        $enviar_email = isset($_POST['enviar_email_boas_vindas']) && $_POST['enviar_email_boas_vindas'] == '1';
        if ($enviar_email && !empty($email_pessoal)) {
            require_once __DIR__ . '/../includes/email_templates.php';
            // Passa a senha em texto claro apenas para o email (não armazena)
            $senha_para_email = !empty($senha) ? $senha : null;
            enviar_email_novo_colaborador($colaborador_id, $senha_para_email);
        }
        
        redirect('colaboradores.php', 'Colaborador cadastrado com sucesso!');
    } catch (PDOException $e) {
        redirect('colaborador_add.php', 'Erro ao cadastrar: ' . $e->getMessage(), 'error');
    }
}

// Busca empresas
if ($usuario['role'] === 'ADMIN') {
    $stmt_empresas = $pdo->query("SELECT id, nome_fantasia FROM empresas WHERE status = 'ativo' ORDER BY nome_fantasia");
    $empresas = $stmt_empresas->fetchAll();
} elseif ($usuario['role'] === 'RH') {
    // RH pode ter múltiplas empresas
    if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
        $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
        $stmt_empresas = $pdo->prepare("SELECT id, nome_fantasia FROM empresas WHERE id IN ($placeholders) AND status = 'ativo' ORDER BY nome_fantasia");
        $stmt_empresas->execute($usuario['empresas_ids']);
        $empresas = $stmt_empresas->fetchAll();
    } else {
        // Fallback para compatibilidade
        $stmt_empresas = $pdo->prepare("SELECT id, nome_fantasia FROM empresas WHERE id = ? AND status = 'ativo'");
        $stmt_empresas->execute([$usuario['empresa_id'] ?? 0]);
        $empresas = $stmt_empresas->fetchAll();
    }
} else {
    // Outros roles (GESTOR, etc) - apenas uma empresa
    $stmt_empresas = $pdo->prepare("SELECT id, nome_fantasia FROM empresas WHERE id = ? AND status = 'ativo'");
    $stmt_empresas->execute([$usuario['empresa_id'] ?? 0]);
    $empresas = $stmt_empresas->fetchAll();
}

// Agora inclui o header (após processar POST para evitar erro de headers already sent)
$page_title = 'Novo Colaborador';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <h2 class="mb-4"><i class="bi bi-person-plus"></i> Novo Colaborador</h2>
            
            <div class="card">
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="row">
                            <?php if ($usuario['role'] === 'ADMIN' || ($usuario['role'] === 'RH' && count($empresas) > 1)): ?>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Empresa *</label>
                                <select name="empresa_id" id="empresa_id" class="form-select" required>
                                    <option value="">Selecione...</option>
                                    <?php foreach ($empresas as $emp): ?>
                                    <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['nome_fantasia']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php else: ?>
                            <input type="hidden" name="empresa_id" value="<?= !empty($empresas) ? $empresas[0]['id'] : ($usuario['empresa_id'] ?? '') ?>">
                            <?php endif; ?>
                            
                            <div class="col-md-<?= ($usuario['role'] === 'ADMIN' || ($usuario['role'] === 'RH' && count($empresas) > 1)) ? '3' : '6' ?> mb-3">
                                <label class="form-label">Setor *</label>
                                <select name="setor_id" id="setor_id" class="form-select" required>
                                    <option value="">Selecione...</option>
                                </select>
                            </div>
                            
                            <div class="col-md-<?= ($usuario['role'] === 'ADMIN' || ($usuario['role'] === 'RH' && count($empresas) > 1)) ? '3' : '6' ?> mb-3">
                                <label class="form-label">Cargo *</label>
                                <select name="cargo_id" id="cargo_id" class="form-select" required>
                                    <option value="">Selecione...</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nível Hierárquico</label>
                                <select name="nivel_hierarquico_id" id="nivel_hierarquico_id" class="form-select">
                                    <option value="">Selecione...</option>
                                    <?php
                                    try {
                                        $stmt_niveis = $pdo->query("SELECT * FROM niveis_hierarquicos WHERE status = 'ativo' ORDER BY nivel ASC, nome ASC");
                                        $niveis = $stmt_niveis->fetchAll();
                                        foreach ($niveis as $nivel):
                                    ?>
                                    <option value="<?= $nivel['id'] ?>"><?= htmlspecialchars($nivel['nome']) ?> (Nível <?= $nivel['nivel'] ?>)</option>
                                    <?php
                                        endforeach;
                                    } catch (PDOException $e) {
                                        // Tabela pode não existir ainda
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Líder</label>
                                <select name="lider_id" id="lider_id" class="form-select">
                                    <option value="">Nenhum</option>
                                </select>
                                <small class="text-muted">Selecione o líder deste colaborador</small>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label class="form-label">Foto de Perfil</label>
                                <input type="file" name="foto" id="foto" class="form-control" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
                                <small class="text-muted">Formatos aceitos: JPG, PNG, GIF, WEBP. Tamanho máximo: 5MB</small>
                                <div id="foto_preview" class="mt-2" style="display: none;">
                                    <img id="foto_preview_img" src="" alt="Preview" style="max-width: 200px; max-height: 200px; border-radius: 8px;">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Nome Completo *</label>
                                <input type="text" name="nome_completo" class="form-control" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">CPF</label>
                                <input type="text" name="cpf" class="form-control" placeholder="000.000.000-00">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">RG</label>
                                <input type="text" name="rg" class="form-control">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Data de Nascimento</label>
                                <input type="date" name="data_nascimento" class="form-control">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Estado Civil</label>
                                <select name="estado_civil" class="form-select">
                                    <option value="">Selecione...</option>
                                    <option value="solteiro">Solteiro(a)</option>
                                    <option value="casado">Casado(a)</option>
                                    <option value="divorciado">Divorciado(a)</option>
                                    <option value="viuvo">Viúvo(a)</option>
                                    <option value="uniao_estavel">União Estável</option>
                                    <option value="outro">Outro</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Telefone</label>
                                <input type="text" name="telefone" class="form-control" placeholder="(00) 00000-0000">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Email Pessoal</label>
                                <input type="email" name="email_pessoal" class="form-control">
                            </div>
                        </div>
                        
                        <!-- Filhos -->
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <hr>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0">Filhos</h5>
                                    <button type="button" class="btn btn-sm btn-primary" onclick="adicionarFilho()">
                                        <i class="ki-duotone ki-plus fs-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        Adicionar Filho
                                    </button>
                                </div>
                                <div id="filhos_container">
                                    <!-- Filhos serão adicionados aqui dinamicamente -->
                                </div>
                            </div>
                        </div>
                        
                        <!-- Formações -->
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <hr>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0">Cursos e Formações</h5>
                                    <button type="button" class="btn btn-sm btn-primary" onclick="adicionarFormacao()">
                                        <i class="ki-duotone ki-plus fs-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        Adicionar Formação
                                    </button>
                                </div>
                                <div id="formacoes_container">
                                    <!-- Formações serão adicionadas aqui dinamicamente -->
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Data de Início *</label>
                                <input type="date" name="data_inicio" class="form-control" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Tipo de Contrato</label>
                                <select name="tipo_contrato" id="tipo_contrato" class="form-select">
                                    <option value="PJ" selected>PJ</option>
                                    <option value="CLT">CLT</option>
                                    <option value="Estágio">Estágio</option>
                                    <option value="Terceirizado">Terceirizado</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="ativo" selected>Ativo</option>
                                    <option value="pausado">Pausado</option>
                                    <option value="desligado">Desligado</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Salário</label>
                                <input type="text" name="salario" id="salario" class="form-control" placeholder="0,00">
                            </div>
                        </div>
                        
                        <div class="row" id="campo_cnpj" style="display: block;">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">CNPJ</label>
                                <div class="input-group">
                                    <input type="text" name="cnpj" id="cnpj" class="form-control" placeholder="00.000.000/0000-00">
                                    <button type="button" class="btn btn-primary" id="btn_sincronizar_cnpj" onclick="sincronizarCNPJ()" style="display: none;">
                                        <i class="ki-duotone ki-arrows-circle fs-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        Sincronizar
                                    </button>
                                </div>
                                <small class="text-muted">Apenas para colaboradores PJ</small>
                            </div>
                        </div>
                        
                        <div class="row" id="campo_endereco">
                            <div class="col-md-12">
                                <hr>
                                <h5 class="mb-3">Endereço</h5>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">CEP</label>
                                <input type="text" name="cep" id="cep" class="form-control" placeholder="00000-000">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Logradouro</label>
                                <input type="text" name="logradouro" id="logradouro" class="form-control" placeholder="Rua, Avenida, etc.">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Número</label>
                                <input type="text" name="numero" id="numero" class="form-control" placeholder="123">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Complemento</label>
                                <input type="text" name="complemento" id="complemento" class="form-control" placeholder="Apto, Sala, etc.">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Bairro</label>
                                <input type="text" name="bairro" id="bairro" class="form-control" placeholder="Nome do bairro">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Cidade</label>
                                <input type="text" name="cidade_endereco" id="cidade_endereco" class="form-control" placeholder="Nome da cidade">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Estado (UF)</label>
                                <input type="text" name="estado_endereco" id="estado_endereco" class="form-control" maxlength="2" placeholder="SP">
                            </div>
                        </div>
                        
                        <hr>
                        <h5 class="mb-3">Dados Bancários</h5>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">PIX</label>
                                <input type="text" name="pix" class="form-control" placeholder="Chave PIX">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Banco</label>
                                <input type="text" name="banco" class="form-control" placeholder="Nome do banco">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Agência</label>
                                <input type="text" name="agencia" class="form-control" placeholder="0000">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Conta</label>
                                <input type="text" name="conta" class="form-control" placeholder="00000-0">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Tipo de Conta</label>
                                <select name="tipo_conta" class="form-select">
                                    <option value="">Selecione...</option>
                                    <option value="corrente">Corrente</option>
                                    <option value="poupanca">Poupança</option>
                                </select>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Senha de Acesso</label>
                                <input type="password" name="senha" id="senha" class="form-control" minlength="6" placeholder="Opcional - mínimo 6 caracteres">
                                <small class="text-muted">Permite acesso ao sistema como colaborador</small>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="enviar_email_boas_vindas" id="enviar_email_boas_vindas" value="1" checked>
                                    <label class="form-check-label" for="enviar_email_boas_vindas">
                                        <strong>Enviar email de boas-vindas</strong>
                                    </label>
                                    <small class="d-block text-muted">Envia email automático com dados de cadastro e acesso ao sistema (se senha for informada)</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Observações</label>
                            <textarea name="observacoes" class="form-control" rows="3"></textarea>
                        </div>
                        
                        <div class="d-flex justify-content-end gap-2">
                            <a href="colaboradores.php" class="btn btn-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary">Salvar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Carrega setores quando empresa é selecionada
document.getElementById('empresa_id')?.addEventListener('change', function() {
    const empresaId = this.value;
    const setorSelect = document.getElementById('setor_id');
    const cargoSelect = document.getElementById('cargo_id');
    
    setorSelect.innerHTML = '<option value="">Carregando...</option>';
    cargoSelect.innerHTML = '<option value="">Carregando...</option>';
    
    if (empresaId) {
        fetch(`../api/get_setores.php?empresa_id=${empresaId}`)
            .then(r => r.json())
            .then(data => {
                setorSelect.innerHTML = '<option value="">Selecione...</option>';
                const setores = Array.isArray(data) ? data : (data.setores || []);
                setores.forEach(setor => {
                    setorSelect.innerHTML += `<option value="${setor.id}">${setor.nome_setor}</option>`;
                });
            })
            .catch(() => {
                setorSelect.innerHTML = '<option value="">Erro ao carregar</option>';
            });
        
        fetch(`../api/get_cargos.php?empresa_id=${empresaId}`)
            .then(r => r.json())
            .then(data => {
                cargoSelect.innerHTML = '<option value="">Selecione...</option>';
                const cargos = Array.isArray(data) ? data : (data.cargos || []);
                cargos.forEach(cargo => {
                    cargoSelect.innerHTML += `<option value="${cargo.id}">${cargo.nome_cargo}</option>`;
                });
            })
            .catch(() => {
                cargoSelect.innerHTML = '<option value="">Erro ao carregar</option>';
            });
    } else {
        setorSelect.innerHTML = '<option value="">Selecione...</option>';
        cargoSelect.innerHTML = '<option value="">Selecione...</option>';
    }
});

// Se houver apenas uma empresa (campo hidden), carrega setores e cargos automaticamente
<?php if (($usuario['role'] !== 'ADMIN' && ($usuario['role'] !== 'RH' || count($empresas) <= 1)) && !empty($empresas)): ?>
document.addEventListener('DOMContentLoaded', function() {
    const empresaId = '<?= $empresas[0]['id'] ?>';
    const setorSelect = document.getElementById('setor_id');
    const cargoSelect = document.getElementById('cargo_id');
    
    if (empresaId && setorSelect && cargoSelect) {
        // Carrega setores
        fetch(`../api/get_setores.php?empresa_id=${empresaId}`)
            .then(r => r.json())
            .then(data => {
                setorSelect.innerHTML = '<option value="">Selecione...</option>';
                const setores = Array.isArray(data) ? data : (data.setores || []);
                setores.forEach(setor => {
                    setorSelect.innerHTML += `<option value="${setor.id}">${setor.nome_setor}</option>`;
                });
            })
            .catch(() => {
                setorSelect.innerHTML = '<option value="">Erro ao carregar</option>';
            });
        
        // Carrega cargos
        fetch(`../api/get_cargos.php?empresa_id=${empresaId}`)
            .then(r => r.json())
            .then(data => {
                cargoSelect.innerHTML = '<option value="">Selecione...</option>';
                const cargos = Array.isArray(data) ? data : (data.cargos || []);
                cargos.forEach(cargo => {
                    cargoSelect.innerHTML += `<option value="${cargo.id}">${cargo.nome_cargo}</option>`;
                });
            })
            .catch(() => {
                cargoSelect.innerHTML = '<option value="">Erro ao carregar</option>';
            });
    }
});
<?php endif; ?>

// Carrega líderes quando empresa, setor ou nível hierárquico mudar
function carregarLideres() {
    const empresaId = document.getElementById('empresa_id')?.value || document.querySelector('input[name="empresa_id"][type="hidden"]')?.value || '<?= ($usuario['role'] === 'ADMIN' || ($usuario['role'] === 'RH' && count($empresas) > 1)) ? '' : (!empty($empresas) ? $empresas[0]['id'] : ($usuario['empresa_id'] ?? '')) ?>';
    const setorId = document.getElementById('setor_id')?.value || '';
    const nivelId = document.getElementById('nivel_hierarquico_id')?.value || '';
    const liderSelect = document.getElementById('lider_id');
    
    if (!empresaId) {
        liderSelect.innerHTML = '<option value="">Selecione uma empresa primeiro</option>';
        return;
    }
    
    liderSelect.innerHTML = '<option value="">Carregando...</option>';
    
    let url = `../api/get_lideres.php?empresa_id=${empresaId}`;
    if (setorId) url += `&setor_id=${setorId}`;
    if (nivelId) url += `&nivel_hierarquico_id=${nivelId}`;
    
    fetch(url)
        .then(r => r.json())
        .then(data => {
            liderSelect.innerHTML = '<option value="">Nenhum</option>';
            const lideres = Array.isArray(data) ? data : (data.lideres || []);
            lideres.forEach(lider => {
                const nivelInfo = lider.nivel_nome ? ` - ${lider.nivel_nome}` : '';
                liderSelect.innerHTML += `<option value="${lider.id}">${lider.nome_completo}${nivelInfo}</option>`;
            });
        })
        .catch(() => {
            liderSelect.innerHTML = '<option value="">Erro ao carregar</option>';
        });
}

// Event listeners para carregar líderes
document.getElementById('empresa_id')?.addEventListener('change', carregarLideres);
document.getElementById('setor_id')?.addEventListener('change', carregarLideres);
document.getElementById('nivel_hierarquico_id')?.addEventListener('change', carregarLideres);

// Preview de foto
document.getElementById('foto')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('foto_preview').style.display = 'block';
            document.getElementById('foto_preview_img').src = e.target.result;
        };
        reader.readAsDataURL(file);
    } else {
        document.getElementById('foto_preview').style.display = 'none';
    }
});

// Função para mostrar/esconder campo CNPJ e botão sincronizar
function toggleCNPJ() {
    const tipoContrato = document.getElementById('tipo_contrato')?.value;
    const campoCNPJ = document.getElementById('campo_cnpj');
    const btnSincronizar = document.getElementById('btn_sincronizar_cnpj');
    const cnpjInput = document.getElementById('cnpj');
    
    if (campoCNPJ) {
        campoCNPJ.style.display = tipoContrato === 'PJ' ? 'block' : 'none';
    }
    
    // Mostra/esconde botão sincronizar baseado no CNPJ preenchido
    if (cnpjInput && btnSincronizar) {
        const cnpjValue = cnpjInput.value.replace(/[^0-9]/g, '');
        btnSincronizar.style.display = (tipoContrato === 'PJ' && cnpjValue.length === 14) ? 'inline-block' : 'none';
    }
}

// Função para sincronizar dados do CNPJ
function sincronizarCNPJ() {
    const cnpjInput = document.getElementById('cnpj');
    const btnSincronizar = document.getElementById('btn_sincronizar_cnpj');
    
    if (!cnpjInput) return;
    
    const cnpj = cnpjInput.value.replace(/[^0-9]/g, '');
    
    if (cnpj.length !== 14) {
        Swal.fire({
            text: 'Por favor, informe um CNPJ válido antes de sincronizar',
            icon: 'warning',
            buttonsStyling: false,
            confirmButtonText: 'OK',
            customClass: {
                confirmButton: 'btn btn-primary'
            }
        });
        return;
    }
    
    // Desabilita botão e mostra loading
    btnSincronizar.disabled = true;
    btnSincronizar.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Buscando...';
    
    fetch(`../api/buscar_cnpj.php?cnpj=${cnpj}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Erro na resposta da API: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            btnSincronizar.disabled = false;
            btnSincronizar.innerHTML = '<i class="ki-duotone ki-arrows-circle fs-2"><span class="path1"></span><span class="path2"></span></i> Sincronizar';
            
            if (data.success && data.data) {
                const dados = data.data;
                
                // Preenche campos de endereço (verifica se existem antes)
                const campoCep = document.getElementById('cep');
                if (dados.cep && campoCep) {
                    campoCep.value = dados.cep.replace(/^(\d{5})(\d{3})$/, '$1-$2');
                }
                
                const campoLogradouro = document.getElementById('logradouro');
                if (dados.logradouro && campoLogradouro) {
                    campoLogradouro.value = dados.logradouro;
                }
                
                const campoNumero = document.getElementById('numero');
                if (dados.numero && campoNumero) {
                    campoNumero.value = dados.numero;
                }
                
                const campoComplemento = document.getElementById('complemento');
                if (dados.complemento && campoComplemento) {
                    campoComplemento.value = dados.complemento;
                }
                
                const campoBairro = document.getElementById('bairro');
                if (dados.bairro && campoBairro) {
                    campoBairro.value = dados.bairro;
                }
                
                const campoCidade = document.getElementById('cidade_endereco');
                if (dados.cidade && campoCidade) {
                    campoCidade.value = dados.cidade;
                }
                
                const campoEstado = document.getElementById('estado_endereco');
                if (dados.estado && campoEstado) {
                    campoEstado.value = dados.estado;
                }
                
                // Se não tiver telefone preenchido, preenche com o da API
                const campoTelefone = document.getElementById('telefone');
                if (dados.telefone && campoTelefone && !campoTelefone.value) {
                    campoTelefone.value = dados.telefone;
                }
                
                // Se não tiver email preenchido, preenche com o da API
                const campoEmail = document.getElementById('email_pessoal');
                if (dados.email && campoEmail && !campoEmail.value) {
                    campoEmail.value = dados.email;
                }
                
                Swal.fire({
                    text: 'Dados sincronizados com sucesso!',
                    icon: 'success',
                    buttonsStyling: false,
                    confirmButtonText: 'OK',
                    customClass: {
                        confirmButton: 'btn btn-primary'
                    }
                });
            } else {
                Swal.fire({
                    text: data.error || 'Erro ao buscar dados do CNPJ',
                    icon: 'error',
                    buttonsStyling: false,
                    confirmButtonText: 'OK',
                    customClass: {
                        confirmButton: 'btn btn-primary'
                    }
                });
            }
        })
        .catch(error => {
            btnSincronizar.disabled = false;
            btnSincronizar.innerHTML = '<i class="ki-duotone ki-arrows-circle fs-2"><span class="path1"></span><span class="path2"></span></i> Sincronizar';
            
            Swal.fire({
                text: 'Erro ao conectar com a API: ' + error.message + '. Verifique sua conexão com a internet.',
                icon: 'error',
                buttonsStyling: false,
                confirmButtonText: 'OK',
                customClass: {
                    confirmButton: 'btn btn-primary'
                }
            });
        });
}

// Máscaras e validações
document.addEventListener('DOMContentLoaded', function() {
    // Mostra/esconde campo CNPJ baseado no tipo de contrato
    toggleCNPJ(); // Executa na carga da página
    
    // Adiciona listener para mudanças
    document.getElementById('tipo_contrato')?.addEventListener('change', toggleCNPJ);
    
    // Listener para CNPJ - mostra botão quando CNPJ válido
    document.getElementById('cnpj')?.addEventListener('input', function() {
        toggleCNPJ();
    });
    
    // Aplica máscaras quando jQuery estiver pronto
    if (typeof jQuery !== 'undefined' && jQuery.fn.mask) {
        // Máscara para salário
        jQuery('#salario').mask('#.##0,00', {reverse: true});
        
        // Máscara para CNPJ
        jQuery('#cnpj').mask('00.000.000/0000-00');
        
        // Máscara para CEP
        jQuery('#cep').mask('00000-000');
        
        // Máscara para Estado
        jQuery('#estado_endereco').mask('AA', {
            translation: {
                A: {pattern: /[A-Za-z]/, recursive: true}
            }
        });
    } else {
        // Aguarda jQuery estar disponível
        setTimeout(function() {
            if (typeof jQuery !== 'undefined' && jQuery.fn.mask) {
                jQuery('#salario').mask('#.##0,00', {reverse: true});
                jQuery('#cnpj').mask('00.000.000/0000-00');
                jQuery('#cep').mask('00000-000');
                jQuery('#estado_endereco').mask('AA', {
                    translation: {
                        A: {pattern: /[A-Za-z]/, recursive: true}
                    }
                });
            }
        }, 100);
    }
});

// Se não for admin, carrega setores e cargos da empresa do usuário
<?php if ($usuario['role'] !== 'ADMIN'): ?>
document.addEventListener('DOMContentLoaded', function() {
    const empresaId = <?= $usuario['empresa_id'] ?>;
    const setorSelect = document.getElementById('setor_id');
    const cargoSelect = document.getElementById('cargo_id');
    
    fetch(`../api/get_setores.php?empresa_id=${empresaId}`)
        .then(r => r.json())
        .then(data => {
            setorSelect.innerHTML = '<option value="">Selecione...</option>';
            const setores = Array.isArray(data) ? data : (data.setores || []);
            setores.forEach(setor => {
                setorSelect.innerHTML += `<option value="${setor.id}">${setor.nome_setor}</option>`;
            });
        })
        .catch(() => {
            setorSelect.innerHTML = '<option value="">Erro ao carregar</option>';
        });
    
    fetch(`../api/get_cargos.php?empresa_id=${empresaId}`)
        .then(r => r.json())
        .then(data => {
            cargoSelect.innerHTML = '<option value="">Selecione...</option>';
            const cargos = Array.isArray(data) ? data : (data.cargos || []);
            cargos.forEach(cargo => {
                cargoSelect.innerHTML += `<option value="${cargo.id}">${cargo.nome_cargo}</option>`;
            });
        })
        .catch(() => {
            cargoSelect.innerHTML = '<option value="">Erro ao carregar</option>';
        });
});
<?php endif; ?>
</script>

<script>
// Gerenciamento de Filhos
let filhosCount = 0;

function adicionarFilho() {
    filhosCount++;
    const container = document.getElementById('filhos_container');
    const filhoDiv = document.createElement('div');
    filhoDiv.className = 'card card-flush mb-3';
    filhoDiv.id = 'filho_' + filhosCount;
    filhoDiv.innerHTML = `
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0">Filho ${filhosCount}</h6>
                <button type="button" class="btn btn-sm btn-light-danger" onclick="removerFilho(${filhosCount})">
                    <i class="ki-duotone ki-trash fs-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                        <span class="path4"></span>
                        <span class="path5"></span>
                    </i>
                    Remover
                </button>
            </div>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Nome *</label>
                    <input type="text" name="filhos[${filhosCount}][nome]" class="form-control" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Data de Nascimento</label>
                    <input type="date" name="filhos[${filhosCount}][data_nascimento]" class="form-control" onchange="calcularIdadeFilho(${filhosCount}, this.value)">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Idade</label>
                    <input type="number" name="filhos[${filhosCount}][idade]" id="idade_filho_${filhosCount}" class="form-control" min="0" max="120">
                </div>
            </div>
        </div>
    `;
    container.appendChild(filhoDiv);
}

function removerFilho(id) {
    const filhoDiv = document.getElementById('filho_' + id);
    if (filhoDiv) {
        filhoDiv.remove();
    }
}

function calcularIdadeFilho(id, dataNascimento) {
    if (dataNascimento) {
        const dataNasc = new Date(dataNascimento);
        const hoje = new Date();
        const idade = hoje.getFullYear() - dataNasc.getFullYear();
        const mes = hoje.getMonth() - dataNasc.getMonth();
        const idadeCalculada = mes < 0 || (mes === 0 && hoje.getDate() < dataNasc.getDate()) ? idade - 1 : idade;
        document.getElementById('idade_filho_' + id).value = idadeCalculada;
    }
}

// Gerenciamento de Formações
let formacoesCount = 0;

function adicionarFormacao() {
    formacoesCount++;
    const container = document.getElementById('formacoes_container');
    const formacaoDiv = document.createElement('div');
    formacaoDiv.className = 'card card-flush mb-3';
    formacaoDiv.id = 'formacao_' + formacoesCount;
    formacaoDiv.innerHTML = `
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0">Formação ${formacoesCount}</h6>
                <button type="button" class="btn btn-sm btn-light-danger" onclick="removerFormacao(${formacoesCount})">
                    <i class="ki-duotone ki-trash fs-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                        <span class="path4"></span>
                        <span class="path5"></span>
                    </i>
                    Remover
                </button>
            </div>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">Tipo *</label>
                    <select name="formacoes[${formacoesCount}][tipo]" class="form-select" required>
                        <option value="curso">Curso</option>
                        <option value="graduacao">Graduação</option>
                        <option value="pos_graduacao">Pós-Graduação</option>
                        <option value="mestrado">Mestrado</option>
                        <option value="doutorado">Doutorado</option>
                        <option value="tecnico">Técnico</option>
                        <option value="certificacao">Certificação</option>
                        <option value="outro">Outro</option>
                    </select>
                </div>
                <div class="col-md-5 mb-3">
                    <label class="form-label">Nome do Curso/Formação *</label>
                    <input type="text" name="formacoes[${formacoesCount}][nome]" class="form-control" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Instituição</label>
                    <input type="text" name="formacoes[${formacoesCount}][instituicao]" class="form-control">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Data Início</label>
                    <input type="date" name="formacoes[${formacoesCount}][data_inicio]" class="form-control">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Data Conclusão</label>
                    <input type="date" name="formacoes[${formacoesCount}][data_conclusao]" class="form-control">
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label">Carga Horária</label>
                    <input type="number" name="formacoes[${formacoesCount}][carga_horaria]" class="form-control" min="0" placeholder="Horas">
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label">Status</label>
                    <select name="formacoes[${formacoesCount}][status]" class="form-select">
                        <option value="concluido" selected>Concluído</option>
                        <option value="em_andamento">Em Andamento</option>
                        <option value="trancado">Trancado</option>
                        <option value="cancelado">Cancelado</option>
                    </select>
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-flex align-items-end h-100">
                        <button type="button" class="btn btn-sm btn-light" onclick="removerFormacao(${formacoesCount})">
                            <i class="ki-duotone ki-trash fs-2 text-danger">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                                <span class="path4"></span>
                                <span class="path5"></span>
                            </i>
                        </button>
                    </div>
                </div>
                <div class="col-md-12 mb-3">
                    <label class="form-label">Observações</label>
                    <textarea name="formacoes[${formacoesCount}][observacoes]" class="form-control" rows="2"></textarea>
                </div>
            </div>
        </div>
    `;
    container.appendChild(formacaoDiv);
}

function removerFormacao(id) {
    const formacaoDiv = document.getElementById('formacao_' + id);
    if (formacaoDiv) {
        formacaoDiv.remove();
    }
}
</script>

<!--begin::Tutorial System-->
<link href="https://cdn.jsdelivr.net/npm/intro.js@7.2.0/introjs.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/intro.js@7.2.0/intro.min.js"></script>
<script src="../assets/js/tutorial-system.js"></script>
<script>
// Configuração do tutorial para esta página
window.pageTutorial = {
    pageId: 'colaborador_add',
    steps: [
        {
            title: 'Bem-vindo ao Cadastro de Colaborador',
            intro: 'Este tutorial vai te guiar pelas principais seções do formulário de cadastro de colaborador. Vamos começar!'
        },
        {
            element: '#empresa_id',
            title: 'Empresa',
            intro: 'Selecione a empresa do colaborador. Se você tiver acesso a apenas uma empresa, este campo estará oculto e será preenchido automaticamente.'
        },
        {
            element: '#setor_id',
            title: 'Setor e Cargo',
            intro: 'Selecione o setor e cargo do colaborador. Os setores e cargos são carregados automaticamente conforme a empresa selecionada.'
        },
        {
            element: '#nivel_hierarquico_id',
            title: 'Nível Hierárquico',
            intro: 'Opcionalmente, você pode definir o nível hierárquico do colaborador. Isso ajuda na organização da estrutura organizacional.'
        },
        {
            element: '#lider_id',
            title: 'Líder',
            intro: 'Selecione o líder direto deste colaborador. A lista de líderes é filtrada conforme o setor e nível hierárquico selecionados.'
        },
        {
            element: '#foto',
            title: 'Foto de Perfil',
            intro: 'Faça upload da foto do colaborador. Formatos aceitos: JPG, PNG, GIF, WEBP. Tamanho máximo: 5MB. Uma prévia será exibida após selecionar.'
        },
        {
            element: 'input[name="nome_completo"]',
            title: 'Dados Pessoais',
            intro: 'Preencha os dados pessoais do colaborador. Campos marcados com * são obrigatórios. O CPF será validado automaticamente.'
        },
        {
            element: '#filhos_container',
            title: 'Filhos',
            intro: 'Você pode adicionar informações sobre os filhos do colaborador clicando em "Adicionar Filho". A idade é calculada automaticamente se você informar a data de nascimento.'
        },
        {
            element: '#formacoes_container',
            title: 'Formações',
            intro: 'Registre cursos e formações do colaborador. Você pode adicionar múltiplas formações, incluindo graduação, pós-graduação, cursos técnicos, etc.'
        },
        {
            element: '#data_inicio',
            title: 'Data de Início',
            intro: 'Informe a data de início do colaborador na empresa. Este campo é obrigatório e é usado para cálculos de tempo de serviço.'
        },
        {
            element: '#tipo_contrato',
            title: 'Tipo de Contrato',
            intro: 'Selecione o tipo de contrato: CLT, PJ, Estágio ou Terceirizado. Se for PJ, o campo CNPJ aparecerá automaticamente.'
        },
        {
            element: '#salario',
            title: 'Salário',
            intro: 'Informe o salário do colaborador. Use o formato brasileiro (ex: 5.000,00). O sistema aplica máscara automaticamente.'
        },
        {
            element: '#campo_endereco',
            title: 'Endereço',
            intro: 'Preencha o endereço completo do colaborador. Se o colaborador for PJ, você pode usar o botão "Sincronizar" para buscar dados do CNPJ automaticamente.'
        },
        {
            element: 'input[name="pix"]',
            title: 'Dados Bancários',
            intro: 'Informe os dados bancários para pagamento: PIX, banco, agência, conta e tipo de conta. Esses dados são importantes para o processamento de pagamentos.'
        },
        {
            element: '#senha',
            title: 'Senha de Acesso',
            intro: 'Opcionalmente, você pode definir uma senha para o colaborador acessar o sistema. Se não informar, o colaborador precisará criar a senha no primeiro acesso.'
        },
        {
            element: '#enviar_email_boas_vindas',
            title: 'Email de Boas-vindas',
            intro: 'Marque esta opção para enviar automaticamente um email de boas-vindas ao colaborador com seus dados de acesso ao sistema.'
        },
        {
            element: 'form button[type="submit"]',
            title: 'Finalizar',
            intro: 'Após preencher todos os campos obrigatórios, clique em "Salvar" para cadastrar o colaborador. O sistema validará os dados antes de salvar.'
        }
    ]
};
</script>
<!--end::Tutorial System-->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

