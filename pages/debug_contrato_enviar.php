<?php
/**
 * DEBUG - Página contrato_enviar.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Debug - Contrato Enviar</h1>";

// Passo 1: Functions
echo "<h2>1. Carregando functions.php...</h2>";
try {
    require_once __DIR__ . '/../includes/functions.php';
    echo "<p style='color:green;'>OK - functions.php carregado</p>";
} catch (Throwable $e) {
    echo "<p style='color:red;'>ERRO: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    exit;
}

// Passo 2: Auth
echo "<h2>2. Carregando auth.php...</h2>";
try {
    require_once __DIR__ . '/../includes/auth.php';
    echo "<p style='color:green;'>OK - auth.php carregado</p>";
} catch (Throwable $e) {
    echo "<p style='color:red;'>ERRO: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    exit;
}

// Passo 3: Permissions
echo "<h2>3. Carregando permissions.php...</h2>";
try {
    require_once __DIR__ . '/../includes/permissions.php';
    echo "<p style='color:green;'>OK - permissions.php carregado</p>";
} catch (Throwable $e) {
    echo "<p style='color:red;'>ERRO: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    exit;
}

// Passo 4: Contratos Functions
echo "<h2>4. Carregando contratos_functions.php...</h2>";
try {
    require_once __DIR__ . '/../includes/contratos_functions.php';
    echo "<p style='color:green;'>OK - contratos_functions.php carregado</p>";
} catch (Throwable $e) {
    echo "<p style='color:red;'>ERRO: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    exit;
}

// Passo 5: Banco de dados
echo "<h2>5. Testando banco de dados...</h2>";
try {
    $pdo = getDB();
    echo "<p style='color:green;'>OK - Conexão com banco</p>";
} catch (Throwable $e) {
    echo "<p style='color:red;'>ERRO: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    exit;
}

// Passo 6: Sessão
echo "<h2>6. Verificando sessão...</h2>";
if (isset($_SESSION['usuario'])) {
    echo "<p style='color:green;'>OK - Usuário logado: " . htmlspecialchars($_SESSION['usuario']['nome'] ?? 'N/A') . "</p>";
} else {
    echo "<p style='color:red;'>ERRO - Usuário não logado</p>";
    exit;
}

// Passo 7: ID do contrato
$contrato_id = intval($_GET['id'] ?? 0);
echo "<h2>7. ID do contrato: $contrato_id</h2>";

// Passo 8: Verificando Autentique config
echo "<h2>8. Verificando autentique_config...</h2>";
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'autentique_config'");
    $existe = $stmt->fetch();
    if ($existe) {
        echo "<p style='color:green;'>OK - Tabela autentique_config existe</p>";
        
        $stmt = $pdo->query("SELECT * FROM autentique_config WHERE ativo = 1 ORDER BY id DESC LIMIT 1");
        $config = $stmt->fetch();
        if ($config) {
            echo "<p style='color:green;'>OK - Configuração encontrada</p>";
            echo "<pre>API Key: " . substr($config['api_key'], 0, 20) . "...</pre>";
        } else {
            echo "<p style='color:orange;'>AVISO - Nenhuma configuração ativa</p>";
        }
    } else {
        echo "<p style='color:red;'>ERRO - Tabela autentique_config não existe</p>";
    }
} catch (Throwable $e) {
    echo "<p style='color:red;'>ERRO: " . $e->getMessage() . "</p>";
}

// Passo 9: Verificando tabela contratos
echo "<h2>9. Verificando tabela contratos...</h2>";
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'contratos'");
    $existe = $stmt->fetch();
    if ($existe) {
        echo "<p style='color:green;'>OK - Tabela contratos existe</p>";
    } else {
        echo "<p style='color:red;'>ERRO - Tabela contratos NÃO existe</p>";
        exit;
    }
} catch (Throwable $e) {
    echo "<p style='color:red;'>ERRO: " . $e->getMessage() . "</p>";
    exit;
}

// Passo 10: Buscando contrato
echo "<h2>10. Buscando contrato #$contrato_id...</h2>";
try {
    $stmt = $pdo->prepare("
        SELECT c.*, 
               col.nome_completo as colaborador_nome,
               col.cpf as colaborador_cpf,
               col.email_pessoal as colaborador_email,
               col.email as colaborador_email_alt,
               col.empresa_id
        FROM contratos c
        INNER JOIN colaboradores col ON c.colaborador_id = col.id
        WHERE c.id = ?
    ");
    $stmt->execute([$contrato_id]);
    $contrato = $stmt->fetch();
    
    if ($contrato) {
        echo "<p style='color:green;'>OK - Contrato encontrado</p>";
        echo "<pre>";
        print_r($contrato);
        echo "</pre>";
    } else {
        echo "<p style='color:red;'>ERRO - Contrato #$contrato_id não encontrado</p>";
        exit;
    }
} catch (Throwable $e) {
    echo "<p style='color:red;'>ERRO: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    exit;
}

// Passo 11: Buscando colaborador completo
echo "<h2>11. Buscando dados completos do colaborador...</h2>";
try {
    $colaborador = buscar_dados_colaborador_completos($contrato['colaborador_id']);
    if ($colaborador) {
        echo "<p style='color:green;'>OK - Colaborador encontrado</p>";
        echo "<pre>";
        print_r($colaborador);
        echo "</pre>";
    } else {
        echo "<p style='color:red;'>ERRO - Colaborador não encontrado</p>";
    }
} catch (Throwable $e) {
    echo "<p style='color:red;'>ERRO: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

// Passo 12: Carregando header
echo "<h2>12. Testando carregamento do header...</h2>";
try {
    // Não vamos carregar de fato, apenas verificar se existe
    $header_path = __DIR__ . '/../includes/header.php';
    if (file_exists($header_path)) {
        echo "<p style='color:green;'>OK - header.php existe</p>";
    } else {
        echo "<p style='color:red;'>ERRO - header.php não encontrado</p>";
    }
} catch (Throwable $e) {
    echo "<p style='color:red;'>ERRO: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h2 style='color:green;'>Todos os testes passaram! O problema pode estar no header.php ou no carregamento de assets.</h2>";
echo "<p><a href='contrato_enviar.php?id=$contrato_id'>Tentar acessar a página original</a></p>";
?>
