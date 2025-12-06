<?php
/**
 * Visualização do FAQ - Manual de Conduta
 */

$page_title = 'FAQ - Manual de Conduta';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/manual_conduta_functions.php';

// Função helper para destacar termo em HTML (preserva tags HTML)
if (!function_exists('destacar_termo_html')) {
    function destacar_termo_html($html, $termo) {
        if (empty($termo)) {
            return $html;
        }
        
        // Extrai apenas o texto visível (sem tags) para verificar se o termo existe
        $texto_sem_tags = strip_tags($html);
        if (stripos($texto_sem_tags, $termo) === false) {
            return $html; // Termo não encontrado
        }
        
        $termo_escaped = preg_quote($termo, '/');
        
        // Processa o HTML preservando tags
        // Divide em partes: tags HTML e texto
        $partes = preg_split('/(<[^>]+>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        $resultado = '';
        
        foreach ($partes as $parte) {
            // Se é uma tag HTML, adiciona sem modificar
            if (preg_match('/^<[^>]+>$/', $parte)) {
                $resultado .= $parte;
            } else {
                // Se é texto, destaca o termo
                $resultado .= preg_replace(
                    '/(' . $termo_escaped . ')/i',
                    '<mark class="bg-warning">$1</mark>',
                    $parte
                );
            }
        }
        
        return $resultado;
    }
}

require_page_permission('faq_view.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Busca FAQs
$termo_busca = $_GET['busca'] ?? '';
$categoria_filtro = $_GET['categoria'] ?? '';

if ($termo_busca) {
    $faqs = buscar_faqs($termo_busca);
} elseif ($categoria_filtro) {
    $faqs = get_faqs_ativas($categoria_filtro);
} else {
    $faqs = get_faqs_ativas();
}

// Agrupa por categoria
$faqs_por_categoria = [];
foreach ($faqs as $faq) {
    $cat = $faq['categoria'] ?: 'Geral';
    if (!isset($faqs_por_categoria[$cat])) {
        $faqs_por_categoria[$cat] = [];
    }
    $faqs_por_categoria[$cat][] = $faq;
}

// Busca categorias
$categorias = get_faq_categorias();

require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">FAQ - Perguntas Frequentes</h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">FAQ</li>
            </ul>
        </div>
        <div class="d-flex align-items-center gap-2 gap-lg-3">
            <?php if (has_role(['ADMIN'])): ?>
            <a href="faq_edit.php" class="btn btn-primary">
                <i class="ki-duotone ki-pencil fs-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                Gerenciar FAQ
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <!--begin::Card - Busca e Filtros-->
        <div class="card mb-5">
            <div class="card-body pt-5">
                <form method="GET" class="d-flex gap-3">
                    <div class="flex-grow-1">
                        <input type="text" name="busca" class="form-control form-control-solid" 
                               placeholder="Buscar perguntas..." 
                               value="<?= htmlspecialchars($termo_busca) ?>" />
                    </div>
                    <?php if (!empty($categorias)): ?>
                    <div style="min-width: 200px;">
                        <select name="categoria" class="form-select form-select-solid">
                            <option value="">Todas as categorias</option>
                            <?php foreach ($categorias as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>" 
                                    <?= $categoria_filtro === $cat ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary">
                        <i class="ki-duotone ki-magnifier fs-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Buscar
                    </button>
                    <?php if ($termo_busca || $categoria_filtro): ?>
                    <a href="faq_view.php" class="btn btn-light">
                        <i class="ki-duotone ki-cross fs-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Limpar
                    </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        <!--end::Card-->
        
        <?php if (empty($faqs)): ?>
        <!--begin::Card - Sem Resultados-->
        <div class="card">
            <div class="card-body text-center py-20">
                <i class="ki-duotone ki-question fs-3x text-muted mb-5">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                <h3 class="text-gray-800 fw-bold mb-2">Nenhuma pergunta encontrada</h3>
                <p class="text-gray-600 mb-5">
                    <?php if ($termo_busca || $categoria_filtro): ?>
                    Tente buscar com outros termos ou limpe os filtros.
                    <?php else: ?>
                    Ainda não há perguntas frequentes cadastradas.
                    <?php if (has_role(['ADMIN'])): ?>
                    <a href="faq_edit.php" class="text-primary">Clique aqui para adicionar perguntas.</a>
                    <?php endif; ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <!--end::Card-->
        <?php else: ?>
        
        <!--begin::Card - FAQs-->
        <div class="card">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">
                        <?php if ($termo_busca): ?>
                        Resultados da busca (<?= count($faqs) ?>)
                        <?php else: ?>
                        Perguntas Frequentes (<?= count($faqs) ?>)
                        <?php endif; ?>
                    </span>
                </h3>
            </div>
            <div class="card-body pt-5">
                <!--begin::Accordion-->
                <div class="accordion accordion-icon-toggle" id="kt_accordion_faq">
                    <?php 
                    $index = 0;
                    foreach ($faqs_por_categoria as $categoria => $faqs_cat): 
                        if (!$termo_busca && count($faqs_por_categoria) > 1):
                    ?>
                    <!--begin::Categoria-->
                    <div class="mb-10">
                        <h4 class="text-gray-800 fw-bold mb-5">
                            <i class="ki-duotone ki-category fs-3 text-primary me-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                            <?= htmlspecialchars($categoria) ?>
                        </h4>
                    </div>
                    <!--end::Categoria-->
                    <?php endif; ?>
                    
                    <?php foreach ($faqs_cat as $faq): 
                        $index++;
                        $faq_id = 'faq_' . $faq['id'];
                        // Registra visualização quando FAQ é expandido
                    ?>
                    <!--begin::Item-->
                    <div class="accordion-item mb-3">
                        <h2 class="accordion-header" id="heading_<?= $faq_id ?>">
                            <button class="accordion-button collapsed" type="button" 
                                    data-bs-toggle="collapse" data-bs-target="#collapse_<?= $faq_id ?>" 
                                    aria-expanded="false" aria-controls="collapse_<?= $faq_id ?>"
                                    onclick="registrarVisualizacaoFAQ(<?= $faq['id'] ?>)">
                                <span class="fw-bold text-gray-800" id="pergunta_<?= $faq_id ?>">
                                    <?php 
                                    if ($termo_busca) {
                                        echo destacar_termo_html($faq['pergunta'], $termo_busca);
                                    } else {
                                        echo $faq['pergunta'];
                                    }
                                    ?>
                                </span>
                            </button>
                        </h2>
                        <div id="collapse_<?= $faq_id ?>" class="accordion-collapse collapse" 
                             aria-labelledby="heading_<?= $faq_id ?>" data-bs-parent="#kt_accordion_faq">
                            <div class="accordion-body">
                                <div class="text-gray-700 mb-5" id="resposta_<?= $faq_id ?>">
                                    <?php 
                                    if ($termo_busca) {
                                        echo destacar_termo_html($faq['resposta'], $termo_busca);
                                    } else {
                                        echo $faq['resposta'];
                                    }
                                    ?>
                                </div>
                                
                                <!-- Feedback Útil -->
                                <div class="separator separator-dashed my-5"></div>
                                <div class="d-flex align-items-center gap-3">
                                    <span class="text-muted fs-7">Esta resposta foi útil?</span>
                                    <button type="button" class="btn btn-sm btn-light-success" 
                                            onclick="marcarUtil(<?= $faq['id'] ?>, true)">
                                        <i class="ki-duotone ki-like fs-5">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        Sim (<?= $faq['util_respondeu_sim'] ?>)
                                    </button>
                                    <button type="button" class="btn btn-sm btn-light-danger" 
                                            onclick="marcarUtil(<?= $faq['id'] ?>, false)">
                                        <i class="ki-duotone ki-dislike fs-5">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        Não (<?= $faq['util_respondeu_nao'] ?>)
                                    </button>
                                    <?php if ($faq['visualizacoes'] > 0): ?>
                                    <span class="text-muted fs-7 ms-auto">
                                        <i class="ki-duotone ki-eye fs-6">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                        </i>
                                        <?= $faq['visualizacoes'] ?> visualizações
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!--end::Item-->
                    <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
                <!--end::Accordion-->
            </div>
        </div>
        <!--end::Card-->
        <?php endif; ?>
        
    </div>
</div>
<!--end::Post-->

<script>
function registrarVisualizacaoFAQ(faqId) {
    // Registra visualização via AJAX
    fetch('api/manual_conduta/visualizacao.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            tipo: 'faq',
            faq_id: faqId
        })
    }).catch(err => console.error('Erro ao registrar visualização:', err));
}

function marcarUtil(faqId, util) {
    fetch('api/manual_conduta/marcar_util.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            faq_id: faqId,
            util: util
        })
    }).then(response => response.json())
      .then(data => {
          if (data.success) {
              // Recarrega a página para atualizar contadores
              location.reload();
          } else {
              alert('Erro ao registrar feedback: ' + (data.message || 'Erro desconhecido'));
          }
      })
      .catch(err => {
          console.error('Erro:', err);
          alert('Erro ao registrar feedback');
      });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

