<?php
/**
 * CRUD de Usuários do Sistema - Metronic Theme
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/upload_foto.php';

require_page_permission('usuarios.php');

$pdo = getDB();

// Verifica e cria a tabela usuarios_empresas se não existir
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'usuarios_empresas'");
    if ($stmt->rowCount() == 0) {
        // Cria tabela de relacionamento muitos-para-muitos
        $pdo->exec("
            CREATE TABLE usuarios_empresas (
                id INT AUTO_INCREMENT PRIMARY KEY,
                usuario_id INT NOT NULL,
                empresa_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
                FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
                UNIQUE KEY uk_usuario_empresa (usuario_id, empresa_id),
                INDEX idx_usuario (usuario_id),
                INDEX idx_empresa (empresa_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Migra dados existentes da coluna empresa_id para a nova tabela
        $pdo->exec("
            INSERT INTO usuarios_empresas (usuario_id, empresa_id)
            SELECT id, empresa_id 
            FROM usuarios 
            WHERE empresa_id IS NOT NULL
        ");
    }
} catch (PDOException $e) {
    // Ignora erro se a tabela já existir ou se houver problema de permissão
    // O erro será capturado quando tentar usar a tabela
}

// Processa ações ANTES de incluir o header (para evitar erro de headers already sent)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $nome = sanitize($_POST['nome'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';
        $role = $_POST['role'] ?? '';
        $empresas = $_POST['empresas'] ?? [];
        $setor_id = $_POST['setor_id'] ?? null;
        $colaborador_id = $_POST['colaborador_id'] ?? null;
        $status = $_POST['status'] ?? 'ativo';
        
        if (empty($nome) || empty($email) || empty($role)) {
            redirect('usuarios.php', 'Preencha os campos obrigatórios!', 'error');
        }
        
        // Se não for ADMIN, pelo menos uma empresa é obrigatória
        if ($role !== 'ADMIN' && empty($empresas)) {
            redirect('usuarios.php', 'Selecione pelo menos uma empresa para este perfil!', 'error');
        }
        
        // Se for GESTOR, setor_id é obrigatório
        if ($role === 'GESTOR' && empty($setor_id)) {
            redirect('usuarios.php', 'Setor é obrigatório para Gestor!', 'error');
        }
        
        // Define empresa_id como primeira empresa selecionada (para compatibilidade)
        $empresa_id = !empty($empresas) ? (int)$empresas[0] : null;
        
        try {
            if ($action === 'add') {
                if (empty($senha)) {
                    redirect('usuarios.php', 'Senha é obrigatória!', 'error');
                }
                
                // Verifica se email já existe
                $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    redirect('usuarios.php', 'Email já cadastrado!', 'error');
                }
                
                // Processa upload de foto se houver
                $foto_path = null;
                if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                    $upload_result = upload_foto_perfil($_FILES['foto'], 'usuario');
                    if ($upload_result['success']) {
                        $foto_path = $upload_result['path'];
                    } else {
                        redirect('usuarios.php', 'Erro no upload da foto: ' . $upload_result['error'], 'error');
                    }
                }
                
                $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO usuarios (nome, email, senha_hash, role, empresa_id, setor_id, colaborador_id, status, foto)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$nome, $email, $senha_hash, $role, $empresa_id ?: null, $setor_id ?: null, $colaborador_id ?: null, $status, $foto_path]);
                
                $usuario_id = $pdo->lastInsertId();
                
                // Salva relacionamento com múltiplas empresas
                if (!empty($empresas)) {
                    $stmt_empresa = $pdo->prepare("INSERT INTO usuarios_empresas (usuario_id, empresa_id) VALUES (?, ?)");
                    foreach ($empresas as $emp_id) {
                        $stmt_empresa->execute([$usuario_id, (int)$emp_id]);
                    }
                }
                
                redirect('usuarios.php', 'Usuário cadastrado com sucesso!');
            } else {
                $id = $_POST['id'] ?? 0;
                
                // Verifica se email já existe (exceto o próprio usuário)
                $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
                $stmt->execute([$email, $id]);
                if ($stmt->fetch()) {
                    redirect('usuarios.php', 'Email já cadastrado!', 'error');
                }
                
                // Busca foto atual
                $stmt = $pdo->prepare("SELECT foto FROM usuarios WHERE id = ?");
                $stmt->execute([$id]);
                $foto_atual = $stmt->fetchColumn();
                
                // Processa upload de foto se houver
                $foto_path = $foto_atual; // Mantém foto atual por padrão
                if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                    // Deleta foto antiga se existir
                    if (!empty($foto_atual)) {
                        delete_foto_perfil($foto_atual);
                    }
                    
                    $upload_result = upload_foto_perfil($_FILES['foto'], 'usuario', $id);
                    if ($upload_result['success']) {
                        $foto_path = $upload_result['path'];
                    } else {
                        redirect('usuarios.php', 'Erro no upload da foto: ' . $upload_result['error'], 'error');
                    }
                }
                
                if (!empty($senha)) {
                    $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("
                        UPDATE usuarios 
                        SET nome = ?, email = ?, senha_hash = ?, role = ?, empresa_id = ?, setor_id = ?, colaborador_id = ?, status = ?, foto = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$nome, $email, $senha_hash, $role, $empresa_id ?: null, $setor_id ?: null, $colaborador_id ?: null, $status, $foto_path, $id]);
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE usuarios 
                        SET nome = ?, email = ?, role = ?, empresa_id = ?, setor_id = ?, colaborador_id = ?, status = ?, foto = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$nome, $email, $role, $empresa_id ?: null, $setor_id ?: null, $colaborador_id ?: null, $status, $foto_path, $id]);
                }
                
                // Atualiza relacionamento com empresas
                // Remove todas as empresas antigas
                $stmt_delete = $pdo->prepare("DELETE FROM usuarios_empresas WHERE usuario_id = ?");
                $stmt_delete->execute([$id]);
                
                // Insere as novas empresas
                if (!empty($empresas)) {
                    $stmt_empresa = $pdo->prepare("INSERT INTO usuarios_empresas (usuario_id, empresa_id) VALUES (?, ?)");
                    foreach ($empresas as $emp_id) {
                        $stmt_empresa->execute([$id, (int)$emp_id]);
                    }
                }
                
                redirect('usuarios.php', 'Usuário atualizado com sucesso!');
            }
        } catch (PDOException $e) {
            redirect('usuarios.php', 'Erro ao salvar: ' . $e->getMessage(), 'error');
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? 0;
        
        // Não permite excluir a si mesmo
        if ($id == $_SESSION['usuario']['id']) {
            redirect('usuarios.php', 'Você não pode excluir seu próprio usuário!', 'error');
        }
        
        try {
            $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
            $stmt->execute([$id]);
            redirect('usuarios.php', 'Usuário excluído com sucesso!');
        } catch (PDOException $e) {
            redirect('usuarios.php', 'Erro ao excluir: ' . $e->getMessage(), 'error');
        }
    }
}

// Busca usuários com suas empresas
$stmt = $pdo->query("
    SELECT u.*, 
           e.nome_fantasia as empresa_nome,
           s.nome_setor,
           c.nome_completo as colaborador_nome
    FROM usuarios u
    LEFT JOIN empresas e ON u.empresa_id = e.id
    LEFT JOIN setores s ON u.setor_id = s.id
    LEFT JOIN colaboradores c ON u.colaborador_id = c.id
    ORDER BY u.nome
");
$usuarios = $stmt->fetchAll();

// Busca empresas de cada usuário
foreach ($usuarios as &$usuario) {
    $stmt_empresas = $pdo->prepare("
        SELECT e.id, e.nome_fantasia 
        FROM usuarios_empresas ue
        INNER JOIN empresas e ON ue.empresa_id = e.id
        WHERE ue.usuario_id = ?
        ORDER BY e.nome_fantasia
    ");
    $stmt_empresas->execute([$usuario['id']]);
    $usuario['empresas'] = $stmt_empresas->fetchAll();
}
unset($usuario);

// Busca empresas
$stmt_empresas = $pdo->query("SELECT id, nome_fantasia FROM empresas WHERE status = 'ativo' ORDER BY nome_fantasia");
$empresas = $stmt_empresas->fetchAll();

// Busca colaboradores
$stmt_colab = $pdo->query("SELECT id, nome_completo FROM colaboradores WHERE status = 'ativo' ORDER BY nome_completo");
$colaboradores = $stmt_colab->fetchAll();

// Agora inclui o header (após processar POST para evitar erro de headers already sent)
$page_title = 'Usuários';
require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Usuários do Sistema</h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Usuários</li>
            </ul>
        </div>
        <div class="d-flex align-items-center py-2">
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <!--begin::Card-->
        <div class="card">
            <!--begin::Card header-->
            <div class="card-header border-0 pt-6">
                <!--begin::Card title-->
                <div class="card-title">
                    <!--begin::Search-->
                    <div class="d-flex align-items-center position-relative my-1">
                        <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <input type="text" data-kt-usuario-table-filter="search" class="form-control form-control-solid w-250px ps-12" placeholder="Buscar usuários" />
                    </div>
                    <!--end::Search-->
                </div>
                <!--begin::Card title-->
                <!--begin::Card toolbar-->
                <div class="card-toolbar">
                    <!--begin::Toolbar-->
                    <div class="d-flex justify-content-end" data-kt-usuario-table-toolbar="base">
                        <!--begin::Add usuario-->
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_usuario">
                            <i class="ki-duotone ki-plus fs-2"></i>
                            Novo Usuário
                        </button>
                        <!--end::Add usuario-->
                    </div>
                    <!--end::Toolbar-->
                </div>
                <!--end::Card toolbar-->
            </div>
            <!--end::Card header-->
            
            <!--begin::Card body-->
            <div class="card-body pt-0">
                <!--begin::Table-->
                <table class="table align-middle table-row-dashed fs-6 gy-5" id="kt_usuarios_table">
                    <thead>
                        <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                            <th class="min-w-50px">ID</th>
                            <th class="min-w-150px">Nome</th>
                            <th class="min-w-150px">Email</th>
                            <th class="min-w-100px">Perfil</th>
                            <th class="min-w-150px">Empresa</th>
                            <th class="min-w-120px">Setor</th>
                            <th class="min-w-150px">Colaborador</th>
                            <th class="min-w-120px">Último Login</th>
                            <th class="min-w-100px">Status</th>
                            <th class="text-end min-w-70px">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-600">
                        <?php foreach ($usuarios as $user): ?>
                        <tr>
                            <td><?= $user['id'] ?></td>
                            <td>
                                <a href="#" class="text-gray-800 text-hover-primary mb-1"><?= htmlspecialchars($user['nome']) ?></a>
                            </td>
                            <td>
                                <a href="mailto:<?= htmlspecialchars($user['email']) ?>" class="text-gray-600 text-hover-primary mb-1"><?= htmlspecialchars($user['email']) ?></a>
                            </td>
                            <td>
                                <?php
                                $badge_class = 'badge-light-primary';
                                if ($user['role'] === 'ADMIN') $badge_class = 'badge-light-danger';
                                elseif ($user['role'] === 'GESTOR') $badge_class = 'badge-light-warning';
                                elseif ($user['role'] === 'COLABORADOR') $badge_class = 'badge-light-info';
                                ?>
                                <span class="badge <?= $badge_class ?>"><?= $user['role'] ?></span>
                            </td>
                            <td>
                                <?php 
                                if (!empty($user['empresas'])) {
                                    $nomes_empresas = array_map(function($e) { return htmlspecialchars($e['nome_fantasia']); }, $user['empresas']);
                                    echo htmlspecialchars(implode(', ', $nomes_empresas));
                                } elseif ($user['empresa_nome']) {
                                    echo htmlspecialchars($user['empresa_nome']);
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td><?= $user['nome_setor'] ? htmlspecialchars($user['nome_setor']) : '-' ?></td>
                            <td><?= $user['colaborador_nome'] ? htmlspecialchars($user['colaborador_nome']) : '-' ?></td>
                            <td><?= $user['ultimo_login'] ? formatar_data($user['ultimo_login'], 'd/m/Y H:i') : 'Nunca' ?></td>
                            <td>
                                <?php if ($user['status'] === 'ativo'): ?>
                                    <span class="badge badge-light-success">Ativo</span>
                                <?php else: ?>
                                    <span class="badge badge-light-secondary">Inativo</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a href="#" class="btn btn-sm btn-light btn-flex btn-center btn-active-light-primary" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                    Ações 
                                    <i class="ki-duotone ki-down fs-5 ms-1">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </a>
                                <!--begin::Menu-->
                                <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-semibold fs-7 w-125px py-4" data-kt-menu="true">
                                    <!--begin::Menu item-->
                                    <div class="menu-item px-3">
                                        <a href="#" class="menu-link px-3" onclick="editarUsuario(<?= htmlspecialchars(json_encode($user)) ?>); return false;">Editar</a>
                                    </div>
                                    <!--end::Menu item-->
                                    <?php if ($user['id'] != $_SESSION['usuario']['id']): ?>
                                    <!--begin::Menu item-->
                                    <div class="menu-item px-3">
                                        <a href="#" class="menu-link px-3" data-kt-usuario-table-filter="delete_row" data-usuario-id="<?= $user['id'] ?>" data-usuario-nome="<?= htmlspecialchars($user['nome']) ?>">Excluir</a>
                                    </div>
                                    <!--end::Menu item-->
                                    <?php endif; ?>
                                </div>
                                <!--end::Menu-->
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <!--end::Table-->
            </div>
            <!--end::Card body-->
        </div>
        <!--end::Card-->
        
    </div>
</div>
<!--end::Post-->

<!--begin::Modal - Usuário-->
<div class="modal fade" id="kt_modal_usuario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header" id="kt_modal_usuario_header">
                <h2 class="fw-bold">Novo Usuário</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <form id="kt_modal_usuario_form" method="POST" enctype="multipart/form-data" class="form">
                    <input type="hidden" name="action" id="usuario_action" value="add">
                    <input type="hidden" name="id" id="usuario_id">
                    
                    <div class="row mb-7">
                        <div class="col-md-12">
                            <label class="fw-semibold fs-6 mb-2">Foto de Perfil</label>
                            <div id="foto_atual_container" style="display: none;" class="mb-2">
                                <img id="foto_atual_img" src="" alt="Foto atual" style="max-width: 150px; max-height: 150px; border-radius: 8px; border: 1px solid #ddd;">
                            </div>
                            <input type="file" name="foto" id="foto" class="form-control form-control-solid" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" />
                            <small class="text-muted">Formatos aceitos: JPG, PNG, GIF, WEBP. Tamanho máximo: 5MB</small>
                            <div id="foto_preview" class="mt-2" style="display: none;">
                                <img id="foto_preview_img" src="" alt="Preview" style="max-width: 200px; max-height: 200px; border-radius: 8px;">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-6">
                            <label class="required fw-semibold fs-6 mb-2">Nome</label>
                            <input type="text" name="nome" id="nome" class="form-control form-control-solid mb-3 mb-lg-0" required />
                        </div>
                        <div class="col-md-6">
                            <label class="required fw-semibold fs-6 mb-2">Email</label>
                            <input type="email" name="email" id="email" class="form-control form-control-solid mb-3 mb-lg-0" required />
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-6">
                            <label class="fw-semibold fs-6 mb-2">Senha <span id="senha_required">*</span></label>
                            <input type="password" name="senha" id="senha" class="form-control form-control-solid mb-3 mb-lg-0" />
                            <small class="text-muted">Deixe em branco para manter a senha atual (ao editar)</small>
                        </div>
                        <div class="col-md-6">
                            <label class="required fw-semibold fs-6 mb-2">Perfil</label>
                            <select name="role" id="role" class="form-select form-select-solid" required>
                                <option value="">Selecione...</option>
                                <option value="ADMIN">Administrador</option>
                                <option value="RH">RH</option>
                                <option value="GESTOR">Gestor</option>
                                <option value="COLABORADOR">Colaborador</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-6">
                            <label class="fw-semibold fs-6 mb-2">Empresas <span id="empresas_required"></span></label>
                            <div class="form-control form-control-solid" style="min-height: 150px; max-height: 200px; overflow-y: auto; padding: 10px;">
                                <?php foreach ($empresas as $emp): ?>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="empresas[]" value="<?= $emp['id'] ?>" id="empresa_<?= $emp['id'] ?>" />
                                    <label class="form-check-label" for="empresa_<?= $emp['id'] ?>">
                                        <?= htmlspecialchars($emp['nome_fantasia']) ?>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                                <?php if (empty($empresas)): ?>
                                <small class="text-muted">Nenhuma empresa cadastrada</small>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted">Selecione uma ou mais empresas. Para RH do grupo, selecione todas as empresas.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="fw-semibold fs-6 mb-2">Setor <span id="setor_required"></span></label>
                            <select name="setor_id" id="setor_id" class="form-select form-select-solid">
                                <option value="">Selecione uma empresa primeiro</option>
                            </select>
                            <small class="text-muted">Obrigatório apenas para Gestor</small>
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-12">
                            <label class="fw-semibold fs-6 mb-2">Colaborador (opcional)</label>
                            <select name="colaborador_id" id="colaborador_id" class="form-select form-select-solid">
                                <option value="">Nenhum</option>
                                <?php foreach ($colaboradores as $colab): ?>
                                <option value="<?= $colab['id'] ?>"><?= htmlspecialchars($colab['nome_completo']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-6">
                            <label class="fw-semibold fs-6 mb-2">Status</label>
                            <select name="status" id="status" class="form-select form-select-solid">
                                <option value="ativo">Ativo</option>
                                <option value="inativo">Inativo</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="text-center pt-15">
                        <button type="reset" class="btn btn-light me-3" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <span class="indicator-label">Salvar</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!--end::Modal - Usuário-->

<script>
"use strict";
var KTUsuariosList = function() {
    var t, n;
    
    var initDeleteHandlers = function() {
        n.querySelectorAll('[data-kt-usuario-table-filter="delete_row"]').forEach(function(element) {
            element.addEventListener("click", function(e) {
                e.preventDefault();
                const usuarioId = this.getAttribute("data-usuario-id");
                const usuarioNome = this.getAttribute("data-usuario-nome");
                
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        text: "Tem certeza que deseja excluir " + usuarioNome + "?",
                        icon: "warning",
                        showCancelButton: true,
                        buttonsStyling: false,
                        confirmButtonText: "Sim, excluir!",
                        cancelButtonText: "Não, cancelar",
                        customClass: {
                            confirmButton: "btn fw-bold btn-danger",
                            cancelButton: "btn fw-bold btn-active-light-primary"
                        }
                    }).then(function(result) {
                        if (result.value) {
                            const form = document.createElement('form');
                            form.method = 'POST';
                            form.innerHTML = `
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="${usuarioId}">
                            `;
                            document.body.appendChild(form);
                            form.submit();
                        } else if (result.dismiss === Swal.DismissReason.cancel) {
                            Swal.fire({
                                text: usuarioNome + " não foi excluído.",
                                icon: "error",
                                buttonsStyling: false,
                                confirmButtonText: "Ok, entendi!",
                                customClass: {
                                    confirmButton: "btn fw-bold btn-primary"
                                }
                            });
                        }
                    });
                } else {
                    if (confirm("Tem certeza que deseja excluir " + usuarioNome + "?")) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.innerHTML = `
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="${usuarioId}">
                        `;
                        document.body.appendChild(form);
                        form.submit();
                    }
                }
            });
        });
    };
    
    return {
        init: function() {
            n = document.querySelector("#kt_usuarios_table");
            
            if (n) {
                t = $(n).DataTable({
                    info: false,
                    order: [],
                    pageLength: 25,
                    language: {
                        url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json'
                    },
                    columnDefs: [
                        { orderable: false, targets: 9 }
                    ]
                });
                
                // Busca customizada
                document.querySelector('[data-kt-usuario-table-filter="search"]').addEventListener("keyup", function(e) {
                    t.search(e.target.value).draw();
                });
                
                // Inicializa handlers de exclusão
                initDeleteHandlers();
                
                // Reinicializa apenas os handlers após draw
                t.on("draw", function() {
                    initDeleteHandlers();
                    
                    // Inicialização manual de componentes específicos se necessário
                    // Evita chamar KTMenu.createInstances() que causa conflito com o menu lateral
                    var menus = document.querySelectorAll('#kt_usuarios_table [data-kt-menu="true"]');
                    if (menus && menus.length > 0) {
                        menus.forEach(function(el) {
                            if (typeof KTMenu !== 'undefined') {
                                // Tenta reinicializar apenas este elemento
                                try {
                                    KTMenu.init(el);
                                } catch (e) {}
                            }
                        });
                    }
                });
            }
        }
    };
}();

// Aguarda jQuery e SweetAlert estarem disponíveis
(function waitForDependencies() {
    if (typeof jQuery === 'undefined' || typeof $ === 'undefined') {
        setTimeout(waitForDependencies, 50);
        return;
    }
    
    if (typeof Swal === 'undefined') {
        setTimeout(function() {
            if (typeof Swal !== 'undefined') {
                KTUsuariosList.init();
            } else {
                console.warn('SweetAlert2 não foi carregado, usando fallback');
                KTUsuariosList.init();
            }
        }, 100);
    } else {
        $(document).ready(function() {
            KTUsuariosList.init();
        });
    }
})();

function carregarSetores(empresaId, setorSelecionado = '') {
    const setorSelect = document.getElementById('setor_id');
    setorSelect.innerHTML = '<option value="">Carregando...</option>';
    
    if (empresaId) {
        fetch(`../api/get_setores.php?empresa_id=${empresaId}`)
            .then(r => r.json())
            .then(data => {
                setorSelect.innerHTML = '<option value="">Selecione...</option>';
                const setores = Array.isArray(data) ? data : (data.setores || []);
                setores.forEach(setor => {
                    const selected = setor.id == setorSelecionado ? 'selected' : '';
                    setorSelect.innerHTML += `<option value="${setor.id}" ${selected}>${setor.nome_setor}</option>`;
                });
            })
            .catch(() => {
                setorSelect.innerHTML = '<option value="">Erro ao carregar</option>';
            });
    } else {
        setorSelect.innerHTML = '<option value="">Selecione uma empresa primeiro</option>';
    }
}

function atualizarValidacaoSetor(role) {
    const setorSelect = document.getElementById('setor_id');
    const setorRequired = document.getElementById('setor_required');
    
    if (role === 'GESTOR') {
        setorSelect.required = true;
        setorRequired.textContent = '*';
    } else {
        setorSelect.required = false;
        setorRequired.textContent = '';
    }
}

function editarUsuario(usuario) {
    document.getElementById('kt_modal_usuario_header').querySelector('h2').textContent = 'Editar Usuário';
    document.getElementById('usuario_action').value = 'edit';
    document.getElementById('usuario_id').value = usuario.id;
    document.getElementById('nome').value = usuario.nome || '';
    document.getElementById('email').value = usuario.email || '';
    document.getElementById('role').value = usuario.role || '';
    document.getElementById('colaborador_id').value = usuario.colaborador_id || '';
    document.getElementById('status').value = usuario.status || 'ativo';
    document.getElementById('senha').value = '';
    document.getElementById('senha_required').textContent = '';
    
    // Limpa todas as empresas selecionadas
    document.querySelectorAll('input[name="empresas[]"]').forEach(cb => {
        cb.checked = false;
    });
    
    // Marca as empresas do usuário
    if (usuario.empresas && usuario.empresas.length > 0) {
        usuario.empresas.forEach(function(emp) {
            const checkbox = document.getElementById('empresa_' + emp.id);
            if (checkbox) {
                checkbox.checked = true;
            }
        });
    } else if (usuario.empresa_id) {
        // Fallback para compatibilidade com dados antigos
        const checkbox = document.getElementById('empresa_' + usuario.empresa_id);
        if (checkbox) {
            checkbox.checked = true;
        }
    }
    
    // Mostra foto atual se existir
    const fotoContainer = document.getElementById('foto_atual_container');
    const fotoImg = document.getElementById('foto_atual_img');
    if (usuario.foto) {
        fotoImg.src = '../' + usuario.foto;
        fotoContainer.style.display = 'block';
    } else {
        fotoContainer.style.display = 'none';
    }
    
    // Limpa preview
    document.getElementById('foto_preview').style.display = 'none';
    document.getElementById('foto').value = '';
    
    // Carrega setores se empresa estiver definida
    // Usa a primeira empresa selecionada ou empresa_id para compatibilidade
    let empresaParaSetores = null;
    if (usuario.empresas && usuario.empresas.length > 0) {
        empresaParaSetores = usuario.empresas[0].id;
    } else if (usuario.empresa_id) {
        empresaParaSetores = usuario.empresa_id;
    }
    
    if (empresaParaSetores) {
        carregarSetores(empresaParaSetores, usuario.setor_id || '');
    }
    
    // Atualiza validação de setor
    atualizarValidacaoSetor(usuario.role);
    
    // Atualiza validação de empresas
    atualizarValidacaoEmpresas(usuario.role);
    
    const modal = new bootstrap.Modal(document.getElementById('kt_modal_usuario'));
    modal.show();
}

// Carrega setores quando uma empresa é selecionada (usa a primeira empresa selecionada)
function atualizarSetores() {
    const empresasSelecionadas = Array.from(document.querySelectorAll('input[name="empresas[]"]:checked')).map(cb => cb.value);
    if (empresasSelecionadas.length > 0) {
        carregarSetores(empresasSelecionadas[0]);
    } else {
        document.getElementById('setor_id').innerHTML = '<option value="">Selecione uma empresa primeiro</option>';
    }
}

// Adiciona listener para cada checkbox de empresa
document.querySelectorAll('input[name="empresas[]"]').forEach(cb => {
    cb.addEventListener('change', atualizarSetores);
});

function atualizarValidacaoEmpresas(role) {
    const empresasRequired = document.getElementById('empresas_required');
    const empresasCheckboxes = document.querySelectorAll('input[name="empresas[]"]');
    
    if (role !== 'ADMIN' && role !== '') {
        empresasRequired.textContent = '*';
        empresasCheckboxes.forEach(cb => {
            cb.setAttribute('data-required', 'true');
        });
    } else {
        empresasRequired.textContent = '';
        empresasCheckboxes.forEach(cb => {
            cb.removeAttribute('data-required');
        });
    }
}

// Validação: se não for ADMIN, pelo menos uma empresa é obrigatória
document.getElementById('role').addEventListener('change', function() {
    // Atualiza validação de empresas
    atualizarValidacaoEmpresas(this.value);
    
    // Atualiza validação de setor
    atualizarValidacaoSetor(this.value);
});

// Validação de Email
function validarEmail(email) {
    var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// Validação do formulário
document.getElementById('kt_modal_usuario_form').addEventListener('submit', function(e) {
    var email = document.getElementById('email').value;
    var role = document.getElementById('role').value;
    var empresasSelecionadas = Array.from(document.querySelectorAll('input[name="empresas[]"]:checked')).length;
    
    if (email && email.length > 0) {
        if (!validarEmail(email)) {
            e.preventDefault();
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    text: 'Email inválido!',
                    icon: 'error',
                    buttonsStyling: false,
                    confirmButtonText: 'Ok, entendi!',
                    customClass: {
                        confirmButton: 'btn fw-bold btn-primary'
                    }
                });
            } else {
                alert('Email inválido!');
            }
            return false;
        }
    }
    
    // Valida se pelo menos uma empresa foi selecionada (exceto ADMIN)
    if (role !== 'ADMIN' && role !== '' && empresasSelecionadas === 0) {
        e.preventDefault();
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                text: 'Selecione pelo menos uma empresa para este perfil!',
                icon: 'error',
                buttonsStyling: false,
                confirmButtonText: 'Ok, entendi!',
                customClass: {
                    confirmButton: 'btn fw-bold btn-primary'
                }
            });
        } else {
            alert('Selecione pelo menos uma empresa para este perfil!');
        }
        return false;
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

// Reset modal ao fechar
document.getElementById('kt_modal_usuario')?.addEventListener('hidden.bs.modal', function () {
    document.getElementById('kt_modal_usuario_form').reset();
    document.getElementById('kt_modal_usuario_header').querySelector('h2').textContent = 'Novo Usuário';
    document.getElementById('usuario_action').value = 'add';
    document.getElementById('usuario_id').value = '';
    document.getElementById('foto_atual_container').style.display = 'none';
    document.getElementById('foto_preview').style.display = 'none';
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

// Reset modal ao fechar
document.getElementById('kt_modal_usuario').addEventListener('hidden.bs.modal', function () {
    document.getElementById('kt_modal_usuario_form').reset();
    document.getElementById('kt_modal_usuario_header').querySelector('h2').textContent = 'Novo Usuário';
    document.getElementById('usuario_action').value = 'add';
    document.getElementById('usuario_id').value = '';
    document.getElementById('senha_required').textContent = '*';
    document.getElementById('setor_id').innerHTML = '<option value="">Selecione uma empresa primeiro</option>';
    document.getElementById('setor_required').textContent = '';
    document.getElementById('empresas_required').textContent = '';
    document.getElementById('foto_atual_container').style.display = 'none';
    document.getElementById('foto_preview').style.display = 'none';
    
    // Desmarca todas as empresas
    document.querySelectorAll('input[name="empresas[]"]').forEach(cb => {
        cb.checked = false;
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
