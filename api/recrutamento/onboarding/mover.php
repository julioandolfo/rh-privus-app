<?php
/**
 * API: Mover Onboarding no Kanban
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
    $usuario = $_SESSION['usuario'];
    
    $onboarding_id = (int)($_POST['onboarding_id'] ?? 0);
    $coluna = trim($_POST['coluna'] ?? '');
    
    if (empty($onboarding_id) || empty($coluna)) {
        throw new Exception('Onboarding e coluna são obrigatórios');
    }
    
    // Valida coluna
    $colunas_validas = ['contratado', 'documentacao', 'treinamento', 'integracao', 'acompanhamento', 'concluido'];
    if (!in_array($coluna, $colunas_validas)) {
        throw new Exception('Coluna inválida');
    }
    
    // Busca onboarding
    $stmt = $pdo->prepare("SELECT * FROM onboarding WHERE id = ?");
    $stmt->execute([$onboarding_id]);
    $onboarding = $stmt->fetch();
    
    if (!$onboarding) {
        throw new Exception('Onboarding não encontrado');
    }
    
    // Atualiza status baseado na coluna
    $status_map = [
        'contratado' => 'contratado',
        'documentacao' => 'documentacao',
        'treinamento' => 'treinamento',
        'integracao' => 'integracao',
        'acompanhamento' => 'acompanhamento',
        'concluido' => 'concluido'
    ];
    
    $novo_status = $status_map[$coluna] ?? $onboarding['status'];
    
    // Atualiza
    $stmt = $pdo->prepare("
        UPDATE onboarding 
        SET coluna_kanban = ?, status = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$coluna, $novo_status, $onboarding_id]);
    
    // Se concluído, cria colaborador no sistema (se ainda não existe)
    if ($coluna === 'concluido' && !$onboarding['colaborador_id']) {
        $dados_candidato = null;
        
        // Verifica se é de candidatura ou entrevista manual
        if ($onboarding['candidatura_id']) {
            // Busca dados da candidatura
            $stmt = $pdo->prepare("
                SELECT c.*, cand.*, v.setor_id, v.cargo_id, v.empresa_id
                FROM candidaturas c
                INNER JOIN candidatos cand ON c.candidato_id = cand.id
                INNER JOIN vagas v ON c.vaga_id = v.id
                WHERE c.id = ?
            ");
            $stmt->execute([$onboarding['candidatura_id']]);
            $candidatura = $stmt->fetch();
            
            if ($candidatura) {
                $dados_candidato = [
                    'empresa_id' => $candidatura['empresa_id'],
                    'setor_id' => $candidatura['setor_id'],
                    'cargo_id' => $candidatura['cargo_id'],
                    'nome_completo' => $candidatura['nome_completo'],
                    'email' => $candidatura['email'],
                    'telefone' => $candidatura['telefone'],
                    'cpf' => $candidatura['cpf'] ?? null
                ];
            }
        } elseif ($onboarding['entrevista_id']) {
            // Busca dados da entrevista manual
            $stmt = $pdo->prepare("
                SELECT e.*, v.setor_id, v.cargo_id, v.empresa_id
                FROM entrevistas e
                LEFT JOIN vagas v ON e.vaga_id_manual = v.id
                WHERE e.id = ?
            ");
            $stmt->execute([$onboarding['entrevista_id']]);
            $entrevista = $stmt->fetch();
            
            if ($entrevista) {
                $dados_candidato = [
                    'empresa_id' => $entrevista['empresa_id'],
                    'setor_id' => $entrevista['setor_id'],
                    'cargo_id' => $entrevista['cargo_id'],
                    'nome_completo' => $entrevista['candidato_nome_manual'],
                    'email' => $entrevista['candidato_email_manual'],
                    'telefone' => $entrevista['candidato_telefone_manual'],
                    'cpf' => null
                ];
            }
        }
        
        if ($dados_candidato && $dados_candidato['email']) {
            // Cria colaborador
            $stmt = $pdo->prepare("
                INSERT INTO colaboradores 
                (empresa_id, setor_id, cargo_id, nome_completo, email, telefone, cpf, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'ativo')
            ");
            $stmt->execute([
                $dados_candidato['empresa_id'],
                $dados_candidato['setor_id'],
                $dados_candidato['cargo_id'],
                $dados_candidato['nome_completo'],
                $dados_candidato['email'],
                $dados_candidato['telefone'],
                $dados_candidato['cpf']
            ]);
            
            $colaborador_id = $pdo->lastInsertId();
            
            // Atualiza onboarding
            $stmt = $pdo->prepare("UPDATE onboarding SET colaborador_id = ? WHERE id = ?");
            $stmt->execute([$colaborador_id, $onboarding_id]);
            
            // Cria usuário no sistema
            $senha_hash = password_hash('senha123', PASSWORD_DEFAULT); // Senha padrão
            $stmt = $pdo->prepare("
                INSERT INTO usuarios 
                (nome, email, senha_hash, role, empresa_id, colaborador_id, status)
                VALUES (?, ?, ?, 'COLABORADOR', ?, ?, 'ativo')
            ");
            $stmt->execute([
                $dados_candidato['nome_completo'],
                $dados_candidato['email'],
                $senha_hash,
                $dados_candidato['empresa_id'],
                $colaborador_id
            ]);
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Onboarding movido com sucesso'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

