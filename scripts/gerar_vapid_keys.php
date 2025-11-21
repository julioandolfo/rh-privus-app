<?php
/**
 * Gera chaves VAPID para Web Push
 * Execute UMA VEZ: php scripts/gerar_vapid_keys.php
 */

require_once __DIR__ . '/../includes/functions.php';

use Minishlink\WebPush\VAPID;

try {
    $keys = VAPID::createVapidKeys();
    
    $pdo = getDB();
    
    // Remove chaves antigas
    $pdo->exec("DELETE FROM vapid_keys");
    
    // Insere novas chaves
    $stmt = $pdo->prepare("
        INSERT INTO vapid_keys (public_key, private_key) 
        VALUES (?, ?)
    ");
    $stmt->execute([$keys['publicKey'], $keys['privateKey']]);
    
    echo "✅ Chaves VAPID geradas com sucesso!\n\n";
    echo "Public Key (use no frontend):\n";
    echo $keys['publicKey'] . "\n\n";
    echo "Private Key (mantenha segura):\n";
    echo $keys['privateKey'] . "\n\n";
    echo "⚠️  IMPORTANTE: Guarde essas chaves em local seguro!\n";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    echo "\nCertifique-se de que:\n";
    echo "1. A biblioteca minishlink/web-push está instalada (composer require minishlink/web-push)\n";
    echo "2. As tabelas foram criadas (execute migracao_push_notifications.sql)\n";
}

