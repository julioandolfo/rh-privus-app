<?php
/**
 * API: Cadastrar Colaborador a partir de Candidatura/Entrevista
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/permissions.php';
require_once __DIR__ . '/../../../includes/upload_foto.php';

require_login();

if (!has_role(['ADMIN', 'RH', 'GESTOR'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissão']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

try {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    
    $candidatura_id = $_POST['candidatura_id'] ?? '';
    $is_entrevista = !empty($_POST['is_entrevista']) && $_POST['is_entrevista'] === '1';
    
    $nome_completo = sanitize($_POST['nome_completo'] ?? '');
    $cpf = preg_replace('/[^0-9]/', '', $_POST['cpf'] ?? '');
    $email = sanitize($_POST['email_pessoal'] ?? '');
    $telefone = sanitize($_POST['telefone'] ?? '');
    $empresa_id = (int)($_POST['empresa_id'] ?? 0);
    $setor_id = (int)($_POST['setor_id'] ?? 0);
    $cargo_id = (int)($_POST['cargo_id'] ?? 0);
    $data_inicio = $_POST['data_inicio'] ?? null;
    $tipo_contrato = $_POST['tipo_contrato'] ?? 'CLT';
    $salario = !empty($_POST['salario']) ? str_replace(['.', ','], ['', '.'], $_POST['salario']) : null;
    
    // Validações
    if (empty($nome_completo) || empty($cpf) || empty($email) || empty($empresa_id) || empty($setor_id) || empty($cargo_id) || empty($data_inicio)) {
        throw new Exception('Preencha todos os campos obrigatórios');
    }
    
    // Verifica permissão
    if ($usuario['role'] === 'RH' && !can_access_empresa($empresa_id)) {
        throw new Exception('Sem permissão para esta empresa');
    }
    
    // Verifica se CPF já existe
    $stmt = $pdo->prepare("SELECT id FROM colaboradores WHERE cpf = ?");
    $stmt->execute([$cpf]);
    if ($stmt->fetch()) {
        throw new Exception('CPF já cadastrado');
    }
    
    // Verifica se email já existe
    $stmt = $pdo->prepare("SELECT id FROM colaboradores WHERE email_pessoal = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        throw new Exception('Email já cadastrado');
    }
    
    $pdo->beginTransaction();
    
    // Cria colaborador
    $stmt = $pdo->prepare("
        INSERT INTO colaboradores 
        (empresa_id, setor_id, cargo_id, nome_completo, cpf, email_pessoal, telefone, data_inicio, tipo_contrato, salario, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ativo')
    ");
    $stmt->execute([
        $empresa_id,
        $setor_id,
        $cargo_id,
        $nome_completo,
        $cpf,
        $email,
        $telefone,
        $data_inicio,
        $tipo_contrato,
        $salario
    ]);
    
    $colaborador_id = $pdo->lastInsertId();
    
    // Se veio de candidatura, atualiza status e cria onboarding
    if (!$is_entrevista && $candidatura_id) {
        $id_limpo = str_replace('entrevista_', '', $candidatura_id);
        
        // Busca a candidatura para verificar se já estava em aprovados
        $stmt = $pdo->prepare("SELECT vaga_id, coluna_kanban FROM candidaturas WHERE id = ?");
        $stmt->execute([$id_limpo]);
        $candidatura_atual = $stmt->fetch();
        
        // Atualiza candidatura para aprovada
        $stmt = $pdo->prepare("
            UPDATE candidaturas 
            SET status = 'aprovada', 
                coluna_kanban = 'contratado',
                data_aprovacao = CURDATE()
            WHERE id = ?
        ");
        $stmt->execute([$id_limpo]);
        
        // Se não estava em aprovados ainda, incrementa quantidade_preenchida
        if ($candidatura_atual && $candidatura_atual['coluna_kanban'] !== 'aprovados') {
            $stmt = $pdo->prepare("
                UPDATE vagas 
                SET quantidade_preenchida = quantidade_preenchida + 1
                WHERE id = ?
            ");
            $stmt->execute([$candidatura_atual['vaga_id']]);
        }
        
        // Cria processo de onboarding
        $stmt = $pdo->prepare("
            INSERT INTO onboarding 
            (candidatura_id, colaborador_id, status, coluna_kanban, data_inicio, responsavel_id)
            VALUES (?, ?, 'contratado', 'contratado', CURDATE(), ?)
        ");
        $stmt->execute([
            $id_limpo,
            $colaborador_id,
            $usuario['id']
        ]);
    }
    
    // Se veio de entrevista manual, atualiza entrevista
    if ($is_entrevista && $candidatura_id) {
        $id_limpo = (int)str_replace('entrevista_', '', $candidatura_id);
        
        // Atualiza entrevista
        $stmt = $pdo->prepare("
            UPDATE entrevistas 
            SET coluna_kanban = 'contratado',
                status = 'realizada'
            WHERE id = ?
        ");
        $stmt->execute([$id_limpo]);
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Colaborador cadastrado com sucesso',
        'colaborador_id' => $colaborador_id
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

