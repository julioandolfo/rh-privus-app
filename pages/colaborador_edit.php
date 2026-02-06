<?php
/**
 * Editar Colaborador
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/upload_foto.php';

require_page_permission('colaborador_edit.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];
$id = $_GET['id'] ?? 0;

// Busca colaborador
$stmt = $pdo->prepare("SELECT * FROM colaboradores WHERE id = ?");
$stmt->execute([$id]);
$colaborador = $stmt->fetch();

if (!$colaborador) {
    redirect('colaboradores.php', 'Colaborador não encontrado!', 'error');
}

// Busca data de desligamento se o colaborador estiver desligado
$data_demissao_atual = null;
$tipo_demissao_atual = null;
$motivo_demissao_atual = null;
if ($colaborador['status'] === 'desligado') {
    $stmt_demissao = $pdo->prepare("
        SELECT data_demissao, tipo_demissao, motivo 
        FROM demissoes 
        WHERE colaborador_id = ? 
        ORDER BY data_demissao DESC, created_at DESC 
        LIMIT 1
    ");
    $stmt_demissao->execute([$id]);
    $demissao_existente = $stmt_demissao->fetch();
    if ($demissao_existente) {
        $data_demissao_atual = $demissao_existente['data_demissao'];
        $tipo_demissao_atual = $demissao_existente['tipo_demissao'];
        $motivo_demissao_atual = $demissao_existente['motivo'];
    }
}

// Verifica permissão
if (!can_access_colaborador($id)) {
    redirect('colaboradores.php', 'Você não tem permissão para editar este colaborador.', 'error');
}

// Processa POST ANTES de incluir o header (para evitar erro de headers already sent)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Para RH, valida se a empresa selecionada está nas empresas permitidas
    $empresa_id = $_POST['empresa_id'] ?? $colaborador['empresa_id'];
    if ($usuario['role'] === 'RH' && $empresa_id) {
        if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
            if (!in_array($empresa_id, $usuario['empresas_ids'])) {
                redirect('colaborador_edit.php?id=' . $id, 'Você não tem permissão para editar colaboradores desta empresa!', 'error');
            }
        } elseif (isset($usuario['empresa_id']) && $empresa_id != $usuario['empresa_id']) {
            redirect('colaborador_edit.php?id=' . $id, 'Você não tem permissão para editar colaboradores desta empresa!', 'error');
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
    $data_demissao = $_POST['data_demissao'] ?? null;
    $tipo_demissao = $_POST['tipo_demissao'] ?? null;
    $motivo_demissao = sanitize($_POST['motivo_demissao'] ?? '');
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
    $descricao_funcao = sanitize($_POST['descricao_funcao'] ?? '');
    
    // Validação: não pode ser líder de si mesmo
    if ($lider_id == $id) {
        redirect('colaborador_edit.php?id=' . $id, 'Um colaborador não pode ser líder de si mesmo!', 'error');
    }
    
    if (empty($nome_completo) || empty($empresa_id) || empty($setor_id) || empty($cargo_id) || empty($data_inicio)) {
        redirect('colaborador_edit.php?id=' . $id, 'Preencha os campos obrigatórios!', 'error');
    }
    
    // Validação: se status for desligado, data de demissão é obrigatória
    if ($status === 'desligado' && empty($data_demissao)) {
        redirect('colaborador_edit.php?id=' . $id, 'Para desligar um colaborador, é necessário informar a data de desligamento!', 'error');
    }
    
    try {
        // Processa upload de foto se houver
        $foto_path = $colaborador['foto']; // Mantém foto atual por padrão
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            // Deleta foto antiga se existir
            if (!empty($colaborador['foto'])) {
                delete_foto_perfil($colaborador['foto']);
            }
            
            $upload_result = upload_foto_perfil($_FILES['foto'], 'colaborador', $id);
            if ($upload_result['success']) {
                $foto_path = $upload_result['path'];
            } else {
                redirect('colaborador_edit.php?id=' . $id, 'Erro no upload da foto: ' . $upload_result['error'], 'error');
            }
        }
        
        // Processa senha se fornecida
        $senha_hash = $colaborador['senha_hash']; // Mantém senha atual por padrão
        if (!empty($senha)) {
            if (strlen($senha) < 6) {
                redirect('colaborador_edit.php?id=' . $id, 'A senha deve ter no mínimo 6 caracteres!', 'error');
            }
            $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
        }
        
        $stmt = $pdo->prepare("
            UPDATE colaboradores 
            SET empresa_id = ?, setor_id = ?, cargo_id = ?, nivel_hierarquico_id = ?, lider_id = ?, 
                nome_completo = ?, cpf = ?, cnpj = ?, rg = ?, data_nascimento = ?, estado_civil = ?, telefone = ?, email_pessoal = ?, 
                data_inicio = ?, status = ?, tipo_contrato = ?, salario = ?, pix = ?, banco = ?, agencia = ?, conta = ?, tipo_conta = ?, 
                cep = ?, logradouro = ?, numero = ?, complemento = ?, bairro = ?, cidade_endereco = ?, estado_endereco = ?, 
                descricao_funcao = ?, observacoes = ?, foto = ?, senha_hash = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $empresa_id, $setor_id, $cargo_id, $nivel_hierarquico_id, $lider_id, $nome_completo, $cpf, 
            !empty($cnpj) ? $cnpj : null, $rg, $data_nascimento ?: null, $estado_civil ?: null, $telefone, $email_pessoal, $data_inicio, 
            $status, $tipo_contrato, $salario, $pix, $banco, $agencia, $conta, $tipo_conta, 
            !empty($cep) ? $cep : null, $logradouro, $numero, $complemento, $bairro, $cidade_endereco, 
            !empty($estado_endereco) ? $estado_endereco : null, $descricao_funcao, $observacoes, $foto_path, $senha_hash, $id
        ]);
        
        // Processa filhos (remove todos e adiciona novamente)
        $stmt_delete_filhos = $pdo->prepare("DELETE FROM colaboradores_filhos WHERE colaborador_id = ?");
        $stmt_delete_filhos->execute([$id]);
        
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
                    $stmt_filho->execute([$id, $nome_filho, $data_nasc_filho, $idade_filho]);
                }
            }
        }
        
        // Processa formações (remove todas e adiciona novamente)
        $stmt_delete_formacoes = $pdo->prepare("DELETE FROM colaboradores_formacoes WHERE colaborador_id = ?");
        $stmt_delete_formacoes->execute([$id]);
        
        if (isset($_POST['formacoes']) && is_array($_POST['formacoes'])) {
            foreach ($_POST['formacoes'] as $formacao) {
                if (!empty($formacao['nome'])) {
                    $stmt_formacao = $pdo->prepare("
                        INSERT INTO colaboradores_formacoes 
                        (colaborador_id, tipo, nome, instituicao, data_inicio, data_conclusao, carga_horaria, status, observacoes)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt_formacao->execute([
                        $id,
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
        
        // Processa demissão se o status for desligado
        if ($status === 'desligado' && $data_demissao) {
            // Verifica se já existe um registro de demissão
            $stmt_check_demissao = $pdo->prepare("SELECT id FROM demissoes WHERE colaborador_id = ? ORDER BY data_demissao DESC LIMIT 1");
            $stmt_check_demissao->execute([$id]);
            $demissao_existente = $stmt_check_demissao->fetch();
            
            if ($demissao_existente) {
                // Atualiza registro existente
                $stmt_update_demissao = $pdo->prepare("
                    UPDATE demissoes 
                    SET data_demissao = ?, tipo_demissao = ?, motivo = ?
                    WHERE id = ?
                ");
                $stmt_update_demissao->execute([
                    $data_demissao,
                    $tipo_demissao,
                    $motivo_demissao,
                    $demissao_existente['id']
                ]);
            } else {
                // Cria novo registro de demissão
                $stmt_insert_demissao = $pdo->prepare("
                    INSERT INTO demissoes (colaborador_id, data_demissao, tipo_demissao, motivo, usuario_id)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt_insert_demissao->execute([
                    $id,
                    $data_demissao,
                    $tipo_demissao,
                    $motivo_demissao,
                    $usuario['id']
                ]);
            }
        } elseif ($status !== 'desligado') {
            // Se o status não for mais desligado, remove o registro de demissão
            $stmt_delete_demissao = $pdo->prepare("DELETE FROM demissoes WHERE colaborador_id = ?");
            $stmt_delete_demissao->execute([$id]);
        }
        
        redirect('colaborador_view.php?id=' . $id, 'Colaborador atualizado com sucesso!');
    } catch (PDOException $e) {
        redirect('colaborador_edit.php?id=' . $id, 'Erro ao atualizar: ' . $e->getMessage(), 'error');
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

// Busca setores da empresa do colaborador
$stmt_setores = $pdo->prepare("SELECT id, nome_setor FROM setores WHERE empresa_id = ? AND status = 'ativo' ORDER BY nome_setor");
$stmt_setores->execute([$colaborador['empresa_id']]);
$setores = $stmt_setores->fetchAll();

// Busca cargos da empresa do colaborador
$stmt_cargos = $pdo->prepare("SELECT id, nome_cargo FROM cargos WHERE empresa_id = ? AND status = 'ativo' ORDER BY nome_cargo");
$stmt_cargos->execute([$colaborador['empresa_id']]);
$cargos = $stmt_cargos->fetchAll();

// Agora inclui o header (após processar POST para evitar erro de headers already sent)
$page_title = 'Editar Colaborador';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <h2 class="mb-4"><i class="bi bi-person-gear"></i> Editar Colaborador</h2>
            
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
                                    <option value="<?= $emp['id'] ?>" <?= $colaborador['empresa_id'] == $emp['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($emp['nome_fantasia']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php else: ?>
                            <input type="hidden" name="empresa_id" value="<?= $colaborador['empresa_id'] ?>">
                            <?php endif; ?>
                            
                            <div class="col-md-<?= ($usuario['role'] === 'ADMIN' || ($usuario['role'] === 'RH' && count($empresas) > 1)) ? '3' : '6' ?> mb-3">
                                <label class="form-label">Setor *</label>
                                <select name="setor_id" id="setor_id" class="form-select" required>
                                    <option value="">Selecione...</option>
                                    <?php foreach ($setores as $setor): ?>
                                    <option value="<?= $setor['id'] ?>" <?= $colaborador['setor_id'] == $setor['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($setor['nome_setor']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-<?= ($usuario['role'] === 'ADMIN' || ($usuario['role'] === 'RH' && count($empresas) > 1)) ? '3' : '6' ?> mb-3">
                                <label class="form-label">Cargo *</label>
                                <select name="cargo_id" id="cargo_id" class="form-select" required>
                                    <option value="">Selecione...</option>
                                    <?php foreach ($cargos as $cargo): ?>
                                    <option value="<?= $cargo['id'] ?>" <?= $colaborador['cargo_id'] == $cargo['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cargo['nome_cargo']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Descrição da Função</label>
                                <textarea name="descricao_funcao" id="descricao_funcao" class="form-control" rows="3" placeholder="Descreva as atividades e responsabilidades do colaborador (usado em contratos)"><?= htmlspecialchars($colaborador['descricao_funcao'] ?? '') ?></textarea>
                                <small class="text-muted">Esta descrição será utilizada nos contratos gerados para este colaborador</small>
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
                                    <option value="<?= $nivel['id'] ?>" <?= ($colaborador['nivel_hierarquico_id'] ?? null) == $nivel['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($nivel['nome']) ?> (Nível <?= $nivel['nivel'] ?>)
                                    </option>
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
                                <?php if (!empty($colaborador['foto'])): ?>
                                <div class="mb-2">
                                    <img src="../<?= htmlspecialchars($colaborador['foto']) ?>" alt="Foto atual" style="max-width: 150px; max-height: 150px; border-radius: 8px; border: 1px solid #ddd;">
                                </div>
                                <?php endif; ?>
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
                                <input type="text" name="nome_completo" class="form-control" value="<?= htmlspecialchars($colaborador['nome_completo']) ?>" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">CPF</label>
                                <input type="text" name="cpf" class="form-control" value="<?= formatar_cpf($colaborador['cpf']) ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">RG</label>
                                <input type="text" name="rg" class="form-control" value="<?= htmlspecialchars($colaborador['rg']) ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Data de Nascimento</label>
                                <input type="date" name="data_nascimento" class="form-control" value="<?= $colaborador['data_nascimento'] ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Estado Civil</label>
                                <select name="estado_civil" class="form-select">
                                    <option value="">Selecione...</option>
                                    <option value="solteiro" <?= ($colaborador['estado_civil'] ?? '') === 'solteiro' ? 'selected' : '' ?>>Solteiro(a)</option>
                                    <option value="casado" <?= ($colaborador['estado_civil'] ?? '') === 'casado' ? 'selected' : '' ?>>Casado(a)</option>
                                    <option value="divorciado" <?= ($colaborador['estado_civil'] ?? '') === 'divorciado' ? 'selected' : '' ?>>Divorciado(a)</option>
                                    <option value="viuvo" <?= ($colaborador['estado_civil'] ?? '') === 'viuvo' ? 'selected' : '' ?>>Viúvo(a)</option>
                                    <option value="uniao_estavel" <?= ($colaborador['estado_civil'] ?? '') === 'uniao_estavel' ? 'selected' : '' ?>>União Estável</option>
                                    <option value="outro" <?= ($colaborador['estado_civil'] ?? '') === 'outro' ? 'selected' : '' ?>>Outro</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Telefone</label>
                                <input type="text" name="telefone" class="form-control" value="<?= htmlspecialchars($colaborador['telefone']) ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Email Pessoal</label>
                                <input type="email" name="email_pessoal" class="form-control" value="<?= htmlspecialchars($colaborador['email_pessoal']) ?>">
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
                                <input type="date" name="data_inicio" class="form-control" value="<?= $colaborador['data_inicio'] ?>" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Tipo de Contrato</label>
                                <select name="tipo_contrato" id="tipo_contrato" class="form-select">
                                    <option value="PJ" <?= $colaborador['tipo_contrato'] === 'PJ' ? 'selected' : '' ?>>PJ</option>
                                    <option value="CLT" <?= $colaborador['tipo_contrato'] === 'CLT' ? 'selected' : '' ?>>CLT</option>
                                    <option value="Estágio" <?= $colaborador['tipo_contrato'] === 'Estágio' ? 'selected' : '' ?>>Estágio</option>
                                    <option value="Terceirizado" <?= $colaborador['tipo_contrato'] === 'Terceirizado' ? 'selected' : '' ?>>Terceirizado</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Status</label>
                                <select name="status" id="status" class="form-select">
                                    <option value="ativo" <?= $colaborador['status'] === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                                    <option value="pausado" <?= $colaborador['status'] === 'pausado' ? 'selected' : '' ?>>Pausado</option>
                                    <option value="desligado" <?= $colaborador['status'] === 'desligado' ? 'selected' : '' ?>>Desligado</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Salário</label>
                                <input type="text" name="salario" id="salario" class="form-control" value="<?= $colaborador['salario'] ? number_format($colaborador['salario'], 2, ',', '.') : '' ?>" placeholder="0,00">
                            </div>
                        </div>
                        
                        <!-- Campos de Desligamento - aparecem apenas quando status = desligado -->
                        <div class="row" id="campos_desligamento" style="display: <?= $colaborador['status'] === 'desligado' ? 'flex' : 'none' ?>;">
                            <div class="col-md-12 mb-2">
                                <hr>
                                <h5 class="text-danger mb-3"><i class="bi bi-exclamation-triangle"></i> Dados de Desligamento</h5>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Data de Desligamento *</label>
                                <input type="date" name="data_demissao" id="data_demissao" class="form-control" value="<?= $data_demissao_atual ?? '' ?>" <?= $colaborador['status'] === 'desligado' ? 'required' : '' ?>>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Tipo de Demissão</label>
                                <select name="tipo_demissao" id="tipo_demissao" class="form-select">
                                    <option value="">Selecione...</option>
                                    <option value="pedido_demissao" <?= ($tipo_demissao_atual ?? '') === 'pedido_demissao' ? 'selected' : '' ?>>Pedido de Demissão</option>
                                    <option value="demissao_sem_justa_causa" <?= ($tipo_demissao_atual ?? '') === 'demissao_sem_justa_causa' ? 'selected' : '' ?>>Demissão sem Justa Causa</option>
                                    <option value="demissao_justa_causa" <?= ($tipo_demissao_atual ?? '') === 'demissao_justa_causa' ? 'selected' : '' ?>>Demissão por Justa Causa</option>
                                    <option value="acordo_mutuo" <?= ($tipo_demissao_atual ?? '') === 'acordo_mutuo' ? 'selected' : '' ?>>Acordo Mútuo</option>
                                    <option value="termino_contrato" <?= ($tipo_demissao_atual ?? '') === 'termino_contrato' ? 'selected' : '' ?>>Término de Contrato</option>
                                    <option value="outro" <?= ($tipo_demissao_atual ?? '') === 'outro' ? 'selected' : '' ?>>Outro</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Motivo</label>
                                <input type="text" name="motivo_demissao" id="motivo_demissao" class="form-control" value="<?= htmlspecialchars($motivo_demissao_atual ?? '') ?>" placeholder="Breve descrição do motivo">
                            </div>
                            <div class="col-md-12 mb-3">
                                <hr>
                            </div>
                        </div>
                        
                        <div class="row" id="campo_cnpj" style="display: <?= $colaborador['tipo_contrato'] === 'PJ' ? 'block' : 'none' ?>;">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">CNPJ</label>
                                <div class="input-group">
                                    <input type="text" name="cnpj" id="cnpj" class="form-control" value="<?= formatar_cnpj($colaborador['cnpj'] ?? '') ?>" placeholder="00.000.000/0000-00">
                                    <button type="button" class="btn btn-primary" id="btn_sincronizar_cnpj" onclick="sincronizarCNPJ()" style="display: <?= (!empty($colaborador['cnpj']) && strlen(preg_replace('/[^0-9]/', '', $colaborador['cnpj'])) === 14) ? 'inline-block' : 'none' ?>;">
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
                                <input type="text" name="cep" id="cep" class="form-control" value="<?= !empty($colaborador['cep']) ? preg_replace('/^(\d{5})(\d{3})$/', '$1-$2', $colaborador['cep']) : '' ?>" placeholder="00000-000">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Logradouro</label>
                                <input type="text" name="logradouro" id="logradouro" class="form-control" value="<?= htmlspecialchars($colaborador['logradouro'] ?? '') ?>" placeholder="Rua, Avenida, etc.">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Número</label>
                                <input type="text" name="numero" id="numero" class="form-control" value="<?= htmlspecialchars($colaborador['numero'] ?? '') ?>" placeholder="123">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Complemento</label>
                                <input type="text" name="complemento" id="complemento" class="form-control" value="<?= htmlspecialchars($colaborador['complemento'] ?? '') ?>" placeholder="Apto, Sala, etc.">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Bairro</label>
                                <input type="text" name="bairro" id="bairro" class="form-control" value="<?= htmlspecialchars($colaborador['bairro'] ?? '') ?>" placeholder="Nome do bairro">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Cidade</label>
                                <input type="text" name="cidade_endereco" id="cidade_endereco" class="form-control" value="<?= htmlspecialchars($colaborador['cidade_endereco'] ?? '') ?>" placeholder="Nome da cidade">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Estado (UF)</label>
                                <input type="text" name="estado_endereco" id="estado_endereco" class="form-control" maxlength="2" value="<?= htmlspecialchars($colaborador['estado_endereco'] ?? '') ?>" placeholder="SP">
                            </div>
                        </div>
                        
                        <hr>
                        <h5 class="mb-3">Dados Bancários</h5>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">PIX</label>
                                <input type="text" name="pix" class="form-control" value="<?= htmlspecialchars($colaborador['pix'] ?? '') ?>" placeholder="Chave PIX">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Banco</label>
                                <input type="text" name="banco" class="form-control" value="<?= htmlspecialchars($colaborador['banco'] ?? '') ?>" placeholder="Nome do banco">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Agência</label>
                                <input type="text" name="agencia" class="form-control" value="<?= htmlspecialchars($colaborador['agencia'] ?? '') ?>" placeholder="0000">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Conta</label>
                                <input type="text" name="conta" class="form-control" value="<?= htmlspecialchars($colaborador['conta'] ?? '') ?>" placeholder="00000-0">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Tipo de Conta</label>
                                <select name="tipo_conta" class="form-select">
                                    <option value="">Selecione...</option>
                                    <option value="corrente" <?= ($colaborador['tipo_conta'] ?? '') === 'corrente' ? 'selected' : '' ?>>Corrente</option>
                                    <option value="poupanca" <?= ($colaborador['tipo_conta'] ?? '') === 'poupanca' ? 'selected' : '' ?>>Poupança</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Senha de Acesso</label>
                                <input type="password" name="senha" class="form-control" minlength="6" placeholder="Deixe em branco para manter atual">
                                <small class="text-muted">Preencha apenas para alterar (mínimo 6 caracteres)</small>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="mb-3">
                            <label class="form-label">Observações</label>
                            <textarea name="observacoes" class="form-control" rows="3"><?= htmlspecialchars($colaborador['observacoes']) ?></textarea>
                        </div>
                        
                        <div class="d-flex justify-content-end gap-2">
                            <a href="colaborador_view.php?id=<?= $id ?>" class="btn btn-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary">Salvar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Carrega líderes quando empresa, setor ou nível hierárquico mudar
function carregarLideres() {
    const empresaId = document.getElementById('empresa_id')?.value || document.querySelector('input[name="empresa_id"][type="hidden"]')?.value || '<?= $colaborador['empresa_id'] ?>';
    const setorId = document.getElementById('setor_id')?.value || '<?= $colaborador['setor_id'] ?>';
    const nivelId = document.getElementById('nivel_hierarquico_id')?.value || '<?= $colaborador['nivel_hierarquico_id'] ?? '' ?>';
    const liderSelect = document.getElementById('lider_id');
    const colaboradorId = <?= $id ?>;
    
    if (!empresaId) {
        liderSelect.innerHTML = '<option value="">Selecione uma empresa primeiro</option>';
        return;
    }
    
    liderSelect.innerHTML = '<option value="">Carregando...</option>';
    
    let url = `../api/get_lideres.php?empresa_id=${empresaId}&excluir_id=${colaboradorId}`;
    if (setorId) url += `&setor_id=${setorId}`;
    if (nivelId) url += `&nivel_hierarquico_id=${nivelId}`;
    
    fetch(url)
        .then(r => r.json())
        .then(data => {
            liderSelect.innerHTML = '<option value="">Nenhum</option>';
            data.forEach(lider => {
                const nivelInfo = lider.nivel_nome ? ` - ${lider.nivel_nome}` : '';
                const selected = lider.id == <?= $colaborador['lider_id'] ?? 'null' ?> ? 'selected' : '';
                liderSelect.innerHTML += `<option value="${lider.id}" ${selected}>${lider.nome_completo}${nivelInfo}</option>`;
            });
        })
        .catch(() => {
            liderSelect.innerHTML = '<option value="">Erro ao carregar</option>';
        });
}

// Carrega líderes ao carregar a página
document.addEventListener('DOMContentLoaded', function() {
    carregarLideres();
    toggleCamposDesligamento(); // Exibe campos de desligamento se necessário
});

// Event listeners para carregar líderes
document.getElementById('empresa_id')?.addEventListener('change', carregarLideres);
document.getElementById('setor_id')?.addEventListener('change', carregarLideres);
document.getElementById('nivel_hierarquico_id')?.addEventListener('change', carregarLideres);

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

// Carrega setores quando empresa é selecionada (se admin)
document.addEventListener('DOMContentLoaded', function() {
    const empresaSelect = document.getElementById('empresa_id');
    if (empresaSelect) {
        empresaSelect.addEventListener('change', function() {
            const empresaId = this.value;
            const setorSelect = document.getElementById('setor_id');
            const cargoSelect = document.getElementById('cargo_id');
            
            if (!setorSelect || !cargoSelect) return;
            
            setorSelect.innerHTML = '<option value="">Carregando...</option>';
            cargoSelect.innerHTML = '<option value="">Carregando...</option>';
            
            if (empresaId) {
                // Carrega setores
                fetch(`../api/get_setores.php?empresa_id=${empresaId}`)
                    .then(r => {
                        if (!r.ok) throw new Error('Erro ao carregar setores');
                        return r.json();
                    })
                    .then(data => {
                        setorSelect.innerHTML = '<option value="">Selecione...</option>';
                        if (data.success && data.setores && Array.isArray(data.setores)) {
                            data.setores.forEach(setor => {
                                setorSelect.innerHTML += `<option value="${setor.id}">${setor.nome_setor}</option>`;
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Erro ao carregar setores:', error);
                        setorSelect.innerHTML = '<option value="">Erro ao carregar</option>';
                    });
                
                // Carrega cargos
                fetch(`../api/get_cargos.php?empresa_id=${empresaId}`)
                    .then(r => {
                        if (!r.ok) throw new Error('Erro ao carregar cargos');
                        return r.json();
                    })
                    .then(data => {
                        cargoSelect.innerHTML = '<option value="">Selecione...</option>';
                        if (data.success && data.cargos && Array.isArray(data.cargos)) {
                            data.cargos.forEach(cargo => {
                                cargoSelect.innerHTML += `<option value="${cargo.id}">${cargo.nome_cargo}</option>`;
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Erro ao carregar cargos:', error);
                        cargoSelect.innerHTML = '<option value="">Erro ao carregar</option>';
                    });
                
                // Recarrega líderes também
                carregarLideres();
            } else {
                setorSelect.innerHTML = '<option value="">Selecione uma empresa primeiro</option>';
                cargoSelect.innerHTML = '<option value="">Selecione uma empresa primeiro</option>';
            }
        });
    }
});

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

// Função para mostrar/ocultar campos de desligamento
function toggleCamposDesligamento() {
    const status = document.getElementById('status')?.value;
    const camposDesligamento = document.getElementById('campos_desligamento');
    const dataDemissao = document.getElementById('data_demissao');
    
    if (camposDesligamento) {
        if (status === 'desligado') {
            camposDesligamento.style.display = 'flex';
            if (dataDemissao) {
                dataDemissao.setAttribute('required', 'required');
                // Se não tiver data, preenche com data atual
                if (!dataDemissao.value) {
                    dataDemissao.value = new Date().toISOString().split('T')[0];
                }
            }
        } else {
            camposDesligamento.style.display = 'none';
            if (dataDemissao) {
                dataDemissao.removeAttribute('required');
            }
        }
    }
}

// Event listener para mudança de status
document.getElementById('status')?.addEventListener('change', toggleCamposDesligamento);
</script>

<script>
// Gerenciamento de Filhos
let filhosCount = 0;

function adicionarFilho(nome = '', dataNascimento = '', idade = '') {
    filhosCount++;
    const container = document.getElementById('filhos_container');
    const filhoDiv = document.createElement('div');
    filhoDiv.className = 'card card-flush mb-3';
    filhoDiv.id = 'filho_' + filhosCount;
    
    // Escapa valores para HTML
    const escapeHtml = (text) => {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    };
    
    const nomeEscaped = escapeHtml(nome || '');
    const dataNascimentoEscaped = escapeHtml(dataNascimento || '');
    const idadeEscaped = escapeHtml(idade || '');
    
    filhoDiv.innerHTML = 
        '<div class="card-body">' +
            '<div class="d-flex justify-content-between align-items-center mb-3">' +
                '<h6 class="mb-0">Filho ' + filhosCount + '</h6>' +
                '<button type="button" class="btn btn-sm btn-light-danger" onclick="removerFilho(' + filhosCount + ')">' +
                    '<i class="ki-duotone ki-trash fs-2">' +
                        '<span class="path1"></span>' +
                        '<span class="path2"></span>' +
                        '<span class="path3"></span>' +
                        '<span class="path4"></span>' +
                        '<span class="path5"></span>' +
                    '</i>' +
                    'Remover' +
                '</button>' +
            '</div>' +
            '<div class="row">' +
                '<div class="col-md-4 mb-3">' +
                    '<label class="form-label">Nome *</label>' +
                    '<input type="text" name="filhos[' + filhosCount + '][nome]" class="form-control" value="' + nomeEscaped + '" required>' +
                '</div>' +
                '<div class="col-md-4 mb-3">' +
                    '<label class="form-label">Data de Nascimento</label>' +
                    '<input type="date" name="filhos[' + filhosCount + '][data_nascimento]" class="form-control" value="' + dataNascimentoEscaped + '" onchange="calcularIdadeFilho(' + filhosCount + ', this.value)">' +
                '</div>' +
                '<div class="col-md-4 mb-3">' +
                    '<label class="form-label">Idade</label>' +
                    '<input type="number" name="filhos[' + filhosCount + '][idade]" id="idade_filho_' + filhosCount + '" class="form-control" min="0" max="120" value="' + idadeEscaped + '">' +
                '</div>' +
            '</div>' +
        '</div>';
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

function adicionarFormacao(tipo = 'curso', nome = '', instituicao = '', dataInicio = '', dataConclusao = '', cargaHoraria = '', status = 'concluido', observacoes = '') {
    formacoesCount++;
    const container = document.getElementById('formacoes_container');
    const formacaoDiv = document.createElement('div');
    formacaoDiv.className = 'card card-flush mb-3';
    formacaoDiv.id = 'formacao_' + formacoesCount;
    
    // Escapa valores para HTML
    const escapeHtml = (text) => {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    };
    
    const tipoEscaped = escapeHtml(tipo || 'curso');
    const nomeEscaped = escapeHtml(nome || '');
    const instituicaoEscaped = escapeHtml(instituicao || '');
    const dataInicioEscaped = escapeHtml(dataInicio || '');
    const dataConclusaoEscaped = escapeHtml(dataConclusao || '');
    const cargaHorariaEscaped = escapeHtml(cargaHoraria || '');
    const statusEscaped = escapeHtml(status || 'concluido');
    const observacoesEscaped = escapeHtml(observacoes || '');
    
    formacaoDiv.innerHTML = 
        '<div class="card-body">' +
            '<div class="d-flex justify-content-between align-items-center mb-3">' +
                '<h6 class="mb-0">Formação ' + formacoesCount + '</h6>' +
                '<button type="button" class="btn btn-sm btn-light-danger" onclick="removerFormacao(' + formacoesCount + ')">' +
                    '<i class="ki-duotone ki-trash fs-2">' +
                        '<span class="path1"></span>' +
                        '<span class="path2"></span>' +
                        '<span class="path3"></span>' +
                        '<span class="path4"></span>' +
                        '<span class="path5"></span>' +
                    '</i>' +
                    'Remover' +
                '</button>' +
            '</div>' +
            '<div class="row">' +
                '<div class="col-md-3 mb-3">' +
                    '<label class="form-label">Tipo *</label>' +
                    '<select name="formacoes[' + formacoesCount + '][tipo]" class="form-select" required>' +
                        '<option value="curso"' + (tipoEscaped === 'curso' ? ' selected' : '') + '>Curso</option>' +
                        '<option value="graduacao"' + (tipoEscaped === 'graduacao' ? ' selected' : '') + '>Graduação</option>' +
                        '<option value="pos_graduacao"' + (tipoEscaped === 'pos_graduacao' ? ' selected' : '') + '>Pós-Graduação</option>' +
                        '<option value="mestrado"' + (tipoEscaped === 'mestrado' ? ' selected' : '') + '>Mestrado</option>' +
                        '<option value="doutorado"' + (tipoEscaped === 'doutorado' ? ' selected' : '') + '>Doutorado</option>' +
                        '<option value="tecnico"' + (tipoEscaped === 'tecnico' ? ' selected' : '') + '>Técnico</option>' +
                        '<option value="certificacao"' + (tipoEscaped === 'certificacao' ? ' selected' : '') + '>Certificação</option>' +
                        '<option value="outro"' + (tipoEscaped === 'outro' ? ' selected' : '') + '>Outro</option>' +
                    '</select>' +
                '</div>' +
                '<div class="col-md-5 mb-3">' +
                    '<label class="form-label">Nome do Curso/Formação *</label>' +
                    '<input type="text" name="formacoes[' + formacoesCount + '][nome]" class="form-control" value="' + nomeEscaped + '" required>' +
                '</div>' +
                '<div class="col-md-4 mb-3">' +
                    '<label class="form-label">Instituição</label>' +
                    '<input type="text" name="formacoes[' + formacoesCount + '][instituicao]" class="form-control" value="' + instituicaoEscaped + '">' +
                '</div>' +
                '<div class="col-md-3 mb-3">' +
                    '<label class="form-label">Data Início</label>' +
                    '<input type="date" name="formacoes[' + formacoesCount + '][data_inicio]" class="form-control" value="' + dataInicioEscaped + '">' +
                '</div>' +
                '<div class="col-md-3 mb-3">' +
                    '<label class="form-label">Data Conclusão</label>' +
                    '<input type="date" name="formacoes[' + formacoesCount + '][data_conclusao]" class="form-control" value="' + dataConclusaoEscaped + '">' +
                '</div>' +
                '<div class="col-md-2 mb-3">' +
                    '<label class="form-label">Carga Horária</label>' +
                    '<input type="number" name="formacoes[' + formacoesCount + '][carga_horaria]" class="form-control" min="0" placeholder="Horas" value="' + cargaHorariaEscaped + '">' +
                '</div>' +
                '<div class="col-md-2 mb-3">' +
                    '<label class="form-label">Status</label>' +
                    '<select name="formacoes[' + formacoesCount + '][status]" class="form-select">' +
                        '<option value="concluido"' + (statusEscaped === 'concluido' ? ' selected' : '') + '>Concluído</option>' +
                        '<option value="em_andamento"' + (statusEscaped === 'em_andamento' ? ' selected' : '') + '>Em Andamento</option>' +
                        '<option value="trancado"' + (statusEscaped === 'trancado' ? ' selected' : '') + '>Trancado</option>' +
                        '<option value="cancelado"' + (statusEscaped === 'cancelado' ? ' selected' : '') + '>Cancelado</option>' +
                    '</select>' +
                '</div>' +
                '<div class="col-md-2 mb-3">' +
                    '<label class="form-label">&nbsp;</label>' +
                    '<div class="d-flex align-items-end h-100">' +
                        '<button type="button" class="btn btn-sm btn-light" onclick="removerFormacao(' + formacoesCount + ')">' +
                            '<i class="ki-duotone ki-trash fs-2 text-danger">' +
                                '<span class="path1"></span>' +
                                '<span class="path2"></span>' +
                                '<span class="path3"></span>' +
                                '<span class="path4"></span>' +
                                '<span class="path5"></span>' +
                            '</i>' +
                        '</button>' +
                    '</div>' +
                '</div>' +
                '<div class="col-md-12 mb-3">' +
                    '<label class="form-label">Observações</label>' +
                    '<textarea name="formacoes[' + formacoesCount + '][observacoes]" class="form-control" rows="2">' + observacoesEscaped + '</textarea>' +
                '</div>' +
            '</div>' +
        '</div>';
    container.appendChild(formacaoDiv);
}

function removerFormacao(id) {
    const formacaoDiv = document.getElementById('formacao_' + id);
    if (formacaoDiv) {
        formacaoDiv.remove();
    }
}

// Carrega filhos e formações existentes ao abrir a página
document.addEventListener('DOMContentLoaded', function() {
    <?php
    // Busca filhos existentes
    $stmt_filhos = $pdo->prepare("SELECT * FROM colaboradores_filhos WHERE colaborador_id = ? ORDER BY id");
    $stmt_filhos->execute([$id]);
    $filhos_existentes = $stmt_filhos->fetchAll();
    
    foreach ($filhos_existentes as $filho):
    ?>
    adicionarFilho(
        <?= json_encode($filho['nome']) ?>,
        <?= json_encode($filho['data_nascimento']) ?>,
        <?= json_encode($filho['idade']) ?>
    );
    <?php endforeach; ?>
    
    <?php
    // Busca formações existentes
    $stmt_formacoes = $pdo->prepare("SELECT * FROM colaboradores_formacoes WHERE colaborador_id = ? ORDER BY id");
    $stmt_formacoes->execute([$id]);
    $formacoes_existentes = $stmt_formacoes->fetchAll();
    
    foreach ($formacoes_existentes as $formacao):
    ?>
    adicionarFormacao(
        <?= json_encode($formacao['tipo']) ?>,
        <?= json_encode($formacao['nome']) ?>,
        <?= json_encode($formacao['instituicao']) ?>,
        <?= json_encode($formacao['data_inicio']) ?>,
        <?= json_encode($formacao['data_conclusao']) ?>,
        <?= json_encode($formacao['carga_horaria']) ?>,
        <?= json_encode($formacao['status']) ?>,
        <?= json_encode($formacao['observacoes']) ?>
    );
    <?php endforeach; ?>
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

