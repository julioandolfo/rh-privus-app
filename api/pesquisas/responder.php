<?php
/**
 * API para Responder Pesquisa (pode ser chamada via link público com token)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/engajamento.php';

// Permite acesso sem autenticação se tiver token
$token = $_GET['token'] ?? $_POST['token'] ?? null;
$colaborador_id = null;
$anonima = false;

if ($token) {
    // Busca pesquisa pelo token do colaborador (token_resposta)
    $pdo = getDB();
    
    // Tenta encontrar pelo token_resposta (token específico do colaborador)
    $stmt = $pdo->prepare("
        SELECT pse.*, ps.*, 'satisfacao' as tipo
        FROM pesquisas_satisfacao_envios pse
        INNER JOIN pesquisas_satisfacao ps ON pse.pesquisa_id = ps.id
        WHERE pse.token_resposta = ? AND ps.status = 'ativa'
    ");
    $stmt->execute([$token]);
    $envio_satisfacao = $stmt->fetch();
    
    if ($envio_satisfacao) {
        $pesquisa_id = $envio_satisfacao['pesquisa_id'];
        $colaborador_id = $envio_satisfacao['colaborador_id'];
        $tipo = 'satisfacao';
        $anonima = $envio_satisfacao['anonima'] == 1;
        $pesquisa = $envio_satisfacao;
    } else {
        // Tenta pesquisa rápida
        $stmt = $pdo->prepare("
            SELECT pre.*, pr.*, 'rapida' as tipo
            FROM pesquisas_rapidas_envios pre
            INNER JOIN pesquisas_rapidas pr ON pre.pesquisa_id = pr.id
            WHERE pre.token_resposta = ? AND pr.status = 'ativa'
        ");
        $stmt->execute([$token]);
        $envio_rapida = $stmt->fetch();
        
        if ($envio_rapida) {
            $pesquisa_id = $envio_rapida['pesquisa_id'];
            $colaborador_id = $envio_rapida['colaborador_id'];
            $tipo = 'rapida';
            $anonima = $envio_rapida['anonima'] == 1;
            $pesquisa = $envio_rapida;
        } else {
            // Fallback: tenta pelo link_token da pesquisa (link público genérico)
            $stmt = $pdo->prepare("SELECT * FROM pesquisas_satisfacao WHERE link_token = ? AND status = 'ativa'");
            $stmt->execute([$token]);
            $pesquisa_satisfacao = $stmt->fetch();
            
            if ($pesquisa_satisfacao) {
                $pesquisa_id = $pesquisa_satisfacao['id'];
                $tipo = 'satisfacao';
                $anonima = $pesquisa_satisfacao['anonima'] == 1;
                $pesquisa = $pesquisa_satisfacao;
                // Se não for anônima e não tiver token_resposta, precisa identificar
                if (!$anonima) {
                    $identificador = $_POST['identificador'] ?? null;
                    if ($identificador) {
                        $cpf_limpo = preg_replace('/[^0-9]/', '', $identificador);
                        $stmt = $pdo->prepare("SELECT id FROM colaboradores WHERE (cpf = ? OR email_pessoal = ?) AND status = 'ativo'");
                        $stmt->execute([$cpf_limpo, $identificador]);
                        $colab = $stmt->fetch();
                        if ($colab) {
                            $colaborador_id = $colab['id'];
                        }
                    }
                    if (!$colaborador_id) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'Identifique-se para responder esta pesquisa']);
                        exit;
                    }
                }
            } else {
                $stmt = $pdo->prepare("SELECT * FROM pesquisas_rapidas WHERE link_token = ? AND status = 'ativa'");
                $stmt->execute([$token]);
                $pesquisa_rapida = $stmt->fetch();
                
                if ($pesquisa_rapida) {
                    $pesquisa_id = $pesquisa_rapida['id'];
                    $tipo = 'rapida';
                    $anonima = $pesquisa_rapida['anonima'] == 1;
                    $pesquisa = $pesquisa_rapida;
                    if (!$anonima) {
                        $identificador = $_POST['identificador'] ?? null;
                        if ($identificador) {
                            $cpf_limpo = preg_replace('/[^0-9]/', '', $identificador);
                            $stmt = $pdo->prepare("SELECT id FROM colaboradores WHERE (cpf = ? OR email_pessoal = ?) AND status = 'ativo'");
                            $stmt->execute([$cpf_limpo, $identificador]);
                            $colab = $stmt->fetch();
                            if ($colab) {
                                $colaborador_id = $colab['id'];
                            }
                        }
                        if (!$colaborador_id) {
                            http_response_code(400);
                            echo json_encode(['success' => false, 'message' => 'Identifique-se para responder esta pesquisa']);
                            exit;
                        }
                    }
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Pesquisa não encontrada ou inativa']);
                    exit;
                }
            }
        }
    }
    
    // Marca link como acessado
    if ($colaborador_id && isset($envio_satisfacao) || isset($envio_rapida)) {
        if ($tipo === 'satisfacao') {
            $stmt = $pdo->prepare("UPDATE pesquisas_satisfacao_envios SET link_acessado = 1 WHERE pesquisa_id = ? AND colaborador_id = ?");
        } else {
            $stmt = $pdo->prepare("UPDATE pesquisas_rapidas_envios SET link_acessado = 1 WHERE pesquisa_id = ? AND colaborador_id = ?");
        }
        $stmt->execute([$pesquisa_id, $colaborador_id]);
    }
    
} else {
    // Requer autenticação normal
    require_once __DIR__ . '/../../includes/auth.php';
    require_login();
    
    $usuario = $_SESSION['usuario'];
    $colaborador_id = $usuario['colaborador_id'] ?? null;
    
    if (!$colaborador_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Apenas colaboradores podem responder pesquisas']);
        exit;
    }
    
    $pesquisa_id = (int)($_POST['pesquisa_id'] ?? 0);
    $tipo = $_POST['tipo'] ?? 'satisfacao';
    
    if ($pesquisa_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID da pesquisa inválido']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

try {
    $pdo = getDB();
    
    // Verifica se já respondeu (se não for anônima)
    if (!$anonima && $colaborador_id) {
        if ($tipo === 'satisfacao') {
            $stmt = $pdo->prepare("SELECT id FROM pesquisas_satisfacao_respostas WHERE pesquisa_id = ? AND colaborador_id = ? LIMIT 1");
        } else {
            $stmt = $pdo->prepare("SELECT id FROM pesquisas_rapidas_respostas WHERE pesquisa_id = ? AND colaborador_id = ? LIMIT 1");
        }
        $stmt->execute([$pesquisa_id, $colaborador_id]);
        
        if ($stmt->fetch()) {
            throw new Exception('Você já respondeu esta pesquisa');
        }
    }
    
    $pdo->beginTransaction();
    
    try {
        if ($tipo === 'satisfacao') {
            // Respostas de campos dinâmicos
            $respostas = $_POST['respostas'] ?? [];
            if (is_string($respostas)) {
                $respostas = json_decode($respostas, true);
            }
            
            if (!is_array($respostas) || empty($respostas)) {
                throw new Exception('Nenhuma resposta fornecida');
            }
            
            $stmt_resposta = $pdo->prepare("
                INSERT INTO pesquisas_satisfacao_respostas (pesquisa_id, campo_id, colaborador_id, resposta, arquivo_path)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            foreach ($respostas as $campo_id => $resposta_data) {
                $campo_id = (int)$campo_id;
                $resposta_texto = is_array($resposta_data) ? json_encode($resposta_data) : $resposta_data;
                $arquivo_path = null; // TODO: implementar upload de arquivos se necessário
                
                $stmt_resposta->execute([$pesquisa_id, $campo_id, $colaborador_id, $resposta_texto, $arquivo_path]);
            }
            
            // Marca como respondida
            if ($colaborador_id) {
                $stmt = $pdo->prepare("
                    UPDATE pesquisas_satisfacao_envios 
                    SET respondida = 1, data_resposta = NOW(), link_acessado = 1
                    WHERE pesquisa_id = ? AND colaborador_id = ?
                ");
                $stmt->execute([$pesquisa_id, $colaborador_id]);
            }
            
        } else {
            // Pesquisa rápida
            $resposta = $_POST['resposta'] ?? '';
            
            if (empty($resposta)) {
                throw new Exception('Resposta é obrigatória');
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO pesquisas_rapidas_respostas (pesquisa_id, colaborador_id, resposta)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$pesquisa_id, $colaborador_id, $resposta]);
            
            // Marca como respondida
            if ($colaborador_id) {
                $stmt = $pdo->prepare("
                    UPDATE pesquisas_rapidas_envios 
                    SET respondida = 1, data_resposta = NOW(), link_acessado = 1
                    WHERE pesquisa_id = ? AND colaborador_id = ?
                ");
                $stmt->execute([$pesquisa_id, $colaborador_id]);
            }
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Resposta registrada com sucesso!'
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

