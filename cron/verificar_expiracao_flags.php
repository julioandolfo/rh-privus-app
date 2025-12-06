<?php
/**
 * Script para verificar e expirar flags automaticamente
 * Executado via cron diariamente
 * 
 * Exemplo de cron (executar diariamente às 00:00):
 * 0 0 * * * /usr/bin/php /caminho/para/cron/verificar_expiracao_flags.php
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ocorrencias_functions.php';

// Permite execução via linha de comando ou web
$is_cli = php_sapi_name() === 'cli';

if (!$is_cli) {
    // Se executado via web, verifica autenticação
    require_once __DIR__ . '/../includes/auth.php';
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['usuario']) || !has_role(['ADMIN', 'RH'])) {
        http_response_code(403);
        die('Acesso negado');
    }
}

try {
    $pdo = getDB();
    
    echo "Iniciando verificação de expiração de flags...\n";
    
    // Verifica e expira flags vencidas
    $flags_expiradas = verificar_expiracao_flags();
    
    echo "Flags expiradas: {$flags_expiradas}\n";
    
    // Busca colaboradores com 3 ou mais flags ativas (para alerta)
    $stmt = $pdo->query("
        SELECT colaborador_id, COUNT(*) as total_flags
        FROM ocorrencias_flags
        WHERE status = 'ativa' AND data_validade >= CURDATE()
        GROUP BY colaborador_id
        HAVING total_flags >= 3
    ");
    $colaboradores_alerta = $stmt->fetchAll();
    
    if (count($colaboradores_alerta) > 0) {
        echo "\n⚠️  ALERTA: Colaboradores com 3 ou mais flags ativas:\n";
        foreach ($colaboradores_alerta as $alerta) {
            $stmt_colab = $pdo->prepare("SELECT nome_completo FROM colaboradores WHERE id = ?");
            $stmt_colab->execute([$alerta['colaborador_id']]);
            $colab = $stmt_colab->fetch();
            echo "  - {$colab['nome_completo']} (ID: {$alerta['colaborador_id']}): {$alerta['total_flags']} flags ativas\n";
        }
    }
    
    echo "\nVerificação concluída com sucesso!\n";
    
    if (!$is_cli) {
        echo json_encode([
            'success' => true,
            'flags_expiradas' => $flags_expiradas,
            'colaboradores_alerta' => count($colaboradores_alerta)
        ]);
    }
    
} catch (Exception $e) {
    $mensagem = "Erro ao verificar expiração de flags: " . $e->getMessage();
    error_log($mensagem);
    
    if ($is_cli) {
        echo "ERRO: {$mensagem}\n";
        exit(1);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $mensagem]);
    }
}

