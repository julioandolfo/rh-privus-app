<?php
/**
 * Executa Migração das Tabelas de OpenAI
 * Execute este arquivo UMA VEZ para criar as tabelas necessárias
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Apenas admins podem executar
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['tipo'] !== 'admin') {
    die('Acesso negado. Apenas administradores podem executar migrações.');
}

$pdo = getDB();
$erros = [];
$sucessos = [];

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Migração OpenAI</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        h1 { color: #333; border-bottom: 2px solid #667eea; padding-bottom: 10px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .btn { display: inline-block; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
        .btn:hover { background: #5568d3; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>";

echo "<h1>🤖 Migração: OpenAI - Geração de Vagas com IA</h1>";

try {
    // Verifica se já existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'openai_config'");
    if ($stmt->rowCount() > 0) {
        echo "<div class='info'><strong>ℹ️ Atenção:</strong> As tabelas já existem. Esta migração só criará tabelas que ainda não existem.</div>";
    }
    
    // Lê o arquivo SQL
    $sql_file = __DIR__ . '/migracao_openai_config.sql';
    
    if (!file_exists($sql_file)) {
        throw new Exception('Arquivo de migração não encontrado: ' . $sql_file);
    }
    
    $sql = file_get_contents($sql_file);
    
    if (empty($sql)) {
        throw new Exception('Arquivo de migração está vazio');
    }
    
    // Executa a migração
    echo "<div class='info'>Executando migração...</div>";
    
    // Divide as queries e executa uma por uma
    $queries = array_filter(array_map('trim', explode(';', $sql)));
    $total = 0;
    
    foreach ($queries as $query) {
        $query = trim($query);
        if (empty($query) || strpos($query, '--') === 0) {
            continue;
        }
        
        try {
            $pdo->exec($query);
            $total++;
        } catch (PDOException $e) {
            // Ignora erros de tabela já existente
            if (strpos($e->getMessage(), 'already exists') === false && 
                strpos($e->getMessage(), 'Duplicate') === false) {
                $erros[] = "Erro ao executar query: " . $e->getMessage();
            }
        }
    }
    
    echo "<div class='success'><strong>✅ Sucesso!</strong> Migração executada com sucesso ($total queries).</div>";
    
    // Verifica tabelas criadas
    $tabelas_esperadas = ['openai_config', 'vagas_geradas_ia', 'openai_prompt_templates', 'openai_rate_limit'];
    $tabelas_criadas = [];
    
    foreach ($tabelas_esperadas as $tabela) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$tabela'");
        if ($stmt->rowCount() > 0) {
            $tabelas_criadas[] = $tabela;
        }
    }
    
    echo "<div class='success'>";
    echo "<strong>📊 Tabelas Criadas/Verificadas:</strong><br>";
    echo "<ul>";
    foreach ($tabelas_criadas as $tabela) {
        echo "<li>✅ $tabela</li>";
    }
    echo "</ul>";
    echo "</div>";
    
    // Verifica templates padrão
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM openai_prompt_templates");
    $total_templates = $stmt->fetch()['total'];
    
    echo "<div class='success'>";
    echo "<strong>📝 Templates de Prompt:</strong> $total_templates templates cadastrados<br>";
    echo "</div>";
    
    // Próximos passos
    echo "<div class='info'>";
    echo "<strong>📋 Próximos Passos:</strong><br><br>";
    echo "<ol>";
    echo "<li>Acesse a página de <a href='pages/configuracoes_openai.php'><strong>Configurações OpenAI</strong></a></li>";
    echo "<li>Obtenha uma API Key em <a href='https://platform.openai.com/api-keys' target='_blank'>platform.openai.com/api-keys</a></li>";
    echo "<li>Configure a API Key e escolha o modelo (recomendado: <strong>gpt-4o-mini</strong>)</li>";
    echo "<li>Teste a conexão para verificar se está funcionando</li>";
    echo "<li>Acesse <a href='pages/vaga_add.php'><strong>Nova Vaga</strong></a> e use a geração com IA!</li>";
    echo "</ol>";
    echo "</div>";
    
    // Informações adicionais
    echo "<div class='info'>";
    echo "<strong>💰 Estimativa de Custos:</strong><br><br>";
    echo "<ul>";
    echo "<li><strong>GPT-4o:</strong> ~\$0.015 por vaga (melhor qualidade)</li>";
    echo "<li><strong>GPT-4o-mini:</strong> ~\$0.002 por vaga (recomendado - ótimo custo/benefício)</li>";
    echo "<li><strong>GPT-3.5-turbo:</strong> ~\$0.0005 por vaga (mais econômico)</li>";
    echo "</ul>";
    echo "<small>Para 100 vagas/mês com GPT-4o-mini: ~\$0.20</small>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='error'><strong>❌ Erro:</strong> " . $e->getMessage() . "</div>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

if (!empty($erros)) {
    echo "<div class='error'>";
    echo "<strong>⚠️ Erros Encontrados:</strong><br>";
    echo "<ul>";
    foreach ($erros as $erro) {
        echo "<li>$erro</li>";
    }
    echo "</ul>";
    echo "</div>";
}

echo "<a href='pages/configuracoes_openai.php' class='btn'>Ir para Configurações OpenAI</a> ";
echo "<a href='pages/dashboard.php' class='btn' style='background: #6c757d;'>Voltar ao Dashboard</a>";

echo "</body></html>";
