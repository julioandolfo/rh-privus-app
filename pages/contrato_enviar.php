<?php
/**
 * Enviar Contrato Rascunho para Assinatura
 */

require_once __DIR__ . '/../includes/functions.php';

// Função para log de contratos
function log_contrato($message) {
    $logFile = __DIR__ . '/../logs/contratos.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/contratos_functions.php';

require_page_permission('contrato_add.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];
$contrato_id = intval($_GET['id'] ?? 0);

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

if ($contrato_id <= 0) {
    redirect('contratos.php', 'Contrato não encontrado.', 'error');
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

// Busca contrato
$stmt = $pdo->prepare("
    SELECT c.*, 
           col.nome_completo as colaborador_nome,
           col.cpf as colaborador_cpf,
           col.email_pessoal as colaborador_email,
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

// Busca testemunhas já cadastradas para este contrato
$stmt = $pdo->prepare("
    SELECT * FROM contratos_signatarios 
    WHERE contrato_id = ? AND tipo = 'testemunha'
    ORDER BY ordem_assinatura ASC
");
$stmt->execute([$contrato_id]);
$testemunhas_salvas = $stmt->fetchAll();

// Busca representante já cadastrado para este contrato
$stmt = $pdo->prepare("
    SELECT * FROM contratos_signatarios 
    WHERE contrato_id = ? AND tipo = 'representante'
    ORDER BY id DESC LIMIT 1
");
$stmt->execute([$contrato_id]);
$representante_salvo = $stmt->fetch();

// Se não tem representante salvo, usa o padrão da configuração
if (!$representante_salvo && $autentique_config) {
    $representante_salvo = [
        'nome' => $autentique_config['representante_nome'] ?? '',
        'email' => $autentique_config['representante_email'] ?? '',
        'cpf' => $autentique_config['representante_cpf'] ?? '',
        'cargo' => $autentique_config['representante_cargo'] ?? '',
        'empresa_cnpj' => $autentique_config['empresa_cnpj'] ?? ''
    ];
}

// DEBUG MODE - Ativar para ver o que está acontecendo
$DEBUG_MODE = isset($_GET['debug']) && $_GET['debug'] === '1';

// Processa POST - Envio para Autentique
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Se modo debug, mostra informações na tela
    if ($DEBUG_MODE) {
        echo "<pre style='background:#222;color:#0f0;padding:20px;'>";
        echo "=== DEBUG CONTRATO ENVIAR ===\n\n";
        echo "Autentique Configurado: " . ($autentique_configurado ? 'SIM' : 'NAO') . "\n";
        echo "Contrato ID: $contrato_id\n";
        echo "POST recebido:\n";
        print_r($_POST);
        echo "\nColaborador:\n";
        print_r($colaborador);
        echo "</pre>";
    }
    
    if (!$autentique_configurado) {
        redirect('contrato_view.php?id=' . $contrato_id, 'Autentique não está configurado. Configure em Configurações > Integrações.', 'error');
    }
    
    $testemunhas = $_POST['testemunhas'] ?? [];
    $representante = $_POST['representante'] ?? [];
    $incluir_representante = isset($_POST['incluir_representante']) && $_POST['incluir_representante'] === '1';
    
    if ($DEBUG_MODE) {
        echo "<pre style='background:#222;color:#0f0;padding:20px;'>";
        echo "Testemunhas: " . count($testemunhas) . "\n";
        echo "Incluir Representante: " . ($incluir_representante ? 'SIM' : 'NAO') . "\n";
        echo "Representante Email: " . ($representante['email'] ?? 'VAZIO') . "\n";
        echo "</pre>";
    }
    
    try {
        $pdo->beginTransaction();
        
        $service = new AutentiqueService();
        
        // Converte PDF para base64
        $pdf_base64 = pdf_para_base64($contrato['pdf_path']);
        
        // Prepara signatários
        $signatarios = [];
        
        // Colaborador como primeiro signatário
        $email_colaborador = $colaborador['email_pessoal'] ?? '';
        if (empty($email_colaborador)) {
            throw new Exception('O colaborador não possui email cadastrado.');
        }
        
        $signatarios[] = [
            'email' => $email_colaborador,
            'x' => 100,
            'y' => 100
        ];
        
        // Representante da empresa (segundo signatário, se habilitado)
        if ($incluir_representante && !empty($representante['email'])) {
            $signatarios[] = [
                'email' => $representante['email'],
                'x' => 100,
                'y' => 250
            ];
        }
        
        // Testemunhas
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
        log_contrato("Enviando para Autentique - Signatários: " . count($signatarios));
        
        if ($DEBUG_MODE) {
            echo "<pre style='background:#222;color:#ff0;padding:20px;'>";
            echo "=== ENVIANDO PARA AUTENTIQUE ===\n";
            echo "Total Signatários: " . count($signatarios) . "\n";
            print_r($signatarios);
            echo "</pre>";
        }
        
        $resultado = $service->criarDocumento($contrato['titulo'], $pdf_base64, $signatarios);
        
        log_contrato("Resultado Autentique: " . json_encode($resultado, JSON_UNESCAPED_UNICODE));
        
        if ($DEBUG_MODE) {
            echo "<pre style='background:#222;color:#0ff;padding:20px;'>";
            echo "=== RESULTADO AUTENTIQUE ===\n";
            echo "Resultado: " . ($resultado ? 'OK' : 'FALHOU') . "\n";
            print_r($resultado);
            echo "</pre>";
        }
        
        if ($resultado) {
            log_contrato("API Autentique retornou sucesso");
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
            
            // Obtém signatures da resposta
            $signatures = $resultado['signatures'] ?? [];
            
            // Remove signatários existentes (se houver) e recria
            $stmt = $pdo->prepare("DELETE FROM contratos_signatarios WHERE contrato_id = ?");
            $stmt->execute([$contrato_id]);
            
            // Insere signatários - match por EMAIL (a API pode retornar o dono da conta como extra)
            $ordem = 0;
            
            // Log para debug
            log_contrato("Contrato ID: $contrato_id - Iniciando inserção de signatários");
            log_contrato("Colaborador: " . json_encode($colaborador, JSON_UNESCAPED_UNICODE));
            log_contrato("Signatures da API: " . json_encode($signatures, JSON_UNESCAPED_UNICODE));
            
            // Cria mapa de signatures por email para lookup correto
            $sig_map = [];
            foreach ($signatures as $sig) {
                $sig_email = strtolower($sig['email'] ?? '');
                if ($sig_email) {
                    $sig_map[$sig_email] = $sig;
                }
            }
            log_contrato("Mapa signatures por email: " . implode(', ', array_keys($sig_map)));
            
            // Colaborador (primeiro signatário)
            try {
                $colab_email = strtolower($email_colaborador);
                $signer = $sig_map[$colab_email] ?? null;
                $link_assinatura = $signer['link']['short_link'] ?? null;
                
                $stmt = $pdo->prepare("
                    INSERT INTO contratos_signatarios 
                    (contrato_id, tipo, nome, email, cpf, autentique_signer_id, ordem_assinatura, link_publico)
                    VALUES (?, 'colaborador', ?, ?, ?, ?, ?, ?)
                ");
                
                $dados_colaborador = [
                    $contrato_id,
                    $colaborador['nome_completo'] ?? 'Nome não informado',
                    $email_colaborador,
                    formatar_cpf($colaborador['cpf'] ?? ''),
                    $signer['public_id'] ?? null,
                    $ordem++,
                    $link_assinatura
                ];
                log_contrato("Inserindo colaborador: " . json_encode($dados_colaborador, JSON_UNESCAPED_UNICODE));
                
                $stmt->execute($dados_colaborador);
                log_contrato("Colaborador inserido com sucesso - signer_id=" . ($signer['public_id'] ?? 'null'));
            } catch (Exception $e) {
                log_contrato("ERRO ao inserir colaborador: " . $e->getMessage());
                throw $e;
            }
            
            // Representante da empresa (se habilitado)
            log_contrato("Incluir representante: " . ($incluir_representante ? 'SIM' : 'NAO'));
            log_contrato("Representante email: " . ($representante['email'] ?? 'VAZIO'));
            
            if ($incluir_representante && !empty($representante['email'])) {
                try {
                    $rep_email = strtolower($representante['email']);
                    $signer = $sig_map[$rep_email] ?? null;
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
                    log_contrato("Representante inserido com sucesso - signer_id=" . ($signer['public_id'] ?? 'null'));
                } catch (Exception $e) {
                    log_contrato("ERRO ao inserir representante: " . $e->getMessage());
                    throw $e;
                }
            }
            
            // Testemunhas
            foreach ($testemunhas as $index => $testemunha) {
                if (!empty($testemunha['email'])) {
                    $test_email = strtolower($testemunha['email']);
                    $signer = $sig_map[$test_email] ?? null;
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
                    log_contrato("Testemunha inserida: " . ($testemunha['email']) . " signer_id=" . ($signer['public_id'] ?? 'null'));
                }
            }
        } else {
            // API falhou - salva signatários básicos sem dados do Autentique
            log_contrato("API Autentique retornou falso/null - salvando signatários localmente");
            
            // Remove signatários existentes
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
                $email_colaborador,
                formatar_cpf($colaborador['cpf'] ?? ''),
                $ordem++
            ]);
            
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
            
            // Ainda atualiza o status para enviado
            $stmt = $pdo->prepare("UPDATE contratos SET status = 'enviado' WHERE id = ?");
            $stmt->execute([$contrato_id]);
        }
        
        log_contrato("Finalizando transação - commit");
        $pdo->commit();
        
        if ($DEBUG_MODE) {
            // Busca signatários inseridos para verificar
            $stmt = $pdo->prepare("SELECT * FROM contratos_signatarios WHERE contrato_id = ?");
            $stmt->execute([$contrato_id]);
            $signatarios_inseridos = $stmt->fetchAll();
            
            echo "<pre style='background:#222;color:#0f0;padding:20px;'>";
            echo "=== SIGNATÁRIOS INSERIDOS NO BANCO ===\n";
            echo "Total: " . count($signatarios_inseridos) . "\n\n";
            foreach ($signatarios_inseridos as $sig) {
                echo "- Tipo: {$sig['tipo']}, Nome: {$sig['nome']}, Email: {$sig['email']}\n";
            }
            echo "\n\n<a href='contrato_view.php?id=$contrato_id' style='color:#fff;'>Ver Contrato</a>";
            echo "</pre>";
            exit;
        }
        
        redirect('contrato_view.php?id=' . $contrato_id, 'Contrato enviado para assinatura com sucesso!', 'success');
        
    } catch (Exception $e) {
        $pdo->rollBack();
        
        if ($DEBUG_MODE) {
            echo "<pre style='background:#400;color:#fff;padding:20px;'>";
            echo "=== ERRO ===\n";
            echo "Mensagem: " . $e->getMessage() . "\n";
            echo "Arquivo: " . $e->getFile() . "\n";
            echo "Linha: " . $e->getLine() . "\n";
            echo "\nStack Trace:\n" . $e->getTraceAsString();
            echo "</pre>";
            exit;
        }
        
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
        
        <form method="POST" id="form_enviar">
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
                                        <?= htmlspecialchars($colaborador['email_pessoal'] ?? 'Sem email') ?>
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
                    
                    <!--begin::Card - Representante da Empresa-->
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
                                           <?= !empty($representante_salvo['email']) ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold" for="incluir_representante">
                                        Incluir
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="card-body pt-5" id="representante_form" style="<?= empty($representante_salvo['email']) ? 'display: none;' : '' ?>">
                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label class="form-label">Nome</label>
                                    <input type="text" name="representante[nome]" class="form-control form-control-solid" 
                                           value="<?= htmlspecialchars($representante_salvo['nome'] ?? '') ?>" 
                                           placeholder="Nome completo do representante" />
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Cargo</label>
                                    <input type="text" name="representante[cargo]" class="form-control form-control-solid" 
                                           value="<?= htmlspecialchars($representante_salvo['cargo'] ?? '') ?>" 
                                           placeholder="Ex: Sócio, Diretor" />
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label required">Email</label>
                                    <input type="email" name="representante[email]" id="representante_email" 
                                           class="form-control form-control-solid" 
                                           value="<?= htmlspecialchars($representante_salvo['email'] ?? '') ?>" 
                                           placeholder="email@empresa.com" />
                                    <div class="form-text">Email para receber o link de assinatura</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">CPF</label>
                                    <input type="text" name="representante[cpf]" class="form-control form-control-solid cpf-mask" 
                                           value="<?= htmlspecialchars($representante_salvo['cpf'] ?? '') ?>" 
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
                    <!--end::Card-->
                    
                    <!--begin::Card - Testemunhas-->
                    <div class="card mb-5">
                        <div class="card-header border-0 pt-5">
                            <h3 class="card-title align-items-start flex-column">
                                <span class="card-label fw-bold fs-3 mb-1">Testemunhas (opcional)</span>
                                <span class="text-muted fw-semibold fs-7">
                                    <?= count($testemunhas_salvas) > 0 ? count($testemunhas_salvas) . ' testemunha(s) cadastrada(s)' : 'Adicione testemunhas que também precisarão assinar' ?>
                                </span>
                            </h3>
                        </div>
                        <div class="card-body pt-5">
                            <div id="testemunhas_container">
                                <?php foreach ($testemunhas_salvas as $index => $testemunha): ?>
                                <div class="card mb-5 testemunha-item" data-index="<?= $index ?>">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h5 class="mb-0">Testemunha <?= $index + 1 ?></h5>
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
                                                <input type="text" name="testemunhas[<?= $index ?>][nome]" 
                                                       class="form-control form-control-solid" 
                                                       value="<?= htmlspecialchars($testemunha['nome'] ?? '') ?>" />
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label required">Email</label>
                                                <input type="email" name="testemunhas[<?= $index ?>][email]" 
                                                       class="form-control form-control-solid" 
                                                       value="<?= htmlspecialchars($testemunha['email'] ?? '') ?>" required />
                                            </div>
                                            <div class="col-md-12">
                                                <label class="form-label">CPF</label>
                                                <input type="text" name="testemunhas[<?= $index ?>][cpf]" 
                                                       class="form-control form-control-solid cpf-mask" 
                                                       value="<?= htmlspecialchars($testemunha['cpf'] ?? '') ?>"
                                                       placeholder="000.000.000-00" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
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
                </div>
                <!--end::Col-->
                
                <!--begin::Col - Preview-->
                <div class="col-lg-6">
                    <!--begin::Card - Resumo Signatários-->
                    <div class="card mb-5">
                        <div class="card-header border-0 pt-5">
                            <h3 class="card-title align-items-start flex-column">
                                <span class="card-label fw-bold fs-3 mb-1">Resumo dos Signatários</span>
                            </h3>
                        </div>
                        <div class="card-body pt-5">
                            <div class="d-flex flex-column gap-4" id="resumo_signatarios">
                                <!-- Colaborador -->
                                <div class="d-flex align-items-center">
                                    <div class="symbol symbol-40px me-4">
                                        <span class="symbol-label bg-light-primary">
                                            <i class="ki-duotone ki-user fs-2 text-primary">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                            </i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1">
                                        <span class="text-muted fs-8 d-block">Colaborador (Contratado)</span>
                                        <span class="fw-bold"><?= htmlspecialchars($contrato['colaborador_nome']) ?></span>
                                        <span class="text-muted fs-7 d-block"><?= htmlspecialchars($colaborador['email_pessoal'] ?? 'Sem email') ?></span>
                                    </div>
                                    <span class="badge badge-light-success">1º</span>
                                </div>
                                
                                <!-- Representante (se houver) -->
                                <div id="resumo_representante" style="<?= empty($representante_salvo['email']) ? 'display: none;' : '' ?>">
                                    <div class="d-flex align-items-center">
                                        <div class="symbol symbol-40px me-4">
                                            <span class="symbol-label bg-light-info">
                                                <i class="ki-duotone ki-briefcase fs-2 text-info">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                            </span>
                                        </div>
                                        <div class="flex-grow-1">
                                            <span class="text-muted fs-8 d-block">Representante da Empresa</span>
                                            <span class="fw-bold" id="resumo_representante_nome"><?= htmlspecialchars($representante_salvo['nome'] ?? 'Nome não informado') ?></span>
                                            <span class="text-muted fs-7 d-block" id="resumo_representante_email"><?= htmlspecialchars($representante_salvo['email'] ?? '') ?></span>
                                        </div>
                                        <span class="badge badge-light-info">2º</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!--end::Card-->
                    
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
        </form>
        
    </div>
</div>
<!--end::Post-->

<script>
// Toggle representante
document.getElementById('incluir_representante')?.addEventListener('change', function() {
    const form = document.getElementById('representante_form');
    const resumo = document.getElementById('resumo_representante');
    const emailInput = document.getElementById('representante_email');
    
    if (this.checked) {
        form.style.display = 'block';
        resumo.style.display = 'block';
        emailInput.setAttribute('required', 'required');
    } else {
        form.style.display = 'none';
        resumo.style.display = 'none';
        emailInput.removeAttribute('required');
    }
});

// Atualiza resumo do representante ao digitar
document.querySelector('[name="representante[nome]"]')?.addEventListener('input', function() {
    document.getElementById('resumo_representante_nome').textContent = this.value || 'Nome não informado';
});

document.querySelector('[name="representante[email]"]')?.addEventListener('input', function() {
    document.getElementById('resumo_representante_email').textContent = this.value;
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

// Inicia o índice com o número de testemunhas já existentes
let testemunhaIndex = <?= count($testemunhas_salvas) ?>;

// Adiciona eventos de remover nas testemunhas já existentes
document.querySelectorAll('.testemunha-item .btn-remover-testemunha').forEach(btn => {
    btn.addEventListener('click', function() {
        this.closest('.testemunha-item').remove();
        atualizarNumerosTestemunhas();
    });
});

// Atualiza os números das testemunhas
function atualizarNumerosTestemunhas() {
    const items = document.querySelectorAll('.testemunha-item');
    items.forEach((item, idx) => {
        const titulo = item.querySelector('h5');
        if (titulo) {
            titulo.textContent = 'Testemunha ' + (idx + 1);
        }
    });
}

// Adiciona testemunha
document.getElementById('btn_adicionar_testemunha')?.addEventListener('click', function() {
    const container = document.getElementById('testemunhas_container');
    const index = testemunhaIndex++;
    const numeroExibido = document.querySelectorAll('.testemunha-item').length + 1;
    
    const html = `
        <div class="card mb-5 testemunha-item" data-index="${index}">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Testemunha ${numeroExibido}</h5>
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
                        <input type="text" name="testemunhas[${index}][cpf]" class="form-control form-control-solid cpf-mask" 
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
        atualizarNumerosTestemunhas();
    });
    
    // Adiciona máscara de CPF
    container.querySelector(`.testemunha-item[data-index="${index}"] .cpf-mask`)?.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length <= 11) {
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
        }
        e.target.value = value;
    });
});

// Confirmação antes de enviar
document.getElementById('form_enviar')?.addEventListener('submit', function(e) {
    // Valida email do representante se estiver habilitado
    const incluirRepresentante = document.getElementById('incluir_representante')?.checked;
    const emailRepresentante = document.getElementById('representante_email')?.value;
    
    if (incluirRepresentante && !emailRepresentante) {
        e.preventDefault();
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Email obrigatório',
                text: 'Por favor, informe o email do representante da empresa.',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
        } else {
            alert('Por favor, informe o email do representante da empresa.');
        }
        return false;
    }
    
    if (typeof Swal !== 'undefined') {
        e.preventDefault();
        
        let signatarios = '<li><strong>Colaborador:</strong> <?= htmlspecialchars($contrato['colaborador_nome']) ?></li>';
        if (incluirRepresentante) {
            const nomeRep = document.querySelector('[name="representante[nome]"]')?.value || 'Representante';
            signatarios += `<li><strong>Representante:</strong> ${nomeRep}</li>`;
        }
        
        Swal.fire({
            title: 'Confirmar Envio',
            html: `<p>Você está prestes a enviar este contrato para assinatura digital.</p>
                   <p><strong>Signatários:</strong></p>
                   <ul class="text-start">${signatarios}</ul>
                   <p class="text-warning"><strong>Após o envio, o contrato não poderá ser editado.</strong></p>`,
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
