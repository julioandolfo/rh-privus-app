<?php
/**
 * Script de Correção - Criar registros em horas_extras para movimentações faltantes
 * 
 * Este script identifica movimentações do banco de horas tipo 'hora_extra' que não têm
 * registro correspondente em horas_extras e cria esses registros automaticamente.
 */

// Configuração de erros
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define se está sendo executado via CLI ou web
$is_cli = php_sapi_name() === 'cli';

// Headers para web
if (!$is_cli) {
    header('Content-Type: text/html; charset=utf-8');
}

try {
    require_once __DIR__ . '/../includes/functions.php';
    require_once __DIR__ . '/../includes/auth.php';
    require_once __DIR__ . '/../includes/permissions.php';
    
    // Se executado via web, verifica autenticação
    if (!$is_cli) {
        if (!isset($_SESSION['usuario'])) {
            die('Erro: Usuário não autenticado. Faça login primeiro.');
        }
        
        // Apenas ADMIN pode executar
        if (!has_role(['ADMIN'])) {
            die('Acesso negado. Apenas administradores podem executar este script.');
        }
    }
} catch (Exception $e) {
    die('Erro ao carregar dependências: ' . $e->getMessage() . "\n");
}

try {
    $pdo = getDB();
} catch (Exception $e) {
    die('Erro ao conectar ao banco de dados: ' . $e->getMessage() . "\n");
}

if (!$is_cli) {
    echo "<pre style='font-family: monospace; font-size: 12px;'>";
}

echo "=== CORREÇÃO DE MOVIMENTAÇÕES SEM HORAS EXTRAS ===\n\n";

// Busca movimentações hora_extra sem registro correspondente
echo "Buscando movimentações hora_extra sem registro em horas_extras...\n\n";

try {
    $stmt = $pdo->query("
        SELECT bhm.*, c.nome_completo, c.empresa_id, e.percentual_hora_extra
        FROM banco_horas_movimentacoes bhm
        INNER JOIN colaboradores c ON bhm.colaborador_id = c.id
        LEFT JOIN empresas e ON c.empresa_id = e.id
        WHERE bhm.origem = 'hora_extra'
        AND bhm.tipo = 'credito'
        AND (bhm.origem_id IS NULL 
             OR NOT EXISTS (
                 SELECT 1 FROM horas_extras h 
                 WHERE h.id = bhm.origem_id
             ))
        ORDER BY bhm.data_movimentacao DESC, bhm.created_at DESC
    ");
    $movimentacoes_sem_registro = $stmt->fetchAll();
} catch (Exception $e) {
    die("❌ Erro ao buscar movimentações: " . $e->getMessage() . "\n");
}

if (count($movimentacoes_sem_registro) === 0) {
    echo "✅ Nenhuma movimentação sem registro encontrada. Sistema está íntegro!\n";
    if (!$is_cli) {
        echo "</pre>";
    }
    exit(0);
}

echo "⚠️  Encontradas " . count($movimentacoes_sem_registro) . " movimentação(ões) sem registro:\n\n";

// Mostra detalhes das movimentações encontradas
foreach ($movimentacoes_sem_registro as $mov) {
    echo "   - ID Movimentação: {$mov['id']}\n";
    echo "     Colaborador: {$mov['nome_completo']} (ID: {$mov['colaborador_id']})\n";
    echo "     Data: " . date('d/m/Y', strtotime($mov['data_movimentacao'])) . "\n";
    echo "     Quantidade: +{$mov['quantidade_horas']}h\n";
    echo "     Motivo: " . substr($mov['motivo'], 0, 60) . "...\n";
    echo "     Origem ID atual: " . ($mov['origem_id'] ?? 'NULL') . "\n\n";
}

// Pergunta se deve corrigir (apenas CLI)
if ($is_cli) {
    echo "Deseja criar os registros faltantes em horas_extras? (s/n): ";
    $handle = fopen("php://stdin", "r");
    $resposta = trim(fgets($handle));
    fclose($handle);
    
    if (strtolower($resposta) !== 's' && strtolower($resposta) !== 'sim') {
        echo "\nOperação cancelada pelo usuário.\n";
        exit(0);
    }
} else {
    // Via web, verifica se tem parâmetro de confirmação
    if (!isset($_GET['confirmar']) || $_GET['confirmar'] !== 'sim') {
        echo "\n⚠️  ATENÇÃO: Esta operação irá criar " . count($movimentacoes_sem_registro) . " registro(s) em horas_extras.\n";
        echo "\nPara confirmar, acesse: " . $_SERVER['PHP_SELF'] . "?confirmar=sim\n";
        if (!$is_cli) {
            echo "</pre>";
        }
        exit(0);
    }
}

echo "\n=== INICIANDO CORREÇÃO ===\n\n";

$sucessos = 0;
$erros = 0;
$erros_detalhes = [];

foreach ($movimentacoes_sem_registro as $mov) {
    try {
        // Busca dados do colaborador para calcular valores (se necessário)
        $colaborador_id = $mov['colaborador_id'];
        $data_trabalho = $mov['data_movimentacao'];
        $quantidade_horas = $mov['quantidade_horas'];
        $observacoes = $mov['observacoes'] ?? '';
        $usuario_id = $mov['usuario_id'] ?? null;
        
        // Para banco de horas, valores são zero
        $valor_hora = 0;
        $percentual_adicional = 0;
        $valor_total = 0;
        
        // Insere registro em horas_extras
        $stmt = $pdo->prepare("
            INSERT INTO horas_extras (
                colaborador_id, 
                data_trabalho, 
                quantidade_horas, 
                valor_hora, 
                percentual_adicional, 
                valor_total, 
                observacoes, 
                usuario_id, 
                tipo_pagamento, 
                banco_horas_movimentacao_id,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'banco_horas', ?, ?)
        ");
        
        $created_at = isset($mov['created_at']) ? $mov['created_at'] : date('Y-m-d H:i:s');
        
        $stmt->execute([
            $colaborador_id,
            $data_trabalho,
            $quantidade_horas,
            $valor_hora,
            $percentual_adicional,
            $valor_total,
            $observacoes,
            $usuario_id,
            $mov['id'], // banco_horas_movimentacao_id
            $created_at
        ]);
        
        $hora_extra_id = $pdo->lastInsertId();
        
        // Atualiza a movimentação com o ID da hora extra criada
        $stmt_update = $pdo->prepare("
            UPDATE banco_horas_movimentacoes 
            SET origem_id = ? 
            WHERE id = ?
        ");
        $stmt_update->execute([$hora_extra_id, $mov['id']]);
        
        echo "   ✅ Criado registro horas_extras ID: {$hora_extra_id} para movimentação ID: {$mov['id']}\n";
        $sucessos++;
        
    } catch (Exception $e) {
        echo "   ❌ Erro ao criar registro para movimentação ID {$mov['id']}: " . $e->getMessage() . "\n";
        $erros++;
        $erros_detalhes[] = [
            'movimentacao_id' => $mov['id'],
            'erro' => $e->getMessage()
        ];
    }
}

echo "\n=== RESULTADO DA CORREÇÃO ===\n";
echo "✅ Registros criados com sucesso: {$sucessos}\n";
if ($erros > 0) {
    echo "❌ Erros encontrados: {$erros}\n";
    if (count($erros_detalhes) > 0) {
        echo "\nDetalhes dos erros:\n";
        foreach ($erros_detalhes as $erro) {
            echo "   - Movimentação ID {$erro['movimentacao_id']}: {$erro['erro']}\n";
        }
    }
} else {
    echo "✅ Todas as correções foram aplicadas com sucesso!\n";
}

echo "\n=== FIM DA CORREÇÃO ===\n";

if (!$is_cli) {
    echo "</pre>";
}

