<?php
/**
 * CRUD de Empresas - Metronic Theme
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('empresas.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Processa ações ANTES de incluir o header (para evitar erro de headers already sent)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $nome_fantasia = sanitize($_POST['nome_fantasia'] ?? '');
        $razao_social = sanitize($_POST['razao_social'] ?? '');
        $cnpj = preg_replace('/[^0-9]/', '', $_POST['cnpj'] ?? '');
        $telefone = sanitize($_POST['telefone'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $cidade = sanitize($_POST['cidade'] ?? '');
        $estado = strtoupper(sanitize($_POST['estado'] ?? ''));
        $status = $_POST['status'] ?? 'ativo';
        $percentual_hora_extra = !empty($_POST['percentual_hora_extra']) ? str_replace(',', '.', $_POST['percentual_hora_extra']) : 50.00;
        
        if (empty($nome_fantasia) || empty($razao_social)) {
            redirect('empresas.php', 'Preencha os campos obrigatórios!', 'error');
        }
        
        try {
            if ($action === 'add') {
                $stmt = $pdo->prepare("
                    INSERT INTO empresas (nome_fantasia, razao_social, cnpj, telefone, email, cidade, estado, status, percentual_hora_extra)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$nome_fantasia, $razao_social, $cnpj, $telefone, $email, $cidade, $estado, $status, $percentual_hora_extra]);
                redirect('empresas.php', 'Empresa cadastrada com sucesso!');
            } else {
                $id = $_POST['id'] ?? 0;
                $stmt = $pdo->prepare("
                    UPDATE empresas 
                    SET nome_fantasia = ?, razao_social = ?, cnpj = ?, telefone = ?, email = ?, cidade = ?, estado = ?, status = ?, percentual_hora_extra = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nome_fantasia, $razao_social, $cnpj, $telefone, $email, $cidade, $estado, $status, $percentual_hora_extra, $id]);
                redirect('empresas.php', 'Empresa atualizada com sucesso!');
            }
        } catch (PDOException $e) {
            redirect('empresas.php', 'Erro ao salvar: ' . $e->getMessage(), 'error');
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? 0;
        try {
            $stmt = $pdo->prepare("DELETE FROM empresas WHERE id = ?");
            $stmt->execute([$id]);
            redirect('empresas.php', 'Empresa excluída com sucesso!');
        } catch (PDOException $e) {
            redirect('empresas.php', 'Erro ao excluir: ' . $e->getMessage(), 'error');
        }
    }
}

// Busca empresas
if ($usuario['role'] === 'ADMIN') {
    $stmt = $pdo->query("SELECT * FROM empresas ORDER BY nome_fantasia");
    $empresas = $stmt->fetchAll();
} elseif ($usuario['role'] === 'RH') {
    // RH pode ter múltiplas empresas
    if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
        $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
        $stmt = $pdo->prepare("SELECT * FROM empresas WHERE id IN ($placeholders) ORDER BY nome_fantasia");
        $stmt->execute($usuario['empresas_ids']);
        $empresas = $stmt->fetchAll();
    } else {
        // Fallback para compatibilidade
        $stmt = $pdo->prepare("SELECT * FROM empresas WHERE id = ? ORDER BY nome_fantasia");
        $stmt->execute([$usuario['empresa_id'] ?? 0]);
        $empresas = $stmt->fetchAll();
    }
} else {
    // Outros roles (GESTOR, etc) - apenas uma empresa
    $stmt = $pdo->prepare("SELECT * FROM empresas WHERE id = ? ORDER BY nome_fantasia");
    $stmt->execute([$usuario['empresa_id'] ?? 0]);
    $empresas = $stmt->fetchAll();
}

// Agora inclui o header (após processar POST para evitar erro de headers already sent)
$page_title = 'Empresas';
require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Empresas</h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Empresas</li>
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
                        <input type="text" data-kt-empresa-table-filter="search" class="form-control form-control-solid w-250px ps-12" placeholder="Buscar empresas" />
                    </div>
                    <!--end::Search-->
                </div>
                <!--begin::Card title-->
                <!--begin::Card toolbar-->
                <div class="card-toolbar">
                    <!--begin::Toolbar-->
                    <div class="d-flex justify-content-end" data-kt-empresa-table-toolbar="base">
                        <!--begin::Add empresa-->
                        <?php if ($usuario['role'] === 'ADMIN'): ?>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_empresa">
                            <i class="ki-duotone ki-plus fs-2"></i>
                            Nova Empresa
                        </button>
                        <?php endif; ?>
                        <!--end::Add empresa-->
                    </div>
                    <!--end::Toolbar-->
                </div>
                <!--end::Card toolbar-->
            </div>
            <!--end::Card header-->
            
            <!--begin::Card body-->
            <div class="card-body pt-0">
                <!--begin::Table-->
                <table class="table align-middle table-row-dashed fs-6 gy-5" id="kt_empresas_table">
                    <thead>
                        <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                            <th class="min-w-50px">ID</th>
                            <th class="min-w-150px">Nome Fantasia</th>
                            <th class="min-w-200px">Razão Social</th>
                            <th class="min-w-120px">CNPJ</th>
                            <th class="min-w-120px">Telefone</th>
                            <th class="min-w-150px">Email</th>
                            <th class="min-w-100px">Cidade/UF</th>
                            <th class="min-w-100px">Status</th>
                            <?php if ($usuario['role'] === 'ADMIN'): ?>
                            <th class="text-end min-w-70px">Ações</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody class="fw-semibold text-gray-600">
                        <?php foreach ($empresas as $empresa): ?>
                        <tr>
                            <td><?= $empresa['id'] ?></td>
                            <td>
                                <a href="#" class="text-gray-800 text-hover-primary mb-1"><?= htmlspecialchars($empresa['nome_fantasia']) ?></a>
                            </td>
                            <td><?= htmlspecialchars($empresa['razao_social']) ?></td>
                            <td><?= formatar_cnpj($empresa['cnpj']) ?></td>
                            <td><?= formatar_telefone($empresa['telefone']) ?></td>
                            <td>
                                <a href="mailto:<?= htmlspecialchars($empresa['email']) ?>" class="text-gray-600 text-hover-primary mb-1"><?= htmlspecialchars($empresa['email']) ?></a>
                            </td>
                            <td><?= htmlspecialchars($empresa['cidade']) ?>/<?= htmlspecialchars($empresa['estado']) ?></td>
                            <td>
                                <?php if ($empresa['status'] === 'ativo'): ?>
                                    <span class="badge badge-light-success">Ativo</span>
                                <?php else: ?>
                                    <span class="badge badge-light-secondary">Inativo</span>
                                <?php endif; ?>
                            </td>
                            <?php if ($usuario['role'] === 'ADMIN'): ?>
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
                                        <a href="#" class="menu-link px-3" onclick="editarEmpresa(<?= htmlspecialchars(json_encode($empresa)) ?>); return false;">Editar</a>
                                    </div>
                                    <!--end::Menu item-->
                                    <!--begin::Menu item-->
                                    <div class="menu-item px-3">
                                        <a href="#" class="menu-link px-3" data-kt-empresa-table-filter="delete_row" data-empresa-id="<?= $empresa['id'] ?>" data-empresa-nome="<?= htmlspecialchars($empresa['nome_fantasia']) ?>">Excluir</a>
                                    </div>
                                    <!--end::Menu item-->
                                </div>
                                <!--end::Menu-->
                            </td>
                            <?php endif; ?>
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

<!--begin::Modal - Empresa-->
<div class="modal fade" id="kt_modal_empresa" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header" id="kt_modal_empresa_header">
                <h2 class="fw-bold">Nova Empresa</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <form id="kt_modal_empresa_form" method="POST" class="form">
                    <input type="hidden" name="action" id="empresa_action" value="add">
                    <input type="hidden" name="id" id="empresa_id">
                    
                    <div class="row mb-7">
                        <div class="col-md-6">
                            <label class="required fw-semibold fs-6 mb-2">Nome Fantasia</label>
                            <input type="text" name="nome_fantasia" id="nome_fantasia" class="form-control form-control-solid mb-3 mb-lg-0" required />
                        </div>
                        <div class="col-md-6">
                            <label class="required fw-semibold fs-6 mb-2">Razão Social</label>
                            <input type="text" name="razao_social" id="razao_social" class="form-control form-control-solid mb-3 mb-lg-0" required />
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-6">
                            <label class="fw-semibold fs-6 mb-2">CNPJ</label>
                            <input type="text" name="cnpj" id="cnpj" class="form-control form-control-solid mb-3 mb-lg-0" placeholder="00.000.000/0000-00" />
                        </div>
                        <div class="col-md-6">
                            <label class="fw-semibold fs-6 mb-2">Telefone</label>
                            <input type="text" name="telefone" id="telefone" class="form-control form-control-solid mb-3 mb-lg-0" placeholder="(00) 00000-0000" />
                        </div>
                    </div>
                    
                    <div class="row mb-7">
                        <div class="col-md-4">
                            <label class="fw-semibold fs-6 mb-2">Email</label>
                            <input type="email" name="email" id="email" class="form-control form-control-solid mb-3 mb-lg-0" />
                        </div>
                        <div class="col-md-4">
                            <label class="fw-semibold fs-6 mb-2">Cidade</label>
                            <input type="text" name="cidade" id="cidade" class="form-control form-control-solid mb-3 mb-lg-0" />
                        </div>
                        <div class="col-md-4">
                            <label class="fw-semibold fs-6 mb-2">Estado (UF)</label>
                            <input type="text" name="estado" id="estado" class="form-control form-control-solid mb-3 mb-lg-0" maxlength="2" placeholder="SP" />
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
                        <div class="col-md-6">
                            <label class="fw-semibold fs-6 mb-2">% Adicional Hora Extra</label>
                            <input type="text" name="percentual_hora_extra" id="percentual_hora_extra" class="form-control form-control-solid" placeholder="50,00" />
                            <small class="text-muted">Percentual adicional sobre a hora normal (padrão: 50%)</small>
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
<!--end::Modal - Empresa-->

<script>
"use strict";
var KTEmpresasList = function() {
    var t, n;
    
    var initDeleteHandlers = function() {
        n.querySelectorAll('[data-kt-empresa-table-filter="delete_row"]').forEach(function(element) {
            element.addEventListener("click", function(e) {
                e.preventDefault();
                const empresaId = this.getAttribute("data-empresa-id");
                const empresaNome = this.getAttribute("data-empresa-nome");
                
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        text: "Tem certeza que deseja excluir " + empresaNome + "?",
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
                                <input type="hidden" name="id" value="${empresaId}">
                            `;
                            document.body.appendChild(form);
                            form.submit();
                        } else if (result.dismiss === Swal.DismissReason.cancel) {
                            Swal.fire({
                                text: empresaNome + " não foi excluída.",
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
                    // Fallback para confirm se SweetAlert não estiver disponível
                    if (confirm("Tem certeza que deseja excluir " + empresaNome + "?")) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.innerHTML = `
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="${empresaId}">
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
            // Aguarda um pouco para garantir que o Metronic inicializou completamente
            setTimeout(function() {
                n = document.querySelector("#kt_empresas_table");
                
                if (n) {
                    t = $(n).DataTable({
                        info: false,
                        order: [],
                        pageLength: 25,
                        language: {
                            url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/pt-BR.json'
                        },
                        columnDefs: [
                            { orderable: false, targets: <?= $usuario['role'] === 'ADMIN' ? 8 : 7 ?> }
                        ]
                    });
                    
                    // Busca customizada
                    var searchInput = document.querySelector('[data-kt-empresa-table-filter="search"]');
                    if (searchInput) {
                        searchInput.addEventListener("keyup", function(e) {
                            t.search(e.target.value).draw();
                        });
                    }
                    
                    // Inicializa handlers de exclusão
                    initDeleteHandlers();
                    
                    // Reinicializa apenas os handlers após draw
                    t.on("draw", function() {
                        initDeleteHandlers();
                        
                        // Inicialização manual de componentes específicos se necessário
                        // Evita chamar KTMenu.createInstances() que causa conflito com o menu lateral
                        var menus = document.querySelectorAll('#kt_empresas_table [data-kt-menu="true"]');
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
            }, 200);
        }
    };
}();

// Aguarda jQuery e SweetAlert estarem disponíveis
(function waitForDependencies() {
    if (typeof jQuery === 'undefined' || typeof $ === 'undefined') {
        setTimeout(waitForDependencies, 50);
        return;
    }
    
    // Aguarda SweetAlert estar disponível
    if (typeof Swal === 'undefined') {
        setTimeout(function() {
            if (typeof Swal !== 'undefined') {
                KTEmpresasList.init();
            } else {
                console.warn('SweetAlert2 não foi carregado, usando fallback');
                KTEmpresasList.init();
            }
        }, 100);
    } else {
        $(document).ready(function() {
            KTEmpresasList.init();
        });
    }
})();

function editarEmpresa(empresa) {
    document.getElementById('kt_modal_empresa_header').querySelector('h2').textContent = 'Editar Empresa';
    document.getElementById('empresa_action').value = 'edit';
    document.getElementById('empresa_id').value = empresa.id;
    document.getElementById('nome_fantasia').value = empresa.nome_fantasia || '';
    document.getElementById('razao_social').value = empresa.razao_social || '';
    document.getElementById('cnpj').value = empresa.cnpj || '';
    document.getElementById('telefone').value = empresa.telefone || '';
    document.getElementById('email').value = empresa.email || '';
    document.getElementById('cidade').value = empresa.cidade || '';
    document.getElementById('estado').value = empresa.estado || '';
    document.getElementById('status').value = empresa.status || 'ativo';
    document.getElementById('percentual_hora_extra').value = empresa.percentual_hora_extra ? parseFloat(empresa.percentual_hora_extra).toLocaleString('pt-BR', {minimumFractionDigits: 2}) : '50,00';
    
    const modal = new bootstrap.Modal(document.getElementById('kt_modal_empresa'));
    modal.show();
}

// Reset modal ao fechar
document.getElementById('kt_modal_empresa').addEventListener('hidden.bs.modal', function () {
    document.getElementById('kt_modal_empresa_form').reset();
    document.getElementById('kt_modal_empresa_header').querySelector('h2').textContent = 'Nova Empresa';
    document.getElementById('empresa_action').value = 'add';
    document.getElementById('empresa_id').value = '';
});

// Aplica máscaras quando o modal é aberto
(function waitForDependencies() {
    if (typeof jQuery === 'undefined' || typeof $ === 'undefined') {
        setTimeout(waitForDependencies, 50);
        return;
    }
    
    $(document).ready(function() {
        // Aguarda jQuery Mask estar disponível
        if (typeof $.fn.mask !== 'undefined') {
            // Máscara para CNPJ
            $('#cnpj').mask('00.000.000/0000-00');
            
            // Máscara para telefone (aceita fixo e celular)
            var SPMaskBehavior = function (val) {
                return val.replace(/\D/g, '').length === 11 ? '(00) 00000-0000' : '(00) 0000-00000';
            },
            spOptions = {
                onKeyPress: function(val, e, field, options) {
                    field.mask(SPMaskBehavior.apply({}, arguments), options);
                }
            };
            $('#telefone').mask(SPMaskBehavior, spOptions);
            
            // Máscara para percentual hora extra
            $('#percentual_hora_extra').mask('#0,00', {reverse: true});
            
            // Máscara para Estado (UF) - apenas letras maiúsculas
            $('#estado').mask('AA', {
                translation: {
                    'A': {pattern: /[A-Za-z]/, optional: false}
                }
            }).on('input', function() {
                this.value = this.value.toUpperCase();
            });
            
            // Reaplica máscaras quando o modal é aberto
            $('#kt_modal_empresa').on('shown.bs.modal', function() {
                $('#cnpj').mask('00.000.000/0000-00');
                $('#telefone').mask(SPMaskBehavior, spOptions);
            });
        } else {
            setTimeout(waitForDependencies, 100);
        }
    });
})();

// Validação de CNPJ
function validarCNPJ(cnpj) {
    cnpj = cnpj.replace(/[^\d]+/g, '');
    
    if (cnpj.length !== 14) return false;
    
    // Elimina CNPJs conhecidos como inválidos
    if (cnpj === "00000000000000" || 
        cnpj === "11111111111111" || 
        cnpj === "22222222222222" || 
        cnpj === "33333333333333" || 
        cnpj === "44444444444444" || 
        cnpj === "55555555555555" || 
        cnpj === "66666666666666" || 
        cnpj === "77777777777777" || 
        cnpj === "88888888888888" || 
        cnpj === "99999999999999") {
        return false;
    }
    
    // Valida dígitos verificadores
    var tamanho = cnpj.length - 2;
    var numeros = cnpj.substring(0, tamanho);
    var digitos = cnpj.substring(tamanho);
    var soma = 0;
    var pos = tamanho - 7;
    
    for (var i = tamanho; i >= 1; i--) {
        soma += numeros.charAt(tamanho - i) * pos--;
        if (pos < 2) pos = 9;
    }
    
    var resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;
    if (resultado != digitos.charAt(0)) return false;
    
    tamanho = tamanho + 1;
    numeros = cnpj.substring(0, tamanho);
    soma = 0;
    pos = tamanho - 7;
    
    for (var i = tamanho; i >= 1; i--) {
        soma += numeros.charAt(tamanho - i) * pos--;
        if (pos < 2) pos = 9;
    }
    
    resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;
    if (resultado != digitos.charAt(1)) return false;
    
    return true;
}

// Validação de Email
function validarEmail(email) {
    var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// Validação do formulário
document.getElementById('kt_modal_empresa_form').addEventListener('submit', function(e) {
    var cnpj = document.getElementById('cnpj').value.replace(/[^\d]+/g, '');
    var email = document.getElementById('email').value;
    var telefone = document.getElementById('telefone').value.replace(/[^\d]+/g, '');
    
    // Valida CNPJ se preenchido
    if (cnpj && cnpj.length > 0) {
        if (!validarCNPJ(cnpj)) {
            e.preventDefault();
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    text: 'CNPJ inválido!',
                    icon: 'error',
                    buttonsStyling: false,
                    confirmButtonText: 'Ok, entendi!',
                    customClass: {
                        confirmButton: 'btn fw-bold btn-primary'
                    }
                });
            } else {
                alert('CNPJ inválido!');
            }
            return false;
        }
    }
    
    // Valida Email se preenchido
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
    
    // Valida Telefone se preenchido (deve ter pelo menos 10 dígitos)
    if (telefone && telefone.length > 0) {
        if (telefone.length < 10) {
            e.preventDefault();
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    text: 'Telefone inválido!',
                    icon: 'error',
                    buttonsStyling: false,
                    confirmButtonText: 'Ok, entendi!',
                    customClass: {
                        confirmButton: 'btn fw-bold btn-primary'
                    }
                });
            } else {
                alert('Telefone inválido!');
            }
            return false;
        }
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
