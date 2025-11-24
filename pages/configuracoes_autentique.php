<?php
/**
 * Configurações do Autentique
 */

$page_title = 'Configurações Autentique';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_page_permission('configuracoes_autentique.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Busca configuração atual
$stmt = $pdo->query("SELECT * FROM autentique_config WHERE ativo = 1 ORDER BY id DESC LIMIT 1");
$config = $stmt->fetch();

// Processa POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $api_key = trim($_POST['api_key'] ?? '');
    $sandbox = isset($_POST['sandbox']) ? 1 : 0;
    
    // Webhook de Documento
    $webhook_documento_url = trim($_POST['webhook_documento_url'] ?? '');
    $webhook_documento_secret = trim($_POST['webhook_documento_secret'] ?? '');
    
    // Webhook de Assinatura
    $webhook_assinatura_url = trim($_POST['webhook_assinatura_url'] ?? '');
    $webhook_assinatura_secret = trim($_POST['webhook_assinatura_secret'] ?? '');
    
    // Mantém compatibilidade com campos antigos
    $webhook_url = trim($_POST['webhook_url'] ?? '');
    $webhook_secret = trim($_POST['webhook_secret'] ?? '');
    
    if (empty($api_key)) {
        redirect('configuracoes_autentique.php', 'API Key é obrigatória!', 'error');
    }
    
    try {
        // Desativa configurações antigas
        $pdo->exec("UPDATE autentique_config SET ativo = 0");
        
        // Se não tem webhooks novos, usa os antigos (compatibilidade)
        if (empty($webhook_documento_url) && !empty($webhook_url)) {
            $webhook_documento_url = $webhook_url;
            $webhook_documento_secret = $webhook_secret;
        }
        if (empty($webhook_assinatura_url) && !empty($webhook_url)) {
            $webhook_assinatura_url = $webhook_url;
            $webhook_assinatura_secret = $webhook_secret;
        }
        
        // Insere nova configuração
        $stmt = $pdo->prepare("
            INSERT INTO autentique_config (
                api_key, sandbox, 
                webhook_url, webhook_secret,
                webhook_documento_url, webhook_documento_secret,
                webhook_assinatura_url, webhook_assinatura_secret,
                ativo
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([
            $api_key, 
            $sandbox,
            $webhook_url, 
            $webhook_secret,
            $webhook_documento_url,
            $webhook_documento_secret,
            $webhook_assinatura_url,
            $webhook_assinatura_secret
        ]);
        
        redirect('configuracoes_autentique.php', 'Configurações salvas com sucesso!', 'success');
    } catch (PDOException $e) {
        redirect('configuracoes_autentique.php', 'Erro ao salvar configurações: ' . $e->getMessage(), 'error');
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Configurações Autentique</h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-muted">Configurações</li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Autentique</li>
            </ul>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <!--begin::Alert Info-->
        <div class="alert alert-info d-flex align-items-center p-5 mb-10">
            <i class="ki-duotone ki-information-5 fs-2hx text-info me-4">
                <span class="path1"></span>
                <span class="path2"></span>
                <span class="path3"></span>
            </i>
            <div class="d-flex flex-column">
                <h4 class="mb-1 text-info">Informação</h4>
                <span>
                    Configure suas credenciais da API Autentique. Você pode obter sua API Key em 
                    <a href="https://app.autentique.com.br/api-keys" target="_blank" class="fw-bold">app.autentique.com.br/api-keys</a>.
                    <br>
                    Use o modo <strong>Sandbox</strong> para testes sem consumir documentos reais.
                </span>
            </div>
        </div>
        <!--end::Alert Info-->
        
        <form method="POST" id="form_config">
            <!--begin::Card-->
            <div class="card mb-5">
                <div class="card-header border-0 pt-5">
                    <h3 class="card-title align-items-start flex-column">
                        <span class="card-label fw-bold fs-3 mb-1">Credenciais da API</span>
                        <span class="text-muted fw-semibold fs-7">Configure sua integração com o Autentique</span>
                    </h3>
                </div>
                <div class="card-body pt-5">
                    <div class="mb-10">
                        <label class="form-label required">API Key</label>
                        <input type="password" name="api_key" class="form-control form-control-solid" 
                               value="<?= htmlspecialchars($config['api_key'] ?? '') ?>" required />
                        <div class="form-text">
                            Sua chave de API do Autentique. Mantenha em segurança e não compartilhe.
                            <a href="https://app.autentique.com.br/api-keys" target="_blank" class="ms-1">Obter API Key</a>
                        </div>
                    </div>
                    
                    <div class="mb-10">
                        <label class="form-label">Ambiente</label>
                        <div class="form-check form-check-custom form-check-solid">
                            <input class="form-check-input" type="checkbox" name="sandbox" id="sandbox" 
                                   <?= ($config['sandbox'] ?? 1) ? 'checked' : '' ?> />
                            <label class="form-check-label" for="sandbox">
                                Modo Sandbox (para testes - não consome documentos reais)
                            </label>
                        </div>
                        <div class="form-text">
                            Desmarque apenas quando estiver pronto para produção.
                        </div>
                    </div>
                    
                    <!--begin::Alert sobre Webhooks-->
                    <div class="alert alert-warning d-flex align-items-center p-5 mb-10">
                        <i class="ki-duotone ki-information-5 fs-2hx text-warning me-4">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        <div class="d-flex flex-column">
                            <h4 class="mb-1 text-warning">Webhooks Separados</h4>
                            <span>
                                Configure 2 webhooks no Autentique: um para eventos de <strong>Documento</strong> e outro para eventos de <strong>Assinatura</strong>.
                                Cada webhook terá seu próprio secret gerado pelo Autentique.
                            </span>
                        </div>
                    </div>
                    <!--end::Alert-->
                    
                    <!--begin::Webhook de Documento-->
                    <div class="card card-flush mb-5">
                        <div class="card-header">
                            <h3 class="card-title">
                                <span class="card-label fw-bold fs-4">Webhook de Documento</span>
                                <span class="text-muted fs-7 ms-2">Eventos: document.created, document.updated, document.finished, document.cancelled</span>
                            </h3>
                        </div>
                        <div class="card-body pt-5">
                            <div class="mb-10">
                                <label class="form-label">URL do Webhook de Documento</label>
                                <input type="url" name="webhook_documento_url" class="form-control form-control-solid" 
                                       value="<?= htmlspecialchars($config['webhook_documento_url'] ?? $config['webhook_url'] ?? '') ?>" 
                                       placeholder="https://seudominio.com.br/api/contratos/webhook.php" />
                                <div class="form-text">
                                    Configure esta URL no primeiro webhook do Autentique (eventos de documento).
                                    <br>
                                    <strong>URL sugerida:</strong> <code><?= get_base_url() ?>/api/contratos/webhook.php</code>
                                </div>
                            </div>
                            
                            <div class="mb-10">
                                <label class="form-label">Secret do Webhook de Documento</label>
                                <input type="password" name="webhook_documento_secret" class="form-control form-control-solid" 
                                       value="<?= htmlspecialchars($config['webhook_documento_secret'] ?? $config['webhook_secret'] ?? '') ?>" 
                                       placeholder="Cole aqui o secret gerado pelo Autentique" />
                                <div class="form-text">
                                    Secret gerado pelo Autentique para este webhook. Copie do dashboard após criar o webhook.
                                </div>
                            </div>
                        </div>
                    </div>
                    <!--end::Webhook de Documento-->
                    
                    <!--begin::Webhook de Assinatura-->
                    <div class="card card-flush mb-5">
                        <div class="card-header">
                            <h3 class="card-title">
                                <span class="card-label fw-bold fs-4">Webhook de Assinatura</span>
                                <span class="text-muted fs-7 ms-2">Eventos: signer.signed, document.signed</span>
                            </h3>
                        </div>
                        <div class="card-body pt-5">
                            <div class="mb-10">
                                <label class="form-label">URL do Webhook de Assinatura</label>
                                <input type="url" name="webhook_assinatura_url" class="form-control form-control-solid" 
                                       value="<?= htmlspecialchars($config['webhook_assinatura_url'] ?? $config['webhook_url'] ?? '') ?>" 
                                       placeholder="https://seudominio.com.br/api/contratos/webhook.php" />
                                <div class="form-text">
                                    Configure esta URL no segundo webhook do Autentique (eventos de assinatura).
                                    <br>
                                    <strong>URL sugerida:</strong> <code><?= get_base_url() ?>/api/contratos/webhook.php</code>
                                    <br>
                                    <small class="text-muted">Pode ser a mesma URL, o sistema identifica pelo secret.</small>
                                </div>
                            </div>
                            
                            <div class="mb-10">
                                <label class="form-label">Secret do Webhook de Assinatura</label>
                                <input type="password" name="webhook_assinatura_secret" class="form-control form-control-solid" 
                                       value="<?= htmlspecialchars($config['webhook_assinatura_secret'] ?? '') ?>" 
                                       placeholder="Cole aqui o secret gerado pelo Autentique" />
                                <div class="form-text">
                                    Secret gerado pelo Autentique para este webhook. Copie do dashboard após criar o webhook.
                                </div>
                            </div>
                        </div>
                    </div>
                    <!--end::Webhook de Assinatura-->
                    
                    <!--begin::Campos Antigos (Compatibilidade - Ocultos)-->
                    <input type="hidden" name="webhook_url" value="<?= htmlspecialchars($config['webhook_url'] ?? '') ?>" />
                    <input type="hidden" name="webhook_secret" value="<?= htmlspecialchars($config['webhook_secret'] ?? '') ?>" />
                    <!--end::Campos Antigos-->
                </div>
            </div>
            <!--end::Card-->
            
            <!--begin::Card - Teste de Conexão-->
            <?php if ($config): ?>
            <div class="card mb-5">
                <div class="card-header border-0 pt-5">
                    <h3 class="card-title align-items-start flex-column">
                        <span class="card-label fw-bold fs-3 mb-1">Teste de Conexão</span>
                        <span class="text-muted fw-semibold fs-7">Verifique se a API está funcionando</span>
                    </h3>
                </div>
                <div class="card-body pt-5">
                    <button type="button" class="btn btn-light-primary" id="btn_testar_api">
                        <i class="ki-duotone ki-abstract-26 fs-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Testar Conexão com API
                    </button>
                    <div id="teste_resultado" class="mt-5" style="display: none;"></div>
                </div>
            </div>
            <!--end::Card-->
            <?php endif; ?>
            
            <!--begin::Actions-->
            <div class="card">
                <div class="card-footer d-flex justify-content-end py-6 px-9">
                    <button type="reset" class="btn btn-light btn-active-light-primary me-2">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <span class="indicator-label">Salvar Configurações</span>
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

<script>
// Submit com loading
document.getElementById('form_config')?.addEventListener('submit', function(e) {
    const submitBtn = this.querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.setAttribute('data-kt-indicator', 'on');
        submitBtn.disabled = true;
    }
});

// Teste de API
document.getElementById('btn_testar_api')?.addEventListener('click', function() {
    const btn = this;
    const resultado = document.getElementById('teste_resultado');
    
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Testando...';
    resultado.style.display = 'none';
    
    fetch('../api/contratos/testar_api.php')
        .then(response => {
            // Verifica se a resposta é realmente JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                return response.text().then(text => {
                    throw new Error('Resposta não é JSON. Resposta: ' + text.substring(0, 200));
                });
            }
            return response.json();
        })
        .then(data => {
            resultado.style.display = 'block';
            if (data.success) {
                resultado.innerHTML = `
                    <div class="alert alert-success">
                        <h4 class="alert-heading">Conexão OK!</h4>
                        <p class="mb-0">${data.message || 'API configurada corretamente.'}</p>
                    </div>
                `;
            } else {
                resultado.innerHTML = `
                    <div class="alert alert-danger">
                        <h4 class="alert-heading">Erro na Conexão</h4>
                        <p class="mb-0">${data.message || 'Erro ao conectar com a API.'}</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            resultado.style.display = 'block';
            console.error('Erro completo:', error);
            resultado.innerHTML = `
                <div class="alert alert-danger">
                    <h4 class="alert-heading">Erro ao Testar Conexão</h4>
                    <p class="mb-2"><strong>Erro:</strong> ${error.message}</p>
                    <p class="mb-0 text-muted fs-7">
                        Verifique se a API Key está configurada corretamente e se o servidor consegue acessar a API do Autentique.
                        <br>
                        Abra o console do navegador (F12) para mais detalhes.
                    </p>
                </div>
            `;
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = `
                <i class="ki-duotone ki-abstract-26 fs-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                Testar Conexão com API
            `;
        });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

