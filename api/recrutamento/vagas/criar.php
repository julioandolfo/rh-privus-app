<?php
/**
 * API: Criar Vaga
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/permissions.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

if (!has_role(['ADMIN', 'RH'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissão']);
    exit;
}

try {
    $pdo = getDB();
    
    // Configura timeout para evitar locks muito longos
    $pdo->setAttribute(PDO::ATTR_TIMEOUT, 10);
    
    // Verifica se há transação aberta e fecha se necessário
    if ($pdo->inTransaction()) {
        error_log('Aviso: Transação já estava aberta, fazendo rollback');
        $pdo->rollBack();
    }
    
    $usuario = $_SESSION['usuario'];
    
    // Log para debug (remover em produção)
    error_log('Dados recebidos: ' . json_encode($_POST));
    
    // Validações detalhadas
    $empresa_id = (int)($_POST['empresa_id'] ?? 0);
    $titulo = trim($_POST['titulo'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    
    $erros_validacao = [];
    if (empty($empresa_id)) {
        $erros_validacao[] = 'Empresa é obrigatória';
    }
    if (empty($titulo)) {
        $erros_validacao[] = 'Título é obrigatório';
    }
    if (empty($descricao)) {
        $erros_validacao[] = 'Descrição é obrigatória';
    }
    
    if (!empty($erros_validacao)) {
        throw new Exception('Campos obrigatórios: ' . implode(', ', $erros_validacao));
    }
    
    // Verifica permissão de empresa
    if (!can_access_empresa($empresa_id)) {
        throw new Exception('Você não tem permissão para criar vagas nesta empresa');
    }
    
    // Processa benefícios
    $beneficios = [];
    if (!empty($_POST['beneficios']) && is_array($_POST['beneficios'])) {
        $beneficios = $_POST['beneficios'];
    }
    
    // Inicia transação
    $pdo->beginTransaction();
    
    try {
        // Insere vaga
    $stmt = $pdo->prepare("
        INSERT INTO vagas (
            empresa_id, setor_id, cargo_id, titulo, descricao,
            requisitos_obrigatorios, requisitos_desejaveis,
            competencias_tecnicas, competencias_comportamentais,
            tipo_contrato, modalidade, salario_min, salario_max,
            beneficios, localizacao, horario_trabalho, dias_trabalho,
            quantidade_vagas, publicar_portal, usar_landing_page_customizada,
            data_abertura, criado_por
        ) VALUES (
            ?, ?, ?, ?, ?,
            ?, ?,
            ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?,
            CURDATE(), ?
        )
    ");
    
    $stmt->execute([
        $empresa_id,
        !empty($_POST['setor_id']) ? (int)$_POST['setor_id'] : null,
        !empty($_POST['cargo_id']) ? (int)$_POST['cargo_id'] : null,
        $titulo,
        $descricao,
        $_POST['requisitos_obrigatorios'] ?? null,
        $_POST['requisitos_desejaveis'] ?? null,
        $_POST['competencias_tecnicas'] ?? null,
        $_POST['competencias_comportamentais'] ?? null,
        $_POST['tipo_contrato'] ?? 'CLT',
        $_POST['modalidade'] ?? 'Presencial',
        !empty($_POST['salario_min']) ? (float)$_POST['salario_min'] : null,
        !empty($_POST['salario_max']) ? (float)$_POST['salario_max'] : null,
        json_encode($beneficios),
        $_POST['localizacao'] ?? null,
        $_POST['horario_trabalho'] ?? null,
        $_POST['dias_trabalho'] ?? null,
        (int)($_POST['quantidade_vagas'] ?? 1),
        isset($_POST['publicar_portal']) ? (int)$_POST['publicar_portal'] : 1,
        isset($_POST['usar_landing_page_customizada']) ? (int)$_POST['usar_landing_page_customizada'] : 0,
        $usuario['id']
    ]);
    
        $vaga_id = $pdo->lastInsertId();
        
        // Se usar landing page customizada, cria estrutura básica
        if (!empty($_POST['usar_landing_page_customizada'])) {
            $stmt = $pdo->prepare("
                INSERT INTO vagas_landing_pages (vaga_id, ativo)
                VALUES (?, 1)
            ");
            $stmt->execute([$vaga_id]);
        }
        
        // Configura etapas da vaga (se informadas)
        if (!empty($_POST['etapas']) && is_array($_POST['etapas'])) {
            $ordem = 1;
            foreach ($_POST['etapas'] as $etapa_id) {
                $stmt = $pdo->prepare("
                    INSERT INTO vagas_etapas (vaga_id, etapa_id, ordem, obrigatoria)
                    VALUES (?, ?, ?, 1)
                ");
                $stmt->execute([$vaga_id, (int)$etapa_id, $ordem++]);
            }
        } else {
            // Usa etapas padrão
            $stmt = $pdo->query("SELECT id FROM processo_seletivo_etapas WHERE vaga_id IS NULL AND ativo = 1 ORDER BY ordem ASC");
            $etapas_padrao = $stmt->fetchAll();
            $ordem = 1;
            foreach ($etapas_padrao as $etapa) {
                $stmt = $pdo->prepare("
                    INSERT INTO vagas_etapas (vaga_id, etapa_id, ordem, obrigatoria)
                    VALUES (?, ?, ?, 1)
                ");
                $stmt->execute([$vaga_id, $etapa['id'], $ordem++]);
            }
        }
        
        // Confirma transação
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Vaga criada com sucesso',
            'vaga_id' => $vaga_id
        ]);
        
    } catch (Exception $e) {
        // Reverte transação em caso de erro
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    
} catch (PDOException $e) {
    // Garante que não há transação aberta
    try {
        $pdo = getDB();
        if ($pdo && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
    } catch (Exception $rollbackEx) {
        error_log('Erro ao fazer rollback: ' . $rollbackEx->getMessage());
    }
    
    http_response_code(400);
    error_log('Erro PDO ao criar vaga: ' . $e->getMessage());
    
    // Mensagem específica para lock timeout
    $mensagem = $e->getMessage();
    if (strpos($mensagem, 'Lock wait timeout') !== false || strpos($mensagem, '1205') !== false) {
        $mensagem = 'O banco de dados está ocupado. Por favor, aguarde alguns segundos e tente novamente.';
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao salvar no banco de dados: ' . $mensagem,
        'debug' => (defined('DEBUG') && DEBUG) ? [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'code' => $e->getCode(),
            'trace' => $e->getTraceAsString()
        ] : null
    ]);
} catch (Exception $e) {
    // Garante que não há transação aberta
    try {
        $pdo = getDB();
        if ($pdo && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
    } catch (Exception $rollbackEx) {
        error_log('Erro ao fazer rollback: ' . $rollbackEx->getMessage());
    }
    
    http_response_code(400);
    error_log('Erro ao criar vaga: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

