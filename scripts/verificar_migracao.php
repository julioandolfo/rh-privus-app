<?php
/**
 * Script para verificar se a migração foi executada e debugar horas extras
 */

require_once __DIR__ . '/../includes/functions.php';

echo "=== VERIFICAÇÃO DO SISTEMA DE HORAS EXTRAS ===\n\n";

$pdo = getDB();

// 1. Verifica se o campo existe
echo "1. Verificando campo fechamento_pagamento_id...\n";
$stmt = $pdo->query("SHOW COLUMNS FROM horas_extras LIKE 'fechamento_pagamento_id'");
$campo_existe = $stmt->fetch();

if ($campo_existe) {
    echo "   ✅ Campo existe!\n\n";
} else {
    echo "   ❌ Campo NÃO existe! Execute a migração:\n";
    echo "      mysql -u root -p seu_banco < migracao_controle_horas_extras_pagas.sql\n\n";
    exit(1);
}

// 2. Conta horas extras totais
echo "2. Contando horas extras cadastradas...\n";
$stmt = $pdo->query("SELECT COUNT(*) as total FROM horas_extras");
$total_he = $stmt->fetch()['total'];
echo "   Total de horas extras: {$total_he}\n\n";

if ($total_he == 0) {
    echo "   ⚠️  Nenhuma hora extra cadastrada!\n\n";
    exit(0);
}

// 3. Horas extras não pagas
echo "3. Horas extras NÃO PAGAS (disponíveis para fechamento)...\n";
$stmt = $pdo->query("
    SELECT COUNT(*) as total,
           COALESCE(SUM(quantidade_horas), 0) as total_horas,
           COALESCE(SUM(valor_total), 0) as total_valor
    FROM horas_extras 
    WHERE fechamento_pagamento_id IS NULL
    AND (tipo_pagamento = 'dinheiro' OR tipo_pagamento IS NULL)
");
$nao_pagas = $stmt->fetch();
echo "   Registros não pagos: {$nao_pagas['total']}\n";
echo "   Total de horas: " . number_format($nao_pagas['total_horas'], 2, ',', '.') . "h\n";
echo "   Valor total: R$ " . number_format($nao_pagas['total_valor'], 2, ',', '.') . "\n\n";

// 4. Horas extras JÁ PAGAS
echo "4. Horas extras JÁ PAGAS (incluídas em fechamentos)...\n";
$stmt = $pdo->query("
    SELECT COUNT(*) as total,
           COALESCE(SUM(quantidade_horas), 0) as total_horas,
           COALESCE(SUM(valor_total), 0) as total_valor
    FROM horas_extras 
    WHERE fechamento_pagamento_id IS NOT NULL
");
$pagas = $stmt->fetch();
echo "   Registros pagos: {$pagas['total']}\n";
echo "   Total de horas: " . number_format($pagas['total_horas'], 2, ',', '.') . "h\n";
echo "   Valor total: R$ " . number_format($pagas['total_valor'], 2, ',', '.') . "\n\n";

// 5. Lista últimas 10 horas extras não pagas
echo "5. Últimas 10 horas extras NÃO PAGAS:\n";
$stmt = $pdo->query("
    SELECT he.id, he.colaborador_id, c.nome_completo, he.data_trabalho, 
           he.quantidade_horas, he.valor_total, he.tipo_pagamento, he.fechamento_pagamento_id,
           he.created_at
    FROM horas_extras he
    INNER JOIN colaboradores c ON he.colaborador_id = c.id
    WHERE he.fechamento_pagamento_id IS NULL
    AND (he.tipo_pagamento = 'dinheiro' OR he.tipo_pagamento IS NULL)
    ORDER BY he.data_trabalho DESC, he.created_at DESC
    LIMIT 10
");
$horas_nao_pagas = $stmt->fetchAll();

if (empty($horas_nao_pagas)) {
    echo "   Nenhuma hora extra não paga encontrada.\n\n";
} else {
    echo "   ID | Colaborador | Data | Horas | Valor | Tipo\n";
    echo "   " . str_repeat("-", 80) . "\n";
    foreach ($horas_nao_pagas as $he) {
        $tipo = $he['tipo_pagamento'] ?? 'dinheiro';
        printf("   %3d | %-30s | %s | %5.2fh | R$ %8.2f | %s\n", 
            $he['id'],
            substr($he['nome_completo'], 0, 30),
            date('d/m/Y', strtotime($he['data_trabalho'])),
            $he['quantidade_horas'],
            $he['valor_total'],
            $tipo
        );
    }
    echo "\n";
}

// 6. Verifica solicitações pendentes
echo "6. Solicitações de colaboradores PENDENTES:\n";
$stmt = $pdo->query("SELECT COUNT(*) as total FROM solicitacoes_horas_extras WHERE status = 'pendente'");
$pendentes = $stmt->fetch()['total'];
echo "   Solicitações pendentes: {$pendentes}\n";
if ($pendentes > 0) {
    echo "   ⚠️  Execute: php scripts/aprovar_solicitacoes_pendentes.php\n";
}
echo "\n";

echo "=== FIM DA VERIFICAÇÃO ===\n";

