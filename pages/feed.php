<?php
/**
 * Feed Privus - Sistema de Feed Social
 */

$page_title = 'Feed Privus';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

require_login();

$pdo = getDB();
$usuario = $_SESSION['usuario'];

require_once __DIR__ . '/../includes/header.php';
?>

<!--begin::Toolbar-->
<div class="toolbar d-flex flex-stack mb-3 mb-lg-5" id="kt_toolbar">
    <div id="kt_toolbar_container" class="container-fluid d-flex flex-stack flex-wrap">
        <div class="page-title d-flex flex-column me-5 py-2">
            <h1 class="d-flex flex-column text-gray-900 fw-bold fs-3 mb-0">Feed Privus</h1>
        </div>
    </div>
</div>
<!--end::Toolbar-->

<!--begin::Post-->
<div class="post fs-6 d-flex flex-column-fluid" id="kt_post">
    <div id="kt_content_container" class="container-xxl">
        <div class="row g-5 g-xl-8">
            <!--begin::Col-->
            <div class="col-xl-12">
                <!--begin::Card - Criar Post-->
                <div class="card card-flush mb-5">
                    <div class="card-body pt-6">
                        <form id="form_post" enctype="multipart/form-data">
                            <div class="mb-5">
                                <label class="form-label">O que você está pensando?</label>
                                <textarea id="feed_conteudo" name="conteudo" class="form-control form-control-solid" rows="5" placeholder="Compartilhe algo com a equipe..." required></textarea>
                            </div>
                            
                            <div class="mb-5">
                                <label class="form-label">Tipo de post</label>
                                <select name="tipo" class="form-select form-select-solid">
                                    <option value="texto">Texto</option>
                                    <option value="celebração">Celebração</option>
                                </select>
                            </div>
                            
                            <div class="mb-5" id="tipo_celebração_group" style="display: none;">
                                <label class="form-label">Tipo de celebração</label>
                                <input type="text" name="tipo_celebração" class="form-control form-control-solid" placeholder="Ex: Aniversário, Promoção, Conquista...">
                            </div>
                            
                            <div class="mb-5">
                                <label class="form-label">Imagem (opcional)</label>
                                <input type="file" name="imagem" class="form-control form-control-solid" accept="image/*">
                            </div>
                            
                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary">
                                    <span class="indicator-label">Publicar</span>
                                    <span class="indicator-progress">Publicando...
                                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                    </span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <!--end::Card-->
                
                <!--begin::Card - Lista de Posts-->
                <div class="card card-flush">
                    <div class="card-header pt-7">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold text-gray-800">Publicações</span>
                        </h3>
                    </div>
                    <div class="card-body pt-6" id="posts_container">
                        <div class="text-center text-muted py-10">
                            <p>Carregando posts...</p>
                        </div>
                    </div>
                </div>
                <!--end::Card-->
            </div>
            <!--end::Col-->
        </div>
    </div>
</div>
<!--end::Post-->

<!--begin::TinyMCE Script-->
<script src="../assets/plugins/custom/tinymce/tinymce.bundle.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inicializa TinyMCE
    function initTinyMCE() {
        // Verifica se TinyMCE está disponível
        if (typeof tinymce === 'undefined') {
            console.warn('TinyMCE não está carregado. Tentando novamente...');
            setTimeout(initTinyMCE, 200);
            return;
        }
        
        // Remove editor existente se houver
        const editorId = 'feed_conteudo';
        if (tinymce.get(editorId)) {
            tinymce.get(editorId).remove();
        }
        
        // Configura base_url e suffix para usar os arquivos diretamente
        const baseUrl = '../assets/plugins/custom/tinymce';
        
        // Inicializa TinyMCE
        tinymce.init({
            selector: '#' + editorId,
            height: 300,
            menubar: false,
            base_url: baseUrl,
            suffix: '.min',
            license_key: 'gpl',
            plugins: [
                'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
                'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                'insertdatetime', 'media', 'table', 'help', 'wordcount', 'emoticons'
            ],
            toolbar: 'undo redo | blocks | ' +
                'bold italic forecolor | alignleft aligncenter ' +
                'alignright alignjustify | bullist numlist outdent indent | ' +
                'link image emoticons | removeformat | help | code',
            content_style: 'body { font-family: Arial, sans-serif; font-size: 14px }',
            language: 'pt_BR',
            promotion: false,
            branding: false,
            skin: 'oxide',
            content_css: baseUrl + '/skins/ui/oxide/content.min.css',
            setup: function(editor) {
                editor.on('change', function() {
                    editor.save();
                });
            }
        });
    }
    
    // Inicializa TinyMCE após carregar
    setTimeout(initTinyMCE, 500);
    
    // Mostrar/ocultar campo de tipo de celebração
    const tipoSelect = document.querySelector('select[name="tipo"]');
    const tipoCelebracaoGroup = document.getElementById('tipo_celebração_group');
    
    if (tipoSelect && tipoCelebracaoGroup) {
        tipoSelect.addEventListener('change', function() {
            if (this.value === 'celebração') {
                tipoCelebracaoGroup.style.display = 'block';
            } else {
                tipoCelebracaoGroup.style.display = 'none';
            }
        });
    }
    
    // Carregar posts
    function carregarPosts() {
        const container = document.getElementById('posts_container');
        if (!container) {
            console.error('Container de posts não encontrado');
            return;
        }
        
        container.innerHTML = '<div class="text-center text-muted py-10"><p>Carregando posts...</p></div>';
        
        fetch('../api/feed/listar.php')
            .then(response => {
                console.log('Resposta da API:', response.status, response.statusText);
                return response.text().then(text => {
                    try {
                        const data = JSON.parse(text);
                        if (!response.ok) {
                            // Se não for OK mas retornou JSON, retorna o JSON com erro
                            return data;
                        }
                        return data;
                    } catch (e) {
                        console.error('Erro ao fazer parse do JSON:', text);
                        throw new Error('Resposta inválida do servidor: ' + text.substring(0, 200));
                    }
                });
            })
            .then(data => {
                console.log('Dados recebidos:', data);
                if (data.success) {
                    renderizarPosts(data.posts || []);
                } else {
                    let errorMsg = data.message || 'Erro desconhecido';
                    if (data.error_code) {
                        errorMsg += ' (Código: ' + data.error_code + ')';
                    }
                    container.innerHTML = '<div class="alert alert-danger"><p><strong>Erro ao carregar posts:</strong> ' + errorMsg + '</p>' + 
                        (data.file ? '<p class="small mb-0">Arquivo: ' + data.file + ' (linha ' + (data.line || 'N/A') + ')</p>' : '') + 
                        '</div>';
                    console.error('Erro na API:', data);
                }
            })
            .catch(error => {
                console.error('Erro ao carregar posts:', error);
                container.innerHTML = '<div class="alert alert-danger"><p>Erro ao carregar posts: ' + error.message + '</p><p class="small">Verifique o console para mais detalhes.</p></div>';
            });
    }
    
    function renderizarPosts(posts) {
        const container = document.getElementById('posts_container');
        if (!container) return;
        
        if (posts.length === 0) {
            container.innerHTML = '<div class="text-center text-muted py-10"><p>Nenhuma publicação ainda. Seja o primeiro a postar!</p></div>';
            return;
        }
        
        container.innerHTML = '';
        
        posts.forEach(function(post) {
            const postDiv = document.createElement('div');
            postDiv.className = 'card card-flush mb-5';
            postDiv.id = 'post-' + post.id;
            
            const dataFormatada = new Date(post.created_at).toLocaleDateString('pt-BR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            
            const autorNome = post.autor_nome || 'Usuário';
            const autorFoto = post.autor_foto ? `../uploads/fotos/${post.autor_foto}` : null;
            const inicial = autorNome.charAt(0).toUpperCase();
            const imagemPost = post.imagem ? `../uploads/feed/${post.imagem}` : null;
            
            postDiv.innerHTML = `
                <div class="card-body pt-6">
                    <div class="d-flex align-items-center mb-5">
                        <div class="symbol symbol-circle symbol-50px me-3">
                            ${autorFoto ? 
                                `<img src="${autorFoto}" alt="${autorNome}" class="symbol-label" onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\\'symbol-label fs-2 fw-bold bg-primary text-white\\'>${inicial}</div>';">` : 
                                `<div class="symbol-label fs-2 fw-bold bg-primary text-white">${inicial}</div>`
                            }
                        </div>
                        <div class="d-flex flex-column">
                            <span class="fw-bold text-gray-800">${autorNome}</span>
                            <span class="text-muted fs-7">${dataFormatada}</span>
                        </div>
                    </div>
                    
                    <div class="mb-5">
                        <div class="text-gray-800 feed-conteudo">${post.conteudo || ''}</div>
                        ${imagemPost ? `<img src="${imagemPost}" class="img-fluid rounded mb-3 mt-3" alt="Imagem do post" onerror="this.style.display='none';">` : ''}
                        ${post.tipo_celebração ? `<span class="badge badge-light-primary mt-2">${post.tipo_celebração}</span>` : ''}
                    </div>
                    
                    <div class="d-flex align-items-center gap-5">
                        <button class="btn btn-sm btn-light ${post.curtiu ? 'btn-active' : ''}" onclick="curtirPost(${post.id}, this)">
                            <i class="ki-duotone ki-heart ${post.curtiu ? 'text-danger' : ''}">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <span class="ms-2">${post.total_curtidas || 0}</span>
                            ${renderizarCurtidasInline(post.curtidas_usuarios || [])}
                        </button>
                        
                        <button class="btn btn-sm btn-light" onclick="toggleComentarios(${post.id})">
                            <i class="ki-duotone ki-message-text">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <span class="ms-2">${post.total_comentarios || 0}</span>
                        </button>
                    </div>
                    
                    <div id="comentarios-${post.id}" class="mt-5" style="display: none;">
                        <div class="mb-3">
                            <form onsubmit="comentarPost(event, ${post.id})">
                                <div class="d-flex gap-2">
                                    <input type="text" class="form-control form-control-solid" placeholder="Escreva um comentário..." required>
                                    <button type="submit" class="btn btn-primary btn-sm">Enviar</button>
                                </div>
                            </form>
                        </div>
                        <div id="lista-comentarios-${post.id}">
                            ${renderizarComentarios(post.comentarios || [])}
                        </div>
                    </div>
                </div>
            `;
            
            container.appendChild(postDiv);
        });
    }
    
    function renderizarCurtidasInline(curtidas) {
        if (!curtidas || curtidas.length === 0) {
            return '';
        }
        
        const avatares = curtidas.slice(0, 5).map(function(curtida) {
            const autorNome = curtida.autor_nome || 'Usuário';
            const autorFoto = curtida.autor_foto ? `../uploads/fotos/${curtida.autor_foto}` : null;
            const inicial = autorNome.charAt(0).toUpperCase();
            
            return `
                <div class="symbol symbol-circle symbol-20px" title="${autorNome}">
                    ${autorFoto ? 
                        `<img src="${autorFoto}" alt="${autorNome}" class="symbol-label" onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\\'symbol-label fs-9 fw-bold bg-light text-primary\\'>${inicial}</div>';">` : 
                        `<div class="symbol-label fs-9 fw-bold bg-light text-primary">${inicial}</div>`
                    }
                </div>
            `;
        }).join('');
        
        return `
            <div class="symbol-group symbol-hover ms-2">
                ${avatares}
            </div>
        `;
    }
    
    function renderizarComentarios(comentarios) {
        if (comentarios.length === 0) {
            return '<p class="text-muted small">Nenhum comentário ainda.</p>';
        }
        
        return comentarios.map(function(comentario) {
            const dataFormatada = new Date(comentario.created_at).toLocaleDateString('pt-BR', {
                day: '2-digit',
                month: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            });
            
            const autorNome = comentario.autor_nome || 'Usuário';
            const autorFoto = comentario.autor_foto ? `../uploads/fotos/${comentario.autor_foto}` : null;
            const inicial = autorNome.charAt(0).toUpperCase();
            
            return `
                <div class="d-flex align-items-start mb-3">
                    <div class="symbol symbol-circle symbol-35px me-3">
                        ${autorFoto ? 
                            `<img src="${autorFoto}" alt="${autorNome}" class="symbol-label" onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\\'symbol-label fs-6 fw-bold bg-light-primary text-primary\\'>${inicial}</div>';">` : 
                            `<div class="symbol-label fs-6 fw-bold bg-light-primary text-primary">${inicial}</div>`
                        }
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center mb-1">
                            <span class="fw-bold text-gray-800 me-2">${autorNome}</span>
                            <span class="text-muted fs-8">${dataFormatada}</span>
                        </div>
                        <p class="text-gray-700 mb-0">${comentario.comentario}</p>
                    </div>
                </div>
            `;
        }).join('');
    }
    
    // Submit do formulário de post
    const formPost = document.getElementById('form_post');
    let isSubmitting = false; // Flag para evitar múltiplos envios
    
    if (formPost) {
        // Remove listeners anteriores para evitar duplicação
        const newFormPost = formPost.cloneNode(true);
        formPost.parentNode.replaceChild(newFormPost, formPost);
        const formPostClean = document.getElementById('form_post');
        
        // Adiciona listener de submit com opção 'once' para garantir execução única
        formPostClean.addEventListener('submit', function(e) {
            e.preventDefault();
            e.stopImmediatePropagation(); // Impede outros listeners
            
            // Previne múltiplos envios
            if (isSubmitting) {
                console.warn('Bloqueado: Já está enviando');
                return false;
            }
            
            // Salva conteúdo do TinyMCE antes de enviar
            if (typeof tinymce !== 'undefined' && tinymce.get('feed_conteudo')) {
                tinymce.get('feed_conteudo').save();
            }
            
            const formData = new FormData(this);
            
            // Adiciona timestamp único para evitar duplicação
            const requestId = Date.now() + '-' + Math.random().toString(36).substr(2, 9);
            formData.append('request_id', requestId);
            
            const btn = this.querySelector('button[type="submit"]');
            const indicator = btn.querySelector('.indicator-label');
            const progress = btn.querySelector('.indicator-progress');
            
            // Marca como enviando IMEDIATAMENTE
            isSubmitting = true;
            btn.setAttribute('data-kt-indicator', 'on');
            btn.disabled = true;
            if (indicator) indicator.style.display = 'none';
            if (progress) progress.style.display = 'inline-block';
            
            console.log('Enviando post... Request ID:', requestId);
            
            fetch('../api/feed/postar.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Resposta recebida:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Dados recebidos:', data);
                if (data.success) {
                    Swal.fire({
                        text: data.message,
                        icon: "success",
                        buttonsStyling: false,
                        confirmButtonText: "Ok",
                        customClass: {
                            confirmButton: "btn btn-primary"
                        }
                    }).then(function() {
                        formPostClean.reset();
                        tipoCelebracaoGroup.style.display = 'none';
                        // Limpa o editor TinyMCE se existir
                        if (typeof tinymce !== 'undefined' && tinymce.get('feed_conteudo')) {
                            tinymce.get('feed_conteudo').setContent('');
                        }
                        carregarPosts();
                    });
                } else {
                    Swal.fire({
                        text: data.message,
                        icon: "error",
                        buttonsStyling: false,
                        confirmButtonText: "Ok",
                        customClass: {
                            confirmButton: "btn btn-primary"
                        }
                    });
                }
            })
            .catch(error => {
                console.error('Erro ao publicar:', error);
                Swal.fire({
                    text: "Erro ao publicar",
                    icon: "error",
                    buttonsStyling: false,
                    confirmButtonText: "Ok",
                    customClass: {
                        confirmButton: "btn btn-primary"
                    }
                });
            })
            .finally(() => {
                // Libera o formulário para novo envio após delay maior
                setTimeout(() => {
                    isSubmitting = false;
                    btn.removeAttribute('data-kt-indicator');
                    btn.disabled = false;
                    if (indicator) indicator.style.display = 'inline-block';
                    if (progress) progress.style.display = 'none';
                }, 2000); // Aumentado para 2 segundos
            });
        });
        
        // Proteção adicional: desabilita o botão imediatamente ao clicar
        const submitBtn = formPostClean.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.addEventListener('click', function(e) {
                if (isSubmitting) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    console.warn('Bloqueado: Botão clicado durante envio');
                    return false;
                }
            }, true); // useCapture = true para executar antes de outros listeners
        }
    }
    
    // Controle de curtidas para evitar cliques duplos
    const curtidasProcessando = new Set();
    
    window.curtirPost = function(postId, btn) {
        // Previne cliques múltiplos no mesmo post
        if (curtidasProcessando.has(postId)) {
            console.warn('Bloqueado: Curtida já está sendo processada');
            return;
        }
        
        // Marca como processando
        curtidasProcessando.add(postId);
        btn.disabled = true;
        
        const formData = new FormData();
        formData.append('post_id', postId);
        
        fetch('../api/feed/curtir.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const span = btn.querySelector('span');
                span.textContent = data.total_curtidas;
                
                if (data.curtido) {
                    btn.classList.add('btn-active');
                    btn.querySelector('i').classList.add('text-danger');
                } else {
                    btn.classList.remove('btn-active');
                    btn.querySelector('i').classList.remove('text-danger');
                }
            } else {
                console.error('Erro ao curtir:', data.message);
            }
        })
        .catch(error => {
            console.error('Erro ao curtir:', error);
        })
        .finally(() => {
            // Libera após delay menor
            setTimeout(() => {
                curtidasProcessando.delete(postId);
                btn.disabled = false;
            }, 300); // Delay bem curto para curtidas
        });
    };
    
    window.toggleComentarios = function(postId) {
        const div = document.getElementById('comentarios-' + postId);
        if (div) {
            div.style.display = div.style.display === 'none' ? 'block' : 'none';
        }
    };
    
    // Controle de envio de comentários para evitar duplicação
    const comentariosEnviando = new Set();
    
    window.comentarPost = function(e, postId) {
        e.preventDefault();
        e.stopImmediatePropagation();
        
        const form = e.target;
        const input = form.querySelector('input');
        const btn = form.querySelector('button[type="submit"]');
        const comentario = input.value.trim();
        
        if (!comentario) return;
        
        // Previne múltiplos envios do mesmo comentário
        const comentarioKey = `${postId}-${comentario}`;
        if (comentariosEnviando.has(comentarioKey)) {
            console.warn('Bloqueado: Comentário já está sendo enviado');
            return;
        }
        
        // Marca como enviando
        comentariosEnviando.add(comentarioKey);
        btn.disabled = true;
        const btnOriginal = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        
        const formData = new FormData();
        formData.append('post_id', postId);
        formData.append('comentario', comentario);
        
        // Adiciona request_id único
        const requestId = Date.now() + '-' + Math.random().toString(36).substr(2, 9);
        formData.append('request_id', requestId);
        
        console.log('Enviando comentário... Request ID:', requestId);
        
        fetch('../api/feed/comentar.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                input.value = '';
                carregarPosts();
            } else {
                Swal.fire({
                    text: data.message || 'Erro ao adicionar comentário',
                    icon: "error",
                    buttonsStyling: false,
                    confirmButtonText: "Ok",
                    customClass: {
                        confirmButton: "btn btn-primary"
                    }
                });
            }
        })
        .catch(error => {
            console.error('Erro ao comentar:', error);
            Swal.fire({
                text: 'Erro ao adicionar comentário',
                icon: "error",
                buttonsStyling: false,
                confirmButtonText: "Ok",
                customClass: {
                    confirmButton: "btn btn-primary"
                }
            });
        })
        .finally(() => {
            // Libera após delay
            setTimeout(() => {
                comentariosEnviando.delete(comentarioKey);
                btn.disabled = false;
                btn.innerHTML = btnOriginal;
            }, 2000);
        });
    };
    
    // Carregar posts ao iniciar
    carregarPosts();
});
</script>

<style>
.feed-conteudo {
    line-height: 1.6;
}

.feed-conteudo p {
    margin-bottom: 0.5rem;
}

.feed-conteudo img {
    max-width: 100%;
    height: auto;
    border-radius: 4px;
}

.feed-conteudo ul,
.feed-conteudo ol {
    padding-left: 1.5rem;
    margin-bottom: 0.5rem;
}

.feed-conteudo a {
    color: #009ef7;
    text-decoration: none;
}

.feed-conteudo a:hover {
    text-decoration: underline;
}

/* Grupo de avatares de curtidas */
.symbol-group {
    display: inline-flex;
    flex-direction: row;
    align-items: center;
}

.symbol-group .symbol {
    margin-left: -10px;
    border: 2px solid #fff;
    transition: all 0.3s ease;
    position: relative;
}

.symbol-group .symbol:first-child {
    margin-left: 0;
}

.symbol-group .symbol:nth-child(1) { z-index: 5; }
.symbol-group .symbol:nth-child(2) { z-index: 4; }
.symbol-group .symbol:nth-child(3) { z-index: 3; }
.symbol-group .symbol:nth-child(4) { z-index: 2; }
.symbol-group .symbol:nth-child(5) { z-index: 1; }

.symbol-group.symbol-hover .symbol:hover {
    z-index: 10 !important;
    transform: scale(1.15);
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
}

.symbol img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

/* Botão de curtir com avatares */
.btn-sm {
    display: inline-flex;
    align-items: center;
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

