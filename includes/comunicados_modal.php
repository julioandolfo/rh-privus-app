<?php
/**
 * Modal de Comunicados - Aparece ao logar
 */
?>

<!--begin::Modal - Comunicados-->
<div class="modal fade" id="modal_comunicados" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Comunicados</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body" id="modal_comunicados_body">
                <div class="text-center py-10">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                    <p class="text-muted mt-3">Carregando comunicados...</p>
                </div>
            </div>
        </div>
    </div>
</div>
<!--end::Modal-->

<script>
// Carrega comunicados não lidos ao abrir o modal
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('modal_comunicados');
    const body = document.getElementById('modal_comunicados_body');
    
    if (!modal) return;
    
    // Carrega comunicados quando o modal é aberto
    modal.addEventListener('show.bs.modal', function() {
        carregarComunicados();
    });
    
    function carregarComunicados() {
        body.innerHTML = `
            <div class="text-center py-10">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Carregando...</span>
                </div>
                <p class="text-muted mt-3">Carregando comunicados...</p>
            </div>
        `;
        
        // Detecta caminho base da API
        const basePath = window.location.pathname.includes('/pages/') ? '../api' : 'api';
        
        fetch(basePath + '/comunicados/listar_nao_lidos.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.comunicados && data.comunicados.length > 0) {
                    exibirComunicados(data.comunicados);
                } else {
                    body.innerHTML = `
                        <div class="text-center py-10">
                            <i class="ki-duotone ki-check-circle fs-3x text-success mb-5">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <p class="text-muted fs-5">Não há comunicados novos para você.</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Erro ao carregar comunicados:', error);
                body.innerHTML = `
                    <div class="alert alert-danger">
                        <p class="mb-0">Erro ao carregar comunicados. Tente novamente mais tarde.</p>
                    </div>
                `;
            });
    }
    
    function exibirComunicados(comunicados) {
        let html = '';
        
        comunicados.forEach((comunicado, index) => {
            const isLido = comunicado.lido;
            const podeVerNovamente = comunicado.horas_desde_visualizacao >= 6;
            
            html += `
                <div class="card mb-5 comunicado-item" data-comunicado-id="${comunicado.id}">
                    <div class="card-header border-0 pt-5">
                        <h3 class="card-title">
                            <span class="card-label fw-bold fs-4">${escapeHtml(comunicado.titulo)}</span>
                            ${isLido && podeVerNovamente ? '<span class="badge badge-light-info ms-2">Ver novamente</span>' : ''}
                        </h3>
                        <div class="card-toolbar">
                            <span class="text-muted fs-7">Por ${escapeHtml(comunicado.criado_por_nome)}</span>
                        </div>
                    </div>
                    <div class="card-body pt-5">
                        ${comunicado.imagem ? `
                            <div class="mb-5 text-center">
                                <img src="../${escapeHtml(comunicado.imagem)}" alt="${escapeHtml(comunicado.titulo)}" class="img-fluid rounded" style="max-height: 300px;" />
                            </div>
                        ` : ''}
                        <div class="fs-6 text-gray-700 comunicado-conteudo">
                            ${comunicado.conteudo}
                        </div>
                    </div>
                    <div class="card-footer border-0 pt-0 pb-5">
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                                Fechar
                            </button>
                            ${!isLido ? `
                                <button type="button" class="btn btn-primary btn-marcar-lido" data-comunicado-id="${comunicado.id}">
                                    <i class="ki-duotone ki-check fs-2">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    Marcar como Lido
                                </button>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;
        });
        
        body.innerHTML = html;
        
        // Registra visualização de todos os comunicados
        comunicados.forEach(comunicado => {
            registrarVisualizacao(comunicado.id);
        });
        
        // Adiciona eventos aos botões de marcar como lido
        body.querySelectorAll('.btn-marcar-lido').forEach(btn => {
            btn.addEventListener('click', function() {
                const comunicadoId = parseInt(this.getAttribute('data-comunicado-id'));
                marcarComoLido(comunicadoId, this);
            });
        });
    }
    
    function marcarComoLido(comunicadoId, button) {
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Marcando...';
        
        // Detecta caminho base da API
        const basePath = window.location.pathname.includes('/pages/') ? '../api' : 'api';
        
        fetch(basePath + '/comunicados/marcar_lido.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ comunicado_id: comunicadoId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const card = button.closest('.comunicado-item');
                if (card) {
                    card.style.opacity = '0.6';
                    button.remove();
                    
                    // Adiciona badge de lido
                    const header = card.querySelector('.card-header');
                    if (header) {
                        const badge = document.createElement('span');
                        badge.className = 'badge badge-light-success ms-2';
                        badge.textContent = 'Lido';
                        header.querySelector('.card-title').appendChild(badge);
                    }
                }
            } else {
                alert('Erro ao marcar como lido: ' + (data.message || 'Erro desconhecido'));
                button.disabled = false;
                button.innerHTML = `
                    <i class="ki-duotone ki-check fs-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Marcar como Lido
                `;
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao marcar como lido. Tente novamente.');
            button.disabled = false;
            button.innerHTML = `
                <i class="ki-duotone ki-check fs-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                Marcar como Lido
            `;
        });
    }
    
    function registrarVisualizacao(comunicadoId) {
        // Detecta caminho base da API
        const basePath = window.location.pathname.includes('/pages/') ? '../api' : 'api';
        
        fetch(basePath + '/comunicados/registrar_visualizacao.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ comunicado_id: comunicadoId })
        })
        .catch(error => {
            console.error('Erro ao registrar visualização:', error);
        });
    }
    
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Verifica se há comunicados não lidos ao carregar a página
    const basePath = window.location.pathname.includes('/pages/') ? '../api' : 'api';
    
    fetch(basePath + '/comunicados/listar_nao_lidos.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.comunicados && data.comunicados.length > 0) {
                // Abre o modal automaticamente após um pequeno delay
                setTimeout(() => {
                    const bsModal = new bootstrap.Modal(modal);
                    bsModal.show();
                }, 1000);
            }
        })
        .catch(error => {
            console.error('Erro ao verificar comunicados:', error);
        });
});
</script>

