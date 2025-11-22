<?php
/**
 * Script para processar notificações agendadas de anotações
 * Deve ser executado via cron a cada minuto ou 5 minutos
 * 
 * Exemplo de cron:
 * */5 * * * * /usr/bin/php /caminho/para/rh-privus/cron/processar_notificacoes_anotacoes.php
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notificacoes.php';

// Define timezone
date_default_timezone_set('America/Sao_Paulo');

try {
    $pdo = getDB();
    
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
    
    $processadas = 0;
    $erros = 0;
    
    foreach ($anotacoes as $anotacao) {
        try {
            $result = enviar_notificacoes_anotacao($anotacao['id'], $pdo);
            if ($result) {
                $processadas++;
                echo "Anotação #{$anotacao['id']} processada com sucesso\n";
            } else {
                $erros++;
                echo "Erro ao processar anotação #{$anotacao['id']}\n";
            }
        } catch (Exception $e) {
            $erros++;
            echo "Erro ao processar anotação #{$anotacao['id']}: " . $e->getMessage() . "\n";
        }
    }
    
    echo "Processamento concluído: $processadas processadas, $erros erros\n";
    
} catch (Exception $e) {
    echo "Erro fatal: " . $e->getMessage() . "\n";
    exit(1);
}

