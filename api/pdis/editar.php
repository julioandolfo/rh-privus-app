<?php
/**
 * API para Editar PDI (Plano de Desenvolvimento Individual)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/engajamento.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Verifica se módulo está ativo
if (!engajamento_modulo_ativo('pdis')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Módulo de PDIs está desativado']);
    exit;
}

try {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    $pdi_id = (int)($_POST['pdi_id'] ?? 0);
    
    if ($pdi_id <= 0) {
        throw new Exception('ID do PDI inválido');
    }
    
    // Verifica se PDI existe e se usuário tem permissão para editar
    $stmt = $pdo->prepare("
        SELECT p.*, c.nome_completo as colaborador_nome
        FROM pdis p
        INNER JOIN colaboradores c ON p.colaborador_id = c.id
        WHERE p.id = ?
    ");
    $stmt->execute([$pdi_id]);
    $pdi_existente = $stmt->fetch();
    
    if (!$pdi_existente) {
        throw new Exception('PDI não encontrado');
    }
    
    // Verifica permissão
    $pode_editar = false;
    if ($usuario['id'] == $pdi_existente['criado_por'] ||
        $usuario['role'] === 'ADMIN' || 
        $usuario['role'] === 'RH') {
        $pode_editar = true;
    }
    
    if (!$pode_editar) {
        throw new Exception('Você não tem permissão para editar este PDI');
    }
    
    $colaborador_id = (int)($_POST['colaborador_id'] ?? 0);
    $titulo = trim($_POST['titulo'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $objetivo_geral = trim($_POST['objetivo_geral'] ?? '');
    $data_inicio = $_POST['data_inicio'] ?? date('Y-m-d');
    $data_fim_prevista = !empty($_POST['data_fim_prevista']) ? $_POST['data_fim_prevista'] : null;
    $status = $_POST['status'] ?? 'rascunho';
    $enviar_email = isset($_POST['enviar_email']) ? (int)$_POST['enviar_email'] : 1;
    $enviar_push = isset($_POST['enviar_push']) ? (int)$_POST['enviar_push'] : 1;
    
    if (empty($titulo)) {
        throw new Exception('Título é obrigatório');
    }
    
    if ($colaborador_id <= 0) {
        throw new Exception('Colaborador é obrigatório');
    }
    
    $pdo->beginTransaction();
    
    try {
        // Atualiza PDI
        $stmt = $pdo->prepare("
            UPDATE pdis SET
                colaborador_id = ?,
                titulo = ?,
                descricao = ?,
                objetivo_geral = ?,
                data_inicio = ?,
                data_fim_prevista = ?,
                status = ?,
                enviar_email = ?,
                enviar_push = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $colaborador_id, $titulo, $descricao, $objetivo_geral,
            $data_inicio, $data_fim_prevista, $status,
            $enviar_email, $enviar_push, $pdi_id
        ]);
        
        // Remove objetivos e ações antigos
        $stmt = $pdo->prepare("DELETE FROM pdi_objetivos WHERE pdi_id = ?");
        $stmt->execute([$pdi_id]);
        
        $stmt = $pdo->prepare("DELETE FROM pdi_acoes WHERE pdi_id = ?");
        $stmt->execute([$pdi_id]);
        
        // Adiciona novos objetivos
        $objetivos = $_POST['objetivos'] ?? [];
        if (is_string($objetivos)) {
            $objetivos = json_decode($objetivos, true);
        }
        
        if (is_array($objetivos) && !empty($objetivos)) {
            $stmt_obj = $pdo->prepare("
                INSERT INTO pdi_objetivos (pdi_id, objetivo, descricao, prazo, ordem)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            foreach ($objetivos as $index => $obj) {
                if (!empty($obj['objetivo'])) {
                    $stmt_obj->execute([
                        $pdi_id,
                        $obj['objetivo'],
                        $obj['descricao'] ?? null,
                        $obj['prazo'] ?? null,
                        $obj['ordem'] ?? $index
                    ]);
                }
            }
        }
        
        // Adiciona novas ações
        $acoes = $_POST['acoes'] ?? [];
        if (is_string($acoes)) {
            $acoes = json_decode($acoes, true);
        }
        
        if (is_array($acoes) && !empty($acoes)) {
            $stmt_acao = $pdo->prepare("
                INSERT INTO pdi_acoes (pdi_id, objetivo_id, acao, descricao, prazo, ordem)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($acoes as $index => $acao) {
                if (!empty($acao['acao'])) {
                    $stmt_acao->execute([
                        $pdi_id,
                        $acao['objetivo_id'] ?? null,
                        $acao['acao'],
                        $acao['descricao'] ?? null,
                        $acao['prazo'] ?? null,
                        $acao['ordem'] ?? $index
                    ]);
                }
            }
        }
        
        // Atualiza progresso
        calcular_progresso_pdi($pdi_id);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'PDI atualizado com sucesso!',
            'pdi_id' => $pdi_id
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

