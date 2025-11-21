<?php
/**
 * Minha Conta - Metronic Theme
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/upload_foto.php';

require_login();

$pdo = getDB();
$usuario = $_SESSION['usuario'];
$usuario_id = $usuario['id'];

// Busca dados completos do usuário
$stmt = $pdo->prepare("
    SELECT u.*, 
           e.nome_fantasia as empresa_nome,
           s.nome_setor,
           c.nome_completo as colaborador_nome,
           c.foto as colaborador_foto
    FROM usuarios u
    LEFT JOIN empresas e ON u.empresa_id = e.id
    LEFT JOIN setores s ON u.setor_id = s.id
    LEFT JOIN colaboradores c ON u.colaborador_id = c.id
    WHERE u.id = ?
");
$stmt->execute([$usuario_id]);
$dados_usuario = $stmt->fetch();

if (!$dados_usuario) {
    redirect('dashboard.php', 'Usuário não encontrado!', 'error');
}

// Processa POST ANTES de incluir o header
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'atualizar_dados') {
        $nome = sanitize($_POST['nome'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $telefone = sanitize($_POST['telefone'] ?? '');
        
        if (empty($nome) || empty($email)) {
            redirect('minha_conta.php', 'Preencha os campos obrigatórios!', 'error');
        }
        
        // Verifica se email já existe (exceto o próprio usuário)
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
        $stmt->execute([$email, $usuario_id]);
        if ($stmt->fetch()) {
            redirect('minha_conta.php', 'Este email já está em uso!', 'error');
        }
        
        try {
            $stmt = $pdo->prepare("UPDATE usuarios SET nome = ?, email = ? WHERE id = ?");
            $stmt->execute([$nome, $email, $usuario_id]);
            
            // Atualiza telefone do colaborador se houver
            if ($dados_usuario['colaborador_id']) {
                $stmt = $pdo->prepare("UPDATE colaboradores SET telefone = ? WHERE id = ?");
                $stmt->execute([$telefone, $dados_usuario['colaborador_id']]);
            }
            
            // Atualiza sessão
            $_SESSION['usuario']['nome'] = $nome;
            $_SESSION['usuario']['email'] = $email;
            
            redirect('minha_conta.php', 'Dados atualizados com sucesso!');
        } catch (PDOException $e) {
            redirect('minha_conta.php', 'Erro ao atualizar: ' . $e->getMessage(), 'error');
        }
    } elseif ($action === 'alterar_senha') {
        $senha_atual = $_POST['senha_atual'] ?? '';
        $nova_senha = $_POST['nova_senha'] ?? '';
        $confirmar_senha = $_POST['confirmar_senha'] ?? '';
        
        if (empty($senha_atual) || empty($nova_senha) || empty($confirmar_senha)) {
            redirect('minha_conta.php', 'Preencha todos os campos de senha!', 'error');
        }
        
        if ($nova_senha !== $confirmar_senha) {
            redirect('minha_conta.php', 'As senhas não coincidem!', 'error');
        }
        
        if (strlen($nova_senha) < 6) {
            redirect('minha_conta.php', 'A senha deve ter no mínimo 6 caracteres!', 'error');
        }
        
        // Verifica senha atual
        $stmt = $pdo->prepare("SELECT senha_hash FROM usuarios WHERE id = ?");
        $stmt->execute([$usuario_id]);
        $user_data = $stmt->fetch();
        
        if (!password_verify($senha_atual, $user_data['senha_hash'])) {
            redirect('minha_conta.php', 'Senha atual incorreta!', 'error');
        }
        
        try {
            $nova_senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE usuarios SET senha_hash = ? WHERE id = ?");
            $stmt->execute([$nova_senha_hash, $usuario_id]);
            
            redirect('minha_conta.php', 'Senha alterada com sucesso!');
        } catch (PDOException $e) {
            redirect('minha_conta.php', 'Erro ao alterar senha: ' . $e->getMessage(), 'error');
        }
    } elseif ($action === 'atualizar_foto') {
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            // Deleta foto antiga se existir
            if (!empty($dados_usuario['foto'])) {
                delete_foto_perfil($dados_usuario['foto']);
            }
            
            $upload_result = upload_foto_perfil($_FILES['foto'], 'usuario', $usuario_id);
            if ($upload_result['success']) {
                $stmt = $pdo->prepare("UPDATE usuarios SET foto = ? WHERE id = ?");
                $stmt->execute([$upload_result['path'], $usuario_id]);
                
                $_SESSION['usuario']['foto'] = $upload_result['path'];
                
                redirect('minha_conta.php', 'Foto atualizada com sucesso!');
            } else {
                redirect('minha_conta.php', 'Erro no upload da foto: ' . $upload_result['error'], 'error');
            }
        } else {
            redirect('minha_conta.php', 'Nenhuma foto foi enviada!', 'error');
        }
    }
}

// Busca dados atualizados
$stmt = $pdo->prepare("
    SELECT u.*, 
           e.nome_fantasia as empresa_nome,
           s.nome_setor,
           c.nome_completo as colaborador_nome,
           c.foto as colaborador_foto,
           c.telefone as colaborador_telefone
    FROM usuarios u
    LEFT JOIN empresas e ON u.empresa_id = e.id
    LEFT JOIN setores s ON u.setor_id = s.id
    LEFT JOIN colaboradores c ON u.colaborador_id = c.id
    WHERE u.id = ?
");
$stmt->execute([$usuario_id]);
$dados_usuario = $stmt->fetch();

$page_title = 'Minha Conta';
require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Minha Conta</h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Minha Conta</li>
            </ul>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <!--begin::Row-->
        <div class="row g-5 g-xl-8">
            <!--begin::Col-->
            <div class="col-xl-4">
                <!--begin::Card-->
                <div class="card">
                    <div class="card-body">
                        <!--begin::Foto de Perfil-->
                        <div class="d-flex flex-column align-items-center text-center mb-7">
                            <div class="symbol symbol-100px symbol-circle mb-3">
                                <?php 
                                $foto_perfil = !empty($dados_usuario['foto']) ? '../' . $dados_usuario['foto'] : 
                                             (!empty($dados_usuario['colaborador_foto']) ? '../' . $dados_usuario['colaborador_foto'] : 
                                             get_foto_perfil('', $dados_usuario['nome']));
                                ?>
                                <img src="<?= htmlspecialchars($foto_perfil) ?>" alt="Foto de Perfil" />
                            </div>
                            <h3 class="text-gray-900 fw-bold mb-1"><?= htmlspecialchars($dados_usuario['nome']) ?></h3>
                            <span class="badge badge-light-primary fs-6 mb-3"><?= htmlspecialchars($dados_usuario['role']) ?></span>
                            <div class="text-muted fs-7">
                                <?php if ($dados_usuario['empresa_nome']): ?>
                                <div><?= htmlspecialchars($dados_usuario['empresa_nome']) ?></div>
                                <?php endif; ?>
                                <?php if ($dados_usuario['nome_setor']): ?>
                                <div><?= htmlspecialchars($dados_usuario['nome_setor']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <!--end::Foto de Perfil-->
                        
                        <!--begin::Informações-->
                        <div class="separator separator-dashed my-5"></div>
                        <div class="d-flex flex-column">
                            <div class="d-flex align-items-center mb-5">
                                <i class="ki-duotone ki-sms fs-2 text-gray-400 me-3">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <div class="flex-grow-1">
                                    <span class="text-gray-600 fw-semibold fs-6 d-block">Email</span>
                                    <span class="text-gray-800 fw-bold fs-6"><?= htmlspecialchars($dados_usuario['email']) ?></span>
                                </div>
                            </div>
                            
                            <?php if ($dados_usuario['colaborador_telefone']): ?>
                            <div class="d-flex align-items-center mb-5">
                                <i class="ki-duotone ki-phone fs-2 text-gray-400 me-3">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <div class="flex-grow-1">
                                    <span class="text-gray-600 fw-semibold fs-6 d-block">Telefone</span>
                                    <span class="text-gray-800 fw-bold fs-6"><?= htmlspecialchars($dados_usuario['colaborador_telefone']) ?></span>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($dados_usuario['ultimo_login']): ?>
                            <div class="d-flex align-items-center">
                                <i class="ki-duotone ki-calendar fs-2 text-gray-400 me-3">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <div class="flex-grow-1">
                                    <span class="text-gray-600 fw-semibold fs-6 d-block">Último Acesso</span>
                                    <span class="text-gray-800 fw-bold fs-6"><?= date('d/m/Y H:i', strtotime($dados_usuario['ultimo_login'])) ?></span>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <!--end::Informações-->
                    </div>
                </div>
                <!--end::Card-->
            </div>
            <!--end::Col-->
            
            <!--begin::Col-->
            <div class="col-xl-8">
                <!--begin::Card-->
                <div class="card">
                    <div class="card-header border-0 pt-5">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold fs-3 mb-1">Editar Perfil</span>
                            <span class="text-muted fw-semibold fs-7">Atualize suas informações pessoais</span>
                        </h3>
                    </div>
                    <div class="card-body pt-5">
                        <!--begin::Tabs-->
                        <ul class="nav nav-stretch nav-line-tabs nav-line-tabs-2x border-transparent fs-5 fw-bold">
                            <li class="nav-item mt-2">
                                <a class="nav-link text-active-primary ms-0 me-10 py-5 active" data-bs-toggle="tab" href="#kt_tab_pane_1">
                                    Dados Pessoais
                                </a>
                            </li>
                            <li class="nav-item mt-2">
                                <a class="nav-link text-active-primary me-10 py-5" data-bs-toggle="tab" href="#kt_tab_pane_2">
                                    Alterar Senha
                                </a>
                            </li>
                            <li class="nav-item mt-2">
                                <a class="nav-link text-active-primary py-5" data-bs-toggle="tab" href="#kt_tab_pane_3">
                                    Foto de Perfil
                                </a>
                            </li>
                        </ul>
                        <!--end::Tabs-->
                        
                        <!--begin::Tab Content-->
                        <div class="tab-content">
                            <!--begin::Tab Pane 1-->
                            <div class="tab-pane fade show active" id="kt_tab_pane_1" role="tabpanel">
                                <form method="POST" class="form mt-7">
                                    <input type="hidden" name="action" value="atualizar_dados">
                                    
                                    <div class="row mb-7">
                                        <div class="col-md-6">
                                            <label class="required fw-semibold fs-6 mb-2">Nome</label>
                                            <input type="text" name="nome" class="form-control form-control-solid" value="<?= htmlspecialchars($dados_usuario['nome']) ?>" required />
                                        </div>
                                        <div class="col-md-6">
                                            <label class="required fw-semibold fs-6 mb-2">Email</label>
                                            <input type="email" name="email" class="form-control form-control-solid" value="<?= htmlspecialchars($dados_usuario['email']) ?>" required />
                                        </div>
                                    </div>
                                    
                                    <?php if ($dados_usuario['colaborador_id']): ?>
                                    <div class="row mb-7">
                                        <div class="col-md-6">
                                            <label class="fw-semibold fs-6 mb-2">Telefone</label>
                                            <input type="text" name="telefone" class="form-control form-control-solid" value="<?= htmlspecialchars($dados_usuario['colaborador_telefone'] ?? '') ?>" />
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="text-end pt-7">
                                        <button type="submit" class="btn btn-primary">
                                            <span class="indicator-label">Salvar Alterações</span>
                                        </button>
                                    </div>
                                </form>
                            </div>
                            <!--end::Tab Pane 1-->
                            
                            <!--begin::Tab Pane 2-->
                            <div class="tab-pane fade" id="kt_tab_pane_2" role="tabpanel">
                                <form method="POST" class="form mt-7">
                                    <input type="hidden" name="action" value="alterar_senha">
                                    
                                    <div class="row mb-7">
                                        <div class="col-md-12">
                                            <label class="required fw-semibold fs-6 mb-2">Senha Atual</label>
                                            <input type="password" name="senha_atual" class="form-control form-control-solid" required />
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-7">
                                        <div class="col-md-6">
                                            <label class="required fw-semibold fs-6 mb-2">Nova Senha</label>
                                            <input type="password" name="nova_senha" class="form-control form-control-solid" minlength="6" required />
                                            <small class="text-muted">Mínimo 6 caracteres</small>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="required fw-semibold fs-6 mb-2">Confirmar Nova Senha</label>
                                            <input type="password" name="confirmar_senha" class="form-control form-control-solid" minlength="6" required />
                                        </div>
                                    </div>
                                    
                                    <div class="text-end pt-7">
                                        <button type="submit" class="btn btn-primary">
                                            <span class="indicator-label">Alterar Senha</span>
                                        </button>
                                    </div>
                                </form>
                            </div>
                            <!--end::Tab Pane 2-->
                            
                            <!--begin::Tab Pane 3-->
                            <div class="tab-pane fade" id="kt_tab_pane_3" role="tabpanel">
                                <form method="POST" enctype="multipart/form-data" class="form mt-7">
                                    <input type="hidden" name="action" value="atualizar_foto">
                                    
                                    <div class="row mb-7">
                                        <div class="col-md-12">
                                            <label class="fw-semibold fs-6 mb-2">Foto de Perfil</label>
                                            <?php 
                                            $foto_atual = !empty($dados_usuario['foto']) ? '../' . $dados_usuario['foto'] : 
                                                         (!empty($dados_usuario['colaborador_foto']) ? '../' . $dados_usuario['colaborador_foto'] : '');
                                            if ($foto_atual):
                                            ?>
                                            <div class="mb-3">
                                                <img src="<?= htmlspecialchars($foto_atual) ?>" alt="Foto atual" style="max-width: 200px; max-height: 200px; border-radius: 8px; border: 1px solid #ddd;">
                                            </div>
                                            <?php endif; ?>
                                            <input type="file" name="foto" id="foto_perfil" class="form-control form-control-solid" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" />
                                            <small class="text-muted">Formatos aceitos: JPG, PNG, GIF, WEBP. Tamanho máximo: 5MB</small>
                                            <div id="foto_preview" class="mt-3" style="display: none;">
                                                <img id="foto_preview_img" src="" alt="Preview" style="max-width: 200px; max-height: 200px; border-radius: 8px;">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="text-end pt-7">
                                        <button type="submit" class="btn btn-primary">
                                            <span class="indicator-label">Atualizar Foto</span>
                                        </button>
                                    </div>
                                </form>
                            </div>
                            <!--end::Tab Pane 3-->
                        </div>
                        <!--end::Tab Content-->
                    </div>
                </div>
                <!--end::Card-->
            </div>
            <!--end::Col-->
        </div>
        <!--end::Row-->
        
    </div>
</div>
<!--end::Post-->

<script>
// Preview de foto
document.getElementById('foto_perfil')?.addEventListener('change', function(e) {
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

