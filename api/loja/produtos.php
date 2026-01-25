<?php
/**
 * API de produtos da loja
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/loja_functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

$usuario = $_SESSION['usuario'];
$pdo = getDB();

try {
    $action = $_REQUEST['action'] ?? 'listar';
    
    switch ($action) {
        case 'listar':
            $filtros = [
                'categoria_id' => $_GET['categoria_id'] ?? null,
                'destaque' => isset($_GET['destaque']),
                'novidade' => isset($_GET['novidade']),
                'em_estoque' => isset($_GET['em_estoque']),
                'busca' => $_GET['busca'] ?? null,
                'ordem' => $_GET['ordem'] ?? null,
                'limite' => $_GET['limite'] ?? null
            ];
            
            $produtos = loja_get_produtos($filtros);
            
            // Adiciona info de pontos do usuário
            $colaborador_id = $usuario['colaborador_id'] ?? null;
            $meus_pontos = $colaborador_id ? obter_pontos(null, $colaborador_id) : ['pontos_totais' => 0];
            
            echo json_encode([
                'success' => true,
                'produtos' => $produtos,
                'meus_pontos' => $meus_pontos['pontos_totais']
            ]);
            break;
            
        case 'detalhe':
            $id = intval($_GET['id'] ?? 0);
            if ($id <= 0) {
                throw new Exception('ID inválido');
            }
            
            $produto = loja_get_produto($id);
            if (!$produto) {
                throw new Exception('Produto não encontrado');
            }
            
            // Info de pontos e wishlist
            $colaborador_id = $usuario['colaborador_id'] ?? null;
            $meus_pontos = $colaborador_id ? obter_pontos(null, $colaborador_id) : ['pontos_totais' => 0];
            $na_wishlist = $colaborador_id ? loja_is_wishlist($colaborador_id, $id) : false;
            
            echo json_encode([
                'success' => true,
                'produto' => $produto,
                'meus_pontos' => $meus_pontos['pontos_totais'],
                'na_wishlist' => $na_wishlist
            ]);
            break;
            
        case 'verificar':
            $id = intval($_GET['id'] ?? 0);
            $quantidade = intval($_GET['quantidade'] ?? 1);
            $colaborador_id = $usuario['colaborador_id'] ?? null;
            
            if (!$colaborador_id) {
                throw new Exception('Usuário não vinculado a um colaborador');
            }
            
            $verificacao = loja_pode_resgatar($colaborador_id, $id, $quantidade);
            echo json_encode($verificacao);
            break;
            
        // CRUD para admin
        case 'salvar':
            if (!in_array($usuario['role'], ['ADMIN', 'RH', 'GESTOR'])) {
                throw new Exception('Sem permissão');
            }
            
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método inválido');
            }
            
            $id = intval($_POST['id'] ?? 0);
            $categoria_id = intval($_POST['categoria_id'] ?? 0);
            $nome = trim($_POST['nome'] ?? '');
            $descricao = trim($_POST['descricao'] ?? '');
            $descricao_curta = trim($_POST['descricao_curta'] ?? '');
            $pontos_necessarios = intval($_POST['pontos_necessarios'] ?? 0);
            $estoque = $_POST['estoque'] !== '' ? intval($_POST['estoque']) : null;
            $limite_por_colaborador = $_POST['limite_por_colaborador'] !== '' ? intval($_POST['limite_por_colaborador']) : null;
            $disponivel_de = !empty($_POST['disponivel_de']) ? $_POST['disponivel_de'] : null;
            $disponivel_ate = !empty($_POST['disponivel_ate']) ? $_POST['disponivel_ate'] : null;
            $destaque = isset($_POST['destaque']) && $_POST['destaque'] == '1' ? 1 : 0;
            $ativo = isset($_POST['ativo']) && $_POST['ativo'] == '1' ? 1 : 0;
            
            if (empty($nome)) {
                throw new Exception('Nome é obrigatório');
            }
            
            if ($categoria_id <= 0) {
                throw new Exception('Categoria é obrigatória');
            }
            
            if ($pontos_necessarios <= 0) {
                throw new Exception('Pontos necessários deve ser maior que zero');
            }
            
            // Upload de imagem
            $imagem = null;
            if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = __DIR__ . '/../../uploads/loja/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $ext = strtolower(pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                
                if (!in_array($ext, $allowed)) {
                    throw new Exception('Formato de imagem não permitido');
                }
                
                $filename = 'produto_' . time() . '_' . uniqid() . '.' . $ext;
                $filepath = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['imagem']['tmp_name'], $filepath)) {
                    $imagem = 'uploads/loja/' . $filename;
                }
            }
            
            if ($id > 0) {
                // Update
                $sql = "UPDATE loja_produtos SET 
                        categoria_id = ?, nome = ?, descricao = ?, descricao_curta = ?,
                        pontos_necessarios = ?, estoque = ?, limite_por_colaborador = ?,
                        disponivel_de = ?, disponivel_ate = ?, destaque = ?, ativo = ?";
                $params = [$categoria_id, $nome, $descricao, $descricao_curta, $pontos_necessarios,
                           $estoque, $limite_por_colaborador, $disponivel_de, $disponivel_ate, $destaque, $ativo];
                
                if ($imagem) {
                    $sql .= ", imagem = ?";
                    $params[] = $imagem;
                }
                
                $sql .= " WHERE id = ?";
                $params[] = $id;
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                
                echo json_encode(['success' => true, 'message' => 'Produto atualizado com sucesso!']);
            } else {
                // Insert
                $stmt = $pdo->prepare("
                    INSERT INTO loja_produtos 
                    (categoria_id, nome, descricao, descricao_curta, imagem, pontos_necessarios, 
                     estoque, limite_por_colaborador, disponivel_de, disponivel_ate, destaque, ativo, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$categoria_id, $nome, $descricao, $descricao_curta, $imagem, $pontos_necessarios,
                               $estoque, $limite_por_colaborador, $disponivel_de, $disponivel_ate, $destaque, $ativo, $usuario['id']]);
                
                echo json_encode(['success' => true, 'message' => 'Produto criado com sucesso!', 'id' => $pdo->lastInsertId()]);
            }
            break;
            
        case 'excluir':
            if (!in_array($usuario['role'], ['ADMIN', 'RH'])) {
                throw new Exception('Sem permissão');
            }
            
            $id = intval($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new Exception('ID inválido');
            }
            
            // Verifica se tem resgates
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM loja_resgates WHERE produto_id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetch()['total'] > 0) {
                // Apenas desativa
                $stmt = $pdo->prepare("UPDATE loja_produtos SET ativo = 0 WHERE id = ?");
                $stmt->execute([$id]);
                echo json_encode(['success' => true, 'message' => 'Produto desativado (possui resgates vinculados)']);
            } else {
                $stmt = $pdo->prepare("DELETE FROM loja_produtos WHERE id = ?");
                $stmt->execute([$id]);
                echo json_encode(['success' => true, 'message' => 'Produto excluído com sucesso!']);
            }
            break;
            
        default:
            throw new Exception('Ação inválida');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
