<?php
/**
 * Trecho de código para migração automática do campo 'regiao'
 * 
 * Adicione este código no início dos arquivos colaborador_add.php e colaborador_edit.php
 * logo após a linha: $pdo = getDB();
 */

// Verifica e adiciona a coluna 'regiao' se não existir
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM colaboradores LIKE 'regiao'");
    if (!$stmt->fetch()) {
        // Coluna não existe, vamos criar
        $pdo->exec("ALTER TABLE colaboradores ADD COLUMN regiao VARCHAR(100) NULL COMMENT 'Região do colaborador para uso em contratos' AFTER estado_endereco");
        error_log('Migração automática: Coluna regiao adicionada à tabela colaboradores');
    }
} catch (Exception $e) {
    error_log('Erro na migração automática do campo regiao: ' . $e->getMessage());
}
