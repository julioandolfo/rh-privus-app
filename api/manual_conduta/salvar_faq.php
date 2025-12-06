<?php
/**
 * API: Salvar FAQ
 */

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/manual_conduta_functions.php';

header('Content-Type: application/json');

// Verifica login
require_login();

// Verifica permissão
if (!has_role(['ADMIN'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissão']);
    exit;
}

// Verifica método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Lê dados JSON
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

$faq_id = $data['faq_id'] ?? null;
$pergunta = trim($data['pergunta'] ?? '');
$resposta = trim($data['resposta'] ?? '');
$categoria = !empty($data['categoria']) ? trim($data['categoria']) : null;
$ordem = isset($data['ordem']) ? (int)$data['ordem'] : 0;
$ativo = isset($data['ativo']) ? (int)$data['ativo'] : 1;

if (empty($pergunta) || empty($resposta)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Preencha pergunta e resposta']);
    exit;
}

$pdo = getDB();
$usuario = $_SESSION['usuario'];

try {
    $pdo->beginTransaction();
    
    if ($faq_id) {
        // Editar FAQ existente
        $stmt = $pdo->prepare("
            SELECT pergunta, resposta, categoria 
            FROM faq_manual_conduta 
            WHERE id = ?
        ");
        $stmt->execute([$faq_id]);
        $faq_anterior = $stmt->fetch();
        
        if (!$faq_anterior) {
            throw new Exception('FAQ não encontrada');
        }
        
        $stmt = $pdo->prepare("
            UPDATE faq_manual_conduta 
            SET pergunta = ?, resposta = ?, categoria = ?, ordem = ?, ativo = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $pergunta,
            $resposta,
            $categoria,
            $ordem,
            $ativo,
            $faq_id
        ]);
        
        // Registra histórico se houve alteração
        if ($faq_anterior['pergunta'] !== $pergunta || 
            $faq_anterior['resposta'] !== $resposta ||
            $faq_anterior['categoria'] !== $categoria) {
            registrar_historico_faq(
                $faq_id,
                $faq_anterior['pergunta'],
                $pergunta,
                $faq_anterior['resposta'],
                $resposta,
                'Alteração via interface'
            );
        }
    } else {
        // Criar nova FAQ
        $stmt = $pdo->prepare("
            INSERT INTO faq_manual_conduta 
            (pergunta, resposta, categoria, ordem, ativo, criado_por)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $pergunta,
            $resposta,
            $categoria,
            $ordem,
            $ativo,
            $usuario['id']
        ]);
        
        $faq_id = $pdo->lastInsertId();
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => $faq_id ? 'FAQ atualizada com sucesso' : 'FAQ criada com sucesso',
        'faq_id' => $faq_id
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar FAQ: ' . $e->getMessage()]);
}

