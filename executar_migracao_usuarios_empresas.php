<?php
/**
 * Script de Migração: Adiciona suporte a múltiplas empresas por usuário
 * Execute este arquivo uma vez para criar a tabela usuarios_empresas
 */

require_once __DIR__ . '/includes/functions.php';

// Verifica se está logado como ADMIN
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['role'] !== 'ADMIN') {
    die('Acesso negado! Apenas administradores podem executar esta migração.');
}

$pdo = getDB();
$success = false;
$error = '';

try {
    // Verifica se a tabela já existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'usuarios_empresas'");
    $tabela_existe = $stmt->rowCount() > 0;
    
    if (!$tabela_existe) {
        // Cria tabela de relacionamento muitos-para-muitos
        $pdo->exec("
            CREATE TABLE usuarios_empresas (
                id INT AUTO_INCREMENT PRIMARY KEY,
                usuario_id INT NOT NULL,
                empresa_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
                FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
                UNIQUE KEY uk_usuario_empresa (usuario_id, empresa_id),
                INDEX idx_usuario (usuario_id),
                INDEX idx_empresa (empresa_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Migra dados existentes da coluna empresa_id para a nova tabela
        $pdo->exec("
            INSERT INTO usuarios_empresas (usuario_id, empresa_id)
            SELECT id, empresa_id 
            FROM usuarios 
            WHERE empresa_id IS NOT NULL
        ");
        
        $success = true;
        $message = 'Migração executada com sucesso! A tabela usuarios_empresas foi criada e os dados foram migrados.';
    } else {
        $success = true;
        $message = 'A tabela usuarios_empresas já existe. Nenhuma migração necessária.';
    }
} catch (PDOException $e) {
    $error = 'Erro ao executar migração: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migração - Usuários Empresas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header <?= $success ? 'bg-success' : 'bg-danger' ?> text-white">
                        <h4 class="mb-0">Migração - Usuários Empresas</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <strong>Sucesso!</strong><br>
                                <?= htmlspecialchars($message) ?>
                            </div>
                            <a href="pages/usuarios.php" class="btn btn-primary">Ir para Usuários</a>
                        <?php else: ?>
                            <div class="alert alert-danger">
                                <strong>Erro!</strong><br>
                                <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

