<?php
/**
 * Editar Template de Contrato
 */

$page_title = 'Editar Template de Contrato';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/contratos_functions.php';

require_page_permission('contrato_template_edit.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];
$template_id = intval($_GET['id'] ?? 0);

if ($template_id <= 0) {
    redirect('contrato_templates.php', 'Template não encontrado.', 'error');
}

// Busca template
$stmt = $pdo->prepare("SELECT * FROM contratos_templates WHERE id = ?");
$stmt->execute([$template_id]);
$template = $stmt->fetch();

if (!$template) {
    redirect('contrato_templates.php', 'Template não encontrado.', 'error');
}

// Verifica permissão
if ($template['criado_por_usuario_id'] != $usuario['id'] && $usuario['role'] !== 'ADMIN') {
    redirect('contrato_templates.php', 'Você não tem permissão para editar este template.', 'error');
}

// Processa POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $conteudo_html = $_POST['conteudo_html'] ?? '';
    $ativo = isset($_POST['ativo']) ? 1 : 0;
    
    if (empty($nome) || empty($conteudo_html)) {
        redirect('contrato_template_edit.php?id=' . $template_id, 'Preencha todos os campos obrigatórios!', 'error');
    }
    
    // Extrai variáveis usadas
    $variaveis = extrair_variaveis_template($conteudo_html);
    $variaveis_json = json_encode($variaveis);
    
    try {
        $stmt = $pdo->prepare("
            UPDATE contratos_templates 
            SET nome = ?, descricao = ?, conteudo_html = ?, variaveis_disponiveis = ?, ativo = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $nome,
            $descricao,
            $conteudo_html,
            $variaveis_json,
            $ativo,
            $template_id
        ]);
        
        redirect('contrato_templates.php', 'Template atualizado com sucesso!', 'success');
    } catch (PDOException $e) {
        redirect('contrato_template_edit.php?id=' . $template_id, 'Erro ao atualizar template: ' . $e->getMessage(), 'error');
    }
}

// Lista de variáveis disponíveis
$variaveis_disponiveis = [
    'Dados do Colaborador' => [
        '{{colaborador.nome_completo}}' => 'Nome Completo',
        '{{colaborador.cpf}}' => 'CPF',
        '{{colaborador.rg}}' => 'RG',
        '{{colaborador.email_pessoal}}' => 'Email Pessoal',
        '{{colaborador.telefone}}' => 'Telefone',
        '{{colaborador.data_nascimento}}' => 'Data de Nascimento',
        '{{colaborador.endereco_completo}}' => 'Endereço Completo',
        '{{colaborador.cidade}}' => 'Cidade',
        '{{colaborador.estado}}' => 'Estado',
        '{{colaborador.cep}}' => 'CEP',
    ],
    'Dados da Empresa' => [
        '{{colaborador.empresa_nome}}' => 'Nome da Empresa',
        '{{colaborador.setor_nome}}' => 'Setor',
        '{{colaborador.cargo_nome}}' => 'Cargo',
        '{{colaborador.salario}}' => 'Salário',
        '{{colaborador.data_admissao}}' => 'Data de Admissão',
    ],
    'Dados do Contrato' => [
        '{{contrato.titulo}}' => 'Título do Contrato',
        '{{contrato.descricao_funcao}}' => 'Descrição da Função',
        '{{contrato.data_criacao}}' => 'Data de Criação',
        '{{contrato.data_vencimento}}' => 'Data de Vencimento',
        '{{contrato.observacoes}}' => 'Observações',
    ],
    'Data/Hora' => [
        '{{data_atual}}' => 'Data Atual (dd/mm/yyyy)',
        '{{hora_atual}}' => 'Hora Atual (HH:mm)',
        '{{data_formatada}}' => 'Data Formatada (dd de mês de yyyy)',
    ],
];

require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Editar Template de Contrato</h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-muted">Contratos</li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Editar Template</li>
            </ul>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <form method="POST" id="form_template">
            <div class="row">
                <!--begin::Col - Editor-->
                <div class="col-lg-8">
                    <!--begin::Card-->
                    <div class="card mb-5">
                        <div class="card-header border-0 pt-5">
                            <h3 class="card-title align-items-start flex-column">
                                <span class="card-label fw-bold fs-3 mb-1">Conteúdo do Template</span>
                            </h3>
                        </div>
                        <div class="card-body pt-5">
                            <div class="mb-10">
                                <label class="form-label required">Nome do Template</label>
                                <input type="text" name="nome" class="form-control form-control-solid" 
                                       value="<?= htmlspecialchars($template['nome']) ?>" required />
                            </div>
                            
                            <div class="mb-10">
                                <label class="form-label">Descrição</label>
                                <textarea name="descricao" class="form-control form-control-solid" rows="2"><?= htmlspecialchars($template['descricao'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="mb-10">
                                <label class="form-label required">Conteúdo</label>
                                <div class="d-flex justify-content-end mb-2">
                                    <button type="button" class="btn btn-sm btn-light-primary" id="btn_inserir_variavel">
                                        <i class="ki-duotone ki-plus fs-4">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        Inserir Variável
                                    </button>
                                </div>
                                <textarea id="conteudo_html" name="conteudo_html" class="form-control" rows="20" required><?= htmlspecialchars($template['conteudo_html']) ?></textarea>
                            </div>
                            
                            <div class="form-check form-check-custom form-check-solid">
                                <input class="form-check-input" type="checkbox" name="ativo" id="ativo" value="1" 
                                       <?= $template['ativo'] ? 'checked' : '' ?> />
                                <label class="form-check-label" for="ativo">
                                    Template ativo
                                </label>
                            </div>
                        </div>
                    </div>
                    <!--end::Card-->
                </div>
                <!--end::Col-->
                
                <!--begin::Col - Variáveis-->
                <div class="col-lg-4">
                    <!--begin::Card-->
                    <div class="card mb-5">
                        <div class="card-header border-0 pt-5">
                            <h3 class="card-title align-items-start flex-column">
                                <span class="card-label fw-bold fs-3 mb-1">Variáveis Disponíveis</span>
                                <span class="text-muted fw-semibold fs-7">Clique para inserir no editor</span>
                            </h3>
                        </div>
                        <div class="card-body pt-5">
                            <?php foreach ($variaveis_disponiveis as $categoria => $vars): ?>
                            <div class="mb-8">
                                <h4 class="text-gray-800 fw-bold fs-5 mb-3"><?= htmlspecialchars($categoria) ?></h4>
                                <div class="d-flex flex-column gap-2">
                                    <?php foreach ($vars as $variavel => $descricao): ?>
                                    <button type="button" class="btn btn-light btn-sm text-start btn-inserir-variavel" 
                                            data-variavel="<?= htmlspecialchars($variavel) ?>"
                                            title="<?= htmlspecialchars($descricao) ?>">
                                        <code class="text-primary"><?= htmlspecialchars($variavel) ?></code>
                                        <br>
                                        <small class="text-muted"><?= htmlspecialchars($descricao) ?></small>
                                    </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <!--end::Card-->
                </div>
                <!--end::Col-->
            </div>
            
            <!--begin::Actions-->
            <div class="card">
                <div class="card-footer d-flex justify-content-end py-6 px-9">
                    <a href="contrato_templates.php" class="btn btn-light btn-active-light-primary me-2">Cancelar</a>
                    <button type="submit" class="btn btn-primary">
                        <span class="indicator-label">Salvar Alterações</span>
                        <span class="indicator-progress">Salvando...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                </div>
            </div>
            <!--end::Actions-->
        </form>
        
    </div>
</div>
<!--end::Post-->

<!--begin::TinyMCE-->
<script src="../assets/plugins/custom/tinymce/tinymce.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    let editorInstance = null;
    
    // Inicializa TinyMCE
    function initTinyMCE() {
        if (typeof tinymce === 'undefined') {
            setTimeout(initTinyMCE, 200);
            return;
        }
        
        const editorId = 'conteudo_html';
        if (tinymce.get(editorId)) {
            tinymce.get(editorId).remove();
        }
        
        const baseUrl = '../assets/plugins/custom/tinymce';
        
        tinymce.init({
            selector: '#' + editorId,
            height: 600,
            menubar: true,
            base_url: baseUrl,
            suffix: '.min',
            license_key: 'gpl',
            plugins: [
                'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
                'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                'insertdatetime', 'media', 'table', 'code', 'help', 'wordcount'
            ],
            toolbar: 'undo redo | blocks | ' +
                'bold italic forecolor | alignleft aligncenter ' +
                'alignright alignjustify | bullist numlist outdent indent | ' +
                'removeformat | help | code',
            content_style: 'body { font-family: Arial, sans-serif; font-size: 14px; line-height: 1.6; }',
            language: 'pt_BR',
            promotion: false,
            branding: false,
            skin: 'oxide',
            content_css: baseUrl + '/skins/ui/oxide/content.min.css',
            setup: function(editor) {
                editorInstance = editor;
                editor.on('change', function() {
                    editor.save();
                });
            }
        });
    }
    
    setTimeout(initTinyMCE, 500);
    
    // Insere variável no editor
    document.querySelectorAll('.btn-inserir-variavel').forEach(btn => {
        btn.addEventListener('click', function() {
            const variavel = this.getAttribute('data-variavel');
            if (editorInstance) {
                editorInstance.insertContent(variavel);
                editorInstance.focus();
            } else if (typeof tinymce !== 'undefined') {
                const editor = tinymce.get('conteudo_html');
                if (editor) {
                    editor.insertContent(variavel);
                    editor.focus();
                }
            }
        });
    });
    
    // Submit com loading
    document.getElementById('form_template')?.addEventListener('submit', function(e) {
        if (typeof tinymce !== 'undefined' && tinymce.get('conteudo_html')) {
            tinymce.get('conteudo_html').save();
        }
        
        const submitBtn = this.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.setAttribute('data-kt-indicator', 'on');
            submitBtn.disabled = true;
        }
    });
});
</script>
<!--end::TinyMCE-->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

