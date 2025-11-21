<?php
/**
 * Script para executar migração do OneSignal
 * Acesse: http://localhost/rh-privus/executar_migracao_onesignal.php
 */

require_once __DIR__ . '/includes/functions.php';

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migração OneSignal - RH Privus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; background: #f5f5f5; }
        .card { margin-bottom: 20px; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h2>🔧 Migração OneSignal</h2>
            </div>
            <div class="card-body">
                <?php
                try {
                    $pdo = getDB();
                    
                    echo "<h3>Executando migração...</h3>";
                    echo "<pre>";
                    
                    $success = 0;
                    $errors = [];
                    
                    // Comando 1: Criar tabela onesignal_subscriptions
                    try {
                        // Primeiro verifica se tabelas referenciadas existem
                        $stmt = $pdo->query("SHOW TABLES LIKE 'usuarios'");
                        $usuarios_existe = $stmt->rowCount() > 0;
                        
                        $stmt = $pdo->query("SHOW TABLES LIKE 'colaboradores'");
                        $colaboradores_existe = $stmt->rowCount() > 0;
                        
                        if (!$usuarios_existe || !$colaboradores_existe) {
                            throw new Exception("Tabelas 'usuarios' ou 'colaboradores' não existem. Execute a instalação do sistema primeiro.");
                        }
                        
                        $sql = "
                            CREATE TABLE IF NOT EXISTS onesignal_subscriptions (
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
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                        ";
                        
                        $pdo->exec($sql);
                        echo "<span class='success'>✅ Tabela onesignal_subscriptions criada com sucesso</span>\n";
                        $success++;
                    } catch (PDOException $e) {
                        $error_msg = $e->getMessage();
                        if (strpos($error_msg, 'already exists') !== false || 
                            strpos($error_msg, 'Duplicate') !== false ||
                            strpos($error_msg, '1050') !== false) {
                            echo "<span class='text-muted'>⚠️ Tabela onesignal_subscriptions já existe (ignorado)</span>\n";
                        } else {
                            echo "<span class='error'>❌ Erro ao criar onesignal_subscriptions:</span>\n";
                            echo "<span class='error'>" . htmlspecialchars($error_msg) . "</span>\n";
                            $errors[] = $error_msg;
                        }
                    } catch (Exception $e) {
                        echo "<span class='error'>❌ Erro: " . htmlspecialchars($e->getMessage()) . "</span>\n";
                        $errors[] = $e->getMessage();
                    }
                    
                    // Comando 2: Criar tabela onesignal_config
                    try {
                        $sql = "
                            CREATE TABLE IF NOT EXISTS onesignal_config (
                                id INT AUTO_INCREMENT PRIMARY KEY,
                                app_id VARCHAR(255) NOT NULL,
                                rest_api_key VARCHAR(255) NOT NULL,
                                safari_web_id VARCHAR(255) NULL,
                                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                        ";
                        
                        $pdo->exec($sql);
                        echo "<span class='success'>✅ Tabela onesignal_config criada com sucesso</span>\n";
                        $success++;
                    } catch (PDOException $e) {
                        $error_msg = $e->getMessage();
                        if (strpos($error_msg, 'already exists') !== false || 
                            strpos($error_msg, 'Duplicate') !== false ||
                            strpos($error_msg, '1050') !== false) {
                            echo "<span class='text-muted'>⚠️ Tabela onesignal_config já existe (ignorado)</span>\n";
                        } else {
                            echo "<span class='error'>❌ Erro ao criar onesignal_config:</span>\n";
                            echo "<span class='error'>" . htmlspecialchars($error_msg) . "</span>\n";
                            $errors[] = $error_msg;
                        }
                    }
                    
                    echo "</pre>";
                    
                    // Verifica se as tabelas foram criadas
                    echo "<h3>Verificação:</h3>";
                    echo "<ul>";
                    
                    $tables_ok = true;
                    $tables = ['onesignal_subscriptions', 'onesignal_config'];
                    foreach ($tables as $table) {
                        try {
                            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                            if ($stmt->rowCount() > 0) {
                                echo "<li class='success'>✅ Tabela <strong>$table</strong> existe</li>";
                            } else {
                                echo "<li class='error'>❌ Tabela <strong>$table</strong> NÃO existe</li>";
                                $tables_ok = false;
                            }
                        } catch (PDOException $e) {
                            echo "<li class='error'>❌ Erro ao verificar $table: " . htmlspecialchars($e->getMessage()) . "</li>";
                            $tables_ok = false;
                        }
                    }
                    
                    echo "</ul>";
                    
                    if ($tables_ok) {
                        echo "<div class='alert alert-success mt-4'>";
                        echo "<h4>✅ Migração concluída com sucesso!</h4>";
                        echo "<p>As tabelas do OneSignal foram criadas.</p>";
                        echo "<p><a href='pages/configuracoes_onesignal.php' class='btn btn-primary'>Ir para Configurações OneSignal</a></p>";
                        echo "</div>";
                    } else {
                        echo "<div class='alert alert-danger mt-4'>";
                        echo "<h4>❌ Erro na migração</h4>";
                        if (!empty($errors)) {
                            echo "<p><strong>Erros encontrados:</strong></p>";
                            echo "<ul>";
                            foreach ($errors as $error) {
                                echo "<li>" . htmlspecialchars($error) . "</li>";
                            }
                            echo "</ul>";
                        }
                        echo "<p><strong>Tente executar manualmente no phpMyAdmin:</strong></p>";
                        echo "<pre style='background: #f8f9fa; padding: 15px; border-radius: 5px;'>";
                        echo htmlspecialchars(file_get_contents(__DIR__ . '/migracao_onesignal.sql'));
                        echo "</pre>";
                        echo "</div>";
                    }
                    
                } catch (Exception $e) {
                    echo "<div class='alert alert-danger'>";
                    echo "<h4>❌ Erro Fatal:</h4>";
                    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
                    echo "</div>";
                }
                ?>
                
                <hr>
                <h4>Próximos Passos:</h4>
                <ol>
                    <li>Configure as credenciais do OneSignal em <a href="pages/configuracoes_onesignal.php">Configurações OneSignal</a></li>
                    <li>Faça login no sistema e permita notificações</li>
                    <li>Teste enviando uma notificação</li>
                </ol>
            </div>
        </div>
    </div>
</body>
</html>

