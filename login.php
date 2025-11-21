<?php
/**
 * Página de Login - Metronic Theme
 */

// Inicia sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/functions.php';

// Se já estiver logado, redireciona
if (isset($_SESSION['usuario'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';
    
    if (empty($email) || empty($senha)) {
        $error = 'Preencha todos os campos!';
    } else {
        try {
            $pdo = getDB();
            
            // Verifica e cria a tabela usuarios_empresas se não existir
            try {
                $stmt_check = $pdo->query("SHOW TABLES LIKE 'usuarios_empresas'");
                if ($stmt_check->rowCount() == 0) {
                    // Cria tabela de relacionamento muitos-para-muitos
                    $pdo->exec("
                        CREATE TABLE usuarios_empresas (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            usuario_id INT NOT NULL,
                            empresa_id INT NOT NULL,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
                            FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
                            UNIQUE KEY uk_usuario_empresa (usuario_id, empresa_id),
                            INDEX idx_usuario (usuario_id),
                            INDEX idx_empresa (empresa_id)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                    ");
                    
                    // Migra dados existentes da coluna empresa_id para a nova tabela
                    $pdo->exec("
                        INSERT INTO usuarios_empresas (usuario_id, empresa_id)
                        SELECT id, empresa_id 
                        FROM usuarios 
                        WHERE empresa_id IS NOT NULL
                    ");
                }
            } catch (PDOException $e) {
                // Ignora erro se a tabela já existir
            }
            
            // Tenta login como usuário do sistema primeiro
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ? AND status = 'ativo'");
            $stmt->execute([$email]);
            $usuario = $stmt->fetch();
            
            if ($usuario && password_verify($senha, $usuario['senha_hash'])) {
                // Atualiza último login
                $stmt = $pdo->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?");
                $stmt->execute([$usuario['id']]);
                
                // Busca empresas do usuário
                $stmt_empresas = $pdo->prepare("
                    SELECT empresa_id 
                    FROM usuarios_empresas 
                    WHERE usuario_id = ?
                ");
                $stmt_empresas->execute([$usuario['id']]);
                $empresas_ids = $stmt_empresas->fetchAll(PDO::FETCH_COLUMN);
                
                // Cria sessão
                $_SESSION['usuario'] = [
                    'id' => $usuario['id'],
                    'nome' => $usuario['nome'],
                    'email' => $usuario['email'],
                    'role' => $usuario['role'],
                    'empresa_id' => $usuario['empresa_id'], // Mantém para compatibilidade
                    'empresas_ids' => $empresas_ids, // Array com IDs das empresas
                    'setor_id' => $usuario['setor_id'] ?? null,
                    'colaborador_id' => $usuario['colaborador_id']
                ];
                
                header('Location: index.php');
                exit;
            } else {
                // Tenta login como colaborador (CPF ou email_pessoal)
                $cpf_limpo = preg_replace('/[^0-9]/', '', $email);
                $stmt = $pdo->prepare("
                    SELECT c.*, u.id as usuario_id, u.role, u.empresa_id as usuario_empresa_id
                    FROM colaboradores c
                    LEFT JOIN usuarios u ON c.id = u.colaborador_id
                    WHERE (c.cpf = ? OR c.email_pessoal = ?) 
                    AND c.status = 'ativo'
                    AND c.senha_hash IS NOT NULL
                ");
                $stmt->execute([$cpf_limpo, $email]);
                $colaborador = $stmt->fetch();
                
                if ($colaborador && password_verify($senha, $colaborador['senha_hash'])) {
                    // Se já existe usuário vinculado, usa ele
                    if ($colaborador['usuario_id']) {
                        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
                        $stmt->execute([$colaborador['usuario_id']]);
                        $usuario = $stmt->fetch();
                        
                        // Busca empresas do usuário
                        $stmt_empresas = $pdo->prepare("
                            SELECT empresa_id 
                            FROM usuarios_empresas 
                            WHERE usuario_id = ?
                        ");
                        $stmt_empresas->execute([$usuario['id']]);
                        $empresas_ids = $stmt_empresas->fetchAll(PDO::FETCH_COLUMN);
                        
                        $_SESSION['usuario'] = [
                            'id' => $usuario['id'],
                            'nome' => $usuario['nome'],
                            'email' => $usuario['email'],
                            'role' => $usuario['role'],
                            'empresa_id' => $usuario['empresa_id'], // Mantém para compatibilidade
                            'empresas_ids' => $empresas_ids, // Array com IDs das empresas
                            'setor_id' => $usuario['setor_id'] ?? null,
                            'colaborador_id' => $colaborador['id']
                        ];
                    } else {
                        // Cria sessão como colaborador direto
                        $_SESSION['usuario'] = [
                            'id' => null,
                            'nome' => $colaborador['nome_completo'],
                            'email' => $colaborador['email_pessoal'] ?? '',
                            'role' => 'COLABORADOR',
                            'empresa_id' => $colaborador['empresa_id'],
                            'setor_id' => $colaborador['setor_id'],
                            'colaborador_id' => $colaborador['id']
                        ];
                    }
                    
                    header('Location: index.php');
                    exit;
                } else {
                    $error = 'Email/CPF ou senha incorretos!';
                }
            }
        } catch (PDOException $e) {
            $error = 'Erro ao fazer login: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="light">
<head>
    <meta charset="utf-8" />
    <title>Login - RH Privus</title>
    <meta name="description" content="Sistema de Gestão de RH - Grupo Privus" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="canonical" href="https://preview.keenthemes.com/metronic8" />
    <link rel="shortcut icon" href="assets/media/logos/favicon.png" />
    
    <!--begin::PWA Manifest-->
    <link rel="manifest" href="manifest.php">
    <meta name="theme-color" content="#009ef7">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="RH Privus">
    <link rel="apple-touch-icon" href="assets/media/logos/favicon.png">
    <!--end::PWA Manifest-->
    
    
    <!--begin::Fonts-->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700" />
    <!--end::Fonts-->
    
    <!--begin::Global Stylesheets Bundle(used by all pages)-->
    <link href="assets/plugins/global/plugins.bundle.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/style.bundle.css" rel="stylesheet" type="text/css" />
    <!--end::Global Stylesheets Bundle-->
    
    <style>
        body {
            background-image: url('assets/media/misc/auth-bg.png');
            background-repeat: no-repeat;
            background-position: center center;
            background-size: cover;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<!--end::Head-->
<!--begin::Body-->
<body id="kt_body" class="bg-body">
    <!--begin::Main-->
    <div class="d-flex flex-center flex-column flex-row-fluid min-vh-100 p-10">
        <!--begin::Card-->
        <div class="card shadow-lg w-100 w-lg-500px">
            <!--begin::Card body-->
            <div class="card-body p-10">
                <!--begin::Form-->
                <form class="form w-100" novalidate="novalidate" method="POST" id="kt_sign_in_form">
                    <!--begin::Heading-->
                    <div class="text-center mb-11">
                        <!--begin::Title-->
                        <h1 class="text-dark fw-bolder mb-3">Entrar no Sistema</h1>
                        <!--end::Title-->
                        <!--begin::Subtitle-->
                        <div class="text-gray-600 fw-semibold fs-6">Use seu email ou CPF para acessar</div>
                        <!--end::Subtitle-->
                    </div>
                    <!--end::Heading-->
                    
                    <?php if ($error): ?>
                    <!--begin::Alert-->
                    <div class="alert alert-danger d-flex align-items-center p-5 mb-10">
                        <i class="ki-duotone ki-shield-cross fs-2hx text-danger me-4">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <div class="d-flex flex-column">
                            <h4 class="mb-1 text-danger">Erro</h4>
                            <span><?= htmlspecialchars($error) ?></span>
                        </div>
                    </div>
                    <!--end::Alert-->
                    <?php endif; ?>
                    
                    <!--begin::Input group=-->
                    <div class="fv-row mb-8">
                        <!--begin::Email-->
                        <input type="text" placeholder="Email ou CPF" name="email" autocomplete="off" class="form-control form-control-solid" required />
                        <!--end::Email-->
                    </div>
                    <!--end::Input group=-->
                    <div class="fv-row mb-3">
                        <!--begin::Password-->
                        <input type="password" placeholder="Senha" name="senha" autocomplete="off" class="form-control form-control-solid" required />
                        <!--end::Password-->
                    </div>
                    <!--end::Input group=-->
                    <!--begin::Wrapper-->
                    <div class="d-flex flex-stack flex-wrap gap-3 fs-base fw-semibold mb-8">
                        <div></div>
                        <!--begin::Link-->
                        <a href="#" class="link-primary">Esqueceu a senha?</a>
                        <!--end::Link-->
                    </div>
                    <!--end::Wrapper-->
                    <!--begin::Submit button-->
                    <div class="d-grid mb-10">
                        <button type="submit" id="kt_sign_in_submit" class="btn btn-primary" data-kt-indicator="off">
                            <!--begin::Indicator label-->
                            <span class="indicator-label">Entrar</span>
                            <!--end::Indicator label-->
                            <!--begin::Indicator progress-->
                            <span class="indicator-progress">Aguarde...
                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                            <!--end::Indicator progress-->
                        </button>
                    </div>
                    <!--end::Submit button-->
                </form>
                <!--end::Form-->
            </div>
            <!--end::Card body-->
            <!--begin::Card footer-->
            <div class="card-footer border-0 pt-0 pb-10">
                <!--begin::Links-->
                <div class="d-flex flex-center flex-wrap fs-base fw-semibold">
                    <a href="#" class="px-5 text-gray-600 text-hover-primary" target="_blank">Termos</a>
                    <a href="#" class="px-5 text-gray-600 text-hover-primary" target="_blank">Planos</a>
                    <a href="#" class="px-5 text-gray-600 text-hover-primary" target="_blank">Contato</a>
                </div>
                <!--end::Links-->
            </div>
            <!--end::Card footer-->
        </div>
        <!--end::Card-->
    </div>
    <!--end::Main-->
    
    <!--begin::Javascript-->
    <script>var hostUrl = "assets/";</script>
    <!--begin::Global Javascript Bundle(used by all pages)-->
    <script src="assets/plugins/global/plugins.bundle.js"></script>
    <script src="assets/js/scripts.bundle.js"></script>
    <!--end::Global Javascript Bundle-->
    
    <!--begin::OneSignal SDK-->
    <script src="https://cdn.onesignal.com/sdks/OneSignalSDK.js" async></script>
    <script src="assets/js/onesignal-init.js"></script>
    <!--end::OneSignal SDK-->
    
    <!--begin::PWA Service Worker-->
    <script src="assets/js/pwa-service-worker.js"></script>
    <!--end::PWA Service Worker-->
    
    <!--begin::PWA Install Prompt-->
    <script src="assets/js/pwa-install-prompt.js"></script>
    <!--end::PWA Install Prompt-->
    
    <script>
        // Loading no botão de login
        document.addEventListener('DOMContentLoaded', function() {
            const submitBtn = document.getElementById('kt_sign_in_submit');
            const form = document.getElementById('kt_sign_in_form');
            
            if (submitBtn && form) {
                form.addEventListener('submit', function(e) {
                    const email = form.querySelector('[name="email"]').value;
                    const senha = form.querySelector('[name="senha"]').value;
                    
                    if (email && senha) {
                        submitBtn.setAttribute('data-kt-indicator', 'on');
                        submitBtn.disabled = true;
                    }
                });
            }
        });
    </script>
    <!--end::Javascript-->
</body>
<!--end::Body-->
</html>
