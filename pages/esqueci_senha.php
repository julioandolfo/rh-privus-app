<?php
/**
 * Página de Recuperação de Senha - Solicitar
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/functions.php';

// Se já estiver logado, redireciona
if (isset($_SESSION['usuario'])) {
    header('Location: index.php');
    exit;
}

$success = false;
$error = '';
$email_enviado = false;

// Processamento será feito via AJAX
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark" data-bs-theme="dark">
<head>
    <meta charset="utf-8" />
    <title>Recuperar Senha - RH Privus</title>
    <meta name="description" content="Recuperação de senha - RH Privus" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="shortcut icon" href="assets/avatar-privus.png" />
    
    <!--begin::PWA Manifest-->
    <link rel="manifest" href="manifest.php">
    <meta name="theme-color" content="#009ef7">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="RH Privus">
    <link rel="apple-touch-icon" href="assets/avatar-privus.png">
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
            background-color: #1E1E2D;
            background-image: none;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Card de recuperação ocupa 100% da largura em mobile */
        @media (max-width: 991.98px) {
            body {
                padding: 0 !important;
            }
            
            .recovery-container {
                padding: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                margin: 0 !important;
            }
            
            .recovery-card {
                width: 100% !important;
                max-width: 100% !important;
                margin: 0 !important;
                border-radius: 0 !important;
                border-left: none !important;
                border-right: none !important;
                box-shadow: none !important;
            }
            
            .recovery-card .card-body {
                padding: 2rem 1.5rem !important;
            }
        }
    </style>
</head>
<body id="kt_body" class="bg-body">
    <!--begin::Main-->
    <div class="d-flex flex-center flex-column flex-row-fluid min-vh-100 p-5 p-lg-10 recovery-container">
        <!--begin::Card-->
        <div class="card shadow-lg w-100 w-lg-500px recovery-card">
            <!--begin::Card body-->
            <div class="card-body p-5 p-lg-10">
                <!--begin::Heading-->
                <div class="text-center mb-11">
                    <h1 class="text-dark fw-bolder mb-3">Recuperar Senha</h1>
                    <div class="text-gray-600 fw-semibold fs-6">Informe seu email ou CPF para receber o link de recuperação</div>
                </div>
                <!--end::Heading-->
                
                <?php if ($email_enviado): ?>
                <!--begin::Success Alert-->
                <div class="alert alert-success d-flex align-items-center p-5 mb-10">
                    <i class="ki-duotone ki-check-circle fs-2hx text-success me-4">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    <div class="d-flex flex-column">
                        <h4 class="mb-1 text-dark">Email enviado!</h4>
                        <span>Se o email informado estiver cadastrado, você receberá um link para redefinir sua senha. Verifique sua caixa de entrada e spam.</span>
                    </div>
                </div>
                <!--end::Success Alert-->
                
                <div class="text-center">
                    <a href="login.php" class="btn btn-primary">Voltar para Login</a>
                </div>
                <?php else: ?>
                
                <?php if ($error): ?>
                <!--begin::Error Alert-->
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
                <!--end::Error Alert-->
                <?php endif; ?>
                
                <!--begin::Form-->
                <form class="form w-100" id="kt_recovery_form">
                    <!--begin::Input group-->
                    <div class="fv-row mb-8">
                        <input type="text" 
                               placeholder="Email ou CPF" 
                               name="email" 
                               id="recovery_email"
                               autocomplete="email" 
                               class="form-control form-control-solid" 
                               required />
                    </div>
                    <!--end::Input group-->
                    
                    <!--begin::Submit button-->
                    <div class="d-grid mb-10">
                        <button type="submit" id="kt_recovery_submit" class="btn btn-primary" data-kt-indicator="off">
                            <span class="indicator-label">Enviar Link de Recuperação</span>
                            <span class="indicator-progress">Enviando...
                                <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                            </span>
                        </button>
                    </div>
                    <!--end::Submit button-->
                    
                    <!--begin::Back to login-->
                    <div class="text-center">
                        <a href="login.php" class="link-primary fw-semibold">Voltar para Login</a>
                    </div>
                    <!--end::Back to login-->
                </form>
                <!--end::Form-->
                <?php endif; ?>
            </div>
            <!--end::Card body-->
        </div>
        <!--end::Card-->
    </div>
    <!--end::Main-->
    
    <!--begin::Javascript-->
    <script>var hostUrl = "assets/";</script>
    <script src="assets/plugins/global/plugins.bundle.js"></script>
    <script src="assets/js/scripts.bundle.js"></script>
    <!--end::Global Javascript Bundle-->
    
    <!--begin::SweetAlert2-->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!--end::SweetAlert2-->
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const submitBtn = document.getElementById('kt_recovery_submit');
            const form = document.getElementById('kt_recovery_form');
            
            if (submitBtn && form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const email = document.getElementById('recovery_email').value.trim();
                    
                    if (!email) {
                        Swal.fire({
                            text: 'Por favor, informe seu email ou CPF!',
                            icon: "warning",
                            buttonsStyling: false,
                            confirmButtonText: "Ok",
                            customClass: {
                                confirmButton: "btn btn-primary"
                            }
                        });
                        return;
                    }
                    
                    submitBtn.setAttribute('data-kt-indicator', 'on');
                    submitBtn.disabled = true;
                    
                    const formData = new FormData();
                    formData.append('email', email);
                    
                    fetch('../api/recuperar_senha/solicitar.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Mostra mensagem de sucesso e recarrega
                            Swal.fire({
                                text: data.message,
                                icon: "success",
                                buttonsStyling: false,
                                confirmButtonText: "Ok",
                                customClass: {
                                    confirmButton: "btn btn-primary"
                                }
                            }).then(function() {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                text: data.message || 'Erro ao processar solicitação',
                                icon: "error",
                                buttonsStyling: false,
                                confirmButtonText: "Ok",
                                customClass: {
                                    confirmButton: "btn btn-primary"
                                }
                            });
                            submitBtn.removeAttribute('data-kt-indicator');
                            submitBtn.disabled = false;
                        }
                    })
                    .catch(error => {
                        console.error('Erro:', error);
                        Swal.fire({
                            text: 'Erro ao processar solicitação',
                            icon: "error",
                            buttonsStyling: false,
                            confirmButtonText: "Ok",
                            customClass: {
                                confirmButton: "btn btn-primary"
                            }
                        });
                        submitBtn.removeAttribute('data-kt-indicator');
                        submitBtn.disabled = false;
                    });
                });
            }
        });
    </script>
</body>
</html>

