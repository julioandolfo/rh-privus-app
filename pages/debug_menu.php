<?php
/**
 * Debug: Verificar Menu do Colaborador
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

$usuario = $_SESSION['usuario'] ?? null;

echo "<h1>Debug Menu Colaborador</h1>";

if (!$usuario) {
    echo "<p style='color: red;'>❌ Usuário não autenticado</p>";
    exit;
}

echo "<h2>Informações do Usuário:</h2>";
echo "<pre>";
print_r($usuario);
echo "</pre>";

echo "<h2>Verificações:</h2>";
echo "<ul>";

echo "<li>is_colaborador(): " . (is_colaborador() ? '✅ SIM' : '❌ NÃO') . "</li>";
echo "<li>Role: " . ($usuario['role'] ?? 'N/A') . "</li>";
echo "<li>Colaborador ID: " . ($usuario['colaborador_id'] ?? 'N/A') . "</li>";
echo "<li>can_access_page('meu_perfil.php'): " . (can_access_page('meu_perfil.php') ? '✅ SIM' : '❌ NÃO') . "</li>";

echo "</ul>";

echo "<h2>Permissões Configuradas para 'meu_perfil.php':</h2>";
echo "<pre>";
$permissions = get_page_permissions();
echo "Permissões definidas: ";
var_dump($permissions['meu_perfil.php'] ?? 'NÃO DEFINIDO');
echo "</pre>";

echo "<h2>Teste de Acesso:</h2>";
if (is_colaborador()) {
    echo "<p style='color: green;'>✅ O menu 'Meu Perfil' DEVERIA aparecer!</p>";
    echo "<a href='meu_perfil.php' class='btn btn-primary' style='display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;'>Ir para Meu Perfil</a>";
} else {
    echo "<p style='color: red;'>❌ O menu 'Meu Perfil' NÃO vai aparecer porque is_colaborador() retornou FALSE</p>";
}
?>
