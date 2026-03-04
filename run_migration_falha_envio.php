<?php
$config = include __DIR__ . '/config/db.php';
$pdo = new PDO(
    'mysql:host=' . $config['host'] . ';dbname=' . $config['dbname'] . ';charset=utf8mb4',
    $config['username'],
    $config['password']
);

$sqls = [
    "ALTER TABLE contratos_signatarios ADD COLUMN IF NOT EXISTS falha_envio TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'e-mail bounce/recusado'",
    "ALTER TABLE contratos_signatarios ADD COLUMN IF NOT EXISTS motivo_falha TEXT NULL COMMENT 'Mensagem de erro do servidor de e-mail'",
    "ALTER TABLE contratos_signatarios ADD COLUMN IF NOT EXISTS email_enviado_em DATETIME NULL COMMENT 'Quando o Autentique enviou o e-mail'",
    "ALTER TABLE contratos_signatarios ADD COLUMN IF NOT EXISTS substituido_por INT NULL COMMENT 'ID do signatario que substituiu este'",
    "ALTER TABLE contratos_signatarios ADD COLUMN IF NOT EXISTS substituido_em DATETIME NULL COMMENT 'Data da substituicao'",
];

foreach ($sqls as $sql) {
    try {
        $pdo->exec($sql);
        echo "OK: " . substr($sql, 0, 80) . "\n";
    } catch (Exception $e) {
        echo "ERR: " . $e->getMessage() . "\n";
    }
}
echo "Migração concluída!\n";
