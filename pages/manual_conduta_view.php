<?php
/**
 * Visualização do Manual de Conduta
 */

$page_title = 'Manual de Conduta Privus';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/manual_conduta_functions.php';

require_page_permission('manual_conduta_view.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Busca manual ativo
$manual = get_manual_conduta_ativo();

// Registra visualização
if ($manual) {
    registrar_visualizacao_manual('manual');
}

require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Manual de Conduta Privus</h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Manual de Conduta</li>
            </ul>
        </div>
        <div class="d-flex align-items-center gap-2 gap-lg-3">
            <?php if (has_role(['ADMIN'])): ?>
            <a href="manual_conduta_edit.php" class="btn btn-primary">
                <i class="ki-duotone ki-pencil fs-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                Editar Manual
            </a>
            <?php endif; ?>
            <a href="manual_conduta_exportar_pdf.php" class="btn btn-light" target="_blank">
                <i class="ki-duotone ki-file-down fs-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                    <span class="path4"></span>
                </i>
                Exportar PDF
            </a>
            <button onclick="window.print()" class="btn btn-light">
                <i class="ki-duotone ki-printer fs-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                </i>
                Imprimir
            </button>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <?php if ($manual): ?>
        <!--begin::Card - Manual-->
        <div class="card mb-5">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1"><?= htmlspecialchars($manual['titulo']) ?></span>
                    <?php if ($manual['versao']): ?>
                    <span class="text-muted fw-semibold fs-7">
                        Versão <?= htmlspecialchars($manual['versao']) ?>
                    </span>
                    <?php endif; ?>
                </h3>
                <div class="card-toolbar">
                    <?php if ($manual['publicado_em']): ?>
                    <span class="badge badge-light-success">
                        <i class="ki-duotone ki-check-circle fs-7 me-1">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Publicado em <?= formatar_data($manual['publicado_em'], 'd/m/Y H:i') ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body pt-5">
                <!-- Índice Navegável (se houver títulos) -->
                <?php
                // Extrai títulos do conteúdo para criar índice
                preg_match_all('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/i', $manual['conteudo'], $titulos);
                if (!empty($titulos[1])):
                ?>
                <div class="card card-flush bg-light-info mb-10">
                    <div class="card-body">
                        <h4 class="fw-bold mb-5">
                            <i class="ki-duotone ki-list fs-2 text-info me-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                            Índice
                        </h4>
                        <ul class="list-unstyled mb-0" id="indice_navegavel">
                            <?php 
                            $indice_num = 1;
                            foreach ($titulos[1] as $titulo): 
                                $titulo_texto = strip_tags($titulo);
                                $titulo_id = 'secao_' . $indice_num;
                                $indice_num++;
                            ?>
                            <li class="mb-2">
                                <a href="#<?= $titulo_id ?>" class="text-gray-700 text-hover-primary fw-semibold">
                                    <?= htmlspecialchars($titulo_texto) ?>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Conteúdo do Manual -->
                <div class="manual-content" id="conteudo_manual">
                    <?php
                    // Adiciona IDs aos títulos para navegação
                    $conteudo_com_ids = preg_replace_callback(
                        '/<h([1-6])[^>]*>(.*?)<\/h[1-6]>/i',
                        function($matches) use (&$indice_num) {
                            static $contador = 0;
                            $contador++;
                            $nivel = $matches[1];
                            $titulo = $matches[2];
                            $id = 'secao_' . $contador;
                            return '<h' . $nivel . ' id="' . $id . '">' . $titulo . '</h' . $nivel . '>';
                        },
                        $manual['conteudo']
                    );
                    echo $conteudo_com_ids;
                    ?>
                </div>
            </div>
            <?php if ($manual['publicado_por_nome']): ?>
            <div class="card-footer border-0 pt-0">
                <div class="d-flex align-items-center text-muted fs-7">
                    <i class="ki-duotone ki-user fs-6 me-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Publicado por: <?= htmlspecialchars($manual['publicado_por_nome']) ?>
                    <?php if ($manual['updated_at']): ?>
                    <span class="ms-4">
                        <i class="ki-duotone ki-time fs-6 me-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Última atualização: <?= formatar_data($manual['updated_at'], 'd/m/Y H:i') ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <!--end::Card-->
        <?php else: ?>
        <!--begin::Card - Sem Manual-->
        <div class="card">
            <div class="card-body text-center py-20">
                <i class="ki-duotone ki-document fs-3x text-muted mb-5">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                    <span class="path4"></span>
                </i>
                <h3 class="text-gray-800 fw-bold mb-2">Manual de Conduta não disponível</h3>
                <p class="text-gray-600 mb-5">
                    O manual de conduta ainda não foi publicado.
                    <?php if (has_role(['ADMIN'])): ?>
                    <a href="manual_conduta_edit.php" class="text-primary">Clique aqui para criar o manual.</a>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <!--end::Card-->
        <?php endif; ?>
        
    </div>
</div>
<!--end::Post-->

<style>
/* Estilos para impressão */
@media print {
    .toolbar, .card-header, .card-footer, .btn, #indice_navegavel {
        display: none !important;
    }
    .manual-content {
        font-size: 12pt;
        line-height: 1.6;
    }
    .manual-content h1 {
        font-size: 18pt;
        margin-top: 20pt;
        margin-bottom: 10pt;
    }
    .manual-content h2 {
        font-size: 16pt;
        margin-top: 16pt;
        margin-bottom: 8pt;
    }
    .manual-content h3 {
        font-size: 14pt;
        margin-top: 12pt;
        margin-bottom: 6pt;
    }
    .manual-content p {
        margin-bottom: 8pt;
    }
    .manual-content ul, .manual-content ol {
        margin-bottom: 8pt;
        padding-left: 20pt;
    }
}

/* Scroll suave para navegação */
html {
    scroll-behavior: smooth;
}

/* Estilo para seções ao navegar */
.manual-content h1[id],
.manual-content h2[id],
.manual-content h3[id],
.manual-content h4[id],
.manual-content h5[id],
.manual-content h6[id] {
    scroll-margin-top: 100px;
}

/* Highlight ao navegar */
.manual-content h1[id]:target,
.manual-content h2[id]:target,
.manual-content h3[id]:target,
.manual-content h4[id]:target,
.manual-content h5[id]:target,
.manual-content h6[id]:target {
    background-color: #fff3cd;
    padding: 10px;
    border-left: 4px solid #ffc700;
    transition: all 0.3s ease;
}
</style>

<script>
// Scroll suave e highlight ao clicar no índice
document.querySelectorAll('#indice_navegavel a').forEach(link => {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        const targetId = this.getAttribute('href').substring(1);
        const targetElement = document.getElementById(targetId);
        
        if (targetElement) {
            targetElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
            
            // Remove highlight anterior
            document.querySelectorAll('.manual-content h1[id]:target, .manual-content h2[id]:target, .manual-content h3[id]:target').forEach(el => {
                el.style.backgroundColor = '';
            });
            
            // Adiciona highlight temporário
            setTimeout(() => {
                targetElement.style.backgroundColor = '#fff3cd';
                setTimeout(() => {
                    targetElement.style.backgroundColor = '';
                }, 2000);
            }, 500);
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

