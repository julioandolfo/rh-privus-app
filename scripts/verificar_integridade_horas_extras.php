<?php
/**
 * Script de Verificação de Integridade - Horas Extras
 * 
 * Verifica inconsistências entre as tabelas horas_extras e banco_horas_movimentacoes
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Apenas ADMIN pode executar
if (!has_role(['ADMIN'])) {
    die('Acesso negado. Apenas administradores podem executar este script.');
}

$pdo = getDB();

echo "=== VERIFICAÇÃO DE INTEGRIDADE - HORAS EXTRAS ===\n\n";

$problemas_encontrados = [];

// 1. Verifica horas extras sem colaborador (órfãos)
echo "1. Verificando horas extras sem colaborador...\n";
$stmt = $pdo->query("
    SELECT h.* 
    FROM horas_extras h
    LEFT JOIN colaboradores c ON h.colaborador_id = c.id
    WHERE c.id IS NULL
");
$horas_sem_colaborador = $stmt->fetchAll();
if (count($horas_sem_colaborador) > 0) {
    $problemas_encontrados[] = [
        'tipo' => 'Horas extras sem colaborador',
        'quantidade' => count($horas_sem_colaborador),
        'detalhes' => $horas_sem_colaborador
    ];
    echo "   ⚠️  Encontradas " . count($horas_sem_colaborador) . " horas extras sem colaborador\n";
} else {
    echo "   ✅ Nenhuma hora extra órfã encontrada\n";
}

// 2. Verifica horas extras tipo banco_horas sem movimentação correspondente
echo "\n2. Verificando horas extras tipo banco_horas sem movimentação...\n";
$stmt = $pdo->query("
    SELECT h.* 
    FROM horas_extras h
    WHERE h.tipo_pagamento = 'banco_horas'
    AND (h.banco_horas_movimentacao_id IS NULL 
         OR NOT EXISTS (
             SELECT 1 FROM banco_horas_movimentacoes bhm 
             WHERE bhm.id = h.banco_horas_movimentacao_id
         ))
");
$horas_sem_movimentacao = $stmt->fetchAll();
if (count($horas_sem_movimentacao) > 0) {
    $problemas_encontrados[] = [
        'tipo' => 'Horas extras banco_horas sem movimentação',
        'quantidade' => count($horas_sem_movimentacao),
        'detalhes' => $horas_sem_movimentacao
    ];
    echo "   ⚠️  Encontradas " . count($horas_sem_movimentacao) . " horas extras sem movimentação\n";
} else {
    echo "   ✅ Todas as horas extras banco_horas têm movimentação correspondente\n";
}

// 3. Verifica movimentações tipo hora_extra sem registro em horas_extras
echo "\n3. Verificando movimentações hora_extra sem registro em horas_extras...\n";
$stmt = $pdo->query("
    SELECT bhm.* 
    FROM banco_horas_movimentacoes bhm
    WHERE bhm.origem = 'hora_extra'
    AND (bhm.origem_id IS NULL 
         OR NOT EXISTS (
             SELECT 1 FROM horas_extras h 
             WHERE h.id = bhm.origem_id
         ))
");
$movimentacoes_sem_hora_extra = $stmt->fetchAll();
if (count($movimentacoes_sem_hora_extra) > 0) {
    $problemas_encontrados[] = [
        'tipo' => 'Movimentações hora_extra sem registro em horas_extras',
        'quantidade' => count($movimentacoes_sem_hora_extra),
        'detalhes' => $movimentacoes_sem_hora_extra
    ];
    echo "   ⚠️  Encontradas " . count($movimentacoes_sem_hora_extra) . " movimentações sem registro\n";
} else {
    echo "   ✅ Todas as movimentações hora_extra têm registro correspondente\n";
}

// 4. Verifica inconsistências de empresa_id
echo "\n4. Verificando inconsistências de empresa...\n";
$stmt = $pdo->query("
    SELECT h.id, h.colaborador_id, c.empresa_id as empresa_colaborador, 
           h.tipo_pagamento, h.data_trabalho
    FROM horas_extras h
    INNER JOIN colaboradores c ON h.colaborador_id = c.id
    WHERE c.empresa_id IS NULL
");
$horas_colaborador_sem_empresa = $stmt->fetchAll();
if (count($horas_colaborador_sem_empresa) > 0) {
    $problemas_encontrados[] = [
        'tipo' => 'Horas extras de colaboradores sem empresa',
        'quantidade' => count($horas_colaborador_sem_empresa),
        'detalhes' => $horas_colaborador_sem_empresa
    ];
    echo "   ⚠️  Encontradas " . count($horas_colaborador_sem_empresa) . " horas extras de colaboradores sem empresa\n";
} else {
    echo "   ✅ Todas as horas extras têm colaborador com empresa\n";
}

// 5. Verifica horas extras com quantidade negativa que não são remoções
echo "\n5. Verificando horas extras com quantidade negativa...\n";
$stmt = $pdo->query("
    SELECT h.*, c.nome_completo
    FROM horas_extras h
    LEFT JOIN colaboradores c ON h.colaborador_id = c.id
    WHERE h.quantidade_horas < 0
    AND h.tipo_pagamento != 'banco_horas'
    ORDER BY h.data_trabalho DESC
");
$horas_negativas_estranhas = $stmt->fetchAll();
if (count($horas_negativas_estranhas) > 0) {
    $problemas_encontrados[] = [
        'tipo' => 'Horas extras negativas não relacionadas a banco de horas',
        'quantidade' => count($horas_negativas_estranhas),
        'detalhes' => $horas_negativas_estranhas
    ];
    echo "   ⚠️  Encontradas " . count($horas_negativas_estranhas) . " horas extras negativas suspeitas\n";
} else {
    echo "   ✅ Nenhuma inconsistência encontrada\n";
}

// 6. Estatísticas gerais
echo "\n=== ESTATÍSTICAS GERAIS ===\n";
$stmt = $pdo->query("SELECT COUNT(*) as total FROM horas_extras");
$total_horas_extras = $stmt->fetch()['total'];
echo "Total de horas extras: " . $total_horas_extras . "\n";

$stmt = $pdo->query("SELECT COUNT(*) as total FROM horas_extras WHERE tipo_pagamento = 'banco_horas'");
$total_banco_horas = $stmt->fetch()['total'];
echo "Horas extras tipo banco_horas: " . $total_banco_horas . "\n";

$stmt = $pdo->query("SELECT COUNT(*) as total FROM horas_extras WHERE tipo_pagamento = 'dinheiro' OR tipo_pagamento IS NULL");
$total_dinheiro = $stmt->fetch()['total'];
echo "Horas extras tipo dinheiro: " . $total_dinheiro . "\n";

$stmt = $pdo->query("
    SELECT COUNT(*) as total 
    FROM banco_horas_movimentacoes 
    WHERE origem = 'hora_extra'
");
$total_movimentacoes_hora_extra = $stmt->fetch()['total'];
echo "Movimentações de banco de horas origem hora_extra: " . $total_movimentacoes_hora_extra . "\n";

// Resumo final
echo "\n=== RESUMO ===\n";
if (count($problemas_encontrados) === 0) {
    echo "✅ Nenhum problema encontrado! Sistema está íntegro.\n";
} else {
    echo "⚠️  Foram encontrados " . count($problemas_encontrados) . " tipo(s) de problema(s):\n\n";
    foreach ($problemas_encontrados as $problema) {
        echo "   - " . $problema['tipo'] . ": " . $problema['quantidade'] . " registro(s)\n";
        
        // Mostra alguns exemplos
        if ($problema['quantidade'] <= 5) {
            echo "     IDs: ";
            $ids = array_map(function($item) {
                return $item['id'] ?? 'N/A';
            }, $problema['detalhes']);
            echo implode(', ', $ids) . "\n";
        } else {
            echo "     Primeiros 5 IDs: ";
            $ids = array_map(function($item) {
                return $item['id'] ?? 'N/A';
            }, array_slice($problema['detalhes'], 0, 5));
            echo implode(', ', $ids) . " ...\n";
        }
    }
    
    echo "\n💡 Recomendações:\n";
    echo "   - Execute este script periodicamente para monitorar a integridade\n";
    echo "   - Revise os registros problemáticos manualmente\n";
    echo "   - Considere criar um script de correção automática se necessário\n";
}

echo "\n=== FIM DA VERIFICAÇÃO ===\n";

