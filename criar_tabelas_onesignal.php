<?php
/**
 * Script SIMPLES para criar tabelas do OneSignal
 * Acesse: http://localhost/rh-privus/criar_tabelas_onesignal.php
 */

require_once __DIR__ . '/includes/functions.php';

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Criar Tabelas OneSignal</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        .success { color: green; }
        .error { color: red; }
        pre { background: #f5f5f5; padding: 15px; }
    </style>
</head>
<body>
    <h1>🔧 Criar Tabelas OneSignal</h1>
    
    <?php
    try {
        $pdo = getDB();
        
        echo "<h2>Executando...</h2><pre>";
        
        // 1. Criar onesignal_subscriptions
        echo "1. Criando tabela onesignal_subscriptions...\n";
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS onesignal_subscriptions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                usuario_id INT NULL,
                colaborador_id INT NULL,
                player_id VARCHAR(255) NOT NULL UNIQUE,
                device_type VARCHAR(50),
                user_agent VARCHAR(500),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
                FOREIGN KEY (colaborador_id) REFERENCES colaboradores(id) ON DELETE CASCADE,
                INDEX idx_usuario (usuario_id),
                INDEX idx_colaborador (colaborador_id),
                INDEX idx_player_id (player_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            echo "<span class='success'>✅ Criada!</span>\n\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), '1050') !== false || strpos($e->getMessage(), 'already exists') !== false) {
                echo "<span class='success'>✅ Já existe (OK)</span>\n\n";
            } else {
                echo "<span class='error'>❌ ERRO: " . htmlspecialchars($e->getMessage()) . "</span>\n\n";
                throw $e;
            }
        }
        
        // 2. Criar onesignal_config
        echo "2. Criando tabela onesignal_config...\n";
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS onesignal_config (
                id INT AUTO_INCREMENT PRIMARY KEY,
                app_id VARCHAR(255) NOT NULL,
                rest_api_key VARCHAR(255) NOT NULL,
                safari_web_id VARCHAR(255) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            echo "<span class='success'>✅ Criada!</span>\n\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), '1050') !== false || strpos($e->getMessage(), 'already exists') !== false) {
                echo "<span class='success'>✅ Já existe (OK)</span>\n\n";
            } else {
                echo "<span class='error'>❌ ERRO: " . htmlspecialchars($e->getMessage()) . "</span>\n\n";
                throw $e;
            }
        }
        
        echo "</pre>";
        
        // Verificação
        echo "<h2>Verificação:</h2><ul>";
        $tables = ['onesignal_subscriptions', 'onesignal_config'];
        $all_ok = true;
        
        foreach ($tables as $table) {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                echo "<li class='success'>✅ <strong>$table</strong> existe</li>";
            } else {
                echo "<li class='error'>❌ <strong>$table</strong> NÃO existe</li>";
                $all_ok = false;
            }
        }
        echo "</ul>";
        
        if ($all_ok) {
            echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin-top: 20px;'>";
            echo "<h3 class='success'>✅ SUCESSO!</h3>";
            echo "<p>Todas as tabelas foram criadas.</p>";
            echo "<p><a href='pages/configuracoes_onesignal.php'>Ir para Configurações OneSignal</a></p>";
            echo "</div>";
        } else {
            echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; margin-top: 20px;'>";
            echo "<h3 class='error'>❌ ERRO</h3>";
            echo "<p>Algumas tabelas não foram criadas. Verifique os erros acima.</p>";
            echo "</div>";
        }
        
    } catch (Exception $e) {
        echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px;'>";
        echo "<h3 class='error'>❌ Erro Fatal</h3>";
        echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
        echo "</div>";
    }
    ?>
</body>
</html>

