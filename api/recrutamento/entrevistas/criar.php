<?php
/**
 * API: Criar Entrevista
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

if (!has_role(['ADMIN', 'RH', 'GESTOR'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissão']);
    exit;
}

try {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    $candidatura_id = !empty($_POST['candidatura_id']) ? (int)$_POST['candidatura_id'] : null;
    $titulo = trim($_POST['titulo'] ?? '');
    $tipo = $_POST['tipo'] ?? 'presencial';
    $data_agendada = $_POST['data_agendada'] ?? '';
    
    // Validações básicas
    if (empty($titulo) || empty($data_agendada)) {
        throw new Exception('Título e data são obrigatórios');
    }
    
    $empresa_id = null;
    $vaga_id_manual = null;
    $coluna_kanban = $_POST['coluna_kanban'] ?? null;
    
    // Se tem candidatura, valida normalmente
    if ($candidatura_id) {
        $stmt = $pdo->prepare("
            SELECT c.*, v.empresa_id
            FROM candidaturas c
            INNER JOIN vagas v ON c.vaga_id = v.id
            WHERE c.id = ?
        ");
        $stmt->execute([$candidatura_id]);
        $candidatura = $stmt->fetch();
        
        if (!$candidatura) {
            throw new Exception('Candidatura não encontrada');
        }
        
        if ($usuario['role'] === 'RH' && !can_access_empresa($candidatura['empresa_id'])) {
            throw new Exception('Sem permissão');
        }
        
        $empresa_id = $candidatura['empresa_id'];
    } else {
        // Entrevista manual - valida campos obrigatórios
        $candidato_nome = trim($_POST['candidato_nome'] ?? '');
        $candidato_email = trim($_POST['candidato_email'] ?? '');
        $vaga_id_manual = !empty($_POST['vaga_id']) ? (int)$_POST['vaga_id'] : null;
        
        if (empty($candidato_nome) || empty($candidato_email)) {
            throw new Exception('Nome e email do candidato são obrigatórios para entrevistas manuais');
        }
        
        if ($vaga_id_manual) {
            // Valida vaga e permissão
            $stmt = $pdo->prepare("SELECT empresa_id FROM vagas WHERE id = ?");
            $stmt->execute([$vaga_id_manual]);
            $vaga = $stmt->fetch();
            
            if (!$vaga) {
                throw new Exception('Vaga não encontrada');
            }
            
            if ($usuario['role'] === 'RH' && !can_access_empresa($vaga['empresa_id'])) {
                throw new Exception('Sem permissão');
            }
            
            $empresa_id = $vaga['empresa_id'];
            
            // Define coluna padrão se não informada
            if (empty($coluna_kanban)) {
                $coluna_kanban = 'entrevista';
            }
        }
    }
    
    // Converte data
    $data_agendada_formatada = date('Y-m-d H:i:s', strtotime($data_agendada));
    
    // Determina link_videoconferencia e localizacao
    $localizacao_input = $_POST['localizacao'] ?? '';
    $link_videoconferencia = null;
    $localizacao = null;
    
    if (!empty($localizacao_input)) {
        if (strpos($localizacao_input, 'http') === 0) {
            $link_videoconferencia = $localizacao_input;
        } else {
            $localizacao = $localizacao_input;
        }
    }
    
    // Cria entrevista
    if ($candidatura_id) {
        // Entrevista com candidatura
        $stmt = $pdo->prepare("
            INSERT INTO entrevistas 
            (candidatura_id, tipo, titulo, descricao, entrevistador_id, data_agendada, duracao_minutos, link_videoconferencia, localizacao, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'agendada')
        ");
        $stmt->execute([
            $candidatura_id,
            $tipo,
            $titulo,
            $_POST['descricao'] ?? null,
            $usuario['id'],
            $data_agendada_formatada,
            !empty($_POST['duracao_minutos']) ? (int)$_POST['duracao_minutos'] : 60,
            $link_videoconferencia,
            $localizacao
        ]);
        
        $entrevista_id = $pdo->lastInsertId();
        
        // Envia notificação ao candidato
        require_once __DIR__ . '/../../../includes/recrutamento_functions.php';
        $candidatura_completa = buscar_candidaturas_kanban(['vaga_id' => $candidatura['vaga_id']]);
        $candidatura_completa = array_filter($candidatura_completa, function($c) use ($candidatura_id) {
            return $c['id'] == $candidatura_id;
        });
        if (!empty($candidatura_completa)) {
            $cand = reset($candidatura_completa);
            enviar_email_candidato($cand, [
                'template' => 'entrevista_agendada',
                'assunto' => 'Entrevista Agendada - ' . $titulo
            ]);
        }
    } else {
        // Entrevista manual sem candidatura
        $stmt = $pdo->prepare("
            INSERT INTO entrevistas 
            (candidatura_id, candidato_nome_manual, candidato_email_manual, candidato_telefone_manual, 
             vaga_id_manual, coluna_kanban, tipo, titulo, descricao, entrevistador_id, 
             data_agendada, duracao_minutos, link_videoconferencia, localizacao, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'agendada')
        ");
        $stmt->execute([
            null,
            trim($_POST['candidato_nome'] ?? ''),
            trim($_POST['candidato_email'] ?? ''),
            trim($_POST['candidato_telefone'] ?? ''),
            $vaga_id_manual,
            $coluna_kanban,
            $tipo,
            $titulo,
            $_POST['descricao'] ?? null,
            $usuario['id'],
            $data_agendada_formatada,
            !empty($_POST['duracao_minutos']) ? (int)$_POST['duracao_minutos'] : 60,
            $link_videoconferencia,
            $localizacao
        ]);
        
        $entrevista_id = $pdo->lastInsertId();
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Entrevista agendada com sucesso',
        'entrevista_id' => $entrevista_id
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

