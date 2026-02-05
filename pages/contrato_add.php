<?php
/**
 * Criar Novo Contrato
 */

$page_title = 'Criar Contrato';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/contratos_functions.php';
require_once __DIR__ . '/../includes/select_colaborador.php';

require_page_permission('contrato_add.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];
$colaborador_id = intval($_GET['colaborador_id'] ?? 0);

// Verifica e atualiza o ENUM para incluir 'representante' se necessário
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM contratos_signatarios LIKE 'tipo'");
    $column = $stmt->fetch();
    if ($column && strpos($column['Type'], 'representante') === false) {
        $pdo->exec("ALTER TABLE contratos_signatarios MODIFY COLUMN tipo ENUM('colaborador', 'testemunha', 'rh', 'representante') NOT NULL");
    }
} catch (Exception $e) {
    error_log('Erro ao verificar/atualizar ENUM: ' . $e->getMessage());
}

// Verifica se Autentique está configurado e busca configuração
$autentique_configurado = false;
$autentique_config = null;
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'autentique_config'");
    if ($stmt->fetch()) {
        $stmt = $pdo->query("SELECT * FROM autentique_config WHERE ativo = 1 ORDER BY id DESC LIMIT 1");
        $autentique_config = $stmt->fetch();
        $autentique_configurado = ($autentique_config !== false);
    }
} catch (Exception $e) {
    error_log('Erro ao verificar Autentique: ' . $e->getMessage());
}

// Só carrega o serviço se estiver configurado
if ($autentique_configurado) {
    require_once __DIR__ . '/../includes/autentique_service.php';
}

// Busca templates ativos
$stmt = $pdo->query("SELECT id, nome FROM contratos_templates WHERE ativo = 1 ORDER BY nome");
$templates = $stmt->fetchAll();

// Busca colaboradores disponíveis
$colaboradores = get_colaboradores_disponiveis($pdo, $usuario);

// Se tem colaborador_id, busca dados
$colaborador = null;
if ($colaborador_id > 0) {
    $colaborador = buscar_dados_colaborador_completos($colaborador_id);
    if (!$colaborador) {
        redirect('colaboradores.php', 'Colaborador não encontrado!', 'error');
    }
}

// Processa POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $colaborador_id = intval($_POST['colaborador_id'] ?? 0);
    $template_id = intval($_POST['template_id'] ?? 0);
    $titulo = trim($_POST['titulo'] ?? '');
    $descricao_funcao = trim($_POST['descricao_funcao'] ?? '');
    $data_criacao = $_POST['data_criacao'] ?? date('Y-m-d');
    $data_vencimento = !empty($_POST['data_vencimento']) ? $_POST['data_vencimento'] : null;
    $observacoes = trim($_POST['observacoes'] ?? '');
    $acao = $_POST['acao'] ?? 'rascunho'; // rascunho ou enviar
    
    if (empty($colaborador_id) || empty($titulo)) {
        redirect('contrato_add.php', 'Preencha todos os campos obrigatórios!', 'error');
    }
    
    // Busca colaborador
    $colaborador = buscar_dados_colaborador_completos($colaborador_id);
    if (!$colaborador) {
        redirect('contrato_add.php', 'Colaborador não encontrado!', 'error');
    }
    
    // Busca template se informado
    $template_html = '';
    if ($template_id > 0) {
        $stmt = $pdo->prepare("SELECT conteudo_html FROM contratos_templates WHERE id = ?");
        $stmt->execute([$template_id]);
        $template = $stmt->fetch();
        if ($template) {
            $template_html = $template['conteudo_html'];
        }
    }
    
    // Se não tem template, usa conteúdo customizado
    if (empty($template_html)) {
        $template_html = $_POST['conteudo_customizado'] ?? '';
    }
    
    if (empty($template_html)) {
        redirect('contrato_add.php?colaborador_id=' . $colaborador_id, 'Selecione um template ou crie um conteúdo customizado!', 'error');
    }
    
    // Substitui variáveis
    $contrato_data = [
        'titulo' => $titulo,
        'descricao_funcao' => $descricao_funcao,
        'data_criacao' => $data_criacao,
        'data_vencimento' => $data_vencimento,
        'observacoes' => $observacoes
    ];
    
    // Campos manuais preenchidos pelo usuário
    $campos_manuais = $_POST['campos_manuais'] ?? [];
    
    // Se for enviar, valida campos obrigatórios
    if ($acao === 'enviar') {
        $campos_faltantes = verificar_campos_faltantes_contrato($template_html, $colaborador, $contrato_data, $campos_manuais);
        if (!empty($campos_faltantes)) {
            $nomes_campos = array_map(function($c) { return $c['label']; }, $campos_faltantes);
            redirect('contrato_add.php?colaborador_id=' . $colaborador_id, 
                'Campos obrigatórios faltando: ' . implode(', ', $nomes_campos), 'error');
        }
    }
    
    // Usa a função que aceita campos manuais
    $conteudo_final = substituir_variaveis_contrato_com_manuais($template_html, $colaborador, $contrato_data, $campos_manuais);
    
    try {
        $pdo->beginTransaction();
        
        // Gera PDF
        $pdf_path = gerar_pdf_contrato($conteudo_final, $titulo);
        
        // Insere contrato
        $stmt = $pdo->prepare("
            INSERT INTO contratos (
                colaborador_id, template_id, titulo, descricao_funcao,
                conteudo_final_html, pdf_path, status, criado_por_usuario_id,
                data_criacao, data_vencimento, observacoes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $colaborador_id,
            $template_id > 0 ? $template_id : null,
            $titulo,
            $descricao_funcao,
            $conteudo_final,
            $pdf_path,
            $acao === 'enviar' ? 'enviado' : 'rascunho',
            $usuario['id'],
            $data_criacao,
            $data_vencimento,
            $observacoes
        ]);
        
        $contrato_id = $pdo->lastInsertId();
        
        // Salva testemunhas (mesmo em rascunho)
        $testemunhas = $_POST['testemunhas'] ?? [];
        foreach ($testemunhas as $index => $testemunha) {
            if (!empty($testemunha['email'])) {
                $stmt = $pdo->prepare("
                    INSERT INTO contratos_signatarios 
                    (contrato_id, tipo, nome, email, cpf, ordem_assinatura)
                    VALUES (?, 'testemunha', ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $contrato_id,
                    $testemunha['nome'] ?? '',
                    $testemunha['email'],
                    formatar_cpf($testemunha['cpf'] ?? ''),
                    $index + 1
                ]);
            }
        }
        
        // Se for enviar, integra com Autentique
        if ($acao === 'enviar') {
            if (!$autentique_configurado) {
                $pdo->rollBack();
                redirect('contrato_add.php?colaborador_id=' . $colaborador_id, 
                    'Autentique não está configurado. Configure em Configurações > Integrações.', 'error');
            }
            
            try {
                $service = new AutentiqueService();
                
                // Converte PDF para base64
                $pdf_base64 = pdf_para_base64($pdf_path);
                
                // Prepara signatários
                $signatarios = [];
                
                // Colaborador como primeiro signatário
                $signatarios[] = [
                    'email' => $colaborador['email_pessoal'] ?? $colaborador['email'] ?? '',
                    'x' => 100,
                    'y' => 100
                ];
                
                // Representante da empresa (se habilitado)
                $representante = $_POST['representante'] ?? [];
                $incluir_representante = isset($_POST['incluir_representante']) && $_POST['incluir_representante'] === '1';
                
                if ($incluir_representante && !empty($representante['email'])) {
                    $signatarios[] = [
                        'email' => $representante['email'],
                        'x' => 100,
                        'y' => 250
                    ];
                }
                
                // Testemunhas (se houver)
                $testemunhas = $_POST['testemunhas'] ?? [];
                foreach ($testemunhas as $index => $testemunha) {
                    if (!empty($testemunha['email'])) {
                        $signatarios[] = [
                            'email' => $testemunha['email'],
                            'x' => 100,
                            'y' => (count($signatarios) + 1) * 150 + 100
                        ];
                    }
                }
                
                // Cria documento no Autentique
                $resultado = $service->criarDocumento($titulo, $pdf_base64, $signatarios);
                
                // Log para debug
                if (function_exists('log_contrato')) {
                    log_contrato("contrato_add.php - Resultado Autentique: " . json_encode($resultado));
                    log_contrato("contrato_add.php - Colaborador: " . json_encode($colaborador));
                    log_contrato("contrato_add.php - Representante: " . json_encode($representante));
                    log_contrato("contrato_add.php - Incluir Representante: " . ($incluir_representante ? 'SIM' : 'NAO'));
                }
                
                // Obtém signatures da resposta
                $signatures = $resultado['signatures'] ?? [];
                $tem_signatures = !empty($signatures);
                
                if ($resultado && $tem_signatures) {
                    // Atualiza contrato com dados do Autentique
                    $stmt = $pdo->prepare("
                        UPDATE contratos 
                        SET autentique_document_id = ?, status = 'enviado'
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $resultado['id'],
                        $contrato_id
                    ]);
                    
                    // Remove signatários existentes (testemunhas salvas como rascunho) e recria
                    $stmt = $pdo->prepare("DELETE FROM contratos_signatarios WHERE contrato_id = ?");
                    $stmt->execute([$contrato_id]);
                    
                    // Insere signatários
                    $ordem = 0;
                    $sigIndex = 0;
                    
                    // Colaborador (primeiro signatário)
                    $stmt = $pdo->prepare("
                        INSERT INTO contratos_signatarios 
                        (contrato_id, tipo, nome, email, cpf, autentique_signer_id, ordem_assinatura, link_publico)
                        VALUES (?, 'colaborador', ?, ?, ?, ?, ?, ?)
                    ");
                    $signer = $signatures[$sigIndex++] ?? null;
                    $link_assinatura = $signer['link']['short_link'] ?? null;
                    $stmt->execute([
                        $contrato_id,
                        $colaborador['nome_completo'],
                        $colaborador['email_pessoal'] ?? '',
                        formatar_cpf($colaborador['cpf'] ?? ''),
                        $signer['public_id'] ?? null,
                        $ordem++,
                        $link_assinatura
                    ]);
                    
                    if (function_exists('log_contrato')) {
                        log_contrato("contrato_add.php - Colaborador inserido: " . $colaborador['nome_completo']);
                    }
                    
                    // Representante da empresa (se habilitado)
                    if ($incluir_representante && !empty($representante['email'])) {
                        $signer = $signatures[$sigIndex++] ?? null;
                        $link_publico = $signer['link']['short_link'] ?? null;
                        
                        $stmt = $pdo->prepare("
                            INSERT INTO contratos_signatarios 
                            (contrato_id, tipo, nome, email, cpf, autentique_signer_id, ordem_assinatura, link_publico)
                            VALUES (?, 'representante', ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $contrato_id,
                            $representante['nome'] ?? '',
                            $representante['email'],
                            formatar_cpf($representante['cpf'] ?? ''),
                            $signer['public_id'] ?? null,
                            $ordem++,
                            $link_publico
                        ]);
                        
                        if (function_exists('log_contrato')) {
                            log_contrato("contrato_add.php - Representante inserido: " . ($representante['nome'] ?? 'sem nome'));
                        }
                    }
                    
                    // Testemunhas
                    foreach ($testemunhas as $index => $testemunha) {
                        if (!empty($testemunha['email'])) {
                            $signer = $signatures[$sigIndex++] ?? null;
                            $link_publico = $signer['link']['short_link'] ?? null;
                            
                            $stmt = $pdo->prepare("
                                INSERT INTO contratos_signatarios 
                                (contrato_id, tipo, nome, email, cpf, autentique_signer_id, ordem_assinatura, link_publico)
                                VALUES (?, 'testemunha', ?, ?, ?, ?, ?, ?)
                            ");
                            $stmt->execute([
                                $contrato_id,
                                $testemunha['nome'] ?? '',
                                $testemunha['email'],
                                formatar_cpf($testemunha['cpf'] ?? ''),
                                $signer['public_id'] ?? null,
                                $ordem++,
                                $link_publico
                            ]);
                        }
                    }
                } else {
                    // API retornou sem signatures ou falhou - salva signatários manualmente
                    if (function_exists('log_contrato')) {
                        log_contrato("contrato_add.php - Usando fallback para salvar signatários (signatures vazio ou resultado nulo)");
                    }
                    
                    // Se temos document_id, atualiza mesmo assim
                    if ($resultado && !empty($resultado['id'])) {
                        $stmt = $pdo->prepare("
                            UPDATE contratos 
                            SET autentique_document_id = ?, status = 'enviado'
                            WHERE id = ?
                        ");
                        $stmt->execute([$resultado['id'], $contrato_id]);
                    } else {
                        // Apenas atualiza status
                        $stmt = $pdo->prepare("UPDATE contratos SET status = 'enviado' WHERE id = ?");
                        $stmt->execute([$contrato_id]);
                    }
                    
                    // Remove signatários existentes e recria
                    $stmt = $pdo->prepare("DELETE FROM contratos_signatarios WHERE contrato_id = ?");
                    $stmt->execute([$contrato_id]);
                    
                    $ordem = 0;
                    
                    // Colaborador
                    $stmt = $pdo->prepare("
                        INSERT INTO contratos_signatarios 
                        (contrato_id, tipo, nome, email, cpf, ordem_assinatura)
                        VALUES (?, 'colaborador', ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $contrato_id,
                        $colaborador['nome_completo'] ?? 'Nome não informado',
                        $colaborador['email_pessoal'] ?? '',
                        formatar_cpf($colaborador['cpf'] ?? ''),
                        $ordem++
                    ]);
                    
                    if (function_exists('log_contrato')) {
                        log_contrato("contrato_add.php - Fallback: Colaborador inserido: " . ($colaborador['nome_completo'] ?? 'sem nome'));
                    }
                    
                    // Representante (se habilitado)
                    if ($incluir_representante && !empty($representante['email'])) {
                        $stmt = $pdo->prepare("
                            INSERT INTO contratos_signatarios 
                            (contrato_id, tipo, nome, email, cpf, ordem_assinatura)
                            VALUES (?, 'representante', ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $contrato_id,
                            $representante['nome'] ?? '',
                            $representante['email'],
                            formatar_cpf($representante['cpf'] ?? ''),
                            $ordem++
                        ]);
                        
                        if (function_exists('log_contrato')) {
                            log_contrato("contrato_add.php - Fallback: Representante inserido: " . ($representante['nome'] ?? 'sem nome'));
                        }
                    }
                    
                    // Testemunhas
                    foreach ($testemunhas as $testemunha) {
                        if (!empty($testemunha['email'])) {
                            $stmt = $pdo->prepare("
                                INSERT INTO contratos_signatarios 
                                (contrato_id, tipo, nome, email, cpf, ordem_assinatura)
                                VALUES (?, 'testemunha', ?, ?, ?, ?)
                            ");
                            $stmt->execute([
                                $contrato_id,
                                $testemunha['nome'] ?? '',
                                $testemunha['email'],
                                formatar_cpf($testemunha['cpf'] ?? ''),
                                $ordem++
                            ]);
                        }
                    }
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                redirect('contrato_add.php?colaborador_id=' . $colaborador_id, 
                    'Erro ao enviar para Autentique: ' . $e->getMessage(), 'error');
            }
        }
        
        $pdo->commit();
        
        redirect('contratos.php', 'Contrato criado com sucesso!', 'success');
    } catch (Exception $e) {
        $pdo->rollBack();
        redirect('contrato_add.php?colaborador_id=' . $colaborador_id, 
            'Erro ao criar contrato: ' . $e->getMessage(), 'error');
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Criar Contrato</h1>
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
                <li class="breadcrumb-item text-gray-900">Criar</li>
            </ul>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <form method="POST" id="form_contrato">
            <div class="row">
                <!--begin::Col - Formulário-->
                <div class="col-lg-6">
                    <!--begin::Card-->
                    <div class="card mb-5">
                        <div class="card-header border-0 pt-5">
                            <h3 class="card-title align-items-start flex-column">
                                <span class="card-label fw-bold fs-3 mb-1">Informações do Contrato</span>
                            </h3>
                        </div>
                        <div class="card-body pt-5">
                            <div class="mb-10">
                                <label class="required fw-semibold fs-6 mb-2">Selecione um colaborador</label>
                                <?= render_select_colaborador('colaborador_id', 'colaborador_id', $colaborador_id, $colaboradores, true) ?>
                            </div>
                            
                            <div class="mb-10">
                                <label class="form-label">Template (opcional)</label>
                                <select name="template_id" id="template_id" class="form-select form-select-solid">
                                    <option value="">Sem template (conteúdo customizado)</option>
                                    <?php foreach ($templates as $template): ?>
                                    <option value="<?= $template['id'] ?>"><?= htmlspecialchars($template['nome']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-10">
                                <label class="form-label required">Título do Contrato</label>
                                <input type="text" name="titulo" class="form-control form-control-solid" required />
                            </div>
                            
                            <div class="mb-10">
                                <label class="form-label required">Descrição da Função</label>
                                <textarea name="descricao_funcao" class="form-control form-control-solid" rows="3" required 
                                          placeholder="Descreva as funções e responsabilidades do colaborador neste contrato"></textarea>
                            </div>
                            
                            <div class="row mb-10">
                                <div class="col-md-6">
                                    <label class="form-label">Data de Criação</label>
                                    <input type="date" name="data_criacao" class="form-control form-control-solid" 
                                           value="<?= date('Y-m-d') ?>" />
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Data de Vencimento (opcional)</label>
                                    <input type="date" name="data_vencimento" class="form-control form-control-solid" />
                                </div>
                            </div>
                            
                            <div class="mb-10">
                                <label class="form-label">Observações</label>
                                <textarea name="observacoes" class="form-control form-control-solid" rows="2"></textarea>
                            </div>
                            
                            <div class="mb-10" id="conteudo_customizado_container" style="display: none;">
                                <label class="form-label">Conteúdo Customizado</label>
                                <textarea id="conteudo_customizado" name="conteudo_customizado" class="form-control" rows="10"></textarea>
                                <div class="form-text">Use variáveis como {{colaborador.nome_completo}}, {{colaborador.cpf}}, etc.</div>
                            </div>
                        </div>
                    </div>
                    <!--end::Card-->
                    
                    <!--begin::Card - Campos Faltantes-->
                    <div class="card mb-5" id="card_campos_faltantes" style="display: none;">
                        <div class="card-header border-0 pt-5">
                            <h3 class="card-title align-items-start flex-column">
                                <span class="card-label fw-bold fs-3 mb-1 text-warning">
                                    <i class="ki-duotone ki-information-5 text-warning fs-2 me-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                    Campos Faltantes
                                </span>
                                <span class="text-muted fw-semibold fs-7">Preencha os campos abaixo para poder enviar o contrato</span>
                            </h3>
                        </div>
                        <div class="card-body pt-5">
                            <div class="alert alert-warning d-flex align-items-center mb-5">
                                <i class="ki-duotone ki-shield-tick fs-2hx text-warning me-4">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <div class="d-flex flex-column">
                                    <span>Os dados abaixo estão faltando no cadastro. Preencha manualmente para este contrato ou atualize o cadastro do colaborador/empresa.</span>
                                </div>
                            </div>
                            <div id="campos_faltantes_container">
                                <!-- Campos faltantes serão adicionados aqui via JavaScript -->
                            </div>
                        </div>
                    </div>
                    <!--end::Card-->
                    
                    <!--begin::Card - Representante da Empresa-->
                    <?php if ($autentique_configurado): ?>
                    <div class="card mb-5">
                        <div class="card-header border-0 pt-5">
                            <h3 class="card-title align-items-start flex-column">
                                <span class="card-label fw-bold fs-3 mb-1">
                                    <i class="ki-duotone ki-user-tick fs-2 me-2 text-primary">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                        <span class="path3"></span>
                                    </i>
                                    Representante da Empresa
                                </span>
                                <span class="text-muted fw-semibold fs-7">Sócio/RH que assina pela empresa</span>
                            </h3>
                            <div class="card-toolbar">
                                <div class="form-check form-switch form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" name="incluir_representante" 
                                           id="incluir_representante" value="1" 
                                           <?= !empty($autentique_config['representante_email']) ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold" for="incluir_representante">
                                        Incluir
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="card-body pt-5" id="representante_form" style="<?= empty($autentique_config['representante_email']) ? 'display: none;' : '' ?>">
                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label class="form-label">Nome</label>
                                    <input type="text" name="representante[nome]" class="form-control form-control-solid" 
                                           value="<?= htmlspecialchars($autentique_config['representante_nome'] ?? '') ?>" 
                                           placeholder="Nome completo do representante" />
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Cargo</label>
                                    <input type="text" name="representante[cargo]" class="form-control form-control-solid" 
                                           value="<?= htmlspecialchars($autentique_config['representante_cargo'] ?? '') ?>" 
                                           placeholder="Ex: Sócio, Diretor" />
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label required">Email</label>
                                    <input type="email" name="representante[email]" id="representante_email" 
                                           class="form-control form-control-solid" 
                                           value="<?= htmlspecialchars($autentique_config['representante_email'] ?? '') ?>" 
                                           placeholder="email@empresa.com" />
                                    <div class="form-text">Email para receber o link de assinatura</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">CPF</label>
                                    <input type="text" name="representante[cpf]" class="form-control form-control-solid cpf-mask" 
                                           value="<?= htmlspecialchars($autentique_config['representante_cpf'] ?? '') ?>" 
                                           placeholder="000.000.000-00" />
                                </div>
                            </div>
                            <?php if (!empty($autentique_config['empresa_cnpj'])): ?>
                            <div class="alert alert-light-info border border-info border-dashed d-flex align-items-center p-4">
                                <i class="ki-duotone ki-shield-tick fs-2hx text-info me-3">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <div>
                                    <span class="fw-bold d-block">CNPJ da Empresa:</span>
                                    <span><?= htmlspecialchars($autentique_config['empresa_cnpj']) ?></span>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <!--end::Card-->
                    
                    <!--begin::Card - Testemunhas-->
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
                    <!--end::Card-->
                </div>
                <!--end::Col-->
                
                <!--begin::Col - Preview-->
                <div class="col-lg-6">
                    <!--begin::Card - Preview-->
                    <div class="card mb-5">
                        <div class="card-header border-0 pt-5">
                            <h3 class="card-title align-items-start flex-column">
                                <span class="card-label fw-bold fs-3 mb-1">Preview do Contrato</span>
                                <span class="text-muted fw-semibold fs-7">Visualização com dados do colaborador</span>
                            </h3>
                        </div>
                        <div class="card-body pt-5">
                            <div id="preview_contrato" class="border rounded p-5 bg-light" style="min-height: 400px;">
                                <p class="text-muted text-center py-10">
                                    Selecione um colaborador e template para ver o preview
                                </p>
                            </div>
                            <div class="mt-5">
                                <button type="button" class="btn btn-light-primary" id="btn_atualizar_preview">
                                    <i class="ki-duotone ki-arrows-circle fs-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    Atualizar Preview
                                </button>
                            </div>
                        </div>
                    </div>
                    <!--end::Card-->
                </div>
                <!--end::Col-->
            </div>
            
            <!--begin::Actions-->
            <div class="card">
                <div class="card-footer d-flex justify-content-between py-6 px-9">
                    <div id="status_envio" class="d-flex align-items-center" style="display: none !important;">
                        <!-- Status será atualizado via JavaScript -->
                    </div>
                    <div class="d-flex">
                        <a href="contratos.php" class="btn btn-light btn-active-light-primary me-2">Cancelar</a>
                        <button type="submit" name="acao" value="rascunho" class="btn btn-light-warning me-2">
                            Salvar como Rascunho
                        </button>
                        <button type="submit" name="acao" value="enviar" id="btn_enviar" class="btn btn-primary" disabled>
                            <span class="indicator-label">Enviar para Assinatura</span>
                            <span class="indicator-progress">Enviando...
                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                        </button>
                    </div>
                </div>
            </div>
            <!--end::Actions-->
        </form>
        
    </div>
</div>
<!--end::Post-->

<script>
let testemunhaIndex = 0;
let camposFaltantes = [];
let podeEnviar = false;

// Toggle representante
document.getElementById('incluir_representante')?.addEventListener('change', function() {
    const form = document.getElementById('representante_form');
    const emailInput = document.getElementById('representante_email');
    
    if (this.checked) {
        form.style.display = 'block';
    } else {
        form.style.display = 'none';
    }
});

// Máscara de CPF
document.querySelectorAll('.cpf-mask').forEach(input => {
    input.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length <= 11) {
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
        }
        e.target.value = value;
    });
});

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

// Atualiza preview
document.getElementById('btn_atualizar_preview')?.addEventListener('click', function() {
    atualizarPreview();
});

// Quando seleciona template ou colaborador, atualiza preview
document.getElementById('template_id')?.addEventListener('change', function() {
    const templateId = this.value;
    const colaboradorId = document.getElementById('colaborador_id').value;
    
    if (templateId && colaboradorId) {
        atualizarPreview();
    } else {
        document.getElementById('conteudo_customizado_container').style.display = templateId ? 'none' : 'block';
    }
});

// Função para buscar dados do colaborador e preencher descrição da função
function buscarDadosColaborador(colaboradorId) {
    if (!colaboradorId) return;
    
    // Faz uma requisição simples para buscar dados do colaborador
    fetch(`../api/contratos/preview.php?colaborador_id=${colaboradorId}&template_id=0`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            titulo: '',
            descricao_funcao: '',
            data_criacao: '',
            data_vencimento: '',
            observacoes: '',
            conteudo_customizado: '<p>placeholder</p>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.colaborador) {
            // Preenche descrição da função se estiver vazia
            const descricaoInput = document.querySelector('[name="descricao_funcao"]');
            if (descricaoInput && !descricaoInput.value.trim()) {
                const descricaoColaborador = data.colaborador.descricao_funcao || data.colaborador.cargo_nome || '';
                if (descricaoColaborador) {
                    descricaoInput.value = descricaoColaborador;
                }
            }
        }
    })
    .catch(error => {
        console.error('Erro ao buscar dados do colaborador:', error);
    });
}

// Listener para Select2 (quando usar Select2)
if (typeof jQuery !== 'undefined') {
    jQuery(document).on('select2:select', '#colaborador_id', function() {
        const colaboradorId = this.value;
        buscarDadosColaborador(colaboradorId);
        atualizarPreview();
    });
}

// Listener padrão também (fallback)
document.getElementById('colaborador_id')?.addEventListener('change', function() {
    buscarDadosColaborador(this.value);
    atualizarPreview();
});

// Coleta campos manuais preenchidos
function coletarCamposManuais() {
    const campos = {};
    document.querySelectorAll('[data-campo-manual]').forEach(input => {
        const variavel = input.getAttribute('data-campo-manual');
        if (input.value && input.value.trim() !== '') {
            campos[variavel] = input.value.trim();
        }
    });
    return campos;
}

// Atualiza interface de campos faltantes
function atualizarCamposFaltantes(campos) {
    camposFaltantes = campos || [];
    const container = document.getElementById('campos_faltantes_container');
    const card = document.getElementById('card_campos_faltantes');
    const btnEnviar = document.getElementById('btn_enviar');
    const statusEnvio = document.getElementById('status_envio');
    
    if (camposFaltantes.length === 0) {
        card.style.display = 'none';
        btnEnviar.disabled = false;
        btnEnviar.classList.remove('btn-secondary');
        btnEnviar.classList.add('btn-primary');
        podeEnviar = true;
        
        // Mostra status de OK
        statusEnvio.style.display = 'flex !important';
        statusEnvio.innerHTML = `
            <span class="badge badge-light-success fs-7">
                <i class="ki-duotone ki-check-circle fs-4 text-success me-1">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                Pronto para enviar
            </span>
        `;
    } else {
        card.style.display = 'block';
        btnEnviar.disabled = true;
        btnEnviar.classList.remove('btn-primary');
        btnEnviar.classList.add('btn-secondary');
        podeEnviar = false;
        
        // Mostra status de pendência
        statusEnvio.style.display = 'flex !important';
        statusEnvio.innerHTML = `
            <span class="badge badge-light-warning fs-7">
                <i class="ki-duotone ki-information-5 fs-4 text-warning me-1">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                </i>
                ${camposFaltantes.length} campo(s) faltante(s)
            </span>
        `;
        
        // Gera formulário de campos faltantes
        let html = '<div class="row">';
        camposFaltantes.forEach((campo, index) => {
            const colSize = campo.tipo === 'textarea' ? '12' : '6';
            const inputType = campo.tipo === 'number' ? 'text' : campo.tipo;
            const valorAtual = document.querySelector(`[data-campo-manual="${campo.variavel}"]`)?.value || '';
            
            html += `
                <div class="col-md-${colSize} mb-5">
                    <label class="form-label required">${campo.label}</label>
                    ${campo.tipo === 'textarea' ? 
                        `<textarea 
                            class="form-control form-control-solid campo-manual-input" 
                            data-campo-manual="${campo.variavel}"
                            rows="3"
                            placeholder="${campo.placeholder}"
                        >${valorAtual}</textarea>` :
                        `<input 
                            type="${inputType}" 
                            class="form-control form-control-solid campo-manual-input" 
                            data-campo-manual="${campo.variavel}"
                            placeholder="${campo.placeholder}"
                            value="${valorAtual}"
                        />`
                    }
                    <div class="form-text text-muted">Variável: <code>${campo.placeholder}</code></div>
                </div>
            `;
        });
        html += '</div>';
        
        html += `
            <div class="d-flex justify-content-end mt-5">
                <button type="button" class="btn btn-light-primary" id="btn_aplicar_campos">
                    <i class="ki-duotone ki-check fs-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Aplicar e Atualizar Preview
                </button>
            </div>
        `;
        
        container.innerHTML = html;
        
        // Adiciona evento ao botão de aplicar
        document.getElementById('btn_aplicar_campos')?.addEventListener('click', function() {
            atualizarPreview();
        });
        
        // Adiciona evento de Enter nos campos
        container.querySelectorAll('.campo-manual-input').forEach(input => {
            input.addEventListener('keypress', function(e) {
                if (e.key === 'Enter' && this.tagName !== 'TEXTAREA') {
                    e.preventDefault();
                    atualizarPreview();
                }
            });
        });
    }
}

function atualizarPreview() {
    const colaboradorId = document.getElementById('colaborador_id').value;
    const templateId = document.getElementById('template_id').value;
    const titulo = document.querySelector('[name="titulo"]').value;
    const descricaoFuncao = document.querySelector('[name="descricao_funcao"]').value;
    const dataCriacao = document.querySelector('[name="data_criacao"]').value;
    const dataVencimento = document.querySelector('[name="data_vencimento"]').value;
    const observacoes = document.querySelector('[name="observacoes"]').value;
    const conteudoCustomizado = document.getElementById('conteudo_customizado').value;
    
    if (!colaboradorId) {
        document.getElementById('preview_contrato').innerHTML = '<p class="text-muted text-center py-10">Selecione um colaborador</p>';
        atualizarCamposFaltantes([]);
        return;
    }
    
    if (!templateId && !conteudoCustomizado) {
        document.getElementById('preview_contrato').innerHTML = '<p class="text-muted text-center py-10">Selecione um template</p>';
        atualizarCamposFaltantes([]);
        return;
    }
    
    const preview = document.getElementById('preview_contrato');
    preview.innerHTML = '<div class="text-center py-10"><div class="spinner-border text-primary"></div><p class="mt-3">Carregando preview...</p></div>';
    
    // Coleta campos manuais
    const camposManuais = coletarCamposManuais();
    
    fetch(`../api/contratos/preview.php?colaborador_id=${colaboradorId}&template_id=${templateId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            titulo: titulo,
            descricao_funcao: descricaoFuncao,
            data_criacao: dataCriacao,
            data_vencimento: dataVencimento,
            observacoes: observacoes,
            conteudo_customizado: conteudoCustomizado,
            campos_manuais: camposManuais
        })
    })
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
        if (data.success) {
            preview.innerHTML = data.html;
            
            // Atualiza campos faltantes
            atualizarCamposFaltantes(data.campos_faltantes || []);
            podeEnviar = data.pode_enviar || false;
            
            // Preenche descrição da função do colaborador se o campo estiver vazio
            const descricaoInput = document.querySelector('[name="descricao_funcao"]');
            if (descricaoInput && !descricaoInput.value.trim() && data.colaborador) {
                // Prioriza descricao_funcao, depois cargo_nome
                const descricaoColaborador = data.colaborador.descricao_funcao || data.colaborador.cargo_nome || '';
                if (descricaoColaborador) {
                    descricaoInput.value = descricaoColaborador;
                }
            }
        } else {
            preview.innerHTML = `<div class="alert alert-danger">
                <strong>Erro ao gerar preview:</strong><br>
                ${data.message || 'Erro desconhecido'}
                ${data.error ? '<br><small>' + data.error + '</small>' : ''}
            </div>`;
            atualizarCamposFaltantes([]);
        }
    })
    .catch(error => {
        console.error('Erro ao gerar preview:', error);
        preview.innerHTML = `<div class="alert alert-danger">
            <strong>Erro ao gerar preview:</strong><br>
            ${error.message || 'Erro desconhecido'}
        </div>`;
        atualizarCamposFaltantes([]);
    });
}

function carregarTemplate(templateId, colaboradorId) {
    fetch(`../api/contratos/carregar_template.php?template_id=${templateId}&colaborador_id=${colaboradorId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.preview) {
                document.getElementById('preview_contrato').innerHTML = data.preview;
            }
        });
}

// Submit com validação e loading
document.getElementById('form_contrato')?.addEventListener('submit', function(e) {
    const acao = e.submitter?.value || 'rascunho';
    
    // Se for enviar e há campos faltantes, bloqueia
    if (acao === 'enviar' && !podeEnviar) {
        e.preventDefault();
        
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Campos Faltantes',
                html: `<p>Existem <strong>${camposFaltantes.length}</strong> campo(s) obrigatório(s) não preenchido(s).</p>
                       <p>Preencha os campos faltantes na seção "Campos Faltantes" antes de enviar.</p>`,
                icon: 'warning',
                confirmButtonText: 'Entendi',
                customClass: {
                    confirmButton: 'btn btn-primary'
                },
                buttonsStyling: false
            });
        } else {
            alert('Existem campos obrigatórios não preenchidos. Preencha os campos faltantes antes de enviar.');
        }
        
        // Scroll até a seção de campos faltantes
        document.getElementById('card_campos_faltantes')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return false;
    }
    
    // Se for enviar, adiciona campos manuais ao formulário
    if (acao === 'enviar') {
        const camposManuais = coletarCamposManuais();
        
        // Remove inputs anteriores
        document.querySelectorAll('input[name^="campos_manuais["]').forEach(el => el.remove());
        
        // Adiciona novos inputs hidden
        Object.keys(camposManuais).forEach(variavel => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = `campos_manuais[${variavel}]`;
            input.value = camposManuais[variavel];
            this.appendChild(input);
        });
    }
    
    // Loading no botão
    const submitBtn = this.querySelector('button[type="submit"][value="enviar"]');
    if (submitBtn && acao === 'enviar') {
        submitBtn.setAttribute('data-kt-indicator', 'on');
        submitBtn.disabled = true;
    }
});

</script>

<!--begin::Select2 CSS-->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<style>
    /* Ajusta a altura do Select2 */
    .select2-container .select2-selection--single {
        height: 44px !important;
        padding: 0.75rem 1rem !important;
        display: flex !important;
        align-items: center !important;
    }
    
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 44px !important;
        padding-left: 0 !important;
        display: flex !important;
        align-items: center !important;
    }
    
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 42px !important;
        top: 50% !important;
        transform: translateY(-50%) !important;
    }
    
    .select2-container .select2-selection--single .select2-selection__rendered img,
    .select2-container .select2-selection--single .select2-selection__rendered .symbol {
        margin-right: 8px !important;
    }
</style>
<!--end::Select2 CSS-->

<!--begin::Select Colaborador Script-->
<script src="../assets/js/select-colaborador.js"></script>
<!--end::Select Colaborador Script-->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

