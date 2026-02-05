<?php
/**
 * Enviar Contrato Rascunho para Assinatura
 */

// Ativa exibição de erros temporariamente para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Handler para capturar erros fatais
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/contratos_functions.php';

require_page_permission('contrato_add.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];
$contrato_id = intval($_GET['id'] ?? 0);

if ($contrato_id <= 0) {
    redirect('contratos.php', 'Contrato não encontrado.', 'error');
}

// Verifica se Autentique está configurado
$autentique_configurado = false;
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'autentique_config'");
    if ($stmt->fetch()) {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM autentique_config WHERE ativo = 1");
        $result = $stmt->fetch();
        $autentique_configurado = ($result['total'] > 0);
    }
} catch (Exception $e) {
    error_log('Erro ao verificar Autentique: ' . $e->getMessage());
}

// Só carrega o serviço se estiver configurado
if ($autentique_configurado) {
    require_once __DIR__ . '/../includes/autentique_service.php';
}

// Busca contrato
$stmt = $pdo->prepare("
    SELECT c.*, 
           col.nome_completo as colaborador_nome,
           col.cpf as colaborador_cpf,
           col.email_pessoal as colaborador_email,
           col.email as colaborador_email_alt,
           col.empresa_id
    FROM contratos c
    INNER JOIN colaboradores col ON c.colaborador_id = col.id
    WHERE c.id = ?
");
$stmt->execute([$contrato_id]);
$contrato = $stmt->fetch();

if (!$contrato) {
    redirect('contratos.php', 'Contrato não encontrado.', 'error');
}

if ($contrato['status'] !== 'rascunho') {
    redirect('contrato_view.php?id=' . $contrato_id, 'Este contrato já foi enviado ou não está em rascunho.', 'error');
}

// Busca colaborador completo
$colaborador = buscar_dados_colaborador_completos($contrato['colaborador_id']);

// Processa POST - Envio para Autentique
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$autentique_configurado) {
        redirect('contrato_view.php?id=' . $contrato_id, 'Autentique não está configurado. Configure em Configurações > Integrações.', 'error');
    }
    
    $testemunhas = $_POST['testemunhas'] ?? [];
    
    try {
        $pdo->beginTransaction();
        
        $service = new AutentiqueService();
        
        // Converte PDF para base64
        $pdf_base64 = pdf_para_base64($contrato['pdf_path']);
        
        // Prepara signatários
        $signatarios = [];
        
        // Colaborador como primeiro signatário
        $email_colaborador = $colaborador['email_pessoal'] ?? $colaborador['email'] ?? '';
        if (empty($email_colaborador)) {
            throw new Exception('O colaborador não possui email cadastrado.');
        }
        
        $signatarios[] = [
            'email' => $email_colaborador,
            'x' => 100,
            'y' => 100
        ];
        
        // Testemunhas
        foreach ($testemunhas as $index => $testemunha) {
            if (!empty($testemunha['email'])) {
                $signatarios[] = [
                    'email' => $testemunha['email'],
                    'x' => 100,
                    'y' => ($index + 2) * 150 + 100
                ];
            }
        }
        
        // Cria documento no Autentique
        $resultado = $service->criarDocumento($contrato['titulo'], $pdf_base64, $signatarios);
        
        if ($resultado) {
            // Atualiza contrato com dados do Autentique
            $stmt = $pdo->prepare("
                UPDATE contratos 
                SET autentique_document_id = ?, autentique_token = ?, status = 'enviado'
                WHERE id = ?
            ");
            $stmt->execute([
                $resultado['id'],
                $resultado['token'],
                $contrato_id
            ]);
            
            // Insere signatários
            $ordem = 0;
            
            // Colaborador
            $stmt = $pdo->prepare("
                INSERT INTO contratos_signatarios 
                (contrato_id, tipo, nome, email, cpf, autentique_signer_id, ordem_assinatura)
                VALUES (?, 'colaborador', ?, ?, ?, ?, ?)
            ");
            $signer = $resultado['signers'][0] ?? null;
            $stmt->execute([
                $contrato_id,
                $colaborador['nome_completo'],
                $email_colaborador,
                formatar_cpf($colaborador['cpf'] ?? ''),
                $signer['id'] ?? null,
                $ordem++
            ]);
            
            // Testemunhas
            foreach ($testemunhas as $index => $testemunha) {
                if (!empty($testemunha['email'])) {
                    $signer = $resultado['signers'][$index + 1] ?? null;
                    
                    // Cria link público para testemunha
                    $link_publico = null;
                    $link_expiracao = null;
                    if ($signer && $signer['id']) {
                        try {
                            $link_result = $service->criarLinkPublico($resultado['id'], $signer['id']);
                            if ($link_result) {
                                $link_publico = $link_result['link'];
                                $link_expiracao = $link_result['expiresAt'] ?? date('Y-m-d H:i:s', strtotime('+30 days'));
                            }
                        } catch (Exception $e) {
                            error_log('Erro ao criar link público: ' . $e->getMessage());
                        }
                    }
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO contratos_signatarios 
                        (contrato_id, tipo, nome, email, cpf, autentique_signer_id, ordem_assinatura, link_publico, link_expiracao)
                        VALUES (?, 'testemunha', ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $contrato_id,
                        $testemunha['nome'] ?? '',
                        $testemunha['email'],
                        formatar_cpf($testemunha['cpf'] ?? ''),
                        $signer['id'] ?? null,
                        $ordem++,
                        $link_publico,
                        $link_expiracao
                    ]);
                }
            }
        }
        
        $pdo->commit();
        
        redirect('contrato_view.php?id=' . $contrato_id, 'Contrato enviado para assinatura com sucesso!', 'success');
        
    } catch (Exception $e) {
        $pdo->rollBack();
        redirect('contrato_enviar.php?id=' . $contrato_id, 'Erro ao enviar: ' . $e->getMessage(), 'error');
    }
}

$page_title = 'Enviar Contrato para Assinatura';
require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Enviar para Assinatura</h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-muted">
                    <a href="contratos.php" class="text-muted text-hover-primary">Contratos</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Enviar</li>
            </ul>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <?php if (!$autentique_configurado): ?>
        <!--begin::Alert - Autentique não configurado-->
        <div class="alert alert-danger d-flex align-items-center mb-5">
            <i class="ki-duotone ki-cross-circle fs-2hx text-danger me-4">
                <span class="path1"></span>
                <span class="path2"></span>
            </i>
            <div class="d-flex flex-column">
                <h4 class="mb-1 text-danger">Autentique não configurado</h4>
                <span>
                    Para enviar contratos para assinatura digital, é necessário configurar a integração com o Autentique.
                    Entre em contato com o administrador do sistema.
                </span>
            </div>
        </div>
        <!--end::Alert-->
        <?php endif; ?>
        
        <div class="row">
            <!--begin::Col - Formulário-->
            <div class="col-lg-6">
                <!--begin::Card - Informações do Contrato-->
                <div class="card mb-5">
                    <div class="card-header border-0 pt-5">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold fs-3 mb-1">Informações do Contrato</span>
                        </h3>
                    </div>
                    <div class="card-body pt-5">
                        <div class="d-flex flex-column gap-4">
                            <div>
                                <span class="text-muted fs-7">Título:</span>
                                <div class="fw-bold fs-5"><?= htmlspecialchars($contrato['titulo']) ?></div>
                            </div>
                            <div>
                                <span class="text-muted fs-7">Colaborador:</span>
                                <div class="fw-bold"><?= htmlspecialchars($contrato['colaborador_nome']) ?></div>
                                <div class="text-muted fs-7">
                                    <?= htmlspecialchars($colaborador['email_pessoal'] ?? $colaborador['email'] ?? 'Sem email') ?>
                                </div>
                            </div>
                            <div>
                                <span class="text-muted fs-7">Data de Criação:</span>
                                <div class="fw-bold"><?= formatar_data($contrato['data_criacao']) ?></div>
                            </div>
                            <?php if ($contrato['pdf_path']): ?>
                            <div>
                                <a href="../<?= htmlspecialchars($contrato['pdf_path']) ?>" target="_blank" class="btn btn-light-primary">
                                    <i class="ki-duotone ki-file-down fs-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    Baixar PDF para Revisão
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <!--end::Card-->
                
                <!--begin::Card - Testemunhas-->
                <form method="POST" id="form_enviar">
                    <div class="card mb-5">
                        <div class="card-header border-0 pt-5">
                            <h3 class="card-title align-items-start flex-column">
                                <span class="card-label fw-bold fs-3 mb-1">Testemunhas (opcional)</span>
                                <span class="text-muted fw-semibold fs-7">Adicione testemunhas que também precisarão assinar</span>
                            </h3>
                        </div>
                        <div class="card-body pt-5">
                            <div id="testemunhas_container">
                                <!-- Testemunhas serão adicionadas aqui via JavaScript -->
                            </div>
                            <button type="button" class="btn btn-light-primary" id="btn_adicionar_testemunha">
                                <i class="ki-duotone ki-plus fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                Adicionar Testemunha
                            </button>
                        </div>
                    </div>
                    
                    <!--begin::Alert-->
                    <div class="alert alert-warning d-flex align-items-center mb-5">
                        <i class="ki-duotone ki-information-5 fs-2hx text-warning me-4">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        <div class="d-flex flex-column">
                            <h4 class="mb-1 text-warning">Atenção</h4>
                            <span>
                                Após enviar para assinatura, o contrato não poderá ser editado.
                                Os signatários receberão um email com o link para assinar digitalmente.
                            </span>
                        </div>
                    </div>
                    <!--end::Alert-->
                    
                    <!--begin::Actions-->
                    <div class="d-flex justify-content-end gap-3">
                        <a href="contrato_view.php?id=<?= $contrato_id ?>" class="btn btn-light">Voltar</a>
                        <button type="submit" class="btn btn-success" <?= !$autentique_configurado ? 'disabled' : '' ?>>
                            <i class="ki-duotone ki-send fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Enviar para Assinatura
                        </button>
                    </div>
                    <!--end::Actions-->
                </form>
                <!--end::Card-->
            </div>
            <!--end::Col-->
            
            <!--begin::Col - Preview-->
            <div class="col-lg-6">
                <!--begin::Card - Preview do PDF-->
                <div class="card mb-5">
                    <div class="card-header border-0 pt-5">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold fs-3 mb-1">Preview do Contrato</span>
                        </h3>
                    </div>
                    <div class="card-body pt-5">
                        <?php if ($contrato['pdf_path']): ?>
                        <div class="ratio ratio-1x1" style="max-height: 600px;">
                            <iframe src="../<?= htmlspecialchars($contrato['pdf_path']) ?>" 
                                    style="border: 1px solid #e4e6ef; border-radius: 8px;"></iframe>
                        </div>
                        <?php else: ?>
                        <div class="border rounded p-5 bg-light" style="min-height: 400px;">
                            <?= $contrato['conteudo_final_html'] ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <!--end::Card-->
            </div>
            <!--end::Col-->
        </div>
        
    </div>
</div>
<!--end::Post-->

<script>
let testemunhaIndex = 0;

// Adiciona testemunha
document.getElementById('btn_adicionar_testemunha')?.addEventListener('click', function() {
    const container = document.getElementById('testemunhas_container');
    const index = testemunhaIndex++;
    
    const html = `
        <div class="card mb-5 testemunha-item" data-index="${index}">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Testemunha ${index + 1}</h5>
                    <button type="button" class="btn btn-sm btn-light-danger btn-remover-testemunha">
                        <i class="ki-duotone ki-trash fs-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                            <span class="path4"></span>
                            <span class="path5"></span>
                        </i>
                    </button>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nome</label>
                        <input type="text" name="testemunhas[${index}][nome]" class="form-control form-control-solid" />
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label required">Email</label>
                        <input type="email" name="testemunhas[${index}][email]" class="form-control form-control-solid" required />
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">CPF</label>
                        <input type="text" name="testemunhas[${index}][cpf]" class="form-control form-control-solid" 
                               placeholder="000.000.000-00" />
                    </div>
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', html);
    
    // Adiciona evento de remover
    container.querySelector(`.testemunha-item[data-index="${index}"] .btn-remover-testemunha`)?.addEventListener('click', function() {
        this.closest('.testemunha-item').remove();
    });
});

// Confirmação antes de enviar
document.getElementById('form_enviar')?.addEventListener('submit', function(e) {
    if (typeof Swal !== 'undefined') {
        e.preventDefault();
        
        Swal.fire({
            title: 'Confirmar Envio',
            html: `<p>Você está prestes a enviar este contrato para assinatura digital.</p>
                   <p><strong>Após o envio, o contrato não poderá ser editado.</strong></p>
                   <p>Os signatários receberão um email com o link para assinar.</p>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sim, Enviar',
            cancelButtonText: 'Cancelar',
            customClass: {
                confirmButton: 'btn btn-success',
                cancelButton: 'btn btn-light'
            },
            buttonsStyling: false
        }).then((result) => {
            if (result.isConfirmed) {
                this.submit();
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
