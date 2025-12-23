<?php
/**
 * API: Buscar dados de candidatura/entrevista para cadastro de colaborador
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/permissions.php';

require_login();

if (!has_role(['ADMIN', 'RH', 'GESTOR'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissão']);
    exit;
}

try {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    $id = (int)($_GET['id'] ?? 0);
    $is_entrevista = !empty($_GET['is_entrevista']) && $_GET['is_entrevista'] === '1';
    
    if (!$id) {
        throw new Exception('ID não informado');
    }
    
    if ($is_entrevista) {
        // Busca dados da entrevista manual
        $stmt = $pdo->prepare("
            SELECT e.*, v.empresa_id, v.setor_id, v.cargo_id
            FROM entrevistas e
            LEFT JOIN vagas v ON e.vaga_id_manual = v.id
            WHERE e.id = ? AND e.candidatura_id IS NULL
        ");
        $stmt->execute([$id]);
        $entrevista = $stmt->fetch();
        
        if (!$entrevista) {
            throw new Exception('Entrevista não encontrada');
        }
        
        // Verifica permissão
        if ($usuario['role'] === 'RH' && $entrevista['empresa_id'] && !can_access_empresa($entrevista['empresa_id'])) {
            throw new Exception('Sem permissão');
        }
        
        $dados = [
            'nome_completo' => $entrevista['candidato_nome_manual'],
            'email' => $entrevista['candidato_email_manual'],
            'telefone' => $entrevista['candidato_telefone_manual'],
            'cpf' => null,
            'empresa_id' => $entrevista['empresa_id'],
            'setor_id' => $entrevista['setor_id'],
            'cargo_id' => $entrevista['cargo_id']
        ];
    } else {
        // Busca dados da candidatura
        $stmt = $pdo->prepare("
            SELECT c.*, cand.*, v.empresa_id, v.setor_id, v.cargo_id
            FROM candidaturas c
            INNER JOIN candidatos cand ON c.candidato_id = cand.id
            INNER JOIN vagas v ON c.vaga_id = v.id
            WHERE c.id = ?
        ");
        $stmt->execute([$id]);
        $candidatura = $stmt->fetch();
        
        if (!$candidatura) {
            throw new Exception('Candidatura não encontrada');
        }
        
        // Verifica permissão
        if ($usuario['role'] === 'RH' && !can_access_empresa($candidatura['empresa_id'])) {
            throw new Exception('Sem permissão');
        }
        
        $dados = [
            'nome_completo' => $candidatura['nome_completo'],
            'email' => $candidatura['email'],
            'telefone' => $candidatura['telefone'],
            'cpf' => $candidatura['cpf'],
            'empresa_id' => $candidatura['empresa_id'],
            'setor_id' => $candidatura['setor_id'],
            'cargo_id' => $candidatura['cargo_id']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'dados' => $dados
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

