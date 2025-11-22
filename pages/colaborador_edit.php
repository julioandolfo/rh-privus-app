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

// Verifica permissão
if (!can_access_colaborador($id)) {
    redirect('colaboradores.php', 'Você não tem permissão para editar este colaborador.', 'error');
}

// Processa POST ANTES de incluir o header (para evitar erro de headers already sent)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $empresa_id = $_POST['empresa_id'] ?? ($usuario['role'] === 'ADMIN' ? null : $usuario['empresa_id']);
    $setor_id = $_POST['setor_id'] ?? null;
    $cargo_id = $_POST['cargo_id'] ?? null;
    $nome_completo = sanitize($_POST['nome_completo'] ?? '');
    $cpf = preg_replace('/[^0-9]/', '', $_POST['cpf'] ?? '');
    $rg = sanitize($_POST['rg'] ?? '');
    $data_nascimento = $_POST['data_nascimento'] ?? null;
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
    
    // Validação: não pode ser líder de si mesmo
    if ($lider_id == $id) {
        redirect('colaborador_edit.php?id=' . $id, 'Um colaborador não pode ser líder de si mesmo!', 'error');
    }
    
    if (empty($nome_completo) || empty($empresa_id) || empty($setor_id) || empty($cargo_id) || empty($data_inicio)) {
        redirect('colaborador_edit.php?id=' . $id, 'Preencha os campos obrigatórios!', 'error');
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
                nome_completo = ?, cpf = ?, cnpj = ?, rg = ?, data_nascimento = ?, telefone = ?, email_pessoal = ?, 
                data_inicio = ?, status = ?, tipo_contrato = ?, salario = ?, pix = ?, banco = ?, agencia = ?, conta = ?, tipo_conta = ?, 
                cep = ?, logradouro = ?, numero = ?, complemento = ?, bairro = ?, cidade_endereco = ?, estado_endereco = ?, 
                observacoes = ?, foto = ?, senha_hash = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $empresa_id, $setor_id, $cargo_id, $nivel_hierarquico_id, $lider_id, $nome_completo, $cpf, 
            !empty($cnpj) ? $cnpj : null, $rg, $data_nascimento ?: null, $telefone, $email_pessoal, $data_inicio, 
            $status, $tipo_contrato, $salario, $pix, $banco, $agencia, $conta, $tipo_conta, 
            !empty($cep) ? $cep : null, $logradouro, $numero, $complemento, $bairro, $cidade_endereco, 
            !empty($estado_endereco) ? $estado_endereco : null, $observacoes, $foto_path, $senha_hash, $id
        ]);
        
        redirect('colaborador_view.php?id=' . $id, 'Colaborador atualizado com sucesso!');
    } catch (PDOException $e) {
        redirect('colaborador_edit.php?id=' . $id, 'Erro ao atualizar: ' . $e->getMessage(), 'error');
    }
}

// Busca empresas
if ($usuario['role'] === 'ADMIN') {
    $stmt_empresas = $pdo->query("SELECT id, nome_fantasia FROM empresas WHERE status = 'ativo' ORDER BY nome_fantasia");
} else {
    $stmt_empresas = $pdo->prepare("SELECT id, nome_fantasia FROM empresas WHERE id = ? AND status = 'ativo'");
    $stmt_empresas->execute([$usuario['empresa_id']]);
}
$empresas = $stmt_empresas->fetchAll();

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
                            <?php if ($usuario['role'] === 'ADMIN'): ?>
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
                            
                            <div class="col-md-<?= $usuario['role'] === 'ADMIN' ? '3' : '6' ?> mb-3">
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
                            
                            <div class="col-md-<?= $usuario['role'] === 'ADMIN' ? '3' : '6' ?> mb-3">
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
                                <label class="form-label">Telefone</label>
                                <input type="text" name="telefone" class="form-control" value="<?= htmlspecialchars($colaborador['telefone']) ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Email Pessoal</label>
                                <input type="email" name="email_pessoal" class="form-control" value="<?= htmlspecialchars($colaborador['email_pessoal']) ?>">
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
                                <select name="status" class="form-select">
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
    const empresaId = document.getElementById('empresa_id')?.value || '<?= $colaborador['empresa_id'] ?>';
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
                data.forEach(setor => {
                    setorSelect.innerHTML += `<option value="${setor.id}">${setor.nome_setor}</option>`;
                });
            });
        
        fetch(`../api/get_cargos.php?empresa_id=${empresaId}`)
            .then(r => r.json())
            .then(data => {
                cargoSelect.innerHTML = '<option value="">Selecione...</option>';
                data.forEach(cargo => {
                    cargoSelect.innerHTML += `<option value="${cargo.id}">${cargo.nome_cargo}</option>`;
                });
            });
        
        // Recarrega líderes também
        carregarLideres();
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
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

