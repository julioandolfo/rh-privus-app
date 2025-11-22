<?php
/**
 * API para Criar Pesquisa (Satisfação ou Rápida)
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

// Verifica permissão
if (!can_access_page('pesquisas_satisfacao.php') && !can_access_page('pesquisas_rapidas.php')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sem permissão']);
    exit;
}

try {
    $pdo = getDB();
    $usuario = $_SESSION['usuario'];
    $request_id = $_POST['request_id'] ?? null;
    $tipo = $_POST['tipo'] ?? 'satisfacao'; // 'satisfacao' ou 'rapida'
    
    if (!in_array($tipo, ['satisfacao', 'rapida'])) {
        throw new Exception('Tipo de pesquisa inválido');
    }
    
    // Validações básicas
    $titulo = trim($_POST['titulo'] ?? '');
    if (empty($titulo)) {
        throw new Exception('Título é obrigatório');
    }
    
    $publico_alvo = $_POST['publico_alvo'] ?? 'todos';
    $data_inicio = $_POST['data_inicio'] ?? date('Y-m-d');
    $data_fim = !empty($_POST['data_fim']) ? $_POST['data_fim'] : null;
    $enviar_email = isset($_POST['enviar_email']) ? (int)$_POST['enviar_email'] : 1;
    $enviar_push = isset($_POST['enviar_push']) ? (int)$_POST['enviar_push'] : 1;
    $anonima = isset($_POST['anonima']) ? (int)$_POST['anonima'] : 0;
    
    // Proteção ATÔMICA contra requisições duplicadas usando GET_LOCK do MySQL
    if ($request_id) {
        $lockName = 'pesquisa_' . $tipo . '_' . $request_id;
        $stmt = $pdo->prepare("SELECT GET_LOCK(?, 0) as lock_result");
        $stmt->execute([$lockName]);
        $lockResult = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($lockResult['lock_result'] != 1) {
            echo json_encode([
                'success' => true,
                'already_processed' => true,
                'message' => 'Pesquisa já está sendo processada.'
            ]);
            return;
        }
    }
    
    // Verifica duplicação: pesquisa idêntica nos últimos 30 segundos
    if ($tipo === 'satisfacao') {
        $stmt_check = $pdo->prepare("
            SELECT id FROM pesquisas_satisfacao 
            WHERE created_by = ? 
            AND titulo = ?
            AND data_inicio = ?
            AND created_at > DATE_SUB(NOW(), INTERVAL 30 SECOND)
            LIMIT 1
        ");
    } else {
        $stmt_check = $pdo->prepare("
            SELECT id FROM pesquisas_rapidas 
            WHERE created_by = ? 
            AND titulo = ?
            AND data_inicio = ?
            AND created_at > DATE_SUB(NOW(), INTERVAL 30 SECOND)
            LIMIT 1
        ");
    }
    $stmt_check->execute([
        $usuario['id'],
        $titulo,
        $data_inicio
    ]);
    if ($stmt_check->fetch()) {
        if ($request_id) {
            $lockName = 'pesquisa_' . $tipo . '_' . $request_id;
            $pdo->prepare("SELECT RELEASE_LOCK(?)")->execute([$lockName]);
        }
        echo json_encode([
            'success' => true,
            'already_processed' => true,
            'message' => 'Pesquisa já foi registrada recentemente.'
        ]);
        return;
    }
    
    // Gera token único para link
    $link_token = gerar_token_pesquisa();
    
    $pdo->beginTransaction();
    
    try {
        // Verifica novamente dentro da transação com lock (double-check)
        if ($tipo === 'satisfacao') {
            $stmt_check2 = $pdo->prepare("
                SELECT id FROM pesquisas_satisfacao 
                WHERE created_by = ? 
                AND titulo = ?
                AND data_inicio = ?
                AND created_at > DATE_SUB(NOW(), INTERVAL 30 SECOND)
                LIMIT 1
                FOR UPDATE
            ");
        } else {
            $stmt_check2 = $pdo->prepare("
                SELECT id FROM pesquisas_rapidas 
                WHERE created_by = ? 
                AND titulo = ?
                AND data_inicio = ?
                AND created_at > DATE_SUB(NOW(), INTERVAL 30 SECOND)
                LIMIT 1
                FOR UPDATE
            ");
        }
        $stmt_check2->execute([
            $usuario['id'],
            $titulo,
            $data_inicio
        ]);
        if ($stmt_check2->fetch()) {
            $pdo->rollBack();
            if ($request_id) {
                $lockName = 'pesquisa_' . $tipo . '_' . $request_id;
                $pdo->prepare("SELECT RELEASE_LOCK(?)")->execute([$lockName]);
            }
            echo json_encode([
                'success' => true,
                'already_processed' => true,
                'message' => 'Pesquisa já foi registrada recentemente.'
            ]);
            return;
        }
        
        if ($tipo === 'satisfacao') {
            // Verifica se módulo está ativo
            if (!engajamento_modulo_ativo('pesquisas_satisfacao')) {
                throw new Exception('Módulo de pesquisas de satisfação está desativado');
            }
            
            $descricao = trim($_POST['descricao'] ?? '');
            $tipo_pesquisa = $_POST['tipo_pesquisa'] ?? 'satisfacao';
            
            // Insere pesquisa
            $stmt = $pdo->prepare("
                INSERT INTO pesquisas_satisfacao (
                    titulo, descricao, tipo, data_inicio, data_fim,
                    publico_alvo, empresa_id, setor_id, cargo_id, participantes_ids,
                    status, link_token, enviar_email, enviar_push, anonima, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'rascunho', ?, ?, ?, ?, ?)
            ");
            
            $empresa_id = !empty($_POST['empresa_id']) ? (int)$_POST['empresa_id'] : null;
            $setor_id = !empty($_POST['setor_id']) ? (int)$_POST['setor_id'] : null;
            $cargo_id = !empty($_POST['cargo_id']) ? (int)$_POST['cargo_id'] : null;
            $participantes_ids = !empty($_POST['participantes_ids']) ? json_encode($_POST['participantes_ids']) : null;
            
            $stmt->execute([
                $titulo, $descricao, $tipo_pesquisa, $data_inicio, $data_fim,
                $publico_alvo, $empresa_id, $setor_id, $cargo_id, $participantes_ids,
                $link_token, $enviar_email, $enviar_push, $anonima, $usuario['id']
            ]);
            
            $pesquisa_id = $pdo->lastInsertId();
            
            // Insere campos dinâmicos
            $campos = $_POST['campos'] ?? [];
            if (is_string($campos)) {
                $campos = json_decode($campos, true);
            }
            
            if (is_array($campos) && !empty($campos)) {
                $stmt_campo = $pdo->prepare("
                    INSERT INTO pesquisas_satisfacao_campos (
                        pesquisa_id, tipo, label, descricao, obrigatorio, ordem, opcoes, placeholder, valor_padrao, validacao
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                foreach ($campos as $index => $campo) {
                    $tipo_campo = $campo['tipo'] ?? 'texto';
                    $label = $campo['label'] ?? '';
                    $descricao_campo = $campo['descricao'] ?? null;
                    $obrigatorio = isset($campo['obrigatorio']) ? (int)$campo['obrigatorio'] : 0;
                    $ordem = $campo['ordem'] ?? $index;
                    $opcoes = !empty($campo['opcoes']) ? json_encode($campo['opcoes']) : null;
                    $placeholder = $campo['placeholder'] ?? null;
                    $valor_padrao = $campo['valor_padrao'] ?? null;
                    $validacao = !empty($campo['validacao']) ? json_encode($campo['validacao']) : null;
                    
                    if (!empty($label)) {
                        $stmt_campo->execute([
                            $pesquisa_id, $tipo_campo, $label, $descricao_campo, $obrigatorio,
                            $ordem, $opcoes, $placeholder, $valor_padrao, $validacao
                        ]);
                    }
                }
            }
            
        } else {
            // Pesquisa rápida
            if (!engajamento_modulo_ativo('pesquisas_rapidas')) {
                throw new Exception('Módulo de pesquisas rápidas está desativado');
            }
            
            $pergunta = trim($_POST['pergunta'] ?? '');
            if (empty($pergunta)) {
                throw new Exception('Pergunta é obrigatória');
            }
            
            $tipo_resposta = $_POST['tipo_resposta'] ?? 'sim_nao';
            $opcoes = !empty($_POST['opcoes']) ? json_encode($_POST['opcoes']) : null;
            $data_inicio_datetime = $_POST['data_inicio_datetime'] ?? date('Y-m-d H:i:s');
            $data_fim_datetime = !empty($_POST['data_fim_datetime']) ? $_POST['data_fim_datetime'] : null;
            
            $stmt = $pdo->prepare("
                INSERT INTO pesquisas_rapidas (
                    titulo, pergunta, tipo_resposta, opcoes, data_inicio, data_fim,
                    publico_alvo, empresa_id, setor_id, cargo_id, participantes_ids,
                    status, link_token, enviar_email, enviar_push, anonima, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'rascunho', ?, ?, ?, ?, ?)
            ");
            
            $empresa_id = !empty($_POST['empresa_id']) ? (int)$_POST['empresa_id'] : null;
            $setor_id = !empty($_POST['setor_id']) ? (int)$_POST['setor_id'] : null;
            $cargo_id = !empty($_POST['cargo_id']) ? (int)$_POST['cargo_id'] : null;
            $participantes_ids = !empty($_POST['participantes_ids']) ? json_encode($_POST['participantes_ids']) : null;
            
            $stmt->execute([
                $titulo, $pergunta, $tipo_resposta, $opcoes, $data_inicio_datetime, $data_fim_datetime,
                $publico_alvo, $empresa_id, $setor_id, $cargo_id, $participantes_ids,
                $link_token, $enviar_email, $enviar_push, $anonima, $usuario['id']
            ]);
            
            $pesquisa_id = $pdo->lastInsertId();
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Pesquisa criada com sucesso!',
            'pesquisa_id' => $pesquisa_id,
            'link_token' => $link_token
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

