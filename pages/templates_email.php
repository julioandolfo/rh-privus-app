<?php
/**
 * Gerenciamento de Templates de Email - Metronic Theme
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('templates_email.php');

$pdo = getDB();

// Verifica e cria a tabela se não existir
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'email_templates'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("
            CREATE TABLE email_templates (
                id INT AUTO_INCREMENT PRIMARY KEY,
                codigo VARCHAR(50) UNIQUE NOT NULL COMMENT 'Código único do template',
                nome VARCHAR(255) NOT NULL,
                assunto VARCHAR(255) NOT NULL,
                corpo_html LONGTEXT NOT NULL,
                corpo_texto TEXT NULL,
                ativo TINYINT(1) NOT NULL DEFAULT 1,
                variaveis_disponiveis TEXT NULL,
                descricao TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_codigo (codigo),
                INDEX idx_ativo (ativo)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Insere templates padrão
        $templates_padrao = [
            ['novo_colaborador', 'Novo Colaborador', 'Bem-vindo ao {empresa_nome}!', '<h2>Olá {nome_completo}!</h2><p>Bem-vindo ao <strong>{empresa_nome}</strong>!</p><p>Estamos felizes em tê-lo(a) em nossa equipe.</p><p><strong>Dados do seu cadastro:</strong></p><ul><li><strong>Cargo:</strong> {cargo_nome}</li><li><strong>Setor:</strong> {setor_nome}</li><li><strong>Data de Início:</strong> {data_inicio}</li><li><strong>Tipo de Contrato:</strong> {tipo_contrato}</li></ul>{dados_acesso_html}<p>Bem-vindo(a)!</p>', 'Olá {nome_completo}!\n\nBem-vindo ao {empresa_nome}!\n\nDados: Cargo: {cargo_nome}, Setor: {setor_nome}, Data: {data_inicio}\n\n{dados_acesso_texto}', 'Enviado quando um novo colaborador é cadastrado.'],
            ['nova_promocao', 'Nova Promoção', 'Parabéns! Você recebeu uma promoção!', '<h2>Parabéns, {nome_completo}!</h2><p>Temos o prazer de informar que você recebeu uma promoção!</p><p><strong>Detalhes:</strong></p><ul><li><strong>Data:</strong> {data_promocao}</li><li><strong>Salário Anterior:</strong> R$ {salario_anterior}</li><li><strong>Novo Salário:</strong> R$ {salario_novo}</li><li><strong>Motivo:</strong> {motivo}</li></ul><p>Parabéns!</p>', 'Parabéns, {nome_completo}!\n\nVocê recebeu uma promoção!\nData: {data_promocao}\nNovo Salário: R$ {salario_novo}', 'Enviado quando uma promoção é registrada.'],
            ['fechamento_pagamento', 'Fechamento de Pagamento', 'Seu pagamento de {mes_referencia} está disponível', '<h2>Olá {nome_completo}!</h2><p>Informamos que o fechamento do pagamento referente ao mês de <strong>{mes_referencia}</strong> está disponível.</p><p><strong>Resumo:</strong></p><ul><li><strong>Salário Base:</strong> R$ {salario_base}</li><li><strong>Horas Extras:</strong> {horas_extras} horas - R$ {valor_horas_extras}</li><li><strong>Valor Total:</strong> R$ {valor_total}</li></ul>', 'Olá {nome_completo}!\n\nFechamento do mês {mes_referencia} disponível.\nValor Total: R$ {valor_total}', 'Enviado para cada colaborador quando um fechamento é realizado.'],
            ['ocorrencia', 'Ocorrência Registrada', 'Ocorrência registrada - {tipo_ocorrencia}', '<h2>Olá {nome_completo}!</h2><p>Informamos que foi registrada uma ocorrência em seu nome.</p><p><strong>Detalhes:</strong></p><ul><li><strong>Tipo:</strong> {tipo_ocorrencia}</li><li><strong>Data:</strong> {data_ocorrencia}</li><li><strong>Descrição:</strong> {descricao}</li></ul>', 'Olá {nome_completo}!\n\nOcorrência registrada:\nTipo: {tipo_ocorrencia}\nData: {data_ocorrencia}', 'Enviado quando uma ocorrência é registrada para um colaborador.']
        ];
        
        $stmt_ins = $pdo->prepare("INSERT INTO email_templates (codigo, nome, assunto, corpo_html, corpo_texto, descricao) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($templates_padrao as $tpl) {
            try {
                $stmt_ins->execute($tpl);
            } catch (PDOException $e) {
                // Ignora se já existir
            }
        }
    }
} catch (PDOException $e) {
    // Ignora erro se a tabela já existir
}

// Processa ações ANTES de incluir o header
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $codigo = sanitize($_POST['codigo'] ?? '');
        $nome = sanitize($_POST['nome'] ?? '');
        $assunto = sanitize($_POST['assunto'] ?? '');
        $corpo_html = $_POST['corpo_html'] ?? '';
        $corpo_texto = $_POST['corpo_texto'] ?? '';
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        $descricao = sanitize($_POST['descricao'] ?? '');
        
        if (empty($codigo) || empty($nome) || empty($assunto) || empty($corpo_html)) {
            redirect('templates_email.php', 'Preencha os campos obrigatórios!', 'error');
        }
        
        try {
            if ($id > 0) {
                // Atualiza
                $stmt = $pdo->prepare("
                    UPDATE email_templates 
                    SET nome = ?, assunto = ?, corpo_html = ?, corpo_texto = ?, ativo = ?, descricao = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nome, $assunto, $corpo_html, $corpo_texto, $ativo, $descricao, $id]);
            } else {
                // Insere
                $stmt = $pdo->prepare("
                    INSERT INTO email_templates (codigo, nome, assunto, corpo_html, corpo_texto, ativo, descricao)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$codigo, $nome, $assunto, $corpo_html, $corpo_texto, $ativo, $descricao]);
            }
            
            redirect('templates_email.php', 'Template salvo com sucesso!');
        } catch (PDOException $e) {
            redirect('templates_email.php', 'Erro ao salvar: ' . $e->getMessage(), 'error');
        }
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $ativo = (int)($_POST['ativo'] ?? 0);
        
        try {
            $stmt = $pdo->prepare("UPDATE email_templates SET ativo = ? WHERE id = ?");
            $stmt->execute([$ativo, $id]);
            echo json_encode(['success' => true]);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }
}

// Busca templates
$stmt = $pdo->query("SELECT * FROM email_templates ORDER BY nome");
$templates = $stmt->fetchAll();

// Agora inclui o header
$page_title = 'Templates de Email';
require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Templates de Email</h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Configurações</li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Templates de Email</li>
            </ul>
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
                <div class="card-title">
                    <h2 class="fw-bold">Templates de Email Configuráveis</h2>
                </div>
            </div>
            <!--end::Card header-->
            
            <!--begin::Card body-->
            <div class="card-body pt-0">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5" id="kt_templates_table">
                        <thead>
                            <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                                <th class="min-w-150px">Nome</th>
                                <th class="min-w-100px">Código</th>
                                <th class="min-w-200px">Assunto</th>
                                <th class="min-w-100px">Status</th>
                                <th class="text-end min-w-100px">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="fw-semibold text-gray-600">
                            <?php foreach ($templates as $template): ?>
                            <tr>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="text-gray-800 mb-1"><?= htmlspecialchars($template['nome']) ?></span>
                                        <?php if ($template['descricao']): ?>
                                        <small class="text-muted"><?= htmlspecialchars($template['descricao']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <code><?= htmlspecialchars($template['codigo']) ?></code>
                                </td>
                                <td><?= htmlspecialchars($template['assunto']) ?></td>
                                <td>
                                    <div class="form-check form-switch form-check-custom form-check-solid">
                                        <input class="form-check-input toggle-template" type="checkbox" 
                                               data-id="<?= $template['id'] ?>" 
                                               <?= $template['ativo'] ? 'checked' : '' ?> />
                                        <label class="form-check-label">
                                            <?= $template['ativo'] ? 'Ativo' : 'Inativo' ?>
                                        </label>
                                    </div>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-light btn-active-light-primary" 
                                            onclick="editarTemplate(<?= htmlspecialchars(json_encode($template)) ?>)">
                                        Editar
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <!--end::Card body-->
        </div>
        <!--end::Card-->
        
        <!--begin::Card - Variáveis Disponíveis-->
        <div class="card mt-5">
            <div class="card-header">
                <div class="card-title">
                    <h3 class="fw-bold">Variáveis Disponíveis</h3>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h5 class="mb-3">Novo Colaborador</h5>
                        <ul class="list-unstyled">
                            <li><code>{nome_completo}</code> - Nome completo do colaborador</li>
                            <li><code>{empresa_nome}</code> - Nome da empresa</li>
                            <li><code>{cargo_nome}</code> - Nome do cargo</li>
                            <li><code>{setor_nome}</code> - Nome do setor</li>
                            <li><code>{data_inicio}</code> - Data de início</li>
                            <li><code>{tipo_contrato}</code> - Tipo de contrato</li>
                            <li><code>{cpf}</code> - CPF formatado</li>
                            <li><code>{email_pessoal}</code> - Email pessoal</li>
                            <li><code>{telefone}</code> - Telefone</li>
                            <li><code>{usuario_login}</code> - Usuário para login (CPF ou email)</li>
                            <li><code>{senha}</code> - Senha de acesso (apenas se informada no cadastro)</li>
                            <li><code>{dados_acesso_html}</code> - Bloco HTML completo com dados de acesso (se senha informada)</li>
                            <li><code>{dados_acesso_texto}</code> - Versão texto dos dados de acesso (se senha informada)</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h5 class="mb-3">Nova Promoção</h5>
                        <ul class="list-unstyled">
                            <li><code>{nome_completo}</code> - Nome do colaborador</li>
                            <li><code>{data_promocao}</code> - Data da promoção</li>
                            <li><code>{salario_anterior}</code> - Salário anterior</li>
                            <li><code>{salario_novo}</code> - Novo salário</li>
                            <li><code>{motivo}</code> - Motivo da promoção</li>
                            <li><code>{observacoes}</code> - Observações</li>
                            <li><code>{empresa_nome}</code> - Nome da empresa</li>
                        </ul>
                    </div>
                    <div class="col-md-6 mt-4">
                        <h5 class="mb-3">Fechamento de Pagamento</h5>
                        <ul class="list-unstyled">
                            <li><code>{nome_completo}</code> - Nome do colaborador</li>
                            <li><code>{mes_referencia}</code> - Mês de referência</li>
                            <li><code>{salario_base}</code> - Salário base</li>
                            <li><code>{horas_extras}</code> - Quantidade de horas extras</li>
                            <li><code>{valor_horas_extras}</code> - Valor das horas extras</li>
                            <li><code>{descontos}</code> - Descontos</li>
                            <li><code>{adicionais}</code> - Adicionais</li>
                            <li><code>{valor_total}</code> - Valor total</li>
                            <li><code>{data_fechamento}</code> - Data do fechamento</li>
                            <li><code>{observacoes}</code> - Observações</li>
                        </ul>
                    </div>
                    <div class="col-md-6 mt-4">
                        <h5 class="mb-3">Ocorrência</h5>
                        <ul class="list-unstyled">
                            <li><code>{nome_completo}</code> - Nome do colaborador</li>
                            <li><code>{tipo_ocorrencia}</code> - Tipo da ocorrência</li>
                            <li><code>{data_ocorrencia}</code> - Data da ocorrência</li>
                            <li><code>{hora_ocorrencia}</code> - Hora da ocorrência</li>
                            <li><code>{tempo_atraso}</code> - Tempo de atraso</li>
                            <li><code>{descricao}</code> - Descrição</li>
                            <li><code>{usuario_registro}</code> - Usuário que registrou</li>
                            <li><code>{data_registro}</code> - Data/hora do registro</li>
                            <li><code>{empresa_nome}</code> - Nome da empresa</li>
                            <li><code>{setor_nome}</code> - Nome do setor</li>
                            <li><code>{cargo_nome}</code> - Nome do cargo</li>
                        </ul>
                    </div>
                    <div class="col-md-6 mt-4">
                        <h5 class="mb-3">Horas Extras</h5>
                        <ul class="list-unstyled">
                            <li><code>{nome_completo}</code> - Nome do colaborador</li>
                            <li><code>{data_trabalho}</code> - Data do trabalho</li>
                            <li><code>{quantidade_horas}</code> - Quantidade de horas</li>
                            <li><code>{tipo_pagamento_html}</code> - Tipo de pagamento (HTML)</li>
                            <li><code>{tipo_pagamento_texto}</code> - Tipo de pagamento (texto)</li>
                            <li><code>{valor_hora_html}</code> - Valor da hora (HTML)</li>
                            <li><code>{valor_hora_texto}</code> - Valor da hora (texto)</li>
                            <li><code>{percentual_adicional_html}</code> - Percentual adicional (HTML)</li>
                            <li><code>{percentual_adicional_texto}</code> - Percentual adicional (texto)</li>
                            <li><code>{valor_total_html}</code> - Valor total (HTML)</li>
                            <li><code>{valor_total_texto}</code> - Valor total (texto)</li>
                            <li><code>{saldo_banco_html}</code> - Saldo no banco (HTML)</li>
                            <li><code>{saldo_banco_texto}</code> - Saldo no banco (texto)</li>
                            <li><code>{observacoes_html}</code> - Observações (HTML)</li>
                            <li><code>{observacoes_texto}</code> - Observações (texto)</li>
                            <li><code>{usuario_registro}</code> - Usuário que registrou</li>
                            <li><code>{data_registro}</code> - Data/hora do registro</li>
                            <li><code>{empresa_nome}</code> - Nome da empresa</li>
                            <li><code>{setor_nome}</code> - Nome do setor</li>
                            <li><code>{cargo_nome}</code> - Nome do cargo</li>
                            <li><code>{ano_atual}</code> - Ano atual</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <!--end::Card-->
        
    </div>
</div>
<!--end::Post-->

<!--begin::Modal - Template-->
<div class="modal fade" id="kt_modal_template" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-900px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold" id="template_modal_title">Editar Template</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <form id="kt_template_form" method="POST">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" id="template_id">
                    
                    <div class="row mb-7">
                        <div class="col-md-6">
                            <label class="required fw-semibold fs-6 mb-2">Código</label>
                            <input type="text" name="codigo" id="template_codigo" class="form-control form-control-solid" required readonly />
                            <small class="text-muted">Código único do template (não pode ser alterado)</small>
                        </div>
                        <div class="col-md-6">
                            <label class="required fw-semibold fs-6 mb-2">Nome</label>
                            <input type="text" name="nome" id="template_nome" class="form-control form-control-solid" required />
                        </div>
                    </div>
                    
                    <div class="mb-7">
                        <label class="fw-semibold fs-6 mb-2">Descrição</label>
                        <textarea name="descricao" id="template_descricao" class="form-control form-control-solid" rows="2"></textarea>
                        <small class="text-muted">Descrição de quando este template é usado</small>
                    </div>
                    
                    <div class="mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Assunto do Email</label>
                        <input type="text" name="assunto" id="template_assunto" class="form-control form-control-solid" required />
                        <small class="text-muted">Use variáveis como {nome_completo}, {empresa_nome}, etc.</small>
                    </div>
                    
                    <div class="mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Corpo do Email (HTML)</label>
                        <textarea name="corpo_html" id="template_corpo_html" class="form-control form-control-solid" rows="15" required></textarea>
                        <small class="text-muted">Use HTML e variáveis entre chaves {variavel}</small>
                    </div>
                    
                    <div class="mb-7">
                        <label class="fw-semibold fs-6 mb-2">Corpo do Email (Texto)</label>
                        <textarea name="corpo_texto" id="template_corpo_texto" class="form-control form-control-solid" rows="10"></textarea>
                        <small class="text-muted">Versão texto alternativo (opcional)</small>
                    </div>
                    
                    <div class="mb-7">
                        <div class="form-check form-check-custom form-check-solid">
                            <input class="form-check-input" type="checkbox" name="ativo" id="template_ativo" value="1" checked />
                            <label class="form-check-label" for="template_ativo">
                                Template Ativo
                            </label>
                        </div>
                    </div>
                    
                    <div class="text-center pt-5">
                        <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <span class="indicator-label">Salvar</span>
                            <span class="indicator-progress">Salvando...
                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
let editorInstance = null;

// Inicializa TinyMCE
function initTinyMCE() {
    // Verifica se TinyMCE está disponível
    if (typeof tinymce === 'undefined') {
        console.warn('TinyMCE não está carregado. Tentando novamente...');
        setTimeout(initTinyMCE, 200);
        return;
    }
    
    // Remove editor existente se houver
    const editorId = 'template_corpo_html';
    if (tinymce.get(editorId)) {
        tinymce.get(editorId).remove();
    }
    
    // Configura base_url e suffix para usar os arquivos diretamente
    const baseUrl = '../assets/plugins/custom/tinymce';
    
    // Inicializa TinyMCE
    tinymce.init({
        selector: '#' + editorId,
        height: 500,
        menubar: true,
        base_url: baseUrl,
        suffix: '.min',
        license_key: 'gpl',
        plugins: [
            'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
            'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
            'insertdatetime', 'media', 'table', 'help', 'wordcount'
        ],
        toolbar: 'undo redo | blocks | ' +
            'bold italic forecolor | alignleft aligncenter ' +
            'alignright alignjustify | bullist numlist outdent indent | ' +
            'link image | removeformat | help | code',
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

function editarTemplate(template) {
    document.getElementById('template_modal_title').textContent = 'Editar Template: ' + template.nome;
    document.getElementById('template_id').value = template.id;
    document.getElementById('template_codigo').value = template.codigo;
    document.getElementById('template_nome').value = template.nome || '';
    document.getElementById('template_descricao').value = template.descricao || '';
    document.getElementById('template_assunto').value = template.assunto || '';
    document.getElementById('template_corpo_texto').value = template.corpo_texto || '';
    document.getElementById('template_ativo').checked = template.ativo == 1;
    
    const modal = new bootstrap.Modal(document.getElementById('kt_modal_template'));
    modal.show();
    
    // Aguarda o modal abrir completamente antes de inicializar TinyMCE
    const modalElement = document.getElementById('kt_modal_template');
    modalElement.addEventListener('shown.bs.modal', function() {
        // Remove listener após primeira execução
        modalElement.removeEventListener('shown.bs.modal', arguments.callee);
        
        // Define o conteúdo HTML antes de inicializar
        document.getElementById('template_corpo_html').value = template.corpo_html || '';
        
        // Inicializa TinyMCE após um pequeno delay para garantir que o textarea está visível
        setTimeout(function() {
            initTinyMCE();
        }, 300);
    }, { once: true });
}

// Toggle ativo/inativo
document.querySelectorAll('.toggle-template').forEach(toggle => {
    toggle.addEventListener('change', function() {
        const id = this.getAttribute('data-id');
        const ativo = this.checked ? 1 : 0;
        
        fetch('templates_email.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=toggle&id=' + id + '&ativo=' + ativo
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                this.checked = !this.checked;
                alert('Erro ao atualizar: ' + (data.message || 'Erro desconhecido'));
            } else {
                const label = this.nextElementSibling;
                label.textContent = ativo ? 'Ativo' : 'Inativo';
            }
        })
        .catch(() => {
            this.checked = !this.checked;
            alert('Erro ao atualizar template');
        });
    });
});

// Loading no formulário
document.getElementById('kt_template_form').addEventListener('submit', function(e) {
    // Salva conteúdo do TinyMCE antes de submeter
    if (editorInstance) {
        editorInstance.save();
    }
    
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.setAttribute('data-kt-indicator', 'on');
    submitBtn.disabled = true;
});

// Limpa TinyMCE quando o modal é fechado
document.getElementById('kt_modal_template').addEventListener('hidden.bs.modal', function() {
    if (editorInstance) {
        editorInstance.remove();
        editorInstance = null;
    }
});
</script>

<!--begin::TinyMCE-->
<script src="../assets/plugins/custom/tinymce/tinymce.bundle.js"></script>
<!--end::TinyMCE-->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

