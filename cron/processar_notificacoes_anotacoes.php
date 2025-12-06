<?php
/**
 * Script para processar notificações agendadas de anotações
 * Deve ser executado via cron a cada minuto ou 5 minutos
 * 
 * Exemplo de cron:
 * */5 * * * * /usr/bin/php /caminho/para/rh-privus/cron/processar_notificacoes_anotacoes.php
 */

// Ativa exibição de erros para debug (ANTES de qualquer include)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Desabilita output buffering para ver erros imediatamente
if (ob_get_level()) {
    ob_end_clean();
}

// Captura erros fatais
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        echo "\n\n=== ERRO FATAL CAPTURADO ===\n";
        echo "Tipo: " . $error['type'] . "\n";
        echo "Mensagem: " . $error['message'] . "\n";
        echo "Arquivo: " . $error['file'] . "\n";
        echo "Linha: " . $error['line'] . "\n";
        echo "===========================\n";
        exit(1);
    }
});

// Handler de erros customizado
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    echo "\n=== ERRO ===\n";
    echo "Nível: " . $errno . "\n";
    echo "Mensagem: " . $errstr . "\n";
    echo "Arquivo: " . $errfile . "\n";
    echo "Linha: " . $errline . "\n";
    echo "===========\n";
    return false; // Continua execução normal
});

echo "Iniciando carregamento de arquivos...\n";

try {
    echo "Carregando functions.php...\n";
    require_once __DIR__ . '/../includes/functions.php';
    echo "OK - functions.php carregado\n";
    
    echo "Carregando notificacoes.php...\n";
    require_once __DIR__ . '/../includes/notificacoes.php';
    echo "OK - notificacoes.php carregado\n";
    
} catch (Throwable $e) {
    echo "\n=== ERRO ao carregar arquivos ===\n";
    echo "Mensagem: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . "\n";
    echo "Linha: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
    echo "==================================\n";
    exit(1);
}

// Define timezone
date_default_timezone_set('America/Sao_Paulo');

try {
    $pdo = getDB();
    
    echo "=== PROCESSAMENTO DE NOTIFICAÇÕES DE ANOTAÇÕES ===\n";
    echo "Data/Hora: " . date('Y-m-d H:i:s') . "\n\n";
    
    // Busca anotações com notificação agendada que ainda não foram enviadas
    $agora = date('Y-m-d H:i:s');
    
    $stmt = $pdo->prepare("
        SELECT id
        FROM anotacoes_sistema
        WHERE (notificar_email = 1 OR notificar_push = 1)
        AND data_notificacao IS NOT NULL
        AND data_notificacao <= ?
        AND notificacao_enviada = 0
        AND status = 'ativa'
    ");
    $stmt->execute([$agora]);
    $anotacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($anotacoes)) {
        echo "Nenhuma anotação pendente para processar.\n";
        exit(0);
    }
    
    echo "Anotações encontradas: " . count($anotacoes) . "\n\n";
    
    $processadas = 0;
    $erros = 0;
    
    foreach ($anotacoes as $anotacao) {
        try {
            $result = enviar_notificacoes_anotacao($anotacao['id'], $pdo);
            if ($result && (is_array($result) ? $result['success'] : $result)) {
                $processadas++;
                $enviados_email = is_array($result) ? ($result['enviados_email'] ?? 0) : 0;
                $enviados_push = is_array($result) ? ($result['enviados_push'] ?? 0) : 0;
                echo "Anotação #{$anotacao['id']} processada com sucesso";
                if ($enviados_email > 0 || $enviados_push > 0) {
                    echo " (Email: {$enviados_email}, Push: {$enviados_push})";
                }
                echo "\n";
            } else {
                $erros++;
                echo "Erro ao processar anotação #{$anotacao['id']}\n";
            }
        } catch (Exception $e) {
            $erros++;
            echo "Erro ao processar anotação #{$anotacao['id']}: " . $e->getMessage() . "\n";
            error_log("Erro ao processar anotação #{$anotacao['id']}: " . $e->getMessage());
        }
    }
    
    echo "\nProcessamento concluído: $processadas processadas, $erros erros\n";
    
} catch (Exception $e) {
    echo "ERRO FATAL: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    error_log("Erro fatal em processar_notificacoes_anotacoes: " . $e->getMessage());
    exit(1);
}

