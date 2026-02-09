<?php
/**
 * Script de diagnóstico para verificar colaboradores disponíveis
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/select_colaborador.php';

$pdo = getDB();
$usuario = $_SESSION['usuario'];

echo "<h2>🔍 Diagnóstico de Colaboradores para Promoções</h2>";
echo "<hr>";

echo "<h3>📋 Dados do Usuário Logado</h3>";
echo "<pre>";
print_r([
    'ID' => $usuario['id'] ?? 'N/A',
    'Nome' => $usuario['nome'] ?? 'N/A',
    'Role' => $usuario['role'] ?? 'N/A',
    'Empresa ID' => $usuario['empresa_id'] ?? 'N/A',
    'Empresas IDs' => $usuario['empresas_ids'] ?? 'N/A',
]);
echo "</pre>";
echo "<hr>";

echo "<h3>👥 Colaboradores Retornados pela Função get_colaboradores_disponiveis()</h3>";
$colaboradores_raw = get_colaboradores_disponiveis($pdo, $usuario);
echo "<strong>Total encontrado:</strong> " . count($colaboradores_raw) . "<br><br>";
echo "<pre>";
print_r($colaboradores_raw);
echo "</pre>";
echo "<hr>";

echo "<h3>🔍 Filtrando apenas Colaboradores (tipo='colaborador')</h3>";
$colaboradores_filtrados = [];
foreach ($colaboradores_raw as $colab) {
    if ($colab['tipo'] === 'colaborador' && !empty($colab['colaborador_id'])) {
        $colaboradores_filtrados[] = $colab;
    }
}
echo "<strong>Total após filtro:</strong> " . count($colaboradores_filtrados) . "<br><br>";
echo "<pre>";
print_r($colaboradores_filtrados);
echo "</pre>";
echo "<hr>";

echo "<h3>💰 Adicionando Dados de Salário</h3>";
$colaboradores_finais = [];
foreach ($colaboradores_filtrados as $colab) {
    $colaborador_id = $colab['colaborador_id'];
    echo "Buscando dados para colaborador ID: {$colaborador_id}<br>";
    
    $stmt = $pdo->prepare("SELECT id, salario, empresa_id FROM colaboradores WHERE id = ?");
    $stmt->execute([$colaborador_id]);
    $colab_data = $stmt->fetch();
    
    if ($colab_data) {
        echo "✅ Encontrado: Salário = R$ " . number_format($colab_data['salario'], 2, ',', '.') . "<br>";
        $colaboradores_finais[] = array_merge($colab, [
            'salario' => $colab_data['salario'],
            'empresa_id' => $colab_data['empresa_id']
        ]);
    } else {
        echo "❌ NÃO encontrado no banco<br>";
    }
    echo "<br>";
}
echo "<strong>Total final:</strong> " . count($colaboradores_finais) . "<br><br>";
echo "<pre>";
print_r($colaboradores_finais);
echo "</pre>";
echo "<hr>";

echo "<h3>📊 Consulta SQL Direta (Todos os Colaboradores Ativos)</h3>";
if ($usuario['role'] === 'ADMIN') {
    $stmt = $pdo->query("SELECT id, nome_completo, status, empresa_id, salario FROM colaboradores WHERE status = 'ativo'");
} elseif ($usuario['role'] === 'RH') {
    if (isset($usuario['empresas_ids']) && !empty($usuario['empresas_ids'])) {
        $placeholders = implode(',', array_fill(0, count($usuario['empresas_ids']), '?'));
        $stmt = $pdo->prepare("SELECT id, nome_completo, status, empresa_id, salario FROM colaboradores WHERE empresa_id IN ($placeholders) AND status = 'ativo'");
        $stmt->execute($usuario['empresas_ids']);
    } elseif (!empty($usuario['empresa_id'])) {
        $stmt = $pdo->prepare("SELECT id, nome_completo, status, empresa_id, salario FROM colaboradores WHERE empresa_id = ? AND status = 'ativo'");
        $stmt->execute([$usuario['empresa_id']]);
    }
}

if (isset($stmt)) {
    $todos_colaboradores = $stmt->fetchAll();
    echo "<strong>Total de colaboradores ativos:</strong> " . count($todos_colaboradores) . "<br><br>";
    echo "<pre>";
    print_r($todos_colaboradores);
    echo "</pre>";
} else {
    echo "⚠️ Nenhuma query executada (verifique role e dados do usuário)";
}

echo "<hr>";
echo "<p><a href='pages/promocoes.php'>← Voltar para Promoções</a></p>";
