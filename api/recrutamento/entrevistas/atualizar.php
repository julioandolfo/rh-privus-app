<?php
/**
 * API para atualizar entrevista
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido');
    }
    
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    $entrevista_id = (int)($_POST['id'] ?? 0);
    
    if (empty($entrevista_id)) {
        throw new Exception('ID da entrevista é obrigatório');
    }
    
    // Busca entrevista existente
    $stmt = $pdo->prepare("
        SELECT e.*, 
               cand.vaga_id as candidatura_vaga_id,
               v.empresa_id,
               vm.empresa_id as vaga_manual_empresa_id
        FROM entrevistas e
        LEFT JOIN candidaturas cand ON e.candidatura_id = cand.id
        LEFT JOIN vagas v ON cand.vaga_id = v.id
        LEFT JOIN vagas vm ON e.vaga_id_manual = vm.id
        WHERE e.id = ?
    ");
    $stmt->execute([$entrevista_id]);
    $entrevista = $stmt->fetch();
    
    if (!$entrevista) {
        throw new Exception('Entrevista não encontrada');
    }
    
    // Verifica permissão
    $empresa_id = $entrevista['empresa_id'] ?? $entrevista['vaga_manual_empresa_id'];
    if ($usuario['role'] === 'RH') {
        if ($empresa_id && isset($usuario['empresas_ids']) && !in_array($empresa_id, $usuario['empresas_ids'])) {
            throw new Exception('Você não tem permissão para editar esta entrevista');
        }
    } elseif ($usuario['role'] === 'GESTOR') {
        if ($entrevista['entrevistador_id'] != $usuario['id']) {
            throw new Exception('Você só pode editar suas próprias entrevistas');
        }
    }
    
    // Dados a atualizar
    $titulo = trim($_POST['titulo'] ?? '');
    $tipo = $_POST['tipo'] ?? $entrevista['tipo'];
    $data_agendada = $_POST['data_agendada'] ?? '';
    $duracao_minutos = !empty($_POST['duracao_minutos']) ? (int)$_POST['duracao_minutos'] : $entrevista['duracao_minutos'];
    $localizacao = trim($_POST['localizacao'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $status = $_POST['status'] ?? $entrevista['status'];
    
    // Validações
    if (empty($titulo)) {
        throw new Exception('Título é obrigatório');
    }
    
    if (empty($data_agendada)) {
        throw new Exception('Data e hora são obrigatórias');
    }
    
    // Formata data
    $data_agendada_formatada = date('Y-m-d H:i:s', strtotime($data_agendada));
    
    // Verifica se é entrevista manual
    $is_manual = $entrevista['candidatura_id'] === null;
    
    if ($is_manual) {
        // Atualiza entrevista manual
        $candidato_nome = trim($_POST['candidato_nome'] ?? $entrevista['candidato_nome_manual']);
        $candidato_email = trim($_POST['candidato_email'] ?? $entrevista['candidato_email_manual']);
        $candidato_telefone = trim($_POST['candidato_telefone'] ?? $entrevista['candidato_telefone_manual']);
        $vaga_id_manual = !empty($_POST['vaga_id_manual']) ? (int)$_POST['vaga_id_manual'] : $entrevista['vaga_id_manual'];
        $coluna_kanban = trim($_POST['coluna_kanban'] ?? $entrevista['coluna_kanban']);
        
        if (empty($candidato_nome) || empty($candidato_email)) {
            throw new Exception('Nome e email do candidato são obrigatórios');
        }
        
        $stmt = $pdo->prepare("
            UPDATE entrevistas SET
                titulo = ?,
                tipo = ?,
                data_agendada = ?,
                duracao_minutos = ?,
                localizacao = ?,
                descricao = ?,
                status = ?,
                candidato_nome_manual = ?,
                candidato_email_manual = ?,
                candidato_telefone_manual = ?,
                vaga_id_manual = ?,
                coluna_kanban = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $titulo,
            $tipo,
            $data_agendada_formatada,
            $duracao_minutos,
            $localizacao ?: null,
            $descricao ?: null,
            $status,
            $candidato_nome,
            $candidato_email,
            $candidato_telefone ?: null,
            $vaga_id_manual ?: null,
            $coluna_kanban ?: 'entrevistas',
            $entrevista_id
        ]);
    } else {
        // Atualiza entrevista com candidatura
        $stmt = $pdo->prepare("
            UPDATE entrevistas SET
                titulo = ?,
                tipo = ?,
                data_agendada = ?,
                duracao_minutos = ?,
                localizacao = ?,
                descricao = ?,
                status = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $titulo,
            $tipo,
            $data_agendada_formatada,
            $duracao_minutos,
            $localizacao ?: null,
            $descricao ?: null,
            $status,
            $entrevista_id
        ]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Entrevista atualizada com sucesso'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

