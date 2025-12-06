<?php
/**
 * Funções Auxiliares do Sistema
 */

// Carrega o autoloader do Composer (apenas uma vez)
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

/**
 * Conecta ao banco de dados
 */
function getDB() {
    static $pdo = null;
    
    if ($pdo === null) {
        $config = include __DIR__ . '/../config/db.php';
        
        if (empty($config['host']) || empty($config['dbname'])) {
            die('Sistema não configurado. Execute install.php primeiro.');
        }
        
        try {
            $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4";
            $pdo = new PDO($dsn, $config['username'], $config['password']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            die('Erro ao conectar ao banco de dados: ' . $e->getMessage());
        }
    }
    
    return $pdo;
}

/**
 * Obtém a base URL do projeto
 */
function get_base_url() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    
    // Detecta o caminho base automaticamente
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
    
    // Remove query string do REQUEST_URI
    $requestUri = strtok($requestUri, '?');
    
    // Detecta se está em /rh-privus/ (localhost) ou /rh/ (produção)
    $basePath = '/rh'; // Padrão para produção
    
    // Prioridade 1: REQUEST_URI (mais confiável)
    if (!empty($requestUri)) {
        if (strpos($requestUri, '/rh-privus/') !== false || strpos($requestUri, '/rh-privus') !== false) {
            $basePath = '/rh-privus';
        } elseif (strpos($requestUri, '/rh/') !== false || preg_match('#^/rh[^a-z]#', $requestUri)) {
            $basePath = '/rh';
        }
    }
    
    // Prioridade 2: SCRIPT_NAME (fallback)
    if ($basePath === '/rh' && !empty($scriptName)) {
        if (strpos($scriptName, '/rh-privus') !== false) {
            $basePath = '/rh-privus';
        } elseif (strpos($scriptName, '/rh') !== false && strpos($scriptName, '/rh-privus') === false) {
            $basePath = '/rh';
        }
    }
    
    // Prioridade 3: DOCUMENT_ROOT (último recurso)
    if ($basePath === '/rh' && !empty($documentRoot)) {
        if (strpos($documentRoot, 'rh-privus') !== false) {
            $basePath = '/rh-privus';
        }
    }
    
    return $protocol . '://' . $host . $basePath;
}

/**
 * Obtém caminho relativo para login
 */
function get_login_url() {
    // Detecta se estamos em uma subpasta (pages, includes, etc)
    $script_path = $_SERVER['SCRIPT_NAME'];
    
    // Se estamos em pages/, volta para raiz
    if (strpos($script_path, '/pages/') !== false) {
        return '../login.php';
    }
    // Se estamos em includes/, volta para raiz
    if (strpos($script_path, '/includes/') !== false) {
        return '../login.php';
    }
    // Se estamos na raiz
    return 'login.php';
}

/**
 * Verifica permissões do usuário
 */
function check_permission($role_required) {
    if (!isset($_SESSION['usuario'])) {
        header('Location: ' . get_login_url());
        exit;
    }
    
    $user_role = $_SESSION['usuario']['role'];
    
    // ADMIN tem acesso total
    if ($user_role === 'ADMIN') {
        return true;
    }
    
    // Verifica se o role requerido está na lista permitida
    $permissions = [
        'RH' => ['RH'],
        'GESTOR' => ['GESTOR'],
        'COLABORADOR' => ['COLABORADOR']
    ];
    
    if ($user_role === $role_required) {
        return true;
    }
    
    // RH pode acessar tudo exceto admin
    if ($user_role === 'RH' && $role_required !== 'ADMIN') {
        return true;
    }
    
    return false;
}

/**
 * Verifica se usuário está logado
 */
function require_login() {
    // Garante que sessão está iniciada
    if (!function_exists('iniciar_sessao_30_dias')) {
        require_once __DIR__ . '/session_config.php';
    }
    if (session_status() === PHP_SESSION_NONE) {
        iniciar_sessao_30_dias();
    }
    
    // Renova sessão se usuário estiver logado
    if (isset($_SESSION['usuario'])) {
        if (!function_exists('verificar_e_renovar_sessao')) {
            require_once __DIR__ . '/session_config.php';
        }
        verificar_e_renovar_sessao();
    }
    
    if (!isset($_SESSION['usuario'])) {
        // Limpa qualquer output buffer antes do redirect
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Headers para evitar cache
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Obtém URL de login
        $loginUrl = get_login_url();
        
        // Se URL é relativa, converte para absoluta se necessário
        if (strpos($loginUrl, 'http') !== 0) {
            // Usa get_base_url() se disponível para detectar automaticamente o caminho
            if (function_exists('get_base_url')) {
                $baseUrl = get_base_url();
                // Se loginUrl já começa com /, usa direto
                if (strpos($loginUrl, '/') === 0) {
                    $loginUrl = $baseUrl . $loginUrl;
                } else {
                    // Se é relativa (ex: login.php ou ../login.php), resolve
                    if (strpos($loginUrl, '../') === 0) {
                        // Remove ../ e adiciona base URL
                        $loginUrl = str_replace('../', '', $loginUrl);
                        $loginUrl = $baseUrl . '/' . $loginUrl;
                    } else {
                        // Relativa ao diretório atual
                        $loginUrl = $baseUrl . '/' . $loginUrl;
                    }
                }
            } else {
                // Fallback: detecta manualmente
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'];
                $requestUri = $_SERVER['REQUEST_URI'] ?? '';
                $requestUri = strtok($requestUri, '?');
                
                // Detecta se está em /rh-privus/ (localhost) ou /rh/ (produção)
                if (strpos($requestUri, '/rh-privus') !== false) {
                    $basePath = '/rh-privus';
                } else {
                    $basePath = '/rh';
                }
                
                // Se loginUrl já começa com /, usa direto
                if (strpos($loginUrl, '/') === 0) {
                    $loginUrl = $protocol . '://' . $host . $loginUrl;
                } else {
                    // Se é relativa (ex: login.php ou ../login.php), resolve
                    if (strpos($loginUrl, '../') === 0) {
                        // Remove ../ e adiciona base path
                        $loginUrl = str_replace('../', '', $loginUrl);
                        $loginUrl = $protocol . '://' . $host . $basePath . '/' . $loginUrl;
                    } else {
                        // Relativa ao diretório atual
                        $loginUrl = $protocol . '://' . $host . $basePath . '/' . $loginUrl;
                    }
                }
            }
        }
        
        header('Location: ' . $loginUrl, true, 302);
        exit;
    }
}

/**
 * Formata data para exibição
 */
function formatar_data($data, $formato = 'd/m/Y') {
    if (empty($data) || $data === '0000-00-00') {
        return '-';
    }
    $date = DateTime::createFromFormat('Y-m-d', $data);
    return $date ? $date->format($formato) : $data;
}

/**
 * Formata CPF
 */
function formatar_cpf($cpf) {
    if (empty($cpf) || $cpf === null) {
        return '';
    }
    $cpf = preg_replace('/[^0-9]/', '', (string)$cpf);
    if (strlen($cpf) === 11) {
        return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
    }
    return $cpf;
}

/**
 * Formata CNPJ
 */
function formatar_cnpj($cnpj) {
    if (empty($cnpj) || $cnpj === null) {
        return '';
    }
    $cnpj = preg_replace('/[^0-9]/', '', (string)$cnpj);
    if (strlen($cnpj) === 14) {
        return substr($cnpj, 0, 2) . '.' . substr($cnpj, 2, 3) . '.' . substr($cnpj, 5, 3) . '/' . substr($cnpj, 8, 4) . '-' . substr($cnpj, 12, 2);
    }
    return $cnpj;
}

/**
 * Formata telefone
 */
function formatar_telefone($telefone) {
    if (empty($telefone) || $telefone === null) {
        return '';
    }
    $telefone = preg_replace('/[^0-9]/', '', (string)$telefone);
    if (strlen($telefone) === 11) {
        return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 5) . '-' . substr($telefone, 7);
    } elseif (strlen($telefone) === 10) {
        return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 4) . '-' . substr($telefone, 6);
    }
    return $telefone;
}

/**
 * Formata valor monetário (R$)
 */
function formatar_moeda($valor) {
    if (empty($valor) || $valor === null) {
        return 'R$ 0,00';
    }
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

/**
 * Formata tamanho do arquivo para exibição
 */
function format_file_size($bytes) {
    if (empty($bytes) || $bytes === null || $bytes === 0) {
        return '0 bytes';
    }
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2, ',', '.') . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2, ',', '.') . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2, ',', '.') . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

/**
 * Sanitiza entrada
 */
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Retorna mensagem de alerta (Metronic Theme)
 */
function get_alert($tipo, $mensagem) {
    $alert_classes = [
        'success' => 'alert-success',
        'error' => 'alert-danger',
        'warning' => 'alert-warning',
        'info' => 'alert-info'
    ];
    
    $icons = [
        'success' => '<i class="ki-duotone ki-check-circle fs-2hx text-success me-4"><span class="path1"></span><span class="path2"></span></i>',
        'error' => '<i class="ki-duotone ki-cross-circle fs-2hx text-danger me-4"><span class="path1"></span><span class="path2"></span></i>',
        'warning' => '<i class="ki-duotone ki-information-5 fs-2hx text-warning me-4"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>',
        'info' => '<i class="ki-duotone ki-information fs-2hx text-info me-4"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>'
    ];
    
    $titles = [
        'success' => 'Sucesso',
        'error' => 'Erro',
        'warning' => 'Atenção',
        'info' => 'Informação'
    ];
    
    $alert_class = $alert_classes[$tipo] ?? $alert_classes['info'];
    $icon = $icons[$tipo] ?? $icons['info'];
    $title = $titles[$tipo] ?? 'Informação';
    
    return '<div class="alert alert-dismissible ' . $alert_class . ' d-flex align-items-center p-5 mb-10" role="alert">
                ' . $icon . '
                <div class="d-flex flex-column">
                    <h4 class="mb-1 text-' . ($tipo === 'success' ? 'success' : ($tipo === 'error' ? 'danger' : ($tipo === 'warning' ? 'warning' : 'info'))) . '">' . htmlspecialchars($title) . '</h4>
                    <span>' . htmlspecialchars($mensagem) . '</span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
}

/**
 * Redireciona com mensagem
 */
function redirect($url, $mensagem = null, $tipo = 'success') {
    if ($mensagem) {
        $_SESSION['alert'] = ['tipo' => $tipo, 'mensagem' => $mensagem];
    }
    
    // Se URL começa com /, remove para tornar relativa
    if (strpos($url, '/') === 0) {
        $url = ltrim($url, '/');
    }
    
    // Se estamos em pages/ e a URL não começa com ../ ou http
    $current_dir = dirname($_SERVER['PHP_SELF']);
    if (strpos($current_dir, '/pages') !== false && strpos($url, '../') !== 0 && strpos($url, 'http') !== 0) {
        // Remove 'pages/' do início da URL se existir
        if (strpos($url, 'pages/') === 0) {
            $url = substr($url, 6); // Remove 'pages/'
        }
    }
    
    header('Location: ' . $url);
    exit;
}

/**
 * Obtém alerta da sessão e limpa
 */
function get_session_alert() {
    if (isset($_SESSION['alert'])) {
        $alert = $_SESSION['alert'];
        unset($_SESSION['alert']);
        return get_alert($alert['tipo'], $alert['mensagem']);
    }
    return '';
}

