<?php
/**
 * Gerenciar FAQ - Manual de Conduta
 */

$page_title = 'Gerenciar FAQ';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/manual_conduta_functions.php';

require_page_permission('faq_edit.php');

$pdo = getDB();
$usuario = $_SESSION['usuario'];

// Busca todas as FAQs (ativas e inativas)
$stmt = $pdo->prepare("
    SELECT * FROM faq_manual_conduta
    ORDER BY categoria ASC, ordem ASC, id ASC
");
$stmt->execute();
$faqs = $stmt->fetchAll();

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
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Gerenciar FAQ</h1>
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 pt-1">
                <li class="breadcrumb-item text-muted">
                    <a href="dashboard.php" class="text-muted text-hover-primary">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-muted">
                    <a href="faq_view.php" class="text-muted text-hover-primary">FAQ</a>
                </li>
                <li class="breadcrumb-item">
                    <span class="bullet bg-gray-200 w-5px h-2px"></span>
                </li>
                <li class="breadcrumb-item text-gray-900">Gerenciar</li>
            </ul>
        </div>
        <div class="d-flex align-items-center gap-2 gap-lg-3">
            <a href="faq_view.php" class="btn btn-light">Ver FAQ</a>
            <a href="manual_conduta_estatisticas.php" class="btn btn-light-primary">
                <i class="ki-duotone ki-chart-simple fs-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                </i>
                Estatísticas
            </a>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal_adicionar_faq">
                <i class="ki-duotone ki-plus fs-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                Adicionar FAQ
            </button>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        
        <!--begin::Card - Lista de FAQs-->
        <div class="card">
            <div class="card-header border-0 pt-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold fs-3 mb-1">Perguntas Frequentes</span>
                    <span class="text-muted fw-semibold fs-7">Total: <?= count($faqs) ?> perguntas</span>
                </h3>
            </div>
            <div class="card-body pt-5">
                <?php if (empty($faqs)): ?>
                <div class="text-center py-20">
                    <i class="ki-duotone ki-question fs-3x text-muted mb-5">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    <h3 class="text-gray-800 fw-bold mb-2">Nenhuma pergunta cadastrada</h3>
                    <p class="text-gray-600 mb-5">Clique em "Adicionar FAQ" para começar.</p>
                </div>
                <?php else: ?>
                
                <?php foreach ($faqs_por_categoria as $categoria => $faqs_cat): ?>
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
                    
                    <div class="table-responsive">
                        <table class="table table-row-bordered table-row-dashed gy-7 align-middle">
                            <thead>
                                <tr class="fw-bold fs-6 text-gray-800">
                                    <th style="width: 50px;">Ordem</th>
                                    <th>Pergunta</th>
                                    <th style="width: 150px;">Estatísticas</th>
                                    <th style="width: 100px;">Status</th>
                                    <th style="width: 150px;">Ações</th>
                                </tr>
                            </thead>
                            <tbody id="faq_list_<?= md5($categoria) ?>" data-categoria="<?= htmlspecialchars($categoria) ?>">
                                <?php foreach ($faqs_cat as $faq): ?>
                                <tr data-faq-id="<?= $faq['id'] ?>" class="<?= !$faq['ativo'] ? 'opacity-50' : '' ?>">
                                    <td>
                                        <span class="badge badge-light-primary"><?= $faq['ordem'] ?></span>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-gray-800"><?= htmlspecialchars($faq['pergunta']) ?></div>
                                        <div class="text-muted fs-7"><?= htmlspecialchars(substr($faq['resposta'], 0, 100)) ?>...</div>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column gap-1">
                                            <span class="text-muted fs-7">
                                                <i class="ki-duotone ki-eye fs-6">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                    <span class="path3"></span>
                                                </i>
                                                <?= $faq['visualizacoes'] ?> visualizações
                                            </span>
                                            <span class="text-success fs-7">
                                                <i class="ki-duotone ki-like fs-6">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                                <?= $faq['util_respondeu_sim'] ?> útil
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($faq['ativo']): ?>
                                        <span class="badge badge-light-success">Ativo</span>
                                        <?php else: ?>
                                        <span class="badge badge-light-danger">Inativo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-sm btn-light-primary" 
                                                    onclick="editarFAQ(<?= $faq['id'] ?>)">
                                                <i class="ki-duotone ki-pencil fs-5">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                </i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-light-danger" 
                                                    onclick="deletarFAQ(<?= $faq['id'] ?>)">
                                                <i class="ki-duotone ki-trash fs-5">
                                                    <span class="path1"></span>
                                                    <span class="path2"></span>
                                                    <span class="path3"></span>
                                                    <span class="path4"></span>
                                                    <span class="path5"></span>
                                                </i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <!--end::Categoria-->
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <!--end::Card-->
        
    </div>
</div>
<!--end::Post-->

<!--begin::Modal - Adicionar/Editar FAQ-->
<div class="modal fade" id="modal_adicionar_faq" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold" id="modal_titulo">Adicionar FAQ</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <form id="form_faq">
                <input type="hidden" name="faq_id" id="faq_id" value="" />
                <div class="modal-body">
                    <div class="mb-5">
                        <label class="form-label required">Pergunta</label>
                        <input type="text" name="pergunta" id="pergunta" class="form-control form-control-solid" required />
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label required">Resposta</label>
                        <textarea name="resposta" id="resposta" class="form-control form-control-solid" rows="8" required></textarea>
                    </div>
                    
                    <div class="row mb-5">
                        <div class="col-md-6">
                            <label class="form-label">Categoria</label>
                            <input type="text" name="categoria" id="categoria" class="form-control form-control-solid" 
                                   list="categorias_list" placeholder="Ex: Geral, Regras, Benefícios" />
                            <datalist id="categorias_list">
                                <?php foreach ($categorias as $cat): ?>
                                <option value="<?= htmlspecialchars($cat) ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ordem</label>
                            <input type="number" name="ordem" id="ordem" class="form-control form-control-solid" 
                                   value="0" min="0" />
                        </div>
                    </div>
                    
                    <div class="mb-5">
                        <div class="form-check form-switch form-check-custom form-check-solid">
                            <input class="form-check-input" type="checkbox" name="ativo" id="ativo" value="1" checked />
                            <label class="form-check-label" for="ativo">
                                FAQ ativo (visível para todos)
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-light-info" onclick="previewFAQ()">
                        <i class="ki-duotone ki-eye fs-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        Preview
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <span class="indicator-label">Salvar FAQ</span>
                        <span class="indicator-progress">Salvando...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!--end::Modal-->

<!--begin::Modal - Preview FAQ-->
<div class="modal fade" id="modal_preview_faq" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Preview da FAQ</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body">
                <div class="accordion accordion-icon-toggle" id="kt_accordion_preview">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" 
                                    data-bs-target="#collapse_preview" aria-expanded="true">
                                <span class="fw-bold text-gray-800" id="preview_pergunta"></span>
                            </button>
                        </h2>
                        <div id="collapse_preview" class="accordion-collapse collapse show">
                            <div class="accordion-body">
                                <div class="text-gray-700" id="preview_resposta"></div>
                                <div class="separator separator-dashed my-5"></div>
                                <div class="d-flex align-items-center gap-3">
                                    <span class="text-muted fs-7">Categoria:</span>
                                    <span class="badge badge-light-primary" id="preview_categoria"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>
<!--end::Modal-->

<script>
function previewFAQ() {
    const pergunta = document.getElementById('pergunta').value;
    const resposta = document.getElementById('resposta').value;
    const categoria = document.getElementById('categoria').value || 'Geral';
    
    if (!pergunta || !resposta) {
        alert('Preencha pergunta e resposta para visualizar o preview');
        return;
    }
    
    document.getElementById('preview_pergunta').textContent = pergunta;
    document.getElementById('preview_resposta').innerHTML = resposta.replace(/\n/g, '<br>');
    document.getElementById('preview_categoria').textContent = categoria;
    
    const modal = new bootstrap.Modal(document.getElementById('modal_preview_faq'));
    modal.show();
}
</script>

<script>
let faqEditando = null;

function editarFAQ(faqId) {
    fetch(`api/manual_conduta/get_faq.php?id=${faqId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const faq = data.faq;
                document.getElementById('faq_id').value = faq.id;
                document.getElementById('pergunta').value = faq.pergunta;
                document.getElementById('resposta').value = faq.resposta;
                document.getElementById('categoria').value = faq.categoria || '';
                document.getElementById('ordem').value = faq.ordem;
                document.getElementById('ativo').checked = faq.ativo == 1;
                document.getElementById('modal_titulo').textContent = 'Editar FAQ';
                faqEditando = faqId;
                
                const modal = new bootstrap.Modal(document.getElementById('modal_adicionar_faq'));
                modal.show();
            } else {
                alert('Erro ao carregar FAQ: ' + (data.message || 'Erro desconhecido'));
            }
        })
        .catch(err => {
            console.error('Erro:', err);
            alert('Erro ao carregar FAQ');
        });
}

function deletarFAQ(faqId) {
    if (!confirm('Tem certeza que deseja deletar esta FAQ?')) {
        return;
    }
    
    fetch('api/manual_conduta/deletar_faq.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ faq_id: faqId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Erro ao deletar FAQ: ' + (data.message || 'Erro desconhecido'));
        }
    })
    .catch(err => {
        console.error('Erro:', err);
        alert('Erro ao deletar FAQ');
    });
}

// Resetar formulário quando modal é fechado
document.getElementById('modal_adicionar_faq').addEventListener('hidden.bs.modal', function() {
    document.getElementById('form_faq').reset();
    document.getElementById('faq_id').value = '';
    document.getElementById('modal_titulo').textContent = 'Adicionar FAQ';
    faqEditando = null;
});

// Submeter formulário
document.getElementById('form_faq').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const data = {
        faq_id: formData.get('faq_id') || null,
        pergunta: formData.get('pergunta'),
        resposta: formData.get('resposta'),
        categoria: formData.get('categoria') || null,
        ordem: parseInt(formData.get('ordem')) || 0,
        ativo: formData.get('ativo') === '1' ? 1 : 0
    };
    
    const btn = this.querySelector('button[type="submit"]');
    btn.setAttribute('data-kt-indicator', 'on');
    btn.disabled = true;
    
    fetch('api/manual_conduta/salvar_faq.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;
        
        if (data.success) {
            const modal = bootstrap.Modal.getInstance(document.getElementById('modal_adicionar_faq'));
            modal.hide();
            location.reload();
        } else {
            alert('Erro ao salvar FAQ: ' + (data.message || 'Erro desconhecido'));
        }
    })
    .catch(err => {
        console.error('Erro:', err);
        btn.removeAttribute('data-kt-indicator');
        btn.disabled = false;
        alert('Erro ao salvar FAQ');
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

